<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tomatillo Design AVIF Everywhere – Reliable Frontend Delivery
 *
 * - Intercepts all rendered frontend HTML via output buffering
 * - Rewrites <img> tags to support AVIF with JavaScript swap
 * - Works regardless of blocks, plugins, or shortcodes
 */

/**
 * Intercept and rewrite final HTML output before render.
 */
add_action( 'template_redirect', function() {
	if ( ! tomatillo_avif_is_enabled() ) {
		return;
	}

	ob_start( 'tomatillo_avif_rewrite_images' );
});

/**
 * Rewrites <img> tags in final output to support data-avif delivery.
 */
function tomatillo_avif_rewrite_images( $html ) {
	if ( stripos( $html, '<img' ) === false ) {
		return $html;
	}

	return preg_replace_callback(
		'/<img\b[^>]*\bsrc=["\']([^"\']+\.(?:jpe?g|png))["\'][^>]*>/i',
		function( $matches ) {
			$original_img = $matches[0];
			$jpg_url      = $matches[1];

			$avif_url = tomatillo_avif_guess_avif_url( $jpg_url );
			$webp_url = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $jpg_url );

			// If neither AVIF nor WebP seems usable, return original
			if ( ! $avif_url && ! $webp_url ) {
				return $original_img;
			}

			// Rewrite preload attributes to data-*
			$revised = preg_replace_callback(
				'/\s+(src|srcset|sizes|fetchpriority)=["\']([^"\']+)["\']/i',
				function( $m ) {
					return sprintf( ' data-%s="%s"', $m[1], esc_attr( $m[2] ) );
				},
				$original_img
			);

			// Inject data-avif and data-webp
			$replacement = '<img';
			if ( $avif_url ) {
				$replacement .= sprintf( ' data-avif="%s"', esc_url( $avif_url ) );
			}
			if ( $webp_url ) {
				$replacement .= sprintf( ' data-webp="%s"', esc_url( $webp_url ) );
			}

			$revised = str_replace( '<img', $replacement, $revised );

			return $revised;
		},
		$html
	);
}



/**
 * Efficient per-request AVIF existence check with in-memory cache.
 */
function tomatillo_avif_guess_avif_url( $url ) {
	static $cache = [];

	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}

	if ( ! tomatillo_avif_is_enabled() ) {
		return $cache[ $url ] = false;
	}

	// Primary guess: same filename with .avif
	$avif_url = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $url );
	if ( tomatillo_avif_remote_file_exists( $avif_url ) ) {
		return $cache[ $url ] = $avif_url;
	}

	// Try fallback (remove size suffix like -683x1024)
	$fallback = preg_replace( '/-\d+x\d+(?=\.avif$)/i', '', $avif_url );
	if ( tomatillo_avif_remote_file_exists( $fallback ) ) {
		return $cache[ $url ] = $fallback;
	}

	// Try removing -scaled
	$scaled_fallback = str_replace( '-scaled.avif', '.avif', $avif_url );
	if ( tomatillo_avif_remote_file_exists( $scaled_fallback ) ) {
		return $cache[ $url ] = $scaled_fallback;
	}

	return $cache[ $url ] = false;
}

/**
 * Maps AVIF URLs to absolute file paths and checks existence.
 */
function tomatillo_avif_remote_file_exists( $url ) {
	static $cache = [];

	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}

	$local_path = str_replace( site_url(), ABSPATH, $url );
	$exists = file_exists( $local_path );

	return $cache[ $url ] = $exists;
}
