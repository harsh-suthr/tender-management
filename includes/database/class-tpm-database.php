<?php
/**
 * Database Architecture and Migration Manager.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/database
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Architecture and Migration Manager.
 *
 * Handles creation, versioning, migrations, and seeding of all
 * relational database tables using WordPress $wpdb and dbDelta().
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/database
 * @since      1.0.0
 */
class TPM_Database {

	/**
	 * Table name constants without prefix.
	 */
	public const TABLE_RECORDS           = 'tpm_records';
	public const TABLE_CUSTOMERS         = 'tpm_customers';
	public const TABLE_PARTNER_COMPANIES = 'tpm_partner_companies';
	public const TABLE_ATTACHMENTS       = 'tpm_attachments';
	public const TABLE_PAYMENTS          = 'tpm_payments';
	public const TABLE_ACTIVITY_LOGS     = 'tpm_activity_logs';
	public const TABLE_STATUSES          = 'tpm_statuses';

	/**
	 * Retrieve all table names with dynamic WordPress database prefix.
	 *
	 * Guaranteed: Never hardcodes 'wp_'.
	 *
	 * @since 1.0.0
	 * @return array<string, string>
	 */
	public static function get_table_names(): array {
		global $wpdb;

		return array(
			'records'           => $wpdb->prefix . self::TABLE_RECORDS,
			'customers'         => $wpdb->prefix . self::TABLE_CUSTOMERS,
			'partner_companies' => $wpdb->prefix . self::TABLE_PARTNER_COMPANIES,
			'attachments'       => $wpdb->prefix . self::TABLE_ATTACHMENTS,
			'payments'          => $wpdb->prefix . self::TABLE_PAYMENTS,
			'activity_logs'     => $wpdb->prefix . self::TABLE_ACTIVITY_LOGS,
			'statuses'          => $wpdb->prefix . self::TABLE_STATUSES,
		);
	}

