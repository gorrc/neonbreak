<?php
defined( 'ABSPATH' ) || exit;

final class NeonLib_Mobile_Transfer {
	private const META = 'neonlib_mobile_transfer';
	private static ?self $instance = null;
	public static function instance(): self { return self::$instance ??= new self(); }
	private function __construct() {
		add_action( 'init', array( $this, 'handle' ) );
		add_action( 'wp_ajax_neonlib_mobile_status', array( $this, 'ajax_status' ) );
		add_action( 'neonlib_mobile_release_lock', array( $this, 'release_lock' ), 10, 2 );
	}
	public static function text( string $english, string $croatian ): string {
		global $TRP_LANGUAGE;
		return str_starts_with( strtolower( (string) ( $TRP_LANGUAGE ?? get_locale() ) ), 'hr' ) ? $croatian : $english;
	}
	private function allowed(): bool {
		return is_user_logged_in() && current_user_can( 'neonlib_access_dashboard' ) && ! current_user_can( 'manage_options' )
			&& '1' === (string) get_user_meta( get_current_user_id(), 'neonlib_email_verified', true );
	}
	private function url(): string {
		$page = (int) get_option( 'neonlib_users_account_page_id' );
		return $page ? get_permalink( $page ) : home_url( '/account/' );
	}
	private function redirect( string $status, string $view = 'mobile-transfer', array $extra = array() ): never {
		wp_safe_redirect( add_query_arg( array_merge( array( 'neonlib_view' => $view, 'mobile_status' => $status ), $extra ), $this->url() ) );
		exit;
	}
	public function handle(): void {
		if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_GET['neonlib_continue'] ) ) {
			$id = sanitize_text_field( wp_unslash( $_GET['neonlib_continue'] ) );
			if ( preg_match( '/^[a-f0-9]{32}$/', $id ) ) $this->redirect( 'received', 'new-subscription', array( 'mobile_transfer' => $id ) );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['neonlib_action'] ?? '' ) );
		if ( ! in_array( $action, array( 'start_mobile_transfer', 'refresh_mobile_transfer', 'cancel_mobile_transfer', 'discard_mobile_draft', 'save_mobile_draft', 'import_mobile_transfer', 'submit_mobile_transfer' ), true ) ) return;
		if ( ! $this->allowed() ) $this->redirect( 'forbidden' );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['neonlib_mobile_nonce'] ?? '' ) ), 'neonlib_mobile_transfer' ) ) $this->redirect( 'expired_request' );
		$user_id = get_current_user_id();
		$client = new NeonLib_Api_Client();
		$session = get_user_meta( $user_id, self::META, true );
		if ( in_array( $action, array( 'start_mobile_transfer', 'refresh_mobile_transfer' ), true ) ) {
			$publisher = $client->publisher( $user_id );
			if ( is_wp_error( $publisher ) ) $this->redirect( 'api_error' );
			if ( empty( $publisher['display_name'] ) || 'NeonLib account' === $publisher['display_name'] ) $this->redirect( 'publisher_required', 'account' );
			if ( is_array( $session ) && isset( $session['sessionId'] ) ) {
				if ( 'refresh_mobile_transfer' === $action && ! hash_equals( (string) $session['sessionId'], sanitize_text_field( wp_unslash( $_POST['mobile_transfer'] ?? '' ) ) ) ) $this->redirect( 'expired_request' );
				$status = $client->mobile_transfer( $user_id, 'GET', $session['sessionId'] );
				if ( 'refresh_mobile_transfer' === $action && ! is_wp_error( $status ) && 'UPLOADED' === ( $status['status'] ?? '' ) ) $this->redirect( 'ready' );
				if ( 'start_mobile_transfer' === $action && ! is_wp_error( $status ) && in_array( $status['status'] ?? '', array( 'WAITING', 'PAIRED' ), true ) ) $this->redirect( 'ready' );
				if ( is_wp_error( $status ) && ! in_array( $status->get_error_code(), array( 'transfer_expired', 'transfer_not_found' ), true ) ) $this->redirect( 'api_error' );
				if ( 'refresh_mobile_transfer' === $action && ! is_wp_error( $status ) ) {
					$cancelled = $client->mobile_transfer( $user_id, 'DELETE', $session['sessionId'] );
					if ( is_wp_error( $cancelled ) && ! in_array( $cancelled->get_error_code(), array( 'transfer_expired', 'transfer_not_found' ), true ) ) $this->redirect( 'api_error' );
					delete_user_meta( $user_id, self::META );
				}
			}
			$result = $client->mobile_transfer( $user_id, 'POST' );
			if ( is_wp_error( $result ) ) $this->redirect( 'rate_limited' === $result->get_error_code() ? 'rate_limited' : 'api_error' );
			if ( ! preg_match( '/^[a-f0-9]{32}$/', $result['sessionId'] ?? '' ) || ! isset( $result['pairing'], $result['manualCode'], $result['expiresAt'] ) ) $this->redirect( 'api_error' );
			update_user_meta( $user_id, self::META, $result );
			$this->redirect( 'ready' );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['mobile_transfer'] ?? '' ) );
		if ( 'submit_mobile_transfer' === $action && preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			$done = get_user_meta( $user_id, 'neonlib_mobile_receipt_' . $id, true );
			if ( is_array( $done ) && ( $done['status'] ?? '' ) === 'SUBMITTED' ) $this->redirect( 'submitted', 'subscriptions' );
		}
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) $this->redirect( 'expired_request' );
		if ( 'cancel_mobile_transfer' === $action && ( ! is_array( $session ) || ! hash_equals( (string) ( $session['sessionId'] ?? '' ), $id ) ) ) $this->redirect( 'expired_request' );
		if ( 'discard_mobile_draft' === $action ) {
			if ( is_array( get_user_meta( $user_id, 'neonlib_mobile_receipt_' . $id, true ) ) ) $this->redirect( 'changed_retry', 'new-subscription', array( 'mobile_transfer' => $id ) );
			$draft = $client->mobile_transfer( $user_id, 'GET', $id, true );
			if ( is_wp_error( $draft ) ) $this->redirect( 'api_error' );
			$result = $client->mobile_transfer( $user_id, 'DELETE', $id );
			if ( is_wp_error( $result ) ) $this->redirect( 'api_error' );
			delete_user_meta( $user_id, 'neonlib_mobile_draft_' . $id );
			if ( is_array( $session ) && ( $session['sessionId'] ?? '' ) === $id ) delete_user_meta( $user_id, self::META );
			$this->redirect( 'draft_deleted', 'subscriptions' );
		}
		if ( 'cancel_mobile_transfer' === $action ) {
			$current = $client->mobile_transfer( $user_id, 'GET', $id );
			if ( ! is_wp_error( $current ) && ( $current['status'] ?? '' ) === 'UPLOADED' ) $this->redirect( 'ready' );
			$result = $client->mobile_transfer( $user_id, 'DELETE', $id );
			if ( is_wp_error( $result ) && ! in_array( $result->get_error_code(), array( 'transfer_expired', 'transfer_not_found' ), true ) ) $this->redirect( 'api_error' );
			delete_user_meta( $user_id, self::META );
			$this->redirect( 'cancelled' );
		}
		if ( 'import_mobile_transfer' === $action ) {
			$values = $this->editor_values( $user_id, $id );
			if ( is_wp_error( $values ) ) $this->redirect( 'api_error' );
			$this->redirect( 'received', 'new-subscription', array( 'mobile_transfer' => $id ) );
		}
		$this->submit( $user_id, $id, $client, 'save_mobile_draft' === $action );
	}
	public function editor_values( int $user_id, string $id ): array|WP_Error {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) return new WP_Error( 'invalid_transfer', 'Invalid transfer.' );
		$receipt = get_user_meta( $user_id, 'neonlib_mobile_receipt_' . $id, true );
		if ( is_array( $receipt ) && ( $receipt['status'] ?? '' ) === 'SUBMITTED' ) return new WP_Error( 'already_submitted', 'This collection has already been submitted for review.' );
		$draft = ( new NeonLib_Api_Client() )->mobile_transfer( $user_id, 'GET', $id, true );
		if ( is_wp_error( $draft ) ) return $draft;
		$saved = get_user_meta( $user_id, 'neonlib_mobile_draft_' . $id, true );
		if ( is_array( $saved ) ) return $saved;
		$content = $draft['draft'] ?? null;
		if ( ! is_array( $content ) || ! is_array( $content['documents'] ?? null ) || empty( $content['documents'] ) ) return new WP_Error( 'invalid_transfer', 'Draft has no documents.' );
		return array( 'package_id' => 'collection.' . substr( $id, 0, 16 ),
			'title' => mb_substr( (string) ( $content['collection']['title'] ?? '' ), 0, 190 ), 'description' => (string) ( $content['collection']['description'] ?? '' ),
			'language' => (string) ( $content['collection']['language'] ?? 'en' ), 'visibility' => 'private',
			'documents_json' => wp_json_encode( $content['documents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}
	private function submit( int $user_id, string $id, NeonLib_Api_Client $client, bool $save_only = false ): never {
		$receipt_key = 'neonlib_mobile_receipt_' . $id;
		$receipt = get_user_meta( $user_id, $receipt_key, true );
		if ( is_array( $receipt ) && ( $receipt['status'] ?? '' ) === 'SUBMITTED' ) $this->redirect( 'submitted', 'subscriptions' );
		$editor = $this->editor_values( $user_id, $id );
		if ( is_wp_error( $editor ) ) $this->redirect( 'api_error' );
		$payload = array();
		foreach ( array( 'package_id', 'title', 'description', 'language', 'visibility' ) as $key ) {
			$value = $_POST[$key] ?? '';
			if ( ! is_string( $value ) ) $this->redirect( 'invalid_fields' );
			$payload[$key] = 'description' === $key ? sanitize_textarea_field( wp_unslash( $value ) ) : sanitize_text_field( wp_unslash( $value ) );
		}
		$payload['package_id'] = strtolower( trim( $payload['package_id'] ) );
		$json = wp_unslash( $_POST['documents_json'] ?? '' );
		$documents = is_string( $json ) ? json_decode( $json, true, 64 ) : null;
		if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]{2,189}$/', $payload['package_id'] ) || '' === trim( $payload['title'] ) || ! is_array( $documents ) || ! array_is_list( $documents ) || ! $documents ) $this->redirect( 'invalid_fields' );
		foreach ( $documents as &$document ) {
			if ( ! is_array( $document ) || ! is_string( $document['title'] ?? null ) ) $this->redirect( 'invalid_fields' );
			$document['title'] = trim( $document['title'] );
		}
		unset( $document );
		if ( ! $this->valid_documents( $documents ) ) $this->redirect( 'invalid_fields', 'new-subscription', array( 'mobile_transfer' => $id ) );
		$documents = array_map( static fn( array $document ): array => array( 'id' => $document['id'], 'title' => $document['title'], 'content' => $document['content'] ), $documents );
		if ( $save_only ) {
			if ( mb_strlen( $payload['title'] ) > 190 || mb_strlen( $payload['description'] ) > 10000 || ! preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $payload['language'] ) || ! in_array( $payload['visibility'], array( 'private', 'public' ), true ) ) $this->redirect( 'invalid_fields', 'new-subscription', array( 'mobile_transfer' => $id ) );
			$receipt = get_user_meta( $user_id, $receipt_key, true );
			if ( is_array( $receipt ) ) $this->redirect( 'changed_retry', 'new-subscription', array( 'mobile_transfer' => $id ) );
			update_user_meta( $user_id, 'neonlib_mobile_draft_' . $id, $payload + array( 'documents_json' => wp_json_encode( $documents, JSON_UNESCAPED_UNICODE ) ) );
			$this->redirect( 'draft_saved', 'subscriptions' );
		}
		set_transient( 'neonlib_subscription_form_' . $user_id, $payload + array( 'documents_json' => $json, 'mobile_transfer' => $id ), DAY_IN_SECONDS );
		$hash = hash( 'sha256', wp_json_encode( array( $payload, $documents ) ) );
		$lock = 'neonlib_mobile_lock_' . $id;
		$lock_value = array( 'token' => bin2hex( random_bytes( 16 ) ), 'time' => time() );
		// Atomic options insert serializes submissions from multiple tabs.
		if ( ! add_option( $lock, $lock_value, '', false ) ) $this->redirect( 'busy', 'new-subscription', array( 'mobile_transfer' => $id ) );
		wp_schedule_single_event( time() + 600, 'neonlib_mobile_release_lock', array( $lock, $lock_value['token'] ) );
		$status = 'api_error';
		try {
			$publisher = $client->publisher( $user_id );
			if ( is_wp_error( $publisher ) || empty( $publisher['display_name'] ) || 'NeonLib account' === $publisher['display_name'] ) throw new RuntimeException( 'Publisher unavailable.' );
			$receipt = get_user_meta( $user_id, $receipt_key, true );
			if ( is_array( $receipt ) && ! hash_equals( (string) $receipt['hash'], $hash ) ) {
				$status = 'changed_retry';
				throw new RuntimeException( 'Retry content changed.' );
			}
			if ( ! is_array( $receipt ) ) {
				// Refuse to attach a transfer to an unrelated existing subscription.
				$existing = $client->subscription( $user_id, $payload['package_id'] );
				if ( ! is_wp_error( $existing ) || 'subscription_not_found' !== $existing->get_error_code() ) {
					$status = is_wp_error( $existing ) ? 'api_error' : 'package_conflict';
					throw new RuntimeException( 'Package unavailable.' );
				}
				$receipt = array( 'hash' => $hash, 'package_id' => $payload['package_id'], 'status' => 'CREATING' );
				update_user_meta( $user_id, $receipt_key, $receipt );
			}
			$package = $receipt['package_id'];
			$existing = $client->subscription( $user_id, $package );
			if ( is_wp_error( $existing ) ) {
				if ( 'subscription_not_found' !== $existing->get_error_code() ) throw new RuntimeException( 'Cannot check subscription.' );
				$created = $client->create_subscription( $user_id, $payload );
				if ( is_wp_error( $created ) ) {
					if ( in_array( $created->get_error_code(), array( 'validation_failed', 'package_id_conflict' ), true ) ) {
						delete_user_meta( $user_id, $receipt_key );
						$status = 'validation_failed' === $created->get_error_code() ? 'invalid_fields' : 'package_conflict';
					}
					throw new RuntimeException( 'Cannot create subscription.' );
				}
			}
			$versions = $client->versions( $user_id, $package );
			if ( is_wp_error( $versions ) ) throw new RuntimeException( 'Cannot check submitted version.' );
			if ( $versions ) {
				$version_number = (int) ( $versions[0]['version'] ?? 0 );
				$version = $client->version( $user_id, $package, $version_number );
				if ( is_wp_error( $version ) || ( $version['documents'] ?? null ) !== $documents ) throw new RuntimeException( 'Existing version differs.' );
				$result = array( 'data' => array( 'version' => $version_number ) );
			} else {
				$result = $client->publish_version( $user_id, $package, $documents );
				if ( is_wp_error( $result ) ) throw new RuntimeException( 'Cannot submit version.' );
			}
			$receipt['status'] = 'SUBMITTED'; $receipt['version'] = $result['data']['version'] ?? null;
			update_user_meta( $user_id, $receipt_key, $receipt );
			delete_transient( 'neonlib_subscription_form_' . $user_id );
			NeonLib_Users::instance()->notify_subscription_review( $user_id, $package, $result, $payload['title'] );
			$client->mobile_transfer( $user_id, 'DELETE', $id );
			delete_user_meta( $user_id, 'neonlib_mobile_draft_' . $id );
			$current = get_user_meta( $user_id, self::META, true );
			if ( is_array( $current ) && ( $current['sessionId'] ?? '' ) === $id ) delete_user_meta( $user_id, self::META );
			$status = 'submitted';
		} catch ( Throwable $error ) {
			// No document content or credentials in logs.
			error_log( 'NeonLib mobile submission could not complete for transfer ' . $id );
		} finally {
			$this->release_lock( $lock, $lock_value['token'] );
			wp_clear_scheduled_hook( 'neonlib_mobile_release_lock', array( $lock, $lock_value['token'] ) );
		}
		$this->redirect( $status, 'submitted' === $status ? 'subscriptions' : 'new-subscription', 'submitted' === $status ? array() : array( 'mobile_transfer' => $id ) );
	}
	public function release_lock( string $lock, string $token ): void {
		if ( ! preg_match( '/^neonlib_mobile_lock_[a-f0-9]{32}$/', $lock ) ) return;
		$stored = get_option( $lock );
		if ( is_array( $stored ) && hash_equals( (string) ( $stored['token'] ?? '' ), $token ) ) delete_option( $lock );
	}
	private function valid_documents( array $documents ): bool {
		if ( count( $documents ) > 500 ) return false;
		$ids = array(); $bytes = 0;
		foreach ( $documents as $document ) {
			if ( count( $document ) !== 3 || array_diff( array_keys( $document ), array( 'id', 'title', 'content' ) )
				|| ! is_string( $document['id'] ?? null ) || ! preg_match( '/^[a-z0-9][a-z0-9._-]{0,188}$/', $document['id'] )
				|| isset( $ids[$document['id']] ) || ! is_string( $document['content'] ?? null )
				|| '' === $document['title'] || mb_strlen( $document['title'] ) > 255 || strlen( $document['content'] ) > 1048576 ) return false;
			$ids[$document['id']] = true; $bytes += strlen( $document['content'] );
		}
		return $bytes <= 8388608;
	}
	public function ajax_status(): void {
		if ( ! $this->allowed() ) wp_send_json_error( array( 'message' => 'Sign in with a verified publisher account.' ), 403 );
		check_ajax_referer( 'neonlib_mobile_transfer', 'nonce' );
		$id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$session = get_user_meta( get_current_user_id(), self::META, true );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) || ! is_array( $session ) || ! hash_equals( (string) ( $session['sessionId'] ?? '' ), $id ) ) wp_send_json_error( array( 'message' => 'Transfer unavailable.' ), 404 );
		$result = ( new NeonLib_Api_Client() )->mobile_transfer( get_current_user_id(), 'GET', $id );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'code' => $result->get_error_code() ), 409 );
		wp_send_json_success( $result );
	}
	public function draft_cards( array $drafts, int $user_id, string $url ): string {
		ob_start();
		foreach ( $drafts as $draft ) {
			$id = $draft['sessionId'] ?? '';
			if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) continue;
			$receipt = get_user_meta( $user_id, 'neonlib_mobile_receipt_' . $id, true );
			if ( is_array( $receipt ) && ( $receipt['status'] ?? '' ) === 'SUBMITTED' ) continue;
			$saved = get_user_meta( $user_id, 'neonlib_mobile_draft_' . $id, true ); ?>
			<article class="neonlib-account-draft">
				<span class="neonlib-status"><?php echo esc_html( self::text( 'Draft — not submitted', 'Nacrt — nije predan' ) ); ?></span>
				<h4><?php echo esc_html( (string) ( is_array( $saved ) ? $saved['title'] : ( $draft['title'] ?? '' ) ) ); ?></h4>
				<p><?php echo esc_html( self::text( 'Received from your phone and saved in your account. Continue whenever you are ready.', 'Primljeno s mobitela i spremljeno u tvoj račun. Nastavi kada želiš.' ) ); ?></p>
				<a class="neonlib-button" href="<?php echo esc_url( add_query_arg( array( 'neonlib_view' => 'new-subscription', 'mobile_transfer' => $id ), $url ) ); ?>"><?php echo esc_html( self::text( 'Continue editing', 'Nastavi uređivanje' ) ); ?></a>
				<?php if ( ! is_array( $receipt ) ) $this->form( $url, 'discard_mobile_draft', $id, self::text( 'Delete draft', 'Obriši nacrt' ), true ); ?>
			</article>
		<?php }
		return (string) ob_get_clean();
	}
	public function render( string $url, string $publisher_name ): string {
		$session = get_user_meta( get_current_user_id(), self::META, true );
		$status = is_array( $session ) && isset( $session['sessionId'] ) ? ( new NeonLib_Api_Client() )->mobile_transfer( get_current_user_id(), 'GET', $session['sessionId'] ) : null;
		$active = is_array( $status ) && in_array( $status['status'] ?? '', array( 'WAITING', 'PAIRED', 'UPLOADED' ), true );
		ob_start(); ?>
		<section class="neonlib-panel neonlib-transfer-panel">
			<a class="neonlib-back-link" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'subscriptions', $url ) ); ?>">← <?php echo esc_html( self::text( 'Back to subscriptions', 'Natrag na zbirke' ) ); ?></a>
			<h3><?php echo esc_html( self::text( 'Receive a collection from your phone', 'Preuzmi zbirku s mobitela' ) ); ?></h3>
			<p><?php echo esc_html( self::text( 'Connect NeonLib, choose your collection and send its documents here. You can review everything before submitting it for publication.', 'Poveži NeonLib, odaberi svoju zbirku i pošalji dokumente ovdje. Sve možeš pregledati prije predaje za objavu.' ) ); ?></p>
			<?php if ( ! $publisher_name ) : ?>
				<p><?php echo esc_html( self::text( 'Set your public publisher name in Account first.', 'Najprije postavi javni naziv izdavača u odjeljku Račun.' ) ); ?></p>
			<?php elseif ( is_wp_error( $status ) && ! in_array( $status->get_error_code(), array( 'transfer_expired', 'transfer_not_found' ), true ) ) : ?>
				<p role="alert"><?php echo esc_html( self::text( 'The transfer service is unavailable. Check that the API update has been uploaded, then try again.', 'Usluga prijenosa nije dostupna. Provjeri je li API ažuriran pa pokušaj ponovno.' ) ); ?></p>
			<?php elseif ( ! $active ) : ?>
				<?php $this->form( $url, 'start_mobile_transfer', '', self::text( 'Create connection code', 'Prikaži kod za povezivanje' ) ); ?>
			<?php else : ?>
				<?php $qr = wp_json_encode( array( 'type' => 'neonlib.mobile-transfer', 'version' => 1, 'apiBaseUrl' => defined( 'NEONLIB_API_URL' ) ? untrailingslashit( NEONLIB_API_URL ) : '', 'sessionId' => $session['sessionId'], 'pairingToken' => $session['pairing']['pairingToken'] ) ); ?>
				<div class="neonlib-transfer-live" data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'neonlib_mobile_transfer' ) ); ?>" data-session="<?php echo esc_attr( $session['sessionId'] ); ?>" data-status="<?php echo esc_attr( $status['status'] ); ?>" data-expires="<?php echo esc_attr( $status['expiresAt'] ); ?>">
					<div class="neonlib-transfer-pairing" <?php if ( 'UPLOADED' === $status['status'] ) echo 'hidden'; ?>>
						<div><p><?php echo esc_html( self::text( 'Connect with code: in NeonLib, choose Send to web and enter:', 'Poveži se kodom: u NeonLibu odaberi Pošalji na web i unesi:' ) ); ?></p><div class="neonlib-transfer-code-row"><code class="neonlib-transfer-code"><?php echo esc_html( $session['manualCode'] ); ?></code><?php $this->form( $url, 'refresh_mobile_transfer', $session['sessionId'], self::text( 'Refresh connection code', 'Generiraj novi kod' ) ); ?></div><p><a class="neonlib-button" href="<?php echo esc_url( 'neonlib://mobile-transfer?code=' . rawurlencode( $session['manualCode'] ), array( 'neonlib' ) ); ?>"><?php echo esc_html( self::text( 'Open in NeonLib', 'Otvori u NeonLibu' ) ); ?></a></p><p class="description"><?php echo esc_html( self::text( 'On this phone, open NeonLib to enter the code automatically, then tap Connect. Requires the updated app. You can also enter the code manually.', 'Na ovom mobitelu otvori NeonLib da se kod automatski unese pa dodirni Poveži. Potrebna je ažurirana aplikacija. Kod možeš unijeti i ručno.' ) ); ?></p><p><?php echo esc_html( self::text( 'Recipient:', 'Primatelj:' ) . ' ' . $publisher_name ); ?></p><p class="neonlib-transfer-timer"><?php echo esc_html( self::text( 'Code expires in', 'Kod istječe za' ) ); ?> <span class="neonlib-transfer-countdown" data-no-translation data-no-dynamic-translation>--:--</span></p><p class="description"><?php echo esc_html( self::text( 'Keep this page open. Share the code only with your own device.', 'Ostavi ovu stranicu otvorenu. Kod koristi samo na svom uređaju.' ) ); ?></p></div>
                        <div class="neonlib-transfer-qr-option"><p><?php echo esc_html( self::text( 'Or scan the QR code from another screen', 'Ili skeniraj QR kod s drugog zaslona' ) ); ?></p>						<div class="neonlib-transfer-qr" data-payload="<?php echo esc_attr( $qr ); ?>" role="img" aria-label="<?php echo esc_attr( self::text( 'Scan this QR code in NeonLib', 'Skeniraj ovaj QR kod u NeonLibu' ) ); ?>"></div></div>
					</div>
					<p class="neonlib-transfer-status" role="status" aria-live="polite"></p>
					<div class="neonlib-transfer-ready" <?php if ( 'UPLOADED' !== $status['status'] ) echo 'hidden'; ?>>
						<p class="neonlib-transfer-summary"><?php echo esc_html( ( $status['title'] ?? '' ) . ( isset( $status['documentCount'] ) ? ' · ' . $status['documentCount'] . ' ' . self::text( 'documents', 'dokumenata' ) : '' ) ); ?></p>
						<?php $this->form( $url, 'import_mobile_transfer', $session['sessionId'], self::text( 'Review received collection', 'Pregledaj primljenu zbirku' ) ); ?>
						<?php $this->form( $url, 'start_mobile_transfer', '', self::text( 'Receive another collection', 'Preuzmi drugu zbirku' ), true ); ?>
					</div>
					<?php if ( 'UPLOADED' !== $status['status'] ) $this->form( $url, 'cancel_mobile_transfer', $session['sessionId'], self::text( 'Cancel transfer', 'Otkaži prijenos' ), true ); ?>
					<?php if ( 'UPLOADED' !== $status['status'] ) : ?>
						<p class="description"><?php echo esc_html( self::text( 'Need another code? Creating a new code disconnects the previous phone connection.', 'Trebaš drugi kod? Novi kod prekida prethodno povezivanje s mobitelom.' ) ); ?></p>
						
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php return (string) ob_get_clean();
	}
	private function form( string $url, string $action, string $id, string $label, bool $secondary = false ): void { ?>
		<form method="post" action="<?php echo esc_url( $url ); ?>" class="neonlib-transfer-action" <?php if ( 'discard_mobile_draft' === $action ) : ?>onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( self::text( 'Delete this saved draft? Your phone collection will stay unchanged.', 'Obrisati ovaj spremljeni nacrt? Zbirka na mobitelu ostaje sačuvana.' ) ) ); ?>);"<?php endif; ?>>
			<?php wp_nonce_field( 'neonlib_mobile_transfer', 'neonlib_mobile_nonce' ); ?>
			<input type="hidden" name="neonlib_action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="mobile_transfer" value="<?php echo esc_attr( $id ); ?>">
			<button type="submit" <?php if ( $secondary ) echo 'class="neonlib-button--secondary"'; ?>><?php echo esc_html( $label ); ?></button>
		</form>
	<?php }
	public function notice(): string {
		$status = sanitize_key( wp_unslash( $_GET['mobile_status'] ?? '' ) );
		$messages = array(
			'draft_saved' => self::text( 'Draft saved in your account. You can continue later from Subscriptions.', 'Nacrt je spremljen u tvoj račun. Možeš nastaviti kasnije kroz Subscriptions.' ),
			'draft_deleted' => self::text( 'Draft deleted. The collection on your phone is unchanged.', 'Nacrt je obrisan. Zbirka na mobitelu ostaje sačuvana.' ),
			'api_error' => self::text( 'The transfer could not be completed. Your phone collection is unchanged. Please try again.', 'Prijenos nije dovršen. Zbirka na mobitelu nije promijenjena. Pokušaj ponovno.' ),
			'expired_request' => self::text( 'This request is no longer valid. Reopen the transfer page.', 'Zahtjev više nije valjan. Ponovno otvori stranicu prijenosa.' ),
			'forbidden' => self::text( 'Sign in with a verified publisher account.', 'Prijavi se izdavačkim računom s potvrđenim e-mailom.' ),
			'rate_limited' => self::text( 'Too many connection codes requested. Please try again later.', 'Zatraženo je previše kodova za povezivanje. Pokušaj kasnije.' ),
			'publisher_required' => self::text( 'Set your public publisher name first.', 'Najprije postavi javni naziv izdavača.' ),
			'busy' => self::text( 'Submission is already in progress. Please wait.', 'Predaja je već u tijeku. Pričekaj.' ),
			'changed_retry' => self::text( 'A previous submission is being recovered. Restore its original fields and documents before retrying, then edit the created subscription if needed.', 'Oporavlja se prethodna predaja. Vrati izvorna polja i dokumente prije ponovnog pokušaja, a zatim po potrebi uredi izrađenu zbirku.' ),
			'package_conflict' => self::text( 'This Package ID is already in use. Choose another one.', 'Ovaj Package ID već postoji. Odaberi drugi.' ),
			'invalid_fields' => self::text( 'Check the collection fields and documents.', 'Provjeri podatke zbirke i dokumente.' ),
			'received' => self::text( 'Collection received. Review its documents before submitting.', 'Zbirka je primljena. Pregledaj dokumente prije predaje.' ),
			'submitted' => self::text( 'Collection submitted for administrator review.', 'Zbirka je predana na administratorski pregled.' ),
			'cancelled' => self::text( 'Transfer cancelled.', 'Prijenos je otkazan.' ),
		);
		if ( ! isset( $messages[$status] ) ) return '';
		$success = in_array( $status, array( 'received', 'submitted', 'cancelled', 'draft_saved', 'draft_deleted' ), true );
		return '<div class="neonlib-notice neonlib-notice--' . ( $success ? 'success' : 'error' ) . '" role="status">' . esc_html( $messages[$status] ) . '</div>';
	}
}
