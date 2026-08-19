<?php

defined( 'ABSPATH' ) || exit;

final class NeonLib_Users {
	private const ROLE = 'neonlib_user';
	private const CAP_ACCESS = 'neonlib_access_dashboard';
	private const PAGE_OPTION = 'neonlib_users_account_page_id';
	private const META_EMAIL_VERIFIED = 'neonlib_email_verified';
	private const META_VERIFY_TOKEN = 'neonlib_email_verify_token';
	private const META_VERIFY_EXPIRES = 'neonlib_email_verify_expires';
	private const META_ACCOUNT_ID = 'neonlib_account_id';

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'handle_email_verification' ) );
		add_action( 'init', array( $this, 'handle_verification_resend' ) );
		add_action( 'init', array( $this, 'handle_registration' ) );
		add_action( 'init', array( $this, 'handle_subscription_action' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
	}

	public static function activate(): void {
		add_role(
			self::ROLE,
			__( 'NeonLib user', 'neonlib-users' ),
			array(
				'read'                   => true,
				self::CAP_ACCESS         => true,
				'neonlib_manage_profile' => true,
			)
		);

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::CAP_ACCESS );
			$administrator->add_cap( 'neonlib_manage_profile' );
			$administrator->add_cap( 'neonlib_manage_users' );
		}

		self::create_account_page();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	private static function create_account_page(): void {
		$page_id = (int) get_option( self::PAGE_OPTION );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) ) {
			return;
		}

		$existing = get_page_by_path( 'neonlib-racun' );
		if ( $existing instanceof WP_Post ) {
			update_option( self::PAGE_OPTION, $existing->ID );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'NeonLib account', 'neonlib-users' ),
				'post_name'    => 'neonlib-racun',
				'post_content' => '[neonlib_account]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( ! is_wp_error( $page_id ) ) {
			update_option( self::PAGE_OPTION, $page_id );
		}
	}

	public function register_shortcode(): void {
		add_shortcode( 'neonlib_account', array( $this, 'render_account' ) );
	}

	public function enqueue_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || ! has_shortcode( $post->post_content, 'neonlib_account' ) ) {
			return;
		}

		wp_enqueue_style(
			'neonlib-users',
			NEONLIB_USERS_URL . 'assets/css/account.css',
			array(),
			NEONLIB_USERS_VERSION
		);
	}

	public function render_account(): string {
		if ( is_user_logged_in() ) {
			return $this->render_dashboard();
		}

		return $this->render_authentication();
	}

	private function render_authentication(): string {
		$messages = $this->registration_messages();
		$redirect = $this->account_url();

		ob_start();
		?>
		<div class="neonlib-account neonlib-auth-grid">
			<section class="neonlib-panel">
				<h2><?php esc_html_e( 'Sign in', 'neonlib-users' ); ?></h2>
				<?php
				wp_login_form(
					array(
						'redirect'       => $redirect,
						'label_username' => __( 'Username or email', 'neonlib-users' ),
						'label_password' => __( 'Password', 'neonlib-users' ),
						'label_log_in'   => __( 'Sign in', 'neonlib-users' ),
						'remember'       => true,
					)
				);
				?>
				<p><a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'neonlib-users' ); ?></a></p>
			</section>

			<section class="neonlib-panel">
				<h2><?php esc_html_e( 'Create a NeonLib account', 'neonlib-users' ); ?></h2>
				<?php echo $messages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped strings. ?>
				<p><?php esc_html_e( 'We will send a link to verify your email address after registration.', 'neonlib-users' ); ?></p>
				<form method="post" action="<?php echo esc_url( $redirect ); ?>">
					<?php wp_nonce_field( 'neonlib_register_user', 'neonlib_registration_nonce' ); ?>
					<input type="hidden" name="neonlib_action" value="register">
					<p>
						<label for="neonlib_display_name"><?php esc_html_e( 'Name', 'neonlib-users' ); ?></label>
						<input id="neonlib_display_name" name="display_name" type="text" autocomplete="name" required>
					</p>
					<p>
						<label for="neonlib_email"><?php esc_html_e( 'E-mail', 'neonlib-users' ); ?></label>
						<input id="neonlib_email" name="email" type="email" autocomplete="email" required>
					</p>
					<p>
						<label for="neonlib_password"><?php esc_html_e( 'Password', 'neonlib-users' ); ?></label>
						<input id="neonlib_password" name="password" type="password" autocomplete="new-password" minlength="12" required>
					</p>
					<button type="submit"><?php esc_html_e( 'Create account', 'neonlib-users' ); ?></button>
				</form>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function render_dashboard(): string {
		$user = wp_get_current_user();
		if ( ! current_user_can( self::CAP_ACCESS ) ) {
			return '<div class="neonlib-notice neonlib-notice--error">' . esc_html__( 'This account does not have access to the NeonLib dashboard.', 'neonlib-users' ) . '</div>';
		}

		if ( ! $user->has_cap( 'manage_options' ) && ! $this->is_email_verified( $user->ID ) ) {
			return $this->render_verification_pending( $user );
		}

		$account_id   = '';
		$subscriptions = array();
		$api_error    = null;
		if ( ! $user->has_cap( 'manage_options' ) ) {
			$link = $this->ensure_account_link( $user->ID );
			if ( is_wp_error( $link ) ) {
				$api_error = $link;
			} else {
				$account_id = $link;
				$result = ( new NeonLib_Api_Client() )->subscriptions( $user->ID );
				if ( is_wp_error( $result ) ) {
					$api_error = $result;
				} else {
					$subscriptions = $result;
				}
			}
		}

		ob_start();
		?>
		<div class="neonlib-account">
			<?php echo $this->subscription_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped strings. ?>
			<header class="neonlib-dashboard-header">
				<div>
					<p class="neonlib-eyebrow"><?php esc_html_e( 'NeonLib account', 'neonlib-users' ); ?></p>
					<h2><?php echo esc_html( sprintf( __( 'Hello, %s', 'neonlib-users' ), $user->display_name ) ); ?></h2>
				</div>
				<a class="neonlib-button neonlib-button--secondary" href="<?php echo esc_url( wp_logout_url( $this->account_url() ) ); ?>"><?php esc_html_e( 'Sign out', 'neonlib-users' ); ?></a>
			</header>

			<div class="neonlib-dashboard-grid">
				<?php if ( $api_error instanceof WP_Error ) : ?>
					<div class="neonlib-notice neonlib-notice--error">
						<?php esc_html_e( 'We could not connect to the NeonLib service. Please try again later.', 'neonlib-users' ); ?>
					</div>
				<?php endif; ?>
				<section class="neonlib-panel">
					<h3><?php esc_html_e( 'Your profile', 'neonlib-users' ); ?></h3>
					<dl>
						<dt><?php esc_html_e( 'Name', 'neonlib-users' ); ?></dt>
						<dd><?php echo esc_html( $user->display_name ); ?></dd>
						<dt><?php esc_html_e( 'E-mail', 'neonlib-users' ); ?></dt>
						<dd><?php echo esc_html( $user->user_email ); ?></dd>
						<?php if ( '' !== $account_id ) : ?>
							<dt><?php esc_html_e( 'NeonLib account ID', 'neonlib-users' ); ?></dt>
							<dd><code><?php echo esc_html( $account_id ); ?></code></dd>
						<?php endif; ?>
					</dl>
				</section>

				<section class="neonlib-panel">
					<h3><?php esc_html_e( 'Subscriptions', 'neonlib-users' ); ?></h3>
					<?php if ( $api_error instanceof WP_Error ) : ?>
						<p><?php esc_html_e( 'Subscriptions nisu dostupni dok se veza sa servisom ne uspostavi.', 'neonlib-users' ); ?></p>
					<?php elseif ( array() === $subscriptions ) : ?>
						<p><?php esc_html_e( 'You do not have any subscriptions yet.', 'neonlib-users' ); ?></p>
					<?php else : ?>
						<ul class="neonlib-subscription-list">
							<?php foreach ( $subscriptions as $subscription ) : ?>
								<li>
									<strong><?php echo esc_html( (string) ( $subscription['title'] ?? '' ) ); ?></strong>
									<code><?php echo esc_html( (string) ( $subscription['package_id'] ?? '' ) ); ?></code>
									<span><?php echo esc_html( (string) ( $subscription['status'] ?? '' ) ); ?></span>
									<details>
										<summary><?php esc_html_e( 'Edit', 'neonlib-users' ); ?></summary>
										<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
											<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
											<input type="hidden" name="neonlib_action" value="update_subscription">
											<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $subscription['package_id'] ); ?>">
											<label><?php esc_html_e( 'Title', 'neonlib-users' ); ?><input name="title" value="<?php echo esc_attr( (string) $subscription['title'] ); ?>" maxlength="190" required></label>
											<label><?php esc_html_e( 'Description', 'neonlib-users' ); ?><textarea name="description" maxlength="10000"><?php echo esc_textarea( (string) $subscription['description'] ); ?></textarea></label>
											<label><?php esc_html_e( 'Language', 'neonlib-users' ); ?><input name="language" value="<?php echo esc_attr( (string) $subscription['language'] ); ?>" maxlength="20" required></label>
											<label><?php esc_html_e( 'Visibility', 'neonlib-users' ); ?><select name="visibility"><option value="private" <?php selected( 'private', $subscription['visibility'] ); ?>>private</option><option value="public" <?php selected( 'public', $subscription['visibility'] ); ?>>public</option></select></label>
											<button type="submit"><?php esc_html_e( 'Save', 'neonlib-users' ); ?></button>
										</form>
									</details>
									<details>
										<summary><?php esc_html_e( 'Publish a new version', 'neonlib-users' ); ?></summary>
										<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
											<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
											<input type="hidden" name="neonlib_action" value="publish_subscription">
											<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $subscription['package_id'] ); ?>">
											<label><?php esc_html_e( 'Documents (JSON)', 'neonlib-users' ); ?><textarea name="documents_json" rows="8" required>[{"id":"intro","title":"Introduction","content":"Document content"}]</textarea></label>
											<button type="submit"><?php esc_html_e( 'Publish version', 'neonlib-users' ); ?></button>
										</form>
									</details>
									<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this subscription and all its versions?', 'neonlib-users' ) ); ?>');">
										<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
										<input type="hidden" name="neonlib_action" value="delete_subscription">
										<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $subscription['package_id'] ); ?>">
										<button class="neonlib-button--danger" type="submit"><?php esc_html_e( 'Delete', 'neonlib-users' ); ?></button>
									</form>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>

				<?php if ( ! $api_error instanceof WP_Error && ! $user->has_cap( 'manage_options' ) ) : ?>
					<section class="neonlib-panel">
						<h3><?php esc_html_e( 'New subscription', 'neonlib-users' ); ?></h3>
						<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
							<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
							<input type="hidden" name="neonlib_action" value="create_subscription">
							<label><?php esc_html_e( 'Package ID', 'neonlib-users' ); ?><input name="package_id" pattern="[a-z0-9][a-z0-9._-]{2,189}" placeholder="my.subscription" required></label>
							<label><?php esc_html_e( 'Title', 'neonlib-users' ); ?><input name="title" maxlength="190" required></label>
							<label><?php esc_html_e( 'Description', 'neonlib-users' ); ?><textarea name="description" maxlength="10000"></textarea></label>
							<label><?php esc_html_e( 'Language', 'neonlib-users' ); ?><input name="language" value="en" maxlength="20" required></label>
							<label><?php esc_html_e( 'Visibility', 'neonlib-users' ); ?><select name="visibility"><option value="private">private</option><option value="public">public</option></select></label>
							<button type="submit"><?php esc_html_e( 'Create subscription', 'neonlib-users' ); ?></button>
						</form>
					</section>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function render_verification_pending( WP_User $user ): string {
		$status = isset( $_GET['neonlib_verification'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_verification'] ) ) : '';
		$notice = '';

		if ( 'sent' === $status ) {
			$notice = '<div class="neonlib-notice neonlib-notice--success">' . esc_html__( 'A new verification link has been sent.', 'neonlib-users' ) . '</div>';
		} elseif ( 'mail_failed' === $status ) {
			$notice = '<div class="neonlib-notice neonlib-notice--error">' . esc_html__( 'The message could not be sent. Please try again later.', 'neonlib-users' ) . '</div>';
		} elseif ( 'invalid' === $status || 'expired' === $status ) {
			$notice = '<div class="neonlib-notice neonlib-notice--error">' . esc_html__( 'The link is invalid or has expired. Request a new one.', 'neonlib-users' ) . '</div>';
		}

		ob_start();
		?>
		<div class="neonlib-account">
			<section class="neonlib-panel">
				<p class="neonlib-eyebrow"><?php esc_html_e( 'One more step', 'neonlib-users' ); ?></p>
				<h2><?php esc_html_e( 'Verify your email address', 'neonlib-users' ); ?></h2>
				<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped strings. ?>
				<p><?php echo esc_html( sprintf( __( 'We sent a verification link to %s.', 'neonlib-users' ), $user->user_email ) ); ?></p>
				<p><?php esc_html_e( 'After verification you will have full access to the NeonLib dashboard.', 'neonlib-users' ); ?></p>
				<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
					<?php wp_nonce_field( 'neonlib_resend_verification', 'neonlib_resend_nonce' ); ?>
					<input type="hidden" name="neonlib_action" value="resend_verification">
					<button type="submit"><?php esc_html_e( 'Resend email', 'neonlib-users' ); ?></button>
				</form>
				<p><a href="<?php echo esc_url( wp_logout_url( $this->account_url() ) ); ?>"><?php esc_html_e( 'Sign out', 'neonlib-users' ); ?></a></p>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_registration(): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		$action = isset( $_POST['neonlib_action'] ) ? sanitize_key( wp_unslash( $_POST['neonlib_action'] ) ) : '';
		if ( 'register' !== $action ) {
			return;
		}

		$nonce = isset( $_POST['neonlib_registration_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['neonlib_registration_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'neonlib_register_user' ) ) {
			$this->redirect_with_status( 'invalid_request' );
		}

		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$password     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( ! is_email( $email ) || '' === $display_name || strlen( $password ) < 12 ) {
			$this->redirect_with_status( 'invalid_fields' );
		}

		if ( email_exists( $email ) ) {
			$this->redirect_with_status( 'email_exists' );
		}

		$username = $this->username_from_email( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $display_name,
				'role'         => self::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->redirect_with_status( 'registration_failed' );
		}

		update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '0' );
		$this->send_verification_email( $user_id );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', $username, get_user_by( 'id', $user_id ) );

		wp_safe_redirect( $this->account_url() );
		exit;
	}

	public function handle_email_verification(): void {
		$action = isset( $_GET['neonlib_action'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_action'] ) ) : '';
		if ( 'verify_email' !== $action ) {
			return;
		}

		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$hash    = (string) get_user_meta( $user_id, self::META_VERIFY_TOKEN, true );
		$expires = (int) get_user_meta( $user_id, self::META_VERIFY_EXPIRES, true );

		if ( ! $user_id || '' === $token || '' === $hash || ! hash_equals( $hash, $this->hash_token( $token ) ) ) {
			$this->redirect_verification_status( 'invalid' );
		}

		if ( $expires < time() ) {
			$this->redirect_verification_status( 'expired' );
		}

		update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '1' );
		delete_user_meta( $user_id, self::META_VERIFY_TOKEN );
		delete_user_meta( $user_id, self::META_VERIFY_EXPIRES );
		$this->ensure_account_link( $user_id );

		$this->redirect_verification_status( 'verified' );
	}

	public function handle_verification_resend(): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		$action = isset( $_POST['neonlib_action'] ) ? sanitize_key( wp_unslash( $_POST['neonlib_action'] ) ) : '';
		if ( 'resend_verification' !== $action || ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['neonlib_resend_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['neonlib_resend_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'neonlib_resend_verification' ) ) {
			$this->redirect_verification_status( 'invalid' );
		}

		$user_id = get_current_user_id();
		if ( $this->is_email_verified( $user_id ) ) {
			$this->redirect_verification_status( 'verified' );
		}

		$this->redirect_verification_status( $this->send_verification_email( $user_id ) ? 'sent' : 'mail_failed' );
	}

	public function handle_subscription_action(): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$action = isset( $_POST['neonlib_action'] ) ? sanitize_key( wp_unslash( $_POST['neonlib_action'] ) ) : '';
		if ( ! in_array( $action, array( 'create_subscription', 'update_subscription', 'delete_subscription', 'publish_subscription' ), true ) ) {
			return;
		}
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_ACCESS ) || current_user_can( 'manage_options' ) ) {
			$this->redirect_subscription_status( 'forbidden' );
		}
		$user_id = get_current_user_id();
		if ( ! $this->is_email_verified( $user_id ) ) {
			$this->redirect_subscription_status( 'email_unverified' );
		}
		$nonce = isset( $_POST['neonlib_subscription_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['neonlib_subscription_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'neonlib_subscription_action' ) ) {
			$this->redirect_subscription_status( 'invalid_request' );
		}
		if ( is_wp_error( $this->ensure_account_link( $user_id ) ) ) {
			$this->redirect_subscription_status( 'api_error' );
		}

		$package_id = isset( $_POST['package_id'] ) ? strtolower( trim( (string) wp_unslash( $_POST['package_id'] ) ) ) : '';
		if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]{2,189}$/', $package_id ) ) {
			$this->redirect_subscription_status( 'invalid_fields' );
		}
		$client = new NeonLib_Api_Client();

		if ( 'delete_subscription' === $action ) {
			$result = $client->delete_subscription( $user_id, $package_id );
			$this->redirect_subscription_status( is_wp_error( $result ) ? 'api_error' : 'deleted' );
		}

		if ( 'publish_subscription' === $action ) {
			$json = isset( $_POST['documents_json'] ) ? (string) wp_unslash( $_POST['documents_json'] ) : '';
			try {
				$documents = json_decode( $json, true, 64, JSON_THROW_ON_ERROR );
			} catch ( JsonException ) {
				$this->redirect_subscription_status( 'invalid_json' );
			}
			if ( ! is_array( $documents ) || ! array_is_list( $documents ) ) {
				$this->redirect_subscription_status( 'invalid_json' );
			}
			$result = $client->publish_version( $user_id, $package_id, $documents );
			$this->redirect_subscription_status( is_wp_error( $result ) ? 'api_error' : 'published' );
		}

		$payload = array(
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'language'    => isset( $_POST['language'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['language'] ) ) ) : '',
			'visibility'  => isset( $_POST['visibility'] ) ? strtolower( sanitize_key( wp_unslash( $_POST['visibility'] ) ) ) : '',
		);
		if ( '' === $payload['title'] || ! preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $payload['language'] )
			|| ! in_array( $payload['visibility'], array( 'private', 'public' ), true ) ) {
			$this->redirect_subscription_status( 'invalid_fields' );
		}
		if ( 'create_subscription' === $action ) {
			$payload['package_id'] = $package_id;
			$result = $client->create_subscription( $user_id, $payload );
			$this->redirect_subscription_status( is_wp_error( $result ) ? 'api_error' : 'created' );
		}

		$result = $client->update_subscription( $user_id, $package_id, $payload );
		$this->redirect_subscription_status( is_wp_error( $result ) ? 'api_error' : 'updated' );
	}

	private function subscription_notice(): string {
		$status = isset( $_GET['neonlib_subscription'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_subscription'] ) ) : '';
		$success = array(
			'created' => __( 'Subscription created.', 'neonlib-users' ),
			'updated' => __( 'Subscription updated.', 'neonlib-users' ),
			'deleted' => __( 'Subscription i njegove verzije su obrisani.', 'neonlib-users' ),
			'published' => __( 'A new version has been published.', 'neonlib-users' ),
		);
		$errors = array(
			'forbidden' => __( 'Nemate ovlast za ovu akciju.', 'neonlib-users' ),
			'email_unverified' => __( 'Verify your email before managing subscriptions.', 'neonlib-users' ),
			'invalid_request' => __( 'The request expired. Please try again.', 'neonlib-users' ),
			'invalid_fields' => __( 'Provjerite unesene podatke.', 'neonlib-users' ),
			'invalid_json' => __( 'Documents must be a valid JSON list.', 'neonlib-users' ),
			'api_error' => __( 'The NeonLib service could not complete the action. Please try again.', 'neonlib-users' ),
		);
		if ( isset( $success[ $status ] ) ) {
			return '<div class="neonlib-notice neonlib-notice--success">' . esc_html( $success[ $status ] ) . '</div>';
		}
		return isset( $errors[ $status ] ) ? '<div class="neonlib-notice neonlib-notice--error">' . esc_html( $errors[ $status ] ) . '</div>' : '';
	}

	private function redirect_subscription_status( string $status ): never {
		wp_safe_redirect( add_query_arg( 'neonlib_subscription', $status, $this->account_url() ) );
		exit;
	}

	private function send_verification_email( int $user_id ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$token = bin2hex( random_bytes( 32 ) );
		update_user_meta( $user_id, self::META_VERIFY_TOKEN, $this->hash_token( $token ) );
		update_user_meta( $user_id, self::META_VERIFY_EXPIRES, time() + DAY_IN_SECONDS );

		$verification_url = add_query_arg(
			array(
				'neonlib_action' => 'verify_email',
				'user'           => $user_id,
				'token'          => $token,
			),
			$this->account_url()
		);

		$subject = __( 'Verify your NeonLib account', 'neonlib-users' );
		$message = sprintf(
			__( "Hello %1\$s,\n\nVerify your email address by opening this link:\n%2\$s\n\nThe link is valid for 24 hours. If you did not create a NeonLib account, ignore this message.", 'neonlib-users' ),
			$user->display_name,
			$verification_url
		);

		return wp_mail( $user->user_email, $subject, $message );
	}

	private function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private function is_email_verified( int $user_id ): bool {
		return '1' === (string) get_user_meta( $user_id, self::META_EMAIL_VERIFIED, true );
	}

	private function ensure_account_link( int $user_id ): string|WP_Error {
		$account_id = (string) get_user_meta( $user_id, self::META_ACCOUNT_ID, true );
		if ( preg_match( '/^acc_[0-9a-hjkmnp-tv-z]{26}$/', $account_id ) ) {
			return $account_id;
		}

		if ( ! $this->is_email_verified( $user_id ) ) {
			return new WP_Error( 'neonlib_email_not_verified', __( 'The email address has not been verified.', 'neonlib-users' ) );
		}

		$response = ( new NeonLib_Api_Client() )->link_account( $user_id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$account_id = (string) ( $response['data']['account_id'] ?? '' );
		if ( ! preg_match( '/^acc_[0-9a-hjkmnp-tv-z]{26}$/', $account_id ) ) {
			return new WP_Error( 'neonlib_invalid_account_id', __( 'NeonLib API nije vratio valjan account ID.', 'neonlib-users' ) );
		}
		update_user_meta( $user_id, self::META_ACCOUNT_ID, $account_id );
		return $account_id;
	}

	private function redirect_verification_status( string $status ): void {
		wp_safe_redirect( add_query_arg( 'neonlib_verification', $status, $this->account_url() ) );
		exit;
	}

	private function username_from_email( string $email ): string {
		$base     = sanitize_user( strstr( $email, '@', true ), true );
		$base     = $base ?: 'neonlib-user';
		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			$username = $base . '-' . $suffix;
			++$suffix;
		}

		return $username;
	}

	private function redirect_with_status( string $status ): void {
		wp_safe_redirect( add_query_arg( 'neonlib_registration', $status, $this->account_url() ) );
		exit;
	}

	private function registration_messages(): string {
		$status = isset( $_GET['neonlib_registration'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_registration'] ) ) : '';
		$verification_status = isset( $_GET['neonlib_verification'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_verification'] ) ) : '';

		if ( 'verified' === $verification_status ) {
			return '<div class="neonlib-notice neonlib-notice--success">' . esc_html__( 'Your email address has been verified. You can now sign in.', 'neonlib-users' ) . '</div>';
		}

		if ( 'invalid' === $verification_status || 'expired' === $verification_status ) {
			return '<div class="neonlib-notice neonlib-notice--error">' . esc_html__( 'The verification link is invalid or has expired.', 'neonlib-users' ) . '</div>';
		}

		$messages = array(
			'invalid_request'     => __( 'The request expired. Please try again.', 'neonlib-users' ),
			'invalid_fields'      => __( 'Check the information entered. The password must be at least 12 characters.', 'neonlib-users' ),
			'email_exists'        => __( 'An account with this email already exists. Try signing in.', 'neonlib-users' ),
			'registration_failed' => __( 'The account could not be created. Please try again.', 'neonlib-users' ),
		);

		if ( ! isset( $messages[ $status ] ) ) {
			return '';
		}

		return '<div class="neonlib-notice neonlib-notice--error">' . esc_html( $messages[ $status ] ) . '</div>';
	}

	public function login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( $user instanceof WP_User && $user->has_cap( self::CAP_ACCESS ) && ! $user->has_cap( 'manage_options' ) ) {
			return $this->account_url();
		}

		return $redirect_to;
	}

	private function account_url(): string {
		$page_id = (int) get_option( self::PAGE_OPTION );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/neonlib-racun/' );

		return $url ?: home_url( '/neonlib-racun/' );
	}
}

