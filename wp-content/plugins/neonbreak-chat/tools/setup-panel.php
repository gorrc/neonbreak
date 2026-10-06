<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! get_page_by_path( 'my-ai-chat' ) ) {
    $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'My AI chat', 'post_name' => 'my-ai-chat', 'post_content' => '[neonbreak_chat_panel]' ), true );
    if ( is_wp_error( $id ) ) { fwrite( STDERR, "Could not create panel page.\n" ); exit( 1 ); }
}
echo home_url( '/my-ai-chat/' ) . "\n";
