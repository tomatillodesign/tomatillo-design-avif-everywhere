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

    echo '<div id="tomatillo-progress-wrapper" style="margin-top: 10px; display: none;">
            <div id="tomatillo-spinner" class="tomatillo-spinner" role="status" aria-label="Loading…"></div>
            <div style="background: #777; border-radius: 4px; overflow: hidden; height: 12px; width: 100%; max-width: 400px;">
                <div id="tomatillo-progress-bar" style="height: 100%; background: #4caf50; width: 0%;"></div>
            </div>
        </div>';

    echo '<hr>';
    echo '<form method="post" action="options.php">';
        settings_fields( 'tomatillo_avif_settings_group' );
        do_settings_sections( 'tomatillo-avif-settings' );
        submit_button( 'Save Settings' );
    echo '</form>';

	// Output diagnostics
	tomatillo_render_avif_server_diagnostics();
	tomatillo_avif_render_plugin_info_panel(); // This new panel

	echo '</div>';

    ?>

    <style>

        .tomatillo-avif-settings-page ul {
            margin-left: 1rem !important;
            list-style: disc !important;
        }

        .tomatillo-avif-settings-page ul.clb-avif-reporting-list {
            list-style: none !important;
        }

        .clb-avif-reporting-item {
            padding: 1rem;
            border: none;
			background: #fff;
            max-width: 600px;
        }

        .tomatillo-spinner {
            width: 32px;
            height: 32px;
            border: 4px solid #ccc;
            border-top-color: #4caf50;
            border-radius: 50%;
            animation: tomatillo-spin 3s linear infinite;
            margin: 12px 0;
        }

        @keyframes tomatillo-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

<?php
}



add_action( 'admin_init', 'tomatillo_avif_register_settings' );
function tomatillo_avif_register_settings() {
	
    register_setting( 'tomatillo_avif_settings_group', 'tomatillo_avif_max_size_mb', [
		'sanitize_callback' => 'tomatillo_avif_sanitize_max_size_mb'
	] );

    register_setting(
        'tomatillo_avif_settings_group',
        'tomatillo_design_avif_everywhere_enable'
    );

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

    add_settings_field(
        'tomatillo_design_avif_everywhere_enable_field',
        'Enable AVIF Replacement',
        'tomatillo_design_avif_everywhere_enable_field_render',
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

		<div id="tomatillo-avif-generate-results-wrapper" style="display:none;max-height:600px; overflow-y:auto; position:relative; border:1px solid #ccc; padding:1em; margin-top:1em;">
			<div id="tomatillo-progress-wrapper" style="display: none; position:sticky; top:0; background:transparent; padding-bottom:0.5em; z-index:10; max-width: 600px;">
				<div id="tomatillo-spinner" class="tomatillo-spinner" role="status" aria-label="Loading…"></div>
				<div style="background: #777; border-radius: 4px; overflow: hidden; height: 12px; width: 100%;">
					<div id="tomatillo-progress-bar" style="height: 100%; background: limegreen; width: 0%; transition: width 0.3s ease;"></div>
				</div>
			</div>
			<div id="tomatillo-avif-scan-results"></div> <!-- ✅ renamed -->
			<div id="tomatillo-bandwidth-scoreboard" style="display: none;margin-top: 0.5em; font-weight: bold; color: #555;">💾 Bandwidth Saved: 0.00 MB (0%)</div>
			<div id="tomatillo-avif-generate-results"></div>
		</div>

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



function tomatillo_design_avif_everywhere_enable_field_render() {
	$option = get_option('tomatillo_design_avif_everywhere_enable', 1);
	?>
	<label class="tomatillo-toggle">
		<input type="checkbox" name="tomatillo_design_avif_everywhere_enable" value="1" <?php checked(1, $option, true); ?> />
		<span class="tomatillo-slider"></span>
	</label>
	<span class="tomatillo-toggle-label">Automatically generate AVIF/WebP files when uploading JPEG/PNG images.</span>
	<?php
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



function tomatillo_avif_render_plugin_info_panel() {
	?>
	<div class="postbox" style="margin-top: 2em;">
		<h2 style="margin-left: 1em;"><span>Plugin Overview & Deactivation Notes</span></h2>
		<div class="inside">
			<h3>✅ What This Plugin Does</h3>
			<p><strong>AVIF Everywhere</strong> enhances image delivery across your WordPress site by generating modern image formats (<code>.avif</code> and optionally <code>.webp</code>) from uploaded JPEG/PNG files. It improves loading times and bandwidth usage without affecting your original media.</p>

			<ul>
				<li>🖼 <strong>Automatic conversion</strong> of new image uploads into AVIF and WebP formats</li>
				<li>🧰 <strong>Admin tools</strong> to scan existing uploads and batch-generate modern formats</li>
				<li>⚡️ <strong>Performance logic</strong> that tests multiple compression levels and only keeps smaller AVIFs</li>
				<li>📊 <strong>Diagnostic panel</strong> to check AVIF support, GD/Imagick versions, and server setup</li>
				<li>🧠 <strong>Metadata-driven</strong> — uses custom fields to track AVIF/WebP URLs on each media item</li>
				<li>👁 <strong>Enhanced Media Library</strong> views that preview AVIF versions when available</li>
				<li>💻 <strong>Frontend JS</strong> that swaps in AVIF with graceful fallbacks when supported</li>
			</ul>

			<h3>⚠️ What Happens When You Disable or Delete</h3>
			<p>Disabling or removing the plugin will:</p>
			<ul>
				<li>⛔️ Stop all AVIF/WebP generation for new uploads</li>
				<li>🖼 Revert Media Library grid and modals back to original JPEG/PNG previews</li>
				<li>🚫 Disable the scan and batch generation tools</li>
				<li>🧩 Remove frontend AVIF swapping unless you’ve added custom replacements</li>
			</ul>

			<p>However, <strong>any previously generated <code>.avif</code> and <code>.webp</code> files remain untouched</strong> on your server inside <code>/wp-content/uploads/</code>. These are safe to leave in place and continue serving manually or via other plugins if needed.</p>

			<p><strong>Important:</strong> This plugin does <em>not</em> modify image URLs in post content or database entries. It dynamically enhances the admin and frontend image delivery — meaning there are no long-term consequences or cleanup requirements unless you choose to remove the AVIF files yourself.</p>
		</div>
	</div>
	<?php
}
