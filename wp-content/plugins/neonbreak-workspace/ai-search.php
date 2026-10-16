<?php
defined('ABSPATH') || exit;
final class NB_AI_Search {
    const DAILY_LIMIT = 100;
    const MINUTE_LIMIT = 10;
    public static function rotate($uid) {
        $secret = bin2hex(random_bytes(32));
        if (!update_user_meta($uid, '_nb_search_key_hash', hash('sha256', $secret))) throw new RuntimeException('Key storage failed.');
        update_user_meta($uid, '_nb_search_key_created', time());
        return 'nbs_' . $uid . '_' . $secret;
    }
    public static function identity($request) {
        $key = $request->get_header('X-Neonbreak-Search-Key');
        if (!$key) $key = preg_replace('/^Bearer\s+/i', '', $request->get_header('Authorization') ?: '');
        if (!is_string($key) || !preg_match('/^nbs_([1-9][0-9]{0,11})_([a-f0-9]{64})$/D', $key, $match)) return new WP_Error('nb_search_auth', 'Invalid or missing AI Search key.', ['status' => 401]);
        $uid = (int) $match[1]; $hash = get_user_meta($uid, '_nb_search_key_hash', true);
        if (!is_string($hash) || !$hash || !hash_equals($hash, hash('sha256', $match[2])) || !get_userdata($uid)) return new WP_Error('nb_search_auth', 'Invalid or missing AI Search key.', ['status' => 401]);
        if (!NB_Advanced_Chat::allowed($uid)) return new WP_Error('nb_search_access', 'Advanced access is not active for this account.', ['status' => 403]);
        if (!is_ssl() && wp_parse_url(get_option('home'), PHP_URL_HOST) !== 'localhost') return new WP_Error('nb_search_https', 'HTTPS is required.', ['status' => 403]);
        return $uid;
    }
    public static function permission($request) {
        $uid = self::identity($request);
        return is_wp_error($uid) ? $uid : true;
    }
    public static function counter_name($uid) { return 'nb_search_day_' . gmdate('Ymd') . '_' . $uid; }
    public static function count($uid) {
        global $wpdb;
        return max(0, (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::counter_name($uid))));
    }
    public static function increment($name) {
        global $wpdb;
        $ok = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1", $name));
        if ($ok === false) throw new RuntimeException('Quota storage failed.');
        return (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
    }
    public static function tracked($uid,$result) { do_action('nb_workspace_operation_result',$uid,'search',is_wp_error($result)?$result->get_error_code():'ok');return $result; }
    public static function search($uid, $query, $limit) {
        if (!is_string($query) || !trim($query) || !mb_check_encoding($query, 'UTF-8') || mb_strlen($query) > 1500 || !is_int($limit) || $limit < 1 || $limit > 5) return self::tracked($uid, new WP_Error('nb_search_input', 'Use a query of 1–1500 characters and an integer limit of 1–5.', ['status' => 400]));
        if (!NB_Advanced_Chat::allowed($uid)) return self::tracked($uid, new WP_Error('nb_search_access', 'Advanced access is not active.', ['status' => 403]));
        if (!Neonbreak_Client_Service::settings($uid)['_api_key']) return self::tracked($uid, new WP_Error('nb_search_setup', 'Save your Fireworks key in AI Chat first.', ['status' => 503]));
        $limits=NB_Workspace_Limits::get($uid);
        try {
            if (self::increment('nb_search_min_' . gmdate('YmdHi') . '_' . $uid) > $limits['search_minute']) return self::tracked($uid, new WP_Error('nb_search_rate', sprintf(nb_url_text('Maximum %d requests per minute.','Najviše %d zahtjeva u minuti.'),$limits['search_minute']), ['status' => 429]));
            $count = self::increment(self::counter_name($uid));
            if ($count > $limits['search_daily']) return self::tracked($uid, new WP_Error('nb_search_quota', 'Daily AI Search limit reached. Resets at 00:00 UTC.', ['status' => 429]));
            $found = NB_Advanced_Chat::retrieve($uid, sanitize_textarea_field($query), $limit);
            $results = array_map(function ($source) { return ['document_id' => $source['document_id'], 'document' => $source['title'], 'chunk' => $source['chunk'], 'text' => $source['text'], 'source_url' => $source['source_url'] ?: null, 'score' => $source['score']]; }, $found['sources']);
            return self::tracked($uid, ['query' => $query, 'results' => $results, 'usage' => ['requests_today' => $count, 'daily_limit' => $limits['search_daily'], 'minute_limit' => $limits['search_minute'], 'remaining' => max(0, $limits['search_daily'] - $count), 'reset_timezone' => 'UTC']]);
        } catch (Throwable $e) { return self::tracked($uid, new WP_Error('nb_search_unavailable', 'Knowledge search is temporarily unavailable. Check your connections or contact support.', ['status' => 502])); }
    }
    public static function api($request) {
        $uid = self::identity($request);
        if (is_wp_error($uid)) return $uid;
        if (strlen($request->get_body()) > 16000) return new WP_Error('nb_search_size', 'Request body too large.', ['status' => 413]);
        $body = $request->get_json_params();
        if (!is_array($body) || array_diff(array_keys($body), ['query', 'limit'])) return new WP_Error('nb_search_input', 'Send a JSON object containing query and optional limit.', ['status' => 400]);
        $result = self::search($uid, $body['query'] ?? null, $body['limit'] ?? 5);
        if (is_wp_error($result)) return $result;
        return new WP_REST_Response($result, 200, ['Cache-Control' => 'no-store, private']);
    }
    public static function preview() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Sign in first.'], 403);
        check_ajax_referer('nb_search_preview');
        $query = isset($_POST['query']) && is_string($_POST['query']) ? wp_unslash($_POST['query']) : null;
        $result = self::search(get_current_user_id(), $query, 5);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()], $result->get_error_data()['status']);
        wp_send_json_success($result);
    }
}
add_action('rest_api_init', function () {
    register_rest_route('neonbreak/v1', '/search', ['methods' => 'POST', 'permission_callback' => ['NB_AI_Search', 'permission'], 'callback' => ['NB_AI_Search', 'api']]);
});
add_action('wp_ajax_nb_search_preview', ['NB_AI_Search', 'preview']);
add_action('admin_post_nb_search_key', function () {
    if (!is_user_logged_in()) wp_die('Sign in first.', '', ['response' => 403]);
    check_admin_referer('nb_search_key');
    $uid = get_current_user_id();
    if (!NB_Advanced_Chat::allowed($uid)) wp_die('Advanced access required.', '', ['response' => 403]);
    $action = $_POST['key_action'] ?? '';
    if ($action === 'revoke') {
        delete_user_meta($uid, '_nb_search_key_hash');
        delete_user_meta($uid, '_nb_search_key_created');
        delete_transient('nb_search_key_once_' . $uid);
    } elseif ($action === 'rotate') {
        try { $key = NB_AI_Search::rotate($uid); set_transient('nb_search_key_once_' . $uid, Neonbreak_Client_Service::encrypt($key), 300); }
        catch (Throwable $e) { wp_die('Key generation failed. Try again.', '', ['response' => 500]); }
    } else wp_die('Invalid action.', '', ['response' => 400]);
    $back = isset($_POST['return_url']) && is_string($_POST['return_url']) ? wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url'])), '') : '';
    $parts = wp_parse_url($back); $site = wp_parse_url(get_option('home'));
    $base = rtrim($site['path'] ?? '', '/');
    if (!$parts || ($parts['host'] ?? '') !== ($site['host'] ?? '') || ($parts['scheme'] ?? '') !== ($site['scheme'] ?? '') || ($parts['port'] ?? null) !== ($site['port'] ?? null) || !preg_match('~^' . preg_quote($base, '~') . '/(?:hr/)?workspace/?$~D', $parts['path'] ?? '')) $back = home_url('/workspace/');
    wp_safe_redirect(add_query_arg('view', 'search', $back)); exit;
});
add_filter('rest_post_dispatch', function ($response, $server, $request) {
    if ($request->get_route() === '/neonbreak/v1/search') $response->header('Cache-Control', 'no-store, private');
    return $response;
}, 10, 3);
add_action('neonbreak_chat_cleanup', function () {
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE (option_name LIKE %s AND option_name NOT LIKE %s) OR (option_name LIKE %s AND option_name NOT LIKE %s)", $wpdb->esc_like('nb_search_min_') . '%', $wpdb->esc_like('nb_search_min_' . gmdate('YmdHi')) . '%', $wpdb->esc_like('nb_search_day_') . '%', $wpdb->esc_like('nb_search_day_' . gmdate('Ymd')) . '%'));
});
