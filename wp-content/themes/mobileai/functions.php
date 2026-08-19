<?php
defined( 'ABSPATH' ) || exit;

define( 'MOBILEAI_VERSION', '1.0.0' );

function mobileai_setup(): void {
	load_theme_textdomain( 'mobileai', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 44, 'width' => 190, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	register_nav_menus( array( 'primary' => __( 'Primary navigation', 'mobileai' ), 'footer' => __( 'Footer navigation', 'mobileai' ) ) );
}
add_action( 'after_setup_theme', 'mobileai_setup' );

function mobileai_assets(): void {
	wp_enqueue_style( 'mobileai', get_template_directory_uri() . '/assets/css/theme.css', array(), MOBILEAI_VERSION );
	wp_enqueue_script( 'mobileai', get_template_directory_uri() . '/assets/js/theme.js', array(), MOBILEAI_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'mobileai_assets', 20 );

function mobileai_body_classes( array $classes ): array {
	if ( is_front_page() ) $classes[] = 'mobileai-front';
	if ( is_page( 'account' ) || is_page( 'neonlib-racun' ) ) $classes[] = 'mobileai-account-page';
	return $classes;
}
add_filter( 'body_class', 'mobileai_body_classes' );

function mobileai_menu_fallback(): void {
	$links = array( 'Services' => home_url( '/services/' ), 'How it works' => home_url( '/#process' ), 'About' => home_url( '/#about' ), 'Contact' => home_url( '/contact/' ) );
	echo '<ul class="site-nav__list">';
	foreach ( $links as $label => $url ) printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	echo '</ul>';
}

function mobileai_document_meta(): void {
	if ( is_admin() ) return;
	$description = is_front_page() ? 'MobileAI builds practical AI systems, automation and data products that turn complex workflows into reliable outcomes.' : wp_strip_all_tags( get_the_excerpt() );
	if ( ! $description ) $description = 'Practical AI systems, automation and software products by MobileAI.';
	$title = wp_get_document_title();
	$url = is_singular() ? get_permalink() : home_url( '/' );
	$image = get_template_directory_uri() . '/assets/images/og-mobileai.svg';
	echo "\n" . '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 28, '' ) ) . '">' . "\n";
	foreach ( array( 'og:title' => $title, 'og:description' => $description, 'og:url' => $url, 'og:type' => is_singular() ? 'article' : 'website', 'og:image' => $image ) as $property => $content ) printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<link rel="icon" href="' . esc_url( get_template_directory_uri() . '/assets/images/favicon.svg' ) . '" type="image/svg+xml">' . "\n";
}
add_action( 'wp_head', 'mobileai_document_meta', 2 );

function mobileai_login_branding(): void {
	wp_enqueue_style( 'mobileai-login', get_template_directory_uri() . '/assets/css/login.css', array(), MOBILEAI_VERSION );
}
add_action( 'login_enqueue_scripts', 'mobileai_login_branding' );
add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => 'MobileAI' );

function mobileai_seed_pages(): void {
	$pages = array(
		'home' => array( 'title' => 'Home', 'template' => 'front-page.php' ),
		'services' => array( 'title' => 'Services', 'template' => 'page-services.php' ),
		'contact' => array( 'title' => 'Contact', 'template' => 'page-contact.php' ),
		'privacy-policy' => array( 'title' => 'Privacy Policy', 'template' => 'page-legal.php' ),
		'cookie-policy' => array( 'title' => 'Cookie Policy', 'template' => 'page-legal.php' ),
		'terms-of-use' => array( 'title' => 'Terms of Use', 'template' => 'page-legal.php' ),
	);
	$ids = array();
	foreach ( $pages as $slug => $data ) {
		$page = get_page_by_path( $slug );
		$id = $page ? $page->ID : wp_insert_post( array( 'post_title' => $data['title'], 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'page' ) );
		if ( ! is_wp_error( $id ) ) { update_post_meta( $id, '_wp_page_template', $data['template'] ); $ids[ $slug ] = $id; }
	}
	if ( isset( $ids['home'] ) ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $ids['home'] ); }
}
add_action( 'after_switch_theme', 'mobileai_seed_pages' );

