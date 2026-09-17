<?php
/**
 * View: Customers Management
 *
 * Provides a complete Customer Directory, Add Customer, Edit Customer,
 * Customer Details (associated tenders, POs, financial metrics, activity),
 * and Safe Deletion Guard with Deactivation / Reassignment workflows.
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
if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to manage customers.', 'tender-po-management' ) );
}

$user_id       = get_current_user_id();
$notice_msg    = get_transient( 'tpm_customer_notice_' . $user_id );
$error_msg     = get_transient( 'tpm_customer_errors_' . $user_id );
$form_data     = get_transient( 'tpm_customer_form_data_' . $user_id ) ?: array();

if ( false !== $notice_msg ) {
	delete_transient( 'tpm_customer_notice_' . $user_id );
}
if ( false !== $error_msg ) {
	delete_transient( 'tpm_customer_errors_' . $user_id );
}
if ( ! empty( $form_data ) ) {
	delete_transient( 'tpm_customer_form_data_' . $user_id );
}

$action      = sanitize_key( $_GET['action'] ?? 'list' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$customer_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// =========================================================================
// SUB-VIEW 1: ADD NEW CUSTOMER
// =========================================================================
if ( 'new' === $action ) :
	$val = function( $key, $default = '' ) use ( $form_data ) {
		return isset( $form_data[ $key ] ) ? esc_attr( (string) $form_data[ $key ] ) : esc_attr( (string) $default );
	};
	?>
	<div class="wrap tpm-admin-wrap">

		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1>
					<?php esc_html_e( 'Add New Customer', 'tender-po-management' ); ?>
					<span class="tpm-version-badge"><?php esc_html_e( 'Client Registry', 'tender-po-management' ); ?></span>
				</h1>
				<p><?php esc_html_e( 'Register a new customer, ministry contact, or procurement organization.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Customers Directory', 'tender-po-management' ); ?>
				</a>
			</div>
		</div>

		<?php if ( ! empty( $error_msg ) ) : ?>
			<div class="notice notice-error tpm-notice is-dismissible">
				<p><strong><?php esc_html_e( 'Error:', 'tender-po-management' ); ?></strong></p>
				<div><?php echo wp_kses_post( $error_msg ); ?></div>
			</div>
		<?php endif; ?>

		<div class="tpm-structured-container" style="max-width: 860px; margin-top: 15px;">
			<div class="tpm-container-toolbar">
				<div class="tpm-toolbar-title">
					<span class="dashicons dashicons-businessman"></span>
					<?php esc_html_e( 'Customer Account Details', 'tender-po-management' ); ?>
				</div>
			</div>

			<div class="tpm-container-content" style="padding: 24px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-form">
					<input type="hidden" name="action" value="tpm_save_customer">
					<?php wp_nonce_field( 'tpm_save_customer_action', 'tpm_customer_nonce' ); ?>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Name (Required) -->
						<div class="tpm-form-group">
							<label for="cust_name" class="tpm-label tpm-required"><?php esc_html_e( 'Customer / Contact Name', 'tender-po-management' ); ?></label>
							<input type="text" name="name" id="cust_name" class="tpm-input" value="<?php echo $val( 'name' ); ?>" required placeholder="e.g. Dr. Arthur Pendelton">
							<span class="tpm-field-hint"><?php esc_html_e( 'Primary contact person or focal authority.', 'tender-po-management' ); ?></span>
						</div>

						<!-- Company Name -->
						<div class="tpm-form-group">
							<label for="cust_company" class="tpm-label"><?php esc_html_e( 'Company / Organization Name', 'tender-po-management' ); ?></label>
							<input type="text" name="company_name" id="cust_company" class="tpm-input" value="<?php echo $val( 'company_name' ); ?>" placeholder="e.g. Ministry of Health & Wellness">
						</div>
					</div>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Email -->
						<div class="tpm-form-group">
							<label for="cust_email" class="tpm-label"><?php esc_html_e( 'Email Address', 'tender-po-management' ); ?></label>
							<input type="email" name="email" id="cust_email" class="tpm-input" value="<?php echo $val( 'email' ); ?>" placeholder="e.g. procurement@ministry.gov">
						</div>

						<!-- Phone -->
						<div class="tpm-form-group">
							<label for="cust_phone" class="tpm-label"><?php esc_html_e( 'Phone Number', 'tender-po-management' ); ?></label>
							<input type="text" name="phone" id="cust_phone" class="tpm-input" value="<?php echo $val( 'phone' ); ?>" placeholder="e.g. +1 (555) 234-5678">
						</div>
					</div>

					<div class="tpm-form-group">
						<label for="cust_address" class="tpm-label"><?php esc_html_e( 'Physical / Billing Address', 'tender-po-management' ); ?></label>
						<textarea name="address" id="cust_address" class="tpm-textarea" rows="3" placeholder="<?php esc_attr_e( 'Street address, office suite, city, state, postal code, country...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $form_data['address'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group">
						<label for="cust_notes" class="tpm-label"><?php esc_html_e( 'Internal Notes & Remarks', 'tender-po-management' ); ?></label>
						<textarea name="notes" id="cust_notes" class="tpm-textarea" rows="3" placeholder="<?php esc_attr_e( 'Add internal notes, payment preferences, key account managers, or tax IDs...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $form_data['notes'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group" style="max-width: 250px;">
						<label for="cust_status" class="tpm-label"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></label>
						<select name="status" id="cust_status" class="tpm-select">
							<option value="active" <?php selected( $val( 'status', 'active' ), 'active' ); ?>><?php esc_html_e( 'Active', 'tender-po-management' ); ?></option>
							<option value="inactive" <?php selected( $val( 'status' ), 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'tender-po-management' ); ?></option>
						</select>
					</div>

					<div class="tpm-action-buttons" style="margin-top: 24px; display: flex; gap: 12px;">
						<button type="submit" class="tpm-btn tpm-btn-primary">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save Customer', 'tender-po-management' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers' ) ); ?>" class="tpm-btn tpm-btn-secondary">
							<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
						</a>
					</div>
				</form>
			</div>
		</div>

	</div>
	<?php
	return;
endif;

// =========================================================================
// SUB-VIEW 2: EDIT CUSTOMER
// =========================================================================
if ( 'edit' === $action && $customer_id > 0 ) :
	$customer = TPM_Customer_Service::get_customer( $customer_id );
	if ( ! $customer ) {
		wp_die( esc_html__( 'Customer not found or has been deleted.', 'tender-po-management' ) );
	}

	$data_source = ! empty( $form_data ) ? $form_data : (array) $customer;
	$val = function( $key, $default = '' ) use ( $data_source ) {
		return isset( $data_source[ $key ] ) ? esc_attr( (string) $data_source[ $key ] ) : esc_attr( (string) $default );
	};
	?>
	<div class="wrap tpm-admin-wrap">

		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1>
					<?php
					/* translators: %s: Customer Name */
					printf( esc_html__( 'Edit Customer: %s', 'tender-po-management' ), esc_html( $customer->name ) );
					?>
					<span class="tpm-version-badge tpm-badge-edit"><?php echo esc_html( sprintf( __( 'ID: #%d', 'tender-po-management' ), $customer_id ) ); ?></span>
				</h1>
				<p><?php esc_html_e( 'Update customer information, contact details, status, or internal remarks.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=view&id=' . $customer_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Customer Details', 'tender-po-management' ); ?>
				</a>
			</div>
		</div>

		<?php if ( ! empty( $error_msg ) ) : ?>
			<div class="notice notice-error tpm-notice is-dismissible">
				<p><strong><?php esc_html_e( 'Please correct the following errors:', 'tender-po-management' ); ?></strong></p>
				<div><?php echo wp_kses_post( $error_msg ); ?></div>
			</div>
		<?php endif; ?>

		<div class="tpm-structured-container" style="max-width: 860px; margin-top: 15px;">
			<div class="tpm-container-toolbar">
				<div class="tpm-toolbar-title">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Edit Customer Profile', 'tender-po-management' ); ?>
				</div>
			</div>

			<div class="tpm-container-content" style="padding: 24px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-form">
					<input type="hidden" name="action" value="tpm_update_customer">
					<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer_id ); ?>">
					<?php wp_nonce_field( 'tpm_update_customer_action', 'tpm_customer_nonce' ); ?>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Name (Required) -->
						<div class="tpm-form-group">
							<label for="cust_name" class="tpm-label tpm-required"><?php esc_html_e( 'Customer / Contact Name', 'tender-po-management' ); ?></label>
							<input type="text" name="name" id="cust_name" class="tpm-input" value="<?php echo $val( 'name' ); ?>" required>
						</div>

						<!-- Company Name -->
						<div class="tpm-form-group">
							<label for="cust_company" class="tpm-label"><?php esc_html_e( 'Company / Organization Name', 'tender-po-management' ); ?></label>
							<input type="text" name="company_name" id="cust_company" class="tpm-input" value="<?php echo $val( 'company_name' ); ?>">
						</div>
					</div>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Email -->
						<div class="tpm-form-group">
							<label for="cust_email" class="tpm-label"><?php esc_html_e( 'Email Address', 'tender-po-management' ); ?></label>
							<input type="email" name="email" id="cust_email" class="tpm-input" value="<?php echo $val( 'email' ); ?>">
						</div>

						<!-- Phone -->
						<div class="tpm-form-group">
							<label for="cust_phone" class="tpm-label"><?php esc_html_e( 'Phone Number', 'tender-po-management' ); ?></label>
							<input type="text" name="phone" id="cust_phone" class="tpm-input" value="<?php echo $val( 'phone' ); ?>">
						</div>
					</div>

					<div class="tpm-form-group">
						<label for="cust_address" class="tpm-label"><?php esc_html_e( 'Physical / Billing Address', 'tender-po-management' ); ?></label>
						<textarea name="address" id="cust_address" class="tpm-textarea" rows="3"><?php echo esc_textarea( $data_source['address'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group">
						<label for="cust_notes" class="tpm-label"><?php esc_html_e( 'Internal Notes & Remarks', 'tender-po-management' ); ?></label>
						<textarea name="notes" id="cust_notes" class="tpm-textarea" rows="3"><?php echo esc_textarea( $data_source['notes'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group" style="max-width: 250px;">
						<label for="cust_status" class="tpm-label"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></label>
						<select name="status" id="cust_status" class="tpm-select">
							<option value="active" <?php selected( $val( 'status', 'active' ), 'active' ); ?>><?php esc_html_e( 'Active', 'tender-po-management' ); ?></option>
							<option value="inactive" <?php selected( $val( 'status' ), 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'tender-po-management' ); ?></option>
						</select>
					</div>

					<div class="tpm-action-buttons" style="margin-top: 24px; display: flex; gap: 12px;">
						<button type="submit" class="tpm-btn tpm-btn-primary">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Update Customer', 'tender-po-management' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=view&id=' . $customer_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
							<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
						</a>
					</div>
				</form>
			</div>
		</div>

	</div>
	<?php
	return;
endif;

// =========================================================================
// SUB-VIEW 3: CUSTOMER DETAILS (VIEW)
// =========================================================================
if ( 'view' === $action && $customer_id > 0 ) :
	$customer = TPM_Customer_Service::get_customer( $customer_id );
	if ( ! $customer ) {
		wp_die( esc_html__( 'Customer not found or has been deleted.', 'tender-po-management' ) );
	}

	$summary  = TPM_Customer_Service::get_customer_financial_summary( $customer_id );
	$tenders  = TPM_Customer_Service::get_customer_records( $customer_id, 'tender' );
	$pos      = TPM_Customer_Service::get_customer_records( $customer_id, 'po' );
	$activity = TPM_Customer_Service::get_customer_recent_activity( $customer_id, 10 );
	$del_info = TPM_Customer_Service::can_delete( $customer_id );
	$is_act   = ( 'active' === $customer->status );
	?>
	<div class="wrap tpm-admin-wrap">

		<?php if ( ! empty( $notice_msg ) || isset( $_GET['created'] ) || isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success tpm-notice is-dismissible">
				<p><strong><?php echo ! empty( $notice_msg ) ? esc_html( $notice_msg ) : ( isset( $_GET['updated'] ) ? esc_html__( 'Customer updated successfully!', 'tender-po-management' ) : esc_html__( 'Customer created successfully!', 'tender-po-management' ) ); ?></strong></p>
			</div>
		<?php endif; ?>

		<!-- Header Banner -->
		<div class="tpm-header-banner tpm-detail-header-banner">
			<div class="tpm-header-left">
				<div class="tpm-detail-badges">
					<span class="tpm-badge <?php echo $is_act ? 'tpm-badge-active' : 'tpm-badge-draft'; ?>">
						<?php echo $is_act ? esc_html__( 'Active Customer', 'tender-po-management' ) : esc_html__( 'Inactive Customer', 'tender-po-management' ); ?>
					</span>
					<span class="tpm-ref-badge"><?php echo esc_html( sprintf( __( 'ID: #%d', 'tender-po-management' ), $customer_id ) ); ?></span>
				</div>

				<h1>
					<?php echo esc_html( $customer->name ); ?>
					<?php if ( ! empty( $customer->company_name ) ) : ?>
						<span class="tpm-title-sub">&mdash; <?php echo esc_html( $customer->company_name ); ?></span>
					<?php endif; ?>
				</h1>
				<p>
					<?php
					/* translators: %s: Created date */
					printf( esc_html__( 'Registered on %s', 'tender-po-management' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $customer->created_at ) ) ) );
					?>
				</p>
			</div>

			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Customers', 'tender-po-management' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=edit&id=' . $customer_id ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Edit Customer', 'tender-po-management' ); ?>
				</a>

				<?php if ( $del_info['can_delete'] ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpm_delete_customer&id=' . $customer_id ), 'tpm_delete_customer_' . $customer_id, 'tpm_nonce' ) ); ?>" class="tpm-btn tpm-btn-secondary" style="color: #dc2626;" data-tpm-confirm="<?php esc_attr_e( 'Are you sure you want to delete this customer?', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Delete', 'tender-po-management' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=delete&id=' . $customer_id ) ); ?>" class="tpm-btn tpm-btn-secondary" title="<?php esc_attr_e( 'Manage dependencies before deleting', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Delete (Protected)', 'tender-po-management' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php $currency_symbol = TPM_Settings_Service::get_currency_symbol(); ?>
		<!-- Financial KPI Metrics Grid -->
		<div class="tpm-kpi-grid" style="margin-top: 20px;">
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Total Revenue (Sell Price)', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val"><?php echo esc_html( $currency_symbol . number_format( $summary['total_revenue'], 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Total Payments Received', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $summary['total_paid'], 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-clock"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Outstanding Balance Due', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val <?php echo ( $summary['total_balance_due'] > 0 ) ? 'tpm-metric-negative' : ''; ?>"><?php echo esc_html( $currency_symbol . number_format( $summary['total_balance_due'], 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-chart-area"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Cumulative Net Profit', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $summary['total_net_profit'], 2 ) ); ?></div>
				</div>
			</div>
		</div>

		<!-- Main 2-Column Content Grid -->
		<div class="tpm-detail-grid" style="margin-top: 20px;">

			<!-- Main Column: Associated Tenders & Associated POs -->
			<div class="tpm-detail-main">

				<!-- ASSOCIATED TENDERS -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Associated Tenders', 'tender-po-management' ); ?>
							<span class="tpm-badge tpm-badge-count"><?php echo count( $tenders ); ?></span>
						</h2>
					</div>
					<div class="tpm-card-body" style="padding: 0;">
						<?php if ( empty( $tenders ) ) : ?>
							<div class="tpm-empty-placeholder" style="padding: 25px; text-align: center;">
								<p class="description"><?php esc_html_e( 'No tender submissions associated with this customer yet.', 'tender-po-management' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Tender Number', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Sell Price', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Due Date', 'tender-po-management' ); ?></th>
										<th style="width: 80px; text-align: right;"><?php esc_html_e( 'Action', 'tender-po-management' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $tenders as $tnd ) : ?>
										<tr>
											<td>
												<strong>
													<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $tnd->id ) ); ?>">
														<?php echo esc_html( $tnd->tender_number ?: '#' . $tnd->id ); ?>
													</a>
												</strong>
											</td>
											<td><span class="tpm-badge"><?php echo esc_html( ucfirst( $tnd->status ) ); ?></span></td>
											<td><strong><?php echo esc_html( $currency_symbol . number_format( (float) $tnd->sell_price, 2 ) ); ?></strong></td>
											<td><?php echo ! empty( $tnd->tender_due_date ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tnd->tender_due_date ) ) ) : '&mdash;'; ?></td>
											<td style="text-align: right;">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $tnd->id ) ); ?>" class="button button-small">
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

				<!-- ASSOCIATED PURCHASE ORDERS -->
				<div class="tpm-card" style="margin-top: 20px;">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-cart"></span>
							<?php esc_html_e( 'Associated Purchase Orders', 'tender-po-management' ); ?>
							<span class="tpm-badge tpm-badge-count"><?php echo count( $pos ); ?></span>
						</h2>
					</div>
					<div class="tpm-card-body" style="padding: 0;">
						<?php if ( empty( $pos ) ) : ?>
							<div class="tpm-empty-placeholder" style="padding: 25px; text-align: center;">
								<p class="description"><?php esc_html_e( 'No purchase orders registered for this customer yet.', 'tender-po-management' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Customer PO #', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Sell Price', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'PO Date', 'tender-po-management' ); ?></th>
										<th style="width: 80px; text-align: right;"><?php esc_html_e( 'Action', 'tender-po-management' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $pos as $po_row ) : ?>
										<tr>
											<td>
												<strong>
													<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $po_row->id ) ); ?>">
														<?php echo esc_html( $po_row->customer_po_number ?: '#' . $po_row->id ); ?>
													</a>
												</strong>
												<?php if ( ! empty( $po_row->customer_po_name ) ) : ?>
													<div class="tpm-field-hint"><?php echo esc_html( $po_row->customer_po_name ); ?></div>
												<?php endif; ?>
											</td>
											<td><span class="tpm-badge"><?php echo esc_html( ucfirst( $po_row->status ) ); ?></span></td>
											<td><strong><?php echo esc_html( $currency_symbol . number_format( (float) $po_row->sell_price, 2 ) ); ?></strong></td>
											<td><?php echo ! empty( $po_row->po_date ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po_row->po_date ) ) ) : '&mdash;'; ?></td>
											<td style="text-align: right;">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $po_row->id ) ); ?>" class="button button-small">
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

			<!-- Sidebar Column: Contact Info & Activity -->
			<div class="tpm-detail-side">

				<!-- Contact Profile Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-id"></span>
							<?php esc_html_e( 'Contact Profile', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-meta-list">
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Email:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php if ( ! empty( $customer->email ) ) : ?>
										<a href="mailto:<?php echo esc_attr( $customer->email ); ?>"><?php echo esc_html( $customer->email ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Phone:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php if ( ! empty( $customer->phone ) ) : ?>
										<a href="tel:<?php echo esc_attr( $customer->phone ); ?>"><?php echo esc_html( $customer->phone ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Address:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php echo ! empty( $customer->address ) ? nl2br( esc_html( $customer->address ) ) : '&mdash;'; ?>
								</span>
							</div>

							<?php if ( ! empty( $customer->notes ) ) : ?>
								<div class="tpm-meta-row" style="flex-direction: column; align-items: flex-start; gap: 4px;">
									<span class="tpm-meta-row-label"><?php esc_html_e( 'Internal Notes:', 'tender-po-management' ); ?></span>
									<div class="tpm-notes-box-static"><?php echo nl2br( esc_html( $customer->notes ) ); ?></div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Recent Activity Timeline -->
				<div class="tpm-card" style="margin-top: 20px;">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-backup"></span>
							<?php esc_html_e( 'Recent Activity', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body" style="padding: 12px 16px;">
						<?php if ( empty( $activity ) ) : ?>
							<p class="description"><?php esc_html_e( 'No activity logs found for this customer.', 'tender-po-management' ); ?></p>
						<?php else : ?>
							<div class="tpm-activity-timeline">
								<?php foreach ( $activity as $log ) : ?>
									<div class="tpm-timeline-item">
										<div class="tpm-timeline-dot"></div>
										<div class="tpm-timeline-content">
											<div class="tpm-timeline-header">
												<strong><?php echo esc_html( $log->description ?: $log->action ); ?></strong>
											</div>
											<div class="tpm-timeline-meta">
												<?php
												$rec_label = ( 'po' === $log->record_type ) ? ( $log->customer_po_number ?: '#' . $log->record_id ) : ( $log->tender_number ?: '#' . $log->record_id );
												echo esc_html( sprintf( __( 'Record: %s', 'tender-po-management' ), $rec_label ) );
												?>
												&bull; <?php echo esc_html( human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'tender-po-management' ) ); ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

			</div>

		</div>

	</div>
	<?php
	return;
endif;

// =========================================================================
// SUB-VIEW 4: DELETE GUARD & REASSIGNMENT SCREEN
// =========================================================================
if ( 'delete' === $action && $customer_id > 0 ) :
	$customer = TPM_Customer_Service::get_customer( $customer_id );
	if ( ! $customer ) {
		wp_die( esc_html__( 'Customer not found or has been deleted.', 'tender-po-management' ) );
	}

	$del_check = TPM_Customer_Service::can_delete( $customer_id );
	$other_customers = TPM_Customer_Service::get_customers( array( 'limit' => 200, 'status' => 'active' ) );
	?>
	<div class="wrap tpm-admin-wrap">

		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1>
					<?php
					/* translators: %s: Customer Name */
					printf( esc_html__( 'Delete Customer: %s', 'tender-po-management' ), esc_html( $customer->name ) );
					?>
				</h1>
				<p><?php esc_html_e( 'Review dependencies before permanently removing this customer account.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=view&id=' . $customer_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Cancel & Go Back', 'tender-po-management' ); ?>
				</a>
			</div>
		</div>

		<?php if ( ! empty( $error_msg ) ) : ?>
			<div class="notice notice-error tpm-notice is-dismissible">
				<p><?php echo wp_kses_post( $error_msg ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $del_check['can_delete'] ) : ?>
			<!-- Case A: 0 Records Exist - Direct Safe Delete -->
			<div class="tpm-structured-container" style="max-width: 650px; margin-top: 20px;">
				<div class="tpm-container-toolbar">
					<div class="tpm-toolbar-title">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Confirm Permanent Deletion', 'tender-po-management' ); ?>
					</div>
				</div>
				<div class="tpm-container-content" style="padding: 24px;">
					<p>
						<?php
						/* translators: %s: Customer Name */
						printf( esc_html__( 'This customer (%s) has no associated Tenders or Purchase Orders. Are you sure you want to permanently delete them?', 'tender-po-management' ), '<strong>' . esc_html( $customer->name ) . '</strong>' );
						?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tpm_delete_customer">
						<input type="hidden" name="id" value="<?php echo esc_attr( $customer_id ); ?>">
						<?php wp_nonce_field( 'tpm_delete_customer_' . $customer_id, 'tpm_nonce' ); ?>
						<div style="display: flex; gap: 12px; margin-top: 20px;">
							<button type="submit" class="button button-primary button-large" style="background: #dc2626; border-color: #b91c1c;">
								<?php esc_html_e( 'Yes, Permanently Delete Customer', 'tender-po-management' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers' ) ); ?>" class="button button-secondary button-large">
								<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
							</a>
						</div>
					</form>
				</div>
			</div>

		<?php else : ?>
			<!-- Case B: Records Exist - Unsafe Deletion Guard & Reassignment -->
			<div class="notice notice-warning tpm-notice" style="margin-top: 15px;">
				<p>
					<strong>
						<span class="dashicons dashicons-warning" style="color: #d97706; vertical-align: middle;"></span>
						<?php esc_html_e( 'Unsafe Deletion Prevented:', 'tender-po-management' ); ?>
					</strong>
					<?php
					/* translators: 1: Total records count, 2: Tenders count, 3: POs count */
					printf( esc_html__( 'This customer has %1$d associated record(s) (%2$d Tender(s) and %3$d Purchase Order(s)). To protect data integrity and financial reporting, direct deletion is blocked.', 'tender-po-management' ), $del_check['record_count'], $del_check['tenders_count'], $del_check['pos_count'] );
					?>
				</p>
			</div>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-top: 20px;">

				<!-- Option 1: Deactivation (Recommended) -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-archive"></span>
							<?php esc_html_e( 'Option 1: Deactivate Customer (Recommended)', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<p><?php esc_html_e( 'Deactivating marks the customer as Inactive. They will no longer appear in Add/Edit Record dropdowns, but all historic tenders, POs, and financial data will remain 100% intact.', 'tender-po-management' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
							<input type="hidden" name="action" value="tpm_deactivate_customer">
							<input type="hidden" name="id" value="<?php echo esc_attr( $customer_id ); ?>">
							<?php wp_nonce_field( 'tpm_deactivate_customer_' . $customer_id, 'tpm_nonce' ); ?>
							<button type="submit" class="tpm-btn tpm-btn-primary">
								<span class="dashicons dashicons-hidden"></span>
								<?php esc_html_e( 'Deactivate Customer Account', 'tender-po-management' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- Option 2: Reassign & Delete -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-randomize"></span>
							<?php esc_html_e( 'Option 2: Reassign Records & Delete', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<p><?php esc_html_e( 'Transfer all associated Tenders and Purchase Orders to another customer organization before permanently deleting this account.', 'tender-po-management' ); ?></p>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 15px;">
							<input type="hidden" name="action" value="tpm_reassign_customer">
							<input type="hidden" name="source_id" value="<?php echo esc_attr( $customer_id ); ?>">
							<?php wp_nonce_field( 'tpm_reassign_customer_action', 'tpm_customer_nonce' ); ?>

							<div class="tpm-form-group">
								<label for="target_customer_id" class="tpm-label tpm-required"><?php esc_html_e( 'Select Target Customer to Receive Records:', 'tender-po-management' ); ?></label>
								<select name="target_id" id="target_customer_id" class="tpm-select" required>
									<option value=""><?php esc_html_e( '-- Choose Target Customer --', 'tender-po-management' ); ?></option>
									<?php foreach ( $other_customers as $other ) : ?>
										<?php if ( (int) $other->id !== $customer_id ) : ?>
											<option value="<?php echo esc_attr( $other->id ); ?>">
												<?php echo esc_html( ! empty( $other->company_name ) ? $other->company_name . ' (' . $other->name . ')' : $other->name ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>

							<button type="submit" class="tpm-btn tpm-btn-primary" style="background: #dc2626; border-color: #b91c1c;" data-tpm-confirm="<?php esc_attr_e( 'Transfer all records and permanently delete this customer?', 'tender-po-management' ); ?>">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Transfer Records & Delete Account', 'tender-po-management' ); ?>
							</button>
						</form>
					</div>
				</div>

			</div>
		<?php endif; ?>

	</div>
	<?php
	return;
endif;

// =========================================================================
// SUB-VIEW 5: DEFAULT LIST VIEW
// =========================================================================
$list_table = new TPM_Customers_List_Table();
$list_table->prepare_items();
?>
<div class="wrap tpm-admin-wrap">

	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'Customers', 'tender-po-management' ); ?>
				<span class="tpm-version-badge"><?php esc_html_e( 'Directory', 'tender-po-management' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Manage buyer accounts, procurement authorities, enterprise clients, and contact persons.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=new' ) ); ?>" class="tpm-btn tpm-btn-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add New Customer', 'tender-po-management' ); ?>
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

	<!-- Filter Views -->
	<?php $list_table->views(); ?>

	<!-- Customers Search & Table -->
	<form id="tpm-customers-form" method="get">
		<input type="hidden" name="page" value="tpm-customers" />
		<?php if ( ! empty( $_REQUEST['view'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<input type="hidden" name="view" value="<?php echo esc_attr( sanitize_key( $_REQUEST['view'] ) ); ?>" />
		<?php endif; ?>

		<?php $list_table->search_box( __( 'Search Customers', 'tender-po-management' ), 'tpm-customer-search' ); ?>

		<?php $list_table->display(); ?>
	</form>

</div>
