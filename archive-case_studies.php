<?php
// Template Name: Blog
get_header();
?>
<main class="content">

  <?php include 'includes/components/inner-hero.php'; ?>

  <div class="primary-container default-margin blog-filter default-center">
    <input type="search" placeholder="Search" id="search-blog" class="search">
  </div>
  <div class="primary-container post-container" id="response-blog">
    <?php
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1; // Get current page
    $post_args = array(
      'post_type' => 'case_studies',
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
                $img_src = "/wp-content/themes/jrcleaning/images/placeholder.png";
                $img_alt = "The placeholder featured image.";
              }
              ?>
              <img src="<?php echo $img_src; ?>" alt="<?php echo $img_alt; ?>">
              <div class="title-button">
                <h3><?php the_title(); ?></h3>
                  <?php
                    $client_name = get_field('client_name');
                    $date_completed = get_field('date_completed');
                    if ($client_name || $date_completed) {
                      echo '<div class="case-study-meta">';
                      if ($client_name) {
                        echo '<span class="client-name">Client: ' . esc_html($client_name) . '</span>';
                      }
                      if ($date_completed) {
                        echo '<span class="date-completed">Completed: ' . esc_html(date('j F Y', strtotime($date_completed))) . '</span>';
                      }
                      echo '</div>';
                    }
                  ?>
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
