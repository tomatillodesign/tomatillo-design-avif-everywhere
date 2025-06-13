<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register AVIF Everywhere settings page.
 */
add_action( 'admin_menu', function() {
	add_options_page(
		'AVIF Everywhere Settings',
		'AVIF Everywhere',
		'manage_options',
		'tomatillo-avif-settings',
		'tomatillo_avif_render_settings_page'
	);
}, 20);

/**
 * Render the settings page.
 */
function tomatillo_avif_render_settings_page() {
	echo '<div class="wrap tomatillo-avif-settings-page">';
	echo '<h1>AVIF Everywhere Settings</h1>';

	// Output Scan UI
	tomatillo_avif_render_scan_ui();

    echo '<hr>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'tomatillo_avif_settings_group' );
    do_settings_sections( 'tomatillo-avif-settings' );
    submit_button( 'Save Settings' );
    echo '</form>';

	// Output diagnostics
	tomatillo_render_avif_server_diagnostics();

	echo '</div>';
}



add_action( 'admin_init', 'tomatillo_avif_register_settings' );
function tomatillo_avif_register_settings() {
	register_setting( 'tomatillo_avif_settings_group', 'tomatillo_avif_max_size_mb', [
		'sanitize_callback' => 'tomatillo_avif_sanitize_max_size_mb'
	] );

	add_settings_section(
		'tomatillo_avif_main_section',
		'General Settings',
		null,
		'tomatillo-avif-settings'
	);

	add_settings_field(
		'tomatillo_avif_max_size_mb_field',
		'Max File Size (MB)',
		'tomatillo_avif_max_size_mb_field_render',
		'tomatillo-avif-settings',
		'tomatillo_avif_main_section'
	);
}

function tomatillo_avif_sanitize_max_size_mb( $input ) {
	$mb = floatval( $input );
	$bytes = intval( $mb * 1000000 );
	update_option( 'tomatillo_avif_max_size', $bytes ); // Store both formats
	return $mb;
}

function tomatillo_avif_max_size_mb_field_render() {
	$mb = get_option( 'tomatillo_avif_max_size_mb', 1.0 ); // default 1MB
	echo '<input type="number" name="tomatillo_avif_max_size_mb" value="' . esc_attr( $mb ) . '" step="0.01" min="0" style="width: 80px;">';
	echo '<p class="description">Optional. AVIFs/WebPs larger than this will be skipped. Default: 1.0 MB.</p>';
}



function tomatillo_avif_render_scan_ui() {
	?>
	<div class="tomatillo-avif-scan-wrapper" style="margin-bottom: 2em;">
		<h2>📦 Scan Media Library</h2>
		<p>This will check for any Media Library images that are missing AVIF or WebP versions.</p>
		<form method="post" id="tomatillo-scan-avif-form">
			<button type="button" class="button button-secondary" id="tomatillo-scan-avif-button">
				Scan for Missing AVIF/WebP Files
			</button>
		</form>
		<div id="tomatillo-avif-scan-results" style="margin-top: 1em;"></div>
	</div>

    <div id="tomatillo-avif-generate-wrapper" style="margin-top:2em;">
        <button type="button" class="button button-primary" id="tomatillo-generate-avif-button" disabled>
            Generate Missing AVIF/WebP Files
        </button>
        <div id="tomatillo-avif-generate-results" style="margin-top: 1em;"></div>
    </div>

	<?php
}


