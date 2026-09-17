<?php
/**
 * View: Record Details
 *
 * Comprehensive Record Details view supporting Tenders and Purchase Orders.
 * Features 6 distinct tabs: Overview, Financial, Shipping, Attachments, Notes, Activity.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/admin/views
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capability check.
if ( ! current_user_can( 'view_tpm_records' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to view record details.', 'tender-po-management' ) );
}

global $wpdb;

// 1. Validate record_id parameter.
$record_id = 0;
if ( isset( $_GET['record_id'] ) && is_numeric( $_GET['record_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$record_id = absint( $_GET['record_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
} elseif ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$record_id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

// Check for invalid ID.
if ( $record_id <= 0 ) :
	?>
	<div class="wrap tpm-admin-wrap">
		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1><?php esc_html_e( 'Record Not Found', 'tender-po-management' ); ?></h1>
				<p><?php esc_html_e( 'An invalid or missing record ID was provided in the URL.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to All Records', 'tender-po-management' ); ?>
				</a>
			</div>
		</div>
		<div class="notice notice-error tpm-notice">
			<p><strong><?php esc_html_e( 'Error: Invalid Record Identifier.', 'tender-po-management' ); ?></strong></p>
			<p><?php esc_html_e( 'Please select a valid Tender or Purchase Order from the records list.', 'tender-po-management' ); ?></p>
		</div>
	</div>
	<?php
	return;
endif;

// 2. Fetch record and validate existence.
$record = TPM_Record_Service::get_record( $record_id );

if ( ! $record ) :
	?>
	<div class="wrap tpm-admin-wrap">
		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1><?php esc_html_e( 'Record Not Found', 'tender-po-management' ); ?></h1>
				<p>
					<?php
					/* translators: %d: Record ID */
					printf( esc_html__( 'Record #%d does not exist in the database or may have been deleted.', 'tender-po-management' ), $record_id );
					?>
				</p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to All Records', 'tender-po-management' ); ?>
				</a>
			</div>
		</div>
		<div class="notice notice-error tpm-notice">
			<p>
				<strong>
					<?php
					/* translators: %d: Record ID */
					printf( esc_html__( 'Record with ID #%d could not be found.', 'tender-po-management' ), $record_id );
					?>
				</strong>
			</p>
			<p><?php esc_html_e( 'The requested record might have been removed, archived, or never created.', 'tender-po-management' ); ?></p>
		</div>
	</div>
	<?php
	return;
endif;

// 3. Fetch related entities.
$attachments   = TPM_Record_Service::get_attachments( $record_id );
$activity_logs = TPM_Record_Service::get_activity_logs( $record_id );
$payments      = TPM_Payment_Service::get_record_payments( $record_id );
$payment_summary = TPM_Payment_Service::get_payment_summary( $record_id );
$payment_status  = $payment_summary['payment_status'];

$payment_notice = get_transient( 'tpm_payment_notice_' . $current_user_id );
$payment_error  = get_transient( 'tpm_payment_errors_' . $current_user_id );
if ( false !== $payment_notice ) {
	delete_transient( 'tpm_payment_notice_' . $current_user_id );
}
if ( false !== $payment_error ) {
	delete_transient( 'tpm_payment_errors_' . $current_user_id );
}

// Customer
$customer = null;
if ( ! empty( $record->customer_id ) ) {
	$cust_table = $wpdb->prefix . 'tpm_customers';
	$customer   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$cust_table}` WHERE id = %d", $record->customer_id ) );
}

// Partner Company
$partner = null;
if ( ! empty( $record->partner_company_id ) ) {
	$partner_table = $wpdb->prefix . 'tpm_partner_companies';
	$partner       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$partner_table}` WHERE id = %d", $record->partner_company_id ) );
}

