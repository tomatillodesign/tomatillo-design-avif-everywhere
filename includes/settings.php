<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tomatillo Design AVIF Everywhere - Settings Page
 */

// Add under Settings menu
add_action('admin_menu', function() {
    add_options_page(
        'Tomatillo Design AVIF Everywhere Settings',
        'AVIF Everywhere',
        'manage_options',
        'tomatillo_design_avif_everywhere_settings',
        'tomatillo_design_avif_everywhere_render_settings_page'
    );
});

// Register settings
add_action('admin_init', function() {
    register_setting('tomatillo_design_avif_everywhere_settings_group', 'tomatillo_design_avif_everywhere_enable');
    
    register_setting('tomatillo_design_avif_everywhere_settings_group', 'tomatillo_design_avif_everywhere_max_size_mb', [
        'sanitize_callback' => function( $input ) {
            $mb = floatval( $input );
            $bytes = intval( $mb * 1000000 );
            update_option('tomatillo_design_avif_everywhere_max_size', $bytes);
            return $mb; // Store MB for display
        }
    ]);

    add_settings_section(
        'tomatillo_design_avif_everywhere_settings_section',
        'General Settings',
        null,
        'tomatillo_design_avif_everywhere_settings'
    );

    add_settings_field(
        'tomatillo_design_avif_everywhere_enable_field',
        'Enable AVIF Replacement',
        'tomatillo_design_avif_everywhere_enable_field_render',
        'tomatillo_design_avif_everywhere_settings',
        'tomatillo_design_avif_everywhere_settings_section'
    );

    add_settings_field(
        'tomatillo_design_avif_everywhere_max_size_mb_field',
        'Max AVIF File Size (MB)',
        'tomatillo_design_avif_everywhere_max_size_field_render',
        'tomatillo_design_avif_everywhere_settings',
        'tomatillo_design_avif_everywhere_settings_section'
    );

});

