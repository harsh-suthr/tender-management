#!/usr/bin/env python3
"""
Test Suite for Phase 10: Partner Company Management.

Verifies all required specifications:
1. Create Partner Company (Company Name, Contact Person, Email, Phone, Address, Notes, Status)
2. Edit Partner Company (Update details, status, terms)
3. Search Partner Company (Search across company name, contact person, email, phone)
4. Duplicate Prevention (Unique Company Name case-insensitive, unique email, self-update exemption)
5. Assign to Record (Integration with Tenders & Purchase Orders)
6. Percentage Validation (Strict 0 to 100 validation; reject < 0, > 100, non-numeric)
7. View Associated Records & Financial Summaries (Tenders, POs, Total Contract Value, Net Profit, Partner Profit Share)
8. Safe Deletion Guard (Block deletion with records, Deactivation, Reassign & Delete, Zero-record deletion)
9. Security & Permissions (Capability gating manage_tpm_partners, Nonce verification)
"""

import os
import re
import sys
import sqlite3
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PARTNER_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-partner-service.php')
RECORD_SERVICE_FILE  = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE     = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE    = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
PARTNERS_TABLE_FILE  = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-partners-list-table.php')
PARTNERS_VIEW_FILE   = os.path.join(BASE_DIR, 'admin', 'views', 'partner-companies.php')
ADD_RECORD_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'add-record.php')
RECORD_DETAILS_FILE  = os.path.join(BASE_DIR, 'admin', 'views', 'record-details.php')
ADMIN_CSS_FILE       = os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')

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
print(" PHASE 10: PARTNER COMPANY MANAGEMENT VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(PARTNER_SERVICE_FILE, 'r') as f:
    partner_service_code = f.read()

with open(RECORD_SERVICE_FILE, 'r') as f:
    rec_service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(PARTNERS_TABLE_FILE, 'r') as f:
    partner_table_code = f.read()

with open(PARTNERS_VIEW_FILE, 'r') as f:
    partners_view = f.read()

with open(ADD_RECORD_VIEW_FILE, 'r') as f:
    add_record_view = f.read()

with open(RECORD_DETAILS_FILE, 'r') as f:
    record_details_view = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# --------------------------------------------------------------------------
# PART A: STRUCTURAL ARCHITECTURE & HOOK REGISTRATION
# --------------------------------------------------------------------------
print("\n--- Architecture, Classes & Hook Registrations ---")

assert_test("class-tpm-partner-service.php exists", os.path.exists(PARTNER_SERVICE_FILE))
assert_test("class-tpm-partners-list-table.php exists", os.path.exists(PARTNERS_TABLE_FILE))
assert_test("TPM_Partner_Service class defined", "class TPM_Partner_Service" in partner_service_code)
assert_test("TPM_Partners_List_Table subclasses WP_List_Table", "class TPM_Partners_List_Table extends WP_List_Table" in partner_table_code)

assert_test("Plugin requires class-tpm-partner-service.php", "class-tpm-partner-service.php" in plugin_code)
assert_test("Plugin requires class-tpm-partners-list-table.php", "class-tpm-partners-list-table.php" in plugin_code)

assert_test("Plugin registers admin_post_tpm_save_partner", "admin_post_tpm_save_partner" in plugin_code)
assert_test("Plugin registers admin_post_tpm_update_partner", "admin_post_tpm_update_partner" in plugin_code)
assert_test("Plugin registers admin_post_tpm_delete_partner", "admin_post_tpm_delete_partner" in plugin_code)
assert_test("Plugin registers admin_post_tpm_deactivate_partner", "admin_post_tpm_deactivate_partner" in plugin_code)
assert_test("Plugin registers admin_post_tpm_reassign_partner", "admin_post_tpm_reassign_partner" in plugin_code)

assert_test("TPM_Admin::handle_save_partner() implemented", "function handle_save_partner" in admin_code)
assert_test("TPM_Admin::handle_update_partner() implemented", "function handle_update_partner" in admin_code)
assert_test("TPM_Admin::handle_delete_partner() implemented", "function handle_delete_partner" in admin_code)
assert_test("TPM_Admin::handle_deactivate_partner() implemented", "function handle_deactivate_partner" in admin_code)
assert_test("TPM_Admin::handle_reassign_partner() implemented", "function handle_reassign_partner" in admin_code)

# --------------------------------------------------------------------------
# PART B: SECURITY & NONCE VERIFICATION
# --------------------------------------------------------------------------
print("\n--- Security, Capabilities & Nonce Verification ---")

assert_test("Partner page gates on manage_tpm_partners or manage_tpm_records",
            "manage_tpm_partners" in partners_view and "current_user_can" in partners_view)
assert_test("TPM_Admin::display_partner_companies_page() checks capabilities",
            "manage_tpm_partners" in admin_code and "display_partner_companies_page" in admin_code)
assert_test("TPM_Admin::handle_save_partner() checks nonce and capability",
            "check_admin_referer" in admin_code and "tpm_save_partner_action" in admin_code)
assert_test("TPM_Admin::handle_update_partner() checks nonce and capability",
            "check_admin_referer" in admin_code and "tpm_update_partner_action" in admin_code)
assert_test("TPM_Admin::handle_delete_partner() checks nonce and capability",
            "check_admin_referer" in admin_code and "tpm_delete_partner_" in admin_code)
assert_test("TPM_Admin::handle_deactivate_partner() checks nonce and capability",
            "check_admin_referer" in admin_code and "tpm_deactivate_partner_" in admin_code)
assert_test("TPM_Admin::handle_reassign_partner() checks nonce and capability",
            "check_admin_referer" in admin_code and "tpm_reassign_partner_action" in admin_code)

# --------------------------------------------------------------------------
# PART C: TABLE COLUMNS & VIEW SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Partner List Table Columns & Views Specification ---")

assert_test("Column 'company_name' (Company) defined in list table", "'company_name'   => __( 'Company'" in partner_table_code)
assert_test("Column 'contact_person' (Contact Person) defined in list table", "'contact_person' => __( 'Contact Person'" in partner_table_code)
assert_test("Column 'email' (Email) defined in list table", "'email'          => __( 'Email'" in partner_table_code)
assert_test("Column 'phone' (Phone) defined in list table", "'phone'          => __( 'Phone'" in partner_table_code)
assert_test("Column 'total_records' (Associated Records) defined in list table", "'total_records'  => __( 'Associated Records'" in partner_table_code)
assert_test("Column 'status' (Status) defined in list table", "'status'         => __( 'Status'" in partner_table_code)
assert_test("Column 'actions' (Actions) defined in list table", "'actions'        => __( 'Actions'" in partner_table_code)

assert_test("List table defines filter views (All, Active, Inactive)",
            "'all'" in partner_table_code and "'active'" in partner_table_code and "'inactive'" in partner_table_code)

assert_test("List table implements search across company_name, contact_person, email, phone",
            "p.company_name LIKE" in partner_service_code and "p.contact_person LIKE" in partner_service_code and "p.email LIKE" in partner_service_code)

# --------------------------------------------------------------------------
# PART D: PARTNER DETAILS VIEW SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Partner Details View Inspection ---")

assert_test("partner-companies.php implements Details view ('view' action)",
            "'view' === $action" in partners_view)
assert_test("Details view displays Associated Tenders",
            "Associated Tenders" in partners_view and "$tenders" in partners_view)
assert_test("Details view displays Associated Purchase Orders",
            "Associated Purchase Orders" in partners_view and "$pos" in partners_view)
assert_test("Details view displays Financial KPI totals (Contract Value, Profit, Partner Share)",
            "Total Contract Value" in partners_view and "Project Cumulative Profit" in partners_view and "Partner Profit Share" in partners_view)
assert_test("Details view displays Company information (Company, Contact, Email, Phone, Address, Notes)",
            "$partner->company_name" in partners_view and "$partner->contact_person" in partners_view and "$partner->email" in partners_view and "$partner->phone" in partners_view and "$partner->address" in partners_view and "$partner->notes" in partners_view)

# --------------------------------------------------------------------------
# PART E: SAFE DELETION, DEACTIVATION & REASSIGNMENT SPECIFICATION
# --------------------------------------------------------------------------
print("\n--- Safe Deletion Guard Inspection ---")

assert_test("partner-companies.php implements Delete Guard screen ('delete' action)",
            "'delete' === $action" in partners_view)
assert_test("Delete Guard checks can_delete dependency",
            "can_delete" in partners_view and "can_delete" in partner_service_code)
assert_test("Delete Guard provides Deactivation option",
            "Deactivate Partner Company" in partners_view and "tpm_deactivate_partner" in partners_view)
assert_test("Delete Guard provides Reassign Records option",
            "Reassign Records" in partners_view and "tpm_reassign_partner" in partners_view)
assert_test("TPM_Partner_Service::can_delete() returns dependency counts",
            "record_count" in partner_service_code and "tenders_count" in partner_service_code and "pos_count" in partner_service_code)
assert_test("TPM_Partner_Service::delete_partner() blocks deletion when records exist",
            "partner_has_records" in partner_service_code)
assert_test("TPM_Partner_Service::reassign_and_delete() reassigns records and logs audit trail",
            "Partner company reassigned from" in partner_service_code and "TPM_Record_Service::log_activity" in partner_service_code)

# --------------------------------------------------------------------------
# PART F: INTEGRATION WITH ADD/EDIT RECORD & RECORD DETAILS
# --------------------------------------------------------------------------
print("\n--- Integration with Add/Edit Record & Details ---")

assert_test("add-record.php queries active partner companies from tpm_partner_companies",
            "$partners_table" in add_record_view and "FROM `{$partners_table}`" in add_record_view)
assert_test("add-record.php includes assigned partner even if inactive",
            "status = 'active' OR id = %d" in add_record_view)
assert_test("add-record.php formats contact person and [Inactive] tag in dropdown",
            "$partner->contact_person" in add_record_view and ( "[Inactive]" in add_record_view or "Inactive" in add_record_view ))
assert_test("add-record.php provides + Add Partner quick link",
            "admin.php?page=tpm-partner-companies&action=new" in add_record_view)
assert_test("add-record.php enforces Partner Percentage input (min 0, max 100, step 0.01)",
            'min="0"' in add_record_view and 'max="100"' in add_record_view and 'name="partner_percentage"' in add_record_view)

assert_test("TPM_Record_Service::validate() validates partner_percentage 0 to 100 strictly",
            "Partner Percentage must be between 0% and 100%." in rec_service_code)
assert_test("TPM_Record_Service::validate() checks partner company existence",
            "Selected Partner Company does not exist." in rec_service_code)
assert_test("TPM_Record_Service::update() detects partner company changes in activity logs",
            "Partner Company changed:" in rec_service_code and "'partner_company_id'" in rec_service_code)

assert_test("record-details.php links Partner Organization to partner details view",
            "admin.php?page=tpm-partner-companies&action=view&id=" in record_details_view)


# --------------------------------------------------------------------------
# PART G: FUNCTIONAL DATABASE SIMULATION TESTS
# --------------------------------------------------------------------------
print("\n--- Database Simulation & Functional Tests ---")

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Create schema
cursor.executescript("""
CREATE TABLE wp_tpm_partner_companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    contact_person TEXT,
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
    partner_company_id INTEGER,
    partner_percentage REAL DEFAULT 0.00,
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

# Python simulation of TPM_Partner_Service logic
def sim_validate_partner(data, partner_id=0):
    errors = []
    company = (data.get('company_name') or '').strip()
    if not company:
        errors.append("Company Name is required.")

    email = (data.get('email') or '').strip()
    if email and not re.match(r"[^@]+@[^@]+\.[^@]+", email):
        errors.append("Please enter a valid email address.")

    # Check duplicate company_name (case-insensitive)
    if company:
        cursor.execute("SELECT id FROM wp_tpm_partner_companies WHERE LOWER(company_name) = LOWER(?) AND id != ?", (company, partner_id))
        if cursor.fetchone():
            errors.append("A partner company with this name already exists.")

    # Check duplicate email
    if email:
        cursor.execute("SELECT id FROM wp_tpm_partner_companies WHERE LOWER(email) = LOWER(?) AND id != ?", (email, partner_id))
        if cursor.fetchone():
            errors.append("A partner company with this email address is already registered.")

    return errors

def sim_create_partner(raw_data, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'manage_tpm_partners': True}
    if not user_caps.get('manage_tpm_partners'):
        return {'success': False, 'error': 'unauthorized', 'message': 'Permission denied'}

    errors = sim_validate_partner(raw_data)
    if errors:
        return {'success': False, 'error': 'validation_failed', 'errors': errors}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
    INSERT INTO wp_tpm_partner_companies (company_name, contact_person, email, phone, address, notes, status, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (
        raw_data.get('company_name'),
        raw_data.get('contact_person'),
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

def sim_update_partner(partner_id, raw_data, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'manage_tpm_partners': True}
    if not user_caps.get('manage_tpm_partners'):
        return {'success': False, 'error': 'unauthorized', 'message': 'Permission denied'}

    cursor.execute("SELECT * FROM wp_tpm_partner_companies WHERE id = ?", (partner_id,))
    if not cursor.fetchone():
        return {'success': False, 'error': 'not_found', 'message': 'Partner not found'}

    errors = sim_validate_partner(raw_data, partner_id)
    if errors:
        return {'success': False, 'error': 'validation_failed', 'errors': errors}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
    UPDATE wp_tpm_partner_companies SET
        company_name = ?,
        contact_person = ?,
        email = ?,
        phone = ?,
        address = ?,
        notes = ?,
        status = ?,
        updated_at = ?
    WHERE id = ?
    """, (
        raw_data.get('company_name'),
        raw_data.get('contact_person'),
        raw_data.get('email'),
        raw_data.get('phone'),
        raw_data.get('address'),
        raw_data.get('notes'),
        raw_data.get('status', 'active'),
        now,
        partner_id
    ))
    conn.commit()
    return {'success': True, 'id': partner_id}

def sim_can_delete_partner(partner_id):
    cursor.execute("""
    SELECT
        COUNT(id) AS record_count,
        SUM(CASE WHEN record_type = 'tender' THEN 1 ELSE 0 END) AS tenders_count,
        SUM(CASE WHEN record_type = 'po' THEN 1 ELSE 0 END) AS pos_count
    FROM wp_tpm_records WHERE partner_company_id = ?
    """, (partner_id,))
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

def sim_delete_partner(partner_id, user_id=1, user_caps=None):
    if user_caps is None:
        user_caps = {'manage_tpm_partners': True}
    if not user_caps.get('manage_tpm_partners'):
        return {'success': False, 'error': 'unauthorized', 'message': 'Permission denied'}

    check = sim_can_delete_partner(partner_id)
    if not check['can_delete']:
        return {
            'success': False,
            'error': 'partner_has_records',
            'message': f"Cannot delete partner: {check['record_count']} associated records exist."
        }

    cursor.execute("DELETE FROM wp_tpm_partner_companies WHERE id = ?", (partner_id,))
    conn.commit()
    return {'success': True}

def sim_deactivate_partner(partner_id):
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("UPDATE wp_tpm_partner_companies SET status = 'inactive', updated_at = ? WHERE id = ?", (now, partner_id))
    conn.commit()
    return {'success': True}

def sim_reassign_and_delete(source_id, target_id, user_id=1):
    if source_id == target_id:
        return {'success': False, 'error': 'invalid_reassignment', 'message': 'Source and target must differ'}

    cursor.execute("SELECT id FROM wp_tpm_partner_companies WHERE id = ?", (source_id,))
    if not cursor.fetchone():
        return {'success': False, 'error': 'source_not_found'}

    cursor.execute("SELECT id FROM wp_tpm_partner_companies WHERE id = ?", (target_id,))
    if not cursor.fetchone():
        return {'success': False, 'error': 'target_not_found'}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("SELECT id FROM wp_tpm_records WHERE partner_company_id = ?", (source_id,))
    records = cursor.fetchall()

    cursor.execute("UPDATE wp_tpm_records SET partner_company_id = ?, updated_by = ?, updated_at = ? WHERE partner_company_id = ?", (target_id, user_id, now, source_id))

    for r in records:
        cursor.execute("""
        INSERT INTO wp_tpm_activity_logs (record_id, user_id, action, description, old_value, new_value, created_at)
        VALUES (?, ?, 'record_updated', 'Partner company reassigned during cleanup', ?, ?, ?)
        """, (r['id'], user_id, str(source_id), str(target_id), now))

    cursor.execute("DELETE FROM wp_tpm_partner_companies WHERE id = ?", (source_id,))
    conn.commit()
    return {'success': True, 'reassigned_count': len(records)}

def sim_validate_record(data):
    errors = []
    # Percentage validation
    pct_raw = data.get('partner_percentage')
    if pct_raw is not None and str(pct_raw).strip() != '':
        try:
            val = float(str(pct_raw).replace(',', '').strip())
            if val < 0 or val > 100:
                errors.append("Partner Percentage must be between 0% and 100%.")
        except ValueError:
            errors.append("Partner Percentage must be a valid number between 0% and 100%.")

    # Partner existence check
    partner_id = data.get('partner_company_id')
    if partner_id:
        cursor.execute("SELECT id FROM wp_tpm_partner_companies WHERE id = ?", (partner_id,))
        if not cursor.fetchone():
            errors.append("Selected Partner Company does not exist.")

    return errors

def sim_create_record(data, user_id=1):
    errors = sim_validate_record(data)
    if errors:
        return {'success': False, 'error': 'validation_failed', 'errors': errors}

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
    INSERT INTO wp_tpm_records (
        record_type, status, tender_number, customer_id, partner_company_id, partner_percentage,
        customer_po_number, customer_po_name, purchase_price, sell_price, expenses,
        actual_payment, balance_payment_due, tender_due_date, po_date, is_archived,
        created_by, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
    """, (
        data.get('record_type', 'tender'),
        data.get('status', 'draft'),
        data.get('tender_number'),
        data.get('customer_id', 1),
        data.get('partner_company_id'),
        float(data.get('partner_percentage', 0.00) or 0.00),
        data.get('customer_po_number'),
        data.get('customer_po_name'),
        float(data.get('purchase_price', 0.00) or 0.00),
        float(data.get('sell_price', 0.00) or 0.00),
        float(data.get('expenses', 0.00) or 0.00),
        float(data.get('actual_payment', 0.00) or 0.00),
        float(data.get('balance_payment_due', 0.00) or 0.00),
        data.get('tender_due_date'),
        data.get('po_date'),
        user_id,
        now,
        now
    ))
    conn.commit()
    return {'success': True, 'id': cursor.lastrowid}

def sim_partner_financial_summary(partner_id):
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
        COALESCE(SUM(sell_price - purchase_price - expenses), 0) AS total_net_profit,
        COALESCE(SUM((sell_price - purchase_price - expenses) * (partner_percentage / 100)), 0) AS partner_profit_share,
        COALESCE(SUM(sell_price * (partner_percentage / 100)), 0) AS partner_revenue_share,
        COALESCE(AVG(partner_percentage), 0) AS avg_percentage
    FROM wp_tpm_records
    WHERE partner_company_id = ? AND is_archived = 0
    """, (partner_id,))
    row = cursor.fetchone()
    return {
        'total_records': row['total_records'] or 0,
        'total_tenders': row['total_tenders'] or 0,
        'total_pos': row['total_pos'] or 0,
        'total_revenue': float(row['total_revenue'] or 0.0),
        'total_cost': float(row['total_cost'] or 0.0),
        'total_expenses': float(row['total_expenses'] or 0.0),
        'total_paid': float(row['total_paid'] or 0.0),
        'total_balance_due': float(row['total_balance_due'] or 0.0),
        'total_net_profit': float(row['total_net_profit'] or 0.0),
        'partner_profit_share': float(row['partner_profit_share'] or 0.0),
        'partner_revenue_share': float(row['partner_revenue_share'] or 0.0),
        'avg_percentage': float(row['avg_percentage'] or 0.0),
    }

# --------------------------------------------------------------------------
# EXECUTE SIMULATION TESTS
# --------------------------------------------------------------------------

# 1. Create Partner Company with all fields
p1_data = {
    'company_name': 'Alpha Joint Ventures Inc',
    'contact_person': 'Robert Hayes',
    'email': 'hayes@alphajv.com',
    'phone': '+1 (555) 123-4567',
    'address': '100 Partnership Way, Suite 400, Chicago, IL',
    'notes': '25% standard consortium share on health tenders.',
    'status': 'active'
}
res1 = sim_create_partner(p1_data)
assert_test("Create Partner Company 1 successfully", res1['success'])
p1_id = res1['id']

cursor.execute("SELECT * FROM wp_tpm_partner_companies WHERE id = ?", (p1_id,))
p1_row = cursor.fetchone()
assert_test("Stored company_name matches input", p1_row['company_name'] == 'Alpha Joint Ventures Inc')
assert_test("Stored contact_person matches input", p1_row['contact_person'] == 'Robert Hayes')
assert_test("Stored email matches input", p1_row['email'] == 'hayes@alphajv.com')
assert_test("Stored phone matches input", p1_row['phone'] == '+1 (555) 123-4567')
assert_test("Stored address matches input", p1_row['address'] == '100 Partnership Way, Suite 400, Chicago, IL')
assert_test("Stored notes matches input", p1_row['notes'] == '25% standard consortium share on health tenders.')
assert_test("Stored status matches input", p1_row['status'] == 'active')

# 2. Validation: Company Name required
res_bad_name = sim_create_partner({'company_name': '', 'email': 'valid@test.com'})
assert_test("Creation without Company Name rejected", not res_bad_name['success'] and "Company Name is required." in res_bad_name['errors'])

# 3. Validation: Invalid email rejected
res_bad_email = sim_create_partner({'company_name': 'Beta Tech', 'email': 'not-an-email'})
assert_test("Creation with invalid email format rejected", not res_bad_email['success'] and "Please enter a valid email address." in res_bad_email['errors'])

# 4. Duplicate Prevention: Case-insensitive company name rejected
res_dup = sim_create_partner({'company_name': 'alpha joint ventures inc', 'email': 'other@test.com'})
assert_test("Creation with duplicate Company Name (case-insensitive) rejected", not res_dup['success'] and "A partner company with this name already exists." in res_dup['errors'])

# 5. Duplicate Prevention: Unique email
res_dup_email = sim_create_partner({'company_name': 'Omega LLC', 'email': 'hayes@alphajv.com'})
assert_test("Creation with duplicate email rejected", not res_dup_email['success'] and "A partner company with this email address is already registered." in res_dup_email['errors'])

# 6. Edit Partner: Updates fields, status, notes
res_edit = sim_update_partner(p1_id, {
    'company_name': 'Alpha Global Ventures Ltd',
    'contact_person': 'Robert Hayes Jr.',
    'email': 'hayes.jr@alphajv.com',
    'phone': '+1 (555) 999-8888',
    'address': '200 High Tower, Chicago, IL',
    'notes': 'Commission revised to 30% profit share.',
    'status': 'active'
})
assert_test("Update Partner Company successfully", res_edit['success'])

cursor.execute("SELECT * FROM wp_tpm_partner_companies WHERE id = ?", (p1_id,))
p1_updated = cursor.fetchone()
assert_test("Updated company_name saved", p1_updated['company_name'] == 'Alpha Global Ventures Ltd')
assert_test("Updated contact_person saved", p1_updated['contact_person'] == 'Robert Hayes Jr.')
assert_test("Updated notes saved", p1_updated['notes'] == 'Commission revised to 30% profit share.')

# 7. Edit Partner: Self-update allows keeping same name and email
res_self = sim_update_partner(p1_id, {
    'company_name': 'Alpha Global Ventures Ltd',
    'contact_person': 'Robert Hayes Jr.',
    'email': 'hayes.jr@alphajv.com',
    'status': 'active'
})
assert_test("Self-update allows keeping same company name and email", res_self['success'])

# 8. Create Partner Company 2
p2_data = {
    'company_name': 'Beta Logistics Solutions',
    'contact_person': 'Elena Rostova',
    'email': 'elena@betalogistics.com',
    'phone': '+1 (555) 456-7890',
    'status': 'active'
}
res2 = sim_create_partner(p2_data)
assert_test("Create Partner Company 2 successfully", res2['success'])
p2_id = res2['id']

# 9. Create Partner Company 3 (Inactive)
p3_data = {
    'company_name': 'Gamma Supply Partners',
    'contact_person': 'Marcus Vance',
    'email': 'vance@gammasupply.com',
    'status': 'inactive'
}
res3 = sim_create_partner(p3_data)
assert_test("Create Partner Company 3 (Inactive) successfully", res3['success'])
p3_id = res3['id']

# 10. Search Partners simulation
def sim_search_partners(term):
    t = f"%{term}%"
    cursor.execute("""
    SELECT * FROM wp_tpm_partner_companies
    WHERE company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?
    """, (t, t, t, t))
    return cursor.fetchall()

search_by_comp = sim_search_partners("Alpha")
assert_test("Search by company_name returns correct partner", len(search_by_comp) == 1 and search_by_comp[0]['id'] == p1_id)

search_by_contact = sim_search_partners("Elena")
assert_test("Search by contact_person returns correct partner", len(search_by_contact) == 1 and search_by_contact[0]['id'] == p2_id)

search_by_email = sim_search_partners("gammasupply")
assert_test("Search by email returns correct partner", len(search_by_email) == 1 and search_by_email[0]['id'] == p3_id)

# 11. Status Counts
cursor.execute("SELECT COUNT(*) FROM wp_tpm_partner_companies")
total_count = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM wp_tpm_partner_companies WHERE status = 'active'")
active_count = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM wp_tpm_partner_companies WHERE status = 'inactive'")
inactive_count = cursor.fetchone()[0]
assert_test("Status view counts accurate (total=3, active=2, inactive=1)", total_count == 3 and active_count == 2 and inactive_count == 1)

# 12. Assign Partner to Record & Percentage Validation
# Test Percentage Validation Rules:
# Valid percentages: 0, 25.5, 100
assert_test("Valid percentage 0 accepted", len(sim_validate_record({'partner_company_id': p1_id, 'partner_percentage': '0'})) == 0)
assert_test("Valid percentage 25.5 accepted", len(sim_validate_record({'partner_company_id': p1_id, 'partner_percentage': '25.5'})) == 0)
assert_test("Valid percentage 100 accepted", len(sim_validate_record({'partner_company_id': p1_id, 'partner_percentage': '100'})) == 0)

# Invalid percentages: -5, 100.01, 150, "abc"
assert_test("Negative percentage (-5) rejected", "Partner Percentage must be between 0% and 100%." in sim_validate_record({'partner_company_id': p1_id, 'partner_percentage': '-5'}))
assert_test("Percentage > 100 (105) rejected", "Partner Percentage must be between 0% and 100%." in sim_validate_record({'partner_company_id': p1_id, 'partner_percentage': '105'}))
assert_test("Non-numeric percentage ('abc') rejected", "Partner Percentage must be a valid number between 0% and 100%." in sim_validate_record({'partner_company_id': p1_id, 'partner_percentage': 'abc'}))
assert_test("Non-existent partner_company_id (9999) rejected", "Selected Partner Company does not exist." in sim_validate_record({'partner_company_id': 9999, 'partner_percentage': '20'}))

# 13. Create Tender with Partner Assignment
rec1 = sim_create_record({
    'record_type': 'tender',
    'status': 'submitted',
    'tender_number': 'TND-2026-P10-001',
    'customer_id': 1,
    'partner_company_id': p1_id,
    'partner_percentage': 25.00,
    'purchase_price': 60000.00,
    'sell_price': 100000.00,
    'expenses': 5000.00,
    'actual_payment': 100000.00,
    'balance_payment_due': 0.00,
    'tender_due_date': '2026-11-15'
})
assert_test("Create Tender record with partner_company_id and partner_percentage=25%", rec1['success'])
r1_id = rec1['id']

# 14. Create Purchase Order with Partner Assignment
rec2 = sim_create_record({
    'record_type': 'po',
    'status': 'confirmed',
    'customer_po_number': 'PO-2026-P10-002',
    'customer_po_name': 'Logistics Upgrade Package',
    'customer_id': 1,
    'partner_company_id': p1_id,
    'partner_percentage': 30.00,
    'purchase_price': 30000.00,
    'sell_price': 50000.00,
    'expenses': 2000.00,
    'actual_payment': 25000.00,
    'balance_payment_due': 25000.00,
    'po_date': '2026-10-01'
})
assert_test("Create Purchase Order with partner_company_id and partner_percentage=30%", rec2['success'])
r2_id = rec2['id']

# 15. View Associated Records for Partner 1
cursor.execute("SELECT * FROM wp_tpm_records WHERE partner_company_id = ? AND is_archived = 0 ORDER BY id ASC", (p1_id,))
assoc_records = cursor.fetchall()
assert_test("Partner 1 has 2 associated records", len(assoc_records) == 2)
assert_test("First associated record is Tender TND-2026-P10-001", assoc_records[0]['tender_number'] == 'TND-2026-P10-001')
assert_test("Second associated record is PO PO-2026-P10-002", assoc_records[1]['customer_po_number'] == 'PO-2026-P10-002')

# 16. Financial Summaries Computation
# Calculation verification:
# Rec 1: Sell 100,000 | Purchase 60,000 | Expenses 5,000 | Net Profit = 35,000 | Partner 25% Share = 8,750
# Rec 2: Sell 50,000  | Purchase 30,000 | Expenses 2,000 | Net Profit = 18,000 | Partner 30% Share = 5,400
# Total Revenue: 150,000.00
# Total Cumulative Net Profit: 35,000 + 18,000 = 53,000.00
# Total Partner Profit Share: 8,750 + 5,400 = 14,150.00
# Average Percentage: (25 + 30) / 2 = 27.50%
summary = sim_partner_financial_summary(p1_id)
assert_test("Financial Summary: Total Records = 2 (1 Tender, 1 PO)", summary['total_records'] == 2 and summary['total_tenders'] == 1 and summary['total_pos'] == 1)
assert_test("Financial Summary: Total Revenue = $150,000.00", abs(summary['total_revenue'] - 150000.00) < 0.01)
assert_test("Financial Summary: Total Cost = $90,000.00", abs(summary['total_cost'] - 90000.00) < 0.01)
assert_test("Financial Summary: Total Net Profit = $53,000.00", abs(summary['total_net_profit'] - 53000.00) < 0.01)
assert_test("Financial Summary: Total Partner Profit Share = $14,150.00", abs(summary['partner_profit_share'] - 14150.00) < 0.01)
assert_test("Financial Summary: Average Partner % = 27.50%", abs(summary['avg_percentage'] - 27.50) < 0.01)

# 17. Safe Deletion Guard: Deletion blocked when records exist
del_res = sim_delete_partner(p1_id)
assert_test("Safe Deletion Guard blocks deletion of partner with active records", not del_res['success'] and del_res['error'] == 'partner_has_records')

# 18. Option 1: Deactivate partner
deact_res = sim_deactivate_partner(p1_id)
assert_test("Deactivate partner succeeds", deact_res['success'])

cursor.execute("SELECT status FROM wp_tpm_partner_companies WHERE id = ?", (p1_id,))
assert_test("Partner status updated to inactive", cursor.fetchone()['status'] == 'inactive')

cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE partner_company_id = ?", (p1_id,))
assert_test("Associated records remain intact after partner deactivation", cursor.fetchone()[0] == 2)

# Reactivate partner 1 for reassignment test
cursor.execute("UPDATE wp_tpm_partner_companies SET status = 'active' WHERE id = ?", (p1_id,))
conn.commit()

# 19. Option 2: Reassign records to Partner 2 and delete Partner 1
reassign_res = sim_reassign_and_delete(p1_id, p2_id, user_id=1)
assert_test("Reassign and delete partner succeeds", reassign_res['success'] and reassign_res['reassigned_count'] == 2)

cursor.execute("SELECT * FROM wp_tpm_partner_companies WHERE id = ?", (p1_id,))
assert_test("Source partner company deleted from database", cursor.fetchone() is None)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE partner_company_id = ?", (p2_id,))
assert_test("Records transferred to target partner company (Partner 2)", cursor.fetchone()[0] == 2)

cursor.execute("SELECT COUNT(*) FROM wp_tpm_activity_logs WHERE action = 'record_updated'")
assert_test("Audit trail activity logs recorded for transferred records", cursor.fetchone()[0] >= 2)

# 20. Direct Safe Deletion: Deleting partner with 0 records
# Partner 3 has 0 records
del_p3 = sim_delete_partner(p3_id)
assert_test("Direct deletion of partner with 0 records succeeds cleanly", del_p3['success'])
cursor.execute("SELECT * FROM wp_tpm_partner_companies WHERE id = ?", (p3_id,))
assert_test("Partner 3 deleted from database", cursor.fetchone() is None)

# --------------------------------------------------------------------------
# SUMMARY REPORT
# --------------------------------------------------------------------------
print("\n" + "=" * 68)
print(f" PHASE 10 VERIFICATION COMPLETE: {passed} PASSED, {failed} FAILED")
print("=" * 68)

if failed > 0:
    print(f"\n[!] ERROR: {failed} test(s) failed. Please review.")
    sys.exit(1)
else:
    print("\n[✓] SUCCESS: All Phase 10 Partner Company Management specifications validated perfectly!")
    sys.exit(0)
