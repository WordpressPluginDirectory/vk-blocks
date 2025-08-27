<?php // phpcs:ignore

use VektorInc\VK_Helpers\VkHelpers;

/**
 * VK Helpers Test
 *
 * @package VK Helpers
 */
class VkHelpersTest extends WP_UnitTestCase {

	/**
	 * Test get_post_type_info
	 *
	 * @return void
	 */
	public function test_get_post_type_info() {

		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'VkHelpers::get_post_type_info()' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print PHP_EOL;

		/*** ↓↓ テスト用事前データ設定（ test_lightning_is_layout_onecolumn と test_lightning_is_subsection_display 共通 ) */

		register_post_type(
			'event',
			array(
				'label'       => 'Event',
				'has_archive' => true,
				'public'      => true,
			)
		);
		register_taxonomy(
			'event_cat',
			'event',
			array(
				'label'        => 'Event Category',
				'rewrite'      => array( 'slug' => 'event_cat' ),
				'hierarchical' => true,
			)
		);

		// Create test category.
		$catarr             = array(
			'cat_name' => 'parent_category',
		);
		$parent_category_id = wp_insert_category( $catarr );

		$catarr            = array(
			'cat_name'        => 'child_category',
			'category_parent' => $parent_category_id,
		);
		$child_category_id = wp_insert_category( $catarr );

		$catarr              = array(
			'cat_name' => 'no_post_category',
		);
		$no_post_category_id = wp_insert_category( $catarr );

		// Create test term.
		$args          = array(
			'slug' => 'event_category_name',
		);
		$term_info     = wp_insert_term( 'event_category_name', 'event_cat', $args );
		$event_term_id = $term_info['term_id'];

		// Create test post.
		$post    = array(
			'post_title'    => 'test',
			'post_status'   => 'publish',
			'post_content'  => 'content',
			'post_category' => array( $parent_category_id ),
		);
		$post_id = wp_insert_post( $post );
		// 投稿にカテゴリー指定.
		wp_set_object_terms( $post_id, 'child_category', 'category' );

		// Create test page.
		$post           = array(
			'post_title'   => 'parent_page',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => 'content',
		);
		$parent_page_id = wp_insert_post( $post );

		$post = array(
			'post_title'   => 'child_page',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_parent'  => $parent_page_id,

		);
		$child_page_id = wp_insert_post( $post );

		// Create test home page.
		$post         = array(
			'post_title'   => 'post_top',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => 'content',
		);
		$home_page_id = wp_insert_post( $post );

		// Create test home page.
		$post          = array(
			'post_title'   => 'front_page',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => 'content',
		);
		$front_page_id = wp_insert_post( $post );

		// custom post type.
		$post          = array(
			'post_title'   => 'event-test-post',
			'post_type'    => 'event',
			'post_status'  => 'publish',
			'post_content' => 'content',
		);
		$event_post_id = wp_insert_post( $post );
		// set event category to event post.
		wp_set_object_terms( $event_post_id, 'event_category_name', 'event_cat' );

		/*** ↑↑ テスト用事前データ設定（ test_lightning_is_layout_onecolumn と test_lightning_is_subsection_display 共通 ) */


		$test_array = array(
			// Post //////////////////////////////////////////////////////////////////.
			'single_post'      => array(
				'target_url' => get_permalink( $post_id ),
				'options'    => array(
					'show_on_front'  => 'page',
					'page_on_front'  => $front_page_id,
					'page_for_posts' => $home_page_id,
				),
				'expected'   => array(
					'slug' => 'post',
					'name' => 'post_top',
					'url'  => get_permalink( $home_page_id ),
				),
			),
			'post_top'         => array(
				'target_url' => get_permalink( $home_page_id ),
				'options'    => array(
					'show_on_front'  => 'page',
					'page_on_front'  => $front_page_id,
					'page_for_posts' => $home_page_id,
				),
				'expected'   => array(
					'slug' => 'post',
					'name' => 'post_top',
					'url'  => get_permalink( $home_page_id ),
				),
			),
			'category_archive' => array(
				'target_url' => get_term_link( $parent_category_id, 'category' ),
				'options'    => array(
					'show_on_front'  => 'page',
					'page_on_front'  => $front_page_id,
					'page_for_posts' => $home_page_id,
				),
				'expected'   => array(
					'slug' => 'post',
					'name' => 'post_top',
					'url'  => get_permalink( $home_page_id ),
				),
			),
			// Page //////////////////////////////////////////////////////////////////.
			'single_page'      => array(
				'target_url' => get_permalink( $parent_page_id ),
				'expected'   => array(
					'slug' => 'page',
					'name' => 'Pages',
					'url'  => '',
				),
			),
			// Event //////////////////////////////////////////////////////////////////.
			'event_category'   => array(
				'target_url' => get_term_link( $event_term_id, 'event_cat' ),
				'expected'   => array(
					'slug' => 'event',
					'name' => 'Event',
					'url'  => get_post_type_archive_link( 'event' ),
				),
			),
			'single_event'     => array(
				'target_url' => get_permalink( $event_post_id ),
				'expected'   => array(
					'slug' => 'event',
					'name' => 'Event',
					'url'  => get_post_type_archive_link( 'event' ),
				),
			),
		);

		foreach ( $test_array as $key => $value ) {

			delete_option( 'show_on_front' );
			delete_option( 'page_on_front' );
			delete_option( 'page_for_posts' );

			if ( ! empty( $value['options'] ) && is_array( $value['options'] ) ) {
				foreach ( $value['options'] as $option_key => $option_value ) {
					update_option( $option_key, $option_value );
				}
			}

			// Move to test page.
			$this->go_to( $value['target_url'] );

			$actual = VkHelpers::get_post_type_info();

			// Debug
			// print esc_html( $value['target_url'] ) . PHP_EOL;
			// print 'actual------------------------------------' . PHP_EOL;
			// var_dump( $actual ) . PHP_EOL;
			// print 'expected------------------------------------' . PHP_EOL;
			// var_dump( $value['expected'] ) . PHP_EOL;
			// print '------------------------------------' . PHP_EOL;

			$this->assertSame( $value['expected'], $actual );

		}
	}

	/**
	 * Test color_auto_modifi
	 *
	 * @return void
	 */
	public function test_color_auto_modifi() {

		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'VkHelpers::color_auto_modifi()' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print PHP_EOL;

		$test_array = array(
			'#00a0e9'  => array(
				'change_rate' => '1.1',
				'color_hex'   => '#00a0e9',
				'expected'    => '#00b0ff',
			),
		);
		foreach ( $test_array as $key => $value ) {

			$actual = VkHelpers::color_auto_modifi( $value['color_hex'], $value['change_rate'] );

			$this->assertSame( $value['expected'], $actual );

		}
	}

	/**
	 * Test color_auto_modifi_single
	 *
	 * @return void
	 */
	public function test_color_auto_modifi_single() {

		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'VkHelpers::color_auto_modifi_single()' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print PHP_EOL;

		$test_array = array(
			'00'  => array(
				'change_rate' => '1.1',
				'color_num'   => '00',
				'expected'    => '00',
			),
			'100' => array(
				'change_rate' => '1.1',
				'color_num'   => '100',
				'expected'    => '6e',
			),
			'250' => array(
				'change_rate' => '1.1',
				'color_num'   => '250',
				'expected'    => 'ff',
			),
		);
		foreach ( $test_array as $key => $value ) {

			$actual = VkHelpers::color_auto_modifi_single( $value['color_num'], $value['change_rate'] );

			$this->assertSame( $value['expected'], $actual );

		}
	}
}
