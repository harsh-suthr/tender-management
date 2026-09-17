<?php
/**
 * Service: Partner Company Management
 *
 * Handles partner company creation, validation, duplicate prevention, updates,
 * analytics, associated records, financial calculations, and safe deletion/reassignment.
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
 * TPM_Partner_Service Class.
 *
 * @since 1.0.0
 */
class TPM_Partner_Service {

	/**
	 * Validate partner company input data.
	 *
	 * Enforces required fields, valid email format, and duplicate prevention.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $data       Partner raw input.
	 * @param int                  $partner_id Optional partner ID for update exemption.
	 * @return array<string> List of validation error messages, or empty array if valid.
	 */
	public static function validate( array $data, int $partner_id = 0 ): array {
		global $wpdb;
		$errors = array();

		// 1. Company Name is required
		$company_name = sanitize_text_field( $data['company_name'] ?? '' );
		if ( empty( $company_name ) ) {
			$errors[] = __( 'Company Name is required.', 'tender-po-management' );
		}

		// 2. Email format validation (if provided)
		$email = sanitize_email( $data['email'] ?? '' );
		if ( ! empty( $data['email'] ) && ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'tender-po-management' );
		}

		// 3. Duplicate Prevention: Unique Company Name (case-insensitive)
		$table = $wpdb->prefix . 'tpm_partner_companies';
		if ( ! empty( $company_name ) ) {
			$sql    = "SELECT id FROM `{$table}` WHERE LOWER(company_name) = LOWER(%s)";
			$params = array( $company_name );

			if ( $partner_id > 0 ) {
				$sql     .= ' AND id != %d';
				$params[] = $partner_id;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$existing_company = $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
			if ( ! empty( $existing_company ) ) {
				$errors[] = __( 'A partner company with this name already exists.', 'tender-po-management' );
			}
		}

		// 4. Duplicate Prevention: Unique email (if provided)
		if ( ! empty( $email ) ) {
			$sql_email    = "SELECT id FROM `{$table}` WHERE LOWER(email) = LOWER(%s)";
			$params_email = array( $email );

			if ( $partner_id > 0 ) {
				$sql_email      .= ' AND id != %d';
				$params_email[]  = $partner_id;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$existing_email = $wpdb->get_var( $wpdb->prepare( $sql_email, ...$params_email ) );
			if ( ! empty( $existing_email ) ) {
				$errors[] = __( 'A partner company with this email address is already registered.', 'tender-po-management' );
			}
		}

		return $errors;
	}

	/**
	 * Create a new partner company record.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $raw_data Raw input data.
	 * @param int                  $user_id  Acting user ID.
	 * @return int|WP_Error Inserted partner ID on success, or WP_Error on failure.
	 */
	public static function create( array $raw_data, int $user_id ) {
		global $wpdb;

		// 1. Validation
		$errors = self::validate( $raw_data );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( '<br>', $errors ) );
		}

