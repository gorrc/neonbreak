<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'DOING_AJAX', true );
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
class NB_Test_End extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', function() { return function() { throw new NB_Test_End(); }; } );
$ids = array(); $calls = array();
add_filter( 'pre_http_request', function( $pre, $args, $url ) use ( &$calls ) {
    if ( $url !== 'https://api.fireworks.ai/inference/v1/chat/completions' ) return $pre;
    $calls[] = $args;
    $body = json_decode( $args['body'], true );
    $answer = isset( $body['response_format'] ) ? '{"allowed":true,"language":"en"}' : 'Tenant answer';
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $answer ) ) ) ) ) );
}, 10, 3 );
function service_check( $condition, $name ) { if ( ! $condition ) throw new RuntimeException( $name ); echo "PASS: $name\n"; }
function service_reply( $post ) {
    $_POST = wp_slash( $post ); ob_start();
    try { Neonbreak_Client_Service::reply(); } catch ( NB_Test_End $e ) {}
    return json_decode( ob_get_clean(), true );
}
try {
    foreach ( array( 'A', 'B' ) as $label ) {
        $uid = wp_insert_user( array( 'user_login' => 'nb_service_test_' . bin2hex( random_bytes( 6 ) ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
        if ( is_wp_error( $uid ) ) throw new RuntimeException( 'Cannot create test account' );
        $ids[] = $uid;
        update_user_meta( $uid, '_nb_chat_presentation', array_merge( Neonbreak_Client_Panel::defaults(), array( 'title' => 'Tenant ' . $label, 'enabled' => 1, 'domains' => 'https://example.test', 'knowledge' => 'Public facts for ' . $label ) ) );
        update_user_meta( $uid, '_nb_chat_public', bin2hex( random_bytes( 16 ) ) );
        update_user_meta( $uid, '_nb_chat_key', Neonbreak_Client_Service::encrypt( 'test-key-' . $label ) );
    }
    $cipher = get_user_meta( $ids[0], '_nb_chat_key', true );
    service_check( strpos( $cipher, 'test-key-A' ) === false && Neonbreak_Client_Service::decrypt( $cipher ) === 'test-key-A', 'key encryption round trip' );
    $raw = base64_decode( $cipher ); $raw[28] = chr( ord( $raw[28] ) ^ 1 );
    service_check( Neonbreak_Client_Service::decrypt( base64_encode( $raw ) ) === '', 'tampered encrypted key rejected' );
    service_check( Neonbreak_Client_Service::origin( 'https://EXAMPLE.test:443/' ) === 'https://example.test' && Neonbreak_Client_Service::origin( 'https://example.test/path' ) === '' && Neonbreak_Client_Service::origin( 'https://user@example.test' ) === '', 'origins validated exactly' );
    $public = get_user_meta( $ids[0], '_nb_chat_public', true ); $expiry = time() + 300;
    $post = array( 'chat' => $public, 'origin' => 'https://example.test', 'expiry' => (string) $expiry, 'token' => Neonbreak_Client_Service::token( $public, 'https://example.test', $expiry ), 'messages' => wp_json_encode( array( array( 'role' => 'user', 'content' => 'What do you offer?' ) ) ) );
    $result = service_reply( $post );
    service_check( $result['success'] === true && count( $calls ) === 2 && $calls[0]['headers']['Authorization'] === 'Bearer test-key-A', 'client A uses its own key for both calls' );
    service_check( strpos( $calls[1]['body'], 'Public facts for A' ) !== false && strpos( $calls[1]['body'], 'Public facts for B' ) === false, 'tenant knowledge isolated' );
    $calls = array(); $bad = $post; $bad['chat'] = get_user_meta( $ids[1], '_nb_chat_public', true );
    service_check( service_reply( $bad )['success'] === false && !$calls, 'token cannot switch clients' );
    $bad = $post; $bad['origin'] = 'https://forbidden.test'; $bad['token'] = Neonbreak_Client_Service::token( $public, $bad['origin'], $expiry );
    service_check( service_reply( $bad )['success'] === false && !$calls, 'unlisted origin rejected' );
    $bad = $post; $bad['expiry'] = (string) ( time() - 1 ); $bad['token'] = Neonbreak_Client_Service::token( $public, $bad['origin'], $bad['expiry'] );
    service_check( service_reply( $bad )['success'] === false && !$calls, 'expired token rejected' );
    wp_set_current_user( $ids[0] ); $html = Neonbreak_Client_Panel::render();
    service_check( strpos( $html, 'test-key-A' ) === false && strpos( $html, $cipher ) === false && strpos( $html, 'data-chat=' ) !== false, 'panel contains embed ID but no stored secret' );
    $s = Neonbreak_Client_Service::settings( $ids[0] ); $s['enabled'] = 0; unset( $s['_api_key'] ); $s['suggestions'] = implode( "\n", $s['suggestions'] ); $s['domains'] = implode( "\n", $s['domains'] );
    update_user_meta( $ids[0], '_nb_chat_presentation', $s ); wp_set_current_user( 0 );
    service_check( service_reply( $post )['success'] === false && !$calls, 'disabled chat rejected' );
} finally {
    wp_set_current_user( 0 );
    global $wpdb;
    foreach ( $ids as $uid ) {
        $scope = 'client:' . $uid;
        $wpdb->delete( $wpdb->options, array( 'option_name' => 'nb_chat_day_' . gmdate( 'Ymd' ) . '_' . md5( $scope ) ) );
        $wpdb->delete( $wpdb->options, array( 'option_name' => 'nb_chat_ip_' . hash_hmac( 'sha256', $scope . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) . gmdate( 'YmdHi' ), wp_salt() ) ) );
        wp_delete_user( $uid );
    }
}
