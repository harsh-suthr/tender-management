<?php
/**
 * View: Reports & Analytics
 *
 * Provides executive reports across 7 modules (Financial, Tender, Purchase Order,
 * Customer, Partner, Payment, and Status) with multi-criteria filtering,
 * financial intelligence cards, and safe CSV export.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/admin/views
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security capability check.
if ( ! current_user_can( 'view_tpm_reports' ) && ! current_user_can( 'manage_tpm_records' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to view reports.', 'tender-po-management' ) );
}

$report_service = new TPM_Report_Service();
$filters        = $report_service->sanitize_filters( $_GET );
$report_type    = $filters['report_type'];
$report         = $report_service->get_report_data( $report_type, $filters );
$filter_options  = $report_service->get_filter_options();
$currency_symbol = TPM_Settings_Service::get_currency_symbol();

// Construct CSV Export URL with active filters and nonce.
$export_args = array_merge(
	array(
		'action'    => 'tpm_export_report',
		'tpm_nonce' => wp_create_nonce( 'tpm_export_report' ),
	),
	$filters
);
$export_url = add_query_arg( $export_args, admin_url( 'admin-post.php' ) );
?>
<div class="wrap tpm-admin-wrap">

	<!-- Header Banner -->
	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'Reports & Analytics', 'tender-po-management' ); ?>
				<span class="tpm-version-badge"><?php esc_html_e( 'Intelligence', 'tender-po-management' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Financial intelligence, procurement conversion metrics, balance payments due, and exportable logs.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<a href="<?php echo esc_url( $export_url ); ?>" class="tpm-btn tpm-btn-primary" id="tpm-export-csv-btn">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'tender-po-management' ); ?>
			</a>
		</div>
	</div>

	<!-- Report Type Tabs -->
	<nav class="nav-tab-wrapper tpm-reports-tabs" style="margin-bottom: 20px;">
		<?php
		$tab_icons = array(
			'financial'      => 'dashicons-chart-area',
			'tender'         => 'dashicons-media-document',
			'purchase_order' => 'dashicons-cart',
			'customer'       => 'dashicons-businessperson',
			'partner'        => 'dashicons-groups',
			'payment'        => 'dashicons-money-alt',
			'status'         => 'dashicons-tag',
		);

		foreach ( TPM_Report_Service::REPORT_TYPES as $type_key => $type_title ) :
			$is_active = ( $report_type === $type_key );
			$tab_url   = add_query_arg(
				array(
					'page'        => 'tpm-reports',
					'report_type' => $type_key,
				),
				admin_url( 'admin.php' )
			);
			$icon = $tab_icons[ $type_key ] ?? 'dashicons-chart-pie';
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="margin-right: 4px; vertical-align: text-top;"></span>
				<?php echo esc_html( $type_title ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<!-- Filter Controls Bar -->
	<div class="tpm-card tpm-filter-bar-card" style="margin-bottom: 25px; padding: 18px 20px;">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tpm-filter-form" id="tpm-report-filters-form">
			<input type="hidden" name="page" value="tpm-reports" />
			<input type="hidden" name="report_type" value="<?php echo esc_attr( $report_type ); ?>" />

			<div class="tpm-filter-grid">
				<!-- Date Range -->
				<div class="tpm-filter-group">
					<label for="date_from"><?php esc_html_e( 'Date From', 'tender-po-management' ); ?></label>
					<input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" class="tpm-input" />
				</div>

				<div class="tpm-filter-group">
					<label for="date_to"><?php esc_html_e( 'Date To', 'tender-po-management' ); ?></label>
					<input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" class="tpm-input" />
				</div>

				<!-- Record Type Filter (when applicable) -->
				<?php if ( in_array( $report_type, array( 'financial', 'payment', 'status' ), true ) ) : ?>
					<div class="tpm-filter-group">
						<label for="record_type"><?php esc_html_e( 'Record Type', 'tender-po-management' ); ?></label>
						<select id="record_type" name="record_type" class="tpm-select">
							<option value="all" <?php selected( $filters['record_type'], 'all' ); ?>><?php esc_html_e( 'All Types', 'tender-po-management' ); ?></option>
							<option value="tender" <?php selected( $filters['record_type'], 'tender' ); ?>><?php esc_html_e( 'Tenders Only', 'tender-po-management' ); ?></option>
							<option value="purchase_order" <?php selected( $filters['record_type'], 'purchase_order' ); ?>><?php esc_html_e( 'Purchase Orders Only', 'tender-po-management' ); ?></option>
						</select>
					</div>
				<?php endif; ?>

				<!-- Status Filter -->
				<?php if ( 'status' !== $report_type ) : ?>
					<div class="tpm-filter-group">
						<label for="status"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></label>
						<select id="status" name="status" class="tpm-select">
							<option value="all" <?php selected( $filters['status'], 'all' ); ?>><?php esc_html_e( 'All Statuses', 'tender-po-management' ); ?></option>
							<?php foreach ( $filter_options['statuses'] as $s ) : ?>
								<option value="<?php echo esc_attr( $s['slug'] ); ?>" <?php selected( $filters['status'], $s['slug'] ); ?>>
									<?php echo esc_html( $s['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<!-- Customer Filter -->
				<div class="tpm-filter-group">
					<label for="customer_id"><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></label>
					<select id="customer_id" name="customer_id" class="tpm-select">
						<option value="0"><?php esc_html_e( 'All Customers', 'tender-po-management' ); ?></option>
						<?php foreach ( $filter_options['customers'] as $c ) : ?>
							<?php $c_label = ! empty( $c['company_name'] ) ? $c['company_name'] . ' (' . $c['name'] . ')' : $c['name']; ?>
							<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $filters['customer_id'], $c['id'] ); ?>>
								<?php echo esc_html( $c_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Partner Filter -->
				<?php if ( 'customer' !== $report_type ) : ?>
					<div class="tpm-filter-group">
						<label for="partner_id"><?php esc_html_e( 'Partner Company', 'tender-po-management' ); ?></label>
						<select id="partner_id" name="partner_id" class="tpm-select">
							<option value="0"><?php esc_html_e( 'All Partners', 'tender-po-management' ); ?></option>
							<?php foreach ( $filter_options['partners'] as $p ) : ?>
								<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $filters['partner_id'], $p['id'] ); ?>>
									<?php echo esc_html( $p['company_name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
			</div>

			<!-- Filter Actions & Quick Date Presets -->
			<div class="tpm-filter-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 12px; border-top: 1px solid #eee; flex-wrap: wrap; gap: 10px;">
				<div class="tpm-date-presets" style="display: flex; gap: 6px; align-items: center;">
					<span style="font-size: 12px; color: #646970; font-weight: 600; margin-right: 4px;"><?php esc_html_e( 'Quick Dates:', 'tender-po-management' ); ?></span>
					<button type="button" class="button button-small tpm-preset-btn" data-range="this_month"><?php esc_html_e( 'This Month', 'tender-po-management' ); ?></button>
					<button type="button" class="button button-small tpm-preset-btn" data-range="last_month"><?php esc_html_e( 'Last Month', 'tender-po-management' ); ?></button>
					<button type="button" class="button button-small tpm-preset-btn" data-range="this_quarter"><?php esc_html_e( 'This Quarter', 'tender-po-management' ); ?></button>
					<button type="button" class="button button-small tpm-preset-btn" data-range="this_year"><?php esc_html_e( 'This Year', 'tender-po-management' ); ?></button>
					<button type="button" class="button button-small tpm-preset-btn" data-range="all_time"><?php esc_html_e( 'All Time', 'tender-po-management' ); ?></button>
				</div>

				<div class="tpm-filter-submit-group" style="display: flex; gap: 8px; align-items: center;">
					<label style="font-size: 13px; margin-right: 6px; cursor: pointer;">
						<input type="checkbox" name="include_archived" value="1" <?php checked( $filters['include_archived'], 1 ); ?> />
						<?php esc_html_e( 'Include Archived', 'tender-po-management' ); ?>
					</label>
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-filter" style="margin-top: 4px;"></span>
						<?php esc_html_e( 'Generate Report', 'tender-po-management' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-reports&report_type=' . $report_type ) ); ?>" class="button">
						<?php esc_html_e( 'Reset', 'tender-po-management' ); ?>
					</a>
				</div>
			</div>
		</form>
	</div>

	<!-- ========================================== -->
	<!-- FINANCIAL & SUMMARY METRIC CARDS           -->
	<!-- ========================================== -->
	<?php if ( 'financial' === $report_type ) : ?>
		<div class="tpm-financial-grid" style="margin-bottom: 25px;">
			<!-- Purchase Total -->
			<div class="tpm-financial-card">
				<div class="tpm-card-icon" style="background: #eef2ff; color: #4338ca;">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Purchase Total', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['purchase_total'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext"><?php esc_html_e( 'Total supplier acquisition costs', 'tender-po-management' ); ?></span>
				</div>
			</div>

			<!-- Sell Total -->
			<div class="tpm-financial-card">
				<div class="tpm-card-icon" style="background: #ecfdf5; color: #047857;">
					<span class="dashicons dashicons-chart-bar"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Sell Total', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['sell_total'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext"><?php esc_html_e( 'Total gross customer order value', 'tender-po-management' ); ?></span>
				</div>
			</div>

			<!-- Expenses -->
			<div class="tpm-financial-card">
				<div class="tpm-card-icon" style="background: #fff7ed; color: #c2410c;">
					<span class="dashicons dashicons-clipboard"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Expenses', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['expenses_total'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext"><?php esc_html_e( 'Operational overhead & logistics', 'tender-po-management' ); ?></span>
				</div>
			</div>

			<!-- Actual Payments -->
			<div class="tpm-financial-card">
				<div class="tpm-card-icon" style="background: #f0fdf4; color: #15803d;">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Payments Collected', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['payments_total'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext"><?php esc_html_e( 'Realized cash received', 'tender-po-management' ); ?></span>
				</div>
			</div>

			<!-- Outstanding -->
			<div class="tpm-financial-card">
				<div class="tpm-card-icon" style="background: #fef2f2; color: #b91c1c;">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Outstanding Balance', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['outstanding_total'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext"><?php esc_html_e( 'Receivables balance pending', 'tender-po-management' ); ?></span>
				</div>
			</div>

			<!-- Estimated Gross Profit -->
			<div class="tpm-financial-card tpm-profit-card" style="border-left: 4px solid #3b82f6;">
				<div class="tpm-card-icon" style="background: #eff6ff; color: #1d4ed8;">
					<span class="dashicons dashicons-trending-up"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Gross Profit', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value" style="color: #1d4ed8;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['gross_profit'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext"><?php esc_html_e( 'Sell Total minus Purchase Total', 'tender-po-management' ); ?></span>
				</div>
			</div>

			<!-- Estimated Net Profit -->
			<div class="tpm-financial-card tpm-profit-card" style="border-left: 4px solid #10b981;">
				<div class="tpm-card-icon" style="background: #ecfdf5; color: #047857;">
					<span class="dashicons dashicons-awards"></span>
				</div>
				<div class="tpm-financial-details">
					<span class="tpm-financial-label"><?php esc_html_e( 'Net Profit', 'tender-po-management' ); ?></span>
					<span class="tpm-financial-value" style="color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['net_profit'], 2 ) ); ?></span>
					<span class="tpm-financial-subtext">
						<?php echo esc_html( sprintf( __( 'Margin: %s%%', 'tender-po-management' ), number_format( $report['summary']['margin_percentage'], 1 ) ) ); ?>
					</span>
				</div>
			</div>
		</div>
	<?php elseif ( 'tender' === $report_type ) : ?>
		<div class="tpm-summary-grid" style="margin-bottom: 25px;">
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Tenders', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['total_tenders'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Purchase Total', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['purchase_total'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Sell Total', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['sell_total'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Gross Profit', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #1d4ed8;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['gross_profit'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Net Profit', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['net_profit'], 2 ) ); ?></span>
			</div>
		</div>
	<?php elseif ( 'purchase_order' === $report_type ) : ?>
		<div class="tpm-summary-grid" style="margin-bottom: 25px;">
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Purchase Orders', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['total_pos'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Purchase Total', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['purchase_total'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Sell Total', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['sell_total'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Payments Collected', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['payments_total'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Outstanding Receivables', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #b91c1c;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['outstanding_total'], 2 ) ); ?></span>
			</div>
		</div>
	<?php elseif ( 'customer' === $report_type ) : ?>
		<div class="tpm-summary-grid" style="margin-bottom: 25px;">
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Active Customers', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['total_customers'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Order Value', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['total_order_value'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Payments Collected', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['total_payments'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Balance Outstanding', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #b91c1c;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['total_outstanding'], 2 ) ); ?></span>
			</div>
		</div>
	<?php elseif ( 'partner' === $report_type ) : ?>
		<div class="tpm-summary-grid" style="margin-bottom: 25px;">
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Partner Companies', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['total_partners'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Sales Generated', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['total_sales_value'], 2 ) ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Estimated Partner Share', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #4338ca;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['total_partner_share'], 2 ) ); ?></span>
			</div>
		</div>
	<?php elseif ( 'payment' === $report_type ) : ?>
		<div class="tpm-summary-grid" style="margin-bottom: 25px;">
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Payment Transactions', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['total_transactions'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Total Cash Collected', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['total_payments_amount'], 2 ) ); ?></span>
			</div>
		</div>
	<?php elseif ( 'status' === $report_type ) : ?>
		<div class="tpm-summary-grid" style="margin-bottom: 25px;">
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Statuses Represented', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['total_statuses'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Grand Total Records', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count"><?php echo esc_html( $report['summary']['grand_total_records'] ); ?></span>
			</div>
			<div class="tpm-summary-card">
				<span class="tpm-summary-label"><?php esc_html_e( 'Grand Total Sales Value', 'tender-po-management' ); ?></span>
				<span class="tpm-summary-count" style="font-size: 20px; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['grand_total_sell'], 2 ) ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<!-- ========================================== -->
	<!-- REPORT DATA TABLE                          -->
	<!-- ========================================== -->
	<div class="tpm-card" style="padding: 0; overflow: hidden;">
		<div class="tpm-container-toolbar" style="padding: 16px 20px; background: #fafafa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
			<div class="tpm-toolbar-title" style="font-size: 15px; font-weight: 600;">
				<span class="dashicons dashicons-list-view" style="margin-right: 6px;"></span>
				<?php echo esc_html( $report['title'] ); ?>
			</div>
			<div class="tpm-toolbar-actions">
				<span class="tpm-tag"><?php echo esc_html( count( $report['rows'] ) . ' ' . __( 'Records Found', 'tender-po-management' ) ); ?></span>
			</div>
		</div>

		<?php if ( empty( $report['rows'] ) ) : ?>
			<div class="tpm-empty-state" style="padding: 50px 20px; text-align: center;">
				<div class="tpm-container-empty-icon" style="font-size: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px;">
					<span class="dashicons dashicons-search"></span>
				</div>
				<h3 style="margin: 0 0 8px; color: #1e293b;"><?php esc_html_e( 'No records found for selected criteria', 'tender-po-management' ); ?></h3>
				<p style="margin: 0 0 15px; color: #64748b; font-size: 14px;">
					<?php esc_html_e( 'Try expanding your date range, clearing specific filters, or toggling "Include Archived".', 'tender-po-management' ); ?>
				</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-reports&report_type=' . $report_type ) ); ?>" class="button">
					<?php esc_html_e( 'Reset Filters', 'tender-po-management' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="tpm-table-responsive" style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped">
					<?php if ( 'financial' === $report_type ) : ?>
						<thead>
							<tr>
								<th style="width: 130px;"><?php esc_html_e( 'Record #', 'tender-po-management' ); ?></th>
								<th style="width: 90px;"><?php esc_html_e( 'Type', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Purchase', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Sell', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 90px;"><?php esc_html_e( 'Expenses', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Paid', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Outstanding', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Gross Profit', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Net Profit', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 80px;"><?php esc_html_e( 'Margin', 'tender-po-management' ); ?></th>
								<th style="width: 70px; text-align: center;"><?php esc_html_e( 'View', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['record_number'] ); ?></strong></td>
									<td>
										<span class="tpm-badge <?php echo 'tender' === $row['record_type'] ? 'tpm-badge-primary' : 'tpm-badge-secondary'; ?>">
											<?php echo 'tender' === $row['record_type'] ? esc_html__( 'Tender', 'tender-po-management' ) : esc_html__( 'PO', 'tender-po-management' ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $row['customer'] ); ?></td>
									<td>
										<span class="tpm-status-badge tpm-status-<?php echo esc_attr( $row['status'] ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['status'] ) ) ); ?>
										</span>
									</td>
									<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $row['purchase_price'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( $currency_symbol . number_format( $row['sell_price'], 2 ) ); ?></td>
									<td style="text-align: right; color: #64748b;"><?php echo esc_html( $currency_symbol . number_format( $row['expenses'], 2 ) ); ?></td>
									<td style="text-align: right; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $row['actual_payment'], 2 ) ); ?></td>
									<td style="text-align: right; color: <?php echo $row['balance_payment_due'] > 0 ? '#b91c1c' : '#15803d'; ?>;">
										<?php echo esc_html( $currency_symbol . number_format( $row['balance_payment_due'], 2 ) ); ?>
									</td>
									<td style="text-align: right; font-weight: 600; color: #1d4ed8;"><?php echo esc_html( $currency_symbol . number_format( $row['gross_profit'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600; color: <?php echo $row['net_profit'] >= 0 ? '#047857' : '#b91c1c'; ?>;">
										<?php echo esc_html( $currency_symbol . number_format( $row['net_profit'], 2 ) ); ?>
									</td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $row['margin_percentage'], 1 ) ); ?>%</td>
									<td style="text-align: center;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $row['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'tender-po-management' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr style="font-weight: bold; background: #f8fafc;">
								<td colspan="4"><?php esc_html_e( 'TOTALS', 'tender-po-management' ); ?></td>
								<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['purchase_total'], 2 ) ); ?></td>
								<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['sell_total'], 2 ) ); ?></td>
								<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['expenses_total'], 2 ) ); ?></td>
								<td style="text-align: right; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['payments_total'], 2 ) ); ?></td>
								<td style="text-align: right; color: #b91c1c;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['outstanding_total'], 2 ) ); ?></td>
								<td style="text-align: right; color: #1d4ed8;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['gross_profit'], 2 ) ); ?></td>
								<td style="text-align: right; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $report['summary']['net_profit'], 2 ) ); ?></td>
								<td style="text-align: right;"><?php echo esc_html( number_format( $report['summary']['margin_percentage'], 1 ) ); ?>%</td>
								<td></td>
							</tr>
						</tfoot>

					<?php elseif ( 'tender' === $report_type ) : ?>
						<thead>
							<tr>
								<th style="width: 140px;"><?php esc_html_e( 'Tender #', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Partner Company', 'tender-po-management' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Due Date', 'tender-po-management' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Purchase', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Sell', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Gross Profit', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Net Profit', 'tender-po-management' ); ?></th>
								<th style="width: 70px; text-align: center;"><?php esc_html_e( 'View', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['tender_number'] ); ?></strong></td>
									<td><?php echo esc_html( $row['customer'] ); ?></td>
									<td><?php echo esc_html( $row['partner'] ); ?></td>
									<td><?php echo esc_html( $row['tender_due_date'] ?: '-' ); ?></td>
									<td>
										<span class="tpm-status-badge tpm-status-<?php echo esc_attr( $row['status'] ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['status'] ) ) ); ?>
										</span>
									</td>
									<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $row['purchase_price'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( $currency_symbol . number_format( $row['sell_price'], 2 ) ); ?></td>
									<td style="text-align: right; color: #1d4ed8;"><?php echo esc_html( $currency_symbol . number_format( $row['gross_profit'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600; color: #047857;"><?php echo esc_html( $currency_symbol . number_format( $row['net_profit'], 2 ) ); ?></td>
									<td style="text-align: center;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $row['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'tender-po-management' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>

					<?php elseif ( 'purchase_order' === $report_type ) : ?>
						<thead>
							<tr>
								<th style="width: 140px;"><?php esc_html_e( 'PO Number', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'PO Name / Item', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'PO Date', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Purchase', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Sell', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Paid', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Outstanding', 'tender-po-management' ); ?></th>
								<th style="width: 70px; text-align: center;"><?php esc_html_e( 'View', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['po_number'] ); ?></strong></td>
									<td><?php echo esc_html( $row['po_name'] ); ?></td>
									<td><?php echo esc_html( $row['customer'] ); ?></td>
									<td>
										<span class="tpm-status-badge tpm-status-<?php echo esc_attr( $row['status'] ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['status'] ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $row['po_date'] ?: '-' ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $row['purchase_price'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( $currency_symbol . number_format( $row['sell_price'], 2 ) ); ?></td>
									<td style="text-align: right; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $row['actual_payment'], 2 ) ); ?></td>
									<td style="text-align: right; color: <?php echo $row['balance_payment_due'] > 0 ? '#b91c1c' : '#15803d'; ?>;">
										<?php echo esc_html( $currency_symbol . number_format( $row['balance_payment_due'], 2 ) ); ?>
									</td>
									<td style="text-align: center;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $row['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'tender-po-management' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>

					<?php elseif ( 'customer' === $report_type ) : ?>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Company Name', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Contact Person', 'tender-po-management' ); ?></th>
								<th style="width: 80px; text-align: center;"><?php esc_html_e( 'Tenders', 'tender-po-management' ); ?></th>
								<th style="width: 80px; text-align: center;"><?php esc_html_e( 'POs', 'tender-po-management' ); ?></th>
								<th style="width: 90px; text-align: center;"><?php esc_html_e( 'Total Records', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Order Value', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Payments Collected', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Outstanding', 'tender-po-management' ); ?></th>
								<th style="width: 80px; text-align: center;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['company_name'] ); ?></strong></td>
									<td><?php echo esc_html( $row['customer_name'] ); ?></td>
									<td style="text-align: center;"><?php echo esc_html( $row['total_tenders'] ); ?></td>
									<td style="text-align: center;"><?php echo esc_html( $row['total_pos'] ); ?></td>
									<td style="text-align: center; font-weight: 600;"><?php echo esc_html( $row['total_records'] ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( $currency_symbol . number_format( $row['total_order_value'], 2 ) ); ?></td>
									<td style="text-align: right; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $row['total_payments'], 2 ) ); ?></td>
									<td style="text-align: right; color: <?php echo $row['total_outstanding'] > 0 ? '#b91c1c' : '#15803d'; ?>;">
										<?php echo esc_html( $currency_symbol . number_format( $row['total_outstanding'], 2 ) ); ?>
									</td>
									<td style="text-align: center;">
										<span class="tpm-badge <?php echo 'active' === $row['status'] ? 'tpm-badge-success' : 'tpm-badge-secondary'; ?>">
											<?php echo esc_html( ucfirst( $row['status'] ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>

					<?php elseif ( 'partner' === $report_type ) : ?>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Company Name', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Contact Person', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Contact Email / Phone', 'tender-po-management' ); ?></th>
								<th style="width: 100px; text-align: center;"><?php esc_html_e( 'Records', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Total Sales Value', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Avg Partner %', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Partner Share', 'tender-po-management' ); ?></th>
								<th style="width: 80px; text-align: center;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['company_name'] ); ?></strong></td>
									<td><?php echo esc_html( $row['contact_person'] ); ?></td>
									<td><?php echo esc_html( $row['email'] ?: $row['phone'] ?: '-' ); ?></td>
									<td style="text-align: center; font-weight: 600;"><?php echo esc_html( $row['total_records'] ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( $currency_symbol . number_format( $row['total_sales_value'], 2 ) ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( number_format( $row['avg_percentage'], 1 ) ); ?>%</td>
									<td style="text-align: right; font-weight: 600; color: #4338ca;"><?php echo esc_html( $currency_symbol . number_format( $row['total_partner_share'], 2 ) ); ?></td>
									<td style="text-align: center;">
										<span class="tpm-badge <?php echo 'active' === $row['status'] ? 'tpm-badge-success' : 'tpm-badge-secondary'; ?>">
											<?php echo esc_html( ucfirst( $row['status'] ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>

					<?php elseif ( 'payment' === $report_type ) : ?>
						<thead>
							<tr>
								<th style="width: 100px;"><?php esc_html_e( 'Date', 'tender-po-management' ); ?></th>
								<th style="width: 130px;"><?php esc_html_e( 'Record #', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Method', 'tender-po-management' ); ?></th>
								<th style="width: 130px;"><?php esc_html_e( 'Reference #', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 120px;"><?php esc_html_e( 'Amount Paid', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 120px;"><?php esc_html_e( 'Remaining Balance', 'tender-po-management' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Recorded By', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['payment_date'] ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $row['record_id'] . '#payments' ) ); ?>">
											<strong><?php echo esc_html( $row['record_number'] ); ?></strong>
										</a>
									</td>
									<td><?php echo esc_html( $row['customer'] ); ?></td>
									<td><code><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['payment_method'] ) ) ); ?></code></td>
									<td><?php echo esc_html( $row['reference_number'] ); ?></td>
									<td style="text-align: right; font-weight: 600; color: #15803d;"><?php echo esc_html( $currency_symbol . number_format( $row['payment_amount'], 2 ) ); ?></td>
									<td style="text-align: right; color: <?php echo $row['balance_payment_due'] > 0 ? '#b91c1c' : '#15803d'; ?>;">
										<?php echo esc_html( $currency_symbol . number_format( $row['balance_payment_due'], 2 ) ); ?>
									</td>
									<td><?php echo esc_html( $row['recorded_by'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>

					<?php elseif ( 'status' === $report_type ) : ?>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Operational Status', 'tender-po-management' ); ?></th>
								<th style="width: 100px; text-align: center;"><?php esc_html_e( 'Tenders', 'tender-po-management' ); ?></th>
								<th style="width: 100px; text-align: center;"><?php esc_html_e( 'POs', 'tender-po-management' ); ?></th>
								<th style="width: 120px; text-align: center;"><?php esc_html_e( 'Total Records', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Purchase Total', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Sell Total', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 140px;"><?php esc_html_e( 'Gross Profit', 'tender-po-management' ); ?></th>
								<th style="text-align: right; width: 110px;"><?php esc_html_e( 'Share %', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<td>
										<span class="tpm-status-badge tpm-status-<?php echo esc_attr( $row['status'] ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['status'] ) ) ); ?>
										</span>
									</td>
									<td style="text-align: center;"><?php echo esc_html( $row['tender_count'] ); ?></td>
									<td style="text-align: center;"><?php echo esc_html( $row['po_count'] ); ?></td>
									<td style="text-align: center; font-weight: 600;"><?php echo esc_html( $row['record_count'] ); ?></td>
									<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $row['purchase_total'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( $currency_symbol . number_format( $row['sell_total'], 2 ) ); ?></td>
									<td style="text-align: right; color: #1d4ed8;"><?php echo esc_html( $currency_symbol . number_format( $row['gross_profit_total'], 2 ) ); ?></td>
									<td style="text-align: right; font-weight: 600;"><?php echo esc_html( number_format( $row['share_percentage'], 1 ) ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					<?php endif; ?>
				</table>
			</div>
		<?php endif; ?>
	</div>

</div>

<!-- JavaScript for Quick Date Range Presets -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
	var dateFromInput = document.getElementById('date_from');
	var dateToInput   = document.getElementById('date_to');
	var presetButtons = document.querySelectorAll('.tpm-preset-btn');

	if (!dateFromInput || !dateToInput) return;

	function formatDate(d) {
		var year  = d.getFullYear();
		var month = String(d.getMonth() + 1).padStart(2, '0');
		var day   = String(d.getDate()).padStart(2, '0');
		return year + '-' + month + '-' + day;
	}

	presetButtons.forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			var range = this.getAttribute('data-range');
			var now   = new Date();
			var start = null;
			var end   = null;

			if (range === 'this_month') {
				start = new Date(now.getFullYear(), now.getMonth(), 1);
				end   = new Date(now.getFullYear(), now.getMonth() + 1, 0);
			} else if (range === 'last_month') {
				start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
				end   = new Date(now.getFullYear(), now.getMonth(), 0);
			} else if (range === 'this_quarter') {
				var qMonth = Math.floor(now.getMonth() / 3) * 3;
				start = new Date(now.getFullYear(), qMonth, 1);
				end   = new Date(now.getFullYear(), qMonth + 3, 0);
			} else if (range === 'this_year') {
				start = new Date(now.getFullYear(), 0, 1);
				end   = new Date(now.getFullYear(), 11, 31);
			} else if (range === 'all_time') {
				dateFromInput.value = '';
				dateToInput.value = '';
				return;
			}

			if (start && end) {
				dateFromInput.value = formatDate(start);
				dateToInput.value   = formatDate(end);
			}
		});
	});
});
</script>