		// 2. Sanitization
		$company_name   = sanitize_text_field( $raw_data['company_name'] ?? '' );
		$contact_person = ! empty( $raw_data['contact_person'] ) ? sanitize_text_field( $raw_data['contact_person'] ) : null;
		$email          = ! empty( $raw_data['email'] ) ? sanitize_email( $raw_data['email'] ) : null;
		$phone          = ! empty( $raw_data['phone'] ) ? sanitize_text_field( $raw_data['phone'] ) : null;
		$address        = ! empty( $raw_data['address'] ) ? wp_kses_post( $raw_data['address'] ) : null;
		$notes          = ! empty( $raw_data['notes'] ) ? wp_kses_post( $raw_data['notes'] ) : null;
		$status         = in_array( $raw_data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $raw_data['status'] : 'active';
		$now            = current_time( 'mysql' );

		$table = $wpdb->prefix . 'tpm_partner_companies';

		$data = array(
			'company_name'   => $company_name,
			'contact_person' => $contact_person,
			'email'          => $email,
			'phone'          => $phone,
			'address'        => $address,
			'notes'          => $notes,
			'status'         => $status,
			'created_by'     => $user_id,
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$inserted = $wpdb->insert( $table, $data );

		if ( false === $inserted ) {
			return new WP_Error( 'db_insert_error', __( 'Failed to create partner company. Please try again.', 'tender-po-management' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing partner company record.
	 *
	 * @since 1.0.0
	 * @param int                  $partner_id Target partner ID.
	 * @param array<string, mixed> $raw_data   Updated input data.
	 * @param int                  $user_id    Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function update( int $partner_id, array $raw_data, int $user_id ) {
		global $wpdb;

		// 1. Existence check
		$existing = self::get_partner( $partner_id );
		if ( ! $existing ) {
			return new WP_Error( 'partner_not_found', __( 'Partner company not found.', 'tender-po-management' ) );
		}

		// 2. Validation
		$errors = self::validate( $raw_data, $partner_id );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( '<br>', $errors ) );
		}

		// 3. Sanitization
		$company_name   = sanitize_text_field( $raw_data['company_name'] ?? '' );
		$contact_person = ! empty( $raw_data['contact_person'] ) ? sanitize_text_field( $raw_data['contact_person'] ) : null;
		$email          = ! empty( $raw_data['email'] ) ? sanitize_email( $raw_data['email'] ) : null;
		$phone          = ! empty( $raw_data['phone'] ) ? sanitize_text_field( $raw_data['phone'] ) : null;
		$address        = ! empty( $raw_data['address'] ) ? wp_kses_post( $raw_data['address'] ) : null;
		$notes          = ! empty( $raw_data['notes'] ) ? wp_kses_post( $raw_data['notes'] ) : null;
		$status         = in_array( $raw_data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $raw_data['status'] : 'active';
		$now            = current_time( 'mysql' );

		$table = $wpdb->prefix . 'tpm_partner_companies';

		$data = array(
			'company_name'   => $company_name,
			'contact_person' => $contact_person,
			'email'          => $email,
			'phone'          => $phone,
			'address'        => $address,
			'notes'          => $notes,
			'status'         => $status,
			'updated_at'     => $now,
		);

		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => $partner_id )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_update_error', __( 'Failed to update partner company. Please try again.', 'tender-po-management' ) );
		}

		return true;
	}

	/**
	 * Get a single partner company by ID.
	 *
	 * @since 1.0.0
	 * @param int $partner_id Partner ID.
	 * @return object|null Partner row or null.
	 */
	public static function get_partner( int $partner_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_partner_companies';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $partner_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	/**
	 * Get paginated list of partners with search, filtering, and total records count.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> List of partner objects.
	 */
	public static function get_partners( array $args = array() ): array {
		global $wpdb;

		$table_partners = $wpdb->prefix . 'tpm_partner_companies';
		$table_records  = $wpdb->prefix . 'tpm_records';

		$where_clauses = array( '1=1' );
		$params        = array();

		// Status filter
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'active', 'inactive' ), true ) ) {
			$where_clauses[] = 'p.status = %s';
			$params[]        = $args['status'];
		}

		// Keyword search
		if ( ! empty( $args['search'] ) ) {
			$search_term = trim( sanitize_text_field( $args['search'] ) );
			$term = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where_clauses[] = '(p.company_name LIKE %s OR p.contact_person LIKE %s OR p.email LIKE %s OR p.phone LIKE %s OR p.address LIKE %s OR p.notes LIKE %s OR p.id LIKE %s)';
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Whitelist sorting
		$allowed_orderby = array(
			'company_name'   => 'p.company_name',
			'contact_person' => 'p.contact_person',
			'email'          => 'p.email',
			'total_records'  => 'total_records',
			'created_at'     => 'p.created_at',
		);

		$orderby_key = $args['orderby'] ?? 'created_at';
		$orderby_sql = $allowed_orderby[ $orderby_key ] ?? 'p.created_at';
		$order_sql   = ( isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

		// Pagination
		$limit  = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : 20;
		$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;

		$sql = "SELECT p.*,
				(SELECT COUNT(*) FROM `{$table_records}` r WHERE r.partner_company_id = p.id AND r.is_archived = 0) AS total_records
				FROM `{$table_partners}` p
				WHERE {$where_sql}
				ORDER BY {$orderby_sql} {$order_sql}
				LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count total partner companies matching query filters for pagination.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args Query arguments.
	 * @return int Total matching records.
	 */
	public static function get_partners_count( array $args = array() ): int {
		global $wpdb;

		$table_partners = $wpdb->prefix . 'tpm_partner_companies';
		$where_clauses  = array( '1=1' );
		$params         = array();

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'active', 'inactive' ), true ) ) {
			$where_clauses[] = 'p.status = %s';
			$params[]        = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$search_term = trim( sanitize_text_field( $args['search'] ) );
			$term = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where_clauses[] = '(p.company_name LIKE %s OR p.contact_person LIKE %s OR p.email LIKE %s OR p.phone LIKE %s OR p.address LIKE %s OR p.notes LIKE %s OR p.id LIKE %s)';
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
			$params[]        = $term;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$sql       = "SELECT COUNT(*) FROM `{$table_partners}` p WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Retrieve counts for tab views (all, active, inactive).
	 *
	 * @since 1.0.0
	 * @return array<string, int>
	/**
	 * Retrieve counts for tab views (all, active, inactive) in a single optimized query.
	 *
	 * @since 1.0.0
	 * @param bool $force_refresh Whether to bypass runtime static cache.
	 * @return array<string, int>
	 */
	public static function get_view_counts( bool $force_refresh = false ): array {
		static $cached_counts = null;

		if ( null !== $cached_counts && ! $force_refresh ) {
			return $cached_counts;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'tpm_partner_companies';
		$row   = $wpdb->get_row(
			"SELECT 
				COUNT(*) AS count_all,
				COUNT(CASE WHEN status = 'active' THEN 1 END) AS count_active,
				COUNT(CASE WHEN status = 'inactive' THEN 1 END) AS count_inactive
			FROM `{$table}`"
		);

		$cached_counts = array(
			'all'      => (int) ( $row->count_all ?? 0 ),
			'active'   => (int) ( $row->count_active ?? 0 ),
			'inactive' => (int) ( $row->count_inactive ?? 0 ),
		);

		return $cached_counts;
	}

	/**
	 * Retrieve all associated Tenders and Purchase Orders for a partner company.
	 *
	 * @since 1.0.0
	 * @param int    $partner_id  Partner Company ID.
	 * @param string $record_type Optional filter by record_type ('tender' or 'po').
	 * @return array<object> List of records.
	 */
	public static function get_partner_records( int $partner_id, string $record_type = '' ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$where = 'WHERE partner_company_id = %d AND is_archived = 0';
		$params = array( $partner_id );

		if ( ! empty( $record_type ) && in_array( $record_type, array( 'tender', 'po' ), true ) ) {
			$where .= ' AND record_type = %s';
			$params[] = $record_type;
		}

		$sql = "SELECT id, record_type, status, tender_number, customer_po_number, customer_po_name,
				purchase_price, sell_price, expenses, partner_percentage,
				actual_payment, balance_payment_due,
				tender_due_date, po_date, created_at
				FROM `{$table}`
				{$where}
				ORDER BY created_at DESC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Compute financial aggregate totals and partner share for a specific partner company.
	 *
	 * @since 1.0.0
	 * @param int $partner_id Partner ID.
	 * @return array<string, float|int> Aggregated metrics.
	 */
	public static function get_partner_financial_summary( int $partner_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(id) AS total_records,
					SUM(CASE WHEN record_type = 'tender' THEN 1 ELSE 0 END) AS total_tenders,
					SUM(CASE WHEN record_type = 'po' THEN 1 ELSE 0 END) AS total_pos,
					COALESCE(SUM(sell_price), 0) AS total_revenue,
					COALESCE(SUM(purchase_price), 0) AS total_cost,
					COALESCE(SUM(expenses), 0) AS total_expenses,
					COALESCE(SUM(actual_payment), 0) AS total_paid,
					COALESCE(SUM(balance_payment_due), 0) AS total_balance_due,
					COALESCE(SUM(sell_price - purchase_price - expenses), 0) AS total_net_profit,
					COALESCE(SUM((sell_price - purchase_price - expenses) * (partner_percentage / 100)), 0) AS partner_profit_share,
					COALESCE(SUM(sell_price * (partner_percentage / 100)), 0) AS partner_revenue_share,
					COALESCE(AVG(partner_percentage), 0) AS avg_percentage
				FROM `{$table}`
				WHERE partner_company_id = %d AND is_archived = 0",
				$partner_id
			)
		);

		return array(
			'total_records'         => (int) ( $row->total_records ?? 0 ),
			'total_tenders'         => (int) ( $row->total_tenders ?? 0 ),
			'total_pos'             => (int) ( $row->total_pos ?? 0 ),
			'total_revenue'         => (float) ( $row->total_revenue ?? 0.0 ),
			'total_cost'            => (float) ( $row->total_cost ?? 0.0 ),
			'total_expenses'        => (float) ( $row->total_expenses ?? 0.0 ),
			'total_paid'            => (float) ( $row->total_paid ?? 0.0 ),
			'total_balance_due'     => (float) ( $row->total_balance_due ?? 0.0 ),
			'total_net_profit'      => (float) ( $row->total_net_profit ?? 0.0 ),
			'partner_profit_share'  => (float) ( $row->partner_profit_share ?? 0.0 ),
			'partner_revenue_share' => (float) ( $row->partner_revenue_share ?? 0.0 ),
			'avg_percentage'        => (float) ( $row->avg_percentage ?? 0.0 ),
		);
	}

	/**
	 * Check whether a partner company can be safely deleted.
	 *
	 * A partner cannot be deleted if active or archived records depend on them.
	 *
	 * @since 1.0.0
	 * @param int $partner_id Partner ID.
	 * @return array{can_delete: bool, record_count: int, tenders_count: int, pos_count: int}
	 */
	public static function can_delete( int $partner_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(id) AS record_count,
					SUM(CASE WHEN record_type = 'tender' THEN 1 ELSE 0 END) AS tenders_count,
					SUM(CASE WHEN record_type = 'po' THEN 1 ELSE 0 END) AS pos_count
				FROM `{$table}`
				WHERE partner_company_id = %d",
				$partner_id
			)
		);

		$total   = (int) ( $row->record_count ?? 0 );
		$tenders = (int) ( $row->tenders_count ?? 0 );
		$pos     = (int) ( $row->pos_count ?? 0 );

		return array(
			'can_delete'    => ( 0 === $total ),
			'record_count'  => $total,
			'tenders_count' => $tenders,
			'pos_count'     => $pos,
		);
	}

