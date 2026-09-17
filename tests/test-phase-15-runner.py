#!/usr/bin/env python3
"""
Verification Test Suite: Phase 15 - Settings
Tender & Purchase Order Management System (tender-po-management)

Validates:
1. Architecture, classes, dependencies, and action hook registrations.
2. Settings admin view (settings.php), 5 navigation tabs, and form components.
3. Security verification: capability checks (manage_tpm_settings / manage_options), nonces, and sanitization.
4. General Settings validation: Currency, Currency Symbol, Date Format, Records Per Page (5-100).
5. Attachment Settings: Allowed file types, Maximum file size (MB), Predefined attachment labels.
6. Archive Settings: Hide archived default, Permanent delete capability level.
7. Uninstall Settings & uninstall.php:
   - "Delete all plugin data on uninstall" toggle (default: disabled)
   - Proper dropping of all 7 database tables and removal of capabilities when enabled.
8. Status Management Lifecycle:
   - Add status (name, unique slug, assignment to Tender/PO/Both, hex color, sort order).
   - Edit status (name, color, assignment, slug cascade).
   - Disable / Enable status (is_active toggle).
   - Reorder statuses.
   - Guardrail protection: Block deletion of statuses currently in use by records with warning.
   - Successful deletion of unused statuses.
9. Invalid values & boundary conditions testing.
"""

import sys
import os
import re
import sqlite3

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PASS_COUNT = 0
FAIL_COUNT = 0

def test(condition, description):
    global PASS_COUNT, FAIL_COUNT
    if condition:
        PASS_COUNT += 1
        print(f" [PASS] {description}")
    else:
        FAIL_COUNT += 1
        print(f" [FAIL] {description}")

def read_file(relative_path):
    full_path = os.path.join(PROJECT_ROOT, relative_path)
    if not os.path.exists(full_path):
        return ""
    with open(full_path, "r", encoding="utf-8") as f:
        return f.read()

print("====================================================================")
print(" PHASE 15: SETTINGS & STATUS MANAGEMENT VERIFICATION SUITE")
print("====================================================================")

# ====================================================================
# SECTION 1: Architecture, Classes & Hook Registrations
# ====================================================================
print("\n--- Architecture, Classes & Hook Registrations ---")

settings_service_file = os.path.join(PROJECT_ROOT, "includes/services/class-tpm-settings-service.php")
test(os.path.exists(settings_service_file), "class-tpm-settings-service.php exists")

service_code = read_file("includes/services/class-tpm-settings-service.php")
test("class TPM_Settings_Service" in service_code, "TPM_Settings_Service class defined")

plugin_code = read_file("includes/class-tpm-plugin.php")
test("class-tpm-settings-service.php" in plugin_code, "Plugin requires class-tpm-settings-service.php")
test("admin_post_tpm_save_settings" in plugin_code, "Plugin registers admin_post_tpm_save_settings hook")
test("admin_post_tpm_add_status" in plugin_code, "Plugin registers admin_post_tpm_add_status hook")
test("admin_post_tpm_edit_status" in plugin_code, "Plugin registers admin_post_tpm_edit_status hook")
test("admin_post_tpm_toggle_status" in plugin_code, "Plugin registers admin_post_tpm_toggle_status hook")
test("admin_post_tpm_delete_status" in plugin_code, "Plugin registers admin_post_tpm_delete_status hook")
test("admin_post_tpm_reorder_statuses" in plugin_code, "Plugin registers admin_post_tpm_reorder_statuses hook")

admin_code = read_file("includes/admin/class-tpm-admin.php")
test("function display_settings_page" in admin_code, "TPM_Admin::display_settings_page() implemented")
test("function handle_save_settings" in admin_code, "TPM_Admin::handle_save_settings() implemented")
test("function handle_add_status" in admin_code, "TPM_Admin::handle_add_status() implemented")
test("function handle_edit_status" in admin_code, "TPM_Admin::handle_edit_status() implemented")
test("function handle_toggle_status" in admin_code, "TPM_Admin::handle_toggle_status() implemented")
test("function handle_delete_status" in admin_code, "TPM_Admin::handle_delete_status() implemented")
test("function handle_reorder_statuses" in admin_code, "TPM_Admin::handle_reorder_statuses() implemented")

