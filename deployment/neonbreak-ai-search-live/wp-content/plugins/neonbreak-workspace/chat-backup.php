<?php
defined('ABSPATH') || exit;
function nb_chat_backup_data($uid) {
    $settings = wp_parse_args(get_user_meta($uid, '_nb_chat_presentation', true) ?: [], Neonbreak_Client_Panel::defaults());
    return ['type' => 'neonbreak-chat-settings', 'version' => 1, 'exported_at' => gmdate('c'), 'settings' => array_intersect_key($settings, Neonbreak_Client_Panel::defaults())];
}
add_action('admin_post_nb_chat_export', function () {
    if (!is_user_logged_in()) wp_die('Sign in first.', '', ['response' => 403]);
    check_admin_referer('nb_chat_export');
    nocache_headers();
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="neonbreak-chat-settings-' . gmdate('Y-m-d') . '.json"');
    header('X-Content-Type-Options: nosniff');
    echo wp_json_encode(nb_chat_backup_data(get_current_user_id()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});
function nb_chat_backup_render() {
    if (!is_user_logged_in()) return;
    $t = function ($en, $hr) { return nb_advanced_language() === 'hr' ? $hr : $en; };
    ?>
    <section class="ws-card ws-chat-backup"><div class="ws-backup-heading"><h2><?php echo esc_html($t('Back up your chat settings', 'Sigurnosna kopija postavki chata')); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nb_chat_export"><?php wp_nonce_field('nb_chat_export'); ?><button type="submit"><?php echo esc_html($t('Download saved settings', 'Preuzmi spremljene postavke')); ?> <span aria-hidden="true">↓</span></button></form></div><p><?php echo esc_html($t('Export saved branding, messages, basic knowledge and connection settings. API keys, conversations and Advanced documents are excluded. The file contains your business knowledge; store it privately.', 'Izvezite spremljeni izgled, poruke, osnovno znanje i postavke povezivanja. API ključevi, razgovori i Advanced dokumenti nisu uključeni. Datoteka sadrži vaše poslovno znanje; čuvajte je privatno.')); ?></p>
    <form id="nb-chat-import"><label><?php echo esc_html($t('Import a settings file', 'Uvezi datoteku postavki')); ?><input id="nb-chat-import-file" type="file" accept=".json,application/json" required></label><button><?php echo esc_html($t('Load into the form', 'Učitaj u obrazac')); ?></button><p role="status"></p></form><p><?php echo esc_html($t('Import fills the form above without saving. Review the website origins and model, enable the widget if needed, then check connection and save. Your API key and widget ID remain unchanged.', 'Uvoz ispunjava gornji obrazac bez spremanja. Pregledajte dopuštene adrese i model, po potrebi uključite widget pa provjerite vezu i spremite. API ključ i ID widgeta ostaju isti.')); ?></p></section>
    <?php
    wp_enqueue_script('nb-chat-backup', plugins_url('chat-backup.js', __FILE__), [], '1.0', true);
    wp_localize_script('nb-chat-backup', 'NBChatBackup', ['language' => nb_advanced_language(), 'fields' => array_keys(Neonbreak_Client_Panel::defaults())]);
}
