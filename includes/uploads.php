<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tomatillo Design AVIF Everywhere - Upload Handling
 *
 * - Automatically generates AVIF from original on upload (JPEG/PNG)
 * - Always runs from original file (ignores -scaled.jpg during upload)
 * - Batch/manual mode can still compare vs scaled version
 */

// Hook into uploads and delay AVIF creation to ensure scaled files are generated
add_action( 'add_attachment', 'tomatillo_avif_schedule_creation', 10, 1 );

function tomatillo_avif_schedule_creation( $attachment_id ) {
    if ( ! tomatillo_avif_is_enabled() ) {
        return;
    }

    $mime = get_post_mime_type( $attachment_id );
    if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png' ], true ) ) {
        return;
    }

    wp_schedule_single_event( time() + 5, 'tomatillo_create_avif_event', [ $attachment_id ] );
}

add_action( 'tomatillo_create_avif_event', 'tomatillo_generate_avif_delayed' );

function tomatillo_generate_avif_delayed( $attachment_id ) {
    if ( ! tomatillo_avif_is_enabled() ) {
        return;
    }

    $file = get_attached_file( $attachment_id );

    // Detect if WP stored the scaled version
    if ( strpos( $file, '-scaled.' ) !== false ) {
        $original_path = preg_replace( '/-scaled\.(jpg|jpeg|png)$/i', '.$1', $file );

        if ( file_exists( $original_path ) ) {
            $file = $original_path;
            error_log("[AVIF] Using original file for AVIF generation: $file");
        } else {
            error_log("[AVIF] Original not found, using scaled: $file");
        }
    }

    if ( ! $file || ! file_exists( $file ) ) {
        error_log("[AVIF] Delayed AVIF: file not found for attachment $attachment_id");
        return;
    }

    $mime = mime_content_type( $file );
    if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png' ], true ) ) {
        return;
    }

    $avif_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $file );
    $result = tomatillo_generate_single_avif( $file, $avif_path, true ); // skip_scaled_check = true

    if ( is_wp_error( $result ) ) {
        error_log("[AVIF] Delayed AVIF failed: " . $result->get_error_message());
    } else {
        error_log("[AVIF] Delayed AVIF created: $avif_path (Q{$result['quality']}, resized to {$result['resize_max']}px)");
    }
}





/**
 * Create an AVIF (or fallback WebP) copy of the original image
 */
