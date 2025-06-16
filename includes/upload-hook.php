<?php



// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hook into image uploads and schedule AVIF/WebP generation via Action Scheduler.
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

	// Schedule AVIF/WebP generation using Action Scheduler
	as_schedule_single_action(
		time() + 15, // delay 15s to let WordPress finish scaled versions
		'tomatillo_generate_avif_action',
		[ $attachment_id ]
	);

	error_log("[AVIF-UPLOAD] Scheduled AVIF generation for attachment #$attachment_id via Action Scheduler");
}, 10, 1);

/**
 * Action Scheduler callback to generate AVIF/WebP for a given attachment.
 */
add_action( 'tomatillo_generate_avif_action', function( $attachment_id ) {
	error_log("[AS-AVIF] Running AVIF generation for attachment ID: $attachment_id");

	require_once TOMATILLO_AVIF_DIR . 'includes/core-generation.php';
	require_once TOMATILLO_AVIF_DIR . 'includes/meta-store.php';

	$result = tomatillo_generate_avif_for_attachment( $attachment_id );

	if ( $result && ! empty( $result['filename'] ) ) {
		error_log("[AS-AVIF] ✅ Success: " . $result['filename']);
	} else {
		error_log("[AS-AVIF] ❌ Failed to generate for attachment ID $attachment_id");
	}
});





// Exit if accessed directly
// if ( ! defined( 'ABSPATH' ) ) exit;

// /**
//  * Schedule AVIF/WebP generation shortly after an image is uploaded.
//  */
// add_action( 'add_attachment', function( $attachment_id ) {
// 	$mime = get_post_mime_type( $attachment_id );
// 	error_log("[AVIF-UPLOAD] Uploaded attachment #$attachment_id, mime: $mime");

// 	if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png', 'image/x-png' ], true ) ) {
// 		error_log("[AVIF-UPLOAD] Skipped unsupported MIME type.");
// 		return;
// 	}

// 	if ( ! get_option( 'tomatillo_design_avif_everywhere_enable' ) ) {
// 		error_log("[AVIF-UPLOAD] Plugin setting is disabled. Skipping generation.");
// 		return;
// 	}

// 	$scheduled = wp_schedule_single_event( time() + 10, 'tomatillo_run_avif_generation_event', [ $attachment_id ] );

// 	if ( $scheduled ) {
// 		error_log("[AVIF-UPLOAD] Scheduled AVIF generation for attachment #$attachment_id");
// 	} else {
// 		error_log("[AVIF-UPLOAD] Failed to schedule event for attachment #$attachment_id");
// 	}
// });


// /**
//  * Cron callback to run AVIF/WebP generation.
//  */
// add_action( 'tomatillo_run_avif_generation_event', function( $attachment_id ) {
// 	error_log("[AVIF-CRON] Running generation for attachment ID $attachment_id");

// 	require_once TOMATILLO_AVIF_DIR . 'includes/core-generation.php';
// 	require_once TOMATILLO_AVIF_DIR . 'includes/meta-store.php';

// 	$result = tomatillo_generate_avif_for_attachment( $attachment_id );

// 	if ( $result && ! empty( $result['filename'] ) ) {
// 		error_log("[AVIF-CRON] ✅ Success: " . $result['filename']);
// 	} else {
// 		error_log("[AVIF-CRON] ❌ Failed to generate for attachment ID $attachment_id");
// 	}
// });



// add_action( 'add_attachment', function( $attachment_id ) {
// 	$mime = get_post_mime_type( $attachment_id );
// 	if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png', 'image/x-png' ], true ) ) return;

// 	// Action Scheduler version — test job
// 	as_schedule_single_action(
// 		time() + 10, // delay 10s
// 		'tomatillo_test_avif_action',
// 		[ 'attachment_id' => $attachment_id ]
// 	);
// }, 10, 1 );


// add_action( 'tomatillo_test_avif_action', function( $attachment_id ) {
// 	error_log("[AS-TEST] 🎯 Action Scheduler ran for attachment ID: $attachment_id");
// });

