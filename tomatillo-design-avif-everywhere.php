<?php
/**
 * Plugin Name: Tomatillo Design AVIF Everywhere
 * Plugin URI:  https://www.tomatillodesign.com/
 * Description: Automatically create AVIF copies of uploads, serve AVIF on front-end and admin where possible. Full library retro-conversion available.
 * Version:     1.1
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

// --- Define constants ---
define( 'TOMATILLO_AVIF_DIR', plugin_dir_path( __FILE__ ) );
define( 'TOMATILLO_AVIF_URL', plugin_dir_url( __FILE__ ) );

// --- Autoload classes ---
foreach ( glob( TOMATILLO_AVIF_DIR . 'includes/*.php' ) as $file ) {
    require_once $file;
}

// --- Initialize Plugin ---
add_action( 'plugins_loaded', function() {
    // Nothing needed here anymore, functions are already hooked.
});


/**
 * TODO LIST:
 *
 * - Create AVIF on new upload (Tomatillo_AVIF_Uploads)
 * - Serve AVIF on frontend and admin (Tomatillo_AVIF_Frontend)
 * - Create settings page with manual conversion (Tomatillo_AVIF_Admin_Settings)
 * - Add server compatibility checks (Imagick AVIF support)
 * - Add 'backup confirmation' checkbox before retro-conversion
 * - Batch processing for library retro-conversion (AJAX or Cron)
 * - Progress tracking for batch jobs
 * - Error logging (optional)
 * - CLI command (optional future feature)
 */




add_action( 'wp_enqueue_scripts', 'tomatillo_avif_enqueue_styles' );

function tomatillo_avif_enqueue_styles() {
    // Only enqueue frontend, not admin
    if ( is_admin() ) {
        return;
    }

    wp_enqueue_style(
        'tomatillo-avif-everywhere',
        plugin_dir_url( __FILE__ ) . 'assets/css/tomatillo-avif-everywhere.css',
        [],
        '1.0'
    );
}


// helper to check the setting from my settings page
if ( ! function_exists( 'tomatillo_avif_is_enabled' ) ) {
    function tomatillo_avif_is_enabled() {
        return (bool) get_option('tomatillo_design_avif_everywhere_enable', 1);
    }
}



// Add a "Settings" link under the plugin name on the Plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=tomatillo_design_avif_everywhere_settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});


add_action('wp_ajax_tomatillo_scan_avif', 'tomatillo_scan_avif_callback');

function tomatillo_scan_avif_callback() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $missing = [];

    $query = new WP_Query([
        'post_type'      => 'attachment',
        'post_mime_type' => [ 'image/jpeg', 'image/jpg', 'image/png' ],
        'posts_per_page' => -1,
        'post_status'    => 'inherit',
        'fields'         => 'ids',
    ]);

    foreach ( $query->posts as $attachment_id ) {
        $file = get_attached_file( $attachment_id );

        if ( ! $file || ! file_exists( $file ) ) {
            continue;
        }

        $filename = basename( $file );

        // Skip resized intermediate sizes like -300x200.jpg
        if ( preg_match( '/-\d+x\d+\.(jpg|jpeg|png)$/i', $filename ) ) {
            continue;
        }

        // Remove -scaled manually if it's present (WordPress quirk)
        $unscaled_file = str_replace( '-scaled', '', $file );
        $avif_file = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $unscaled_file );

        if ( ! file_exists( $avif_file ) ) {
            $missing[] = basename( $file );
        }
    }

    if ( empty( $missing ) ) {
        wp_send_json_error();
    } else {
        wp_send_json_success( $missing );
    }
}