// Render the settings page
function tomatillo_design_avif_everywhere_render_settings_page() {
    $enabled = tomatillo_avif_is_enabled();
    ?>
    <div class="wrap">
        <h1>Tomatillo Design AVIF Everywhere Settings</h1>

        <?php if ( ! $enabled ) : ?>
            <div class="notice notice-warning" style="padding: 12px 20px; margin-bottom: 20px; background: #fff3cd; border-left: 4px solid #ffeeba;">
                <p><strong>Note:</strong> AVIF replacement is currently disabled. New uploads will not be converted to AVIF, and the plugin will not modify frontend image output. However, any images previously replaced with AVIF will still load as-is until edited or removed manually.</p>
            </div>
        <?php endif; ?>

        <form method="post" id="tomatillo-scan-avif-form">
            <button type="button" class="button button-secondary" id="tomatillo-scan-avif-button">
                Scan Media Library for Missing AVIF Files
            </button>
        </form>

        <div id="tomatillo-avif-scan-results" style="margin-top:20px;"></div>

        <button type="button" class="button button-primary" id="tomatillo-generate-avif-button" disabled>
            Generate Missing AVIF Files
        </button>

        <div id="tomatillo-progress-wrapper" style="margin-top: 10px; display: none;">
            <div id="tomatillo-spinner" style="width: 24px; height: 24px; border: 3px solid #ccc; border-top-color: #4caf50; border-radius: 50%; animation: tomatillo-spin 0.8s linear infinite; margin-bottom: 10px;"></div>
            <div style="background: #777; border-radius: 4px; overflow: hidden; height: 12px; width: 100%; max-width: 400px;">
                <div id="tomatillo-progress-bar" style="height: 100%; background: #4caf50; width: 0%;"></div>
            </div>
        </div>


        <div id="tomatillo-avif-generate-results" style="margin-top: 20px;"></div>

        <form method="post" action="options.php">
            <?php
            settings_fields('tomatillo_design_avif_everywhere_settings_group');
            do_settings_sections('tomatillo_design_avif_everywhere_settings');
            submit_button();
            ?>
        </form>

    </div>

    <style>
        .tomatillo-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .tomatillo-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .tomatillo-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .tomatillo-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        .tomatillo-toggle input:checked + .tomatillo-slider {
            background-color: #4caf50;
        }

        .tomatillo-toggle input:checked + .tomatillo-slider:before {
            transform: translateX(26px);
        }

        .tomatillo-toggle-label {
            margin-left: 10px;
            vertical-align: middle;
        }

        @keyframes tomatillo-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scanButton = document.getElementById('tomatillo-scan-avif-button');
            const resultsDiv = document.getElementById('tomatillo-avif-scan-results');

            if (scanButton) {
                scanButton.addEventListener('click', function() {
                    scanButton.disabled = true;
                    scanButton.innerText = 'Scanning...';

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=tomatillo_scan_avif'
                    })
                    .then(response => response.json())
                    .then(data => {
                        scanButton.disabled = false;
                        scanButton.innerText = 'Scan Media Library for Missing AVIF Files';
                        
                        if (data.success) {
                            resultsDiv.innerHTML = '<h3>Missing AVIF Files:</h3><ul>' +
                                data.data.map(item => '<li>' + item + '</li>').join('') +
                                '</ul>';
                            document.getElementById('tomatillo-generate-avif-button').disabled = false;
                            document.getElementById('tomatillo-generate-avif-button').dataset.files = JSON.stringify(data.data);
                        } else {
                            resultsDiv.innerHTML = '<p>No missing AVIF files found!</p>';
                            document.getElementById('tomatillo-generate-avif-button').disabled = true;
                        }
                    })
                    .catch(() => {
                        scanButton.disabled = false;
                        scanButton.innerText = 'Scan Media Library for Missing AVIF Files';
                        resultsDiv.innerHTML = '<p>Error scanning media library.</p>';
                    });
                });
            }
        });


        // generate AVIF when button is clicked
        document.getElementById('tomatillo-generate-avif-button').addEventListener('click', function () {
            const button = this;
            const allFiles = JSON.parse(button.dataset.files || '[]');
            const resultsDiv = document.getElementById('tomatillo-avif-generate-results');

            let batchSize = 5;
            let index = 0;
            let success = [], failed = [];
            let totalAvifBytes = 0;
            let totalScaledBytes = 0;

            button.disabled = true;
            button.innerText = 'Generating...';

            const progressWrapper = document.getElementById('tomatillo-progress-wrapper');
            const progressBar = document.getElementById('tomatillo-progress-bar');
            const spinner = document.getElementById('tomatillo-spinner');

            // Before starting:
            button.disabled = true;
            button.innerText = 'Generating...';
            progressWrapper.style.display = 'block';
            progressBar.style.width = '0%';

            function processNextBatch() {
                const chunk = allFiles.slice(index, index + batchSize);
                if (chunk.length === 0) {
                    button.innerText = 'Generate Missing AVIF Files';
                    progressWrapper.style.display = 'none';

                    const webpCount = success.filter(f => f.includes('(WebP fallback)')).length;
                    const avifCount = success.length - webpCount;

                    resultsDiv.innerHTML = `
                        <h3>AVIF Generation Complete</h3>
                        <p><strong>Success:</strong> ${success.length}</p>
                        <ul>${success.map(f => `<li>${f}</li>`).join('')}</ul>
                        <p><strong>WebP Fallbacks:</strong> ${webpCount}</p>
                        <p><strong>AVIF Direct:</strong> ${avifCount}</p>
                        <p><strong>Failed:</strong> ${failed.length}</p>
                        <ul style="color:red">${failed.map(f => `<li>${f}</li>`).join('')}</ul>
                    `;
                    return;
                }

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=tomatillo_generate_avif_batch&files=' + encodeURIComponent(JSON.stringify(chunk))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.data.success.forEach(file => {
                            let line = `${file.filename}: ${file.size_kb} KB`;

                            if (file.savings !== null) {
                                line += ` — Saved ${file.savings}% vs scaled JPG`;
                            }

                            if (file.note === 'already exists') {
                                line += ` (already exists)`;
                            } else if (file.note && file.note.toLowerCase().includes('webp')) {
                                line += ` (WebP fallback)`;
                            }

                            success.push(line);
                        });

                        failed.push(...data.data.failed);
                    } else {
                        failed.push(...chunk.map(f => `${f} (ajax error)`));
                    }

                    index += batchSize;
                    const progressPercent = Math.min(100, Math.round((index / allFiles.length) * 100));
                    progressBar.style.width = `${progressPercent}%`;

                    resultsDiv.innerHTML = `<p>Processed ${index} of ${allFiles.length}...</p>`;
                    setTimeout(processNextBatch, 400);
                });
            }


            processNextBatch();
        });


    </script>


    <?php


    // ✅ Insert diagnostics block directly in content
	tomatillo_render_avif_server_diagnostics();



}


// Render toggle field
function tomatillo_design_avif_everywhere_enable_field_render() {
    $option = get_option('tomatillo_design_avif_everywhere_enable', 1);
    ?>
    <label class="tomatillo-toggle">
        <input type="checkbox" name="tomatillo_design_avif_everywhere_enable" value="1" <?php checked(1, $option, true); ?> />
        <span class="tomatillo-slider"></span>
    </label>
    <span class="tomatillo-toggle-label">Automatically replace images with AVIF versions where available.</span>
    <?php
}

// Render max file size field
function tomatillo_design_avif_everywhere_max_size_field_render() {
    $option_bytes = get_option('tomatillo_design_avif_everywhere_max_size', 1000000); // default 1MB
    $option_mb = round($option_bytes / 1000000, 2);
    ?>
    <input type="number" name="tomatillo_design_avif_everywhere_max_size_mb" value="<?php echo esc_attr($option_mb); ?>" min="0" step="0.1" style="width: 100px;">
    <p class="description">Optional. Max AVIF file size in MB. (e.g., 1.0 = 1MB)</p>
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
