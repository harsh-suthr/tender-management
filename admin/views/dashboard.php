<?php
/**
 * View: Management Dashboard
 *
 * Executive overview of active tenders, purchase orders,
 * financial summaries, operational alerts, recent records,
 * and upcoming milestone dates.
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
if ( ! current_user_can( 'view_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tender-po-management' ) );
}

// Retrieve high-performance aggregated dashboard payload
$dashboard_data = TPM_Dashboard_Service::get_dashboard_data();

$summary    = $dashboard_data['summary_cards'];
$fin        = $dashboard_data['financial_summary'];
$alerts     = $dashboard_data['alerts'];
$recent     = $dashboard_data['recent_records'];
$milestones = $dashboard_data['upcoming_dates'];
$currency_symbol = TPM_Settings_Service::get_currency_symbol();

$plugin_ver = TPM_VERSION;
?>
<div class="wrap tpm-admin-wrap">

	<!-- Header Banner -->
	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'Management Dashboard', 'tender-po-management' ); ?>
				<span class="tpm-version-badge">v<?php echo esc_html( $plugin_ver ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Executive overview of active bids, awarded contracts, financial performance, and operational deadlines.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<?php if ( current_user_can( 'create_tpm_records' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-add-record&type=tender' ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'New Tender', 'tender-po-management' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-add-record&type=po' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-cart"></span>
					<?php esc_html_e( 'New Purchase Order', 'tender-po-management' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- ====================================================================
	     SECTION 1: SUMMARY CARDS (7 Executive KPI Cards)
	     ==================================================================== -->
	<div class="tpm-summary-grid">
		<!-- Total Records -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-summary-card card-theme-total">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Total Records', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-portfolio"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['total_records'] ) ); ?></div>
		</a>

		<!-- Total Tenders -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-tenders' ) ); ?>" class="tpm-summary-card card-theme-tenders">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Total Tenders', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-clipboard"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['total_tenders'] ) ); ?></div>
		</a>

		<!-- Total Purchase Orders -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-purchase-orders' ) ); ?>" class="tpm-summary-card card-theme-pos">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Total Purchase Orders', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-cart"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['total_pos'] ) ); ?></div>
		</a>

		<!-- Pending -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records&view=pending' ) ); ?>" class="tpm-summary-card card-theme-pending">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Pending', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-hourglass"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['pending'] ) ); ?></div>
		</a>

		<!-- Submitted -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records&view=submitted' ) ); ?>" class="tpm-summary-card card-theme-submitted">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Submitted', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-upload"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['submitted'] ) ); ?></div>
		</a>

		<!-- Complete -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records&view=complete' ) ); ?>" class="tpm-summary-card card-theme-complete">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Complete', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-yes-alt"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['complete'] ) ); ?></div>
		</a>

		<!-- Archived -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-archived-records' ) ); ?>" class="tpm-summary-card card-theme-archived">
			<div class="tpm-card-top">
				<span class="tpm-card-title"><?php esc_html_e( 'Archived', 'tender-po-management' ); ?></span>
				<div class="tpm-card-indicator"><span class="dashicons dashicons-archive"></span></div>
			</div>
			<div class="tpm-card-count"><?php echo esc_html( number_format_i18n( $summary['archived'] ) ); ?></div>
		</a>
	</div>

	<!-- ====================================================================
	     SECTION 2: FINANCIAL SUMMARY
	     ==================================================================== -->
	<div class="tpm-card" style="margin-bottom: 24px;">
		<div class="tpm-card-header">
			<h2>
				<span class="dashicons dashicons-chart-area"></span>
				<?php esc_html_e( 'Financial Summary', 'tender-po-management' ); ?>
			</h2>
			<span class="tpm-badge"><?php esc_html_e( 'Active Contracts', 'tender-po-management' ); ?></span>
		</div>
		<div class="tpm-card-body">
			<div class="tpm-financial-grid">
				<!-- Total Purchase Amount -->
				<div class="tpm-financial-box">
					<div class="tpm-financial-label"><?php esc_html_e( 'Total Purchase Amount', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val"><?php echo esc_html( $currency_symbol . number_format( $fin['total_purchase'], 2 ) ); ?></div>
				</div>

				<!-- Total Sell Amount -->
				<div class="tpm-financial-box">
					<div class="tpm-financial-label"><?php esc_html_e( 'Total Sell Amount', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val"><?php echo esc_html( $currency_symbol . number_format( $fin['total_sell'], 2 ) ); ?></div>
				</div>

				<!-- Total Expenses -->
				<div class="tpm-financial-box">
					<div class="tpm-financial-label"><?php esc_html_e( 'Total Expenses', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val"><?php echo esc_html( $currency_symbol . number_format( $fin['total_expenses'], 2 ) ); ?></div>
				</div>

				<!-- Total Actual Payments -->
				<div class="tpm-financial-box">
					<div class="tpm-financial-label"><?php esc_html_e( 'Total Actual Payments', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $fin['total_actual_payments'], 2 ) ); ?></div>
				</div>

				<!-- Total Outstanding -->
				<div class="tpm-financial-box">
					<div class="tpm-financial-label"><?php esc_html_e( 'Total Outstanding', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val <?php echo ( $fin['total_outstanding'] > 0 ) ? 'tpm-metric-negative' : ''; ?>">
						<?php echo esc_html( $currency_symbol . number_format( $fin['total_outstanding'], 2 ) ); ?>
					</div>
				</div>

				<!-- Estimated Gross Profit -->
				<div class="tpm-financial-box">
					<div class="tpm-financial-label"><?php esc_html_e( 'Estimated Gross Profit', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val <?php echo ( $fin['estimated_gross_profit'] >= 0 ) ? 'tpm-metric-positive' : 'tpm-metric-negative'; ?>">
						<?php echo esc_html( $currency_symbol . number_format( $fin['estimated_gross_profit'], 2 ) ); ?>
					</div>
				</div>

				<!-- Estimated Net Profit -->
				<div class="tpm-financial-box" style="background: #f0fdf4; border-color: #bbf7d0;">
					<div class="tpm-financial-label" style="color: #15803d;"><?php esc_html_e( 'Estimated Net Profit', 'tender-po-management' ); ?></div>
					<div class="tpm-financial-val <?php echo ( $fin['estimated_net_profit'] >= 0 ) ? 'tpm-metric-positive' : 'tpm-metric-negative'; ?>">
						<?php echo esc_html( $currency_symbol . number_format( $fin['estimated_net_profit'], 2 ) ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     SECTION 3: ACTIONABLE OPERATIONAL ALERTS
	     ==================================================================== -->
	<div class="tpm-alerts-grid">
		<?php foreach ( $alerts as $alert_key => $alert_item ) : ?>
			<a href="<?php echo esc_url( $alert_item['filter_url'] ); ?>" class="tpm-alert-chip severity-<?php echo esc_attr( $alert_item['severity'] ); ?>">
				<div class="tpm-alert-icon">
					<span class="dashicons dashicons-<?php echo esc_attr( $alert_item['icon'] ); ?>"></span>
				</div>
				<div class="tpm-alert-body">
					<div class="tpm-alert-title">
						<?php echo esc_html( $alert_item['label'] ); ?>
						<span class="tpm-alert-badge <?php echo ( $alert_item['count'] > 0 ) ? 'has-alerts' : ''; ?>">
							<?php echo esc_html( $alert_item['count'] ); ?>
						</span>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- ====================================================================
	     SECTION 4 & 5: RECENT RECORDS & UPCOMING DATES (2-Column Grid)
	     ==================================================================== -->
	<div class="tpm-dashboard-cols">

		<!-- Left Column: Recent Records Table -->
		<div class="tpm-col-main">
			<div class="tpm-card">
				<div class="tpm-card-header">
					<h2>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Recent Records', 'tender-po-management' ); ?>
					</h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-link-add-new">
						<?php esc_html_e( 'View All Records &rarr;', 'tender-po-management' ); ?>
					</a>
				</div>
				<div class="tpm-card-body" style="padding: 0;">
					<?php if ( empty( $recent ) ) : ?>
						<div class="tpm-empty-placeholder" style="padding: 40px; text-align: center;">
							<span class="dashicons dashicons-portfolio" style="font-size: 36px; width: 36px; height: 36px; color: #cbd5e1; margin-bottom: 10px;"></span>
							<p class="description" style="font-size: 14px; margin-bottom: 0;">
								<?php esc_html_e( 'No records found. Click "New Tender" or "New Purchase Order" above to create your first record.', 'tender-po-management' ); ?>
							</p>
						</div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 140px;"><?php esc_html_e( 'Record Number', 'tender-po-management' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Type', 'tender-po-management' ); ?></th>
									<th><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></th>
									<th style="width: 120px;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Date', 'tender-po-management' ); ?></th>
									<th style="width: 80px; text-align: right;"><?php esc_html_e( 'Actions', 'tender-po-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent as $rec ) :
									$rec_num     = ! empty( $rec->tender_number ) ? $rec->tender_number : ( ! empty( $rec->customer_po_number ) ? $rec->customer_po_number : '#' . $rec->id );
									$details_url = admin_url( 'admin.php?page=tpm-record-details&record_id=' . (int) $rec->id );
									$cust_label  = ! empty( $rec->customer_company ) ? $rec->customer_company : ( ! empty( $rec->customer_name ) ? $rec->customer_name : '&mdash;' );
									$is_po       = 'po' === $rec->record_type;
									?>
									<tr>
										<td>
											<strong>
												<a href="<?php echo esc_url( $details_url ); ?>">
													<?php echo esc_html( $rec_num ); ?>
												</a>
											</strong>
										</td>
										<td>
											<span class="tpm-badge <?php echo $is_po ? 'tpm-badge-type-po' : 'tpm-badge-type-tender'; ?>">
												<?php echo $is_po ? esc_html__( 'Purchase Order', 'tender-po-management' ) : esc_html__( 'Tender', 'tender-po-management' ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $cust_label ); ?></td>
										<td>
											<span class="tpm-tag"><?php echo esc_html( ucfirst( (string) $rec->status ) ); ?></span>
										</td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rec->created_at ) ) ); ?></td>
										<td style="text-align: right;">
											<a href="<?php echo esc_url( $details_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'View Details', 'tender-po-management' ); ?>">
												<?php esc_html_e( 'View', 'tender-po-management' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Right Column: Upcoming Milestone Dates -->
		<div class="tpm-col-side">
			<div class="tpm-card">
				<div class="tpm-card-header">
					<h2>
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Upcoming Dates', 'tender-po-management' ); ?>
					</h2>
					<span class="tpm-badge"><?php echo count( $milestones ); ?></span>
				</div>
				<div class="tpm-card-body" style="padding: 0;">
					<?php if ( empty( $milestones ) ) : ?>
						<div class="tpm-empty-placeholder" style="padding: 30px; text-align: center;">
							<span class="dashicons dashicons-calendar" style="font-size: 32px; width: 32px; height: 32px; color: #cbd5e1; margin-bottom: 8px;"></span>
							<p class="description" style="margin-bottom: 0;">
								<?php esc_html_e( 'No upcoming milestone deadlines scheduled.', 'tender-po-management' ); ?>
							</p>
						</div>
					<?php else : ?>
						<ul class="tpm-timeline-list">
							<?php foreach ( $milestones as $event ) :
								$details_url = admin_url( 'admin.php?page=tpm-record-details&record_id=' . (int) $event['record_id'] );
								$diff        = (int) $event['days_diff'];

								if ( $diff === 0 ) {
									$pill_class = 'pill-today';
									$pill_text  = __( 'Today', 'tender-po-management' );
								} elseif ( $diff === 1 ) {
									$pill_class = 'pill-soon';
									$pill_text  = __( 'Tomorrow', 'tender-po-management' );
								} elseif ( $diff > 1 ) {
									$pill_class = ( $diff <= 7 ) ? 'pill-soon' : '';
									/* translators: %d: Days ahead */
									$pill_text = sprintf( __( 'In %d days', 'tender-po-management' ), $diff );
								} else {
									$pill_class = 'pill-overdue';
									/* translators: %d: Days overdue */
									$pill_text = sprintf( __( '%d days ago', 'tender-po-management' ), abs( $diff ) );
								}
								?>
								<li class="tpm-timeline-item">
									<div class="tpm-timeline-main">
										<span class="tpm-timeline-label"><?php echo esc_html( $event['milestone_label'] ); ?></span>
										<a href="<?php echo esc_url( $details_url ); ?>" class="tpm-timeline-rec">
											<?php echo esc_html( $event['record_number'] ); ?>
										</a>
									</div>
									<div class="tpm-timeline-side">
										<span class="tpm-timeline-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event['date'] ) ) ); ?></span>
										<span class="tpm-countdown-pill <?php echo esc_attr( $pill_class ); ?>">
											<?php echo esc_html( $pill_text ); ?>
										</span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>

	</div>

</div>
