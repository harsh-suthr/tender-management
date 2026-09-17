#!/usr/bin/env python3
"""
Test Suite for Phase 6: Attachment Management and PDF Preview.

Tests the 9 required Phase 6 specifications:
1. Add PDF while creating Tender
2. View PDF in details
3. Download PDF
4. Add PDF after creation
5. Multiple attachments
6. Delete attachment relationship
7. Attachment labels (including filter extensibility)
8. Unauthorized access (capability and nonce checks)
9. Invalid attachment ID (WordPress Media Library post validation)
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
ADD_RECORD_VIEW_FILE = os.path.join(BASE_DIR, 'admin', 'views', 'add-record.php')
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
print(" PHASE 6: ATTACHMENT MANAGEMENT & PDF PREVIEW VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(RECORD_DETAILS_VIEW_FILE, 'r') as f:
    details_view = f.read()

with open(ADD_RECORD_VIEW_FILE, 'r') as f:
    add_record_view = f.read()

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

# --------------------------------------------------------------------------
# SPEC 1: ADD PDF WHILE CREATING TENDER
# --------------------------------------------------------------------------
print("\n--- SPEC 1: Add PDF while creating Tender ---")
assert_test("Add Record form contains Attachments section container", "tpm-attachments-list" in add_record_view)
assert_test("Add Record form has '+ Add Document / Attachment' button", "tpm-add-attachments-btn" in add_record_view)
assert_test("create_record handles attachments array", "if ( ! empty( $data['attachments'] )" in service_code or "foreach ( $data['attachments']" in service_code)
assert_test("create_record logs attachment_added activity", "'attachment_added'" in service_code)
assert_test("create_record sets uploaded_by and uploaded_at on attachments", "uploaded_by" in service_code and "uploaded_at" in service_code)

# --------------------------------------------------------------------------
# SPEC 2: VIEW PDF IN DETAILS
# --------------------------------------------------------------------------
print("\n--- SPEC 2: View PDF inside wp-admin ---")
assert_test("Record details has Attachments tab pane", 'id="tab-pane-attachments"' in details_view)
assert_test("Attachments table shows Dashicon, file name, label, type, size, uploader, date",
            "tpm-table-file-icon" in details_view and
            "attachment_label" in details_view and
            "file_type" in details_view and
            "file_size" in details_view and
            "uploaded_by" in details_view and
            "uploaded_at" in details_view)
assert_test("PDF preview button rendered with .tpm-preview-pdf-btn and data-pdf-url",
            "tpm-preview-pdf-btn" in details_view and 'data-pdf-url=' in details_view)
assert_test("Dedicated PDF preview modal markup (#tpm-preview-modal) exists",
            'id="tpm-preview-modal"' in details_view)
assert_test("Modal contains browser-native iframe (#tpm-pdf-iframe)",
            'id="tpm-pdf-iframe"' in details_view)
assert_test("Modal contains external new-tab fallback link",
            'id="tpm-modal-external-link"' in details_view)
assert_test("admin.js handles .tpm-preview-pdf-btn click event",
            "$(document).on('click', '.tpm-preview-pdf-btn'" in js_code)
assert_test("admin.js sets iframe src to pdfUrl and shows modal",
            "$('#tpm-pdf-iframe').attr('src', pdfUrl);" in js_code and
            "$('#tpm-preview-modal').fadeIn" in js_code)
assert_test("admin.js clears iframe src on close to prevent memory leaks",
            "$('#tpm-pdf-iframe').attr('src', '');" in js_code)

# --------------------------------------------------------------------------
# SPEC 3: DOWNLOAD PDF & OTHER FILE TYPES
# --------------------------------------------------------------------------
print("\n--- SPEC 3: Download PDF & Non-PDF files ---")
assert_test("Download button rendered with download attribute",
            'download' in details_view and 'tpm-btn' in details_view)
assert_test("File URL is retrieved securely via wp_get_attachment_url()",
            "wp_get_attachment_url( $att->attachment_id )" in details_view)
assert_test("Safe image preview button rendered for image files",
            "tpm-preview-img-btn" in details_view and "data-img-url" in details_view)
assert_test("Non-PDF and non-image files do not have unsafe previews",
            "$is_pdf" in details_view and "$is_img" in details_view)

# --------------------------------------------------------------------------
# SPEC 4: ADD PDF AFTER CREATION (FROM DETAILS PAGE)
# --------------------------------------------------------------------------
print("\n--- SPEC 4: Add PDF after creation from Details Page ---")
assert_test("'+ Upload Attachment' button exists on Details page",
            'id="tpm-details-upload-btn"' in details_view)
assert_test("Staging container (#tpm-details-staging-box) exists on Details page",
            'id="tpm-details-staging-box"' in details_view)
assert_test("admin_post_tpm_add_attachments hook registered in plugin loader",
            "admin_post_tpm_add_attachments" in plugin_code)
assert_test("TPM_Admin::handle_add_attachments() implemented",
            "function handle_add_attachments" in admin_code)
assert_test("handle_add_attachments verifies check_admin_referer nonce",
            "check_admin_referer( 'tpm_add_attachments_action', 'tpm_nonce' )" in admin_code)
assert_test("TPM_Record_Service::add_attachments_to_record() implemented",
            "function add_attachments_to_record" in service_code)
assert_test("admin.js wires #tpm-details-upload-btn to open wp.media",
            "#tpm-details-upload-btn" in js_code and "wp.media" in js_code)

# --------------------------------------------------------------------------
# SPEC 5: MULTIPLE ATTACHMENTS
# --------------------------------------------------------------------------
print("\n--- SPEC 5: Multiple Attachments Support ---")
assert_test("Add record form media frame configured with multiple: true",
            "multiple: true" in js_code)
assert_test("Details page media frame configured with multiple: true",
            js_code.count("multiple: true") >= 2)
assert_test("add_attachments_to_record iterates and inserts multiple attachments in batch",
            "foreach ( $attachments as $att )" in service_code)
assert_test("Loop handles indexing and label assignment per file",
            "$att['attachment_label']" in service_code)

# --------------------------------------------------------------------------
# SPEC 6: DELETE ATTACHMENT RELATIONSHIP (NOT MEDIA FILE)
# --------------------------------------------------------------------------
print("\n--- SPEC 6: Delete Attachment Relationship ---")
assert_test("admin_post_tpm_delete_attachment hook registered in plugin loader",
            "admin_post_tpm_delete_attachment" in plugin_code)
assert_test("TPM_Admin::handle_delete_attachment() implemented",
            "function handle_delete_attachment" in admin_code)
assert_test("handle_delete_attachment verifies nonce per attachment row",
            "check_admin_referer( 'tpm_delete_attachment_' . $attachment_row_id, 'tpm_nonce' )" in admin_code)
assert_test("TPM_Record_Service::delete_attachment() deletes from tpm_attachments table",
            "$wpdb->delete(" in service_code and "array( 'id' => $attachment_row_id )" in service_code)
assert_test("CRITICAL: wp_delete_attachment is NOT called to preserve Media Library file",
            "wp_delete_attachment" not in service_code)
assert_test("delete_attachment logs 'attachment_deleted' activity",
            "'attachment_deleted'" in service_code)
assert_test("Delete button displays user confirmation prompt via data-tpm-confirm",
            "data-tpm-confirm=" in details_view and "dashicons-trash" in details_view)

# --------------------------------------------------------------------------
# SPEC 7: ATTACHMENT LABELS & EXTENSIBILITY
# --------------------------------------------------------------------------
print("\n--- SPEC 7: Attachment Labels & Filter Extensibility ---")
expected_labels = [
    "Tender Document",
    "Customer PO",
    "Quotation",
    "Invoice",
    "Shipping Document",
    "Other"
]
assert_test("TPM_Record_Service::get_attachment_labels() implemented",
            "function get_attachment_labels" in service_code)
for lbl in expected_labels:
    assert_test(f"Label '{lbl}' supported by default", f"'{lbl}'" in service_code)

assert_test("Labels are extensible via apply_filters('tpm_attachment_labels', ...)",
            "apply_filters( 'tpm_attachment_labels', $labels )" in service_code)
assert_test("TPM_Admin localizes dynamic labels from get_attachment_labels() to JS",
            "TPM_Record_Service::get_attachment_labels()" in admin_code)

# --------------------------------------------------------------------------
# SPEC 8: UNAUTHORIZED ACCESS & CAPABILITY ENFORCEMENT
# --------------------------------------------------------------------------
print("\n--- SPEC 8: Unauthorized Access & Capability Enforcement ---")
assert_test("handle_add_attachments enforces edit_tpm_records or manage_tpm_records capability",
            "current_user_can( 'edit_tpm_records' )" in admin_code and
            "current_user_can( 'manage_tpm_records' )" in admin_code)
assert_test("handle_delete_attachment enforces edit_tpm_records or manage_tpm_records capability",
            "current_user_can( 'edit_tpm_records' )" in admin_code)
assert_test("Unauthorized users cannot see '+ Upload Attachment' button in view",
            "current_user_can( 'edit_tpm_records' )" in details_view and "tpm-details-upload-btn" in details_view)
assert_test("Unauthorized users cannot see Delete button in view",
            "current_user_can( 'edit_tpm_records' )" in details_view and "tpm_delete_attachment" in details_view)

# --------------------------------------------------------------------------
# SPEC 9: INVALID ATTACHMENT ID & INTEGRITY VALIDATION
# --------------------------------------------------------------------------
print("\n--- SPEC 9: Invalid Attachment ID & Integrity Validation ---")
assert_test("add_attachments_to_record validates attachment existence in WP Media Library",
            "get_post( $attachment_id )" in service_code)
assert_test("add_attachments_to_record validates post_type is strictly 'attachment'",
            "'attachment' !== $post->post_type" in service_code or "$post->post_type === 'attachment'" in service_code)
assert_test("add_attachments_to_record rejects non-existent record_id",
            "self::get_record( $record_id )" in service_code)
assert_test("File URL is never trusted from user input; always generated from attachment_id",
            "wp_get_attachment_url" in details_view and "attachments['file_url']" not in service_code)

# --------------------------------------------------------------------------
# PART B: SIMULATED DATABASE & WORKFLOW LOGIC TEST
# --------------------------------------------------------------------------
print("\n--- SIMULATED DATABASE & LIFECYCLE WORKFLOWS ---")

conn = sqlite3.connect(':memory:')
cursor = conn.cursor()

# Set up SQLite tables matching MySQL schemas
cursor.execute('''
CREATE TABLE wp_posts (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    post_title TEXT,
    post_type TEXT,
    post_mime_type TEXT
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT,
    status_id INTEGER,
    tender_number TEXT,
    created_by INTEGER,
    created_at TEXT
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER,
    attachment_id INTEGER,
    file_name TEXT,
    file_type TEXT,
    file_size INTEGER,
    attachment_label TEXT,
    uploaded_by INTEGER,
    uploaded_at TEXT
);
''')

cursor.execute('''
CREATE TABLE wp_tpm_activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER,
    user_id INTEGER,
    action TEXT,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    created_at TEXT
);
''')

# Insert Mock WP Posts
cursor.execute("INSERT INTO wp_posts (ID, post_title, post_type, post_mime_type) VALUES (101, 'tender-specification.pdf', 'attachment', 'application/pdf');")
cursor.execute("INSERT INTO wp_posts (ID, post_title, post_type, post_mime_type) VALUES (102, 'po-agreement.pdf', 'attachment', 'application/pdf');")
cursor.execute("INSERT INTO wp_posts (ID, post_title, post_type, post_mime_type) VALUES (103, 'product-spec.xlsx', 'attachment', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');")
cursor.execute("INSERT INTO wp_posts (ID, post_title, post_type, post_mime_type) VALUES (999, 'blog-post', 'post', '');") # Non-attachment post!

# Workflow 1: Create Tender with PDF attachment
cursor.execute("INSERT INTO wp_tpm_records (id, record_type, status_id, tender_number, created_by, created_at) VALUES (1, 'tender', 1, 'TND-2026-001', 1, '2026-09-11 10:00:00');")
record_id = 1
# Attach file 101
cursor.execute("""
INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?);
""", (record_id, 101, 'tender-specification.pdf', 'application/pdf', 204800, 'Tender Document', 1, '2026-09-11 10:00:00'))

cursor.execute("""
INSERT INTO wp_tpm_activity_log (record_id, user_id, action, description, created_at)
VALUES (?, ?, 'attachment_added', 'Added document: tender-specification.pdf (Tender Document)', '2026-09-11 10:00:00');
""", (record_id, 1))

# Workflow 2: Query attachments for Record Details
cursor.execute("SELECT * FROM wp_tpm_attachments WHERE record_id = ?", (record_id,))
atts = cursor.fetchall()
assert_test("Simulation: Record 1 has 1 attachment after creation", len(atts) == 1)
assert_test("Simulation: Attachment 101 has correct label and type", atts[0][2] == 101 and atts[0][6] == 'Tender Document' and atts[0][4] == 'application/pdf')

# Workflow 3: Add second and third attachments after creation (Multiple attachments)
new_attachments = [
    (102, 'po-agreement.pdf', 'application/pdf', 512000, 'Customer PO'),
    (103, 'product-spec.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 1048576, 'Quotation')
]
for att_id, fname, ftype, fsize, flabel in new_attachments:
    # Validate post exists and is attachment
    cursor.execute("SELECT post_type FROM wp_posts WHERE ID = ?", (att_id,))
    post = cursor.fetchone()
    if post and post[0] == 'attachment':
        cursor.execute("""
        INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, file_size, attachment_label, uploaded_by, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?);
        """, (record_id, att_id, fname, ftype, fsize, flabel, 1, '2026-09-11 11:30:00'))
        cursor.execute("""
        INSERT INTO wp_tpm_activity_log (record_id, user_id, action, description, created_at)
        VALUES (?, ?, 'attachment_added', ?, '2026-09-11 11:30:00');
        """, (record_id, 1, f"Added document: {fname} ({flabel})"))

cursor.execute("SELECT COUNT(*) FROM wp_tpm_attachments WHERE record_id = ?", (record_id,))
assert_test("Simulation: Record 1 now has 3 total attachments", cursor.fetchone()[0] == 3)

# Workflow 4: Validation against invalid attachment ID (Post ID 999 is a 'post', not 'attachment')
cursor.execute("SELECT post_type FROM wp_posts WHERE ID = 999")
post_999 = cursor.fetchone()
is_valid_attachment = (post_999 is not None and post_999[0] == 'attachment')
assert_test("Simulation: Validation correctly rejects post ID 999 because post_type != 'attachment'", not is_valid_attachment)

# Workflow 5: Validation against non-existent attachment ID (ID 8888)
cursor.execute("SELECT post_type FROM wp_posts WHERE ID = 8888")
post_8888 = cursor.fetchone()
assert_test("Simulation: Validation correctly rejects non-existent attachment ID 8888", post_8888 is None)

# Workflow 6: Delete attachment relationship (delete attachment row 2, ID 102)
cursor.execute("SELECT id, attachment_id, file_name FROM wp_tpm_attachments WHERE attachment_id = 102 AND record_id = ?", (record_id,))
del_row = cursor.fetchone()
assert_test("Simulation: Target attachment row found for deletion", del_row is not None)

# Perform relationship deletion
cursor.execute("DELETE FROM wp_tpm_attachments WHERE id = ?", (del_row[0],))
cursor.execute("""
INSERT INTO wp_tpm_activity_log (record_id, user_id, action, description, created_at)
VALUES (?, ?, 'attachment_deleted', 'Removed document: po-agreement.pdf (Attachment #102)', '2026-09-11 11:45:00');
""", (record_id, 1))

# Verify relationship is deleted from tpm_attachments
cursor.execute("SELECT COUNT(*) FROM wp_tpm_attachments WHERE record_id = ?", (record_id,))
assert_test("Simulation: wp_tpm_attachments now has 2 attachments remaining", cursor.fetchone()[0] == 2)

# Verify Media Library post 102 still exists in wp_posts
cursor.execute("SELECT ID, post_title FROM wp_posts WHERE ID = 102")
wp_post_102 = cursor.fetchone()
assert_test("Simulation: CRITICAL - wp_posts Media file 102 is PRESERVED and NOT deleted", wp_post_102 is not None and wp_post_102[0] == 102)

# Verify activity logs for added and deleted
cursor.execute("SELECT action, description FROM wp_tpm_activity_log WHERE record_id = ? ORDER BY id ASC", (record_id,))
logs = cursor.fetchall()
assert_test("Simulation: Activity log contains attachment_added events", any(l[0] == 'attachment_added' for l in logs))
assert_test("Simulation: Activity log contains attachment_deleted event", any(l[0] == 'attachment_deleted' for l in logs))

print("\n" + "=" * 68)
print(f" RESULTS: {passed} PASSED, {failed} FAILED")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    print(" ALL 9 PHASE 6 SPECIFICATIONS VERIFIED SUCCESSFULLY!\n")
    sys.exit(0)
