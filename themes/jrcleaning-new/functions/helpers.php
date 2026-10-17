<?php
/**
 * Shared template helpers.
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render an <img> from an ACF image array with explicit width/height and
 * performance attributes. Dimensions prevent layout shift (CLS) and satisfy
 * the "missing image size attributes" SEO check; lazy/async loading defers
 * off-screen images.
 *
 * @param array|int $image ACF image array (return format "array") or attachment ID.
 * @param array     $args  {
 *     @type string $alt           Override alt text (defaults to the image's own alt).
 *     @type string $fallback_alt  Used only when the image has no alt of its own.
 *     @type string $class         Class attribute for the <img>.
 *     @type string $size          Registered image size to render. Default 'full'.
 *     @type string $sizes         Override the generated `sizes` hint (e.g. "(min-width:768px) 50vw, 100vw").
 *     @type bool   $lazy          Lazy-load + async decode. Default true.
 *     @type bool   $priority      Above-the-fold: eager load + fetchpriority="high". Default false.
 * }
 * @return void Echoes the markup (nothing if there is no usable image).
 */
function jrc_image_tag( $image, $args = array() ) {
	if ( empty( $image ) ) { return; }

	$size   = $args['size'] ?? 'full';
	$srcset = '';
	$sizes  = '';

	// Resolve an attachment ID from either form, so ACF arrays get responsive
	// images too rather than always shipping the full-size file.
	$id = 0;
	if ( is_numeric( $image ) ) {
		$id = (int) $image;
	} elseif ( is_array( $image ) ) {
		$id = (int) ( $image['ID'] ?? $image['id'] ?? 0 );
	}

	if ( $id ) {
		$src_data = wp_get_attachment_image_src( $id, $size );
		$src      = $src_data ? $src_data[0] : '';
		$w        = $src_data ? $src_data[1] : '';
		$h        = $src_data ? $src_data[2] : '';
		$alt      = get_post_meta( $id, '_wp_attachment_image_alt', true );
		$srcset   = (string) wp_get_attachment_image_srcset( $id, $size );
		$sizes    = $srcset ? (string) wp_get_attachment_image_sizes( $id, $size ) : '';
	} else {
		// No ID available (raw array): fall back to the single URL it carries.
		$src = $image['url'] ?? '';
		$w   = $image['width'] ?? '';
		$h   = $image['height'] ?? '';
		$alt = $image['alt'] ?? '';
	}

	if ( ! $src ) { return; }

	// SVGs carry no dimensions in WordPress's attachment metadata, so they render
	// with no width/height and the layout jumps when they load (CLS). Read the
	// intrinsic size out of the file itself and cache it on the attachment.
	if ( ( ! $w || ! $h ) && preg_match( '/\.svgz?$/i', (string) $src ) ) {
		list( $w, $h ) = jrc_svg_dimensions( $id, $src );
	}

	// Callers can override the sizes hint when they know the rendered width.
	if ( ! empty( $args['sizes'] ) ) {
		$sizes = $args['sizes'];
	}

	$alt = isset( $args['alt'] ) ? $args['alt'] : $alt;
	// Fall back to a caller-supplied description only when the image has no alt
	// of its own. Real alt text set in the media library always takes priority.
	if ( '' === trim( (string) $alt ) && ! empty( $args['fallback_alt'] ) ) {
		$alt = wp_strip_all_tags( $args['fallback_alt'] );
	}
	$priority = ! empty( $args['priority'] );
	$lazy     = $priority ? false : ( ! isset( $args['lazy'] ) || $args['lazy'] );

	$attrs   = array();
	$attrs[] = 'src="' . esc_url( $src ) . '"';
	if ( $srcset ) { $attrs[] = 'srcset="' . esc_attr( $srcset ) . '"'; }
	if ( $sizes ) { $attrs[] = 'sizes="' . esc_attr( $sizes ) . '"'; }
	$attrs[] = 'alt="' . esc_attr( $alt ) . '"';
	if ( $w ) { $attrs[] = 'width="' . esc_attr( $w ) . '"'; }
	if ( $h ) { $attrs[] = 'height="' . esc_attr( $h ) . '"'; }
	if ( ! empty( $args['class'] ) ) { $attrs[] = 'class="' . esc_attr( $args['class'] ) . '"'; }

	if ( $priority ) {
		$attrs[] = 'loading="eager"';
		$attrs[] = 'fetchpriority="high"';
		$attrs[] = 'decoding="async"';
	} elseif ( $lazy ) {
		$attrs[] = 'loading="lazy"';
		$attrs[] = 'decoding="async"';
	}

	echo '<img ' . implode( ' ', $attrs ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput -- each attribute escaped above.
}

/** The phone number used whenever Theme Settings has none. The one place it is written. */
const JRC_DEFAULT_PHONE = '07507 657223';

/**
 * The business phone number, from Theme Settings or the default.
 *
 * @return string Display form, e.g. "07507 657223".
 */
function jrc_phone() {
	$phone = function_exists( 'get_field' ) ? trim( (string) get_field( 'contact_phone', 'options' ) ) : '';
	return '' !== $phone ? $phone : JRC_DEFAULT_PHONE;
}

/** The phone number with whitespace removed, for tel: links. */
function jrc_phone_tel() {
	return preg_replace( '/\s+/', '', jrc_phone() );
}

/**
 * Office hours: the fallback for the Contact page AND the source of the LocalBusiness
 * openingHoursSpecification, so the two can no longer drift apart.
 *
 * NB: when office hours are edited in the Contact page's ACF field, the schema keeps
 * these — the free-text field ("9am – 5pm") cannot be turned into schema reliably.
 * Update `schema` here when the hours genuinely change.
 *
 * @return array{display: array<int,array{day:string,time:string}>, schema: array<int,array>}
 */
function jrc_default_office_hours() {
	return array(
		'display' => array(
			array( 'day' => 'Mon – Fri', 'time' => '9am – 5pm' ),
			array( 'day' => 'Saturday', 'time' => 'Closed' ),
			array( 'day' => 'Sunday', 'time' => 'Closed' ),
		),
		'schema'  => array(
			array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ), 'opens' => '09:00', 'closes' => '17:00' ),
		),
	);
}