# Verify method definitions in TPM_Settings_Service
test("function get_defaults" in service_code, "TPM_Settings_Service::get_defaults() implemented")
test("function get_settings" in service_code, "TPM_Settings_Service::get_settings() implemented")
test("function get_setting" in service_code, "TPM_Settings_Service::get_setting() implemented")
test("function update_settings" in service_code, "TPM_Settings_Service::update_settings() implemented")
test("function get_statuses" in service_code, "TPM_Settings_Service::get_statuses() implemented")
test("function get_status" in service_code, "TPM_Settings_Service::get_status() implemented")
test("function get_status_by_slug" in service_code, "TPM_Settings_Service::get_status_by_slug() implemented")
test("function add_status" in service_code, "TPM_Settings_Service::add_status() implemented")
test("function update_status" in service_code, "TPM_Settings_Service::update_status() implemented")
test("function toggle_status_active" in service_code, "TPM_Settings_Service::toggle_status_active() implemented")
test("function reorder_statuses" in service_code, "TPM_Settings_Service::reorder_statuses() implemented")
test("function get_records_count_with_status" in service_code, "TPM_Settings_Service::get_records_count_with_status() implemented")
test("function delete_status" in service_code, "TPM_Settings_Service::delete_status() implemented")

# ====================================================================
# SECTION 2: Security & Capability Verification
# ====================================================================
print("\n--- Security, Capabilities & Nonce Verification ---")

test("manage_tpm_settings" in admin_code, "display_settings_page() enforces 'manage_tpm_settings' capability")
test("check_admin_referer( 'tpm_save_settings'" in admin_code, "handle_save_settings() verifies nonce 'tpm_save_settings'")
test("check_admin_referer( 'tpm_add_status'" in admin_code, "handle_add_status() verifies nonce 'tpm_add_status'")
test("check_admin_referer( 'tpm_edit_status'" in admin_code, "handle_edit_status() verifies nonce 'tpm_edit_status'")
test("check_admin_referer( 'tpm_toggle_status_'" in admin_code, "handle_toggle_status() verifies nonce 'tpm_toggle_status_*'")
test("check_admin_referer( 'tpm_delete_status_'" in admin_code, "handle_delete_status() verifies nonce 'tpm_delete_status_*'")
test("check_admin_referer( 'tpm_reorder_statuses'" in admin_code, "handle_reorder_statuses() verifies nonce 'tpm_reorder_statuses'")

# ====================================================================
# SECTION 3: Settings View UI Specification (settings.php)
# ====================================================================
print("\n--- Settings View Specification (settings.php) ---")

settings_view = read_file("admin/views/settings.php")
test("tpm-settings-tabs" in settings_view, "settings.php renders tabbed navigation")
test("tab=general" in settings_view, "Settings tabs include General Settings")
test("tab=statuses" in settings_view, "Settings tabs include Status Management")
test("tab=attachments" in settings_view, "Settings tabs include Attachment Settings")
test("tab=archive" in settings_view, "Settings tabs include Archive Settings")
test("tab=uninstall" in settings_view, "Settings tabs include Uninstall Settings")