add_action('wp_ajax_tomatillo_generate_avif_batch', 'tomatillo_generate_avif_batch_callback');
function tomatillo_generate_avif_batch_callback() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    if ( empty( $_POST['files'] ) ) {
        wp_send_json_error( 'Missing input' );
    }

    $files = json_decode( stripslashes( $_POST['files'] ), true );
    if ( ! is_array( $files ) ) {
        wp_send_json_error( 'Invalid input' );
    }

    $uploads = wp_get_upload_dir();
    $basedir = trailingslashit( $uploads['basedir'] );

    $success = [];
    $failed = [];

    foreach ( $files as $filename ) {
        $base_name = preg_replace( '/-scaled\.(jpg|jpeg|png)$/i', '.$1', $filename );

        $original_path = false;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basedir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ( $iterator as $fileinfo ) {
            if ( $fileinfo->isFile() && $fileinfo->getFilename() === $base_name ) {
                $original_path = $fileinfo->getPathname();
                break;
            }
        }

        if ( ! $original_path || ! file_exists( $original_path ) ) {
            $failed[] = "$filename (original not found)";
            continue;
        }

        $avif_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $original_path );

        if ( file_exists( $avif_path ) ) {
            $size = filesize( $avif_path );
            $success[] = [
                'filename' => basename( $avif_path ),
                'size_kb' => round( $size / 1024 ),
                'savings' => null,
                'note' => 'already exists',
            ];
            continue;
        }

        $result = tomatillo_generate_single_avif( $original_path, $avif_path );

        if ( is_wp_error( $result ) ) {
            if ( $result->get_error_code() === 'avif_too_large' ) {
                $failed[] = "$filename (AVIF not created — larger than scaled JPG after all attempts)";
            } else {
                $failed[] = "$filename (" . $result->get_error_message() . ")";
            }
        } else {
            $size_kb = round( $result['size_bytes'] / 1024 );
            $quality = $result['quality'];
            $resize = $result['resize_max'];
            $savings = $result['savings'];

            $success[] = [
                'filename' => basename( $avif_path ),
                'size_kb'  => $size_kb,
                'quality'  => $quality,
                'resize_max' => $resize,
                'savings'  => $savings,
            ];
        }

    }

    wp_send_json_success([
        'success' => $success,
        'failed'  => $failed,
    ]);
}




///  Beautify the default Media Library

add_filter( 'wp_prepare_attachment_for_js', 'tomatillo_force_full_size_in_media_library' );

function tomatillo_force_full_size_in_media_library( $response ) {
    if ( is_admin() && get_current_screen() && get_current_screen()->id === 'upload' ) {
        if ( isset( $response['sizes'] ) && isset( $response['sizes']['full'] ) ) {
            // Overwrite the thumbnail details with full size
            $response['sizes']['thumbnail'] = $response['sizes']['full'];
            $response['sizes']['medium']    = $response['sizes']['full'];
            $response['sizes']['large']     = $response['sizes']['full'];
        }
    }
    return $response;
}



add_action( 'admin_enqueue_scripts', 'tomatillo_design_avif_everywhere_admin_assets' );

function tomatillo_design_avif_everywhere_admin_assets( $hook ) {
    if ( $hook !== 'upload.php' ) {
        return; // Only on Media Library
    }

    // Enqueue dummy handles just so we can inject inline CSS/JS
    wp_register_style( 'tomatillo-avif-admin-style', false );
    wp_enqueue_style( 'tomatillo-avif-admin-style' );
    wp_add_inline_style( 'tomatillo-avif-admin-style', tomatillo_design_avif_everywhere_admin_css() );

    wp_register_script( 'tomatillo-avif-admin-js', false );
    wp_enqueue_script( 'tomatillo-avif-admin-js' );
    // wp_add_inline_script( 'tomatillo-avif-admin-js', tomatillo_design_avif_everywhere_admin_js() );
}


add_action( 'admin_enqueue_scripts', 'tomatillo_enqueue_avif_admin_script' );

function tomatillo_enqueue_avif_admin_script( $hook ) {
    $screen = get_current_screen();

    // Avoid duplicate script loads
    if ( $hook === 'upload.php' ) {
        wp_add_inline_script(
            'jquery-core',
            tomatillo_design_avif_everywhere_admin_js()
        );
        return;
    }

    // Only enqueue on post editors (including CPTs), but NOT media or settings screens
    if (
        $screen &&
        $screen->base === 'post' &&
        post_type_exists( $screen->post_type ) &&
        $screen->post_type !== 'attachment'
    ) {
        // ✅ Inject CSS just like you do for upload.php
        wp_register_style( 'tomatillo-avif-admin-style', false );
        wp_enqueue_style( 'tomatillo-avif-admin-style' );
        wp_add_inline_style( 'tomatillo-avif-admin-style', tomatillo_design_avif_everywhere_admin_css() );
        
        wp_add_inline_script(
            'jquery-core',
            tomatillo_design_avif_everywhere_admin_js()
        );
    }
}






