<?php
get_header();
$s = get_search_query();
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$args = array(
    's' => $s,
    'post_type' => array('post', 'page'),
    'posts_per_page' => 6,
    'paged' => $paged,
);

$the_query = new WP_Query($args);
?>

<div class="search-results">

    <section class="search-results__hero inner-hero-block">

        <div class="container">

            <div class="inner-hero-block__content">

                <h1 class="inner-hero-block__content-heading">Search Results</h1>

                <h6 class="inner-hero-block__content-subheading">
                    <?php if (!empty($s)) : ?>
                        Results for: "<?php echo esc_html($s); ?>"
                    <?php else : ?>
                        No search term provided
                    <?php endif; ?>
                </h6>

            </div>

        </div>

    </section>

        <section class="search-results__count">
            <?php
            // Calculate the range of results being displayed
            $end_result = min($paged * 6, $the_query->found_posts);

            // Display the results count message
            echo "Showing $end_result of {$the_query->found_posts} results";
            ?>
        </section>


    <?php if ($the_query->have_posts()) { ?>

        <section class="search-results__results">

            <?php while ($the_query->have_posts()) {
                $the_query->the_post(); ?>

                <div class="search-results__results__item">

                    <?php if (has_post_thumbnail()) { ?>
                        <div class="search-results__results__item__image">
                            <?php the_post_thumbnail('medium'); ?>
                        </div>
                    <?php } ?>

                    <div class="search-results__results__item__content">
                        <h2 class="search-results__results__item__content__title"><?php the_title(); ?></h2>

                        <p class="search-results__results__item__content__excerpt">
                            <?php echo wp_trim_words(get_the_excerpt(), 25, '...'); ?>
                        </p>

                        <a class="search-results__results__item__content__button" href="<?php the_permalink(); ?>">Read More</a>
                    </div>

                </div>

            <?php } ?>

        </section>

        <section class="search-results__pagination">
            <?php
            echo paginate_links(array(
                'total'        => $the_query->max_num_pages,
                'current'      => $paged,
                'format'       => '?paged=%#%',
                'prev_text'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><g opacity="0.4"><mask id="mask0_2472_1502" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20"><rect width="20" height="20" transform="matrix(-1 0 0 1 20 0)" fill="#D9D9D9"/></mask><g mask="url(#mask0_2472_1502)"><path d="M6.875 10.7501H15.2504C15.4628 10.7501 15.6408 10.6786 15.7846 10.5357C15.9282 10.3928 16 10.2157 16 10.0045C16 9.79321 15.9282 9.61467 15.7846 9.46883C15.6408 9.323 15.4628 9.25008 15.2504 9.25008H6.875L10.546 5.57904C10.6959 5.42918 10.7708 5.25355 10.7708 5.05216C10.7708 4.85078 10.6944 4.67036 10.5417 4.51092C10.3889 4.3648 10.2118 4.29175 10.0104 4.29175C9.80903 4.29175 9.63326 4.36689 9.48312 4.51716L4.52917 9.47737C4.45417 9.55251 4.39931 9.6339 4.36458 9.72154C4.32986 9.80918 4.3125 9.90314 4.3125 10.0034C4.3125 10.1036 4.32986 10.1975 4.36458 10.2853C4.39931 10.3729 4.45139 10.4515 4.52083 10.5209L9.47917 15.4792C9.63194 15.632 9.80556 15.7049 10 15.698C10.1944 15.6911 10.3681 15.6145 10.5208 15.4684C10.6736 15.309 10.75 15.1274 10.75 14.9236C10.75 14.72 10.6736 14.5441 10.5208 14.3959L6.875 10.7501Z" fill="#331C13"/></g></g></svg>',
                'next_text'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><mask id="mask0_2472_1509" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20"><rect width="20" height="20" fill="#D9D9D9"/></mask><g mask="url(#mask0_2472_1509)"><path d="M13.125 10.7501H4.74958C4.53722 10.7501 4.35917 10.6786 4.21542 10.5357C4.07181 10.3928 4 10.2157 4 10.0045C4 9.79321 4.07181 9.61467 4.21542 9.46883C4.35917 9.323 4.53722 9.25008 4.74958 9.25008H13.125L9.45396 5.57904C9.3041 5.42918 9.22917 5.25355 9.22917 5.05216C9.22917 4.85078 9.30556 4.67036 9.45833 4.51092C9.61111 4.3648 9.78819 4.29175 9.98958 4.29175C10.191 4.29175 10.3667 4.36689 10.5169 4.51716L15.4708 9.47737C15.5458 9.55251 15.6007 9.6339 15.6354 9.72154C15.6701 9.80918 15.6875 9.90314 15.6875 10.0034C15.6875 10.1036 15.6701 10.1975 15.6354 10.2853C15.6007 10.3729 15.5486 10.4515 15.4792 10.5209L10.5208 15.4792C10.3681 15.632 10.1944 15.7049 10 15.698C9.80556 15.6911 9.63194 15.6145 9.47917 15.4684C9.32639 15.309 9.25 15.1274 9.25 14.9236C9.25 14.72 9.32639 14.5441 9.47917 14.3959L13.125 10.7501Z" fill="#331C13"/></g></svg>',
            ));
            ?>
        </section>

    <?php } else { ?>

        <div class="search-results__no-results">
            <h2>Nothing Found</h2>
            <div class="alert alert-info">
                <p>Sorry, but nothing matched your search criteria. Please try again with some different keywords.</p>
            </div>
        </div>

    <?php } ?>

</div>

<?php get_footer(); ?>
