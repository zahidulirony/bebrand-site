<?php
/**
 * The home page as one Elementor element, "BeBrand site".
 *
 * The page is the same markup, styles and script as the BeBrand landing page. What changes is where the words come from: every
 * text of the page is a field of the element (site/fields.json lists them, the build makes that list from the page itself), and
 * the page is put together from site/sections.json with the fields filled in. A field nobody changed goes out exactly as it
 * was written, so the page built from the defaults is the page as designed.
 *
 * On the public site this theme builds the page itself from the element's saved fields, so nothing of Elementor's own markup,
 * styles or scripts stands between the design and the visitor. Inside Elementor's editor the element draws the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEBRAND_SITE_TEMPLATE', 'templates/site.php' );
define( 'BEBRAND_SITE_WIDGET', 'bebrand-site' );

/** A JSON file of the site folder, read once. */
function bebrand_site_file( $name ) {
	static $files = array();
	if ( ! isset( $files[ $name ] ) ) {
		$raw            = file_get_contents( get_template_directory() . '/site/' . $name );
		$data           = false === $raw ? null : json_decode( $raw, true );
		$files[ $name ] = is_array( $data ) ? $data : array();
	}
	return $files[ $name ];
}

/** The sections of the page, each with its fields. */
function bebrand_site_sections() {
	$file = bebrand_site_file( 'fields.json' );
	return isset( $file['sections'] ) ? $file['sections'] : array();
}

/** Every field by its key. */
function bebrand_site_fields() {
	static $all = null;
	if ( null === $all ) {
		$all = array();
		foreach ( bebrand_site_sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$all[ $field['key'] ] = $field;
			}
		}
	}
	return $all;
}

/** Text with the few tags a heading or sentence of the page uses. */
function bebrand_site_inline( $html ) {
	$cls = array( 'class' => true );
	return wp_kses(
		(string) $html,
		array(
			'br'     => $cls,
			'em'     => $cls,
			'strong' => $cls,
			'b'      => $cls,
			'i'      => $cls,
			'u'      => $cls,
			's'      => $cls,
			'small'  => $cls,
			'sup'    => $cls,
			'sub'    => $cls,
			'span'   => $cls,
			'wbr'    => array(),
			'a'      => array(
				'href'   => true,
				'class'  => true,
				'target' => true,
				'rel'    => true,
			),
		)
	);
}

/** The same for words the script writes into the page: safe inside an attribute as well. */
function bebrand_site_text( $text ) {
	return str_replace( '"', '&quot;', bebrand_site_inline( $text ) );
}

/** Has this field been given a value of its own? */
function bebrand_site_changed( $settings, $key ) {
	$fields = bebrand_site_fields();
	return isset( $fields[ $key ] ) && is_array( $settings ) && array_key_exists( $key, $settings ) && $settings[ $key ] !== $fields[ $key ]['default'];
}

/** One section template with the fields filled in. */
function bebrand_site_fill( $template, $settings ) {
	$fields = bebrand_site_fields();
	return preg_replace_callback(
		'/\{\{([fah]):([a-z0-9_]+)\}\}/',
		function ( $m ) use ( $fields, $settings ) {
			if ( ! isset( $fields[ $m[2] ] ) ) {
				return '';
			}
			$field = $fields[ $m[2] ];
			$value = is_array( $settings ) && array_key_exists( $field['key'], $settings ) ? $settings[ $field['key'] ] : $field['default'];
			if ( 'h' === $m[1] ) {
				// A link that has no address until one is typed in.
				$url = is_string( $value ) ? esc_url( trim( $value ) ) : '';
				return '' === $url ? '' : ' href="' . $url . '" target="_blank" rel="noopener"';
			}
			if ( 'choice' === $field['kind'] ) {
				return isset( $field['html'][ $value ] ) ? $field['html'][ $value ] : $field['html'][ $field['default'] ];
			}
			if ( ! is_string( $value ) || $value === $field['default'] ) {
				return $field['default'];          // untouched text goes out exactly as it was written
			}
			if ( 'a' === $m[1] ) {
				return esc_attr( $value );
			}
			if ( 'attr' === $field['kind'] ) {
				return isset( $field['attr'] ) && 'href' === $field['attr'] ? esc_url( $value ) : esc_attr( $value );
			}
			return 'html' === $field['kind'] ? bebrand_site_inline( $value ) : esc_html( $value );
		},
		$template
	);
}

