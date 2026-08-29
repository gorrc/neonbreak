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
	private const META_PUBLISHER_NAME = 'neonlib_publisher_name';

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
		add_action( 'init', array( $this, 'handle_profile_action' ) );
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
		wp_enqueue_script(
			'neonlib-users-document-builder',
			NEONLIB_USERS_URL . 'assets/js/document-builder.js',
			array(),
			NEONLIB_USERS_VERSION,
			true
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
		$view = isset( $_GET['neonlib_view'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_view'] ) ) : 'subscriptions';
		if ( ! in_array( $view, array( 'account', 'subscriptions', 'new-subscription', 'edit-subscription', 'publish' ), true ) ) {
			$view = 'subscriptions';
		}
		$new_subscription_values = array( 'package_id' => '', 'title' => '', 'description' => '', 'language' => 'en', 'visibility' => 'private', 'documents_json' => '[]' );
		if ( 'new-subscription' === $view ) {
			$saved_values = get_transient( 'neonlib_subscription_form_' . $user->ID );
			if ( is_array( $saved_values ) ) {
				$new_subscription_values = array_merge( $new_subscription_values, array_intersect_key( $saved_values, $new_subscription_values ) );
				delete_transient( 'neonlib_subscription_form_' . $user->ID );
			}
		}
		$publish_package_id = isset( $_GET['package_id'] ) ? sanitize_text_field( wp_unslash( $_GET['package_id'] ) ) : '';
		if ( ! current_user_can( self::CAP_ACCESS ) ) {
			return '<div class="neonlib-notice neonlib-notice--error">' . esc_html__( 'This account does not have access to the NeonLib dashboard.', 'neonlib-users' ) . '</div>';
		}

		if ( ! $user->has_cap( 'manage_options' ) && ! $this->is_email_verified( $user->ID ) ) {
			return $this->render_verification_pending( $user );
		}

		$account_id   = '';
		$publisher_name = '';
		$subscriptions = array();
		$api_error    = null;
		if ( ! $user->has_cap( 'manage_options' ) ) {
			$link = $this->ensure_account_link( $user->ID );
			if ( is_wp_error( $link ) ) {
				$api_error = $link;
			} else {
				$account_id = $link;
				$publisher = ( new NeonLib_Api_Client() )->publisher( $user->ID );
				if ( ! is_wp_error( $publisher ) ) {
					$publisher_name = trim( (string) ( $publisher['display_name'] ?? '' ) );
					if ( 'NeonLib account' === $publisher_name ) {
						$publisher_name = '';
					}
					if ( '' !== $publisher_name ) {
						update_user_meta( $user->ID, self::META_PUBLISHER_NAME, $publisher_name );
					}
				}
				if ( in_array( $view, array( 'subscriptions', 'edit-subscription', 'publish' ), true ) ) {
					$result = ( new NeonLib_Api_Client() )->subscriptions( $user->ID );
					if ( is_wp_error( $result ) ) {
						$api_error = $result;
					} else {
						$subscriptions = $result;
					}
				}
			}
		}
		$selected_subscription = null;
		$documents_json       = '[]';
		$latest_version       = null;
		if ( in_array( $view, array( 'edit-subscription', 'publish' ), true ) ) {
			foreach ( $subscriptions as $subscription ) {
				if ( hash_equals( (string) ( $subscription['package_id'] ?? '' ), $publish_package_id ) ) {
					$selected_subscription = $subscription;
					break;
				}
			}
			if ( null === $selected_subscription && ! $api_error instanceof WP_Error ) {
				$view = 'subscriptions';
			} elseif ( 'publish' === $view && is_array( $selected_subscription ) ) {
				$client   = new NeonLib_Api_Client();
				$versions = $client->versions( $user->ID, $publish_package_id );
				if ( is_wp_error( $versions ) ) {
					$api_error = $versions;
				} elseif ( isset( $versions[0]['version'] ) ) {
					$latest_version = (int) $versions[0]['version'];
					$version_data   = $client->version( $user->ID, $publish_package_id, $latest_version );
					if ( is_wp_error( $version_data ) ) {
						$api_error = $version_data;
					} elseif ( isset( $version_data['documents'] ) && is_array( $version_data['documents'] ) ) {
						$documents_json = wp_json_encode( $version_data['documents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
					}
				}
			}
			if ( 'publish' === $view ) {
				$pending_documents = get_transient( 'neonlib_documents_form_' . $user->ID . '_' . md5( $publish_package_id ) );
				if ( is_string( $pending_documents ) ) {
					$documents_json = $pending_documents;
					delete_transient( 'neonlib_documents_form_' . $user->ID . '_' . md5( $publish_package_id ) );
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
			<nav class="neonlib-dashboard-tabs" aria-label="<?php esc_attr_e( 'Account sections', 'neonlib-users' ); ?>">
				<a class="<?php echo 'account' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'account', $this->account_url() ) ); ?>" <?php echo 'account' === $view ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Account', 'neonlib-users' ); ?></a>
				<a class="<?php echo in_array( $view, array( 'subscriptions', 'new-subscription', 'edit-subscription', 'publish' ), true ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'subscriptions', $this->account_url() ) ); ?>" <?php echo in_array( $view, array( 'subscriptions', 'new-subscription', 'edit-subscription', 'publish' ), true ) ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Subscriptions', 'neonlib-users' ); ?></a>
			</nav>

			<div class="neonlib-dashboard-grid">
				<?php if ( $api_error instanceof WP_Error ) : ?>
					<div class="neonlib-notice neonlib-notice--error">
						<?php esc_html_e( 'We could not connect to the NeonLib service. Please try again later.', 'neonlib-users' ); ?>
					</div>
				<?php endif; ?>
				<?php if ( 'account' === $view ) : ?>
				<section class="neonlib-panel neonlib-profile-panel">
					<h3><?php esc_html_e( 'Your profile', 'neonlib-users' ); ?></h3>
					<?php echo $this->profile_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped strings. ?>
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
					<?php if ( ! $user->has_cap( 'manage_options' ) ) : ?>
					<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
						<?php wp_nonce_field( 'neonlib_profile_action', 'neonlib_profile_nonce' ); ?>
						<input type="hidden" name="neonlib_action" value="update_profile">
						<label for="neonlib_publisher_name"><?php esc_html_e( 'Public publisher name', 'neonlib-users' ); ?></label>
						<input id="neonlib_publisher_name" name="publisher_name" type="text" value="<?php echo esc_attr( $publisher_name ); ?>" maxlength="160" required>
						<p class="description"><?php esc_html_e( 'This name is shown next to all your subscriptions in the NeonLib mobile app.', 'neonlib-users' ); ?></p>
						<button type="submit"><?php esc_html_e( 'Save publisher name', 'neonlib-users' ); ?></button>
					</form>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php if ( 'subscriptions' === $view ) : ?>
				<section class="neonlib-panel neonlib-subscriptions-panel">
					<div class="neonlib-list-heading"><div><p class="neonlib-eyebrow"><?php esc_html_e( 'Knowledge packages', 'neonlib-users' ); ?></p><h3><?php esc_html_e( 'Subscriptions', 'neonlib-users' ); ?></h3></div><?php if ( ! $user->has_cap( 'manage_options' ) ) : ?><a class="neonlib-button" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'new-subscription', $this->account_url() ) ); ?>"><?php esc_html_e( 'Add New', 'neonlib-users' ); ?></a><?php endif; ?></div>
					<?php if ( $user->has_cap( 'manage_options' ) ) : ?>
						<div class="neonlib-empty-state"><p><?php esc_html_e( 'Administrator accounts manage all subscriptions in NeonLib Admin.', 'neonlib-users' ); ?></p><a class="neonlib-button" href="<?php echo esc_url( admin_url( 'admin.php?page=neonlib-admin&tab=subscriptions' ) ); ?>"><?php esc_html_e( 'Open NeonLib Admin', 'neonlib-users' ); ?></a></div>
					<?php elseif ( $api_error instanceof WP_Error ) : ?>
						<p><?php esc_html_e( 'Subscriptions are unavailable until the connection to the NeonLib service is restored.', 'neonlib-users' ); ?></p>
					<?php elseif ( array() === $subscriptions ) : ?>
						<div class="neonlib-empty-state"><p><?php esc_html_e( 'You do not have any subscriptions yet.', 'neonlib-users' ); ?></p><a class="neonlib-button" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'new-subscription', $this->account_url() ) ); ?>"><?php esc_html_e( 'Create your first subscription', 'neonlib-users' ); ?></a></div>
					<?php else : ?>
						<div class="neonlib-table-wrap"><table class="neonlib-subscriptions-table"><thead><tr><th><?php esc_html_e( 'Title', 'neonlib-users' ); ?></th><th><?php esc_html_e( 'Package ID', 'neonlib-users' ); ?></th><th><?php esc_html_e( 'Status', 'neonlib-users' ); ?></th><th><?php esc_html_e( 'Visibility', 'neonlib-users' ); ?></th><th><?php esc_html_e( 'Updated', 'neonlib-users' ); ?></th><th><?php esc_html_e( 'Actions', 'neonlib-users' ); ?></th></tr></thead><tbody>
							<?php foreach ( $subscriptions as $subscription ) : ?>
								<tr><td><strong><?php echo esc_html( (string) ( $subscription['title'] ?? '' ) ); ?></strong></td><td><code><?php echo esc_html( (string) ( $subscription['package_id'] ?? '' ) ); ?></code></td><td><span class="neonlib-status"><?php echo esc_html( (string) ( $subscription['status'] ?? '' ) ); ?></span></td><td><?php echo esc_html( (string) ( $subscription['visibility'] ?? '' ) ); ?></td><td><?php echo esc_html( isset( $subscription['updated_at'] ) ? wp_date( get_option( 'date_format' ), strtotime( (string) $subscription['updated_at'] ) ) : '—' ); ?></td><td><div class="neonlib-row-actions"><a href="<?php echo esc_url( add_query_arg( array( 'neonlib_view' => 'edit-subscription', 'package_id' => (string) $subscription['package_id'] ), $this->account_url() ) ); ?>"><?php esc_html_e( 'Edit', 'neonlib-users' ); ?></a><a href="<?php echo esc_url( add_query_arg( array( 'neonlib_view' => 'publish', 'package_id' => (string) $subscription['package_id'] ), $this->account_url() ) ); ?>"><?php esc_html_e( 'Documents', 'neonlib-users' ); ?></a><form method="post" action="<?php echo esc_url( $this->account_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this subscription and all its versions?', 'neonlib-users' ) ); ?>');">
										<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
										<input type="hidden" name="neonlib_action" value="delete_subscription">
										<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $subscription['package_id'] ); ?>">
										<button class="neonlib-link-danger" type="submit"><?php esc_html_e( 'Delete', 'neonlib-users' ); ?></button>
									</form></div></td></tr>
							<?php endforeach; ?>
						</tbody></table></div>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php if ( 'new-subscription' === $view && ! $api_error instanceof WP_Error && ! $user->has_cap( 'manage_options' ) ) : ?>
				<section class="neonlib-panel neonlib-subscription-form-panel">
					<a class="neonlib-back-link" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'subscriptions', $this->account_url() ) ); ?>">&larr; <?php esc_html_e( 'Back to subscriptions', 'neonlib-users' ); ?></a>
					<?php if ( '' === $publisher_name ) : ?>
					<div class="neonlib-notice neonlib-notice--error">
						<p><?php esc_html_e( 'Set your public publisher name before creating a subscription.', 'neonlib-users' ); ?></p>
						<a class="neonlib-button" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'account', $this->account_url() ) ); ?>"><?php esc_html_e( 'Open account settings', 'neonlib-users' ); ?></a>
					</div>
					<?php else : ?>
					<p class="neonlib-eyebrow"><?php esc_html_e( 'Add New', 'neonlib-users' ); ?></p><h3><?php esc_html_e( 'New subscription', 'neonlib-users' ); ?></h3>
					<form class="neonlib-document-form" method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
						<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
						<input type="hidden" name="neonlib_action" value="create_subscription">
						<label><?php esc_html_e( 'Package ID', 'neonlib-users' ); ?><input name="package_id" value="<?php echo esc_attr( (string) $new_subscription_values['package_id'] ); ?>" pattern="[a-z0-9][a-z0-9._-]{2,189}" placeholder="my.subscription" required></label>
						<label><?php esc_html_e( 'Title', 'neonlib-users' ); ?><input name="title" value="<?php echo esc_attr( (string) $new_subscription_values['title'] ); ?>" maxlength="190" required></label>
						<label class="neonlib-form-wide"><?php esc_html_e( 'Description', 'neonlib-users' ); ?><textarea name="description" maxlength="10000"><?php echo esc_textarea( (string) $new_subscription_values['description'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Language', 'neonlib-users' ); ?><input name="language" value="<?php echo esc_attr( (string) $new_subscription_values['language'] ); ?>" maxlength="20" required></label>
						<label><?php esc_html_e( 'Visibility', 'neonlib-users' ); ?><select name="visibility"><option value="private" <?php selected( 'private', $new_subscription_values['visibility'] ); ?>>private</option><option value="public" <?php selected( 'public', $new_subscription_values['visibility'] ); ?>>public</option></select></label>
						<div class="neonlib-form-wide neonlib-create-documents">
							<div class="neonlib-document-toolbar"><div><h4><?php esc_html_e( 'Documents', 'neonlib-users' ); ?></h4><p><?php esc_html_e( 'Add text manually or import TXT, Markdown, HTML and CSV files. Arrange the order before publishing.', 'neonlib-users' ); ?></p></div><div><label class="neonlib-file-button"><?php esc_html_e( 'Import files', 'neonlib-users' ); ?><input class="neonlib-document-files" type="file" multiple accept=".txt,.md,.markdown,.html,.htm,.csv,text/plain,text/markdown,text/html,text/csv"></label><button class="neonlib-add-document" type="button"><?php esc_html_e( 'Add document', 'neonlib-users' ); ?></button></div></div>
							<div class="neonlib-document-builder"></div>
							<details class="neonlib-raw-json"><summary><?php esc_html_e( 'Advanced: raw JSON', 'neonlib-users' ); ?></summary><label><?php esc_html_e( 'Documents (JSON)', 'neonlib-users' ); ?><textarea class="neonlib-documents-json" name="documents_json" rows="8" required><?php echo esc_textarea( (string) $new_subscription_values['documents_json'] ); ?></textarea></label></details>
						</div>
						<div class="neonlib-form-actions"><p class="neonlib-json-status" aria-live="polite"></p><button type="submit"><?php esc_html_e( 'Create and publish', 'neonlib-users' ); ?></button></div>
					</form>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php if ( 'edit-subscription' === $view && is_array( $selected_subscription ) ) : ?>
				<section class="neonlib-panel neonlib-subscription-form-panel">
					<a class="neonlib-back-link" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'subscriptions', $this->account_url() ) ); ?>">&larr; <?php esc_html_e( 'Back to subscriptions', 'neonlib-users' ); ?></a>
					<p class="neonlib-eyebrow"><?php echo esc_html( (string) $selected_subscription['package_id'] ); ?></p><h3><?php esc_html_e( 'Edit subscription', 'neonlib-users' ); ?></h3>
					<form method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
						<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
						<input type="hidden" name="neonlib_action" value="update_subscription"><input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $selected_subscription['package_id'] ); ?>">
						<label><?php esc_html_e( 'Title', 'neonlib-users' ); ?><input name="title" value="<?php echo esc_attr( (string) $selected_subscription['title'] ); ?>" maxlength="190" required></label>
						<label><?php esc_html_e( 'Language', 'neonlib-users' ); ?><input name="language" value="<?php echo esc_attr( (string) $selected_subscription['language'] ); ?>" maxlength="20" required></label>
						<label class="neonlib-form-wide"><?php esc_html_e( 'Description', 'neonlib-users' ); ?><textarea name="description" maxlength="10000"><?php echo esc_textarea( (string) $selected_subscription['description'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Visibility', 'neonlib-users' ); ?><select name="visibility"><option value="private" <?php selected( 'private', $selected_subscription['visibility'] ); ?>>private</option><option value="public" <?php selected( 'public', $selected_subscription['visibility'] ); ?>>public</option></select></label>
						<div class="neonlib-form-actions"><button type="submit"><?php esc_html_e( 'Save changes', 'neonlib-users' ); ?></button></div>
					</form>
				</section>
				<?php endif; ?>

				<?php if ( 'publish' === $view && is_array( $selected_subscription ) ) : ?>
				<section class="neonlib-panel neonlib-publish-panel">
					<a class="neonlib-back-link" href="<?php echo esc_url( add_query_arg( 'neonlib_view', 'subscriptions', $this->account_url() ) ); ?>">&larr; <?php esc_html_e( 'Back to subscriptions', 'neonlib-users' ); ?></a>
					<div class="neonlib-publish-heading">
						<div><p class="neonlib-eyebrow"><?php echo esc_html( (string) $selected_subscription['package_id'] ); ?></p><h3><?php esc_html_e( 'Documents and publishing', 'neonlib-users' ); ?></h3></div>
						<span><?php echo esc_html( (string) ( $selected_subscription['title'] ?? '' ) ); ?><?php if ( null !== $latest_version ) echo ' · ' . esc_html( sprintf( __( 'Based on version %d', 'neonlib-users' ), $latest_version ) ); ?></span>
					</div>
					<form class="neonlib-document-form" method="post" action="<?php echo esc_url( $this->account_url() ); ?>">
						<?php wp_nonce_field( 'neonlib_subscription_action', 'neonlib_subscription_nonce' ); ?>
						<input type="hidden" name="neonlib_action" value="publish_subscription">
						<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $selected_subscription['package_id'] ); ?>">
						<div class="neonlib-document-toolbar">
							<div><h4><?php esc_html_e( 'Documents', 'neonlib-users' ); ?></h4><p><?php esc_html_e( 'Add text manually or import TXT, Markdown, HTML and CSV files. Arrange the order before publishing.', 'neonlib-users' ); ?></p></div>
							<div>
								<label class="neonlib-file-button"><?php esc_html_e( 'Import files', 'neonlib-users' ); ?><input class="neonlib-document-files" type="file" multiple accept=".txt,.md,.markdown,.html,.htm,.csv,text/plain,text/markdown,text/html,text/csv"></label>
								<button class="neonlib-add-document" type="button"><?php esc_html_e( 'Add document', 'neonlib-users' ); ?></button>
							</div>
						</div>
						<div class="neonlib-document-builder"></div>
						<details class="neonlib-raw-json">
							<summary><?php esc_html_e( 'Advanced: raw JSON', 'neonlib-users' ); ?></summary>
							<label><?php esc_html_e( 'Documents (JSON)', 'neonlib-users' ); ?><textarea class="neonlib-documents-json" name="documents_json" rows="8" required><?php echo esc_textarea( $documents_json ); ?></textarea></label>
						</details>
						<div class="neonlib-publish-actions"><p class="neonlib-json-status" aria-live="polite"></p><button type="submit"><?php esc_html_e( 'Publish new version', 'neonlib-users' ); ?></button></div>
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

	public function handle_profile_action(): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$action = isset( $_POST['neonlib_action'] ) ? sanitize_key( wp_unslash( $_POST['neonlib_action'] ) ) : '';
		if ( 'update_profile' !== $action ) {
			return;
		}
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_ACCESS ) || current_user_can( 'manage_options' ) ) {
			$this->redirect_profile_status( 'forbidden' );
		}
		$nonce = isset( $_POST['neonlib_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['neonlib_profile_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'neonlib_profile_action' ) ) {
			$this->redirect_profile_status( 'invalid_request' );
		}
		$user_id = get_current_user_id();
		if ( ! $this->is_email_verified( $user_id ) ) {
			$this->redirect_profile_status( 'email_unverified' );
		}
		$name = isset( $_POST['publisher_name'] ) ? sanitize_text_field( wp_unslash( $_POST['publisher_name'] ) ) : '';
		if ( '' === $name || mb_strlen( $name ) > 160 ) {
			$this->redirect_profile_status( 'invalid_name' );
		}
		if ( is_wp_error( $this->ensure_account_link( $user_id ) ) ) {
			$this->redirect_profile_status( 'api_error' );
		}
		$result = ( new NeonLib_Api_Client() )->update_publisher( $user_id, $name );
		if ( is_wp_error( $result ) ) {
			$this->redirect_profile_status( 'api_error' );
		}
		update_user_meta( $user_id, self::META_PUBLISHER_NAME, $name );
		$this->redirect_profile_status( 'updated' );
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
		if ( 'create_subscription' === $action ) {
			$publisher = ( new NeonLib_Api_Client() )->publisher( $user_id );
			$publisher_name = is_wp_error( $publisher ) ? '' : trim( (string) ( $publisher['display_name'] ?? '' ) );
			if ( '' === $publisher_name || 'NeonLib account' === $publisher_name ) {
				$this->redirect_subscription_status( 'publisher_required', 'account' );
			}
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
			$documents_json = isset( $_POST['documents_json'] ) ? (string) wp_unslash( $_POST['documents_json'] ) : '';
			try {
				$documents = json_decode( $documents_json, true, 64, JSON_THROW_ON_ERROR );
			} catch ( JsonException ) {
				$documents = null;
			}
			if ( ! is_array( $documents ) || ! array_is_list( $documents ) || array() === $documents ) {
				set_transient( 'neonlib_subscription_form_' . $user_id, $payload + array( 'documents_json' => $documents_json ), 5 * MINUTE_IN_SECONDS );
				$this->redirect_subscription_status( 'invalid_documents', 'new-subscription' );
			}
			$result = $client->create_subscription( $user_id, $payload );
			if ( is_wp_error( $result ) ) {
				set_transient( 'neonlib_subscription_form_' . $user_id, $payload + array( 'documents_json' => $documents_json ), 5 * MINUTE_IN_SECONDS );
				$status = 'package_id_conflict' === $result->get_error_code() ? 'duplicate_package_id' : 'api_error';
				$this->redirect_subscription_status( $status, 'new-subscription' );
			}
			$publish_result = $client->publish_version( $user_id, $package_id, $documents );
			if ( is_wp_error( $publish_result ) ) {
				set_transient( 'neonlib_documents_form_' . $user_id . '_' . md5( $package_id ), $documents_json, 5 * MINUTE_IN_SECONDS );
				$this->redirect_subscription_status( 'created_publish_failed', 'publish', array( 'package_id' => $package_id ) );
			}
			$this->redirect_subscription_status( 'created_and_published' );
		}

		$result = $client->update_subscription( $user_id, $package_id, $payload );
		$this->redirect_subscription_status( is_wp_error( $result ) ? 'api_error' : 'updated' );
	}

	private function subscription_notice(): string {
		$status = isset( $_GET['neonlib_subscription'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_subscription'] ) ) : '';
		$success = array(
			'created' => __( 'Subscription created.', 'neonlib-users' ),
			'created_and_published' => __( 'Subscription created and its first version published.', 'neonlib-users' ),
			'updated' => __( 'Subscription updated.', 'neonlib-users' ),
			'deleted' => __( 'The subscription and all of its versions were deleted.', 'neonlib-users' ),
			'published' => __( 'A new version has been published.', 'neonlib-users' ),
		);
		$errors = array(
			'forbidden' => __( 'You do not have permission to perform this action.', 'neonlib-users' ),
			'email_unverified' => __( 'Verify your email before managing subscriptions.', 'neonlib-users' ),
			'invalid_request' => __( 'The request expired. Please try again.', 'neonlib-users' ),
			'invalid_fields' => __( 'Check the entered information and try again.', 'neonlib-users' ),
			'invalid_json' => __( 'Documents must be a valid JSON list.', 'neonlib-users' ),
			'invalid_documents' => __( 'Add at least one valid document before creating the subscription.', 'neonlib-users' ),
			'duplicate_package_id' => __( 'A subscription with this Package ID already exists. Choose a different Package ID.', 'neonlib-users' ),
			'created_publish_failed' => __( 'The subscription was created, but its first version could not be published. Review the documents and try publishing again.', 'neonlib-users' ),
			'publisher_required' => __( 'Set your public publisher name before creating a subscription.', 'neonlib-users' ),
			'api_error' => __( 'The NeonLib service could not complete the action. Please try again.', 'neonlib-users' ),
		);
		if ( isset( $success[ $status ] ) ) {
			return '<div class="neonlib-notice neonlib-notice--success">' . esc_html( $success[ $status ] ) . '</div>';
		}
		return isset( $errors[ $status ] ) ? '<div class="neonlib-notice neonlib-notice--error">' . esc_html( $errors[ $status ] ) . '</div>' : '';
	}

	private function profile_notice(): string {
		$status = isset( $_GET['neonlib_profile'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_profile'] ) ) : '';
		if ( 'updated' === $status ) {
			return '<div class="neonlib-notice neonlib-notice--success">' . esc_html__( 'Publisher name saved.', 'neonlib-users' ) . '</div>';
		}
		$errors = array(
			'forbidden'        => __( 'You do not have permission to update this profile.', 'neonlib-users' ),
			'invalid_request'  => __( 'The request expired. Please try again.', 'neonlib-users' ),
			'email_unverified' => __( 'Verify your email before updating the publisher profile.', 'neonlib-users' ),
			'invalid_name'     => __( 'Enter a publisher name containing no more than 160 characters.', 'neonlib-users' ),
			'api_error'        => __( 'The NeonLib service could not save the publisher name. Please try again.', 'neonlib-users' ),
		);
		return isset( $errors[ $status ] ) ? '<div class="neonlib-notice neonlib-notice--error">' . esc_html( $errors[ $status ] ) . '</div>' : '';
	}

	private function redirect_profile_status( string $status ): never {
		wp_safe_redirect( add_query_arg( array( 'neonlib_profile' => $status, 'neonlib_view' => 'account' ), $this->account_url() ) );
		exit;
	}

	private function redirect_subscription_status( string $status, string $view = 'subscriptions', array $extra_args = array() ): never {
		wp_safe_redirect( add_query_arg( array_merge( array( 'neonlib_subscription' => $status, 'neonlib_view' => $view ), $extra_args ), $this->account_url() ) );
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

