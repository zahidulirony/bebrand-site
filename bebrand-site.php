<?php
/**
 * Plugin Name: BeBrand Site
 * Description: Shows the BeBrand website on the home page and receives its project briefs and call requests. Requests are saved under "Project requests" and emailed to you.
 * Version: 1.0.0
 * Author: BeBrand
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: bebrand-site
 */

defined( 'ABSPATH' ) || exit;

define( 'BEBRAND_SITE_VERSION', '1.0.0' );
define( 'BEBRAND_SITE_FILE', __FILE__ );

/* ------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------ */

/** Whether the BeBrand page takes the place of the home page. On unless switched off. */
function bebrand_site_on_home() {
	return '0' !== (string) get_option( 'bebrand_site_home', '1' );
}

/** Where requests are emailed: the address in the settings, or the site's admin address. */
function bebrand_site_recipient() {
	$to = sanitize_email( (string) get_option( 'bebrand_site_to', '' ) );
	return $to ? $to : get_option( 'admin_email' );
}

register_activation_hook(
	__FILE__,
	function () {
		add_option( 'bebrand_site_home', '1' );
		add_option( 'bebrand_site_to', '' );
	}
);

/* ------------------------------------------------------------------
 * The page
 * ------------------------------------------------------------------ */

/**
 * The whole page as HTML. The page is one file (site/page.html) with three marks in it that are filled in here:
 * where its pictures live, where its forms send to, and a few lines for the head.
 */
function bebrand_site_html() {
	$html = file_get_contents( plugin_dir_path( __FILE__ ) . 'site/page.html' );
	if ( false === $html ) {
		return '';
	}

	$head = '<link rel="canonical" href="' . esc_url( home_url( '/' ) ) . '">' . "\n";
	if ( '0' === (string) get_option( 'blog_public' ) ) {
		// "Discourage search engines" is ticked under Settings > Reading.
		$head .= '<meta name="robots" content="noindex,nofollow">' . "\n";
	}
	$icon = get_site_icon_url( 192 );
	if ( $icon ) {
		$head .= '<link rel="icon" href="' . esc_url( $icon ) . '">' . "\n";
	}

	return strtr(
		$html,
		array(
			'{{BB_ASSETS}}' => esc_url( plugins_url( 'site/', __FILE__ ) ),
			'{{BB_API}}'    => wp_json_encode( admin_url( 'admin-ajax.php?action=bebrand_send' ) ),
			'{{BB_HEAD}}'   => $head,
		)
	);
}

add_action(
	'template_redirect',
	function () {
		if ( ! bebrand_site_on_home() ) {
			return;
		}
		if ( ! is_front_page() || is_paged() || is_feed() || is_preview() || is_customize_preview() || is_robots() ) {
			return;
		}
		$html = bebrand_site_html();
		if ( '' === $html ) {
			return; // The page file is missing: leave the theme's home page in place.
		}
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own page file.
		exit;
	},
	0
);

/* ------------------------------------------------------------------
 * Requests: project briefs and call requests
 * ------------------------------------------------------------------ */

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
function bebrand_site_line( $value, $max = 200 ) {
	$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
}

/** Sends the JSON answer and stops. */
function bebrand_site_answer( $ok, $message = '', $status = 200 ) {
	wp_send_json(
		array(
			'ok'      => (bool) $ok,
			'message' => $message,
		),
		$status
	);
}

function bebrand_site_receive() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		bebrand_site_answer( false, 'Send this form from the website.', 405 );
	}

	$raw = file_get_contents( 'php://input' );
	if ( ! is_string( $raw ) || strlen( $raw ) > 20000 ) {
		bebrand_site_answer( false, 'That message is too long. Shorten the notes and try again.', 413 );
	}
	$in = json_decode( $raw, true );
	if ( ! is_array( $in ) ) {
		bebrand_site_answer( false, 'We could not read the form. Reload the page and try again.', 400 );
	}

	// Two quiet checks against bots: a field people never see must be empty, and nobody fills the form in under three seconds.
	// A bot gets the same "sent" answer as a person, so it learns nothing.
	if ( ! empty( $in['website'] ) || ( isset( $in['ms'] ) && (int) $in['ms'] < 3000 ) ) {
		bebrand_site_answer( true );
	}

	// At most 20 requests an hour from one address.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$key = 'bb_rate_' . md5( $ip . wp_salt() );
	$n   = (int) get_transient( $key );
	if ( $n >= 20 ) {
		bebrand_site_answer( false, 'Too many requests from this connection. Try again in an hour, or email us.', 429 );
	}

	$kind = ( isset( $in['kind'] ) && 'call' === $in['kind'] ) ? 'call' : 'brief';
	$data = ( isset( $in['data'] ) && is_array( $in['data'] ) ) ? $in['data'] : array();

	$name  = bebrand_site_line( isset( $data['name'] ) ? $data['name'] : '', 120 );
	$email = sanitize_email( isset( $data['email'] ) && is_scalar( $data['email'] ) ? (string) $data['email'] : '' );
	if ( '' === $name ) {
		bebrand_site_answer( false, 'Add your name.', 422 );
	}
	if ( ! $email || ! is_email( $email ) ) {
		bebrand_site_answer( false, 'Add a valid email so we can reply.', 422 );
	}

	$text = isset( $in['text'] ) && is_scalar( $in['text'] ) ? sanitize_textarea_field( (string) $in['text'] ) : '';
	$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 6000 ) : substr( $text, 0, 6000 );
	$tz   = bebrand_site_line( isset( $in['tz'] ) ? $in['tz'] : '', 60 );

	if ( 'call' === $kind ) {
		$day   = bebrand_site_line( isset( $data['day'] ) ? $data['day'] : '', 40 );
		$time  = bebrand_site_line( isset( $data['time'] ) ? $data['time'] : '', 40 );
		if ( '' === $day || '' === $time ) {
			bebrand_site_answer( false, 'Pick a day and a time.', 422 );
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
		bebrand_site_answer( false, 'We could not save your request. Try again in a moment, or email us.', 500 );
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
		bebrand_site_recipient(),
		'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject,
		$body,
		array( 'Reply-To: ' . str_replace( array( '<', '>', '"', ',' ), '', $name ) . ' <' . $email . '>' )
	);
	update_post_meta( $id, '_bb_mailed', $mailed ? 'yes' : 'no' );

	// The request is saved either way, so the visitor is told it was sent even if the email did not go out.
	bebrand_site_answer( true );
}
add_action( 'wp_ajax_nopriv_bebrand_send', 'bebrand_site_receive' );
add_action( 'wp_ajax_bebrand_send', 'bebrand_site_receive' );

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
 * Settings page
 * ------------------------------------------------------------------ */

add_action(
	'admin_init',
	function () {
		register_setting(
			'bebrand_site',
			'bebrand_site_home',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return $value ? '1' : '0';
				},
				'default'           => '1',
			)
		);
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
			'BeBrand Site',
			'BeBrand Site',
			'manage_options',
			'bebrand-site',
			function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				?>
				<div class="wrap">
					<h1>BeBrand Site</h1>
					<form method="post" action="options.php">
						<?php settings_fields( 'bebrand_site' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">Home page</th>
								<td>
									<input type="hidden" name="bebrand_site_home" value="0">
									<label><input type="checkbox" name="bebrand_site_home" value="1" <?php checked( bebrand_site_on_home() ); ?>> Show the BeBrand website on the home page</label>
									<p class="description">Untick to get your theme's home page back. <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">View the home page</a></p>
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

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=bebrand-site' ) ) . '">Settings</a>' );
		return $links;
	}
);
