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

	$image = wp_get_image_editor( $input_path );
	if ( is_wp_error( $image ) ) {
		return new WP_Error( 'editor_error', 'Could not load image editor.' );
	}

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

	// Strip -scaled.jpg if applicable
	$unscaled_path = preg_replace( '/-scaled\.(jpe?g|png)$/i', '.$1', $original_path );
	$path_to_use   = file_exists( $unscaled_path ) ? $unscaled_path : $original_path;
	$original_size = filesize( $path_to_use );

	// Check for -scaled.jpg file (for comparison only)
	$scaled_path = preg_replace( '/\.(jpe?g|png)$/i', '-scaled.$1', $unscaled_path );
	$scaled_size = file_exists( $scaled_path ) ? filesize( $scaled_path ) : $original_size;

	// Define AVIF fallbacks
	$fallback_attempts = [
		[ 'resize_max' => 2000, 'quality' => 42 ],
		[ 'resize_max' => 1800, 'quality' => 40 ],
		[ 'resize_max' => 1600, 'quality' => 38 ],
	];

	$avif_info = null;

	foreach ( $fallback_attempts as $attempt ) {
		$generated = tomatillo_generate_image_formats( $path_to_use, [
			'quality'    => $attempt['quality'],
			'resize_max' => $attempt['resize_max'],
			'formats'    => [ 'avif' ]
		]);

		if (
			! is_wp_error( $generated ) &&
			isset( $generated['formats']['avif'] ) &&
			! is_wp_error( $generated['formats']['avif'] )
		) {
			$avif_info = $generated['formats']['avif'];

			// ❌ Check max size
			$max_bytes = (int) get_option( 'tomatillo_avif_max_size', 0 );
			if ( $max_bytes && $avif_info['size_bytes'] > $max_bytes ) {
				unlink( $avif_info['path'] );
				$avif_info = null;
				continue;
			}

			// Only accept if at least 20% smaller than scaled.jpg
			if ( $avif_info['size_bytes'] < ( $scaled_size * 0.8 ) ) {
				break; // ✅ Accept
			} else {
				unlink( $avif_info['path'] );
				$avif_info = null;
			}

		}
	}

	// ✅ WebP always created once
	$webp_info = null;
	$webp_result = tomatillo_generate_image_formats( $path_to_use, [
		'quality'    => 65,
		'resize_max' => 2000,
		'formats'    => [ 'webp' ]
	]);
	if (
		! is_wp_error( $webp_result ) &&
		isset( $webp_result['formats']['webp'] ) &&
		! is_wp_error( $webp_result['formats']['webp'] )
	) {
		$webp_info = $webp_result['formats']['webp'];
	}

	// Store meta
	$upload_dir = wp_upload_dir();
	$base_url   = trailingslashit( $upload_dir['baseurl'] );
	$base_dir   = trailingslashit( $upload_dir['basedir'] );

	if ( $avif_info ) {
		$rel_path = str_replace( $base_dir, '', $avif_info['path'] );
		update_post_meta( $attachment_id, '_avif_url', esc_url_raw( $base_url . ltrim( $rel_path, '/' ) ) );
	} else {
		delete_post_meta( $attachment_id, '_avif_url' );
	}

	if ( $webp_info ) {
		$rel_path = str_replace( $base_dir, '', $webp_info['path'] );
		update_post_meta( $attachment_id, '_webp_url', esc_url_raw( $base_url . ltrim( $rel_path, '/' ) ) );
	}

	// Return summary
	return [
		'id'            => $attachment_id,
		'filename'      => basename( $path_to_use ),
		'original_size' => $original_size,
		'scaled_size'   => $scaled_size,
		'avif'          => $avif_info,
		'webp'          => $webp_info,
	];
}
