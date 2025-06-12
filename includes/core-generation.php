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
