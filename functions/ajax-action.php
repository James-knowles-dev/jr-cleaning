<?php 
add_action('wp_ajax_action__post_filters', 'action__post_filters');
add_action('wp_ajax_nopriv_action__post_filters', 'action__post_filters');

function action__post_filters(){
    $html = '';
    $pagination = '';
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $post_args = array(
        'post_type'         => 'post',
        'post_status'       => 'publish',
        'posts_per_page'	=>  12,
        'order' => 'DESC',
        'paged'           => $paged,
    );
    if (!empty($_POST['category'])) {
        $tax_query = array();
        $tax_query[] = array(
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_POST['category']),
        );
        $post_args['tax_query'] = $tax_query;
    }
    if ($_POST['search']) {
        $post_args['s'] = $_POST['search'];
    }
    $post_query = new WP_Query( $post_args );
    $numberPages = $post_query->max_num_pages;
    
    if($post_query->have_posts()) {
        while ( $post_query->have_posts() ) { 
            $post_query->the_post(); 
            $post_id = get_the_ID();
            $title = get_the_title();
            $link = get_permalink();
            $has_featured = '';
            if (has_post_thumbnail()) {
                $image = get_the_post_thumbnail_url(get_the_ID(), 'full'); 
                $image_id = get_post_thumbnail_id();
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', TRUE); 
            } else {
                $image = get_stylesheet_directory_uri().'/images/placeholder.png';
                $image_alt = "The placeholder featured image.";
                $has_featured = 'no_featured';
            }
            $html .= '<div class="post-grid-item">
                <div class="item-wrapper">
                    <div class="image-wrapper '. $has_featured .'">
                        <img src="'. $image .'" alt="'. $image_alt .'" />
                    </div>
                    <div class="content-wrapper">
                        <h6 class="h3">'. $title .'</h6>
                        <a href="'. get_the_permalink() .'" class="button with-icon trasparent-btn"><span>Read More</span></a>
                    </div>
                </div>
            </div>';
        } wp_reset_query();

        ob_start();
    
        if ($numberPages > 1) {
            $pagination .= '<div class="pagination" data-max="'. $numberPages .'">';
            $pagination .= '<div class="pagination-content cdp" actpage="'. $paged .'">';
            $pagination .=  '<a href="#" class="prev-page icon-btn cdp_i" data-page="' . ($paged - 1) . '">
                <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6.875 10.9349H15.2504C15.4628 10.9349 15.6408 10.8634 15.7846 10.7205C15.9282 10.5776 16 10.4005 16 10.1893C16 9.97802 15.9282 9.79948 15.7846 9.65365C15.6408 9.50781 15.4628 9.4349 15.2504 9.4349H6.875L10.546 5.76385C10.6959 5.61399 10.7708 5.43837 10.7708 5.23698C10.7708 5.03559 10.6944 4.85517 10.5417 4.69573C10.3889 4.54962 10.2118 4.47656 10.0104 4.47656C9.80903 4.47656 9.63326 4.5517 9.48312 4.70198L4.52917 9.66219C4.45417 9.73733 4.39931 9.81872 4.36458 9.90635C4.32986 9.99399 4.3125 10.088 4.3125 10.1882C4.3125 10.2884 4.32986 10.3823 4.36458 10.4701C4.39931 10.5577 4.45139 10.6363 4.52083 10.7057L9.47917 15.6641C9.63194 15.8168 9.80556 15.8898 10 15.8828C10.1944 15.8759 10.3681 15.7993 10.5208 15.6532C10.6736 15.4938 10.75 15.3122 10.75 15.1084C10.75 14.9048 10.6736 14.7289 10.5208 14.5807L6.875 10.9349Z" fill="#331C13"/>                        
                </svg>
            </a>';

            for ($i = 1; $i <= $numberPages; $i++) {
                $current_class = ($i == $paged) ? ' active' : '';
                $pagination .= '<a href="#" class="dots cdp_i' . $current_class . '" data-page="' . $i . '">' . $i . '</a>';
            }
            
            $pagination .= '<a href="#" class="next-page icon-btn cdp_i"  data-page="' . ($paged + 1) . '">
                <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13.125 10.9349H4.74958C4.53722 10.9349 4.35917 10.8634 4.21542 10.7205C4.07181 10.5776 4 10.4005 4 10.1893C4 9.97802 4.07181 9.79948 4.21542 9.65365C4.35917 9.50781 4.53722 9.4349 4.74958 9.4349H13.125L9.45396 5.76385C9.3041 5.61399 9.22917 5.43837 9.22917 5.23698C9.22917 5.03559 9.30556 4.85517 9.45833 4.69573C9.61111 4.54962 9.78819 4.47656 9.98958 4.47656C10.191 4.47656 10.3667 4.5517 10.5169 4.70198L15.4708 9.66219C15.5458 9.73733 15.6007 9.81872 15.6354 9.90635C15.6701 9.99399 15.6875 10.088 15.6875 10.1882C15.6875 10.2884 15.6701 10.3823 15.6354 10.4701C15.6007 10.5577 15.5486 10.6363 15.4792 10.7057L10.5208 15.6641C10.3681 15.8168 10.1944 15.8898 10 15.8828C9.80556 15.8759 9.63194 15.7993 9.47917 15.6532C9.32639 15.4938 9.25 15.3122 9.25 15.1084C9.25 14.9048 9.32639 14.7289 9.47917 14.5807L13.125 10.9349Z" fill="#331C13"/>
                </svg>
            </a>';
            $pagination .=  '</div>';
            $pagination .=  '</div>';
        }        
    }
    if(!empty($html)){
		$msg = array('response'=>'success','message'=> $html, 'pagination' => $pagination, );
		echo json_encode($msg);
		exit();
	}else{
		$msg = array('response'=>'error','message'=>'No Post found');
		echo json_encode($msg);
		exit();
	}
    wp_die();
}