<?php
/**
 * Service: Plugin Settings & Status Management
 *
 * Manages plugin configuration, options persistence, status lifecycle (CRUD,
 * reordering, color setting, activation toggle), and safe deletion guardrails.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/services
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TPM_Settings_Service
 *
 * Provides central access to general options, status taxonomy configuration,
 * attachment limits, and archive/uninstall preferences.
 *
 * @since 1.0.0
 */
class TPM_Settings_Service {

	/**
	 * Option key for plugin settings in wp_options.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'tpm_settings';

	/**
	 * Retrieve default system settings.
	 *
	 * @since  1.0.0
	 * @return array Default configuration array.
	 */
	public static function get_defaults(): array {
		return array(
			// General Settings
			'currency'                 => 'INR',
			'currency_symbol'          => '₹',
			'date_format'              => 'Y-m-d',
			'records_per_page'         => 20,

			// Attachment Settings
			'allowed_file_types'       => 'pdf, doc, docx, xls, xlsx, jpg, jpeg, png, zip',
			'max_file_size_mb'         => 10,
			'attachment_labels'        => 'Tender Document, Quotation, Purchase Order, Technical Specs, Bill of Quantities, Commercial Offer, AWB / Shipping Doc, Invoice, Receipt, Warranty / Certificate, Other',

			// Archive Settings
			'hide_archived_default'    => 1,
			'permanent_delete_cap'     => 'manage_options',

			// Uninstall Settings
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * Retrieve current plugin settings merged with defaults.
	 *
	 * @since  1.0.0
	 * @return array Saved settings merged with defaults.
	 */
	public static function get_settings(): array {
		$saved    = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Retrieve a single setting by key.
	 *
	 * @since  1.0.0
	 * @param  string $key     Setting key.
	 * @param  mixed  $default Fallback value if setting is not set.
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Retrieve current currency symbol.
	 *
	 * @since 1.0.0
	 * @return string Currency symbol (default '₹').
	 */
	public static function get_currency_symbol(): string {
		$symbol = (string) self::get_setting( 'currency_symbol', '₹' );
		if ( empty( $symbol ) ) {
			$symbol = '₹';
		}
		return apply_filters( 'tpm_currency_symbol', $symbol );
	}

	/**
	 * Retrieve current currency code.
	 *
	 * @since 1.0.0
	 * @return string Currency code (default 'INR').
	 */
	public static function get_currency_code(): string {
		$code = (string) self::get_setting( 'currency', 'INR' );
		return ! empty( $code ) ? $code : 'INR';
	}

	/**
	 * Format an amount with current currency symbol and two decimal places.
	 *
	 * @since 1.0.0
	 * @param float|int|string $amount Amount to format.
	 * @return string Formatted price string (e.g. '₹1,234.56').
	 */
	public static function format_price( $amount ): string {
		$val    = (float) $amount;
		$symbol = self::get_currency_symbol();
		return $symbol . number_format( $val, 2 );
	}

	/**
	 * Update plugin settings with validation and sanitization.
	 *
	 * @since  1.0.0
	 * @param  array $input Raw inputs.
	 * @return array Updated settings array.
	 */
	public static function update_settings( array $input ): array {
		$current  = self::get_settings();
		$defaults = self::get_defaults();

		$sanitized = array();

		// 1. General: Currency Code (e.g. INR, USD, EUR, GBP).
		if ( isset( $input['currency'] ) ) {
			$currency = strtoupper( sanitize_text_field( $input['currency'] ) );
			$sanitized['currency'] = ( ! empty( $currency ) && strlen( $currency ) <= 10 ) ? $currency : $defaults['currency'];
		} else {
			$sanitized['currency'] = $current['currency'];
		}

		// 2. General: Currency Symbol (e.g. $, €, £, ₹).
		if ( isset( $input['currency_symbol'] ) ) {
			$symbol = sanitize_text_field( $input['currency_symbol'] );
			$sanitized['currency_symbol'] = ! empty( $symbol ) ? $symbol : $defaults['currency_symbol'];
		} else {
			$sanitized['currency_symbol'] = $current['currency_symbol'];
		}

		// 3. General: Date Format.
		$valid_date_formats = array( 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd-m-Y', 'F j, Y', 'j F Y' );
		if ( isset( $input['date_format'] ) && in_array( $input['date_format'], $valid_date_formats, true ) ) {
			$sanitized['date_format'] = $input['date_format'];
		} else {
			$sanitized['date_format'] = $current['date_format'];
		}

		// 4. General: Records Per Page (min: 5, max: 100).
		if ( isset( $input['records_per_page'] ) ) {
			$per_page = absint( $input['records_per_page'] );
			if ( $per_page >= 5 && $per_page <= 100 ) {
				$sanitized['records_per_page'] = $per_page;
			} else {
				$sanitized['records_per_page'] = $defaults['records_per_page'];
			}
		} else {
			$sanitized['records_per_page'] = $current['records_per_page'];
		}

		// 5. Attachments: Allowed File Types.
		if ( isset( $input['allowed_file_types'] ) ) {
			$types_raw = sanitize_text_field( $input['allowed_file_types'] );
			$types_arr = array_filter( array_map( 'trim', explode( ',', strtolower( $types_raw ) ) ) );
			$sanitized['allowed_file_types'] = implode( ', ', $types_arr );
		} else {
			$sanitized['allowed_file_types'] = $current['allowed_file_types'];
		}

		// 6. Attachments: Maximum File Size (MB) (min: 1, max: 256).
		if ( isset( $input['max_file_size_mb'] ) ) {
			$max_mb = absint( $input['max_file_size_mb'] );
			if ( $max_mb >= 1 && $max_mb <= 256 ) {
				$sanitized['max_file_size_mb'] = $max_mb;
			} else {
				$sanitized['max_file_size_mb'] = $defaults['max_file_size_mb'];
			}
		} else {
			$sanitized['max_file_size_mb'] = $current['max_file_size_mb'];
		}

		// 7. Attachments: Allowed Attachment Labels.
		if ( isset( $input['attachment_labels'] ) ) {
			$labels_raw = sanitize_textarea_field( $input['attachment_labels'] );
			$labels_arr = array_filter( array_map( 'trim', preg_split( '/[,\r\n]+/', $labels_raw ) ) );
			$sanitized['attachment_labels'] = implode( ', ', $labels_arr );
		} else {
			$sanitized['attachment_labels'] = $current['attachment_labels'];
		}

		// 8. Archive: Hide Archived Records by Default.
		$sanitized['hide_archived_default'] = ! empty( $input['hide_archived_default'] ) ? 1 : 0;

		// 9. Archive: Permanent Delete Permission.
		$valid_caps = array( 'manage_options', 'manage_tpm_records' );
		if ( isset( $input['permanent_delete_cap'] ) && in_array( $input['permanent_delete_cap'], $valid_caps, true ) ) {
			$sanitized['permanent_delete_cap'] = $input['permanent_delete_cap'];
		} else {
			$sanitized['permanent_delete_cap'] = $current['permanent_delete_cap'];
		}

		// 10. Uninstall: Delete Data on Uninstall.
		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0;

		update_option( self::OPTION_NAME, $sanitized );

		return $sanitized;
	}

	// =========================================================================
	// STATUS MANAGEMENT (wp_tpm_statuses)
	// =========================================================================

	/**
	 * In-memory runtime cache for status queries.
	 *
	 * @var array<string, array>
	 */
	private static array $status_cache = array();

	/**
	 * Invalidate in-memory runtime cache for statuses.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_statuses_cache(): void {
		self::$status_cache = array();
	}

	/**
	 * Retrieve list of statuses with in-memory caching.
	 *
	 * @since  1.0.0
	 * @param  array $args Filter arguments (record_type, is_active).
	 * @return array List of status records.
	 */
	public static function get_statuses( array $args = array() ): array {
		$cache_key = md5( serialize( $args ) );
		if ( isset( self::$status_cache[ $cache_key ] ) ) {
			return self::$status_cache[ $cache_key ];
		}

		global $wpdb;

		$tables  = TPM_Database::get_table_names();
		$clauses = array( '1=1' );
		$params  = array();

		if ( isset( $args['is_active'] ) ) {
			$clauses[] = 'is_active = %d';
			$params[]  = (int) $args['is_active'];
		}

		if ( ! empty( $args['record_type'] ) && 'all' !== $args['record_type'] ) {
			// 'both' / 'general' statuses apply to all record types.
			$clauses[] = '(record_type = %s OR record_type = "both" OR record_type = "general")';
			$params[]  = sanitize_key( $args['record_type'] );
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $clauses );
		$sql       = "SELECT * FROM {$tables['statuses']} {$where_sql} ORDER BY sort_order ASC, id ASC";

		$results = $wpdb->get_results(
			$params ? $wpdb->prepare( $sql, $params ) : $sql,
			ARRAY_A
		);

		$status_list = $results ?: array();
		self::$status_cache[ $cache_key ] = $status_list;
		return $status_list;
	}

	/**
	 * Retrieve a single status by ID.
	 *
	 * @since  1.0.0
	 * @param  int $id Status ID.
	 * @return array|null Status data or null if not found.
	 */
	public static function get_status( int $id ): ?array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();
		$row    = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tables['statuses']} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Retrieve a status by slug.
	 *
	 * @since  1.0.0
	 * @param  string $slug Status slug.
	 * @return array|null Status data or null.
	 */
	public static function get_status_by_slug( string $slug ): ?array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();
		$row    = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tables['statuses']} WHERE status_slug = %s", sanitize_key( $slug ) ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Add a new status to the system.
	 *
	 * @since  1.0.0
	 * @param  array $data Status parameters.
	 * @return int|WP_Error Inserted status ID or WP_Error on failure.
	 */
	public static function add_status( array $data ) {
		global $wpdb;

		$tables = TPM_Database::get_table_names();

		$name = sanitize_text_field( $data['status_name'] ?? '' );
		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', __( 'Status name is required.', 'tender-po-management' ) );
		}

		// Derive or sanitize slug.
		$slug = ! empty( $data['status_slug'] ) ? sanitize_title( $data['status_slug'] ) : sanitize_title( $name );
		if ( empty( $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'A valid status slug is required.', 'tender-po-management' ) );
		}

		// Check uniqueness of slug.
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$tables['statuses']} WHERE status_slug = %s", $slug )
		);
		if ( $exists ) {
			return new WP_Error( 'duplicate_slug', sprintf( __( 'A status with the slug "%s" already exists.', 'tender-po-management' ), $slug ) );
		}

		// Record type assignment: 'tender', 'po', or 'both'.
		$record_type = sanitize_key( $data['record_type'] ?? 'both' );
		if ( ! in_array( $record_type, array( 'tender', 'po', 'both', 'general' ), true ) ) {
			$record_type = 'both';
		}

		// Color code validation (Hex format #rrggbb or #rgb).
		$color = sanitize_text_field( $data['status_color'] ?? '#646970' );
		if ( ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
			$color = '#646970';
		}

		$sort_order = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		if ( 0 === $sort_order ) {
			$max_order  = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$tables['statuses']}" );
			$sort_order = $max_order + 10;
		}

		$result = $wpdb->insert(
			$tables['statuses'],
			array(
				'status_name'  => $name,
				'status_slug'  => $slug,
				'record_type'  => $record_type,
				'status_color' => $color,
				'sort_order'   => $sort_order,
				'is_active'    => 1,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to save new status to database.', 'tender-po-management' ) );
		}

		self::clear_statuses_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing status.
	 *
	 * @since  1.0.0
	 * @param  int   $id   Status ID.
	 * @param  array $data Updated fields.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function update_status( int $id, array $data ) {
		global $wpdb;

		$tables   = TPM_Database::get_table_names();
		$existing = self::get_status( $id );
		if ( ! $existing ) {
			return new WP_Error( 'status_not_found', __( 'Status record not found.', 'tender-po-management' ) );
		}

		$update_data   = array();
		$update_format = array();

		// Name
		if ( isset( $data['status_name'] ) ) {
			$name = sanitize_text_field( $data['status_name'] );
			if ( empty( $name ) ) {
				return new WP_Error( 'empty_name', __( 'Status name cannot be empty.', 'tender-po-management' ) );
			}
			$update_data['status_name'] = $name;
			$update_format[]            = '%s';
		}

		// Slug
		if ( isset( $data['status_slug'] ) ) {
			$new_slug = sanitize_title( $data['status_slug'] );
			if ( empty( $new_slug ) ) {
				return new WP_Error( 'empty_slug', __( 'Status slug cannot be empty.', 'tender-po-management' ) );
			}

			// Check uniqueness if slug is changing
			if ( $new_slug !== $existing['status_slug'] ) {
				$duplicate = $wpdb->get_var(
					$wpdb->prepare( "SELECT id FROM {$tables['statuses']} WHERE status_slug = %s AND id != %d", $new_slug, $id )
				);
				if ( $duplicate ) {
					return new WP_Error( 'duplicate_slug', sprintf( __( 'Slug "%s" is already taken.', 'tender-po-management' ), $new_slug ) );
				}

				// If slug changes, update records referencing old slug
				$wpdb->update(
					$tables['records'],
					array( 'status' => $new_slug ),
					array( 'status' => $existing['status_slug'] ),
					array( '%s' ),
					array( '%s' )
				);

				$update_data['status_slug'] = $new_slug;
				$update_format[]            = '%s';
			}
		}

		// Record Type Assignment
		if ( isset( $data['record_type'] ) ) {
			$record_type = sanitize_key( $data['record_type'] );
			if ( in_array( $record_type, array( 'tender', 'po', 'both', 'general' ), true ) ) {
				$update_data['record_type'] = $record_type;
				$update_format[]            = '%s';
			}
		}

		// Status Color
		if ( isset( $data['status_color'] ) ) {
			$color = sanitize_text_field( $data['status_color'] );
			if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
				$update_data['status_color'] = $color;
				$update_format[]             = '%s';
			}
		}

		// Sort Order
		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = (int) $data['sort_order'];
			$update_format[]           = '%d';
		}

		// Active toggle
		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = ! empty( $data['is_active'] ) ? 1 : 0;
			$update_format[]          = '%d';
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$tables['statuses'],
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		self::clear_statuses_cache();

		return ( false !== $result );
	}

	/**
	 * Toggle status active state (enable / disable).
	 *
	 * @since  1.0.0
	 * @param  int $id Status ID.
	 * @return bool|WP_Error
	 */
	public static function toggle_status_active( int $id ) {
		$status = self::get_status( $id );
		if ( ! $status ) {
			return new WP_Error( 'not_found', __( 'Status not found.', 'tender-po-management' ) );
		}

		$new_active = empty( $status['is_active'] ) ? 1 : 0;
		return self::update_status( $id, array( 'is_active' => $new_active ) );
	}

	/**
	 * Reorder statuses by updating sort_order values.
	 *
	 * @since  1.0.0
	 * @param  array $order_map Array of status_id => sort_order.
	 * @return bool
	 */
	public static function reorder_statuses( array $order_map ): bool {
		global $wpdb;

		$tables = TPM_Database::get_table_names();

		foreach ( $order_map as $id => $order ) {
			$wpdb->update(
				$tables['statuses'],
				array( 'sort_order' => (int) $order ),
				array( 'id' => absint( $id ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		self::clear_statuses_cache();

		return true;
	}

	/**
	 * Count records in wp_tpm_records currently assigned to a status slug.
	 *
	 * @since  1.0.0
	 * @param  string $status_slug Status slug.
	 * @return int Record count.
	 */
	public static function get_records_count_with_status( string $status_slug ): int {
		global $wpdb;

		$tables = TPM_Database::get_table_names();
		$count  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['records']} WHERE status = %s",
				sanitize_key( $status_slug )
			)
		);

		return (int) $count;
	}

	/**
	 * Delete a status with guardrail protection against deleting in-use statuses.
	 *
	 * @since  1.0.0
	 * @param  int  $id    Status ID.
	 * @param  bool $force Set to true to override in-use warning.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_status( int $id, bool $force = false ) {
		global $wpdb;

		$tables = TPM_Database::get_table_names();
		$status = self::get_status( $id );
		if ( ! $status ) {
			return new WP_Error( 'not_found', __( 'Status not found.', 'tender-po-management' ) );
		}

		// Check if any records currently use this status
		$records_count = self::get_records_count_with_status( $status['status_slug'] );
		if ( $records_count > 0 && ! $force ) {
			return new WP_Error(
				'status_in_use',
				sprintf(
					/* translators: 1: Status name, 2: Number of records using this status */
					__( 'Cannot delete status "%1$s" because it is currently in use by %2$d active record(s). Please reassign those records before deleting this status, or disable it instead.', 'tender-po-management' ),
					esc_html( $status['status_name'] ),
					$records_count
				)
			);
		}

		$deleted = $wpdb->delete(
			$tables['statuses'],
			array( 'id' => $id ),
			array( '%d' )
		);

		self::clear_statuses_cache();

		return ( false !== $deleted );
	}
}