/**
 * The default service-area map embed, used on both the Contact and Areas pages.
 *
 * @return string
 */
function jrc_default_map_embed() {
	return '<iframe class="jr-map-embed" title="J R Cleaning service area" src="https://www.google.com/maps/d/embed?mid=1y1BWPQ2r4EmB67UWhbquzxinf0o5OWQ&amp;ehbc=2E312F&amp;noprof=1&amp;ll=52.0915,-1.9490&amp;z=8" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
}

/**
 * Sanitise a pasted map embed down to a single iframe with a known attribute set.
 *
 * Shared by the Contact and Areas templates so there is one allowlist to maintain
 * rather than two that can drift.
 *
 * @param string|null $raw     Field value. null means "not set" and falls back to the
 *                             default map; a blank/whitespace string hides the map.
 * @param string|null $default Embed to use when $raw is null. Defaults to the shared map.
 * @return string Safe HTML, or '' to render nothing.
 */
function jrc_map_embed_html( $raw, $default = null ) {
	$default = ( null === $default ) ? jrc_default_map_embed() : $default;
	$embed   = ( null === $raw ) ? $default : trim( (string) $raw );

	if ( '' === $embed ) {
		return '';
	}

	$html = wp_kses( $embed, array(
		'iframe' => array(
			'src' => array(), 'width' => array(), 'height' => array(), 'class' => array(),
			'title' => array(), 'style' => array(), 'frameborder' => array(),
			'loading' => array(), 'referrerpolicy' => array(), 'allowfullscreen' => array(),
			'allow' => array(), 'aria-label' => array(),
		),
	) );

	// Guarantee the sizing class survives a paste that omitted it.
	if ( $html && false === strpos( $html, 'jr-map-embed' ) ) {
		$html = str_replace( '<iframe', '<iframe class="jr-map-embed"', $html );
	}

	return $html;
}

