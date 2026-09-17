<?php
/**
 * Record Creation and Management Service.
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
 * Record Creation and Management Service.
 *
 * Handles validation, sanitization, database persistence, attachment linking,
 * and activity audit logging for Tenders and Purchase Orders.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/services
 * @since      1.0.0
 */
class TPM_Record_Service {

	/**
	 * Validate record input data.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $data Raw or partially sanitized input.
	 * @return array<string> List of validation error messages. Empty array if valid.
	 */
	public static function validate( array $data ): array {
		global $wpdb;
		$errors = array();

		// 1. Record Type (Required: 'tender' or 'po')
		$record_type = sanitize_key( $data['record_type'] ?? '' );
		if ( ! in_array( $record_type, array( 'tender', 'po' ), true ) ) {
			$errors[] = __( 'Please select a valid Record Type (Tender or Purchase Order).', 'tender-po-management' );
		}

		// 2. Status (Required)
		$status = sanitize_text_field( $data['status'] ?? '' );
		if ( empty( $status ) ) {
			$errors[] = __( 'Please select a Status for this record.', 'tender-po-management' );
		}

		// 3. Tender Number uniqueness (if provided and record is tender)
		$tender_number = sanitize_text_field( $data['tender_number'] ?? '' );
		if ( 'tender' === $record_type && ! empty( $tender_number ) ) {
			$table = $wpdb->prefix . 'tpm_records';
			$record_id = absint( $data['id'] ?? 0 );
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$table}` WHERE tender_number = %s AND id != %d AND is_archived = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$tender_number,
					$record_id
				)
			);
			if ( $existing ) {
				$errors[] = sprintf(
					/* translators: %s: Tender reference number */
					__( 'Tender number "%s" is already in use by another active record.', 'tender-po-management' ),
					esc_html( $tender_number )
				);
			}
		}

		// 4. Numeric Financials (Must be non-negative numeric)
		$numeric_fields = array(
			'purchase_price'      => __( 'Purchase Price', 'tender-po-management' ),
			'sell_price'          => __( 'Sell Price', 'tender-po-management' ),
			'expenses'            => __( 'Expenses', 'tender-po-management' ),
			'actual_payment'      => __( 'Actual Payment', 'tender-po-management' ),
			'balance_payment_due' => __( 'Balance Payment Due', 'tender-po-management' ),
		);

		foreach ( $numeric_fields as $field_key => $field_label ) {
			if ( isset( $data[ $field_key ] ) && '' !== trim( (string) $data[ $field_key ] ) ) {
				$val = str_replace( array( ',', ' ' ), '', (string) $data[ $field_key ] );
				if ( ! is_numeric( $val ) || (float) $val < 0 ) {
					$errors[] = sprintf(
						/* translators: %s: Field label */
						__( '%s must be a valid positive number.', 'tender-po-management' ),
						$field_label
					);
				}
			}
		}

		// 5. Partner Percentage (0 to 100)
		if ( isset( $data['partner_percentage'] ) && '' !== trim( (string) $data['partner_percentage'] ) ) {
			$raw_pct = str_replace( array( ',', ' ' ), '', (string) $data['partner_percentage'] );
			if ( ! is_numeric( $raw_pct ) ) {
				$errors[] = __( 'Partner Percentage must be a valid number between 0% and 100%.', 'tender-po-management' );
			} else {
				$pct = (float) $raw_pct;
				if ( $pct < 0 || $pct > 100 ) {
					$errors[] = __( 'Partner Percentage must be between 0% and 100%.', 'tender-po-management' );
				}
			}
		}

		// 5b. Partner Company validation (if provided)
		if ( ! empty( $data['partner_company_id'] ) ) {
			$partner_table  = $wpdb->prefix . 'tpm_partner_companies';
			$partner_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$partner_table}` WHERE id = %d", absint( $data['partner_company_id'] ) ) );
			if ( ! $partner_exists ) {
				$errors[] = __( 'Selected Partner Company does not exist.', 'tender-po-management' );
			}
		}


		// 6. Dates format validation (YYYY-MM-DD or empty)
		$date_fields = array(
			'tender_due_date'        => __( 'Tender Due Date', 'tender-po-management' ),
			'tender_submission_date' => __( 'Tender Submission Date', 'tender-po-management' ),
			'quote_expiration_date'  => __( 'Quote Expiration Date', 'tender-po-management' ),
			'po_date'                => __( 'PO Date', 'tender-po-management' ),
			'payment_due_date'       => __( 'Payment Due Date', 'tender-po-management' ),
			'shipping_date'          => __( 'Shipping Date', 'tender-po-management' ),
			'settled_date'           => __( 'Settled Date', 'tender-po-management' ),
		);

