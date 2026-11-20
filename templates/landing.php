<?php
/**
 * Template Name: BeBrand landing page
 *
 * Shows a page as the BeBrand website: the page's own sections, with the theme's design around them and nothing else.
 */

defined( 'ABSPATH' ) || exit;

$bebrand_html = bebrand_landing_html( get_queried_object_id() );
if ( '' === $bebrand_html ) {
	// The design files are missing: fall back to the ordinary page layout.
	require get_template_directory() . '/index.php';
	return;
}
echo $bebrand_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the theme's own files and the page's own content.
