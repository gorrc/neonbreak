<?php
defined( 'ABSPATH' ) || exit;

final class Neonbreak_Client_Service {
    public static function encrypt( $key ) {
        $iv = random_bytes( 12 );
        $cipher = openssl_encrypt( $key, 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv, $tag );
        if ( false === $cipher ) throw new RuntimeException( 'Cannot encrypt key' );
        return base64_encode( $iv . $tag . $cipher );
    }
    public static function decrypt( $value ) {
        $raw = base64_decode( $value, true );
        if ( false === $raw || strlen( $raw ) < 29 ) return '';
        return openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) ) ?: '';
    }
    public static function origin( $value ) {
        $p = wp_parse_url( trim( $value ) );
        if ( ! is_array( $p ) || ! in_array( $p['scheme'] ?? '', array( 'https', 'http' ), true ) || empty( $p['host'] ) || ! preg_match( '/^[a-z0-9.-]+$/i', $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['query'] ) || isset( $p['fragment'] ) || ! in_array( $p['path'] ?? '', array( '', '/' ), true ) ) return '';
        $port = $p['port'] ?? null;
        return $p['scheme'] . '://' . strtolower( $p['host'] ) . ( $port && !( $p['scheme'] === 'https' && $port === 443 ) && !( $p['scheme'] === 'http' && $port === 80 ) ? ':' . $port : '' );
    }
    public static function host_origin() {
        $p = wp_parse_url( home_url() );
        return self::origin( $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) );
    }
    public static function token( $public, $origin, $expiry ) { return hash_hmac( 'sha256', $public . '|' . $origin . '|' . $expiry, wp_salt( 'nonce' ) ); }
    public static function find( $public ) {
        if ( ! is_string( $public ) || ! preg_match( '/^[a-f0-9]{32}$/', $public ) ) return 0;
        $users = get_users( array( 'meta_key' => '_nb_chat_public', 'meta_value' => $public, 'fields' => 'ID', 'number' => 1 ) );
        return $users ? (int) $users[0] : 0;
    }
    public static function settings( $uid ) {
        $s = wp_parse_args( get_user_meta( $uid, '_nb_chat_presentation', true ) ?: array(), Neonbreak_Client_Panel::defaults() );
        $s['suggestions'] = array_values( array_filter( explode( "\n", $s['suggestions'] ) ) );
        $s['domains'] = array_values( array_filter( array_map( array( __CLASS__, 'origin' ), explode( "\n", $s['domains'] ) ) ) );
        $s['_api_key'] = self::decrypt( get_user_meta( $uid, '_nb_chat_key', true ) );
        return $s;
    }
    public static function input( $source, $field ) { return isset( $source[$field] ) && is_string( $source[$field] ) ? wp_unslash( $source[$field] ) : ''; }
    public static function frame() {
        $public = self::input( $_GET, 'chat' ); $uid = self::find( $public );
        if ( ! $uid ) { status_header( 404 ); exit; }
        $s = self::settings( $uid );
        $preview = isset( $_GET['preview'] ) && is_user_logged_in() && $uid === get_current_user_id();
        $origin = $preview ? self::host_origin() : self::origin( self::input( $_GET, 'origin' ) );
        if ( ! $preview && ( ! $s['enabled'] || ! in_array( $origin, $s['domains'], true ) ) ) { status_header( 403 ); exit; }
        if ( ! $s['_api_key'] ) { status_header( 503 ); exit; }
        nocache_headers(); header_remove( 'X-Frame-Options' );
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( "Content-Security-Policy: frame-ancestors " . $origin . "; object-src 'none'; base-uri 'none'" );
        header( 'X-Content-Type-Options: nosniff' ); header( 'Referrer-Policy: no-referrer' );
        $expiry = time() + 3600;
        $config = array( 'action' => 'nb_client_reply', 'endpoint' => admin_url( 'admin-ajax.php' ), 'parentOrigin' => $preview ? '' : $origin, 'request' => array( 'chat' => $public, 'origin' => $origin, 'expiry' => $expiry, 'token' => self::token( $public, $origin, $expiry ), 'preview' => $preview ? '1' : '' ) );
        echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="' . esc_url( plugins_url( 'chat.css', __FILE__ ) . '?ver=0.5.0' ) . '"><style>html,body{margin:0;background:transparent}.nb-chat{--nb-chat-accent:' . esc_html( $s['accent'] ) . '}.nb-chat-floating{right:8px;bottom:8px;width:calc(100vw - 16px)}.nb-chat-panel{box-shadow:none}.nb-chat-embedded{margin:8px}.nb-chat-messages{height:230px;max-height:230px}.nb-chat-suggestions button,.nb-chat-contact a{color:var(--nb-chat-accent)}</style></head><body>';
        echo Neonbreak_Chat::widget( ! $preview, $s );
        echo '<script>var NeonbreakChat=' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';</script><script src="' . esc_url( plugins_url( 'chat.js', __FILE__ ) . '?ver=0.5.0' ) . '"></script></body></html>'; exit;
    }
    public static function reply() {
        $public = self::input( $_POST, 'chat' ); $uid = self::find( $public );
        $origin = self::origin( self::input( $_POST, 'origin' ) ); $expiry = (int) self::input( $_POST, 'expiry' );
        if ( ! $uid || $expiry < time() || $expiry > time() + 3600 || ! hash_equals( self::token( $public, $origin, $expiry ), self::input( $_POST, 'token' ) ) ) wp_send_json_error( array( 'message' => 'Refresh the chat to continue.' ), 403 );
        $s = self::settings( $uid );
        $preview = self::input( $_POST, 'preview' ) === '1' && is_user_logged_in() && get_current_user_id() === $uid && $origin === self::host_origin();
        if ( ! $preview && ( ! $s['enabled'] || ! in_array( $origin, $s['domains'], true ) ) ) wp_send_json_error( array( 'message' => 'Chat is unavailable on this website.' ), 403 );
        if ( ! $s['_api_key'] ) wp_send_json_error( array( 'message' => 'The chat connection needs attention.' ), 503 );
        Neonbreak_Chat::reply( $s, 'client:' . $uid );
    }
    public static function signup() {
        check_admin_referer( 'nb_client_signup' );
        $rate = 'nb_signup_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '', wp_salt() );
        if ( ! get_transient( $rate ) ) {
            set_transient( $rate, 1, 300 );
            $email = sanitize_email( self::input( $_POST, 'email' ) );
            if ( is_email( $email ) && ! email_exists( $email ) ) {
                $uid = wp_insert_user( array( 'user_login' => 'chat_' . bin2hex( random_bytes( 8 ) ), 'user_pass' => wp_generate_password( 32 ), 'user_email' => $email, 'role' => 'subscriber' ) );
                if ( ! is_wp_error( $uid ) ) wp_new_user_notification( $uid, null, 'user' );
            }
        }
        wp_safe_redirect( add_query_arg( 'access_sent', '1', apply_filters( 'nb_client_panel_url', home_url( '/my-ai-chat/' ) ) ) ); exit;
    }
}
foreach ( array( 'nb_client_frame' => 'frame', 'nb_client_reply' => 'reply' ) as $action => $method ) {
    add_action( 'wp_ajax_' . $action, array( 'Neonbreak_Client_Service', $method ) );
    add_action( 'wp_ajax_nopriv_' . $action, array( 'Neonbreak_Client_Service', $method ) );
}
add_action( 'admin_post_nopriv_nb_client_signup', array( 'Neonbreak_Client_Service', 'signup' ) );

