<?php //phpcs:ignore
/**
 * VK_Font_Awesome_Versions
 *
 * @package vektor-inc/font-awesome-versions
 * @license GPL-2.0+
 *
 * @version 0.7.0
 */

namespace VektorInc\VK_Font_Awesome_Versions;

/**
 * VkFontAwesomeVersions
 */
class VkFontAwesomeVersions {

	/**
	 * Get default options for Font Awesome settings.
	 *
	 * @return array Default option values.
	 */
	public static function get_option_default() {
		$default = array(
			'version'       => '7_WebFonts_CSS',
			'compatibility' => array(
				'v4' => false,
				'v5' => false,
			),
		);
		return apply_filters( 'vk_font_awesome_option_default', $default );
	}

	/**
	 * 直接 new VkFontAwesomeVersions() している場所がありえるので fallback.
	 */
	public function __construct() {
		self::init();
	}

	/**
	 * Initialise
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * テキストドメイン
		 */
		if ( did_action( 'init' ) ) {
			self::load_text_domain();
		} else {
			add_action( 'init', array( __CLASS__, 'load_text_domain' ) );
		}

		/**
		 * Reason of Using through the after_setup_theme is
		 * to be able to change the action hook point of css load from theme..
		 */
		add_action( 'after_setup_theme', array( __CLASS__, 'load_css_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'old_notice' ) );

		add_action( 'customize_register', array( __CLASS__, 'customize_register' ) );

		/* admin init だと use_block_editor_for_post が効かない */
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_admin_font_awesome' ) );

		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'load_gutenberg_font_awesome' ) );
		add_action( 'wp_head', array( __CLASS__, 'dynamic_css' ), 3 );
		add_filter( 'body_class', array( __CLASS__, 'add_body_class_fa_version' ) );
	}

	/**
	 * Load plugin text domain files.
	 *
	 * @return void
	 */
	public static function load_text_domain() {
		// We're not using load_plugin_textdomain() or its siblings because figuring out where
		// the library is located (plugin, mu-plugin, theme, custom wp-content paths) is messy.
		$domain = 'font-awesome-versions';
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WP core filter.
		$locale = apply_filters(
			'plugin_locale',
			( is_admin() && function_exists( 'get_user_locale' ) ) ? get_user_locale() : get_locale(),
			$domain
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$mo_file = $domain . '-' . $locale . '.mo';
		$path    = realpath( __DIR__ . '/languages' );
		if ( $path && file_exists( $path ) ) {
			load_textdomain( $domain, $path . '/' . $mo_file );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @since 0.3.0
	 * @param string $path PHPUnit テスト用.
	 * @return string $uri.
	 */
	public static function get_directory_uri( $path = '' ) {

		$uri = '';

		if ( ! empty( $path ) ) {
			$path = wp_normalize_path( $path );
		} else {
			// このファイルのパス.
			$path = wp_normalize_path( __DIR__ );
		}

		// ファイルのパスの wp-content より前の部分を site_url() に置換する
		// ABSPATH の部分を site_url() に置換したいところだが、ABSPATHは WordPress.com で /wordpress/core/5.9.3/ のような返し方をされて、一般的なサーバーのパスとは異なるので、置換などには使用しない.
		preg_match( '/(.*)(wp-content.*)/', $path, $matches, PREG_OFFSET_CAPTURE );
		if ( ! empty( $matches[2][0] ) ) {
			$uri = site_url( '/' ) . $matches[2][0] . '/';
		}

		return $uri;
	}

	/**
	 * アイコンの class 名だけ保存されている場合も i タグに変換して出力する
	 *
	 * @param string $option : saved value.
	 * @param string $additional_class : i タグに追加する Font Awesome 以外のクラス名.
	 *
	 * @return string $icon_html : icon tag
	 */
	public static function get_icon_tag( $option = '', $additional_class = '' ) {
		if ( empty( $option ) ) {
			return;
		}
		if (
			false !== strpos( $option, '<i' ) &&
			false !== strpos( $option, '</i>' )
		) {
			$icon_html = $option;
			if ( $additional_class ) {
				preg_match( '/(<i class=\")(.*)(\"><\/i>)/', $option, $matches );
				if ( ! empty( $matches[2] ) ) {
					$icon_html = '<i class="' . esc_attr( $matches[2] ) . ' ' . esc_attr( $additional_class ) . '"></i>';
				}
			}
		} else {

			// 4.7 fall back.
			$print_fa = '';
			$print_fa = self::print_fa();

			$class = $print_fa . $option;

			// Font Awesome 以外のクラス名がある場合.
			if ( $additional_class ) {
				$class .= ' ' . $additional_class;
			}

			$icon_html = '<i class="' . esc_attr( $class ) . '"></i>';
		}
		return $icon_html;
	}

	/**
	 * Load Font Awesome Action
	 *
	 * @return void
	 */
	public static function load_css_action() {
		$hook_point = apply_filters( 'vkfa_enqueue_point', 'wp_enqueue_scripts' );
		add_action( $hook_point, array( __CLASS__, 'load_font_awesome' ) );
	}

	/**
	 * Supported Font Awesome versions and asset URLs.
	 *
	 * @return array Versions meta.
	 */
	public static function versions() {

		$font_awesome_directory_uri = self::get_directory_uri() . 'font-awesome/';

		$versions = array(
			'7_WebFonts_CSS' => array(
				'label'                => '7 Web Fonts with CSS',
				'version'              => '7.1.0',
				'type'                 => 'web-fonts-with-css',
				'url_css'              => $font_awesome_directory_uri . 'css/all.min.css',
				'url_js'               => '',
				'url_v4-shims_css'     => $font_awesome_directory_uri . 'css/v4-shims.min.css', // Font Awesome 4.7 用の shims 定義ファイル.
				'url_v4-shims_js'      => '',
				'url_v4-font-face_css' => $font_awesome_directory_uri . 'css/v4-font-face.min.css', // Font Awesome 4.7 用の font-face 定義ファイル.
				'url_v5-font-face_css' => $font_awesome_directory_uri . 'css/v5-font-face.min.css', // Font Awesome 5.0 用の font-face 定義ファイル.

			),
			'7_SVG_JS'       => array(
				'label'                => '7 SVG with JS ( ' . __( 'Not recommended', 'font-awesome-versions' ) . ' )',
				'version'              => '7.1.0',
				'type'                 => 'svg-with-js',
				/* [ Notice ] use editor css*/
				'url_css'              => $font_awesome_directory_uri . 'css/all.min.css',
				'url_js'               => $font_awesome_directory_uri . 'js/all.min.js',
				'url_v4-shims_css'     => $font_awesome_directory_uri . 'css/v4-shims.min.css', // Font Awesome 4.7 用の shims 定義ファイル.
				'url_v4-shims_js'      => $font_awesome_directory_uri . 'js/v4-shims.min.js',  // Font Awesome 4.7 用の shims 定義ファイル.
				'url_v4-font-face_css' => $font_awesome_directory_uri . 'css/v4-font-face.min.css', // Font Awesome 4.7 用の font-face 定義ファイル.
				'url_v5-font-face_css' => $font_awesome_directory_uri . 'css/v5-font-face.min.css', // Font Awesome 5.0 用の font-face 定義ファイル.
			),
		);
		return $versions;
	}

	/**
	 * Compatibility options for older FA class names.
	 *
	 * @return array Compatibility configuration.
	 */
	public static function compatibilities() {
		$compatibilities = array(
			'v4' => array(
				'label' => __( 'Compatibility mode 4', 'font-awesome-versions' ),
				'note'  => __( 'Enables support for Font Awesome 4 class names.', 'font-awesome-versions' ),
			),
			'v5' => array(
				'label' => __( 'Compatibility mode 5', 'font-awesome-versions' ),
				'note'  => __( 'Enables support for Font Awesome 5 class names.', 'font-awesome-versions' ),
			),
		);
		return $compatibilities;
	}


	/**
	 * Get current Font Awesome option.
	 *
	 * @return array Current option (version and compatibility flags).
	 */
	public static function get_option_fa() {

		// 基本の保存値（実際に読み込むアセットのバージョン）
		$version = get_option( 'vk_font_awesome_version' );
		$options = get_option( 'vk_font_awesome_options', self::get_option_default() );

		// 古い保存値が残っている場合のマイグレーション対応
		if ( ! empty( $version ) && empty( $options['version'] ) ) {
			$options['version'] = $version;
			delete_option( 'vk_font_awesome_version' );
		}

		// 4系は7系へ移行しつつ4系互換モードを有効化
		if ( '4.7' === $options['version'] ) {
			$options['version']             = '7_WebFonts_CSS';
			$options['compatibility']['v4'] = true;
		}

		// 5系は7系へ移行しつつ5系互換モードを有効化
		if ( '5.0_WebFonts_CSS' === $options['version'] || '5_WebFonts_CSS' === $options['version'] ) {
			$options['version']             = '7_WebFonts_CSS';
			$options['compatibility']['v5'] = true;
		} elseif ( '5.0_SVG_JS' === $options['version'] || '5_SVG_JS' === $options['version'] ) {
			$options['version']             = '7_SVG_JS';
			$options['compatibility']['v5'] = true;
		}

		// ６系は７系へ移行
		if ( '6_WebFonts_CSS' === $options['version'] ) {
			$options['version'] = '7_WebFonts_CSS';
		} elseif ( '6_SVG_JS' === $options['version'] ) {
			$options['version'] = '7_SVG_JS';
		}

		// 保存値が存在しない場合はデフォルトをセット
		update_option( 'vk_font_awesome_options', $options );

		return $options;
	}

	/**
	 * Get current asset info for the selected FA version.
	 *
	 * @return array Asset info for the active version.
	 */
	public static function current_info() {
		// アセット読み込み用の実バージョンを算出
		$versions = self::versions();
		$option   = get_option( 'vk_font_awesome_options', self::get_option_default() );

		if ( '7_WebFonts_CSS' === $option['version'] ) {
			$option = '7_WebFonts_CSS';
		} elseif ( '7_SVG_JS' === $option['version'] ) {
			$option = '7_SVG_JS';
		}

		// 存在しないキーが指定されても7系CSSにフォールバック
		if ( empty( $versions[ $option ] ) ) {
			$options = self::get_option_default();
			$option  = $options['version'];
		}

		return $versions[ $option ];
	}

	/**
	 * Display icon list link
	 *
	 * @param string $type = 'class' : クラス名のみ / $type = 'html' : i タグ表示.
	 * @param string $example_class_array 例として表示するクラス名のバージョンごとの配列.
	 * @return string $ex_and_link
	 */
	public static function ex_and_link( $type = '', $example_class_array = array() ) {
		$current_option = self::get_option_fa();

		if ( '7_WebFonts_CSS' === $current_option['version'] || '7_SVG_JS' === $current_option['version'] ) {
			$version = '7';
			$link    = 'https://fontawesome.com/search?ic=free-collection';
			if ( ! empty( $example_class_array ['v7'] ) ) {
				$icon_class = esc_attr( $example_class_array['v7'] );
			} else {
				$icon_class = 'fa-regular fa-file-lines';
			}
		}

		$ex_and_link  = '<div style="margin-top:5px"><strong>Font Awesome ' . $version . '</strong></div>';
		$ex_and_link .= __( 'Ex ) ', 'font-awesome-versions' );
		if ( 'class' === $type ) {
			$ex_and_link .= $icon_class;
		} else {
			$ex_and_link .= esc_html( '<i class="' . $icon_class . '"></i>' );
		}
		$ex_and_link .= '<br>[ -> <a href="' . $link . '" target="_blank" rel="noreferrer">' . __( 'Font Awesome Icon list', 'font-awesome-versions' ) . '</a> ]';

		return wp_kses_post( $ex_and_link );
	}

	/**
	 * When Font Awesome 4.7 is selected, return 'fa ' prefix.
	 *
	 * @return string Prefix for FA 4.7 classes.
	 */
	public static function print_fa() {
		$fa             = '';
		$current_option = self::get_option_fa();
		if ( '4.7' === $current_option['version'] ) {
			$fa = 'fa ';
		}
		return $fa;
	}

	/**
	 * Enqueue Font Awesome assets based on current setting.
	 *
	 * @return void
	 */
	public static function load_font_awesome() {
		$current = self::current_info();
		$options = self::get_option_fa();
		if ( 'svg-with-js' === $current['type'] ) {
				wp_enqueue_script( 'vk-font-awesome-js', $current['url_js'], array(), $current['version'], false );
			if ( ! empty( $options['compatibility']['v4'] ) ) {
					wp_enqueue_script( 'vk-font-awesome-v4-shims-js', $current['url_v4-shims_js'], array( 'vk-font-awesome-js' ), $current['version'], false );
			}
			// [ Danger ] This script now causes important errors
			// wp_add_inline_script( 'font-awesome-js', 'FontAwesomeConfig = { searchPseudoElements: true };', 'before' );
		} else {
			wp_enqueue_style( 'vk-font-awesome', $current['url_css'], array(), $current['version'] );
			if ( ! empty( $options['compatibility']['v4'] ) ) {
				wp_enqueue_style( 'vk-font-awesome-v4-shims', $current['url_v4-shims_css'], array( 'vk-font-awesome' ), $current['version'] );
				wp_enqueue_style( 'vk-font-awesome-v4-font-face', $current['url_v4-font-face_css'], array( 'vk-font-awesome' ), $current['version'] );
			}
			if ( ! empty( $options['compatibility']['v5'] ) ) {
				wp_enqueue_style( 'vk-font-awesome-v5-font-face', $current['url_v5-font-face_css'], array( 'vk-font-awesome' ), $current['version'] );
			}
		}
	}

	/**
	 * Load Font Awesome for Classic editor only.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public static function load_admin_font_awesome( $post ) {
		$current = self::current_info();
		$options = self::get_option_fa();
		// ブロックエディタでこれがあるとコンソールでエラー吐かれるのでclassicエディタのときだけ読み込み.
		if ( ! function_exists( 'use_block_editor_for_post' ) || ! use_block_editor_for_post( $post ) ) {
			add_editor_style( $current['url_css'] );
			if ( ! empty( $options['compatibility']['v4'] ) ) {
				add_editor_style( $current['url_v4-shims_css'] );
				add_editor_style( $current['url_v4-font-face_css'] );
			}
			if ( ! empty( $options['compatibility']['v5'] ) ) {
				add_editor_style( $current['url_v5-font-face_css'] );
			}
		}
	}

	/**
	 * Load Font Awesome CSS for block editor.
	 *
	 * @return void
	 */
	public static function load_gutenberg_font_awesome() {
		$current_info = self::current_info();
		$options = self::get_option_fa();
		wp_enqueue_style( 'gutenberg-font-awesome', $current_info['url_css'], array(), $current_info['version'] );
		if ( ! empty( $options['compatibility']['v4'] ) ) {
			wp_enqueue_style( 'gutenberg-font-awesome-v4-shims', $current_info['url_v4-shims_css'], array( 'gutenberg-font-awesome' ), $current_info['version'] );
			wp_enqueue_style( 'gutenberg-font-awesome-v4-font-face', $current_info['url_v4-font-face_css'], array( 'gutenberg-font-awesome' ), $current_info['version'] );
		}
		if ( ! empty( $options['compatibility']['v5'] ) ) {
			wp_enqueue_style( 'gutenberg-font-awesome-v5-font-face', $current_info['url_v5-font-face_css'], array( 'gutenberg-font-awesome' ), $current_info['version'] );
		}
	}

	/**
	 * Add body class
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes with FA version slug.
	 */
	public static function add_body_class_fa_version( $classes ) {
		$current_option = self::get_option_fa();
		if ( '7_WebFonts_CSS' === $current_option['version'] ) {
			$classes[] = 'fa_v7_css';
		} elseif ( '7_SVG_JS' === $current_option['version'] ) {
			$classes[] = 'fa_v7_svg';
		}

		return $classes;
	}

	/**
	 * Output dynamic CSS according to Font Awesome versions.
	 *
	 * @return void
	 */
	public static function dynamic_css() {
		$current     = self::get_option_fa();
		$dynamic_css = '';
		if ( '7_WebFonts_CSS' === $current['version'] ) {
			$dynamic_css = '.tagcloud a:before { font-family: "Font Awesome 7 Free";content: "\f02b";font-weight: bold; }';
		} elseif ( '7_SVG_JS' === $current['version'] ) {
			$dynamic_css = '.tagcloud a:before { content:"" }';
		}
		// delete before after space.
		$dynamic_css = trim( $dynamic_css );
		// convert tab and br to space.
		$dynamic_css = preg_replace( '/[\n\r\t]/', '', $dynamic_css );
		// Change multiple spaces to single space.
		$dynamic_css = preg_replace( '/\s(?=\s)/', '', $dynamic_css );

		global $vkfav_set_enqueue_handle_style;
		wp_add_inline_style( $vkfav_set_enqueue_handle_style, $dynamic_css );
	}

	/**
	 * 同じ絵柄のアイコンをバージョンによって出し分ける場合に切り替える.
	 *
	 * @param string $class_v4 v4 の場合のアイコン.
	 * @param string $class_v5 v5 の場合のアイコン.
	 * @param string $class_v6 v6 の場合のアイコン.
	 * @param string $class_v7 v7 の場合のアイコン.
	 * @return string 選択されたバージョンに応じたクラス名.
	 */
	public static function class_switch( $class_v4 = '', $class_v5 = '', $class_v6 = '', $class_v7 = '' ) {
		$current_option = self::get_option_fa();
		if ( '7_WebFonts_CSS' === $current_option['version'] || '7_SVG_JS' === $current_option['version'] ) {
			return $class_v7;
		} elseif ( '6_WebFonts_CSS' === $current_option['version'] || '6_SVG_JS' === $current_option['version'] ) {
			return $class_v6;
		} else {
			return $class_v4;
		}
	}

	/**
	 * Show admin notice for old FA versions.
	 *
	 * @return void
	 */
	public static function old_notice() {
		$old_notice     = '';
		$current_option = self::get_option_fa();
		if ( '4.7' === $current_option['version'] ) {
			$old_notice .= '<div class="error">';
			$old_notice .= '<p>' . __( 'An older version of Font Awesome is selected. This version will be removed by August 2022.', 'font-awesome-versions' ) . '</p>';
			$old_notice .= '<p>' . __( 'Please change the version of FontAwesome on the Appearance > Customize screen.', 'font-awesome-versions' ) . '</p>';
			$old_notice .= '<p>' . __( '* It is necessary to reset the icon font in the place where Font Awesome is used.', 'font-awesome-versions' ) . '</p>';
			$old_notice .= '</div>';
		}
		echo wp_kses_post( $old_notice );
	}

	/**
	 * Customize_register
	 *
	 * @param object $wp_customize : customize object.
	 * @return void
	 */
	public static function customize_register( $wp_customize ) {

		global $vkfav_customize_panel_prefix;
		global $vkfav_customize_panel_priority;
		if ( ! $vkfav_customize_panel_priority ) {
			$vkfav_customize_panel_priority = 450;
		}

		$versions = self::versions();
		foreach ( $versions as $key => $value ) {
			$choices[ $key ] = $value['label'];
		}

		$compatibilities = self::compatibilities();

		$wp_customize->add_section(
			'VK Font Awesome',
			array(
				'title'    => $vkfav_customize_panel_prefix . __( 'Font Awesome', 'font-awesome-versions' ),
				'priority' => $vkfav_customize_panel_priority,
			)
		);

		$wp_customize->add_setting(
			'vk_font_awesome_options[version]',
			array(
				'default'           => '7_WebFonts_CSS',
				'type'              => 'option',
				'capability'        => 'edit_theme_options',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'vk_font_awesome_options[version]',
			array(
				'label'       => __( 'Font Awesome Version', 'font-awesome-versions' ),
				'section'     => 'VK Font Awesome',
				'settings'    => 'vk_font_awesome_options[version]',
				'type'        => 'select',
				'priority'    => '',
				'choices'     => $choices,
			)
		);

		foreach ( $compatibilities as $key => $value ) {
			$wp_customize->add_setting(
				'vk_font_awesome_options[compatibility][' . $key . ']',
				array(
					'default'           => false,
					'type'              => 'option',
					'capability'        => 'edit_theme_options',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			$wp_customize->add_control(
				'vk_font_awesome_options[compatibility][' . $key . ']',
				array(
					'label'       => $value['label'],
					'section'     => 'VK Font Awesome',
					'settings'    => 'vk_font_awesome_options[compatibility][' . $key . ']',
					'description' => $value['note'],
					'type'        => 'checkbox',
					'priority'    => '',
				)
			);
		}
	}
}
