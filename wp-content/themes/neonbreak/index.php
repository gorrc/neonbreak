<?php get_header(); ?>
<section class="content-page"><div class="nb-wrap">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
  <article <?php post_class(); ?>><p class="eyebrow">Insights</p><h1><?php the_title(); ?></h1><div class="entry-content"><?php the_content(); ?></div></article>
<?php endwhile; else : ?><h1>Nothing here yet.</h1><?php endif; ?>
</div></section>
<?php get_footer(); ?>
