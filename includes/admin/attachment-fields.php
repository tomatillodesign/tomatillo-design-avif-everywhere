<?php
// Adds AVIF and WebP metadata fields to the single Media view
add_filter( 'attachment_fields_to_edit', 'tomatillo_add_avif_webp_meta_fields', 10, 2 );

function tomatillo_add_avif_webp_meta_fields( $form_fields, $post ) {
	$avif_url = get_post_meta( $post->ID, '_avif_url', true );
	$avif_size = get_post_meta( $post->ID, '_avif_size_kb', true );

	$webp_url = get_post_meta( $post->ID, '_webp_url', true );
	$webp_size = get_post_meta( $post->ID, '_webp_size_kb', true );

	if ( $avif_url ) {
		$html = '<code style="font-size: 12px;">' . esc_url( $avif_url ) . '</code>';
		if ( $avif_size ) {
			$html .= '<br><span style="font-size: 12px; color: #666;">Size: ' . intval( $avif_size ) . ' KB</span>';
		}

		$form_fields['tomatillo_avif_url'] = [
			'label' => 'AVIF URL',
			'input' => 'html',
			'html'  => $html,
		];
	}

	if ( $webp_url ) {
		$html = '<code style="font-size: 12px;">' . esc_url( $webp_url ) . '</code>';
		if ( $webp_size ) {
			$html .= '<br><span style="font-size: 12px; color: #666;">Size: ' . intval( $webp_size ) . ' KB</span>';
		}

		$form_fields['tomatillo_webp_url'] = [
			'label' => 'WebP URL',
			'input' => 'html',
			'html'  => $html,
		];
	}

	return $form_fields;
}

