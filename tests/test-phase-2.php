<?php
/**
 * Automated Verification & Test Suite for Phase 2: Database Architecture.
 *
 * Can be run via CLI: php tests/test-phase-2.php
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/tests
 * @since      1.0.0
 */

// 1. Setup Mock Environment for standalone CLI execution.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['wp_options'] = array();
$GLOBALS['wp_queries'] = array();
$GLOBALS['wp_tables']  = array();

// Mock WPDB
class MockWPDB_Phase2 {
	public string $prefix = 'customprefix_';
	public array $mock_inserted_rows = array();

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function prepare( $query, ...$args ): string {
		return vsprintf( str_replace( array( '%s', '%d', '%f' ), array( "'%s'", '%d', '%f' ), $query ), $args );
	}

	public function get_var( $query ) {
		// Mock table existence check.
		if ( strpos( $query, 'SHOW TABLES LIKE' ) !== false ) {
			preg_match( "/LIKE '([^']+)'/", $query, $m );
			$tbl = $m[1] ?? '';
			return $tbl;
		}

		// Mock slug existence check in tpm_statuses.
		if ( strpos( $query, 'SELECT id FROM' ) !== false && strpos( $query, 'status_slug' ) !== false ) {
			preg_match( "/status_slug = '([^']+)'/", $query, $m );
			$slug = $m[1] ?? '';
			return isset( $this->mock_inserted_rows[ $slug ] ) ? 1 : null;
		}

		return 0;
	}

	public function insert( $table, $data, $format = null ): bool {
		if ( isset( $data['status_slug'] ) ) {
			$this->mock_inserted_rows[ $data['status_slug'] ] = $data;
		}
		return true;
	}

	public function query( $query ) {
		$GLOBALS['wp_queries'][] = $query;
		return true;
	}
}

