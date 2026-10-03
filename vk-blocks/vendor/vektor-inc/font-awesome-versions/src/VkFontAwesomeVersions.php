<?php //phpcs:ignore
/**
 * VK_Font_Awesome_Versions
 *
 * @package vektor-inc/font-awesome-versions
 * @license GPL-2.0+
 *
 * @version 0.7.6
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

		add_action( 'enqueue_block_assets', array( __CLASS__, 'load_gutenberg_font_awesome' ) );
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
	 * AWS Bitnami 等、シンボリックリンクで WordPress が配置された環境では、
	 * __DIR__（PHP。シンボリックリンクを辿って実体のパスを返す）と
	 * WP_PLUGIN_DIR 等の WordPress の定数（シンボリックリンクを辿らない文字列）が食い違い、
	 * 単純な前方一致では基準ディレクトリのどれにも一致しないことがある（issue #56）。
	 * そのため、まず従来どおりの前方一致を試したうえで、一致しない場合のみ後続の段階で解決を試みる。
	 * 通常構成（シンボリックリンク無し）では最初の一致で確定するため後続の段階は一切実行されず、
	 * 挙動・戻り値ともに従来（master）から変化しない。
	 * 0. まず変換無しでそのまま突き合わせる（従来と完全に同一の経路）。
	 * 1. 0 で一致しなければ、WordPress 本体の対応表（$wp_plugin_paths）による変換を試す。
	 * 2. 1 でも一致しなければ、基準ディレクトリ側を realpath() で実体パスへ変換して再度突き合わせる。
	 * 3. それでも解決できなければ、空文字（ドメイン直結の壊れた URL の原因になる）を返さず、
	 *    content_url() を最後の手段として返す（詳細は各処理のコメントを参照）。
	 *
	 * @since 0.3.0
	 * @param string $path PHPUnit テスト用.
	 * @return string $uri.
	 */
	public static function get_directory_uri( $path = '' ) {

		if ( ! empty( $path ) ) {
			$path = wp_normalize_path( $path );
		} else {
			// このファイルのパス.
			$path = wp_normalize_path( __DIR__ );
		}

		// WP_PLUGIN_DIR / WPMU_PLUGIN_DIR / テーマルート / WP_CONTENT_DIR は
		// それぞれ独立にカスタマイズ可能なため、より具体的なディレクトリから順にマッチさせて URL を生成する。
		$directories = array(
			array(
				'dir' => wp_normalize_path( WP_PLUGIN_DIR ),
				'url' => plugins_url(),
			),
			array(
				'dir' => wp_normalize_path( WPMU_PLUGIN_DIR ),
				'url' => WPMU_PLUGIN_URL,
			),
			array(
				'dir' => wp_normalize_path( get_theme_root() ),
				'url' => get_theme_root_uri(),
			),
			array(
				'dir' => wp_normalize_path( WP_CONTENT_DIR ),
				'url' => content_url(),
			),
		);

		// (0) まず従来どおり、変換無しでそのまま突き合わせる（master と完全に同一の経路。通常構成はここで確定する）.
		$uri = self::match_directory_uri( $path, $directories );

		// (1) (0) で一致しなければ、WordPress 本体が持つシンボリックリンク対応表
		// （$wp_plugin_paths。plugin_basename が内部で使っているもの）による変換を試す.
		if ( '' === $uri ) {
			$uri = self::match_directory_uri( self::resolve_symlinked_plugin_path( $path ), $directories );
		}

		// (2) (1) でも一致しなければ、基準ディレクトリ側を realpath() で実体パスへ解決してから、
		// 変換前の $path（__DIR__ 相当。実体パスのまま）と改めて突き合わせる.
		if ( '' === $uri ) {
			$uri = self::match_directory_uri( $path, self::realpath_directories( $directories ) );
		}

		if ( '' !== $uri ) {
			return $uri;
		}

		// (3) (0)(1)(2) のいずれでも解決できなかったときの最後の手段.
		// 空文字を返すと、呼び出し元 versions() で 'font-awesome/' という相対パスになり、
		// wp_enqueue_style() がサイト URL（末尾スラッシュ無し）を前置してドメインと直結した壊れた URL になる（issue #56）。
		// content_url() はファイルシステムとの突き合わせを行わない純粋な URL 生成関数のため、
		// このケースでも安全にサイト内の絶対 URL を返せる。実際に Font Awesome が置かれたディレクトリと
		// 一致しない可能性はある（読み込むファイルが 404 になりうる）が、ドメイン直結の壊れた URL は避けられる。
		return trailingslashit( content_url() );
	}

	/**
	 * シンボリックリンク対応の変換.
	 *
	 * WordPress 本体が持つグローバル変数 $wp_plugin_paths（論理パス（定数ベース）→実体パス（realpath）の
	 * 対応表。plugin_basename が内部で使っているもの）を使って、実体パス表記の $path を
	 * 論理パス（定数ベース）表記へ変換する。対応表に一致するエントリが無ければ $path をそのまま返す。
	 *
	 * この対応表を直接参照し plugin_basename 自体は呼び出していない。plugin_basename は
	 * WP_PLUGIN_DIR / WPMU_PLUGIN_DIR からの相対パス（basename）へ変換してしまうため、
	 * テーマ配下・wp-content 直下など他の基準ディレクトリの判定に使い回せなくなるためである。
	 * 対応表の構造・突き合わせ方（値が長いものを優先する arsort）は plugin_basename の実装に合わせている。
	 *
	 * このライブラリの外から呼ぶ用途を想定していないため private static とする
	 * （Composer パッケージとして配布しており、public にすると破壊的変更なしに変更できなくなるため）。
	 * テストからは ReflectionMethod 経由で呼び出す.
	 *
	 * @since 0.7.6
	 * @param string $path 変換対象のパス（wp_normalize_path 済み）.
	 * @return string 変換後のパス（対応表に一致しなければ $path のまま）.
	 */
	private static function resolve_symlinked_plugin_path( $path ) {
		global $wp_plugin_paths;

		if ( empty( $wp_plugin_paths ) || ! is_array( $wp_plugin_paths ) ) {
			return $path;
		}

		// 値（実体パス）が長いものから優先的に評価する。plugin_basename() と同じ arsort().
		$plugin_paths = $wp_plugin_paths;
		arsort( $plugin_paths );

		foreach ( $plugin_paths as $dir => $realdir ) {
			// $dir = 論理パス（定数ベース）, $realdir = 実体パス（realpath）.
			$realdir = wp_normalize_path( $realdir );
			// ディレクトリ境界を正しく判定する。例: /srv/foo が /srv/foo-bar に誤マッチしないようにする。
			// $realdir が空文字の場合、PHP 8 では strpos( $path, '' ) が 0 を返し全マッチしてしまうため、
			// 空文字チェックも合わせて行う.
			if ( '' !== $realdir && ( $path === $realdir || 0 === strpos( $path, $realdir . '/' ) ) ) {
				return wp_normalize_path( $dir ) . substr( $path, strlen( $realdir ) );
			}
		}

		return $path;
	}

	/**
	 * 基準ディレクトリの配列（dir/url の組）を、dir 側を realpath() で実体パスへ解決した配列に変換する。
	 * realpath() は存在しないパスに false を返すため、その場合はマッチ対象から除外する
	 * （false のまま突き合わせに使うと strpos() 等で意図しない挙動になるため）。
	 *
	 * このメソッドを private static にしている理由・テストからの呼び出し方は resolve_symlinked_plugin_path() の PHPDoc を参照.
	 *
	 * @since 0.7.6
	 * @param array $directories dir/url の組の配列.
	 * @return array realpath 解決後の dir/url の組の配列（解決できなかったものは除外済み）.
	 */
	private static function realpath_directories( $directories ) {
		$resolved = array();
		foreach ( $directories as $directory ) {
			// match_directory_uri() と同じ入力検証（dir/url が揃っていない要素は候補から除外する）.
			if ( empty( $directory['dir'] ) || ! isset( $directory['url'] ) ) {
				continue;
			}
			// open_basedir 制限下のホストでは、制限外パスへの realpath() が E_WARNING を出すことがある。
			// 戻り値 false は後続で正しく処理できる想定のため、警告だけがログを汚さないよう '@' で抑制する。
			// is_dir() で事前確認する方式も検討したが、確認から realpath() 呼び出しまでの間に
			// ディレクトリが消える可能性（TOCTOU）を避けるため、1回の呼び出しで完結するこちらを選んだ.
			$real_dir = @realpath( $directory['dir'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir 制限下での E_WARNING 抑制のため（false は後続で処理する）.
			if ( false === $real_dir ) {
				continue;
			}
			$resolved[] = array(
				'dir' => wp_normalize_path( $real_dir ),
				'url' => $directory['url'],
			);
		}
		return $resolved;
	}

	/**
	 * $path が $directories（dir/url の組の配列）のいずれかの dir と前方一致するか調べ、
	 * 一致したら URL を組み立てて返す。一致しなければ空文字を返す.
	 *
	 * このメソッドを private static にしている理由・テストからの呼び出し方は resolve_symlinked_plugin_path() の PHPDoc を参照.
	 *
	 * @since 0.7.6
	 * @param string $path 判定対象のパス.
	 * @param array  $directories dir/url の組の配列.
	 * @return string 一致した場合は URL、しなければ空文字.
	 */
	private static function match_directory_uri( $path, $directories ) {
		foreach ( $directories as $directory ) {
			if ( empty( $directory['dir'] ) || ! isset( $directory['url'] ) ) {
				continue;
			}
			// ディレクトリ境界を正しく判定するため、末尾のスラッシュを取り除いてから比較する。
			// 例: /wp-content/plugins が /wp-content/plugins-extra に誤マッチしないようにする。
			// 比較に使う正規化済みの $dir を substr() の切り出し位置にもそのまま使う
			// （以前は比較にだけ末尾スラッシュ除去後の値を使い、substr() は除去前の $directory['dir'] の
			// 長さを使っていたため、WP_CONTENT_DIR 等が末尾スラッシュ付きで define() されている環境で
			// 相対パスの先頭が1文字欠ける不具合があった）.
			$dir = untrailingslashit( $directory['dir'] );
			// $directory['dir'] が '/' や '//' のように untrailingslashit() 後に空文字になるケースを弾く。
			// 空文字のままだと strpos( $path, '/' ) === 0 で全マッチしてしまう
			// （resolve_symlinked_plugin_path() に入れた同趣旨のガード（R1）と揃えている。安藤のレビュー指摘 LOW-4）.
			if ( '' === $dir ) {
				continue;
			}
			if ( $path === $dir || 0 === strpos( $path, $dir . '/' ) ) {
				$relative_path = substr( $path, strlen( $dir ) );
				// url 側も末尾スラッシュを正規化してから連結する。正規化しないと、WP_PLUGIN_URL /
				// WP_CONTENT_URL 等を末尾スラッシュ付きで define() している環境でスラッシュが重複する
				// （dir 側は R5 で untrailingslashit() 済みのため、url 側も揃える。安藤のレビュー指摘 LOW-5）.
				return untrailingslashit( $directory['url'] ) . $relative_path . '/';
			}
		}
		return '';
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
				'version'              => '7.3.1',
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
				'version'              => '7.3.1',
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

		// 既定値は複数の分岐で使うため、一度だけ算出して使い回す（get_option_default() は apply_filters 経由のため）。
		$default = self::get_option_default();

		// 基本の保存値（実際に読み込むアセットのバージョン）
		$version = get_option( 'vk_font_awesome_version' );
		$options = get_option( 'vk_font_awesome_options', $default );

		// 保存値が配列でない壊れた状態でも、後続の添字アクセスや書き込みで警告や Fatal を起こさないよう、
		// マイグレーション処理より前に配列であることを保証する。
		if ( ! is_array( $options ) ) {
			$options = $default;
		}

		// 古い保存値（旧 vk_font_awesome_version オプション）が残っている場合のマイグレーション対応。
		// 値は vk_font_awesome_options へ一本化するため、現在の version が空のときのみ引き継ぎ、
		// 引き継ぎの有無にかかわらず旧オプションは常に削除して DB にスタール値を残さない。
		if ( ! empty( $version ) ) {
			if ( empty( $options['version'] ) ) {
				$options['version'] = $version;
			}
			delete_option( 'vk_font_awesome_version' );
		}

		// version キーが無い／文字列でない・compatibility が不正など、壊れた保存値でも
		// 後続の比較や添字アクセスで警告や Fatal を起こさないよう正規化する。
		if ( empty( $options['version'] ) || ! is_string( $options['version'] ) ) {
			$options['version'] = $default['version'];
		}
		if ( ! isset( $options['compatibility'] ) || ! is_array( $options['compatibility'] ) ) {
			$options['compatibility'] = array();
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

		// ここまでの移行処理でも versions() に存在しないキー（不正な文字列など）が残っている場合はデフォルトへフォールバックする。
		// get_option_fa() が常に有効なバージョンキーを返すようにし、=== 比較のみを行う呼び出し元
		// （ex_and_link() / print_fa() / dynamic_css() / class_switch() / old_notice() 等）での未定義参照を防ぐ。
		$valid_versions = self::versions();
		if ( empty( $valid_versions[ $options['version'] ] ) ) {
			$options['version'] = $default['version'];
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

		// 実際に使用するバージョンは get_option_fa() の正規化結果を唯一の基準にする。
		// これによりレガシー値（4/5/6 系）も 7 系（SVG / CSS の別を保持）へ正しく解決され、
		// 呼び出し箇所（フロント / 管理画面 / エディタ）による差異が出ない。
		$option = self::get_option_fa();

		// 保存値は array( 'version' => ... ) 想定だが、古い保存値や誤った値が入っている場合に備えて
		// バージョンキー（文字列）を安全に取り出す。配列のまま添字アクセスすると PHP 8 で Fatal になるため。
		$version = is_array( $option ) && isset( $option['version'] ) ? $option['version'] : $option;

		// フィルタ等で $versions に存在しないキーが返っても未定義参照にならないよう、
		// 確実に存在する 7 系 CSS を最終フォールバックにする（$versions は 7 系のキーのみ持つ）。
		if ( ! is_string( $version ) || empty( $versions[ $version ] ) ) {
			$version = '7_WebFonts_CSS';
		}

		return $versions[ $version ];
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
		if ( ! is_admin() ) {
			return;
		}
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
