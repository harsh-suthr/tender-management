<?php
/**
 * View: All Records
 *
 * Renders the full WP_List_Table registry for all Tenders and Purchase Orders.
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
	wp_die( esc_html__( 'You do not have sufficient permissions to view records.', 'tender-po-management' ) );
}

$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// If viewing a single record, delegate to record-details view
if ( 'view' === $action && ( ! empty( $_GET['id'] ) || ! empty( $_GET['record_id'] ) ) ) {
	require_once __DIR__ . '/record-details.php';
	return;
}

// If editing a single record, delegate to add-record view
if ( 'edit' === $action && ! empty( $_GET['id'] ) ) {
	require_once __DIR__ . '/add-record.php';
	return;
}

// Instantiate WP_List_Table for Records
$list_table = new TPM_Records_List_Table(
	array(
		'screen'         => 'tpm-records',
		'preset_filters' => array(),
	)
);

$list_table->prepare_items();

// Flash notices from transients
$notice_key = 'tpm_record_notice_' . get_current_user_id();
$notice_msg = get_transient( $notice_key );
if ( ! empty( $notice_msg ) ) {
	delete_transient( $notice_key );
}
?>
<div class="wrap tpm-admin-wrap">

	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'All Records', 'tender-po-management' ); ?>
				<span class="tpm-version-badge"><?php esc_html_e( 'Registry', 'tender-po-management' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Unified repository of all Tenders and Purchase Orders across the organization.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<?php if ( current_user_can( 'create_tpm_records' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-add-record' ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add New Record', 'tender-po-management' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $notice_msg ) ) : ?>
		<div class="notice notice-success is-dismissible tpm-notice">
			<p><?php echo esc_html( $notice_msg ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Quick Filter Views -->
	<?php $list_table->views(); ?>

	<!-- Main Records Form (Search, Filters, Table, Bulk Actions) -->
	<form id="tpm-records-form" method="get">
		<input type="hidden" name="page" value="tpm-records" />
		<?php if ( ! empty( $_REQUEST['view'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<input type="hidden" name="view" value="<?php echo esc_attr( sanitize_key( $_REQUEST['view'] ) ); ?>" />
		<?php endif; ?>

		<!-- Search Box -->
		<?php $list_table->search_box( __( 'Search Records', 'tender-po-management' ), 'tpm-search' ); ?>

		<!-- Security Nonce for Bulk Actions -->
		<?php wp_nonce_field( 'bulk-records' ); ?>

		<!-- List Table Display -->
		<?php $list_table->display(); ?>
	</form>

</div>
