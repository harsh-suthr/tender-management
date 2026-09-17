#!/usr/bin/env python3
"""
Python Test Runner for Phase 2: Database Architecture
Directly validates class-tpm-database.php and tender-po-management.php
for schema requirements, indexes, dynamic prefixes, and default statuses.
"""

import os
import re
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_FILE = os.path.join(BASE_DIR, 'includes', 'database', 'class-tpm-database.php')
MAIN_FILE = os.path.join(BASE_DIR, 'tender-po-management.php')

passed = 0
failed = 0

def assert_test(desc, condition):
    global passed, failed
    if condition:
        print(f" [PASS] {desc}")
        passed += 1
    else:
        print(f" [FAIL] {desc}")
        failed += 1

print("=" * 60)
print(" PHASE 2: DATABASE ARCHITECTURE VERIFICATION TEST")
print("=" * 60)

with open(MAIN_FILE, 'r') as f:
    main_content = f.read()

with open(DB_FILE, 'r') as f:
    db_content = f.read()

# 1. Verify Constants
assert_test("TPM_DB_VERSION constant defined in tender-po-management.php", "define( 'TPM_DB_VERSION', '1.0.0' );" in main_content)

# 2. Verify Class TPM_Database exists
assert_test("Class TPM_Database declared", "class TPM_Database" in db_content)

# 3. Verify Dynamic Prefixes and Table Names
tables = ['tpm_records', 'tpm_customers', 'tpm_partner_companies', 'tpm_attachments', 'tpm_payments', 'tpm_activity_logs', 'tpm_statuses']
for tbl in tables:
    assert_test(f"Table name constant for {tbl} defined", f"'{tbl}'" in db_content)

assert_test("Never hardcodes 'wp_'", "wp_tpm" not in db_content)
assert_test("Uses dynamic $wpdb->prefix", "$wpdb->prefix" in db_content)

# 4. Verify Columns in tpm_records
record_columns = [
    'record_type', 'status', 'tender_number', 'fill_ref_number', 'customer_id',
    'customer_po_number', 'customer_po_name', 'item_description', 'purchase_price',
    'sell_price', 'expenses', 'actual_payment', 'balance_payment_due', 'partner_company_id',
    'partner_percentage', 'tender_due_date', 'tender_submission_date', 'quote_expiration_date',
    'po_date', 'payment_due_date', 'shipping_date', 'settled_date', 'hs_code',
    'tracking_number', 'ff_location', 'fill_invoice_number', 'fill_po',
    'original_awb_received', 'notes', 'is_archived', 'created_by', 'updated_by',
    'created_at', 'updated_at', 'deleted_at'
]
for col in record_columns:
    assert_test(f"tpm_records column '{col}' defined", re.search(r'\b' + col + r'\b', db_content) is not None)

# 5. Verify Indexes in tpm_records
record_indexes = [
    'KEY idx_record_type (record_type)',
    'KEY idx_status (status)',
    'KEY idx_customer_id (customer_id)',
    'KEY idx_partner_company_id (partner_company_id)',
    'KEY idx_is_archived (is_archived)',
    'KEY idx_tender_number (tender_number)',
    'KEY idx_customer_po_number (customer_po_number)',
    'KEY idx_created_at (created_at)',
]
for idx in record_indexes:
    assert_test(f"tpm_records index: {idx}", idx in db_content)

# 6. Verify Attachments table references WordPress Media Library attachment_id
assert_test("tpm_attachments references attachment_id", "attachment_id bigint(20) unsigned" in db_content)
assert_test("tpm_attachments has KEY idx_attachment_id", "KEY idx_attachment_id (attachment_id)" in db_content)

# 7. Verify Activity Logs indexes
assert_test("tpm_activity_logs has KEY idx_record_id", "KEY idx_record_id (record_id)" in db_content)
assert_test("tpm_activity_logs has KEY idx_user_id", "KEY idx_user_id (user_id)" in db_content)
assert_test("tpm_activity_logs has KEY idx_action", "KEY idx_action (action)" in db_content)
assert_test("tpm_activity_logs has KEY idx_created_at", "KEY idx_created_at (created_at)" in db_content)

# 8. Verify Default Statuses Seeding
general_statuses = ['tender', 'submitted', 'complete', 'partial', 'payment', 'hold', 'expense', 'duplicate', 'repair', 'archive']
for st in general_statuses:
    assert_test(f"General default status '{st}' present", f"'status_slug' => '{st}'" in db_content)

tender_statuses = ['t-lost', 't-past-due', 't-submitted']
for st in tender_statuses:
    assert_test(f"Tender-specific default status '{st}' present", f"'status_slug' => '{st}'" in db_content)

po_statuses = ['po', 'awb', 'bq']
for st in po_statuses:
    assert_test(f"PO-specific default status '{st}' present", f"'status_slug' => '{st}'" in db_content)

# 9. Verify Idempotency Check in Seeder
assert_test("Idempotent check before status insert", "SELECT id FROM `{$table}` WHERE status_slug = %s" in db_content)

# 10. Verify dbDelta and Database Versioning
assert_test("Uses dbDelta()", "dbDelta( $query )" in db_content)
assert_test("Stores tpm_db_version in options", "update_option( 'tpm_db_version', TPM_DB_VERSION );" in db_content)
assert_test("Migration method implemented", "public static function migrate(): void" in db_content)

print("-" * 60)
print(f" TEST RESULTS: {passed} Passed, {failed} Failed")
print("-" * 60)

if failed > 0:
    sys.exit(1)
print(" ALL PHASE 2 DATABASE SPECIFICATIONS VERIFIED PERFECTLY!")
sys.exit(0)
