<?php
/**
 * The "BeBrand site" element for Elementor: the whole BeBrand home page, with every text as a field in the panel.
 * Loaded only when Elementor asks for elements. The fields are listed in site/fields.json, which the build writes from the page.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class BeBrand_Site_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return BEBRAND_SITE_WIDGET;
	}

	public function get_title() {
		return 'BeBrand site';
	}

	public function get_icon() {
		return 'eicon-site-identity';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'bebrand', 'home', 'site', 'landing' );
	}

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/** One panel field for one field of the page. */
	private function bb_args( $field ) {
		$cm   = '\Elementor\Controls_Manager';
		$args = array(
			'label'       => $field['label'],
			'label_block' => true,
			'default'     => $field['default'],
		);
		switch ( $field['kind'] ) {
			case 'html':
				$args['type']        = constant( $cm . '::TEXTAREA' );
				$args['rows']        = 3;
				$args['description'] = 'The tags (&lt;br&gt;, &lt;em&gt;, &amp;nbsp;) shape the line: keep them around your words.';
				break;
			case 'lines':
				$args['type'] = constant( $cm . '::TEXTAREA' );
				$args['rows'] = 6;
				break;
			case 'switch':
				$args['type']         = constant( $cm . '::SWITCHER' );
				$args['return_value'] = 'yes';
				$args['label_block']  = false;
				break;
			case 'choice':
				$args['type']        = constant( $cm . '::SELECT' );
				$args['options']     = $field['options'];
				$args['label_block'] = false;
				break;
			case 'colour':
				$args['type']        = constant( $cm . '::COLOR' );
				$args['label_block'] = false;
				break;
			case 'media':
				$args['type']    = constant( $cm . '::MEDIA' );
				$args['default'] = array( 'url' => '' );
				break;
			case 'gallery':
				$args['type']    = constant( $cm . '::GALLERY' );
				$args['default'] = array();
				break;
			default:
				$args['type'] = constant( $cm . '::TEXT' );
		}
		return $args;
	}

	protected function register_controls() {
		$cm    = '\Elementor\Controls_Manager';
		$first = true;
		foreach ( bebrand_site_sections() as $section ) {
			$this->start_controls_section(
				'bb_' . $section['key'],
				array(
					'label' => $section['label'],
					'tab'   => constant( $cm . '::TAB_CONTENT' ),
				)
			);
			if ( $first ) {
				$first = false;
				$this->add_control(
					'bb_about',
					array(
						'type'            => constant( $cm . '::RAW_HTML' ),
						'raw'             => 'This element is the whole BeBrand page. Open a section below to change its words, prices, questions, reviews and photos. The design, layout and animation stay as built.',
						'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
					)
				);
			}
			foreach ( $section['fields'] as $field ) {
				if ( 'repeater' === $field['kind'] ) {
					$rows = new \Elementor\Repeater();
					foreach ( $field['sub'] as $sub ) {
						$sub['default'] = isset( $sub['default'] ) ? $sub['default'] : '';
						$rows->add_control( $sub['key'], $this->bb_args( $sub ) );
					}
					$this->add_control(
						$field['key'],
						array(
							'label'       => $field['label'],
							'type'        => constant( $cm . '::REPEATER' ),
							'fields'      => $rows->get_controls(),
							'default'     => $field['default'],
							'title_field' => '{{{ ' . $field['title'] . ' }}}',
						)
					);
					continue;
				}
				$this->add_control( $field['key'], $this->bb_args( $field ) );
			}
			$this->end_controls_section();
		}
	}

	protected function render() {
		echo bebrand_site_body( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field by field as the page is filled in.
	}
}
