<?php
/**
 * BeBrand theme.
 *
 * The website's home page is an ordinary WordPress page ("Home"), made of one HTML block per section, shown with the
 * "BeBrand landing page" template. This theme supplies that page's design (styles, pictures, script), writes the page when
 * the theme is first switched on, and receives the site's project briefs and call requests.
 */

defined( 'ABSPATH' ) || exit;

define( 'BEBRAND_VERSION', '1.2.0-567a3e02' );
define( 'BEBRAND_LANDING', 'templates/landing.php' );
define( 'BEBRAND_FONTS', 'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400..700&family=Outfit:wght@400;500;600&display=swap' );

/* ------------------------------------------------------------------
 * Setup
 * ------------------------------------------------------------------ */

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'html5', array( 'style', 'script', 'search-form', 'gallery', 'caption' ) );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'editor-styles' );
	}
);

/**
 * In the editor, a page looks like it does on the site: the landing page with its own design, any other page with the
 * theme's simple styles. The landing page also gets its icons and fonts there (bebrand_editor_extras).
 */
function bebrand_editor_setup() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $post_id && 'page' === get_post_type( $post_id ) && BEBRAND_LANDING === get_page_template_slug( $post_id ) ) {
		add_editor_style( 'site/editor.css' );
		add_action( 'enqueue_block_editor_assets', 'bebrand_editor_extras' );
	} else {
		add_editor_style( array( BEBRAND_FONTS, 'style.css' ) );
	}
}
add_action( 'load-post.php', 'bebrand_editor_setup' );
add_action( 'load-post-new.php', 'bebrand_editor_setup' );

/** Puts the icon sheet and the fonts into the editor's canvas, where the page's sections are shown. */
function bebrand_editor_extras() {
	$sprite = file_get_contents( get_template_directory() . '/site/sprite.svg' );
	if ( false === $sprite ) {
		return;
	}
	$js  = '(function(){var S=' . wp_json_encode( $sprite ) . ',F=' . wp_json_encode( BEBRAND_FONTS ) . ';';
	$js .= 'function fill(){var f=document.querySelector(\'iframe[name="editor-canvas"]\'),d=f?f.contentDocument:document;';
	$js .= 'if(!d||!d.body||!d.head||!d.querySelector(".editor-styles-wrapper"))return;';
	$js .= 'if(!d.getElementById("bb-sprite")){var w=d.createElement("div");w.id="bb-sprite";w.setAttribute("aria-hidden","true");w.style.cssText="position:absolute;width:0;height:0;overflow:hidden";w.innerHTML=S;d.body.appendChild(w)}';
	$js .= 'if(!d.getElementById("bb-fonts")){var l=d.createElement("link");l.id="bb-fonts";l.rel="stylesheet";l.href=F;d.head.appendChild(l)}}';
	$js .= 'setInterval(fill,700);fill()})();';
	wp_register_script( 'bebrand-editor', false, array(), BEBRAND_VERSION, true );
	wp_enqueue_script( 'bebrand-editor' );
	wp_add_inline_script( 'bebrand-editor', $js );
}

/** True on a page shown with the landing template, or with the same design fed from the Elementor element. */
function bebrand_is_landing() {
	return is_page() && in_array( get_page_template_slug( get_queried_object_id() ), array( BEBRAND_LANDING, BEBRAND_SITE_TEMPLATE ), true );
}

require_once get_template_directory() . '/inc/site.php';

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( bebrand_is_landing() ) {
			// The landing page brings its whole design with it: WordPress's own block styles stay out of it.
			foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles' ) as $handle ) {
				wp_dequeue_style( $handle );
			}
			return;
		}
		wp_enqueue_style( 'bebrand-fonts', BEBRAND_FONTS, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'bebrand', get_stylesheet_uri(), array(), BEBRAND_VERSION );
	},
	100
);

add_action(
	'wp',
	function () {
		if ( bebrand_is_landing() ) {
			// The landing page has no emoji and no WordPress-styled blocks, so the styles and script WordPress adds for them are left out.
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
			remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
			remove_action( 'wp_head', 'wp_enqueue_img_auto_sizes_contain_css_fix', 0 );
			remove_action( 'wp_head', 'wp_print_auto_sizes_contain_css_fix', 1 );
		}
	}
);

