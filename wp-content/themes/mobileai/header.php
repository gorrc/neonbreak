<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" data-header>
	<div class="shell site-header__inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="MobileAI home">
			<?php get_template_part( 'template-parts/logo' ); ?>
		</a>
		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation"><span></span><span></span><span></span><span class="screen-reader-text">Toggle navigation</span></button>
		<nav id="primary-navigation" class="site-nav" aria-label="Primary navigation">
			<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'site-nav__list', 'fallback_cb' => 'mobileai_menu_fallback' ) ); ?>
			<a class="button button--small button--outline" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">Client portal</a>
			<a class="button button--small" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Start a project</a>
		</nav>
	</div>
</header>
<main id="main">
