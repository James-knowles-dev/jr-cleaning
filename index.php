<?php
// Template Name: Blog
get_header();
?>
<main class="content">

  <?php include 'includes/components/inner-hero.php'; ?>

  <div class="primary-container default-margin blog-filter default-center">
    <input type="search" placeholder="Search" id="search-blog" class="search">
    <form action="<?php echo site_url() ?>/wp-admin/admin-ajax.php" method="POST" id="filter-blog"
      class="default-center">
      <?php
      echo '<select name="category-filter-blog" class="category-filter-dropdown">';
      echo '<option value="" disabled="disabled" selected>Categories</option>';
      echo '<option value="">All</option>';
      $args = array(
        'hide_empty' => true,
        'post_type' => 'post',
        'order' => 'DESC',
        'orderby' => 'DATE',
        'posts_per_page' => 999,
      );
      $categories = get_categories($args);
      foreach ($categories as $category) {
        echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
      }
      echo '</select>';
      ?>
      <input type="hidden" name="action" value="filter_blog">
    </form>
  </div>
  <div class="primary-container post-container" id="response-blog">
    <?php
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1; // Get current page
    $post_args = array(
      'post_type' => 'post',
      'order' => 'DESC',
      'orderby' => 'DATE',
      'post_status' => 'publish',
      'posts_per_page' => 12, // Limit to 12 posts per page
      'paged' => $paged, // Add pagination support
    );
    $post_loop = new WP_Query($post_args);

    if ($post_loop->have_posts()) {
      while ($post_loop->have_posts()) {
        $post_loop->the_post();
        ?>
            <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="post" data-aos="fade-up">
              <?php
              if (has_post_thumbnail()) {
                $img_src = get_the_post_thumbnail_url($post, "thumbnail");
                $img_alt = get_post_meta(get_post_thumbnail_id($post->ID), "_wp_attachment_image_alt", "true");
              } else {
                $img_src = "/wp-content/themes/pixelbase/images/placeholder.png";
                $img_alt = "The placeholder featured image.";
              }
              ?>
              <img src="<?php echo $img_src; ?>" alt="<?php echo $img_alt; ?>">
              <div class="title-button">
                <h3><?php the_title(); ?></h3>
                <p class="button">Read More</p>
              </div>
            </a>
        <?php
      }
    } else {
      ?>
      <div class="primary-container default-center default-margin no-results" data-aos="fade-up">
        <h3>No Results Found <span>:(</span></h3>
      </div>
      <?php
    }
    wp_reset_postdata();
    ?>
  </div>
  <!-- Pagination moved outside of post-container -->
  <?php
  if ($post_loop->max_num_pages > 1) {
      $pagination_args = array(
          'total' => $post_loop->max_num_pages,
          'current' => $paged,
          'prev_text' => __('« Previous'),
          'next_text' => __('Next »'),
      );
      echo '<div class="pagination primary-container default-center">';
      echo paginate_links($pagination_args);
      echo '</div>';
  }
  ?>
</main>

<?php get_footer(); ?>