/** Lines of a text box as a list. */
function bebrand_site_lines( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$out[] = bebrand_site_text( $line );
		}
	}
	return $out;
}

/**
 * The lists the script draws (services, prices, questions, reviews, work photos), where they differ from the ones built into
 * the script. Nothing changed: nothing is sent and the script uses its own.
 */
function bebrand_site_data( $settings ) {
	if ( ! is_array( $settings ) ) {
		return array();
	}
	$base  = bebrand_site_file( 'data.json' );
	$file  = bebrand_site_file( 'fields.json' );
	$links = isset( $file['links'] ) ? $file['links'] : array();
	$out   = array();
	if ( empty( $base['caps'] ) || empty( $base['pricing'] ) ) {
		return $out;
	}

	// Services.
	$caps = $base['caps'];
	foreach ( $caps as $i => $cap ) {
		foreach ( array( 'title' => 0, 'text' => 1 ) as $part => $at ) {
			$key = 'svc' . ( $i + 1 ) . '_' . $part;
			if ( bebrand_site_changed( $settings, $key ) && is_string( $settings[ $key ] ) ) {
				$caps[ $i ][ $at ] = bebrand_site_text( $settings[ $key ] );
			}
		}
	}
	if ( $caps !== $base['caps'] ) {
		$out['caps'] = $caps;
	}

	// Prices.
	$pricing = $base['pricing'];
	foreach ( array( 'once', 'month' ) as $bill ) {
		$words = array( 'unit' => 'pr_' . $bill . '_unit' );
		if ( isset( $links[ $bill . '_label' ] ) ) {
			$words['label'] = $links[ $bill . '_label' ];
		}
		foreach ( $words as $prop => $key ) {
			if ( bebrand_site_changed( $settings, $key ) && is_string( $settings[ $key ] ) ) {
				$pricing[ $bill ][ $prop ] = bebrand_site_text( $settings[ $key ] );
			}
		}
		foreach ( array( 'realtor', 'firm' ) as $who ) {
			foreach ( $pricing[ $bill ][ $who ]['plans'] as $i => $plan ) {
				$pre = 'pl_' . $bill . '_' . $who . '_' . $i . '_';
				foreach ( array( 'name' => 'k', 'price' => 'p', 'text' => 'd' ) as $part => $prop ) {
					if ( bebrand_site_changed( $settings, $pre . $part ) && is_string( $settings[ $pre . $part ] ) ) {
						$plan[ $prop ] = bebrand_site_text( $settings[ $pre . $part ] );
					}
				}
				if ( bebrand_site_changed( $settings, $pre . 'from' ) ) {
					if ( 'yes' === $settings[ $pre . 'from' ] ) {
						$plan['from'] = true;
					} else {
						unset( $plan['from'] );
					}
				}
				if ( bebrand_site_changed( $settings, $pre . 'inc' ) && is_string( $settings[ $pre . 'inc' ] ) ) {
					$plan['inc'] = bebrand_site_lines( $settings[ $pre . 'inc' ] );
				}
				$pricing[ $bill ][ $who ]['plans'][ $i ] = $plan;
			}
		}
	}
	if ( $pricing != $base['pricing'] ) { // phpcs:ignore Universal.Operators.StrictComparisons -- same content, whatever the order of the keys.
		$out['pricing'] = $pricing;
	}
	$who = $base['who'];
	foreach ( array( 'realtor', 'firm' ) as $name ) {
		$key = isset( $links[ 'who_' . $name ] ) ? $links[ 'who_' . $name ] : '';
		if ( $key && bebrand_site_changed( $settings, $key ) && is_string( $settings[ $key ] ) ) {
			$who[ $name ] = bebrand_site_text( $settings[ $key ] );
		}
	}
	if ( $who !== $base['who'] ) {
		$out['who'] = $who;
	}

	// Questions.
	if ( isset( $settings['faq'] ) && is_array( $settings['faq'] ) ) {
		$rows = array();
		foreach ( $settings['faq'] as $row ) {
			$q = is_array( $row ) && isset( $row['q'] ) ? trim( (string) $row['q'] ) : '';
			if ( '' !== $q ) {
				$rows[] = array( bebrand_site_text( $q ), bebrand_site_text( isset( $row['a'] ) ? $row['a'] : '' ) );
			}
		}
		if ( $rows && $rows !== $base['faq'] ) {
			$out['faq'] = $rows;
		}
	}

	// Reviews.
	if ( isset( $settings['reviews'] ) && is_array( $settings['reviews'] ) ) {
		$rows = array();
		foreach ( $settings['reviews'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$get   = function ( $k ) use ( $row ) {
				return isset( $row[ $k ] ) && is_string( $row[ $k ] ) ? trim( wp_strip_all_tags( $row[ $k ] ) ) : '';
			};
			$stars = (int) $get( 'stars' );
			$one   = array(
				's' => $stars >= 1 && $stars <= 5 ? $stars : 5,
				'q' => $get( 'quote' ),
				'n' => $get( 'person' ),
				'r' => $get( 'role' ),
				'c' => preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([0-9.,\s%]+\))$/i', $get( 'colour' ) ) ? $get( 'colour' ) : '#CFE4FB',
			);
			if ( isset( $row['is_video'] ) && 'yes' === $row['is_video'] ) {
				$one['v'] = esc_url_raw( $get( 'video' ) );
			}
			$photo = isset( $row['photo'] ) && is_array( $row['photo'] ) && ! empty( $row['photo']['url'] ) ? esc_url_raw( $row['photo']['url'] ) : '';
			if ( $photo ) {
				$one['p'] = $photo;
			}
			if ( '' !== $one['q'] || '' !== $one['n'] ) {
				$rows[] = $one;
			}
		}
		if ( $rows && $rows != $base['reviews'] ) { // phpcs:ignore Universal.Operators.StrictComparisons
			$out['reviews'] = $rows;
		}
	}

	// Work photos.
	if ( ! empty( $settings['work_photos'] ) && is_array( $settings['work_photos'] ) ) {
		$urls = array();
		foreach ( $settings['work_photos'] as $pic ) {
			if ( is_array( $pic ) && ! empty( $pic['url'] ) ) {
				$urls[] = esc_url_raw( $pic['url'] );
			}
		}
		if ( $urls ) {
			$out['work'] = $urls;
		}
	}
	return $out;
}

