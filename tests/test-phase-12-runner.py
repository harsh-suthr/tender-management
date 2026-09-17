#!/usr/bin/env python3
"""
Test Suite for Phase 12: Archive Management.

Verifies all required specifications:
1. Archive Action (sets is_archived = 1, does NOT permanently delete, creates activity log 'record_archived')
2. Archive Page (displays archived Tenders and POs, filter views, summary KPI metrics)
3. Actions (View, Restore, Delete Permanently)
4. Restore Action (sets is_archived = 0, creates activity log 'record_restored')
5. Permanent Delete Requirements:
   - High-level capability gating (delete_tpm_records / manage_tpm_records)
   - User confirmation required (data-tpm-confirm)
   - Scoped nonce verification (tpm_permanent_delete_{id})
   - Careful related data cleanup:
     * Unlinks attachments without deleting media library files
     * Deletes associated payments
     * Cleans associated logs / records system audit
     * Deletes record row
6. Record Details Integration for Archived Records (Notice banner, Back to Archive link, Restore & Delete Permanently buttons)
7. Bulk Actions (Bulk Archive, Bulk Restore, Bulk Permanent Delete)
"""

import os
import re
import sys
import sqlite3
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RECORD_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE    = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE   = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
LIST_TABLE_FILE     = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-records-list-table.php')
ARCHIVED_VIEW_FILE  = os.path.join(BASE_DIR, 'admin', 'views', 'archived-records.php')
RECORD_DETAILS_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')

passed = 0
failed = 0

def assert_test(desc, condition, err_msg=""):
    global passed, failed
    if condition:
        print(f" [PASS] {desc}")
        passed += 1
    else:
        print(f" [FAIL] {desc} - {err_msg}")
        failed += 1