function tomatillo_render_avif_server_diagnostics() {
	$imagick_loaded    = extension_loaded( 'imagick' );
	$imagick_class     = class_exists( 'Imagick' );
	$imagick_formats   = $imagick_class ? ( new Imagick() )->queryFormats() : [];
	$avif_supported    = in_array( 'AVIF', $imagick_formats, true );

	$gd_loaded         = extension_loaded( 'gd' );
	$gd_info           = $gd_loaded ? gd_info() : [];
	$gd_avif_supported = isset( $gd_info['AVIF Support'] ) && $gd_info['AVIF Support'];

	$upload_dir        = wp_upload_dir();
	$upload_writable   = is_writable( $upload_dir['basedir'] );

	echo '<div id="avif-hud" style="
		background:#f8f9fa;
		border:1px solid #ccd0d4;
		padding:1em;
		margin-top:2em;
		font-family:monospace;
		font-size:14px;
		line-height:1.5;
		border-radius:4px;
		max-width:600px;
	">
		<h2 style="margin-top:0">🛠 AVIF Server Compatibility</h2>
		<ul style="padding-left:1.2em;margin:0">
			<li>Imagick extension loaded: <strong style="color:'.($imagick_loaded ? '#0073aa' : '#d63638').'">'.($imagick_loaded ? 'Yes' : 'No').'</strong></li>
			<li>Imagick class available: <strong style="color:'.($imagick_class ? '#0073aa' : '#d63638').'">'.($imagick_class ? 'Yes' : 'No').'</strong></li>
			<li>Imagick AVIF support: <strong style="color:'.($avif_supported ? '#0073aa' : '#d63638').'">'.($avif_supported ? 'Yes' : 'No').'</strong></li>
			<li>GD extension loaded: <strong style="color:'.($gd_loaded ? '#0073aa' : '#d63638').'">'.($gd_loaded ? 'Yes' : 'No').'</strong></li>
			<li>GD AVIF support: <strong style="color:'.($gd_avif_supported ? '#0073aa' : '#d63638').'">'.($gd_avif_supported ? 'Yes' : 'No').'</strong></li>
			<li>Uploads writable: <strong style="color:'.($upload_writable ? '#0073aa' : '#d63638').'">'.($upload_writable ? 'Yes' : 'No').'</strong></li>
			<li>Uploads path: <code>'.esc_html( $upload_dir['basedir'] ).'</code></li>
		</ul>
	</div>';

    echo '<div id="avif-hud-advanced" style="
		background:#f8f9fa;
		border:1px solid #ccd0d4;
		padding:1em;
		margin-top:2em;
		font-family:monospace;
		font-size:14px;
		line-height:1.5;
		border-radius:4px;
		max-width:600px;
	">
		<h2 style="margin-top:0">🔍 Extended AVIF Diagnostics</h2>
		<ul style="padding-left:1.2em;margin:0">
			<li>PHP version: <strong style="color:#0073aa">'.phpversion().'</strong></li>
			<li>WordPress version: <strong style="color:#0073aa">'.get_bloginfo('version').'</strong></li>
			<li>WP memory limit: <strong style="color:#0073aa">'.WP_MEMORY_LIMIT.'</strong></li>
			<li>PHP memory_limit: <strong style="color:#0073aa">'.ini_get('memory_limit').'</strong></li>
			<li>Max execution time: <strong style="color:#0073aa">'.ini_get('max_execution_time').' seconds</strong></li>';

if ( class_exists('Imagick') ) {
	$imagick = new Imagick();
	$version = $imagick->getVersion();
	$imagick_version = $version['versionString'];
} else {
	$imagick_version = 'Not available';
}

echo '		<li>Imagick version: <strong style="color:#0073aa">'.esc_html($imagick_version).'</strong></li>';

$library_used = ( class_exists('Imagick') && (new Imagick())->queryFormats('AVIF') ) ? 'Imagick' : 'GD';

echo '		<li>Library selected: <strong style="color:#0073aa">'.$library_used.'</strong></li>
		</ul>
	</div>';


}


add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( $hook !== 'settings_page_tomatillo-avif-settings' ) return;

	wp_enqueue_script(
		'tomatillo-avif-admin',
		TOMATILLO_AVIF_ASSETS_URL . 'js/admin-settings.js',
		[ 'jquery' ],
		TOMATILLO_AVIF_VERSION,
		true
	);

	wp_localize_script( 'tomatillo-avif-admin', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
}, 20 );
