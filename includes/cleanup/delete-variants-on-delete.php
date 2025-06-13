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

		// Build full file path
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['basedir'] . $parsed['path'];

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