/**
 * Intrinsic width/height of an SVG, from its width/height attributes or, failing
 * that, its viewBox. Cached in post meta so we only parse each file once.
 *
 * WordPress stores no dimensions for SVG attachments, which is why SVG icons
 * otherwise ship with no width/height and shift the layout as they load.
 *
 * @param int    $id  Attachment ID (0 if unknown — result is then not cached).
 * @param string $src URL of the SVG.
 * @return array{0:int|string,1:int|string} [ width, height ], or [ '', '' ] if undeterminable.
 */
function jrc_svg_dimensions( $id, $src ) {
	if ( $id ) {
		$cached = get_post_meta( $id, '_jrc_svg_dims', true );
		if ( is_array( $cached ) && isset( $cached[0], $cached[1] ) ) {
			return $cached;
		}
	}

	$dims = array( '', '' );

	// Resolve the URL back to a local path; never fetch over HTTP.
	$path = $id ? get_attached_file( $id ) : '';
	if ( ! $path || ! file_exists( $path ) ) {
		$uploads = wp_get_upload_dir();
		if ( 0 === strpos( $src, $uploads['baseurl'] ) ) {
			$path = $uploads['basedir'] . substr( $src, strlen( $uploads['baseurl'] ) );
		} elseif ( 0 === strpos( $src, JRC_URI ) ) {
			$path = JRC_DIR . substr( $src, strlen( JRC_URI ) );
		}
	}

	if ( $path && file_exists( $path ) ) {
		// The <svg> root element is at the top; no need to read the whole file.
		$head = (string) file_get_contents( $path, false, null, 0, 2048 );

		if ( preg_match( '/<svg\b[^>]*>/i', $head, $tag ) ) {
			$svg = $tag[0];
			$w   = preg_match( '/\bwidth\s*=\s*["\']?\s*([\d.]+)/i', $svg, $m ) ? (float) $m[1] : 0;
			$h   = preg_match( '/\bheight\s*=\s*["\']?\s*([\d.]+)/i', $svg, $m ) ? (float) $m[1] : 0;

			// Fall back to the viewBox, which is what most icon sets actually set.
			if ( ( ! $w || ! $h ) && preg_match( '/\bviewBox\s*=\s*["\']\s*[\d.-]+[\s,]+[\d.-]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $m ) ) {
				$w = (float) $m[1];
				$h = (float) $m[2];
			}

			if ( $w > 0 && $h > 0 ) {
				$dims = array( (int) round( $w ), (int) round( $h ) );
			}
		}
	}

	if ( $id ) {
		update_post_meta( $id, '_jrc_svg_dims', $dims );
	}

	return $dims;
}

/**
 * Served postcode areas as [ OUTWARD_PREFIX => County ], for the coverage checker.
 *
 * Parsed from the Areas page "Covered postcode areas" field (one "PREFIX = County"
 * per line). Falls back to the built-in list, which mirrors the defaults baked into
 * app/js/coverage.js so the two can't silently drift apart.
 *
 * @param int|null $post_id Page to read the field from. Defaults to the current post.
 * @return array<string,string>
 */
function jrc_coverage_map( $post_id = null ) {
	$map = array();

	$raw = function_exists( 'get_field' ) ? get_field( 'cv_areas', $post_id ) : '';
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		if ( false === strpos( $line, '=' ) ) {
			continue;
		}
		list( $prefix, $county ) = array_map( 'trim', explode( '=', $line, 2 ) );
		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $prefix ) );
		if ( '' !== $prefix && '' !== $county ) {
			$map[ $prefix ] = $county;
		}
	}

	if ( $map ) {
		return $map;
	}

	return array(
		'WR'  => 'Worcestershire',
		'DY'  => 'Worcestershire',
		'B96' => 'Worcestershire',
		'B97' => 'Worcestershire',
		'B98' => 'Worcestershire',
		'GL'  => 'Gloucestershire',
		'CV'  => 'Warwickshire',
		'B49' => 'Warwickshire',
		'B50' => 'Warwickshire',
		'B95' => 'Warwickshire',
		'B80' => 'Warwickshire',
	);
}

