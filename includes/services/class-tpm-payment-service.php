<?php
/**
 * Service: Payment Management
 *
 * Handles payment recording, validation, updates, deletion,
 * automated balance calculations, payment status determination,
 * and activity logging.
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
 * TPM_Payment_Service Class.
 *
 * @since 1.0.0
 */
class TPM_Payment_Service {

	/**
	 * Validate payment input data.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $data Payment input data.
	 * @return array<string> List of validation errors, or empty array if valid.
	 */
	public static function validate( array $data ): array {
		global $wpdb;
		$errors = array();

		// 1. Payment Amount validation
		if ( ! isset( $data['payment_amount'] ) || '' === trim( (string) $data['payment_amount'] ) ) {
			$errors[] = __( 'Payment Amount is required.', 'tender-po-management' );
		} else {
			$raw_amount = str_replace( array( ',', ' ' ), '', (string) $data['payment_amount'] );
			if ( ! is_numeric( $raw_amount ) || (float) $raw_amount <= 0 ) {
				$errors[] = __( 'Payment Amount must be a valid positive number greater than 0.', 'tender-po-management' );
			}
		}

		// 2. Payment Date validation
		$date = trim( (string) ( $data['payment_date'] ?? '' ) );
		if ( empty( $date ) ) {
			$errors[] = __( 'Payment Date is required.', 'tender-po-management' );
		} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || false === strtotime( $date ) ) {
			$errors[] = __( 'Payment Date must be a valid date in YYYY-MM-DD format.', 'tender-po-management' );
		}

