<?php

defined( 'ABSPATH' ) || exit;

final class NeonLib_Admin {
	private const CAPABILITY = 'manage_options';
	private const PAGE = 'neonlib-admin';
	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_neonlib_admin_account_update', array( $this, 'handle_account_update' ) );
		add_action( 'admin_post_neonlib_admin_subscription_update', array( $this, 'handle_subscription_update' ) );
	}

	public function register_menu(): void {
		add_menu_page( __( 'NeonLib', 'neonlib-admin' ), __( 'NeonLib', 'neonlib-admin' ), self::CAPABILITY, self::PAGE, array( $this, 'render_page' ), 'dashicons-book-alt', 58 );
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) return;
		wp_enqueue_style( 'neonlib-admin', NEONLIB_ADMIN_URL . 'assets/admin.css', array(), NEONLIB_ADMIN_VERSION );
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Nemate ovlast za ovu stranicu.', 'neonlib-admin' ) );
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'accounts';
		if ( ! in_array( $tab, array( 'accounts', 'subscriptions', 'status' ), true ) ) $tab = 'accounts';
		?>
		<div class="wrap neonlib-admin">
			<h1><?php esc_html_e( 'NeonLib administracija', 'neonlib-admin' ); ?></h1>
			<?php $this->render_notice(); ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( array( 'accounts' => __( 'Accounts', 'neonlib-admin' ), 'subscriptions' => __( 'Subscriptions', 'neonlib-admin' ), 'status' => __( 'API status', 'neonlib-admin' ) ) as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'accounts' === $tab ) $this->render_accounts();
			elseif ( 'subscriptions' === $tab ) $this->render_subscriptions();
			else $this->render_status();
			?>
		</div>
		<?php
	}

	private function render_accounts(): void {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$result = ( new NeonLib_Admin_Api_Client() )->accounts( array( 'status' => $status, 'q' => $search ) );
		?>
		<form class="neonlib-admin-filters" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>"><input type="hidden" name="tab" value="accounts">
			<input name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="acc_…">
			<select name="status"><option value=""><?php esc_html_e( 'All statuses', 'neonlib-admin' ); ?></option><?php foreach ( array( 'active', 'suspended', 'deleted' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select>
			<button class="button"><?php esc_html_e( 'Filter', 'neonlib-admin' ); ?></button>
		</form>
		<?php if ( is_wp_error( $result ) ) { $this->render_api_error( $result ); return; } $rows = (array) ( $result['data'] ?? array() ); ?>
		<table class="widefat striped neonlib-admin-table"><thead><tr><th>Account ID</th><th><?php esc_html_e( 'Status', 'neonlib-admin' ); ?></th><th><?php esc_html_e( 'Linkovi', 'neonlib-admin' ); ?></th><th><?php esc_html_e( 'Subscriptions', 'neonlib-admin' ); ?></th><th><?php esc_html_e( 'Akcija', 'neonlib-admin' ); ?></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'Nema rezultata.', 'neonlib-admin' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr><td><code><?php echo esc_html( (string) $row['account_id'] ); ?></code></td><td><?php echo esc_html( (string) $row['status'] ); ?></td><td><?php echo esc_html( (string) $row['active_link_count'] ); ?></td><td><?php echo esc_html( (string) $row['subscription_count'] ); ?></td><td>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'neonlib_admin_account_update' ); ?><input type="hidden" name="action" value="neonlib_admin_account_update"><input type="hidden" name="account_id" value="<?php echo esc_attr( (string) $row['account_id'] ); ?>"><select name="status"><?php foreach ( array( 'active', 'suspended', 'deleted' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['status'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select> <button class="button button-primary"><?php esc_html_e( 'Save', 'neonlib-admin' ); ?></button></form>
		</td></tr><?php endforeach; ?></tbody></table>
		<?php
	}

	private function render_subscriptions(): void {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$account_id = isset( $_GET['account_id'] ) ? sanitize_text_field( wp_unslash( $_GET['account_id'] ) ) : '';
		$result = ( new NeonLib_Admin_Api_Client() )->subscriptions( array( 'status' => $status, 'account_id' => $account_id ) );
		?>
		<form class="neonlib-admin-filters" method="get"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>"><input type="hidden" name="tab" value="subscriptions"><input name="account_id" value="<?php echo esc_attr( $account_id ); ?>" placeholder="Account ID"><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'neonlib-admin' ); ?></option><?php foreach ( array( 'draft', 'published', 'archived' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><button class="button"><?php esc_html_e( 'Filter', 'neonlib-admin' ); ?></button></form>
		<?php if ( is_wp_error( $result ) ) { $this->render_api_error( $result ); return; } $rows = (array) ( $result['data'] ?? array() ); ?>
		<table class="widefat striped neonlib-admin-table"><thead><tr><th>Package ID</th><th>Account ID</th><th><?php esc_html_e( 'Title', 'neonlib-admin' ); ?></th><th><?php esc_html_e( 'Moderation', 'neonlib-admin' ); ?></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="4"><?php esc_html_e( 'Nema rezultata.', 'neonlib-admin' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr><td><code><?php echo esc_html( (string) $row['package_id'] ); ?></code></td><td><code><?php echo esc_html( (string) ( $row['account_id'] ?? 'legacy' ) ); ?></code></td><td><?php echo esc_html( (string) $row['title'] ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'neonlib_admin_subscription_update' ); ?><input type="hidden" name="action" value="neonlib_admin_subscription_update"><input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $row['package_id'] ); ?>"><select name="status"><?php foreach ( array( 'draft', 'published', 'archived' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['status'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><select name="visibility"><option value="private" <?php selected( $row['visibility'], 'private' ); ?>>private</option><option value="public" <?php selected( $row['visibility'], 'public' ); ?>>public</option></select><label><input type="checkbox" name="is_featured" value="1" <?php checked( ! empty( $row['is_featured'] ) ); ?>> featured</label> <button class="button button-primary"><?php esc_html_e( 'Save', 'neonlib-admin' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table>
		<?php
	}

	private function render_status(): void {
		$client = new NeonLib_Admin_Api_Client(); $health = $client->health();
		?><div class="neonlib-admin-card"><h2><?php esc_html_e( 'Veza s NeonLib API-jem', 'neonlib-admin' ); ?></h2><p><strong><?php esc_html_e( 'API URL:', 'neonlib-admin' ); ?></strong> <code><?php echo esc_html( defined( 'NEONLIB_API_URL' ) ? (string) NEONLIB_API_URL : '—' ); ?></code></p><p><strong><?php esc_html_e( 'Admin token:', 'neonlib-admin' ); ?></strong> <?php echo $client->is_configured() ? esc_html__( 'konfiguriran', 'neonlib-admin' ) : esc_html__( 'nije konfiguriran', 'neonlib-admin' ); ?></p><?php if ( is_wp_error( $health ) ) $this->render_api_error( $health ); else echo '<div class="notice notice-success inline"><p>' . esc_html__( 'API je dostupan.', 'neonlib-admin' ) . '</p></div>'; ?></div><?php
	}

	public function handle_account_update(): void {
		$this->authorize_action( 'neonlib_admin_account_update' );
		$account_id = isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! preg_match( '/^acc_[0-9a-hjkmnp-tv-z]{26}$/', $account_id ) || ! in_array( $status, array( 'active', 'suspended', 'deleted' ), true ) ) $this->redirect( 'accounts', 'invalid' );
		$result = ( new NeonLib_Admin_Api_Client() )->update_account( $account_id, $status );
		$this->redirect( 'accounts', is_wp_error( $result ) ? 'error' : 'updated' );
	}

	public function handle_subscription_update(): void {
		$this->authorize_action( 'neonlib_admin_subscription_update' );
		$package_id = isset( $_POST['package_id'] ) ? strtolower( trim( (string) wp_unslash( $_POST['package_id'] ) ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$visibility = isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : '';
		if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]{2,189}$/', $package_id ) || ! in_array( $status, array( 'draft', 'published', 'archived' ), true ) || ! in_array( $visibility, array( 'public', 'private' ), true ) ) $this->redirect( 'subscriptions', 'invalid' );
		$result = ( new NeonLib_Admin_Api_Client() )->update_subscription( $package_id, array( 'status' => $status, 'visibility' => $visibility, 'is_featured' => isset( $_POST['is_featured'] ) ) );
		$this->redirect( 'subscriptions', is_wp_error( $result ) ? 'error' : 'updated' );
	}

	private function authorize_action( string $nonce_action ): void { if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Nemate ovlast.', 'neonlib-admin' ), 403 ); check_admin_referer( $nonce_action ); }
	private function redirect( string $tab, string $status ): never { wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab, 'neonlib_admin_status' => $status ), admin_url( 'admin.php' ) ) ); exit; }
	private function render_notice(): void { $status = isset( $_GET['neonlib_admin_status'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_admin_status'] ) ) : ''; if ( 'updated' === $status ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The change was saved and added to the audit log.', 'neonlib-admin' ) . '</p></div>'; elseif ( in_array( $status, array( 'error', 'invalid' ), true ) ) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The change could not be saved.', 'neonlib-admin' ) . '</p></div>'; }
	private function render_api_error( WP_Error $error ): void { echo '<div class="notice notice-error inline"><p>' . esc_html( $error->get_error_message() ) . '</p></div>'; }
}

