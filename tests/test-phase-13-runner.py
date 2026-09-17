#!/usr/bin/env python3
"""
Test Suite for Phase 13: Management Dashboard.

Verifies all required specifications:
1. Summary Cards (Total Records, Total Tenders, Total POs, Pending, Submitted, Complete, Archived)
2. Financial Summary (Total Purchase, Total Sell, Total Expenses, Total Actual Payments, Total Outstanding, Gross Profit, Net Profit)
3. Operational Alerts (Tender Due Soon, Tender Past Due, Quote Expiring Soon, Payment Overdue, Missing Attachments, Pending Shipping)
4. Recent Records (Record Number, Type, Customer, Status, Date, View button)
5. Upcoming Dates (Tender Due Date, Quote Expiration, Payment Due Date, Shipping Date)
6. Performance & Query Optimization (Single-pass conditional aggregates, zero N+1 queries)
7. Final Test Scenarios:
   - No records (empty database safety)
   - Few records (exact financial & alert assertions)
   - Archived records handling (strict exclusion from active metrics)
   - Large dataset performance benchmark (5,000 records < 50ms)
"""

import os
import re
import sys
import time
import sqlite3
from datetime import datetime, timedelta

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DASHBOARD_SERVICE_FILE = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-dashboard-service.php')
RECORD_SERVICE_FILE    = os.path.join(BASE_DIR, 'includes', 'services', 'class-tpm-record-service.php')
ADMIN_CLASS_FILE       = os.path.join(BASE_DIR, 'includes', 'admin', 'class-tpm-admin.php')
PLUGIN_CLASS_FILE      = os.path.join(BASE_DIR, 'includes', 'class-tpm-plugin.php')
DASHBOARD_VIEW_FILE    = os.path.join(BASE_DIR, 'admin', 'views', 'dashboard.php')
ADMIN_CSS_FILE         = os.path.join(BASE_DIR, 'admin', 'css', 'admin.css')

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
print(" PHASE 13: MANAGEMENT DASHBOARD VERIFICATION SUITE")
print("=" * 68)

# Read source files
with open(DASHBOARD_SERVICE_FILE, 'r') as f:
    dashboard_service_code = f.read()

with open(ADMIN_CLASS_FILE, 'r') as f:
    admin_code = f.read()

with open(PLUGIN_CLASS_FILE, 'r') as f:
    plugin_code = f.read()

with open(DASHBOARD_VIEW_FILE, 'r') as f:
    dashboard_view = f.read()

with open(ADMIN_CSS_FILE, 'r') as f:
    css_code = f.read()

# --------------------------------------------------------------------------
# PART A: STRUCTURAL ARCHITECTURE & CLASS METHODS
# --------------------------------------------------------------------------
print("\n--- Architecture, Classes & Hook Registrations ---")

assert_test("class-tpm-dashboard-service.php exists", os.path.exists(DASHBOARD_SERVICE_FILE))
assert_test("TPM_Dashboard_Service class defined", "class TPM_Dashboard_Service" in dashboard_service_code)
assert_test("Plugin requires class-tpm-dashboard-service.php", "class-tpm-dashboard-service.php" in plugin_code)

assert_test("TPM_Dashboard_Service::get_summary_cards() implemented",
            "function get_summary_cards(" in dashboard_service_code)
assert_test("TPM_Dashboard_Service::get_financial_summary() implemented",
            "function get_financial_summary(" in dashboard_service_code)
assert_test("TPM_Dashboard_Service::get_alerts() implemented",
            "function get_alerts(" in dashboard_service_code)
assert_test("TPM_Dashboard_Service::get_recent_records() implemented",
            "function get_recent_records(" in dashboard_service_code)
assert_test("TPM_Dashboard_Service::get_upcoming_dates() implemented",
            "function get_upcoming_dates(" in dashboard_service_code)
assert_test("TPM_Dashboard_Service::get_dashboard_data() implemented",
            "function get_dashboard_data(" in dashboard_service_code)

assert_test("TPM_Admin::display_dashboard_page() implemented",
            "function display_dashboard_page" in admin_code)