/**
 * Safety net for WordPress-rendered images (the_post_thumbnail, content images):
 * when an attachment has no alt text of its own, fall back to its caption, then
 * its title, then the parent post title. This stops blank alt="" shipping on key
 * images — but it's a fallback only; proper alt should still be set in the library.
 */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! empty( trim( (string) ( $attr['alt'] ?? '' ) ) ) ) {
		return $attr;
	}

	$fallback = wp_get_attachment_caption( $attachment->ID );
	if ( ! $fallback ) {
		$fallback = $attachment->post_title;
	}
	if ( ! $fallback && $attachment->post_parent ) {
		$fallback = get_the_title( $attachment->post_parent );
	}

	if ( $fallback ) {
		$attr['alt'] = wp_strip_all_tags( $fallback );
	}
	return $attr;
}, 20, 2 );

/**
 * Render the reusable "buttons" repeater of the current ACF row.
 *
 * The same markup was written out by hand in hero.php, cta.php and split.php. Rather
 * than copy it a fourth, fifth and sixth time onto the service blocks, it lives here.
 * The existing three still have their own copies — identical output, worth folding in
 * next time they're touched.
 *
 * @param string $wrapper_class Class for the wrapping div.
 */
function jrc_render_buttons( $wrapper_class = 'block-actions' ) {
	if ( ! have_rows( 'buttons' ) ) {
		return;
	}

	$out = '';
	while ( have_rows( 'buttons' ) ) {
		the_row();

		$link = get_sub_field( 'link' );
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			continue;
		}

		$style  = get_sub_field( 'style' ) ?: 'primary';
		$target = ! empty( $link['target'] ) ? $link['target'] : '_self';

		$out .= sprintf(
			'<a class="button button--%s" href="%s" target="%s"%s>%s</a>',
			esc_attr( $style ),
			esc_url( $link['url'] ),
			esc_attr( $target ),
			'_blank' === $target ? ' rel="noopener"' : '',
			esc_html( $link['title'] )
		);
	}

	// Every row could have been empty, in which case don't print a stray wrapper.
	if ( '' === $out ) {
		return;
	}

	echo '<div class="' . esc_attr( $wrapper_class ) . '">' . $out . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
}

/**
 * Resolve one row of the "Related links" repeater to a url/label/target array.
 *
 * Internal links are stored as a post ID rather than a URL so they survive a slug
 * change — the whole point of the block is that the internal linking stays intact.
 * Anything not published is dropped: an editor linking to a draft would otherwise
 * publish a 404 into the very structure the SEO work depends on.
 *
 * @return array|null
 */
function jrc_resolve_link_row() {
	$type = get_sub_field( 'type' ) ?: 'internal';

	if ( 'custom' === $type ) {
		$link = get_sub_field( 'custom_link' );
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			return null;
		}
		return array(
			'url'    => $link['url'],
			'label'  => ! empty( $link['title'] ) ? $link['title'] : $link['url'],
			'target' => ! empty( $link['target'] ) ? $link['target'] : '',
			'desc'   => (string) get_sub_field( 'description' ),
		);
	}

	$post_id = get_sub_field( 'page' );
	if ( is_object( $post_id ) ) {
		$post_id = $post_id->ID;
	}
	$post_id = (int) $post_id;

	if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
		return null;
	}

	$label = trim( (string) get_sub_field( 'label' ) );

	return array(
		'url'    => get_permalink( $post_id ),
		'label'  => '' !== $label ? $label : get_the_title( $post_id ),
		'target' => '',
		'desc'   => (string) get_sub_field( 'description' ),
	);
}