/** The body of the page (navigation to quick-navigation bar) for these field values. */
function bebrand_site_body( $settings ) {
	$templates = bebrand_site_file( 'sections.json' );
	$parts     = array();
	foreach ( bebrand_site_sections() as $section ) {
		if ( isset( $templates[ $section['key'] ] ) ) {
			$parts[] = bebrand_site_fill( $templates[ $section['key'] ], $settings );
		}
	}
	$html = bebrand_wrap_sections( implode( "\n\n", $parts ) );
	$data = bebrand_site_data( $settings );
	if ( $data ) {
		$html .= "\n" . '<script type="application/json" id="bb-data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	}
	return $html;
}

/** The first "BeBrand site" element in a tree of Elementor elements: its settings, or null. */
function bebrand_site_find( $elements ) {
	if ( ! is_array( $elements ) ) {
		return null;
	}
	foreach ( $elements as $el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		if ( isset( $el['widgetType'] ) && BEBRAND_SITE_WIDGET === $el['widgetType'] ) {
			return isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
		}
		$inner = isset( $el['elements'] ) ? bebrand_site_find( $el['elements'] ) : null;
		if ( null !== $inner ) {
			return $inner;
		}
	}
	return null;
}

/** The fields saved for a page: from Elementor when it is running (so a preview shows the unsaved draft), else from the page itself. */
function bebrand_site_settings( $post_id ) {
	$elements = null;
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
		try {
			$doc = \Elementor\Plugin::$instance->documents->get_doc_for_frontend( $post_id );
			if ( $doc ) {
				$elements = $doc->get_elements_data();
			}
		} catch ( \Throwable $e ) {
			$elements = null;
		}
	}
	if ( ! is_array( $elements ) || ! $elements ) {
		$raw      = get_post_meta( $post_id, '_elementor_data', true );
		$elements = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	}
	$found = bebrand_site_find( $elements );
	return null === $found ? array() : $found;
}

/** Is this request the page inside Elementor's editor? */
function bebrand_site_in_editor() {
	if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->preview ) ) {
		return false;
	}
	try {
		return (bool) \Elementor\Plugin::$instance->preview->is_preview_mode();
	} catch ( \Throwable $e ) {
		return false;
	}
}

/** Is this the public view of a page that uses the template? */
function bebrand_is_site_page() {
	return is_page() && BEBRAND_SITE_TEMPLATE === get_page_template_slug( get_queried_object_id() );
}

