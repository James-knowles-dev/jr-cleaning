<?php get_header(); ?>

<main class="content">
  <?php if (class_exists('WooCommerce') && (is_cart() || is_checkout() || is_account_page())) : ?>
    <div class="container">
      <?php while (have_posts()) : the_post(); ?>
        <div class="page-content">
          <?php the_content(); ?>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else : ?>
    <?php include(get_template_directory().'/includes/acf.php'); ?>
  <?php endif; ?>
</main>

<?php get_footer(); ?>