/* ------------------------------------------------------------------
 * The landing page
 * ------------------------------------------------------------------ */

/**
 * The page's sections arrive as separate blocks. The design expects them inside <main> and, for the scrolling sections,
 * inside <div id="home">, so those two wrappers are put back around them here: <main> opens before the first section,
 * #home closes before the project form (or, without one, where <main> closes), and <main> closes before the closing
 * band, the footer or the end, whichever comes first.
 */
function bebrand_wrap_sections( $html ) {
	$first = strpos( $html, '<section' );
	if ( false === $first ) {
		return $html;
	}
	$end = false;
	foreach ( array( '<div class="closing"', '<footer', '<dialog' ) as $mark ) {
		$end = strpos( $html, $mark, $first );
		if ( false !== $end ) {
			break;
		}
	}
	if ( false === $end ) {
		$end = strlen( $html );
	}
	$start = strpos( $html, '<section class="start-sec"', $first );
	if ( false === $start || $start > $end ) {
		$start = $end;
	}
	return substr( $html, 0, $first ) . "<main>\n<div id=\"home\">\n" . substr( $html, $first, $start - $first ) . "</div>\n" . substr( $html, $start, $end - $start ) . "</main>\n" . substr( $html, $end );
}

/** The whole landing page as HTML: the theme's head and styles, the page's own sections, the theme's script. */
function bebrand_landing_html( $post_id ) {
	$dir  = get_template_directory() . '/site/';
	$head = file_get_contents( $dir . 'head.html' );
	$tail = file_get_contents( $dir . 'tail.html' );
	$icon = file_get_contents( $dir . 'sprite.svg' );
	$post = get_post( $post_id );
	if ( false === $head || false === $tail || false === $icon || ! $post ) {
		return '';
	}

	// The blocks are given out as they were written, without WordPress's text filters, so the markup reaches the browser unchanged.
	$body = bebrand_wrap_sections( do_blocks( $post->post_content ) );

	ob_start();
	wp_head();
	$wp_head = ob_get_clean();
	if ( is_admin_bar_showing() ) {
		// Signed-in visitors see WordPress's toolbar: the site's own bar sticks below it.
		$wp_head .= '<style>.nav{top:32px}@media screen and (max-width:782px){.nav{top:46px}}@media screen and (max-width:600px){.nav{top:0}}</style>' . "\n";
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
	return strtr( $head, $marks ) . "\n" . $icon . "\n<!-- bebrand: page " . (int) $post->ID . " -->\n" . $body . "\n" . strtr( $tail, $marks );
}

/* ------------------------------------------------------------------
 * The Home page: written when the theme is switched on, and kept up to date while nobody has edited it
 * ------------------------------------------------------------------ */

/** The Home page's content as the theme ships it: one HTML block per section. */
function bebrand_home_blocks() {
	$html = file_get_contents( get_template_directory() . '/site/content.html' );
	return false === $html ? '' : $html;
}

/**
 * Creates the page, or with an ID changes only the fields given. The content is saved exactly as given: WordPress would
 * otherwise strip forms, drawings and the like when nobody is signed in.
 */
function bebrand_save_page( $postarr ) {
	kses_remove_filters();
	$id = empty( $postarr['ID'] ) ? wp_insert_post( wp_slash( $postarr ), true ) : wp_update_post( wp_slash( $postarr ), true );
	kses_init();
	return $id;
}

/**
 * Makes sure the Home page exists and holds the theme's current sections.
 * A page somebody has edited in WordPress is left alone (a notice in the admin offers to replace it), unless $force.
 *
 * @return string What happened: created, updated, current, edited or failed.
 */
function bebrand_sync_home( $force = false ) {
	$blocks = bebrand_home_blocks();
	if ( '' === $blocks ) {
		return 'failed';
	}
	$id   = (int) get_option( 'bebrand_home_id' );
	$post = $id ? get_post( $id ) : null;

	if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
		$id = bebrand_save_page(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Home',
				'post_name'    => 'home',
				'post_content' => $blocks,
				'meta_input'   => array( '_wp_page_template' => BEBRAND_LANDING ),
			)
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 'failed';
		}
		update_option( 'bebrand_home_id', (int) $id );
		update_option( 'bebrand_home_hash', md5( get_post( $id )->post_content ) );
		delete_option( 'bebrand_home_pending' );
		// A new Home page becomes the site's front page.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $id );
		return 'created';
	}

	$stored = md5( $post->post_content );
	if ( ! $force && get_option( 'bebrand_home_hash' ) !== $stored ) {
		// Edited in WordPress since the theme last wrote it: left alone. If the theme's own sections have changed too, say so in the admin.
		if ( md5( $blocks ) !== get_option( 'bebrand_home_source' ) ) {
			update_option( 'bebrand_home_pending', BEBRAND_VERSION );
		}
		return 'edited';
	}

	$result = 'current';
	if ( $force || md5( $blocks ) !== get_option( 'bebrand_home_source' ) ) {
		$done = bebrand_save_page(
			array(
				'ID'           => $post->ID,
				'post_content' => $blocks,
			)
		);
		if ( is_wp_error( $done ) || ! $done ) {
			return 'failed';
		}
		$result = 'updated';
	}
	update_option( 'bebrand_home_hash', md5( get_post( $post->ID )->post_content ) );
	delete_option( 'bebrand_home_pending' );
	return $result;
}

