<?php get_header(); while(have_posts()):the_post(); ?><section class="page-hero page-hero--compact"><div class="shell"><p class="eyebrow"><span></span>MobileAI</p><h1><?php the_title(); ?></h1></div></section><article class="section content"><div class="shell"><?php the_content(); ?></div></article><?php endwhile; get_footer(); ?>

