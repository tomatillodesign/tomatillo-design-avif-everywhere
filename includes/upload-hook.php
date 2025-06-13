<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Schedule AVIF/WebP generation shortly after an image is uploaded.
 */
add_action( 'add_attachment', function( $attachment_id ) {
	$mime = get_post_mime_type( $attachment_id );
	error_log("[AVIF-UPLOAD] Uploaded attachment #$attachment_id, mime: $mime");

	if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png', 'image/x-png' ], true ) ) {
		error_log("[AVIF-UPLOAD] Skipped unsupported MIME type.");
		return;
	}

	if ( ! get_option( 'tomatillo_design_avif_everywhere_enable' ) ) {
		error_log("[AVIF-UPLOAD] Plugin setting is disabled. Skipping generation.");
		return;
	}

	$scheduled = wp_schedule_single_event( time() + 10, 'tomatillo_run_avif_generation_event', [ $attachment_id ] );

	if ( $scheduled ) {
		error_log("[AVIF-UPLOAD] Scheduled AVIF generation for attachment #$attachment_id");
	} else {
		error_log("[AVIF-UPLOAD] Failed to schedule event for attachment #$attachment_id");
	}
});


/**
 * Cron callback to run AVIF/WebP generation.
 */
add_action( 'tomatillo_run_avif_generation_event', function( $attachment_id ) {
	error_log("[AVIF-CRON] Running generation for attachment ID $attachment_id");

	require_once TOMATILLO_AVIF_DIR . 'includes/core-generation.php';
	require_once TOMATILLO_AVIF_DIR . 'includes/meta-store.php';

	$result = tomatillo_generate_avif_for_attachment( $attachment_id );

	if ( $result && ! empty( $result['filename'] ) ) {
		error_log("[AVIF-CRON] ✅ Success: " . $result['filename']);
	} else {
		error_log("[AVIF-CRON] ❌ Failed to generate for attachment ID $attachment_id");
	}
});