$GLOBALS['wpdb'] = new MockWPDB_Phase2();

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $query ) {
		$GLOBALS['dbDelta_queries'][] = $query;
		return array( 'success' );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['wp_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['wp_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

// ----------------------------------------------------
// Load Main Plugin and Database Class
// ----------------------------------------------------
require_once dirname( __DIR__ ) . '/tender-po-management.php';
require_once dirname( __DIR__ ) . '/includes/database/class-tpm-database.php';

echo "====================================================\n";
echo " TENDER & PO MANAGEMENT - PHASE 2 DATABASE TEST SUITE\n";
echo "====================================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function assert_test( string $description, bool $condition ): void {
	global $tests_passed, $tests_failed;
	if ( $condition ) {
		echo " [PASS] " . $description . "\n";
		$tests_passed++;
	} else {
		echo " [FAIL] " . $description . "\n";
		$tests_failed++;
	}
}

// 1. Test Constants
assert_test( 'Constant TPM_DB_VERSION is defined as 1.0.0', defined( 'TPM_DB_VERSION' ) && '1.0.0' === TPM_DB_VERSION );

// 2. Test Dynamic Prefix on Table Names (no hardcoded 'wp_')
$tables = TPM_Database::get_table_names();
assert_test( 'Tables array contains exactly 7 tables', count( $tables ) === 7 );
assert_test( 'Table records uses dynamic prefix', $tables['records'] === 'customprefix_tpm_records' );
assert_test( 'Table customers uses dynamic prefix', $tables['customers'] === 'customprefix_tpm_customers' );
assert_test( 'Table partner_companies uses dynamic prefix', $tables['partner_companies'] === 'customprefix_tpm_partner_companies' );
assert_test( 'Table attachments uses dynamic prefix', $tables['attachments'] === 'customprefix_tpm_attachments' );
assert_test( 'Table payments uses dynamic prefix', $tables['payments'] === 'customprefix_tpm_payments' );
assert_test( 'Table activity_logs uses dynamic prefix', $tables['activity_logs'] === 'customprefix_tpm_activity_logs' );
assert_test( 'Table statuses uses dynamic prefix', $tables['statuses'] === 'customprefix_tpm_statuses' );

// 3. Test Installation & Schema Creation
$GLOBALS['dbDelta_queries'] = array();
TPM_Database::install();

assert_test( 'dbDelta was called for all 7 table schemas', count( $GLOBALS['dbDelta_queries'] ) === 7 );
assert_test( 'tpm_db_version stored in wp_options', get_option( 'tpm_db_version' ) === '1.0.0' );

// 4. Verify Field Declarations in Schema Queries
$records_sql = $GLOBALS['dbDelta_queries'][0];
assert_test( 'tpm_records contains record_type column', strpos( $records_sql, 'record_type' ) !== false );
assert_test( 'tpm_records contains tender_number column', strpos( $records_sql, 'tender_number' ) !== false );
assert_test( 'tpm_records contains fill_ref_number column', strpos( $records_sql, 'fill_ref_number' ) !== false );
assert_test( 'tpm_records contains customer_po_number column', strpos( $records_sql, 'customer_po_number' ) !== false );
assert_test( 'tpm_records contains purchase_price and sell_price', strpos( $records_sql, 'purchase_price' ) !== false && strpos( $records_sql, 'sell_price' ) !== false );
assert_test( 'tpm_records contains partner_company_id and partner_percentage', strpos( $records_sql, 'partner_company_id' ) !== false && strpos( $records_sql, 'partner_percentage' ) !== false );
assert_test( 'tpm_records contains all date fields (tender_due_date, shipping_date, settled_date)', strpos( $records_sql, 'tender_due_date' ) !== false && strpos( $records_sql, 'shipping_date' ) !== false && strpos( $records_sql, 'settled_date' ) !== false );
assert_test( 'tpm_records contains original_awb_received flag', strpos( $records_sql, 'original_awb_received' ) !== false );
assert_test( 'tpm_records contains deleted_at soft delete column', strpos( $records_sql, 'deleted_at' ) !== false );

// 5. Verify Required Indexes on tpm_records
assert_test( 'tpm_records indexes: record_type index exists', strpos( $records_sql, 'KEY idx_record_type' ) !== false );
assert_test( 'tpm_records indexes: status index exists', strpos( $records_sql, 'KEY idx_status' ) !== false );
assert_test( 'tpm_records indexes: customer_id index exists', strpos( $records_sql, 'KEY idx_customer_id' ) !== false );
assert_test( 'tpm_records indexes: partner_company_id index exists', strpos( $records_sql, 'KEY idx_partner_company_id' ) !== false );
assert_test( 'tpm_records indexes: is_archived index exists', strpos( $records_sql, 'KEY idx_is_archived' ) !== false );
assert_test( 'tpm_records indexes: tender_number index exists', strpos( $records_sql, 'KEY idx_tender_number' ) !== false );
assert_test( 'tpm_records indexes: customer_po_number index exists', strpos( $records_sql, 'KEY idx_customer_po_number' ) !== false );
assert_test( 'tpm_records indexes: created_at index exists', strpos( $records_sql, 'KEY idx_created_at' ) !== false );

// 6. Verify Attachments Schema (references WordPress Media Library)
$attachments_sql = $GLOBALS['dbDelta_queries'][3];
assert_test( 'tpm_attachments contains attachment_id referring to WP Media Library', strpos( $attachments_sql, 'attachment_id' ) !== false );
assert_test( 'tpm_attachments contains index on attachment_id', strpos( $attachments_sql, 'KEY idx_attachment_id' ) !== false );

// 7. Verify Activity Logs Schema
$logs_sql = $GLOBALS['dbDelta_queries'][5];
assert_test( 'tpm_activity_logs contains action and user_id', strpos( $logs_sql, 'action' ) !== false && strpos( $logs_sql, 'user_id' ) !== false );
assert_test( 'tpm_activity_logs indexes: user_id and action indexed', strpos( $logs_sql, 'KEY idx_user_id' ) !== false && strpos( $logs_sql, 'KEY idx_action' ) !== false );

// 8. Verify Default Statuses Seeding & Idempotency
global $wpdb;
$seeded_count = count( $wpdb->mock_inserted_rows );
assert_test( 'Initial seeding created 16 default statuses', $seeded_count === 16 );
assert_test( 'General status "tender" seeded', isset( $wpdb->mock_inserted_rows['tender'] ) );
assert_test( 'General status "hold" seeded', isset( $wpdb->mock_inserted_rows['hold'] ) );
assert_test( 'Tender-specific status "t-lost" seeded', isset( $wpdb->mock_inserted_rows['t-lost'] ) );
assert_test( 'Tender-specific status "t-past-due" seeded', isset( $wpdb->mock_inserted_rows['t-past-due'] ) );
assert_test( 'PO-specific status "awb" seeded', isset( $wpdb->mock_inserted_rows['awb'] ) );
assert_test( 'PO-specific status "bq" seeded', isset( $wpdb->mock_inserted_rows['bq'] ) );

// Run seeding second time to test idempotency
$second_run_inserted = TPM_Database::seed_default_statuses();
assert_test( 'Second seeding run inserted 0 rows (idempotent)', $second_run_inserted === 0 );
assert_test( 'Total statuses count remains exactly 16 without duplicates', count( $wpdb->mock_inserted_rows) === 16 );

// 9. Verify Migration Engine
$GLOBALS['wp_options']['tpm_db_version'] = '0.9.0';
TPM_Database::migrate();
assert_test( 'Migration engine upgrades outdated version to current TPM_DB_VERSION', get_option( 'tpm_db_version' ) === '1.0.0' );

// 10. Verify Table Status Helper
$verified = TPM_Database::verify_tables();
assert_test( 'verify_tables() checks all 7 tables and reports existence', count( $verified ) === 7 && $verified['records']['exists'] === true );

echo "\n----------------------------------------------------\n";
echo " TEST SUMMARY: {$tests_passed} Passed, {$tests_failed} Failed\n";
echo "----------------------------------------------------\n";

if ( $tests_failed > 0 ) {
	exit( 1 );
}
exit( 0 );
