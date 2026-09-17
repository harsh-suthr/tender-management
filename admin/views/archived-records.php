<?php
/**
 * View: Archived Records
 *
 * Scoped WP_List_Table for Archived Tenders and Purchase Orders.
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
if ( ! current_user_can( 'view_tpm_records' ) || ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to view archived records.', 'tender-po-management' ) );
}

$current_user_id = get_current_user_id();
$selected_type   = sanitize_key( $_REQUEST['record_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Instantiate WP_List_Table scoped strictly to archived records
$list_table = new TPM_Records_List_Table(
	array(
		'screen'         => 'tpm-archived-records',
		'preset_filters' => array(
			'is_archived' => 1,
			'view'        => 'archived',
		),
	)
);

$list_table->prepare_items();

// Fetch summary metrics for top KPI cards
$summary         = TPM_Record_Service::get_archived_summary();
$currency_symbol = TPM_Settings_Service::get_currency_symbol();

// Handle transient flash notices and errors
$notice_key = 'tpm_record_notice_' . $current_user_id;
$notice_msg = get_transient( $notice_key );
if ( ! empty( $notice_msg ) ) {
	delete_transient( $notice_key );
}

$error_key = 'tpm_record_errors_' . $current_user_id;
$error_msg = get_transient( $error_key );
if ( ! empty( $error_msg ) ) {
	delete_transient( $error_key );
}
?>
<div class="wrap tpm-admin-wrap">

	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'Archived Records', 'tender-po-management' ); ?>
				<span class="tpm-version-badge"><?php esc_html_e( 'Archive Vault', 'tender-po-management' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Historical repository of concluded, cancelled, and retired tenders and purchase orders.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-btn tpm-btn-secondary">
				<span class="dashicons dashicons-arrow-left-alt"></span>
				<?php esc_html_e( 'Back to Active Records', 'tender-po-management' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! empty( $notice_msg ) ) : ?>
		<div class="notice notice-success is-dismissible tpm-notice">
			<p><?php echo esc_html( $notice_msg ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $error_msg ) ) : ?>
		<div class="notice notice-error is-dismissible tpm-notice">
			<p><?php echo esc_html( $error_msg ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Top Metric KPI Summary Cards -->
	<div class="tpm-dashboard-kpis" style="margin-bottom: 20px;">
		<div class="tpm-kpi-card">
			<div class="tpm-kpi-icon icon-all">
				<span class="dashicons dashicons-archive"></span>
			</div>
			<div class="tpm-kpi-content">
				<div class="tpm-kpi-label"><?php esc_html_e( 'Total Archived', 'tender-po-management' ); ?></div>
				<div class="tpm-kpi-value"><?php echo esc_html( number_format_i18n( $summary['total_archived'] ) ); ?></div>
				<div class="tpm-kpi-sub"><?php esc_html_e( 'Retired Tenders & POs', 'tender-po-management' ); ?></div>
			</div>
		</div>

		<div class="tpm-kpi-card">
			<div class="tpm-kpi-icon icon-tenders">
				<span class="dashicons dashicons-clipboard"></span>
			</div>
			<div class="tpm-kpi-content">
				<div class="tpm-kpi-label"><?php esc_html_e( 'Archived Tenders', 'tender-po-management' ); ?></div>
				<div class="tpm-kpi-value"><?php echo esc_html( number_format_i18n( $summary['archived_tenders'] ) ); ?></div>
				<div class="tpm-kpi-sub"><?php esc_html_e( 'Historical Tender Records', 'tender-po-management' ); ?></div>
			</div>
		</div>

		<div class="tpm-kpi-card">
			<div class="tpm-kpi-icon icon-pos">
				<span class="dashicons dashicons-cart"></span>
			</div>
			<div class="tpm-kpi-content">
				<div class="tpm-kpi-label"><?php esc_html_e( 'Archived POs', 'tender-po-management' ); ?></div>
				<div class="tpm-kpi-value"><?php echo esc_html( number_format_i18n( $summary['archived_pos'] ) ); ?></div>
				<div class="tpm-kpi-sub"><?php esc_html_e( 'Historical Purchase Orders', 'tender-po-management' ); ?></div>
			</div>
		</div>

		<div class="tpm-kpi-card">
			<div class="tpm-kpi-icon icon-finance">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="tpm-kpi-content">
				<div class="tpm-kpi-label"><?php esc_html_e( 'Total Archived Value', 'tender-po-management' ); ?></div>
				<div class="tpm-kpi-value"><?php echo esc_html( $currency_symbol . number_format( $summary['total_archived_value'], 2 ) ); ?></div>
				<div class="tpm-kpi-sub"><?php esc_html_e( 'Cumulative Contract Volume', 'tender-po-management' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Archive Vault Filter Navigation -->
	<ul class="subsubsub">
		<li class="all">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-archived-records' ) ); ?>" class="<?php echo empty( $selected_type ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'All Archived', 'tender-po-management' ); ?>
				<span class="count">(<?php echo esc_html( $summary['total_archived'] ); ?>)</span>
			</a> |
		</li>
		<li class="tenders">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-archived-records&record_type=tender' ) ); ?>" class="<?php echo ( 'tender' === $selected_type ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'Archived Tenders', 'tender-po-management' ); ?>
				<span class="count">(<?php echo esc_html( $summary['archived_tenders'] ); ?>)</span>
			</a> |
		</li>
		<li class="pos">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-archived-records&record_type=po' ) ); ?>" class="<?php echo ( 'po' === $selected_type ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'Archived Purchase Orders', 'tender-po-management' ); ?>
				<span class="count">(<?php echo esc_html( $summary['archived_pos'] ); ?>)</span>
			</a>
		</li>
	</ul>

	<!-- Archived Records Form -->
	<form id="tpm-archived-form" method="get">
		<input type="hidden" name="page" value="tpm-archived-records" />
		<input type="hidden" name="is_archived" value="1" />
		<input type="hidden" name="view" value="archived" />
		<?php if ( ! empty( $selected_type ) ) : ?>
			<input type="hidden" name="record_type" value="<?php echo esc_attr( $selected_type ); ?>" />
		<?php endif; ?>

		<!-- Search Box -->
		<?php $list_table->search_box( __( 'Search Archive', 'tender-po-management' ), 'tpm-search' ); ?>

		<!-- Security Nonce for Bulk Actions -->
		<?php wp_nonce_field( 'bulk-records' ); ?>

		<!-- List Table Display -->
		<?php $list_table->display(); ?>
	</form>

</div>