function tomatillo_generate_single_avif( $original_path, $avif_path, $skip_scaled_check = false ) {
	if ( ! file_exists( $original_path ) ) {
		return new WP_Error( 'file_missing', 'Original image not found' );
	}

	$reference_size = null;

	if ( ! $skip_scaled_check ) {
		$scaled_path = preg_replace('/\.(jpg|jpeg|png)$/i', '-scaled.$1', $original_path);
		$reference_path = file_exists( $scaled_path ) ? $scaled_path : $original_path;
		$reference_size = filesize( $reference_path );

		if ( ! $reference_size || $reference_size < 1000 ) {
			return new WP_Error( 'bad_reference_size', 'Reference image size is invalid' );
		}
	}

	$attempts = [
		[3000, 50],
		[2400, 45],
		[2000, 40],
	];

	$is_png = strtolower( pathinfo( $original_path, PATHINFO_EXTENSION ) ) === 'png';

	// Optional: detect if PNG uses transparency
	$has_transparency = false;
	if ( $is_png && class_exists('Imagick') ) {
		try {
			$probe = new Imagick( $original_path );
			$has_transparency = $probe->getImageAlphaChannel() &&
				$probe->getImageChannelDepth(Imagick::CHANNEL_ALPHA) > 1;
			$probe->clear();
			$probe->destroy();
		} catch (Exception $e) {}
	}

	// ✅ If avifenc available + PNG, try lossless CLI conversion first
	if ( $is_png && ! $has_transparency && shell_exec('which avifenc') ) {
		$cmd = escapeshellcmd("avifenc --lossless --speed 4") . ' ' .
			escapeshellarg($original_path) . ' ' . escapeshellarg($avif_path . '.temp');
		shell_exec($cmd);

		if ( file_exists( $avif_path . '.temp' ) ) {
			$avif_size = filesize( $avif_path . '.temp' );
			$savings_allowed = $skip_scaled_check || ( $reference_size !== null && $avif_size < $reference_size );

			if ( $savings_allowed ) {
				rename( $avif_path . '.temp', $avif_path );
				return [
					'size_bytes' => $avif_size,
					'quality'    => 'lossless',
					'resize_max' => 'native',
					'savings'    => $skip_scaled_check || ! $reference_size
						? null
						: 100 - round( ( $avif_size / $reference_size ) * 100 ),
				];
			}
			@unlink( $avif_path . '.temp' );
		}
	}

	// Fallback: try Imagick
	if ( ! class_exists( 'Imagick' ) ) {
		return new WP_Error( 'no_imagick', 'Imagick not available and no avifenc fallback worked' );
	}

	foreach ( $attempts as $attempt ) {
		list( $resize_max, $quality ) = $attempt;

		$image = new Imagick();
		try {
			$image->readImage( $original_path );
		} catch ( Exception $e ) {
			return new WP_Error( 'read_failed', 'Failed to read original image' );
		}

		if ( ! in_array( 'AVIF', $image->queryFormats(), true ) ) {
			$image->clear(); $image->destroy();
			break;
		}

		// 🛑 Skip transparent PNGs for AVIF (fallback to WebP)
		if ( $is_png && $has_transparency ) {
			$image->clear(); $image->destroy();
			break;
		}

		$dimensions = $image->getImageGeometry();
		$width = $dimensions['width'];
		$height = $dimensions['height'];

		if ( $width > $resize_max || $height > $resize_max ) {
			if ( $width >= $height ) {
				$new_width = $resize_max;
				$new_height = intval( ( $resize_max / $width ) * $height );
			} else {
				$new_height = $resize_max;
				$new_width = intval( ( $resize_max / $height ) * $width );
			}
			$image->resizeImage( $new_width, $new_height, Imagick::FILTER_LANCZOS, 1, true );
		}

		$image->setImageFormat( 'AVIF' );
		$image->setOption( 'avif:quality', $quality );

		$temp_path = $avif_path . '.temp';
		try {
			$image->writeImage( $temp_path );
		} catch ( Exception $e ) {
			$image->clear(); $image->destroy(); @unlink( $temp_path );
			continue;
		}

		$image->clear(); $image->destroy();

		$avif_size = file_exists( $temp_path ) ? filesize( $temp_path ) : 0;
		$savings_allowed = $skip_scaled_check || ( $reference_size !== null && $avif_size < $reference_size );

		if ( $avif_size > 0 && $savings_allowed ) {
			rename( $temp_path, $avif_path );
			return [
				'size_bytes' => $avif_size,
				'quality'    => $quality,
				'resize_max' => $resize_max,
				'savings'    => $skip_scaled_check || ! $reference_size
					? null
					: 100 - round( ( $avif_size / $reference_size ) * 100 ),
			];
		}

		@unlink( $temp_path );
	}

	// 🧯 Fallback to WebP if AVIF skipped or failed
	$webp_path = preg_replace('/\.avif$/i', '.webp', $avif_path);
	try {
		$img = new Imagick( $original_path );
		$img->setImageFormat('webp');
		$img->setOption('webp:method', '6');
		$img->writeImage( $webp_path );
		$size = filesize( $webp_path );
		$img->clear(); $img->destroy();
		return new WP_Error( 'fallback_webp', 'AVIF skipped, WebP created instead: ' . basename( $webp_path ) . ' ('.size_format($size).')' );
	} catch ( Exception $e ) {
		return new WP_Error( 'avif_failed_webp_failed', 'Both AVIF and WebP conversions failed' );
	}
}



add_action( 'delete_attachment', 'tomatillo_delete_avif_on_delete' );

function tomatillo_delete_avif_on_delete( $attachment_id ) {
    $file = get_attached_file( $attachment_id );

    if ( ! $file || ! file_exists( $file ) ) {
        return;
    }

    $mime = mime_content_type( $file );
    if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png' ], true ) ) {
        return;
    }

    // Handle both scaled and original versions
    $avif_candidates = [];

    if ( strpos( $file, '-scaled.' ) !== false ) {
        $original_path = preg_replace( '/-scaled\.(jpg|jpeg|png)$/i', '.$1', $file );
        $avif_candidates[] = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $original_path );
    }

    $avif_candidates[] = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $file );

    foreach ( $avif_candidates as $avif_file ) {
        if ( file_exists( $avif_file ) ) {
            @unlink( $avif_file );
            error_log("[AVIF] Deleted orphan AVIF: $avif_file");
        }
    }
}
