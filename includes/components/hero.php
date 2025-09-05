<?php
$layout = get_sub_field('hero_layout_selector');

switch ($layout) {
    case 'background_video':
        get_template_part('includes/components/hero-layouts/background-video');
        break;

    case 'background_image':
        get_template_part('includes/components/hero-layouts/background-image');
        break;

    case 'image_carousel_text':
        get_template_part('includes/components/hero-layouts/image-carousel-text');
        break;
}
