<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Override WordPress image rendering with AVIF/WebP version.
 */
add_filter( 'image_downsize', function( $out, $attachment_id, $size ) {
	if ( is_admin() || wp_doing_ajax() ) {
		return false;
	}

	$avif_url = get_post_meta( $attachment_id, '_avif_url', true );
	$webp_url = get_post_meta( $attachment_id, '_webp_url', true );

	// Fall back to original logic if neither format exists
	if ( ! $avif_url && ! $webp_url ) {
		return false;
	}

	// Get original image size (since we're skipping resized versions)
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! isset( $meta['width'], $meta['height'] ) ) {
		return false;
	}

	$src = $avif_url ?: $webp_url;

	// Bypass core logic entirely, return our own image URL and size
	return [ esc_url( $src ), $meta['width'], $meta['height'], false ];
}, 10, 3 );


add_filter( 'the_content', function( $content ) {
	if ( is_admin() || wp_doing_ajax() ) {
		return $content;
	}

	// Load HTML into DOM
	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

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
			$img->removeAttribute( 'srcset' ); // prevent browser override
		}
	}

	// Save back to content
	$body = $doc->getElementsByTagName('body')->item(0);
	$new_content = '';
	foreach ( $body->childNodes as $child ) {
		$new_content .= $doc->saveHTML( $child );
	}

	return $new_content;
}, 20 );
