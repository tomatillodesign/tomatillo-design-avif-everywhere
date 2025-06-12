<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store AVIF and WebP URLs in attachment metadata.
 *
 * @param int   $attachment_id WP attachment ID.
 * @param array $generation_result Result from tomatillo_generate_image_formats().
 * @return array Stored meta values or WP_Error.
 */
function tomatillo_store_avif_webp_meta( $attachment_id, $generation_result ) {
	if ( ! get_post( $attachment_id ) || get_post_type( $attachment_id ) !== 'attachment' ) {
		return new WP_Error( 'invalid_attachment', 'Invalid attachment ID.' );
	}

	if ( ! isset( $generation_result['formats'] ) || ! is_array( $generation_result['formats'] ) ) {
		return new WP_Error( 'invalid_result', 'Generation result missing or malformed.' );
	}

	$upload_dir = wp_get_upload_dir();
	$base_path  = trailingslashit( $upload_dir['basedir'] );
	$base_url   = trailingslashit( $upload_dir['baseurl'] );

	$stored = [];

	foreach ( $generation_result['formats'] as $format => $info ) {
		if ( is_wp_error( $info ) || empty( $info['path'] ) ) {
			continue;
		}

		$path = $info['path'];

		// Confirm it's inside uploads dir
		if ( strpos( $path, $base_path ) !== 0 ) {
			continue;
		}

		$url = $base_url . ltrim( str_replace( $base_path, '', $path ), '/' );
		$key = "_{$format}_url";

		update_post_meta( $attachment_id, $key, esc_url_raw( $url ) );
		$stored[ $key ] = $url;
	}

	return $stored;
}
