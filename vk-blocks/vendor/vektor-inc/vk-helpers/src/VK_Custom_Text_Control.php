<?php
/**
 * VK Custom Text Control loader.
 *
 * カスタマイザー用のカスタムテキスト入力コントロールを提供する。
 * input_before / input_after で入力欄の前後に文字列を付けたり、
 * input_type で text 以外（number, email, url など）を指定できる。
 *
 * WP_Customize_Control が未読込のタイミングで autoload されることを考慮し、
 * customize_register アクション内で遅延宣言する。
 *
 * グローバル名前空間に置くため、composer.json の autoload.files で読み込む。
 *
 * @package VektorInc\VK_Helpers
 */

// WordPress 環境（add_action がある）でのみフックを登録する。
if ( function_exists( 'add_action' ) ) {
	add_action( 'customize_register', 'vk_helpers_register_custom_text_control', 0 );
}

if ( ! function_exists( 'vk_helpers_register_custom_text_control' ) ) {
	/**
	 * VK_Custom_Text_Control クラスを customize_register のタイミングで宣言する。
	 *
	 * WP_Customize_Control はカスタマイザー画面でのみ読み込まれるため、
	 * autoload 時点では存在しない可能性がある。そのため customize_register
	 * フック内で class_exists ガード付きで宣言する。
	 *
	 * @return void
	 */
	function vk_helpers_register_custom_text_control() {
		// WP_Customize_Control が未読込、または既に同名クラスが定義済みなら何もしない。
		if ( ! class_exists( 'WP_Customize_Control', false ) || class_exists( 'VK_Custom_Text_Control', false ) ) {
			return;
		}

		/**
		 * VK_Custom_Text_Control
		 *
		 * 入力欄の前後に文字列を付与できる拡張テキストコントロール。
		 * input_type で number/email/url 等の HTML5 入力タイプを切り替えられる。
		 */
		class VK_Custom_Text_Control extends WP_Customize_Control {

			/**
			 * Control type.
			 *
			 * @var string
			 */
			public $type = 'customtext';

			/**
			 * input 要素の前に出力する HTML。例: 単位ラベル等。
			 *
			 * @var string
			 */
			public $input_before = '';

			/**
			 * input 要素の後ろに出力する HTML。例: "px" "%" 等の単位。
			 *
			 * @var string
			 */
			public $input_after = '';

			/**
			 * input 要素の type 属性。許可: text/number/email/url/tel/password/search。
			 *
			 * @var string
			 */
			public $input_type = 'text';

			/**
			 * input 要素に追加する属性の連想配列。
			 *
			 * 例: array( 'min' => 0, 'max' => 100, 'step' => 1 )
			 * type/value/style/on* 属性は予約済みのため指定不可。
			 *
			 * @var array
			 */
			public $input_attrs = array();

			/**
			 * コントロールの中身を出力する。
			 *
			 * @return void
			 */
			public function render_content() {
				// 許可する input type の一覧。許可外が来た場合は text にフォールバックする。
				$allowed_input_types = array( 'text', 'number', 'email', 'url', 'tel', 'password', 'search' );
				$input_type = ( is_string( $this->input_type ) && '' !== $this->input_type && in_array( strtolower( $this->input_type ), $allowed_input_types, true ) ) ? strtolower( $this->input_type ) : 'text';

				// input_attrs は配列であることを保証する。
				$input_attrs = is_array( $this->input_attrs ) ? $this->input_attrs : array();

				// 予約属性。これらは input_attrs で上書きさせない。
				$reserved_attrs = array( 'type', 'value', 'style' );
				?>
				<label>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php
					// 前後にテキストがある場合は input の幅を 50% に縮めて見た目を整える。
					$style = ( $this->input_before || $this->input_after ) ? ' style="width:50%"' : '';
					?>
					<div>
						<?php echo wp_kses_post( $this->input_before ); ?>
						<input type="<?php echo esc_attr( $input_type ); ?>" value="<?php echo esc_attr( $this->value() ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部で定義した固定文字列のみ。 ?>
							<?php
							// input_attrs を 1 つずつ吐き出す。
							foreach ( $input_attrs as $attr_name => $attr_value ) :
								// キーが文字列でない場合は無視。
								if ( ! is_string( $attr_name ) ) {
									continue;
								}
								$attr_name = trim( $attr_name );
								// HTML 属性名トークンのみ許可（空白・記号混入を拒否）。
								// 「foo onfocus」のようなキーで on* チェックを迂回されるのを防ぐ。
								if ( '' === $attr_name || ! preg_match( '/^[a-zA-Z_:][a-zA-Z0-9:._-]*$/', $attr_name ) ) {
									continue;
								}
								$attr_name_lc = strtolower( $attr_name );
								// on* イベントハンドラと予約属性はスキップ（XSS / 衝突回避）。
								if ( 0 === strpos( $attr_name_lc, 'on' ) || in_array( $attr_name_lc, $reserved_attrs, true ) ) {
									continue;
								}
								// 真偽値は「属性名だけ出力 or 出力しない」の動作にする（HTML5 boolean attribute）。
								if ( is_bool( $attr_value ) ) {
									if ( $attr_value ) {
										echo ' ' . esc_attr( $attr_name );
									}
									continue;
								}
								// null や非スカラー（配列・オブジェクト）はスキップする。
								if ( null === $attr_value || ! is_scalar( $attr_value ) ) {
									continue;
								}
								?>
								<?php echo esc_attr( $attr_name ); ?>="<?php echo esc_attr( (string) $attr_value ); ?>"
							<?php endforeach; ?>
							<?php $this->link(); ?> />
						<?php echo wp_kses_post( $this->input_after ); ?>
					</div>
					<?php if ( $this->description ) : ?>
						<div><?php echo wp_kses_post( $this->description ); ?></div>
					<?php endif; ?>
				</label>
				<?php
			}
		}
	}
}
