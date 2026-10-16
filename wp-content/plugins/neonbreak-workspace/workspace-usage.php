<?php
defined('ABSPATH') || exit;
if (!is_user_logged_in()) return;
$usage_uid = get_current_user_id();
$usage_doc_limit = NB_Workspace_Limits::get($usage_uid)['documents'];
$usage_settings = Neonbreak_Client_Service::settings($usage_uid);
$usage_docs = get_user_meta($usage_uid, '_nb_advanced_docs', true) ?: [];
$usage_count = is_array($usage_docs) ? count($usage_docs) : 0;
global $wpdb;
$usage_counter = 'nb_chat_day_' . gmdate('Ymd') . '_' . md5('client:' . $usage_uid);
$usage_requests = max(0, (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $usage_counter)));
$usage_limit = max(1, (int) $usage_settings['daily_limit']);
$usage_active = NB_Advanced_Chat::allowed($usage_uid);
$usage_quote = get_user_meta($usage_uid, '_nb_advanced_quote', true);
?>
<section class="ws-usage" aria-label="<?php echo esc_attr($t('Usage and access', 'Korištenje i pristup')); ?>">
    <div class="ws-usage-grid">
        <div class="ws-card ws-usage-item"><span class="ws-usage-label"><?php echo esc_html($t('Knowledge documents', 'Dokumenti u bazi znanja')); ?></span><strong><?php echo $usage_count; ?><small> / <?php echo $usage_doc_limit; ?></small></strong><p><?php echo esc_html(sprintf($t('%d available slots', '%d slobodnih mjesta'), max(0, $usage_doc_limit - $usage_count))); ?></p><progress max="<?php echo $usage_doc_limit; ?>" value="<?php echo min($usage_doc_limit, $usage_count); ?>" aria-label="<?php echo esc_attr($t('Document capacity', 'Kapacitet dokumenata')); ?>"></progress></div>
        <div class="ws-card ws-usage-item"><span class="ws-usage-label"><?php echo esc_html($t('Widget requests today', 'Današnji zahtjevi widgeta')); ?></span><strong><?php echo $usage_requests; ?><small> / <?php echo $usage_limit; ?></small></strong><p><?php echo esc_html(sprintf($t('%d requests remaining', '%d preostalih zahtjeva'), max(0, $usage_limit - $usage_requests))); ?></p><progress max="<?php echo $usage_limit; ?>" value="<?php echo min($usage_limit, $usage_requests); ?>" aria-label="<?php echo esc_attr($t('Daily request limit', 'Dnevni limit zahtjeva')); ?>"></progress></div>
        <div class="ws-card ws-usage-item"><span class="ws-usage-label">Fireworks</span><strong class="ws-usage-status"><?php echo esc_html($usage_settings['_api_key'] ? $t('Key saved', 'Ključ spremljen') : $t('Setup required', 'Potrebno povezivanje')); ?></strong><p><?php echo esc_html($t('Key status does not verify your balance or connection.', 'Status ključa ne potvrđuje saldo ni ispravnost veze.')); ?></p><a href="<?php echo esc_url($url('chat') . '#nb-connection'); ?>"><?php echo esc_html($t('Connection settings →', 'Postavke povezivanja →')); ?></a></div>
        <div class="ws-card ws-usage-item"><span class="ws-usage-label">AI Advanced Chat</span><strong class="ws-usage-status"><?php echo esc_html($usage_active ? $t('Active', 'Aktivan') : ((is_array($usage_quote) && ($usage_quote['status'] ?? '') === 'pending') ? $t('Request pending', 'Zahtjev u obradi') : $t('Not activated', 'Nije aktiviran'))); ?></strong><p><?php echo esc_html($t('Document search for your existing chat.', 'Pretraživanje dokumenata za vaš postojeći chat.')); ?></p><a href="<?php echo esc_url($url($usage_active ? 'knowledge' : 'advanced')); ?>"><?php echo esc_html($usage_active ? $t('Manage documents →', 'Upravljaj dokumentima →') : $t('View upgrade →', 'Pogledaj nadogradnju →')); ?></a></div>
    </div>
    <p class="ws-usage-note"><?php echo esc_html($t('The widget counter includes failed requests counted toward the limit. It resets at 00:00 UTC and excludes document indexing and the separate knowledge test. This is not a token or cost report.', 'Brojač widgeta uključuje i neuspjele zahtjeve koji se računaju u limit. Resetira se u 00:00 UTC i ne uključuje indeksiranje dokumenata ni zasebni test baze znanja. Ovo nije izvještaj o tokenima ni troškovima.')); ?> <a href="https://app.fireworks.ai/account/usage" target="_blank" rel="noopener noreferrer"><?php echo esc_html($t('View Fireworks usage ↗', 'Pogledaj Fireworks potrošnju ↗')); ?></a></p>
</section>
