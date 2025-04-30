<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tomatillo Design AVIF Everywhere - Frontend and Block Editor Handling
 *
 * - Replace frontend images with AVIF where available
 * - No output modifications if plugin setting is disabled
 */


/**
 * Swap frontend images (themes/templates) to AVIF
 */
add_filter( 'wp_get_attachment_image_src', 'tomatillo_avif_filter_image_src', 10, 4 );

function tomatillo_avif_filter_image_src( $image, $attachment_id, $size, $icon ) {
    if ( ! tomatillo_avif_is_enabled() ) {
        return $image;
    }

    if ( ! is_array( $image ) || empty( $image[0] ) ) {
        return $image;
    }

    $original_url = $image[0];
    $avif_url = tomatillo_avif_guess_avif_url( $original_url );

    if ( $avif_url ) {
        $image[0] = $avif_url;
    }

    return $image;
}

/**
 * Swap Gutenberg rendered blocks (core/image, core/gallery) to use <picture> with AVIF
 */
add_filter( 'render_block', 'tomatillo_avif_render_block_filter', 9, 2 );

function tomatillo_avif_render_block_filter( $block_content, $block ) {
    if ( ! tomatillo_avif_is_enabled() ) {
        return $block_content;
    }

    if ( empty( $block['blockName'] ) ) {
        return $block_content;
    }

    if ( ! in_array( $block['blockName'], [ 'core/image', 'core/gallery' ], true ) ) {
        return $block_content;
    }

    if ( strpos( $block_content, '<img' ) === false ) {
        return $block_content;
    }

    libxml_use_internal_errors( true );

    $dom = new DOMDocument();
    $dom->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $block_content );

    $image_nodes = $dom->getElementsByTagName( 'img' );

    if ( $image_nodes->length === 0 ) {
        return $block_content;
    }

    $images_to_replace = [];

    foreach ( $image_nodes as $img ) {
        $parent = $img->parentNode;
        if ( $parent && $parent->nodeName === 'picture' ) {
            continue;
        }
        $images_to_replace[] = $img;
    }

    foreach ( $images_to_replace as $img ) {
        $src = $img->getAttribute( 'src' );
        $avif_src = tomatillo_avif_guess_avif_url( $src );

        if ( ! $avif_src ) {
            continue;
        }

        $picture = $dom->createElement( 'picture' );

        $source = $dom->createElement( 'source' );
        $source->setAttribute( 'srcset', esc_url( $avif_src ) );
        $source->setAttribute( 'type', 'image/avif' );

        $img_clone = $img->cloneNode( true );

        $picture->appendChild( $source );
        $picture->appendChild( $img_clone );

        $img->parentNode->replaceChild( $picture, $img );
    }

    $html = $dom->saveHTML();

    $html = preg_replace( '~<(?:!DOCTYPE|/?(?:html|body))[^>]*>~i', '', $html );
    $html = preg_replace( '~<meta[^>]+charset[^>]*>~i', '', $html );

    return $html;
}

/**
 * Swap Block Editor REST API attachment previews
 */
add_filter( 'rest_prepare_attachment', 'tomatillo_avif_rest_api_attachment', 10, 3 );

function tomatillo_avif_rest_api_attachment( $response, $post, $request ) {
    if ( ! tomatillo_avif_is_enabled() ) {
        return $response;
    }

    if ( ! $response instanceof WP_REST_Response ) {
        return $response;
    }

    $mime = get_post_mime_type( $post );
    if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png' ], true ) ) {
        return $response;
    }

    if ( ! empty( $response->data['source_url'] ) ) {
        $avif_url = tomatillo_avif_guess_avif_url( $response->data['source_url'] );

        if ( $avif_url ) {
            $response->data['source_url'] = $avif_url;

            if ( ! empty( $response->data['media_details']['sizes'] ) ) {
                foreach ( $response->data['media_details']['sizes'] as &$size_data ) {
                    if ( isset( $size_data['source_url'] ) ) {
                        $size_data['source_url'] = $avif_url;
                    }
                }
            }
        }
    }

    return $response;
}

/**
 * Guess AVIF URL from original URL
 */
function tomatillo_avif_guess_avif_url( $url ) {
    if ( ! tomatillo_avif_is_enabled() ) {
        return false;
    }

    $avif_url = preg_replace( '/\\.(jpg|jpeg|png)$/i', '.avif', $url );

    if ( tomatillo_avif_remote_file_exists( $avif_url ) ) {
        return $avif_url;
    }

    $fallback_url = preg_replace( '/-\\d+x\\d+(?=\\.avif$)/i', '', $avif_url );
    if ( tomatillo_avif_remote_file_exists( $fallback_url ) ) {
        return $fallback_url;
    }

    $fallback_url_scaled = str_replace( '-scaled.avif', '.avif', $avif_url );
    if ( tomatillo_avif_remote_file_exists( $fallback_url_scaled ) ) {
        return $fallback_url_scaled;
    }

    return false;
}




/**
 * Fast file existence check with per-page cache
 */
function tomatillo_avif_remote_file_exists( $url ) {
    static $cache = [];

    if ( isset( $cache[ $url ] ) ) {
        return $cache[ $url ];
    }

    $file = str_replace( site_url(), ABSPATH, $url );
    $exists = file_exists( $file );

    $cache[ $url ] = $exists;

    return $exists;
}


