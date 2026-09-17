=== Tender & PO Management System ===
Contributors: enterprisesystems
Tags: tender, purchase-order, procurement, bidding, management, tracking
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete, production-grade Tender and Purchase Order Management System built natively for the WordPress wp-admin backend.

== Description ==

The **Tender & Purchase Order Management System** (`tender-po-management`) is an enterprise procurement management suite designed exclusively for procurement departments, government contractors, logistics coordinators, and agencies.

Built 100% natively for the WordPress administrative backend (`wp-admin`), it operates without unnecessary frontend pages, external SaaS subscriptions, or public login dependencies.

### Core Features

* **Tender & Purchase Order Management**: Create, track, and manage tenders and purchase orders through distinct workflow statuses with real-time financial margin calculations.
* **Customer & Partner Directories**: Maintain clean, dedicated directories for procurement clients and logistics/partner companies with primary contact info.
* **Granular Role-Based Access Control (RBAC)**: Supports Administrator and 3 specialized roles (`Tender Manager`, `Tender Staff`, `Tender Viewer`) backed by 10 custom capabilities.
* **Interactive Financial Engine**: Real-time Gross Profit, Net Profit, Gross Margin %, Total Paid, and Balance Due calculations.
* **Payment Tracking**: Full payment ledger with transaction history, payment methods, reference numbers, and automated status transitions (`Pending`, `Partial`, `Paid`, `Overdue`).
* **WordPress Media Integration**: Secure document attachment linking with label categorizations (`Tender Document`, `Customer PO`, `Quotation`, `Invoice`, `Shipping Document`, `Other`).
* **In-Admin PDF & Document Preview**: Instant modal preview for PDF specifications and image attachments without leaving the wp-admin dashboard.
* **Safe Archiving & Restoration**: Soft-delete archiving with complete activity audit trail and strict permanent deletion guards.
* **Executive Management Dashboard**: At-a-glance summary cards, financial totals, action alerts (tenders due soon, quotes expiring, payments overdue), and recent activity feed.
* **Advanced Reporting & CSV Export**: 7 specialized procurement reports (Tender, PO, Financial, Customer, Partner, Payment, Status) with RFC 4180 CSV export.
* **Dynamic Settings & Workflow Statuses**: Configurable currencies, date formats, records per page, custom workflow statuses with color badges, and upload validation limits.

== Installation ==

1. Upload the `tender-po-management` folder to your `/wp-content/plugins/` directory (or install via the WordPress Plugins zip uploader).
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access **Tender Management** directly from the WordPress administrative sidebar.

== Frequently Asked Questions ==

= Does this plugin create frontend pages or public registration? =
No. This plugin is built 100% natively for the WordPress `wp-admin` backend. It secures all management behind WordPress authentication and user capabilities.

= What are the minimum requirements? =
WordPress 6.0 or higher and PHP 8.1 or higher. MySQL 5.7+ or MariaDB 10.3+.

= Is WordPress Multisite supported? =
Yes. The plugin supports both per-site activation and network-wide activation across multisite networks.

= How are files and PDF documents stored? =
Files are uploaded through the native WordPress Media Library and linked relationally to records, ensuring compatibility with standard WordPress backups and cloud media offloaders.

== Screenshots ==

1. Management Dashboard with KPI summary cards, financial totals, and action alerts.
2. All Records list table with search, filters, status color badges, and row actions.
3. Record Details overview showing financial margins, shipping tracking, and attached specifications.
4. Native PDF in-admin preview modal.
5. Payment management ledger with automatic status transition to Paid.
6. Reports interface with 7 report types and CSV export.

== Changelog ==

= 1.0.0 =
* Phase 1: WordPress Plugin Foundation & relational architecture.
* Phase 2: Complete Relational Database Architecture (7 tables via dbDelta).
* Phase 3: Customer Management module with WP_List_Table integration.
* Phase 4: Partner Companies Management module.
* Phase 5: Core Record Management for Tenders and Purchase Orders.
* Phase 6: Record Creation & Edit UI with real-time financial calculations.
* Phase 7: Records List Table with search, sorting, filtering, and bulk actions.
* Phase 8: Record Details View with tabbed layout and financial KPI widgets.
* Phase 9: Attachment System with WordPress Media Library integration.
* Phase 10: PDF & Document In-Admin Preview Modal with native iframe rendering.
* Phase 11: Payment Management ledger with automated balance & status calculation.
* Phase 12: Archive Management with safe soft-deletion and activity logging.
* Phase 13: Executive Management Dashboard with KPI cards and alert widgets.
* Phase 14: Comprehensive Reporting System with 7 report types and CSV export.
* Phase 15: Plugin Settings, Currency management, and dynamic Status Manager.
* Phase 16: User Roles & Permissions with 4 roles and 10 granular capabilities.
* Phase 17: Security & Performance Audit with composite indexes and single-pass aggregations.
* Phase 18: Final QA, comprehensive 28-step integration testing, and developer documentation.