		// 3. Record ID validation (if provided)
		if ( isset( $data['record_id'] ) ) {
			$record_id    = absint( $data['record_id'] );
			$table_records = $wpdb->prefix . 'tpm_records';
			$record_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_records}` WHERE id = %d", $record_id ) );
			if ( ! $record_exists ) {
				$errors[] = __( 'Associated record does not exist.', 'tender-po-management' );
			}
		}

		return $errors;
	}

	/**
	 * Record a new payment.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $raw_data Payment form inputs.
	 * @param int                  $user_id  Acting user ID.
	 * @return int|WP_Error Inserted payment ID on success, or WP_Error on failure.
	 */
	public static function create_payment( array $raw_data, int $user_id ) {
		global $wpdb;

		// 1. Validation
		$errors = self::validate( $raw_data );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( '<br>', $errors ) );
		}

		$record_id = absint( $raw_data['record_id'] ?? 0 );
		if ( ! $record_id ) {
			return new WP_Error( 'invalid_record', __( 'Record ID is required to register a payment.', 'tender-po-management' ) );
		}

		// 2. Sanitization
		$amount    = (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['payment_amount'] );
		$date      = sanitize_text_field( $raw_data['payment_date'] );
		$method    = ! empty( $raw_data['payment_method'] ) ? sanitize_text_field( $raw_data['payment_method'] ) : null;
		$reference = ! empty( $raw_data['reference_number'] ) ? sanitize_text_field( $raw_data['reference_number'] ) : null;
		$notes     = ! empty( $raw_data['notes'] ) ? wp_kses_post( $raw_data['notes'] ) : null;
		$now       = current_time( 'mysql' );

		$table = $wpdb->prefix . 'tpm_payments';

		$inserted = $wpdb->insert(
			$table,
			array(
				'record_id'        => $record_id,
				'payment_amount'   => $amount,
				'payment_date'     => $date,
				'payment_method'   => $method,
				'reference_number' => $reference,
				'notes'            => $notes,
				'created_by'       => $user_id,
				'created_at'       => $now,
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_insert_error', __( 'Failed to record payment in database.', 'tender-po-management' ) );
		}

		$payment_id = (int) $wpdb->insert_id;

		// 3. Recalculate parent record actual payment and balance due
		self::recalculate_record_payments( $record_id );

		// 4. Audit Activity Logging
		$method_label = $method ?: __( 'Unspecified Method', 'tender-po-management' );
		$ref_label    = $reference ? sprintf( __( ' (Ref: %s)', 'tender-po-management' ), $reference ) : '';
		$curr_sym     = TPM_Settings_Service::get_currency_symbol();
		/* translators: 1: Currency symbol, 2: Payment amount, 3: Payment method, 4: Optional reference string */
		$log_desc = sprintf( __( 'Payment of %1$s%2$s recorded via %3$s%4$s', 'tender-po-management' ), $curr_sym, number_format( $amount, 2 ), $method_label, $ref_label );

		TPM_Record_Service::log_activity(
			$record_id,
			$user_id,
			'payment_added',
			$log_desc,
			null,
			(string) $amount,
			$now
		);

		return $payment_id;
	}

	/**
	 * Update an existing payment.
	 *
	 * @since 1.0.0
	 * @param int                  $payment_id Payment ID.
	 * @param array<string, mixed> $raw_data   Updated fields.
	 * @param int                  $user_id    Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function update_payment( int $payment_id, array $raw_data, int $user_id ) {
		global $wpdb;

		$existing = self::get_payment( $payment_id );
		if ( ! $existing ) {
			return new WP_Error( 'payment_not_found', __( 'Payment record not found.', 'tender-po-management' ) );
		}

		$record_id          = (int) $existing->record_id;
		$raw_data['record_id'] = $record_id;

		// Validation
		$errors = self::validate( $raw_data );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( '<br>', $errors ) );
		}

		// Sanitization
		$amount    = (float) str_replace( array( ',', ' ' ), '', (string) $raw_data['payment_amount'] );
		$date      = sanitize_text_field( $raw_data['payment_date'] );
		$method    = ! empty( $raw_data['payment_method'] ) ? sanitize_text_field( $raw_data['payment_method'] ) : null;
		$reference = ! empty( $raw_data['reference_number'] ) ? sanitize_text_field( $raw_data['reference_number'] ) : null;
		$notes     = ! empty( $raw_data['notes'] ) ? wp_kses_post( $raw_data['notes'] ) : null;
		$now       = current_time( 'mysql' );

		$table   = $wpdb->prefix . 'tpm_payments';
		$updated = $wpdb->update(
			$table,
			array(
				'payment_amount'   => $amount,
				'payment_date'     => $date,
				'payment_method'   => $method,
				'reference_number' => $reference,
				'notes'            => $notes,
			),
			array( 'id' => $payment_id )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_update_error', __( 'Failed to update payment record.', 'tender-po-management' ) );
		}

		// Recalculate parent record actual payment and balance due
		self::recalculate_record_payments( $record_id );

		// Audit Activity Logging
		$old_amount = (float) $existing->payment_amount;
		$curr_sym   = TPM_Settings_Service::get_currency_symbol();
		/* translators: 1: Payment ID, 2: Currency symbol, 3: Old amount, 4: New amount */
		$log_desc = sprintf( __( 'Payment #%1$d updated: amount changed from %2$s%3$s to %2$s%4$s', 'tender-po-management' ), $payment_id, $curr_sym, number_format( $old_amount, 2 ), number_format( $amount, 2 ) );

		TPM_Record_Service::log_activity(
			$record_id,
			$user_id,
			'payment_updated',
			$log_desc,
			(string) $old_amount,
			(string) $amount,
			$now
		);

		return true;
	}

	/**
	 * Delete a payment record.
	 *
	 * @since 1.0.0
	 * @param int $payment_id Payment ID.
	 * @param int $user_id    Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function delete_payment( int $payment_id, int $user_id ) {
		global $wpdb;

		$existing = self::get_payment( $payment_id );
		if ( ! $existing ) {
			return new WP_Error( 'payment_not_found', __( 'Payment record not found.', 'tender-po-management' ) );
		}

		$record_id = (int) $existing->record_id;
		$amount    = (float) $existing->payment_amount;
		$ref       = $existing->reference_number ?: '';
		$now       = current_time( 'mysql' );

		$table   = $wpdb->prefix . 'tpm_payments';
		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $payment_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'db_delete_error', __( 'Failed to delete payment record.', 'tender-po-management' ) );
		}

		// Recalculate parent record actual payment and balance due
		self::recalculate_record_payments( $record_id );

		// Audit Activity Logging
		$ref_label = $ref ? sprintf( __( ' (Ref: %s)', 'tender-po-management' ), $ref ) : '';
		$curr_sym  = TPM_Settings_Service::get_currency_symbol();
		/* translators: 1: Payment ID, 2: Currency symbol, 3: Payment amount, 4: Optional reference string */
		$log_desc = sprintf( __( 'Payment #%1$d of %2$s%3$s deleted%4$s', 'tender-po-management' ), $payment_id, $curr_sym, number_format( $amount, 2 ), $ref_label );

		TPM_Record_Service::log_activity(
			$record_id,
			$user_id,
			'payment_deleted',
			$log_desc,
			(string) $amount,
			null,
			$now
		);

		return true;
	}

	/**
	 * Retrieve a single payment row by ID.
	 *
	 * @since 1.0.0
	 * @param int $payment_id Payment ID.
	 * @return object|null Payment row or null.
	 */
	public static function get_payment( int $payment_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_payments';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $payment_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	/**
	 * Retrieve all payments for a given record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Record ID.
	 * @return array<object> List of payment rows with user names.
	 */
	public static function get_record_payments( int $record_id ): array {
		global $wpdb;

		$table_payments = $wpdb->prefix . 'tpm_payments';
		$table_users    = $wpdb->users;

		$sql = "SELECT p.*, u.display_name AS created_by_name
				FROM `{$table_payments}` p
				LEFT JOIN `{$table_users}` u ON p.created_by = u.ID
				WHERE p.record_id = %d
				ORDER BY p.payment_date DESC, p.id DESC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $record_id ) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Recalculate and synchronize actual_payment and balance_payment_due on parent record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Record ID.
	 * @return array{total_paid: float, balance_due: float}
	 */
	public static function recalculate_record_payments( int $record_id ): array {
		global $wpdb;

		$table_payments = $wpdb->prefix . 'tpm_payments';
		$table_records  = $wpdb->prefix . 'tpm_records';

		// 1. Single query to calculate sum of payments and fetch record sell_price
		$sql = "SELECT 
					r.sell_price,
					COALESCE((SELECT SUM(p.payment_amount) FROM `{$table_payments}` p WHERE p.record_id = r.id), 0.0) AS total_paid
				FROM `{$table_records}` r
				WHERE r.id = %d";
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $record_id ) );

		$sell_price  = (float) ( $row->sell_price ?? 0.0 );
		$total_paid  = (float) ( $row->total_paid ?? 0.0 );
		$balance_due = max( 0.0, $sell_price - $total_paid );

		// 4. Update parent record
		$wpdb->update(
			$table_records,
			array(
				'actual_payment'      => $total_paid,
				'balance_payment_due' => $balance_due,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => $record_id )
		);

		return array(
			'total_paid'  => $total_paid,
			'balance_due' => $balance_due,
		);
	}

	/**
	 * Determine real-time payment status based on expected amount, paid amount, due date, and current date.
	 *
	 * Statuses:
	 * - Paid: total_paid >= total_expected (when total_expected > 0)
	 * - Overdue: balance_due > 0, due date specified, and current date is past due date
	 * - Partial: total_paid > 0 and total_paid < total_expected, within due date
	 * - Pending: total_paid == 0, within due date (or no due date set)
	 *
	 * Note: Does not overwrite manually selected record workflow statuses.
	 *
	 * @since 1.0.0
	 * @param float       $expected     Total expected amount (sell_price).
	 * @param float       $paid         Total actual paid amount.
	 * @param string|null $due_date     Payment due date (YYYY-MM-DD) or null.
	 * @param string|null $current_date Optional current date for comparison (defaults to today).
	 * @return string Payment status: 'Pending', 'Partial', 'Paid', or 'Overdue'.
	 */
	public static function determine_payment_status( float $expected, float $paid, ?string $due_date = null, ?string $current_date = null ): string {
		$today = $current_date ? sanitize_text_field( $current_date ) : current_time( 'Y-m-d' );

		// 1. Paid check
		if ( $expected > 0 && $paid >= $expected ) {
			return 'Paid';
		}
		if ( $expected <= 0 && $paid > 0 ) {
			return 'Paid';
		}

		// 2. Overdue check
		$clean_due = ! empty( $due_date ) ? trim( (string) $due_date ) : '';
		if ( ! empty( $clean_due ) && '0000-00-00' !== $clean_due ) {
			if ( $today > $clean_due && $paid < $expected ) {
				return 'Overdue';
			}
		}

		// 3. Partial check
		if ( $paid > 0 && $paid < $expected ) {
			return 'Partial';
		}

		// 4. Default pending
		return 'Pending';
	}

	/**
	 * Fetch full payment financial summary for a record.
	 *
	 * @since 1.0.0
	 * @param int $record_id Record ID.
	 * @return array{total_expected: float, total_paid: float, balance_due: float, payment_status: string, payment_due_date: string|null, payments_count: int}
	 */
	public static function get_payment_summary( int $record_id ): array {
		global $wpdb;

		$table_records  = $wpdb->prefix . 'tpm_records';
		$table_payments = $wpdb->prefix . 'tpm_payments';

		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT sell_price, actual_payment, balance_payment_due, payment_due_date FROM `{$table_records}` WHERE id = %d", $record_id )
		);

		$expected  = (float) ( $record->sell_price ?? 0.0 );
		$paid      = (float) ( $record->actual_payment ?? 0.0 );
		$balance   = (float) ( $record->balance_payment_due ?? 0.0 );
		$due_date  = ! empty( $record->payment_due_date ) ? (string) $record->payment_due_date : null;

		$status = self::determine_payment_status( $expected, $paid, $due_date );

		$payments_count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table_payments}` WHERE record_id = %d", $record_id )
		);

		return array(
			'total_expected'   => $expected,
			'total_paid'       => $paid,
			'balance_due'      => $balance,
			'payment_status'   => $status,
			'payment_due_date' => $due_date,
			'payments_count'   => $payments_count,
		);
	}
}