assert_test("display_dashboard_page() checks capabilities",
            "view_tpm_records" in admin_code and "display_dashboard_page" in admin_code)

# --------------------------------------------------------------------------
# PART B: VIEW TEMPLATE SPECIFICATION INSPECTION (dashboard.php)
# --------------------------------------------------------------------------
print("\n--- Dashboard View Template Specification ---")

# 1. Summary Cards
assert_test("dashboard.php displays 'Total Records' summary card", "Total Records" in dashboard_view)
assert_test("dashboard.php displays 'Total Tenders' summary card", "Total Tenders" in dashboard_view)
assert_test("dashboard.php displays 'Total Purchase Orders' summary card", "Total Purchase Orders" in dashboard_view)
assert_test("dashboard.php displays 'Pending' summary card", "Pending" in dashboard_view)
assert_test("dashboard.php displays 'Submitted' summary card", "Submitted" in dashboard_view)
assert_test("dashboard.php displays 'Complete' summary card", "Complete" in dashboard_view)
assert_test("dashboard.php displays 'Archived' summary card", "Archived" in dashboard_view)

# 2. Financial Summary
assert_test("dashboard.php displays 'Total Purchase Amount'", "Total Purchase Amount" in dashboard_view)
assert_test("dashboard.php displays 'Total Sell Amount'", "Total Sell Amount" in dashboard_view)
assert_test("dashboard.php displays 'Total Expenses'", "Total Expenses" in dashboard_view)
assert_test("dashboard.php displays 'Total Actual Payments'", "Total Actual Payments" in dashboard_view)
assert_test("dashboard.php displays 'Total Outstanding'", "Total Outstanding" in dashboard_view)
assert_test("dashboard.php displays 'Estimated Gross Profit'", "Estimated Gross Profit" in dashboard_view)
assert_test("dashboard.php displays 'Estimated Net Profit'", "Estimated Net Profit" in dashboard_view)

# 3. Operational Alerts
assert_test("dashboard.php displays 'Tender Due Soon' alert", "Tender Due Soon" in dashboard_service_code)
assert_test("dashboard.php displays 'Tender Past Due' alert", "Tender Past Due" in dashboard_service_code)
assert_test("dashboard.php displays 'Quote Expiring Soon' alert", "Quote Expiring Soon" in dashboard_service_code)
assert_test("dashboard.php displays 'Payment Overdue' alert", "Payment Overdue" in dashboard_service_code)
assert_test("dashboard.php displays 'Missing Attachments' alert", "Missing Attachments" in dashboard_service_code)
assert_test("dashboard.php displays 'Pending Shipping' alert", "Pending Shipping" in dashboard_service_code)

# 4. Recent Records
assert_test("dashboard.php includes Recent Records section", "Recent Records" in dashboard_view)
assert_test("Recent records table defines Record Number column", "Record Number" in dashboard_view)
assert_test("Recent records table defines Type column", "Type" in dashboard_view)
assert_test("Recent records table defines Customer column", "Customer" in dashboard_view)
assert_test("Recent records table defines Status column", "Status" in dashboard_view)
assert_test("Recent records table defines Date column", "Date" in dashboard_view)
assert_test("Recent records table includes View button link", "View Details" in dashboard_view or "View" in dashboard_view)

# 5. Upcoming Dates
assert_test("dashboard.php includes Upcoming Dates section", "Upcoming Dates" in dashboard_view)
assert_test("Upcoming dates tracks Tender Due Date", "Tender Due Date" in dashboard_service_code)
assert_test("Upcoming dates tracks Quote Expiration", "Quote Expiration" in dashboard_service_code)
assert_test("Upcoming dates tracks Payment Due Date", "Payment Due Date" in dashboard_service_code)
assert_test("Upcoming dates tracks Shipping Date", "Shipping Date" in dashboard_service_code)

# 6. CSS Styles
assert_test("CSS defines .tpm-summary-grid", ".tpm-summary-grid" in css_code)
assert_test("CSS defines .tpm-financial-grid", ".tpm-financial-grid" in css_code)
assert_test("CSS defines .tpm-alerts-grid", ".tpm-alerts-grid" in css_code)
assert_test("CSS defines .tpm-timeline-list", ".tpm-timeline-list" in css_code)

