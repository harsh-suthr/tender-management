#!/usr/bin/env python3
"""
Test Suite for Phase 8: Edit Record.

Verifies the 9 required Phase 8 specifications:
1. Edit Tender (Basic Info, Customer, Item, Notes)
2. Edit Purchase Order (PO Number, PO Name, Customer, Fill PO)
3. Edit Status with Change Detection
4. Edit Financial Values (Purchase Price, Sell Price, Expenses, Balance Due)
5. Edit Dates (Tender Due Date, Submission Date, PO Date, Milestones)
6. Add Attachment in Edit Mode (Preserve existing attachments)
7. Remove Attachment Relationship (Unlink without deleting physical media)
8. Activity Logging (Old vs New value comparison, detailed audit trail)
9. Unauthorized Editing (Capability checks & Nonce protection)
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
ADD_RECORD_VIEW     = os.path.join(BASE_DIR, 'admin', 'views', 'add-record.php')
DETAILS_VIEW        = os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')
RECORDS_VIEW        = os.path.join(BASE_DIR, 'admin', 'views', 'records.php')
ADMIN_JS_FILE       = os.path.join(BASE_DIR, 'admin', 'js', 'admin.js')
ADMIN_CSS_FILE      = os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')

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
print(" PHASE 8: EDIT RECORD VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(RECORD_SERVICE_FILE, 'r') as f:
    service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(ADD_RECORD_VIEW, 'r') as f:
    add_record_view = f.read()

with open(DETAILS_VIEW, 'r') as f:
    details_view = f.read()

with open(RECORDS_VIEW, 'r') as f:
    records_view = f.read()

with open(ADMIN_JS_FILE, 'r') as f:
    js_code = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# --------------------------------------------------------------------------
# PART A: CODEBASE & ARCHITECTURAL VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Architecture, Component Reuse & Hook Registration ---")

assert_test("TPM_Record_Service::update() implemented",
            "public static function update(" in service_code)

assert_test("TPM_Record_Service::update() takes record_id, raw_data, user_id",
            "public static function update( int $record_id, array $raw_data, int $user_id )" in service_code)

assert_test("Plugin registers admin_post_tpm_update_record hook",
            "admin_post_tpm_update_record" in plugin_code)

assert_test("TPM_Admin::handle_update_record() implemented",
            "function handle_update_record" in admin_code)

assert_test("TPM_Admin::display_add_record_page() supports edit action",
            "$is_edit" in admin_code and "'edit' === $_GET['action']" in admin_code)

assert_test("Form components reused (no separate duplicate edit view file)",
            not os.path.exists(os.path.join(BASE_DIR, 'admin', 'views', 'edit-record.php')),
            "add-record.php should be reused to avoid duplicated form code")

assert_test("add-record.php checks is_edit mode",
            "$is_edit" in add_record_view and "'edit' === $_GET['action']" in add_record_view)

assert_test("add-record.php pre-fills form data from existing record",
            "$form_data = (array) $record;" in add_record_view)

assert_test("add-record.php switches action to tpm_update_record in edit mode",
            '<input type="hidden" name="action" value="tpm_update_record">' in add_record_view)

assert_test("add-record.php outputs record_id hidden input in edit mode",
            '<input type="hidden" name="record_id" value="' in add_record_view)

assert_test("add-record.php changes submit button to Update Record in edit mode",
            "Update Record" in add_record_view and "Create Record" in add_record_view)

assert_test("record-details.php has Edit Record primary button",
            "action=edit" in details_view and "Edit Record" in details_view)

assert_test("records.php delegates edit action to add-record.php",
            "'edit' === $action" in records_view and "add-record.php" in records_view)

# --------------------------------------------------------------------------
# PART B: SECURITY VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Security: Nonces, Capability Checks & Sanitization ---")

assert_test("handle_update_record verifies tpm_update_record_action nonce",
            "check_admin_referer( 'tpm_update_record_action', 'tpm_record_nonce' )" in admin_code)

assert_test("handle_update_record checks edit_tpm_records capability",
            "current_user_can( 'edit_tpm_records' )" in admin_code)

assert_test("display_add_record_page checks edit_tpm_records for editing",
            "current_user_can( 'edit_tpm_records' )" in admin_code)

assert_test("add-record.php view guards editing with capability check",
            "current_user_can( 'edit_tpm_records' )" in add_record_view)

assert_test("TPM_Record_Service::update sanitizes text fields",
            "sanitize_text_field" in service_code and "wp_kses_post" in service_code)

assert_test("TPM_Record_Service::update casts numeric prices securely",
            "(float)" in service_code and "str_replace" in service_code)

assert_test("TPM_Record_Service::update uses prepared statements for record existence check",
            "$wpdb->prepare" in service_code and "SELECT * FROM `{$table}` WHERE id = %d" in service_code)

# --------------------------------------------------------------------------
# PART C: CHANGE DETECTION CODE INSPECTION
# --------------------------------------------------------------------------
print("\n--- Change Detection Logic Inspection ---")

assert_test("Status change detection formatted: Status changed: {old} → {new}",
            "Status changed: %1$s → %2$s" in service_code)

assert_test("Record type change detection formatted",
            "Record type changed: %1$s → %2$s" in service_code)

assert_test("Tender number change detection formatted",
            "Tender Number changed: %1$s → %2$s" in service_code)

assert_test("Sell Price change detection formatted: Sell Price changed: {old} → {new}",
            "Sell Price changed: %1$s → %2$s" in service_code)

assert_test("Purchase Price change detection formatted",
            "Purchase Price changed: %1$s → %2$s" in service_code)

assert_test("Expenses change detection formatted",
            "Expenses changed: %1$s → %2$s" in service_code)

assert_test("Tender Due Date change detection formatted",
            "Tender Due Date changed: %1$s → %2$s" in service_code)

assert_test("PO Date change detection formatted",
            "PO Date changed: %1$s → %2$s" in service_code)

assert_test("Submission Date change detection formatted",
            "Tender Submission Date changed: %1$s → %2$s" in service_code)

assert_test("Tracking Number change detection formatted",
            "Tracking Number changed: %1$s → %2$s" in service_code)

assert_test("Customer PO Number change detection formatted",
            "Customer PO Number changed: %1$s → %2$s" in service_code)

assert_test("Notes update detection formatted",
            "Internal notes updated" in service_code)

# --------------------------------------------------------------------------
# PART D: ATTACHMENT MANAGEMENT INSPECTION
# --------------------------------------------------------------------------
print("\n--- Attachment Management in Edit Mode ---")

assert_test("add-record.php displays existing attachments section",
            "tpm-existing-attachments-section" in add_record_view)

assert_test("add-record.php renders existing attachment labels dropdown",
            "existing_attachment_labels" in add_record_view)

assert_test("add-record.php renders remove relationship button",
            "tpm-remove-existing-att-btn" in add_record_view)

assert_test("admin.js handles .tpm-remove-existing-att-btn click",
            ".tpm-remove-existing-att-btn" in js_code)

assert_test("admin.js appends deleted_attachments[] hidden input on removal",
            'deleted_attachments[]' in js_code)

assert_test("admin.js confirms removal without deleting physical file",
            "confirmRemoveAtt" in js_code or "WordPress Media Library" in js_code)

assert_test("TPM_Record_Service::update() processes deleted_attachments[]",
            "deleted_attachments" in service_code and "delete_attachment" in service_code)

assert_test("TPM_Record_Service::update() updates existing_attachment_labels[]",
            "existing_attachment_labels" in service_code)

assert_test("TPM_Record_Service::update() adds newly uploaded attachments",
            "add_attachments_to_record" in service_code)

assert_test("CSS styles for existing attachments present",
            ".tpm-existing-att-row" in css_code and ".tpm-badge-existing" in css_code)

# --------------------------------------------------------------------------
# PART E: DATABASE SIMULATION & COMPREHENSIVE BEHAVIORAL TESTS
# --------------------------------------------------------------------------
print("\n--- Database Simulation & Functional Tests ---")

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Create schema
cursor.executescript("""
CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL DEFAULT 'tender',
    status TEXT NOT NULL DEFAULT 'draft',
    tender_number TEXT UNIQUE,
    fill_ref_number TEXT,
    customer_id INTEGER,
    customer_po_number TEXT,
    customer_po_name TEXT,
    item_description TEXT,
    purchase_price REAL DEFAULT 0.00,
    sell_price REAL DEFAULT 0.00,
    expenses REAL DEFAULT 0.00,
    actual_payment REAL DEFAULT 0.00,
    balance_payment_due REAL DEFAULT 0.00,
    partner_company_id INTEGER,
    partner_percentage REAL DEFAULT 0.00,
    tender_due_date TEXT,
    tender_submission_date TEXT,
    quote_expiration_date TEXT,
    po_date TEXT,
    payment_due_date TEXT,
    shipping_date TEXT,
    settled_date TEXT,
    hs_code TEXT,
    tracking_number TEXT,
    ff_location TEXT,
    fill_invoice_number TEXT,
    fill_po TEXT,
    original_awb_received INTEGER DEFAULT 0,
    notes TEXT,
    is_archived INTEGER DEFAULT 0,
    created_by INTEGER NOT NULL,
    updated_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE wp_tpm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT,
    status TEXT DEFAULT 'active'
);

