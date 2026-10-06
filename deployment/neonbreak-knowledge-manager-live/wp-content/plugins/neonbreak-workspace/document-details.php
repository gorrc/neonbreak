<?php
defined('ABSPATH') || exit;
function nb_document_metadata($source, $previous = [], $now = null) {
    $now = $now ?? time();
    $format = is_string($source['source_format'] ?? null) ? sanitize_key($source['source_format']) : 'text';
    if (!in_array($format, ['pdf', 'txt', 'md', 'url', 'text'], true)) $format = 'text';
    $url = $format === 'url' && is_string($source['source_url'] ?? null) ? esc_url_raw(wp_unslash($source['source_url'])) : '';
    $parts = wp_parse_url($url);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass'])) $url = '';
    return ['created_at' => $previous ? (int) ($previous['created_at'] ?? 0) : $now, 'updated_at' => $now, 'format' => $format, 'source_url' => $url];
}
function nb_document_details($doc) {
    $t = function ($en, $hr) { return nb_advanced_language() === 'hr' ? $hr : $en; };
    $formats = ['pdf' => 'PDF', 'txt' => 'TXT', 'md' => 'Markdown', 'url' => 'URL', 'text' => $t('Entered text', 'Uneseni tekst')];
    echo '<dl class="nb-document-details"><div><dt>' . esc_html($t('Format', 'Format')) . '</dt><dd>' . esc_html($formats[$doc['format'] ?? ''] ?? $t('Not recorded', 'Nije zabilježeno')) . '</dd></div>';
    foreach (['created_at' => $t('Added', 'Dodano'), 'updated_at' => $t('Updated', 'Ažurirano')] as $field => $label) {
        echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html(!empty($doc[$field]) ? wp_date('d.m.Y. H:i', (int) $doc[$field]) : $t('Not recorded', 'Nije zabilježeno')) . '</dd></div>';
    }
    echo '</dl>';
    $url = $doc['source_url'] ?? '';
    if ($url && in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) echo '<a class="nb-document-origin" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($t('Source page', 'Izvorna stranica')) . ': ' . esc_html($url) . '</a>';
}
