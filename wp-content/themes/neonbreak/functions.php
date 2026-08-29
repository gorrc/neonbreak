<?php
function neonbreak_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    register_nav_menus( array( 'primary' => __( 'Primary navigation', 'neonbreak' ) ) );
}
add_action( 'after_setup_theme', 'neonbreak_setup' );

function neonbreak_assets() {
    wp_enqueue_style( 'neonbreak-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap', array(), null );
    wp_enqueue_style( 'neonbreak', get_stylesheet_uri(), array( 'neonbreak-fonts' ), (string) filemtime( get_stylesheet_directory() . '/style.css' ) );
    wp_enqueue_script( 'neonbreak', get_template_directory_uri() . '/theme.js', array(), (string) filemtime( get_template_directory() . '/theme.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'neonbreak_assets' );

function neonbreak_meta() {
    if ( is_admin() ) return;
    if ( is_front_page() ) {
        $description = 'Neon Break builds focused AI systems, custom web applications and practical automation for businesses that value clarity and ownership.';
    } elseif ( is_singular() && get_queried_object_id() ) {
        $description = wp_strip_all_tags( get_the_excerpt( get_queried_object_id() ) );
    } else {
        $description = '';
    }
    if ( ! $description ) $description = 'AI-native software, custom web applications and practical automation built with intent.';
    $image = get_template_directory_uri() . '/assets/images/neonbreak-logo-concept.png';
    $url = is_singular() ? get_permalink() : home_url( '/' );
    printf( "\n<meta name=\"description\" content=\"%s\">\n", esc_attr( wp_trim_words( $description, 30, '' ) ) );
    foreach ( array( 'og:title' => wp_get_document_title(), 'og:description' => $description, 'og:type' => is_singular() ? 'article' : 'website', 'og:url' => $url, 'og:image' => $image ) as $property => $value ) {
        printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $value ) );
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<link rel="icon" href="' . esc_url( get_template_directory_uri() . '/assets/images/neonbreak-mark.svg' ) . '" type="image/svg+xml">' . "\n";
}
add_action( 'wp_head', 'neonbreak_meta', 2 );

function neonbreak_login_branding() {
    echo '<style>body.login{background:#07080d;color:#f5f7fb;font-family:Inter,"Segoe UI",sans-serif}body.login:before{content:"";position:fixed;inset:0;z-index:-1;background:radial-gradient(circle at 80% 0%,rgba(165,108,255,.15),transparent 35rem)}#login h1 a{width:82px;height:82px;background-image:url(' . esc_url( get_template_directory_uri() . '/assets/images/neonbreak-mark.svg' ) . ');background-size:contain}.login form{border:1px solid rgba(114,241,255,.22);border-radius:18px;background:#0d111a;box-shadow:0 25px 80px rgba(0,0,0,.35)}.login label{color:#cbd2de}.login input{border-color:#364052!important;background:#080b11!important;color:#fff!important}.login .button-primary{border:0;border-radius:999px;background:#72f1ff;color:#061014;font-weight:700}.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:#929caf}#login_error,.login .message,.login .success{border-left:3px solid #ff5d7d!important;border-radius:10px!important;background:#111722!important;color:#f5f7fb!important;box-shadow:0 16px 45px rgba(0,0,0,.28)!important}#login_error a,.login .message a,.login .success a{color:#72f1ff!important}body.login{background:#07080d!important;color:#f5f7fb!important}#login #loginform{border:1px solid rgba(114,241,255,.22)!important;border-radius:18px!important;background:#0d111a!important;box-shadow:0 25px 80px rgba(0,0,0,.45)!important}#login #loginform label,#login #loginform .forgetmenot label{color:#e1e6ee!important}#login #loginform .input,#login #loginform input[type=text],#login #loginform input[type=password]{border-color:#364052!important;background:#080b11!important;color:#fff!important;box-shadow:none!important}#login #loginform input[type=checkbox]{border-color:#465268!important;background:#080b11!important}#login #loginform .wp-hide-pw{border:0!important;background:transparent!important;color:#72f1ff!important;box-shadow:none!important}#login #loginform .button-primary{border:0!important;background:#72f1ff!important;color:#061014!important;text-shadow:none!important}#login #loginform .button-primary:hover{background:#a7f7ff!important}</style>';
}
add_action( 'login_enqueue_scripts', 'neonbreak_login_branding' );
add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => 'Neon Break' );

function neonbreak_body_classes( $classes ) {
    $classes[] = 'neonbreak-theme';
    $post = get_post();
    if ( $post && has_shortcode( $post->post_content, 'neonlib_account' ) ) $classes[] = 'neonbreak-account-page';
    return $classes;
}
add_filter( 'body_class', 'neonbreak_body_classes' );

function neonbreak_site_icon_fallback() {
    if ( ! has_site_icon() ) {
        echo '<link rel="icon" href="' . esc_url( get_template_directory_uri() . '/assets/images/neonbreak-mark.svg' ) . '" type="image/svg+xml">' . "\n";
    }
}
add_action( 'wp_head', 'neonbreak_site_icon_fallback', 5 );

function neonbreak_handle_contact_form() {
    $redirect = home_url( '/contact/' );
    if ( ! isset( $_POST['neonbreak_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['neonbreak_contact_nonce'] ) ), 'neonbreak_contact' ) ) {
        wp_safe_redirect( add_query_arg( 'contact', 'invalid', $redirect ) ); exit;
    }
    if ( ! empty( $_POST['website'] ) ) { wp_safe_redirect( add_query_arg( 'contact', 'sent', $redirect ) ); exit; }
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $rate_key = 'neonbreak_contact_' . md5( $ip );
    if ( get_transient( $rate_key ) ) { wp_safe_redirect( add_query_arg( 'contact', 'rate', $redirect ) ); exit; }
    $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
    if ( '' === $name || ! is_email( $email ) || '' === $message ) { wp_safe_redirect( add_query_arg( 'contact', 'invalid', $redirect ) ); exit; }
    $subject = sprintf( 'Neon Break enquiry from %s', $name );
    $body = "Name: {$name}\nEmail: {$email}\nCompany: {$company}\n\nMessage:\n{$message}";
    $sent = wp_mail( 'info@neonbreak.com', $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
    set_transient( $rate_key, 1, MINUTE_IN_SECONDS );
    wp_safe_redirect( add_query_arg( 'contact', $sent ? 'sent' : 'error', $redirect ) ); exit;
}
add_action( 'admin_post_neonbreak_contact', 'neonbreak_handle_contact_form' );
add_action( 'admin_post_nopriv_neonbreak_contact', 'neonbreak_handle_contact_form' );
