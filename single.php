<?php get_header(); ?>

<main class="content">
  <section class="post-detail-content">
    <div class="primary-container" data-aos="fade-up">
      <div class="primary-text single-content">
        <div class="page-main-content">
          <?php the_content(); ?>
        </div>
        <?php if (have_rows('post_detail')) {
          while (have_rows('post_detail')) {
            the_row();
            $rows = get_row_layout();
            $layout = str_replace('_', '-', $rows);
            switch ($layout) {
              case $layout:
              include(locate_template('includes/components/' . $layout . '.php'));
              break;
            }
          }
        } ?>
      </div>
    </div>
  </section>

</main>

<?php get_footer(); ?>