		foreach ( $date_fields as $date_key => $date_label ) {
			$date_val = trim( (string) ( $data[ $date_key ] ?? '' ) );
			if ( ! empty( $date_val ) ) {
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_val ) || false === strtotime( $date_val ) ) {
					$errors[] = sprintf(
						/* translators: %s: Date field label */
						__( '%s contains an invalid date format (expected YYYY-MM-DD).', 'tender-po-management' ),
						$date_label
					);
				}
			}
		}

		// 7. Attachments: must be positive integers
		if ( ! empty( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
			foreach ( $data['attachments'] as $att ) {
				$att_id = absint( $att['attachment_id'] ?? 0 );
				if ( $att_id <= 0 ) {
					$errors[] = __( 'Invalid attachment reference provided.', 'tender-po-management' );
					break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Create a new Tender or Purchase Order record.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $raw_data Input submission data.
	 * @return int|WP_Error Record ID on success, or WP_Error on failure.
	 */
	public static function create( array $raw_data ) {
		global $wpdb;

		// 1. Validate inputs
		$validation_errors = self::validate( $raw_data );
		if ( ! empty( $validation_errors ) ) {
			return new WP_Error( 'validation_failed', implode( '<br>', $validation_errors ) );
		}

		$current_user_id = get_current_user_id() ?: 1;
		$now = current_time( 'mysql' );

		// 2. Sanitize and prepare record fields
		$record_type = sanitize_key( $raw_data['record_type'] ?? 'tender' );
		$status      = sanitize_text_field( $raw_data['status'] ?? 'draft' );

		$tender_number        = ! empty( $raw_data['tender_number'] ) ? sanitize_text_field( $raw_data['tender_number'] ) : null;
		$fill_ref_number      = ! empty( $raw_data['fill_ref_number'] ) ? sanitize_text_field( $raw_data['fill_ref_number'] ) : null;
		$customer_id          = ! empty( $raw_data['customer_id'] ) ? absint( $raw_data['customer_id'] ) : null;
		$customer_po_number   = ! empty( $raw_data['customer_po_number'] ) ? sanitize_text_field( $raw_data['customer_po_number'] ) : null;
		$customer_po_name     = ! empty( $raw_data['customer_po_name'] ) ? sanitize_text_field( $raw_data['customer_po_name'] ) : null;
		$item_description     = ! empty( $raw_data['item_description'] ) ? wp_kses_post( $raw_data['item_description'] ) : null;

		$purchase_price       = ! empty( $raw_data['purchase_price'] ) ? (float) str_replace( array( ',', ' ' ), '', $raw_data['purchase_price'] ) : 0.00;
		$sell_price           = ! empty( $raw_data['sell_price'] ) ? (float) str_replace( array( ',', ' ' ), '', $raw_data['sell_price'] ) : 0.00;
		$expenses             = ! empty( $raw_data['expenses'] ) ? (float) str_replace( array( ',', ' ' ), '', $raw_data['expenses'] ) : 0.00;
		$actual_payment       = ! empty( $raw_data['actual_payment'] ) ? (float) str_replace( array( ',', ' ' ), '', $raw_data['actual_payment'] ) : 0.00;

		// Calculate remaining balance payment if not explicitly overridden
		if ( isset( $raw_data['balance_payment_due'] ) && '' !== trim( (string) $raw_data['balance_payment_due'] ) ) {
			$balance_payment_due = (float) str_replace( array( ',', ' ' ), '', $raw_data['balance_payment_due'] );
		} else {
			$balance_payment_due = max( 0.00, $sell_price - $actual_payment );
		}

		$partner_company_id   = ! empty( $raw_data['partner_company_id'] ) ? absint( $raw_data['partner_company_id'] ) : null;
		$partner_percentage   = ! empty( $raw_data['partner_percentage'] ) ? (float) $raw_data['partner_percentage'] : 0.00;

		$tender_due_date        = ! empty( $raw_data['tender_due_date'] ) ? sanitize_text_field( $raw_data['tender_due_date'] ) : null;
		$tender_submission_date = ! empty( $raw_data['tender_submission_date'] ) ? sanitize_text_field( $raw_data['tender_submission_date'] ) : null;
		$quote_expiration_date  = ! empty( $raw_data['quote_expiration_date'] ) ? sanitize_text_field( $raw_data['quote_expiration_date'] ) : null;
		$po_date                = ! empty( $raw_data['po_date'] ) ? sanitize_text_field( $raw_data['po_date'] ) : null;
		$payment_due_date       = ! empty( $raw_data['payment_due_date'] ) ? sanitize_text_field( $raw_data['payment_due_date'] ) : null;
		$shipping_date          = ! empty( $raw_data['shipping_date'] ) ? sanitize_text_field( $raw_data['shipping_date'] ) : null;
		$settled_date           = ! empty( $raw_data['settled_date'] ) ? sanitize_text_field( $raw_data['settled_date'] ) : null;

		$hs_code              = ! empty( $raw_data['hs_code'] ) ? sanitize_text_field( $raw_data['hs_code'] ) : null;
		$tracking_number      = ! empty( $raw_data['tracking_number'] ) ? sanitize_text_field( $raw_data['tracking_number'] ) : null;
		$ff_location          = ! empty( $raw_data['ff_location'] ) ? sanitize_text_field( $raw_data['ff_location'] ) : null;
		$fill_invoice_number  = ! empty( $raw_data['fill_invoice_number'] ) ? sanitize_text_field( $raw_data['fill_invoice_number'] ) : null;
		$fill_po              = ! empty( $raw_data['fill_po'] ) ? sanitize_text_field( $raw_data['fill_po'] ) : null;
		$original_awb_received = ! empty( $raw_data['original_awb_received'] ) ? 1 : 0;
		$notes                = ! empty( $raw_data['notes'] ) ? wp_kses_post( $raw_data['notes'] ) : null;

		$table = $wpdb->prefix . 'tpm_records';

		$record_row = array(
			'record_type'           => $record_type,
			'status'                => $status,
			'tender_number'         => $tender_number,
			'fill_ref_number'       => $fill_ref_number,
			'customer_id'           => $customer_id,
			'customer_po_number'    => $customer_po_number,
			'customer_po_name'      => $customer_po_name,
			'item_description'      => $item_description,
			'purchase_price'        => $purchase_price,
			'sell_price'            => $sell_price,
			'expenses'              => $expenses,
			'actual_payment'        => $actual_payment,
			'balance_payment_due'   => $balance_payment_due,
			'partner_company_id'    => $partner_company_id,
			'partner_percentage'    => $partner_percentage,
			'tender_due_date'       => $tender_due_date,
			'tender_submission_date'=> $tender_submission_date,
			'quote_expiration_date' => $quote_expiration_date,
			'po_date'               => $po_date,
			'payment_due_date'      => $payment_due_date,
			'shipping_date'         => $shipping_date,
			'settled_date'          => $settled_date,
			'hs_code'               => $hs_code,
			'tracking_number'       => $tracking_number,
			'ff_location'           => $ff_location,
			'fill_invoice_number'   => $fill_invoice_number,
			'fill_po'               => $fill_po,
			'original_awb_received' => $original_awb_received,
			'notes'                 => $notes,
			'is_archived'           => 0,
			'created_by'            => $current_user_id,
			'updated_by'            => $current_user_id,
			'created_at'            => $now,
			'updated_at'            => $now,
		);

		$inserted = $wpdb->insert( $table, $record_row );

		if ( false === $inserted ) {
			return new WP_Error( 'db_insert_error', __( 'Database insert failed. Please try again.', 'tender-po-management' ) );
		}

		$record_id = (int) $wpdb->insert_id;

		// 3. Save Attachments
		if ( ! empty( $raw_data['attachments'] ) && is_array( $raw_data['attachments'] ) ) {
			self::save_attachments( $record_id, $raw_data['attachments'], $current_user_id, $now );
		}

		// 4. Create Activity Log
		$label = ( 'po' === $record_type ) ? __( 'Purchase Order', 'tender-po-management' ) : __( 'Tender', 'tender-po-management' );
		$description = ( 'po' === $record_type ) ? __( 'Purchase Order created successfully', 'tender-po-management' ) : __( 'Tender created successfully', 'tender-po-management' );

		self::log_activity(
			$record_id,
			$current_user_id,
			'record_created',
			$description,
			null,
			wp_json_encode( array( 'type' => $record_type, 'status' => $status, 'number' => $tender_number ?: $customer_po_number ) ),
			$now
		);

		return $record_id;
	}

	/**
	 * Update an existing Tender or Purchase Order record.
	 *
	 * Performs input validation, captures old state, detects changes, updates the database,
	 * logs detailed activity entries for modified fields, and manages attachments (preserves
	 * existing, deletes requested, adds new).
	 *
	 * @since 1.0.0
	 * @param int                  $record_id Target record ID.
	 * @param array<string, mixed> $raw_data  Updated input data.
	 * @param int                  $user_id   Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function update( int $record_id, array $raw_data, int $user_id ) {
		global $wpdb;

		// 1. Check record existence
		$old_record = self::get_record( $record_id );
		if ( ! $old_record ) {
			return new WP_Error( 'record_not_found', __( 'Record not found.', 'tender-po-management' ) );
		}

		// 2. Validate input data (set ID for unique check allowance)
		$raw_data['id'] = $record_id;
		$validation_errors = self::validate( $raw_data );
		if ( ! empty( $validation_errors ) ) {
			return new WP_Error( 'validation_failed', implode( '<br>', $validation_errors ) );
		}

		$table = $wpdb->prefix . 'tpm_records';
		$now   = current_time( 'mysql' );

		// 3. Prepare sanitized updated fields
		$record_type = sanitize_key( $raw_data['record_type'] ?? $old_record->record_type );
		$status      = sanitize_text_field( $raw_data['status'] ?? $old_record->status );

		$tender_number      = ( 'tender' === $record_type ) ? sanitize_text_field( $raw_data['tender_number'] ?? '' ) : null;
		$fill_ref_number    = sanitize_text_field( $raw_data['fill_ref_number'] ?? '' );
		$customer_id        = ! empty( $raw_data['customer_id'] ) ? absint( $raw_data['customer_id'] ) : null;
		$customer_po_number = sanitize_text_field( $raw_data['customer_po_number'] ?? '' );
		$customer_po_name   = sanitize_text_field( $raw_data['customer_po_name'] ?? '' );
		$item_description   = ! empty( $raw_data['item_description'] ) ? wp_kses_post( $raw_data['item_description'] ) : null;

		$purchase_price      = isset( $raw_data['purchase_price'] ) ? (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['purchase_price'] ) : 0.00;
		$sell_price          = isset( $raw_data['sell_price'] ) ? (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['sell_price'] ) : 0.00;
		$expenses            = isset( $raw_data['expenses'] ) ? (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['expenses'] ) : 0.00;
		$actual_payment      = isset( $raw_data['actual_payment'] ) ? (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['actual_payment'] ) : 0.00;
		$balance_payment_due = isset( $raw_data['balance_payment_due'] ) && '' !== trim( (string) $raw_data['balance_payment_due'] )
			? (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['balance_payment_due'] )
			: max( 0.00, $sell_price - $actual_payment );

		$partner_company_id = ! empty( $raw_data['partner_company_id'] ) ? absint( $raw_data['partner_company_id'] ) : null;
		$partner_percentage = isset( $raw_data['partner_percentage'] ) ? (float) $raw_data['partner_percentage'] : 0.00;

		$date_fields = array(
			'tender_due_date',
			'tender_submission_date',
			'quote_expiration_date',
			'po_date',
			'payment_due_date',
			'shipping_date',
			'settled_date',
		);

		$dates = array();
		foreach ( $date_fields as $df ) {
			$dates[ $df ] = ! empty( $raw_data[ $df ] ) ? sanitize_text_field( $raw_data[ $df ] ) : null;
		}

		$hs_code               = sanitize_text_field( $raw_data['hs_code'] ?? '' );
		$tracking_number       = sanitize_text_field( $raw_data['tracking_number'] ?? '' );
		$ff_location           = sanitize_text_field( $raw_data['ff_location'] ?? '' );
		$fill_invoice_number   = sanitize_text_field( $raw_data['fill_invoice_number'] ?? '' );
		$fill_po               = sanitize_text_field( $raw_data['fill_po'] ?? '' );
		$original_awb_received = ! empty( $raw_data['original_awb_received'] ) ? 1 : 0;
		$notes                 = ! empty( $raw_data['notes'] ) ? wp_kses_post( $raw_data['notes'] ) : null;

		$update_data = array(
			'record_type'            => $record_type,
			'status'                 => $status,
			'tender_number'          => $tender_number,
			'fill_ref_number'        => $fill_ref_number,
			'customer_id'            => $customer_id,
			'customer_po_number'     => $customer_po_number,
			'customer_po_name'       => $customer_po_name,
			'item_description'       => $item_description,
			'purchase_price'         => $purchase_price,
			'sell_price'             => $sell_price,
			'expenses'               => $expenses,
			'actual_payment'         => $actual_payment,
			'balance_payment_due'    => $balance_payment_due,
			'partner_company_id'     => $partner_company_id,
			'partner_percentage'     => $partner_percentage,
			'tender_due_date'        => $dates['tender_due_date'],
			'tender_submission_date' => $dates['tender_submission_date'],
			'quote_expiration_date'  => $dates['quote_expiration_date'],
			'po_date'                => $dates['po_date'],
			'payment_due_date'       => $dates['payment_due_date'],
			'shipping_date'          => $dates['shipping_date'],
			'settled_date'           => $dates['settled_date'],
			'hs_code'                => $hs_code,
			'tracking_number'        => $tracking_number,
			'ff_location'            => $ff_location,
			'fill_invoice_number'    => $fill_invoice_number,
			'fill_po'                => $fill_po,
			'original_awb_received'  => $original_awb_received,
			'notes'                  => $notes,
			'updated_by'             => $user_id,
			'updated_at'             => $now,
		);

		// 4. Change Detection (Compare old vs new values)
		$changes = array();

		// Status change
		if ( (string) $old_record->status !== (string) $status ) {
			$changes[] = array(
				'field' => 'status',
				'desc'  => sprintf( __( 'Status changed: %1$s → %2$s', 'tender-po-management' ), $old_record->status, $status ),
				'old'   => $old_record->status,
				'new'   => $status,
			);
		}

		// Record type change
		if ( (string) $old_record->record_type !== (string) $record_type ) {
			$changes[] = array(
				'field' => 'record_type',
				'desc'  => sprintf( __( 'Record type changed: %1$s → %2$s', 'tender-po-management' ), $old_record->record_type, $record_type ),
				'old'   => $old_record->record_type,
				'new'   => $record_type,
			);
		}

		// Tender number change
		if ( (string) $old_record->tender_number !== (string) $tender_number ) {
			$changes[] = array(
				'field' => 'tender_number',
				'desc'  => sprintf( __( 'Tender Number changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->tender_number, (string) $tender_number ),
				'old'   => (string) $old_record->tender_number,
				'new'   => (string) $tender_number,
			);
		}

		// Customer change
		if ( (int) $old_record->customer_id !== (int) $customer_id ) {
			$changes[] = array(
				'field' => 'customer_id',
				'desc'  => __( 'Customer changed', 'tender-po-management' ),
				'old'   => (string) $old_record->customer_id,
				'new'   => (string) $customer_id,
			);
		}

		// Customer PO number change
		if ( (string) $old_record->customer_po_number !== (string) $customer_po_number ) {
			$changes[] = array(
				'field' => 'customer_po_number',
				'desc'  => sprintf( __( 'Customer PO Number changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->customer_po_number, (string) $customer_po_number ),
				'old'   => (string) $old_record->customer_po_number,
				'new'   => (string) $customer_po_number,
			);
		}

		// Financial values change
		if ( abs( (float) $old_record->purchase_price - $purchase_price ) > 0.001 ) {
			$changes[] = array(
				'field' => 'purchase_price',
				'desc'  => sprintf( __( 'Purchase Price changed: %1$s → %2$s', 'tender-po-management' ), number_format( (float) $old_record->purchase_price, 2 ), number_format( $purchase_price, 2 ) ),
				'old'   => (string) $old_record->purchase_price,
				'new'   => (string) $purchase_price,
			);
		}

		if ( abs( (float) $old_record->sell_price - $sell_price ) > 0.001 ) {
			$changes[] = array(
				'field' => 'sell_price',
				'desc'  => sprintf( __( 'Sell Price changed: %1$s → %2$s', 'tender-po-management' ), number_format( (float) $old_record->sell_price, 2 ), number_format( $sell_price, 2 ) ),
				'old'   => (string) $old_record->sell_price,
				'new'   => (string) $sell_price,
			);
		}

		if ( abs( (float) $old_record->expenses - $expenses ) > 0.001 ) {
			$changes[] = array(
				'field' => 'expenses',
				'desc'  => sprintf( __( 'Expenses changed: %1$s → %2$s', 'tender-po-management' ), number_format( (float) $old_record->expenses, 2 ), number_format( $expenses, 2 ) ),
				'old'   => (string) $old_record->expenses,
				'new'   => (string) $expenses,
			);
		}

		// Dates change detection
		if ( (string) $old_record->tender_due_date !== (string) $dates['tender_due_date'] ) {
			$changes[] = array(
				'field' => 'tender_due_date',
				'desc'  => sprintf( __( 'Tender Due Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->tender_due_date, (string) $dates['tender_due_date'] ),
				'old'   => (string) $old_record->tender_due_date,
				'new'   => (string) $dates['tender_due_date'],
			);
		}

		if ( (string) $old_record->tender_submission_date !== (string) $dates['tender_submission_date'] ) {
			$changes[] = array(
				'field' => 'tender_submission_date',
				'desc'  => sprintf( __( 'Tender Submission Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->tender_submission_date, (string) $dates['tender_submission_date'] ),
				'old'   => (string) $old_record->tender_submission_date,
				'new'   => (string) $dates['tender_submission_date'],
			);
		}

		if ( (string) $old_record->po_date !== (string) $dates['po_date'] ) {
			$changes[] = array(
				'field' => 'po_date',
				'desc'  => sprintf( __( 'PO Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->po_date, (string) $dates['po_date'] ),
				'old'   => (string) $old_record->po_date,
				'new'   => (string) $dates['po_date'],
			);
		}

		if ( (string) $old_record->quote_expiration_date !== (string) $dates['quote_expiration_date'] ) {
			$changes[] = array(
				'field' => 'quote_expiration_date',
				'desc'  => sprintf( __( 'Quote Expiration Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->quote_expiration_date, (string) $dates['quote_expiration_date'] ),
				'old'   => (string) $old_record->quote_expiration_date,
				'new'   => (string) $dates['quote_expiration_date'],
			);
		}

		if ( (string) $old_record->payment_due_date !== (string) $dates['payment_due_date'] ) {
			$changes[] = array(
				'field' => 'payment_due_date',
				'desc'  => sprintf( __( 'Payment Due Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->payment_due_date, (string) $dates['payment_due_date'] ),
				'old'   => (string) $old_record->payment_due_date,
				'new'   => (string) $dates['payment_due_date'],
			);
		}

		if ( (string) $old_record->shipping_date !== (string) $dates['shipping_date'] ) {
			$changes[] = array(
				'field' => 'shipping_date',
				'desc'  => sprintf( __( 'Shipping Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->shipping_date, (string) $dates['shipping_date'] ),
				'old'   => (string) $old_record->shipping_date,
				'new'   => (string) $dates['shipping_date'],
			);
		}

		if ( (string) $old_record->settled_date !== (string) $dates['settled_date'] ) {
			$changes[] = array(
				'field' => 'settled_date',
				'desc'  => sprintf( __( 'Settled Date changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->settled_date, (string) $dates['settled_date'] ),
				'old'   => (string) $old_record->settled_date,
				'new'   => (string) $dates['settled_date'],
			);
		}

		// Tracking number change detection
		if ( (string) $old_record->tracking_number !== (string) $tracking_number ) {
			$changes[] = array(
				'field' => 'tracking_number',
				'desc'  => sprintf( __( 'Tracking Number changed: %1$s → %2$s', 'tender-po-management' ), (string) $old_record->tracking_number, (string) $tracking_number ),
				'old'   => (string) $old_record->tracking_number,
				'new'   => (string) $tracking_number,
			);
		}

		// Partner company change detection
		if ( (int) ( $old_record->partner_company_id ?? 0 ) !== (int) ( $partner_company_id ?? 0 ) ) {
			$partner_table = $wpdb->prefix . 'tpm_partner_companies';
			$old_name      = ! empty( $old_record->partner_company_id ) ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT company_name FROM `{$partner_table}` WHERE id = %d", (int) $old_record->partner_company_id ) ) : __( 'None', 'tender-po-management' );
			$new_name      = ! empty( $partner_company_id ) ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT company_name FROM `{$partner_table}` WHERE id = %d", (int) $partner_company_id ) ) : __( 'None', 'tender-po-management' );

			$changes[] = array(
				'field' => 'partner_company_id',
				'desc'  => sprintf( __( 'Partner Company changed: %1$s → %2$s', 'tender-po-management' ), $old_name, $new_name ),
				'old'   => (string) ( $old_record->partner_company_id ?? '' ),
				'new'   => (string) ( $partner_company_id ?? '' ),
			);
		}

		// Partner percentage change detection
		if ( abs( (float) $old_record->partner_percentage - $partner_percentage ) > 0.001 ) {
			$changes[] = array(
				'field' => 'partner_percentage',
				'desc'  => sprintf( __( 'Partner Percentage changed: %1$s%% → %2$s%%', 'tender-po-management' ), number_format( (float) $old_record->partner_percentage, 2 ), number_format( $partner_percentage, 2 ) ),
				'old'   => (string) $old_record->partner_percentage,
				'new'   => (string) $partner_percentage,
			);
		}

		// Notes change detection
		if ( (string) $old_record->notes !== (string) $notes ) {
			$changes[] = array(
				'field' => 'notes',
				'desc'  => __( 'Internal notes updated', 'tender-po-management' ),
				'old'   => (string) $old_record->notes,
				'new'   => (string) $notes,
			);
		}

		// Execute update
		$updated = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $record_id )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_update_error', __( 'Database update failed. Please try again.', 'tender-po-management' ) );
		}

		// 5. Manage Attachments:
		// Delete requested attachments
		if ( ! empty( $raw_data['deleted_attachments'] ) && is_array( $raw_data['deleted_attachments'] ) ) {
			foreach ( $raw_data['deleted_attachments'] as $del_att_row_id ) {
				$del_att_row_id = absint( $del_att_row_id );
				if ( $del_att_row_id > 0 ) {
					self::delete_attachment( $del_att_row_id, $user_id );
				}
			}
		}

		// Update existing attachments labels
		if ( ! empty( $raw_data['existing_attachment_labels'] ) && is_array( $raw_data['existing_attachment_labels'] ) ) {
			$table_att = $wpdb->prefix . 'tpm_attachments';
			foreach ( $raw_data['existing_attachment_labels'] as $att_row_id => $new_label ) {
				$att_row_id = absint( $att_row_id );
				$new_label  = sanitize_text_field( $new_label );
				if ( $att_row_id > 0 && ! empty( $new_label ) ) {
					$wpdb->update(
						$table_att,
						array( 'attachment_label' => $new_label ),
						array( 'id' => $att_row_id, 'record_id' => $record_id ),
						array( '%s' ),
						array( '%d', '%d' )
					);
				}
			}
		}

		// Add newly uploaded attachments
		if ( ! empty( $raw_data['attachments'] ) && is_array( $raw_data['attachments'] ) ) {
			self::add_attachments_to_record( $record_id, $raw_data['attachments'], $user_id );
		}

		// 6. Log change activities
		if ( ! empty( $changes ) ) {
			foreach ( $changes as $c ) {
				self::log_activity(
					$record_id,
					$user_id,
					'record_updated',
					$c['desc'],
					$c['old'],
					$c['new'],
					$now
				);
			}
		} else {
			self::log_activity(
				$record_id,
				$user_id,
				'record_updated',
				__( 'Record updated', 'tender-po-management' ),
				null,
				null,
				$now
			);
		}

		return true;
	}

	/**
	 * Save multiple attachments linked to a record.
	 *
	 * @since 1.0.0
	 * @param int                  $record_id       Target record ID.
	 * @param array<array<string>> $attachments     List of attachment objects.
	 * @param int                  $uploaded_by     User ID.
	 * @param string               $created_at      Timestamp.
	 * @return int Number of attachments saved.
	 */
	public static function save_attachments( int $record_id, array $attachments, int $uploaded_by, string $created_at ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_attachments';
		$saved = 0;

		foreach ( $attachments as $att ) {
			$attachment_id = absint( $att['attachment_id'] ?? 0 );
			if ( ! $attachment_id ) {
				continue;
			}

			// Validate attachment exists in WordPress Media Library.
			$post = get_post( $attachment_id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				continue;
			}

			$file_path        = get_attached_file( $attachment_id );
			$file_name        = ! empty( $att['file_name'] ) ? sanitize_text_field( $att['file_name'] ) : ( $file_path ? basename( $file_path ) : $post->post_title );
			$file_type        = ! empty( $att['file_type'] ) ? sanitize_mime_type( $att['file_type'] ) : ( $post->post_mime_type ?: 'application/octet-stream' );
			$file_size        = ! empty( $att['file_size'] ) ? absint( $att['file_size'] ) : ( $file_path && file_exists( $file_path ) ? (int) filesize( $file_path ) : 0 );
			$attachment_label = ! empty( $att['attachment_label'] ) ? sanitize_text_field( $att['attachment_label'] ) : 'Other';

			$wpdb->insert(
				$table,
				array(
					'record_id'        => $record_id,
					'attachment_id'    => $attachment_id,
					'file_name'        => $file_name,
					'file_type'        => $file_type,
					'file_size'        => $file_size,
					'attachment_label' => $attachment_label,
					'uploaded_by'      => $uploaded_by,
					'uploaded_at'      => $created_at,
				)
			);
			$saved++;
		}

		return $saved;
	}

	/**
	 * Retrieve supported attachment labels with filter extensibility.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Map of label slug/key to human-readable label.
	 */
	public static function get_attachment_labels(): array {
		$labels = array(
			'Tender Document'   => __( 'Tender Document', 'tender-po-management' ),
			'Customer PO'       => __( 'Customer PO', 'tender-po-management' ),
			'Quotation'         => __( 'Quotation', 'tender-po-management' ),
			'Invoice'           => __( 'Invoice', 'tender-po-management' ),
			'Shipping Document' => __( 'Shipping Document', 'tender-po-management' ),
			'Other'             => __( 'Other', 'tender-po-management' ),
		);

		return apply_filters( 'tpm_attachment_labels', $labels );
	}

	/**
	 * Attach one or more WordPress Media Library files to an existing record.
	 *
	 * @since 1.0.0
	 * @param int                  $record_id    Target record ID.
	 * @param array<array<string>> $attachments List of attachment data.
	 * @param int                  $user_id      Acting user ID.
	 * @return int|WP_Error Number of attachments saved or WP_Error.
	 */
	public static function add_attachments_to_record( int $record_id, array $attachments, int $user_id ) {
		global $wpdb;

		$record = self::get_record( $record_id );
		if ( ! $record ) {
			return new WP_Error( 'record_not_found', __( 'Record not found.', 'tender-po-management' ) );
		}

		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return new WP_Error( 'invalid_attachments', __( 'No attachments provided.', 'tender-po-management' ) );
		}

		$table          = $wpdb->prefix . 'tpm_attachments';
		$now            = current_time( 'mysql' );
		$saved_count    = 0;
		$allowed_labels = array_keys( self::get_attachment_labels() );

		foreach ( $attachments as $att ) {
			$attachment_id = absint( $att['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}

			// Validate attachment exists in WordPress Media Library.
			$post = get_post( $attachment_id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				continue;
			}

			// Safe file metadata resolution directly from WordPress.
			$file_path = get_attached_file( $attachment_id );
			$file_name = ! empty( $att['file_name'] ) ? sanitize_text_field( $att['file_name'] ) : ( $file_path ? basename( $file_path ) : $post->post_title );
			$file_type = ! empty( $att['file_type'] ) ? sanitize_mime_type( $att['file_type'] ) : ( $post->post_mime_type ?: 'application/octet-stream' );
			$file_size = ! empty( $att['file_size'] ) ? absint( $att['file_size'] ) : ( $file_path && file_exists( $file_path ) ? filesize( $file_path ) : 0 );

			$label_raw = sanitize_text_field( $att['attachment_label'] ?? 'Other' );
			$label     = in_array( $label_raw, $allowed_labels, true ) ? $label_raw : 'Other';

			$inserted = $wpdb->insert(
				$table,
				array(
					'record_id'        => $record_id,
					'attachment_id'    => $attachment_id,
					'file_name'        => $file_name,
					'file_type'        => $file_type,
					'file_size'        => $file_size,
					'attachment_label' => $label,
					'uploaded_by'      => $user_id,
					'uploaded_at'      => $now,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s' )
			);

			if ( false !== $inserted ) {
				$saved_count++;
				/* translators: 1: File name, 2: Label */
				$desc = sprintf( __( 'Attached document: %1$s (%2$s)', 'tender-po-management' ), $file_name, $label );
				self::log_activity(
					$record_id,
					$user_id,
					'attachment_added',
					$desc,
					null,
					wp_json_encode(
						array(
							'attachment_id' => $attachment_id,
							'file_name'     => $file_name,
							'label'         => $label,
						)
					),
					$now
				);
			}
		}

		return $saved_count;
	}

	/**
	 * Delete an attachment relationship from a record.
	 *
	 * Note: Deletes ONLY the relationship from tpm_attachments.
	 * The actual WordPress Media Library file is never deleted.
	 *
	 * @since 1.0.0
	 * @param int $attachment_row_id ID of the row in tpm_attachments.
	 * @param int $user_id           Acting user ID.
	 * @return bool|WP_Error
	 */
	public static function delete_attachment( int $attachment_row_id, int $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_attachments';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $attachment_row_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $row ) {
			return new WP_Error( 'attachment_not_found', __( 'Attachment relationship not found.', 'tender-po-management' ) );
		}

		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $attachment_row_id ),
			array( '%d' )
		);

		if ( false !== $deleted ) {
			$now = current_time( 'mysql' );
			/* translators: %s: File name */
			$desc = sprintf( __( 'Removed attachment: %s', 'tender-po-management' ), $row->file_name );
			self::log_activity(
				(int) $row->record_id,
				$user_id,
				'attachment_deleted',
				$desc,
				wp_json_encode(
					array(
						'attachment_id' => (int) $row->attachment_id,
						'file_name'     => $row->file_name,
						'label'         => $row->attachment_label,
					)
				),
				null,
				$now
			);
			return true;
		}

		return false;
	}

	/**
	 * Create an audit log entry in tpm_activity_logs.
	 *
	 * @since 1.0.0
	 * @param int|null    $record_id   Record ID (or null for system actions).
	 * @param int         $user_id     Acting user ID.
	 * @param string      $action      Action identifier (e.g. 'record_created').
	 * @param string      $description Human-readable action description.
	 * @param string|null $old_value   JSON or text representation of old state.
	 * @param string|null $new_value   JSON or text representation of new state.
	 * @param string|null $created_at  Timestamp.
	 * @return bool
	 */
	public static function log_activity( ?int $record_id, int $user_id, string $action, string $description, ?string $old_value = null, ?string $new_value = null, ?string $created_at = null ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_activity_logs';

		$result = $wpdb->insert(
			$table,
			array(
				'record_id'   => $record_id,
				'user_id'     => $user_id,
				'action'      => sanitize_key( $action ),
				'description' => sanitize_text_field( $description ),
				'old_value'   => $old_value,
				'new_value'   => $new_value,
				'created_at'  => $created_at ?: current_time( 'mysql' ),
			)
		);

		return false !== $result;
	}

	/**
	 * Fetch a single record by ID.
	 *
	 * @since 1.0.0
	 * @param int $record_id Record ID.
	 * @return object|null Record object or null if not found.
	 */
	public static function get_record( int $record_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $record_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $record ?: null;
	}

	/**
	 * Fetch attachments linked to a record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Record ID.
	 * @return array<object>
	 */
	public static function get_attachments( int $record_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_attachments';
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE record_id = %d ORDER BY id ASC", $record_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Fetch activity logs for a record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Record ID.
	 * @return array<object>
	 */
	public static function get_activity_logs( int $record_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_activity_logs';
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE record_id = %d ORDER BY id DESC", $record_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Archive an active record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Target record ID.
	 * @param int $user_id   Acting user ID.
	 * @return bool
	 */
	public static function archive_record( int $record_id, int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$now   = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			array(
				'is_archived' => 1,
				'updated_by'  => $user_id,
				'updated_at'  => $now,
			),
			array( 'id' => $record_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			self::log_activity(
				$record_id,
				$user_id,
				'record_archived',
				__( 'Record moved to archive', 'tender-po-management' ),
				null,
				null,
				$now
			);
			return true;
		}

		return false;
	}

	/**
	 * Restore an archived record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Target record ID.
	 * @param int $user_id   Acting user ID.
	 * @return bool
	 */
	public static function unarchive_record( int $record_id, int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$now   = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			array(
				'is_archived' => 0,
				'updated_by'  => $user_id,
				'updated_at'  => $now,
			),
			array( 'id' => $record_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			self::log_activity(
				$record_id,
				$user_id,
				'record_restored',
				__( 'Record restored from archive', 'tender-po-management' ),
				null,
				null,
				$now
			);
			return true;
		}

		return false;
	}

	/**
	 * Soft delete a record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Target record ID.
	 * @param int $user_id   Acting user ID.
	 * @return bool
	 */
	public static function delete_record( int $record_id, int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$now   = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			array(
				'is_archived' => 1,
				'deleted_at'  => $now,
				'updated_by'  => $user_id,
				'updated_at'  => $now,
			),
			array( 'id' => $record_id ),
			array( '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			self::log_activity(
				$record_id,
				$user_id,
				'record_deleted',
				__( 'Record deleted', 'tender-po-management' ),
				null,
				null,
				$now
			);
			return true;
		}

		return false;
	}

	/**
	 * Update internal notes for a record.
	 *
	 * @since 1.0.0
	 * @param int    $record_id Target record ID.
	 * @param string $notes     Notes content.
	 * @param int    $user_id   Acting user ID.
	 * @return bool
	 */
	public static function update_notes( int $record_id, string $notes, int $user_id ): bool {
		global $wpdb;

		$table           = $wpdb->prefix . 'tpm_records';
		$sanitized_notes = wp_kses_post( $notes );
		$now             = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			array(
				'notes'      => $sanitized_notes,
				'updated_by' => $user_id,
				'updated_at' => $now,
			),
			array( 'id' => $record_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			self::log_activity(
				$record_id,
				$user_id,
				'notes_updated',
				__( 'Internal notes updated', 'tender-po-management' ),
				null,
				null,
				$now
			);
			return true;
		}

		return false;
	}

	/**
	 * Build SQL WHERE conditions and parameters for record querying.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{0: string, 1: array<mixed>} Array of [where_sql, params].
	 */
	private static function build_where_clause( array $args ): array {
		global $wpdb;

		$where_clauses = array( '1=1' );
		$params        = array();

		// 1. Keyword search across all target fields
		if ( ! empty( $args['search'] ) ) {
			$search_term = trim( sanitize_text_field( $args['search'] ) );
			$like        = '%' . $wpdb->esc_like( $search_term ) . '%';

			$search_conditions = array(
				'r.tender_number LIKE %s',
				'r.customer_po_number LIKE %s',
				'r.customer_po_name LIKE %s',
				'r.item_description LIKE %s',
				'r.fill_ref_number LIKE %s',
				'r.fill_invoice_number LIKE %s',
				'r.tracking_number LIKE %s',
				'r.ff_location LIKE %s',
				'r.hs_code LIKE %s',
				'r.fill_po LIKE %s',
				'r.notes LIKE %s',
				'r.record_type LIKE %s',
				'r.status LIKE %s',
				's.status_name LIKE %s',
				'c.name LIKE %s',
				'c.company_name LIKE %s',
				'p.company_name LIKE %s',
				'r.id LIKE %s',
			);

			$search_params = array(
				$like, $like, $like, $like, $like, $like, $like,
				$like, $like, $like, $like, $like, $like, $like,
				$like, $like, $like, $like,
			);

			// If search term contains an ID pattern like "#1", "Record #1", "Record 1", or is just a number
			if ( is_numeric( $search_term ) ) {
				$search_conditions[] = 'r.id = %d';
				$search_params[]     = absint( $search_term );
			} elseif ( preg_match( '/(?:record\s*#?|#)\s*(\d+)/i', $search_term, $id_matches ) ) {
				$search_conditions[] = 'r.id = %d';
				$search_params[]     = absint( $id_matches[1] );
			}

			$where_clauses[] = '(' . implode( ' OR ', $search_conditions ) . ')';
			foreach ( $search_params as $sp ) {
				$params[] = $sp;
			}
		}

		// 2. Quick View Filter
		$view         = sanitize_key( $args['view'] ?? 'all' );
		$is_searching = ! empty( $args['search'] );

		if ( 'tenders' === $view ) {
			$where_clauses[] = "r.record_type = 'tender'";
			$where_clauses[] = 'r.is_archived = 0';
		} elseif ( 'pos' === $view ) {
			$where_clauses[] = "r.record_type = 'po'";
			$where_clauses[] = 'r.is_archived = 0';
		} elseif ( 'archived' === $view ) {
			$where_clauses[] = 'r.is_archived = 1';
		} elseif ( ! $is_searching && 'submitted' === $view ) {
			$where_clauses[] = 'r.is_archived = 0';
			$where_clauses[] = "(r.status LIKE '%submitted%' OR r.status = 'po-confirmed')";
		} elseif ( ! $is_searching && 'complete' === $view ) {
			$where_clauses[] = 'r.is_archived = 0';
			$where_clauses[] = "(r.status LIKE '%settled%' OR r.status LIKE '%awarded%' OR r.status LIKE '%complete%')";
		} elseif ( ! $is_searching && 'pending' === $view ) {
			$where_clauses[] = 'r.is_archived = 0';
			$where_clauses[] = "(r.status LIKE '%draft%' OR r.status LIKE '%review%' OR r.status LIKE '%pending%')";
		} elseif ( ! isset( $args['is_archived'] ) || -1 !== (int) $args['is_archived'] ) {
			// Default 'all': only active records unless explicitly overridden
			if ( ! isset( $args['is_archived'] ) ) {
				$where_clauses[] = 'r.is_archived = 0';
			}
		}

		// 3. Explicit is_archived override (if provided)
		if ( isset( $args['is_archived'] ) && -1 !== (int) $args['is_archived'] && 'archived' !== $view ) {
			$where_clauses[] = 'r.is_archived = %d';
			$params[]        = (int) $args['is_archived'];
		}

		// 4. Record Type Filter
		if ( ! empty( $args['record_type'] ) && in_array( $args['record_type'], array( 'tender', 'po' ), true ) ) {
			$where_clauses[] = 'r.record_type = %s';
			$params[]        = sanitize_key( $args['record_type'] );
		}

		// 5. Status Filter
		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'r.status = %s';
			$params[]        = sanitize_text_field( $args['status'] );
		}

		// 6. Customer Filter
		if ( ! empty( $args['customer_id'] ) && absint( $args['customer_id'] ) > 0 ) {
			$where_clauses[] = 'r.customer_id = %d';
			$params[]        = absint( $args['customer_id'] );
		}

		// 7. Partner Company Filter
		if ( ! empty( $args['partner_company_id'] ) && absint( $args['partner_company_id'] ) > 0 ) {
			$where_clauses[] = 'r.partner_company_id = %d';
			$params[]        = absint( $args['partner_company_id'] );
		}

		// 8. Date Range Filters
		$date_field_whitelist = array(
			'tender_due_date'        => 'r.tender_due_date',
			'tender_submission_date' => 'r.tender_submission_date',
			'quote_expiration_date'  => 'r.quote_expiration_date',
			'created_at'             => 'r.created_at',
		);

		$date_field = sanitize_key( $args['date_field'] ?? 'tender_due_date' );
		$target_col = $date_field_whitelist[ $date_field ] ?? 'r.tender_due_date';

		if ( ! empty( $args['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where_clauses[] = "{$target_col} >= %s";
			$params[]        = $args['date_from'] . ( 'created_at' === $date_field ? ' 00:00:00' : '' );
		}

		if ( ! empty( $args['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where_clauses[] = "{$target_col} <= %s";
			$params[]        = $args['date_to'] . ( 'created_at' === $date_field ? ' 23:59:59' : '' );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		return array( $where_sql, $params );
	}

	/**
	 * Retrieve paginated records with server-side filtering, searching, and sorting.
	 *
	 * Uses single query with subqueries and LEFT JOINs to avoid N+1 performance pitfalls.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> List of record objects.
	 */
	public static function get_records( array $args = array() ): array {
		global $wpdb;

		$table_records     = $wpdb->prefix . 'tpm_records';
		$table_customers   = $wpdb->prefix . 'tpm_customers';
		$table_partners    = $wpdb->prefix . 'tpm_partner_companies';
		$table_statuses    = $wpdb->prefix . 'tpm_statuses';
		$table_attachments = $wpdb->prefix . 'tpm_attachments';

		list( $where_sql, $params ) = self::build_where_clause( $args );

		// Whitelist sorting parameters
		$sort_whitelist = array(
			'tender_number'      => 'r.tender_number',
			'record_type'        => 'r.record_type',
			'customer'           => 'COALESCE(c.company_name, c.name)',
			'status'             => 'r.status',
			'customer_po_number' => 'r.customer_po_number',
			'item_description'   => 'r.item_description',
			'purchase_price'     => 'r.purchase_price',
			'sell_price'         => 'r.sell_price',
			'net_profit'         => '(r.sell_price - r.purchase_price - r.expenses)',
			'due_date'           => 'COALESCE(r.tender_due_date, r.payment_due_date)',
			'created_at'         => 'r.created_at',
			'id'                 => 'r.id',
		);

		$orderby_param = sanitize_key( $args['orderby'] ?? 'created_at' );
		$orderby_sql   = $sort_whitelist[ $orderby_param ] ?? 'r.created_at';

		$order_param = strtoupper( sanitize_key( $args['order'] ?? 'DESC' ) );
		$order_sql   = ( 'ASC' === $order_param ) ? 'ASC' : 'DESC';

		// Pagination parameters
		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20;
		$paged    = isset( $args['paged'] ) ? max( 1, absint( $args['paged'] ) ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$query = "SELECT
					r.*,
					COALESCE(c.company_name, c.name) AS customer_name,
					c.company_name AS customer_company,
					p.company_name AS partner_company_name,
					s.status_name,
					s.status_color,
					(SELECT COUNT(*) FROM `{$table_attachments}` a WHERE a.record_id = r.id) AS attachments_count
				FROM `{$table_records}` r
				LEFT JOIN `{$table_customers}` c ON r.customer_id = c.id
				LEFT JOIN `{$table_partners}` p ON r.partner_company_id = p.id
				LEFT JOIN `{$table_statuses}` s ON r.status = s.status_slug
				WHERE {$where_sql}
				ORDER BY {$orderby_sql} {$order_sql}
				LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		$prepared_query = $wpdb->prepare( $query, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results        = $wpdb->get_results( $prepared_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count total records matching filter and search criteria for pagination.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args Query arguments.
	 * @return int Total records count.
	 */
	public static function get_records_count( array $args = array() ): int {
		global $wpdb;

		$table_records   = $wpdb->prefix . 'tpm_records';
		$table_customers = $wpdb->prefix . 'tpm_customers';
		$table_partners  = $wpdb->prefix . 'tpm_partner_companies';
		$table_statuses  = $wpdb->prefix . 'tpm_statuses';

		list( $where_sql, $params ) = self::build_where_clause( $args );

		$query = "SELECT COUNT(*)
				FROM `{$table_records}` r
				LEFT JOIN `{$table_customers}` c ON r.customer_id = c.id
				LEFT JOIN `{$table_partners}` p ON r.partner_company_id = p.id
				LEFT JOIN `{$table_statuses}` s ON r.status = s.status_slug
				WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			$prepared = $wpdb->prepare( $query, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Calculate item counts for each Quick View tab.
	 *
	 * @since 1.0.0
	 * @return array<string, int>
	 */
	public static function get_view_counts(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';

		$query = "SELECT
					COUNT(CASE WHEN is_archived = 0 THEN 1 END) AS count_all,
					COUNT(CASE WHEN is_archived = 0 AND record_type = 'tender' THEN 1 END) AS count_tenders,
					COUNT(CASE WHEN is_archived = 0 AND record_type = 'po' THEN 1 END) AS count_pos,
					COUNT(CASE WHEN is_archived = 1 THEN 1 END) AS count_archived,
					COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%submitted%' OR status = 'po-confirmed') THEN 1 END) AS count_submitted,
					COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%settled%' OR status LIKE '%awarded%' OR status LIKE '%complete%') THEN 1 END) AS count_complete,
					COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%draft%' OR status LIKE '%review%' OR status LIKE '%pending%') THEN 1 END) AS count_pending
				FROM `{$table}`";

		$counts = $wpdb->get_row( $query );

		return array(
			'all'       => (int) ( $counts->count_all ?? 0 ),
			'tenders'   => (int) ( $counts->count_tenders ?? 0 ),
			'pos'       => (int) ( $counts->count_pos ?? 0 ),
			'archived'  => (int) ( $counts->count_archived ?? 0 ),
			'submitted' => (int) ( $counts->count_submitted ?? 0 ),
			'complete'  => (int) ( $counts->count_complete ?? 0 ),
			'pending'   => (int) ( $counts->count_pending ?? 0 ),
		);
	}

	/**
	 * Bulk archive records.
	 *
	 * @since 1.0.0
	 * @param array<int> $record_ids List of record IDs.
	 * @param int        $user_id    Acting user ID.
	 * @return int Number of records successfully archived.
	 */
	public static function bulk_archive( array $record_ids, int $user_id ): int {
		$count = 0;
		foreach ( $record_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && self::archive_record( $id, $user_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Bulk restore archived records.
	 *
	 * @since 1.0.0
	 * @param array<int> $record_ids List of record IDs.
	 * @param int        $user_id    Acting user ID.
	 * @return int Number of records successfully restored.
	 */
	public static function bulk_restore( array $record_ids, int $user_id ): int {
		$count = 0;
		foreach ( $record_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && self::unarchive_record( $id, $user_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Bulk delete records (soft-delete / archive).
	 *
	 * @since 1.0.0
	 * @param array<int> $record_ids List of record IDs.
	 * @param int        $user_id    Acting user ID.
	 * @return int Number of records successfully deleted.
	 */
	public static function bulk_delete( array $record_ids, int $user_id ): int {
		$count = 0;
		foreach ( $record_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && self::delete_record( $id, $user_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Permanently delete a record and carefully clean up its related data.
	 *
	 * Cascade operations:
	 * 1. Unlinks and deletes attachment relationships from {$wpdb->prefix}tpm_attachments
	 *    (Crucial: Does NOT delete physical files from the WordPress Media Library).
	 * 2. Deletes recorded payments from {$wpdb->prefix}tpm_payments.
	 * 3. Deletes record-associated activity logs from {$wpdb->prefix}tpm_activity_logs.
	 * 4. Deletes the primary record row from {$wpdb->prefix}tpm_records.
	 *
	 * @since 1.0.0
	 * @param int $record_id Target record ID.
	 * @param int $user_id   Acting user ID.
	 * @return bool True on success, false on failure or if record not found.
	 */
	public static function permanent_delete_record( int $record_id, int $user_id ): bool {
		global $wpdb;

		$table_records     = $wpdb->prefix . 'tpm_records';
		$table_attachments = $wpdb->prefix . 'tpm_attachments';
		$table_payments    = $wpdb->prefix . 'tpm_payments';
		$table_logs        = $wpdb->prefix . 'tpm_activity_logs';

		// 1. Verify record exists and get identifiers for audit trail
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, record_type, tender_number, customer_po_number FROM `{$table_records}` WHERE id = %d", $record_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $record ) {
			return false;
		}

		$identifier = ! empty( $record->tender_number ) ? $record->tender_number : ( ! empty( $record->customer_po_number ) ? $record->customer_po_number : '#' . $record_id );

		// 2. Cascade cleanup of related data
		// A. Remove attachment relationships (preserves WP media library physical files)
		$wpdb->delete(
			$table_attachments,
			array( 'record_id' => $record_id ),
			array( '%d' )
		);

		// B. Remove recorded payments
		$wpdb->delete(
			$table_payments,
			array( 'record_id' => $record_id ),
			array( '%d' )
		);

		// C. Remove record-associated activity logs
		$wpdb->delete(
			$table_logs,
			array( 'record_id' => $record_id ),
			array( '%d' )
		);

		// D. Finally, remove the record itself from records table
		$deleted = $wpdb->delete(
			$table_records,
			array( 'id' => $record_id ),
			array( '%d' )
		);

		if ( false !== $deleted && $deleted > 0 ) {
			// Record system-level audit entry (record_id = 0 for permanent deletion tracking)
			$now = current_time( 'mysql' );
			/* translators: 1: Record type, 2: Record identifier */
			$desc = sprintf( __( 'Permanently deleted %1$s %2$s and cleaned associated attachments, payments, and logs.', 'tender-po-management' ), ucfirst( (string) $record->record_type ), $identifier );
			self::log_activity(
				0,
				$user_id,
				'record_permanently_deleted',
				$desc,
				(string) $record_id,
				null,
				$now
			);
			return true;
		}

		return false;
	}

	/**
	 * Bulk permanently delete records and cascade cleanup related data.
	 *
	 * @since 1.0.0
	 * @param array<int> $record_ids List of record IDs.
	 * @param int        $user_id    Acting user ID.
	 * @return int Number of records permanently deleted.
	 */
	public static function bulk_permanent_delete( array $record_ids, int $user_id ): int {
		$count = 0;
		foreach ( $record_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && self::permanent_delete_record( $id, $user_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Fetch summary metrics for archived records.
	 *
	 * @since 1.0.0
	 * @return array{total_archived: int, archived_tenders: int, archived_pos: int, total_archived_value: float}
	 */
	public static function get_archived_summary(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$sql   = "SELECT 
					COUNT(*) AS total_archived,
					COUNT(CASE WHEN record_type = 'tender' THEN 1 END) AS archived_tenders,
					COUNT(CASE WHEN record_type = 'po' THEN 1 END) AS archived_pos,
					COALESCE(SUM(sell_price), 0.0) AS total_archived_value
				  FROM `{$table}`
				  WHERE is_archived = 1";

		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'total_archived'       => (int) ( $row->total_archived ?? 0 ),
			'archived_tenders'     => (int) ( $row->archived_tenders ?? 0 ),
			'archived_pos'         => (int) ( $row->archived_pos ?? 0 ),
			'total_archived_value' => (float) ( $row->total_archived_value ?? 0.0 ),
		);
	}
}

