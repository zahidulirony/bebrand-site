<?php
/**
 * Template Name: BeBrand site (edited in Elementor)
 *
 * Shows the BeBrand website with its words taken from the "BeBrand site" element of the page, which is edited in Elementor.
 */

defined( 'ABSPATH' ) || exit;

$bebrand_html = bebrand_site_page_html( get_queried_object_id() );
if ( '' === $bebrand_html ) {
	// The design files are missing: fall back to the ordinary page layout.
	require get_template_directory() . '/index.php';
	return;
}
echo $bebrand_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the theme's own files, with the fields escaped as they are filled in.
