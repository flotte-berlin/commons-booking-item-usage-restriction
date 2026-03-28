<?php

use \CommonsBooking\Model\Restriction;
use \CommonsBooking\Model\Booking;
use \CommonsBooking\Plugin;

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
		return get_post_meta($post->ID, Booking::REPETITION_START, true );
	}

	public static function update_restriction_end_date($post_id, $date_end) {
		$result = update_post_meta( $post_id, Restriction::META_END, self::date_end_to_timestamp($date_end) );

		//refresh cache
		$post = get_post($post_id);
		$cbPlugin = new Plugin();
		$cbPlugin->savePostActions( $post_id, $post, true );

		return $result;
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

	/**
	 * Check for conflicting bookings in item usage restriction period
	 * 
	 * @param int $blocking_user_id The user ID that blocks the period
	 * @param array $conflict_bookings Array of bookings that may conflict
	 * @param string $date_start Start date of the period (Y-m-d format)
	 * @param string $date_end End date of the period (Y-m-d format)
	 * @param int $max_day_column_weight Maximum weight allowed per day column
	 * 
	 * @return int Number of conflicting bookings
	 */
	public static function check_conflict_bookings_in_item_usage_restriction($blocking_user_id, $conflict_bookings, $date_start, $date_end, $max_day_column_weight = 0) {
		error_reporting(E_ALL);
		$conflict_bookings_count = 0;

		$duration_length = self::date_difference($date_start, $date_end) + 1;
		$day_column = [];
		$matrix = [];
		$day_column_weights = [];
		$day_booking_deadline = [];

		$day_column = array_pad($day_column , count($conflict_bookings) , 0);
		$matrix = array_pad($matrix , $duration_length , $day_column);
		$day_column_weights = array_pad($day_column_weights , $duration_length , 0);
		$day_booking_deadline = array_pad($day_booking_deadline , $duration_length , null);

		foreach($conflict_bookings as $booking_index => $conflict_booking) {
			$date_time = new \DateTime($date_start);
			$date_time->setTime(12, 0, 0);

			// Get booking dates - handle CB2 format
			$booking_start_timestamp = self::get_booking_start_date($conflict_booking);
			if (!$booking_start_timestamp) {
				continue;
			}
			
			$booking_date_time_start = new \DateTime();
			$booking_date_time_start->setTimestamp($booking_start_timestamp);
			$booking_date_time_start->setTime(0, 0, 0);

			$booking_end_timestamp = get_post_meta($conflict_booking->ID, \CommonsBooking\Model\Booking::REPETITION_END, true);
			$booking_date_time_end = new \DateTime();
			$booking_date_time_end->setTimestamp($booking_end_timestamp);
			$booking_date_time_end->setTime(23, 59, 59);

			//first step: consider only blocking bookings
			for($d = 0; $d < $duration_length; $d++) {
				if($date_time > $booking_date_time_start && $date_time < $booking_date_time_end) {
					$booking_user_id = $conflict_booking->post_author;
					if($booking_user_id == $blocking_user_id) {
						$day_booking_deadline[$d] = new \DateTime($conflict_booking->post_date);
						$matrix[$d][$booking_index] = -1; //weight
					}
				}
				$date_time->modify('+1 day');
			}

			//second step: consider other bookings
			$date_time = new \DateTime($date_start);
			$date_time->setTime(12, 0, 0);
			for($d = 0; $d < $duration_length; $d++) {
				if($date_time > $booking_date_time_start && $date_time < $booking_date_time_end) {
					$booking_user_id = $conflict_booking->post_author;
					if($booking_user_id != $blocking_user_id) {
						//booking was created after a parallel blocking booking
						$booking_created = new \DateTime($conflict_booking->post_date);
						if($day_booking_deadline[$d] && $booking_created > $day_booking_deadline[$d]) {
							$weight = 2;
						}
						else {
							$weight = 1;
						}
						$matrix[$d][$booking_index] = $weight;
					}
				}
				$date_time->modify('+1 day');
			}
		}

		//sum up all weights of a column
		foreach($matrix as $column_index => $day_column) {
			foreach ($day_column as $weight) {
				$day_column_weights[$column_index] += $weight;
			}
		}

		//sum up overall weights of all columns (only if > 0)
		foreach($day_column_weights as $day_column_weight) {
			if($day_column_weight > $max_day_column_weight) {
				$conflict_bookings_count++;
			}
		}

		return $conflict_bookings_count;
	}

	/**
	 * Calculate difference between two dates
	 * 
	 * @param string $date_1 Start date (Y-m-d format)
	 * @param string $date_2 End date (Y-m-d format)
	 * @param string $differenceFormat Format for date_diff output
	 * 
	 * @return string The difference formatted as requested
	 */
	private static function date_difference($date_1 , $date_2 , $differenceFormat = '%a' ) {
		$datetime1 = date_create($date_1);
		$datetime2 = date_create($date_2);
		$interval = date_diff($datetime1, $datetime2);
		return $interval->format($differenceFormat);
	}

	/**
	 * Fetch bookings in a given period for an item
	 * 
	 * @param string $date_start Start date (Y-m-d format)
	 * @param string $date_end End date (Y-m-d format)
	 * @param int $item_id Item ID
	 * @param int $location_id Location ID (optional)
	 * @param int|null $ignore_booking Booking ID to ignore (optional)
	 * 
	 * @return array Array of booking posts
	 */
	public static function fetch_bookings_in_period($date_start, $date_end, $item_id, $location_id = null, $ignore_booking = null) {
		$repetition_start = strtotime($date_start);
		$repetition_end = strtotime($date_end);

		$existingBookings = \CommonsBooking\Repository\Booking::getExistingBookings(
			$item_id,
			$location_id,
			$repetition_start,
			$repetition_end,
			$ignore_booking,
		);

		return $existingBookings;
	}
}