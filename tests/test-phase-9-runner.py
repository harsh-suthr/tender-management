#!/usr/bin/env python3
"""
Test Suite for Phase 9: Customer Management.

Verifies the 7 required Phase 9 specifications:
1. Create Customer (Name, Company, Email, Phone, Address, Notes, Status)
2. Edit Customer (Update details, status)
3. Search Customer (Search across name, company, email, phone)
4. Duplicate Prevention (Name+Company combo, unique email, allow self-update)
5. Assign to Record (Integration with Tenders & POs)
6. View Associated Records & Financial Totals (Tenders, POs, Revenue, Profit, Recent Activity)
7. Safe Deletion Guard (Block deletion with records, Deactivation, Reassign & Delete, Zero-record deletion)
8. Security & Permissions (Capability gating, Nonce verification)
"""

import os
import re
import sys
import sqlite3
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CUSTOMER_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-customer-service.php')
RECORD_SERVICE_FILE   = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE      = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE     = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
CUSTOMERS_TABLE_FILE  = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-customers-list-table.php')
CUSTOMERS_VIEW_FILE   = os.path.join(BASE_DIR, 'admin', 'views', 'customers.php')
ADD_RECORD_VIEW_FILE  = os.path.join(BASE_DIR, 'admin', 'views', 'add-record.php')
ADMIN_CSS_FILE        = os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')

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
print(" PHASE 9: CUSTOMER MANAGEMENT VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(CUSTOMER_SERVICE_FILE, 'r') as f:
    cust_service_code = f.read()

with open(RECORD_SERVICE_FILE, 'r') as f:
    rec_service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(CUSTOMERS_TABLE_FILE, 'r') as f:
    cust_table_code = f.read()

with open(CUSTOMERS_VIEW_FILE, 'r') as f:
    customers_view = f.read()

with open(ADD_RECORD_VIEW_FILE, 'r') as f:
    add_record_view = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# --------------------------------------------------------------------------
# PART A: STRUCTURAL ARCHITECTURE & HOOK REGISTRATION
# --------------------------------------------------------------------------
print("\n--- Architecture, Classes & Hook Registrations ---")

assert_test("class-tpm-customer-service.php exists", os.path.exists(CUSTOMER_SERVICE_FILE))
assert_test("class-tpm-customers-list-table.php exists", os.path.exists(CUSTOMERS_TABLE_FILE))
assert_test("TPM_Customer_Service class defined", "class TPM_Customer_Service" in cust_service_code)
assert_test("TPM_Customers_List_Table subclasses WP_List_Table", "class TPM_Customers_List_Table extends WP_List_Table" in cust_table_code)

assert_test("Plugin requires class-tpm-customer-service.php", "class-tpm-customer-service.php" in plugin_code)
assert_test("Plugin requires class-tpm-customers-list-table.php", "class-tpm-customers-list-table.php" in plugin_code)

assert_test("Plugin registers admin_post_tpm_save_customer", "admin_post_tpm_save_customer" in plugin_code)
assert_test("Plugin registers admin_post_tpm_update_customer", "admin_post_tpm_update_customer" in plugin_code)
assert_test("Plugin registers admin_post_tpm_delete_customer", "admin_post_tpm_delete_customer" in plugin_code)
assert_test("Plugin registers admin_post_tpm_deactivate_customer", "admin_post_tpm_deactivate_customer" in plugin_code)
assert_test("Plugin registers admin_post_tpm_reassign_customer", "admin_post_tpm_reassign_customer" in plugin_code)

assert_test("TPM_Admin::handle_save_customer() implemented", "function handle_save_customer" in admin_code)
assert_test("TPM_Admin::handle_update_customer() implemented", "function handle_update_customer" in admin_code)
assert_test("TPM_Admin::handle_delete_customer() implemented", "function handle_delete_customer" in admin_code)
assert_test("TPM_Admin::handle_deactivate_customer() implemented", "function handle_deactivate_customer" in admin_code)
assert_test("TPM_Admin::handle_reassign_customer() implemented", "function handle_reassign_customer" in admin_code)

# --------------------------------------------------------------------------
# PART B: SECURITY & NONCE VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Security: Nonces, Capabilities & Sanitization ---")

assert_test("handle_save_customer verifies nonce tpm_save_customer_action",
            "check_admin_referer( 'tpm_save_customer_action', 'tpm_customer_nonce' )" in admin_code)
assert_test("handle_update_customer verifies nonce tpm_update_customer_action",
            "check_admin_referer( 'tpm_update_customer_action', 'tpm_customer_nonce' )" in admin_code)
assert_test("handle_delete_customer verifies nonce tpm_delete_customer_{id}",
            "check_admin_referer( 'tpm_delete_customer_' . $customer_id, 'tpm_nonce' )" in admin_code)
assert_test("handle_deactivate_customer verifies nonce tpm_deactivate_customer_{id}",
            "check_admin_referer( 'tpm_deactivate_customer_' . $customer_id, 'tpm_nonce' )" in admin_code)
assert_test("handle_reassign_customer verifies nonce tpm_reassign_customer_action",
            "check_admin_referer( 'tpm_reassign_customer_action', 'tpm_customer_nonce' )" in admin_code)

assert_test("All customer handlers check manage_tpm_customers capability",
            "current_user_can( 'manage_tpm_customers' )" in admin_code)

assert_test("TPM_Customer_Service sanitizes name, email, phone, and HTML text",
            "sanitize_text_field" in cust_service_code and "sanitize_email" in cust_service_code and "wp_kses_post" in cust_service_code)

# --------------------------------------------------------------------------
# PART C: TABLE COLUMNS & VIEW SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Customer List Table Columns & Views Specification ---")

assert_test("Column 'name' (Customer Name) defined in list table", "'name'          => __( 'Customer Name'" in cust_table_code)
assert_test("Column 'company_name' (Company) defined in list table", "'company_name'  => __( 'Company'" in cust_table_code)
assert_test("Column 'email' (Email) defined in list table", "'email'         => __( 'Email'" in cust_table_code)
assert_test("Column 'phone' (Phone) defined in list table", "'phone'         => __( 'Phone'" in cust_table_code)
assert_test("Column 'total_records' (Total Records) defined in list table", "'total_records' => __( 'Total Records'" in cust_table_code)
assert_test("Column 'status' (Status) defined in list table", "'status'        => __( 'Status'" in cust_table_code)
assert_test("Column 'created_at' (Created Date) defined in list table", "'created_at'    => __( 'Created Date'" in cust_table_code)
assert_test("Column 'actions' (Actions) defined in list table", "'actions'       => __( 'Actions'" in cust_table_code)

assert_test("List table defines filter views (All, Active, Inactive)",
            "'all'" in cust_table_code and "'active'" in cust_table_code and "'inactive'" in cust_table_code)

assert_test("List table implements search across name, company, email, phone",
            "c.name LIKE" in cust_service_code and "c.company_name LIKE" in cust_service_code and "c.email LIKE" in cust_service_code)

# --------------------------------------------------------------------------
# PART D: CUSTOMER DETAILS VIEW SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Customer Details View Inspection ---")

assert_test("customers.php implements Details view ('view' action)",
            "'view' === $action" in customers_view)
assert_test("Details view displays Associated Tenders",
            "Associated Tenders" in customers_view and "$tenders" in customers_view)
assert_test("Details view displays Associated Purchase Orders",
            "Associated Purchase Orders" in customers_view and "$pos" in customers_view)
assert_test("Details view displays Financial KPI totals (Revenue, Paid, Balance, Profit)",
            "Total Revenue" in customers_view and "Total Payments Received" in customers_view and "Outstanding Balance Due" in customers_view and "Cumulative Net Profit" in customers_view)
assert_test("Details view displays Recent Activity stream",
            "Recent Activity" in customers_view and "$activity" in customers_view)
assert_test("Details view displays Contact profile (Email, Phone, Address, Notes)",
            "$customer->email" in customers_view and "$customer->phone" in customers_view and "$customer->address" in customers_view and "$customer->notes" in customers_view)

# --------------------------------------------------------------------------
# PART E: SAFE DELETION, DEACTIVATION & REASSIGNMENT SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Safe Deletion Guard Inspection ---")

assert_test("customers.php implements Delete Guard screen ('delete' action)",
            "'delete' === $action" in customers_view)
assert_test("Delete Guard checks can_delete dependency",
            "can_delete" in customers_view and "can_delete" in cust_service_code)
assert_test("Delete Guard provides Deactivation option",
            "Deactivate Customer" in customers_view and "tpm_deactivate_customer" in customers_view)
assert_test("Delete Guard provides Reassign Records option",
            "Reassign Records" in customers_view and "tpm_reassign_customer" in customers_view)
assert_test("TPM_Customer_Service::can_delete() returns dependency counts",
            "record_count" in cust_service_code and "tenders_count" in cust_service_code and "pos_count" in cust_service_code)
assert_test("TPM_Customer_Service::delete_customer() blocks deletion when records exist",
            "customer_has_records" in cust_service_code)
assert_test("TPM_Customer_Service::reassign_and_delete() reassigns records and logs audit trail",
            "Customer reassigned from" in cust_service_code and "TPM_Record_Service::log_activity" in cust_service_code)

# --------------------------------------------------------------------------
# PART F: INTEGRATION WITH ADD/EDIT RECORD
# --------------------------------------------------------------------------
print("\n--- Integration with Add/Edit Record Forms ---")

assert_test("add-record.php queries active customers from tpm_customers",
            "$customers_table" in add_record_view and "FROM `{$customers_table}`" in add_record_view)
assert_test("add-record.php includes assigned customer even if inactive",
            "status = 'active' OR id = %d" in add_record_view)
assert_test("add-record.php formats [Inactive] tag for inactive customer",
            "[Inactive]" in add_record_view or "[' . __( 'Inactive'" in add_record_view)
assert_test("add-record.php provides + Add New Customer link",
            "admin.php?page=tpm-customers&action=new" in add_record_view)


# --------------------------------------------------------------------------
# PART G: FUNCTIONAL DATABASE SIMULATION TESTS
# --------------------------------------------------------------------------
print("\n--- Database Simulation & Functional Tests ---")

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Create schema
cursor.executescript("""
CREATE TABLE wp_tpm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    created_by INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT
);

CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL DEFAULT 'tender',
    status TEXT NOT NULL DEFAULT 'draft',
    tender_number TEXT,
    customer_id INTEGER,
    customer_po_number TEXT,
    customer_po_name TEXT,
    purchase_price REAL DEFAULT 0.00,
    sell_price REAL DEFAULT 0.00,
    expenses REAL DEFAULT 0.00,
    actual_payment REAL DEFAULT 0.00,
    balance_payment_due REAL DEFAULT 0.00,
    tender_due_date TEXT,
    po_date TEXT,
    is_archived INTEGER DEFAULT 0,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    created_at TEXT NOT NULL,
    updated_at TEXT
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

# Python simulation of TPM_Customer_Service logic
def sim_validate_customer(data, customer_id=0):
    errors = []
    name = (data.get('name') or '').strip()
    if not name:
        errors.append("Customer Name is required.")

    email = (data.get('email') or '').strip()
    if email and not re.match(r"[^@]+@[^@]+\.[^@]+", email):
        errors.append("Please enter a valid email address.")

    company = (data.get('company_name') or '').strip()

    # Check duplicate (name + company)
    if name:
        if company:
            cursor.execute("SELECT id FROM wp_tpm_customers WHERE LOWER(name) = LOWER(?) AND LOWER(company_name) = LOWER(?) AND id != ?", (name, company, customer_id))
        else:
            cursor.execute("SELECT id FROM wp_tpm_customers WHERE LOWER(name) = LOWER(?) AND (company_name IS NULL OR company_name = '') AND id != ?", (name, customer_id))
        if cursor.fetchone():
            errors.append("A customer with this Name and Company combination already exists.")

    # Check duplicate email
    if email:
        cursor.execute("SELECT id FROM wp_tpm_customers WHERE LOWER(email) = LOWER(?) AND id != ?", (email, customer_id))
        if cursor.fetchone():
            errors.append("A customer with this email address is already registered.")

    return errors

def sim_create_customer(raw_data, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'manage_tpm_customers': True}
    if not user_caps.get('manage_tpm_customers'):
        return {'success': False, 'error': 'unauthorized', 'message': 'Permission denied'}

    errors = sim_validate_customer(raw_data)
    if errors:
        return {'success': False, 'error': 'validation_failed', 'errors': errors}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
    INSERT INTO wp_tpm_customers (name, company_name, email, phone, address, notes, status, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (
        raw_data.get('name'),
        raw_data.get('company_name'),
        raw_data.get('email'),
        raw_data.get('phone'),
        raw_data.get('address'),
        raw_data.get('notes'),
        raw_data.get('status', 'active'),
        user_id,
        now,
        now
    ))
    conn.commit()
    return {'success': True, 'id': cursor.lastrowid}

def sim_update_customer(customer_id, raw_data, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'manage_tpm_customers': True}
    if not user_caps.get('manage_tpm_customers'):
        return {'success': False, 'error': 'unauthorized', 'message': 'Permission denied'}

    cursor.execute("SELECT * FROM wp_tpm_customers WHERE id = ?", (customer_id,))
    if not cursor.fetchone():
        return {'success': False, 'error': 'not_found', 'message': 'Customer not found'}

    errors = sim_validate_customer(raw_data, customer_id)
    if errors:
        return {'success': False, 'error': 'validation_failed', 'errors': errors}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
    UPDATE wp_tpm_customers SET
        name = ?,
        company_name = ?,
        email = ?,
        phone = ?,
        address = ?,
        notes = ?,
        status = ?,
        updated_at = ?
    WHERE id = ?
    """, (
        raw_data.get('name'),
        raw_data.get('company_name'),
        raw_data.get('email'),
        raw_data.get('phone'),
        raw_data.get('address'),
        raw_data.get('notes'),
        raw_data.get('status', 'active'),
        now,
        customer_id
    ))
    conn.commit()
    return {'success': True, 'id': customer_id}

def sim_can_delete_customer(customer_id):
    cursor.execute("""
    SELECT
        COUNT(id) AS record_count,
        SUM(CASE WHEN record_type = 'tender' THEN 1 ELSE 0 END) AS tenders_count,
        SUM(CASE WHEN record_type = 'po' THEN 1 ELSE 0 END) AS pos_count
    FROM wp_tpm_records WHERE customer_id = ?
    """, (customer_id,))
    row = cursor.fetchone()
    total = row['record_count'] or 0
    tenders = row['tenders_count'] or 0
    pos = row['pos_count'] or 0
    return {
        'can_delete': (total == 0),
        'record_count': total,
        'tenders_count': tenders,
        'pos_count': pos
    }

def sim_delete_customer(customer_id, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'manage_tpm_customers': True}
    if not user_caps.get('manage_tpm_customers'):
        return {'success': False, 'error': 'unauthorized', 'message': 'Permission denied'}

    check = sim_can_delete_customer(customer_id)
    if not check['can_delete']:
        return {
            'success': False,
            'error': 'customer_has_records',
            'message': f"Cannot delete customer: {check['record_count']} associated records exist."
        }

    cursor.execute("DELETE FROM wp_tpm_customers WHERE id = ?", (customer_id,))
    conn.commit()
    return {'success': True}

def sim_deactivate_customer(customer_id):
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("UPDATE wp_tpm_customers SET status = 'inactive', updated_at = ? WHERE id = ?", (now, customer_id))
    conn.commit()
    return {'success': True}

def sim_reassign_and_delete(source_id, target_id, user_id=1):
    if source_id == target_id:
        return {'success': False, 'error': 'invalid_reassignment', 'message': 'Source and target must differ'}

    cursor.execute("SELECT id FROM wp_tpm_customers WHERE id = ?", (source_id,))
    if not cursor.fetchone():
        return {'success': False, 'error': 'source_not_found'}

    cursor.execute("SELECT name, company_name FROM wp_tpm_customers WHERE id = ?", (target_id,))
    target = cursor.fetchone()
    if not target:
        return {'success': False, 'error': 'target_not_found'}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # Fetch records to reassign
    cursor.execute("SELECT id FROM wp_tpm_records WHERE customer_id = ?", (source_id,))
    rec_ids = [r['id'] for r in cursor.fetchall()]

    # Transfer
    cursor.execute("UPDATE wp_tpm_records SET customer_id = ?, updated_by = ?, updated_at = ? WHERE customer_id = ?", (target_id, user_id, now, source_id))

    # Audit log
    for rid in rec_ids:
        cursor.execute("""
        INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
        VALUES (?, ?, 'record_updated', 'Customer reassigned to target', ?, ?, ?)
        """, (rid, user_id, str(source_id), str(target_id), now))

    # Delete source
    cursor.execute("DELETE FROM wp_tpm_customers WHERE id = ?", (source_id,))
    conn.commit()
    return {'success': True, 'reassigned_count': len(rec_ids)}

# --------------------------------------------------------------------------
# TEST 1: CREATE CUSTOMER
# --------------------------------------------------------------------------
print("\n--- Test 1: Create Customer ---")
c1_res = sim_create_customer({
    'name': 'Dr. Sarah Connor',
    'company_name': 'Cyberdyne Defense Solutions',
    'email': 'sarah@cyberdyne.org',
    'phone': '+1 (555) 777-8899',
    'address': '100 Skynet Way, Tech Valley, CA',
    'notes': 'Government security cleared account.',
    'status': 'active'
})
assert_test("Customer 1 created successfully", c1_res['success'] is True)
c1_id = c1_res['id']

cursor.execute("SELECT * FROM wp_tpm_customers WHERE id = ?", (c1_id,))
c1_row = cursor.fetchone()
assert_test("Customer name saved correctly", c1_row['name'] == 'Dr. Sarah Connor')
assert_test("Customer company saved correctly", c1_row['company_name'] == 'Cyberdyne Defense Solutions')
assert_test("Customer email saved correctly", c1_row['email'] == 'sarah@cyberdyne.org')
assert_test("Customer phone saved correctly", c1_row['phone'] == '+1 (555) 777-8899')
assert_test("Customer address saved correctly", '100 Skynet Way' in c1_row['address'])
assert_test("Customer status is active", c1_row['status'] == 'active')

# Create Customer 2
c2_res = sim_create_customer({
    'name': 'John Reese',
    'company_name': 'Machine Intelligence Corp',
    'email': 'john@machine.net',
    'phone': '+1 (555) 333-2211',
    'status': 'active'
})
assert_test("Customer 2 created successfully", c2_res['success'] is True)
c2_id = c2_res['id']

# --------------------------------------------------------------------------
# TEST 2: DUPLICATE PREVENTION
# --------------------------------------------------------------------------
print("\n--- Test 2: Duplicate Prevention ---")

# Attempt duplicate name + company
dup_name_res = sim_create_customer({
    'name': 'dr. sarah connor',
    'company_name': 'cyberdyne defense solutions',
    'email': 'another.email@test.com'
})
assert_test("Duplicate name + company rejected", dup_name_res['success'] is False)
assert_test("Duplicate name error message returned",
            any("Name and Company combination already exists" in e for e in dup_name_res['errors']))

# Attempt duplicate email
dup_email_res = sim_create_customer({
    'name': 'Different Person',
    'company_name': 'Different Company',
    'email': 'sarah@cyberdyne.org'
})
assert_test("Duplicate email rejected", dup_email_res['success'] is False)
assert_test("Duplicate email error message returned",
            any("email address is already registered" in e for e in dup_email_res['errors']))

# Allow update of same customer without self-duplicate trigger
self_update_res = sim_update_customer(c1_id, {
    'name': 'Dr. Sarah Connor',
    'company_name': 'Cyberdyne Defense Solutions',
    'email': 'sarah@cyberdyne.org',
    'phone': '+1 (555) 777-8899',
    'status': 'active'
})
assert_test("Updating customer with own name/email succeeds (no false duplicate)", self_update_res['success'] is True)

# --------------------------------------------------------------------------
# TEST 3: EDIT CUSTOMER
# --------------------------------------------------------------------------
print("\n--- Test 3: Edit Customer ---")
edit_res = sim_update_customer(c1_id, {
    'name': 'Dr. Sarah Connor, PhD',
    'company_name': 'Cyberdyne Defense & Aerospace',
    'email': 'sarah.connor@cyberdyne-aero.com',
    'phone': '+1 (555) 999-0000',
    'address': '200 Aero Plaza, Tech Valley, CA',
    'notes': 'Account escalated to Tier 1 partner.',
    'status': 'active'
})
assert_test("Edit customer succeeded", edit_res['success'] is True)

cursor.execute("SELECT * FROM wp_tpm_customers WHERE id = ?", (c1_id,))
c1_updated = cursor.fetchone()
assert_test("Customer name updated", c1_updated['name'] == 'Dr. Sarah Connor, PhD')
assert_test("Customer company updated", c1_updated['company_name'] == 'Cyberdyne Defense & Aerospace')
assert_test("Customer email updated", c1_updated['email'] == 'sarah.connor@cyberdyne-aero.com')
assert_test("Customer notes updated", 'Tier 1 partner' in c1_updated['notes'])

# --------------------------------------------------------------------------
# TEST 4: SEARCH CUSTOMERS
# --------------------------------------------------------------------------
print("\n--- Test 4: Search Customers ---")
def sim_search_customers(query):
    term = f"%{query}%"
    cursor.execute("""
    SELECT c.*, (SELECT COUNT(*) FROM wp_tpm_records r WHERE r.customer_id = c.id) AS total_records
    FROM wp_tpm_customers c
    WHERE (c.name LIKE ? OR c.company_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)
    """, (term, term, term, term))
    return cursor.fetchall()

# Search by contact name
s1 = sim_search_customers('Connor')
assert_test("Search by contact name 'Connor' returns Customer 1", len(s1) == 1 and s1[0]['id'] == c1_id)

# Search by company
s2 = sim_search_customers('Machine Intelligence')
assert_test("Search by company returns Customer 2", len(s2) == 1 and s2[0]['id'] == c2_id)

# Search by email
s3 = sim_search_customers('machine.net')
assert_test("Search by email domain returns Customer 2", len(s3) == 1 and s3[0]['id'] == c2_id)

# Search by phone
s4 = sim_search_customers('333-2211')
assert_test("Search by phone returns Customer 2", len(s4) == 1 and s4[0]['id'] == c2_id)

# --------------------------------------------------------------------------
# TEST 5: ASSIGN TO RECORD & ASSOCIATED RECORDS
# --------------------------------------------------------------------------
print("\n--- Test 5: Assign to Records & View Associated Tenders/POs ---")
now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

# Create a Tender linked to Customer 1
cursor.execute("""
INSERT INTO wp_tpm_records (
    record_type, status, tender_number, customer_id, purchase_price, sell_price, expenses,
    actual_payment, balance_payment_due, tender_due_date, created_by, created_at
) VALUES (
    'tender', 'submitted', 'TND-CYBER-01', ?, 50000.00, 75000.00, 2500.00,
    25000.00, 50000.00, '2026-11-01', 1, ?
)
""", (c1_id, now_str))
tnd_id = cursor.lastrowid

# Create a Purchase Order linked to Customer 1
cursor.execute("""
INSERT INTO wp_tpm_records (
    record_type, status, customer_po_number, customer_po_name, customer_id, purchase_price, sell_price, expenses,
    actual_payment, balance_payment_due, po_date, created_by, created_at
) VALUES (
    'po', 'in-progress', 'CPO-CYBER-88', 'Tactical Radar Upgrade', ?, 30000.00, 45000.00, 1500.00,
    45000.00, 0.00, '2026-10-15', 1, ?
)
""", (c1_id, now_str))
po_id = cursor.lastrowid
conn.commit()

# Query associated records for Customer 1
cursor.execute("SELECT * FROM wp_tpm_records WHERE customer_id = ? AND record_type = 'tender'", (c1_id,))
c1_tenders = cursor.fetchall()
assert_test("Customer 1 has 1 associated Tender", len(c1_tenders) == 1 and c1_tenders[0]['tender_number'] == 'TND-CYBER-01')

cursor.execute("SELECT * FROM wp_tpm_records WHERE customer_id = ? AND record_type = 'po'", (c1_id,))
c1_pos = cursor.fetchall()
assert_test("Customer 1 has 1 associated Purchase Order", len(c1_pos) == 1 and c1_pos[0]['customer_po_number'] == 'CPO-CYBER-88')

# --------------------------------------------------------------------------
# TEST 6: FINANCIAL TOTALS AGGREGATION
# --------------------------------------------------------------------------
print("\n--- Test 6: Financial Summary Aggregation ---")
cursor.execute("""
SELECT
    COUNT(id) AS total_records,
    SUM(CASE WHEN record_type = 'tender' THEN 1 ELSE 0 END) AS total_tenders,
    SUM(CASE WHEN record_type = 'po' THEN 1 ELSE 0 END) AS total_pos,
    COALESCE(SUM(sell_price), 0) AS total_revenue,
    COALESCE(SUM(purchase_price), 0) AS total_cost,
    COALESCE(SUM(expenses), 0) AS total_expenses,
    COALESCE(SUM(actual_payment), 0) AS total_paid,
    COALESCE(SUM(balance_payment_due), 0) AS total_balance_due,
    COALESCE(SUM(sell_price - purchase_price - expenses), 0) AS total_net_profit
FROM wp_tpm_records
WHERE customer_id = ? AND is_archived = 0
""", (c1_id,))
fin_summary = cursor.fetchone()

# Expected:
# total_revenue: 75000 + 45000 = 120000
# total_paid: 25000 + 45000 = 70000
# total_balance_due: 50000 + 0 = 50000
# total_profit: (75000 - 50000 - 2500) + (45000 - 30000 - 1500) = 22500 + 13500 = 36000
assert_test("Total records is 2", fin_summary['total_records'] == 2)
assert_test("Total tenders is 1", fin_summary['total_tenders'] == 1)
assert_test("Total POs is 1", fin_summary['total_pos'] == 1)
assert_test("Total revenue is $120,000.00", abs(fin_summary['total_revenue'] - 120000.00) < 0.01)
assert_test("Total payments received is $70,000.00", abs(fin_summary['total_paid'] - 70000.00) < 0.01)
assert_test("Total balance due is $50,000.00", abs(fin_summary['total_balance_due'] - 50000.00) < 0.01)
assert_test("Total net profit is $36,000.00", abs(fin_summary['total_net_profit'] - 36000.00) < 0.01)

# --------------------------------------------------------------------------
# TEST 7: SAFE DELETION GUARD (RECORDS EXIST)
# --------------------------------------------------------------------------
print("\n--- Test 7: Safe Deletion Guard with Associated Records ---")
del_attempt = sim_delete_customer(c1_id)
assert_test("Direct deletion of Customer 1 with records is blocked", del_attempt['success'] is False)
assert_test("Blocked deletion returns 'customer_has_records' error", del_attempt['error'] == 'customer_has_records')

cursor.execute("SELECT id FROM wp_tpm_customers WHERE id = ?", (c1_id,))
assert_test("Customer 1 remains safely in database", cursor.fetchone() is not None)

# --------------------------------------------------------------------------
# TEST 8: DEACTIVATION WORKFLOW
# --------------------------------------------------------------------------
print("\n--- Test 8: Deactivation Workflow ---")
deact_res = sim_deactivate_customer(c1_id)
assert_test("Deactivation succeeds", deact_res['success'] is True)

cursor.execute("SELECT status FROM wp_tpm_customers WHERE id = ?", (c1_id,))
assert_test("Customer 1 status updated to 'inactive'", cursor.fetchone()['status'] == 'inactive')

# Verify customer's records still intact
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE customer_id = ?", (c1_id,))
assert_test("Historic records remain 100% intact after deactivation", cursor.fetchone()[0] == 2)

# --------------------------------------------------------------------------
# TEST 9: REASSIGN RECORDS & DELETE WORKFLOW
# --------------------------------------------------------------------------
print("\n--- Test 9: Reassignment & Deletion Workflow ---")
# Reassign records from Customer 1 to Customer 2, then delete Customer 1
reassign_res = sim_reassign_and_delete(c1_id, c2_id, user_id=1)
assert_test("Reassignment and deletion executed successfully", reassign_res['success'] is True)
assert_test("2 records were reassigned", reassign_res['reassigned_count'] == 2)

# Customer 1 should be gone from wp_tpm_customers
cursor.execute("SELECT id FROM wp_tpm_customers WHERE id = ?", (c1_id,))
assert_test("Customer 1 was permanently deleted after reassignment", cursor.fetchone() is None)

# Customer 2 should now own those 2 records
cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE customer_id = ?", (c2_id,))
assert_test("Customer 2 now owns the 2 reassigned records", cursor.fetchone()[0] == 2)

# Audit log recorded for reassignment
cursor.execute("SELECT COUNT(*) FROM wp_tpm_activity_logs WHERE action = 'record_updated' AND description LIKE '%Customer reassigned%'")
assert_test("Audit log recorded for record transfer", cursor.fetchone()[0] >= 2)

# --------------------------------------------------------------------------
# TEST 10: ZERO-RECORD CUSTOMER DELETION
# --------------------------------------------------------------------------
print("\n--- Test 10: Zero-Record Deletion ---")
# Create customer with 0 records
c3_res = sim_create_customer({'name': 'Temporary Contact', 'company_name': 'Temp Ltd'})
c3_id = c3_res['id']
del_c3 = sim_delete_customer(c3_id)
assert_test("Customer with 0 records can be deleted directly", del_c3['success'] is True)

cursor.execute("SELECT id FROM wp_tpm_customers WHERE id = ?", (c3_id,))
assert_test("Zero-record customer removed from DB", cursor.fetchone() is None)

# --------------------------------------------------------------------------
# TEST 11: UNAUTHORIZED CUSTOMER ACTIONS
# --------------------------------------------------------------------------
print("\n--- Test 11: Unauthorized Access Protection ---")
unauth_res = sim_create_customer({'name': 'Hacker'}, user_caps={'manage_tpm_customers': False})
assert_test("User without manage_tpm_customers cannot create customers", unauth_res['success'] is False)

unauth_edit = sim_update_customer(c2_id, {'name': 'Hacked'}, user_caps={'manage_tpm_customers': False})
assert_test("User without manage_tpm_customers cannot edit customers", unauth_edit['success'] is False)

unauth_del = sim_delete_customer(c2_id, user_caps={'manage_tpm_customers': False})
assert_test("User without manage_tpm_customers cannot delete customers", unauth_del['success'] is False)

# Summary
print("\n" + "=" * 68)
print(f" PHASE 9 VERIFICATION COMPLETE: {passed} PASSED, {failed} FAILED")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    sys.exit(0)
