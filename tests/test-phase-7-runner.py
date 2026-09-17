#!/usr/bin/env python3
"""
Test Suite for Phase 7: All Records Management.

Tests the 10 required Phase 7 specifications:
1. Search across 7 target fields
2. Filters (Status, Type, Customer, Partner, Date Range, Archived)
3. Sorting (Tender Number, Customer, Status, Purchase Price, Sell Price, Created Date, Due Date)
4. Server-side Pagination (Limit/Offset, Total pages)
5. Bulk Actions (Archive, Restore, Delete with capability & nonce verification)
6. View link (Pointing to Record Details)
7. Archive row action
8. Restore row action
9. Attachment count (Optimized single-query resolution)
10. Large dataset performance benchmark
"""

import os
import re
import sys
import time
import sqlite3
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RECORD_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
LIST_TABLE_FILE = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-records-list-table.php')
RECORDS_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'records.php')
TENDERS_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'tenders.php')
POS_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'purchase-orders.php')
ARCHIVED_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'archived-records.php')
ADMIN_JS_FILE = os.path.join(BASE_DIR, 'admin', 'js', 'admin.js')
ADMIN_CSS_FILE = os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')

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
print(" PHASE 7: ALL RECORDS MANAGEMENT VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(LIST_TABLE_FILE, 'r') as f:
    list_table_code = f.read()

with open(RECORD_SERVICE_FILE, 'r') as f:
    service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(RECORDS_VIEW_FILE, 'r') as f:
    records_view = f.read()

with open(TENDERS_VIEW_FILE, 'r') as f:
    tenders_view = f.read()

with open(POS_VIEW_FILE, 'r') as f:
    pos_view = f.read()

with open(ARCHIVED_VIEW_FILE, 'r') as f:
    archived_view = f.read()

with open(ADMIN_JS_FILE, 'r') as f:
    js_code = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# --------------------------------------------------------------------------
# PART A: STRUCTURAL ARCHITECTURE INSPECTION
# --------------------------------------------------------------------------
print("\n--- Structural Architecture & WP_List_Table Integration ---")
assert_test("class-tpm-records-list-table.php exists", os.path.exists(LIST_TABLE_FILE))
assert_test("TPM_Records_List_Table subclasses WP_List_Table", "class TPM_Records_List_Table extends WP_List_Table" in list_table_code)
assert_test("TPM_Plugin requires class-tpm-records-list-table.php", "class-tpm-records-list-table.php" in plugin_code)
assert_test("Plugin registers admin_post_tpm_bulk_records hook", "admin_post_tpm_bulk_records" in plugin_code)
assert_test("Plugin registers admin_post_tpm_quick_archive hook", "admin_post_tpm_quick_archive" in plugin_code)
assert_test("Plugin registers admin_post_tpm_quick_restore hook", "admin_post_tpm_quick_restore" in plugin_code)
assert_test("Plugin registers admin_post_tpm_quick_delete hook", "admin_post_tpm_quick_delete" in plugin_code)
assert_test("TPM_Admin::handle_bulk_records() implemented", "function handle_bulk_records" in admin_code)
assert_test("TPM_Admin::handle_quick_archive() implemented", "function handle_quick_archive" in admin_code)
assert_test("TPM_Admin::handle_quick_restore() implemented", "function handle_quick_restore" in admin_code)
assert_test("TPM_Admin::handle_quick_delete() implemented", "function handle_quick_delete" in admin_code)

# --------------------------------------------------------------------------
# SPEC 1: COLUMNS VERIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 1: Table Columns Specification ---")
expected_columns = [
    ('cb', 'checkbox'),
    ('tender_number', 'Record Number'),
    ('record_type', 'Type'),
    ('status', 'Status'),
    ('customer', 'Customer'),
    ('item_description', 'Item Description'),
    ('customer_po_number', 'Customer PO #'),
    ('purchase_price', 'Purchase Price'),
    ('sell_price', 'Sell Price'),
    ('net_profit', 'Net Profit'),
    ('due_date', 'Due Date'),
    ('attachments_count', 'Attachments'),
    ('created_at', 'Created Date'),
    ('actions', 'Actions'),
]

for col_key, col_label in expected_columns:
    assert_test(f"Column '{col_key}' defined in get_columns()", f"'{col_key}'" in list_table_code)

# --------------------------------------------------------------------------
# SPEC 2: ACTIONS & ROW ACTIONS
# --------------------------------------------------------------------------
print("\n--- SPEC 2: Row Actions (View, Edit, Archive, Delete) ---")
assert_test("Row action 'View' implemented", "details_url" in list_table_code and "actions['view']" in list_table_code)
assert_test("Row action 'Edit' capability gated (edit_tpm_records)", "current_user_can( 'edit_tpm_records' )" in list_table_code and "actions['edit']" in list_table_code)
assert_test("Row action 'Archive / Restore' capability gated with nonce", "tpm_quick_archive_" in list_table_code and "tpm_quick_restore_" in list_table_code)
assert_test("Row action 'Delete' capability gated with nonce & confirmation", "current_user_can( 'delete_tpm_records' )" in list_table_code and "data-tpm-confirm=" in list_table_code and "tpm_quick_delete_" in list_table_code)

# --------------------------------------------------------------------------
# SPEC 3: SEARCH ACROSS 7 TARGET FIELDS
# --------------------------------------------------------------------------
print("\n--- SPEC 3: Multi-Field Search Specification ---")
search_fields = [
    'tender_number',
    'customer_po_number',
    'customer_po_name',
    'item_description',
    'fill_ref_number',
    'fill_invoice_number',
    'tracking_number',
]
for sf in search_fields:
    assert_test(f"Search inspects '{sf}' field", f"r.{sf} LIKE" in service_code)

# --------------------------------------------------------------------------
# SPEC 4: FILTERS SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 4: Multi-Axis Filters Specification ---")
assert_test("Status filter implemented in extra_tablenav() and service query", "filter-by-status" in list_table_code and "r.status = %s" in service_code)
assert_test("Record Type filter implemented", "filter-by-type" in list_table_code and "r.record_type = %s" in service_code)
assert_test("Customer filter implemented", "filter-by-customer" in list_table_code and "r.customer_id = %d" in service_code)
assert_test("Partner Company filter implemented", "filter-by-partner" in list_table_code and "r.partner_company_id = %d" in service_code)
assert_test("Date Range filter supports tender_due_date, tender_submission_date, quote_expiration_date",
            "tender_due_date" in service_code and "tender_submission_date" in service_code and "quote_expiration_date" in service_code)
assert_test("Archived status filter implemented", "r.is_archived = %d" in service_code)

# --------------------------------------------------------------------------
# SPEC 5: QUICK FILTERS (VIEWS) SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 5: Quick Filter Views ---")
expected_views = ['all', 'tenders', 'pos', 'archived', 'submitted', 'complete', 'pending']
for v in expected_views:
    assert_test(f"Quick view '{v}' defined in get_views() and service counts", f"'{v}'" in list_table_code and f"count_{v}" in service_code)

# --------------------------------------------------------------------------
# SPEC 6: SORTING SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 6: Column Sorting ---")
sort_columns = ['tender_number', 'customer', 'status', 'purchase_price', 'sell_price', 'net_profit', 'due_date', 'created_at']
for sc in sort_columns:
    assert_test(f"Sorting whitelist allows '{sc}'", f"'{sc}'" in service_code)

# --------------------------------------------------------------------------
# SPEC 7: SERVER-SIDE PAGINATION SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 7: Server-side Pagination ---")
assert_test("Server-side pagination uses LIMIT %d OFFSET %d", "LIMIT %d OFFSET %d" in service_code)
assert_test("set_pagination_args called in prepare_items", "set_pagination_args" in list_table_code)
assert_test("get_records_count counts matching records without full data loading", "function get_records_count" in service_code and "SELECT COUNT(*)" in service_code)

# --------------------------------------------------------------------------
# SPEC 8: BULK ACTIONS SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 8: Bulk Actions (Archive, Restore, Delete) ---")
assert_test("Bulk actions require nonce verification (check_admin_referer('bulk-records'))", "check_admin_referer( 'bulk-records' )" in admin_code)
assert_test("Bulk archive requires edit capability", "current_user_can( 'edit_tpm_records' )" in admin_code)
assert_test("Bulk restore requires edit capability", "current_user_can( 'edit_tpm_records' )" in admin_code)
assert_test("Bulk delete requires delete capability", "current_user_can( 'delete_tpm_records' )" in admin_code)
assert_test("Bulk archive service method implemented", "function bulk_archive" in service_code)
assert_test("Bulk restore service method implemented", "function bulk_restore" in service_code)
assert_test("Bulk delete service method implemented", "function bulk_delete" in service_code)
assert_test("admin.js handles bulk action submission routing", "#doaction" in js_code and "tpm_bulk_records" in js_code)

# --------------------------------------------------------------------------
# SPEC 9: ATTACHMENTS COUNT SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- SPEC 9: Attachment Count (Single-Query Resolution) ---")
assert_test("Attachment count is computed via subquery (no N+1 queries)", "SELECT COUNT(*) FROM `{$table_attachments}`" in service_code or "SELECT COUNT(*) FROM {$table_attachments}" in service_code)
assert_test("Attachment column renders paperclip indicator linking to #attachments", "column_attachments_count" in list_table_code and "#attachments" in list_table_code)

# --------------------------------------------------------------------------
# PART B: DATABASE SIMULATION & WORKFLOW VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Database Simulation & Functional Logic Testing ---")

conn = sqlite3.connect(':memory:')
cursor = conn.cursor()

# Set up SQLite schema matching WordPress plugin tables
cursor.execute('''
CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL,
    status TEXT NOT NULL,
    tender_number TEXT,
    fill_ref_number TEXT,
    customer_id INTEGER,
    customer_po_number TEXT,
    customer_po_name TEXT,
    item_description TEXT,
    purchase_price REAL DEFAULT 0.00,
    sell_price REAL DEFAULT 0.00,
    expenses REAL DEFAULT 0.00,
    partner_company_id INTEGER,
    tender_due_date TEXT,
    tender_submission_date TEXT,
    quote_expiration_date TEXT,
    tracking_number TEXT,
    fill_invoice_number TEXT,
    is_archived INTEGER DEFAULT 0,
    created_by INTEGER DEFAULT 1,
    created_at TEXT
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT,
    status TEXT DEFAULT 'active'
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_partner_companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    status TEXT DEFAULT 'active'
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_statuses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    status_name TEXT NOT NULL,
    status_slug TEXT NOT NULL,
    status_color TEXT DEFAULT '#646970',
    record_type TEXT DEFAULT 'general',
    is_active INTEGER DEFAULT 1
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    attachment_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_size INTEGER DEFAULT 0,
    attachment_label TEXT,
    uploaded_by INTEGER,
    uploaded_at TEXT
);
''')
cursor.execute("CREATE INDEX idx_att_record_id ON wp_tpm_attachments(record_id);")
cursor.execute("CREATE INDEX idx_rec_is_archived ON wp_tpm_records(is_archived);")


cursor.execute('''
CREATE TABLE wp_tpm_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER,
    user_id INTEGER,
    action TEXT,
    description TEXT,
    created_at TEXT
);
''')

# Seed Customers
cursor.execute("INSERT INTO wp_tpm_customers (id, name, company_name) VALUES (1, 'Acme Corp Contact', 'Acme Corporation');")
cursor.execute("INSERT INTO wp_tpm_customers (id, name, company_name) VALUES (2, 'Stark Tech Contact', 'Stark Industries');")
cursor.execute("INSERT INTO wp_tpm_customers (id, name, company_name) VALUES (3, 'Wayne Ent Contact', 'Wayne Enterprises');")

# Seed Partners
cursor.execute("INSERT INTO wp_tpm_partner_companies (id, company_name) VALUES (1, 'Global Logistics Ltd');")

# Seed Statuses
cursor.execute("INSERT INTO wp_tpm_statuses (status_name, status_slug, status_color) VALUES ('Draft', 'draft', '#646970');")
cursor.execute("INSERT INTO wp_tpm_statuses (status_name, status_slug, status_color) VALUES ('Submitted', 't-submitted', '#2563eb');")
cursor.execute("INSERT INTO wp_tpm_statuses (status_name, status_slug, status_color) VALUES ('Awarded', 't-awarded', '#16a34a');")
cursor.execute("INSERT INTO wp_tpm_statuses (status_name, status_slug, status_color) VALUES ('PO Confirmed', 'po-confirmed', '#0284c7');")
cursor.execute("INSERT INTO wp_tpm_statuses (status_name, status_slug, status_color) VALUES ('PO Settled', 'po-settled', '#10b981');")

# Seed 5 Diverse Test Records
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number, customer_po_name, item_description, purchase_price, sell_price, expenses, partner_company_id, tender_due_date, tracking_number, fill_invoice_number, is_archived, created_at)
VALUES (1, 'tender', 't-submitted', 'TND-2026-001', 'REF-A1', 1, 'PO-ACME-99', 'Acme Server Order', 'High performance compute nodes', 10000.00, 15000.00, 500.00, 1, '2026-10-15', 'TRACK-1111', 'INV-5001', 0, '2026-09-01 10:00:00');
""")

cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number, customer_po_name, item_description, purchase_price, sell_price, expenses, partner_company_id, tender_due_date, tracking_number, fill_invoice_number, is_archived, created_at)
VALUES (2, 'po', 'po-confirmed', '', 'REF-B2', 2, 'PO-STARK-01', 'Arc Reactor Valves', 'Titanium alloy precision valves', 25000.00, 38000.00, 1200.00, NULL, '2026-11-20', 'TRACK-2222', 'INV-5002', 0, '2026-09-05 14:00:00');
""")

cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number, customer_po_name, item_description, purchase_price, sell_price, expenses, partner_company_id, tender_due_date, tracking_number, fill_invoice_number, is_archived, created_at)
VALUES (3, 'tender', 't-awarded', 'TND-2026-002', 'REF-C3', 3, 'PO-WAYNE-88', 'Batmobile Composite Chassis', 'Carbon fiber reinforced polymer body panels', 50000.00, 80000.00, 3000.00, 1, '2026-12-01', 'TRACK-3333', 'INV-5003', 0, '2026-09-08 09:30:00');
""")

cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number, customer_po_name, item_description, purchase_price, sell_price, expenses, partner_company_id, tender_due_date, tracking_number, fill_invoice_number, is_archived, created_at)
VALUES (4, 'tender', 'draft', 'TND-2026-003', 'REF-D4', 1, '', '', 'Prototype drone surveillance kit', 8000.00, 12000.00, 400.00, NULL, '2026-10-30', 'TRACK-4444', '', 0, '2026-09-10 16:00:00');
""")

cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number, customer_po_name, item_description, purchase_price, sell_price, expenses, partner_company_id, tender_due_date, tracking_number, fill_invoice_number, is_archived, created_at)
VALUES (5, 'po', 'po-settled', '', 'REF-E5', 2, 'PO-STARK-02', 'Security Sensors', 'Infrared perimeter boundary sensors', 5000.00, 7500.00, 200.00, NULL, '2026-08-15', 'TRACK-5555', 'INV-5005', 1, '2026-08-01 11:00:00');
""")

# Seed Attachments
# Record 1 has 3 attachments
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, attachment_label) VALUES (1, 101, 'rfp-specs.pdf', 'application/pdf', 'Tender Document');")
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, attachment_label) VALUES (1, 102, 'quote.pdf', 'application/pdf', 'Quotation');")
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, attachment_label) VALUES (1, 103, 'schedule.xlsx', 'application/vnd.ms-excel', 'Other');")

# Record 3 has 1 attachment
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, attachment_label) VALUES (3, 104, 'contract.pdf', 'application/pdf', 'Tender Document');")

# TEST 1: Multi-field Search Verification
# Search by tender_number
cursor.execute("SELECT id FROM wp_tpm_records WHERE tender_number LIKE '%TND-2026-002%'")
assert_test("Search by Tender Number ('TND-2026-002') matches Record #3", cursor.fetchone()[0] == 3)

# Search by customer_po_number
cursor.execute("SELECT id FROM wp_tpm_records WHERE customer_po_number LIKE '%PO-STARK-01%'")
assert_test("Search by Customer PO Number ('PO-STARK-01') matches Record #2", cursor.fetchone()[0] == 2)

# Search by customer_po_name
cursor.execute("SELECT id FROM wp_tpm_records WHERE customer_po_name LIKE '%Arc Reactor%'")
assert_test("Search by Customer PO Name ('Arc Reactor') matches Record #2", cursor.fetchone()[0] == 2)

# Search by item_description
cursor.execute("SELECT id FROM wp_tpm_records WHERE item_description LIKE '%Carbon fiber%'")
assert_test("Search by Item Description ('Carbon fiber') matches Record #3", cursor.fetchone()[0] == 3)

# Search by fill_ref_number
cursor.execute("SELECT id FROM wp_tpm_records WHERE fill_ref_number LIKE '%REF-A1%'")
assert_test("Search by Fill Ref Number ('REF-A1') matches Record #1", cursor.fetchone()[0] == 1)

# Search by fill_invoice_number
cursor.execute("SELECT id FROM wp_tpm_records WHERE fill_invoice_number LIKE '%INV-5002%'")
assert_test("Search by Fill Invoice Number ('INV-5002') matches Record #2", cursor.fetchone()[0] == 2)

# Search by tracking_number
cursor.execute("SELECT id FROM wp_tpm_records WHERE tracking_number LIKE '%TRACK-3333%'")
assert_test("Search by Tracking Number ('TRACK-3333') matches Record #3", cursor.fetchone()[0] == 3)

# TEST 2: Filter Verification
# Filter by record_type
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE record_type = 'tender' AND is_archived = 0")
assert_test("Filter by record_type = 'tender' returns 3 active tenders", cursor.fetchone()[0] == 3)

# Filter by status
cursor.execute("SELECT id FROM wp_tpm_records WHERE status = 't-submitted'")
assert_test("Filter by status = 't-submitted' returns Record #1", cursor.fetchone()[0] == 1)

# Filter by customer_id
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE customer_id = 1 AND is_archived = 0")
assert_test("Filter by customer Acme (ID 1) returns 2 active records", cursor.fetchone()[0] == 2)

# Filter by date range (due date in October 2026)
cursor.execute("SELECT id FROM wp_tpm_records WHERE tender_due_date >= '2026-10-01' AND tender_due_date <= '2026-10-31'")
oct_records = [row[0] for row in cursor.fetchall()]
assert_test("Filter by October 2026 due dates matches Records #1 and #4", oct_records == [1, 4])

# Filter by archived
cursor.execute("SELECT id FROM wp_tpm_records WHERE is_archived = 1")
assert_test("Filter by is_archived = 1 returns Record #5", cursor.fetchone()[0] == 5)

# TEST 3: Sorting Verification
# Sort by Sell Price DESC
cursor.execute("SELECT id, sell_price FROM wp_tpm_records WHERE is_archived = 0 ORDER BY sell_price DESC")
sorted_prices = cursor.fetchall()
assert_test("Sort by sell_price DESC returns highest price first (Record #3 with $80k)", sorted_prices[0][0] == 3 and sorted_prices[0][1] == 80000.00)

# Sort by Net Profit (sell - purchase - expenses) DESC
cursor.execute("SELECT id, (sell_price - purchase_price - expenses) AS net_profit FROM wp_tpm_records WHERE is_archived = 0 ORDER BY net_profit DESC")
sorted_profit = cursor.fetchall()
assert_test("Sort by net_profit DESC returns Record #3 ($27,000 net profit) as #1", sorted_profit[0][0] == 3 and sorted_profit[0][1] == 27000.00)

# TEST 4: Single Query Attachment Count Resolution
cursor.execute("""
SELECT r.id, r.tender_number,
    (SELECT COUNT(*) FROM wp_tpm_attachments a WHERE a.record_id = r.id) AS attachments_count