function bebrand_after_sync() {
	update_option( 'bebrand_home_source', md5( bebrand_home_blocks() ) );
	update_option( 'bebrand_version', BEBRAND_VERSION );
}

add_action(
	'after_switch_theme',
	function () {
		bebrand_sync_home();
		bebrand_after_sync();
	}
);

// A new version of the theme arrives as files, without the theme being switched off and on: the page is brought up to date then.
add_action(
	'init',
	function () {
		if ( get_option( 'bebrand_version' ) !== BEBRAND_VERSION ) {
			bebrand_sync_home();
			bebrand_after_sync();
		}
	},
	20
);

add_action(
	'admin_notices',
	function () {
		if ( ! get_option( 'bebrand_home_pending' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=bebrand_replace_home' ), 'bebrand_replace_home' );
		echo '<div class="notice notice-info"><p><strong>BeBrand:</strong> a newer version of the Home page is ready, but the page has been edited in WordPress, so it was left as it is. ';
		echo '<a href="' . esc_url( $url ) . '">Replace the page with the new version</a>. Your edited version stays in the page\'s revisions.</p></div>';
	}
);

add_action(
	'admin_post_bebrand_replace_home',
	function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( 'You are not allowed to do this.' );
		}
		check_admin_referer( 'bebrand_replace_home' );
		bebrand_sync_home( true );
		bebrand_after_sync();
		wp_safe_redirect( admin_url( 'edit.php?post_type=page' ) );
		exit;
	}
);

/* ------------------------------------------------------------------
 * Requests: project briefs and call requests
 * ------------------------------------------------------------------ */

/** Where requests are emailed: the address in the settings, or the site's admin address. */
function bebrand_recipient() {
	$to = sanitize_email( (string) get_option( 'bebrand_site_to', '' ) );
	return $to ? $to : get_option( 'admin_email' );
}

