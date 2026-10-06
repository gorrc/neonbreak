<?php
/**
 * Plugin Name: Neonbreak AI Workspace
 * Description: Standalone customer workspace for Neonbreak AI tools.
 * Version: 0.1.0
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/advanced-chat.php';
require_once __DIR__ . '/chat-backup.php';
require_once __DIR__ . '/ai-search.php';
function nb_workspace_install() {
    if (!get_page_by_path('workspace')) wp_insert_post(['post_title' => 'AI Workspace', 'post_name' => 'workspace', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '']);
}
register_activation_hook(__FILE__, 'nb_workspace_install');
add_filter('nb_client_panel_url', function ($url) { return home_url('/workspace/?view=chat'); });
add_filter('login_redirect', function ($redirect, $requested, $user) {
    if (!$user instanceof WP_User || !is_string($requested)) return $redirect;
    $target = wp_parse_url($requested);
    // Use the canonical URL; TranslatePress can add /hr/ to home_url().
    $site = wp_parse_url(get_option('home'));
    $base = rtrim($site['path'] ?? '', '/');
    $same_site = ($target['host'] ?? '') === ($site['host'] ?? '') && ($target['scheme'] ?? '') === ($site['scheme'] ?? '') && ($target['port'] ?? null) === ($site['port'] ?? null) && !isset($target['user']) && !isset($target['pass']);
    if ($same_site && preg_match('~^' . preg_quote($base, '~') . '/(?:hr/)?workspace/?$~D', $target['path'] ?? '')) return $requested;
    return $redirect;
}, 99, 3);
add_action('template_redirect', function () {
    if (!is_page('workspace')) return;
    nocache_headers();
    if (isset($_GET['view']) && $_GET['view'] === 'connection') {
        wp_safe_redirect(add_query_arg('view', 'chat', home_url('/workspace/')) . '#nb-connection');
        exit;
    }
    if (!class_exists('Neonbreak_Client_Panel')) wp_die('Activate Neonbreak Chat to use AI Workspace.');
    remove_action('wp_footer', ['Neonbreak_Chat', 'floating']);
    require __DIR__ . '/workspace-template.php';
    exit;
});
add_action('wp_enqueue_scripts', function () {
    if (!is_page('workspace')) return;
    foreach (['neonbreak', 'neonbreak-fonts', 'nb-chat-offer'] as $handle) wp_dequeue_style($handle);
    foreach (['neonbreak', 'neonbreak-language', 'neonbreak-chat'] as $handle) wp_dequeue_script($handle);
    Neonbreak_Client_Panel::assets();
    wp_enqueue_style('nb-workspace', plugins_url('workspace.css', __FILE__), ['nb-client-panel'], '0.2.0');
}, 100);