// Status details
$statuses_table = $wpdb->prefix . 'tpm_statuses';
$status_obj     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$statuses_table}` WHERE status_slug = %s", $record->status ) );
$status_label   = $status_obj ? $status_obj->status_name : ucfirst( $record->status );
$status_color   = $status_obj ? $status_obj->status_color : '#646970';

// Financial calculations
$currency_symbol = TPM_Settings_Service::get_currency_symbol();
$purchase_price  = (float) $record->purchase_price;
$sell_price      = (float) $record->sell_price;
$expenses        = (float) $record->expenses;
$actual_payment  = (float) $record->actual_payment;
$balance_due     = (float) $record->balance_payment_due;

$gross_profit = $sell_price - $purchase_price;
$net_profit   = $gross_profit - $expenses;

// Payment completion percentage
$payment_pct = ( $sell_price > 0 ) ? min( 100, round( ( $actual_payment / $sell_price ) * 100, 1 ) ) : ( $actual_payment > 0 ? 100 : 0 );

// Partner share calculation
$partner_pct   = (float) $record->partner_percentage;
$partner_share = ( $partner_pct > 0 && $net_profit > 0 ) ? ( $net_profit * ( $partner_pct / 100 ) ) : 0.00;

// User / author details
$creator_user = get_userdata( $record->created_by );
$author_name  = $creator_user ? $creator_user->display_name : __( 'System', 'tender-po-management' );

// Formatted Record Title
$is_po = ( 'po' === $record->record_type );
if ( $is_po ) {
	$rec_num = ! empty( $record->customer_po_number ) ? $record->customer_po_number : ( ! empty( $record->fill_po ) ? $record->fill_po : ( 'PO-' . str_pad( (string) $record->id, 5, '0', STR_PAD_LEFT ) ) );
	$title   = sprintf( 'PO # %s', $rec_num );
} else {
	$rec_num = ! empty( $record->tender_number ) ? $record->tender_number : ( 'TN-' . str_pad( (string) $record->id, 5, '0', STR_PAD_LEFT ) );
	$title   = sprintf( 'Tender # %s', $rec_num );
}

// User notices
$user_id        = get_current_user_id();
$success_notice = get_transient( 'tpm_record_success_' . $user_id );
$general_notice = get_transient( 'tpm_record_notice_' . $user_id );

if ( false !== $success_notice ) {
	delete_transient( 'tpm_record_success_' . $user_id );
}
if ( false !== $general_notice ) {
	delete_transient( 'tpm_record_notice_' . $user_id );
}
?>

<div class="wrap tpm-admin-wrap">

	<!-- Admin Notifications -->
	<?php if ( ! empty( $success_notice ) || isset( $_GET['created'] ) || isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success tpm-notice is-dismissible">
			<p><strong><?php echo ! empty( $success_notice ) ? esc_html( $success_notice ) : ( isset( $_GET['updated'] ) ? esc_html__( 'Record updated successfully!', 'tender-po-management' ) : esc_html__( 'Record created successfully!', 'tender-po-management' ) ); ?></strong></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $general_notice ) ) : ?>
		<div class="notice notice-info tpm-notice is-dismissible">
			<p><?php echo esc_html( $general_notice ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Header Banner -->
	<div class="tpm-header-banner tpm-detail-header-banner">
		<div class="tpm-header-left">
			<div class="tpm-detail-badges">
				<!-- Record Type Badge -->
				<span class="tpm-badge <?php echo $is_po ? 'tpm-badge-fulfilled' : 'tpm-badge-active'; ?>">
					<?php echo $is_po ? esc_html__( 'Purchase Order', 'tender-po-management' ) : esc_html__( 'Tender', 'tender-po-management' ); ?>
				</span>

				<!-- Status Badge -->
				<span class="tpm-badge" style="background-color: <?php echo esc_attr( $status_color ); ?>20; color: <?php echo esc_attr( $status_color ); ?>; border: 1px solid <?php echo esc_attr( $status_color ); ?>60;">
					<?php echo esc_html( $status_label ); ?>
				</span>

				<?php if ( ! empty( $record->is_archived ) ) : ?>
					<span class="tpm-badge tpm-badge-draft"><?php esc_html_e( 'Archived', 'tender-po-management' ); ?></span>
				<?php endif; ?>

				<?php if ( ! empty( $record->fill_ref_number ) ) : ?>
					<span class="tpm-ref-badge" title="<?php esc_attr_e( 'Fill Reference', 'tender-po-management' ); ?>">
						<?php echo esc_html( $record->fill_ref_number ); ?>
					</span>
				<?php endif; ?>
			</div>

			<h1>
				<?php echo esc_html( $title ); ?>
				<?php if ( ! empty( $record->customer_po_name ) ) : ?>
					<span class="tpm-title-sub">&mdash; <?php echo esc_html( $record->customer_po_name ); ?></span>
				<?php endif; ?>
			</h1>

			<p>
				<?php
				/* translators: 1: Author name, 2: Created date */
				printf( esc_html__( 'Created by %1$s on %2$s', 'tender-po-management' ), '<strong>' . esc_html( $author_name ) . '</strong>', esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->created_at ) ) ) );
				if ( ! empty( $record->updated_at ) && $record->updated_at !== $record->created_at ) {
					/* translators: %s: Updated timestamp */
					printf( esc_html__( ' &bull; Last updated: %s', 'tender-po-management' ), esc_html( human_time_diff( strtotime( $record->updated_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'tender-po-management' ) ) );
				}
				?>
			</p>
		</div>

		<!-- Action Buttons in Header -->
		<div class="tpm-header-actions">
			<!-- Back Link -->
			<?php if ( ! empty( $record->is_archived ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-archived-records' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Archive', 'tender-po-management' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Records', 'tender-po-management' ); ?>
				</a>
			<?php endif; ?>

			<!-- Edit Record Action (Authorized only) -->
			<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-add-record&action=edit&id=' . $record->id ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Edit Record', 'tender-po-management' ); ?>
				</a>
			<?php endif; ?>

			<!-- Archive / Restore Action (Authorized only) -->
			<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
				<?php if ( ! empty( $record->is_archived ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-inline-form">
						<input type="hidden" name="action" value="tpm_unarchive_record">
						<input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
						<?php wp_nonce_field( 'tpm_unarchive_record_' . $record->id, 'tpm_nonce' ); ?>
						<button type="submit" class="tpm-btn tpm-btn-secondary">
							<span class="dashicons dashicons-undo"></span>
							<?php esc_html_e( 'Restore', 'tender-po-management' ); ?>
						</button>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-inline-form">
						<input type="hidden" name="action" value="tpm_archive_record">
						<input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
						<?php wp_nonce_field( 'tpm_archive_record_' . $record->id, 'tpm_nonce' ); ?>
						<button type="submit" class="tpm-btn tpm-btn-secondary">
							<span class="dashicons dashicons-archive"></span>
							<?php esc_html_e( 'Archive', 'tender-po-management' ); ?>
						</button>
					</form>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Delete Action (Authorized only) -->
			<?php if ( current_user_can( 'delete_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-inline-form">
					<input type="hidden" name="action" value="tpm_permanent_delete">
					<input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
					<?php wp_nonce_field( 'tpm_permanent_delete_' . $record->id, 'tpm_nonce' ); ?>
					<button type="submit" class="tpm-btn tpm-btn-danger" data-tpm-confirm="<?php esc_attr_e( 'Are you sure you want to permanently delete this record? This action cannot be undone and will delete all related payments and attachments.', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Delete Permanently', 'tender-po-management' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $record->is_archived ) ) : ?>
		<div class="notice notice-warning tpm-notice" style="margin-bottom: 20px; border-left-color: #f59e0b; background: #fffbeb;">
			<p style="font-size: 14px; margin: 6px 0;">
				<span class="dashicons dashicons-archive" style="color: #d97706; vertical-align: middle; margin-right: 5px;"></span>
				<strong><?php esc_html_e( 'This record is currently archived.', 'tender-po-management' ); ?></strong>
				<?php esc_html_e( 'It is excluded from active listings and financial reporting. Use the Restore button to return it to active status, or Delete Permanently to remove it completely.', 'tender-po-management' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- TAB NAVIGATION -->
	<div class="tpm-tab-navigation-wrapper">
		<nav class="nav-tab-wrapper tpm-tabs" id="tpm-record-tabs">
			<a href="#overview" class="nav-tab nav-tab-active tpm-tab-link" data-tab="overview">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Overview', 'tender-po-management' ); ?>
			</a>
			<a href="#financial" class="nav-tab tpm-tab-link" data-tab="financial">
				<span class="dashicons dashicons-money-alt"></span>
				<?php esc_html_e( 'Financial', 'tender-po-management' ); ?>
			</a>
			<a href="#payments" class="nav-tab tpm-tab-link" data-tab="payments">
				<span class="dashicons dashicons-cart"></span>
				<?php esc_html_e( 'Payments', 'tender-po-management' ); ?>
				<span class="tpm-tab-counter"><?php echo count( $payments ); ?></span>
			</a>
			<a href="#shipping" class="nav-tab tpm-tab-link" data-tab="shipping">
				<span class="dashicons dashicons-location-alt"></span>
				<?php esc_html_e( 'Shipping', 'tender-po-management' ); ?>
			</a>
			<a href="#attachments" class="nav-tab tpm-tab-link" data-tab="attachments">
				<span class="dashicons dashicons-paperclip"></span>
				<?php esc_html_e( 'Attachments', 'tender-po-management' ); ?>
				<span class="tpm-tab-counter"><?php echo count( $attachments ); ?></span>
			</a>
			<a href="#notes" class="nav-tab tpm-tab-link" data-tab="notes">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Notes', 'tender-po-management' ); ?>
			</a>
			<a href="#activity" class="nav-tab tpm-tab-link" data-tab="activity">
				<span class="dashicons dashicons-backup"></span>
				<?php esc_html_e( 'Activity', 'tender-po-management' ); ?>
				<span class="tpm-tab-counter"><?php echo count( $activity_logs ); ?></span>
			</a>
		</nav>
	</div>

	<!-- ==================================================================== -->
	<!-- TAB 1: OVERVIEW TAB                                                  -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane active" id="tab-pane-overview">
		<div class="tpm-dashboard-cols">

			<!-- Left Main Column -->
			<div class="tpm-col-main">

				<!-- Core Metadata Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'Core Specifications', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-detail-grid">
							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Record Type', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<strong><?php echo $is_po ? esc_html__( 'Purchase Order (PO)', 'tender-po-management' ) : esc_html__( 'Tender Submission', 'tender-po-management' ); ?></strong>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Lifecycle Status', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<span class="tpm-badge" style="background-color: <?php echo esc_attr( $status_color ); ?>20; color: <?php echo esc_attr( $status_color ); ?>; border: 1px solid <?php echo esc_attr( $status_color ); ?>60;">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Tender Number', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php echo ! empty( $record->tender_number ) ? esc_html( $record->tender_number ) : '<span class="tpm-text-muted">—</span>'; ?>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Fill Ref Number', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php echo ! empty( $record->fill_ref_number ) ? esc_html( $record->fill_ref_number ) : '<span class="tpm-text-muted">—</span>'; ?>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Customer Organization', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php
									if ( $customer ) {
										echo '<strong>' . esc_html( ! empty( $customer->company_name ) ? $customer->company_name : $customer->name ) . '</strong>';
										if ( ! empty( $customer->email ) ) {
											echo ' <span class="tpm-text-muted">(' . esc_html( $customer->email ) . ')</span>';
										}
									} else {
										echo '<span class="tpm-text-muted">' . esc_html__( 'Not Assigned', 'tender-po-management' ) . '</span>';
									}
									?>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Customer PO Number', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php echo ! empty( $record->customer_po_number ) ? esc_html( $record->customer_po_number ) : '<span class="tpm-text-muted">—</span>'; ?>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Customer PO Name', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php echo ! empty( $record->customer_po_name ) ? esc_html( $record->customer_po_name ) : '<span class="tpm-text-muted">—</span>'; ?>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'HS Code', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php echo ! empty( $record->hs_code ) ? esc_html( $record->hs_code ) : '<span class="tpm-text-muted">—</span>'; ?>
								</span>
							</div>
						</div>

						<!-- Item Description -->
						<div class="tpm-detail-field tpm-field-full">
							<span class="tpm-field-name"><?php esc_html_e( 'Item Description', 'tender-po-management' ); ?></span>
							<div class="tpm-desc-box">
								<?php echo ! empty( $record->item_description ) ? nl2br( wp_kses_post( $record->item_description ) ) : '<span class="tpm-text-muted">' . esc_html__( 'No item description provided.', 'tender-po-management' ) . '</span>'; ?>
							</div>
						</div>
					</div>
				</div>

			</div>

			<!-- Right Sidebar Column -->
			<div class="tpm-col-side">

				<!-- Dates Milestones Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php esc_html_e( 'Important Dates', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<ul class="tpm-diag-list">
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Tender Due Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->tender_due_date ) ? esc_html( $record->tender_due_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Tender Submission Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->tender_submission_date ) ? esc_html( $record->tender_submission_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Quote Expiration Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->quote_expiration_date ) ? esc_html( $record->quote_expiration_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'PO Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->po_date ) ? esc_html( $record->po_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Payment Due Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->payment_due_date ) ? esc_html( $record->payment_due_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Shipping Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->shipping_date ) ? esc_html( $record->shipping_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Settled Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo ! empty( $record->settled_date ) ? esc_html( $record->settled_date ) : '<span class="tpm-text-muted">—</span>'; ?></span>
							</li>
						</ul>
					</div>
				</div>

				<!-- Audit Timestamps Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Record Audit Metadata', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<ul class="tpm-diag-list">
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Created By', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo esc_html( $author_name ); ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Created Date', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->created_at ) ) ); ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Last Updated', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val">
									<?php echo ! empty( $record->updated_at ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->updated_at ) ) ) : esc_html__( 'Never', 'tender-po-management' ); ?>
								</span>
							</li>
						</ul>
					</div>
				</div>

			</div>

		</div>
	</div>

	<!-- ==================================================================== -->
	<!-- TAB 2: FINANCIAL TAB                                                 -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane" id="tab-pane-financial">

		<!-- Financial KPI Strip -->
		<div class="tpm-kpi-grid">
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-records">
					<span class="dashicons dashicons-money"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Purchase Price', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value"><?php echo esc_html( $currency_symbol . number_format( $purchase_price, 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-tenders">
					<span class="dashicons dashicons-cart"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Sell Price', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value"><?php echo esc_html( $currency_symbol . number_format( $sell_price, 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-finance">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Net Profit', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value <?php echo ( $net_profit >= 0 ) ? 'tpm-metric-positive' : 'tpm-metric-negative'; ?>">
						<?php echo esc_html( $currency_symbol . number_format( $net_profit, 2 ) ); ?>
					</div>
					<div class="tpm-kpi-sub"><?php printf( esc_html__( 'Gross: %s', 'tender-po-management' ), esc_html( $currency_symbol . number_format( $gross_profit, 2 ) ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-pos">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Balance Due', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value"><?php echo esc_html( $currency_symbol . number_format( $balance_due, 2 ) ); ?></div>
					<div class="tpm-kpi-sub"><?php printf( esc_html__( 'Paid: %s', 'tender-po-management' ), esc_html( $currency_symbol . number_format( $actual_payment, 2 ) ) ); ?></div>
				</div>
			</div>
		</div>

		<!-- Financial Detailed Breakdown -->
		<div class="tpm-dashboard-cols">

			<!-- Left Column: Ledgers -->
			<div class="tpm-col-main">

				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-calculator"></span>
							<?php esc_html_e( 'Financial Breakdown', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Financial Item', 'tender-po-management' ); ?></th>
									<th style="text-align: right;"><?php printf( esc_html__( 'Amount (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></th>
									<th><?php esc_html_e( 'Description & Logic', 'tender-po-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><strong><?php esc_html_e( 'Purchase Price', 'tender-po-management' ); ?></strong></td>
									<td style="text-align: right;"><strong><?php echo esc_html( $currency_symbol . number_format( $purchase_price, 2 ) ); ?></strong></td>
									<td class="tpm-text-muted"><?php esc_html_e( 'Total procurement or manufacturing cost', 'tender-po-management' ); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e( 'Sell Price (Contract Value)', 'tender-po-management' ); ?></strong></td>
									<td style="text-align: right;"><strong><?php echo esc_html( $currency_symbol . number_format( $sell_price, 2 ) ); ?></strong></td>
									<td class="tpm-text-muted"><?php esc_html_e( 'Total billable contract amount agreed with client', 'tender-po-management' ); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e( 'Gross Profit', 'tender-po-management' ); ?></strong></td>
									<td style="text-align: right;" class="<?php echo ( $gross_profit >= 0 ) ? 'tpm-metric-positive' : 'tpm-metric-negative'; ?>">
										<strong><?php echo esc_html( $currency_symbol . number_format( $gross_profit, 2 ) ); ?></strong>
									</td>
									<td class="tpm-text-muted"><?php esc_html_e( 'Sell Price minus Purchase Price', 'tender-po-management' ); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e( 'Expenses & Overhead', 'tender-po-management' ); ?></strong></td>
									<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $expenses, 2 ) ); ?></td>
									<td class="tpm-text-muted"><?php esc_html_e( 'Freight, customs clearance, and administrative fees', 'tender-po-management' ); ?></td>
								</tr>
								<tr class="tpm-row-highlight">
									<td><strong><?php esc_html_e( 'Net Profit', 'tender-po-management' ); ?></strong></td>
									<td style="text-align: right;" class="<?php echo ( $net_profit >= 0 ) ? 'tpm-metric-positive' : 'tpm-metric-negative'; ?>">
										<strong><?php echo esc_html( $currency_symbol . number_format( $net_profit, 2 ) ); ?></strong>
									</td>
									<td class="tpm-text-muted"><?php esc_html_e( 'Gross Profit minus Expenses', 'tender-po-management' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Partner Company & Profit Share Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-groups"></span>
							<?php esc_html_e( 'Partner Company & Profit Participation', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-detail-grid">
							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Partner Organization', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<?php
									if ( $partner ) {
										$partner_url = admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . absint( $partner->id ) );
										echo '<strong><a href="' . esc_url( $partner_url ) . '" class="tpm-link-primary">' . esc_html( $partner->company_name ) . '</a></strong>';
									} else {
										echo '<span class="tpm-text-muted">' . esc_html__( 'None Assigned', 'tender-po-management' ) . '</span>';
									}
									?>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Partner Percentage', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<strong><?php echo esc_html( number_format( $partner_pct, 2 ) ); ?>%</strong>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Estimated Partner Profit Share', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val tpm-metric-positive">
									<strong><?php echo esc_html( $currency_symbol . number_format( $partner_share, 2 ) ); ?></strong>
								</span>
							</div>

							<div class="tpm-detail-field">
								<span class="tpm-field-name"><?php esc_html_e( 'Company Retained Net Profit', 'tender-po-management' ); ?></span>
								<span class="tpm-field-val">
									<strong><?php echo esc_html( $currency_symbol . number_format( max( 0.00, $net_profit - $partner_share ), 2 ) ); ?></strong>
								</span>
							</div>
						</div>
					</div>
				</div>

			</div>

			<!-- Right Column: Payment Progress & Summary -->
			<div class="tpm-col-side">

				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-chart-pie"></span>
							<?php esc_html_e( 'Payment Summary', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-progress-container">
							<div class="tpm-progress-header">
								<span><?php esc_html_e( 'Collection Progress', 'tender-po-management' ); ?></span>
								<strong><?php echo esc_html( $payment_pct ); ?>%</strong>
							</div>
							<div class="tpm-progress-bar">
								<div class="tpm-progress-fill" style="width: <?php echo esc_attr( $payment_pct ); ?>%;"></div>
							</div>
						</div>

						<ul class="tpm-diag-list" style="margin-top: 18px;">
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Total Contract Value', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val"><?php echo esc_html( $currency_symbol . number_format( $sell_price, 2 ) ); ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Actual Payment Received', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $actual_payment, 2 ) ); ?></span>
							</li>
							<li class="tpm-diag-item">
								<span class="tpm-diag-label"><?php esc_html_e( 'Balance Payment Due', 'tender-po-management' ); ?></span>
								<span class="tpm-diag-val <?php echo ( $balance_due > 0 ) ? 'tpm-metric-negative' : 'tpm-status-ok'; ?>">
									<?php echo esc_html( $currency_symbol . number_format( $balance_due, 2 ) ); ?>
								</span>
							</li>
						</ul>
					</div>
				</div>

			</div>

		</div>
	</div>

	<!-- ==================================================================== -->
	<!-- TAB: PAYMENTS TAB                                                    -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane" id="tab-pane-payments">

		<?php if ( ! empty( $payment_notice ) ) : ?>
			<div class="notice notice-success is-dismissible tpm-notice">
				<p><strong><?php echo esc_html( $payment_notice ); ?></strong></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $payment_error ) ) : ?>
			<div class="notice notice-error is-dismissible tpm-notice">
				<p><strong><?php esc_html_e( 'Error:', 'tender-po-management' ); ?></strong> <?php echo esc_html( $payment_error ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Payment KPI Metrics Cards -->
		<div class="tpm-kpi-grid">
			<!-- Total Expected Amount -->
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-records">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Total Expected Amount', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value"><?php echo esc_html( $currency_symbol . number_format( $sell_price, 2 ) ); ?></div>
					<div class="tpm-kpi-sub"><?php esc_html_e( 'Contract / Selling Price', 'tender-po-management' ); ?></div>
				</div>
			</div>

			<!-- Total Paid -->
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-awarded">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Total Paid', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $actual_payment, 2 ) ); ?></div>
					<div class="tpm-kpi-sub"><?php echo esc_html( sprintf( __( '%s%% of invoice received', 'tender-po-management' ), number_format( $payment_pct, 1 ) ) ); ?></div>
				</div>
			</div>

			<!-- Balance Due -->
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-profit">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Balance Due', 'tender-po-management' ); ?></div>
					<div class="tpm-kpi-value <?php echo ( $balance_due > 0 ) ? 'tpm-metric-negative' : 'tpm-metric-positive'; ?>">
						<?php echo esc_html( $currency_symbol . number_format( $balance_due, 2 ) ); ?>
					</div>
					<div class="tpm-kpi-sub">
						<?php
						if ( ! empty( $record->payment_due_date ) ) {
							/* translators: %s: Payment due date */
							printf( esc_html__( 'Due: %s', 'tender-po-management' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $record->payment_due_date ) ) ) );
						} else {
							esc_html_e( 'No due date set', 'tender-po-management' );
						}
						?>
					</div>
				</div>
			</div>

			<!-- Payment Status -->
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon icon-tender">
					<span class="dashicons dashicons-shield"></span>
				</div>
				<div class="tpm-kpi-content">
					<div class="tpm-kpi-label"><?php esc_html_e( 'Payment Status', 'tender-po-management' ); ?></div>
					<div style="margin-top: 6px;">
						<?php
						$status_badge_class = 'tpm-badge-pending';
						if ( 'Paid' === $payment_status ) {
							$status_badge_class = 'tpm-badge-paid';
						} elseif ( 'Overdue' === $payment_status ) {
							$status_badge_class = 'tpm-badge-overdue';
						} elseif ( 'Partial' === $payment_status ) {
							$status_badge_class = 'tpm-badge-partial';
						}
						?>
						<span class="tpm-badge <?php echo esc_attr( $status_badge_class ); ?>" style="font-size: 14px; padding: 5px 12px; font-weight: 600;">
							<?php echo esc_html( $payment_status ); ?>
						</span>
					</div>
					<div class="tpm-kpi-sub" style="margin-top: 5px;">
						<?php
						if ( 'Paid' === $payment_status ) {
							esc_html_e( 'Invoice fully settled', 'tender-po-management' );
						} elseif ( 'Overdue' === $payment_status ) {
							esc_html_e( 'Payment deadline passed', 'tender-po-management' );
						} elseif ( 'Partial' === $payment_status ) {
							esc_html_e( 'Partial payment recorded', 'tender-po-management' );
						} else {
							esc_html_e( 'Awaiting initial payment', 'tender-po-management' );
						}
						?>
					</div>
				</div>
			</div>
		</div>

		<!-- Main 2-Column Content Grid: Payment History Table & Record Payment Form -->
		<div class="tpm-dashboard-cols" style="margin-top: 20px;">

			<!-- Left Column: Payment History Table -->
			<div class="tpm-col-main">
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-list-view"></span>
							<?php esc_html_e( 'Payment History', 'tender-po-management' ); ?>
							<span class="tpm-badge tpm-badge-count"><?php echo count( $payments ); ?></span>
						</h2>
					</div>
					<div class="tpm-card-body" style="padding: 0;">
						<?php if ( empty( $payments ) ) : ?>
							<div class="tpm-empty-placeholder" style="padding: 35px; text-align: center;">
								<span class="dashicons dashicons-money-alt" style="font-size: 36px; width: 36px; height: 36px; color: #cbd5e1; margin-bottom: 10px;"></span>
								<p class="description" style="font-size: 14px; margin-bottom: 0;">
									<?php esc_html_e( 'No payments recorded yet for this record. Use the form on the right to register payments.', 'tender-po-management' ); ?>
								</p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th style="width: 130px;"><?php esc_html_e( 'Amount', 'tender-po-management' ); ?></th>
										<th style="width: 110px;"><?php esc_html_e( 'Date', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Method', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Reference', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Added By', 'tender-po-management' ); ?></th>
										<th style="width: 100px; text-align: right;"><?php esc_html_e( 'Actions', 'tender-po-management' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $payments as $pay ) :
										$edit_nonce = wp_create_nonce( 'tpm_update_payment_' . $pay->id );
										$del_nonce  = wp_create_nonce( 'tpm_delete_payment_' . $pay->id );
										$del_url    = admin_url( 'admin-post.php?action=tpm_delete_payment&record_id=' . $record_id . '&id=' . $pay->id . '&tpm_nonce=' . $del_nonce );
										?>
										<tr>
											<td>
												<strong class="tpm-metric-positive" style="font-size: 14px;">
													<?php echo esc_html( $currency_symbol . number_format( (float) $pay->payment_amount, 2 ) ); ?>
												</strong>
											</td>
											<td>
												<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $pay->payment_date ) ) ); ?>
											</td>
											<td>
												<span class="tpm-tag">
													<span class="dashicons dashicons-money"></span>
													<?php echo esc_html( $pay->payment_method ?: __( 'Standard', 'tender-po-management' ) ); ?>
												</span>
											</td>
											<td>
												<?php if ( ! empty( $pay->reference_number ) ) : ?>
													<code><?php echo esc_html( $pay->reference_number ); ?></code>
												<?php else : ?>
													<span class="tpm-text-muted">&mdash;</span>
												<?php endif; ?>
												<?php if ( ! empty( $pay->notes ) ) : ?>
													<div class="tpm-field-hint" style="margin-top: 3px; font-style: italic;"><?php echo esc_html( $pay->notes ); ?></div>
												<?php endif; ?>
											</td>
											<td>
												<?php echo esc_html( $pay->created_by_name ?: __( 'System', 'tender-po-management' ) ); ?>
											</td>
											<td style="text-align: right;">
												<div class="tpm-row-btn-group">
													<?php if ( $can_edit ) : ?>
														<button type="button"
															class="button button-small tpm-btn-edit-payment"
															data-id="<?php echo esc_attr( $pay->id ); ?>"
															data-amount="<?php echo esc_attr( $pay->payment_amount ); ?>"
															data-date="<?php echo esc_attr( $pay->payment_date ); ?>"
															data-method="<?php echo esc_attr( $pay->payment_method ); ?>"
															data-reference="<?php echo esc_attr( $pay->reference_number ); ?>"
															data-notes="<?php echo esc_attr( $pay->notes ); ?>"
															data-nonce="<?php echo esc_attr( $edit_nonce ); ?>"
															title="<?php esc_attr_e( 'Edit Payment', 'tender-po-management' ); ?>">
															<span class="dashicons dashicons-edit"></span>
														</button>
														<a href="<?php echo esc_url( $del_url ); ?>"
															class="button button-small"
															style="color: #dc2626;"
															data-tpm-confirm="<?php esc_attr_e( 'Are you sure you want to delete this payment record?', 'tender-po-management' ); ?>"
															title="<?php esc_attr_e( 'Delete Payment', 'tender-po-management' ); ?>">
															<span class="dashicons dashicons-trash"></span>
														</a>
													<?php else : ?>
														<span class="tpm-text-muted">&mdash;</span>
													<?php endif; ?>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Right Column: Record New Payment Form -->
			<div class="tpm-col-side">
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Record New Payment', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body" style="padding: 20px;">
						<?php if ( $can_edit ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-form">
								<input type="hidden" name="action" value="tpm_add_payment">
								<input type="hidden" name="record_id" value="<?php echo esc_attr( $record_id ); ?>">
								<?php wp_nonce_field( 'tpm_add_payment_' . $record_id, 'tpm_payment_nonce' ); ?>

								<!-- Payment Amount -->
								<div class="tpm-form-group">
									<label for="new_payment_amount" class="tpm-label tpm-required"><?php printf( esc_html__( 'Payment Amount (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
									<input type="number" step="0.01" min="0.01" name="payment_amount" id="new_payment_amount" class="tpm-input" value="<?php echo ( $balance_due > 0 ) ? esc_attr( number_format( $balance_due, 2, '.', '' ) ) : ''; ?>" required placeholder="0.00">
									<span class="tpm-field-hint">
										<?php
										/* translators: 1: Currency symbol, 2: Remaining balance due */
										printf( esc_html__( 'Outstanding balance due: %1$s%2$s', 'tender-po-management' ), esc_html( $currency_symbol ), esc_html( number_format( $balance_due, 2 ) ) );
										?>
									</span>
								</div>

								<!-- Payment Date -->
								<div class="tpm-form-group">
									<label for="new_payment_date" class="tpm-label tpm-required"><?php esc_html_e( 'Payment Date', 'tender-po-management' ); ?></label>
									<input type="date" name="payment_date" id="new_payment_date" class="tpm-input" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
								</div>

								<!-- Payment Method -->
								<div class="tpm-form-group">
									<label for="new_payment_method" class="tpm-label"><?php esc_html_e( 'Payment Method', 'tender-po-management' ); ?></label>
									<select name="payment_method" id="new_payment_method" class="tpm-select">
										<option value="Bank Transfer / Wire"><?php esc_html_e( 'Bank Transfer / Wire', 'tender-po-management' ); ?></option>
										<option value="Check"><?php esc_html_e( 'Check', 'tender-po-management' ); ?></option>
										<option value="Credit Card"><?php esc_html_e( 'Credit Card', 'tender-po-management' ); ?></option>
										<option value="Cash"><?php esc_html_e( 'Cash', 'tender-po-management' ); ?></option>
										<option value="Online Payment"><?php esc_html_e( 'Online Payment', 'tender-po-management' ); ?></option>
										<option value="Letter of Credit (LC)"><?php esc_html_e( 'Letter of Credit (LC)', 'tender-po-management' ); ?></option>
										<option value="Other"><?php esc_html_e( 'Other', 'tender-po-management' ); ?></option>
									</select>
								</div>

								<!-- Reference Number -->
								<div class="tpm-form-group">
									<label for="new_reference_number" class="tpm-label"><?php esc_html_e( 'Reference Number', 'tender-po-management' ); ?></label>
									<input type="text" name="reference_number" id="new_reference_number" class="tpm-input" placeholder="<?php esc_attr_e( 'e.g. Wire Ref, Check #, Transaction ID', 'tender-po-management' ); ?>">
								</div>

								<!-- Notes -->
								<div class="tpm-form-group">
									<label for="new_payment_notes" class="tpm-label"><?php esc_html_e( 'Notes & Remarks', 'tender-po-management' ); ?></label>
									<textarea name="notes" id="new_payment_notes" class="tpm-textarea" rows="2" placeholder="<?php esc_attr_e( 'Optional remarks or payment details...', 'tender-po-management' ); ?>"></textarea>
								</div>

								<div style="margin-top: 15px;">
									<button type="submit" class="tpm-btn tpm-btn-primary" style="width: 100%; justify-content: center;">
										<span class="dashicons dashicons-saved"></span>
										<?php esc_html_e( 'Record Payment', 'tender-po-management' ); ?>
									</button>
								</div>
							</form>
						<?php else : ?>
							<p class="tpm-text-muted"><?php esc_html_e( 'You do not have permission to record payments.', 'tender-po-management' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</div>

	</div>

	<!-- ==================================================================== -->
	<!-- TAB 3: SHIPPING TAB                                                  -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane" id="tab-pane-shipping">
		<div class="tpm-card">
			<div class="tpm-card-header">
				<h2>
					<span class="dashicons dashicons-location-alt"></span>
					<?php esc_html_e( 'Logistics & Shipping Details', 'tender-po-management' ); ?>
				</h2>
			</div>
			<div class="tpm-card-body">
				<div class="tpm-detail-grid">
					<div class="tpm-detail-field">
						<span class="tpm-field-name"><?php esc_html_e( 'Shipping Date', 'tender-po-management' ); ?></span>
						<span class="tpm-field-val">
							<?php echo ! empty( $record->shipping_date ) ? esc_html( $record->shipping_date ) : '<span class="tpm-text-muted">—</span>'; ?>
						</span>
					</div>

					<div class="tpm-detail-field">
						<span class="tpm-field-name"><?php esc_html_e( 'Tracking Number', 'tender-po-management' ); ?></span>
						<span class="tpm-field-val">
							<?php if ( ! empty( $record->tracking_number ) ) : ?>
								<code><?php echo esc_html( $record->tracking_number ); ?></code>
							<?php else : ?>
								<span class="tpm-text-muted">—</span>
							<?php endif; ?>
						</span>
					</div>

					<div class="tpm-detail-field">
						<span class="tpm-field-name"><?php esc_html_e( 'FF Location (Freight Forwarder Hub)', 'tender-po-management' ); ?></span>
						<span class="tpm-field-val">
							<?php echo ! empty( $record->ff_location ) ? esc_html( $record->ff_location ) : '<span class="tpm-text-muted">—</span>'; ?>
						</span>
					</div>

					<div class="tpm-detail-field">
						<span class="tpm-field-name"><?php esc_html_e( 'Original AWB / BoL Received', 'tender-po-management' ); ?></span>
						<span class="tpm-field-val">
							<?php if ( ! empty( $record->original_awb_received ) ) : ?>
								<span class="tpm-badge tpm-badge-active">
									<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Yes - Original Received', 'tender-po-management' ); ?>
								</span>
							<?php else : ?>
								<span class="tpm-badge tpm-badge-draft">
									<span class="dashicons dashicons-no"></span> <?php esc_html_e( 'No - Pending Physical Document', 'tender-po-management' ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>

					<div class="tpm-detail-field">
						<span class="tpm-field-name"><?php esc_html_e( 'Fill PO Number', 'tender-po-management' ); ?></span>
						<span class="tpm-field-val">
							<?php echo ! empty( $record->fill_po ) ? esc_html( $record->fill_po ) : '<span class="tpm-text-muted">—</span>'; ?>
						</span>
					</div>

					<div class="tpm-detail-field">
						<span class="tpm-field-name"><?php esc_html_e( 'Fill Invoice Number', 'tender-po-management' ); ?></span>
						<span class="tpm-field-val">
							<?php echo ! empty( $record->fill_invoice_number ) ? esc_html( $record->fill_invoice_number ) : '<span class="tpm-text-muted">—</span>'; ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ==================================================================== -->
	<!-- TAB 4: ATTACHMENTS TAB                                               -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane" id="tab-pane-attachments">
		<div class="tpm-card">
			<div class="tpm-card-header">
				<h2>
					<span class="dashicons dashicons-paperclip"></span>
					<?php esc_html_e( 'Document Attachments', 'tender-po-management' ); ?>
					<span class="tpm-badge"><?php echo count( $attachments ); ?></span>
				</h2>
				<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
					<button type="button" class="tpm-btn tpm-btn-primary" id="tpm-details-upload-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( '+ Upload Attachment', 'tender-po-management' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<div class="tpm-card-body">

				<!-- Staging Box for adding new attachments via Media Library -->
				<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
					<div class="tpm-staged-upload-container" id="tpm-details-staging-box" style="display: none;">
						<div class="tpm-staged-header">
							<strong><span class="dashicons dashicons-cloud-upload"></span> <?php esc_html_e( 'Selected Files Ready to Attach', 'tender-po-management' ); ?></strong>
							<span class="tpm-text-muted" style="font-size: 12px;"><?php esc_html_e( 'Assign labels and click Save Attachments', 'tender-po-management' ); ?></span>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tpm-add-attachments-form">
							<input type="hidden" name="action" value="tpm_add_attachments">
							<input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
							<?php wp_nonce_field( 'tpm_add_attachments_action', 'tpm_nonce' ); ?>

							<div id="tpm-details-staged-list" class="tpm-staged-list"></div>

							<div class="tpm-staged-actions" style="margin-top: 14px; display: flex; gap: 10px;">
								<button type="submit" class="tpm-btn tpm-btn-primary">
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e( 'Save Attachments to Record', 'tender-po-management' ); ?>
								</button>
								<button type="button" class="tpm-btn tpm-btn-secondary" id="tpm-details-cancel-upload-btn">
									<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
								</button>
							</div>
						</form>
					</div>
				<?php endif; ?>

				<!-- Existing Attachments List -->
				<?php if ( ! empty( $attachments ) ) : ?>
					<div class="tpm-attachment-table-wrap">
						<table class="widefat striped tpm-attachments-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Document', 'tender-po-management' ); ?></th>
									<th><?php esc_html_e( 'Label', 'tender-po-management' ); ?></th>
									<th><?php esc_html_e( 'Type & Size', 'tender-po-management' ); ?></th>
									<th><?php esc_html_e( 'Uploaded By', 'tender-po-management' ); ?></th>
									<th><?php esc_html_e( 'Upload Date', 'tender-po-management' ); ?></th>
									<th style="text-align: right;"><?php esc_html_e( 'Actions', 'tender-po-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $attachments as $att ) :
									$url           = wp_get_attachment_url( $att->attachment_id );
									$is_pdf        = ( 'application/pdf' === $att->file_type || 'pdf' === strtolower( pathinfo( $att->file_name, PATHINFO_EXTENSION ) ) );
									$is_img        = ( 0 === strpos( $att->file_type, 'image/' ) );
									$uploader      = get_userdata( $att->uploaded_by );
									$uploader_name = $uploader ? $uploader->display_name : __( 'System', 'tender-po-management' );

									// Determine icon
									$icon_class = 'dashicons-media-default';
									if ( $is_pdf ) {
										$icon_class = 'dashicons-media-document tpm-color-pdf';
									} elseif ( $is_img ) {
										$icon_class = 'dashicons-format-image tpm-color-img';
									} elseif ( false !== strpos( $att->file_type, 'sheet' ) || false !== strpos( $att->file_type, 'excel' ) ) {
										$icon_class = 'dashicons-media-spreadsheet tpm-color-sheet';
									} elseif ( false !== strpos( $att->file_type, 'word' ) ) {
										$icon_class = 'dashicons-media-document tpm-color-word';
									}
									?>
									<tr class="tpm-attachment-item-row" id="tpm-att-row-<?php echo esc_attr( $att->id ); ?>">
										<td>
											<div class="tpm-att-title-cell">
												<span class="dashicons <?php echo esc_attr( $icon_class ); ?> tpm-table-file-icon"></span>
												<strong><?php echo esc_html( $att->file_name ); ?></strong>
											</div>
										</td>
										<td>
											<span class="tpm-tag tpm-tag-label"><?php echo esc_html( $att->attachment_label ?: __( 'Other', 'tender-po-management' ) ); ?></span>
										</td>
										<td>
											<span class="tpm-text-muted"><?php echo esc_html( $att->file_type ); ?></span>
											<?php if ( ! empty( $att->file_size ) ) : ?>
												<div><small class="tpm-text-muted"><?php echo esc_html( size_format( (int) $att->file_size ) ); ?></small></div>
											<?php endif; ?>
										</td>
										<td>
											<?php echo esc_html( $uploader_name ); ?>
										</td>
										<td>
											<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $att->uploaded_at ) ) ); ?>
										</td>
										<td style="text-align: right; white-space: nowrap;">
											<div class="tpm-att-actions-wrap">
												<!-- Preview Button -->
												<?php if ( $is_pdf && $url ) : ?>
													<button type="button" class="tpm-btn tpm-btn-secondary tpm-btn-sm tpm-preview-pdf-btn" data-pdf-url="<?php echo esc_url( $url ); ?>" data-pdf-title="<?php echo esc_attr( $att->file_name ); ?>">
														<span class="dashicons dashicons-visibility"></span>
														<?php esc_html_e( 'Preview', 'tender-po-management' ); ?>
													</button>
												<?php elseif ( $is_img && $url ) : ?>
													<button type="button" class="tpm-btn tpm-btn-secondary tpm-btn-sm tpm-preview-img-btn" data-img-url="<?php echo esc_url( $url ); ?>" data-img-title="<?php echo esc_attr( $att->file_name ); ?>">
														<span class="dashicons dashicons-visibility"></span>
														<?php esc_html_e( 'Preview', 'tender-po-management' ); ?>
													</button>
												<?php endif; ?>

												<!-- Download Button -->
												<?php if ( $url ) : ?>
													<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="tpm-btn tpm-btn-secondary tpm-btn-sm" download title="<?php esc_attr_e( 'Download File', 'tender-po-management' ); ?>">
														<span class="dashicons dashicons-download"></span>
														<?php esc_html_e( 'Download', 'tender-po-management' ); ?>
													</a>
												<?php endif; ?>

												<!-- Delete Button (Authorized only) -->
												<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-inline-form">
														<input type="hidden" name="action" value="tpm_delete_attachment">
														<input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
														<input type="hidden" name="attachment_id" value="<?php echo esc_attr( $att->id ); ?>">
														<?php wp_nonce_field( 'tpm_delete_attachment_' . $att->id, 'tpm_nonce' ); ?>
														<button type="submit" class="tpm-btn tpm-btn-danger tpm-btn-sm" data-tpm-confirm="<?php esc_attr_e( 'Are you sure you want to remove this attachment from the record? (The file in your Media Library will not be deleted)', 'tender-po-management' ); ?>" title="<?php esc_attr_e( 'Delete attachment relationship', 'tender-po-management' ); ?>">
															<span class="dashicons dashicons-trash"></span>
															<?php esc_html_e( 'Delete', 'tender-po-management' ); ?>
														</button>
													</form>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="tpm-no-attachments" id="tpm-no-attachments-view-placeholder">
						<span class="dashicons dashicons-media-document"></span>
						<p><?php esc_html_e( 'No attachments currently linked to this record.', 'tender-po-management' ); ?></p>
						<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
							<button type="button" class="tpm-btn tpm-btn-secondary" id="tpm-details-upload-empty-btn">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e( '+ Upload Your First Attachment', 'tender-po-management' ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div>
		</div>
	</div>

	<!-- ==================================================================== -->
	<!-- TAB 5: NOTES TAB                                                     -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane" id="tab-pane-notes">
		<div class="tpm-card">
			<div class="tpm-card-header">
				<h2>
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Internal Notes & Remarks', 'tender-po-management' ); ?>
				</h2>
			</div>
			<div class="tpm-card-body">
				<!-- Notes Display -->
				<div class="tpm-notes-display">
					<span class="tpm-field-name"><?php esc_html_e( 'Current Notes Content', 'tender-po-management' ); ?></span>
					<div class="tpm-desc-box">
						<?php echo ! empty( $record->notes ) ? nl2br( wp_kses_post( $record->notes ) ) : '<span class="tpm-text-muted">' . esc_html__( 'No internal notes saved for this record.', 'tender-po-management' ) . '</span>'; ?>
					</div>
				</div>

				<!-- Quick Notes Edit Form (If authorized) -->
				<?php if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) : ?>
					<div class="tpm-notes-edit-box">
						<hr style="margin: 24px 0 20px 0; border: 0; border-top: 1px solid var(--tpm-border-subtle);">
						<h3><?php esc_html_e( 'Update Internal Notes', 'tender-po-management' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="tpm_update_notes">
							<input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
							<?php wp_nonce_field( 'tpm_update_notes_' . $record->id, 'tpm_nonce' ); ?>

							<div class="tpm-form-group">
								<label for="edit_record_notes" class="screen-reader-text"><?php esc_html_e( 'Edit Notes', 'tender-po-management' ); ?></label>
								<textarea name="notes" id="edit_record_notes" class="tpm-textarea" rows="5" placeholder="<?php esc_attr_e( 'Enter updated internal remarks or instructions...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $record->notes ?? '' ); ?></textarea>
							</div>

							<button type="submit" class="tpm-btn tpm-btn-primary">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Save Notes', 'tender-po-management' ); ?>
							</button>
						</form>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ==================================================================== -->
	<!-- TAB 6: ACTIVITY TAB                                                  -->
	<!-- ==================================================================== -->
	<div class="tpm-tab-pane" id="tab-pane-activity">
		<div class="tpm-card">
			<div class="tpm-card-header">
				<h2>
					<span class="dashicons dashicons-backup"></span>
					<?php esc_html_e( 'Chronological Activity Audit Trail', 'tender-po-management' ); ?>
					<span class="tpm-badge"><?php echo count( $activity_logs ); ?></span>
				</h2>
			</div>
			<div class="tpm-card-body">
				<?php if ( ! empty( $activity_logs ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Timestamp', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'User', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Action', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Description & Changes', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $activity_logs as $log ) :
								$log_u = get_userdata( $log->user_id );
								$u_name = $log_u ? $log_u->display_name : __( 'System', 'tender-po-management' );
								?>
								<tr>
									<td style="white-space: nowrap;">
										<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?></strong>
										<div class="tpm-text-muted" style="font-size: 11px;">
											<?php echo esc_html( human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'tender-po-management' ) ); ?>
										</div>
									</td>
									<td>
										<strong><?php echo esc_html( $u_name ); ?></strong>
									</td>
									<td>
										<code><?php echo esc_html( $log->action ); ?></code>
									</td>
									<td>
										<?php echo esc_html( $log->description ); ?>
										<?php if ( ! empty( $log->new_value ) ) : ?>
											<div class="tpm-text-muted" style="font-size: 11px; margin-top: 2px;">
												<code><?php echo esc_html( $log->new_value ); ?></code>
											</div>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="tpm-text-muted"><?php esc_html_e( 'No activity records found.', 'tender-po-management' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ==================================================================== -->
	<!-- PDF & DOCUMENT PREVIEW MODAL DIALOG                                  -->
	<!-- ==================================================================== -->
	<div id="tpm-preview-modal" class="tpm-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="tpm-preview-modal-title">
		<div class="tpm-modal-backdrop" id="tpm-modal-backdrop"></div>
		<div class="tpm-modal-dialog">
			<div class="tpm-modal-header">
				<div class="tpm-modal-title-wrap">
					<span class="dashicons dashicons-media-document tpm-modal-icon"></span>
					<h3 id="tpm-preview-modal-title" class="tpm-modal-title"><?php esc_html_e( 'Document Preview', 'tender-po-management' ); ?></h3>
				</div>
				<div class="tpm-modal-header-actions">
					<a href="#" id="tpm-modal-external-link" target="_blank" rel="noopener noreferrer" class="tpm-btn tpm-btn-secondary tpm-btn-sm" title="<?php esc_attr_e( 'Open in new tab', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'Open in New Tab', 'tender-po-management' ); ?>
					</a>
					<button type="button" class="tpm-modal-close" id="tpm-modal-close-btn" aria-label="<?php esc_attr_e( 'Close Preview', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<div class="tpm-modal-body">
				<!-- PDF Viewer Area (Browser-Native PDF rendering via iframe) -->
				<div id="tpm-modal-pdf-container" class="tpm-modal-content-container" style="display: none; height: 100%; width: 100%;">
					<iframe id="tpm-pdf-iframe" src="" title="<?php esc_attr_e( 'PDF Document Viewer', 'tender-po-management' ); ?>" width="100%" height="100%" frameborder="0"></iframe>
				</div>

				<!-- Image Viewer Area -->
				<div id="tpm-modal-img-container" class="tpm-modal-content-container" style="display: none; text-align: center;">
					<img id="tpm-preview-img" src="" alt="<?php esc_attr_e( 'Document Preview', 'tender-po-management' ); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
				</div>
			</div>
		</div>
	<!-- EDIT PAYMENT MODAL DIALOG -->
	<div id="tpm-edit-payment-modal" class="tpm-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="tpm-edit-payment-title">
		<div class="tpm-modal-backdrop" id="tpm-edit-payment-backdrop"></div>
		<div class="tpm-modal-dialog" style="max-width: 520px;">
			<div class="tpm-modal-header">
				<div class="tpm-modal-title-wrap">
					<span class="dashicons dashicons-money-alt tpm-modal-icon"></span>
					<h3 id="tpm-edit-payment-title" class="tpm-modal-title"><?php esc_html_e( 'Edit Payment Record', 'tender-po-management' ); ?></h3>
				</div>
				<div class="tpm-modal-header-actions">
					<button type="button" class="tpm-modal-close" id="tpm-edit-payment-close-btn" aria-label="<?php esc_attr_e( 'Close', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-form" id="tpm-edit-payment-form">
				<input type="hidden" name="action" value="tpm_update_payment">
				<input type="hidden" name="record_id" value="<?php echo esc_attr( $record_id ); ?>">
				<input type="hidden" name="payment_id" id="tpm-edit-payment-id" value="">
				<input type="hidden" name="tpm_payment_nonce" id="tpm-edit-payment-nonce" value="">

				<div class="tpm-modal-body" style="padding: 20px;">
					<div class="tpm-form-group">
						<label for="edit_payment_amount" class="tpm-label tpm-required"><?php printf( esc_html__( 'Payment Amount (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
						<input type="number" step="0.01" min="0.01" name="payment_amount" id="edit_payment_amount" class="tpm-input" required>
					</div>

					<div class="tpm-form-row tpm-cols-2">
						<div class="tpm-form-group">
							<label for="edit_payment_date" class="tpm-label tpm-required"><?php esc_html_e( 'Payment Date', 'tender-po-management' ); ?></label>
							<input type="date" name="payment_date" id="edit_payment_date" class="tpm-input" required>
						</div>

						<div class="tpm-form-group">
							<label for="edit_payment_method" class="tpm-label"><?php esc_html_e( 'Payment Method', 'tender-po-management' ); ?></label>
							<select name="payment_method" id="edit_payment_method" class="tpm-select">
								<option value="Bank Transfer / Wire"><?php esc_html_e( 'Bank Transfer / Wire', 'tender-po-management' ); ?></option>
								<option value="Check"><?php esc_html_e( 'Check', 'tender-po-management' ); ?></option>
								<option value="Credit Card"><?php esc_html_e( 'Credit Card', 'tender-po-management' ); ?></option>
								<option value="Cash"><?php esc_html_e( 'Cash', 'tender-po-management' ); ?></option>
								<option value="Online Payment"><?php esc_html_e( 'Online Payment', 'tender-po-management' ); ?></option>
								<option value="Letter of Credit (LC)"><?php esc_html_e( 'Letter of Credit (LC)', 'tender-po-management' ); ?></option>
								<option value="Other"><?php esc_html_e( 'Other', 'tender-po-management' ); ?></option>
							</select>
						</div>
					</div>

					<div class="tpm-form-group">
						<label for="edit_reference_number" class="tpm-label"><?php esc_html_e( 'Reference / Transaction #', 'tender-po-management' ); ?></label>
						<input type="text" name="reference_number" id="edit_reference_number" class="tpm-input">
					</div>

					<div class="tpm-form-group">
						<label for="edit_payment_notes" class="tpm-label"><?php esc_html_e( 'Notes & Remarks', 'tender-po-management' ); ?></label>
						<textarea name="notes" id="edit_payment_notes" class="tpm-textarea" rows="2"></textarea>
					</div>
				</div>

				<div class="tpm-modal-footer" style="padding: 12px 20px; display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
					<button type="button" class="button button-secondary" id="tpm-edit-payment-cancel-btn"><?php esc_html_e( 'Cancel', 'tender-po-management' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'tender-po-management' ); ?></button>
				</div>
			</form>
		</div>
	</div>

</div>
