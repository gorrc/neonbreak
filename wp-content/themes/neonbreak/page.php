<?php get_header(); ?>
<section class="content-page"><div class="nb-wrap">
<?php while ( have_posts() ) : the_post(); ?>
  <article <?php post_class(); ?>><p class="eyebrow">Neon Break</p><h1><?php the_title(); ?></h1><div class="entry-content"><?php the_content(); ?></div></article>
<?php endwhile; ?>
</div></section>
<?php get_footer(); ?>
