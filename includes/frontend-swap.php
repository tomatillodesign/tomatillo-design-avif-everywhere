<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bail out early unless this is a proper frontend page load.
 * Prevents this file from loading during admin, AJAX, or REST contexts.
 */
if (
	is_admin() ||
	wp_doing_ajax() ||
	( defined( 'REST_REQUEST' ) && REST_REQUEST )
) {
	return;
}

/**
 * Override WordPress image rendering with AVIF/WebP version (e.g., in wp_get_attachment_image()).
 */
add_filter( 'image_downsize', function( $out, $attachment_id, $size ) {
	$avif_url = get_post_meta( $attachment_id, '_avif_url', true );
	$webp_url = get_post_meta( $attachment_id, '_webp_url', true );

	if ( ! $avif_url && ! $webp_url ) {
		return false;
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! isset( $meta['width'], $meta['height'] ) ) {
		return false;
	}

	$src = $avif_url ?: $webp_url;

	// Return custom image URL and dimensions
	return [ esc_url( $src ), $meta['width'], $meta['height'], false ];
}, 10, 3 );


/**
 * Replace <img> src values in post content with AVIF or WebP URLs where available.
 */
add_filter( 'the_content', function( $content ) {
	// Sanity check: skip if no <img in content
	if ( stripos( $content, '<img' ) === false ) {
		return $content;
	}

	libxml_use_internal_errors( true );
	$doc = new DOMDocument();

	if ( ! $doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) ) ) {
		return $content; // Fail gracefully if DOM parse fails
	}

	$images = $doc->getElementsByTagName('img');

	foreach ( $images as $img ) {
		$class = $img->getAttribute('class');

		if ( ! preg_match( '/wp-image-(\d+)/', $class, $matches ) ) {
			continue;
		}

		$attachment_id = (int) $matches[1];
		$avif_url = str_replace( '-scaled.avif', '.avif', get_post_meta( $attachment_id, '_avif_url', true ) );
		$webp_url = get_post_meta( $attachment_id, '_webp_url', true );
		$new_url = $avif_url ?: $webp_url;

		if ( $new_url ) {
			$img->setAttribute( 'src', esc_url( $new_url ) );
			$img->removeAttribute( 'srcset' );

			// Add yak-swapped-img class
			$class .= ' yak-swapped-img';
			$img->setAttribute( 'class', trim( $class ) );
		}
	}

	$body = $doc->getElementsByTagName('body')->item(0);
	$new_content = '';

	foreach ( $body->childNodes as $child ) {
		$new_content .= $doc->saveHTML( $child );
	}

	return $new_content;
}, 20 );
