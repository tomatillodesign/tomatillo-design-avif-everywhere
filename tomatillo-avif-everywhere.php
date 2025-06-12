<?php
/**
 * Plugin Name: Tomatillo Design AVIF Everywhere
 * Plugin URI:  https://www.tomatillodesign.com/
 * Description: Automatically create AVIF copies of uploads, serve AVIF on front-end and admin where possible. Full library retro-conversion available.
 * Version:     1.2
 * Author:      Tomatillo Design
 * Author URI:  https://www.tomatillodesign.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tomatillo-avif-everywhere
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// --- Plugin Constants ---
define( 'TOMATILLO_AVIF_VERSION', '1.2.0' );
define( 'TOMATILLO_AVIF_FILE', __FILE__ );
define( 'TOMATILLO_AVIF_DIR', plugin_dir_path( __FILE__ ) );
define( 'TOMATILLO_AVIF_URL', plugin_dir_url( __FILE__ ) );
define( 'TOMATILLO_AVIF_ASSETS_URL', TOMATILLO_AVIF_URL . 'assets/' );
define( 'TOMATILLO_AVIF_TEXT_DOMAIN', 'tomatillo-avif-everywhere' );


// --- Load Modular Includes ---
$includes = [
	'core-generation.php',
	'meta-store.php',
	'upload-hook.php',
	'batch-process.php',
	'frontend-swap.php',
	'admin-ui.php',
	'debug-tools.php',
	'helpers.php',
];

foreach ( $includes as $file ) {
	$path = TOMATILLO_AVIF_DIR . 'includes/' . $file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}







add_shortcode( 'test_avif_meta', function() {
	$attachment_id = 82; // swap to real ID
	$file = get_attached_file( $attachment_id );

	if ( ! $file || ! file_exists( $file ) ) {
		return '<p>Original file not found.</p>';
	}

	$generation = tomatillo_generate_image_formats( $file );
	if ( is_wp_error( $generation ) ) {
		return '<p>Generation failed: ' . esc_html( $generation->get_error_message() ) . '</p>';
	}

	$meta_result = tomatillo_store_avif_webp_meta( $attachment_id, $generation );
	if ( is_wp_error( $meta_result ) ) {
		return '<p>Meta storage failed: ' . esc_html( $meta_result->get_error_message() ) . '</p>';
	}

	ob_start();
	echo '<h3>Stored Format URLs:</h3><ul>';
	foreach ( $meta_result as $key => $url ) {
		echo '<li><strong>' . esc_html( $key ) . ':</strong> <code>' . esc_url( $url ) . '</code></li>';
	}
	echo '</ul>';

	return ob_get_clean();
} );
