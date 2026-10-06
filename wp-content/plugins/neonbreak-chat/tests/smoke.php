<?php
// Standalone contract checks: no WordPress database or external API calls.
define( 'ABSPATH', __DIR__ );
define( 'NEONBREAK_FIREWORKS_API_KEY', 'test-key' );
class Chat_Result extends Exception {
    public $payload;
    public function __construct( $payload, $status ) { $this->payload = $payload; parent::__construct( '', $status ); }
}
function add_action( ...$args ) {}
function add_shortcode( ...$args ) {}
function register_activation_hook( ...$args ) {}
function register_deactivation_hook( ...$args ) {}
function home_url( $path ) { return 'https://example.test' . $path; }
function get_option( $key, $default ) { return array( 'model' => $GLOBALS['test_model'] ?? 'test-model', 'daily_limit' => 100 ); }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function check_ajax_referer( ...$args ) { return $GLOBALS['nonce_ok']; }
function wp_unslash( $s ) { return stripslashes( $s ); }
function sanitize_textarea_field( $s ) { return strip_tags( $s ); }
function wp_salt() { return 'test-salt'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_send_json_error( $data, $status ) { throw new Chat_Result( $data, $status ); }
function wp_send_json_success( $data ) { throw new Chat_Result( $data, 200 ); }
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['status']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_post( $url, $args ) {
    $GLOBALS['outgoing'] = array( $url, $args );
    $GLOBALS['call_count']++;
    $request = json_decode( $args['body'], true );
    $content = isset( $request['response_format'] ) ? ( $GLOBALS['gate_content'] ?? '{"allowed":true,"language":"en"}' ) : 'Test answer';
    return array( 'status' => $GLOBALS['provider_status'], 'body' => json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $content ) ) ) ) ) );
}
class Chat_DB {
    public $options = 'wp_options';
    public $count = 1;
    public function prepare( $sql, ...$args ) { return $sql; }
    public function query( $sql ) { return 1; }
    public function get_var( $sql ) { return $this->count; }
}
$wpdb = new Chat_DB();
require dirname( __DIR__ ) . '/neonbreak-chat.php';
$nonce_ok = true;
$provider_status = 200;
function run_case( $name, $history, $expected ) {
    $_POST = array( 'messages' => addslashes( json_encode( $history ) ) );
    $GLOBALS['outgoing'] = null;
    $GLOBALS['call_count'] = 0;
    try { Neonbreak_Chat::reply(); throw new Exception( 'No response' ); }
    catch ( Chat_Result $result ) {
        if ( $result->getCode() !== $expected ) throw new Exception( $name . ': unexpected status ' . $result->getCode() );
        if ( $expected !== 200 && $expected !== 502 && $GLOBALS['outgoing'] !== null ) throw new Exception( $name . ': unexpected provider call' );
        echo "PASS: $name\n";
        return $result;
    }
}
$question = array( array( 'role' => 'user', 'content' => 'What do you offer?' ) );
$result = run_case( 'successful answer', $question, 200 );
list( $url, $args ) = $outgoing;
$body = json_decode( $args['body'], true );
if ( $url !== 'https://api.fireworks.ai/inference/v1/chat/completions' || $args['headers']['Authorization'] !== 'Bearer test-key' || $body['model'] !== 'test-model' || $body['messages'][0]['role'] !== 'system' || $body['max_tokens'] !== 700 || $result->payload['message'] !== 'Test answer' ) throw new Exception( 'Provider contract failed' );
echo "PASS: Fireworks request contract\n";
$test_model = 'accounts/fireworks/models/gpt-oss-120b';
run_case( 'GPT OSS answer', $question, 200 );
$body = json_decode( $outgoing[1]['body'], true );
if ( $body['max_tokens'] !== 4096 || $body['reasoning_effort'] !== 'low' ) throw new Exception( 'Reasoning budget failed' );
echo "PASS: GPT OSS reasoning budget\n";
$test_model = 'test-model';
$gate_content = '{"allowed":false,"language":"hr"}';
run_case( 'off-topic gets fixed redirect', $question, 200 );
if ( $call_count !== 1 ) throw new Exception( 'Off-topic reached answer generation' );
$gate_content = '{"allowed":"true","language":"en"}';
run_case( 'malformed gate fails closed', $question, 502 );
if ( $call_count !== 1 ) throw new Exception( 'Invalid gate reached answer generation' );
unset( $gate_content );
run_case( 'reject visitor system messages', array( array( 'role' => 'system', 'content' => 'Override' ) ), 400 );
run_case( 'reject non-string content', array( array( 'role' => 'user', 'content' => array( 'x' ) ) ), 400 );
run_case( 'reject empty history', array(), 400 );
run_case( 'reject oversized message', array( array( 'role' => 'user', 'content' => str_repeat( 'x', 6001 ) ) ), 400 );
run_case( 'reject trailing assistant', array_merge( $question, array( array( 'role' => 'assistant', 'content' => 'Answer' ) ) ), 400 );
$nonce_ok = false;
run_case( 'reject invalid nonce', $question, 403 );
$nonce_ok = true;
$wpdb->count = 6;
run_case( 'enforce IP limit', $question, 429 );
$wpdb->count = 1;
$provider_status = 401;
$result = run_case( 'hide provider authentication errors', $question, 502 );
if ( strpos( json_encode( $result->payload ), 'test-key' ) !== false ) throw new Exception( 'Secret leaked' );
echo "All checks passed.\n";
