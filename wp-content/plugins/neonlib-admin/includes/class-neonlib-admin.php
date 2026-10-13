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
        add_action( 'admin_post_neonlib_admin_vectors', array( $this, 'handle_vectors' ) );
        add_action( 'admin_post_neonlib_admin_edit', array( $this, 'handle_edit' ) );
		add_action( 'admin_post_neonlib_admin_report_update', array( $this, 'handle_report_update' ) );
		add_action( 'admin_post_neonlib_admin_bulk_delete', array( $this, 'handle_bulk_delete' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_neonlib_admin_approve', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_neonlib_admin_account_update', array( $this, 'handle_account_update' ) );
		add_action( 'admin_post_neonlib_admin_subscription_update', array( $this, 'handle_subscription_update' ) );
	}

	public function register_menu(): void {
		add_menu_page( __( 'NeonLib', 'neonlib-admin' ), __( 'NeonLib', 'neonlib-admin' ), self::CAPABILITY, self::PAGE, array( $this, 'render_page' ), 'dashicons-book-alt', 58 );
	}

	public function enqueue_assets( ?string $hook ): void {
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
        if ( isset( $_GET['edit_package'] ) ) {
            $this->render_editor( sanitize_text_field( wp_unslash( $_GET['edit_package'] ) ) );
            return;
        }
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
		<form id="neonlib-bulk-delete" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Permanently delete the selected collections, ALL their versions and documents? This cannot be undone.');">
            <?php wp_nonce_field( 'neonlib_admin_bulk_delete' ); ?>
            <input type="hidden" name="action" value="neonlib_admin_bulk_delete">
            <p><label><input type="checkbox" name="confirm_delete" value="1" required> I confirm permanent deletion of the selected collections and their documents.</label></p>
            <button class="button" type="submit">Delete selected permanently</button>
            <p>Select all applies only to the rows shown below (up to 100), including the current filters. Accounts and publishers are kept.</p>
        </form>
        <table class="widefat striped neonlib-admin-table"><thead><tr><th><label><input type="checkbox" id="neonlib-select-all" onchange="document.querySelectorAll('.neonlib-delete-item').forEach(item => item.checked = this.checked)"> Select all shown</label></th><th>Package ID</th><th>Account ID</th><th><?php esc_html_e( 'Title', 'neonlib-admin' ); ?></th><th><?php esc_html_e( 'Moderation', 'neonlib-admin' ); ?></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'Nema rezultata.', 'neonlib-admin' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr><td><input type="checkbox" class="neonlib-delete-item" form="neonlib-bulk-delete" name="package_ids[]" value="<?php echo esc_attr( $row['package_id'] ); ?>" aria-label="<?php echo esc_attr( 'Select ' . $row['title'] ); ?>" onchange="const all = document.getElementById('neonlib-select-all'); const items = Array.from(document.querySelectorAll('.neonlib-delete-item')); all.checked = items.every(item => item.checked); all.indeterminate = items.some(item => item.checked) &amp;&amp; !all.checked;"></td><td><code><?php echo esc_html( (string) $row['package_id'] ); ?></code></td><td><code><?php echo esc_html( (string) ( $row['account_id'] ?? 'legacy' ) ); ?></code></td><td><?php echo esc_html( (string) $row['title'] ); ?></td><td><p><strong><?php echo esc_html( (string) $row['status'] ); ?></strong>
            · <?php echo esc_html( sprintf( 'Pending versions: %d', (int) ( $row['pending_count'] ?? 0 ) ) ); ?></p>
            <p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'subscriptions', 'edit_package' => $row['package_id'] ), admin_url( 'admin.php' ) ) ); ?>">Edit collection</a> <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'subscriptions', 'review_package' => $row['package_id'] ), admin_url( 'admin.php' ) ) ); ?>">Review documents</a></p>
            <p>Status stays unchanged unless you choose an action. Publishing requires document review. Changing visibility requires a new review.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'neonlib_admin_subscription_update' ); ?><input type="hidden" name="action" value="neonlib_admin_subscription_update"><input type="hidden" name="package_id" value="<?php echo esc_attr( (string) $row['package_id'] ); ?>"><select name="status" aria-label="Status action"><option value="">Keep status: <?php echo esc_html( $row['status'] ); ?></option><option value="draft">Send back to Draft</option><option value="archived">Archive</option></select><select name="visibility" aria-label="Visibility action"><option value="">Keep visibility: <?php echo esc_html( $row['visibility'] ); ?></option><option value="private">Change to private (requires review)</option><option value="public">Change to public (requires review)</option></select><label><input type="checkbox" name="is_featured" value="1" <?php checked( ! empty( $row['is_featured'] ) ); ?>> featured</label> <button class="button button-primary"><?php esc_html_e( 'Save', 'neonlib-admin' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table>
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

    private function render_editor( string $package_id, ?array $submitted = null, string $error = '' ): void {
        $result = ( new NeonLib_Admin_Api_Client() )->subscription( $package_id );
        if ( is_wp_error( $result ) ) { $this->render_api_error( $result ); return; }
        $editor = $result['data']['editor'] ?? null;
        if ( ! is_array( $editor ) ) { echo '<p>Update the NeonLib API before using the editor.</p>'; return; }
        $meta = $submitted ?? $editor['metadata'];
        $documents = $submitted['documents'] ?? $editor['review']['documents'] ?? array();
        if ( $error ) echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        ?>
        <h2>Edit collection: <?php echo esc_html( $package_id ); ?></h2>
        <p><a href="<?php echo esc_url( add_query_arg( array( 'page'=>self::PAGE, 'tab'=>'subscriptions' ), admin_url('admin.php') ) ); ?>">Back to subscriptions</a></p>
        <p>Saving creates a new draft version and requires a fresh review before publication. The collection will be unavailable until approved. Archived collections stay archived. Package ID and publisher do not change.</p>
        <form id="neonlib-editor" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('neonlib_admin_edit'); ?>
            <input type="hidden" name="action" value="neonlib_admin_edit">
            <input type="hidden" name="package_id" value="<?php echo esc_attr($package_id); ?>">
            <input type="hidden" name="edit_token" value="<?php echo esc_attr($submitted['edit_token'] ?? $editor['edit_token']); ?>">
            <input type="hidden" name="documents_json" id="neonlib-editor-json" value="">
            <p><label>Title<br><input name="title" class="large-text" maxlength="190" required value="<?php echo esc_attr($meta['title']); ?>"></label></p>
            <p><label>Description<br><textarea name="description" class="large-text" rows="4" maxlength="10000"><?php echo esc_textarea($meta['description']); ?></textarea></label></p>
            <p><label>Language <input name="language" required pattern="[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*" value="<?php echo esc_attr($meta['language']); ?>"></label>
            <label>Visibility <select name="visibility"><option value="public" <?php selected(strtolower($meta['visibility']), 'public'); ?>>Public</option><option value="private" <?php selected(strtolower($meta['visibility']), 'private'); ?>>Private</option></select></label></p>
            <h3>Articles</h3>
            <div id="neonlib-editor-documents">
            <?php foreach ( $documents as $document ) : ?>
                <fieldset class="neonlib-admin-card" data-document>
                    <p><label>Article ID <input data-field="id" required pattern="[a-z0-9][a-z0-9._-]{0,188}" value="<?php echo esc_attr($document['id']); ?>"></label></p>
                    <p><label>Title<input data-field="title" class="large-text" required maxlength="255" value="<?php echo esc_attr($document['title']); ?>"></label></p>
                    <p><label>Full text<textarea data-field="content" class="large-text" rows="10"><?php echo esc_textarea($document['content']); ?></textarea></label></p>
                    <button type="button" class="button" data-remove>Remove article from this draft</button>
                </fieldset>
            <?php endforeach; ?>
            </div>
            <p><button type="button" class="button" id="neonlib-add-document">Add article</button></p>
            <p id="neonlib-editor-error" role="alert"></p>
            <noscript><p>Enable JavaScript to edit articles.</p></noscript>
            <button class="button button-primary" id="neonlib-save-editor" disabled>Save draft for review</button>
        </form>
        <template id="neonlib-document-template"><fieldset class="neonlib-admin-card" data-document>
            <p><label>Article ID <input data-field="id" required pattern="[a-z0-9][a-z0-9._-]{0,188}"></label></p>
            <p><label>Title<input data-field="title" class="large-text" required maxlength="255"></label></p>
            <p><label>Full text<textarea data-field="content" class="large-text" rows="10"></textarea></label></p>
            <button type="button" class="button" data-remove>Remove article from this draft</button>
        </fieldset></template>
        <script>
        (() => {
            const form = document.getElementById('neonlib-editor');
            const list = document.getElementById('neonlib-editor-documents');
            document.getElementById('neonlib-save-editor').disabled = false;
            list.addEventListener('click', event => {
                if (event.target.matches('[data-remove]')) event.target.closest('[data-document]').remove();
            });
            document.getElementById('neonlib-add-document').addEventListener('click', () => {
                list.appendChild(document.getElementById('neonlib-document-template').content.cloneNode(true));
            });
            form.addEventListener('submit', event => {
                const documents = Array.from(list.querySelectorAll('[data-document]'), card => Object.fromEntries(
                    ['id','title','content'].map(key => [key, card.querySelector('[data-field="'+key+'"]').value])
                ));
                if (!documents.length || documents.length > 500 || new Set(documents.map(d => d.id)).size !== documents.length) {
                    event.preventDefault();
                    document.getElementById('neonlib-editor-error').textContent = 'Provide 1–500 articles with unique IDs.';
                    return;
                }
                document.getElementById('neonlib-editor-json').value = JSON.stringify(documents);
            });
        })();
        </script>
        <?php
    }

    public function handle_edit(): void {
        $this->authorize_action('neonlib_admin_edit');
        $package = sanitize_text_field(wp_unslash($_POST['package_id'] ?? ''));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,189}$/', $package)) $this->redirect('subscriptions', 'invalid');
        $input = array();
        foreach (array('edit_token','title','description','language','visibility') as $field) {
            if (!isset($_POST[$field]) || !is_string($_POST[$field])) $this->redirect('subscriptions','invalid');
            $input[$field] = wp_unslash($_POST[$field]);
        }
        if (!isset($_POST['documents_json']) || !is_string($_POST['documents_json'])) $this->redirect('subscriptions','invalid');
        $input['documents'] = json_decode(wp_unslash($_POST['documents_json']), true);
        if (!is_array($input['documents']) || !array_is_list($input['documents'])) $this->redirect('subscriptions','invalid');
        foreach ($input['documents'] as $document) {
            if (!is_array($document) || !is_string($document['id'] ?? null) || !is_string($document['title'] ?? null) || !is_string($document['content'] ?? null)) $this->redirect('subscriptions','invalid');
        }
        $result = (new NeonLib_Admin_Api_Client())->update_subscription($package, array('edit'=>$input));
        if (is_wp_error($result)) {
            // Retain input on failure, including the original token: stale content must not silently overwrite a newer version.
            require_once ABSPATH . 'wp-admin/admin-header.php';
            echo '<div class="wrap neonlib-admin">';
            $this->render_editor($package, $input, $result->get_error_message());
            echo '</div>';
            require_once ABSPATH . 'wp-admin/admin-footer.php';
            exit;
        }
        wp_safe_redirect(add_query_arg(array('page'=>self::PAGE,'tab'=>'subscriptions','review_package'=>$package,'neonlib_admin_status'=>'updated'),admin_url('admin.php')));
        exit;
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
        $this->render_vectors($package_id, (int)$review['version']['version_number'], $review['version']['status'] ?? '');
        foreach ( $review['documents'] as $document ) {
            echo '<section class="neonlib-admin-card"><h3>' . esc_html( (string) $document['title'] ) . '</h3><p><code>' . esc_html( (string) $document['id'] ) . '</code></p><pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( (string) $document['content'] ) . '</pre></section>';
        }
        if ( 'ARCHIVED' === $meta['status'] ) {
            echo '<p>This subscription is archived. Restore it to Draft, then review and approve it.</p>';
            ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('neonlib_admin_subscription_update'); ?>
                <input type="hidden" name="action" value="neonlib_admin_subscription_update">
                <input type="hidden" name="package_id" value="<?php echo esc_attr($package_id); ?>">
                <input type="hidden" name="status" value="draft">
                <?php if (!empty($result['data']['is_featured'])) : ?><input type="hidden" name="is_featured" value="1"><?php endif; ?>
                <button class="button button-primary">Restore to Draft</button>
            </form><?php return;
        }
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

    private function render_vectors(string $package, int $version, string $version_status): void {
        $version_status=strtoupper(trim($version_status));
        if ($version_status === '') {
            echo '<div class="notice notice-error inline"><p>API nije vratio status verzije. Ažuriraj src/SubscriptionReview.php na API serveru. Ponovno spremanje zbirke neće riješiti ovaj problem.</p></div>';
            return;
        }
        echo '<p>Status verzije: <strong>'.esc_html($version_status).'</strong></p>';
        if ($version_status !== 'DRAFT') {
            echo '<p>Za pripremu vektora napravi novu verziju kroz Edit collection → Save draft for review.</p>';
            return;
        }
        $result=(new NeonLib_Admin_Api_Client())->vector_job($package,$version);
        if(is_wp_error($result)) { $this->render_api_error($result); return; }
        $job=$result['data']??null;
        $state=$job['status']??'';
        $labels=array('QUEUED'=>'Čeka računalo','RUNNING'=>'Priprema vektora','DONE'=>'Vektori spremni','FAILED'=>'Obrada nije uspjela');
        echo '<section class="neonlib-admin-card"><h3>Priprema za brže preuzimanje</h3>';
        echo '<p>Vektori se računaju na povezanom računalu. Ono mora biti uključeno s pokrenutom obradom.</p>';
        if($state) echo '<p role="status"><strong>'.esc_html($labels[$state]??$state).'</strong> '.esc_html($state==='RUNNING' ? sprintf('%d / %d dokumenata',(int)$job['progress'],(int)$job['total']) : '').'</p>';
        if($state==='FAILED')echo '<p>'.esc_html($job['error']??'Pokušaj ponovno.').'</p>';
        if(in_array($state,array('QUEUED','RUNNING'),true)) {
            echo '<p>Pričekaj da vektori budu spremni prije odobravanja ove verzije.</p><button class="button" type="button" onclick="location.reload()">Osvježi status</button>';
            echo '<script>setTimeout(() => {if(!document.hidden && !document.querySelector(\'input[name="review_confirmed"]:checked\')) location.reload();}, 15000);</script>';
        } else {
            ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('neonlib_admin_vectors'); ?>
                <input type="hidden" name="action" value="neonlib_admin_vectors">
                <input type="hidden" name="package_id" value="<?php echo esc_attr($package); ?>">
                <input type="hidden" name="version" value="<?php echo esc_attr((string)$version); ?>">
                <button class="button button-primary"><?php echo esc_html($state==='DONE'?'Ponovno pripremi vektore':'Pripremi vektore'); ?></button>
            </form><?php
        }
        echo '</section>';
    }

    public function handle_vectors(): void {
        $this->authorize_action('neonlib_admin_vectors');
        $package=sanitize_text_field(wp_unslash($_POST['package_id']??''));
        $version=absint($_POST['version']??0);
        if(!preg_match('/^[a-z0-9][a-z0-9._-]{1,189}$/',$package)||!$version)$this->redirect('subscriptions','invalid');
        $result=(new NeonLib_Admin_Api_Client())->vector_job($package,$version,true);
        wp_safe_redirect(add_query_arg(array('page'=>self::PAGE,'tab'=>'subscriptions','review_package'=>$package,'neonlib_admin_status'=>is_wp_error($result)?'error':'updated'),admin_url('admin.php')));
        exit;
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

    public function handle_bulk_delete(): void {
        $this->authorize_action( 'neonlib_admin_bulk_delete' );
        $ids = isset( $_POST['package_ids'] ) ? wp_unslash( $_POST['package_ids'] ) : array();
        if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 100 || ( $_POST['confirm_delete'] ?? '' ) !== '1' ) $this->redirect( 'subscriptions', 'invalid' );
        foreach ( $ids as $id ) {
            if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9][a-z0-9._-]{1,189}$/', $id ) ) $this->redirect( 'subscriptions', 'invalid' );
        }
        $result = ( new NeonLib_Admin_Api_Client() )->delete_subscriptions( array_values( array_unique( $ids ) ) );
        $this->redirect( 'subscriptions', is_wp_error( $result ) ? 'error' : 'deleted' );
    }

	public function handle_subscription_update(): void {
		$this->authorize_action( 'neonlib_admin_subscription_update' );
		$package_id = isset( $_POST['package_id'] ) ? strtolower( trim( (string) wp_unslash( $_POST['package_id'] ) ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$visibility = isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : '';
		if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]{2,189}$/', $package_id ) || ! in_array( $status, array( '', 'draft', 'archived' ), true ) || ! in_array( $visibility, array( '', 'public', 'private' ), true ) ) $this->redirect( 'subscriptions', 'invalid' );
		$changes = array( 'is_featured' => isset( $_POST['is_featured'] ) );
        if ( '' !== $status ) $changes['status'] = $status;
        if ( '' !== $visibility ) $changes['visibility'] = $visibility;
        $result = ( new NeonLib_Admin_Api_Client() )->update_subscription( $package_id, $changes );
		$this->redirect( 'subscriptions', is_wp_error( $result ) ? 'error' : 'updated' );
	}

	private function authorize_action( string $nonce_action ): void { if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Nemate ovlast.', 'neonlib-admin' ), 403 ); check_admin_referer( $nonce_action ); }
	private function redirect( string $tab, string $status ): never { wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab, 'neonlib_admin_status' => $status ), admin_url( 'admin.php' ) ) ); exit; }
	private function render_notice(): void { $status = isset( $_GET['neonlib_admin_status'] ) ? sanitize_key( wp_unslash( $_GET['neonlib_admin_status'] ) ) : ''; if ( 'deleted' === $status ) echo '<div class="notice notice-success is-dismissible"><p>Selected collections, versions and documents were permanently deleted.</p></div>'; elseif ( 'updated' === $status ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The change was saved and added to the audit log.', 'neonlib-admin' ) . '</p></div>'; elseif ( 'review_error' === $status ) echo '<div class="notice notice-error"><p>Approval failed. The content may have changed or the API may be unavailable. Open the review again and retry.</p></div>'; elseif ( in_array( $status, array( 'error', 'invalid' ), true ) ) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The change could not be saved.', 'neonlib-admin' ) . '</p></div>'; }
	private function render_api_error( WP_Error $error ): void { echo '<div class="notice notice-error inline"><p>' . esc_html( $error->get_error_message() ) . '</p></div>'; }
}