function tomatillo_design_avif_everywhere_admin_css() {
    return <<<CSS
/* === Fluid Masonry Layout for WordPress Media Library Grid View === */

.attachments {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    grid-auto-rows: auto;
    gap: 12px;
    align-items: start;
}

/* Each attachment flows naturally */
li.attachment {
    display: block;
    max-width: 300px !important;
    width: 100% !important;
    margin: 0;
    border: none;
    overflow: hidden;
    border: none !important;
    /* box-shadow: none !important; */
    background: transparent !important;
    border-radius: 6px !important;
}

/* Remove WP's fixed aspect ratio */
.attachment-preview {
    width: 100% !important;
    height: auto !important;
    padding: 0 !important;
    background: none !important;
    box-shadow: none !important;
    overflow: hidden !important;
    display: block;
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
    border-radius: 6px !important;
}

/* Images behave correctly */
.attachment-preview img {
    aspect-ratio: 1 / 1;
    width: 100% !important;
    height: auto !important;
    object-fit: cover;
    border-radius: 6px !important;
    display: block;
    box-shadow: 0 0 12px rgba(0,0,0,.25) !important;
}

.wp-core-ui .attachment .thumbnail:after {
    display: none !important;
    box-shadow: none !important;
}

/* Responsive columns */
@media (max-width: 1200px) {
    .attachment {
        max-width: 30%;
    }
}
@media (max-width: 800px) {
    .attachment {
        max-width: 45%;
    }
}
@media (max-width: 500px) {
    .attachment {
        max-width: 90%;
    }
}

/* Hover effect */
.attachment:hover {
    outline: 2px solid #aaa;
}
CSS;
}



// function tomatillo_design_avif_everywhere_admin_js() {
//     return <<<JS
// document.addEventListener('DOMContentLoaded', function() {
//     console.log("tomatillo_design_avif_everywhere_admin_js 752");
//     const observer = new MutationObserver(mutations => {
//         mutations.forEach(mutation => {
//             mutation.addedNodes.forEach(node => {
//                 if (node.nodeType === 1 && node.classList.contains('attachment')) {
//                     const img = node.querySelector('img');
//                     if (img) {
//                         const originalSrc = img.getAttribute('src');
//                         if (originalSrc && /\.(jpe?g|png)$/i.test(originalSrc)) {

//                             const testAvif = (srcToTry, onSuccess, onFail) => {
//                                 const probe = new Image();
//                                 probe.onload = () => onSuccess(srcToTry);
//                                 probe.onerror = () => onFail();
//                                 probe.src = srcToTry;
//                             };

//                             const baseAvif = originalSrc.replace(/\.(jpe?g|png)$/i, '.avif');
//                             const fallbackAvif = baseAvif.replace(/-\\d+x\\d+\\.avif$/i, '.avif');

//                             testAvif(baseAvif, function(successUrl) {
//                                 img.src = successUrl;
//                             }, function() {
//                                 if (baseAvif !== fallbackAvif) {
//                                     testAvif(fallbackAvif, function(successUrl) {
//                                         img.src = successUrl;
//                                     }, function() {
//                                         // final fallback = do nothing, leave original JPG/PNG
//                                     });
//                                 }
//                             });
//                         }
//                     }
//                 }
//             });
//         });
//     });

//     const container = document.querySelector('.attachments') || document.body;
//     observer.observe(container, { childList: true, subtree: true });
// });
// JS;
// }


add_action('wp_ajax_tomatillo_check_avif_exists', function () {
    if (empty($_GET['url'])) {
        wp_send_json_error('Missing URL');
    }

    $upload_dir = wp_get_upload_dir();
    $url = esc_url_raw($_GET['url']);
    $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

    if (file_exists($path)) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
});



// function tomatillo_design_avif_everywhere_admin_js() {
//     return <<<JS
// document.addEventListener('DOMContentLoaded', function () {
    
//     console.log("tomatillo_design_avif_everywhere_admin_js 830");

//     const avifCheckCache = {};
//     const avifCheckQueue = [];
//     let activeRequests = 0;
//     const MAX_CONCURRENT = 4;

//     const processQueue = () => {
//         if (activeRequests >= MAX_CONCURRENT || avifCheckQueue.length === 0) return;

//         const { img, baseAvif, fallbackAvif } = avifCheckQueue.shift();
//         activeRequests++;

//         const tryUrl = async (url) => {
//             if (avifCheckCache[url] !== undefined) return avifCheckCache[url];

//             try {
//                 const res = await fetch(ajaxurl + '?action=tomatillo_check_avif_exists&url=' + encodeURIComponent(url));
//                 const data = await res.json();
//                 avifCheckCache[url] = data.success === true;
//                 return avifCheckCache[url];
//             } catch (err) {
//                 avifCheckCache[url] = false;
//                 return false;
//             }
//         };

//         const tryReplace = async () => {
//             if (await tryUrl(baseAvif)) {
//                 img.src = baseAvif;
//             } else if (fallbackAvif && fallbackAvif !== baseAvif && await tryUrl(fallbackAvif)) {
//                 img.src = fallbackAvif;
//             }
//             activeRequests--;
//             setTimeout(processQueue, 50); // small spacing between batches
//         };

