<?php
/**
 * Service: Management Dashboard
 *
 * Provides high-performance aggregated metrics, financial summaries,
 * operational alerts, recent records, and upcoming milestone dates.
 * Optimized with single-pass conditional aggregate SQL queries.
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
 * TPM_Dashboard_Service Class.
 *
 * @since 1.0.0
 */
class TPM_Dashboard_Service {

	/**
	 * Fetch Summary Cards metrics (single-pass query).
	 *
	 * Metrics:
	 * - Total Records (active)
	 * - Total Tenders (active)
	 * - Total Purchase Orders (active)
	 * - Pending (draft, review, pending)
	 * - Submitted (submitted, confirmed)
	 * - Complete (awarded, delivered, settled, complete)
	 * - Archived
	 *
	 * @since 1.0.0
	 * @return array{total_records: int, total_tenders: int, total_pos: int, pending: int, submitted: int, complete: int, archived: int}
	 */
	public static function get_summary_cards(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';

		$sql = "SELECT 
					COUNT(CASE WHEN is_archived = 0 THEN 1 END) AS total_records,
					COUNT(CASE WHEN is_archived = 0 AND record_type = 'tender' THEN 1 END) AS total_tenders,
					COUNT(CASE WHEN is_archived = 0 AND record_type = 'po' THEN 1 END) AS total_pos,
					COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%draft%' OR status LIKE '%review%' OR status LIKE '%pending%') THEN 1 END) AS count_pending,
					COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%submitted%' OR status LIKE '%confirmed%') THEN 1 END) AS count_submitted,
					COUNT(CASE WHEN is_archived = 0 AND (status LIKE '%complete%' OR status LIKE '%awarded%' OR status LIKE '%settled%' OR status LIKE '%delivered%') THEN 1 END) AS count_complete,
					COUNT(CASE WHEN is_archived = 1 THEN 1 END) AS count_archived
				FROM `{$table}`";

		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'total_records' => (int) ( $row->total_records ?? 0 ),
			'total_tenders' => (int) ( $row->total_tenders ?? 0 ),
			'total_pos'     => (int) ( $row->total_pos ?? 0 ),
			'pending'       => (int) ( $row->count_pending ?? 0 ),
			'submitted'     => (int) ( $row->count_submitted ?? 0 ),
			'complete'      => (int) ( $row->count_complete ?? 0 ),
			'archived'      => (int) ( $row->count_archived ?? 0 ),
		);
	}

	/**
	 * Fetch Financial Summary metrics across active records (single-pass query).
	 *
	 * Metrics:
	 * - Total Purchase Amount
	 * - Total Sell Amount
	 * - Total Expenses
	 * - Total Actual Payments
	 * - Total Outstanding
	 * - Estimated Gross Profit (Sell - Purchase)
	 * - Estimated Net Profit (Sell - Purchase - Expenses)
	 *
	 * @since 1.0.0
	 * @return array{total_purchase: float, total_sell: float, total_expenses: float, total_actual_payments: float, total_outstanding: float, estimated_gross_profit: float, estimated_net_profit: float}
	 */
	public static function get_financial_summary(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tpm_records';

		$sql = "SELECT 
					COALESCE(SUM(purchase_price), 0.0) AS total_purchase,
					COALESCE(SUM(sell_price), 0.0) AS total_sell,
					COALESCE(SUM(expenses), 0.0) AS total_expenses,
					COALESCE(SUM(actual_payment), 0.0) AS total_actual_payments,
					COALESCE(SUM(balance_payment_due), 0.0) AS total_outstanding,
					COALESCE(SUM(sell_price - purchase_price), 0.0) AS estimated_gross_profit,
					COALESCE(SUM(sell_price - purchase_price - expenses), 0.0) AS estimated_net_profit
				FROM `{$table}`
				WHERE is_archived = 0";

		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'total_purchase'         => (float) ( $row->total_purchase ?? 0.0 ),
			'total_sell'             => (float) ( $row->total_sell ?? 0.0 ),
			'total_expenses'         => (float) ( $row->total_expenses ?? 0.0 ),
			'total_actual_payments'  => (float) ( $row->total_actual_payments ?? 0.0 ),
			'total_outstanding'      => (float) ( $row->total_outstanding ?? 0.0 ),
			'estimated_gross_profit' => (float) ( $row->estimated_gross_profit ?? 0.0 ),
			'estimated_net_profit'   => (float) ( $row->estimated_net_profit ?? 0.0 ),
		);
	}

	/**
	 * Fetch actionable operational alerts.
	 *
	 * Alerts:
	 * - Tender Due Soon: Tenders due in <= 7 days, not complete
	 * - Tender Past Due: Tenders past due date, not complete
	 * - Quote Expiring Soon: Quotes expiring in <= 7 days, not complete
	 * - Payment Overdue: Payment due date passed with balance > 0
	 * - Missing Attachments: Active records with 0 attachments
	 * - Pending Shipping: Purchase orders missing shipping date or AWB
	 *
	 * @since 1.0.0
	 * @param string|null $current_date Optional reference date (YYYY-MM-DD).
	 * @return array<string, array{count: int, label: string, severity: string, icon: string, filter_url: string}>
	 */
	public static function get_alerts( ?string $current_date = null ): array {
		global $wpdb;

		$today            = $current_date ? sanitize_text_field( $current_date ) : current_time( 'Y-m-d' );
		$seven_days_later = date( 'Y-m-d', strtotime( '+7 days', strtotime( $today ) ) );

		$table_records     = $wpdb->prefix . 'tpm_records';
		$table_attachments = $wpdb->prefix . 'tpm_attachments';

		// Single multi-conditional count query for all 6 alerts to avoid multiple roundtrips
		$sql = "SELECT 
					COUNT(CASE 
						WHEN r.record_type = 'tender' 
						 AND r.is_archived = 0 
						 AND r.tender_due_date >= %s 
						 AND r.tender_due_date <= %s 
						 AND r.status NOT IN ('awarded', 'settled', 'complete', 'delivered', 'cancelled')
						THEN 1 END) AS count_tender_due_soon,

					COUNT(CASE 
						WHEN r.record_type = 'tender' 
						 AND r.is_archived = 0 
						 AND r.tender_due_date < %s 
						 AND r.tender_due_date IS NOT NULL 
						 AND r.tender_due_date != '' 
						 AND r.tender_due_date != '0000-00-00'
						 AND r.status NOT IN ('awarded', 'settled', 'complete', 'delivered', 'cancelled')
						THEN 1 END) AS count_tender_past_due,

					COUNT(CASE 
						WHEN r.is_archived = 0 
						 AND r.quote_expiration_date >= %s 
						 AND r.quote_expiration_date <= %s 
						 AND r.status NOT IN ('awarded', 'settled', 'complete', 'delivered', 'cancelled')
						THEN 1 END) AS count_quote_expiring_soon,

					COUNT(CASE 
						WHEN r.is_archived = 0 
						 AND r.payment_due_date < %s 
						 AND r.payment_due_date IS NOT NULL 
						 AND r.payment_due_date != '' 
						 AND r.payment_due_date != '0000-00-00'
						 AND r.balance_payment_due > 0 
						THEN 1 END) AS count_payment_overdue,

					COUNT(CASE 
						WHEN r.is_archived = 0 
						 AND NOT EXISTS (SELECT 1 FROM `{$table_attachments}` a WHERE a.record_id = r.id)
						THEN 1 END) AS count_missing_attachments,

					COUNT(CASE 
						WHEN r.record_type = 'po' 
						 AND r.is_archived = 0 
						 AND (r.shipping_date IS NULL OR r.shipping_date = '' OR r.shipping_date = '0000-00-00' OR r.original_awb_received = 0)
						 AND r.status NOT IN ('delivered', 'complete', 'cancelled')
						THEN 1 END) AS count_pending_shipping
				FROM `{$table_records}` r";

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$today,
				$seven_days_later,
				$today,
				$today,
				$seven_days_later,
				$today
			)
		);

		return array(
			'tender_due_soon'     => array(
				'count'      => (int) ( $row->count_tender_due_soon ?? 0 ),
				'label'      => __( 'Tender Due Soon', 'tender-po-management' ),
				'severity'   => 'warning',
				'icon'       => 'clock',
				'filter_url' => admin_url( 'admin.php?page=tpm-tenders' ),
			),
			'tender_past_due'     => array(
				'count'      => (int) ( $row->count_tender_past_due ?? 0 ),
				'label'      => __( 'Tender Past Due', 'tender-po-management' ),
				'severity'   => 'danger',
				'icon'       => 'warning',
				'filter_url' => admin_url( 'admin.php?page=tpm-tenders' ),
			),
			'quote_expiring_soon' => array(
				'count'      => (int) ( $row->count_quote_expiring_soon ?? 0 ),
				'label'      => __( 'Quote Expiring Soon', 'tender-po-management' ),
				'severity'   => 'warning',
				'icon'       => 'calendar-alt',
				'filter_url' => admin_url( 'admin.php?page=tpm-records' ),
			),
			'payment_overdue'     => array(
				'count'      => (int) ( $row->count_payment_overdue ?? 0 ),
				'label'      => __( 'Payment Overdue', 'tender-po-management' ),
				'severity'   => 'danger',
				'icon'       => 'money-alt',
				'filter_url' => admin_url( 'admin.php?page=tpm-records' ),
			),
			'missing_attachments' => array(
				'count'      => (int) ( $row->count_missing_attachments ?? 0 ),
				'label'      => __( 'Missing Attachments', 'tender-po-management' ),
				'severity'   => 'info',
				'icon'       => 'paperclip',
				'filter_url' => admin_url( 'admin.php?page=tpm-records' ),
			),
			'pending_shipping'    => array(
				'count'      => (int) ( $row->count_pending_shipping ?? 0 ),
				'label'      => __( 'Pending Shipping', 'tender-po-management' ),
				'severity'   => 'info',
				'icon'       => 'location',
				'filter_url' => admin_url( 'admin.php?page=tpm-purchase-orders' ),
			),
		);
	}

	/**
	 * Retrieve Recent Records for the dashboard table.
	 *
	 * @since 1.0.0
	 * @param int $limit Max items to return (default 8).
	 * @return array<object> Recent records list with joined customer info.
	 */
	public static function get_recent_records( int $limit = 8 ): array {
		global $wpdb;

		$table_records   = $wpdb->prefix . 'tpm_records';
		$table_customers = $wpdb->prefix . 'tpm_customers';

		$sql = "SELECT r.id, r.record_type, r.status, r.tender_number, r.customer_po_number,
					   r.sell_price, r.created_at,
					   c.name AS customer_name, c.company_name AS customer_company
				FROM `{$table_records}` r
				LEFT JOIN `{$table_customers}` c ON r.customer_id = c.id
				WHERE r.is_archived = 0
				ORDER BY r.created_at DESC, r.id DESC
				LIMIT %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Retrieve Upcoming Milestone Dates for the dashboard schedule/timeline.
	 *
	 * Milestones tracked:
	 * - Tender Due Date (tender_due_date)
	 * - Quote Expiration (quote_expiration_date)
	 * - Payment Due Date (payment_due_date)
	 * - Shipping Date (shipping_date)
	 *
	 * @since 1.0.0
	 * @param string|null $current_date Optional reference date.
	 * @param int         $limit        Max milestone items to return (default 10).
	 * @return array<array{record_id: int, record_number: string, record_type: string, milestone_type: string, milestone_label: string, date: string, days_diff: int, is_past: bool}>
	 */
	public static function get_upcoming_dates( ?string $current_date = null, int $limit = 10 ): array {
		global $wpdb;

		$today = $current_date ? sanitize_text_field( $current_date ) : current_time( 'Y-m-d' );
		$table = $wpdb->prefix . 'tpm_records';

		// Query records that have active milestone dates within reasonable window (from 30 days ago to 90 days ahead)
		$lookback = date( 'Y-m-d', strtotime( '-30 days', strtotime( $today ) ) );
		$lookahed = date( 'Y-m-d', strtotime( '+90 days', strtotime( $today ) ) );

		$sql = "SELECT id, record_type, tender_number, customer_po_number, status,
					   tender_due_date, quote_expiration_date, payment_due_date, shipping_date
				FROM `{$table}`
				WHERE is_archived = 0 
				  AND status NOT IN ('delivered', 'complete', 'settled', 'cancelled')
				  AND (
					   (tender_due_date >= %s AND tender_due_date <= %s) OR
					   (quote_expiration_date >= %s AND quote_expiration_date <= %s) OR
					   (payment_due_date >= %s AND payment_due_date <= %s) OR
					   (shipping_date >= %s AND shipping_date <= %s)
				  )
				LIMIT 50";

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$lookback,
				$lookahed,
				$lookback,
				$lookahed,
				$lookback,
				$lookahed,
				$lookback,
				$lookahed
			)
		);

		$events = array();

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$rec_id   = (int) $row->id;
				$rec_num  = ! empty( $row->tender_number ) ? $row->tender_number : ( ! empty( $row->customer_po_number ) ? $row->customer_po_number : '#' . $rec_id );
				$rec_type = (string) $row->record_type;

				// 1. Tender Due Date
				if ( ! empty( $row->tender_due_date ) && '0000-00-00' !== $row->tender_due_date ) {
					$events[] = array(
						'record_id'       => $rec_id,
						'record_number'   => $rec_num,
						'record_type'     => $rec_type,
						'milestone_type'  => 'tender_due',
						'milestone_label' => __( 'Tender Due Date', 'tender-po-management' ),
						'date'            => $row->tender_due_date,
					);
				}

				// 2. Quote Expiration
				if ( ! empty( $row->quote_expiration_date ) && '0000-00-00' !== $row->quote_expiration_date ) {
					$events[] = array(
						'record_id'       => $rec_id,
						'record_number'   => $rec_num,
						'record_type'     => $rec_type,
						'milestone_type'  => 'quote_expiry',
						'milestone_label' => __( 'Quote Expiration', 'tender-po-management' ),
						'date'            => $row->quote_expiration_date,
					);
				}

				// 3. Payment Due Date
				if ( ! empty( $row->payment_due_date ) && '0000-00-00' !== $row->payment_due_date ) {
					$events[] = array(
						'record_id'       => $rec_id,
						'record_number'   => $rec_num,
						'record_type'     => $rec_type,
						'milestone_type'  => 'payment_due',
						'milestone_label' => __( 'Payment Due Date', 'tender-po-management' ),
						'date'            => $row->payment_due_date,
					);
				}

				// 4. Shipping Date
				if ( ! empty( $row->shipping_date ) && '0000-00-00' !== $row->shipping_date ) {
					$events[] = array(
						'record_id'       => $rec_id,
						'record_number'   => $rec_num,
						'record_type'     => $rec_type,
						'milestone_type'  => 'shipping_date',
						'milestone_label' => __( 'Shipping Date', 'tender-po-management' ),
						'date'            => $row->shipping_date,
					);
				}
			}
		}

		// Calculate days diff & sort chronologically (closest first)
		$today_ts = strtotime( $today );
		foreach ( $events as $idx => $event ) {
			$event_ts               = strtotime( $event['date'] );
			$diff_days              = (int) round( ( $event_ts - $today_ts ) / DAY_IN_SECONDS );
			$events[ $idx ]['days_diff'] = $diff_days;
			$events[ $idx ]['is_past']   = $diff_days < 0;
		}

		// Sort by date ASC
		usort(
			$events,
			function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		return array_slice( $events, 0, $limit );
	}

	/**
	 * High-level orchestrator: retrieve full dashboard payload in optimized queries.
	 *
	 * @since 1.0.0
	 * @param string|null $current_date Optional reference date.
	 * @return array<string, mixed>
	 */
	public static function get_dashboard_data( ?string $current_date = null ): array {
		return array(
			'summary_cards'     => self::get_summary_cards(),
			'financial_summary' => self::get_financial_summary(),
			'alerts'            => self::get_alerts( $current_date ),
			'recent_records'    => self::get_recent_records( 8 ),
			'upcoming_dates'    => self::get_upcoming_dates( $current_date, 10 ),
		);
	}
}
