<?php
/**
 * Test Suite for Phase 4: Add New Record Form & Service
 *
 * Can be executed via CLI or inside WordPress environment.
 *
 * @package Tender_PO_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'TPM_PLUGIN_DIR' ) ) {
	define( 'TPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

$passed = 0;
$failed = 0;

function tpm_test( $desc, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		echo " [PASS] {$desc}\n";
		$passed++;
	} else {
		echo " [FAIL] {$desc}\n";
		$failed++;
	}
}

echo "============================================================\n";
echo " PHASE 4: ADD NEW RECORD FORM & SERVICE TEST SUITE\n";
echo "============================================================\n";

// 1. Files existence
tpm_test( 'TPM_Record_Service file exists', file_exists( TPM_PLUGIN_DIR . 'includes/services/class-tpm-record-service.php' ) );
tpm_test( 'Add record view exists', file_exists( TPM_PLUGIN_DIR . 'admin/views/add-record.php' ) );
tpm_test( 'Records view exists', file_exists( TPM_PLUGIN_DIR . 'admin/views/records.php' ) );
tpm_test( 'Admin JS file exists', file_exists( TPM_PLUGIN_DIR . 'admin/js/admin.js' ) );
tpm_test( 'Admin CSS file exists', file_exists( TPM_PLUGIN_DIR . 'admin/css/admin.css' ) );

// 2. Class definition
require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-record-service.php';
tpm_test( 'TPM_Record_Service class defined', class_exists( 'TPM_Record_Service' ) );
tpm_test( 'validate() method exists', method_exists( 'TPM_Record_Service', 'validate' ) );
tpm_test( 'create() method exists', method_exists( 'TPM_Record_Service', 'create' ) );
tpm_test( 'save_attachments() method exists', method_exists( 'TPM_Record_Service', 'save_attachments' ) );
tpm_test( 'log_activity() method exists', method_exists( 'TPM_Record_Service', 'log_activity' ) );
tpm_test( 'get_record() method exists', method_exists( 'TPM_Record_Service', 'get_record' ) );
tpm_test( 'get_attachments() method exists', method_exists( 'TPM_Record_Service', 'get_attachments' ) );
tpm_test( 'get_activity_logs() method exists', method_exists( 'TPM_Record_Service', 'get_activity_logs' ) );

// 3. Validation Logic Tests
$invalid_type = TPM_Record_Service::validate( array( 'record_type' => 'invoice', 'status' => 'draft' ) );
tpm_test( 'Validation rejects invalid record type', ! empty( $invalid_type ) );

$empty_status = TPM_Record_Service::validate( array( 'record_type' => 'tender', 'status' => '' ) );
tpm_test( 'Validation rejects empty status', ! empty( $empty_status ) );

$neg_price = TPM_Record_Service::validate( array( 'record_type' => 'tender', 'status' => 'draft', 'purchase_price' => '-100' ) );
tpm_test( 'Validation rejects negative price', ! empty( $neg_price ) );

$invalid_pct = TPM_Record_Service::validate( array( 'record_type' => 'tender', 'status' => 'draft', 'partner_percentage' => '120' ) );
tpm_test( 'Validation rejects partner percentage > 100%', ! empty( $invalid_pct ) );

$invalid_date = TPM_Record_Service::validate( array( 'record_type' => 'tender', 'status' => 'draft', 'tender_due_date' => '31/12/2026' ) );
tpm_test( 'Validation rejects invalid date format', ! empty( $invalid_date ) );

$valid_data = TPM_Record_Service::validate(
	array(
		'record_type'            => 'tender',
		'status'                 => 'active',
		'tender_number'          => 'TND-2026-TEST',
		'purchase_price'         => '1000.00',
		'sell_price'             => '1500.00',
		'partner_percentage'     => '15.5',
		'tender_due_date'        => '2026-11-15',
		'tender_submission_date' => '2026-11-10',
	)
);
tpm_test( 'Validation accepts clean valid record data', empty( $valid_data ) );

echo "------------------------------------------------------------\n";
echo " TOTAL TESTS: " . ( $passed + $failed ) . " | PASSED: {$passed} | FAILED: {$failed}\n";
echo "============================================================\n";

exit( $failed > 0 ? 1 : 0 );
