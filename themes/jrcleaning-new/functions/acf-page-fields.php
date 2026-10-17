<?php
/**
 * ACF field groups for the bespoke page templates, so their content is editable.
 * Templates read these with the original copy as fallback defaults (see each template),
 * so display is unchanged until a field is filled in.
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }

	$tpl = function ( $file ) {
		return array( array( array( 'param' => 'page_template', 'operator' => '==', 'value' => $file ) ) );
	};

	// ---------------- CONTACT ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_pg_contact',
		'title'    => 'Contact page',
		'location' => $tpl( 'page-contact.php' ),
		'fields'   => array(
			array( 'key' => 'field_jrc_ct_heading', 'label' => 'Heading', 'name' => 'ct_heading', 'type' => 'text', 'placeholder' => 'Get in touch' ),
			array( 'key' => 'field_jrc_ct_intro', 'label' => 'Intro', 'name' => 'ct_intro', 'type' => 'textarea', 'rows' => 3 ),
				array( 'key' => 'field_jrc_ct_map', 'label' => 'Map embed code', 'name' => 'ct_map_embed', 'type' => 'textarea', 'rows' => 4, 'instructions' => 'Paste a Google Maps "Embed" iframe. Leave blank to hide the map.' ),
			array( 'key' => 'field_jrc_ct_hours', 'label' => 'Office hours', 'name' => 'ct_hours', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add row', 'sub_fields' => array(
				array( 'key' => 'field_jrc_ct_day', 'label' => 'Day', 'name' => 'day', 'type' => 'text' ),
				array( 'key' => 'field_jrc_ct_time', 'label' => 'Hours', 'name' => 'time', 'type' => 'text' ),
			) ),
		),
	) );

	// ---------------- AREAS ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_pg_areas',
		'title'    => 'Areas page',
		'location' => $tpl( 'page-areas.php' ),
		'fields'   => array(
			array( 'key' => 'field_jrc_ar_heading', 'label' => 'Heading', 'name' => 'ar_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_ar_intro', 'label' => 'Intro', 'name' => 'ar_intro', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_ar_band_t', 'label' => 'Postcode band, title', 'name' => 'ar_band_title', 'type' => 'text' ),
			array( 'key' => 'field_jrc_ar_band_s', 'label' => 'Postcode band, subtext', 'name' => 'ar_band_sub', 'type' => 'text' ),
			array( 'key' => 'field_jrc_cv_areas', 'label' => 'Covered postcode areas', 'name' => 'cv_areas', 'type' => 'textarea', 'rows' => 6, 'instructions' => 'Drives the postcode checker on this page. One per line as PREFIX = County, e.g. "WR = Worcestershire". Leave blank to use the built-in defaults.' ),
			array( 'key' => 'field_jrc_ar_counties', 'label' => 'Counties', 'name' => 'ar_counties', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add county', 'sub_fields' => array(
				array( 'key' => 'field_jrc_ar_c_name', 'label' => 'County name', 'name' => 'name', 'type' => 'text' ),
				array( 'key' => 'field_jrc_ar_c_shape', 'label' => 'Marker', 'name' => 'shape', 'type' => 'select', 'choices' => array( 'circle' => 'Circle', 'square' => 'Square', 'diamond' => 'Diamond' ), 'default_value' => 'circle' ),
				array( 'key' => 'field_jrc_ar_c_towns', 'label' => 'Towns (one per line)', 'name' => 'towns', 'type' => 'textarea', 'rows' => 6 ),
			) ),
			array( 'key' => 'field_jrc_ar_map', 'label' => 'Coverage map embed code', 'name' => 'ar_map_embed', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Optional. Paste a Google My Maps iframe to show the service area beside the closing section. Leave empty to use the same map as the Contact page; enter a single space to hide the map, which falls back to this page\'s featured image.' ),
			array( 'key' => 'field_jrc_ar_split_h', 'label' => 'Closing section, heading', 'name' => 'ar_split_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_ar_split_t', 'label' => 'Closing section, text', 'name' => 'ar_split_text', 'type' => 'textarea', 'rows' => 3 ),
		),
	) );

	// ---------------- PRODUCTS ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_pg_products',
		'title'    => 'Products page',
		'location' => $tpl( 'page-products.php' ),
		'fields'   => array(
			array( 'key' => 'field_jrc_pr_heading', 'label' => 'Heading', 'name' => 'pr_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_pr_intro', 'label' => 'Intro', 'name' => 'pr_intro', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_pr_features', 'label' => 'Feature cards', 'name' => 'pr_features', 'type' => 'repeater', 'layout' => 'block', 'max' => 3, 'button_label' => 'Add feature', 'sub_fields' => array(
				array( 'key' => 'field_jrc_pr_f_icon', 'label' => 'Icon (emoji)', 'name' => 'icon', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pr_f_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pr_f_text', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2 ),
			) ),
			array( 'key' => 'field_jrc_pr_split_h', 'label' => 'Equipment, heading', 'name' => 'pr_split_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_pr_split_e', 'label' => 'Equipment, eyebrow', 'name' => 'pr_split_eyebrow', 'type' => 'text' ),
			array( 'key' => 'field_jrc_pr_split_t', 'label' => 'Equipment, text', 'name' => 'pr_split_text', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_pr_bullets', 'label' => 'Equipment, bullets', 'name' => 'pr_bullets', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add bullet', 'sub_fields' => array(
				array( 'key' => 'field_jrc_pr_b_strong', 'label' => 'Lead-in (bold)', 'name' => 'strong', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pr_b_text', 'label' => 'Text', 'name' => 'text', 'type' => 'text' ),
			) ),
			array( 'key' => 'field_jrc_pr_cta_h', 'label' => 'CTA, heading', 'name' => 'pr_cta_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_pr_cta_t', 'label' => 'CTA, text', 'name' => 'pr_cta_text', 'type' => 'textarea', 'rows' => 2 ),
		),
	) );

	// ---------------- SERVICES ARCHIVE (Theme Settings, since a CPT archive has no page) ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_services_page',
		'title'    => 'Services page',
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings' ) ) ),
		'fields'   => array(
			array( 'key' => 'field_jrc_sp_heading', 'label' => 'Services, heading', 'name' => 'services_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_sp_intro', 'label' => 'Services, intro', 'name' => 'services_intro', 'type' => 'textarea', 'rows' => 2 ),
			array( 'key' => 'field_jrc_sp_steps', 'label' => 'Services, "How it works" steps', 'name' => 'services_steps', 'type' => 'repeater', 'layout' => 'block', 'max' => 4, 'button_label' => 'Add step', 'sub_fields' => array(
				array( 'key' => 'field_jrc_sp_s_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
				array( 'key' => 'field_jrc_sp_s_text', 'label' => 'Text', 'name' => 'text', 'type' => 'text' ),
			) ),
		),
	) );

	// ---------------- PARTNER PROGRAMME (Theme Settings: channels + tiers) ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_partner_data',
		'title'    => 'Partner programme',
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings' ) ) ),
		'fields'   => array(
			array( 'key' => 'field_jrc_pc', 'label' => 'Partner channels', 'name' => 'partner_channels', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add channel', 'instructions' => 'Slug must match the channel page slug under /partners/.', 'sub_fields' => array(
				array( 'key' => 'field_jrc_pc_slug', 'label' => 'Slug', 'name' => 'slug', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pc_num', 'label' => 'Number (01)', 'name' => 'num', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pc_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pc_sub', 'label' => 'Sub', 'name' => 'sub', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pc_short', 'label' => 'Short (overview card)', 'name' => 'short', 'type' => 'textarea', 'rows' => 2 ),
				array( 'key' => 'field_jrc_pc_intro', 'label' => 'Intro', 'name' => 'intro', 'type' => 'textarea', 'rows' => 3 ),
				array( 'key' => 'field_jrc_pc_value', 'label' => 'Value (navy card)', 'name' => 'value', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pc_why', 'label' => 'Why refer us', 'name' => 'why', 'type' => 'repeater', 'layout' => 'block', 'max' => 3, 'button_label' => 'Add reason', 'sub_fields' => array(
					array( 'key' => 'field_jrc_pc_why_t', 'label' => 'Title', 'name' => 't', 'type' => 'text' ),
					array( 'key' => 'field_jrc_pc_why_d', 'label' => 'Description', 'name' => 'd', 'type' => 'text' ),
				) ),
			) ),
			array( 'key' => 'field_jrc_pt', 'label' => 'Reward tiers', 'name' => 'partner_tiers', 'type' => 'repeater', 'layout' => 'table', 'max' => 3, 'button_label' => 'Add tier', 'sub_fields' => array(
				array( 'key' => 'field_jrc_pt_tier', 'label' => 'Tier', 'name' => 'tier', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pt_qual', 'label' => 'Qualifies at', 'name' => 'qual', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pt_cust', 'label' => 'Client discount', 'name' => 'customer', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pt_aff', 'label' => 'Your reward', 'name' => 'affiliate', 'type' => 'text' ),
				array( 'key' => 'field_jrc_pt_top', 'label' => 'Most rewarding', 'name' => 'top', 'type' => 'true_false', 'ui' => 1 ),
			) ),
		),
	) );

	// ---------------- PARTNERS OVERVIEW ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_pg_partners',
		'title'    => 'Partners overview',
		'location' => $tpl( 'page-partners.php' ),
		'fields'   => array(
			array( 'key' => 'field_jrc_po_heading', 'label' => 'Hero heading (HTML allowed)', 'name' => 'po_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_po_intro', 'label' => 'Hero intro', 'name' => 'po_intro', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_po_why', 'label' => 'Why partner with us', 'name' => 'po_why', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add point', 'sub_fields' => array(
				array( 'key' => 'field_jrc_po_why_t', 'label' => 'Point', 'name' => 'text', 'type' => 'text' ),
			) ),
			array( 'key' => 'field_jrc_po_cta_h', 'label' => 'CTA heading', 'name' => 'po_cta_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_po_cta_t', 'label' => 'CTA text', 'name' => 'po_cta_text', 'type' => 'text' ),
		),
	) );

	// ---------------- PARTNER REGISTRATION ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_pg_register',
		'title'    => 'Partner registration',
		'location' => $tpl( 'page-partner-register.php' ),
		'fields'   => array(
			array( 'key' => 'field_jrc_rg_heading', 'label' => 'Heading', 'name' => 'rg_heading', 'type' => 'text' ),
			array( 'key' => 'field_jrc_rg_intro', 'label' => 'Intro', 'name' => 'rg_intro', 'type' => 'textarea', 'rows' => 3 ),
		),
	) );

	// ---------------- QUOTE WIZARD ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_pg_quote',
		'title'    => 'Quote wizard',
		'location' => $tpl( 'page-quote.php' ),
		'fields'   => array(
			array( 'key' => 'field_jrc_qz_services', 'label' => 'Service options (step 1)', 'name' => 'qz_services', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add service', 'sub_fields' => array(
				array( 'key' => 'field_jrc_qz_s_t', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
				array( 'key' => 'field_jrc_qz_s_s', 'label' => 'Subtitle', 'name' => 'sub', 'type' => 'text' ),
			) ),
		),
	) );

	// NB: the old "Coverage checker" group (page-coverage.php) was retired — that
	// template was dead and its cv_areas field never reached the live checker.
	// The field now lives on the Areas page group above, beside the checker itself.

	// ---------------- SERVICE CONTENT BLOCKS (Service CPT) ----------------
	acf_add_local_field_group( array(
		'key'      => 'group_jrc_service_blocks',
		'title'    => 'Service content',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'service' ) ) ),
		'menu_order' => 5,
		'fields'   => array(
			array(
				'key'          => 'field_jrc_sb',
				'label'        => 'Content blocks',
				'name'         => 'service_blocks',
				'type'         => 'flexible_content',
				'button_label' => 'Add block',
				'instructions' => 'Builds the body of the service page. Leave empty to fall back to the classic editor content.',
				'layouts'      => array(
					'feature' => array(
						'key'        => 'layout_jrc_sb_feat',
						'name'       => 'feature',
						'label'      => 'Feature (title + intro + checklist)',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_sb_feat_anchor', 'label' => 'Anchor ID (optional)', 'name' => 'anchor', 'type' => 'text', 'instructions' => 'Optional slug for deep-linking, e.g. "carpet" gives #carpet.' ),
							array( 'key' => 'field_jrc_sb_feat_eyebrow', 'label' => 'Eyebrow (optional)', 'name' => 'eyebrow', 'type' => 'text', 'instructions' => 'Small teal label above the title, e.g. "The detail" or "Pricing".' ),
							array( 'key' => 'field_jrc_sb_feat_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
							array( 'key' => 'field_jrc_sb_feat_intro', 'label' => 'Intro', 'name' => 'intro', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '' ),
							array( 'key' => 'field_jrc_sb_feat_items', 'label' => "What's included", 'name' => 'items', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add item', 'sub_fields' => array(
								array( 'key' => 'field_jrc_sb_feat_item', 'label' => 'Item', 'name' => 'item', 'type' => 'text' ),
							) ),
							array( 'key' => 'field_jrc_sb_feat_cta', 'label' => 'Button (optional)', 'name' => 'cta', 'type' => 'link', 'instructions' => 'Shown under the intro on the left. Use for a quote or contact link.' ),
													jrc_buttons_subfield( 'sb_feature' ),
						),
					),
					'steps' => array(
						'key'        => 'layout_jrc_sb_steps',
						'name'       => 'steps',
						'label'      => 'Steps (how it works)',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_sb_steps_anchor', 'label' => 'Anchor ID (optional)', 'name' => 'anchor', 'type' => 'text' ),
							array( 'key' => 'field_jrc_sb_steps_h', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text', 'default_value' => 'How it works' ),
							array( 'key' => 'field_jrc_sb_steps_items', 'label' => 'Steps', 'name' => 'items', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add step', 'sub_fields' => array(
								array( 'key' => 'field_jrc_sb_steps_t', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
								array( 'key' => 'field_jrc_sb_steps_x', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '' ),
							) ),
													jrc_buttons_subfield( 'sb_steps' ),
						),
					),
					'reasons' => array(
						'key'        => 'layout_jrc_sb_reasons',
						'name'       => 'reasons',
						'label'      => 'Reasons (why choose us)',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_sb_reasons_anchor', 'label' => 'Anchor ID (optional)', 'name' => 'anchor', 'type' => 'text' ),
							array( 'key' => 'field_jrc_sb_reasons_h', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text', 'default_value' => 'Why choose us' ),
							array( 'key' => 'field_jrc_sb_reasons_intro', 'label' => 'Intro (optional)', 'name' => 'intro', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '' ),
							array( 'key' => 'field_jrc_sb_reasons_items', 'label' => 'Reasons', 'name' => 'items', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add reason', 'sub_fields' => array(
								array( 'key' => 'field_jrc_sb_reasons_t', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
								array( 'key' => 'field_jrc_sb_reasons_x', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '' ),
							) ),
													jrc_buttons_subfield( 'sb_reasons' ),
						),
					),
					'rich_text' => array(
						'key'        => 'layout_jrc_sb_rt',
						'name'       => 'rich_text',
						'label'      => 'Rich text',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_sb_rt_anchor', 'label' => 'Anchor ID (optional)', 'name' => 'anchor', 'type' => 'text', 'instructions' => 'Optional slug for deep-linking, e.g. "carpet" gives #carpet.' ),
							array( 'key' => 'field_jrc_sb_rt_c', 'label' => 'Content', 'name' => 'content', 'type' => 'wysiwyg', 'media_upload' => 0 ),
													jrc_buttons_subfield( 'sb_rich_text' ),
						),
					),
					// Service pages had no way to show a photograph: the CPT has no
					// featured-image support and no other layout takes an image.
					'image' => array(
						'key'        => 'layout_jrc_sb_img',
						'name'       => 'image',
						'label'      => 'Image',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_sb_img_anchor', 'label' => 'Anchor ID (optional)', 'name' => 'anchor', 'type' => 'text' ),
							array( 'key' => 'field_jrc_sb_img_img', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium' ),
							array( 'key' => 'field_jrc_sb_img_cap', 'label' => 'Caption (optional)', 'name' => 'caption', 'type' => 'text', 'instructions' => 'Shown under the image, and used as the alt text if the image has none of its own.' ),
							array( 'key' => 'field_jrc_sb_img_full', 'label' => 'Full width', 'name' => 'full_width', 'type' => 'true_false', 'ui' => 1, 'instructions' => 'Break out of the page container edge to edge.' ),
													jrc_buttons_subfield( 'sb_image' ),
						),
					),
					'faq' => array(
						'key'        => 'layout_jrc_sb_faq',
						'name'       => 'faq',
						'label'      => 'FAQ / accordion',
						'display'    => 'block',
						'sub_fields' => array(
							array( 'key' => 'field_jrc_sb_faq_h', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text', 'default_value' => 'Common questions' ),
							array( 'key' => 'field_jrc_sb_faq_items', 'label' => 'Questions', 'name' => 'items', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add question', 'sub_fields' => array(
								array( 'key' => 'field_jrc_sb_faq_q', 'label' => 'Question', 'name' => 'question', 'type' => 'text' ),
								array( 'key' => 'field_jrc_sb_faq_a', 'label' => 'Answer', 'name' => 'answer', 'type' => 'wysiwyg', 'media_upload' => 0 ),
							) ),
													jrc_buttons_subfield( 'sb_faq' ),
						),
					),
									'links' => jrc_links_layout( 'sb' ),
				),
			),
		),
	) );
} );
