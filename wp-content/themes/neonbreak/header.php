<?php
$nav_current = function ($slug) {
    if ($slug === 'home') return is_front_page() ? ' aria-current="page"' : '';
    if (is_page($slug)) return ' aria-current="page"';
    if (is_page()) {
        foreach (get_post_ancestors(get_queried_object_id()) as $id) {
            if (get_post_field('post_name', $id) === $slug) return ' aria-current="location"';
        }
    }
    return '';
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
  <div class="nb-wrap header-inner">
    <a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Neon Break home">
      <img class="brand-mark" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/neonbreak-mark.svg?v=' . filemtime( get_template_directory() . '/assets/images/neonbreak-mark.svg' ) ); ?>" alt="" width="44" height="32"><span>neon<em>break</em></span>
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation"><span class="screen-reader-text">Toggle navigation</span>☰</button>
    <nav class="site-nav" id="site-navigation" aria-label="Primary navigation">
      <a class="nav-workspace" href="<?php echo esc_url( home_url( '/workspace/' ) ); ?>"<?php echo $nav_current( 'workspace' ); ?>>AI Workspace <span aria-hidden="true">↗</span></a>
      <a href="<?php echo esc_url( home_url( '/#work' ) ); ?>"<?php echo $nav_current( 'home' ); ?>>What we do</a>
      <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"<?php echo $nav_current( 'about' ); ?>>About</a>
      <div class="nav-dropdown">
        <div class="nav-dropdown-label"><a href="<?php echo esc_url( home_url( '/services/' ) ); ?>"<?php echo $nav_current( 'services' ); ?>>Services</a><button class="nav-dropdown-toggle" type="button" aria-expanded="false" aria-controls="services-menu"><span class="screen-reader-text">Toggle services menu</span></button></div>
        <div class="nav-dropdown-menu" id="services-menu">
          <a href="<?php echo esc_url( home_url( '/services/php-nodejs/' ) ); ?>"<?php echo $nav_current( 'php-nodejs' ); ?>><span>01</span>PHP &amp; Node.js</a>
          <a href="<?php echo esc_url( home_url( '/services/firebase/' ) ); ?>"<?php echo $nav_current( 'firebase' ); ?>><span>02</span>Firebase</a>
          <a href="<?php echo esc_url( home_url( '/services/applied-ai/' ) ); ?>"<?php echo $nav_current( 'applied-ai' ); ?>><span>03</span>Applied AI</a>
          <a href="<?php echo esc_url( home_url( '/services/cloud-infrastructure/' ) ); ?>"<?php echo $nav_current( 'cloud-infrastructure' ); ?>><span>04</span>Cloud Infrastructure</a>
        </div>
      </div>
      <a class="nav-product" href="<?php echo esc_url( home_url( '/neonlib/' ) ); ?>"<?php echo $nav_current( 'neonlib' ); ?>><small>Product</small>NeonLib</a>
      <a class="nav-product" href="<?php echo esc_url( home_url( '/natdian/' ) ); ?>"<?php echo $nav_current( 'natdian' ); ?>><small>Product</small>Natdian</a>
      <a class="nav-cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"<?php echo $nav_current( 'contact' ); ?>>Contact</a>
    </nav>
  </div>
</header>
<main id="main-content">

