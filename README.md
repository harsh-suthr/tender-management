# Tender & Purchase Order Management System (`tender-po-management`)

> A complete, enterprise-grade Tender and Purchase Order Management System built natively for the WordPress `wp-admin` backend.

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org)
[![License](https://img.shields.io/badge/License-GPLv2%20or%20later-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Architecture](https://img.shields.io/badge/Architecture-Service--Oriented%20OOP-success?style=flat-square)](#architecture--services)

---

## Table of Contents

- [Overview & Description](#overview--description)
- [Requirements](#requirements)
- [Installation](#installation)
- [Activation](#activation)
- [Menu Overview](#menu-overview)
- [User Roles & Permissions](#user-roles--permissions)
- [Record Creation & Management](#record-creation--management)
- [Attachment Management](#attachment-management)
- [PDF & Document In-Admin Preview](#pdf--document-in-admin-preview)
- [Payment Tracking & Balances](#payment-tracking--balances)
- [Archive & Restore Workflow](#archive--restore-workflow)
- [Reporting System & CSV Export](#reporting-system--csv-export)
- [Plugin Settings & Customization](#plugin-settings--customization)
- [Troubleshooting & Diagnostics](#troubleshooting--diagnostics)
- [Developer Documentation](#developer-documentation)
  - [Folder Structure](#folder-structure)
  - [Database Schema & Indexes](#database-schema--indexes)
  - [Service Layer Architecture](#service-layer-architecture)
  - [Action & Filter Hooks](#action--filter-hooks)
  - [Capabilities Reference](#capabilities-reference)
  - [Extension Points](#extension-points)

---

## Overview & Description

The **Tender & Purchase Order Management System** (`tender-po-management`) is an enterprise procurement management suite designed exclusively for internal procurement teams, contractors, and supply chain operators. 

### Key Principles
- **100% Native WP-Admin**: Zero front-end overhead, zero custom public login pages, and zero external software dependencies. Seamlessly extends the native WordPress administrative dashboard.
- **Relational Data Architecture**: Utilizes 7 dedicated `$wpdb` relational tables with composite indexing, foreign key cascade awareness, and zero post-type table bloat.
- **Strict Enterprise Security**: All dynamic queries execute via `$wpdb->prepare()`. Every form submission, AJAX request, bulk action, and state transition is guarded by explicit CSRF nonces and granular capability checks.
- **Real-Time Financial Calculations**: Live client-side and server-side computations for purchase price, sell price, expenses, gross profit margin, net profit margin, actual payments, and balance payment due.
- **Comprehensive Audit Trail**: Automatically captures user actions, previous/new states, timestamps, and IP addresses across all records.

---

## Requirements

| Component | Minimum Requirement | Recommended |
| :--- | :--- | :--- |
| **PHP** | 8.1+ | 8.2 or 8.3 |
| **WordPress** | 6.0+ | 6.7+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | MySQL 8.0+ / MariaDB 10.6+ |
| **Web Server** | Nginx or Apache | Nginx with HTTP/2 |
| **Browser** | Modern Evergreen (Chrome, Firefox, Safari, Edge) | Modern Evergreen with PDF rendering |

*Note: Fully compatible with WordPress Multisite networks (Single-site and Network-wide activation supported).*

---

## Installation

### Manual Installation via ZIP
1. Download or compress the plugin folder as `tender-po-management.zip`.
2. In your WordPress admin, navigate to **Plugins &rarr; Add New &rarr; Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### Installation via FTP / Terminal
1. Upload or copy the `tender-po-management` directory directly into `/wp-content/plugins/`.
2. Ensure file permissions allow the web server to read plugin files (typically `755` for directories, `644` for files).
3. Navigate to **Plugins &rarr; Installed Plugins** in the WordPress admin.
4. Locate **Tender & PO Management System** and click **Activate**.

---

## Activation

Upon activation, the system automatically executes:
1. **Environment Compatibility Check**: Validates PHP &ge; 8.1 and WordPress &ge; 6.0; terminates cleanly with a user-friendly error if requirements are not met.
2. **Database Provisioning**: Executes `TPM_Database::install()` to create or upgrade all 7 relational tables via WordPress `dbDelta()`.
3. **Default Status Seeding**: Provisions 8 standard procurement workflow statuses (`draft`, `in_review`, `submitted`, `awarded`, `in_progress`, `completed`, `cancelled`, `lost`).
4. **Role & Capability Registration**: Registers 10 custom capabilities and assigns them to Administrator and 3 specialized procurement roles.
5. **Multisite Support**: When activated network-wide, automatically loops through all network sub-sites via `switch_to_blog()` and hooks `wp_initialize_site` for new sites.

---

## Menu Overview

Under the top-level **Tender Management** admin menu (`dashicons-portfolio`):

| Menu Item | Screen Slug | Description | Capability Required |
| :--- | :--- | :--- | :--- |
| **Dashboard** | `tender-po-management` | Summary cards, financial KPI totals, action alerts, and recent activity log feed. | `view_tpm_records` |
| **Add New Record** | `tpm-add-record` | Unified responsive form for drafting Tenders or issuing Purchase Orders. | `create_tpm_records` |
| **All Records** | `tpm-records` | Filterable, searchable `WP_List_Table` displaying both Tenders and POs. | `view_tpm_records` |
| **Tenders** | `tpm-tenders` | Focused list view filtered exclusively to Tender records. | `view_tpm_records` |
| **Purchase Orders** | `tpm-purchase-orders`| Focused list view filtered exclusively to Purchase Order records. | `view_tpm_records` |
| **Archived Records**| `tpm-archived-records`| Segregated repository for archived records with Restore and Permanent Delete. | `manage_tpm_archive` |
| **Customers** | `tpm-customers` | Directory of procurement clients with primary contact details and statuses. | `manage_tpm_customers` |
| **Partner Companies**| `tpm-partner-companies`| Directory of freight forwarders, subcontractors, and logistics partners. | `manage_tpm_partners` |
| **Reports** | `tpm-reports` | In-depth reporting engine across 7 report types with CSV export. | `view_tpm_reports` |
| **Settings** | `tpm-settings` | System currency, status manager, allowed upload MIME types, and archive rules. | `manage_tpm_settings` |

---

## User Roles & Permissions

The plugin registers 10 granular capabilities:
1. `manage_tpm_records`: Full record administration rights.
2. `view_tpm_records`: Read-only access to view dashboards, records, and details.
3. `create_tpm_records`: Ability to draft new tenders and purchase orders.
4. `edit_tpm_records`: Ability to edit existing records.
5. `delete_tpm_records`: Ability to permanently delete records.
6. `manage_tpm_customers`: Full CRUD access for Customers.
7. `manage_tpm_partners`: Full CRUD access for Partner Companies.
8. `view_tpm_reports`: Access to view reports and export CSV summaries.
9. `manage_tpm_settings`: Access to modify system settings, currencies, and statuses.
10. `manage_tpm_archive`: Access to archive, restore, and view archived records.

### Roles Matrix

| Capability | Administrator | Tender Manager (`tpm_manager`) | Tender Staff (`tpm_staff`) | Tender Viewer (`tpm_viewer`) |
| :--- | :---: | :---: | :---: | :---: |
| `manage_tpm_records` | :white_check_mark: | :white_check_mark: | :x: | :x: |
| `view_tpm_records` | :white_check_mark: | :white_check_mark: | :white_check_mark: | :white_check_mark: |
| `create_tpm_records` | :white_check_mark: | :white_check_mark: | :white_check_mark: | :x: |
| `edit_tpm_records` | :white_check_mark: | :white_check_mark: | :white_check_mark: | :x: |
| `delete_tpm_records` | :white_check_mark: | :white_check_mark: | :x: | :x: |
| `manage_tpm_customers` | :white_check_mark: | :white_check_mark: | :x: | :x: |
| `manage_tpm_partners` | :white_check_mark: | :white_check_mark: | :x: | :x: |
| `view_tpm_reports` | :white_check_mark: | :white_check_mark: | :x: | :x: |
| `manage_tpm_archive` | :white_check_mark: | :white_check_mark: | :x: | :x: |
| `manage_tpm_settings` | :white_check_mark: | :x: *(admin only)* | :x: | :x: |

---

## Record Creation & Management

### Creating a Record
1. Navigate to **Tender Management &rarr; Add New Record**.
2. Select the **Record Type** using the visual cards:
   - **Tender**: Bids, tenders, and proposals submitted to clients.
   - **Purchase Order**: Procurement orders issued to vendors or partners.
3. Complete the required and optional sections:
   - **Primary Details**: Record / Tender Number, Title / Item Description, Customer, Partner Company, Workflow Status.
   - **Milestone Dates**: Tender Due Date, Quote Expiration Date, Award Date, PO Date, Estimated Shipping Date.
   - **Financial Information**: Purchase Price, Sell Price, Estimated Expenses. Gross profit and net profit margins update in real time.
   - **Shipping & Logistics**: Shipping Address, Shipping Terms (e.g. FOB Destination, CIF), Tracking Number, Delivery Status.
   - **Attachments**: Attach PDF specifications, drawings, or quotes directly via the WordPress Media Library.
   - **Notes**: Internal audit notes or client remarks.
4. Click **Create Record**. The system validates input, persists relational rows, creates an activity log entry, and redirects to the **Record Details** screen.

---

## Attachment Management

- **Native Media Library**: No fragile direct file uploads to non-standard folders. Attachments are tracked as WordPress Media attachments with full metadata.
- **Categorization Labels**: Attachments can be labeled as *Tender Document*, *Customer PO*, *Quotation*, *Invoice*, *Shipping Document*, or *Other*.
- **Post Existence Verification**: On save, attachment IDs are verified against WordPress posts (`get_post()`) ensuring only valid attachments are linked.
- **Inline Upload & Removal**: Add or unlink attachments at any time directly from the Record Details screen without navigating away.

---

## PDF & Document In-Admin Preview

1. On any record details screen containing PDF documents, click the **Preview** button (`dashicons-visibility`).
2. A responsive modal opens displaying the PDF rendered directly within the native browser viewer via a sandboxed `<iframe>`.
3. Quick actions within the modal allow opening the document in a new tab or initiating a direct download.
4. Closing the modal automatically unloads the iframe to free browser memory.

---

## Payment Tracking & Balances

### Adding Payments
1. Open any record and select the **Payments** tab.
2. Enter the **Payment Amount**, **Payment Date**, **Payment Method** (Bank Transfer, Credit Card, Cash, Cheque, Wire Transfer, Letter of Credit), and optional **Reference Number** or **Notes**.
3. Click **Add Payment**.

### Automatic Balance & Status Transitions
The system automatically recalculates payments using optimized database aggregations:
- **Pending**: Total Paid = $0.00
- **Partial**: $0.00 < Total Paid < Sell Price
- **Paid**: Total Paid &ge; Sell Price
- **Overdue**: Balance Due > $0.00 and Payment Due Date has passed.

---

## Archive & Restore Workflow

### Archiving a Record
- From the records list table or record details view, click **Archive Record**.
- The record is soft-deleted (`is_archived = 1`) and timestamped.
- An immutable activity log entry (`record_archived`) is recorded.
- The record is hidden from all active tables, dashboard metrics, and default reports.

### Restoring a Record
- Navigate to **Tender Management &rarr; Archived Records**.
- Click **Restore Record** on any row.
- The record is restored to active status (`is_archived = 0`), and an activity log entry (`record_restored`) is recorded.

### Permanent Deletion
- Permanent deletion requires high-level capabilities (`delete_tpm_records` or `manage_tpm_records`).
- Requires individual row nonce or bulk action nonce verification and browser confirmation dialog.
- Automatically cascades and cleans up associated attachments, payment rows, and activity log entries.

---

## Reporting System & CSV Export

Navigate to **Tender Management &rarr; Reports** to access 7 specialized procurement reports:
1. **Tender Report**: Status, customers, submission dates, and value of all tenders.
2. **Purchase Order Report**: Vendor POs, delivery dates, and shipping statuses.
3. **Financial Report**: Purchase totals, sell totals, gross margins, net margins, payments, and outstanding balances.
4. **Customer Report**: Summary of records, revenue, and active tenders per customer.
5. **Partner Company Report**: Logistics partner activity, fulfillment counts, and expenses.
6. **Payment Report**: Detailed breakdown of transaction dates, methods, references, and amounts.
7. **Status Report**: Record distribution across all workflow statuses.

### CSV Export
- Click **Export CSV** on any report view.
- Generates standard RFC 4180 compliant CSV streams with UTF-8 Byte Order Mark (BOM) for compatibility with Microsoft Excel, Apple Numbers, and Google Sheets.

---

## Plugin Settings & Customization

Located at **Tender Management &rarr; Settings** (Requires `manage_tpm_settings` capability):
- **General Settings**: Default currency is Indian Rupee (`INR`) with symbol (`₹`), fully customizable to any currency (e.g. `USD`, `EUR`, `GBP`), Date Format (`Y-m-d`, `m/d/Y`, `d/m/Y`), and Records Per Page for list tables.
- **Status Management**: Add custom statuses, edit labels, reorder display sequences, define HEX color badges, and restrict statuses to Tenders, POs, or Both. Protected against accidental deletion when in use.
- **Attachment Settings**: Configurable maximum upload file sizes and allowed MIME types/extensions.
- **Archive Retention**: Configurable auto-archive schedules or manual archiving policies.

---

## Troubleshooting & Diagnostics

### Common Scenarios

**1. "You do not have sufficient permissions to access this page."**
- Ensure your WordPress user account is assigned the `administrator`, `tpm_manager`, or appropriate procurement role.
- Verify user capabilities under WordPress Users settings.

**2. "Invalid Security Token / Nonce Verification Failed"**
- Occurs when an administrative tab is left open for longer than WordPress nonce expiration (typically 24 hours). Refresh the page and resubmit the form.

**3. "File upload rejected"**
- Verify the file extension matches allowed MIME types in **Settings &rarr; Attachment Settings**.
- Check your server's `upload_max_filesize` and `post_max_size` PHP settings.

**4. PDF preview does not display**
- Ensure browser settings allow PDF rendering. If blocked by browser security extensions or if using an older browser, use the provided **Download File** or **Open in New Tab** links.

---

## Developer Documentation

### Folder Structure

```
tender-po-management/
├── tender-po-management.php       # Main plugin bootstrap file with header & constants
├── uninstall.php                  # Clean database and options uninstaller
├── README.md                      # Complete system & developer documentation
├── readme.txt                     # WordPress.org standard plugin readme file
├── admin/                         # Admin view templates and static assets
│   ├── css/
│   │   └── admin.css              # Modern, responsive WP Admin design system
│   ├── js/
│   │   └── admin.js               # Dynamic financial calculations, modals, & media handlers
│   └── views/                     # Screen view templates
│       ├── add-record.php         # Create record form view
│       ├── archived-records.php   # Archived records list view
│       ├── customers.php          # Customer list and modal views
│       ├── dashboard.php          # Primary KPI dashboard view
│       ├── partner-companies.php  # Partner companies list and modal views
│       ├── purchase-orders.php    # Purchase orders list view
│       ├── record-details.php     # Tabbed record details view (Overview, Financial, etc.)
│       ├── records.php            # All records list view
│       ├── reports.php            # Multi-report dashboard and filter screen
│       ├── settings.php           # Plugin settings, status manager, and system health
│       └── tenders.php            # Tenders focused list view
├── includes/                      # Core backend PHP architecture
│   ├── class-tpm.php              # Plugin orchestrator and hook coordinator
│   ├── class-tpm-activator.php    # Activation handler (migrations, roles, multisite)
│   ├── class-tpm-deactivator.php  # Deactivation cleanup handler
│   ├── class-tpm-loader.php       # Action and filter registration registry
│   ├── admin/                     # Admin controller and list tables
│   │   ├── class-tpm-admin.php    # Main admin controller (request routing, nonces, POST)
│   │   ├── class-tpm-customers-list-table.php # WP_List_Table for Customers
│   │   ├── class-tpm-partners-list-table.php  # WP_List_Table for Partners
│   │   └── class-tpm-records-list-table.php   # WP_List_Table for Tenders and POs
│   ├── database/                  # Relational database manager
│   │   └── class-tpm-database.php # Table schemas, migrations, indexes, and seeding
│   └── services/                  # Business logic and domain service layer
│       ├── class-tpm-customer-service.php # Customer queries and mutations
│       ├── class-tpm-dashboard-service.php# KPI aggregations and action alerts
│       ├── class-tpm-partner-service.php  # Partner company queries and mutations
│       ├── class-tpm-payment-service.php  # Payment recording and balance computations
│       ├── class-tpm-record-service.php   # Record CRUD, filters, and activity logging
│       ├── class-tpm-report-service.php   # Report aggregations and CSV streaming
│       ├── class-tpm-roles-service.php    # Role and capability registration
│       └── class-tpm-settings-service.php # Settings storage and status management
├── languages/                     # Localization files
│   └── tender-po-management.pot   # Translation template
└── tests/                         # Complete automated test suite
    ├── verify-syntax.py           # PHP syntax and lint validator across all 39 files
    └── test-phase-18-runner.py    # 28-step end-to-end integration and QA test runner
```

---

### Database Schema & Indexes

The plugin creates 7 relational tables prefixed with `{$wpdb->prefix}`:

#### 1. `tpm_records`
Primary table storing both Tenders and Purchase Orders.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `record_type` (VARCHAR(20), 'tender' | 'po')
- `status` (VARCHAR(50), default 'draft')
- `tender_number` (VARCHAR(100))
- `customer_id` (BIGINT UNSIGNED, Foreign Key &rarr; `tpm_customers.id`)
- `partner_company_id` (BIGINT UNSIGNED, Foreign Key &rarr; `tpm_partner_companies.id`)
- `purchase_price`, `sell_price`, `expenses`, `actual_payment`, `balance_payment_due` (DECIMAL(15,2))
- `tender_due_date`, `quote_expiration_date`, `po_date`, `payment_due_date`, `shipping_date` (DATE)
- `is_archived` (TINYINT(1), default 0)
- **High-Performance Composite Indexes**:
  - `idx_archived_type` (`is_archived`, `record_type`)
  - `idx_archived_created` (`is_archived`, `created_at`)
  - `idx_archived_status` (`is_archived`, `status`)
  - `idx_tender_due_date` (`tender_due_date`)
  - `idx_payment_due_date` (`payment_due_date`)

#### 2. `tpm_customers`
Directory of customers and procurement agencies.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `company_name` (VARCHAR(255))
- `contact_person`, `email`, `phone`, `address`, `notes`
- `status` (VARCHAR(20), 'active' | 'inactive')
- **Composite Index**: `idx_status_company` (`status`, `company_name`)

#### 3. `tpm_partner_companies`
Directory of subcontractors and freight forwarders.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `company_name` (VARCHAR(255))
- `contact_person`, `email`, `phone`, `address`, `services`, `notes`
- `status` (VARCHAR(20), 'active' | 'inactive')
- **Composite Index**: `idx_status_company` (`status`, `company_name`)

#### 4. `tpm_attachments`
Relational mapping between records and WordPress Media Library items.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `record_id` (BIGINT UNSIGNED, Foreign Key &rarr; `tpm_records.id`)
- `attachment_id` (BIGINT UNSIGNED, References `wp_posts.ID`)
- `file_name`, `file_type`, `file_size`, `attachment_label`

#### 5. `tpm_payments`
Payment transaction and milestone ledger.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `record_id` (BIGINT UNSIGNED, Foreign Key &rarr; `tpm_records.id`)
- `payment_amount` (DECIMAL(15,2))
- `payment_date` (DATE)
- `payment_method` (VARCHAR(50))
- `reference_number`, `notes`

#### 6. `tpm_activity_logs`
Immutable audit log tracking all actions.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `record_id` (BIGINT UNSIGNED, Foreign Key &rarr; `tpm_records.id`)
- `user_id` (BIGINT UNSIGNED)
- `action` (VARCHAR(100))
- `details` (LONGTEXT)
- `ip_address` (VARCHAR(45))

#### 7. `tpm_statuses`
Dynamic workflow status manager.
- `id` (BIGINT UNSIGNED, Primary Key, Auto-Increment)
- `slug` (VARCHAR(50), Unique)
- `label` (VARCHAR(100))
- `color` (VARCHAR(7), HEX code)
- `applicable_to` (VARCHAR(20), 'tender' | 'po' | 'both')
- `display_order` (INT)
- `is_active`, `is_default` (TINYINT(1))

---

### Service Layer Architecture

Business logic is cleanly isolated from views and presentation controllers:
- `TPM_Record_Service`: Handles record creation, validation, updating, soft archiving, restoration, and deletion.
- `TPM_Customer_Service`: Manages customer directory CRUD, search, and view counts.
- `TPM_Partner_Service`: Manages partner company CRUD, search, and view counts.
- `TPM_Payment_Service`: Manages payment transactions, balance recalculations, and automated payment status transitions.
- `TPM_Dashboard_Service`: Gathers aggregate KPI metrics, financial sums, action alerts, and recent activities.
- `TPM_Report_Service`: Generates data datasets across 7 report types and handles CSV export formatting.
- `TPM_Settings_Service`: Manages system options, custom statuses, in-memory caching, and cache invalidation.
- `TPM_Roles_Service`: Manages custom capability and role provisioning.

---

### Action & Filter Hooks

#### Action Hooks
```php
// Fires immediately after a new record is created
do_action( 'tpm_after_record_created', int $record_id, array $data );

// Fires immediately after an existing record is updated
do_action( 'tpm_after_record_updated', int $record_id, array $data );

// Fires when a record status is updated
do_action( 'tpm_status_changed', int $record_id, string $old_status, string $new_status );

// Fires when a payment is added to a record
do_action( 'tpm_payment_added', int $payment_id, int $record_id, float $amount );

// Fires when a record is archived
do_action( 'tpm_record_archived', int $record_id );

// Fires when a record is restored from the archive
do_action( 'tpm_record_restored', int $record_id );
```

#### Filter Hooks
```php
// Filter allowed attachment labels
$labels = apply_filters( 'tpm_attachment_labels', array $default_labels );

// Filter system currency symbol
$symbol = apply_filters( 'tpm_currency_symbol', string $symbol );

// Filter allowed upload MIME types
$mimes = apply_filters( 'tpm_allowed_upload_mimes', array $mimes );

// Filter dashboard KPI cards
$kpi_cards = apply_filters( 'tpm_dashboard_summary_cards', array $cards, array $raw_counts );
```

---

### Capabilities Reference

To grant custom roles access to Tender Management features, map the corresponding capability:

| Capability Slug | Description |
| :--- | :--- |
| `manage_tpm_records` | Master administrative record management capability. |
| `view_tpm_records` | Required to access dashboard and record views. |
| `create_tpm_records` | Required to create new records. |
| `edit_tpm_records` | Required to modify existing records. |
| `delete_tpm_records` | Required to permanently remove records. |
| `manage_tpm_customers` | Required to create, edit, or delete customer records. |
| `manage_tpm_partners` | Required to create, edit, or delete partner company records. |
| `view_tpm_reports` | Required to view and export reports. |
| `manage_tpm_archive` | Required to view, archive, or restore archived records. |
| `manage_tpm_settings` | Required to access plugin settings and modify workflow statuses. |

---

### Extension Points

#### Adding a Custom Attachment Label
```php
add_filter( 'tpm_attachment_labels', function( $labels ) {
    $labels['Quality Certificate'] = __( 'Quality Certificate', 'my-textdomain' );
    $labels['Customs Declaration'] = __( 'Customs Declaration', 'my-textdomain' );
    return $labels;
});
```

#### Listening to Tender Status Changes
```php
add_action( 'tpm_status_changed', function( $record_id, $old_status, $new_status ) {
    if ( 'awarded' === $new_status ) {
        // Trigger external notification or webhook
        wp_mail( 'procurement@example.com', 'Tender Awarded!', 'Tender ID ' . $record_id . ' has been awarded.' );
    }
}, 10, 3 );
```

---

## License & Copyright

Tender & Purchase Order Management System is licensed under the **GNU General Public License v2 or later (GPL-2.0-or-later)**.
Copyright &copy; 2026 Enterprise Systems. All rights reserved.