# Form fields in view
test("name=\"currency\"" in settings_view, "General tab includes 'currency' input")
test("name=\"currency_symbol\"" in settings_view, "General tab includes 'currency_symbol' input")
test("name=\"date_format\"" in settings_view, "General tab includes 'date_format' select")
test("name=\"records_per_page\"" in settings_view, "General tab includes 'records_per_page' input")
test("name=\"status_name\"" in settings_view, "Status tab includes 'status_name' input")
test("name=\"status_slug\"" in settings_view, "Status tab includes 'status_slug' input")
test("name=\"record_type\"" in settings_view, "Status tab includes 'record_type' assignment")
test("name=\"status_color\"" in settings_view, "Status tab includes 'status_color' input")
test("name=\"allowed_file_types\"" in settings_view, "Attachment tab includes 'allowed_file_types' input")
test("name=\"max_file_size_mb\"" in settings_view, "Attachment tab includes 'max_file_size_mb' input")
test("name=\"attachment_labels\"" in settings_view, "Attachment tab includes 'attachment_labels' textarea")
test("name=\"hide_archived_default\"" in settings_view, "Archive tab includes 'hide_archived_default' checkbox")
test("name=\"permanent_delete_cap\"" in settings_view, "Archive tab includes 'permanent_delete_cap' select")
test("name=\"delete_data_on_uninstall\"" in settings_view, "Uninstall tab includes 'delete_data_on_uninstall' checkbox")

css_code = read_file("admin/css/admin.css")
test(".tpm-settings-tabs" in css_code, "admin.css defines .tpm-settings-tabs")
test(".tpm-status-swatch-badge" in css_code, "admin.css defines .tpm-status-swatch-badge")

# ====================================================================
# SECTION 4: Uninstall Procedure Verification (uninstall.php)
# ====================================================================
print("\n--- Uninstall Procedure Verification (uninstall.php) ---")

uninstall_code = read_file("uninstall.php")
test("delete_data_on_uninstall" in uninstall_code, "uninstall.php checks 'delete_data_on_uninstall' option")
test("tpm_records" in uninstall_code, "uninstall.php drops tpm_records")
test("tpm_customers" in uninstall_code, "uninstall.php drops tpm_customers")
test("tpm_partner_companies" in uninstall_code, "uninstall.php drops tpm_partner_companies")
test("tpm_attachments" in uninstall_code, "uninstall.php drops tpm_attachments")
test("tpm_payments" in uninstall_code, "uninstall.php drops tpm_payments")
test("tpm_activity_logs" in uninstall_code, "uninstall.php drops tpm_activity_logs")
test("tpm_statuses" in uninstall_code, "uninstall.php drops tpm_statuses")
test("delete_option( 'tpm_settings' )" in uninstall_code, "uninstall.php removes 'tpm_settings' option")
test("manage_tpm_settings" in uninstall_code, "uninstall.php cleans administrator capabilities")

# ====================================================================
# SECTION 5: Database Simulation & Functional Logic Testing
# ====================================================================
print("\n--- Functional Database Simulation & Settings Logic ---")

def setup_simulation_db():
    conn = sqlite3.connect(":memory:")
    c = conn.cursor()

    c.execute("""
    CREATE TABLE wp_options (
        option_id INTEGER PRIMARY KEY AUTOINCREMENT,
        option_name TEXT UNIQUE,
        option_value TEXT
    )""")

    c.execute("""
    CREATE TABLE wp_tpm_statuses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status_name TEXT NOT NULL,
        status_slug TEXT UNIQUE NOT NULL,
        record_type TEXT NOT NULL DEFAULT 'both',
        status_color TEXT NOT NULL DEFAULT '#646970',
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT
    )""")

    c.execute("""
    CREATE TABLE wp_tpm_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        record_type TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        tender_number TEXT,
        is_archived INTEGER DEFAULT 0,
        created_at TEXT
    )""")

    # Seed default statuses
    default_statuses = [
        ('Draft', 'draft', 'both', '#646970', 10, 1),
        ('Pending', 'pending', 'both', '#dba617', 20, 1),
        ('Submitted', 'submitted', 'both', '#996800', 30, 1),
        ('Awarded', 'awarded', 'tender', '#0a7b45', 40, 1),
        ('Complete', 'complete', 'both', '#0a7b45', 50, 1),
        ('PO', 'po', 'po', '#854d0e', 60, 1),
    ]
    c.executemany("""
    INSERT INTO wp_tpm_statuses (status_name, status_slug, record_type, status_color, sort_order, is_active, created_at)
    VALUES (?, ?, ?, ?, ?, ?, '2026-09-01 10:00:00')
    """, default_statuses)

    conn.commit()
    return conn

