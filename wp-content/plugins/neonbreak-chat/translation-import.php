<?php
defined('ABSPATH') || exit;
add_action('admin_menu', function () {
    add_management_page('Neonbreak prijevodi', 'Neonbreak prijevodi', 'manage_options', 'neonbreak-translations', function () {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>Neonbreak prijevodi</h1><p>Uključuje engleski i hrvatski jezik te uvozi pripremljene prijevode. Postojeći ručno uređeni hrvatski prijevodi ostaju sačuvani. Prije uvoza napravite sigurnosnu kopiju baze.</p>';
        if (!class_exists('TRP_Translate_Press')) { echo '<p>Najprije instalirajte i aktivirajte TranslatePress – Multilingual.</p></div>'; return; }
        if (isset($_GET['imported'])) echo '<div class="notice notice-success"><p>Prijevodi su uvezeni.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="nb_import_translations">';
        wp_nonce_field('nb_import_translations');
        submit_button('Uvezi hrvatske prijevode');
        echo '</form></div>';
    });
});
add_action('admin_post_nb_import_translations', function () {
    if (!current_user_can('manage_options')) wp_die('Nedopušten pristup.', '', ['response' => 403]);
    check_admin_referer('nb_import_translations');
    if (!class_exists('TRP_Translate_Press')) wp_die('Aktivirajte TranslatePress.');
    $trp = TRP_Translate_Press::get_trp_instance();
    $settings_component = $trp->get_component('settings');
    $settings = $settings_component->get_settings();
    if (($settings['default-language'] ?? '') !== 'en_US') wp_die('Zadani jezik u TranslatePressu mora biti English (United States).');
    if (array_diff($settings['translation-languages'] ?? [], ['en_US', 'hr'])) wp_die('Postoje dodatni jezici. Uskladite ih prije uvoza.');
    add_option('nb_translation_settings_before_import', $settings, '', false);
    $settings['translation-languages'] = ['en_US', 'hr'];
    $settings['publish-languages'] = ['en_US', 'hr'];
    $settings['url-slugs'] = ['en_US' => 'en', 'hr' => 'hr'];
    $settings['add-subdirectory-to-default-language'] = 'no';
    $settings['trp-ls-floater'] = 'yes';
    update_option('trp_settings', $settings_component->sanitize_settings($settings));
    $machine = get_option('trp_machine_translation_settings', []);
    $machine['machine-translation'] = 'no';
    update_option('trp_machine_translation_settings', $machine);
    // Fresh query components must see the newly saved language settings.
    $query = new TRP_Query(get_option('trp_settings'));
    $translations = json_decode(file_get_contents(__DIR__ . '/tools/live-translations-hr.json'), true, 512, JSON_THROW_ON_ERROR);
    $ids = $query->get_string_ids(array_keys($translations), 'hr');
    $missing = array_diff(array_keys($translations), array_keys($ids));
    if ($missing) $query->insert_strings(array_values($missing), 'hr');
    $existing = $query->get_existing_translations(array_keys($translations), 'hr');
    $updates = [];
    foreach ($query->get_string_ids(array_keys($translations), 'hr') as $row) {
        if (!empty($existing[$row->original]->translated)) continue;
        $updates[] = ['id' => $row->id, 'translated' => $translations[$row->original], 'status' => 2];
    }
    if ($updates && !$query->update_strings($updates, 'hr', ['id', 'translated', 'status'])) wp_die('Uvoz nije uspio.');
    $account = json_decode(file_get_contents(__DIR__ . '/tools/neonlib-account-hr.json'), true, 512, JSON_THROW_ON_ERROR);
    $writer = $query->get_query_component('gettext_insert_update');
    $known = []; $updates = []; $new = [];
    foreach ($query->get_gettext_string_rows_by_original(array_keys($account), 'hr') as $row) {
        $row = (object) $row;
        if ($row->domain !== 'neonlib-users') continue;
        $known[$row->original] = true;
        if ((int) $row->status === 2 && !empty($row->translated)) continue;
        $updates[] = ['id' => $row->id, 'translated' => $account[$row->original], 'status' => 2];
    }
    foreach ($account as $original => $translated) {
        if (!isset($known[$original])) $new[] = ['original' => $original, 'original_plural' => '', 'translated' => $translated, 'domain' => 'neonlib-users', 'plural_form' => 0, 'context' => null, 'status' => 2];
    }
    if ($new) $writer->insert_gettext_strings($new, 'hr');
    if ($updates) $writer->update_gettext_strings($updates, 'hr', ['id', 'translated', 'status']);
    flush_rewrite_rules();
    wp_safe_redirect(admin_url('tools.php?page=neonbreak-translations&imported=1')); exit;
});
