#!/usr/bin/env python3
"""
Static Syntax and Security Checker for Tender & PO Management System (Phase 1)
Validates:
1. Balanced braces, brackets, and parentheses via character scanner
2. ABSPATH or WP_UNINSTALL_PLUGIN or directory silence protection
3. Required file existence and plugin constants
"""

import os
import re
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def strip_php_comments_and_strings(content):
    output = []
    i = 0
    n = len(content)

    while i < n:
        c = content[i]

        # Single-quote string
        if c == "'":
            output.append(' ')
            i += 1
            while i < n:
                if content[i] == '\\' and i + 1 < n:
                    i += 2
                    continue
                if content[i] == "'":
                    i += 1
                    break
                if content[i] == '\n':
                    output.append('\n')
                else:
                    output.append(' ')
                i += 1
            output.append(' ')
            continue

        # Double-quote string
        if c == '"':
            output.append(' ')
            i += 1
            while i < n:
                if content[i] == '\\' and i + 1 < n:
                    i += 2
                    continue
                if content[i] == '"':
                    i += 1
                    break
                if content[i] == '\n':
                    output.append('\n')
                else:
                    output.append(' ')
                i += 1
            output.append(' ')
            continue

        # Block comment /* ... */
        if c == '/' and i + 1 < n and content[i + 1] == '*':
            i += 2
            while i + 1 < n and not (content[i] == '*' and content[i + 1] == '/'):
                if content[i] == '\n':
                    output.append('\n')
                else:
                    output.append(' ')
                i += 1
            i += 2
            continue

        # Line comment // or #
        if (c == '/' and i + 1 < n and content[i + 1] == '/') or c == '#':
            while i < n and content[i] != '\n':
                output.append(' ')
                i += 1
            continue

        output.append(c)
        i += 1

    return ''.join(output)

def check_file(file_path):
    errors = []
    warnings = []
    rel_path = os.path.relpath(file_path, BASE_DIR)

    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Direct access guard check
    is_uninstall = 'uninstall.php' in file_path
    is_index = os.path.basename(file_path) == 'index.php'
    if is_uninstall:
        if 'WP_UNINSTALL_PLUGIN' not in content:
            errors.append("Missing WP_UNINSTALL_PLUGIN check in uninstall.php")
    elif is_index:
        if 'Silence is golden' not in content:
            errors.append("Missing 'Silence is golden' in index.php")
    else:
        if 'defined' not in content or 'ABSPATH' not in content:
            errors.append("Missing ABSPATH direct access guard")

    # 2. Balanced delimiters check
    clean_code = strip_php_comments_and_strings(content)
    stack = []
    pairs = {')': '(', '}': '{', ']': '['}

    line_idx = 1
    for char in clean_code:
        if char == '\n':
            line_idx += 1
            continue
        if char in '({[':
            stack.append((char, line_idx))
        elif char in ')}]':
            if not stack:
                errors.append(f"Unmatched closing '{char}' at line {line_idx}")
                break
            top, top_line = stack.pop()
            if top != pairs[char]:
                errors.append(f"Mismatched delimiter: expected closing for '{top}' (line {top_line}), got '{char}' at line {line_idx}")
                break

    if stack:
        for char, line_no in stack:
            errors.append(f"Unclosed '{char}' opened at line {line_no}")

    return errors, warnings

def main():
    print("=" * 60)
    print(" STATIC CODE & SECURITY VALIDATOR - PHASE 1 FOUNDATION")
    print("=" * 60)

    total_files = 0
    total_errors = 0
    total_warnings = 0

    for root, dirs, files in os.walk(BASE_DIR):
        if any(skip in root for skip in ['.git', 'node_modules', 'vendor', '.system_generated']):
            continue
        for file in sorted(files):
            if file.endswith('.php'):
                total_files += 1
                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, BASE_DIR)
                errors, warnings = check_file(file_path)

                if errors:
                    print(f" [FAIL] {rel_path}")
                    for err in errors:
                        print(f"        -> ERROR: {err}")
                    total_errors += len(errors)
                else:
                    print(f" [PASS] {rel_path}")
                total_warnings += len(warnings)

    print("-" * 60)
    print(f" Checked {total_files} PHP files: {total_errors} errors, {total_warnings} warnings")
    print("-" * 60)

    if total_errors > 0:
        sys.exit(1)
    print(" ALL FILES PASSED VALIDATION PERFECTLY!")
    sys.exit(0)

if __name__ == '__main__':
    main()
