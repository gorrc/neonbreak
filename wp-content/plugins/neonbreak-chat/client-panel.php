<?php
defined( 'ABSPATH' ) || exit;

/** Client-owned configuration; secrets handled by the server service. */
final class Neonbreak_Client_Panel {
    public static function assets() {
        wp_enqueue_style( 'nb-client-panel', plugins_url( 'panel.css', __FILE__ ), array(), '1.3' );
        wp_enqueue_script( 'nb-client-panel', plugins_url( 'panel.js', __FILE__ ), array(), '1.2', true );
    }
    public static function defaults() {
        return array( 'title' => 'My assistant', 'accent' => '#72f1ff', 'launcher' => "Let's talk", 'welcome' => 'Hello! How can we help?', 'contact' => '', 'contact_label' => 'Contact our team', 'suggestions' => "What services do you offer?\nHow can we get started?", 'knowledge' => '', 'domains' => '', 'model' => 'accounts/fireworks/models/gpt-oss-120b', 'daily_limit' => 100, 'show_sources' => 0, 'enabled' => 0 );
    }
    public static function render() {
        nocache_headers();
        self::assets();
        if ( ! is_user_logged_in() ) {
            ob_start(); ?><section class="nb-client nb-client-auth"><div class="nb-auth-intro"><p class="eyebrow">Neonbreak / AI workspace</p><h1>Your brand.<br>Your knowledge.<br><span>Your assistant.</span></h1><p>Create a chat that feels like your business. Personalise it, connect Fireworks and add it to your website.</p><div class="nb-auth-benefits"><div><span>01</span><p><strong>Make it yours</strong>Colours, messages and questions under your control.</p></div><div><span>02</span><p><strong>Give it context</strong>Your services, products and answers in one place.</p></div><div><span>03</span><p><strong>Put it to work</strong>One embed code. Update your settings anytime.</p></div></div><a class="nb-auth-back" href="<?php echo esc_url( home_url( '/ai-chat/' ) ); ?>">← Explore AI Chat</a></div><div class="nb-auth-card"><span class="nb-auth-symbol" aria-hidden="true">✦</span><p class="eyebrow">Start here</p><h2>Create your workspace</h2><p>Enter your email. We'll send a link to set your password and access your chat settings.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nb_client_signup"><?php wp_nonce_field( 'nb_client_signup' ); ?><label>Work email<input name="email" type="email" placeholder="you@company.com" required autocomplete="email"></label><button class="button button-primary" type="submit">Create my workspace ↗</button></form><?php if ( isset( $_GET['access_sent'] ) ) : ?><p class="nb-client-alert" role="status">If eligible, account access instructions have been sent. Check your email.</p><?php endif; ?><div class="nb-auth-signin"><span>Already have an account?</span><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Sign in →</a></div><p class="nb-auth-footnote">Use your own Fireworks account for AI usage. Your key stays on the server.</p></div></section><?php return ob_get_clean();
        }
        $s = wp_parse_args( get_user_meta( get_current_user_id(), '_nb_chat_presentation', true ) ?: array(), self::defaults() );
        ob_start(); ?>
        <section class="nb-client"><div class="nb-workspace-head"><div><p class="eyebrow">Neonbreak / Your workspace</p><h1>My AI chat</h1><p>Make it yours. Preview your changes, then save when you're ready.</p></div><span class="nb-workspace-badge"><?php echo $s['enabled'] ? 'Widget enabled' : 'Draft configuration'; ?></span></div><nav class="nb-panel-steps" aria-label="Configuration sections"><a href="#nb-appearance"><span>01</span> Appearance</a><a href="#nb-knowledge"><span>02</span> Knowledge</a><a href="#nb-connection"><span>03</span> Connection</a></nav>
        <?php if ( isset( $_GET['chat_saved'] ) ) : ?><p class="nb-client-alert" role="status"><?php echo esc_html( $_GET['chat_saved'] === 'error' ? 'Could not save. Check your connection, model and website origins.' : 'Your configuration has been saved.' ); ?></p><?php endif; ?>
        <?php if (class_exists('NB_Site_Workspace') && NB_Site_Workspace::owner() === get_current_user_id()) : global $TRP_LANGUAGE; ?><p class="nb-client-alert"><?php echo esc_html(($TRP_LANGUAGE ?? '') === 'hr' ? 'Ovaj račun upravlja chatom na Neonbreak stranici. Spremljene promjene primjenjuju se na javni widget i demo.' : 'This account manages the Neonbreak website chat. Saved changes apply to the public widget and demo.'); ?></p><?php endif; ?>
        <div class="nb-client-grid"><form class="nb-client-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="nb_client_presentation_save"><?php wp_nonce_field( 'nb_client_presentation_save' ); ?>
        <fieldset id="nb-appearance"><legend>1 · Appearance and messages</legend>
        <?php foreach ( array( 'title' => 'Assistant name', 'launcher' => 'Launcher text', 'welcome' => 'Welcome message', 'contact_label' => 'Contact button text' ) as $field => $label ) : ?><label><?php echo esc_html( $label ); ?><input name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $s[$field] ); ?>" maxlength="<?php echo $field === 'welcome' ? 400 : 100; ?>" required></label><?php endforeach; ?>
        <label>Accent colour<input type="color" name="accent" value="<?php echo esc_attr( $s['accent'] ); ?>"></label>
        <label>Contact page<input name="contact" type="url" value="<?php echo esc_attr( $s['contact'] ); ?>" placeholder="https://your-company.com/contact"></label>
        <label>Suggested questions · one per line, maximum 3<textarea name="suggestions" rows="3" maxlength="450"><?php echo esc_textarea( $s['suggestions'] ); ?></textarea></label>
        <?php global $TRP_LANGUAGE; $sources_hr = ($TRP_LANGUAGE ?? '') === 'hr'; ?>
        <label><input type="checkbox" name="show_sources" value="1" <?php checked( $s['show_sources'], 1 ); ?>> <?php echo esc_html($sources_hr ? 'Prikaži izvore u chatu' : 'Show sources in chat'); ?></label><p><?php echo esc_html($sources_hr ? 'Zadano isključeno. Dokumenti se i dalje koriste za odgovore, ali se njihovi izvori ne prikazuju posjetiteljima.' : 'Off by default. Documents still inform answers, but their sources are hidden from visitors.'); ?></p></fieldset>
        <fieldset id="nb-knowledge"><legend>2 · Business knowledge</legend><label>Public information about your business<textarea name="knowledge" rows="10" maxlength="20000" placeholder="Services, products, frequently asked questions and contact details…"><?php echo esc_textarea( $s['knowledge'] ); ?></textarea></label><p>Use public information only. Do not include passwords or confidential data.</p></fieldset>
        <fieldset id="nb-connection"><legend>3 · Connection and website</legend><label>Fireworks API key<input name="api_key" type="password" maxlength="256" autocomplete="new-password" placeholder="<?php echo get_user_meta( get_current_user_id(), '_nb_chat_key', true ) ? 'Key saved · leave blank to keep it' : 'Paste your own Fireworks key'; ?>"></label><p>Your key is encrypted on the server and never appears in the widget.</p><label>Model ID<input name="model" value="<?php echo esc_attr( $s['model'] ); ?>" maxlength="200" required></label><label>Daily request limit<input type="number" name="daily_limit" min="1" max="1000" value="<?php echo (int) $s['daily_limit']; ?>"></label><p>A question can use two model calls. Set a spending limit in Fireworks too.</p><label>Website origins · one per line<textarea name="domains" rows="3" maxlength="2000" placeholder="https://your-company.com&#10;https://www.your-company.com"><?php echo esc_textarea( $s['domains'] ); ?></textarea></label><p>Include https:// and any port. www and non-www are separate origins.</p><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], 1 ); ?>> Enable embedded chat</label></fieldset>
        <div class="button-row"><button class="button button-primary" type="submit">Save my configuration</button><button class="button" type="submit" name="verify" value="1">Verify connection & save</button></div></form>
        <aside class="nb-client-preview"><h2>Live appearance preview</h2><p>This preview demonstrates the appearance. It does not send AI requests.</p><div class="nb-client-mock"><header><span>✦</span><strong data-preview="title"></strong><span>×</span></header><p data-preview="welcome"></p><div data-preview="suggestions"></div><a data-preview="contact_label" target="_blank" rel="noopener noreferrer"></a><div class="nb-mock-input">Your message… <span>↗</span></div><small>AI answers may be inaccurate. Avoid sharing sensitive information.</small></div><button class="nb-mock-launcher" type="button" data-preview="launcher"></button><p>Saved settings belong to your signed-in account. Changes do not alter the Neonbreak website assistant.</p></aside></div></section>
        <?php $public = get_user_meta( get_current_user_id(), '_nb_chat_public', true );
        if ( $public ) : ?><div class="nb-client nb-client-embed"><h2>Embed your chat</h2><p>Copy this code into your website. Saved configuration changes apply without replacing the code.</p><textarea readonly class="nb-client-code" rows="3"><?php echo esc_textarea( '<script src="' . plugins_url( 'embed.js', __FILE__ ) . '" data-chat="' . $public . '" data-host="' . admin_url( 'admin-ajax.php' ) . '" defer></script>' ); ?></textarea><button class="button nb-client-copy" type="button">Copy embed code</button><span class="nb-copy-status" role="status"></span><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( add_query_arg( array( 'action' => 'nb_client_frame', 'chat' => $public, 'preview' => '1' ), admin_url( 'admin-ajax.php' ) ) ); ?>">Test saved chat ↗</a><p>This local address works only on this computer. External websites require the service to be hosted at a public HTTPS address.</p></div><?php endif;
        return ob_get_clean();
    }
    public static function sanitize( $input ) {
        $s = self::defaults();
        foreach ( array( 'title', 'launcher', 'welcome', 'contact_label' ) as $field ) $s[$field] = mb_substr( sanitize_text_field( $input[$field] ?? '' ), 0, $field === 'welcome' ? 400 : 100 );
        $s['accent'] = sanitize_hex_color( $input['accent'] ?? '' ) ?: '#72f1ff';
        $s['contact'] = esc_url_raw( $input['contact'] ?? '', array( 'https', 'http' ) );
        $questions = array_slice( array_filter( array_map( function( $value ) { return mb_substr( sanitize_text_field( $value ), 0, 150 ); }, explode( "\n", $input['suggestions'] ?? '' ) ) ), 0, 3 );
        $s['suggestions'] = implode( "\n", $questions );
        $s['knowledge'] = mb_substr( sanitize_textarea_field( $input['knowledge'] ?? '' ), 0, 20000 );
        $s['domains'] = mb_substr( sanitize_textarea_field( $input['domains'] ?? '' ), 0, 2000 );
        $s['model'] = mb_substr( sanitize_text_field( $input['model'] ?? self::defaults()['model'] ), 0, 200 );
        $s['daily_limit'] = max( 1, min( 1000, absint( $input['daily_limit'] ?? 100 ) ) );
        $s['show_sources'] = ($input['show_sources'] ?? '') === '1' || ($input['show_sources'] ?? 0) === 1 ? 1 : 0;
        $s['enabled'] = empty( $input['enabled'] ) ? 0 : 1;
        return $s;
    }
    public static function save() {
        if ( ! is_user_logged_in() ) { wp_die( 'Sign in first.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'nb_client_presentation_save' );
        $input = array();
        foreach ( array_keys( self::defaults() ) as $field ) $input[$field] = isset( $_POST[$field] ) && is_string( $_POST[$field] ) ? wp_unslash( $_POST[$field] ) : '';
        $s = self::sanitize( $input ); $uid = get_current_user_id();
        $error = function() { wp_safe_redirect( add_query_arg( 'chat_saved', 'error', apply_filters( 'nb_client_panel_url', home_url( '/my-ai-chat/' ) ) ) ); exit; };
        $origins = array();
        foreach ( array_filter( array_map( 'trim', explode( "\n", $s['domains'] ) ) ) as $value ) { $origin = Neonbreak_Client_Service::origin( $value ); if ( ! $origin ) $error(); $origins[] = $origin; }
        $s['domains'] = implode( "\n", array_slice( array_unique( $origins ), 0, 10 ) );
        $new_key = trim( Neonbreak_Client_Service::input( $_POST, 'api_key' ) );
        $old_key = Neonbreak_Client_Service::decrypt( get_user_meta( $uid, '_nb_chat_key', true ) );
        $key = $new_key ?: $old_key;
        if ( strlen( $key ) > 256 || preg_match( '/[\r\n]/', $key ) || ! preg_match( '~^accounts/[a-zA-Z0-9_-]+/(models|deployments)/[a-zA-Z0-9._-]+$~', $s['model'] ) ) $error();
        if ( $s['enabled'] && ( ! $key || ! $s['knowledge'] || ! $origins || ! $s['title'] ) ) $error();
        $old = get_user_meta( $uid, '_nb_chat_presentation', true );
        if ( $new_key || isset( $_POST['verify'] ) || ( $key && ( $old['model'] ?? '' ) !== $s['model'] ) ) {
            if ( ! $key || null === Neonbreak_Chat::call_model( array_merge( $s, array( '_api_key' => $key ) ), array( array( 'role' => 'user', 'content' => 'Reply with OK only.' ) ) ) ) $error();
        }
        if ( $new_key ) update_user_meta( $uid, '_nb_chat_key', Neonbreak_Client_Service::encrypt( $new_key ) );
        update_user_meta( $uid, '_nb_chat_presentation', $s );
        if ( ! get_user_meta( $uid, '_nb_chat_public', true ) ) update_user_meta( $uid, '_nb_chat_public', bin2hex( random_bytes( 16 ) ) );
        wp_safe_redirect( add_query_arg( 'chat_saved', '1', apply_filters( 'nb_client_panel_url', home_url( '/my-ai-chat/' ) ) ) ); exit;
    }
}
add_shortcode( 'neonbreak_chat_panel', array( 'Neonbreak_Client_Panel', 'render' ) );
add_action( 'admin_post_nb_client_presentation_save', array( 'Neonbreak_Client_Panel', 'save' ) );
add_action( 'admin_post_nopriv_nb_client_presentation_save', array( 'Neonbreak_Client_Panel', 'save' ) );
add_action( 'wp_enqueue_scripts', function() { $post = get_post(); if ( is_singular() && $post && has_shortcode( $post->post_content, 'neonbreak_chat_panel' ) ) Neonbreak_Client_Panel::assets(); } );