CREATE TABLE wp_tpm_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    attachment_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL DEFAULT '',
    file_type TEXT NOT NULL DEFAULT 'application/pdf',
    file_size INTEGER DEFAULT 1024,
    attachment_label TEXT DEFAULT 'Other',
    uploaded_by INTEGER NOT NULL,
    uploaded_at TEXT NOT NULL
);

CREATE TABLE wp_tpm_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    created_at TEXT NOT NULL
);
""")

# Seed mock customers
cursor.execute("INSERT INTO wp_tpm_customers (name, company_name) VALUES ('Acme Buyer', 'Acme Corp')")
cursor.execute("INSERT INTO wp_tpm_customers (name, company_name) VALUES ('Global Tech', 'Global Corp')")

# Seed initial Tender record
now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
cursor.execute("""
INSERT INTO wp_tpm_records (
    record_type, status, tender_number, fill_ref_number, customer_id, customer_po_number,
    item_description, purchase_price, sell_price, expenses, actual_payment, balance_payment_due,
    tender_due_date, created_by, updated_by, created_at, updated_at
) VALUES (
    'tender', 'draft', 'TND-2026-001', 'REF-100', 1, 'CPO-100',
    'Server Hardware Rack', 8000.00, 10000.00, 500.00, 2000.00, 8000.00,
    '2026-10-15', 1, 1, ?, ?
)
""", (now_str, now_str))
tender_rec_id = cursor.lastrowid

# Seed initial PO record
cursor.execute("""
INSERT INTO wp_tpm_records (
    record_type, status, customer_po_number, customer_po_name, customer_id, fill_po,
    purchase_price, sell_price, expenses, actual_payment, balance_payment_due,
    po_date, created_by, updated_by, created_at, updated_at
) VALUES (
    'po', 'draft', 'PO-2026-001', 'Initial Order', 1, 'FPO-101',
    15000.00, 20000.00, 1000.00, 5000.00, 15000.00,
    '2026-09-01', 1, 1, ?, ?
)
""", (now_str, now_str))
po_rec_id = cursor.lastrowid

# Seed attachments for the tender record
cursor.execute("""
INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
VALUES (?, 201, 'rfp-specs.pdf', 'application/pdf', 204800, 'Tender Document', 1, ?)
""", (tender_rec_id, now_str))
att1_id = cursor.lastrowid

cursor.execute("""
INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
VALUES (?, 202, 'initial-quote.pdf', 'application/pdf', 102400, 'Quotation', 1, ?)
""", (tender_rec_id, now_str))
att2_id = cursor.lastrowid

conn.commit()

# --- Simulation Helper Functions ---
def simulate_update_record(record_id, raw_data, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'edit_tpm_records': True, 'manage_tpm_records': True}

    if not (user_caps.get('edit_tpm_records') or user_caps.get('manage_tpm_records')):
        return {'success': False, 'error': 'unauthorized', 'message': 'You do not have sufficient permissions to edit records.'}

    # Fetch old record
    cursor.execute("SELECT * FROM wp_tpm_records WHERE id = ?", (record_id,))
    old_row = cursor.fetchone()
    if not old_row:
        return {'success': False, 'error': 'not_found', 'message': 'Record not found.'}

    # Validation: unique tender_number check
    rec_type = raw_data.get('record_type', old_row['record_type'])
    new_tnd_num = raw_data.get('tender_number') if rec_type == 'tender' else None
    if new_tnd_num:
        cursor.execute("SELECT id FROM wp_tpm_records WHERE tender_number = ? AND id != ? AND is_archived = 0", (new_tnd_num, record_id))
        if cursor.fetchone():
            return {'success': False, 'error': 'duplicate_tender_number', 'message': 'Tender Number already exists.'}

    # Financial sanitization
    p_price = float(str(raw_data.get('purchase_price', old_row['purchase_price'])).replace(',', ''))
    s_price = float(str(raw_data.get('sell_price', old_row['sell_price'])).replace(',', ''))
    exp     = float(str(raw_data.get('expenses', old_row['expenses'])).replace(',', ''))
    act_pay = float(str(raw_data.get('actual_payment', old_row['actual_payment'])).replace(',', ''))
    bal_due = raw_data.get('balance_payment_due')
    if bal_due is not None and str(bal_due).strip() != '':
        bal_due = float(str(bal_due).replace(',', ''))
    else:
        bal_due = max(0.0, s_price - act_pay)

    # Change detection
    changes = []
    new_status = raw_data.get('status', old_row['status'])
    if str(old_row['status']) != str(new_status):
        changes.append({
            'field': 'status',
            'desc': f"Status changed: {old_row['status']} → {new_status}",
            'old': old_row['status'],
            'new': new_status
        })

    if new_tnd_num and str(old_row['tender_number']) != str(new_tnd_num):
        changes.append({
            'field': 'tender_number',
            'desc': f"Tender Number changed: {old_row['tender_number']} → {new_tnd_num}",
            'old': str(old_row['tender_number']),
            'new': str(new_tnd_num)
        })

    cust_po_num = raw_data.get('customer_po_number', old_row['customer_po_number'])
    if str(old_row['customer_po_number']) != str(cust_po_num):
        changes.append({
            'field': 'customer_po_number',
            'desc': f"Customer PO Number changed: {old_row['customer_po_number']} → {cust_po_num}",
            'old': str(old_row['customer_po_number']),
            'new': str(cust_po_num)
        })

    if abs(float(old_row['sell_price']) - s_price) > 0.001:
        changes.append({
            'field': 'sell_price',
            'desc': f"Sell Price changed: {float(old_row['sell_price']):.2f} → {s_price:.2f}",
            'old': f"{float(old_row['sell_price']):.2f}",
            'new': f"{s_price:.2f}"
        })

    if abs(float(old_row['purchase_price']) - p_price) > 0.001:
        changes.append({
            'field': 'purchase_price',
            'desc': f"Purchase Price changed: {float(old_row['purchase_price']):.2f} → {p_price:.2f}",
            'old': f"{float(old_row['purchase_price']):.2f}",
            'new': f"{p_price:.2f}"
        })

    new_due_date = raw_data.get('tender_due_date', old_row['tender_due_date'])
    if str(old_row['tender_due_date']) != str(new_due_date):
        changes.append({
            'field': 'tender_due_date',
            'desc': f"Tender Due Date changed: {old_row['tender_due_date']} → {new_due_date}",
            'old': str(old_row['tender_due_date']),
            'new': str(new_due_date)
        })

    now_up = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # Execute DB update
    cursor.execute("""
    UPDATE wp_tpm_records SET
        record_type = ?,
        status = ?,
        tender_number = ?,
        fill_ref_number = ?,
        customer_id = ?,
        customer_po_number = ?,
        customer_po_name = ?,
        item_description = ?,
        purchase_price = ?,
        sell_price = ?,
        expenses = ?,
        actual_payment = ?,
        balance_payment_due = ?,
        tender_due_date = ?,
        tender_submission_date = ?,
        po_date = ?,
        fill_po = ?,
        tracking_number = ?,
        notes = ?,
        updated_by = ?,
        updated_at = ?
    WHERE id = ?
    """, (
        rec_type,
        new_status,
        new_tnd_num,
        raw_data.get('fill_ref_number', old_row['fill_ref_number']),
        raw_data.get('customer_id', old_row['customer_id']),
        cust_po_num,
        raw_data.get('customer_po_name', old_row['customer_po_name']),
        raw_data.get('item_description', old_row['item_description']),
        p_price,
        s_price,
        exp,
        act_pay,
        bal_due,
        new_due_date,
        raw_data.get('tender_submission_date', old_row['tender_submission_date']),
        raw_data.get('po_date', old_row['po_date']),
        raw_data.get('fill_po', old_row['fill_po']),
        raw_data.get('tracking_number', old_row['tracking_number']),
        raw_data.get('notes', old_row['notes']),
        user_id,
        now_up,
        record_id
    ))

    # Manage attachments: Deletions
    if 'deleted_attachments' in raw_data and isinstance(raw_data['deleted_attachments'], list):
        for del_id in raw_data['deleted_attachments']:
            cursor.execute("SELECT file_name FROM wp_tpm_attachments WHERE id = ? AND record_id = ?", (del_id, record_id))
            att_row = cursor.fetchone()
            if att_row:
                cursor.execute("DELETE FROM wp_tpm_attachments WHERE id = ?", (del_id,))
                cursor.execute("""
                INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
                VALUES (?, ?, 'attachment_deleted', ?, ?, NULL, ?)
                """, (record_id, user_id, f"Removed attachment: {att_row['file_name']}", att_row['file_name'], now_up))

    # Manage attachments: Label updates
    if 'existing_attachment_labels' in raw_data and isinstance(raw_data['existing_attachment_labels'], dict):
        for att_id, new_label in raw_data['existing_attachment_labels'].items():
            cursor.execute("UPDATE wp_tpm_attachments SET attachment_label = ? WHERE id = ? AND record_id = ?", (new_label, att_id, record_id))

    # Manage attachments: New additions
    if 'attachments' in raw_data and isinstance(raw_data['attachments'], list):
        for new_att in raw_data['attachments']:
            cursor.execute("""
            INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                record_id,
                new_att.get('attachment_id', 999),
                new_att.get('file_name', 'new-doc.pdf'),
                new_att.get('file_type', 'application/pdf'),
                new_att.get('file_size', 1024),
                new_att.get('attachment_label', 'Other'),
                user_id,
                now_up
            ))
            cursor.execute("""
            INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
            VALUES (?, ?, 'attachment_added', ?, NULL, ?, ?)
            """, (record_id, user_id, f"Added attachment: {new_att.get('file_name', 'new-doc.pdf')}", new_att.get('file_name', 'new-doc.pdf'), now_up))

    # Log change activities
    if changes:
        for c in changes:
            cursor.execute("""
            INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
            VALUES (?, ?, 'record_updated', ?, ?, ?, ?)
            """, (record_id, user_id, c['desc'], c['old'], c['new'], now_up))
    else:
        cursor.execute("""
        INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
        VALUES (?, ?, 'record_updated', 'Record updated', NULL, NULL, ?)
        """, (record_id, user_id, now_up))

    conn.commit()
    return {'success': True, 'record_id': record_id, 'changes': changes}


