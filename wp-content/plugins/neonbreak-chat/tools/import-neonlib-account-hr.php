<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 4) . '/wp-load.php';
$translations = json_decode(file_get_contents(__DIR__ . '/neonlib-account-hr.json'), true, 512, JSON_THROW_ON_ERROR);
$query = TRP_Translate_Press::get_trp_instance()->get_component('query');
$writer = $query->get_query_component('gettext_insert_update');
$rows = $query->get_gettext_string_rows_by_original(array_keys($translations), 'hr');
$known = [];
$updates = [];
foreach ($rows as $row) {
    $row = (object) $row;
    if ($row->domain !== 'neonlib-users') continue;
    $known[$row->original] = true;
    if ((int) $row->status === 2 && !empty($row->translated)) continue;
    $updates[] = ['id' => $row->id, 'translated' => $translations[$row->original], 'status' => 2];
}
$new = [];
foreach ($translations as $original => $translated) {
    if (isset($known[$original])) continue;
    $new[] = ['original' => $original, 'original_plural' => '', 'translated' => $translated, 'domain' => 'neonlib-users', 'plural_form' => 0, 'context' => null, 'status' => 2];
}
if ($new) $writer->insert_gettext_strings($new, 'hr');
if ($updates) $writer->update_gettext_strings($updates, 'hr', ['id', 'translated', 'status']);
echo 'Imported ' . (count($new) + count($updates)) . " account translations.\n";