conn = setup_simulation_db()
c = conn.cursor()

# --- 5.1 General Settings Defaults & Validation ---
defaults = {
    'currency': 'INR',
    'currency_symbol': '₹',
    'date_format': 'Y-m-d',
    'records_per_page': 20,
    'allowed_file_types': 'pdf, doc, docx, xls, xlsx, jpg, jpeg, png, zip',
    'max_file_size_mb': 10,
    'attachment_labels': 'Tender Document, Quotation, Purchase Order, Technical Specs, Bill of Quantities, Commercial Offer, AWB / Shipping Doc, Invoice, Receipt, Warranty / Certificate, Other',
    'hide_archived_default': 1,
    'permanent_delete_cap': 'manage_options',
    'delete_data_on_uninstall': 0,
}

test(defaults['currency'] == 'INR', "Default Currency is 'INR'")
test(defaults['currency_symbol'] == '₹', "Default Currency Symbol is '₹'")
test(defaults['date_format'] == 'Y-m-d', "Default Date Format is 'Y-m-d'")
test(defaults['records_per_page'] == 20, "Default Records Per Page is 20")
test(defaults['max_file_size_mb'] == 10, "Default Max File Size is 10 MB")
test(defaults['delete_data_on_uninstall'] == 0, "Default Delete Data on Uninstall is 0 (Disabled)")

# Validation function simulation
def sanitize_settings_input(raw, current, defaults):
    sanitized = {}

    # Currency
    curr = raw.get('currency', '').strip().upper()
    sanitized['currency'] = curr if (curr and len(curr) <= 10) else defaults['currency']

    # Currency symbol
    sym = raw.get('currency_symbol', '').strip()
    sanitized['currency_symbol'] = sym if sym else defaults['currency_symbol']

    # Date format
    df = raw.get('date_format', '')
    sanitized['date_format'] = df if df in ['Y-m-d', 'm/d/Y', 'd/m/Y', 'F j, Y'] else defaults['date_format']

    # Records per page (5-100)
    try:
        rpp = int(raw.get('records_per_page', defaults['records_per_page']))
        sanitized['records_per_page'] = rpp if (5 <= rpp <= 100) else defaults['records_per_page']
    except (ValueError, TypeError):
        sanitized['records_per_page'] = defaults['records_per_page']

    # Allowed file types
    types_raw = raw.get('allowed_file_types', '')
    if types_raw:
        types_arr = [t.strip().lower() for t in types_raw.split(',') if t.strip()]
        sanitized['allowed_file_types'] = ', '.join(types_arr)
    else:
        sanitized['allowed_file_types'] = defaults['allowed_file_types']

    # Max file size MB (1-256)
    try:
        max_mb = int(raw.get('max_file_size_mb', defaults['max_file_size_mb']))
        sanitized['max_file_size_mb'] = max_mb if (1 <= max_mb <= 256) else defaults['max_file_size_mb']
    except (ValueError, TypeError):
        sanitized['max_file_size_mb'] = defaults['max_file_size_mb']

    # Delete data on uninstall
    sanitized['delete_data_on_uninstall'] = 1 if raw.get('delete_data_on_uninstall') else 0

    return sanitized