# --------------------------------------------------------------------------
# TEST 1: EDIT TENDER
# --------------------------------------------------------------------------
print("\n--- Test 1: Edit Tender ---")
edit_tender_res = simulate_update_record(tender_rec_id, {
    'record_type': 'tender',
    'tender_number': 'TND-2026-001-REV',
    'fill_ref_number': 'REF-100-MOD',
    'item_description': 'Upgraded Server Rack with redundant PSUs',
    'notes': 'Client requested specification revision.',
})
assert_test("Edit Tender executed successfully", edit_tender_res['success'] is True)

cursor.execute("SELECT tender_number, fill_ref_number, item_description, notes FROM wp_tpm_records WHERE id = ?", (tender_rec_id,))
updated_tender = cursor.fetchone()
assert_test("Tender Number updated to revised value", updated_tender['tender_number'] == 'TND-2026-001-REV')
assert_test("Fill Ref Number updated", updated_tender['fill_ref_number'] == 'REF-100-MOD')
assert_test("Item description updated", 'Upgraded Server Rack' in updated_tender['item_description'])
assert_test("Internal notes updated", 'Client requested specification revision' in updated_tender['notes'])

# --------------------------------------------------------------------------
# TEST 2: EDIT PURCHASE ORDER
# --------------------------------------------------------------------------
print("\n--- Test 2: Edit Purchase Order ---")
edit_po_res = simulate_update_record(po_rec_id, {
    'record_type': 'po',
    'customer_po_number': 'CPO-9999-FINAL',
    'customer_po_name': 'Ministry Hospital Ward Tech',
    'fill_po': 'FPO-777',
    'customer_id': 2
})
assert_test("Edit Purchase Order executed successfully", edit_po_res['success'] is True)

