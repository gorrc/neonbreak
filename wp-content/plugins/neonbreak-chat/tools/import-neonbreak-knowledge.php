<?php
// Explicit CLI import for the Neonbreak installation. Not run on client activation.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
$settings = Neonbreak_Chat::settings();
add_option( 'neonbreak_chat_knowledge_before_import', $settings['knowledge'], '', false );
$text = file_get_contents( dirname( __DIR__ ) . '/knowledge/neonbreak.txt' );
if ( false === $text ) { fwrite( STDERR, "Knowledge file missing\n" ); exit( 1 ); }
$settings['knowledge'] = str_replace( '{site}', untrailingslashit( home_url() ), $text );
update_option( 'neonbreak_chat', $settings );
echo "Neonbreak knowledge imported; previous context retained as backup.\n";
