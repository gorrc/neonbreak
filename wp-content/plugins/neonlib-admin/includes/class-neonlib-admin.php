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
		add_action( 'admin_post_neonlib_admin_report_update', array( $this, 'handle_report_update' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_neonlib_admin_approve', array( $this, 'handle_approve' ) );
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
		if ( ! in_array( $tab, array( 'accounts', 'subscriptions', 'reports', 'status' ), true ) ) $tab = 'accounts';
		?>
		<div class="wrap neonlib-admin">
			<h1><?php esc_html_e( 'NeonLib administracija', 'neonlib-admin' ); ?></h1>
			<?php $this->render_notice(); ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( array( 'accounts' => __( 'Accounts', 'neonlib-admin' ), 'subscriptions' => __( 'Subscriptions', 'neonlib-admin' ), 'reports' => __( 'Reports', 'neonlib-admin' ), 'status' => __( 'API status', 'neonlib-admin' ) ) as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'accounts' === $tab ) $this->render_accounts();
			elseif ( 'subscriptions' === $tab ) $this->render_subscriptions();
			elseif ( 'reports' === $tab ) $this->render_reports();
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
        if ( isset( $_GET['review_package'] ) ) {
            $this->render_review( sanitize_text_field( wp_unslash( $_GET['review_package'] ) ) );
            return;
        }
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$account_id = isset( $_GET['account_id'] ) ? sanitize_text_field( wp_unslash( $_GET['account_id'] ) ) : '';
		$result = ( new NeonLib_Admin_Api_Client() )->subscriptions( array( 'status' => $status, 'account_id' => $account_id ) );
		?>
		<form class="neonlib-admin-filters" method="get"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>"><input type="hidden" name="tab" value="subscriptions"><input name="account_id" value="<?php echo esc_attr( $account_id ); ?>" placeholder="Account ID"><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'neonlib-admin' ); ?></option><?php foreach ( array( 'draft', 'published', 'archived' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><button class="button"><?php esc_html_e( 'Filter', 'neonlib-admin' ); ?></button></form>
		<?php if ( is_wp_error( $result ) ) { $this->render_api_error( $result ); return; } $rows = (array) ( $result['data'] ?? array() ); ?>
		<table class="widefat striped neonlib-admin-table"><thead><tr><th>Package ID</th><th>Account ID</th><th><?php esc_html_e( 'Title', 'neonlib-admin' ); ?></th><th><?php esc_html_e( 'Moderation', 'neonlib-admin' ); ?></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="4"><?php esc_html_e( 'Nema rezultata.', 'neonlib-admin' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr><td><code><?php echo esc_html( (string) $row['package_id'] ); ?></code></td><td><code><?php echo esc_html( (string) ( $row['account_id'] ?? 'legacy' ) ); ?></code></td><td><?php echo esc_html( (string) $row['title'] ); ?></td><td><p><strong><?php echo esc_html( (string) $row['status'] ); ?></strong>
            · <?php echo esc_html( sprintf( 'Pending versions: %d', (int) ( $row['pending_count'] ?? 0 ) ) ); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'subscriptions', 'review_package' => $row['package_id'] ), admin_url( 'admin.php' ) ) ); ?>">Review documents</a></p>
            <p>Save changes as Draft for review, or Archive to stop distribution.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'neonlib_admin_subscription_update' ); ?><input type="hidden" name="action" value="neonlib_admin_subscription_update"><input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $row['package_id'] ); ?>"><select name="status"><?php foreach ( array( 'draft', 'archived' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['status'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><select name="visibility"><option value="private" <?php selected( $row['visibility'], 'private' ); ?>>private</option><option value="public" <?php selected( $row['visibility'], 'public' ); ?>>public</option></select><label><input type="checkbox" name="is_featured" value="1" <?php checked( ! empty( $row['is_featured'] ) ); ?>> featured</label> <button class="button button-primary"><?php esc_html_e( 'Save', 'neonlib-admin' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table>
		<?php
	}

    private function render_reports(): void {
        $status = isset( $_GET['report_status'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['report_status'] ) ) ) : 'NEW';
        if ( ! in_array( $status, array( 'NEW', 'REVIEWING', 'RESOLVED', 'DISMISSED' ), true ) ) $status = 'NEW';
        $page = isset( $_GET['report_page'] ) ? min( 100000, absint( $_GET['report_page'] ) ) : 0;
        // API client omits empty filters, so list each selected state explicitly.
        $result = ( new NeonLib_Admin_Api_Client() )->reports( array( 'status' => $status, 'page' => $page ) );
        ?>
        <p>Reports are user allegations, not verified violations. Review them before taking action. Resolving a report does not automatically archive content.</p>
        <form method="get"><input type="hidden" name="page" value="neonlib-admin"><input type="hidden" name="tab" value="reports">
        <select name="report_status"><?php foreach ( array( 'NEW', 'REVIEWING', 'RESOLVED', 'DISMISSED' ) as $option ) : ?>
            <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( $option ); ?></option>
        <?php endforeach; ?></select><button class="button">Filter</button></form>
        <?php
        if ( is_wp_error( $result ) ) { $this->render_api_error( $result ); return; }
        $rows = (array) ( $result['data'] ?? array() );
        if ( ! $rows ) echo '<p>No reports in this state.</p>';
        foreach ( $rows as $row ) : ?>
            <details class="neonlib-admin-card"><summary><?php echo esc_html( $row['created_at'] . ' · ' . $row['kind'] . ' · ' . $row['reason'] . ' · ' . $row['target_label'] ); ?></summary>
            <p>Report: <code><?php echo esc_html( $row['report_id'] ); ?></code></p>
            <p>Target: <code><?php echo esc_html( $row['target_id'] ); ?></code> · Version: <?php echo esc_html( (string) ( $row['content_version'] ?? 'unknown' ) ); ?></p>
            <?php if ( 'AI' !== $row['kind'] ) : ?>
                <p><a href="<?php echo esc_url( add_query_arg( array( 'page'=>self::PAGE,'tab'=>'subscriptions','review_package'=>$row['target_id'] ), admin_url( 'admin.php' ) ) ); ?>">Review referenced collection and publisher</a></p>
            <?php endif; ?>
            <h3>Content voluntarily included by reporter</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo esc_html( $row['excerpt'] ); ?></pre>
            <h3>Reporter explanation</h3><p><?php echo nl2br( esc_html( $row['details'] ) ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'neonlib_admin_report_update' ); ?>
                <input type="hidden" name="action" value="neonlib_admin_report_update">
                <input type="hidden" name="report_id" value="<?php echo esc_attr( $row['report_id'] ); ?>">
                <input type="hidden" name="revision" value="<?php echo esc_attr( (string) $row['revision'] ); ?>">
                <select name="status"><?php foreach ( array( 'NEW','REVIEWING','RESOLVED','DISMISSED' ) as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $row['status'], $option ); ?>><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select>
                <p><label>Internal action note<br><textarea name="admin_note" maxlength="2000" rows="4" class="large-text"><?php echo esc_textarea( $row['admin_note'] ); ?></textarea></label></p>
                <button class="button button-primary">Save report review</button>
            </form></details>
        <?php endforeach;
        foreach ( array( 'Previous'=>max( 0, $page-1 ), 'Next'=>$page+1 ) as $label=>$destination ) {
            if ( ( 'Previous' === $label && $page === 0 ) || ( 'Next' === $label && count( $rows ) < 50 ) ) continue;
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page'=>self::PAGE,'tab'=>'reports','report_status'=>$status,'report_page'=>$destination ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a> ';
        }
    }

    public function handle_report_update(): void {
        $this->authorize_action( 'neonlib_admin_report_update' );
        $id = isset( $_POST['report_id'] ) ? sanitize_text_field( wp_unslash( $_POST['report_id'] ) ) : '';
        $status = isset( $_POST['status'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['status'] ) ) ) : '';
        $note = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';
        $revision = isset( $_POST['revision'] ) ? absint( $_POST['revision'] ) : 0;
        if ( ! preg_match( '/^[a-f0-9-]{36}$/', $id ) || ! in_array( $status, array( 'NEW','REVIEWING','RESOLVED','DISMISSED' ), true ) || ! $revision ) $this->redirect( 'reports', 'invalid' );
        $result = ( new NeonLib_Admin_Api_Client() )->update_report( $id, array( 'status'=>$status,'admin_note'=>$note,'revision'=>$revision ) );
        $this->redirect( 'reports', is_wp_error( $result ) ? 'error' : 'updated' );
    }

	private function render_review( string $package_id ): void {
        $result = ( new NeonLib_Admin_Api_Client() )->subscription( $package_id );
        if ( is_wp_error( $result ) ) { $this->render_api_error( $result ); return; }
        $review = $result['data']['review'] ?? null;
        echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'subscriptions' ), admin_url( 'admin.php' ) ) ) . '">Back to subscriptions</a></p>';
        if ( ! is_array( $review ) ) { echo '<p>No document version has been submitted yet.</p>'; return; }
        $meta = $review['subscription'];
        echo '<h2>' . esc_html( (string) $meta['title'] ) . '</h2>';
        echo '<p>' . esc_html( 'Publisher: ' . $meta['publisher_name'] . ' | Language: ' . $meta['language'] . ' | Visibility: ' . $meta['visibility'] . ' | Status: ' . $meta['status'] ) . '</p>';
        echo '<p>' . nl2br( esc_html( (string) $meta['description'] ) ) . '</p>';
        echo '<h3>' . esc_html( 'Version ' . $review['version']['version_number'] ) . '</h3>';
        foreach ( $review['documents'] as $document ) {
            echo '<section class="neonlib-admin-card"><h3>' . esc_html( (string) $document['title'] ) . '</h3><p><code>' . esc_html( (string) $document['id'] ) . '</code></p><pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( (string) $document['content'] ) . '</pre></section>';
        }
        if ( 'ARCHIVED' === $meta['status'] ) { echo '<p>This subscription is archived. Move it to Draft before reviewing it for publication.</p>'; return; }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'neonlib_admin_approve' ); ?>
            <input type="hidden" name="action" value="neonlib_admin_approve">
            <input type="hidden" name="package_id" value="<?php echo esc_attr( $package_id ); ?>">
            <input type="hidden" name="approve_version" value="<?php echo esc_attr( (string) $review['version']['version_number'] ); ?>">
            <input type="hidden" name="review_token" value="<?php echo esc_attr( $review['review_token'] ); ?>">
            <p><label><input type="checkbox" name="review_confirmed" value="1" required> I reviewed the documents and metadata above and approve this version for distribution.</label></p>
            <button class="button button-primary">Approve reviewed version</button>
        </form>
        <?php
    }

    public function handle_approve(): void {
        $this->authorize_action( 'neonlib_admin_approve' );
        $package = isset( $_POST['package_id'] ) ? sanitize_text_field( wp_unslash( $_POST['package_id'] ) ) : '';
        $token = isset( $_POST['review_token'] ) ? sanitize_text_field( wp_unslash( $_POST['review_token'] ) ) : '';
        $version = isset( $_POST['approve_version'] ) ? absint( $_POST['approve_version'] ) : 0;
        if ( '1' !== ( $_POST['review_confirmed'] ?? '' ) || ! $version || ! preg_match( '/^[a-f0-9]{64}$/', $token ) || ! preg_match( '/^[a-z0-9][a-z0-9._-]{2,189}$/', $package ) ) $this->redirect( 'subscriptions', 'invalid' );
        $result = ( new NeonLib_Admin_Api_Client() )->update_subscription( $package, array( 'approve_version' => $version, 'review_token' => $token ) );
        $this->redirect( 'subscriptions', is_wp_error( $result ) ? 'review_error' : 'updated' );
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
	private function render_notice(): void { $status = isset( $_GET['neonlib_admin_status'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_admin_status'] ) ) : ''; if ( 'updated' === $status ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The change was saved and added to the audit log.', 'neonlib-admin' ) . '</p></div>'; elseif ( 'review_error' === $status ) echo '<div class="notice notice-error"><p>Approval failed. The content may have changed or the API may be unavailable. Open the review again and retry.</p></div>'; elseif ( in_array( $status, array( 'error', 'invalid' ), true ) ) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The change could not be saved.', 'neonlib-admin' ) . '</p></div>'; }
	private function render_api_error( WP_Error $error ): void { echo '<div class="notice notice-error inline"><p>' . esc_html( $error->get_error_message() ) . '</p></div>'; }
}

