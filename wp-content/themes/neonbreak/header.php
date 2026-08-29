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
      <a href="<?php echo esc_url( home_url( '/#work' ) ); ?>">What we do</a>
      <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a>
      <div class="nav-dropdown">
        <div class="nav-dropdown-label"><a href="<?php echo esc_url( home_url( '/services/' ) ); ?>">Services</a><button class="nav-dropdown-toggle" type="button" aria-expanded="false" aria-controls="services-menu"><span class="screen-reader-text">Toggle services menu</span></button></div>
        <div class="nav-dropdown-menu" id="services-menu">
          <a href="<?php echo esc_url( home_url( '/services/php-nodejs/' ) ); ?>"><span>01</span>PHP &amp; Node.js</a>
          <a href="<?php echo esc_url( home_url( '/services/firebase/' ) ); ?>"><span>02</span>Firebase</a>
          <a href="<?php echo esc_url( home_url( '/services/applied-ai/' ) ); ?>"><span>03</span>Applied AI</a>
          <a href="<?php echo esc_url( home_url( '/services/cloud-infrastructure/' ) ); ?>"><span>04</span>Cloud Infrastructure</a>
        </div>
      </div>
      <a class="nav-product" href="<?php echo esc_url( home_url( '/neonlib/' ) ); ?>"><small>Product</small>NeonLib</a>
      <a class="nav-cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
    </nav>
  </div>
</header>
<main id="main-content">
