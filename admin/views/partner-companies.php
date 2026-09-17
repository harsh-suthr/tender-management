<?php
/**
 * View: Partner Companies Management
 *
 * Provides a complete Partner Company Directory, Add Partner, Edit Partner,
 * Partner Details (associated tenders, POs, financial metrics, profit sharing),
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
if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to manage partner companies.', 'tender-po-management' ) );
}

$user_id    = get_current_user_id();
$notice_msg = get_transient( 'tpm_partner_notice_' . $user_id );
$error_msg  = get_transient( 'tpm_partner_errors_' . $user_id );
$form_data  = get_transient( 'tpm_partner_form_data_' . $user_id ) ?: array();

if ( false !== $notice_msg ) {
	delete_transient( 'tpm_partner_notice_' . $user_id );
}
if ( false !== $error_msg ) {
	delete_transient( 'tpm_partner_errors_' . $user_id );
}
if ( ! empty( $form_data ) ) {
	delete_transient( 'tpm_partner_form_data_' . $user_id );
}

$action     = sanitize_key( $_GET['action'] ?? 'list' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$partner_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// =========================================================================
// SUB-VIEW 1: ADD NEW PARTNER COMPANY
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
					<?php esc_html_e( 'Add New Partner Company', 'tender-po-management' ); ?>
					<span class="tpm-version-badge"><?php esc_html_e( 'Partnerships', 'tender-po-management' ); ?></span>
				</h1>
				<p><?php esc_html_e( 'Register a new joint-venture partner, consortium member, subcontractor, or distributor alliance.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Partner Directory', 'tender-po-management' ); ?>
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
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Partner Company Details', 'tender-po-management' ); ?>
				</div>
			</div>

			<div class="tpm-container-content" style="padding: 24px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-form">
					<input type="hidden" name="action" value="tpm_save_partner">
					<?php wp_nonce_field( 'tpm_save_partner_action', 'tpm_partner_nonce' ); ?>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Company Name (Required) -->
						<div class="tpm-form-group">
							<label for="partner_company_name" class="tpm-label tpm-required"><?php esc_html_e( 'Company Name', 'tender-po-management' ); ?></label>
							<input type="text" name="company_name" id="partner_company_name" class="tpm-input" value="<?php echo $val( 'company_name' ); ?>" required placeholder="e.g. Apex Global Logistics Ltd">
							<span class="tpm-field-hint"><?php esc_html_e( 'Official legal or registered trade name.', 'tender-po-management' ); ?></span>
						</div>

						<!-- Contact Person -->
						<div class="tpm-form-group">
							<label for="partner_contact_person" class="tpm-label"><?php esc_html_e( 'Contact Person', 'tender-po-management' ); ?></label>
							<input type="text" name="contact_person" id="partner_contact_person" class="tpm-input" value="<?php echo $val( 'contact_person' ); ?>" placeholder="e.g. Sarah Jenkins">
							<span class="tpm-field-hint"><?php esc_html_e( 'Principal partnership liaison or account executive.', 'tender-po-management' ); ?></span>
						</div>
					</div>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Email -->
						<div class="tpm-form-group">
							<label for="partner_email" class="tpm-label"><?php esc_html_e( 'Email Address', 'tender-po-management' ); ?></label>
							<input type="email" name="email" id="partner_email" class="tpm-input" value="<?php echo $val( 'email' ); ?>" placeholder="e.g. contact@apexlogistics.com">
						</div>

						<!-- Phone -->
						<div class="tpm-form-group">
							<label for="partner_phone" class="tpm-label"><?php esc_html_e( 'Phone Number', 'tender-po-management' ); ?></label>
							<input type="text" name="phone" id="partner_phone" class="tpm-input" value="<?php echo $val( 'phone' ); ?>" placeholder="e.g. +1 (555) 789-0123">
						</div>
					</div>

					<div class="tpm-form-group">
						<label for="partner_address" class="tpm-label"><?php esc_html_e( 'Address', 'tender-po-management' ); ?></label>
						<textarea name="address" id="partner_address" class="tpm-textarea" rows="3" placeholder="<?php esc_attr_e( 'Street address, office suite, city, state, postal code, country...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $form_data['address'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group">
						<label for="partner_notes" class="tpm-label"><?php esc_html_e( 'Notes & Partnership Terms', 'tender-po-management' ); ?></label>
						<textarea name="notes" id="partner_notes" class="tpm-textarea" rows="3" placeholder="<?php esc_attr_e( 'Commission agreement details, standard revenue share percentages, banking coordinates, agreement terms...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $form_data['notes'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group" style="max-width: 250px;">
						<label for="partner_status" class="tpm-label"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></label>
						<select name="status" id="partner_status" class="tpm-select">
							<option value="active" <?php selected( $val( 'status', 'active' ), 'active' ); ?>><?php esc_html_e( 'Active', 'tender-po-management' ); ?></option>
							<option value="inactive" <?php selected( $val( 'status' ), 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'tender-po-management' ); ?></option>
						</select>
					</div>

					<div class="tpm-action-buttons" style="margin-top: 24px; display: flex; gap: 12px;">
						<button type="submit" class="tpm-btn tpm-btn-primary">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save Partner Company', 'tender-po-management' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies' ) ); ?>" class="tpm-btn tpm-btn-secondary">
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
// SUB-VIEW 2: EDIT PARTNER COMPANY
// =========================================================================
if ( 'edit' === $action && $partner_id > 0 ) :
	$partner = TPM_Partner_Service::get_partner( $partner_id );
	if ( ! $partner ) {
		wp_die( esc_html__( 'Partner company not found or has been deleted.', 'tender-po-management' ) );
	}

	$data_source = ! empty( $form_data ) ? $form_data : (array) $partner;
	$val = function( $key, $default = '' ) use ( $data_source ) {
		return isset( $data_source[ $key ] ) ? esc_attr( (string) $data_source[ $key ] ) : esc_attr( (string) $default );
	};
	?>
	<div class="wrap tpm-admin-wrap">

		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1>
					<?php
					/* translators: %s: Company Name */
					printf( esc_html__( 'Edit Partner Company: %s', 'tender-po-management' ), esc_html( $partner->company_name ) );
					?>
					<span class="tpm-version-badge tpm-badge-edit"><?php echo esc_html( sprintf( __( 'ID: #%d', 'tender-po-management' ), $partner_id ) ); ?></span>
				</h1>
				<p><?php esc_html_e( 'Update company profile, contact person, phone, email, notes, or active status.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . $partner_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Partner Details', 'tender-po-management' ); ?>
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
					<?php esc_html_e( 'Edit Partner Profile', 'tender-po-management' ); ?>
				</div>
			</div>

			<div class="tpm-container-content" style="padding: 24px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpm-form">
					<input type="hidden" name="action" value="tpm_update_partner">
					<input type="hidden" name="partner_id" value="<?php echo esc_attr( $partner_id ); ?>">
					<?php wp_nonce_field( 'tpm_update_partner_action', 'tpm_partner_nonce' ); ?>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Company Name (Required) -->
						<div class="tpm-form-group">
							<label for="partner_company_name" class="tpm-label tpm-required"><?php esc_html_e( 'Company Name', 'tender-po-management' ); ?></label>
							<input type="text" name="company_name" id="partner_company_name" class="tpm-input" value="<?php echo $val( 'company_name' ); ?>" required>
						</div>

						<!-- Contact Person -->
						<div class="tpm-form-group">
							<label for="partner_contact_person" class="tpm-label"><?php esc_html_e( 'Contact Person', 'tender-po-management' ); ?></label>
							<input type="text" name="contact_person" id="partner_contact_person" class="tpm-input" value="<?php echo $val( 'contact_person' ); ?>">
						</div>
					</div>

					<div class="tpm-form-row tpm-cols-2">
						<!-- Email -->
						<div class="tpm-form-group">
							<label for="partner_email" class="tpm-label"><?php esc_html_e( 'Email Address', 'tender-po-management' ); ?></label>
							<input type="email" name="email" id="partner_email" class="tpm-input" value="<?php echo $val( 'email' ); ?>">
						</div>

						<!-- Phone -->
						<div class="tpm-form-group">
							<label for="partner_phone" class="tpm-label"><?php esc_html_e( 'Phone Number', 'tender-po-management' ); ?></label>
							<input type="text" name="phone" id="partner_phone" class="tpm-input" value="<?php echo $val( 'phone' ); ?>">
						</div>
					</div>

					<div class="tpm-form-group">
						<label for="partner_address" class="tpm-label"><?php esc_html_e( 'Address', 'tender-po-management' ); ?></label>
						<textarea name="address" id="partner_address" class="tpm-textarea" rows="3"><?php echo esc_textarea( $data_source['address'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group">
						<label for="partner_notes" class="tpm-label"><?php esc_html_e( 'Notes & Partnership Terms', 'tender-po-management' ); ?></label>
						<textarea name="notes" id="partner_notes" class="tpm-textarea" rows="3"><?php echo esc_textarea( $data_source['notes'] ?? '' ); ?></textarea>
					</div>

					<div class="tpm-form-group" style="max-width: 250px;">
						<label for="partner_status" class="tpm-label"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></label>
						<select name="status" id="partner_status" class="tpm-select">
							<option value="active" <?php selected( $val( 'status', 'active' ), 'active' ); ?>><?php esc_html_e( 'Active', 'tender-po-management' ); ?></option>
							<option value="inactive" <?php selected( $val( 'status' ), 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'tender-po-management' ); ?></option>
						</select>
					</div>

					<div class="tpm-action-buttons" style="margin-top: 24px; display: flex; gap: 12px;">
						<button type="submit" class="tpm-btn tpm-btn-primary">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Update Partner Company', 'tender-po-management' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . $partner_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
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
// SUB-VIEW 3: PARTNER COMPANY DETAILS (VIEW)
// =========================================================================
if ( 'view' === $action && $partner_id > 0 ) :
	$partner = TPM_Partner_Service::get_partner( $partner_id );
	if ( ! $partner ) {
		wp_die( esc_html__( 'Partner company not found or has been deleted.', 'tender-po-management' ) );
	}

	$summary         = TPM_Partner_Service::get_partner_financial_summary( $partner_id );
	$tenders         = TPM_Partner_Service::get_partner_records( $partner_id, 'tender' );
	$pos             = TPM_Partner_Service::get_partner_records( $partner_id, 'po' );
	$del_info        = TPM_Partner_Service::can_delete( $partner_id );
	$is_act          = ( 'active' === $partner->status );
	$currency_symbol = TPM_Settings_Service::get_currency_symbol();
	?>
	<div class="wrap tpm-admin-wrap">

		<?php if ( ! empty( $notice_msg ) || isset( $_GET['created'] ) || isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success tpm-notice is-dismissible">
				<p><strong><?php echo ! empty( $notice_msg ) ? esc_html( $notice_msg ) : ( isset( $_GET['updated'] ) ? esc_html__( 'Partner company updated successfully!', 'tender-po-management' ) : esc_html__( 'Partner company created successfully!', 'tender-po-management' ) ); ?></strong></p>
			</div>
		<?php endif; ?>

		<!-- Header Banner -->
		<div class="tpm-header-banner tpm-detail-header-banner">
			<div class="tpm-header-left">
				<div class="tpm-detail-badges">
					<span class="tpm-badge <?php echo $is_act ? 'tpm-badge-active' : 'tpm-badge-draft'; ?>">
						<?php echo $is_act ? esc_html__( 'Active Partner', 'tender-po-management' ) : esc_html__( 'Inactive Partner', 'tender-po-management' ); ?>
					</span>
					<span class="tpm-ref-badge"><?php echo esc_html( sprintf( __( 'ID: #%d', 'tender-po-management' ), $partner_id ) ); ?></span>
				</div>

				<h1>
					<?php echo esc_html( $partner->company_name ); ?>
					<?php if ( ! empty( $partner->contact_person ) ) : ?>
						<span class="tpm-title-sub">&mdash; <?php echo esc_html( $partner->contact_person ); ?></span>
					<?php endif; ?>
				</h1>
				<p>
					<?php
					/* translators: %s: Created date */
					printf( esc_html__( 'Partner registered on %s', 'tender-po-management' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $partner->created_at ) ) ) );
					?>
				</p>
			</div>

			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Partner Directory', 'tender-po-management' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=edit&id=' . $partner_id ) ); ?>" class="tpm-btn tpm-btn-primary">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Edit Partner', 'tender-po-management' ); ?>
				</a>

				<?php if ( $del_info['can_delete'] ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpm_delete_partner&id=' . $partner_id ), 'tpm_delete_partner_' . $partner_id, 'tpm_nonce' ) ); ?>" class="tpm-btn tpm-btn-secondary" style="color: #dc2626;" data-tpm-confirm="<?php esc_attr_e( 'Are you sure you want to delete this partner company?', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Delete', 'tender-po-management' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=delete&id=' . $partner_id ) ); ?>" class="tpm-btn tpm-btn-secondary" title="<?php esc_attr_e( 'Manage dependencies before deleting', 'tender-po-management' ); ?>">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Delete (Protected)', 'tender-po-management' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Financial KPI Metrics Grid -->
		<div class="tpm-kpi-grid" style="margin-top: 20px;">
			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-clipboard"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Associated Records', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val"><?php echo esc_html( $summary['total_records'] ); ?> <span style="font-size: 13px; font-weight: normal; color: #64748b;">(<?php echo esc_html( $summary['total_tenders'] ); ?> Tenders, <?php echo esc_html( $summary['total_pos'] ); ?> POs)</span></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Total Contract Value', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val"><?php echo esc_html( $currency_symbol . number_format( $summary['total_revenue'], 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-chart-area"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Project Cumulative Profit', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $summary['total_net_profit'], 2 ) ); ?></div>
				</div>
			</div>

			<div class="tpm-kpi-card">
				<div class="tpm-kpi-icon"><span class="dashicons dashicons-groups"></span></div>
				<div class="tpm-kpi-content">
					<span class="tpm-kpi-label"><?php esc_html_e( 'Partner Profit Share', 'tender-po-management' ); ?></span>
					<div class="tpm-kpi-val tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $summary['partner_profit_share'], 2 ) ); ?></div>
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
								<p class="description"><?php esc_html_e( 'No tender submissions associated with this partner company yet.', 'tender-po-management' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Tender Number', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Sell Price', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Partner %', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Partner Share', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Due Date', 'tender-po-management' ); ?></th>
										<th style="width: 80px; text-align: right;"><?php esc_html_e( 'Action', 'tender-po-management' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $tenders as $tnd ) :
										$tnd_net   = (float) $tnd->sell_price - (float) $tnd->purchase_price - (float) $tnd->expenses;
										$tnd_pct   = (float) $tnd->partner_percentage;
										$tnd_share = ( $tnd_pct > 0 && $tnd_net > 0 ) ? ( $tnd_net * ( $tnd_pct / 100 ) ) : 0.00;
										?>
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
											<td><span class="tpm-tag"><?php echo esc_html( number_format( $tnd_pct, 2 ) ); ?>%</span></td>
											<td><strong class="tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $tnd_share, 2 ) ); ?></strong></td>
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
								<p class="description"><?php esc_html_e( 'No purchase orders registered for this partner company yet.', 'tender-po-management' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Customer PO #', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Sell Price', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Partner %', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'Partner Share', 'tender-po-management' ); ?></th>
										<th><?php esc_html_e( 'PO Date', 'tender-po-management' ); ?></th>
										<th style="width: 80px; text-align: right;"><?php esc_html_e( 'Action', 'tender-po-management' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $pos as $po_row ) :
										$po_net   = (float) $po_row->sell_price - (float) $po_row->purchase_price - (float) $po_row->expenses;
										$po_pct   = (float) $po_row->partner_percentage;
										$po_share = ( $po_pct > 0 && $po_net > 0 ) ? ( $po_net * ( $po_pct / 100 ) ) : 0.00;
										?>
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
											<td><span class="tpm-tag"><?php echo esc_html( number_format( $po_pct, 2 ) ); ?>%</span></td>
											<td><strong class="tpm-metric-positive"><?php echo esc_html( $currency_symbol . number_format( $po_share, 2 ) ); ?></strong></td>
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

			<!-- Sidebar Column: Contact Info & Terms -->
			<div class="tpm-detail-side">

				<!-- Contact Profile Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-id"></span>
							<?php esc_html_e( 'Company & Contact Profile', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-meta-list">
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Company Name:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val"><strong><?php echo esc_html( $partner->company_name ); ?></strong></span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Contact Person:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php echo ! empty( $partner->contact_person ) ? esc_html( $partner->contact_person ) : '&mdash;'; ?>
								</span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Email:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php if ( ! empty( $partner->email ) ) : ?>
										<a href="mailto:<?php echo esc_attr( $partner->email ); ?>"><?php echo esc_html( $partner->email ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Phone:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php if ( ! empty( $partner->phone ) ) : ?>
										<a href="tel:<?php echo esc_attr( $partner->phone ); ?>"><?php echo esc_html( $partner->phone ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Address:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<?php echo ! empty( $partner->address ) ? nl2br( esc_html( $partner->address ) ) : '&mdash;'; ?>
								</span>
							</div>

							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Status:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val">
									<span class="tpm-badge <?php echo $is_act ? 'tpm-badge-active' : 'tpm-badge-draft'; ?>">
										<?php echo $is_act ? esc_html__( 'Active', 'tender-po-management' ) : esc_html__( 'Inactive', 'tender-po-management' ); ?>
									</span>
								</span>
							</div>

							<?php if ( ! empty( $partner->notes ) ) : ?>
								<div class="tpm-meta-row" style="flex-direction: column; align-items: flex-start; gap: 4px;">
									<span class="tpm-meta-row-label"><?php esc_html_e( 'Partnership Notes & Terms:', 'tender-po-management' ); ?></span>
									<div class="tpm-notes-box-static"><?php echo nl2br( esc_html( $partner->notes ) ); ?></div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Financial Summary Card -->
				<div class="tpm-card" style="margin-top: 20px;">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-analytics"></span>
							<?php esc_html_e( 'Financial Summary', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-meta-list">
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Total Contract Value:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val"><strong><?php echo esc_html( $currency_symbol . number_format( $summary['total_revenue'], 2 ) ); ?></strong></span>
							</div>
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Cumulative Net Profit:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val tpm-metric-positive"><strong><?php echo esc_html( $currency_symbol . number_format( $summary['total_net_profit'], 2 ) ); ?></strong></span>
							</div>
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Partner Profit Share:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val tpm-metric-positive"><strong><?php echo esc_html( $currency_symbol . number_format( $summary['partner_profit_share'], 2 ) ); ?></strong></span>
							</div>
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Average Partner %:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val"><strong><?php echo esc_html( number_format( $summary['avg_percentage'], 2 ) ); ?>%</strong></span>
							</div>
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Total Payments Received:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val"><?php echo esc_html( $currency_symbol . number_format( $summary['total_paid'], 2 ) ); ?></span>
							</div>
							<div class="tpm-meta-row">
								<span class="tpm-meta-row-label"><?php esc_html_e( 'Outstanding Balance Due:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-row-val <?php echo ( $summary['total_balance_due'] > 0 ) ? 'tpm-metric-negative' : ''; ?>"><?php echo esc_html( $currency_symbol . number_format( $summary['total_balance_due'], 2 ) ); ?></span>
							</div>
						</div>
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
if ( 'delete' === $action && $partner_id > 0 ) :
	$partner = TPM_Partner_Service::get_partner( $partner_id );
	if ( ! $partner ) {
		wp_die( esc_html__( 'Partner company not found or has been deleted.', 'tender-po-management' ) );
	}

	$del_check     = TPM_Partner_Service::can_delete( $partner_id );
	$other_partners = TPM_Partner_Service::get_partners( array( 'limit' => 200, 'status' => 'active' ) );
	?>
	<div class="wrap tpm-admin-wrap">

		<div class="tpm-header-banner">
			<div class="tpm-header-left">
				<h1>
					<?php
					/* translators: %s: Company Name */
					printf( esc_html__( 'Delete Partner Company: %s', 'tender-po-management' ), esc_html( $partner->company_name ) );
					?>
				</h1>
				<p><?php esc_html_e( 'Review dependencies before permanently removing this partner company profile.', 'tender-po-management' ); ?></p>
			</div>
			<div class="tpm-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . $partner_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
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
						/* translators: %s: Company Name */
						printf( esc_html__( 'This partner company (%s) has no associated Tenders or Purchase Orders. Are you sure you want to permanently delete them?', 'tender-po-management' ), '<strong>' . esc_html( $partner->company_name ) . '</strong>' );
						?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tpm_delete_partner">
						<input type="hidden" name="id" value="<?php echo esc_attr( $partner_id ); ?>">
						<?php wp_nonce_field( 'tpm_delete_partner_' . $partner_id, 'tpm_nonce' ); ?>
						<div style="display: flex; gap: 12px; margin-top: 20px;">
							<button type="submit" class="button button-primary button-large" style="background: #dc2626; border-color: #b91c1c;">
								<?php esc_html_e( 'Yes, Permanently Delete Partner', 'tender-po-management' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies' ) ); ?>" class="button button-secondary button-large">
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
					printf( esc_html__( 'This partner company has %1$d associated record(s) (%2$d Tender(s) and %3$d Purchase Order(s)). To protect data integrity and profit-sharing calculations, direct deletion is blocked.', 'tender-po-management' ), $del_check['record_count'], $del_check['tenders_count'], $del_check['pos_count'] );
					?>
				</p>
			</div>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-top: 20px;">

				<!-- Option 1: Deactivation (Recommended) -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-archive"></span>
							<?php esc_html_e( 'Option 1: Deactivate Partner (Recommended)', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<p><?php esc_html_e( 'Deactivating marks the partner company as Inactive. They will no longer appear in Add/Edit Record partner dropdowns, but all historic tenders, POs, and profit share percentages will remain 100% intact.', 'tender-po-management' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
							<input type="hidden" name="action" value="tpm_deactivate_partner">
							<input type="hidden" name="id" value="<?php echo esc_attr( $partner_id ); ?>">
							<?php wp_nonce_field( 'tpm_deactivate_partner_' . $partner_id, 'tpm_nonce' ); ?>
							<button type="submit" class="tpm-btn tpm-btn-primary">
								<span class="dashicons dashicons-hidden"></span>
								<?php esc_html_e( 'Deactivate Partner Company', 'tender-po-management' ); ?>
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
						<p><?php esc_html_e( 'Transfer all associated Tenders and Purchase Orders to another partner company before permanently deleting this account.', 'tender-po-management' ); ?></p>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 15px;">
							<input type="hidden" name="action" value="tpm_reassign_partner">
							<input type="hidden" name="source_id" value="<?php echo esc_attr( $partner_id ); ?>">
							<?php wp_nonce_field( 'tpm_reassign_partner_action', 'tpm_partner_nonce' ); ?>

							<div class="tpm-form-group">
								<label for="target_partner_id" class="tpm-label tpm-required"><?php esc_html_e( 'Select Target Partner to Receive Records:', 'tender-po-management' ); ?></label>
								<select name="target_id" id="target_partner_id" class="tpm-select" required>
									<option value=""><?php esc_html_e( '-- Choose Target Partner --', 'tender-po-management' ); ?></option>
									<?php foreach ( $other_partners as $other ) : ?>
										<?php if ( (int) $other->id !== $partner_id ) : ?>
											<option value="<?php echo esc_attr( $other->id ); ?>">
												<?php echo esc_html( ! empty( $other->contact_person ) ? $other->company_name . ' (' . $other->contact_person . ')' : $other->company_name ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>

							<button type="submit" class="tpm-btn tpm-btn-primary" style="background: #dc2626; border-color: #b91c1c;" data-tpm-confirm="<?php esc_attr_e( 'Transfer all records and permanently delete this partner company?', 'tender-po-management' ); ?>">
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
$list_table = new TPM_Partners_List_Table();
$list_table->prepare_items();
?>
<div class="wrap tpm-admin-wrap">

	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'Partner Companies', 'tender-po-management' ); ?>
				<span class="tpm-version-badge"><?php esc_html_e( 'Directory', 'tender-po-management' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Manage joint-venture partners, logistics alliances, and contract percentage commissions.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=new' ) ); ?>" class="tpm-btn tpm-btn-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Partner Company', 'tender-po-management' ); ?>
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

	<!-- Partner Search & Table -->
	<form id="tpm-partners-form" method="get">
		<input type="hidden" name="page" value="tpm-partner-companies" />
		<?php if ( ! empty( $_REQUEST['view'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<input type="hidden" name="view" value="<?php echo esc_attr( sanitize_key( $_REQUEST['view'] ) ); ?>" />
		<?php endif; ?>

		<?php $list_table->search_box( __( 'Search Partners', 'tender-po-management' ), 'tpm-partner-search' ); ?>

		<?php $list_table->display(); ?>
	</form>

</div>
