<?php
// CLI-only, repeatable import. Match original text, never depend on database IDs.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 4) . '/wp-load.php';
$source = json_decode(file_get_contents(__DIR__ . '/homepage-translation-source.json'), true, 512, JSON_THROW_ON_ERROR);
$translations = json_decode(file_get_contents(__DIR__ . '/homepage-hr.json'), true, 512, JSON_THROW_ON_ERROR);
$query = TRP_Translate_Press::get_trp_instance()->get_component('query');
$originals = [];
foreach ($source as $row) {
    if (isset($translations[$row['id']])) $originals[$row['original']] = $translations[$row['id']];
}
$existing = $query->get_existing_translations(array_keys($originals), 'hr');
$ids = $query->get_string_ids(array_keys($originals), 'hr');
$updates = [];
foreach ($ids as $row) {
    $row = (array) $row;
    if (!empty($existing[$row['original']]->translated)) continue;
    $updates[] = ['id' => $row['id'], 'translated' => $originals[$row['original']], 'status' => 2];
}
if ($updates && !$query->update_strings($updates, 'hr', ['id', 'translated', 'status'])) {
    fwrite(STDERR, "Translation import failed.\n"); exit(1);
}
echo 'Saved ' . count($updates) . " Croatian translations.\n";