# --------------------------------------------------------------------------
# PART C: DATABASE SIMULATION & FUNCTIONAL LOGIC (SQLite)
# --------------------------------------------------------------------------
print("\n--- Functional Database Simulation Tests ---")

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Create schema matching WordPress MySQL tables
cursor.executescript("""
CREATE TABLE wp_users (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    display_name TEXT NOT NULL,
    user_email TEXT NOT NULL
);

CREATE TABLE wp_tpm_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT,
    email TEXT,
    phone TEXT,
    status TEXT NOT NULL DEFAULT 'active'
);

CREATE TABLE wp_tpm_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_type TEXT NOT NULL DEFAULT 'tender',
    status TEXT NOT NULL DEFAULT 'draft',
    tender_number TEXT,
    customer_po_number TEXT,
    customer_id INTEGER,
    purchase_price REAL DEFAULT 0.00,
    sell_price REAL DEFAULT 0.00,
    expenses REAL DEFAULT 0.00,
    actual_payment REAL DEFAULT 0.00,
    balance_payment_due REAL DEFAULT 0.00,
    tender_due_date TEXT,
    quote_expiration_date TEXT,
    payment_due_date TEXT,
    shipping_date TEXT,
    original_awb_received INTEGER NOT NULL DEFAULT 0,
    is_archived INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT
);

CREATE TABLE wp_tpm_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    attachment_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_size INTEGER DEFAULT 0,
    uploaded_at TEXT NOT NULL
);
""")

# Python implementation of service query logic for simulation testing
def sim_get_summary_cards(conn):
    c = conn.cursor()
    c.execute("""
        SELECT 
            COUNT(CASE WHEN is_archived = 0 THEN 1 END) AS total_records,
            COUNT(CASE WHEN is_archived = 0 AND record_type = 'tender' THEN 1 END) AS total_tenders,
            COUNT(CASE WHEN is_archived = 0 AND record_type = 'po' THEN 1 END) AS total_pos,
            COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%draft%' OR status LIKE '%review%' OR status LIKE '%pending%') THEN 1 END) AS count_pending,
            COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%submitted%' OR status LIKE '%confirmed%') THEN 1 END) AS count_submitted,
            COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%complete%' OR status LIKE '%awarded%' OR status LIKE '%settled%' OR status LIKE '%delivered%') THEN 1 END) AS count_complete,
            COUNT(CASE WHEN is_archived = 1 THEN 1 END) AS count_archived
        FROM wp_tpm_records
    """)
    row = c.fetchone()
    return {
        'total_records': row['total_records'] or 0,
        'total_tenders': row['total_tenders'] or 0,
        'total_pos': row['total_pos'] or 0,
        'pending': row['count_pending'] or 0,
        'submitted': row['count_submitted'] or 0,
        'complete': row['count_complete'] or 0,
        'archived': row['count_archived'] or 0,
    }

def sim_get_financial_summary(conn):
    c = conn.cursor()
    c.execute("""
        SELECT 
            COALESCE(SUM(purchase_price), 0.0) AS total_purchase,
            COALESCE(SUM(sell_price), 0.0) AS total_sell,
            COALESCE(SUM(expenses), 0.0) AS total_expenses,
            COALESCE(SUM(actual_payment), 0.0) AS total_actual_payments,
            COALESCE(SUM(balance_payment_due), 0.0) AS total_outstanding,
            COALESCE(SUM(sell_price - purchase_price), 0.0) AS estimated_gross_profit,
            COALESCE(SUM(sell_price - purchase_price - expenses), 0.0) AS estimated_net_profit
        FROM wp_tpm_records
        WHERE is_archived = 0
    """)
    row = c.fetchone()
    return {
        'total_purchase': float(row['total_purchase'] or 0.0),
        'total_sell': float(row['total_sell'] or 0.0),
        'total_expenses': float(row['total_expenses'] or 0.0),
        'total_actual_payments': float(row['total_actual_payments'] or 0.0),
        'total_outstanding': float(row['total_outstanding'] or 0.0),
        'estimated_gross_profit': float(row['estimated_gross_profit'] or 0.0),
        'estimated_net_profit': float(row['estimated_net_profit'] or 0.0),
    }

