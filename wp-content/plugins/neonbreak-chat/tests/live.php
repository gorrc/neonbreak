<?php
// CLI-only end-to-end check. Makes one billed request with configured credentials.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
$_POST = array(
    'nonce' => wp_create_nonce( 'neonbreak_chat' ),
    'messages' => wp_slash( wp_json_encode( array( array( 'role' => 'user', 'content' => $argv[1] ?? 'U dvije kratke rečenice reci koje usluge nudi Neon Break. Odgovori na hrvatskom.' ) ) ) ),
);
$_REQUEST['nonce'] = $_POST['nonce'];
Neonbreak_Chat::reply();
