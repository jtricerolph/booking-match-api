<?php
/**
 * Gantt Chart Generator Class
 *
 * Reusable class for generating restaurant booking Gantt charts with multiple display modes.
 * Based on the reservation-management-integration plugin Gantt implementation.
 *
 * @package Booking_Match_API
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class BMA_Gantt_Chart {

	/**
	 * Display mode constants
	 */
	const MODE_FULL = 'full';
	const MODE_MEDIUM = 'medium';
	const MODE_COMPACT = 'compact';

	/**
	 * Configuration for each display mode
	 */
	private $mode_config = array(
		'full' => array(
			'bar_height' => 40,
			'grid_row_height' => 14,
			'show_names' => true,
			'show_room_numbers' => true,
			'show_tooltips' => true,
			'font_size' => 13,
		),
		'medium' => array(
			'bar_height' => 28,
			'grid_row_height' => 10,
			'show_names' => true,
			'show_room_numbers' => false,
			'show_tooltips' => true,
			'font_size' => 11,
		),
		'compact' => array(
			'bar_height' => 14,
			'grid_row_height' => 7,
			'show_names' => false,
			'show_room_numbers' => false,
			'show_tooltips' => true,
			'font_size' => 10,
		),
	);

	/**
	 * Chart parameters
	 */
	private $bookings = array();
	private $opening_hours = array();
	private $special_events = array();
	private $available_times = array();
	private $online_booking_available = true;
	private $display_mode = self::MODE_FULL;
	private $viewport_hours = null;
	private $initial_center_time = null;
	private $chart_id = 'gantt';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Empty constructor - configuration via setter methods
	}

	/**
	 * Set bookings data
	 *
	 * @param array $bookings Array of booking objects
	 * @return $this
	 */
	public function set_bookings( $bookings ) {
		$this->bookings = is_array( $bookings ) ? $bookings : array();
		return $this;
	}

	/**
	 * Set opening hours
	 *
	 * @param array $opening_hours Array of opening hour periods
	 * @return $this
	 */
	public function set_opening_hours( $opening_hours ) {
		$this->opening_hours = is_array( $opening_hours ) ? $opening_hours : array();
		return $this;
	}

	/**
	 * Set special events
	 *
	 * @param array $special_events Array of special event objects
	 * @return $this
	 */
	public function set_special_events( $special_events ) {
		$this->special_events = is_array( $special_events ) ? $special_events : array();
		return $this;
	}

	/**
	 * Set available times
	 *
	 * @param array $available_times Array of available time strings (HH:MM format)
	 * @return $this
	 */
	public function set_available_times( $available_times ) {
		$this->available_times = is_array( $available_times ) ? $available_times : array();
		return $this;
	}

	/**
	 * Set online booking availability
	 *
	 * @param bool $available Whether online booking is available
	 * @return $this
	 */
	public function set_online_booking_available( $available ) {
		$this->online_booking_available = (bool) $available;
		return $this;
	}

	/**
	 * Set display mode
	 *
	 * @param string $mode One of: 'full', 'medium', 'compact'
	 * @return $this
	 */
	public function set_display_mode( $mode ) {
		if ( in_array( $mode, array( self::MODE_FULL, self::MODE_MEDIUM, self::MODE_COMPACT ), true ) ) {
			$this->display_mode = $mode;
		}
		return $this;
	}

	/**
	 * Set viewport width in hours (for windowed mode)
	 *
	 * @param int|null $hours Number of hours to show in viewport (null = full day)
	 * @return $this
	 */
	public function set_viewport_hours( $hours ) {
		$this->viewport_hours = is_numeric( $hours ) ? (int) $hours : null;
		return $this;
	}

	/**
	 * Set initial center time for viewport scrolling
	 *
	 * @param string|null $time Time in HHMM format (e.g., "1900") or HH:MM format
	 * @return $this
	 */
	public function set_initial_center_time( $time ) {
		if ( $time ) {
			// Convert HH:MM to HHMM if needed
			if ( strpos( $time, ':' ) !== false ) {
				$time = str_replace( ':', '', $time );
			}
			$this->initial_center_time = $time;
		}
		return $this;
	}

	/**
	 * Set chart ID for multiple charts on same page
	 *
	 * @param string $id Unique chart identifier
	 * @return $this
	 */
	public function set_chart_id( $id ) {
		$this->chart_id = sanitize_key( $id );
		return $this;
	}

	/**
	 * Generate complete Gantt chart HTML
	 *
	 * @return string HTML markup for the Gantt chart
	 */
	public function generate() {
		$config = $this->mode_config[ $this->display_mode ];

		// Determine time range from opening hours
		list( $start_hour, $end_hour ) = $this->calculate_time_range();

		$total_minutes = ( $end_hour - $start_hour ) * 60;
		$booking_duration = 120; // Default 2 hours

		// Process and position all bookings
		$positioned_bookings = $this->position_bookings( $start_hour, $total_minutes, $booking_duration, $config['grid_row_height'] );

		// Calculate total height based on grid rows used
		$total_height = $this->calculate_total_height( $positioned_bookings, $config['grid_row_height'] );

		// Build HTML components
		$closed_blocks_html = $this->build_closed_blocks( $start_hour, $end_hour, $total_minutes, $total_height );
		$interval_lines_html = $this->build_interval_lines( $total_minutes, $total_height );
		$booking_bars_html = $this->build_booking_bars( $positioned_bookings, $total_minutes, $booking_duration, $config );
		$time_axis_html = $this->build_time_axis( $start_hour, $end_hour, $total_minutes );

		// Determine if viewport mode should be used
		$use_viewport = ( $this->viewport_hours !== null );

		// Build complete HTML
		$html = '';

		if ( $use_viewport ) {
			// Viewport mode with scroll controls
			$html .= '<div class="bma-gantt-container" id="' . esc_attr( $this->chart_id ) . '-container">';
			$html .= $this->build_gantt_controls();
			$html .= '<div class="gantt-viewport" id="' . esc_attr( $this->chart_id ) . '-viewport" style="' . $this->get_viewport_styles() . '">';
		}

		// Time axis
		$html .= '<div class="gantt-time-axis">';
		$html .= $time_axis_html;
		$html .= '</div>';

		// Bookings area
		$html .= '<div class="gantt-bookings" style="height: ' . esc_attr( $total_height ) . 'px; position: relative;">';
		$html .= $closed_blocks_html;
		$html .= $interval_lines_html;
		$html .= $booking_bars_html;
		$html .= '<div class="gantt-sight-line" id="gantt-sight-line-' . esc_attr( $this->chart_id ) . '" style="height: ' . esc_attr( $total_height ) . 'px; display: none;"></div>';
		$html .= '</div>';

		if ( $use_viewport ) {
			$html .= '</div>'; // Close viewport
			$html .= '</div>'; // Close container
		}

		// Add inline styles
		$html .= $this->get_inline_styles( $config );

		// Add initialization script for viewport scrolling
		if ( $use_viewport && $this->initial_center_time ) {
			$html .= $this->get_initialization_script();
		}

		return $html;
	}

	/**
	 * Calculate time range from opening hours
	 *
	 * @return array Array with [start_hour, end_hour]
	 */
	private function calculate_time_range() {
		$start_hour = 18;
		$end_hour = 22;

		if ( ! empty( $this->opening_hours ) ) {
			$earliest_open = 2400;
			$latest_close = 0;

			foreach ( $this->opening_hours as $period ) {
				$open = isset( $period['open'] ) ? (int) $period['open'] : 1800;
				$close = isset( $period['close'] ) ? (int) $period['close'] : 2200;

				if ( $open < $earliest_open ) {
					$earliest_open = $open;
				}
				if ( $close > $latest_close ) {
					$latest_close = $close;
				}
			}

			$start_hour = floor( $earliest_open / 100 );
			$end_hour = floor( $latest_close / 100 );

			// Round up if there are minutes
			if ( $latest_close % 100 > 0 ) {
				$end_hour++;
			}
		}

		return array( $start_hour, $end_hour );
	}

	/**
	 * Position bookings using grid-based layout algorithm
	 *
	 * @param int $start_hour Starting hour
	 * @param int $total_minutes Total minutes in chart
	 * @param int $booking_duration Default booking duration
	 * @param int $grid_row_height Height of each grid row
	 * @return array Array of positioned booking objects
	 */
	private function position_bookings( $start_hour, $total_minutes, $booking_duration, $grid_row_height ) {
		$all_bookings = array();

		// Flatten bookings array and calculate positions
		foreach ( $this->bookings as $key => $booking_list ) {
			if ( ! is_array( $booking_list ) ) {
				continue;
			}

			foreach ( $booking_list as $booking ) {
				// Handle both direct booking objects and nested resos_booking structure
				$booking_data = isset( $booking['resos_booking'] ) ? $booking['resos_booking'] : $booking;

				if ( empty( $booking_data['time'] ) ) {
					continue;
				}

				$time = $booking_data['time'];
				$time_parts = explode( ':', $time );
				$hours = (int) $time_parts[0];
				$minutes = isset( $time_parts[1] ) ? (int) $time_parts[1] : 0;

				$minutes_from_start = ( $hours - $start_hour ) * 60 + $minutes;

				if ( $minutes_from_start >= 0 && $minutes_from_start < $total_minutes ) {
					$all_bookings[] = array(
						'time' => $time,
						'people' => isset( $booking_data['people'] ) ? (int) $booking_data['people'] : 2,
						'name' => isset( $booking_data['name'] ) ? $booking_data['name'] : 'Guest',
						'room' => isset( $booking_data['room'] ) ? $booking_data['room'] : 'Unknown',
						'notes' => isset( $booking_data['notes'] ) ? $booking_data['notes'] : array(),
						'tables' => isset( $booking_data['tables'] ) ? $booking_data['tables'] : array(),
						'hours' => $hours,
						'minutes' => $minutes,
						'minutes_from_start' => $minutes_from_start,
					);
				}
			}
		}

		// Sort by start time
		usort( $all_bookings, function( $a, $b ) {
			return $a['minutes_from_start'] - $b['minutes_from_start'];
		});

		// Grid-based positioning algorithm
		$grid_rows = array();
		$max_party_size = 20;
		$buffer = 5; // 5-minute buffer

		foreach ( $all_bookings as $index => $booking ) {
			$booking_start = $booking['minutes_from_start'];
			$booking_end = $booking_start + $booking_duration;
			if ( $booking_end > $total_minutes ) {
				$booking_end = $total_minutes;
			}

			// Calculate row span based on party size
			$party_size = min( $booking['people'], $max_party_size );
			$row_span = max( 2, floor( $party_size / 2 ) + 1 );

			// Find placement
			$start_grid_row = 0;
			$placed = false;

			while ( ! $placed ) {
				// Ensure enough grid rows exist
				while ( count( $grid_rows ) < $start_grid_row + $row_span ) {
					$grid_rows[] = array( 'occupied' => array() );
				}

				// Check if all required rows are free
				$can_place = true;
				for ( $r = $start_grid_row; $r < $start_grid_row + $row_span; $r++ ) {
					foreach ( $grid_rows[ $r ]['occupied'] as $seg ) {
						// Check for overlap with buffer
						if ( ! ( $booking_end + $buffer <= $seg['start'] || $booking_start >= $seg['end'] + $buffer ) ) {
							$can_place = false;
							break 2;
						}
					}
				}

				if ( $can_place ) {
					// Place the booking
					for ( $r = $start_grid_row; $r < $start_grid_row + $row_span; $r++ ) {
						$grid_rows[ $r ]['occupied'][] = array(
							'start' => $booking_start,
							'end' => $booking_end,
						);
					}

					$all_bookings[ $index ]['grid_row'] = $start_grid_row;
					$all_bookings[ $index ]['row_span'] = $row_span;
					$all_bookings[ $index ]['total_grid_rows'] = count( $grid_rows );
					$placed = true;
				} else {
					$start_grid_row++;
				}
			}
		}

		// Store total grid rows in each booking
		$total_grid_rows = count( $grid_rows );
		foreach ( $all_bookings as $index => $booking ) {
			$all_bookings[ $index ]['total_grid_rows'] = $total_grid_rows;
		}

		return $all_bookings;
	}

	/**
	 * Calculate total chart height
	 *
	 * @param array $positioned_bookings Array of positioned bookings
	 * @param int $grid_row_height Height of each grid row
	 * @return int Total height in pixels
	 */
	private function calculate_total_height( $positioned_bookings, $grid_row_height ) {
		if ( empty( $positioned_bookings ) ) {
			return 80;
		}

		$total_grid_rows = $positioned_bookings[0]['total_grid_rows'];
		return 10 + ( $total_grid_rows * $grid_row_height ) + 10;
	}

	/**
	 * Build booking bars HTML
	 *
	 * @param array $positioned_bookings Array of positioned bookings
	 * @param int $total_minutes Total minutes in chart
	 * @param int $booking_duration Default booking duration
	 * @param array $config Display mode configuration
	 * @return string HTML for booking bars
	 */
	private function build_booking_bars( $positioned_bookings, $total_minutes, $booking_duration, $config ) {
		$html = '';

		foreach ( $positioned_bookings as $booking ) {
			$left_percent = ( $booking['minutes_from_start'] / $total_minutes ) * 100;
			$y_position = 10 + ( $booking['grid_row'] * $config['grid_row_height'] );
			$bar_height = ( $booking['row_span'] * $config['grid_row_height'] ) - 4;

			// Calculate width
			$booking_end_minutes = $booking['minutes_from_start'] + $booking_duration;
			$is_capped = false;
			if ( $booking_end_minutes > $total_minutes ) {
				$booking_end_minutes = $total_minutes;
				$is_capped = true;
			}
			$actual_booking_width = $booking_end_minutes - $booking['minutes_from_start'];
			$width_percent = ( $actual_booking_width / $total_minutes ) * 100;

			// Display text based on config
			$display_text = '';
			if ( $config['show_names'] ) {
				$display_text = $booking['room'] === 'Non-Resident' ? $booking['name'] : $booking['name'];
				if ( $config['show_room_numbers'] && $booking['room'] !== 'Non-Resident' ) {
					$display_text .= ' - ' . $booking['room'];
				}
			}

			// Prepare data attributes
			$notes_json = wp_json_encode( $booking['notes'] );
			$tables_json = wp_json_encode( $booking['tables'] );

			$bar_class = 'gantt-booking-bar' . ( $is_capped ? ' gantt-bar-capped' : '' );

			$html .= sprintf(
				'<div class="%s" data-name="%s" data-people="%d" data-time="%s" data-room="%s" data-notes=\'%s\' data-tables=\'%s\' style="left: %s%%; top: %dpx; width: %s%%; height: %dpx;">',
				esc_attr( $bar_class ),
				esc_attr( $booking['name'] ),
				esc_attr( $booking['people'] ),
				esc_attr( $booking['time'] ),
				esc_attr( $booking['room'] ),
				esc_attr( $notes_json ),
				esc_attr( $tables_json ),
				esc_attr( $left_percent ),
				esc_attr( $y_position ),
				esc_attr( $width_percent ),
				esc_attr( $bar_height )
			);

			$html .= '<span class="gantt-party-size">' . esc_html( $booking['people'] ) . '</span>';

			if ( ! empty( $display_text ) ) {
				$html .= '<span class="gantt-bar-text" style="font-size: ' . esc_attr( $config['font_size'] ) . 'px;">' . esc_html( $display_text ) . '</span>';
			}

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Build closed blocks (grey overlays for unavailable times)
	 *
	 * @param int $start_hour Starting hour
	 * @param int $end_hour Ending hour
	 * @param int $total_minutes Total minutes in chart
	 * @param int $total_height Total chart height
	 * @return string HTML for closed blocks
	 */
	private function build_closed_blocks( $start_hour, $end_hour, $total_minutes, $total_height ) {
		$html = '';

		// Blocks for times outside opening hours
		if ( ! empty( $this->opening_hours ) ) {
			$sorted_hours = $this->opening_hours;
			usort( $sorted_hours, function( $a, $b ) {
				$a_open = isset( $a['open'] ) ? $a['open'] : 0;
				$b_open = isset( $b['open'] ) ? $b['open'] : 0;
				return $a_open - $b_open;
			});

			// Block from start to first opening
			$first_open = isset( $sorted_hours[0]['open'] ) ? $sorted_hours[0]['open'] : 1800;
			$first_open_minutes = floor( $first_open / 100 ) * 60 + ( $first_open % 100 );
			$minutes_from_chart_start = $first_open_minutes - ( $start_hour * 60 );

			if ( $minutes_from_chart_start > 0 ) {
				$width_percent = ( $minutes_from_chart_start / $total_minutes ) * 100;
				$html .= sprintf(
					'<div class="gantt-closed-block outside-hours" style="left: 0%%; width: %s%%; height: %dpx;"></div>',
					esc_attr( $width_percent ),
					esc_attr( $total_height )
				);
			}

			// Blocks between periods
			for ( $i = 0; $i < count( $sorted_hours ) - 1; $i++ ) {
				$current_close = isset( $sorted_hours[ $i ]['close'] ) ? $sorted_hours[ $i ]['close'] : 2200;
				$next_open = isset( $sorted_hours[ $i + 1 ]['open'] ) ? $sorted_hours[ $i + 1 ]['open'] : 1800;

				$close_minutes = floor( $current_close / 100 ) * 60 + ( $current_close % 100 );
				$open_minutes = floor( $next_open / 100 ) * 60 + ( $next_open % 100 );

				$gap_start = $close_minutes - ( $start_hour * 60 );
				$gap_end = $open_minutes - ( $start_hour * 60 );
				$gap_duration = $gap_end - $gap_start;

				if ( $gap_duration > 0 ) {
					$left_percent = ( $gap_start / $total_minutes ) * 100;
					$width_percent = ( $gap_duration / $total_minutes ) * 100;
					$html .= sprintf(
						'<div class="gantt-closed-block outside-hours" style="left: %s%%; width: %s%%; height: %dpx;"></div>',
						esc_attr( $left_percent ),
						esc_attr( $width_percent ),
						esc_attr( $total_height )
					);
				}
			}

			// Block from last close to end
			$last_close = isset( $sorted_hours[ count( $sorted_hours ) - 1 ]['close'] ) ? $sorted_hours[ count( $sorted_hours ) - 1 ]['close'] : 2200;
			$last_close_minutes = floor( $last_close / 100 ) * 60 + ( $last_close % 100 );
			$minutes_from_close = ( $end_hour * 60 ) - $last_close_minutes;

			if ( $minutes_from_close > 0 ) {
				$left_percent = ( ( $last_close_minutes - ( $start_hour * 60 ) ) / $total_minutes ) * 100;
				$width_percent = ( $minutes_from_close / $total_minutes ) * 100;
				$html .= sprintf(
					'<div class="gantt-closed-block outside-hours" style="left: %s%%; width: %s%%; height: %dpx;"></div>',
					esc_attr( $left_percent ),
					esc_attr( $width_percent ),
					esc_attr( $total_height )
				);
			}
		}

		// Block for entire day if online booking closed
		if ( ! $this->online_booking_available ) {
			$html .= sprintf(
				'<div class="gantt-closed-block outside-hours" style="left: 0%%; width: 100%%; height: %dpx;"></div>',
				esc_attr( $total_height )
			);
		}

		// Blocks for special events (restrictions/closures)
		foreach ( $this->special_events as $event ) {
			if ( isset( $event['isOpen'] ) && $event['isOpen'] === true ) {
				continue; // Skip open events
			}

			// Full day closure
			if ( empty( $event['open'] ) && empty( $event['close'] ) ) {
				$html .= sprintf(
					'<div class="gantt-closed-block outside-hours" style="left: 0%%; width: 100%%; height: %dpx;"></div>',
					esc_attr( $total_height )
				);
				continue;
			}

			// Partial closure
			if ( isset( $event['open'] ) && isset( $event['close'] ) ) {
				$event_open_minutes = floor( $event['open'] / 100 ) * 60 + ( $event['open'] % 100 );
				$event_close_minutes = floor( $event['close'] / 100 ) * 60 + ( $event['close'] % 100 );

				$block_start = $event_open_minutes - ( $start_hour * 60 );
				$block_end = $event_close_minutes - ( $start_hour * 60 );
				$block_duration = $block_end - $block_start;

				if ( $block_start < $total_minutes && $block_end > 0 ) {
					$block_start = max( 0, $block_start );
					$block_end = min( $total_minutes, $block_end );
					$block_duration = $block_end - $block_start;

					if ( $block_duration > 0 ) {
						$left_percent = ( $block_start / $total_minutes ) * 100;
						$width_percent = ( $block_duration / $total_minutes ) * 100;
						$html .= sprintf(
							'<div class="gantt-closed-block outside-hours" style="left: %s%%; width: %s%%; height: %dpx;"></div>',
							esc_attr( $left_percent ),
							esc_attr( $width_percent ),
							esc_attr( $total_height )
						);
					}
				}
			}
		}

		// Blocks for unavailable time slots (fully booked)
		if ( ! empty( $this->opening_hours ) && ! empty( $this->available_times ) ) {
			$available_set = array_flip( $this->available_times );

			foreach ( $this->opening_hours as $period ) {
				$period_start = isset( $period['open'] ) ? $period['open'] : 1800;
				$period_close = isset( $period['close'] ) ? $period['close'] : 2200;
				$interval = isset( $period['interval'] ) ? $period['interval'] : 15;
				$duration = isset( $period['duration'] ) ? $period['duration'] : 120;

				// Calculate last seating time
				$close_hour = floor( $period_close / 100 );
				$close_min = $period_close % 100;
				$duration_hours = floor( $duration / 60 );
				$duration_mins = $duration % 60;

				$close_min -= $duration_mins;
				$close_hour -= $duration_hours;
				if ( $close_min < 0 ) {
					$close_min += 60;
					$close_hour--;
				}
				$last_seating = $close_hour * 100 + $close_min;

				// Generate all expected time slots
				$current_hour = floor( $period_start / 100 );
				$current_min = $period_start % 100;

				while ( true ) {
					$current_time = $current_hour * 100 + $current_min;
					if ( $current_time > $last_seating ) {
						break;
					}

					$time_str = $current_hour . ':' . ( $current_min < 10 ? '0' . $current_min : $current_min );

					// If NOT available, add grey block
					if ( ! isset( $available_set[ $time_str ] ) ) {
						$slot_minutes = ( $current_hour - $start_hour ) * 60 + $current_min;

						if ( $slot_minutes >= 0 && $slot_minutes < $total_minutes ) {
							$left_percent = ( $slot_minutes / $total_minutes ) * 100;
							$width_percent = ( $interval / $total_minutes ) * 100;
							$html .= sprintf(
								'<div class="gantt-closed-block" style="left: %s%%; width: %s%%; height: %dpx;"></div>',
								esc_attr( $left_percent ),
								esc_attr( $width_percent ),
								esc_attr( $total_height )
							);
						}
					}

					// Increment by interval
					$current_min += $interval;
					if ( $current_min >= 60 ) {
						$current_min -= 60;
						$current_hour++;
					}
				}
			}
		}

		return $html;
	}

	/**
	 * Build interval lines (vertical grid lines every 15 minutes)
	 *
	 * @param int $total_minutes Total minutes in chart
	 * @param int $total_height Total chart height
	 * @return string HTML for interval lines
	 */
	private function build_interval_lines( $total_minutes, $total_height ) {
		$html = '';

		for ( $m = 15; $m < $total_minutes; $m += 15 ) {
			$line_left_percent = ( $m / $total_minutes ) * 100;
			$html .= sprintf(
				'<div class="gantt-interval-line" style="left: %s%%; height: %dpx;"></div>',
				esc_attr( $line_left_percent ),
				esc_attr( $total_height )
			);
		}

		return $html;
	}

	/**
	 * Build time axis (half-hourly labels)
	 *
	 * @param int $start_hour Starting hour
	 * @param int $end_hour Ending hour
	 * @param int $total_minutes Total minutes in chart
	 * @return string HTML for time axis
	 */
	private function build_time_axis( $start_hour, $end_hour, $total_minutes ) {
		$html = '';

		for ( $h = $start_hour; $h < $end_hour; $h++ ) {
			// Hour marker
			$position1 = ( ( $h - $start_hour ) * 60 / $total_minutes ) * 100;
			$html .= sprintf(
				'<div class="gantt-time-label" style="left: %s%%;">%d:00</div>',
				esc_attr( $position1 ),
				esc_html( $h )
			);

			// Half-hour marker
			$position2 = ( ( $h - $start_hour ) * 60 + 30 ) / $total_minutes * 100;
			$html .= sprintf(
				'<div class="gantt-time-label" style="left: %s%%;">%d:30</div>',
				esc_attr( $position2 ),
				esc_html( $h )
			);
		}

		return $html;
	}

	/**
	 * Build Gantt controls (scroll arrows and title)
	 *
	 * @return string HTML for controls
	 */
	private function build_gantt_controls() {
		$html = '<div class="gantt-controls">';
		$html .= '<button class="gantt-scroll-btn" data-direction="left" data-chart-id="' . esc_attr( $this->chart_id ) . '">◄</button>';
		$html .= '<span class="gantt-title">Restaurant Timeline</span>';
		$html .= '<button class="gantt-scroll-btn" data-direction="right" data-chart-id="' . esc_attr( $this->chart_id ) . '">►</button>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Get viewport inline styles
	 *
	 * @return string CSS styles for viewport
	 */
	private function get_viewport_styles() {
		$config = $this->mode_config[ $this->display_mode ];
		$height = 120; // Default compact height

		if ( $this->display_mode === self::MODE_FULL ) {
			$height = 200;
		} elseif ( $this->display_mode === self::MODE_MEDIUM ) {
			$height = 160;
		}

		return 'overflow-x: auto; overflow-y: hidden; height: ' . $height . 'px; position: relative;';
	}

	/**
	 * Get inline CSS styles for the chart
	 *
	 * @param array $config Display mode configuration
	 * @return string HTML <style> tag with CSS
	 */
	private function get_inline_styles( $config ) {
		$opacity = $this->display_mode === self::MODE_COMPACT ? '0.3' : '0.15';

		$css = '<style>
		.gantt-time-axis {
			position: relative;
			height: 30px;
			border-bottom: 1px solid #ddd;
			background: #f9f9f9;
		}
		.gantt-time-label {
			position: absolute;
			top: 8px;
			transform: translateX(-50%);
			font-size: 11px;
			color: #666;
			white-space: nowrap;
		}
		.gantt-bookings {
			position: relative;
			background: white;
		}
		.gantt-booking-bar {
			position: absolute;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			border-radius: 4px;
			border: 2px solid #5568d3;
			padding: 4px 8px;
			color: white;
			font-weight: 500;
			display: flex;
			align-items: center;
			gap: 6px;
			overflow: hidden;
			cursor: pointer;
			transition: transform 0.2s;
		}
		.gantt-booking-bar:hover {
			transform: scale(1.02);
			z-index: 10;
		}
		.gantt-party-size {
			background: rgba(255,255,255,0.3);
			border-radius: 3px;
			padding: 2px 6px;
			font-size: 11px;
			font-weight: bold;
			flex-shrink: 0;
		}
		.gantt-bar-text {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.gantt-closed-block {
			position: absolute;
			background: rgba(200, 200, 200, ' . $opacity . ');
			top: 0;
			pointer-events: none;
		}
		.gantt-closed-block.outside-hours {
			background: rgba(100, 100, 100, 0.1);
		}
		.gantt-interval-line {
			position: absolute;
			width: 1px;
			background: #e5e5e5;
			top: 0;
			pointer-events: none;
		}
		.gantt-sight-line {
			position: absolute;
			width: 2px;
			background: #ef4444;
			top: 0;
			z-index: 100;
			pointer-events: none;
		}
		.bma-gantt-container {
			border: 1px solid #e5e7eb;
			border-radius: 6px;
			overflow: hidden;
			margin-bottom: 16px;
		}
		.gantt-controls {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 8px 12px;
			background: #f9fafb;
			border-bottom: 1px solid #e5e7eb;
		}
		.gantt-scroll-btn {
			background: white;
			border: 1px solid #d1d5db;
			border-radius: 4px;
			padding: 4px 12px;
			cursor: pointer;
			font-size: 16px;
			transition: background 0.2s;
		}
		.gantt-scroll-btn:hover {
			background: #f3f4f6;
		}
		.gantt-title {
			font-size: 13px;
			font-weight: 500;
			color: #374151;
		}
		</style>';

		return $css;
	}

	/**
	 * Get initialization script for viewport scrolling
	 *
	 * @return string HTML <script> tag with JavaScript
	 */
	private function get_initialization_script() {
		$script = '<script>
		(function() {
			var viewport = document.getElementById("' . esc_js( $this->chart_id ) . '-viewport");
			if (!viewport) return;

			// Scroll to initial center time
			var centerTime = "' . esc_js( $this->initial_center_time ) . '";
			var timeMinutes = parseInt(centerTime.substring(0, 2)) * 60 + parseInt(centerTime.substring(2, 4));
			var viewportWidth = viewport.clientWidth;
			var totalDayMinutes = 24 * 60;
			var scrollPercentage = timeMinutes / totalDayMinutes;
			var scrollPosition = (viewport.scrollWidth * scrollPercentage) - (viewportWidth / 2);

			viewport.scrollTo({
				left: scrollPosition,
				behavior: "smooth"
			});

			// Attach scroll button handlers
			var scrollButtons = document.querySelectorAll(".gantt-scroll-btn[data-chart-id=\'' . esc_js( $this->chart_id ) . '\']");
			scrollButtons.forEach(function(btn) {
				btn.addEventListener("click", function() {
					var direction = this.dataset.direction;
					var scrollAmount = direction === "left" ? -60 : 60; // 1 hour in minutes
					var pixelsPerMinute = viewport.scrollWidth / totalDayMinutes;

					viewport.scrollBy({
						left: scrollAmount * pixelsPerMinute,
						behavior: "smooth"
					});
				});
			});
		})();
		</script>';

		return $script;
	}
}
