#!/usr/bin/env python3
"""
Python Test Runner for Phase 1: Verify Phase 1 Architecture Integrity
"""

import os
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

required_files = [
    'tender-po-management.php',
    'uninstall.php',
    'readme.txt',
    'includes/class-tpm-plugin.php',
    'includes/class-tpm-loader.php',
    'includes/class-tpm-activator.php',
    'includes/class-tpm-deactivator.php',
    'includes/admin/class-tpm-admin.php',
    'admin/css/admin.css',
    'admin/js/admin.js',
    'admin/views/dashboard.php',
    'public/css/index.php',
    'public/js/index.php',
    'languages/tender-po-management.pot',
]

passed = 0
failed = 0

print("=" * 60)
print(" PHASE 1 INTEGRITY VERIFICATION TEST")
print("=" * 60)

for f in required_files:
    p = os.path.join(BASE_DIR, f)
    if os.path.exists(p):
        print(f" [PASS] File exists: {f}")
        passed += 1
    else:
        print(f" [FAIL] Missing file: {f}")
        failed += 1

print("-" * 60)
print(f" RESULTS: {passed} Passed, {failed} Failed")
print("-" * 60)

if failed > 0:
    sys.exit(1)
print(" PHASE 1 INTEGRITY FULLY INTACT!")
sys.exit(0)
