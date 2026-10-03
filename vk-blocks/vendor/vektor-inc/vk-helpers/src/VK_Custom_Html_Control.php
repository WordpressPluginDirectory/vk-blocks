<?php
/**
 * VK Custom Html Control loader.
 *
 * カスタマイザー用のカスタム HTML コントロールを提供する。
 * WP_Customize_Control が未読込のタイミングで autoload されることを考慮し、
 * customize_register アクション内で遅延宣言する。
 *
 * グローバル名前空間に置くため、composer.json の autoload.files で読み込む。
 *
 * @package VektorInc\VK_Helpers
 */

// WordPress 環境（add_action がある）でのみフックを登録する。
if ( function_exists( 'add_action' ) ) {
	add_action( 'customize_register', 'vk_helpers_register_custom_html_control', 0 );
}

if ( ! function_exists( 'vk_helpers_register_custom_html_control' ) ) {
	/**
	 * VK_Custom_Html_Control クラスを customize_register のタイミングで宣言する。
	 *
	 * WP_Customize_Control はカスタマイザー画面でのみ読み込まれるため、
	 * autoload 時点では存在しない可能性がある。そのため customize_register
	 * フック内で class_exists ガード付きで宣言する。
	 *
	 * @return void
	 */
	function vk_helpers_register_custom_html_control() {
		// WP_Customize_Control が未読込、または既に同名クラスが定義済みなら何もしない。
		if ( ! class_exists( 'WP_Customize_Control', false ) || class_exists( 'VK_Custom_Html_Control', false ) ) {
			return;
		}

		/**
		 * VK_Custom_Html_Control
		 *
		 * カスタマイザーセクション内に任意の見出し・サブ見出し・HTML を出力する
		 * 設定なしのコントロール。設定セクションの説明用に使うことを想定。
		 */
		class VK_Custom_Html_Control extends WP_Customize_Control {

			/**
			 * Control type.
			 *
			 * @var string
			 */
			public $type = 'customtext';

			/**
			 * サブタイトル（h3 で出力される追加見出し）。
			 *
			 * @var string
			 */
			public $custom_title_sub = '';

			/**
			 * 任意の HTML 本文。wp_kses_post でサニタイズされる。
			 *
			 * @var string
			 */
			public $custom_html = '';

			/**
			 * label の出力に使う見出しタグ。許可: h2/h3/h4/h5/h6。
			 *
			 * @var string
			 */
			public $label_tag = 'h2';

			/**
			 * コントロールの中身を出力する。
			 *
			 * label / custom_title_sub / custom_html をそれぞれ条件付きで出力する。
			 *
			 * @return void
			 */
			public function render_content() {
				// label が指定されていれば、label_tag で指定された見出しタグで出力する。
				if ( $this->label ) {
					// 許可する見出しタグの一覧。許可外が来た場合は h2 にフォールバックする。
					$allowed_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6' );
					$label_tag    = in_array( strtolower( (string) $this->label_tag ), $allowed_tags, true ) ? strtolower( (string) $this->label_tag ) : 'h2';
					// クラス名は admin-custom-{tag} の規則で生成する（既存テーマの CSS と互換）。
					$label_class  = 'admin-custom-' . $label_tag;
					printf(
						'<%1$s class="%2$s">%3$s</%1$s>',
						esc_attr( $label_tag ),
						esc_attr( $label_class ),
						wp_kses_post( $this->label )
					);
				}
				// サブタイトルがあれば h3 で出力する。
				if ( $this->custom_title_sub ) {
					echo '<h3 class="admin-custom-h3">' . wp_kses_post( $this->custom_title_sub ) . '</h3>';
				}
				// 任意 HTML があれば div でラップして出力する。
				if ( $this->custom_html ) {
					echo '<div>' . wp_kses_post( $this->custom_html ) . '</div>';
				}
			}
		}
	}
}
