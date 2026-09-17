<?php
/**
 * Service: Reporting System
 *
 * Handles data aggregation, financial calculations, multi-criteria filtering,
 * and secure CSV data export for all procurement reporting modules.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/services
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TPM_Report_Service
 *
 * Core reporting engine supporting 7 report types, date/status/customer/partner
 * filtering, executive financial KPI calculations, and Excel-compatible CSV exports.
 *
 * @since 1.0.0
 */
class TPM_Report_Service {

	/**
	 * Supported report types.
	 *
	 * @var array<string, string>
	 */
	public const REPORT_TYPES = array(
		'financial'      => 'Financial Report',
		'tender'         => 'Tender Report',
		'purchase_order' => 'Purchase Order Report',
		'customer'       => 'Customer Report',
		'partner'        => 'Partner Company Report',
		'payment'        => 'Payment Report',
		'status'         => 'Status Report',
	);

	/**
	 * Sanitize and validate filter inputs.
	 *
	 * @since  1.0.0
	 * @param  array $raw_filters Raw input array from $_GET or request.
	 * @return array Sanitized filter parameters.
	 */
	public function sanitize_filters( array $raw_filters ): array {
		$sanitized = array();

		// 1. Report Type.
		$report_type = isset( $raw_filters['report_type'] ) ? sanitize_key( $raw_filters['report_type'] ) : 'financial';
		if ( ! array_key_exists( $report_type, self::REPORT_TYPES ) ) {
			$report_type = 'financial';
		}
		$sanitized['report_type'] = $report_type;

		// 2. Date Range (From & To).
		$sanitized['date_from'] = '';
		if ( ! empty( $raw_filters['date_from'] ) ) {
			$date_from = sanitize_text_field( $raw_filters['date_from'] );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$sanitized['date_from'] = $date_from;
			}
		}

