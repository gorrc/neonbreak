<?php
// CLI-only import of reviewed translations; preserve existing editor changes.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 4) . '/wp-load.php';
$translations = json_decode(file_get_contents(__DIR__ . '/contact-hr.json'), true, 512, JSON_THROW_ON_ERROR);
$query = TRP_Translate_Press::get_trp_instance()->get_component('query');
$existing = $query->get_existing_translations(array_keys($translations), 'hr');
$ids = $query->get_string_ids(array_keys($translations), 'hr');
if (count($ids) !== count($translations)) {
    fwrite(STDERR, "Visit /hr/contact/ first to register all source strings.\n"); exit(1);
}
$updates = [];
foreach ($ids as $row) {
    if (!empty($existing[$row->original]->translated)) continue;
    $updates[] = ['id' => $row->id, 'translated' => $translations[$row->original], 'status' => 2];
}
if ($updates && !$query->update_strings($updates, 'hr', ['id', 'translated', 'status'])) {
    fwrite(STDERR, "Translation import failed.\n"); exit(1);
}
echo 'Saved ' . count($updates) . " Croatian translations.\n";