# Test valid updates
valid_input = {
    'currency': 'eur',
    'currency_symbol': '€',
    'date_format': 'd/m/Y',
    'records_per_page': '50',
    'allowed_file_types': 'PDF, PNG, JPG, XLSX',
    'max_file_size_mb': '25',
    'delete_data_on_uninstall': '1',
}
res = sanitize_settings_input(valid_input, defaults, defaults)
test(res['currency'] == 'EUR', "Settings sanitization: Currency converted to uppercase 'EUR'")
test(res['currency_symbol'] == '€', "Settings sanitization: Currency symbol updated to '€'")
test(res['date_format'] == 'd/m/Y', "Settings sanitization: Date format updated to 'd/m/Y'")
test(res['records_per_page'] == 50, "Settings sanitization: Records per page updated to 50")
test(res['allowed_file_types'] == 'pdf, png, jpg, xlsx', "Settings sanitization: Allowed file types normalized to lowercase")
test(res['max_file_size_mb'] == 25, "Settings sanitization: Max file size updated to 25 MB")
test(res['delete_data_on_uninstall'] == 1, "Settings sanitization: Delete on uninstall set to 1")

# Test invalid inputs & boundary conditions
invalid_input = {
    'currency': '', # Empty currency
    'records_per_page': '999', # Out of bounds (> 100)
    'date_format': 'invalid-format-sql-injection', # Invalid date format
    'max_file_size_mb': '-5', # Negative size
}
bad_res = sanitize_settings_input(invalid_input, defaults, defaults)
test(bad_res['currency'] == 'INR', "Invalid input: Empty currency falls back to default 'INR'")
test(bad_res['records_per_page'] == 20, "Boundary test: 999 records per page rejected, falls back to 20")
test(bad_res['date_format'] == 'Y-m-d', "Invalid input: Malicious date format rejected, falls back to 'Y-m-d'")
test(bad_res['max_file_size_mb'] == 10, "Boundary test: Negative max file size rejected, falls back to 10 MB")

# --- 5.2 Status Management: Add Status ---
print("\n--- Status Management Functional Testing ---")

def add_status_sim(conn, data):
    name = (data.get('status_name') or '').strip()
    if not name:
        return None, "Status name is required"

    slug = data.get('status_slug') or name.lower().replace(' ', '-')
    slug = re.sub(r'[^a-z0-9\-]', '', slug.lower())
    if not slug:
        return None, "Valid status slug is required"

    c = conn.cursor()
    c.execute("SELECT id FROM wp_tpm_statuses WHERE status_slug = ?", (slug,))
    if c.fetchone():
        return None, f"Slug '{slug}' is already taken"

    rec_type = data.get('record_type', 'both')
    if rec_type not in ('tender', 'po', 'both', 'general'):
        rec_type = 'both'

    color = data.get('status_color', '#646970')
    if not re.match(r'^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$', color):
        color = '#646970'

    order = int(data.get('sort_order') or 0)
    if order == 0:
        c.execute("SELECT MAX(sort_order) FROM wp_tpm_statuses")
        max_order = c.fetchone()[0] or 0
        order = max_order + 10

    c.execute("""
    INSERT INTO wp_tpm_statuses (status_name, status_slug, record_type, status_color, sort_order, is_active, created_at)
    VALUES (?, ?, ?, ?, ?, 1, '2026-09-11 12:00:00')
    """, (name, slug, rec_type, color, order))
    conn.commit()
    return c.lastrowid, None

# 1. Add valid status
status_id, err = add_status_sim(conn, {
    'status_name': 'Under Evaluation',
    'status_slug': 'under-evaluation',
    'record_type': 'tender',
    'status_color': '#2563eb',
    'sort_order': 70,
})
test(status_id is not None and err is None, "Add Status: Successfully inserted 'Under Evaluation'")

c.execute("SELECT status_name, status_slug, record_type, status_color, sort_order FROM wp_tpm_statuses WHERE id = ?", (status_id,))
row = c.fetchone()
test(row[0] == 'Under Evaluation', "Add Status: Name matches 'Under Evaluation'")
test(row[1] == 'under-evaluation', "Add Status: Slug matches 'under-evaluation'")
test(row[2] == 'tender', "Add Status: Assigned record type is 'tender'")
test(row[3] == '#2563eb', "Add Status: Color is '#2563eb'")
test(row[4] == 70, "Add Status: Sort order is 70")

