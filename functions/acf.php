<?php
// Create ACF options page.
add_action('acf/init', 'my_acf_op_init');
function my_acf_op_init() {
  if( function_exists('acf_add_options_page') ) {
    $options_page = acf_add_options_page(array(
      'page_title'    => __('Theme Settings'),
      'menu_title'    => __('Theme Settings'),
      'menu_slug'     => 'theme-settings',
      'capability'    => 'edit_posts',
      'redirect'      => false
    ));
  }
}

// Add a default image option to ACF fields.
add_action('acf/render_field_settings/type=image', 'add_default_value_to_image_field');
function add_default_value_to_image_field($field) {
  acf_render_field_setting( $field, array(
    'label'			=> 'Default Image',
    'instructions'		=> 'Appears when creating a new post',
    'type'			=> 'image',
    'name'			=> 'default_value',
  ));
}

// Add functionality for auto-collapse of ACF boxes.
add_action('acf/input/admin_head', function() { ?>
<script>
(function($) {
  $(document).ready(function() {
    var collapseButtonClass = 'collapse-all';
    $('.acf-field-flexible-content > .acf-label')
      .append('<a class="' + collapseButtonClass +
        '" style="position: absolute; top: 0; right: 0; cursor: pointer;">Collapse All</a>');
    $('.' + collapseButtonClass).on('click', function() {
      $('.acf-flexible-content .layout:not(.-collapsed) .acf-fc-layout-controls .-collapse').click();
    });
  });
})(jQuery);
</script>
<?php });

// Change ACF preview image path.
add_filter ('acf-flexible-content-extended.images_path', function () {
  return "/images/acf";
});

// ACF .json launch sync.
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point( $path ) {
  // update path
  $path = get_stylesheet_directory() . '/acf-json';  
  // return
  return $path;  
}

add_filter('acf/load_field/name=hero_layout_selector', function ($field) {
    $base = get_stylesheet_directory_uri() . '/images/acf/hero-layouts/';

    $field['choices'] = [
        'background_video'   => '<div class="selector-wrapper"><h4>Background Video</h4><img src="' . $base . 'background-video.png" style="width:250px;height:auto;min-height:93px;border:1px solid #ccc;"></div>',
        'background_image'   => '<div class="selector-wrapper"><h4>Background Image</h4><img src="' . $base . 'background-image.png" style="width:250px;height:auto;min-height:93px;border:1px solid #ccc;"></div>',
        'image_carousel_text'=> '<div class="selector-wrapper"><h4>Image Carousel with Text</h4><img src="' . $base . 'image-carousel-text.png" style="width:250px;height:auto;min-height:93px;border:1px solid #ccc;"></div>',
    ];

    // Keep stored value clean
    $field['return_format'] = 'value';

    return $field;
});