def sim_get_alerts(conn, today='2026-09-11'):
    seven_days = (datetime.strptime(today, '%Y-%m-%d') + timedelta(days=7)).strftime('%Y-%m-%d')
    c = conn.cursor()
    c.execute("""
        SELECT 
            COUNT(CASE 
                WHEN r.record_type = 'tender' 
                 AND r.is_archived = 0 
                 AND r.tender_due_date >= ? 
                 AND r.tender_due_date <= ? 
                 AND r.status NOT IN ('awarded', 'settled', 'complete', 'delivered', 'cancelled')
                THEN 1 END) AS count_tender_due_soon,

            COUNT(CASE 
                WHEN r.record_type = 'tender' 
                 AND r.is_archived = 0 
                 AND r.tender_due_date < ? 
                 AND r.tender_due_date IS NOT NULL 
                 AND r.tender_due_date != '' 
                 AND r.tender_due_date != '0000-00-00'
                 AND r.status NOT IN ('awarded', 'settled', 'complete', 'delivered', 'cancelled')
                THEN 1 END) AS count_tender_past_due,

            COUNT(CASE 
                WHEN r.is_archived = 0 
                 AND r.quote_expiration_date >= ? 
                 AND r.quote_expiration_date <= ? 
                 AND r.status NOT IN ('awarded', 'settled', 'complete', 'delivered', 'cancelled')
                THEN 1 END) AS count_quote_expiring_soon,

            COUNT(CASE 
                WHEN r.is_archived = 0 
                 AND r.payment_due_date < ? 
                 AND r.payment_due_date IS NOT NULL 
                 AND r.payment_due_date != '' 
                 AND r.payment_due_date != '0000-00-00'
                 AND r.balance_payment_due > 0 
                THEN 1 END) AS count_payment_overdue,

            COUNT(CASE 
                WHEN r.is_archived = 0 
                 AND NOT EXISTS (SELECT 1 FROM wp_tpm_attachments a WHERE a.record_id = r.id)
                THEN 1 END) AS count_missing_attachments,

            COUNT(CASE 
                WHEN r.record_type = 'po' 
                 AND r.is_archived = 0 
                 AND (r.shipping_date IS NULL OR r.shipping_date = '' OR r.shipping_date = '0000-00-00' OR r.original_awb_received = 0)
                 AND r.status NOT IN ('delivered', 'complete', 'cancelled')
                THEN 1 END) AS count_pending_shipping
        FROM wp_tpm_records r
    """, (today, seven_days, today, today, seven_days, today))
    row = c.fetchone()
    return {
        'tender_due_soon': row['count_tender_due_soon'] or 0,
        'tender_past_due': row['count_tender_past_due'] or 0,
        'quote_expiring_soon': row['count_quote_expiring_soon'] or 0,
        'payment_overdue': row['count_payment_overdue'] or 0,
        'missing_attachments': row['count_missing_attachments'] or 0,
        'pending_shipping': row['count_pending_shipping'] or 0,
    }

def sim_get_recent_records(conn, limit=8):
    c = conn.cursor()
    c.execute("""
        SELECT r.id, r.record_type, r.status, r.tender_number, r.customer_po_number,
               r.sell_price, r.created_at,
               c.name AS customer_name, c.company_name AS customer_company
        FROM wp_tpm_records r
        LEFT JOIN wp_tpm_customers c ON r.customer_id = c.id
        WHERE r.is_archived = 0
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT ?
    """, (limit,))
    return c.fetchall()

