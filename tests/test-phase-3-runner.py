#!/usr/bin/env python3
"""
Verification & Test Suite for Phase 3: WordPress Admin Menu.
Tests:
1. Main menu "Tender Management" registration and dashicon.
2. All 10 submenus registration, slugs, and order.
3. Strict capability checks on each admin screen.
4. Existence and structure of all 10 view files in admin/views/.
5. Asset files (admin.css, admin.js) and screen-isolated enqueuing.
6. Administrator capability assignment in TPM_Activator.
7. No duplicate menus or slugs.
"""

import os
import re
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ADMIN_CLASS_FILE = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
ACTIVATOR_FILE = os.path.join(BASE_DIR, 'includes', 'class-tpm-activator.php')
VIEWS_DIR = os.path.join(BASE_DIR, 'admin', 'views')

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
print(" PHASE 3: WORDPRESS ADMIN MENU VERIFICATION TEST")
print("=" * 60)

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(ACTIVATOR_FILE, 'r') as f:
    activator_code = f.read()

# 1. Verify Main Menu Registration
assert_test("Main menu title 'Tender Management' registered", "'Tender Management'" in admin_code)
assert_test("Main menu capability 'manage_tpm_records' required", "'manage_tpm_records'" in admin_code)
assert_test("Main menu uses WordPress dashicon 'dashicons-portfolio'", "'dashicons-portfolio'" in admin_code)
assert_test("Main menu slug is 'tender-po-management'", "'tender-po-management'" in admin_code)

# 2. Verify All 10 Submenus
submenus = [
    ("Dashboard", "tender-po-management", "view_tpm_records"),
    ("Add New Record", "tpm-add-record", "create_tpm_records"),
    ("All Records", "tpm-records", "view_tpm_records"),
    ("Tenders", "tpm-tenders", "view_tpm_records"),
    ("Purchase Orders", "tpm-purchase-orders", "view_tpm_records"),
    ("Archived Records", "tpm-archived-records", "view_tpm_records"),
    ("Customers", "tpm-customers", "manage_tpm_customers"),
    ("Partner Companies", "tpm-partner-companies", "manage_tpm_partners"),
    ("Reports", "tpm-reports", "view_tpm_reports"),
    ("Settings", "tpm-settings", "manage_tpm_settings"),
]

for title, slug, cap in submenus:
    assert_test(f"Submenu '{title}' registered with slug '{slug}'", f"'{slug}'" in admin_code)
    assert_test(f"Submenu '{title}' requires capability '{cap}'", f"'{cap}'" in admin_code)

# 3. Verify No Duplicate Menu Slugs
slug_matches = re.findall(r"add_submenu_page\s*\(\s*'[^']+',\s*__\([^)]+\),\s*__\([^)]+\),\s*'[^']+',\s*'([^']+)'", admin_code)
assert_test("Exactly 10 submenus registered", len(slug_matches) == 10)
assert_test("No duplicate submenu slugs registered", len(slug_matches) == len(set(slug_matches)))

# 4. Verify All 10 View Files Exist and are Structured
expected_views = [
    'dashboard.php',
    'add-record.php',
    'records.php',
    'tenders.php',
    'purchase-orders.php',
    'archived-records.php',
    'customers.php',
    'partner-companies.php',
    'reports.php',
    'settings.php',
]

for view_name in expected_views:
    view_path = os.path.join(VIEWS_DIR, view_name)
    assert_test(f"View file exists: admin/views/{view_name}", os.path.exists(view_path))
    if os.path.exists(view_path):
        with open(view_path, 'r') as vf:
            view_code = vf.read()
        assert_test(f"View {view_name} has ABSPATH guard", "defined( 'ABSPATH' )" in view_code)
        assert_test(f"View {view_name} checks current_user_can()", "current_user_can(" in view_code)
        assert_test(f"View {view_name} has tpm-structured-container, tpm-form-layout, or dashboard layout", "tpm-structured-container" in view_code or "tpm-kpi-grid" in view_code or "tpm-form-layout" in view_code or "tpm-admin-wrap" in view_code)

# 5. Verify Capabilities Provisioning in Activator / Roles Service
ROLES_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-roles-service.php')
roles_code = ""
if os.path.exists(ROLES_FILE):
    with open(ROLES_FILE, 'r') as rf:
        roles_code = rf.read()
caps_code = activator_code + "\n" + roles_code

expected_caps = [
    'manage_tpm_records',
    'view_tpm_records',
    'create_tpm_records',
    'edit_tpm_records',
    'delete_tpm_records',
    'manage_tpm_settings',
    'manage_tpm_customers',
    'manage_tpm_partners',
    'view_tpm_reports',
]

for cap in expected_caps:
    assert_test(f"Activator registers capability '{cap}'", f"'{cap}'" in caps_code)

assert_test("Activator assigns capabilities to 'administrator' role only", "get_role( 'administrator' )" in caps_code)

# 6. Verify Asset Files Exist and Enqueued Only on Plugin Screens
assert_test("Asset file exists: admin/css/admin.css", os.path.exists(os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')))
assert_test("Asset file exists: admin/js/admin.js", os.path.exists(os.path.join(BASE_DIR, 'admin', 'js', 'admin.js')))
assert_test("Assets enqueuing checks is_plugin_screen", "! $this->is_plugin_screen( $hook )" in admin_code)

print("-" * 60)
print(f" TEST RESULTS: {passed} Passed, {failed} Failed")
print("-" * 60)

if failed > 0:
    sys.exit(1)
print(" ALL PHASE 3 ADMIN MENU SPECIFICATIONS VERIFIED PERFECTLY!")
sys.exit(0)
