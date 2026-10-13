<?php
defined('ABSPATH') || exit;
/** Keep customer accounts in the product interface; retain authenticated handlers. */
final class Neonbreak_Customer_Navigation {
    public static function register(): void {
        add_action('admin_init', [self::class, 'redirect'], 1);
        add_filter('show_admin_bar', [self::class, 'admin_bar']);
    }
    public static function is_customer(): bool {
        return is_user_logged_in() && !current_user_can('manage_options') && !current_user_can('edit_posts') && !current_user_can('edit_pages');
    }
    public static function destination(): string {
        if (current_user_can('neonlib_access_dashboard')) {
            $page=(int)get_option('neonlib_users_account_page_id');
            if ($page && get_post_status($page)==='publish') {
                $url=get_permalink($page);
                if (is_string($url) && $url!=='') return $url;
            }
            return home_url('/neonlib-racun/');
        }
        return home_url('/workspace/');
    }
    public static function redirect(): void {
        if (!self::is_customer() || wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) return;
        global $pagenow;
        // Product forms use admin-post.php. Their own nonce/capability checks remain mandatory.
        if (in_array($pagenow, ['admin-post.php','admin-ajax.php'], true)) return;
        wp_safe_redirect(self::destination());
        exit;
    }
    public static function admin_bar(bool $show): bool {
        return self::is_customer() ? false : $show;
    }
}
Neonbreak_Customer_Navigation::register();