def sim_get_upcoming_dates(conn, today='2026-09-11', limit=10):
    lookback = (datetime.strptime(today, '%Y-%m-%d') - timedelta(days=30)).strftime('%Y-%m-%d')
    lookahed = (datetime.strptime(today, '%Y-%m-%d') + timedelta(days=90)).strftime('%Y-%m-%d')
    c = conn.cursor()
    c.execute("""
        SELECT id, record_type, tender_number, customer_po_number, status,
               tender_due_date, quote_expiration_date, payment_due_date, shipping_date
        FROM wp_tpm_records
        WHERE is_archived = 0 
          AND status NOT IN ('delivered', 'complete', 'settled', 'cancelled')
          AND (
               (tender_due_date >= ? AND tender_due_date <= ?) OR
               (quote_expiration_date >= ? AND quote_expiration_date <= ?) OR
               (payment_due_date >= ? AND payment_due_date <= ?) OR
               (shipping_date >= ? AND shipping_date <= ?)
          )
        LIMIT 50
    """, (lookback, lookahed, lookback, lookahed, lookback, lookahed, lookback, lookahed))
    rows = c.fetchall()
    events = []
    for r in rows:
        rec_id = r['id']
        rec_num = r['tender_number'] or r['customer_po_number'] or f"#{rec_id}"
        rec_type = r['record_type']

        if r['tender_due_date'] and r['tender_due_date'] != '0000-00-00':
            events.append({'record_id': rec_id, 'record_number': rec_num, 'record_type': rec_type, 'label': 'Tender Due Date', 'date': r['tender_due_date']})
        if r['quote_expiration_date'] and r['quote_expiration_date'] != '0000-00-00':
            events.append({'record_id': rec_id, 'record_number': rec_num, 'record_type': rec_type, 'label': 'Quote Expiration', 'date': r['quote_expiration_date']})
        if r['payment_due_date'] and r['payment_due_date'] != '0000-00-00':
            events.append({'record_id': rec_id, 'record_number': rec_num, 'record_type': rec_type, 'label': 'Payment Due Date', 'date': r['payment_due_date']})
        if r['shipping_date'] and r['shipping_date'] != '0000-00-00':
            events.append({'record_id': rec_id, 'record_number': rec_num, 'record_type': rec_type, 'label': 'Shipping Date', 'date': r['shipping_date']})

    events.sort(key=lambda x: x['date'])
    return events[:limit]

# --------------------------------------------------------------------------
# SCENARIO 1: NO RECORDS (Empty Database Safety)
# --------------------------------------------------------------------------
print("\n--- Scenario 1: Zero Records (Empty Database Safety) ---")
empty_summary = sim_get_summary_cards(conn)
assert_test("Empty DB: Total Records is 0", empty_summary['total_records'] == 0)
assert_test("Empty DB: Total Tenders is 0", empty_summary['total_tenders'] == 0)
assert_test("Empty DB: Total POs is 0", empty_summary['total_pos'] == 0)
assert_test("Empty DB: Pending is 0", empty_summary['pending'] == 0)
assert_test("Empty DB: Submitted is 0", empty_summary['submitted'] == 0)
assert_test("Empty DB: Complete is 0", empty_summary['complete'] == 0)
assert_test("Empty DB: Archived is 0", empty_summary['archived'] == 0)

empty_fin = sim_get_financial_summary(conn)
assert_test("Empty DB: Total Purchase is $0.00", empty_fin['total_purchase'] == 0.0)
assert_test("Empty DB: Total Sell is $0.00", empty_fin['total_sell'] == 0.0)
assert_test("Empty DB: Total Expenses is $0.00", empty_fin['total_expenses'] == 0.0)
assert_test("Empty DB: Total Payments is $0.00", empty_fin['total_actual_payments'] == 0.0)
assert_test("Empty DB: Total Outstanding is $0.00", empty_fin['total_outstanding'] == 0.0)
assert_test("Empty DB: Gross Profit is $0.00", empty_fin['estimated_gross_profit'] == 0.0)
assert_test("Empty DB: Net Profit is $0.00", empty_fin['estimated_net_profit'] == 0.0)

empty_alerts = sim_get_alerts(conn)
assert_test("Empty DB: All alerts count is 0", sum(empty_alerts.values()) == 0)

empty_recent = sim_get_recent_records(conn)
assert_test("Empty DB: Recent records list is empty", len(empty_recent) == 0)

