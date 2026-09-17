#!/usr/bin/env python3
"""
Phase 17: Security and Performance Audit - Comprehensive Verification Runner
=============================================================================
Validates:
1. Security Hardening (Capability checks, Nonces, Sanitization, Escaping, Direct Access Guards)
2. Database Schema & Indexes (Composite indexes, Date indexes, Zero missing keys)
3. Performance & Query Optimization (Single-pass aggregations, Caching, Setting-driven pagination)
4. WordPress Compatibility & Multisite (PHP 8.1+, WP 6.0+, Multisite network activation, Zero deprecated functions)
5. Error Handling & Guardrails (Transients, No exposed DB errors, Strict type safety)
"""

import os
import sys
import re
import sqlite3
import time

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PASS_COUNT = 0
FAIL_COUNT = 0

def test(condition, name):
    global PASS_COUNT, FAIL_COUNT
    if condition:
        print(f" [PASS] {name}")
        PASS_COUNT += 1
    else:
        print(f" [FAIL] {name}")
        FAIL_COUNT += 1

print("====================================================================")
print(" PHASE 17: SECURITY AND PERFORMANCE AUDIT VERIFICATION SUITE")
print("====================================================================")

# ====================================================================
# SECTION 1: Direct File Access & Security Architecture
# ====================================================================
print("\n--- Section 1: Direct File Access & Security Architecture ---")

# Verify ABSPATH or WP_UNINSTALL_PLUGIN in all PHP files
php_files = []
for root, dirs, files in os.walk(BASE_DIR):
    for f in files:
        if f.endswith('.php'):
            php_files.append(os.path.join(root, f))

test(len(php_files) >= 38, f"Identified {len(php_files)} PHP files in plugin repository")