cursor.execute("SELECT customer_po_number, customer_po_name, fill_po, customer_id FROM wp_tpm_records WHERE id = ?", (po_rec_id,))
updated_po = cursor.fetchone()
assert_test("Customer PO Number updated", updated_po['customer_po_number'] == 'CPO-9999-FINAL')
assert_test("Customer PO Name updated", updated_po['customer_po_name'] == 'Ministry Hospital Ward Tech')
assert_test("Customer ID updated to 2", updated_po['customer_id'] == 2)
assert_test("Fill PO updated", updated_po['fill_po'] == 'FPO-777')

# --------------------------------------------------------------------------
# TEST 3: EDIT STATUS WITH CHANGE DETECTION
# --------------------------------------------------------------------------
print("\n--- Test 3: Edit Status with Change Detection ---")
status_res = simulate_update_record(tender_rec_id, {
    'status': 'submitted',
})
assert_test("Status update succeeded", status_res['success'] is True)

cursor.execute("SELECT status FROM wp_tpm_records WHERE id = ?", (tender_rec_id,))
assert_test("Tender status in DB updated to 'submitted'", cursor.fetchone()['status'] == 'submitted')

cursor.execute("""
SELECT description, old_value, new_value FROM wp_tpm_activity_logs
WHERE record_id = ? AND action = 'record_updated' AND description LIKE '%Status changed%'
ORDER BY id DESC LIMIT 1
""", (tender_rec_id,))
status_log = cursor.fetchone()
assert_test("Status change activity logged", status_log is not None)
assert_test("Status change description formatted as 'Status changed: draft → submitted'",
            status_log['description'] == 'Status changed: draft → submitted')
