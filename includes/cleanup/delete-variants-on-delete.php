<?php
// Delete AVIF and WebP files when an attachment is deleted
add_action( 'delete_attachment', 'tomatillo_delete_generated_variants', 20 );

function tomatillo_delete_generated_variants( $post_id ) {
	$avif_url = get_post_meta( $post_id, '_avif_url', true );
	$webp_url = get_post_meta( $post_id, '_webp_url', true );

	foreach ( [ $avif_url, $webp_url ] as $url ) {
		if ( ! $url ) {
			continue;
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['path'] ) ) {
			continue;
		}

		// Remove /wp-content/uploads from path
		$relative_path = str_replace( wp_upload_dir()['baseurl'], '', $url );
		$file_path = wp_upload_dir()['basedir'] . $relative_path;

		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	// Clean up meta
	delete_post_meta( $post_id, '_avif_url' );
	delete_post_meta( $post_id, '_avif_size_kb' );
	delete_post_meta( $post_id, '_webp_url' );
	delete_post_meta( $post_id, '_webp_size_kb' );
}