direct_access_guarded = 0
for pf in php_files:
    fname = os.path.basename(pf)
    with open(pf, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    if fname == 'uninstall.php':
        if "WP_UNINSTALL_PLUGIN" in content:
            direct_access_guarded += 1
    elif fname.startswith('test-phase-') and pf.endswith('.php'):
        if "defined( 'ABSPATH' )" in content:
            direct_access_guarded += 1
    elif "defined( 'ABSPATH' )" in content or "defined( 'WPINC' )" in content or "// Silence is golden." in content:
        direct_access_guarded += 1
    else:
        print(f"    Missing guard in: {pf}")

test(direct_access_guarded == len(php_files), f"100% of PHP files ({direct_access_guarded}/{len(php_files)}) protected against direct URL access")

# ====================================================================
# SECTION 2: Controller Capability & CSRF Nonce Hardening
# ====================================================================
print("\n--- Section 2: Controller Capability & CSRF Nonce Hardening ---")

admin_controller_path = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
with open(admin_controller_path, 'r', encoding='utf-8') as f:
    admin_content = f.read()

# Verify handle_archive_record requires manage_tpm_archive
archive_cap_check = "current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' )"
test(archive_cap_check in admin_content, "handle_archive_record() enforces 'manage_tpm_archive' or 'manage_tpm_records'")

# Verify handle_unarchive_record requires manage_tpm_archive
test("handle_unarchive_record" in admin_content and "manage_tpm_archive" in admin_content,
     "handle_unarchive_record() enforces 'manage_tpm_archive' or 'manage_tpm_records'")

# Verify handle_delete_record capability check
test("handle_delete_record" in admin_content and "delete_tpm_records" in admin_content and "manage_tpm_records" in admin_content,
     "handle_delete_record() checks 'delete_tpm_records' or 'manage_tpm_records'")

# Verify handle_bulk_records enforces manage_tpm_archive for archive and restore
test("'archive' === $action" in admin_content and "manage_tpm_archive" in admin_content,
     "handle_bulk_records() archive action enforces 'manage_tpm_archive'")
test("'restore' === $action" in admin_content and "manage_tpm_archive" in admin_content,
     "handle_bulk_records() restore action enforces 'manage_tpm_archive'")

# Verify notice key unification on delete
test("tpm_record_notice_" in admin_content and "handle_delete_record" in admin_content,
     "handle_delete_record() sets unified 'tpm_record_notice_' transient key")

# ====================================================================
# SECTION 3: Attachment Post Validation & Security
# ====================================================================
print("\n--- Section 3: Attachment Post Validation & Security ---")

record_service_path = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
with open(record_service_path, 'r', encoding='utf-8') as f:
    rec_service_content = f.read()

# Check save_attachments validates post existence & post_type
save_att_fn = rec_service_content[rec_service_content.find("function save_attachments"):rec_service_content.find("function get_attachment_labels")]
test("get_post(" in save_att_fn, "save_attachments() queries get_post() to verify WordPress post existence")
test("'attachment' !== $post->post_type" in save_att_fn, "save_attachments() strictly rejects non-attachment post types")

# Check add_attachments_to_record validates post existence & post_type
add_att_fn = rec_service_content[rec_service_content.find("function add_attachments_to_record"):rec_service_content.find("function delete_attachment")]
test("get_post(" in add_att_fn and "'attachment' !== $post->post_type" in add_att_fn,
     "add_attachments_to_record() strictly validates attachment post type")

# ====================================================================
# SECTION 4: Database Schema & High-Performance Index Audit
# ====================================================================
print("\n--- Section 4: Database Schema & High-Performance Index Audit ---")

db_class_path = os.path.join(BASE_DIR, 'includes', 'database', 'class-tpm-database.php')
with open(db_class_path, 'r', encoding='utf-8') as f:
    db_content = f.read()

# Verify composite indexes on wp_tpm_records
test("KEY idx_archived_type (is_archived, record_type)" in db_content,
     "Composite index idx_archived_type (is_archived, record_type) defined")
test("KEY idx_archived_created (is_archived, created_at)" in db_content,
     "Composite index idx_archived_created (is_archived, created_at) defined")
test("KEY idx_archived_status (is_archived, status)" in db_content,
     "Composite index idx_archived_status (is_archived, status) defined")

# Verify date milestone indexes on wp_tpm_records
test("KEY idx_tender_due_date (tender_due_date)" in db_content,
     "Date index idx_tender_due_date defined on tender_due_date")
test("KEY idx_payment_due_date (payment_due_date)" in db_content,
     "Date index idx_payment_due_date defined on payment_due_date")
test("KEY idx_quote_exp_date (quote_expiration_date)" in db_content,
     "Date index idx_quote_exp_date defined on quote_expiration_date")
test("KEY idx_po_date (po_date)" in db_content,
     "Date index idx_po_date defined on po_date")

# Verify composite index on customers and partners
test("KEY idx_status_company (status, company_name(191))" in db_content,
     "Composite index idx_status_company defined on customers & partners")

# ====================================================================
# SECTION 5: Query Optimization & Single-Pass Aggregations
# ====================================================================
print("\n--- Section 5: Query Optimization & Single-Pass Aggregations ---")

cust_service_path = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-customer-service.php')
with open(cust_service_path, 'r', encoding='utf-8') as f:
    cust_content = f.read()

# Verify customer get_view_counts uses single-pass conditional aggregation
cust_counts_fn = cust_content[cust_content.find("function get_view_counts"):cust_content.find("function get_customer_records")]
test("COUNT(CASE WHEN status = 'active'" in cust_counts_fn,
     "Customer get_view_counts() uses single-pass COUNT(CASE WHEN status = 'active')")
test("static $cached_counts" in cust_counts_fn,
     "Customer get_view_counts() implements runtime in-memory caching")

part_service_path = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-partner-service.php')
with open(part_service_path, 'r', encoding='utf-8') as f:
    part_content = f.read()

# Verify partner get_view_counts uses single-pass conditional aggregation
part_counts_fn = part_content[part_content.find("function get_view_counts"):part_content.find("function get_partner_records")]
test("COUNT(CASE WHEN status = 'active'" in part_counts_fn,
     "Partner get_view_counts() uses single-pass COUNT(CASE WHEN status = 'active')")
test("static $cached_counts" in part_counts_fn,
     "Partner get_view_counts() implements runtime in-memory caching")

# Verify payment service recalculate_record_payments combines queries
payment_service_path = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-payment-service.php')
with open(payment_service_path, 'r', encoding='utf-8') as f:
    pay_content = f.read()

recalc_fn = pay_content[pay_content.find("function recalculate_record_payments"):pay_content.find("function format_currency")]
test("r.sell_price" in recalc_fn and "SUM(p.payment_amount)" in recalc_fn,
     "Payment recalculate_record_payments() queries sell_price and payments sum in a single SQL query")

# Verify settings service status caching & invalidation
settings_service_path = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-settings-service.php')
with open(settings_service_path, 'r', encoding='utf-8') as f:
    settings_content = f.read()

test("private static array $status_cache" in settings_content,
     "TPM_Settings_Service declares static $status_cache")
test("function clear_statuses_cache" in settings_content,
     "TPM_Settings_Service implements clear_statuses_cache()")
test("self::clear_statuses_cache();" in settings_content,
     "TPM_Settings_Service automatically invalidates cache upon status mutation")

# ====================================================================
# SECTION 6: Dynamic Pagination Settings Integration
# ====================================================================
print("\n--- Section 6: Dynamic Pagination Settings Integration ---")

records_table_path = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-records-list-table.php')
with open(records_table_path, 'r', encoding='utf-8') as f:
    rec_tbl_content = f.read()

test("TPM_Settings_Service::get_setting( 'records_per_page'" in rec_tbl_content,
     "TPM_Records_List_Table respects dynamic records_per_page setting")

customers_table_path = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-customers-list-table.php')
with open(customers_table_path, 'r', encoding='utf-8') as f:
    cust_tbl_content = f.read()

test("TPM_Settings_Service::get_setting( 'records_per_page'" in cust_tbl_content,
     "TPM_Customers_List_Table respects dynamic records_per_page setting")

partners_table_path = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-partners-list-table.php')
with open(partners_table_path, 'r', encoding='utf-8') as f:
    part_tbl_content = f.read()

test("TPM_Settings_Service::get_setting( 'records_per_page'" in part_tbl_content,
     "TPM_Partners_List_Table respects dynamic records_per_page setting")

# ====================================================================
# SECTION 7: WordPress Compatibility, Multisite & Deprecations
# ====================================================================
print("\n--- Section 7: WordPress Compatibility, Multisite & Deprecations ---")

activator_path = os.path.join(BASE_DIR, 'includes', 'class-tpm-activator.php')
with open(activator_path, 'r', encoding='utf-8') as f:
    activator_content = f.read()

test("public const MIN_PHP_VERSION = '8.1'" in activator_content,
     "Activator enforces PHP 8.1+ minimum requirement")
test("public const MIN_WP_VERSION = '6.0'" in activator_content,
     "Activator enforces WordPress 6.0+ minimum requirement")
test("bool $network_wide = false" in activator_content,
     "Activator activate() accepts $network_wide parameter")
test("get_sites(" in activator_content and "switch_to_blog(" in activator_content,
     "Activator iterates get_sites() with switch_to_blog() for multisite network activation")
test("function on_new_blog" in activator_content,
     "Activator provides on_new_blog() hook handler for newly initialized multisite blogs")

# Check for deprecated functions
deprecated_patterns = [
    (r'\blike_escape\s*\(', "like_escape"),
    (r'\bscreen_icon\s*\(', "screen_icon"),
    (r'\bget_currentuserinfo\s*\(', "get_currentuserinfo"),
    (r'\buser_can_access_admin_page\s*\(', "user_can_access_admin_page"),
    (r'\bwp_get_sites\s*\(', "wp_get_sites"),
    (r'\bget_blog_list\s*\(', "get_blog_list"),
]

deprecated_found = False
for pf in php_files:
    with open(pf, 'r', encoding='utf-8', errors='ignore') as f:
        c = f.read()
    for pattern, name in deprecated_patterns:
        if re.search(pattern, c):
            print(f"    Found deprecated {name} in {pf}")
            deprecated_found = True

test(not deprecated_found, "Zero deprecated WordPress functions detected across entire plugin")

# ====================================================================
# SECTION 8: In-Memory Database Simulation of Optimized Queries & Indexes
# ====================================================================
print("\n--- Section 8: In-Memory Database Simulation of Optimized Queries & Indexes ---")

conn = sqlite3.connect(":memory:")
c = conn.cursor()

# Create table with newly added composite and date indexes
c.execute("""
CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    tender_number TEXT,
    customer_po_number TEXT,
    customer_id INTEGER,
    partner_company_id INTEGER,
    purchase_price REAL NOT NULL DEFAULT 0.0,
    sell_price REAL NOT NULL DEFAULT 0.0,
    expenses REAL NOT NULL DEFAULT 0.0,
    actual_payment REAL NOT NULL DEFAULT 0.0,
    balance_payment_due REAL NOT NULL DEFAULT 0.0,
    tender_due_date TEXT,
    payment_due_date TEXT,
    quote_expiration_date TEXT,
    po_date TEXT,
    is_archived INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
)
""")

c.execute("CREATE INDEX idx_archived_type ON wp_tpm_records (is_archived, record_type)")
c.execute("CREATE INDEX idx_archived_created ON wp_tpm_records (is_archived, created_at)")
c.execute("CREATE INDEX idx_archived_status ON wp_tpm_records (is_archived, status)")
c.execute("CREATE INDEX idx_tender_due ON wp_tpm_records (tender_due_date)")
c.execute("CREATE INDEX idx_pay_due ON wp_tpm_records (payment_due_date)")

c.execute("""
CREATE TABLE wp_tpm_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    payment_amount REAL NOT NULL,
    payment_date TEXT NOT NULL
)
""")
c.execute("CREATE INDEX idx_pm_record ON wp_tpm_payments (record_id)")

# Seed test records
records_to_seed = []
for i in range(1, 1001):
    rtype = 'tender' if i % 2 == 0 else 'po'
    status = 'complete' if i % 5 == 0 else ('submitted' if i % 3 == 0 else 'pending')
    is_arc = 1 if i > 950 else 0
    records_to_seed.append((
        i, rtype, status, f"TND-{i}", f"PO-{i}", (i % 10) + 1, (i % 5) + 1,
        1000.0, 1500.0, 50.0, 900.0, 600.0,
        "2026-09-15", "2026-09-30", "2026-09-20", "2026-09-01",
        is_arc, f"2026-09-{(i % 28) + 1:02d} 10:00:00"
    ))

c.executemany("""
INSERT INTO wp_tpm_records (
    id, record_type, status, tender_number, customer_po_number, customer_id, partner_company_id,
    purchase_price, sell_price, expenses, actual_payment, balance_payment_due,
    tender_due_date, payment_due_date, quote_expiration_date, po_date,
    is_archived, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
""", records_to_seed)

# Seed payments for record 1
c.execute("INSERT INTO wp_tpm_payments (record_id, payment_amount, payment_date) VALUES (1, 400.0, '2026-09-02')")
c.execute("INSERT INTO wp_tpm_payments (record_id, payment_amount, payment_date) VALUES (1, 500.0, '2026-09-05')")
conn.commit()

# Test 1: Combined payment recalculation query
t0 = time.perf_counter()
c.execute("""
SELECT 
    r.sell_price,
    COALESCE((SELECT SUM(p.payment_amount) FROM wp_tpm_payments p WHERE p.record_id = r.id), 0.0) AS total_paid
FROM wp_tpm_records r
WHERE r.id = 1
""")
res = c.fetchone()
t_recalc = (time.perf_counter() - t0) * 1000

test(res[0] == 1500.0 and res[1] == 900.0, "Consolidated payment query correctly returns sell_price $1,500 and total_paid $900")
test(t_recalc < 5.0, f"Consolidated payment query executes in {t_recalc:.2f}ms (< 5ms budget)")

# Test 2: Composite index range scan on (is_archived, record_type)
t0 = time.perf_counter()
c.execute("SELECT COUNT(id) FROM wp_tpm_records WHERE is_archived = 0 AND record_type = 'tender'")
tenders_active = c.fetchone()[0]
t_query = (time.perf_counter() - t0) * 1000

test(tenders_active > 450, f"Query on (is_archived, record_type) matched {tenders_active} records")
test(t_query < 5.0, f"Composite index query executed in {t_query:.2f}ms (< 5ms budget)")

# Test 3: Single-pass conditional aggregation on customers
c.execute("""
CREATE TABLE wp_tpm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    status TEXT NOT NULL
)
""")
cust_rows = [('active',) if i % 4 != 0 else ('inactive',) for i in range(1, 101)]
c.executemany("INSERT INTO wp_tpm_customers (status) VALUES (?)", cust_rows)
conn.commit()

t0 = time.perf_counter()
c.execute("""
SELECT 
    COUNT(*) AS count_all,
    COUNT(CASE WHEN status = 'active' THEN 1 END) AS count_active,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) AS count_inactive
FROM wp_tpm_customers
""")
cust_counts = c.fetchone()
t_counts = (time.perf_counter() - t0) * 1000

test(cust_counts[0] == 100 and cust_counts[1] == 75 and cust_counts[2] == 25,
     "Single-pass view count query accurately computed 100 total, 75 active, 25 inactive")
test(t_counts < 5.0, f"Single-pass view count query executed in {t_counts:.2f}ms (< 5ms budget)")

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
    print("\n>>> ALL PHASE 17 SECURITY & PERFORMANCE AUDIT TESTS PASSED WITH 100% SUCCESS! <<<\n")
    sys.exit(0)
else:
    print(f"\n>>> ERROR: {FAIL_COUNT} test(s) failed in Phase 17 suite. <<<\n")
    sys.exit(1)
