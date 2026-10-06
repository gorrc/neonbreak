<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 4) . '/wp-load.php';
$translations = json_decode(file_get_contents(__DIR__ . '/my-ai-chat-hr.json'), true, 512, JSON_THROW_ON_ERROR);
$query = TRP_Translate_Press::get_trp_instance()->get_component('query');
$ids = $query->get_string_ids(array_keys($translations), 'hr');
$missing = array_diff(array_keys($translations), array_keys($ids));
if ($missing) $query->insert_strings(array_values($missing), 'hr');
$ids = $query->get_string_ids(array_keys($translations), 'hr');
$existing = $query->get_existing_translations(array_keys($translations), 'hr');
$updates = [];
foreach ($ids as $row) {
    if (!empty($existing[$row->original]->translated)) continue;
    $updates[] = ['id' => $row->id, 'translated' => $translations[$row->original], 'status' => 2];
}
if ($updates && !$query->update_strings($updates, 'hr', ['id', 'translated', 'status'])) exit(1);
echo 'Saved ' . count($updates) . " Croatian translations.\n";