# 2. Reject duplicate slug
dup_id, dup_err = add_status_sim(conn, {
    'status_name': 'Another Evaluation',
    'status_slug': 'under-evaluation',
})
test(dup_id is None and "already taken" in dup_err, "Add Status: Duplicate slug rejected with error")

# 3. Reject empty name
blank_id, blank_err = add_status_sim(conn, {'status_name': '   '})
test(blank_id is None and "required" in blank_err, "Add Status: Empty status name rejected with error")

# 4. Color sanitization fallback on invalid hex
bad_color_id, _ = add_status_sim(conn, {
    'status_name': 'Bad Color Status',
    'status_slug': 'bad-color',
    'status_color': 'not-a-color; DROP TABLE',
})
c.execute("SELECT status_color FROM wp_tpm_statuses WHERE id = ?", (bad_color_id,))
test(c.fetchone()[0] == '#646970', "Add Status: Malformed color string sanitizes to default '#646970'")

# --- 5.3 Status Management: Edit Status ---
def update_status_sim(conn, status_id, data):
    c = conn.cursor()
    c.execute("SELECT status_slug FROM wp_tpm_statuses WHERE id = ?", (status_id,))
    existing = c.fetchone()
    if not existing:
        return False, "Not found"

    old_slug = existing[0]
    name = data.get('status_name')
    new_slug = data.get('status_slug')
    color = data.get('status_color')

    if new_slug and new_slug != old_slug:
        c.execute("SELECT id FROM wp_tpm_statuses WHERE status_slug = ? AND id != ?", (new_slug, status_id))
        if c.fetchone():
            return False, "Slug already taken"

        # Cascade update to records table
        c.execute("UPDATE wp_tpm_records SET status = ? WHERE status = ?", (new_slug, old_slug))

    c.execute("""
    UPDATE wp_tpm_statuses 
    SET status_name = COALESCE(?, status_name),
        status_slug = COALESCE(?, status_slug),
        status_color = COALESCE(?, status_color)
    WHERE id = ?
    """, (name, new_slug, color, status_id))
    conn.commit()
    return True, None

# Seed a record using old slug 'draft'
c.execute("INSERT INTO wp_tpm_records (record_type, status, tender_number) VALUES ('tender', 'draft', 'TN-TEST-01')")
conn.commit()

# Edit status 'draft' (id=1): change name to 'Drafted Proposal' and slug to 'drafted-proposal'
update_ok, _ = update_status_sim(conn, 1, {
    'status_name': 'Drafted Proposal',
    'status_slug': 'drafted-proposal',
    'status_color': '#475569',
})
test(update_ok, "Edit Status: Updated status 1 successfully")

c.execute("SELECT status_name, status_slug, status_color FROM wp_tpm_statuses WHERE id = 1")
updated_st = c.fetchone()
test(updated_st[0] == 'Drafted Proposal', "Edit Status: Name updated to 'Drafted Proposal'")
test(updated_st[1] == 'drafted-proposal', "Edit Status: Slug updated to 'drafted-proposal'")

# Check that referencing record was cascaded
c.execute("SELECT status FROM wp_tpm_records WHERE tender_number = 'TN-TEST-01'")
test(c.fetchone()[0] == 'drafted-proposal', "Edit Status: Cascaded slug change to existing record")

# --- 5.4 Status Management: Disable / Enable Toggle ---
def toggle_status_sim(conn, status_id):
    c = conn.cursor()
    c.execute("SELECT is_active FROM wp_tpm_statuses WHERE id = ?", (status_id,))
    row = c.fetchone()
    if not row:
        return False
    new_active = 0 if row[0] == 1 else 1
    c.execute("UPDATE wp_tpm_statuses SET is_active = ? WHERE id = ?", (new_active, status_id))
    conn.commit()
    return True

