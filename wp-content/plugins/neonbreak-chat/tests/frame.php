<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$uid = wp_insert_user( array( 'user_login' => 'nb_frame_test_' . bin2hex( random_bytes( 6 ) ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
if ( is_wp_error( $uid ) ) throw new RuntimeException( 'Cannot create test account' );
function frame_check( $condition, $name ) { if ( ! $condition ) throw new RuntimeException( $name ); echo "PASS: $name\n"; }
try {
    $public = bin2hex( random_bytes( 16 ) );
    update_user_meta( $uid, '_nb_chat_public', $public );
    update_user_meta( $uid, '_nb_chat_key', Neonbreak_Client_Service::encrypt( 'frame-test-secret' ) );
    $s = array_merge( Neonbreak_Client_Panel::defaults(), array( 'title' => 'Frame test brand', 'welcome' => 'Frame welcome', 'knowledge' => 'PRIVATE TEST CONTEXT', 'domains' => 'https://example.test', 'enabled' => 1 ) );
    update_user_meta( $uid, '_nb_chat_presentation', $s );
    $url = add_query_arg( array( 'action' => 'nb_client_frame', 'chat' => $public, 'origin' => 'https://example.test' ), admin_url( 'admin-ajax.php' ) );
    $r = wp_remote_get( $url ); $html = wp_remote_retrieve_body( $r );
    frame_check( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) === 200 && strpos( $html, 'Frame test brand' ) !== false, 'public iframe loads client branding' );
    frame_check( strpos( wp_remote_retrieve_header( $r, 'content-security-policy' ), 'frame-ancestors https://example.test;' ) !== false, 'browser embedding restricted to configured origin' );
    frame_check( strpos( $html, 'frame-test-secret' ) === false && strpos( $html, 'PRIVATE TEST CONTEXT' ) === false, 'iframe exposes neither key nor knowledge' );
    $s['welcome'] = 'Updated without replacing code'; update_user_meta( $uid, '_nb_chat_presentation', $s );
    frame_check( strpos( wp_remote_retrieve_body( wp_remote_get( $url ) ), $s['welcome'] ) !== false, 'saved changes load using same embed ID' );
    $bad = add_query_arg( 'origin', 'https://forbidden.test', $url );
    frame_check( wp_remote_retrieve_response_code( wp_remote_get( $bad ) ) === 403, 'unlisted iframe origin rejected' );
    $s['enabled'] = 0; update_user_meta( $uid, '_nb_chat_presentation', $s );
    frame_check( wp_remote_retrieve_response_code( wp_remote_get( $url ) ) === 403, 'disabled iframe rejected' );
    frame_check( Neonbreak_Client_Service::host_origin() === 'http://localhost', 'subdirectory install has correct host origin' );
} finally { wp_delete_user( $uid ); }