	/**
	 * Install all database tables and seed default statuses.
	 *
	 * Uses WordPress core dbDelta() with optimal charset and collation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_table_names();

		$queries = array();

		// ==========================================
		// TABLE 1: RECORDS (Tenders & Purchase Orders)
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['records']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_type varchar(20) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'draft',
			tender_number varchar(100) DEFAULT NULL,
			fill_ref_number varchar(100) DEFAULT NULL,
			customer_id bigint(20) unsigned DEFAULT NULL,
			customer_po_number varchar(150) DEFAULT NULL,
			customer_po_name varchar(255) DEFAULT NULL,
			item_description longtext DEFAULT NULL,
			purchase_price decimal(15,2) NOT NULL DEFAULT 0.00,
			sell_price decimal(15,2) NOT NULL DEFAULT 0.00,
			expenses decimal(15,2) NOT NULL DEFAULT 0.00,
			actual_payment decimal(15,2) NOT NULL DEFAULT 0.00,
			balance_payment_due decimal(15,2) NOT NULL DEFAULT 0.00,
			partner_company_id bigint(20) unsigned DEFAULT NULL,
			partner_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
			tender_due_date date DEFAULT NULL,
			tender_submission_date date DEFAULT NULL,
			quote_expiration_date date DEFAULT NULL,
			po_date date DEFAULT NULL,
			payment_due_date date DEFAULT NULL,
			shipping_date date DEFAULT NULL,
			settled_date date DEFAULT NULL,
			hs_code varchar(100) DEFAULT NULL,
			tracking_number varchar(150) DEFAULT NULL,
			ff_location varchar(255) DEFAULT NULL,
			fill_invoice_number varchar(150) DEFAULT NULL,
			fill_po varchar(150) DEFAULT NULL,
			original_awb_received tinyint(1) NOT NULL DEFAULT 0,
			notes longtext DEFAULT NULL,
			is_archived tinyint(1) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_record_type (record_type),
			KEY idx_status (status),
			KEY idx_customer_id (customer_id),
			KEY idx_partner_company_id (partner_company_id),
			KEY idx_is_archived (is_archived),
			KEY idx_tender_number (tender_number),
			KEY idx_customer_po_number (customer_po_number),
			KEY idx_created_at (created_at),
			KEY idx_archived_type (is_archived, record_type),
			KEY idx_archived_created (is_archived, created_at),
			KEY idx_archived_status (is_archived, status),
			KEY idx_tender_due_date (tender_due_date),
			KEY idx_payment_due_date (payment_due_date),
			KEY idx_quote_exp_date (quote_expiration_date),
			KEY idx_po_date (po_date)
		) $charset_collate;";

		// ==========================================
		// TABLE 2: CUSTOMERS
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['customers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			company_name varchar(255) DEFAULT NULL,
			email varchar(100) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			address text DEFAULT NULL,
			notes longtext DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_name (name(191)),
			KEY idx_email (email),
			KEY idx_status_company (status, company_name(191))
		) $charset_collate;";

		// ==========================================
		// TABLE 3: PARTNER COMPANIES
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['partner_companies']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_name varchar(255) NOT NULL,
			contact_person varchar(255) DEFAULT NULL,
			email varchar(100) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			address text DEFAULT NULL,
			notes longtext DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_company_name (company_name(191)),
			KEY idx_status_company (status, company_name(191))
		) $charset_collate;";

		// ==========================================
		// TABLE 4: ATTACHMENTS (WP Media Reference)
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['attachments']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL,
			file_name varchar(255) NOT NULL,
			file_type varchar(100) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_label varchar(100) DEFAULT NULL,
			uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
			uploaded_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_record_id (record_id),
			KEY idx_attachment_id (attachment_id)
		) $charset_collate;";

		// ==========================================
		// TABLE 5: PAYMENTS
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['payments']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			payment_amount decimal(15,2) NOT NULL DEFAULT 0.00,
			payment_date date NOT NULL,
			payment_method varchar(50) DEFAULT NULL,
			reference_number varchar(150) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_record_id (record_id),
			KEY idx_payment_date (payment_date)
		) $charset_collate;";

		// ==========================================
		// TABLE 6: ACTIVITY LOGS
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['activity_logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			description text NOT NULL,
			old_value longtext DEFAULT NULL,
			new_value longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_record_id (record_id),
			KEY idx_user_id (user_id),
			KEY idx_action (action),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		// ==========================================
		// TABLE 7: STATUS SETTINGS
		// ==========================================
		$queries[] = "CREATE TABLE {$tables['statuses']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status_name varchar(100) NOT NULL,
			status_slug varchar(100) NOT NULL,
			record_type varchar(20) NOT NULL DEFAULT 'general',
			status_color varchar(20) NOT NULL DEFAULT '#646970',
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_status_slug (status_slug),
			KEY idx_record_type (record_type),
			KEY idx_is_active (is_active),
			KEY idx_sort_order (sort_order)
		) $charset_collate;";

		// Execute schema creation via dbDelta.
		foreach ( $queries as $query ) {
			dbDelta( $query );
		}

		// Seed default statuses (idempotent).
		self::seed_default_statuses();

		// Record installed database schema version.
		update_option( 'tpm_db_version', TPM_DB_VERSION );
	}

	/**
	 * Seed default statuses into {$wpdb->prefix}tpm_statuses.
	 *
	 * Idempotent: Checks status_slug before insertion so reactivation
	 * or subsequent runs never duplicate rows.
	 *
	 * @since 1.0.0
	 * @return int Count of newly inserted statuses.
	 */
	public static function seed_default_statuses(): int {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_STATUSES;
		$now   = current_time( 'mysql' );

		// Check if table exists before attempting inserts.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}

		$default_statuses = array(
			// General Statuses
			array( 'status_name' => 'Tender',     'status_slug' => 'tender',      'record_type' => 'general', 'status_color' => '#1e429f', 'sort_order' => 10 ),
			array( 'status_name' => 'Submitted',  'status_slug' => 'submitted',   'record_type' => 'general', 'status_color' => '#996800', 'sort_order' => 20 ),
			array( 'status_name' => 'Complete',   'status_slug' => 'complete',    'record_type' => 'general', 'status_color' => '#0a7b45', 'sort_order' => 30 ),
			array( 'status_name' => 'Partial',    'status_slug' => 'partial',     'record_type' => 'general', 'status_color' => '#2271b1', 'sort_order' => 40 ),
			array( 'status_name' => 'Payment',    'status_slug' => 'payment',     'record_type' => 'general', 'status_color' => '#723b13', 'sort_order' => 50 ),
			array( 'status_name' => 'HOLD',       'status_slug' => 'hold',        'record_type' => 'general', 'status_color' => '#dba617', 'sort_order' => 60 ),
			array( 'status_name' => 'Expense',    'status_slug' => 'expense',     'record_type' => 'general', 'status_color' => '#b32d2e', 'sort_order' => 70 ),
			array( 'status_name' => 'Duplicate',  'status_slug' => 'duplicate',   'record_type' => 'general', 'status_color' => '#646970', 'sort_order' => 80 ),
			array( 'status_name' => 'Repair',     'status_slug' => 'repair',      'record_type' => 'general', 'status_color' => '#d63638', 'sort_order' => 90 ),
			array( 'status_name' => 'Archive',    'status_slug' => 'archive',     'record_type' => 'general', 'status_color' => '#50575e', 'sort_order' => 100 ),

			// Tender-specific Statuses
			array( 'status_name' => 'T-Lost',      'status_slug' => 't-lost',      'record_type' => 'tender',  'status_color' => '#b32d2e', 'sort_order' => 110 ),
			array( 'status_name' => 'T-Past Due',  'status_slug' => 't-past-due',  'record_type' => 'tender',  'status_color' => '#d63638', 'sort_order' => 120 ),
			array( 'status_name' => 'T-Submitted', 'status_slug' => 't-submitted', 'record_type' => 'tender',  'status_color' => '#1a56db', 'sort_order' => 130 ),

			// PO-specific Statuses
			array( 'status_name' => 'PO',  'status_slug' => 'po',  'record_type' => 'po', 'status_color' => '#854d0e', 'sort_order' => 140 ),
			array( 'status_name' => 'AWB', 'status_slug' => 'awb', 'record_type' => 'po', 'status_color' => '#1d5ca8', 'sort_order' => 150 ),
			array( 'status_name' => 'BQ',  'status_slug' => 'bq',  'record_type' => 'po', 'status_color' => '#0e7490', 'sort_order' => 160 ),
		);

		$inserted = 0;
		foreach ( $default_statuses as $st ) {
			// Idempotent check: skip if slug already exists.
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM `{$table}` WHERE status_slug = %s", $st['status_slug'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( ! $exists ) {
				$wpdb->insert(
					$table,
					array(
						'status_name'  => $st['status_name'],
						'status_slug'  => $st['status_slug'],
						'record_type'  => $st['record_type'],
						'status_color' => $st['status_color'],
						'sort_order'   => $st['sort_order'],
						'is_active'    => 1,
						'created_at'   => $now,
					),
					array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
				);
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Run migrations when a version mismatch is detected.
	 *
	 * Extensible migration engine comparing installed DB version with TPM_DB_VERSION.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function migrate(): void {
		$installed_version = get_option( 'tpm_db_version', '0.0.0' );

		if ( version_compare( $installed_version, TPM_DB_VERSION, '<' ) ) {
			// Run installation & updates.
			self::install();

			// Hook for version-specific incremental migration scripts if needed in future:
			// if ( version_compare( $installed_version, '1.1.0', '<' ) ) { self::migrate_to_1_1_0(); }

			update_option( 'tpm_db_version', TPM_DB_VERSION );
		}
	}

	/**
	 * Diagnostic verification helper inspecting existence and row counts of all 7 tables.
	 *
	 * @since 1.0.0
	 * @return array<string, array{name: string, exists: bool, rows: int}>
	 */
	public static function verify_tables(): array {
		global $wpdb;

		$results = array();
		$tables  = self::get_table_names();

		foreach ( $tables as $key => $table_name ) {
			$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name );
			$count  = 0;

			if ( $exists ) {
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$results[ $key ] = array(
				'name'   => $table_name,
				'exists' => $exists,
				'rows'   => $count,
			);
		}

		return $results;
	}

	/**
	 * Drop all custom tables (strictly for uninstall routine with explicit permission).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = self::get_table_names();
		foreach ( $tables as $table_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