/**
 * The public page is built by the theme from the element's fields, so Elementor's own styles, fonts and scripts have nothing to
 * work on there: they are left out, which keeps the page as light as the original. (Inside the editor they all stay.)
 */
function bebrand_site_drop_elementor_assets() {
	if ( ! bebrand_is_site_page() || bebrand_site_in_editor() ) {
		return;
	}
	$theirs = '/^(elementor|e-|widget-|swiper|font-awesome|fontawesome|base-desktop|base-mobile|base-tablet)/';
	foreach ( wp_styles()->queue as $handle ) {
		if ( preg_match( $theirs, $handle ) && false === strpos( $handle, 'admin-bar' ) ) {
			wp_dequeue_style( $handle );
		}
	}
	foreach ( wp_scripts()->queue as $handle ) {
		if ( preg_match( $theirs, $handle ) && false === strpos( $handle, 'admin-bar' ) ) {
			wp_dequeue_script( $handle );
		}
	}
}
add_action( 'wp_print_styles', 'bebrand_site_drop_elementor_assets', 100 );
add_action( 'wp_print_footer_scripts', 'bebrand_site_drop_elementor_assets', 1 );

/**
 * Only the front page is the site. Another page with the same design (the block-built "Home" kept as a fallback, or this page
 * before it is made the front page) is a copy: it is marked noindex and left out of WordPress's sitemap, so search engines see
 * the site once.
 */
function bebrand_site_copy_robots( $robots ) {
	if ( bebrand_is_landing() && ! is_front_page() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'bebrand_site_copy_robots' );

function bebrand_site_copy_sitemap( $args, $type ) {
	if ( 'page' !== $type ) {
		return $args;
	}
	$copies = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 50,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of pages, asked for the sitemap only.
				array(
					'key'     => '_wp_page_template',
					'value'   => array( BEBRAND_LANDING, BEBRAND_SITE_TEMPLATE ),
					'compare' => 'IN',
				),
			),
		)
	);
	$copies = array_diff( array_map( 'intval', $copies ), array( (int) get_option( 'page_on_front' ) ) );
	if ( $copies ) {
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $copies );
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'bebrand_site_copy_sitemap', 10, 2 );

/** The whole document for a page that uses the template. */
function bebrand_site_page_html( $post_id ) {
	$dir  = get_template_directory() . '/site/';
	$head = file_get_contents( $dir . 'head.html' );
	$tail = file_get_contents( $dir . 'tail.html' );
	$icon = file_get_contents( $dir . 'sprite.svg' );
	if ( false === $head || false === $tail || false === $icon || ! get_post( $post_id ) ) {
		return '';
	}
	$editing = bebrand_site_in_editor();

	ob_start();
	wp_head();
	$wp_head = ob_get_clean();

	if ( $editing ) {
		// Elementor draws the element itself, inside the content. Its own styles are told apart by the layer filter below; here the
		// box around the element is flattened so the page spans the window as it does on the site.
		ob_start();
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		$body     = ob_get_clean();
		// The site kit's class is left off the body: its colours and fonts for headings, links and buttons belong to pages built
		// from Elementor's own elements, and would repaint this design in the editor only.
		$classes  = array_filter(
			get_body_class( 'bebrand-el-preview' ),
			function ( $c ) {
				return ! preg_match( '/^elementor-kit-\d+$/', $c );
			}
		);
		$head     = str_replace( '<body>', '<body class="' . esc_attr( implode( ' ', $classes ) ) . '">', $head );
		$wp_head .= '<style>.bebrand-el-preview .e-con,.bebrand-el-preview .e-con>.e-con-inner,.bebrand-el-preview .elementor-section,.bebrand-el-preview .elementor-container,.bebrand-el-preview .elementor-column,.bebrand-el-preview .elementor-widget-wrap{display:block!important;padding:0!important;margin:0!important;max-width:none!important;width:auto!important;gap:0!important}.bebrand-el-preview .elementor-widget-' . BEBRAND_SITE_WIDGET . '{margin:0!important;width:auto!important;max-width:none!important}</style>' . "\n";
		// Elementor's boxes carry custom properties of their own (--display, --gap ...), which are inherited: the element's box
		// states the design's own again so the page reads the same values as on the site.
		$vars     = file_get_contents( $dir . 'editor-vars.css' );
		$wp_head .= '<style>' . ( false === $vars ? '' : $vars ) . '</style>' . "\n";
		$boot     = file_get_contents( $dir . 'boot.js' );
		$tail     = '<script>' . "\n" . ( false === $boot ? '' : $boot ) . '</script>' . "\n" . '{{BB_FOOT}}</body>' . "\n" . '</html>' . "\n";
	} else {
		$body = bebrand_site_body( bebrand_site_settings( $post_id ) );
		if ( is_admin_bar_showing() ) {
			$wp_head .= '<style>.nav{top:32px}@media screen and (max-width:782px){.nav{top:46px}}@media screen and (max-width:600px){.nav{top:0}}</style>' . "\n";
		}
	}

	ob_start();
	wp_footer();
	$wp_foot = ob_get_clean();

	$marks = array(
		'{{BB_ASSETS}}' => esc_url( get_template_directory_uri() . '/site/' ),
		'{{BB_API}}'    => wp_json_encode( admin_url( 'admin-ajax.php?action=bebrand_send' ) ),
		'{{BB_HEAD}}'   => $wp_head,
		'{{BB_FOOT}}'   => $wp_foot,
	);
	return strtr( $head, $marks ) . "\n" . $icon . "\n<!-- bebrand: site page " . (int) $post_id . " -->\n" . $body . "\n" . strtr( $tail, $marks );
}

