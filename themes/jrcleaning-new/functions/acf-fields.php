<?php
/**
 * ACF local field groups: the "Page Blocks" flexible content + Theme Settings.
 * Registered in code so the theme is self-contained (acf-json still syncs admin edits).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Reusable buttons sub-field (repeater) used by several blocks.
 */
function jrc_buttons_subfield( $key ) {
	return array(
		'key'        => 'field_jrc_btns_' . $key,
		'label'      => 'Buttons',
		'name'       => 'buttons',
		'type'       => 'repeater',
		'layout'     => 'block',
		'button_label' => 'Add button',
		'max'        => 3,
		'sub_fields' => array(
			array(
				'key'   => 'field_jrc_btn_link_' . $key,
				'label' => 'Link',
				'name'  => 'link',
				'type'  => 'link',
			),
			array(
				'key'     => 'field_jrc_btn_style_' . $key,
				'label'   => 'Style',
				'name'    => 'style',
				'type'    => 'select',
				'choices' => array(
					'primary' => 'Primary (teal)',
					'navy'    => 'Navy',
					'outline' => 'Outline',
					'white'   => 'White',
				),
				'default_value' => 'primary',
			),
		),
	);
}

/**
 * Reusable "Related links" flexible-content layout, for internal linking.
 *
 * Shared by the page builder ("blocks") and the service pages ("service_blocks") so an
 * editor sees the same block wherever they are. Internal targets are stored as a post
 * ID, not a URL, so renaming a page does not quietly break every link pointing at it.
 *
 * @param string $key Unique suffix, keeps the generated field keys distinct per group.
 */
function jrc_links_layout( $key ) {
	$k = 'field_jrc_lnk_';

	return array(
		'key'        => 'layout_jrc_links_' . $key,
		'name'       => 'links',
		'label'      => 'Related links (internal linking)',
		'display'    => 'block',
		'sub_fields' => array(
			array(
				'key'          => $k . 'anchor_' . $key,
				'label'        => 'Anchor ID (optional)',
				'name'         => 'anchor',
				'type'         => 'text',
				'instructions' => 'Optional slug for deep-linking, e.g. "related" gives #related.',
			),
			array(
				'key'           => $k . 'heading_' . $key,
				'label'         => 'Heading',
				'name'          => 'heading',
				'type'          => 'text',
				'default_value' => 'Related services',
			),
			array(
				'key'   => $k . 'intro_' . $key,
				'label' => 'Intro (optional)',
				'name'  => 'intro',
				'type'  => 'textarea',
				'rows'  => 2,
				'new_lines' => '',
			),
			array(
				'key'           => $k . 'style_' . $key,
				'label'         => 'Layout',
				'name'          => 'style',
				'type'          => 'button_group',
				'choices'       => array(
					'cards' => 'Cards',
					'list'  => 'Simple list',
				),
				'default_value' => 'cards',
				'instructions'  => 'Cards suit 2-4 links with a line of description. Use the list for a longer run of links.',
			),
			array(
				'key'          => $k . 'items_' . $key,
				'label'        => 'Links',
				'name'         => 'items',
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => 'Add link',
				'sub_fields'   => array(
					array(
						'key'           => $k . 'type_' . $key,
						'label'         => 'Link to',
						'name'          => 'type',
						'type'          => 'button_group',
						'choices'       => array(
							'internal' => 'A page on this site',
							'custom'   => 'Custom URL',
						),
						'default_value' => 'internal',
					),
					array(
						'key'               => $k . 'page_' . $key,
						'label'             => 'Page',
						'name'              => 'page',
						'type'              => 'post_object',
						'post_type'         => array( 'service', 'page', 'case_studies', 'post' ),
						'return_format'     => 'id',
						'ui'                => 1,
						'allow_null'        => 0,
						'conditional_logic' => array( array( array( 'field' => $k . 'type_' . $key, 'operator' => '==', 'value' => 'internal' ) ) ),
					),
					array(
						'key'               => $k . 'label_' . $key,
						'label'             => 'Anchor text (optional)',
						'name'              => 'label',
						'type'              => 'text',
						'instructions'      => 'The words the link is made from. Leave blank to use the page title. Descriptive text here is worth more than "click here" or "read more".',
						'conditional_logic' => array( array( array( 'field' => $k . 'type_' . $key, 'operator' => '==', 'value' => 'internal' ) ) ),
					),
					array(
						'key'               => $k . 'url_' . $key,
						'label'             => 'Custom link',
						'name'              => 'custom_link',
						'type'              => 'link',
						'instructions'      => 'Use for an anchor on another page, e.g. /service/add-on-services/#carpet, or an external site.',
						'conditional_logic' => array( array( array( 'field' => $k . 'type_' . $key, 'operator' => '==', 'value' => 'custom' ) ) ),
					),
					array(
						'key'          => $k . 'desc_' . $key,
						'label'        => 'Description (optional)',
						'name'         => 'description',
						'type'         => 'text',
						'instructions' => 'One short line, shown under the link in the card layout.',
					),
				),
			),
			jrc_buttons_subfield( 'links_' . $key ),
		),
	);
}

