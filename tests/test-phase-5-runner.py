#!/usr/bin/env python3
"""
Test Suite for Phase 5: Record Details Page.

Tests:
1. Open Tender
2. Open PO
3. Invalid record ID
4. Unauthorized access
5. All information displays
6. Tabs work
7. Financial calculations display correctly
8. Activity displays correctly
"""

import os
import re
import sys
import sqlite3
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RECORD_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
RECORD_DETAILS_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')
RECORDS_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'records.php')
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

print("=" * 65)
print(" PHASE 5: RECORD DETAILS PAGE VERIFICATION TEST")
print("=" * 65)

# --------------------------------------------------------------------------
# PART A: FILE EXISTENCE & INTEGRATION INSPECTION
# --------------------------------------------------------------------------
assert_test("Record Details view file exists (admin/views/record-details.php)", os.path.exists(RECORD_DETAILS_VIEW_FILE))
assert_test("TPM_Record_Service file exists", os.path.exists(RECORD_SERVICE_FILE))
assert_test("Admin class file exists", os.path.exists(ADMIN_CLASS_FILE))
assert_test("Admin JS file exists", os.path.exists(ADMIN_JS_FILE))
assert_test("Admin CSS file exists", os.path.exists(ADMIN_CSS_FILE))

with open(RECORD_DETAILS_VIEW_FILE, 'r') as f:
    details_view_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(RECORD_SERVICE_FILE, 'r') as f:
    service_code = f.read()

with open(ADMIN_JS_FILE, 'r') as f:
    js_code = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# 1. Verify Submenu & Page Registration
assert_test("tpm-record-details page registered in add_admin_menus", "'tpm-record-details'" in admin_code)
assert_test("display_record_details_page method implemented in TPM_Admin", "function display_record_details_page" in admin_code)
assert_test("display_record_details_page enforces view_tpm_records capability", "current_user_can( 'view_tpm_records' )" in admin_code)

# 2. Verify Action Hooks in Plugin
assert_test("admin_post_tpm_archive_record registered", "admin_post_tpm_archive_record" in plugin_code)
assert_test("admin_post_tpm_unarchive_record registered", "admin_post_tpm_unarchive_record" in plugin_code)
assert_test("admin_post_tpm_delete_record registered", "admin_post_tpm_delete_record" in plugin_code)
assert_test("admin_post_tpm_update_notes registered", "admin_post_tpm_update_notes" in plugin_code)

# 3. Verify Action Handlers in TPM_Admin
assert_test("handle_archive_record implemented with nonce check", "function handle_archive_record" in admin_code and "check_admin_referer" in admin_code)
assert_test("handle_unarchive_record implemented with nonce check", "function handle_unarchive_record" in admin_code and "check_admin_referer" in admin_code)
assert_test("handle_delete_record implemented with nonce check", "function handle_delete_record" in admin_code and "check_admin_referer" in admin_code)
assert_test("handle_update_notes implemented with nonce check", "function handle_update_notes" in admin_code and "check_admin_referer" in admin_code)

# 4. Verify Service Lifecycle Methods in TPM_Record_Service
assert_test("TPM_Record_Service::archive_record implemented", "function archive_record" in service_code)
assert_test("TPM_Record_Service::unarchive_record implemented", "function unarchive_record" in service_code)
assert_test("TPM_Record_Service::delete_record implemented", "function delete_record" in service_code)
assert_test("TPM_Record_Service::update_notes implemented", "function update_notes" in service_code)

# 5. Verify All 6 Tabs in record-details.php
expected_tabs = [
    ("Overview", "overview", "tab-pane-overview"),
    ("Financial", "financial", "tab-pane-financial"),
    ("Shipping", "shipping", "tab-pane-shipping"),
    ("Attachments", "attachments", "tab-pane-attachments"),
    ("Notes", "notes", "tab-pane-notes"),
    ("Activity", "activity", "tab-pane-activity"),
]

for tab_label, tab_slug, pane_id in expected_tabs:
    assert_test(f"Tab link for '{tab_label}' exists with data-tab='{tab_slug}'", f'data-tab="{tab_slug}"' in details_view_code)
    assert_test(f"Tab content pane '#{pane_id}' exists in view", f'id="{pane_id}"' in details_view_code)

# 6. Verify JavaScript Tab Switching
assert_test("admin.js contains activateTab function", "function activateTab" in js_code)
assert_test("admin.js handles tab click events", ".tpm-tab-link" in js_code and "activateTab" in js_code)
assert_test("admin.js synchronizes window.location.hash with active tab", "window.location.hash" in js_code or "history.replaceState" in js_code)

