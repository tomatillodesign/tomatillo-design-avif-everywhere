<?php

// Hook for both logged-in and non-logged-in (just in case)
function tomatillo_ajax_scan_avif() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$missing = [];

	$args = [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'post_mime_type' => ['image/jpeg', 'image/png'],
		'fields'         => 'ids',
		'meta_query'     => [
			'relation' => 'OR',
			[
				'key'     => '_avif_url',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'   => '_avif_url',
				'value' => '',
				'compare' => '=',
			],
			[
				'key'     => '_webp_url',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'   => '_webp_url',
				'value' => '',
				'compare' => '=',
			],
		],
	];

	$query = new WP_Query( $args );

	foreach ( $query->posts as $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( $file && file_exists( $file ) ) {
			$missing[] = [
				'id'       => $attachment_id,
				'filename' => basename( $file ),
			];
		}
	}

	wp_send_json_success([
		'total_checked' => $query->found_posts,
		'missing'       => $missing,
	]);
}
add_action( 'wp_ajax_tomatillo_scan_avif', 'tomatillo_ajax_scan_avif' );




add_action( 'wp_ajax_tomatillo_generate_avif_batch', 'tomatillo_ajax_generate_avif_batch' );
function tomatillo_ajax_generate_avif_batch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	if ( empty( $_POST['files'] ) ) {
		wp_send_json_error( 'No files provided' );
	}

	$files = json_decode( stripslashes( $_POST['files'] ), true );
	if ( ! is_array( $files ) ) {
		wp_send_json_error( 'Invalid data format' );
	}

	require_once TOMATILLO_AVIF_DIR . 'includes/core-generation.php';
	require_once TOMATILLO_AVIF_DIR . 'includes/meta-store.php';

	$results = [
		'success' => [],
		'failed'  => [],
	];

	foreach ( $files as $item ) {
		$attachment_id = intval( $item['id'] ?? 0 );

		if ( ! $attachment_id ) {
			$results['failed'][] = 'Missing attachment ID';
			continue;
		}

		$result = tomatillo_generate_avif_for_attachment( $attachment_id );

		if ( $result && ! empty( $result['filename'] ) ) {
			$results['success'][] = $result;
		} else {
			$results['failed'][] = "#$attachment_id";
		}
	}

	wp_send_json_success( $results );
}
