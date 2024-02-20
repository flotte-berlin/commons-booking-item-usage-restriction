<?php

use \CommonsBooking\Model\Restriction;
use \CommonsBooking\Model\Booking;

class CB2_Restriction_Service {

	//it's needed to identify a certain query for items and prevent endless loop with memory exaustion
	static $get_items_by_cats_query_id = 'd23deb79-819d-4330-a43a-5b51ef47daa6';

    /**
     * create a CB2 restriction
     * inspired by \CommonsBooking\Migration\Migration::migrateBooking()
     * 
     * @param $item_id
     * @param $date_start
     * @param $date_end
     * @param $type
     * @param $hint
	 *
	 * @return int
     */
    public static function create($item_id, $date_start, $date_end, $type, $hint = '', $title = ''): int {
        //blocker user
        $user_id = get_option('cb_item_restriction_blocking_user_id', null);

		$post_data = [
			'post_title'  => empty($title) ? 'Item Usage Restriction' : $title, // add more information about usage restriction
			'post_type'   => \CommonsBooking\Wordpress\CustomPostType\Restriction::$postType,
			'post_name'   => \CommonsBooking\Helper\Helper::generateRandomString(),
			'post_status' => 'publish',
			'post_author' => $user_id,
		];
        
        switch($type) {
            case 1:
                $meta_type = Restriction::TYPE_REPAIR;
                break;
            case 2:
                $meta_type = Restriction::TYPE_HINT;
                break;
            default:
                $meta_type = null;
        }

		$post_meta = [
			Restriction::META_START   => self::date_start_to_timestamp($date_start),
			Restriction::META_END     => self::date_end_to_timestamp($date_end),
			Restriction::META_ITEM_ID => $item_id,
            Restriction::META_TYPE    => $meta_type,
            Restriction::META_HINT    => $hint,
            Restriction::META_STATE   => Restriction::STATE_ACTIVE,
            Restriction::META_SENT    => time()
		];

		$post_id = self::save_post_data( $post_data, $post_meta );
        return $post_id;
    }

	protected static function date_start_to_timestamp($date_string) {
		return strtotime( $date_string );
	}

	protected static function date_end_to_timestamp($date_string) {
		return strtotime( $date_string ) + 24 * 60 * 60 - 1;
	}

    /**
	 * @param array $post_data Post data
	 * @param array $post_meta Post meta
	 *
	 * @return int
	 */
	protected static function save_post_data( array $post_data, array $post_meta ): int {
		$post_id = wp_insert_post( $post_data );
        
		if ( $post_id ) {
			foreach ( $post_meta as $key => $value ) {
				update_post_meta(
					$post_id,
					$key,
					$value
				);
			}

			return $post_id;
		}

		return null;
	}

	/**
	 * update post_meta 'restriction-hint'
	 * 
	 * @param int $post_id
	 * @param string $hint
	 *
	 * @return int|bool
	 */
    public static function update_restriction_hint($post_id, $hint) {
        return update_post_meta( $post_id, Restriction::META_HINT, $hint );
    }

	/**
	 * delete a post
	 * 
	 * @param int $post_id
	 * 
	 * @return null
	 */
	public static function delete($post_id) {
		wp_trash_post($post_id);
	}

	/**
	 * get start date of restriction
	 * 
	 * @param WP_Post $post
	 * 
	 * @return null
	 */
	public static function get_booking_start_date($post) {
		get_post_meta($post->ID, Booking::REPETITION_START, true );
	}

	public static function update_restriction_end_date($post_id, $date_end) {
		return update_post_meta( $post_id, Restriction::META_END, self::date_end_to_timestamp($date_end) );
	}

	/**
	 * filter out items that are managed by this plugin and not CB2 own restrictions
	 * 
	 * @param WP_Query $query
	 * 
	 * @return null
	 */
	public static function filter_admin_restriction_item_filter_list($query) {
		global $pagenow;

		if (
			is_admin() && !$query->is_main_query() &&
			isset( $_GET['post_type'] ) && CommonsBooking\Wordpress\CustomPostType\Restriction::$postType == sanitize_text_field(  $_GET['post_type'] ) &&
			$pagenow == 'edit.php'
		) {
			// add filter for non admins
			if( !commonsbooking_isCurrentUserAdmin() ) {
				if(
					$query->query['post_type'] == CommonsBooking\Wordpress\CustomPostType\Item::$postType &&
					(empty($query->query_vars['meta_query']) || 
					$query->query_vars['meta_query'][0]['key'] !== self::$get_items_by_cats_query_id) //don't use it for the item query by cat
				) {
	
					//get all items of filter category
					$cat_ids = get_option('cb_item_restriction_unmanaged_cb2_items_categories', []);
					if(count($cat_ids) > 0) {
						$no_iur_items = self::get_items_by_cats($cat_ids);
						$no_iur_item_ids = [];
						foreach($no_iur_items as $no_iur_item) {
							$no_iur_item_ids[] = $no_iur_item->ID;
						}
						$query->query_vars['post__in'] = $no_iur_item_ids;
					}
					
				}
			}
		}
	}