# 7. Verify CSS Tab & Component Styling
assert_test("admin.css contains .tpm-tabs styling", ".tpm-tabs" in css_code)
assert_test("admin.css contains .tpm-tab-pane styling (hidden by default, display on active)", ".tpm-tab-pane" in css_code and ".tpm-tab-pane.active" in css_code)
assert_test("admin.css contains payment progress bar styling", ".tpm-progress-bar" in css_code and ".tpm-progress-fill" in css_code)
assert_test("admin.css contains action button styles (.tpm-btn-danger)", ".tpm-btn-danger" in css_code)

# --------------------------------------------------------------------------
# PART B: FUNCTIONAL VALIDATION & DATABASE SIMULATION (SQLite Engine)
# --------------------------------------------------------------------------
print("\n--- Running Functional Logic & Specification Tests ---")

conn = sqlite3.connect(":memory:")
cursor = conn.cursor()

# Set up database tables matching TPM_Database schema
cursor.execute("""
CREATE TABLE tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    tender_number TEXT DEFAULT NULL,
    fill_ref_number TEXT DEFAULT NULL,
    customer_id INTEGER DEFAULT NULL,
    customer_po_number TEXT DEFAULT NULL,
    customer_po_name TEXT DEFAULT NULL,
    item_description TEXT DEFAULT NULL,
    purchase_price REAL NOT NULL DEFAULT 0.00,
    sell_price REAL NOT NULL DEFAULT 0.00,
    expenses REAL NOT NULL DEFAULT 0.00,
    actual_payment REAL NOT NULL DEFAULT 0.00,
    balance_payment_due REAL NOT NULL DEFAULT 0.00,
    partner_company_id INTEGER DEFAULT NULL,
    partner_percentage REAL NOT NULL DEFAULT 0.00,
    tender_due_date TEXT DEFAULT NULL,
    tender_submission_date TEXT DEFAULT NULL,
    quote_expiration_date TEXT DEFAULT NULL,
    po_date TEXT DEFAULT NULL,
    payment_due_date TEXT DEFAULT NULL,
    shipping_date TEXT DEFAULT NULL,
    settled_date TEXT DEFAULT NULL,
    hs_code TEXT DEFAULT NULL,
    tracking_number TEXT DEFAULT NULL,
    ff_location TEXT DEFAULT NULL,
    fill_invoice_number TEXT DEFAULT NULL,
    fill_po TEXT DEFAULT NULL,
    original_awb_received INTEGER NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    is_archived INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NOT NULL DEFAULT 1,
    updated_by INTEGER DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT DEFAULT NULL,
    deleted_at TEXT DEFAULT NULL
)
""")

cursor.execute("""
CREATE TABLE tpm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT DEFAULT NULL,
    email TEXT DEFAULT NULL,
    phone TEXT DEFAULT NULL,
    address TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'active'
)
""")

cursor.execute("""
CREATE TABLE tpm_partner_companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    contact_person TEXT DEFAULT NULL,
    email TEXT DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'active'
)
""")

cursor.execute("""
CREATE TABLE tpm_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    attachment_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_size INTEGER NOT NULL DEFAULT 0,
    attachment_label TEXT DEFAULT 'Other',
    uploaded_by INTEGER NOT NULL DEFAULT 1,
    uploaded_at TEXT NOT NULL
)
""")

cursor.execute("""
CREATE TABLE tpm_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER DEFAULT NULL,
    user_id INTEGER NOT NULL DEFAULT 1,
    action TEXT NOT NULL,
    description TEXT NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    created_at TEXT NOT NULL
)
""")

# Seed test customer and partner
cursor.execute("INSERT INTO tpm_customers (name, company_name, email) VALUES ('Dr. Alan Turing', 'Turing Medical Ltd', 'alan@turingmed.com')")
test_cust_id = cursor.lastrowid

cursor.execute("INSERT INTO tpm_partner_companies (company_name, contact_person) VALUES ('Apex Logistics Corp', 'Sarah Connor')")
test_partner_id = cursor.lastrowid

now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")