/**
 * Inside the editor, every stylesheet that is not this page's own (Elementor's, other plugins') goes into a cascade layer, so
 * the page's own rules always win over them and the design looks the same there as on the site. The editor's own tools keep
 * their styles as they are.
 */
function bebrand_site_layer_styles( $tag, $handle, $href, $media ) {
	if ( is_admin() || ! $href || false !== strpos( $handle, 'editor' ) || ( $media && 'all' !== $media ) ) {
		return $tag;
	}
	if ( ! bebrand_site_in_editor() || BEBRAND_SITE_TEMPLATE !== get_page_template_slug( get_queried_object_id() ) ) {
		return $tag;
	}
	return '<style id="' . esc_attr( $handle ) . '-css">@import url("' . esc_url( $href ) . '") layer(plugins);</style>' . "\n";
}
add_filter( 'style_loader_tag', 'bebrand_site_layer_styles', 20, 4 );

/** The element is offered to Elementor when Elementor is running. */
function bebrand_site_register_widget( $manager ) {
	require_once get_template_directory() . '/inc/elementor-widget.php';
	if ( class_exists( 'BeBrand_Site_Widget' ) ) {
		$manager->register( new BeBrand_Site_Widget() );
	}
}
add_action( 'elementor/widgets/register', 'bebrand_site_register_widget' );

/**
 * The page that holds the element: made once, when Elementor is running and the page is not there yet. It is a normal Elementor
 * page (one full-width container with the one element in it) on the "BeBrand site" template, and it is never written again by
 * the theme, so what is typed into its fields stays through every theme update.
 */
function bebrand_site_make_page() {
	if ( ! did_action( 'elementor/loaded' ) || get_option( 'bebrand_site_page_id' ) ) {
		return 0;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Home (edit in Elementor)',
			'post_name'    => 'home-site',
			'post_content' => '',
			'meta_input'   => array( '_wp_page_template' => BEBRAND_SITE_TEMPLATE ),
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	$mark = substr( md5( 'bebrand' . $id ), 0, 7 );
	$zero = array(
		'unit'     => 'px',
		'top'      => '0',
		'right'    => '0',
		'bottom'   => '0',
		'left'     => '0',
		'isLinked' => true,
	);
	$tree = array(
		array(
			'id'       => substr( md5( 'box' . $id ), 0, 7 ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array(
				'content_width' => 'full',
				'padding'       => $zero,
				'margin'        => $zero,
				'flex_gap'      => array(
					'unit'     => 'px',
					'size'     => 0,
					'column'   => '0',
					'row'      => '0',
					'isLinked' => true,
				),
			),
			'elements' => array(
				array(
					'id'         => $mark,
					'elType'     => 'widget',
					'widgetType' => BEBRAND_SITE_WIDGET,
					'isInner'    => false,
					'settings'   => new stdClass(),
					'elements'   => array(),
				),
			),
		),
	);
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	update_option( 'bebrand_site_page_id', (int) $id );
	return (int) $id;
}
add_action( 'init', 'bebrand_site_make_page', 30 );
