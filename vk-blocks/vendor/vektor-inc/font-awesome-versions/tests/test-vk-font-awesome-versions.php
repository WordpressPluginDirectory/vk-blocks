<?php
use VektorInc\VK_Font_Awesome_Versions\VkFontAwesomeVersions;

class VkFontAwesomeVersionsTest extends WP_UnitTestCase {

	/**
	 * Test get_icon_tag() method
	 *
	 * @return void
	 */
	function test_get_icon_tag() {

		$tests = array(
			array(
				'option_fa_version' => '7_WebFonts_CSS',
				'saved_value'       => 'far fa-file-alt',
				'correct'           => '<i class="far fa-file-alt"></i>',
			),
			array(
				'option_fa_version' => '7_WebFonts_CSS',
				'saved_value'       => '<i class="far fa-file-alt"></i>',
				'correct'           => '<i class="far fa-file-alt"></i>',
			),
			array(
				'option_fa_version' => '7_WebFonts_CSS',
				'saved_value'       => 'far fa-file-alt',
				'additional_class'  => 'test-class',
				'correct'           => '<i class="far fa-file-alt test-class"></i>',
			),
			array(
				'option_fa_version' => '7_WebFonts_CSS',
				'saved_value'       => '<i class="far fa-file-alt"></i>',
				'additional_class'  => 'test-class',
				'correct'           => '<i class="far fa-file-alt test-class"></i>',
			),
		);

		foreach ( $tests as $key => $value ) {
			$options = array(
				'version'       => $value['option_fa_version'],
				'compatibility' => array(
					'v4' => false,
					'v5' => false,
				),
			);
			update_option( 'vk_font_awesome_options', $options );
			if ( ! empty( $value['additional_class'] ) ) {
				$return = VkFontAwesomeVersions::get_icon_tag( $value['saved_value'], $value['additional_class'] );
			} else {
				$return = VkFontAwesomeVersions::get_icon_tag( $value['saved_value'] );
			}
			$this->assertEquals( $value['correct'], $return );
		}
	}

	function test_get_directory_uri() {
		$tests = array(
			array(
				'path'    => '/var/www/html/wp-content/themes/lightning-pro/vendor/vektor-inc/font-awesome-versions/src',
				'correct' => site_url( '/' ) . 'wp-content/themes/lightning-pro/vendor/vektor-inc/font-awesome-versions/src/',
			),
		);
		foreach ( $tests as $key => $value ) {
			$return = VkFontAwesomeVersions::get_directory_uri( $value['path'] );
			$this->assertEquals( $value['correct'], $return );
		}
	}

	function test_get_option_fa() {
		$tests = array(
			array(
				'option_fa_version' => '4.7',
				'correct'           => array(
					'version'       => '7_WebFonts_CSS',
					'compatibility' => array(
						'v4' => true,
						'v5' => false,
					),
				),
			),
			array(
				'option_fa_version' => '5.0_WebFonts_CSS',
				'correct'           => array(
					'version'       => '7_WebFonts_CSS',
					'compatibility' => array(
						'v4' => false,
						'v5' => true,
					),
				),
			),
			array(
				'option_fa_version' => '5.0_SVG_JS',
				'correct'           => array(
					'version'       => '7_SVG_JS',
					'compatibility' => array(
						'v4' => false,
						'v5' => true,
					),
				),
			),
			array(
				'option_fa_version' => '5_WebFonts_CSS',
				'correct'           => array(
					'version'       => '7_WebFonts_CSS',
					'compatibility' => array(
						'v4' => false,
						'v5' => true,
					),
				),
			),
			array(
				'option_fa_version' => '5_SVG_JS',
				'correct'           => array(
					'version'       => '7_SVG_JS',
					'compatibility' => array(
						'v4' => false,
						'v5' => true,
					),
				),
			),
			array(
				'option_fa_version' => '6_WebFonts_CSS',
				'correct'           => array(
					'version'       => '7_WebFonts_CSS',
					'compatibility' => array(
						'v4' => false,
						'v5' => false,
					),
				),
			),
			array(
				'option_fa_version' => '6_SVG_JS',
				'correct'           => array(
					'version'       => '7_SVG_JS',
					'compatibility' => array(
						'v4' => false,
						'v5' => false,
					),
				),
			),
		);

		foreach ( $tests as $key => $value ) {
			$options = array(
				'version'       => $value['option_fa_version'],
				'compatibility' => array(
					'v4' => false,
					'v5' => false,
				),
			);
			update_option( 'vk_font_awesome_options', $options );
			$return = VkFontAwesomeVersions::get_option_fa();
			$this->assertEquals( $value['correct'], $return );
		}
	}
}