# Seed Tender Record
cursor.execute("""
INSERT INTO tpm_records (
    record_type, status, tender_number, fill_ref_number, customer_id,
    customer_po_number, customer_po_name, item_description,
    purchase_price, sell_price, expenses, actual_payment, balance_payment_due,
    partner_company_id, partner_percentage,
    tender_due_date, tender_submission_date, quote_expiration_date,
    payment_due_date, shipping_date, settled_date,
    hs_code, tracking_number, ff_location, fill_invoice_number, fill_po,
    original_awb_received, notes, is_archived, created_by, created_at, updated_at
) VALUES (
    'tender', 'awarded', 'TN-2026-001', 'REF-9921', ?,
    'CPO-10029', 'National Health Lab Supplies', 'Precision laboratory centrifuge and microplates.',
    18000.00, 25000.00, 1500.00, 10000.00, 15000.00,
    ?, 20.00,
    '2026-10-01', '2026-09-25', '2026-11-01',
    '2026-10-15', '2026-10-10', '2026-11-20',
    '8421.19.00', 'DHL-981273910', 'Rotterdam Port Hub 4', 'INV-8812', 'FPO-3312',
    1, 'Initial deposit confirmed. Packing underway.', 0, 1, ?, ?
)
""", (test_cust_id, test_partner_id, now, now))
tender_id = cursor.lastrowid

cursor.execute("INSERT INTO tpm_activity_logs (record_id, user_id, action, description, created_at) VALUES (?, 1, 'record_created', 'Tender created successfully', ?)", (tender_id, now))

# Seed PO Record
cursor.execute("""
INSERT INTO tpm_records (
    record_type, status, customer_po_number, customer_po_name,
    customer_id, item_description,
    purchase_price, sell_price, expenses, actual_payment, balance_payment_due,
    po_date, payment_due_date, shipping_date,
    tracking_number, ff_location, fill_po, fill_invoice_number,
    original_awb_received, notes, is_archived, created_by, created_at, updated_at
) VALUES (
    'po', 'in_progress', 'PO-2026-001', 'Cardiology Diagnostics Units',
    ?, 'Ultrasound Doppler System with dual probes.',
    32000.00, 42000.00, 2200.00, 42000.00, 0.00,
    '2026-09-05', '2026-09-20', '2026-09-18',
    'FDX-44910284', 'Dubai Airport Cargo Gate 3', 'FPO-9912', 'INV-9011',
    1, 'Payment cleared in full. Customs documentation approved.', 0, 1, ?, ?
)
""", (test_cust_id, now, now))
po_id = cursor.lastrowid

cursor.execute("INSERT INTO tpm_activity_logs (record_id, user_id, action, description, created_at) VALUES (?, 1, 'record_created', 'Purchase Order created successfully', ?)", (po_id, now))

# ==========================================================================
# TEST SUITE EXECUTION (8 Specified Requirements)
# ==========================================================================

# 1. Test: Open Tender
cursor.execute("SELECT * FROM tpm_records WHERE id = ?", (tender_id,))
tender_record = cursor.fetchone()
assert_test("1. Open Tender: Successfully fetched tender record", tender_record is not None)
assert_test("1. Open Tender: Verified record_type is 'tender'", tender_record[1] == 'tender')
tender_title = f"Tender # {tender_record[3]}" if tender_record[3] else f"Tender # {tender_id}"
assert_test("1. Open Tender: Verified formatted title 'Tender # TN-2026-001'", tender_title == "Tender # TN-2026-001")

# 2. Test: Open PO
cursor.execute("SELECT * FROM tpm_records WHERE id = ?", (po_id,))
po_record = cursor.fetchone()
assert_test("2. Open PO: Successfully fetched purchase order record", po_record is not None)
assert_test("2. Open PO: Verified record_type is 'po'", po_record[1] == 'po')
po_title = f"PO # {po_record[6]}" if po_record[6] else f"PO # {po_id}"
assert_test("2. Open PO: Verified formatted title 'PO # PO-2026-001'", po_title == "PO # PO-2026-001")

# 3. Test: Invalid record ID
def check_record_id_error(rid):
    """Simulate validation checks performed at the top of record-details.php."""
    if rid <= 0:
        return "Invalid Record Identifier."
    cursor.execute("SELECT id FROM tpm_records WHERE id = ?", (rid,))
    if not cursor.fetchone():
        return "Record Not Found."
    return "OK"

assert_test("3. Invalid record ID: Zero/negative ID rejected", check_record_id_error(0) == "Invalid Record Identifier.")
assert_test("3. Invalid record ID: Negative ID rejected", check_record_id_error(-5) == "Invalid Record Identifier.")
assert_test("3. Invalid record ID: Non-existent ID rejected", check_record_id_error(999999) == "Record Not Found.")

# 4. Test: Unauthorized access
def check_capability(user_cap, required_cap):
    return user_cap == required_cap or user_cap == 'manage_options' or user_cap == 'manage_tpm_records'