		$sanitized['date_to'] = '';
		if ( ! empty( $raw_filters['date_to'] ) ) {
			$date_to = sanitize_text_field( $raw_filters['date_to'] );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$sanitized['date_to'] = $date_to;
			}
		}

		// 3. Record Type filter.
		$record_type = isset( $raw_filters['record_type'] ) ? sanitize_key( $raw_filters['record_type'] ) : 'all';
		if ( ! in_array( $record_type, array( 'all', 'tender', 'purchase_order' ), true ) ) {
			$record_type = 'all';
		}
		$sanitized['record_type'] = $record_type;

		// 4. Status filter.
		$status = isset( $raw_filters['status'] ) ? sanitize_key( $raw_filters['status'] ) : 'all';
		$sanitized['status'] = $status;

		// 5. Customer ID filter.
		$sanitized['customer_id'] = ! empty( $raw_filters['customer_id'] ) ? absint( $raw_filters['customer_id'] ) : 0;

		// 6. Partner ID filter.
		$sanitized['partner_id'] = ! empty( $raw_filters['partner_id'] ) ? absint( $raw_filters['partner_id'] ) : 0;

		// 7. Include Archived records toggle.
		$sanitized['include_archived'] = ! empty( $raw_filters['include_archived'] ) ? 1 : 0;

		return $sanitized;
	}

	/**
	 * Retrieve dropdown options for filter forms (customers, partners, statuses).
	 *
	 * @since  1.0.0
	 * @return array Filter options.
	 */
	public function get_filter_options(): array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();

		// Customers.
		$customers = $wpdb->get_results(
			"SELECT id, name, company_name FROM {$tables['customers']} ORDER BY company_name ASC, name ASC",
			ARRAY_A
		);

		// Partner companies.
		$partners = $wpdb->get_results(
			"SELECT id, company_name, contact_person FROM {$tables['partner_companies']} ORDER BY company_name ASC",
			ARRAY_A
		);

		// Statuses.
		$statuses = $wpdb->get_results(
			"SELECT slug, name FROM {$tables['statuses']} ORDER BY sort_order ASC",
			ARRAY_A
		);

		return array(
			'customers' => $customers ?: array(),
			'partners'  => $partners ?: array(),
			'statuses'  => $statuses ?: array(),
		);
	}

	/**
	 * Unified router to fetch report data by type.
	 *
	 * @since  1.0.0
	 * @param  string $report_type Report type slug.
	 * @param  array  $filters     Sanitized filters.
	 * @return array Report payload containing rows, summaries, and metadata.
	 */
	public function get_report_data( string $report_type, array $filters ): array {
		switch ( $report_type ) {
			case 'tender':
				return $this->get_tender_report( $filters );
			case 'purchase_order':
				return $this->get_po_report( $filters );
			case 'customer':
				return $this->get_customer_report( $filters );
			case 'partner':
				return $this->get_partner_report( $filters );
			case 'payment':
				return $this->get_payment_report( $filters );
			case 'status':
				return $this->get_status_report( $filters );
			case 'financial':
			default:
				return $this->get_financial_report( $filters );
		}
	}

	/**
	 * 1. Financial Report: Detailed profitability analysis with line items.
	 *
	 * Displays: Purchase Total, Sell Total, Expenses, Payments, Outstanding,
	 * Gross Profit, and Net Profit.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filter parameters.
	 * @return array Financial report data.
	 */
	public function get_financial_report( array $filters ): array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();
		$where  = $this->build_records_where_clause( $filters );

		// Aggregated Financial KPIs.
		$summary_sql = "SELECT 
			COUNT(r.id) AS total_records,
			COALESCE(SUM(r.purchase_price), 0.00) AS purchase_total,
			COALESCE(SUM(r.sell_price), 0.00) AS sell_total,
			COALESCE(SUM(r.expenses), 0.00) AS expenses_total,
			COALESCE(SUM(r.actual_payment), 0.00) AS payments_total,
			COALESCE(SUM(r.balance_payment_due), 0.00) AS outstanding_total
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}";

		$raw_summary = $wpdb->get_row(
			$where['params'] ? $wpdb->prepare( $summary_sql, $where['params'] ) : $summary_sql,
			ARRAY_A
		);

		$purchase_total    = (float) ( $raw_summary['purchase_total'] ?? 0.00 );
		$sell_total        = (float) ( $raw_summary['sell_total'] ?? 0.00 );
		$expenses_total    = (float) ( $raw_summary['expenses_total'] ?? 0.00 );
		$payments_total    = (float) ( $raw_summary['payments_total'] ?? 0.00 );
		$outstanding_total = (float) ( $raw_summary['outstanding_total'] ?? 0.00 );
		$gross_profit      = $sell_total - $purchase_total;
		$net_profit        = $gross_profit - $expenses_total;

		$summary = array(
			'total_records'     => (int) ( $raw_summary['total_records'] ?? 0 ),
			'purchase_total'    => $purchase_total,
			'sell_total'        => $sell_total,
			'expenses_total'    => $expenses_total,
			'payments_total'    => $payments_total,
			'outstanding_total' => $outstanding_total,
			'gross_profit'      => $gross_profit,
			'net_profit'        => $net_profit,
			'margin_percentage' => $sell_total > 0 ? ( ( $net_profit / $sell_total ) * 100 ) : 0.0,
		);

		// Line Item Ledger Rows.
		$rows_sql = "SELECT 
			r.id,
			r.record_type,
			r.status,
			r.tender_number,
			r.customer_po_number,
			r.customer_po_name,
			r.item_description,
			r.purchase_price,
			r.sell_price,
			r.expenses,
			r.actual_payment,
			r.balance_payment_due,
			r.created_at,
			c.name AS customer_name,
			c.company_name AS customer_company,
			p.company_name AS partner_company
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}
		ORDER BY r.created_at DESC";

		$raw_rows = $wpdb->get_results(
			$where['params'] ? $wpdb->prepare( $rows_sql, $where['params'] ) : $rows_sql,
			ARRAY_A
		);

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$purchase     = (float) $row['purchase_price'];
				$sell         = (float) $row['sell_price'];
				$expenses     = (float) $row['expenses'];
				$gross        = $sell - $purchase;
				$net          = $gross - $expenses;
				$margin       = $sell > 0 ? ( ( $net / $sell ) * 100 ) : 0.0;
				$ref_number   = ! empty( $row['tender_number'] ) ? $row['tender_number'] : ( ! empty( $row['customer_po_number'] ) ? $row['customer_po_number'] : '#' . $row['id'] );

				$rows[] = array(
					'id'                  => (int) $row['id'],
					'record_number'       => $ref_number,
					'record_type'         => $row['record_type'],
					'status'              => $row['status'],
					'customer'            => ! empty( $row['customer_company'] ) ? $row['customer_company'] : ( ! empty( $row['customer_name'] ) ? $row['customer_name'] : '-' ),
					'partner'             => ! empty( $row['partner_company'] ) ? $row['partner_company'] : '-',
					'purchase_price'      => $purchase,
					'sell_price'          => $sell,
					'expenses'            => $expenses,
					'actual_payment'      => (float) $row['actual_payment'],
					'balance_payment_due' => (float) $row['balance_payment_due'],
					'gross_profit'        => $gross,
					'net_profit'          => $net,
					'margin_percentage'   => $margin,
					'created_at'          => $row['created_at'],
				);
			}
		}

		return array(
			'type'    => 'financial',
			'title'   => __( 'Financial Intelligence Report', 'tender-po-management' ),
			'summary' => $summary,
			'rows'    => $rows,
		);
	}

	/**
	 * 2. Tender Report: Dedicated tenders listing and procurement conversion.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array Tender report data.
	 */
	public function get_tender_report( array $filters ): array {
		global $wpdb;

		// Force record_type = 'tender' for Tender report.
		$tender_filters                = $filters;
		$tender_filters['record_type'] = 'tender';

		$tables = TPM_Database::get_table_names();
		$where  = $this->build_records_where_clause( $tender_filters );

		$summary_sql = "SELECT 
			COUNT(r.id) AS total_tenders,
			COALESCE(SUM(r.purchase_price), 0.00) AS purchase_total,
			COALESCE(SUM(r.sell_price), 0.00) AS sell_total,
			COALESCE(SUM(r.expenses), 0.00) AS expenses_total
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}";

		$raw_summary = $wpdb->get_row(
			$where['params'] ? $wpdb->prepare( $summary_sql, $where['params'] ) : $summary_sql,
			ARRAY_A
		);

		$purchase_total = (float) ( $raw_summary['purchase_total'] ?? 0.00 );
		$sell_total     = (float) ( $raw_summary['sell_total'] ?? 0.00 );
		$expenses_total = (float) ( $raw_summary['expenses_total'] ?? 0.00 );
		$gross_profit   = $sell_total - $purchase_total;
		$net_profit     = $gross_profit - $expenses_total;

		$summary = array(
			'total_tenders'     => (int) ( $raw_summary['total_tenders'] ?? 0 ),
			'purchase_total'    => $purchase_total,
			'sell_total'        => $sell_total,
			'expenses_total'    => $expenses_total,
			'gross_profit'      => $gross_profit,
			'net_profit'        => $net_profit,
			'margin_percentage' => $sell_total > 0 ? ( ( $net_profit / $sell_total ) * 100 ) : 0.0,
		);

		$rows_sql = "SELECT 
			r.id,
			r.tender_number,
			r.status,
			r.item_description,
			r.tender_due_date,
			r.tender_submission_date,
			r.quote_expiration_date,
			r.purchase_price,
			r.sell_price,
			r.expenses,
			r.actual_payment,
			r.balance_payment_due,
			r.created_at,
			c.company_name AS customer_company,
			c.name AS customer_name,
			p.company_name AS partner_company
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}
		ORDER BY r.tender_due_date ASC, r.created_at DESC";

		$raw_rows = $wpdb->get_results(
			$where['params'] ? $wpdb->prepare( $rows_sql, $where['params'] ) : $rows_sql,
			ARRAY_A
		);

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$purchase = (float) $row['purchase_price'];
				$sell     = (float) $row['sell_price'];
				$expenses = (float) $row['expenses'];

				$rows[] = array(
					'id'                     => (int) $row['id'],
					'tender_number'          => ! empty( $row['tender_number'] ) ? $row['tender_number'] : '#' . $row['id'],
					'customer'               => ! empty( $row['customer_company'] ) ? $row['customer_company'] : ( ! empty( $row['customer_name'] ) ? $row['customer_name'] : '-' ),
					'partner'                => ! empty( $row['partner_company'] ) ? $row['partner_company'] : '-',
					'item_description'       => $row['item_description'],
					'status'                 => $row['status'],
					'tender_due_date'        => $row['tender_due_date'],
					'tender_submission_date' => $row['tender_submission_date'],
					'quote_expiration_date'  => $row['quote_expiration_date'],
					'purchase_price'         => $purchase,
					'sell_price'             => $sell,
					'expenses'               => $expenses,
					'gross_profit'           => $sell - $purchase,
					'net_profit'             => ( $sell - $purchase ) - $expenses,
					'created_at'             => $row['created_at'],
				);
			}
		}

		return array(
			'type'    => 'tender',
			'title'   => __( 'Tender Report', 'tender-po-management' ),
			'summary' => $summary,
			'rows'    => $rows,
		);
	}

	/**
	 * 3. Purchase Order Report: Dedicated PO procurement tracking.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array PO report data.
	 */
	public function get_po_report( array $filters ): array {
		global $wpdb;

		// Force record_type = 'purchase_order' for PO report.
		$po_filters                = $filters;
		$po_filters['record_type'] = 'purchase_order';

		$tables = TPM_Database::get_table_names();
		$where  = $this->build_records_where_clause( $po_filters );

		$summary_sql = "SELECT 
			COUNT(r.id) AS total_pos,
			COALESCE(SUM(r.purchase_price), 0.00) AS purchase_total,
			COALESCE(SUM(r.sell_price), 0.00) AS sell_total,
			COALESCE(SUM(r.actual_payment), 0.00) AS payments_total,
			COALESCE(SUM(r.balance_payment_due), 0.00) AS outstanding_total
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}";

		$raw_summary = $wpdb->get_row(
			$where['params'] ? $wpdb->prepare( $summary_sql, $where['params'] ) : $summary_sql,
			ARRAY_A
		);

		$purchase_total    = (float) ( $raw_summary['purchase_total'] ?? 0.00 );
		$sell_total        = (float) ( $raw_summary['sell_total'] ?? 0.00 );
		$payments_total    = (float) ( $raw_summary['payments_total'] ?? 0.00 );
		$outstanding_total = (float) ( $raw_summary['outstanding_total'] ?? 0.00 );

		$summary = array(
			'total_pos'         => (int) ( $raw_summary['total_pos'] ?? 0 ),
			'purchase_total'    => $purchase_total,
			'sell_total'        => $sell_total,
			'payments_total'    => $payments_total,
			'outstanding_total' => $outstanding_total,
			'gross_profit'      => $sell_total - $purchase_total,
		);

		$rows_sql = "SELECT 
			r.id,
			r.customer_po_number,
			r.customer_po_name,
			r.status,
			r.item_description,
			r.po_date,
			r.payment_due_date,
			r.shipping_date,
			r.tracking_number,
			r.purchase_price,
			r.sell_price,
			r.actual_payment,
			r.balance_payment_due,
			r.created_at,
			c.company_name AS customer_company,
			c.name AS customer_name,
			p.company_name AS partner_company
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}
		ORDER BY r.po_date DESC, r.created_at DESC";

		$raw_rows = $wpdb->get_results(
			$where['params'] ? $wpdb->prepare( $rows_sql, $where['params'] ) : $rows_sql,
			ARRAY_A
		);

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$purchase = (float) $row['purchase_price'];
				$sell     = (float) $row['sell_price'];

				$rows[] = array(
					'id'                  => (int) $row['id'],
					'po_number'           => ! empty( $row['customer_po_number'] ) ? $row['customer_po_number'] : '#' . $row['id'],
					'po_name'             => ! empty( $row['customer_po_name'] ) ? $row['customer_po_name'] : '-',
					'customer'            => ! empty( $row['customer_company'] ) ? $row['customer_company'] : ( ! empty( $row['customer_name'] ) ? $row['customer_name'] : '-' ),
					'partner'             => ! empty( $row['partner_company'] ) ? $row['partner_company'] : '-',
					'item_description'    => $row['item_description'],
					'status'              => $row['status'],
					'po_date'             => $row['po_date'],
					'payment_due_date'    => $row['payment_due_date'],
					'shipping_date'       => $row['shipping_date'],
					'purchase_price'      => $purchase,
					'sell_price'          => $sell,
					'actual_payment'      => (float) $row['actual_payment'],
					'balance_payment_due' => (float) $row['balance_payment_due'],
					'gross_profit'        => $sell - $purchase,
					'created_at'          => $row['created_at'],
				);
			}
		}

		return array(
			'type'    => 'purchase_order',
			'title'   => __( 'Purchase Order Report', 'tender-po-management' ),
			'summary' => $summary,
			'rows'    => $rows,
		);
	}

	/**
	 * 4. Customer Report: Customer spending, procurement history, and receivables.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array Customer report data.
	 */
	public function get_customer_report( array $filters ): array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();

		// Base conditions for records.
		$archived_cond = ! empty( $filters['include_archived'] ) ? '' : 'AND r.is_archived = 0';
		$date_cond     = '';
		$params        = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$date_cond .= ' AND r.created_at >= %s';
			$params[]   = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$date_cond .= ' AND r.created_at <= %s';
			$params[]   = $filters['date_to'] . ' 23:59:59';
		}
		if ( ! empty( $filters['customer_id'] ) ) {
			$date_cond .= ' AND c.id = %d';
			$params[]   = $filters['customer_id'];
		}

		$sql = "SELECT 
			c.id,
			c.name,
			c.company_name,
			c.email,
			c.phone,
			c.status AS customer_status,
			COUNT(r.id) AS total_records,
			SUM(CASE WHEN r.record_type = 'tender' THEN 1 ELSE 0 END) AS total_tenders,
			SUM(CASE WHEN r.record_type = 'purchase_order' THEN 1 ELSE 0 END) AS total_pos,
			COALESCE(SUM(r.sell_price), 0.00) AS total_order_value,
			COALESCE(SUM(r.actual_payment), 0.00) AS total_payments,
			COALESCE(SUM(r.balance_payment_due), 0.00) AS total_outstanding
		FROM {$tables['customers']} c
		LEFT JOIN {$tables['records']} r ON c.id = r.customer_id {$archived_cond} {$date_cond}
		GROUP BY c.id
		ORDER BY total_order_value DESC, c.company_name ASC";

		$raw_rows = $wpdb->get_results(
			$params ? $wpdb->prepare( $sql, $params ) : $sql,
			ARRAY_A
		);

		$summary = array(
			'total_customers'   => count( $raw_rows ),
			'total_order_value' => 0.00,
			'total_payments'    => 0.00,
			'total_outstanding' => 0.00,
		);

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$order_val   = (float) $row['total_order_value'];
				$payments    = (float) $row['total_payments'];
				$outstanding = (float) $row['total_outstanding'];

				$summary['total_order_value'] += $order_val;
				$summary['total_payments']    += $payments;
				$summary['total_outstanding'] += $outstanding;

				$rows[] = array(
					'id'                => (int) $row['id'],
					'customer_name'     => $row['name'],
					'company_name'      => ! empty( $row['company_name'] ) ? $row['company_name'] : $row['name'],
					'email'             => $row['email'],
					'phone'             => $row['phone'],
					'status'            => $row['customer_status'],
					'total_records'     => (int) $row['total_records'],
					'total_tenders'     => (int) $row['total_tenders'],
					'total_pos'         => (int) $row['total_pos'],
					'total_order_value' => $order_val,
					'total_payments'    => $payments,
					'total_outstanding' => $outstanding,
				);
			}
		}

		return array(
			'type'    => 'customer',
			'title'   => __( 'Customer Report', 'tender-po-management' ),
			'summary' => $summary,
			'rows'    => $rows,
		);
	}

	/**
	 * 5. Partner Company Report: Partner shares, commission totals, and collaboration.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array Partner report data.
	 */
	public function get_partner_report( array $filters ): array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();

		$archived_cond = ! empty( $filters['include_archived'] ) ? '' : 'AND r.is_archived = 0';
		$date_cond     = '';
		$params        = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$date_cond .= ' AND r.created_at >= %s';
			$params[]   = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$date_cond .= ' AND r.created_at <= %s';
			$params[]   = $filters['date_to'] . ' 23:59:59';
		}
		if ( ! empty( $filters['partner_id'] ) ) {
			$date_cond .= ' AND p.id = %d';
			$params[]   = $filters['partner_id'];
		}

		$sql = "SELECT 
			p.id,
			p.company_name,
			p.contact_person,
			p.email,
			p.phone,
			p.status AS partner_status,
			COUNT(r.id) AS total_records,
			COALESCE(SUM(r.sell_price), 0.00) AS total_sales_value,
			COALESCE(SUM( (r.sell_price * r.partner_percentage) / 100 ), 0.00) AS total_partner_share,
			COALESCE(AVG(NULLIF(r.partner_percentage, 0)), 0.00) AS avg_percentage
		FROM {$tables['partner_companies']} p
		LEFT JOIN {$tables['records']} r ON p.id = r.partner_company_id {$archived_cond} {$date_cond}
		GROUP BY p.id
		ORDER BY total_sales_value DESC, p.company_name ASC";

		$raw_rows = $wpdb->get_results(
			$params ? $wpdb->prepare( $sql, $params ) : $sql,
			ARRAY_A
		);

		$summary = array(
			'total_partners'      => count( $raw_rows ),
			'total_sales_value'   => 0.00,
			'total_partner_share' => 0.00,
		);

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$sales_val     = (float) $row['total_sales_value'];
				$partner_share = (float) $row['total_partner_share'];

				$summary['total_sales_value']   += $sales_val;
				$summary['total_partner_share'] += $partner_share;

				$rows[] = array(
					'id'                  => (int) $row['id'],
					'company_name'        => $row['company_name'],
					'contact_person'      => ! empty( $row['contact_person'] ) ? $row['contact_person'] : '-',
					'email'               => $row['email'],
					'phone'               => $row['phone'],
					'status'              => $row['partner_status'],
					'total_records'       => (int) $row['total_records'],
					'total_sales_value'   => $sales_val,
					'total_partner_share' => $partner_share,
					'avg_percentage'      => (float) $row['avg_percentage'],
				);
			}
		}

		return array(
			'type'    => 'partner',
			'title'   => __( 'Partner Company Report', 'tender-po-management' ),
			'summary' => $summary,
			'rows'    => $rows,
		);
	}

	/**
	 * 6. Payment Report: Payment ledger tracking all actual transactions.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array Payment report data.
	 */
	public function get_payment_report( array $filters ): array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();

		$where_clauses = array( '1=1' );
		$params        = array();

		if ( empty( $filters['include_archived'] ) ) {
			$where_clauses[] = 'r.is_archived = 0';
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'pm.payment_date >= %s';
			$params[]        = $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'pm.payment_date <= %s';
			$params[]        = $filters['date_to'];
		}
		if ( ! empty( $filters['customer_id'] ) ) {
			$where_clauses[] = 'r.customer_id = %d';
			$params[]        = $filters['customer_id'];
		}
		if ( ! empty( $filters['partner_id'] ) ) {
			$where_clauses[] = 'r.partner_company_id = %d';
			$params[]        = $filters['partner_id'];
		}
		if ( ! empty( $filters['record_type'] ) && 'all' !== $filters['record_type'] ) {
			$where_clauses[] = 'r.record_type = %s';
			$params[]        = $filters['record_type'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		$summary_sql = "SELECT 
			COUNT(pm.id) AS total_transactions,
			COALESCE(SUM(pm.payment_amount), 0.00) AS total_payments_amount
		FROM {$tables['payments']} pm
		INNER JOIN {$tables['records']} r ON pm.record_id = r.id
		{$where_sql}";

		$raw_summary = $wpdb->get_row(
			$params ? $wpdb->prepare( $summary_sql, $params ) : $summary_sql,
			ARRAY_A
		);

		$summary = array(
			'total_transactions'    => (int) ( $raw_summary['total_transactions'] ?? 0 ),
			'total_payments_amount' => (float) ( $raw_summary['total_payments_amount'] ?? 0.00 ),
		);

		$rows_sql = "SELECT 
			pm.id,
			pm.payment_amount,
			pm.payment_date,
			pm.payment_method,
			pm.reference_number,
			pm.notes,
			pm.created_at,
			r.id AS record_id,
			r.record_type,
			r.tender_number,
			r.customer_po_number,
			r.sell_price,
			r.balance_payment_due,
			c.company_name AS customer_company,
			c.name AS customer_name,
			u.display_name AS user_name
		FROM {$tables['payments']} pm
		INNER JOIN {$tables['records']} r ON pm.record_id = r.id
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$wpdb->users} u ON pm.created_by = u.ID
		{$where_sql}
		ORDER BY pm.payment_date DESC, pm.id DESC";

		$raw_rows = $wpdb->get_results(
			$params ? $wpdb->prepare( $rows_sql, $params ) : $rows_sql,
			ARRAY_A
		);

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$ref_number = ! empty( $row['tender_number'] ) ? $row['tender_number'] : ( ! empty( $row['customer_po_number'] ) ? $row['customer_po_number'] : '#' . $row['record_id'] );

				$rows[] = array(
					'id'                  => (int) $row['id'],
					'record_id'           => (int) $row['record_id'],
					'record_number'       => $ref_number,
					'record_type'         => $row['record_type'],
					'customer'            => ! empty( $row['customer_company'] ) ? $row['customer_company'] : ( ! empty( $row['customer_name'] ) ? $row['customer_name'] : '-' ),
					'payment_amount'      => (float) $row['payment_amount'],
					'payment_date'        => $row['payment_date'],
					'payment_method'      => ! empty( $row['payment_method'] ) ? $row['payment_method'] : 'bank_transfer',
					'reference_number'    => ! empty( $row['reference_number'] ) ? $row['reference_number'] : '-',
					'sell_price'          => (float) $row['sell_price'],
					'balance_payment_due' => (float) $row['balance_payment_due'],
					'notes'               => $row['notes'],
					'recorded_by'         => ! empty( $row['user_name'] ) ? $row['user_name'] : 'System',
				);
			}
		}

		return array(
			'type'    => 'payment',
			'title'   => __( 'Payment Report', 'tender-po-management' ),
			'summary' => $summary,
			'rows'    => $rows,
		);
	}

	/**
	 * 7. Status Report: Grouped distribution by operational status.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array Status report data.
	 */
	public function get_status_report( array $filters ): array {
		global $wpdb;

		$tables = TPM_Database::get_table_names();
		$where  = $this->build_records_where_clause( $filters );

		$sql = "SELECT 
			r.status,
			COUNT(r.id) AS record_count,
			SUM(CASE WHEN r.record_type = 'tender' THEN 1 ELSE 0 END) AS tender_count,
			SUM(CASE WHEN r.record_type = 'purchase_order' THEN 1 ELSE 0 END) AS po_count,
			COALESCE(SUM(r.purchase_price), 0.00) AS purchase_total,
			COALESCE(SUM(r.sell_price), 0.00) AS sell_total,
			COALESCE(SUM(r.sell_price - r.purchase_price), 0.00) AS gross_profit_total
		FROM {$tables['records']} r
		LEFT JOIN {$tables['customers']} c ON r.customer_id = c.id
		LEFT JOIN {$tables['partner_companies']} p ON r.partner_company_id = p.id
		{$where['sql']}
		GROUP BY r.status
		ORDER BY record_count DESC";

		$raw_rows = $wpdb->get_results(
			$where['params'] ? $wpdb->prepare( $sql, $where['params'] ) : $sql,
			ARRAY_A
		);

		// Calculate grand total for percentages.
		$grand_total_records = 0;
		$grand_total_sell    = 0.00;
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$grand_total_records += (int) $row['record_count'];
				$grand_total_sell    += (float) $row['sell_total'];
			}
		}

		$rows = array();
		if ( ! empty( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$cnt  = (int) $row['record_count'];
				$sell = (float) $row['sell_total'];
				$pct  = $grand_total_records > 0 ? ( ( $cnt / $grand_total_records ) * 100 ) : 0.0;

				$rows[] = array(
					'status'             => $row['status'],
					'record_count'       => $cnt,
					'tender_count'       => (int) $row['tender_count'],
					'po_count'           => (int) $row['po_count'],
					'purchase_total'     => (float) $row['purchase_total'],
					'sell_total'         => $sell,
					'gross_profit_total' => (float) $row['gross_profit_total'],
					'share_percentage'   => $pct,
				);
			}
		}

		return array(
			'type'    => 'status',
			'title'   => __( 'Status Distribution Report', 'tender-po-management' ),
			'summary' => array(
				'total_statuses'      => count( $rows ),
				'grand_total_records' => $grand_total_records,
				'grand_total_sell'    => $grand_total_sell,
			),
			'rows'    => $rows,
		);
	}

	/**
	 * Build parameterized SQL WHERE clause for records queries.
	 *
	 * @since  1.0.0
	 * @param  array $filters Sanitized filters.
	 * @return array Array with 'sql' string and 'params' array.
	 */
	private function build_records_where_clause( array $filters ): array {
		$clauses = array( '1=1' );
		$params  = array();

		// Archive exclusion.
		if ( empty( $filters['include_archived'] ) ) {
			$clauses[] = 'r.is_archived = 0';
		}

		// Record type filter.
		if ( ! empty( $filters['record_type'] ) && 'all' !== $filters['record_type'] ) {
			$clauses[] = 'r.record_type = %s';
			$params[]  = $filters['record_type'];
		}

		// Status filter.
		if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
			$clauses[] = 'r.status = %s';
			$params[]  = $filters['status'];
		}

		// Customer filter.
		if ( ! empty( $filters['customer_id'] ) ) {
			$clauses[] = 'r.customer_id = %d';
			$params[]  = $filters['customer_id'];
		}

		// Partner filter.
		if ( ! empty( $filters['partner_id'] ) ) {
			$clauses[] = 'r.partner_company_id = %d';
			$params[]  = $filters['partner_id'];
		}

		// Date from.
		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = 'r.created_at >= %s';
			$params[]  = $filters['date_from'] . ' 00:00:00';
		}

		// Date to.
		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'r.created_at <= %s';
			$params[]  = $filters['date_to'] . ' 23:59:59';
		}

		return array(
			'sql'    => 'WHERE ' . implode( ' AND ', $clauses ),
			'params' => $params,
		);
	}

	/**
	 * Export report data as a safe, Excel-compatible CSV file.
	 *
	 * - Escapes formula injection characters (=, +, -, @, \t, \r).
	 * - Includes UTF-8 BOM (\xEF\xBB\xBF) for automatic Excel character recognition.
	 * - Outputs HTTP headers for download attachment.
	 *
	 * @since  1.0.0
	 * @param  string $report_type Report type slug.
	 * @param  array  $filters     Sanitized filters.
	 * @return void
	 */
	public function export_csv( string $report_type, array $filters ): void {
		$report = $this->get_report_data( $report_type, $filters );

		$filename = sprintf(
			'tpm-%s-report-%s.csv',
			sanitize_file_name( $report_type ),
			gmdate( 'Y-m-d-His' )
		);

		// Output download headers.
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Cache-Control: max-age=0, no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to generate CSV export.', 'tender-po-management' ) );
		}

		// Write UTF-8 BOM for Microsoft Excel compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Metadata banner row.
		fputcsv( $output, array( 'Tender & PO Management System - ' . $report['title'] ) );
		fputcsv( $output, array( 'Generated Date', gmdate( 'Y-m-d H:i:s' ) . ' UTC' ) );
		fputcsv( $output, array( 'Date From', ! empty( $filters['date_from'] ) ? $filters['date_from'] : 'All Time' ) );
		fputcsv( $output, array( 'Date To', ! empty( $filters['date_to'] ) ? $filters['date_to'] : 'All Time' ) );
		fputcsv( $output, array() ); // Empty line.

		// Render columns and rows based on report type.
		$this->write_csv_content( $output, $report_type, $report );

		fclose( $output );
		exit;
	}

	/**
	 * Write column headers, sanitized data rows, and summary totals to the CSV handle.
	 *
	 * @since  1.0.0
	 * @param  resource $output      File pointer to php://output.
	 * @param  string   $report_type Report type.
	 * @param  array    $report      Report payload.
	 * @return void
	 */
	public function write_csv_content( $output, string $report_type, array $report ): void {
		switch ( $report_type ) {
			case 'tender':
				$headers = array( 'ID', 'Tender Number', 'Customer', 'Partner Company', 'Status', 'Due Date', 'Submission Date', 'Purchase Price', 'Sell Price', 'Expenses', 'Gross Profit', 'Net Profit', 'Created Date' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['id'],
							$this->escape_csv_cell( $r['tender_number'] ),
							$this->escape_csv_cell( $r['customer'] ),
							$this->escape_csv_cell( $r['partner'] ),
							$r['status'],
							$r['tender_due_date'] ?: '-',
							$r['tender_submission_date'] ?: '-',
							number_format( $r['purchase_price'], 2, '.', '' ),
							number_format( $r['sell_price'], 2, '.', '' ),
							number_format( $r['expenses'], 2, '.', '' ),
							number_format( $r['gross_profit'], 2, '.', '' ),
							number_format( $r['net_profit'], 2, '.', '' ),
							$r['created_at'],
						)
					);
				}
				// Summary totals row.
				fputcsv(
					$output,
					array(
						'TOTALS',
						$report['summary']['total_tenders'] . ' Tenders',
						'',
						'',
						'',
						'',
						'',
						number_format( $report['summary']['purchase_total'], 2, '.', '' ),
						number_format( $report['summary']['sell_total'], 2, '.', '' ),
						number_format( $report['summary']['expenses_total'], 2, '.', '' ),
						number_format( $report['summary']['gross_profit'], 2, '.', '' ),
						number_format( $report['summary']['net_profit'], 2, '.', '' ),
						'',
					)
				);
				break;

			case 'purchase_order':
				$headers = array( 'ID', 'PO Number', 'PO Name', 'Customer', 'Partner Company', 'Status', 'PO Date', 'Payment Due Date', 'Shipping Date', 'Purchase Price', 'Sell Price', 'Actual Payments', 'Balance Due', 'Created Date' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['id'],
							$this->escape_csv_cell( $r['po_number'] ),
							$this->escape_csv_cell( $r['po_name'] ),
							$this->escape_csv_cell( $r['customer'] ),
							$this->escape_csv_cell( $r['partner'] ),
							$r['status'],
							$r['po_date'] ?: '-',
							$r['payment_due_date'] ?: '-',
							$r['shipping_date'] ?: '-',
							number_format( $r['purchase_price'], 2, '.', '' ),
							number_format( $r['sell_price'], 2, '.', '' ),
							number_format( $r['actual_payment'], 2, '.', '' ),
							number_format( $r['balance_payment_due'], 2, '.', '' ),
							$r['created_at'],
						)
					);
				}
				// Summary totals row.
				fputcsv(
					$output,
					array(
						'TOTALS',
						$report['summary']['total_pos'] . ' POs',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						number_format( $report['summary']['purchase_total'], 2, '.', '' ),
						number_format( $report['summary']['sell_total'], 2, '.', '' ),
						number_format( $report['summary']['payments_total'], 2, '.', '' ),
						number_format( $report['summary']['outstanding_total'], 2, '.', '' ),
						'',
					)
				);
				break;

			case 'customer':
				$headers = array( 'ID', 'Company Name', 'Customer Name', 'Email', 'Phone', 'Status', 'Total Records', 'Tenders', 'POs', 'Total Order Value', 'Payments Collected', 'Outstanding Balance' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['id'],
							$this->escape_csv_cell( $r['company_name'] ),
							$this->escape_csv_cell( $r['customer_name'] ),
							$this->escape_csv_cell( $r['email'] ),
							$this->escape_csv_cell( $r['phone'] ),
							$r['status'],
							$r['total_records'],
							$r['total_tenders'],
							$r['total_pos'],
							number_format( $r['total_order_value'], 2, '.', '' ),
							number_format( $r['total_payments'], 2, '.', '' ),
							number_format( $r['total_outstanding'], 2, '.', '' ),
						)
					);
				}
				// Summary totals row.
				fputcsv(
					$output,
					array(
						'TOTALS',
						$report['summary']['total_customers'] . ' Customers',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						number_format( $report['summary']['total_order_value'], 2, '.', '' ),
						number_format( $report['summary']['total_payments'], 2, '.', '' ),
						number_format( $report['summary']['total_outstanding'], 2, '.', '' ),
					)
				);
				break;

			case 'partner':
				$headers = array( 'ID', 'Company Name', 'Contact Person', 'Email', 'Phone', 'Status', 'Associated Records', 'Total Sales Value', 'Avg Partner %', 'Estimated Partner Share' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['id'],
							$this->escape_csv_cell( $r['company_name'] ),
							$this->escape_csv_cell( $r['contact_person'] ),
							$this->escape_csv_cell( $r['email'] ),
							$this->escape_csv_cell( $r['phone'] ),
							$r['status'],
							$r['total_records'],
							number_format( $r['total_sales_value'], 2, '.', '' ),
							number_format( $r['avg_percentage'], 2, '.', '' ) . '%',
							number_format( $r['total_partner_share'], 2, '.', '' ),
						)
					);
				}
				// Summary totals row.
				fputcsv(
					$output,
					array(
						'TOTALS',
						$report['summary']['total_partners'] . ' Partners',
						'',
						'',
						'',
						'',
						'',
						number_format( $report['summary']['total_sales_value'], 2, '.', '' ),
						'',
						number_format( $report['summary']['total_partner_share'], 2, '.', '' ),
					)
				);
				break;

			case 'payment':
				$headers = array( 'ID', 'Payment Date', 'Record Number', 'Type', 'Customer', 'Method', 'Reference #', 'Amount Paid', 'Order Sell Price', 'Remaining Balance', 'Recorded By' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['id'],
							$r['payment_date'],
							$this->escape_csv_cell( $r['record_number'] ),
							$r['record_type'],
							$this->escape_csv_cell( $r['customer'] ),
							$r['payment_method'],
							$this->escape_csv_cell( $r['reference_number'] ),
							number_format( $r['payment_amount'], 2, '.', '' ),
							number_format( $r['sell_price'], 2, '.', '' ),
							number_format( $r['balance_payment_due'], 2, '.', '' ),
							$this->escape_csv_cell( $r['recorded_by'] ),
						)
					);
				}
				// Summary totals row.
				fputcsv(
					$output,
					array(
						'TOTALS',
						$report['summary']['total_transactions'] . ' Transactions',
						'',
						'',
						'',
						'',
						'',
						number_format( $report['summary']['total_payments_amount'], 2, '.', '' ),
						'',
						'',
						'',
					)
				);
				break;

			case 'status':
				$headers = array( 'Status', 'Total Records', 'Tenders', 'Purchase Orders', 'Purchase Total', 'Sell Total', 'Gross Profit', 'Share of Total %' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['status'],
							$r['record_count'],
							$r['tender_count'],
							$r['po_count'],
							number_format( $r['purchase_total'], 2, '.', '' ),
							number_format( $r['sell_total'], 2, '.', '' ),
							number_format( $r['gross_profit_total'], 2, '.', '' ),
							number_format( $r['share_percentage'], 1, '.', '' ) . '%',
						)
					);
				}
				// Grand totals row.
				fputcsv(
					$output,
					array(
						'GRAND TOTAL',
						$report['summary']['grand_total_records'],
						'',
						'',
						'',
						number_format( $report['summary']['grand_total_sell'], 2, '.', '' ),
						'',
						'100.0%',
					)
				);
				break;

			case 'financial':
			default:
				$headers = array( 'ID', 'Record Number', 'Type', 'Customer', 'Partner Company', 'Status', 'Purchase Price', 'Sell Price', 'Expenses', 'Payments Received', 'Balance Due', 'Gross Profit', 'Net Profit', 'Margin %', 'Created Date' );
				fputcsv( $output, $headers );
				foreach ( $report['rows'] as $r ) {
					fputcsv(
						$output,
						array(
							$r['id'],
							$this->escape_csv_cell( $r['record_number'] ),
							$r['record_type'],
							$this->escape_csv_cell( $r['customer'] ),
							$this->escape_csv_cell( $r['partner'] ),
							$r['status'],
							number_format( $r['purchase_price'], 2, '.', '' ),
							number_format( $r['sell_price'], 2, '.', '' ),
							number_format( $r['expenses'], 2, '.', '' ),
							number_format( $r['actual_payment'], 2, '.', '' ),
							number_format( $r['balance_payment_due'], 2, '.', '' ),
							number_format( $r['gross_profit'], 2, '.', '' ),
							number_format( $r['net_profit'], 2, '.', '' ),
							number_format( $r['margin_percentage'], 1, '.', '' ) . '%',
							$r['created_at'],
						)
					);
				}
				// Summary totals row.
				fputcsv(
					$output,
					array(
						'TOTALS',
						$report['summary']['total_records'] . ' Records',
						'',
						'',
						'',
						'',
						number_format( $report['summary']['purchase_total'], 2, '.', '' ),
						number_format( $report['summary']['sell_total'], 2, '.', '' ),
						number_format( $report['summary']['expenses_total'], 2, '.', '' ),
						number_format( $report['summary']['payments_total'], 2, '.', '' ),
						number_format( $report['summary']['outstanding_total'], 2, '.', '' ),
						number_format( $report['summary']['gross_profit'], 2, '.', '' ),
						number_format( $report['summary']['net_profit'], 2, '.', '' ),
						number_format( $report['summary']['margin_percentage'], 1, '.', '' ) . '%',
						'',
					)
				);
				break;
		}
	}

	/**
	 * Escape cell content against CSV Formula Injection vulnerabilities.
	 *
	 * If a string begins with =, +, -, @, \t, or \r, a single quote (') is prepended.
	 *
	 * @since  1.0.0
	 * @param  mixed $cell Cell value.
	 * @return string Safe cell content.
	 */
	public function escape_csv_cell( $cell ): string {
		if ( null === $cell ) {
			return '';
		}

		$cell = (string) $cell;

		if ( '' !== $cell ) {
			$first_char = $cell[0];
			if ( in_array( $first_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				return "'" . $cell;
			}
		}

		return $cell;
	}
}