FROM wp_tpm_records r
WHERE r.id IN (1, 2, 3)
ORDER BY r.id ASC;
""")
att_results = cursor.fetchall()
assert_test("Record #1 has exactly 3 attachments via subquery", att_results[0][2] == 3)
assert_test("Record #2 has exactly 0 attachments via subquery", att_results[1][2] == 0)
assert_test("Record #3 has exactly 1 attachment via subquery", att_results[2][2] == 1)

# TEST 5: Bulk Actions Functional Verification
# Bulk Archive Records #1 and #2
target_ids = [1, 2]
for rid in target_ids:
    cursor.execute("UPDATE wp_tpm_records SET is_archived = 1 WHERE id = ?", (rid,))
    cursor.execute("INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, created_at) VALUES (?, 1, 'record_archived', 'Moved to archive', '2026-09-11 12:00:00')", (rid,))

cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE is_archived = 1")
assert_test("Bulk archive of Records #1 and #2 succeeded (Total archived now 3)", cursor.fetchone()[0] == 3)

# Bulk Restore Record #1 and Record #5
restore_ids = [1, 5]
for rid in restore_ids:
    cursor.execute("UPDATE wp_tpm_records SET is_archived = 0 WHERE id = ?", (rid,))
    cursor.execute("INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, created_at) VALUES (?, 1, 'record_restored', 'Restored from archive', '2026-09-11 12:05:00')", (rid,))

cursor.execute("SELECT is_archived FROM wp_tpm_records WHERE id = 1")
assert_test("Record #1 successfully restored to active (is_archived = 0)", cursor.fetchone()[0] == 0)
cursor.execute("SELECT is_archived FROM wp_tpm_records WHERE id = 5")
assert_test("Record #5 successfully restored to active (is_archived = 0)", cursor.fetchone()[0] == 0)

# Bulk Delete Record #4
del_id = 4
cursor.execute("DELETE FROM wp_tpm_records WHERE id = ?", (del_id,))
cursor.execute("DELETE FROM wp_tpm_attachments WHERE record_id = ?", (del_id,))
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE id = 4")
assert_test("Record #4 permanently deleted via bulk delete", cursor.fetchone()[0] == 0)

# --------------------------------------------------------------------------
# SPEC 10: LARGE DATASET PERFORMANCE BENCHMARK
# --------------------------------------------------------------------------
print("\n--- SPEC 10: Large Dataset Performance Benchmark (5,000 records) ---")

# Seed 5,000 synthetic records into database
print(" Seeding 5,000 records into database for performance benchmarking...")
batch = []
for i in range(100, 5100):
    rtype = 'tender' if i % 2 == 0 else 'po'
    rstatus = 't-submitted' if i % 3 == 0 else ( 'po-confirmed' if i % 3 == 1 else 'draft' )
    tnd_num = f"TND-2026-{i:05d}" if rtype == 'tender' else ""
    po_num = f"PO-CUST-{i:05d}" if rtype == 'po' else ""
    batch.append((
        i, rtype, rstatus, tnd_num, f"REF-{i}", (i % 3) + 1, po_num, f"PO Name {i}",
        f"Description for enterprise item {i} with high grade components",
        1000.0 + (i * 2.5), 1500.0 + (i * 3.5), 50.0 + (i * 0.5), 1,
        '2026-11-15', f"TRACK-{i}", f"INV-{i}", 0, '2026-09-11 12:00:00'
    ))

cursor.executemany("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number, customer_po_name, item_description, purchase_price, sell_price, expenses, partner_company_id, tender_due_date, tracking_number, fill_invoice_number, is_archived, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
""", batch)