add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'      => 'group_jrc_page_blocks',
		'title'    => 'Page Blocks',
		'fields'   => array(
			array(
				'key'          => 'field_jrc_blocks',
				'label'        => 'Blocks',
				'name'         => 'blocks',
				'type'         => 'flexible_content',
				'button_label' => 'Add block',
				'layouts'      => array(

					// ---------------- HERO ----------------
					'hero' => array(
						'key'        => 'layout_jrc_hero',
						'name'       => 'hero',
						'label'      => 'Hero',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_hero_rating', 'label' => 'Rating pill text', 'name' => 'rating_text', 'type' => 'text', 'default_value' => 'Rated 5.0 across Worcestershire' ),
							array( 'key' => 'field_jrc_hero_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_hero_highlight', 'label' => 'Highlighted heading (teal)', 'name' => 'highlight', 'type' => 'text', 'instructions' => 'Appended to the heading in teal.' ),
							array( 'key' => 'field_jrc_hero_sub', 'label' => 'Subheading', 'name' => 'subheading', 'type' => 'textarea', 'rows' => 3 ),
							jrc_buttons_subfield( 'hero' ),
							array( 'key' => 'field_jrc_hero_ticks', 'label' => 'Trust ticks', 'name' => 'ticks', 'type' => 'repeater', 'layout' => 'table', 'max' => 3, 'button_label' => 'Add tick', 'sub_fields' => array(
								array( 'key' => 'field_jrc_hero_tick', 'label' => 'Tick', 'name' => 'text', 'type' => 'text' ),
							) ),
							array( 'key' => 'field_jrc_hero_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium' ),
							array( 'key' => 'field_jrc_hero_stat_num', 'label' => 'Floating stat, number', 'name' => 'stat_number', 'type' => 'text', 'default_value' => '99.6%' ),
							array( 'key' => 'field_jrc_hero_stat_lab', 'label' => 'Floating stat, label', 'name' => 'stat_label', 'type' => 'text', 'default_value' => 'end-of-tenancy pass rate' ),
							array( 'key' => 'field_jrc_hero_trust', 'label' => 'Floating trust card (replaces the stat)', 'name' => 'trust_text', 'type' => 'text', 'instructions' => 'Optional social-proof line, e.g. "Trusted by local estate agents". When filled it replaces the numeric stat card above; the stat values are kept, so clearing this brings the stat back.' ),
						array( 'key' => 'field_jrc_hero_badge', 'label' => 'Floating badge text', 'name' => 'badge', 'type' => 'text', 'default_value' => 'Photo report after every clean' ),
						),
					),

					// ------------- TRUST STRIP -------------
					'trust_strip' => array(
						'key'        => 'layout_jrc_trust',
						'name'       => 'trust_strip',
						'label'      => 'Trust strip',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_trust_items', 'label' => 'Stats', 'name' => 'stats', 'type' => 'repeater', 'layout' => 'table', 'max' => 4, 'button_label' => 'Add stat', 'sub_fields' => array(
								array( 'key' => 'field_jrc_trust_num', 'label' => 'Number', 'name' => 'number', 'type' => 'text' ),
								array( 'key' => 'field_jrc_trust_suf', 'label' => 'Suffix (teal)', 'name' => 'suffix', 'type' => 'text' ),
								array( 'key' => 'field_jrc_trust_lab', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ),
							) ),
						),
					),

					// -------------- SERVICES --------------
					'services' => array(
						'key'        => 'layout_jrc_services',
						'name'       => 'services',
						'label'      => 'Services grid',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_svc_eyebrow', 'label' => 'Eyebrow', 'name' => 'eyebrow', 'type' => 'text', 'default_value' => 'What we do' ),
							array( 'key' => 'field_jrc_svc_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_svc_intro', 'label' => 'Intro', 'name' => 'intro', 'type' => 'textarea', 'rows' => 2 ),
							array( 'key' => 'field_jrc_svc_cards', 'label' => 'Cards', 'name' => 'cards', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add service', 'sub_fields' => array(
								array( 'key' => 'field_jrc_svc_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'thumbnail' ),
								array( 'key' => 'field_jrc_svc_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
								array( 'key' => 'field_jrc_svc_text', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2 ),
								array( 'key' => 'field_jrc_svc_link', 'label' => 'Link', 'name' => 'link', 'type' => 'link' ),
							) ),
							array( 'key' => 'field_jrc_svc_cta', 'label' => 'Show "not sure" CTA card', 'name' => 'show_cta', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ),
						),
					),

					// ------------- SPLIT (why us / content) -------------
					'split' => array(
						'key'        => 'layout_jrc_split',
						'name'       => 'split',
						'label'      => 'Split (image + content)',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_split_eyebrow', 'label' => 'Eyebrow', 'name' => 'eyebrow', 'type' => 'text' ),
							array( 'key' => 'field_jrc_split_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_split_text', 'label' => 'Text', 'name' => 'text', 'type' => 'wysiwyg', 'media_upload' => 0 ),
							array( 'key' => 'field_jrc_split_bullets', 'label' => 'Ticked points', 'name' => 'bullets', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add point', 'sub_fields' => array(
								array( 'key' => 'field_jrc_split_b_strong', 'label' => 'Lead-in (bold)', 'name' => 'strong', 'type' => 'text' ),
								array( 'key' => 'field_jrc_split_b_text', 'label' => 'Text', 'name' => 'text', 'type' => 'text' ),
							) ),
							jrc_buttons_subfield( 'split' ),
							array( 'key' => 'field_jrc_split_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium' ),
							array( 'key' => 'field_jrc_split_side', 'label' => 'Image side', 'name' => 'image_side', 'type' => 'button_group', 'choices' => array( 'left' => 'Left', 'right' => 'Right' ), 'default_value' => 'left' ),
						),
					),

					// ------------- TESTIMONIALS -------------
					'testimonials' => array(
						'key'        => 'layout_jrc_testimonials',
						'name'       => 'testimonials',
						'label'      => 'Testimonials',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_tst_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text', 'default_value' => 'Loved by our clients' ),
							array( 'key' => 'field_jrc_tst_items', 'label' => 'Testimonials', 'name' => 'items', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add testimonial', 'sub_fields' => array(
								array( 'key' => 'field_jrc_tst_stars', 'label' => 'Stars', 'name' => 'stars', 'type' => 'number', 'default_value' => 5, 'min' => 1, 'max' => 5 ),
								array( 'key' => 'field_jrc_tst_quote', 'label' => 'Quote', 'name' => 'quote', 'type' => 'textarea', 'rows' => 3 ),
								array( 'key' => 'field_jrc_tst_name', 'label' => 'Client name', 'name' => 'name', 'type' => 'text' ),
							) ),
						),
					),

					// ----------------- CTA -----------------
					'cta' => array(
						'key'        => 'layout_jrc_cta',
						'name'       => 'cta',
						'label'      => 'CTA strip',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_cta_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_cta_text', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2 ),
							jrc_buttons_subfield( 'cta' ),
						),
					),

					// -------------- TEXT BLOCK --------------
					'text_block' => array(
						'key'        => 'layout_jrc_text',
						'name'       => 'text_block',
						'label'      => 'Text block',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_text_content', 'label' => 'Content', 'name' => 'content', 'type' => 'wysiwyg' ),
						),
					),

					// -------------- IMAGE BLOCK --------------
					'image_block' => array(
						'key'        => 'layout_jrc_image',
						'name'       => 'image_block',
						'label'      => 'Image block',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_img_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array' ),
							array( 'key' => 'field_jrc_img_full', 'label' => 'Full width', 'name' => 'full_width', 'type' => 'true_false', 'ui' => 1 ),
							array( 'key' => 'field_jrc_img_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
						),
					),

					// ------------- INNER HERO -------------
					'inner_hero' => array(
						'key'        => 'layout_jrc_inner_hero',
						'name'       => 'inner_hero',
						'label'      => 'Inner hero (breadcrumb + title)',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_ih_eyebrow', 'label' => 'Eyebrow', 'name' => 'eyebrow', 'type' => 'text' ),
							array( 'key' => 'field_jrc_ih_heading', 'label' => 'Heading (blank = page title)', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_ih_show', 'label' => 'Show heading', 'name' => 'show_heading', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ),
						),
					),

					// ------------- CONTACT FORM -------------
					'contact_form' => array(
						'key'        => 'layout_jrc_contact_form',
						'name'       => 'contact_form',
						'label'      => 'Contact form (Gravity Form)',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_cf_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_cf_text', 'label' => 'Intro', 'name' => 'text', 'type' => 'textarea', 'rows' => 2 ),
							array( 'key' => 'field_jrc_cf_form', 'label' => 'Gravity Form ID', 'name' => 'form_id', 'type' => 'number' ),
						),
					),

					// ------------- CONTACT DETAILS -------------
					'contact_details' => array(
						'key'        => 'layout_jrc_contact',
						'name'       => 'contact_details',
						'label'      => 'Contact details',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_con_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
							array( 'key' => 'field_jrc_con_text', 'label' => 'Intro', 'name' => 'text', 'type' => 'textarea', 'rows' => 2 ),
							array( 'key' => 'field_jrc_con_items', 'label' => 'Items', 'name' => 'items', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add item', 'sub_fields' => array(
								array( 'key' => 'field_jrc_con_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ),
								array( 'key' => 'field_jrc_con_value', 'label' => 'Value', 'name' => 'value', 'type' => 'text' ),
								array( 'key' => 'field_jrc_con_link', 'label' => 'Link (optional)', 'name' => 'link', 'type' => 'url' ),
							) ),
							array( 'key' => 'field_jrc_con_map', 'label' => 'Map embed (iframe URL)', 'name' => 'map_url', 'type' => 'url' ),
							array( 'key' => 'field_jrc_con_form', 'label' => 'Gravity Form ID (optional)', 'name' => 'form_id', 'type' => 'number' ),
						),
					),

									'links' => jrc_links_layout( 'pg' ),
				),
			),
		),
		'location' => array(
			array(
				array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ),
			),
		),
		'menu_order'    => 0,
		'position'      => 'normal',
		'style'         => 'seamless',
		'hide_on_screen' => array( 'the_content' ),
	) );

	// ---------------- SERVICE DETAIL FIELDS ----------------
	acf_add_local_field_group( array(
		'key'    => 'group_jrc_service',
		'title'  => 'Service details',
		'fields' => array(
			array( 'key' => 'field_jrc_svc_h1', 'label' => 'H1 override (SEO)', 'name' => 'seo_h1', 'type' => 'text', 'instructions' => 'Optional. Replaces the page heading only — the post title still drives menus, cards and breadcrumbs. Use it when the searched phrase differs from the internal name, e.g. title "End of Tenancy Clean" but H1 "End of Tenancy Cleaning".' ),
			array( 'key' => 'field_jrc_svc_sub', 'label' => 'Eyebrow (sub-heading)', 'name' => 'sub', 'type' => 'text', 'instructions' => 'e.g. "Turnovers & end of tenancy"' ),
			array( 'key' => 'field_jrc_svc_intro', 'label' => 'Intro', 'name' => 'intro', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_svc_popular', 'label' => 'Show "Most popular" tag', 'name' => 'popular', 'type' => 'true_false', 'ui' => 1 ),
			array( 'key' => 'field_jrc_svc_statn', 'label' => 'Stat, number', 'name' => 'stat_number', 'type' => 'text', 'instructions' => 'e.g. 99.6%' ),
			array( 'key' => 'field_jrc_svc_statl', 'label' => 'Stat, label', 'name' => 'stat_label', 'type' => 'text', 'instructions' => 'e.g. check-out pass rate' ),
			array( 'key' => 'field_jrc_svc_included', 'label' => "What's included", 'name' => 'included', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add item', 'sub_fields' => array(
				array( 'key' => 'field_jrc_svc_inc_item', 'label' => 'Item', 'name' => 'item', 'type' => 'text' ),
			) ),
			array( 'key' => 'field_jrc_svc_forwho', 'label' => "Who it's for", 'name' => 'for_who', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add audience', 'max' => 3, 'sub_fields' => array(
				array( 'key' => 'field_jrc_svc_fw_t', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
				array( 'key' => 'field_jrc_svc_fw_d', 'label' => 'Description', 'name' => 'desc', 'type' => 'text' ),
			) ),
		),
		'location' => array(
			array(
				array( 'param' => 'post_type', 'operator' => '==', 'value' => 'service' ),
			),
		),
		'menu_order' => 0,
		'style'      => 'default',
	) );

	// ---------------- THEME SETTINGS ----------------
	acf_add_local_field_group( array(
		'key'    => 'group_jrc_settings',
		'title'  => 'Theme Settings',
		'fields' => array(
			array( 'key' => 'field_jrc_set_logo', 'label' => 'Logo (navy, for light header)', 'name' => 'site_logo', 'type' => 'image', 'return_format' => 'array' ),
			array( 'key' => 'field_jrc_set_logo_white', 'label' => 'Logo (white, for dark areas)', 'name' => 'site_logo_white', 'type' => 'image', 'return_format' => 'array' ),
			array( 'key' => 'field_jrc_set_phone', 'label' => 'Phone number', 'name' => 'contact_phone', 'type' => 'text', 'default_value' => JRC_DEFAULT_PHONE ),
			array( 'key' => 'field_jrc_set_email', 'label' => 'Email', 'name' => 'contact_email', 'type' => 'email' ),
			array( 'key' => 'field_jrc_set_location', 'label' => 'Location line', 'name' => 'contact_location', 'type' => 'text', 'default_value' => 'Evesham, Worcestershire' ),
			array( 'key' => 'field_jrc_set_street', 'label' => 'Street address', 'name' => 'contact_street', 'type' => 'text', 'instructions' => 'Used in the LocalBusiness structured data. Google needs a street address and postcode to show local rich results.' ),
			array( 'key' => 'field_jrc_set_postcode', 'label' => 'Postcode', 'name' => 'contact_postcode', 'type' => 'text', 'instructions' => 'Used in the LocalBusiness structured data.' ),
			array( 'key' => 'field_jrc_set_locality', 'label' => 'Town / city', 'name' => 'contact_locality', 'type' => 'text', 'default_value' => 'Evesham' ),
			array( 'key' => 'field_jrc_set_region', 'label' => 'County', 'name' => 'contact_region', 'type' => 'text', 'default_value' => 'Worcestershire' ),
			array( 'key' => 'field_jrc_set_lat', 'label' => 'Latitude', 'name' => 'contact_lat', 'type' => 'text', 'instructions' => 'Optional. Adds geo coordinates to the structured data, e.g. 52.0920.' ),
			array( 'key' => 'field_jrc_set_lng', 'label' => 'Longitude', 'name' => 'contact_lng', 'type' => 'text', 'instructions' => 'Optional, e.g. -1.9470.' ),
			array( 'key' => 'field_jrc_set_rating', 'label' => 'Average review score', 'name' => 'reviews_rating', 'type' => 'number', 'min' => 1, 'max' => 5, 'step' => 0.1, 'instructions' => 'Optional. Adds an aggregateRating to the structured data, which is what produces review stars in Google results. Only used when a review count is also set. Enter the genuine figure from Bark/Google — inflating it risks a manual penalty.' ),
			array( 'key' => 'field_jrc_set_rating_count', 'label' => 'Number of reviews', 'name' => 'reviews_count', 'type' => 'number', 'min' => 1, 'instructions' => 'Optional. The number of reviews the score above is based on.' ),
			array( 'key' => 'field_jrc_set_footer_about', 'label' => 'Footer about text', 'name' => 'footer_about', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_set_fcta_show', 'label' => 'Show pre-footer CTA band', 'name' => 'footer_cta_show', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ),
			array( 'key' => 'field_jrc_set_fcta_heading', 'label' => 'Pre-footer CTA, heading', 'name' => 'footer_cta_heading', 'type' => 'text', 'default_value' => 'Ready for a spotless space?' ),
			array( 'key' => 'field_jrc_set_fcta_text', 'label' => 'Pre-footer CTA, text', 'name' => 'footer_cta_text', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Trained staff, guaranteed standards and a photo report after every clean.' ),
			array( 'key' => 'field_jrc_set_bark', 'label' => 'Bark reviews widget code', 'name' => 'bark_widget', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Paste the Bark reviews widget embed code to show it in the pre-footer CTA.' ),
			array( 'key' => 'field_jrc_set_contact_form', 'label' => 'Contact, Gravity Form ID', 'name' => 'contact_form_id', 'type' => 'number', 'default_value' => 1 ),
			array( 'key' => 'field_jrc_set_quote_form', 'label' => 'Quote, Gravity Form ID', 'name' => 'quote_form_id', 'type' => 'number' ),
			array( 'key' => 'field_jrc_set_partner_form', 'label' => 'Partners, Gravity Form ID', 'name' => 'partner_form_id', 'type' => 'number' ),
			array( 'key' => 'field_jrc_set_socials', 'label' => 'Socials', 'name' => 'socials', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add social', 'sub_fields' => array(
				array( 'key' => 'field_jrc_set_soc_platform', 'label' => 'Platform', 'name' => 'platform', 'type' => 'select', 'choices' => array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'X', 'tiktok' => 'TikTok', 'youtube' => 'YouTube' ) ),
				array( 'key' => 'field_jrc_set_soc_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url' ),
			) ),
		),
		'location' => array(
			array(
				array( 'param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings' ),
			),
		),
	) );

} );