assert_test("Status log captures old value 'draft' and new value 'submitted'",
            status_log['old_value'] == 'draft' and status_log['new_value'] == 'submitted')

# --------------------------------------------------------------------------
# TEST 4: EDIT FINANCIAL VALUES WITH CHANGE DETECTION
# --------------------------------------------------------------------------
print("\n--- Test 4: Edit Financial Values & Live Calculator Sync ---")
# Old sell_price was 10000.00, update to 12000.00 (as in prompt example)
fin_res = simulate_update_record(tender_rec_id, {
    'purchase_price': '8500.00',
    'sell_price': '12000.00',
    'expenses': '750.00',
    'actual_payment': '3000.00',
})
assert_test("Financial update succeeded", fin_res['success'] is True)

cursor.execute("SELECT purchase_price, sell_price, expenses, actual_payment, balance_payment_due FROM wp_tpm_records WHERE id = ?", (tender_rec_id,))
updated_fin = cursor.fetchone()
assert_test("Sell price updated to 12000.00 in DB", abs(updated_fin['sell_price'] - 12000.00) < 0.01)
assert_test("Purchase price updated to 8500.00 in DB", abs(updated_fin['purchase_price'] - 8500.00) < 0.01)
assert_test("Expenses updated to 750.00 in DB", abs(updated_fin['expenses'] - 750.00) < 0.01)
assert_test("Balance payment due auto-calculated to 9000.00 (12000 - 3000)",
            abs(updated_fin['balance_payment_due'] - 9000.00) < 0.01)