//         tryReplace();
//     };

//     const enqueueCheck = (img) => {
//         const originalSrc = img.getAttribute('src');
//         if (!originalSrc || !/\.(jpe?g|png)$/i.test(originalSrc)) return;

//         const baseAvif = originalSrc.replace(/\.(jpe?g|png)$/i, '.avif');
//         const hasSizeSuffix = /-\d+x\d+\.avif$/.test(baseAvif);
//         const fallbackAvif = hasSizeSuffix
//             ? baseAvif.replace(/-\d+x\d+\.avif$/, '.avif')
//             : null;

//         avifCheckQueue.push({ img, baseAvif, fallbackAvif });
//         processQueue();
//     };

//     const observer = new MutationObserver(mutations => {
//         mutations.forEach(mutation => {
//             mutation.addedNodes.forEach(node => {
//                 if (node.nodeType === 1 && node.classList.contains('attachment')) {
//                     const img = node.querySelector('img');
//                     if (img) enqueueCheck(img);
//                 }
//             });
//         });
//     });

//     const container = document.querySelector('.attachments') || document.body;
//     observer.observe(container, { childList: true, subtree: true });
// });

// JS;
// }




function tomatillo_design_avif_everywhere_admin_js() {
    return <<<JS
document.addEventListener('DOMContentLoaded', function () {
    console.log("tomatillo_design_avif_everywhere_admin_js 850");

    const avifCheckCache = {};
    const avifCheckQueue = [];
    let activeRequests = 0;
    const MAX_CONCURRENT = 4;

    const processQueue = () => {
        if (activeRequests >= MAX_CONCURRENT || avifCheckQueue.length === 0) return;

        const { img, baseAvif, fallbackAvif } = avifCheckQueue.shift();
        activeRequests++;

        const tryUrl = async (url) => {
            if (avifCheckCache[url] !== undefined) return avifCheckCache[url];

            try {
                const res = await fetch(ajaxurl + '?action=tomatillo_check_avif_exists&url=' + encodeURIComponent(url));
                const data = await res.json();
                avifCheckCache[url] = data.success === true;
                return avifCheckCache[url];
            } catch (err) {
                avifCheckCache[url] = false;
                return false;
            }
        };

        const tryReplace = async () => {
            if (await tryUrl(baseAvif)) {
                img.src = baseAvif;
            } else if (fallbackAvif && fallbackAvif !== baseAvif && await tryUrl(fallbackAvif)) {
                img.src = fallbackAvif;
            }
            activeRequests--;
            setTimeout(processQueue, 50);
        };

        tryReplace();
    };

    const enqueueCheck = (img) => {
        const originalSrc = img.getAttribute('src');
        if (!originalSrc || !/\.(jpe?g|png)$/i.test(originalSrc)) return;

        const baseAvif = originalSrc.replace(/\.(jpe?g|png)$/i, '.avif');
        const hasSizeSuffix = /-\\d+x\\d+\\.avif$/.test(baseAvif);
        const fallbackAvif = hasSizeSuffix
            ? baseAvif.replace(/-\\d+x\\d+\\.avif$/, '.avif')
            : null;

        avifCheckQueue.push({ img, baseAvif, fallbackAvif });
        processQueue();
    };

    const handleNode = (node) => {
        if (node.nodeType !== 1) return;
        if (node.classList.contains('attachment')) {
            const img = node.querySelector('img');
            if (img) enqueueCheck(img);
        } else {
            const imgs = node.querySelectorAll('.attachment img');
            imgs.forEach(img => enqueueCheck(img));
        }
    };

    // Observer callback shared by both contexts
    const observerCallback = (mutations) => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => handleNode(node));
        });
    };

    // 1. Global observer for main media grid (upload.php)
    const mainObserver = new MutationObserver(observerCallback);
    mainObserver.observe(document.body, { childList: true, subtree: true });

    // 2. Modal loader watcher — triggers a scoped observer once modal grid appears
    const modalWatchObserver = new MutationObserver(() => {
        const modalGrid = document.querySelector('.media-modal-content .attachments');
        if (modalGrid && !modalGrid.dataset.avifWatched) {
            modalGrid.dataset.avifWatched = 'true';
            const modalObserver = new MutationObserver(observerCallback);
            modalObserver.observe(modalGrid, { childList: true, subtree: true });
        }
    });

    modalWatchObserver.observe(document.body, { childList: true, subtree: true });
});
JS;
}
