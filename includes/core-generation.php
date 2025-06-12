<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Generate AVIF and/or WebP versions of a given image.
 *
 * @param string $input_path Full path to JPG/PNG image.
 * @param array  $options    Optional: [quality, resize_max, formats => ['avif', 'webp']]
 * @return array|WP_Error    On success, returns ['formats' => ['avif' => [...], 'webp' => [...]]]
 */
function tomatillo_generate_image_formats( $input_path, $options = [] ) {
	if ( ! file_exists( $input_path ) ) {
		return new WP_Error( 'file_missing', 'Source file not found.' );
	}

	$quality     = isset( $options['quality'] ) ? (int) $options['quality'] : 50;
	$resize_max  = isset( $options['resize_max'] ) ? (int) $options['resize_max'] : 3000;
	$formats     = isset( $options['formats'] ) ? (array) $options['formats'] : [ 'avif', 'webp' ];
	$results     = [ 'formats' => [] ];

	// Load image editor
	$image = wp_get_image_editor( $input_path );
	if ( is_wp_error( $image ) ) {
		return new WP_Error( 'editor_error', 'Could not load image editor.' );
	}

	// Resize if needed
	$size = $image->get_size();
	if ( max( $size['width'], $size['height'] ) > $resize_max ) {
		$image->resize( $resize_max, $resize_max, false );
	}

	foreach ( $formats as $format ) {
		$output_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.' . $format, $input_path );
		if ( ! $output_path ) {
			$results['formats'][ $format ] = new WP_Error( 'invalid_path', "Failed to determine $format path." );
			continue;
		}

		// Save in desired format
		$saved = $image->save( $output_path, 'image/' . $format, [ 'quality' => $quality ] );
		if ( is_wp_error( $saved ) ) {
			$results['formats'][ $format ] = $saved;
		} else {
			$results['formats'][ $format ] = [
				'path'        => $output_path,
				'quality'     => $quality,
				'resize_max'  => $resize_max,
				'size_bytes'  => filesize( $output_path ),
			];
		}
	}

	return $results;
}

/**
 * Generate AVIF/WebP for a given attachment ID and update post meta.
 *
 * @param int $attachment_id
 * @return array|null
 */
function tomatillo_generate_avif_for_attachment( $attachment_id ) {
	$original_path = get_attached_file( $attachment_id );

	if ( ! file_exists( $original_path ) ) return null;

	// 🧠 Strip -scaled if present (WordPress quirk)
	$unscaled_path = preg_replace( '/-scaled\.(jpe?g|png)$/i', '.$1', $original_path );

	$path_to_use = file_exists( $unscaled_path ) ? $unscaled_path : $original_path;

	$result = tomatillo_generate_image_formats( $path_to_use, [
		'quality'    => 50,
		'resize_max' => 3000,
		'formats'    => [ 'avif', 'webp' ],
	]);

	if ( is_wp_error( $result ) ) return null;

	$upload_dir = wp_upload_dir();
	$base_url   = trailingslashit( $upload_dir['baseurl'] );
	$base_dir   = trailingslashit( $upload_dir['basedir'] );

	foreach ( $result['formats'] as $format => $info ) {
		if ( is_wp_error( $info ) ) continue;
		$rel_path = str_replace( $base_dir, '', $info['path'] );
		$url = $base_url . ltrim( $rel_path, '/' );

		if ( $format === 'avif' ) {
			update_post_meta( $attachment_id, '_avif_url', esc_url_raw( $url ) );
		}
		if ( $format === 'webp' ) {
			update_post_meta( $attachment_id, '_webp_url', esc_url_raw( $url ) );
		}
	}

	return [
		'id'       => $attachment_id,
		'filename' => basename( $path_to_use ),
		'avif'     => $result['formats']['avif'] ?? null,
		'webp'     => $result['formats']['webp'] ?? null,
	];
}