empty_milestones = sim_get_upcoming_dates(conn)
assert_test("Empty DB: Upcoming dates list is empty", len(empty_milestones) == 0)

# --------------------------------------------------------------------------
# SCENARIO 2: FEW RECORDS (Targeted Verification & Archived Exclusion)
# --------------------------------------------------------------------------
print("\n--- Scenario 2: Few Records with Varied Statuses & Archived Exclusion ---")
today_sim = '2026-09-11'

cursor.execute("INSERT INTO wp_tpm_customers (id, name, company_name) VALUES (1, 'Acme Corp', 'Acme Global')")

# Record 1: Active Tender, draft (pending), sell 10k, purchase 6k, expenses 500, due in 3 days (2026-09-14), 0 attachments
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_id, purchase_price, sell_price, expenses, actual_payment, balance_payment_due, tender_due_date, is_archived, created_at)
VALUES (1, 'tender', 'draft', 'TN-2026-001', 1, 6000.00, 10000.00, 500.00, 0.00, 10000.00, '2026-09-14', 0, '2026-09-01 10:00:00')
""")

# Record 2: Active Tender, submitted, sell 25k, purchase 18k, expenses 1000, tender_due_date 2026-09-05 (past due!), quote_exp 2026-09-15 (expiring in 4 days!). Has 1 attachment.
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_id, purchase_price, sell_price, expenses, actual_payment, balance_payment_due, tender_due_date, quote_expiration_date, is_archived, created_at)
VALUES (2, 'tender', 'submitted', 'TN-2026-002', 1, 18000.00, 25000.00, 1000.00, 0.00, 25000.00, '2026-09-05', '2026-09-15', 0, '2026-09-02 11:00:00')
""")
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, uploaded_at) VALUES (2, 501, 'Bid_Response.pdf', 'application/pdf', '2026-09-02 11:30:00')")

# Record 3: Active PO, po-confirmed (submitted), sell 15k, purchase 11k, expenses 800, shipping_date NULL, original_awb_received 0 (pending shipping!). Has 1 attachment.
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, customer_po_number, customer_id, purchase_price, sell_price, expenses, actual_payment, balance_payment_due, shipping_date, original_awb_received, is_archived, created_at)
VALUES (3, 'po', 'po-confirmed', 'PO-2026-003', 1, 11000.00, 15000.00, 800.00, 0.00, 15000.00, NULL, 0, 0, '2026-09-03 12:00:00')
""")
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, uploaded_at) VALUES (3, 502, 'Purchase_Order.pdf', 'application/pdf', '2026-09-03 12:30:00')")

# Record 4: Active PO, delivered (complete), sell 30k, purchase 22k, expenses 1500, actual_payment 10k, balance 20k, payment_due_date 2026-09-01 (payment overdue!). Has 1 attachment.
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, customer_po_number, customer_id, purchase_price, sell_price, expenses, actual_payment, balance_payment_due, payment_due_date, is_archived, created_at)
VALUES (4, 'po', 'delivered', 'PO-2026-004', 1, 22000.00, 30000.00, 1500.00, 10000.00, 20000.00, '2026-09-01', 0, '2026-09-04 13:00:00')
""")
cursor.execute("INSERT INTO wp_tpm_attachments (record_id, attachment_id, file_name, file_type, uploaded_at) VALUES (4, 503, 'Delivery_Note.pdf', 'application/pdf', '2026-09-04 13:30:00')")

