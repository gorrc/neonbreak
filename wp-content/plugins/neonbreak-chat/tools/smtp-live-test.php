<?php
// Explicit CLI-only test. Sends one message to the Neonbreak mailbox.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
$settings = Neonbreak_Email::settings();
if ( ! Neonbreak_Client_Service::decrypt( $settings['password'] ) ) { echo "No usable local SMTP password.\n"; exit( 1 ); }
// Enable only for this process; do not alter the site's saved settings.
$settings['enabled'] = 1;
add_filter( 'pre_option_neonbreak_email', function() use ( $settings ) { return $settings; } );
add_action( 'wp_mail_failed', function( $error ) use ( $settings ) {
    $message = $error->get_error_message();
    $password = Neonbreak_Client_Service::decrypt( $settings['password'] );
    $message = str_replace( array( $password, base64_encode( $password ), base64_encode( "\0" . $settings['username'] . "\0" . $password ) ), '[redacted]', $message );
    echo Neonbreak_Email::failure_reason( $message ) . "\n";
    echo 'Sanitized error: ' . substr( strip_tags( $message ), 0, 500 ) . "\n";
} );
try {
    $sent = wp_mail( 'info@neonbreak.com', 'Neonbreak SMTP test from local WAMP', 'One-time SMTP test sent from the local Neonbreak development site through the configured Hetzner SMTP server. No action is required.' );
    echo $sent ? "SMTP server accepted the test message. Check info@neonbreak.com inbox and spam.\n" : "SMTP test failed.\n";
    exit( $sent ? 0 : 1 );
} catch ( Throwable $error ) { echo Neonbreak_Email::failure_reason( $error->getMessage() ) . "\n"; exit( 1 ); }
