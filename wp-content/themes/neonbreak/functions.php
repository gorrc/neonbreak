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

function neonbreak_body_classes( $classes ) {
    $classes[] = 'neonbreak-theme';
    return $classes;
}
add_filter( 'body_class', 'neonbreak_body_classes' );

function neonbreak_site_icon_fallback() {
    if ( ! has_site_icon() ) {
        echo '<link rel="icon" href="' . esc_url( get_template_directory_uri() . '/assets/images/neonbreak-mark.svg' ) . '" type="image/svg+xml">' . "\n";
    }
}
add_action( 'wp_head', 'neonbreak_site_icon_fallback', 5 );
