<?php
// CLI-only integration test. Creates temporary users, mocks external APIs and cleans up.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
function nb_search_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$users = []; $expected_uid = 0; $foreign = false; $external_calls = 0;
$mock = function ($pre, $args, $url) use (&$expected_uid, &$foreign, &$external_calls) {
    $external_calls++;
    $body = json_decode($args['body'] ?? '{}', true);
    if ($url === 'https://api.fireworks.ai/inference/v1/embeddings') return ['response' => ['code' => 200], 'body' => json_encode(['data' => [['index' => 0, 'embedding' => array_fill(0, 4096, 0.1)]]])];
    nb_search_check(strpos($url, 'https://fixture.cloud.qdrant.io/') === 0, 'Unexpected outbound request');
    if (strpos($url, '/index?') !== false) return ['response' => ['code' => 200], 'body' => '{}'];
    nb_search_check($body['filter']['must'][0]['match']['value'] === (string) $expected_uid, 'Tenant filter missing');
    nb_search_check($body['filter']['must'][1]['match']['any'] === ['doc-' . $expected_uid], 'Active document filter missing');
    nb_search_check($body['limit'] >= 1 && $body['limit'] <= 5, 'Result limit missing');
    return ['response' => ['code' => 200], 'body' => json_encode(['result' => ['points' => [['score' => 0.92, 'payload' => ['tenant' => (string) ($foreign ? $expected_uid + 1 : $expected_uid), 'document' => 'doc-' . $expected_uid, 'title' => 'Fixture', 'chunk' => 1, 'text' => 'Business package: 1500 EUR.']]]]])];
};
$fixture_url = fn() => 'https://fixture.cloud.qdrant.io';
$fixture_key = fn() => Neonbreak_Client_Service::encrypt('fixture-qdrant-secret');
add_filter('pre_option_nb_qdrant_url', $fixture_url); add_filter('pre_option_nb_qdrant_key', $fixture_key);
add_filter('pre_http_request', $mock, 10, 3);
try {
    foreach ([1, 2] as $index) {
        $uid = wp_insert_user(['user_login' => 'nb_search_test_' . wp_generate_password(10, false), 'user_pass' => wp_generate_password(24), 'user_email' => 'nb-search-' . wp_generate_password(10, false) . '@example.invalid', 'role' => 'subscriber']);
        nb_search_check(!is_wp_error($uid), 'User fixture failed'); $users[] = $uid;
        update_user_meta($uid, '_nb_advanced_enabled', '1');
        update_user_meta($uid, '_nb_chat_key', Neonbreak_Client_Service::encrypt('fixture-fireworks-secret'));
        update_user_meta($uid, '_nb_advanced_docs', ['doc-' . $uid => ['title' => 'Fixture', 'chunks' => 1, 'source_url' => 'https://example.invalid/price']]);
    }
    [$a, $b] = $users; $expected_uid = $a;
    $key_a = NB_AI_Search::rotate($a); $key_b = NB_AI_Search::rotate($b);
    $server = rest_get_server();
    $send = function ($key, $body, $bearer = false) use ($server) {
        $request = new WP_REST_Request('POST', '/neonbreak/v1/search');
        $request->set_header('Content-Type', 'application/json');
        if ($key) $request->set_header($bearer ? 'Authorization' : 'X-Neonbreak-Search-Key', $bearer ? 'Bearer ' . $key : $key);
        $request->set_body(json_encode($body)); return $server->dispatch($request);
    };
    nb_search_check($send('', ['query' => 'price'])->get_status() === 401, 'Missing key accepted');
    nb_search_check($send(str_replace('nbs_' . $a . '_', 'nbs_' . $b . '_', $key_a), ['query' => 'price'])->get_status() === 401, 'Other user ID accepted');
    $response = $send($key_a, ['query' => 'price', 'limit' => 1]);
    nb_search_check($response->get_status() === 200, 'Search failed');
    $data = $response->get_data();
    nb_search_check($data['results'][0]['document_id'] === 'doc-' . $a && $data['results'][0]['score'] === 0.92, 'Wrong source/score');
    nb_search_check(strpos(json_encode($data), 'secret') === false && strpos(json_encode($data), $key_a) === false, 'Secret leaked');
    nb_search_check($send($key_a, ['query' => 'price'], true)->get_status() === 200, 'Bearer auth failed');
    nb_search_check($send($key_a, ['query' => 'price', 'tenant' => $b])->get_status() === 400, 'Client tenant override accepted');
    nb_search_check($send($key_a, ['query' => 'price', 'limit' => '3'])->get_status() === 400, 'Invalid limit accepted');
    nb_search_check($send($key_a, ['query' => ''])->get_status() === 400, 'Empty query accepted');
    update_user_meta($a, '_nb_advanced_enabled', '0');
    nb_search_check($send($key_a, ['query' => 'price'])->get_status() === 403, 'Disabled access accepted');
    update_user_meta($a, '_nb_advanced_enabled', '1');
    $foreign = true;
    nb_search_check($send($key_a, ['query' => 'price'])->get_status() === 502, 'Foreign tenant response accepted');
    $foreign = false;
    $new_key = NB_AI_Search::rotate($a);
    nb_search_check($send($key_a, ['query' => 'price'])->get_status() === 401, 'Rotated key still accepted');
    nb_search_check($send($new_key, ['query' => 'price'])->get_status() === 200, 'Replacement key rejected');
    update_option(NB_AI_Search::counter_name($a), '100', false);
    nb_search_check($send($new_key, ['query' => 'price'])->get_status() === 429, 'Daily quota not enforced');
    delete_option(NB_AI_Search::counter_name($a));
    update_option('nb_search_min_' . gmdate('YmdHi') . '_' . $a, '10', false);
    nb_search_check($send($new_key, ['query' => 'price'])->get_status() === 429, 'Minute quota not enforced');
    delete_user_meta($a, '_nb_search_key_hash');
    nb_search_check($send($new_key, ['query' => 'price'])->get_status() === 401, 'Revoked key accepted');
    echo 'PASS: REST JSON search, both auth headers, key rotation/revocation, tenant isolation, input validation, daily/minute quotas; all external calls mocked.' . PHP_EOL;
} finally {
    remove_filter('pre_http_request', $mock, 10); remove_filter('pre_option_nb_qdrant_url', $fixture_url); remove_filter('pre_option_nb_qdrant_key', $fixture_key);
    foreach ($users as $uid) {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s) AND option_name LIKE %s", $wpdb->esc_like('nb_search_min_') . '%', $wpdb->esc_like('nb_search_day_') . '%', '%\_' . $uid));
        wp_delete_user($uid);
    }
}
