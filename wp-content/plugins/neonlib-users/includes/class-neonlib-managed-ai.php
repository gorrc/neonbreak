<?php
defined('ABSPATH') || exit;
final class NeonLib_Managed_AI {
    public static function register(): void {
        add_action('template_redirect', [self::class, 'page'], 1);
        add_filter('login_redirect', [self::class, 'login_redirect'], 20, 3);
    }
    public static function login_redirect(string $redirect, string $requested, $user): string {
        $home=wp_parse_url(home_url('/')); $target=wp_parse_url($requested);
        if (!$user instanceof WP_User || !is_array($target) || isset($target['user']) || isset($target['pass'])) return $redirect;
        foreach (['host','scheme','port','path'] as $key) if (($home[$key]??null)!==($target[$key]??null)) return $redirect;
        parse_str($target['query']??'', $query);
        return is_string($query['neonlib_ai']??null) && preg_match('/^[a-f0-9]{32}$/',$query['neonlib_ai']) ? add_query_arg('neonlib_ai',$query['neonlib_ai'],home_url('/')) : $redirect;
    }
    public static function page(): void {
        if (!isset($_GET['neonlib_ai'])) return;
        $id=wp_unslash($_GET['neonlib_ai']);
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/',$id)) { status_header(400); wp_die('Invalid connection.'); }
        nocache_headers(); header('Referrer-Policy: no-referrer');
        $url=add_query_arg('neonlib_ai',$id,home_url('/'));
        if (!is_user_logged_in()) {
            get_header();
            $page=(int)get_option('neonlib_users_account_page_id');
            $account=$page ? get_permalink($page) : home_url('/neonlib/');
            echo '<main style="max-width:650px;margin:50px auto;padding:24px"><h1>NeonLib AI</h1><p>Sign in to connect your phone. If you do not have a NeonLib account, create one and verify your email first, then return to the app and connect again.</p><p><a class="button" href="'.esc_url(wp_login_url($url)).'">Sign in</a></p><p><a href="'.esc_url($account).'">Create a NeonLib account</a></p></main>';
            get_footer(); exit;
        }
        $user=wp_get_current_user();
        $hr=str_starts_with(get_locale(),'hr') || ($_GET['lang']??'')==='hr';
        $text=static fn($en,$cro)=>$hr?$cro:$en;
        if (!$user->has_cap('manage_options') && get_user_meta($user->ID,'neonlib_email_verified',true)!=='1') wp_die(esc_html($text('Verify your NeonLib account email, then reopen the connection from the app.','Potvrdite e-mail svog NeonLib raÄŤuna, pa ponovno otvorite povezivanje iz aplikacije.')));
        $client=new NeonLib_Api_Client();
        $linked=$client->get_account_link($user->ID);
        if (is_wp_error($linked) && $linked->get_error_code()==='account_link_not_found') $linked=$client->link_account($user->ID);
        if (is_wp_error($linked)) wp_die(esc_html($linked->get_error_message()));
        $info=$client->managed_ai_device($user->ID,$id);
        if (is_wp_error($info)) wp_die(esc_html($info->get_error_message()));
        $connected=false;
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            check_admin_referer('neonlib_ai_'.$id);
            $code=isset($_POST['device_code']) && is_string($_POST['device_code']) ? wp_unslash($_POST['device_code']) : '';
            $result=$client->managed_ai_device($user->ID,$id,$code);
            if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
            $connected=true;
        }
        get_header();
        echo '<main style="max-width:650px;margin:50px auto;padding:24px"><h1>NeonLib AI</h1>';
        if ($connected) {
            echo '<p>'.esc_html($text('Connected. Return to NeonLib on your phone.','Povezano. Vratite se u NeonLib na mobitelu.')).'</p>';
        } else {
            echo '<p>'.esc_html($text('Connect this phone to your account only if the code below matches the code shown in NeonLib.','PoveĹľite ovaj mobitel sa svojim raÄŤunom samo ako se kod ispod podudara s kodom prikazanim u NeonLibu.')).'</p>';
            echo '<p style="font-size:32px;letter-spacing:5px;font-weight:bold">'.esc_html($info['code']??'').'</p>';
            echo '<p>'.esc_html(sprintf($text('Up to %d requests per day, shared across your devices. Questions, chat history and selected passages are sent through NeonLib to Groq to generate answers.','Do %d zahtjeva dnevno, zajedniÄŤki za vaĹˇe ureÄ‘aje. Pitanja, povijest razgovora i odabrani odlomci Ĺˇalju se preko NeonLiba Groqu za izradu odgovora.'),(int)($info['dailyLimit']??0))).'</p>';
            echo '<form method="post" action="'.esc_url($url).'">';
            wp_nonce_field('neonlib_ai_'.$id);
            echo '<input type="hidden" name="device_code" value="'.esc_attr($info['code']??'').'"><button type="submit" class="button">'.esc_html($text('Connect this phone','PoveĹľi ovaj mobitel')).'</button></form>';
        }
        echo '</main>'; get_footer(); exit;
    }
}
