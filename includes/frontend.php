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
 * Rewrites <img> tags in final output to support data-avif delivery,
 * but only within specific containers (entry-content, yak-featured-image-top-wrapper).
 */
function tomatillo_avif_rewrite_images($html) {
	if (stripos($html, '<img') === false) return $html;

	libxml_use_internal_errors(true); // Suppress DOM warnings

	$dom = new DOMDocument();
	$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

	$xpath = new DOMXPath($dom);
	$images = $xpath->query(
		'//div[contains(@class, "entry-content")]//img | //div[contains(@class, "yak-featured-image-top-wrapper")]//img'
	);

	foreach ($images as $img) {
		$src = $img->getAttribute('src');
		if (!preg_match('/\.(jpe?g|png)$/i', $src)) continue;

		$avif = tomatillo_avif_guess_avif_url($src);
		$webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);

		if (!$avif && !$webp) continue;

		foreach (['src', 'srcset', 'sizes', 'fetchpriority'] as $attr) {
			if ($img->hasAttribute($attr)) {
				$img->setAttribute("data-{$attr}", $img->getAttribute($attr));
				$img->removeAttribute($attr);
			}
		}

		if ($avif) $img->setAttribute('data-avif', esc_url($avif));
		if ($webp) $img->setAttribute('data-webp', esc_url($webp));
	}

	return $dom->saveHTML();
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
