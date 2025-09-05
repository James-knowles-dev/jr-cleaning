<?php 
// Admin .css file.
function admin_style() {
    wp_enqueue_style('admin-styles', get_template_directory_uri().'/admin.css');
}
add_action('admin_enqueue_scripts', 'admin_style');

function my_admin_acf_custom_css() {
    echo '<style>
        .acf-fc-popup ul {
            flex-direction: row;
            flex-wrap: wrap;
        }
        .acf-fc-popup li {
            height: auto;
        }

        .acf-field-68b5dd1f8f2c2 .acf-radio-list {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .acf-field-68b5dd1f8f2c2 .acf-radio-list:before {
            display: none;
        }

        .acf-field-68b5dd1f8f2c2 .acf-radio-list li label {
            display:flex!important;
            gap: 20px;
            flex-direction: column-reverse;
            align-items: center;
        }

    </style>';
}
add_action('admin_head', 'my_admin_acf_custom_css');


// Enable featured images on posts and pages.
add_theme_support('post-thumbnails', array(
  'post',
  'page',
));

// Add WooCommerce support
add_theme_support('woocommerce');
add_theme_support('wc-product-gallery-zoom');
add_theme_support('wc-product-gallery-lightbox');
add_theme_support('wc-product-gallery-slider');

// Remove WooCommerce breadcrumb
remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

// Add excerpt to default page type.
function add_excerpts() {
  add_post_type_support('page','excerpt');
}
add_action('init','add_excerpts');

// Custom Menus
function add_menu_support(){
  register_nav_menus( array(
    'menu' => 'Menu',
    'footer' => 'Footer',
    'footer_menu_1' => 'Footer Menu 1',
    'footer_menu_2' => 'Footer Menu 2',
    'footer_menu_3' => 'Footer Menu 3',
  ));
}
add_action( 'after_setup_theme', 'add_menu_support' );

// Register Widget Areas
function register_theme_sidebars() {
    register_sidebar(array(
        'name' => 'Shop Sidebar',
        'id' => 'shop-sidebar',
        'description' => 'Widgets in this area will be displayed on the shop page and product archives.',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));
}
add_action('widgets_init', 'register_theme_sidebars');

// Remove unused functions.
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'index_rel_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
remove_action( 'wp_head', 'adjacent_posts_rel_link', 10, 0 );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
remove_action('welcome_panel', 'wp_welcome_panel');

// Remove comments.
add_action( 'admin_menu', 'my_remove_admin_menus' );
function my_remove_admin_menus() {
    remove_menu_page( 'edit-comments.php' );
}
add_action('init', 'remove_comment_support', 100);
function remove_comment_support() {
    remove_post_type_support( 'post', 'comments' );
    remove_post_type_support( 'page', 'comments' );
}
function mytheme_admin_bar_render() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
}
add_action( 'wp_before_admin_bar_render', 'mytheme_admin_bar_render' );

// Remove tags from default post type.
add_action('init', 'my_register_post_tags');
function my_register_post_tags() {
    register_taxonomy('post_tag', array('cpt'));
}

// Allow SVG filetypes to be uploaded
function additional_filetypes($mime_types){
    $mime_types['svg'] = 'image/svg+xml';
    return $mime_types;
}
add_filter('upload_mimes', 'additional_filetypes', 1, 1);


// Disable site health dashboard
add_action('wp_dashboard_setup', 'remove_site_health_dashboard_widget');
function remove_site_health_dashboard_widget()
{
    remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
}
