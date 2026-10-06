<?php
// Local bilingual trial; does not call a translation provider.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result = activate_plugin( 'translatepress-multilingual/index.php' );
if ( is_wp_error( $result ) ) { fwrite( STDERR, $result->get_error_message() ); exit( 1 ); }
$trp = TRP_Translate_Press::get_trp_instance();
$component = $trp->get_component( 'settings' );
$settings = $component->get_settings();
add_option( 'nb_translation_settings_before_trial', $settings, '', false );
$settings['default-language'] = 'en_US';
$settings['translation-languages'] = array( 'en_US', 'hr' );
$settings['publish-languages'] = array( 'en_US', 'hr' );
$settings['url-slugs'] = array( 'en_US' => 'en', 'hr' => 'hr' );
$settings['add-subdirectory-to-default-language'] = 'no';
$settings['floater-position'] = 'bottom-left';
$settings['trp-ls-floater'] = 'yes';
update_option( 'trp_settings', $component->sanitize_settings( $settings ) );
$machine = get_option( 'trp_machine_translation_settings', array() );
$machine['machine-translation'] = 'no';
update_option( 'trp_machine_translation_settings', $machine );
flush_rewrite_rules();
echo "TranslatePress enabled: English + Croatian. Automatic translation disabled.\n";