	/**
	 * filter out restrictions where the item is managed by this plugin and not CB2 own restrictions
	 * 
	 * @param $query
	 * 
	 * @return null
	 */
	public static function filter_admin_restriction_list($query) {
		global $pagenow;

		if (
			is_admin() && $query->is_main_query() &&
			isset( $_GET['post_type'] ) && CommonsBooking\Wordpress\CustomPostType\Restriction::$postType == sanitize_text_field(  $_GET['post_type'] ) &&
			$pagenow == 'edit.php'
		) {
			// add filter for non admins
			if ( !commonsbooking_isCurrentUserAdmin() ) {
				//get all items of filter category
				$cat_ids = get_option('cb_item_restriction_unmanaged_cb2_items_categories', []);
				var_dump($cat_ids);
				if(count($cat_ids) > 0) {
					$no_iur_items = self::get_items_by_cats($cat_ids);
					$no_iur_item_ids = [];
					foreach($no_iur_items as $no_iur_item) {
						$no_iur_item_ids[] = $no_iur_item->ID;
					}
					
					$query->query_vars['meta_query'][] = [
						'key'     => \CommonsBooking\Model\Restriction::META_ITEM_ID,
						'value'   => $no_iur_item_ids,
						'compare' => 'IN'
					];
				}
			}
		}
	}

	public static function get_items_by_cats($cat_ids) {
		$args = [
			'post_type' => \CommonsBooking\Wordpress\CustomPostType\Item::getPostType(),
			'post_status' => 'publish',
			'tax_query' => [
				[
					'taxonomy' => 'cb_items_category',
					'terms' => $cat_ids,
					'include_children' => false
				]
			],
			'posts_per_page' => -1,
			//this is just for query identification - using custom $args property seems not work
			'meta_query' => [
				[
					'key'     => self::$get_items_by_cats_query_id,
					'compare' => 'NOT EXISTS'
				]
			]
		];

		$query = new \WP_Query( $args );
		$items = $query->get_posts();

		return $items;
	}

	public static function get_location_array($post_id) {
		$location = [
			'name' => get_the_title( $post_id ),
		];

		return $location;
	}

	public static function get_regular_closed_weekdays($timeframe_id) {
		$closed_days = [];

		$timeframe_repitition = get_post_meta($timeframe_id, \CommonsBooking\Model\Timeframe::META_REPETITION, true);
		if($timeframe_repitition === 'w') {
			$weekdays = get_post_meta($timeframe_id, 'weekdays', true);

			//TODO: convert weekdays in CB1 closed_days
			$closed_days = [1, 2, 3, 4, 5, 6, 7 ];
			$closed_days = array_values(array_diff($closed_days, $weekdays));
		}

		return $closed_days;
	}

	static function fetch_restrictions_in_period($date_start, $date_end, $item_id) {
		$date_start_timestamp = strtotime($date_start);
		$date_end_timestamp = strtotime($date_end) + 24 * 60 * 60 - 1;
	
		$args = [
		  'post_type' => \CommonsBooking\Wordpress\CustomPostType\Restriction::getPostType(),
		  'post_status' => 'publish',
		  'posts_per_page' => -1,
		  'meta_query'  => [
			'relation' => 'AND',
			[
			  'key'     => Restriction::META_ITEM_ID,
			  'value'   => $item_id,
			  'compare' => '=',
			  'type'    => 'numeric',
			],
			[
				'key'     => Restriction::META_STATE,
				'value'   => Restriction::STATE_ACTIVE,
				'compare' => '=',
			],
			[
				'key'     => Restriction::META_TYPE,
				'value'   => Restriction::TYPE_REPAIR,
				'compare' => '=',
			],
			[
			  'relation' => 'OR',
			  [
				'key'     => Restriction::META_START,
				'value'   => [$date_start_timestamp, $date_end_timestamp],
				'compare' => 'BETWEEN',
				'type'    => 'numeric',
			  ],
			  [
				'key'     => Restriction::META_END,
				'value'   => [$date_start_timestamp, $date_end_timestamp],
				'compare' => 'BETWEEN',
				'type'    => 'numeric',
			  ]
			]
		  ]
		];
		
		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
		  return $query->get_posts();
		}
		else {
		  return [];
		}
	}
}