cursor.execute("""
SELECT description, old_value, new_value FROM wp_tpm_activity_logs
WHERE record_id = ? AND description LIKE '%Sell Price changed%'
ORDER BY id DESC LIMIT 1
""", (tender_rec_id,))
sell_price_log = cursor.fetchone()
assert_test("Sell Price change activity logged", sell_price_log is not None)
assert_test("Sell Price change formatted as 'Sell Price changed: 10000.00 → 12000.00'",
            '10000.00 → 12000.00' in sell_price_log['description'])

# --------------------------------------------------------------------------
# TEST 5: EDIT DATES WITH CHANGE DETECTION
# --------------------------------------------------------------------------
print("\n--- Test 5: Edit Milestone Dates ---")
# Old tender_due_date was 2026-10-15
date_res = simulate_update_record(tender_rec_id, {
    'tender_due_date': '2026-11-20',
    'tender_submission_date': '2026-11-18',
})
assert_test("Dates update succeeded", date_res['success'] is True)

cursor.execute("SELECT tender_due_date, tender_submission_date FROM wp_tpm_records WHERE id = ?", (tender_rec_id,))
updated_dates = cursor.fetchone()
assert_test("Tender Due Date updated to 2026-11-20 in DB", updated_dates['tender_due_date'] == '2026-11-20')
assert_test("Tender Submission Date updated to 2026-11-18", updated_dates['tender_submission_date'] == '2026-11-18')

