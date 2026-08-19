<?php get_header(); ?><section class="section content"><div class="shell"><?php if(have_posts()):while(have_posts()):the_post(); ?><article><h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1><?php the_excerpt(); ?></article><?php endwhile; else: ?><h1>Nothing found</h1><?php endif; ?></div></section><?php get_footer(); ?>

