<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$ids = array();
function panel_check( $condition, $name ) { if ( ! $condition ) throw new RuntimeException( $name ); echo "PASS: $name\n"; }
try {
    foreach ( array( 'A', 'B' ) as $label ) {
        $id = wp_insert_user( array( 'user_login' => 'nb_panel_test_' . bin2hex( random_bytes( 6 ) ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
        if ( is_wp_error( $id ) ) throw new RuntimeException( 'Test user creation failed' );
        $ids[] = $id;
        update_user_meta( $id, '_nb_chat_presentation', array( 'title' => 'Isolated customer ' . $label ) );
    }
    wp_set_current_user( $ids[0] ); $first = Neonbreak_Client_Panel::render();
    wp_set_current_user( $ids[1] ); $second = Neonbreak_Client_Panel::render();
    panel_check( strpos( $first, 'Isolated customer A' ) !== false && strpos( $first, 'Isolated customer B' ) === false, 'first account sees only its configuration' );
    panel_check( strpos( $second, 'Isolated customer B' ) !== false && strpos( $second, 'Isolated customer A' ) === false, 'second account sees only its configuration' );
    panel_check( strpos( $first, 'type="password"' ) !== false, 'provider key uses an empty password field' );
    $clean = Neonbreak_Client_Panel::sanitize( array( 'title' => '<script>x</script>Brand', 'accent' => 'red;bad', 'contact' => 'javascript:alert(1)', 'suggestions' => "One\nTwo\nThree\nFour", 'knowledge' => '<b>Public facts</b>' ) );
    panel_check( strpos( $clean['title'], '<' ) === false && $clean['contact'] === '' && $clean['accent'] === '#72f1ff', 'unsafe presentation values rejected' );
    panel_check( count( explode( "\n", $clean['suggestions'] ) ) === 3, 'suggested questions bounded' );
    wp_set_current_user( 0 );
    panel_check( strpos( Neonbreak_Client_Panel::render(), 'nb-client-form' ) === false, 'signed-out users cannot edit configuration' );
} finally {
    wp_set_current_user( 0 );
    foreach ( $ids as $id ) wp_delete_user( $id );
}
