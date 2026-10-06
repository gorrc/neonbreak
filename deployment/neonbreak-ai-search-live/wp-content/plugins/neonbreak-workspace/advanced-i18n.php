<?php
defined('ABSPATH') || exit;
function nb_advanced_language() {
    global $TRP_LANGUAGE;
    if (wp_doing_ajax()) return ($_POST['language'] ?? '') === 'hr' ? 'hr' : 'en';
    return ($TRP_LANGUAGE ?? '') === 'hr' ? 'hr' : 'en';
}
function nb_advanced_translations() {
    static $strings;
    if ($strings === null) $strings = json_decode(file_get_contents(__DIR__ . '/advanced-en.json'), true);
    return $strings ?: [];
}
function nb_advanced_text($text) {
    return nb_advanced_language() === 'hr' ? $text : strtr($text, nb_advanced_translations());
}
