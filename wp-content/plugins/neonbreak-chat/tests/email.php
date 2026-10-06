<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
$config = array( 'enabled' => 1, 'password' => Neonbreak_Client_Service::encrypt( 'smtp-test-only' ) );
add_filter( 'pre_option_neonbreak_email', function() use ( &$config ) { return $config; } );
$mailer = new \PHPMailer\PHPMailer\PHPMailer( true );
Neonbreak_Email::configure( $mailer );
if ( $mailer->Mailer !== 'smtp' || $mailer->Host !== 'mail.your-server.de' || $mailer->Port !== 587 || $mailer->SMTPSecure !== 'tls' || ! $mailer->SMTPAuth || $mailer->Password !== 'smtp-test-only' || $mailer->From !== 'info@neonbreak.com' || $mailer->SMTPDebug !== 0 ) throw new RuntimeException( 'SMTP configuration failed' );
echo "PASS: authenticated SMTP configured with encrypted password\n";
$config['enabled'] = 0;
$disabled = new \PHPMailer\PHPMailer\PHPMailer( true );
Neonbreak_Email::configure( $disabled );
if ( $disabled->Mailer !== 'mail' ) throw new RuntimeException( 'Disabled SMTP changed transport' );
echo "PASS: disabled SMTP leaves default transport unchanged\n";
$config['enabled'] = 1; $config['password'] = '';
try { Neonbreak_Email::configure( $mailer ); throw new RuntimeException( 'Missing key allowed' ); }
catch ( \PHPMailer\PHPMailer\Exception $e ) { echo "PASS: missing password blocks SMTP\n"; }
echo "No emails sent.\n";