add_action(
	'init',
	function () {
		register_post_type(
			'bb_request',
			array(
				'labels'              => array(
					'name'               => 'Project requests',
					'singular_name'      => 'Project request',
					'menu_name'          => 'Project requests',
					'all_items'          => 'All requests',
					'edit_item'          => 'Project request',
					'view_item'          => 'Project request',
					'search_items'       => 'Search requests',
					'not_found'          => 'No requests yet.',
					'not_found_in_trash' => 'No requests in the trash.',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'menu_position'       => 25,
				'menu_icon'           => 'dashicons-email-alt',
				'supports'            => array( 'title' ),
				'capability_type'     => 'page',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
	}
);

/** A short, single-line text field from the visitor. */
function bebrand_line( $value, $max = 200 ) {
	$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
}

/** Sends the JSON answer and stops. */
function bebrand_answer( $ok, $message = '', $status = 200 ) {
	wp_send_json(
		array(
			'ok'      => (bool) $ok,
			'message' => $message,
		),
		$status
	);
}

function bebrand_receive() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		bebrand_answer( false, 'Send this form from the website.', 405 );
	}

	$raw = file_get_contents( 'php://input' );
	if ( ! is_string( $raw ) || strlen( $raw ) > 20000 ) {
		bebrand_answer( false, 'That message is too long. Shorten the notes and try again.', 413 );
	}
	$in = json_decode( $raw, true );
	if ( ! is_array( $in ) ) {
		bebrand_answer( false, 'We could not read the form. Reload the page and try again.', 400 );
	}

	// Two quiet checks against bots: a field people never see must be empty, and nobody fills the form in under three seconds.
	// A bot gets the same "sent" answer as a person, so it learns nothing.
	if ( ! empty( $in['website'] ) || ( isset( $in['ms'] ) && (int) $in['ms'] < 3000 ) ) {
		bebrand_answer( true );
	}

	// At most 20 requests an hour from one address.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$key = 'bb_rate_' . md5( $ip . wp_salt() );
	$n   = (int) get_transient( $key );
	if ( $n >= 20 ) {
		bebrand_answer( false, 'Too many requests from this connection. Try again in an hour, or email us.', 429 );
	}

	$kind = ( isset( $in['kind'] ) && 'call' === $in['kind'] ) ? 'call' : 'brief';
	$data = ( isset( $in['data'] ) && is_array( $in['data'] ) ) ? $in['data'] : array();

	$name  = bebrand_line( isset( $data['name'] ) ? $data['name'] : '', 120 );
	$email = sanitize_email( isset( $data['email'] ) && is_scalar( $data['email'] ) ? (string) $data['email'] : '' );
	if ( '' === $name ) {
		bebrand_answer( false, 'Add your name.', 422 );
	}
	if ( ! $email || ! is_email( $email ) ) {
		bebrand_answer( false, 'Add a valid email so we can reply.', 422 );
	}

	$text = isset( $in['text'] ) && is_scalar( $in['text'] ) ? sanitize_textarea_field( (string) $in['text'] ) : '';
	$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 6000 ) : substr( $text, 0, 6000 );
	$tz   = bebrand_line( isset( $in['tz'] ) ? $in['tz'] : '', 60 );

	if ( 'call' === $kind ) {
		$day  = bebrand_line( isset( $data['day'] ) ? $data['day'] : '', 40 );
		$time = bebrand_line( isset( $data['time'] ) ? $data['time'] : '', 40 );
		if ( '' === $day || '' === $time ) {
			bebrand_answer( false, 'Pick a day and a time.', 422 );
		}
		$title   = sprintf( 'Call request: %s, %s at %s', $name, $day, $time );
		$subject = sprintf( 'Call request from %s: %s at %s', $name, $day, $time );
	} else {
		$title   = sprintf( 'Project brief: %s', $name );
		$subject = sprintf( 'New project brief from %s', $name );
	}

	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'bb_request',
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => $text,
			)
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		bebrand_answer( false, 'We could not save your request. Try again in a moment, or email us.', 500 );
	}
	// WordPress encodes "&" and the like in post content, so the text is also kept exactly as sent, for showing and reading.
	update_post_meta( $id, '_bb_text', wp_slash( $text ) );
	update_post_meta( $id, '_bb_kind', $kind );
	update_post_meta( $id, '_bb_name', wp_slash( $name ) );
	update_post_meta( $id, '_bb_email', $email );
	update_post_meta( $id, '_bb_tz', wp_slash( $tz ) );

	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$body  = $text . "\n\n";
	$body .= 'Reply to: ' . $name . ' <' . $email . ">\n";
	if ( $tz ) {
		$body .= 'Their time zone: ' . $tz . "\n";
	}
	$body .= 'Saved in WordPress: ' . admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) . "\n";

	$mailed = wp_mail(
		bebrand_recipient(),
		'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject,
		$body,
		array( 'Reply-To: ' . str_replace( array( '<', '>', '"', ',' ), '', $name ) . ' <' . $email . '>' )
	);
	update_post_meta( $id, '_bb_mailed', $mailed ? 'yes' : 'no' );

	// The request is saved either way, so the visitor is told it was sent even if the email did not go out.
	bebrand_answer( true );
}
add_action( 'wp_ajax_nopriv_bebrand_send', 'bebrand_receive' );
add_action( 'wp_ajax_bebrand_send', 'bebrand_receive' );