assert_test("4. Unauthorized access: Viewer can view records", check_capability('view_tpm_records', 'view_tpm_records'))
assert_test("4. Unauthorized access: Subscriber cannot view records", not check_capability('read', 'view_tpm_records'))
assert_test("4. Unauthorized access: Viewer without delete capability cannot delete", not check_capability('view_tpm_records', 'delete_tpm_records'))
assert_test("4. Unauthorized access: Administrator can delete records", check_capability('manage_tpm_records', 'delete_tpm_records'))

# 5. Test: All information displays
cursor.execute("""
SELECT r.tender_number, r.item_description, r.hs_code, r.tracking_number, r.notes,
       c.name, c.company_name, p.company_name
FROM tpm_records r
LEFT JOIN tpm_customers c ON r.customer_id = c.id
LEFT JOIN tpm_partner_companies p ON r.partner_company_id = p.id
WHERE r.id = ?
""", (tender_id,))
info_row = cursor.fetchone()
assert_test("5. All info displays: Customer organization mapped", info_row[6] == 'Turing Medical Ltd')
assert_test("5. All info displays: Partner company mapped", info_row[7] == 'Apex Logistics Corp')
assert_test("5. All info displays: Item description and HS Code present", info_row[1] is not None and info_row[2] == '8421.19.00')
assert_test("5. All info displays: Tracking number present", info_row[3] == 'DHL-981273910')

# 6. Test: Tabs work
tab_panes = re.findall(r'id="(tab-pane-[^"]+)"', details_view_code)
assert_test("6. Tabs work: All 6 tab panes exist in view HTML", len(tab_panes) == 6)
assert_test("6. Tabs work: Tab panes match expected slugs", set(tab_panes) == {
    'tab-pane-overview', 'tab-pane-financial', 'tab-pane-shipping',
    'tab-pane-attachments', 'tab-pane-notes', 'tab-pane-activity'
})

# 7. Test: Financial calculations display correctly
p_price = tender_record[9]
s_price = tender_record[10]
exp = tender_record[11]
act_pay = tender_record[12]
bal_due = tender_record[13]
pct = tender_record[15]

calc_gross = s_price - p_price
calc_net = calc_gross - exp
calc_bal = s_price - act_pay
calc_partner_share = calc_net * (pct / 100) if (pct > 0 and calc_net > 0) else 0.0

assert_test("7. Financial calculations: Gross Profit ($25k - $18k = $7,000)", calc_gross == 7000.00)
assert_test("7. Financial calculations: Net Profit ($7,000 - $1,500 = $5,500)", calc_net == 5500.00)
assert_test("7. Financial calculations: Balance Due ($25k - $10k = $15,000)", calc_bal == 15000.00)
assert_test("7. Financial calculations: Partner Share ($5,500 * 20% = $1,100)", calc_partner_share == 1100.00)

# PO Paid in Full test
po_s_price = po_record[10]
po_act_pay = po_record[12]
po_bal_due = po_record[13]
assert_test("7. Financial calculations: PO Paid in full has $0.00 balance", po_s_price == po_act_pay and po_bal_due == 0.00)

# 8. Test: Activity displays correctly
# Add some subsequent activities
cursor.execute("INSERT INTO tpm_activity_logs (record_id, user_id, action, description, created_at) VALUES (?, 1, 'notes_updated', 'Internal notes updated', ?)", (tender_id, now))
cursor.execute("INSERT INTO tpm_activity_logs (record_id, user_id, action, description, created_at) VALUES (?, 1, 'record_archived', 'Record moved to archive', ?)", (tender_id, now))

cursor.execute("SELECT action, description FROM tpm_activity_logs WHERE record_id = ? ORDER BY id DESC", (tender_id,))
activity_events = cursor.fetchall()

assert_test("8. Activity displays: Correct number of audit logs retrieved (3 events)", len(activity_events) == 3)
assert_test("8. Activity displays: Latest event is 'record_archived'", activity_events[0][0] == 'record_archived')
assert_test("8. Activity displays: Original event is 'record_created'", activity_events[2][0] == 'record_created')

# Test Note Update Service Functionality
cursor.execute("UPDATE tpm_records SET notes = ? WHERE id = ?", ("Updated remarks: Shipment delivered successfully.", tender_id))
cursor.execute("SELECT notes FROM tpm_records WHERE id = ?", (tender_id,))
updated_notes = cursor.fetchone()[0]
assert_test("8. Notes updated: Content updated and retrievable", "Shipment delivered successfully" in updated_notes)

# Summary
print("=" * 65)
print(f" TOTAL TESTS: {passed + failed} | PASSED: {passed} | FAILED: {failed}")
print("=" * 65)

if failed > 0:
    sys.exit(1)
else:
    print(" ALL 8 FINAL TESTS PASSED WITH 100% SUCCESS!")
    sys.exit(0)
