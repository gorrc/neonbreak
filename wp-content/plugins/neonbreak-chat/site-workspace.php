<?php
defined('ABSPATH') || exit;

final class NB_Site_Workspace {
    public static function owner() { return absint(get_option('nb_site_workspace_owner', 0)); }
    public static function configured() { return self::owner() > 0; }
    public static function migrate($email, $legacy = null, $key = null) {
        $user = get_user_by('email', $email);
        if (!$user) return new WP_Error('site_chat_user', 'Workspace račun nije pronađen.');
        if (self::configured()) return self::owner() === (int) $user->ID ? (int) $user->ID : new WP_Error('site_chat_bound', 'Stranica je već povezana s drugim računom.');
        $uid = (int) $user->ID;
        $legacy = $legacy ?? Neonbreak_Chat::settings();
        $old_key = get_user_meta($uid, '_nb_chat_key', true);
        $key = $key ?? Neonbreak_Chat::key();
        if (!$key) $key = Neonbreak_Client_Service::decrypt($old_key);
        if (!$key || strlen($key) > 256 || preg_match('/[\r\n]/', $key)) return new WP_Error('site_chat_key', 'Najprije konfigurirajte ispravan Fireworks ključ.');
        $settings = Neonbreak_Client_Panel::defaults();
        foreach (['title', 'model', 'knowledge', 'daily_limit', 'enabled'] as $field) if (isset($legacy[$field])) $settings[$field] = $legacy[$field];
        if (!preg_match('~^accounts/[a-zA-Z0-9_-]+/(models|deployments)/[a-zA-Z0-9._-]+$~', $settings['model'])) return new WP_Error('site_chat_model', 'Model glavnog chata nije konfiguriran.');
        $settings['launcher'] = "Let's talk";
        $settings['welcome'] = "Hi! Tell me what you're building, or ask about our services. We can explore the next step together.";
        $settings['suggestions'] = "What can you build?\nHow can AI help my business?\nHow do we get started?";
        $settings['contact'] = home_url('/contact/');
        $settings['contact_label'] = 'Talk to our team ↗';
        $origins = [Neonbreak_Client_Service::host_origin(), 'https://neonbreak.com', 'https://www.neonbreak.com'];
        $settings['domains'] = implode("\n", array_unique(array_filter($origins)));
        if (wp_parse_url(get_option('home'), PHP_URL_HOST) !== 'localhost') $settings['knowledge'] = str_replace('http://localhost/mobileai/', trailingslashit(get_option('home')), $settings['knowledge']);
        $settings = Neonbreak_Client_Panel::sanitize($settings);
        $encrypted = Neonbreak_Client_Service::encrypt($key);
        $public = get_user_meta($uid, '_nb_chat_public', true);
        if (!is_string($public) || !preg_match('/^[a-f0-9]{32}$/D', $public)) $public = bin2hex(random_bytes(16));
        $backup = ['owner_id' => $uid, 'created_at' => gmdate('c'), 'presentation' => get_user_meta($uid, '_nb_chat_presentation', true), 'encrypted_key' => $old_key, 'public_id' => get_user_meta($uid, '_nb_chat_public', true)];
        $previous = get_option('nb_site_workspace_backup');
        if ($previous && (int) ($previous['owner_id'] ?? 0) !== $uid) return new WP_Error('site_chat_backup', 'Postoji sigurnosna kopija drugog računa; provjerite je prije prijenosa.');
        if (!$previous && !add_option('nb_site_workspace_backup', $backup, '', false)) return new WP_Error('site_chat_backup', 'Sigurnosna kopija nije spremljena.');
        update_user_meta($uid, '_nb_chat_presentation', wp_slash($settings));
        update_user_meta($uid, '_nb_chat_key', $encrypted);
        update_user_meta($uid, '_nb_chat_public', $public);
        $saved = Neonbreak_Client_Service::settings($uid);
        if ($saved['_api_key'] !== $key || get_user_meta($uid, '_nb_chat_presentation', true) !== $settings || get_user_meta($uid, '_nb_chat_public', true) !== $public) return new WP_Error('site_chat_save', 'Spremanje nije dovršeno. Stari chat stranice ostaje uključen.');
        update_option('nb_site_workspace_owner', $uid, false);
        return self::owner() === $uid ? $uid : new WP_Error('site_chat_save', 'Povezivanje vlasnika nije spremljeno.');
    }
    public static function render($floating = true) {
        $uid = self::owner();
        if (!$uid || !get_userdata($uid)) return '';
        $s = Neonbreak_Client_Service::settings($uid);
        $public = get_user_meta($uid, '_nb_chat_public', true);
        if (!$s['enabled'] || !$s['_api_key'] || !$s['model'] || !is_string($public) || !preg_match('/^[a-f0-9]{32}$/D', $public)) return '';
        $src = plugins_url('embed.js', __FILE__) . '?ver=0.7.3';
        $script = '<script src="' . esc_url($src) . '" data-chat="' . esc_attr($public) . '" data-host="' . esc_url(admin_url('admin-ajax.php')) . '"' . ($floating ? '' : ' data-layout="inline"') . ' defer></script>';
        return $floating ? $script : '<div class="nb-managed-chat-demo">' . $script . '</div>';
    }
    public static function settings_page() {
        $uid = self::owner(); $user = $uid ? get_userdata($uid) : null;
        if (isset($_GET['site_chat_result'])) {
            $message = get_transient('nb_site_chat_result_' . get_current_user_id());
            if ($message) echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        }
        echo '<div class="wrap"><h1>Neonbreak · Workspace chat</h1>';
        if ($uid) {
            echo '<p>Chat na stranici sada koristi postavke računa <strong>' . esc_html($user ? $user->user_email : 'Račun je uklonjen') . '</strong>. Izgled, znanje, model i uključivanje uređuju se u AI Workspaceu.</p><p><a class="button button-primary" href="' . esc_url(home_url('/workspace/?view=chat')) . '">Otvori AI Chat</a> <a class="button" href="' . esc_url(home_url('/workspace/?view=history')) . '">Povijest razgovora</a></p><p>Prijavite se kao vlasnik tog računa. Dokumenti i povijest ostaju u njegovu Workspaceu. Stare globalne postavke i poslužiteljski ključ više se ne koriste za javni chat.</p>';
        } else {
            echo '<p>Prebacite postojeći chatbot stranice na Workspace račun. Prijenos kopira sadašnje znanje, model i Fireworks povezivanje, sprema kopiju prethodnih postavki računa te povezuje plutajući widget i demo na stranici. Promjene računa primjenjivat će se na javni chat.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="nb_site_chat_migrate">';
            wp_nonce_field('nb_site_chat_migrate');
            echo '<label>Workspace e-mail <input type="email" name="owner_email" required value="gsambolic@gmail.com" class="regular-text"></label><p>Postojeća konfiguracija chata tog računa zamjenjuje se konfiguracijom Neonbreak chata. Koristite račun bez drugog aktivnog widgeta. Dokumenti i povijest ne brišu se; pregledajte postojeće dokumente prije javnog korištenja.</p>';
            submit_button('Prenesi Neonbreak chat u Workspace');
            echo '</form>';
        }
        echo '</div>';
        return $uid > 0;
    }
}
add_action('admin_post_nb_site_chat_migrate', function () {
    if (!current_user_can('manage_options')) wp_die('Administrator access required.', '', ['response' => 403]);
    check_admin_referer('nb_site_chat_migrate');
    $email = sanitize_email(Neonbreak_Client_Service::input($_POST, 'owner_email'));
    $result = NB_Site_Workspace::migrate($email);
    $message = is_wp_error($result) ? $result->get_error_message() : 'Chat je prenesen u Workspace. Osvježite predmemoriju stranice i pregledajte dokumente te postavke povijesti.';
    set_transient('nb_site_chat_result_' . get_current_user_id(), $message, 300);
    wp_safe_redirect(add_query_arg(['page' => 'neonbreak-chat', 'site_chat_result' => '1'], admin_url('options-general.php'))); exit;
});