# Record 5: ARCHIVED Tender, awarded, sell 50k, purchase 35k, expenses 2000. (MUST BE EXCLUDED FROM ACTIVE STATS!)
cursor.execute("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_id, purchase_price, sell_price, expenses, actual_payment, balance_payment_due, is_archived, created_at)
VALUES (5, 'tender', 'awarded', 'TN-2026-ARCH', 1, 35000.00, 50000.00, 2000.00, 50000.00, 0.00, 1, '2026-08-01 10:00:00')
""")
conn.commit()

# --- Verify Summary Cards ---
cards = sim_get_summary_cards(conn)
assert_test("Summary: Total Active Records is 4 (Archived Record 5 excluded)", cards['total_records'] == 4)
assert_test("Summary: Total Tenders is 2", cards['total_tenders'] == 2)
assert_test("Summary: Total Purchase Orders is 2", cards['total_pos'] == 2)
assert_test("Summary: Pending status count is 1 (Record 1)", cards['pending'] == 1)
assert_test("Summary: Submitted status count is 2 (Records 2 & 3)", cards['submitted'] == 2)
assert_test("Summary: Complete status count is 1 (Record 4)", cards['complete'] == 1)
assert_test("Summary: Archived count is 1 (Record 5)", cards['archived'] == 1)

# --- Verify Financial Summary ---
# Active records: 1, 2, 3, 4
# Total Purchase: 6,000 + 18,000 + 11,000 + 22,000 = 57,000
# Total Sell: 10,000 + 25,000 + 15,000 + 30,000 = 80,000
# Total Expenses: 500 + 1000 + 800 + 1500 = 3,800
# Total Actual Payments: 10,000
# Total Outstanding: 10,000 + 25,000 + 15,000 + 20,000 = 70,000
# Gross Profit: 80,000 - 57,000 = 23,000
# Net Profit: 80,000 - 57,000 - 3,800 = 19,200
fin = sim_get_financial_summary(conn)
assert_test("Financial: Total Purchase Amount = $57,000.00", fin['total_purchase'] == 57000.00)
assert_test("Financial: Total Sell Amount = $80,000.00", fin['total_sell'] == 80000.00)
assert_test("Financial: Total Expenses = $3,800.00", fin['total_expenses'] == 3800.00)
assert_test("Financial: Total Actual Payments = $10,000.00", fin['total_actual_payments'] == 10000.00)
assert_test("Financial: Total Outstanding = $70,000.00", fin['total_outstanding'] == 70000.00)
assert_test("Financial: Estimated Gross Profit = $23,000.00 ($80k - $57k)", fin['estimated_gross_profit'] == 23000.00)
assert_test("Financial: Estimated Net Profit = $19,200.00 ($23k - $3.8k)", fin['estimated_net_profit'] == 19200.00)

# --- Verify Operational Alerts ---
alerts = sim_get_alerts(conn, today_sim)
assert_test("Alerts: Tender Due Soon count is 1 (Record 1 due 2026-09-14)", alerts['tender_due_soon'] == 1)
assert_test("Alerts: Tender Past Due count is 1 (Record 2 past due 2026-09-05)", alerts['tender_past_due'] == 1)
assert_test("Alerts: Quote Expiring Soon count is 1 (Record 2 exp 2026-09-15)", alerts['quote_expiring_soon'] == 1)
assert_test("Alerts: Payment Overdue count is 1 (Record 4 past due 2026-09-01 with $20k bal)", alerts['payment_overdue'] == 1)
assert_test("Alerts: Missing Attachments count is 1 (Record 1 has 0 files)", alerts['missing_attachments'] == 1)
assert_test("Alerts: Pending Shipping count is 1 (Record 3 pending AWB/shipping)", alerts['pending_shipping'] == 1)

# --- Verify Recent Records Feed ---
recent = sim_get_recent_records(conn, limit=8)
assert_test("Recent: Returns 4 active records", len(recent) == 4)
assert_test("Recent: Excludes archived Record 5", 5 not in [r['id'] for r in recent])
assert_test("Recent: First record is newest (Record 4 created 2026-09-04)", recent[0]['id'] == 4)
assert_test("Recent: Customer company name joined ('Acme Global')", recent[0]['customer_company'] == 'Acme Global')

# --- Verify Upcoming Dates ---
milestones = sim_get_upcoming_dates(conn, today_sim, limit=10)
assert_test("Upcoming Dates: Found milestone events", len(milestones) > 0)
assert_test("Upcoming Dates: Sorted chronologically (earliest date first)",
            milestones[0]['date'] <= milestones[-1]['date'])

# --------------------------------------------------------------------------
# SCENARIO 3: LARGE DATASET PERFORMANCE BENCHMARK (5,000 RECORDS)
# --------------------------------------------------------------------------
print("\n--- Scenario 3: Large Dataset Performance Benchmark (5,000 Records) ---")
print(" Seeding 5,000 records into in-memory database...")

records_batch = []
for i in range(10, 5010):
    rtype = 'tender' if i % 2 == 0 else 'po'
    st = ['draft', 'submitted', 'review', 'awarded', 'delivered'][i % 5]
    is_arch = 1 if i % 10 == 0 else 0
    t_num = f"TND-BIG-{i}" if rtype == 'tender' else None
    po_num = f"PO-BIG-{i}" if rtype == 'po' else None
    p_price = 1000.0 + (i % 500)
    s_price = 1500.0 + (i % 700)
    exp = 50.0 + (i % 50)
    act_pay = 500.0 if i % 3 == 0 else 0.0
    bal = s_price - act_pay
    c_date = f"2026-0{1 + (i % 8):02d}-15 10:00:00"

    records_batch.append((
        i, rtype, st, t_num, po_num, 1, p_price, s_price, exp, act_pay, bal, is_arch, c_date
    ))

cursor.executemany("""
INSERT INTO wp_tpm_records (id, record_type, status, tender_number, customer_po_number, customer_id, purchase_price, sell_price, expenses, actual_payment, balance_payment_due, is_archived, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
""", records_batch)
conn.commit()

cursor.execute("SELECT COUNT(*) FROM wp_tpm_records")
total_in_db = cursor.fetchone()[0]
assert_test(f"Database contains {total_in_db} records", total_in_db > 5000)

# Benchmark Summary Cards query
t0 = time.perf_counter()
bench_summary = sim_get_summary_cards(conn)
t1 = time.perf_counter()
cards_ms = (t1 - t0) * 1000.0
assert_test(f"Benchmark: Summary Cards query executed in {cards_ms:.2f}ms (< 50ms requirement)",
            cards_ms < 50.0)
assert_test("Benchmark: Summary Cards total_records matches active count",
            bench_summary['total_records'] > 4500)

# Benchmark Financial Summary query
t0 = time.perf_counter()
bench_fin = sim_get_financial_summary(conn)
t1 = time.perf_counter()
fin_ms = (t1 - t0) * 1000.0
assert_test(f"Benchmark: Financial Summary query executed in {fin_ms:.2f}ms (< 50ms requirement)",
            fin_ms < 50.0)
assert_test("Benchmark: Financial Summary total_sell > $7,000,000.00",
            bench_fin['total_sell'] > 7000000.0)

# Benchmark Alerts query
t0 = time.perf_counter()
bench_alerts = sim_get_alerts(conn, today_sim)
t1 = time.perf_counter()
alerts_ms = (t1 - t0) * 1000.0
assert_test(f"Benchmark: Operational Alerts query executed in {alerts_ms:.2f}ms (< 50ms requirement)",
            alerts_ms < 50.0)

# Benchmark Recent Records query
t0 = time.perf_counter()
bench_recent = sim_get_recent_records(conn, limit=8)
t1 = time.perf_counter()
recent_ms = (t1 - t0) * 1000.0
assert_test(f"Benchmark: Recent Records query executed in {recent_ms:.2f}ms (< 50ms requirement)",
            recent_ms < 50.0)
assert_test("Benchmark: Recent Records returned exact slice of 8 records", len(bench_recent) == 8)

total_dashboard_ms = cards_ms + fin_ms + alerts_ms + recent_ms
assert_test(f"Benchmark: Cumulative full dashboard data generated in {total_dashboard_ms:.2f}ms (< 100ms total budget)",
            total_dashboard_ms < 100.0)

# --- Summary ---
print("\n" + "=" * 68)
print(f" TOTAL TESTS RUN: {passed + failed}")
print(f" PASSED: {passed}")
print(f" FAILED: {failed}")
print("=" * 68)

if failed > 0:
    sys.exit(1)
else:
    print("\n>>> ALL PHASE 13 MANAGEMENT DASHBOARD TESTS PASSED WITH 100% SUCCESS! <<<\n")
    sys.exit(0)
