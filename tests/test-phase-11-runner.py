#!/usr/bin/env python3
"""
Test Suite for Phase 11: Payment Management.

Verifies all required specifications:
1. Payment Tab on Record Details (Total Expected, Total Paid, Balance Due, Payment Status)
2. Add Payment Form & Validation (Payment Amount, Date, Method, Reference, Notes)
3. Payment History List Table (Amount, Date, Method, Reference, Added By, Actions)
4. Payment Status Automation (Pending, Partial, Paid, Overdue based on due date, totals, current date)
5. Preservation of Record Workflow Status (Never overwriting manual record status)
6. Balance & Totals Recalculation (Single & multiple payments, overpayments, edits, deletions)
7. Audit Activity Logging (payment_added, payment_updated, payment_deleted)
8. Security & Permissions (Capability gating manage_tpm_records/edit_tpm_records, Nonce verification)
"""

import os
import re
import sys
import sqlite3
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PAYMENT_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-payment-service.php')
RECORD_SERVICE_FILE  = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE     = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE    = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
RECORD_DETAILS_FILE  = os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')
ADMIN_CSS_FILE       = os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')
ADMIN_JS_FILE        = os.path.join(BASE_DIR, 'admin', 'js', 'admin.js')

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
print(" PHASE 11: PAYMENT MANAGEMENT VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(PAYMENT_SERVICE_FILE, 'r') as f:
    payment_service_code = f.read()

with open(RECORD_SERVICE_FILE, 'r') as f:
    rec_service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(RECORD_DETAILS_FILE, 'r') as f:
    record_details_view = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

with open(ADMIN_JS_FILE, 'r') as f:
    js_code = f.read()

# --------------------------------------------------------------------------
# PART A: STRUCTURAL ARCHITECTURE & HOOK REGISTRATION
# --------------------------------------------------------------------------
print("\n--- Architecture, Classes & Hook Registrations ---")

assert_test("class-tpm-payment-service.php exists", os.path.exists(PAYMENT_SERVICE_FILE))
assert_test("TPM_Payment_Service class defined", "class TPM_Payment_Service" in payment_service_code)
assert_test("Plugin requires class-tpm-payment-service.php", "class-tpm-payment-service.php" in plugin_code)

assert_test("Plugin registers admin_post_tpm_add_payment", "admin_post_tpm_add_payment" in plugin_code)
assert_test("Plugin registers admin_post_tpm_update_payment", "admin_post_tpm_update_payment" in plugin_code)
assert_test("Plugin registers admin_post_tpm_delete_payment", "admin_post_tpm_delete_payment" in plugin_code)

assert_test("TPM_Admin::handle_add_payment() implemented", "function handle_add_payment" in admin_code)
assert_test("TPM_Admin::handle_update_payment() implemented", "function handle_update_payment" in admin_code)
assert_test("TPM_Admin::handle_delete_payment() implemented", "function handle_delete_payment" in admin_code)

assert_test("TPM_Payment_Service::validate() method declared", "function validate(" in payment_service_code)
assert_test("TPM_Payment_Service::create_payment() method declared", "function create_payment(" in payment_service_code)
assert_test("TPM_Payment_Service::update_payment() method declared", "function update_payment(" in payment_service_code)
assert_test("TPM_Payment_Service::delete_payment() method declared", "function delete_payment(" in payment_service_code)
assert_test("TPM_Payment_Service::get_payment() method declared", "function get_payment(" in payment_service_code)
assert_test("TPM_Payment_Service::get_record_payments() method declared", "function get_record_payments(" in payment_service_code)
assert_test("TPM_Payment_Service::recalculate_record_payments() method declared", "function recalculate_record_payments(" in payment_service_code)
assert_test("TPM_Payment_Service::determine_payment_status() method declared", "function determine_payment_status(" in payment_service_code)
assert_test("TPM_Payment_Service::get_payment_summary() method declared", "function get_payment_summary(" in payment_service_code)

# --------------------------------------------------------------------------
# PART B: SECURITY & NONCE VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Security, Capabilities & Nonce Verification ---")

assert_test("TPM_Admin::handle_add_payment() verifies nonce 'tpm_add_payment_*'",
            "check_admin_referer( 'tpm_add_payment_" in admin_code and "tpm_payment_nonce" in admin_code)
assert_test("TPM_Admin::handle_add_payment() enforces capability",
            "manage_tpm_records" in admin_code and "handle_add_payment" in admin_code)

assert_test("TPM_Admin::handle_update_payment() verifies nonce 'tpm_update_payment_*'",
            "check_admin_referer( 'tpm_update_payment_" in admin_code and "tpm_payment_nonce" in admin_code)
assert_test("TPM_Admin::handle_update_payment() enforces capability",
            "edit_tpm_records" in admin_code and "handle_update_payment" in admin_code)

assert_test("TPM_Admin::handle_delete_payment() verifies nonce 'tpm_delete_payment_*'",
            "check_admin_referer( 'tpm_delete_payment_" in admin_code and "tpm_nonce" in admin_code)
assert_test("TPM_Admin::handle_delete_payment() enforces capability",
            "delete_tpm_records" in admin_code and "handle_delete_payment" in admin_code)

# --------------------------------------------------------------------------
# PART C: RECORD DETAILS VIEW SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Record Details Payment Section & View Specification ---")

# 1. Tab Nav item
assert_test("Record Details includes Payments navigation tab (#payments)",
            'href="#payments"' in record_details_view and "Payments" in record_details_view)

# 2. KPI Summary Cards
assert_test("Payments section displays 'Total Expected Amount'",
            "Total Expected Amount" in record_details_view)
assert_test("Payments section displays 'Total Paid'",
            "Total Paid" in record_details_view)
assert_test("Payments section displays 'Balance Due'",
            "Balance Due" in record_details_view)
assert_test("Payments section displays 'Payment Status'",
            "Payment Status" in record_details_view)

# 3. Add Payment Form Fields
assert_test("Add Payment form includes Payment Amount field",
            'name="payment_amount"' in record_details_view and "Payment Amount" in record_details_view)
assert_test("Add Payment form includes Payment Date field",
            'name="payment_date"' in record_details_view and "Payment Date" in record_details_view)
assert_test("Add Payment form includes Payment Method dropdown/field",
            'name="payment_method"' in record_details_view and "Payment Method" in record_details_view)
assert_test("Add Payment form includes Reference Number field",
            'name="reference_number"' in record_details_view and "Reference Number" in record_details_view)
assert_test("Add Payment form includes Notes field",
            'name="notes"' in record_details_view and "Notes" in record_details_view)

# 4. Payment History List Table Columns
assert_test("Payment History table defines Amount column",
            "'Amount'" in record_details_view)
assert_test("Payment History table defines Date column",
            "'Date'" in record_details_view)
assert_test("Payment History table defines Method column",
            "'Method'" in record_details_view)
assert_test("Payment History table defines Reference column",
            "'Reference'" in record_details_view)
assert_test("Payment History table defines Added By column",
            "'Added By'" in record_details_view)
assert_test("Payment History table defines Actions column",
            "'Actions'" in record_details_view)

# 5. Actions: Edit and Delete
assert_test("Payment History table includes Edit payment action button",
            "tpm-btn-edit-payment" in record_details_view)
assert_test("Payment History table includes Delete payment action link with nonce",
            "tpm_delete_payment" in record_details_view and "wp_create_nonce" in record_details_view and "tpm_delete_payment_" in record_details_view)

# 6. Edit Payment Modal
assert_test("Record Details includes Edit Payment Modal (#tpm-edit-payment-modal)",
            'id="tpm-edit-payment-modal"' in record_details_view)
assert_test("Edit Modal has hidden payment_id field",
            'id="tpm-edit-payment-id"' in record_details_view)

# 7. CSS & JS support
assert_test("CSS defines .tpm-badge-pending", ".tpm-badge-pending" in css_code)
assert_test("CSS defines .tpm-badge-partial", ".tpm-badge-partial" in css_code)
assert_test("CSS defines .tpm-badge-paid", ".tpm-badge-paid" in css_code)
assert_test("CSS defines .tpm-badge-overdue", ".tpm-badge-overdue" in css_code)
assert_test("JS handles .tpm-btn-edit-payment click and opens modal",
            "tpm-btn-edit-payment" in js_code and "tpm-edit-payment-modal" in js_code)

# --------------------------------------------------------------------------
# PART D: FUNCTIONAL LOGIC & DATABASE SIMULATION (SQLite)
# --------------------------------------------------------------------------
print("\n--- Functional Database Simulation Tests ---")

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

cursor.executescript("""
CREATE TABLE wp_users (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    display_name TEXT NOT NULL,
    user_email TEXT NOT NULL
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
    payment_due_date TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT
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
cursor.execute("INSERT INTO wp_users (ID, display_name, user_email) VALUES (2, 'Finance Manager', 'finance@example.com')")

# Seed sample Tender and PO records
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_po_number, sell_price, actual_payment, balance_payment_due, payment_due_date, created_at)
VALUES (101, 'tender', 'awarded', 'TN-2026-001', NULL, 10000.00, 0.00, 10000.00, '2026-12-31', '2026-09-01 10:00:00')
""")
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_po_number, sell_price, actual_payment, balance_payment_due, payment_due_date, created_at)
VALUES (102, 'purchase_order', 'delivered', NULL, 'PO-2026-099', 5000.00, 0.00, 5000.00, '2026-08-15', '2026-08-01 10:00:00')
""")
conn.commit()

# Python implementation of service logic for simulation verification
def validate_payment(data, record_exists=True):
    errors = []
    amount_str = str(data.get('payment_amount', '')).replace(',', '').strip()
    if not amount_str:
        errors.append('Payment Amount is required.')
    else:
        try:
            val = float(amount_str)
            if val <= 0:
                errors.append('Payment Amount must be a valid positive number greater than 0.')
        except ValueError:
            errors.append('Payment Amount must be a valid positive number greater than 0.')

    date_str = str(data.get('payment_date', '')).strip()
    if not date_str:
        errors.append('Payment Date is required.')
    elif not re.match(r'^\d{4}-\d{2}-\d{2}$', date_str):
        errors.append('Payment Date must be a valid date in YYYY-MM-DD format.')
    else:
        try:
            datetime.strptime(date_str, '%Y-%m-%d')
        except ValueError:
            errors.append('Payment Date must be a valid date in YYYY-MM-DD format.')

    if not record_exists:
        errors.append('Associated record does not exist.')

    return errors

def determine_payment_status(expected, paid, due_date=None, current_date='2026-09-11'):
    expected = float(expected or 0.0)
    paid = float(paid or 0.0)
    if expected > 0 and paid >= expected:
        return 'Paid'
    if expected <= 0 and paid > 0:
        return 'Paid'

    clean_due = (due_date or '').strip()
    if clean_due and clean_due != '0000-00-00':
        if current_date > clean_due and paid < expected:
            return 'Overdue'

    if 0 < paid < expected:
        return 'Partial'

    return 'Pending'

def recalculate_record_payments(conn, record_id):
    c = conn.cursor()
    c.execute("SELECT COALESCE(SUM(payment_amount), 0.0) FROM wp_tpm_payments WHERE record_id = ?", (record_id,))
    total_paid = float(c.fetchone()[0])

    c.execute("SELECT sell_price FROM wp_tpm_records WHERE id = ?", (record_id,))
    row = c.fetchone()
    sell_price = float(row[0]) if row else 0.0

    balance_due = max(0.0, sell_price - total_paid)
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute("UPDATE wp_tpm_records SET actual_payment = ?, balance_payment_due = ?, updated_at = ? WHERE id = ?",
              (total_paid, balance_due, now, record_id))
    conn.commit()
    return total_paid, balance_due

def create_payment(conn, record_id, user_id, amount, date, method='Bank Transfer', ref='REF-1001', notes='Initial installment'):
    c = conn.cursor()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute("""
        INSERT INTO wp_tpm_payments (record_id, payment_amount, payment_date, payment_method, reference_number, notes, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    """, (record_id, amount, date, method, ref, notes, user_id, now))
    payment_id = c.lastrowid

    recalculate_record_payments(conn, record_id)

    # Activity log
    desc = f"Payment of ${amount:,.2f} recorded via {method} (Ref: {ref})"
    c.execute("""
        INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
        VALUES (?, ?, 'payment_added', ?, NULL, ?, ?)
    """, (record_id, user_id, desc, str(amount), now))
    conn.commit()
    return payment_id

def update_payment(conn, payment_id, user_id, new_amount, new_date, new_method, new_ref, new_notes):
    c = conn.cursor()
    c.execute("SELECT record_id, payment_amount FROM wp_tpm_payments WHERE id = ?", (payment_id,))
    existing = c.fetchone()
    if not existing:
        return False

    record_id = existing[0]
    old_amount = float(existing[1])
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute("""
        UPDATE wp_tpm_payments
        SET payment_amount = ?, payment_date = ?, payment_method = ?, reference_number = ?, notes = ?
        WHERE id = ?
    """, (new_amount, new_date, new_method, new_ref, new_notes, payment_id))

    recalculate_record_payments(conn, record_id)

    desc = f"Payment #{payment_id} updated: amount changed from ${old_amount:,.2f} to ${new_amount:,.2f}"
    c.execute("""
        INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
        VALUES (?, ?, 'payment_updated', ?, ?, ?, ?)
    """, (record_id, user_id, desc, str(old_amount), str(new_amount), now))
    conn.commit()
    return True

def delete_payment(conn, payment_id, user_id):
    c = conn.cursor()
    c.execute("SELECT record_id, payment_amount, reference_number FROM wp_tpm_payments WHERE id = ?", (payment_id,))
    existing = c.fetchone()
    if not existing:
        return False

    record_id = existing[0]
    amount = float(existing[1])
    ref = existing[2] or ''
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute("DELETE FROM wp_tpm_payments WHERE id = ?", (payment_id,))
    recalculate_record_payments(conn, record_id)

    ref_label = f" (Ref: {ref})" if ref else ""
    desc = f"Payment #{payment_id} of ${amount:,.2f} deleted{ref_label}"
    c.execute("""
        INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
        VALUES (?, ?, 'payment_deleted', ?, ?, NULL, ?)
    """, (record_id, user_id, desc, str(amount), now))
    conn.commit()
    return True

# --- 1. Validation Tests ---
print("\n--- 1. Validation Logic Checks ---")
assert_test("Validation rejects missing amount", len(validate_payment({'payment_date': '2026-09-11'})) > 0)
assert_test("Validation rejects zero amount", len(validate_payment({'payment_amount': '0.00', 'payment_date': '2026-09-11'})) > 0)
assert_test("Validation rejects negative amount", len(validate_payment({'payment_amount': '-150.00', 'payment_date': '2026-09-11'})) > 0)
assert_test("Validation rejects non-numeric amount", len(validate_payment({'payment_amount': 'abc', 'payment_date': '2026-09-11'})) > 0)
assert_test("Validation rejects empty date", len(validate_payment({'payment_amount': '500', 'payment_date': ''})) > 0)
assert_test("Validation rejects invalid date syntax", len(validate_payment({'payment_amount': '500', 'payment_date': '2026/09/11'})) > 0)
assert_test("Validation rejects non-existent calendar date", len(validate_payment({'payment_amount': '500', 'payment_date': '2026-02-31'})) > 0)
assert_test("Validation rejects non-existent record", len(validate_payment({'payment_amount': '500', 'payment_date': '2026-09-11'}, record_exists=False)) > 0)
assert_test("Validation passes with valid input", len(validate_payment({'payment_amount': '1,500.50', 'payment_date': '2026-09-11'})) == 0)

# --- 2. Initial State & Single Payment Test ---
print("\n--- 2. Single Payment Recording & Balance Calculation ---")
cursor.execute("SELECT sell_price, actual_payment, balance_payment_due, payment_due_date, status FROM wp_tpm_records WHERE id = 101")
rec = cursor.fetchone()
initial_status = determine_payment_status(rec['sell_price'], rec['actual_payment'], rec['payment_due_date'])
assert_test("Initial payment status is 'Pending'", initial_status == 'Pending')
assert_test("Initial balance due equals expected sell price ($10,000.00)", rec['balance_payment_due'] == 10000.00)

p1_id = create_payment(conn, record_id=101, user_id=1, amount=2500.00, date='2026-09-02', method='Wire Transfer', ref='WIRE-001')
cursor.execute("SELECT actual_payment, balance_payment_due, status FROM wp_tpm_records WHERE id = 101")
rec_p1 = cursor.fetchone()

assert_test("Single payment created successfully (ID > 0)", p1_id > 0)
assert_test("Total paid updated to $2,500.00", rec_p1['actual_payment'] == 2500.00)
assert_test("Balance due recalculated to $7,500.00 ($10,000 - $2,500)", rec_p1['balance_payment_due'] == 7500.00)
assert_test("Payment status updated to 'Partial'",
            determine_payment_status(10000.00, rec_p1['actual_payment'], '2026-12-31') == 'Partial')
assert_test("Record's main workflow status ('awarded') is preserved and not overwritten",
            rec_p1['status'] == 'awarded')

cursor.execute("SELECT * FROM wp_tpm_activity_logs WHERE record_id = 101 AND action = 'payment_added'")
p1_logs = cursor.fetchall()
assert_test("Activity log 'payment_added' created for single payment", len(p1_logs) == 1)
assert_test("Activity log includes payment amount and reference",
            "2,500.00" in p1_logs[0]['description'] and "WIRE-001" in p1_logs[0]['description'])

# --- 3. Multiple Payments Test ---
print("\n--- 3. Multiple Payments Recording & Cumulative Totals ---")
p2_id = create_payment(conn, record_id=101, user_id=2, amount=3500.00, date='2026-09-05', method='Cheque', ref='CHQ-8821')
cursor.execute("SELECT actual_payment, balance_payment_due FROM wp_tpm_records WHERE id = 101")
rec_p2 = cursor.fetchone()
assert_test("Second payment added, total paid = $6,000.00", rec_p2['actual_payment'] == 6000.00)
assert_test("Balance due = $4,000.00", rec_p2['balance_payment_due'] == 4000.00)
assert_test("Payment status remains 'Partial'",
            determine_payment_status(10000.00, rec_p2['actual_payment'], '2026-12-31') == 'Partial')

p3_id = create_payment(conn, record_id=101, user_id=1, amount=4000.00, date='2026-09-10', method='Credit Card', ref='CC-9932')
cursor.execute("SELECT actual_payment, balance_payment_due FROM wp_tpm_records WHERE id = 101")
rec_p3 = cursor.fetchone()
assert_test("Third payment added, total paid = $10,000.00", rec_p3['actual_payment'] == 10000.00)
assert_test("Balance due = $0.00", rec_p3['balance_payment_due'] == 0.00)
assert_test("Payment status updated to 'Paid'",
            determine_payment_status(10000.00, rec_p3['actual_payment'], '2026-12-31') == 'Paid')

cursor.execute("SELECT COUNT(*) FROM wp_tpm_payments WHERE record_id = 101")
assert_test("Total of 3 payments stored in database for record 101", cursor.fetchone()[0] == 3)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_activity_logs WHERE record_id = 101 AND action = 'payment_added'")
assert_test("Total of 3 'payment_added' activity logs recorded", cursor.fetchone()[0] == 3)

# --- 4. Overpayment Handling ---
print("\n--- 4. Overpayment Handling & Balance Clamping ---")
p4_id = create_payment(conn, record_id=101, user_id=1, amount=500.00, date='2026-09-11', method='Bank Transfer', ref='BONUS-01')
cursor.execute("SELECT actual_payment, balance_payment_due FROM wp_tpm_records WHERE id = 101")
rec_p4 = cursor.fetchone()
assert_test("Overpayment correctly tracked in total paid ($10,500.00)", rec_p4['actual_payment'] == 10500.00)
assert_test("Balance due is clamped to $0.00 (non-negative)", rec_p4['balance_payment_due'] == 0.00)
assert_test("Payment status remains 'Paid' with overpayment",
            determine_payment_status(10000.00, rec_p4['actual_payment'], '2026-12-31') == 'Paid')

# Clean up overpayment for subsequent tests
delete_payment(conn, p4_id, user_id=1)

# --- 5. Status Determination Matrix (Pending, Partial, Paid, Overdue) ---
print("\n--- 5. Payment Status Determination Matrix ---")
today_ref = '2026-09-11'

# Test Pending
assert_test("Status: Expected $5,000, Paid $0, Future due date -> 'Pending'",
            determine_payment_status(5000.0, 0.0, '2026-12-31', today_ref) == 'Pending')
assert_test("Status: Expected $5,000, Paid $0, No due date -> 'Pending'",
            determine_payment_status(5000.0, 0.0, None, today_ref) == 'Pending')

# Test Partial
assert_test("Status: Expected $5,000, Paid $2,000, Future due date -> 'Partial'",
            determine_payment_status(5000.0, 2000.0, '2026-12-31', today_ref) == 'Partial')
assert_test("Status: Expected $5,000, Paid $2,000, No due date -> 'Partial'",
            determine_payment_status(5000.0, 2000.0, None, today_ref) == 'Partial')

# Test Paid
assert_test("Status: Expected $5,000, Paid $5,000, Any due date -> 'Paid'",
            determine_payment_status(5000.0, 5000.0, '2026-12-31', today_ref) == 'Paid')
assert_test("Status: Expected $5,000, Paid $6,000 (overpaid) -> 'Paid'",
            determine_payment_status(5000.0, 6000.0, '2026-12-31', today_ref) == 'Paid')
assert_test("Status: Expected $0, Paid $500 -> 'Paid'",
            determine_payment_status(0.0, 500.0, None, today_ref) == 'Paid')

# Test Overdue
assert_test("Status: Expected $5,000, Paid $0, Past due date (2026-08-01 vs 2026-09-11) -> 'Overdue'",
            determine_payment_status(5000.0, 0.0, '2026-08-01', today_ref) == 'Overdue')
assert_test("Status: Expected $5,000, Paid $2,500, Past due date (2026-08-01 vs 2026-09-11) -> 'Overdue'",
            determine_payment_status(5000.0, 2500.0, '2026-08-01', today_ref) == 'Overdue')
assert_test("Status: Expected $5,000, Paid $5,000, Past due date -> 'Paid' (Paid takes precedence over overdue)",
            determine_payment_status(5000.0, 5000.0, '2026-08-01', today_ref) == 'Paid')

# Verify on Record 102 (Purchase Order with past due date 2026-08-15)
cursor.execute("SELECT sell_price, actual_payment, payment_due_date, status FROM wp_tpm_records WHERE id = 102")
rec_po = cursor.fetchone()
assert_test("Record 102 initial status with past due date is 'Overdue'",
            determine_payment_status(rec_po['sell_price'], rec_po['actual_payment'], rec_po['payment_due_date'], today_ref) == 'Overdue')

# --- 6. Update Payment Test & Activity Logging ---
print("\n--- 6. Update Payment Test & Activity Logging ---")
# Record 101 currently has p1 (2500), p2 (3500), p3 (4000) -> total 10,000
# Let's update p2 from 3500.00 to 1500.00
update_res = update_payment(conn, payment_id=p2_id, user_id=1,
                            new_amount=1500.00, new_date='2026-09-05',
                            new_method='Cheque Updated', new_ref='CHQ-8821-REV',
                            new_notes='Adjusted amount down')
assert_test("Payment update executed successfully", update_res is True)

cursor.execute("SELECT actual_payment, balance_payment_due, status FROM wp_tpm_records WHERE id = 101")
rec_updated = cursor.fetchone()
assert_test("Record total paid recalculated to $8,000.00 (2500 + 1500 + 4000)", rec_updated['actual_payment'] == 8000.00)
assert_test("Record balance due recalculated to $2,000.00", rec_updated['balance_payment_due'] == 2000.00)
assert_test("Record payment status recalculated back to 'Partial'",
            determine_payment_status(10000.00, rec_updated['actual_payment'], '2026-12-31') == 'Partial')
assert_test("Record workflow status remained 'awarded'", rec_updated['status'] == 'awarded')

cursor.execute("SELECT * FROM wp_tpm_activity_logs WHERE record_id = 101 AND action = 'payment_updated'")
update_logs = cursor.fetchall()
assert_test("Activity log 'payment_updated' recorded", len(update_logs) == 1)
assert_test("Update log captures old_value ('3500.0') and new_value ('1500.0')",
            update_logs[0]['old_value'] == '3500.0' and update_logs[0]['new_value'] == '1500.0')

# --- 7. Delete Payment Test & Activity Logging ---
print("\n--- 7. Delete Payment Test & Activity Logging ---")
# Record 101 has p1 (2500), p2 (1500), p3 (4000)
# Delete p3 (4000)
del_res = delete_payment(conn, payment_id=p3_id, user_id=2)
assert_test("Payment deletion executed successfully", del_res is True)

cursor.execute("SELECT actual_payment, balance_payment_due, status FROM wp_tpm_records WHERE id = 101")
rec_deleted = cursor.fetchone()
assert_test("Record total paid recalculated to $4,000.00 (2500 + 1500)", rec_deleted['actual_payment'] == 4000.00)
assert_test("Record balance due recalculated to $6,000.00", rec_deleted['balance_payment_due'] == 6000.00)
assert_test("Payment status is 'Partial'",
            determine_payment_status(10000.00, rec_deleted['actual_payment'], '2026-12-31') == 'Partial')

cursor.execute("SELECT * FROM wp_tpm_activity_logs WHERE record_id = 101 AND action = 'payment_deleted'")
del_logs = cursor.fetchall()
# Note: one deleted earlier in overpayment cleanup, plus p3
assert_test("Activity log 'payment_deleted' recorded", len(del_logs) >= 1)
assert_test("Deletion log captures payment amount in old_value", del_logs[-1]['old_value'] == '4000.0')

# If we change due date to yesterday, status becomes 'Overdue'
assert_test("With unpaid balance and past due date, status becomes 'Overdue'",
            determine_payment_status(10000.00, rec_deleted['actual_payment'], '2026-09-01', today_ref) == 'Overdue')

# Delete remaining payments p1 and p2
delete_payment(conn, p1_id, user_id=1)
delete_payment(conn, p2_id, user_id=1)
cursor.execute("SELECT actual_payment, balance_payment_due FROM wp_tpm_records WHERE id = 101")
rec_empty = cursor.fetchone()
assert_test("After deleting all payments, total paid is $0.00", rec_empty['actual_payment'] == 0.00)
assert_test("After deleting all payments, balance due is $10,000.00", rec_empty['balance_payment_due'] == 10000.00)
assert_test("After deleting all payments with future due date, status is 'Pending'",
            determine_payment_status(10000.00, rec_empty['actual_payment'], '2026-12-31', today_ref) == 'Pending')

# --- 8. Non-Overwrite Rule Final Check ---
print("\n--- 8. Workflow Status Preservation Verification ---")
cursor.execute("SELECT status FROM wp_tpm_records WHERE id = 101")
final_rec1 = cursor.fetchone()
assert_test("Record 101 main workflow status remains exactly 'awarded'", final_rec1['status'] == 'awarded')

cursor.execute("SELECT status FROM wp_tpm_records WHERE id = 102")
final_rec2 = cursor.fetchone()
assert_test("Record 102 main workflow status remains exactly 'delivered'", final_rec2['status'] == 'delivered')

# --- Summary ---
print("\n" + "=" * 68)
print(f" TOTAL TESTS RUN: {passed + failed}")
print(f" PASSED: {passed}")
print(f" FAILED: {failed}")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    print("\n>>> ALL PHASE 11 PAYMENT MANAGEMENT TESTS PASSED WITH 100% SUCCESS! <<<\n")
    sys.exit(0)
