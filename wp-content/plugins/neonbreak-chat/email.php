<?php
defined( 'ABSPATH' ) || exit;

final class Neonbreak_Email {
    public static function failure_reason( $message ) {
        $message = strtolower( $message );
        if ( strpos( $message, 'password missing' ) !== false ) return 'The saved SMTP password is missing or cannot be decrypted. Enter the mailbox password again and save.';
        if ( strpos( $message, 'authenticate' ) !== false || strpos( $message, '535' ) !== false || strpos( $message, 'authentication' ) !== false ) return 'SMTP login was rejected. Check the full mailbox address and its mailbox password.';
        if ( strpos( $message, 'certificate' ) !== false || strpos( $message, 'crypto' ) !== false || strpos( $message, 'ssl' ) !== false || strpos( $message, 'tls' ) !== false ) return 'The encrypted connection failed. Check the server name, STARTTLS port 587 and the PHP certificate configuration. Keep certificate verification enabled.';
        if ( strpos( $message, 'connect' ) !== false || strpos( $message, 'timed out' ) !== false || strpos( $message, 'getaddrinfo' ) !== false ) return 'The SMTP server could not be reached. Check mail.your-server.de, port 587, DNS and outgoing connection restrictions.';
        if ( strpos( $message, 'recipient' ) !== false || strpos( $message, 'sender' ) !== false || strpos( $message, 'data not accepted' ) !== false ) return 'The server rejected the sender, recipient or message. Use the mailbox address as sender and check the recipient.';
        return 'Sending failed. Check mailbox credentials and server access. No raw SMTP transcript is stored.';
    }
    public static function settings() {
        return wp_parse_args( get_option( 'neonbreak_email', array() ), array( 'enabled' => 0, 'host' => 'mail.your-server.de', 'port' => 587, 'username' => 'info@neonbreak.com', 'from' => 'info@neonbreak.com', 'name' => 'Neon Break', 'password' => '' ) );
    }
    public static function configure( $mailer ) {
        $s = self::settings();
        if ( ! $s['enabled'] ) return;
        $password = Neonbreak_Client_Service::decrypt( $s['password'] );
        if ( ! $password ) throw new \PHPMailer\PHPMailer\Exception( 'SMTP password missing. Check Neonbreak Email settings.' );
        $mailer->isSMTP();
        $mailer->Host = $s['host'];
        $mailer->Port = (int) $s['port'];
        $mailer->SMTPSecure = $s['port'] === 465 ? 'ssl' : 'tls';
        $mailer->SMTPAuth = true;
        $mailer->Username = $s['username'];
        $mailer->Password = $password;
        $mailer->SMTPDebug = 0;
        $mailer->Timeout = 20;
        $mailer->setFrom( $s['from'], $s['name'], false );
        $mailer->Sender = $s['from'];
    }
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = self::settings(); ?>
        <div class="wrap"><h1>Neonbreak Email</h1><p>SMTP delivery for WordPress registration, password reset and contact messages.</p>
        <?php $diagnostic = get_transient( 'nb_email_test_' . get_current_user_id() ); if ( $diagnostic && isset( $_GET['result'] ) && $_GET['result'] === 'failed' ) : ?><div class="notice notice-error"><p><?php echo esc_html( $diagnostic ); ?></p></div><?php endif; ?>
        <?php if ( isset( $_GET['result'] ) ) : ?><div class="notice notice-info"><p><?php echo esc_html( array( 'saved' => 'Settings saved.', 'sent' => 'The mail server accepted the test message. Check the recipient inbox and spam folder.', 'failed' => 'Test failed. Check the SMTP credentials, enabled setting and server connection.', 'invalid' => 'Check all settings. Enabling SMTP requires a saved password.' )[sanitize_key( $_GET['result'] )] ?? 'Please try again.' ); ?></p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nb_email_save"><?php wp_nonce_field( 'nb_email_save' ); ?><table class="form-table">
        <tr><th>SMTP delivery</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], 1 ); ?>> Enable authenticated SMTP</label></td></tr>
        <?php foreach ( array( 'host' => 'SMTP server', 'username' => 'Mailbox username', 'from' => 'Sender email', 'name' => 'Sender name' ) as $field => $label ) : ?><tr><th><label for="nb-email-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" id="nb-email-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $s[$field] ); ?>" required></td></tr><?php endforeach; ?>
        <tr><th><label for="nb-email-port">Port / encryption</label></th><td><select id="nb-email-port" name="port"><option value="587" <?php selected( $s['port'], 587 ); ?>>587 · STARTTLS (Hetzner recommended)</option><option value="465" <?php selected( $s['port'], 465 ); ?>>465 · TLS</option></select></td></tr>
        <tr><th><label for="nb-email-password">Mailbox password</label></th><td><input class="regular-text" id="nb-email-password" name="password" type="password" autocomplete="new-password" maxlength="512" value=""><p class="description"><?php echo $s['password'] ? 'Password saved. Leave blank to keep it.' : 'Enter the password for your Hetzner mailbox.'; ?> Stored encrypted; never shown back here.</p><label><input type="checkbox" name="clear_password" value="1"> Remove saved password and disable SMTP</label></td></tr>
        </table><?php submit_button( 'Save email settings' ); ?></form><hr><h2>Send a test message</h2><p>Save and enable SMTP first. Sending a test message checks server acceptance, not final inbox delivery.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nb_email_test"><?php wp_nonce_field( 'nb_email_test' ); ?><label for="nb-email-recipient">Recipient</label> <input class="regular-text" id="nb-email-recipient" name="recipient" type="email" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"><?php submit_button( 'Send test email', 'secondary' ); ?></form></div>
        <?php
    }
    public static function redirect( $status ) { wp_safe_redirect( add_query_arg( array( 'page' => 'neonbreak-email', 'result' => $status ), admin_url( 'options-general.php' ) ) ); exit; }
    public static function authorize( $action ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Administrator access required.', '', array( 'response' => 403 ) );
        check_admin_referer( $action );
    }
    public static function save() {
        self::authorize( 'nb_email_save' ); $s = self::settings();
        foreach ( array( 'host', 'username', 'from', 'name' ) as $field ) $s[$field] = sanitize_text_field( Neonbreak_Client_Service::input( $_POST, $field ) );
        $s['port'] = (int) Neonbreak_Client_Service::input( $_POST, 'port' );
        $s['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
        if ( ! preg_match( '/^[a-z0-9.-]+$/i', $s['host'] ) || ! in_array( $s['port'], array( 587, 465 ), true ) || ! is_email( $s['username'] ) || ! is_email( $s['from'] ) || ! $s['name'] ) self::redirect( 'invalid' );
        $password = Neonbreak_Client_Service::input( $_POST, 'password' );
        if ( strlen( $password ) > 512 ) self::redirect( 'invalid' );
        if ( $password !== '' ) $s['password'] = Neonbreak_Client_Service::encrypt( $password );
        if ( ! empty( $_POST['clear_password'] ) ) { $s['password'] = ''; $s['enabled'] = 0; }
        if ( $s['enabled'] && ! Neonbreak_Client_Service::decrypt( $s['password'] ) ) self::redirect( 'invalid' );
        update_option( 'neonbreak_email', $s, false ); self::redirect( 'saved' );
    }
    public static function test() {
        self::authorize( 'nb_email_test' );
        $recipient = sanitize_email( Neonbreak_Client_Service::input( $_POST, 'recipient' ) );
        $diagnostic_key = 'nb_email_test_' . get_current_user_id();
        delete_transient( $diagnostic_key );
        if ( ! is_email( $recipient ) || ! self::settings()['enabled'] ) {
            set_transient( $diagnostic_key, 'Save settings, enable SMTP and enter a valid recipient before testing.', 600 );
            self::redirect( 'failed' );
        }
        $failure = '';
        $capture = function( $error ) use ( &$failure ) { $failure = self::failure_reason( $error->get_error_message() ); };
        add_action( 'wp_mail_failed', $capture );
        try { $sent = wp_mail( $recipient, 'Neonbreak email connection test', 'This is a test message from Neonbreak. SMTP settings were configured by your website administrator.' ); }
        catch ( Throwable $e ) { $sent = false; $failure = self::failure_reason( $e->getMessage() ); }
        remove_action( 'wp_mail_failed', $capture );
        if ( ! $sent ) set_transient( $diagnostic_key, $failure ?: self::failure_reason( '' ), 600 );
        self::redirect( $sent ? 'sent' : 'failed' );
    }
}
add_action( 'admin_menu', function() { add_options_page( 'Neonbreak Email', 'Neonbreak Email', 'manage_options', 'neonbreak-email', array( 'Neonbreak_Email', 'page' ) ); } );
add_action( 'phpmailer_init', array( 'Neonbreak_Email', 'configure' ) );
add_filter( 'wp_mail_from', function( $from ) { $s = Neonbreak_Email::settings(); return $s['enabled'] ? $s['from'] : $from; } );
add_filter( 'wp_mail_from_name', function( $name ) { $s = Neonbreak_Email::settings(); return $s['enabled'] ? $s['name'] : $name; } );
add_action( 'admin_post_nb_email_save', array( 'Neonbreak_Email', 'save' ) );
add_action( 'admin_post_nb_email_test', array( 'Neonbreak_Email', 'test' ) );