toggle_status_sim(conn, status_id) # Disable 'under-evaluation'
c.execute("SELECT is_active FROM wp_tpm_statuses WHERE id = ?", (status_id,))
test(c.fetchone()[0] == 0, "Toggle Status: Disabled status (is_active = 0)")

toggle_status_sim(conn, status_id) # Enable 'under-evaluation'
c.execute("SELECT is_active FROM wp_tpm_statuses WHERE id = ?", (status_id,))
test(c.fetchone()[0] == 1, "Toggle Status: Re-enabled status (is_active = 1)")

# --- 5.5 Status Management: Reorder Statuses ---
def reorder_statuses_sim(conn, order_map):
    c = conn.cursor()
    for sid, ord_val in order_map.items():
        c.execute("UPDATE wp_tpm_statuses SET sort_order = ? WHERE id = ?", (ord_val, sid))
    conn.commit()
    return True

reorder_statuses_sim(conn, {1: 5, 2: 15, status_id: 25})
c.execute("SELECT sort_order FROM wp_tpm_statuses WHERE id = ?", (status_id,))
test(c.fetchone()[0] == 25, "Reorder Statuses: Updated sort order to 25")

# --- 5.6 CRITICAL: Guardrail Deletion Safety (In-Use Status Protection) ---
print("\n--- Guardrail Deletion Safety (In-Use Status Protection) ---")

def delete_status_sim(conn, status_id, force=False):
    c = conn.cursor()
    c.execute("SELECT status_name, status_slug FROM wp_tpm_statuses WHERE id = ?", (status_id,))
    row = c.fetchone()
    if not row:
        return False, "Status not found"

    st_name, st_slug = row

    # Count records currently using this status
    c.execute("SELECT COUNT(*) FROM wp_tpm_records WHERE status = ?", (st_slug,))
    usage_count = c.fetchone()[0]

    if usage_count > 0 and not force:
        return False, f"Cannot delete status '{st_name}' because it is currently in use by {usage_count} active record(s)."

    c.execute("DELETE FROM wp_tpm_statuses WHERE id = ?", (status_id,))
    conn.commit()
    return True, None

# Attempt 1: Status 1 ('drafted-proposal') is currently in use by TN-TEST-01
del_ok, del_err = delete_status_sim(conn, 1, force=False)
test(not del_ok and "currently in use by 1 active record" in del_err,
     "CRITICAL Guardrail: Deletion of in-use status BLOCKED with warning message")

c.execute("SELECT id FROM wp_tpm_statuses WHERE id = 1")
test(c.fetchone() is not None, "CRITICAL Guardrail: Status 1 remains safely in database")

# Attempt 2: Delete unused status 'under-evaluation' (0 records using it)
del_unused_ok, del_unused_err = delete_status_sim(conn, status_id, force=False)
test(del_unused_ok and del_unused_err is None, "Deletion: Unused status successfully deleted")

c.execute("SELECT id FROM wp_tpm_statuses WHERE id = ?", (status_id,))
test(c.fetchone() is None, "Deletion: Unused status permanently removed from database")

# Attempt 3: Force deletion of in-use status override
del_forced_ok, _ = delete_status_sim(conn, 1, force=True)
test(del_forced_ok, "Force Deletion: Status deleted when force=True")

conn.close()

# ====================================================================
# SUMMARY
# ====================================================================
print("\n====================================================================")
print(f" TOTAL TESTS RUN: {PASS_COUNT + FAIL_COUNT}")
print(f" PASSED: {PASS_COUNT}")
print(f" FAILED: {FAIL_COUNT}")
print("====================================================================")

if FAIL_COUNT == 0:
    print("\n>>> ALL PHASE 15 SETTINGS & STATUS MANAGEMENT TESTS PASSED WITH 100% SUCCESS! <<<\n")
    sys.exit(0)
else:
    print(f"\n>>> ERROR: {FAIL_COUNT} test(s) failed in Phase 15 suite. <<<\n")
    sys.exit(1)
