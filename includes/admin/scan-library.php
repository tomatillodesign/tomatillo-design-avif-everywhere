<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handler: scan media library for images missing AVIF/WebP.
 */
add_action( 'wp_ajax_tomatillo_scan_missing_formats', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$query = new WP_Query([
		'post_type'      => 'attachment',
		'post_mime_type' => ['image/jpeg', 'image/png'],
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);

	$missing = [];

	foreach ( $query->posts as $attachment_id ) {
		$avif = get_post_meta( $attachment_id, '_avif_url', true );
		$webp = get_post_meta( $attachment_id, '_webp_url', true );

		if ( ! $avif || ! $webp ) {
			$missing[] = [
				'id'    => $attachment_id,
				'title' => get_the_title( $attachment_id ),
				'url'   => wp_get_attachment_url( $attachment_id ),
				'avif'  => $avif ? '✅' : '❌',
				'webp'  => $webp ? '✅' : '❌',
			];
		}
	}

	wp_send_json_success( $missing );
});
