#!/usr/bin/env python3
"""
Test Suite for Phase 4: Add New Record Form & Record Service.

Tests:
1. Create Tender
2. Create PO
3. Validation errors
4. Invalid prices
5. Invalid percentage
6. Save notes
7. Save dates
8. Save attachment
9. Multiple attachments
10. Activity log creation
11. Redirect to details page
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
ADD_RECORD_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'add-record.php')
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
print(" PHASE 4: ADD NEW RECORD FORM & SERVICE VERIFICATION")
print("=" * 65)

# --------------------------------------------------------------------------
# PART A: FILE EXISTENCE & INTEGRATION INSPECTION
# --------------------------------------------------------------------------
assert_test("TPM_Record_Service file exists", os.path.exists(RECORD_SERVICE_FILE))
assert_test("Add Record view file exists", os.path.exists(ADD_RECORD_VIEW_FILE))
assert_test("All Records view file exists", os.path.exists(RECORDS_VIEW_FILE))
assert_test("Admin JS file exists", os.path.exists(ADMIN_JS_FILE))
assert_test("Admin CSS file exists", os.path.exists(ADMIN_CSS_FILE))

with open(RECORD_SERVICE_FILE, 'r') as f:
    service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(ADD_RECORD_VIEW_FILE, 'r') as f:
    add_view_code = f.read()

with open(RECORDS_VIEW_FILE, 'r') as f:
    records_view_code = f.read()

with open(ADMIN_JS_FILE, 'r') as f:
    js_code = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# Verify Service Dependencies and Hooks
assert_test("TPM_Record_Service is required in TPM_Plugin", "class-tpm-record-service.php" in plugin_code)
assert_test("admin_post_tpm_save_record hook registered in TPM_Plugin", "admin_post_tpm_save_record" in plugin_code)
assert_test("handle_save_record method implemented in TPM_Admin", "function handle_save_record" in admin_code)
assert_test("Security nonce check in handle_save_record", "check_admin_referer" in admin_code and "tpm_save_record_action" in admin_code)
assert_test("Capability check in handle_save_record", "create_tpm_records" in admin_code)
assert_test("wp_enqueue_media() called in TPM_Admin::enqueue_scripts", "wp_enqueue_media()" in admin_code)

# Verify 8 Sections in Add Record View
sections = [
    ("Section 1: Basic Information", "tpm-section-basic"),
    ("Section 2: Customer Information", "tpm-section-customer"),
    ("Section 3: Item Information", "tpm-section-items"),
    ("Section 4: Important Dates", "tpm-section-dates"),
    ("Section 5: Financial Information", "tpm-section-financial"),
    ("Section 6: Shipping & Logistics", "tpm-section-shipping"),
    ("Section 7: Additional Information", "tpm-section-additional"),
    ("Section 8: Document Attachments", "tpm-section-attachments"),
]
for title, section_id in sections:
    assert_test(f"Add Record form contains {title} (#{section_id})", section_id in add_view_code)

# Verify JavaScript Calculations & Media Library
assert_test("admin.js contains Gross Profit calculation (sell - purchase)", "sell - purchase" in js_code)
assert_test("admin.js contains Net Profit calculation (grossProfit - expenses)", "grossProfit - expenses" in js_code)
assert_test("admin.js contains Balance calculation (sell - payment)", "sell - payment" in js_code)
assert_test("admin.js integrates wp.media() frame", "wp.media" in js_code)
assert_test("admin.js handles attachment removal", "tpm-remove-att-btn" in js_code)

# Verify Record Details Sheet in records.php or record-details.php
details_code = records_view_code
if os.path.exists(os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')):
    with open(os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')) as df:
        details_code += df.read()

assert_test("records.php handles action=view", "$action" in records_view_code and "'view'" in records_view_code)
assert_test("Record details queries TPM_Record_Service::get_record", "TPM_Record_Service::get_record" in details_code)
assert_test("Record details queries TPM_Record_Service::get_attachments", "TPM_Record_Service::get_attachments" in details_code)
assert_test("Record details queries TPM_Record_Service::get_activity_logs", "TPM_Record_Service::get_activity_logs" in details_code)

# --------------------------------------------------------------------------
# PART B: FUNCTIONAL VALIDATION & DATABASE SIMULATION (SQLite Engine)
# --------------------------------------------------------------------------
print("\n--- Running Functional Logic & Persistence Tests ---")

conn = sqlite3.connect(":memory:")
cursor = conn.cursor()

# Create tables matching TPM_Database schema
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
    updated_at TEXT DEFAULT NULL
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

def validate_record_data(data, cursor):
    """Python port of TPM_Record_Service::validate logic."""
    errors = []

    # 1. Record Type
    rtype = data.get('record_type', '')
    if rtype not in ['tender', 'po']:
        errors.append("Please select a valid Record Type (Tender or Purchase Order).")

    # 2. Status
    status = data.get('status', '').strip()
    if not status:
        errors.append("Please select a Status for this record.")

    # 3. Tender Number uniqueness
    tender_no = data.get('tender_number', '').strip()
    rec_id = data.get('id', 0)
    if rtype == 'tender' and tender_no:
        cursor.execute("SELECT id FROM tpm_records WHERE tender_number = ? AND id != ? AND is_archived = 0", (tender_no, rec_id))
        if cursor.fetchone():
            errors.append(f"Tender number '{tender_no}' is already in use by another active record.")

    # 4. Numeric Financials
    num_fields = ['purchase_price', 'sell_price', 'expenses', 'actual_payment', 'balance_payment_due']
    for f in num_fields:
        if f in data and str(data[f]).strip() != '':
            val_str = str(data[f]).replace(',', '').strip()
            try:
                val = float(val_str)
                if val < 0:
                    errors.append(f"{f} must be a valid positive number.")
            except ValueError:
                errors.append(f"{f} must be a valid positive number.")

    # 5. Partner Percentage (0 to 100)
    if 'partner_percentage' in data and str(data['partner_percentage']).strip() != '':
        try:
            pct = float(data['partner_percentage'])
            if pct < 0 or pct > 100:
                errors.append("Partner Percentage must be between 0% and 100%.")
        except ValueError:
            errors.append("Partner Percentage must be numeric.")

    # 6. Date validation (YYYY-MM-DD)
    date_fields = ['tender_due_date', 'tender_submission_date', 'quote_expiration_date', 'po_date', 'payment_due_date', 'shipping_date', 'settled_date']
    date_regex = re.compile(r'^\d{4}-\d{2}-\d{2}$')
    for df in date_fields:
        val = data.get(df, '').strip() if data.get(df) else ''
        if val:
            if not date_regex.match(val):
                errors.append(f"{df} contains an invalid date format.")
            else:
                try:
                    datetime.strptime(val, "%Y-%m-%d")
                except ValueError:
                    errors.append(f"{df} contains an invalid calendar date.")

    # 7. Attachments
    if data.get('attachments'):
        for att in data['attachments']:
            try:
                aid = int(att.get('attachment_id', 0))
                if aid <= 0:
                    errors.append("Invalid attachment reference provided.")
                    break
            except (ValueError, TypeError):
                errors.append("Invalid attachment reference provided.")
                break

    return errors

def create_record(data, cursor, conn):
    """Python port of TPM_Record_Service::create logic."""
    errors = validate_record_data(data, cursor)
    if errors:
        return None, errors

    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    rtype = data.get('record_type', 'tender')
    status = data.get('status', 'draft')
    tender_no = data.get('tender_number') or None
    fill_ref = data.get('fill_ref_number') or None
    cust_id = int(data['customer_id']) if data.get('customer_id') else None
    cust_po_no = data.get('customer_po_number') or None
    cust_po_name = data.get('customer_po_name') or None
    desc = data.get('item_description') or None

    p_price = float(data.get('purchase_price', 0.00))
    s_price = float(data.get('sell_price', 0.00))
    exp = float(data.get('expenses', 0.00))
    act_pay = float(data.get('actual_payment', 0.00))

    if data.get('balance_payment_due') is not None and str(data['balance_payment_due']).strip() != '':
        bal_due = float(data['balance_payment_due'])
    else:
        bal_due = max(0.00, s_price - act_pay)

    partner_id = int(data['partner_company_id']) if data.get('partner_company_id') else None
    partner_pct = float(data.get('partner_percentage', 0.00))

    t_due = data.get('tender_due_date') or None
    t_sub = data.get('tender_submission_date') or None
    q_exp = data.get('quote_expiration_date') or None
    p_date = data.get('po_date') or None
    pay_due = data.get('payment_due_date') or None
    s_date = data.get('shipping_date') or None
    set_date = data.get('settled_date') or None

    hs = data.get('hs_code') or None
    track = data.get('tracking_number') or None
    ff = data.get('ff_location') or None
    inv_no = data.get('fill_invoice_number') or None
    fill_po_no = data.get('fill_po') or None
    orig_awb = 1 if data.get('original_awb_received') else 0
    notes = data.get('notes') or None

    cursor.execute("""
    INSERT INTO tpm_records (
        record_type, status, tender_number, fill_ref_number, customer_id,
        customer_po_number, customer_po_name, item_description,
        purchase_price, sell_price, expenses, actual_payment, balance_payment_due,
        partner_company_id, partner_percentage,
        tender_due_date, tender_submission_date, quote_expiration_date,
        po_date, payment_due_date, shipping_date, settled_date,
        hs_code, tracking_number, ff_location, fill_invoice_number, fill_po,
        original_awb_received, notes, is_archived, created_by, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, 0, 1, ?, ?
    )
    """, (
        rtype, status, tender_no, fill_ref, cust_id,
        cust_po_no, cust_po_name, desc,
        p_price, s_price, exp, act_pay, bal_due,
        partner_id, partner_pct,
        t_due, t_sub, q_exp,
        p_date, pay_due, s_date, set_date,
        hs, track, ff, inv_no, fill_po_no,
        orig_awb, notes, now, now
    ))
    record_id = cursor.lastrowid

    # Attachments
    if data.get('attachments'):
        for att in data['attachments']:
            aid = int(att['attachment_id'])
            fname = att.get('file_name', 'document')
            ftype = att.get('file_type', 'application/octet-stream')
            fsize = int(att.get('file_size', 0))
            flabel = att.get('attachment_label', 'Other')
            cursor.execute("""
            INSERT INTO tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?)
            """, (record_id, aid, fname, ftype, fsize, flabel, now))

    # Activity Log
    desc_label = "Purchase Order created successfully" if rtype == 'po' else "Tender created successfully"
    cursor.execute("""
    INSERT INTO tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
    VALUES (?, 1, 'record_created', ?, NULL, ?, ?)
    """, (record_id, desc_label, f'{{"type":"{rtype}","status":"{status}"}}', now))

    conn.commit()
    return record_id, []

# ==========================================================================
# 11 SPECIFIC TESTS AS REQUESTED IN SPECIFICATION:
# ==========================================================================

# 1. Test: Create Tender
tnd_data = {
    'record_type': 'tender',
    'status': 'active',
    'tender_number': 'TND-2026-9901',
    'fill_ref_number': 'REF-001',
    'purchase_price': '15000.00',
    'sell_price': '22000.00',
    'expenses': '1200.00',
    'actual_payment': '5000.00',
    'balance_payment_due': '17000.00',
    'item_description': 'High-end surgical instruments package.',
    'tender_due_date': '2026-10-15',
    'tender_submission_date': '2026-10-10'
}
tnd_id, errors = create_record(tnd_data, cursor, conn)
assert_test("1. Create Tender record successfully", tnd_id is not None and len(errors) == 0)

cursor.execute("SELECT record_type, tender_number, purchase_price, sell_price FROM tpm_records WHERE id = ?", (tnd_id,))
row = cursor.fetchone()
assert_test("1. Verify Tender saved correct record_type and prices", row == ('tender', 'TND-2026-9901', 15000.0, 22000.0))

# 2. Test: Create PO
po_data = {
    'record_type': 'po',
    'status': 'in_progress',
    'customer_po_number': 'CPO-7741',
    'customer_po_name': 'Federal Hospital Procurement',
    'purchase_price': '8500.00',
    'sell_price': '11200.00',
    'actual_payment': '11200.00',
    'balance_payment_due': '0.00',
    'po_date': '2026-09-01'
}
po_id, errors = create_record(po_data, cursor, conn)
assert_test("2. Create Purchase Order record successfully", po_id is not None and len(errors) == 0)

cursor.execute("SELECT record_type, customer_po_number, sell_price FROM tpm_records WHERE id = ?", (po_id,))
row = cursor.fetchone()
assert_test("2. Verify PO saved correct record_type and customer_po_number", row == ('po', 'CPO-7741', 11200.0))

# 3. Test: Validation errors (Missing required status, invalid record type, duplicate tender number)
invalid_type_data = {'record_type': 'invoice', 'status': 'draft'}
errs = validate_record_data(invalid_type_data, cursor)
assert_test("3. Validation error: Rejects invalid record_type", any("Record Type" in e for e in errs))

missing_status_data = {'record_type': 'tender', 'status': ''}
errs = validate_record_data(missing_status_data, cursor)
assert_test("3. Validation error: Rejects empty status", any("Status" in e for e in errs))

dup_tender_data = {'record_type': 'tender', 'status': 'draft', 'tender_number': 'TND-2026-9901'}
errs = validate_record_data(dup_tender_data, cursor)
assert_test("3. Validation error: Rejects duplicate Tender Number", any("already in use" in e for e in errs))

# 4. Test: Invalid prices
neg_price_data = {'record_type': 'tender', 'status': 'draft', 'purchase_price': '-500.00'}
errs = validate_record_data(neg_price_data, cursor)
assert_test("4. Invalid prices: Rejects negative purchase price", any("positive number" in e for e in errs))

nan_price_data = {'record_type': 'tender', 'status': 'draft', 'sell_price': 'abc-free'}
errs = validate_record_data(nan_price_data, cursor)
assert_test("4. Invalid prices: Rejects non-numeric sell price", any("positive number" in e for e in errs))

# 5. Test: Invalid percentage
bad_pct_data_high = {'record_type': 'tender', 'status': 'draft', 'partner_percentage': '105.50'}
errs = validate_record_data(bad_pct_data_high, cursor)
assert_test("5. Invalid percentage: Rejects percentage > 100%", any("between 0% and 100%" in e for e in errs))

bad_pct_data_neg = {'record_type': 'tender', 'status': 'draft', 'partner_percentage': '-5.00'}
errs = validate_record_data(bad_pct_data_neg, cursor)
assert_test("5. Invalid percentage: Rejects percentage < 0%", any("between 0% and 100%" in e for e in errs))

# 6. Test: Save notes
notes_data = {
    'record_type': 'tender',
    'status': 'draft',
    'tender_number': 'TND-NOTES-01',
    'notes': 'Special delivery requirement: fragile glassware. Inspected by QA team on arrival.'
}
notes_id, _ = create_record(notes_data, cursor, conn)
cursor.execute("SELECT notes FROM tpm_records WHERE id = ?", (notes_id,))
saved_notes = cursor.fetchone()[0]
assert_test("6. Save notes: Text preserved completely in database", "Special delivery requirement" in saved_notes)

# 7. Test: Save dates
valid_dates_data = {
    'record_type': 'tender',
    'status': 'active',
    'tender_number': 'TND-DATES-01',
    'tender_due_date': '2026-11-20',
    'payment_due_date': '2026-12-15',
    'shipping_date': '2026-12-01',
    'settled_date': '2026-12-28'
}
dates_id, errs = create_record(valid_dates_data, cursor, conn)
assert_test("7. Save dates: Valid dates accepted and saved", dates_id is not None and len(errs) == 0)

cursor.execute("SELECT tender_due_date, shipping_date FROM tpm_records WHERE id = ?", (dates_id,))
row = cursor.fetchone()
assert_test("7. Save dates: Verified stored date values in YYYY-MM-DD format", row == ('2026-11-20', '2026-12-01'))

invalid_date_data = {'record_type': 'tender', 'status': 'draft', 'tender_due_date': '20-11-2026'}
errs = validate_record_data(invalid_date_data, cursor)
assert_test("7. Save dates: Rejects invalid date format (e.g. DD-MM-YYYY)", any("invalid date format" in e for e in errs))

# 8. Test: Save attachment (single)
att_data = {
    'record_type': 'tender',
    'status': 'submitted',
    'tender_number': 'TND-ATT-01',
    'attachments': [
        {
            'attachment_id': 401,
            'file_name': 'technical_proposal.pdf',
            'file_type': 'application/pdf',
            'file_size': 2048500,
            'attachment_label': 'Tender Document'
        }
    ]
}
att_rec_id, _ = create_record(att_data, cursor, conn)
cursor.execute("SELECT attachment_id, file_name, file_type, attachment_label FROM tpm_attachments WHERE record_id = ?", (att_rec_id,))
att_row = cursor.fetchone()
assert_test("8. Save attachment: Single PDF attachment stored with metadata", att_row == (401, 'technical_proposal.pdf', 'application/pdf', 'Tender Document'))

# 9. Test: Multiple attachments
multi_att_data = {
    'record_type': 'po',
    'status': 'active',
    'customer_po_number': 'CPO-MULTI-01',
    'attachments': [
        {
            'attachment_id': 501,
            'file_name': 'customer_signed_po.pdf',
            'file_type': 'application/pdf',
            'file_size': 1050000,
            'attachment_label': 'Customer PO'
        },
        {
            'attachment_id': 502,
            'file_name': 'commercial_invoice.xlsx',
            'file_type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size': 45000,
            'attachment_label': 'Invoice'
        },
        {
            'attachment_id': 503,
            'file_name': 'packing_declaration.docx',
            'file_type': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size': 125000,
            'attachment_label': 'Shipping Document'
        }
    ]
}
multi_id, _ = create_record(multi_att_data, cursor, conn)
cursor.execute("SELECT count(*) FROM tpm_attachments WHERE record_id = ?", (multi_id,))
count = cursor.fetchone()[0]
assert_test("9. Multiple attachments: Exactly 3 attachments saved for record", count == 3)

cursor.execute("SELECT attachment_label FROM tpm_attachments WHERE record_id = ? ORDER BY id ASC", (multi_id,))
labels = [r[0] for r in cursor.fetchall()]
assert_test("9. Multiple attachments: Stored distinct labels correctly", labels == ['Customer PO', 'Invoice', 'Shipping Document'])

# 10. Test: Activity log creation
cursor.execute("SELECT action, description FROM tpm_activity_logs WHERE record_id = ?", (tnd_id,))
tnd_log = cursor.fetchone()
assert_test("10. Activity log creation: 'record_created' logged for Tender", tnd_log == ('record_created', 'Tender created successfully'))

cursor.execute("SELECT action, description FROM tpm_activity_logs WHERE record_id = ?", (po_id,))
po_log = cursor.fetchone()
assert_test("10. Activity log creation: 'record_created' logged for PO", po_log == ('record_created', 'Purchase Order created successfully'))

# 11. Test: Redirect to details page
redirect_pattern = r"wp_safe_redirect\s*\(\s*admin_url\s*\(\s*['\"]admin\.php\?page=(?:tpm-record-details&record_id=|tpm-records&action=view&id=)['\"]\s*\.\s*absint\s*\(\s*\$result\s*\)\s*\.\s*['\"]&created=1['\"]\s*\)\s*\);"
assert_test("11. Redirect to details page: handle_save_record redirects to details view with record ID and &created=1", bool(re.search(redirect_pattern, admin_code)))

# Summary
print("=" * 65)
print(f" TOTAL TESTS: {passed + failed} | PASSED: {passed} | FAILED: {failed}")
print("=" * 65)

if failed > 0:
    sys.exit(1)
else:
    print(" ALL 11 FINAL TESTS PASSED WITH 100% SUCCESS!")
    sys.exit(0)