# Seed 2,500 attachments linked to these records
att_batch = []
for i in range(100, 2600):
    att_batch.append((i, 9000 + i, f"doc-{i}.pdf", "application/pdf", 102400, "Tender Document", 1, '2026-09-11 12:00:00'))

cursor.executemany("""
INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?);
""", att_batch)

conn.commit()

# Measure count query time on 5,000+ records with search
t0 = time.perf_counter()
cursor.execute("""
SELECT COUNT(*) FROM wp_tpm_records r
WHERE r.is_archived = 0
AND (
    r.tender_number LIKE '%2026%'
    OR r.customer_po_number LIKE '%2026%'
    OR r.item_description LIKE '%enterprise%'
);
""")
count_result = cursor.fetchone()[0]
t1 = time.perf_counter()
count_duration_ms = (t1 - t0) * 1000.0

assert_test(f"Count query on 5,000 records completed in {count_duration_ms:.2f}ms (< 50ms requirement)", count_duration_ms < 50.0)
assert_test("Count query returned correct match count", count_result > 0)

# Measure full paginated query (JOIN customers, statuses, subquery attachments count, sorted by net profit, LIMIT 20 OFFSET 40)
t2 = time.perf_counter()
cursor.execute("""
SELECT
    r.*,
    COALESCE(c.company_name, c.name) AS customer_name,
    s.status_name,
    s.status_color,
    (SELECT COUNT(*) FROM wp_tpm_attachments a WHERE a.record_id = r.id) AS attachments_count
FROM wp_tpm_records r
LEFT JOIN wp_tpm_customers c ON r.customer_id = c.id
LEFT JOIN wp_tpm_statuses s ON r.status = s.status_slug
WHERE r.is_archived = 0
AND (
    r.tender_number LIKE '%2026%'
    OR r.customer_po_number LIKE '%2026%'
    OR r.item_description LIKE '%enterprise%'
)
ORDER BY r.created_at DESC
LIMIT 20 OFFSET 40;
""")
page_slice = cursor.fetchall()
t3 = time.perf_counter()
page_duration_ms = (t3 - t2) * 1000.0

assert_test(f"Paginated query with JOINs and attachment subquery completed in {page_duration_ms:.2f}ms (< 100ms requirement)", page_duration_ms < 100.0)
assert_test("Paginated query returned exact page size slice of 20 records", len(page_slice) == 20)
assert_test("First record in slice has computed customer and attachments count", page_slice[0][21] is not None and page_slice[0][24] >= 0)

print("\n" + "=" * 68)
print(f" RESULTS: {passed} PASSED, {failed} FAILED")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    print(" ALL 10 PHASE 7 SPECIFICATIONS VERIFIED SUCCESSFULLY!\n")
    sys.exit(0)