/* ------------------------------------------------------------------
 * Requests in the admin
 * ------------------------------------------------------------------ */

add_filter(
	'manage_bb_request_posts_columns',
	function ( $columns ) {
		return array(
			'cb'        => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'     => 'Request',
			'bb_email'  => 'Email',
			'bb_mailed' => 'Emailed to you',
			'date'      => 'Date',
		);
	}
);

// Every request is stored as a private entry; the list says "Received" and leaves out the "Private" label on each row.
add_filter(
	'post_date_column_status',
	function ( $status, $post ) {
		return 'bb_request' === $post->post_type ? 'Received' : $status;
	},
	10,
	2
);
add_filter(
	'display_post_states',
	function ( $states, $post ) {
		return 'bb_request' === $post->post_type ? array() : $states;
	},
	10,
	2
);

add_action(
	'manage_bb_request_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'bb_email' === $column ) {
			$email = (string) get_post_meta( $post_id, '_bb_email', true );
			echo $email ? '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' : '';
		} elseif ( 'bb_mailed' === $column ) {
			echo 'yes' === get_post_meta( $post_id, '_bb_mailed', true ) ? 'Yes' : 'No (saved here only)';
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_bb_request',
	function () {
		add_meta_box(
			'bb_request_body',
			'What they sent',
			function ( $post ) {
				$email = (string) get_post_meta( $post->ID, '_bb_email', true );
				$name  = (string) get_post_meta( $post->ID, '_bb_name', true );
				$tz    = (string) get_post_meta( $post->ID, '_bb_tz', true );
				$text  = (string) get_post_meta( $post->ID, '_bb_text', true );
				echo '<pre style="white-space:pre-wrap;font:14px/1.6 inherit;margin:0 0 14px">' . esc_html( $text ) . '</pre>';
				if ( $email ) {
					echo '<p><strong>Reply to:</strong> ' . esc_html( $name ) . ' &lt;<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>&gt;</p>';
				}
				if ( $tz ) {
					echo '<p><strong>Their time zone:</strong> ' . esc_html( $tz ) . '</p>';
				}
				if ( 'yes' !== get_post_meta( $post->ID, '_bb_mailed', true ) ) {
					echo '<p><em>WordPress could not email this request to you, so it is saved here only. Ask your host about outgoing mail, or add an SMTP plugin.</em></p>';
				}
			},
			'bb_request',
			'normal',
			'high'
		);
	}
);

/* ------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------ */

add_action(
	'admin_init',
	function () {
		register_setting(
			'bebrand_site',
			'bebrand_site_to',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'BeBrand',
			'BeBrand',
			'manage_options',
			'bebrand-site',
			function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				$home = (int) get_option( 'bebrand_home_id' );
				?>
				<div class="wrap">
					<h1>BeBrand</h1>
					<form method="post" action="options.php">
						<?php settings_fields( 'bebrand_site' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">Home page</th>
								<td>
									<?php if ( $home && get_post( $home ) ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $home ) ); ?>">Edit the Home page</a> &nbsp;|&nbsp; <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">View the site</a>
										<p class="description">The website is the page called "Home", one HTML block per section. Headlines, the steps, the comparison table, the forms and the footer are edited there. The service cards, reviews, prices and FAQs come from the theme.</p>
									<?php else : ?>
										<p class="description">The Home page is missing. Switch the theme off and on again to write it afresh.</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bebrand_site_to">Send requests to</label></th>
								<td>
									<input type="email" class="regular-text" id="bebrand_site_to" name="bebrand_site_to" value="<?php echo esc_attr( (string) get_option( 'bebrand_site_to', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
									<p class="description">Project briefs and call requests are emailed here. Leave empty to use the site's admin address. Every request is also saved under <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bb_request' ) ); ?>">Project requests</a>.</p>
								</td>
							</tr>
						</table>
						<?php submit_button(); ?>
					</form>
				</div>
				<?php
			}
		);
	}
);