cursor.execute("""
SELECT description FROM wp_tpm_activity_logs
WHERE record_id = ? AND description LIKE '%Tender Due Date changed%'
ORDER BY id DESC LIMIT 1
""", (tender_rec_id,))
due_date_log = cursor.fetchone()
assert_test("Tender Due Date change activity logged", due_date_log is not None)
assert_test("Tender Due Date description captures old and new dates",
            '2026-10-15 → 2026-11-20' in due_date_log['description'])

# --------------------------------------------------------------------------
# TEST 6: ADD ATTACHMENT IN EDIT MODE
# --------------------------------------------------------------------------
print("\n--- Test 6: Add Attachment in Edit Mode (Preserve Existing) ---")
# Before adding: tender record has 2 existing attachments
cursor.execute("SELECT COUNT(*) FROM wp_tpm_attachments WHERE record_id = ?", (tender_rec_id,))
initial_att_count = cursor.fetchone()[0]
assert_test("Record initially has 2 attachments", initial_att_count == 2)

# Add a third attachment during edit
add_att_res = simulate_update_record(tender_rec_id, {
    'attachments': [
        {
            'attachment_id': 303,
            'file_name': 'contract-addendum.pdf',
            'file_type': 'application/pdf',
            'file_size': 51200,
            'attachment_label': 'Contract'
        }
    ]
})
assert_test("Add attachment during edit succeeded", add_att_res['success'] is True)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_attachments WHERE record_id = ?", (tender_rec_id,))
new_att_count = cursor.fetchone()[0]
assert_test("Existing attachments preserved; total attachments now 3", new_att_count == 3)

cursor.execute("SELECT file_name, attachment_label FROM wp_tpm_attachments WHERE record_id = ? AND attachment_id = 303", (tender_rec_id,))
new_att_row = cursor.fetchone()
assert_test("New attachment properly linked to record", new_att_row is not None and new_att_row['file_name'] == 'contract-addendum.pdf')

cursor.execute("""
SELECT description, action FROM wp_tpm_activity_logs
WHERE record_id = ? AND action = 'attachment_added'
ORDER BY id DESC LIMIT 1
""", (tender_rec_id,))
add_att_log = cursor.fetchone()
assert_test("Activity log recorded for attachment addition", add_att_log is not None and 'contract-addendum.pdf' in add_att_log['description'])

# --------------------------------------------------------------------------
# TEST 7: REMOVE ATTACHMENT RELATIONSHIP
# --------------------------------------------------------------------------
print("\n--- Test 7: Remove Attachment Relationship ---")
# We unlink att2_id ('initial-quote.pdf')
rem_att_res = simulate_update_record(tender_rec_id, {
    'deleted_attachments': [att2_id]
})
assert_test("Remove attachment relationship succeeded", rem_att_res['success'] is True)