	/**
	 * Delete a partner company record safely.
	 *
	 * Rejects deletion if any records exist.
	 *
	 * @since 1.0.0
	 * @param int $partner_id Partner ID.
	 * @param int $user_id    Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function delete_partner( int $partner_id, int $user_id ) {
		global $wpdb;

		$check = self::can_delete( $partner_id );
		if ( ! $check['can_delete'] ) {
			/* translators: %d: Associated records count */
			return new WP_Error(
				'partner_has_records',
				sprintf(
					__( 'Cannot delete partner company: %d associated record(s) exist. Please deactivate this partner or reassign the records first.', 'tender-po-management' ),
					$check['record_count']
				)
			);
		}

		$table   = $wpdb->prefix . 'tpm_partner_companies';
		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $partner_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'db_delete_error', __( 'Failed to delete partner company.', 'tender-po-management' ) );
		}

		return true;
	}

	/**
	 * Deactivate a partner company.
	 *
	 * Sets partner status to 'inactive' so they are hidden from active selection
	 * while preserving historic references.
	 *
	 * @since 1.0.0
	 * @param int $partner_id Partner ID.
	 * @param int $user_id    Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function deactivate_partner( int $partner_id, int $user_id ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'tpm_partner_companies';
		$updated = $wpdb->update(
			$table,
			array(
				'status'     => 'inactive',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $partner_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_update_error', __( 'Failed to deactivate partner company.', 'tender-po-management' ) );
		}

		return true;
	}

	/**
	 * Reassign all records from one partner company to another, then delete the source partner.
	 *
	 * @since 1.0.0
	 * @param int $source_partner_id Source partner ID to be deleted.
	 * @param int $target_partner_id Target partner ID to receive the records.
	 * @param int $user_id           Acting user ID.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public static function reassign_and_delete( int $source_partner_id, int $target_partner_id, int $user_id ) {
		global $wpdb;

		if ( $source_partner_id === $target_partner_id ) {
			return new WP_Error( 'invalid_reassignment', __( 'Target partner must be different from the source partner.', 'tender-po-management' ) );
		}

		$source = self::get_partner( $source_partner_id );
		$target = self::get_partner( $target_partner_id );

		if ( ! $source ) {
			return new WP_Error( 'source_not_found', __( 'Source partner company not found.', 'tender-po-management' ) );
		}
		if ( ! $target ) {
			return new WP_Error( 'target_not_found', __( 'Target partner company not found.', 'tender-po-management' ) );
		}

		$table_records = $wpdb->prefix . 'tpm_records';
		$table_logs    = $wpdb->prefix . 'tpm_activity_logs';
		$now           = current_time( 'mysql' );

		// 1. Fetch all records linked to source partner for audit logging
		$records = $wpdb->get_results(
			$wpdb->prepare( "SELECT id FROM `{$table_records}` WHERE partner_company_id = %d", $source_partner_id )
		);

		// 2. Transfer records to target partner
		$wpdb->update(
			$table_records,
			array(
				'partner_company_id' => $target_partner_id,
				'updated_by'         => $user_id,
				'updated_at'         => $now,
			),
			array( 'partner_company_id' => $source_partner_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		// 3. Log audit activity on each affected record
		/* translators: 1: Previous partner, 2: New partner */
		$log_desc = sprintf( __( 'Partner company reassigned from %1$s to %2$s during partner cleanup.', 'tender-po-management' ), $source->company_name, $target->company_name );

		foreach ( $records as $rec ) {
			TPM_Record_Service::log_activity(
				(int) $rec->id,
				$user_id,
				'record_updated',
				$log_desc,
				(string) $source_partner_id,
				(string) $target_partner_id,
				$now
			);
		}

		// 4. Now safe to delete source partner
		$table_partners = $wpdb->prefix . 'tpm_partner_companies';
		$deleted        = $wpdb->delete(
			$table_partners,
			array( 'id' => $source_partner_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'db_delete_error', __( 'Records reassigned, but failed to delete source partner.', 'tender-po-management' ) );
		}

		return true;
	}
}