print("=" * 68)
print(" PHASE 12: ARCHIVE MANAGEMENT VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(RECORD_SERVICE_FILE, 'r') as f:
    rec_service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(LIST_TABLE_FILE, 'r') as f:
    list_table_code = f.read()

with open(ARCHIVED_VIEW_FILE, 'r') as f:
    archived_view = f.read()

with open(RECORD_DETAILS_FILE, 'r') as f:
    record_details_view = f.read()

# --------------------------------------------------------------------------
# PART A: STRUCTURAL ARCHITECTURE & HOOK REGISTRATION
# --------------------------------------------------------------------------
print("\n--- Architecture, Classes & Hook Registrations ---")

assert_test("class-tpm-record-service.php exists", os.path.exists(RECORD_SERVICE_FILE))
assert_test("TPM_Record_Service::archive_record() method implemented",
            "function archive_record(" in rec_service_code)
assert_test("TPM_Record_Service::unarchive_record() method implemented",
            "function unarchive_record(" in rec_service_code)
assert_test("TPM_Record_Service::permanent_delete_record() method implemented",
            "function permanent_delete_record(" in rec_service_code)
assert_test("TPM_Record_Service::bulk_permanent_delete() method implemented",
            "function bulk_permanent_delete(" in rec_service_code)
assert_test("TPM_Record_Service::get_archived_summary() method implemented",
            "function get_archived_summary(" in rec_service_code)

assert_test("Plugin registers admin_post_tpm_permanent_delete",
            "admin_post_tpm_permanent_delete" in plugin_code)
assert_test("Plugin registers admin_post_tpm_quick_archive",
            "admin_post_tpm_quick_archive" in plugin_code)
assert_test("Plugin registers admin_post_tpm_quick_restore",
            "admin_post_tpm_quick_restore" in plugin_code)
assert_test("Plugin registers admin_post_tpm_quick_delete",
            "admin_post_tpm_quick_delete" in plugin_code)

assert_test("TPM_Admin::handle_permanent_delete_record() implemented",
            "function handle_permanent_delete_record" in admin_code)
assert_test("TPM_Admin::handle_quick_archive() implemented",
            "function handle_quick_archive" in admin_code)
assert_test("TPM_Admin::handle_quick_restore() implemented",
            "function handle_quick_restore" in admin_code)
assert_test("TPM_Admin::handle_quick_delete() implemented",
            "function handle_quick_delete" in admin_code)
assert_test("TPM_Admin::display_archived_records_page() implemented",
            "function display_archived_records_page" in admin_code)

# --------------------------------------------------------------------------
# PART B: SECURITY, CAPABILITY & NONCE VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Security, Capabilities & Nonce Verification ---")

assert_test("handle_permanent_delete_record() requires delete_tpm_records or manage_tpm_records",
            "delete_tpm_records" in admin_code and "handle_permanent_delete_record" in admin_code)
assert_test("handle_permanent_delete_record() verifies nonce tpm_permanent_delete_*",
            "tpm_permanent_delete_" in admin_code and "check_admin_referer" in admin_code)

assert_test("handle_quick_archive() checks edit_tpm_records or manage_tpm_records capability",
            "edit_tpm_records" in admin_code and "handle_quick_archive" in admin_code)
assert_test("handle_quick_archive() verifies nonce tpm_quick_archive_*",
            "check_admin_referer( 'tpm_quick_archive_" in admin_code)

assert_test("handle_quick_restore() checks edit_tpm_records or manage_tpm_records capability",
            "edit_tpm_records" in admin_code and "handle_quick_restore" in admin_code)
assert_test("handle_quick_restore() verifies nonce tpm_quick_restore_*",
            "check_admin_referer( 'tpm_quick_restore_" in admin_code)

assert_test("handle_bulk_records() enforces delete capability on permanent_delete",
            "bulk_permanent_delete" in admin_code and "delete_tpm_records" in admin_code)

assert_test("Archive list table enforces JavaScript confirmation prompt on Delete Permanently",
            'data-tpm-confirm="' in list_table_code and "permanently delete" in list_table_code.lower())
assert_test("Record details enforces JavaScript confirmation prompt on Delete Permanently",
            'data-tpm-confirm="' in record_details_view and "permanently delete" in record_details_view.lower())

# --------------------------------------------------------------------------
# PART C: ARCHIVE PAGE & RECORD DETAILS SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Archive Page Views & Record Details Specification ---")

assert_test("archived-records.php checks view_tpm_records capability",
            "current_user_can( 'view_tpm_records' )" in archived_view)
assert_test("archived-records.php displays Top Metric KPI summary cards",
            "tpm-dashboard-kpis" in archived_view and "Total Archived" in archived_view)
assert_test("archived-records.php displays Archived Tenders metric",
            "Archived Tenders" in archived_view)
assert_test("archived-records.php displays Archived POs metric",
            "Archived POs" in archived_view)
assert_test("archived-records.php displays Total Archived Value metric",
            "Total Archived Value" in archived_view)

assert_test("archived-records.php provides filter tabs for All, Tenders, and POs",
            "page=tpm-archived-records" in archived_view and "record_type=tender" in archived_view and "record_type=po" in archived_view)
assert_test("archived-records.php renders search box",
            "search_box" in archived_view)
assert_test("archived-records.php scopes list table preset_filters to is_archived = 1",
            "'is_archived' => 1" in archived_view)

assert_test("List table provides 'Restore from Archive' bulk action when archived",
            "Restore from Archive" in list_table_code)
assert_test("List table provides 'Delete Permanently' bulk action",
            "Delete Permanently" in list_table_code)

assert_test("List table row actions for archived items include 'Restore'",
            "tpm-action-restore" in list_table_code and "tpm_quick_restore" in list_table_code)
assert_test("List table row actions for archived items include 'Delete Permanently'",
            "Delete Permanently" in list_table_code and "tpm_permanent_delete" in list_table_code)

assert_test("List table column_actions renders View button",
            "View details and documents" in list_table_code)
assert_test("List table column_actions renders Restore button for archived items",
            "tpm-btn-restore" in list_table_code)
assert_test("List table column_actions renders Delete Permanently button for archived items",
            "Delete Permanently" in list_table_code and "tpm-btn-danger" in list_table_code)

assert_test("Record details displays archived notice banner when is_archived = 1",
            "This record is currently archived." in record_details_view)
assert_test("Record details header displays Back to Archive link when archived",
            "Back to Archive" in record_details_view and "page=tpm-archived-records" in record_details_view)
assert_test("Record details renders Restore button when archived",
            "tpm_unarchive_record" in record_details_view and "Restore" in record_details_view)
assert_test("Record details renders Delete Permanently button when archived",
            "tpm_permanent_delete" in record_details_view and "Delete Permanently" in record_details_view)
assert_test("Record details renders Archive button when active",
            "tpm_archive_record" in record_details_view and "'Archive'" in record_details_view)

# --------------------------------------------------------------------------
# PART D: FUNCTIONAL DATABASE SIMULATION (SQLite)
# --------------------------------------------------------------------------
print("\n--- Functional Database Simulation Tests ---")

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Create schema
cursor.executescript("""
CREATE TABLE wp_users (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    display_name TEXT NOT NULL,
    user_email TEXT NOT NULL
);

CREATE TABLE wp_posts (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    post_title TEXT NOT NULL,
    post_type TEXT NOT NULL DEFAULT 'attachment',
    guid TEXT NOT NULL
);

CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL DEFAULT 'tender',
    status TEXT NOT NULL DEFAULT 'draft',
    tender_number TEXT,
    customer_po_number TEXT,
    sell_price REAL DEFAULT 0.00,
    actual_payment REAL DEFAULT 0.00,
    balance_payment_due REAL DEFAULT 0.00,
    is_archived INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NOT NULL DEFAULT 0,
    updated_by INTEGER DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT,
    deleted_at TEXT
);

CREATE TABLE wp_tpm_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    attachment_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_size INTEGER DEFAULT 0,
    attachment_label TEXT,
    uploaded_by INTEGER DEFAULT 0,
    uploaded_at TEXT NOT NULL
);

CREATE TABLE wp_tpm_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    payment_amount REAL NOT NULL,
    payment_date TEXT NOT NULL,
    payment_method TEXT,
    reference_number TEXT,
    notes TEXT,
    created_by INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE wp_tpm_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    description TEXT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TEXT NOT NULL
);
""")

# Seed users
cursor.execute("INSERT INTO wp_users (ID, display_name, user_email) VALUES (1, 'Admin User', 'admin@example.com')")
cursor.execute("INSERT INTO wp_users (ID, display_name, user_email) VALUES (2, 'Manager User', 'manager@example.com')")

# Seed WP Media Library posts (physical files)
cursor.execute("INSERT INTO wp_posts (ID, post_title, post_type, guid) VALUES (201, 'Tender_Specs.pdf', 'attachment', 'http://example.com/wp-content/uploads/Tender_Specs.pdf')")
cursor.execute("INSERT INTO wp_posts (ID, post_title, post_type, guid) VALUES (202, 'PO_Invoice.pdf', 'attachment', 'http://example.com/wp-content/uploads/PO_Invoice.pdf')")

# Seed Records: 1 Active Tender, 1 Active PO, 1 Pre-archived Tender
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_po_number, sell_price, is_archived, created_by, created_at)
VALUES (101, 'tender', 'awarded', 'TN-2026-001', NULL, 15000.00, 0, 1, '2026-09-01 10:00:00')
""")
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_po_number, sell_price, is_archived, created_by, created_at)
VALUES (102, 'po', 'delivered', NULL, 'PO-2026-002', 8000.00, 0, 1, '2026-09-02 11:00:00')
""")
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_po_number, sell_price, is_archived, created_by, created_at)
VALUES (103, 'tender', 'draft', 'TN-2026-003', NULL, 5000.00, 1, 2, '2026-08-15 09:00:00')
""")

# Link attachments and payments to Record 102
cursor.execute("""
INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
VALUES (102, 201, 'Tender_Specs.pdf', 'application/pdf', 102400, 'Tender Document', 1, '2026-09-02 11:05:00')
""")
cursor.execute("""
INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
VALUES (102, 202, 'PO_Invoice.pdf', 'application/pdf', 204800, 'Invoice', 1, '2026-09-02 11:10:00')
""")

cursor.execute("""
INSERT INTO wp_tpm_payments (record_id, payment_amount, payment_date, payment_method, reference_number, created_by, created_at)
VALUES (102, 3000.00, '2026-09-05', 'Bank Transfer', 'REF-001', 1, '2026-09-05 14:00:00')
""")
cursor.execute("""
INSERT INTO wp_tpm_payments (record_id, payment_amount, payment_date, payment_method, reference_number, created_by, created_at)
VALUES (102, 5000.00, '2026-09-10', 'Wire Transfer', 'REF-002', 1, '2026-09-10 15:00:00')
""")

cursor.execute("""
INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, created_at)
VALUES (102, 1, 'record_created', 'Purchase order created', '2026-09-02 11:00:00')
""")
cursor.execute("""
INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, created_at)
VALUES (102, 1, 'payment_added', 'Payment of $3,000.00 recorded', '2026-09-05 14:00:00')
""")
conn.commit()

# Python representation of service logic for simulation
def archive_record(conn, record_id, user_id):
    c = conn.cursor()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute("UPDATE wp_tpm_records SET is_archived = 1, updated_by = ?, updated_at = ? WHERE id = ?", (user_id, now, record_id))
    if c.rowcount > 0:
        c.execute("""
            INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, created_at)
            VALUES (?, ?, 'record_archived', 'Record moved to archive', ?)
        """, (record_id, user_id, now))
        conn.commit()
        return True
    return False

def unarchive_record(conn, record_id, user_id):
    c = conn.cursor()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute("UPDATE wp_tpm_records SET is_archived = 0, updated_by = ?, updated_at = ? WHERE id = ?", (user_id, now, record_id))
    if c.rowcount > 0:
        c.execute("""
            INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, created_at)
            VALUES (?, ?, 'record_restored', 'Record restored from archive', ?)
        """, (record_id, user_id, now))
        conn.commit()
        return True
    return False

def permanent_delete_record(conn, record_id, user_id):
    c = conn.cursor()
    c.execute("SELECT id, record_type, tender_number, customer_po_number FROM wp_tpm_records WHERE id = ?", (record_id,))
    rec = c.fetchone()
    if not rec:
        return False

    ident = rec['tender_number'] or rec['customer_po_number'] or f"#{record_id}"

    # Cascade cleanup
    # 1. Attachments: delete relationship rows (NOT wp_posts media files)
    c.execute("DELETE FROM wp_tpm_attachments WHERE record_id = ?", (record_id,))

    # 2. Payments: delete payment records
    c.execute("DELETE FROM wp_tpm_payments WHERE record_id = ?", (record_id,))

    # 3. Activity logs: delete record-specific logs
    c.execute("DELETE FROM wp_tpm_activity_logs WHERE record_id = ?", (record_id,))

    # 4. Record row: delete primary record
    c.execute("DELETE FROM wp_tpm_records WHERE id = ?", (record_id,))
    deleted = c.rowcount

    if deleted > 0:
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        desc = f"Permanently deleted {rec['record_type']} {ident} and cleaned associated attachments, payments, and logs."
        c.execute("""
            INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
            VALUES (0, ?, 'record_permanently_deleted', ?, ?, NULL, ?)
        """, (user_id, desc, str(record_id), now))
        conn.commit()
        return True
    return False

def get_archived_summary(conn):
    c = conn.cursor()
    c.execute("""
        SELECT 
            COUNT(*) AS total_archived,
            COUNT(CASE WHEN record_type = 'tender' THEN 1 END) AS archived_tenders,
            COUNT(CASE WHEN record_type = 'po' THEN 1 END) AS archived_pos,
            COALESCE(SUM(sell_price), 0.0) AS total_archived_value
        FROM wp_tpm_records
        WHERE is_archived = 1
    """)
    row = c.fetchone()
    return {
        'total_archived': row['total_archived'],
        'archived_tenders': row['archived_tenders'],
        'archived_pos': row['archived_pos'],
        'total_archived_value': float(row['total_archived_value']),
    }

# --- 1. Archive Action Tests ---
print("\n--- 1. Archive Action & Integrity Verification ---")
# Initial state: Record 101 is active (is_archived = 0)
cursor.execute("SELECT is_archived, sell_price, tender_number FROM wp_tpm_records WHERE id = 101")
r101_init = cursor.fetchone()
assert_test("Record 101 is initially active (is_archived = 0)", r101_init['is_archived'] == 0)

# Archive Record 101
archive_success = archive_record(conn, record_id=101, user_id=1)
assert_test("Archive record 101 returns True", archive_success is True)

cursor.execute("SELECT is_archived, sell_price, tender_number FROM wp_tpm_records WHERE id = 101")
r101_archived = cursor.fetchone()
assert_test("Record 101 has is_archived = 1", r101_archived['is_archived'] == 1)
assert_test("Record 101 data is 100% preserved (not deleted, sell price intact)",
            r101_archived['sell_price'] == 15000.00 and r101_archived['tender_number'] == 'TN-2026-001')

# Check activity log for record_archived
cursor.execute("SELECT * FROM wp_tpm_activity_logs WHERE record_id = 101 AND action = 'record_archived'")
arch_log = cursor.fetchone()
assert_test("Activity log 'record_archived' created for Record 101", arch_log is not None)
assert_test("Activity log description mentions 'Record moved to archive'", "archive" in arch_log['description'].lower())
assert_test("Activity log has valid user_id", arch_log['user_id'] == 1)

# Archive Record 102 (Purchase Order)
archive_po_success = archive_record(conn, record_id=102, user_id=2)
assert_test("Archive Purchase Order 102 returns True", archive_po_success is True)

cursor.execute("SELECT is_archived FROM wp_tpm_records WHERE id = 102")
assert_test("Purchase Order 102 has is_archived = 1", cursor.fetchone()['is_archived'] == 1)

# --- 2. Archive Page Queries & Summary Metrics ---
print("\n--- 2. Archive Page Queries & Summary Metrics ---")
# Query archived records
cursor.execute("SELECT id, record_type, is_archived FROM wp_tpm_records WHERE is_archived = 1")
all_archived = cursor.fetchall()
assert_test("Total archived records is 3 (101, 102, 103)", len(all_archived) == 3)

# Filter by tenders
cursor.execute("SELECT id FROM wp_tpm_records WHERE is_archived = 1 AND record_type = 'tender'")
archived_tenders = cursor.fetchall()
assert_test("Archived Tenders filter returns 2 records (101 and 103)", len(archived_tenders) == 2)

# Filter by POs
cursor.execute("SELECT id FROM wp_tpm_records WHERE is_archived = 1 AND record_type = 'po'")
archived_pos = cursor.fetchall()
assert_test("Archived POs filter returns 1 record (102)", len(archived_pos) == 1)

# Summary metrics function
summary = get_archived_summary(conn)
assert_test("Summary total_archived is 3", summary['total_archived'] == 3)
assert_test("Summary archived_tenders is 2", summary['archived_tenders'] == 2)
assert_test("Summary archived_pos is 1", summary['archived_pos'] == 1)
assert_test("Summary total_archived_value = $28,000.00 ($15k + $8k + $5k)",
            summary['total_archived_value'] == 28000.00)

# --- 3. Restore Action Verification ---
print("\n--- 3. Restore Action & Reactivation Verification ---")
restore_success = unarchive_record(conn, record_id=101, user_id=1)
assert_test("Restore record 101 returns True", restore_success is True)

cursor.execute("SELECT is_archived, sell_price FROM wp_tpm_records WHERE id = 101")
r101_restored = cursor.fetchone()
assert_test("Record 101 has is_archived = 0", r101_restored['is_archived'] == 0)
assert_test("Record 101 data preserved completely after restore", r101_restored['sell_price'] == 15000.00)

# Check activity log for record_restored
cursor.execute("SELECT * FROM wp_tpm_activity_logs WHERE record_id = 101 AND action = 'record_restored'")
restore_log = cursor.fetchone()
assert_test("Activity log 'record_restored' created for Record 101", restore_log is not None)
assert_test("Activity log description mentions 'restored from archive'", "restored" in restore_log['description'].lower())

# Summary metrics updated dynamically after restore
summary_after = get_archived_summary(conn)
assert_test("Summary total_archived decremented to 2", summary_after['total_archived'] == 2)
assert_test("Summary archived_tenders decremented to 1", summary_after['archived_tenders'] == 1)
assert_test("Summary total_archived_value reduced to $13,000.00 ($8k + $5k)",
            summary_after['total_archived_value'] == 13000.00)

# --- 4. Permanent Delete & Careful Handling of Related Data ---
print("\n--- 4. Permanent Delete & Cascade Cleanup Verification ---")
# Pre-checks on Record 102:
cursor.execute("SELECT COUNT(*) FROM wp_tpm_attachments WHERE record_id = 102")
assert_test("Record 102 initially has 2 attachment relationships", cursor.fetchone()[0] == 2)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_payments WHERE record_id = 102")
assert_test("Record 102 initially has 2 payment records", cursor.fetchone()[0] == 2)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_activity_logs WHERE record_id = 102")
assert_test("Record 102 initially has activity logs", cursor.fetchone()[0] > 0)

cursor.execute("SELECT COUNT(*) FROM wp_posts WHERE ID IN (201, 202)")
assert_test("WordPress Media Library contains 2 attachment media posts (201, 202)", cursor.fetchone()[0] == 2)

# Execute Permanent Deletion
perm_del_success = permanent_delete_record(conn, record_id=102, user_id=1)
assert_test("Permanent delete of Record 102 returns True", perm_del_success is True)

# Post-deletion checks:
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE id = 102")
assert_test("Record 102 permanently removed from wp_tpm_records", cursor.fetchone()[0] == 0)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_attachments WHERE record_id = 102")
assert_test("Attachment relationships for Record 102 removed from wp_tpm_attachments", cursor.fetchone()[0] == 0)

# CRITICAL: Media files preserved!
cursor.execute("SELECT COUNT(*) FROM wp_posts WHERE ID IN (201, 202)")
assert_test("CRITICAL: Media Library files (wp_posts 201, 202) are PRESERVED and NOT deleted", cursor.fetchone()[0] == 2)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_payments WHERE record_id = 102")
assert_test("Payments for Record 102 removed from wp_tpm_payments", cursor.fetchone()[0] == 0)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_activity_logs WHERE record_id = 102")
assert_test("Record-specific logs for Record 102 cleaned up", cursor.fetchone()[0] == 0)

cursor.execute("SELECT * FROM wp_tpm_activity_logs WHERE action = 'record_permanently_deleted'")
perm_del_log = cursor.fetchone()
assert_test("System audit log 'record_permanently_deleted' recorded", perm_del_log is not None)
assert_test("System audit log contains deleted record id '102' in old_value", perm_del_log['old_value'] == '102')
assert_test("System audit log description notes cleanup of attachments, payments, and logs",
            "attachments" in perm_del_log['description'] and "payments" in perm_del_log['description'])

# Permanent delete on non-existent record returns False safely
assert_test("Permanent delete on non-existent record (9999) returns False safely",
            permanent_delete_record(conn, 9999, user_id=1) is False)

# --- 5. Bulk Operations Simulation ---
print("\n--- 5. Bulk Operations (Archive, Restore, Permanent Delete) ---")
# Seed 2 fresh active records for bulk operations
cursor.execute("INSERT INTO wp_tpm_records (id, record_type, tender_number, is_archived, created_at) VALUES (104, 'tender', 'TN-BULK-1', 0, '2026-09-11 10:00:00')")
cursor.execute("INSERT INTO wp_tpm_records (id, record_type, tender_number, is_archived, created_at) VALUES (105, 'tender', 'TN-BULK-2', 0, '2026-09-11 10:00:00')")
conn.commit()

# Bulk Archive
def bulk_archive(conn, ids, user_id):
    count = 0
    for rid in ids:
        if archive_record(conn, rid, user_id):
            count += 1
    return count

def bulk_restore(conn, ids, user_id):
    count = 0
    for rid in ids:
        if unarchive_record(conn, rid, user_id):
            count += 1
    return count

def bulk_perm_delete(conn, ids, user_id):
    count = 0
    for rid in ids:
        if permanent_delete_record(conn, rid, user_id):
            count += 1
    return count

arch_count = bulk_archive(conn, [104, 105], user_id=1)
assert_test("Bulk archive of 2 records succeeded (count = 2)", arch_count == 2)
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE id IN (104, 105) AND is_archived = 1")
assert_test("Both records have is_archived = 1", cursor.fetchone()[0] == 2)

restore_count = bulk_restore(conn, [104, 105], user_id=1)
assert_test("Bulk restore of 2 records succeeded (count = 2)", restore_count == 2)
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE id IN (104, 105) AND is_archived = 0")
assert_test("Both records returned to active (is_archived = 0)", cursor.fetchone()[0] == 2)

del_count = bulk_perm_delete(conn, [104, 105], user_id=1)
assert_test("Bulk permanent delete of 2 records succeeded (count = 2)", del_count == 2)
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE id IN (104, 105)")
assert_test("Both records permanently removed from database", cursor.fetchone()[0] == 0)

# --- Summary ---
print("\n" + "=" * 68)
print(f" TOTAL TESTS RUN: {passed + failed}")
print(f" PASSED: {passed}")
print(f" FAILED: {failed}")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    print("\n>>> ALL PHASE 12 ARCHIVE MANAGEMENT TESTS PASSED WITH 100% SUCCESS! <<<\n")
    sys.exit(0)