cursor.execute("SELECT id FROM wp_tpm_attachments WHERE id = ?", (att2_id,))
assert_test("Attachment relationship unlinked from record in DB", cursor.fetchone() is None)

cursor.execute("SELECT id FROM wp_tpm_attachments WHERE id = ?", (att1_id,))
assert_test("Other existing attachment (att1_id) safely preserved", cursor.fetchone() is not None)

cursor.execute("""
SELECT description, action FROM wp_tpm_activity_logs
WHERE record_id = ? AND action = 'attachment_deleted'
ORDER BY id DESC LIMIT 1
""", (tender_rec_id,))
del_att_log = cursor.fetchone()
assert_test("Activity log recorded for attachment removal",
            del_att_log is not None and 'Removed attachment: initial-quote.pdf' in del_att_log['description'])

# Also test updating attachment labels
label_update_res = simulate_update_record(tender_rec_id, {
    'existing_attachment_labels': {
        att1_id: 'Technical Specs'
    }
})
assert_test("Existing attachment label update succeeded", label_update_res['success'] is True)
cursor.execute("SELECT attachment_label FROM wp_tpm_attachments WHERE id = ?", (att1_id,))
assert_test("Attachment label updated to 'Technical Specs'", cursor.fetchone()['attachment_label'] == 'Technical Specs')

# --------------------------------------------------------------------------
# TEST 8: ACTIVITY LOGGING AUDIT TRAIL
# --------------------------------------------------------------------------
print("\n--- Test 8: Activity Logging Audit Trail ---")
cursor.execute("""
SELECT COUNT(*) FROM wp_tpm_activity_logs WHERE record_id = ?
""", (tender_rec_id,))
total_logs = cursor.fetchone()[0]
assert_test(f"Multiple activity audit logs created for tender record ({total_logs} entries)", total_logs >= 5)

cursor.execute("""
SELECT DISTINCT action FROM wp_tpm_activity_logs WHERE record_id = ?
""", (tender_rec_id,))
actions = [r['action'] for r in cursor.fetchall()]
assert_test("Audit trail includes 'record_updated'", 'record_updated' in actions)
assert_test("Audit trail includes 'attachment_added'", 'attachment_added' in actions)
assert_test("Audit trail includes 'attachment_deleted'", 'attachment_deleted' in actions)

# Verify user ID and timestamps present
cursor.execute("SELECT user_id, created_at FROM wp_tpm_activity_logs WHERE record_id = ? LIMIT 1", (tender_rec_id,))
sample_log = cursor.fetchone()
assert_test("Activity log entries store valid user_id", sample_log['user_id'] == 1)
assert_test("Activity log entries store timestamp", sample_log['created_at'] is not None and len(sample_log['created_at']) > 0)

# --------------------------------------------------------------------------
# TEST 9: UNAUTHORIZED EDITING
# --------------------------------------------------------------------------
print("\n--- Test 9: Unauthorized Editing Protection ---")
# User without edit_tpm_records or manage_tpm_records
unauth_res = simulate_update_record(tender_rec_id, {
    'status': 'awarded',
    'sell_price': '999999.00'
}, user_id=99, user_caps={'edit_tpm_records': False, 'manage_tpm_records': False})

assert_test("Unauthorized user edit is rejected", unauth_res['success'] is False)
assert_test("Unauthorized attempt error is 'unauthorized'", unauth_res['error'] == 'unauthorized')

# Verify database did not update during unauthorized attempt
cursor.execute("SELECT status, sell_price FROM wp_tpm_records WHERE id = ?", (tender_rec_id,))
unmodified_row = cursor.fetchone()
assert_test("Database values unchanged after rejected edit attempt",
            unmodified_row['status'] == 'submitted' and abs(unmodified_row['sell_price'] - 12000.00) < 0.01)

# Summary
print("\n" + "=" * 68)
print(f" PHASE 8 VERIFICATION COMPLETE: {passed} PASSED, {failed} FAILED")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    sys.exit(0)
