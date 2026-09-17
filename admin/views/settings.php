<?php
/**
 * View: Settings & Status Management
 *
 * Provides comprehensive configuration for General system preferences,
 * Status lifecycle management, Attachment limits, Archive policies, and Uninstall.
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
if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access settings.', 'tender-po-management' ) );
}

$user_id = get_current_user_id();
$notice  = get_transient( 'tpm_settings_notice_' . $user_id );
if ( $notice ) {
	delete_transient( 'tpm_settings_notice_' . $user_id );
}
$error = get_transient( 'tpm_settings_error_' . $user_id );
if ( $error ) {
	delete_transient( 'tpm_settings_error_' . $user_id );
}

$settings    = TPM_Settings_Service::get_settings();
$statuses    = TPM_Settings_Service::get_statuses();
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
$valid_tabs  = array( 'general', 'statuses', 'attachments', 'archive', 'uninstall' );
if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
	$current_tab = 'general';
}

// Optional edit status mode
$edit_status_id = ! empty( $_GET['edit_status'] ) ? absint( $_GET['edit_status'] ) : 0;
$edit_status    = $edit_status_id ? TPM_Settings_Service::get_status( $edit_status_id ) : null;
?>
<div class="wrap tpm-admin-wrap">

	<!-- Header Banner -->
	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php esc_html_e( 'System Settings', 'tender-po-management' ); ?>
				<span class="tpm-version-badge"><?php esc_html_e( 'Configuration', 'tender-po-management' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Configure system preferences, status workflows, attachment constraints, and archive behavior.', 'tender-po-management' ); ?></p>
		</div>
		<div class="tpm-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tender-po-management' ) ); ?>" class="tpm-btn tpm-btn-secondary">
				<span class="dashicons dashicons-arrow-left-alt"></span>
				<?php esc_html_e( 'Back to Dashboard', 'tender-po-management' ); ?>
			</a>
		</div>
	</div>

	<!-- Flash Feedback Notices -->
	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin: 15px 0;">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $error ) ) : ?>
		<div class="notice notice-error is-dismissible" style="margin: 15px 0;">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Settings Navigation Tabs -->
	<nav class="nav-tab-wrapper tpm-settings-tabs" style="margin-bottom: 25px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-settings&tab=general' ) ); ?>" class="nav-tab <?php echo 'general' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-admin-generic" style="margin-right: 5px; vertical-align: text-top;"></span>
			<?php esc_html_e( 'General Settings', 'tender-po-management' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) ); ?>" class="nav-tab <?php echo 'statuses' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-tag" style="margin-right: 5px; vertical-align: text-top;"></span>
			<?php esc_html_e( 'Status Management', 'tender-po-management' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-settings&tab=attachments' ) ); ?>" class="nav-tab <?php echo 'attachments' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-paperclip" style="margin-right: 5px; vertical-align: text-top;"></span>
			<?php esc_html_e( 'Attachment Settings', 'tender-po-management' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-settings&tab=archive' ) ); ?>" class="nav-tab <?php echo 'archive' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-archive" style="margin-right: 5px; vertical-align: text-top;"></span>
			<?php esc_html_e( 'Archive Settings', 'tender-po-management' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-settings&tab=uninstall' ) ); ?>" class="nav-tab <?php echo 'uninstall' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-trash" style="margin-right: 5px; vertical-align: text-top;"></span>
			<?php esc_html_e( 'Uninstall', 'tender-po-management' ); ?>
		</a>
	</nav>

	<!-- ===================================================================== -->
	<!-- TAB 1: GENERAL SETTINGS                                               -->
	<!-- ===================================================================== -->
	<?php if ( 'general' === $current_tab ) : ?>
		<div class="tpm-card" style="padding: 25px; max-width: 800px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tpm_save_settings" />
				<input type="hidden" name="current_tab" value="general" />
				<?php wp_nonce_field( 'tpm_save_settings', 'tpm_settings_nonce' ); ?>

				<h3 style="margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee;">
					<?php esc_html_e( 'General Localization & Pagination', 'tender-po-management' ); ?>
				</h3>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Currency -->
						<tr>
							<th scope="row"><label for="currency"><?php esc_html_e( 'Currency Code', 'tender-po-management' ); ?></label></th>
							<td>
								<input type="text" id="currency" name="currency" value="<?php echo esc_attr( $settings['currency'] ); ?>" class="regular-text" style="max-width: 160px;" required />
								<p class="description"><?php esc_html_e( 'Currency code (default: INR; e.g. INR, USD, EUR, GBP, AED).', 'tender-po-management' ); ?></p>
							</td>
						</tr>

						<!-- Currency Symbol -->
						<tr>
							<th scope="row"><label for="currency_symbol"><?php esc_html_e( 'Currency Symbol', 'tender-po-management' ); ?></label></th>
							<td>
								<input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" class="regular-text" style="max-width: 100px;" required />
								<p class="description"><?php esc_html_e( 'Display symbol (default: ₹; e.g. ₹, $, €, £, AED).', 'tender-po-management' ); ?></p>
							</td>
						</tr>

						<!-- Date Format -->
						<tr>
							<th scope="row"><label for="date_format"><?php esc_html_e( 'Date Format', 'tender-po-management' ); ?></label></th>
							<td>
								<select id="date_format" name="date_format" class="regular-text">
									<option value="Y-m-d" <?php selected( $settings['date_format'], 'Y-m-d' ); ?>>YYYY-MM-DD (e.g. <?php echo esc_html( gmdate( 'Y-m-d' ) ); ?>)</option>
									<option value="m/d/Y" <?php selected( $settings['date_format'], 'm/d/Y' ); ?>>MM/DD/YYYY (e.g. <?php echo esc_html( gmdate( 'm/d/Y' ) ); ?>)</option>
									<option value="d/m/Y" <?php selected( $settings['date_format'], 'd/m/Y' ); ?>>DD/MM/YYYY (e.g. <?php echo esc_html( gmdate( 'd/m/Y' ) ); ?>)</option>
									<option value="F j, Y" <?php selected( $settings['date_format'], 'F j, Y' ); ?>>Month D, YYYY (e.g. <?php echo esc_html( gmdate( 'F j, Y' ) ); ?>)</option>
								</select>
								<p class="description"><?php esc_html_e( 'Standard format used for dates across tables and reports.', 'tender-po-management' ); ?></p>
							</td>
						</tr>

						<!-- Records Per Page -->
						<tr>
							<th scope="row"><label for="records_per_page"><?php esc_html_e( 'Records Per Page', 'tender-po-management' ); ?></label></th>
							<td>
								<input type="number" id="records_per_page" name="records_per_page" value="<?php echo esc_attr( $settings['records_per_page'] ); ?>" min="5" max="100" class="small-text" required />
								<p class="description"><?php esc_html_e( 'Number of items displayed per page in admin list tables (5 - 100).', 'tender-po-management' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save General Settings', 'tender-po-management' ); ?>
					</button>
				</p>
			</form>
		</div>

	<!-- ===================================================================== -->
	<!-- TAB 2: STATUS MANAGEMENT                                              -->
	<!-- ===================================================================== -->
	<?php elseif ( 'statuses' === $current_tab ) : ?>
		<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: flex-start;">
			
			<!-- Left Column: Statuses List Table -->
			<div class="tpm-card" style="padding: 0; overflow: hidden;">
				<div class="tpm-container-toolbar" style="padding: 16px 20px; background: #fafafa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
					<div class="tpm-toolbar-title" style="font-size: 15px; font-weight: 600;">
						<span class="dashicons dashicons-tag" style="margin-right: 6px;"></span>
						<?php esc_html_e( 'Configured Operational Statuses', 'tender-po-management' ); ?>
					</div>
					<span class="tpm-tag"><?php echo esc_html( count( $statuses ) . ' ' . __( 'Statuses', 'tender-po-management' ) ); ?></span>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tpm_reorder_statuses" />
					<?php wp_nonce_field( 'tpm_reorder_statuses', 'tpm_nonce' ); ?>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 50px; text-align: center;"><?php esc_html_e( 'Order', 'tender-po-management' ); ?></th>
								<th style="width: 140px;"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'tender-po-management' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Applies To', 'tender-po-management' ); ?></th>
								<th style="width: 80px; text-align: center;"><?php esc_html_e( 'In Use', 'tender-po-management' ); ?></th>
								<th style="width: 80px; text-align: center;"><?php esc_html_e( 'State', 'tender-po-management' ); ?></th>
								<th style="width: 120px; text-align: right;"><?php esc_html_e( 'Actions', 'tender-po-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $statuses as $st ) : ?>
								<?php
								$in_use_count = TPM_Settings_Service::get_records_count_with_status( $st['status_slug'] );
								$toggle_url   = wp_nonce_url(
									admin_url( 'admin-post.php?action=tpm_toggle_status&status_id=' . $st['id'] ),
									'tpm_toggle_status_' . $st['id'],
									'tpm_nonce'
								);
								$delete_url   = wp_nonce_url(
									admin_url( 'admin-post.php?action=tpm_delete_status&status_id=' . $st['id'] ),
									'tpm_delete_status_' . $st['id'],
									'tpm_nonce'
								);
								$edit_url     = admin_url( 'admin.php?page=tpm-settings&tab=statuses&edit_status=' . $st['id'] );
								?>
								<tr>
									<td style="text-align: center;">
										<input type="number" name="status_orders[<?php echo esc_attr( $st['id'] ); ?>]" value="<?php echo esc_attr( $st['sort_order'] ); ?>" style="width: 50px; text-align: center; height: 28px;" />
									</td>
									<td>
										<span class="tpm-status-swatch-badge" style="background-color: <?php echo esc_attr( $st['status_color'] ); ?>; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 11px; display: inline-block;">
											<?php echo esc_html( $st['status_name'] ); ?>
										</span>
									</td>
									<td><code><?php echo esc_html( $st['status_slug'] ); ?></code></td>
									<td>
										<span class="tpm-badge <?php echo 'tender' === $st['record_type'] ? 'tpm-badge-primary' : ( 'po' === $st['record_type'] ? 'tpm-badge-secondary' : 'tpm-badge-success' ); ?>">
											<?php echo esc_html( ucfirst( $st['record_type'] ) ); ?>
										</span>
									</td>
									<td style="text-align: center;">
										<?php if ( $in_use_count > 0 ) : ?>
											<span class="tpm-tag" title="<?php echo esc_attr( sprintf( __( '%d records currently use this status', 'tender-po-management' ), $in_use_count ) ); ?>" style="font-weight: 700; color: #b45309; background: #fef3c7;">
												<?php echo esc_html( $in_use_count ); ?>
											</span>
										<?php else : ?>
											<span style="color: #94a3b8;">0</span>
										<?php endif; ?>
									</td>
									<td style="text-align: center;">
										<?php if ( ! empty( $st['is_active'] ) ) : ?>
											<span style="color: #047857; font-weight: 600;"><?php esc_html_e( 'Active', 'tender-po-management' ); ?></span>
										<?php else : ?>
											<span style="color: #64748b; font-style: italic;"><?php esc_html_e( 'Disabled', 'tender-po-management' ); ?></span>
										<?php endif; ?>
									</td>
									<td style="text-align: right;">
										<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'Edit Status', 'tender-po-management' ); ?>">
											<span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px; margin-top: 3px;"></span>
										</a>
										<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small" title="<?php echo ! empty( $st['is_active'] ) ? esc_attr__( 'Disable', 'tender-po-management' ) : esc_attr__( 'Enable', 'tender-po-management' ); ?>">
											<span class="dashicons <?php echo ! empty( $st['is_active'] ) ? 'dashicons-hidden' : 'dashicons-visibility'; ?>" style="font-size: 14px; width: 14px; height: 14px; margin-top: 3px;"></span>
										</a>
										<?php if ( $in_use_count > 0 ) : ?>
											<button type="button" class="button button-small button-link-delete" onclick="alert('<?php echo esc_js( sprintf( __( 'Cannot delete status \'%s\' because it is currently assigned to %d record(s). Please reassign those records first.', 'tender-po-management' ), $st['status_name'], $in_use_count ) ); ?>');" style="color: #b32d2e;">
												<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; margin-top: 3px;"></span>
											</button>
										<?php else : ?>
											<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to delete status \'%s\'?', 'tender-po-management' ), $st['status_name'] ) ); ?>');" style="color: #b32d2e;">
												<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; margin-top: 3px;"></span>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div style="padding: 12px 20px; background: #fafafa; border-top: 1px solid #eee; display: flex; justify-content: flex-end;">
						<button type="submit" class="button">
							<span class="dashicons dashicons-sort" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Update Sort Order', 'tender-po-management' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Right Column: Add or Edit Status Form -->
			<div class="tpm-card" style="padding: 20px;">
				<?php if ( $edit_status ) : ?>
					<h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
						<?php esc_html_e( 'Edit Status', 'tender-po-management' ); ?>
					</h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tpm_edit_status" />
						<input type="hidden" name="status_id" value="<?php echo esc_attr( $edit_status['id'] ); ?>" />
						<?php wp_nonce_field( 'tpm_edit_status', 'tpm_status_nonce' ); ?>

						<div style="margin-bottom: 15px;">
							<label for="status_name" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Status Name', 'tender-po-management' ); ?> *</label>
							<input type="text" id="status_name" name="status_name" value="<?php echo esc_attr( $edit_status['status_name'] ); ?>" class="widefat" required />
						</div>

						<div style="margin-bottom: 15px;">
							<label for="status_slug" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Status Slug', 'tender-po-management' ); ?> *</label>
							<input type="text" id="status_slug" name="status_slug" value="<?php echo esc_attr( $edit_status['status_slug'] ); ?>" class="widefat" required />
						</div>

						<div style="margin-bottom: 15px;">
							<label for="record_type" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Assign Status To', 'tender-po-management' ); ?></label>
							<select id="record_type" name="record_type" class="widefat">
								<option value="both" <?php selected( $edit_status['record_type'], 'both' ); ?>><?php esc_html_e( 'Both (Tender & Purchase Order)', 'tender-po-management' ); ?></option>
								<option value="tender" <?php selected( $edit_status['record_type'], 'tender' ); ?>><?php esc_html_e( 'Tender Only', 'tender-po-management' ); ?></option>
								<option value="po" <?php selected( $edit_status['record_type'], 'po' ); ?>><?php esc_html_e( 'Purchase Order Only', 'tender-po-management' ); ?></option>
							</select>
						</div>

						<div style="margin-bottom: 15px;">
							<label for="status_color" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Status Color', 'tender-po-management' ); ?></label>
							<div style="display: flex; gap: 10px; align-items: center;">
								<input type="color" id="status_color_picker" value="<?php echo esc_attr( $edit_status['status_color'] ); ?>" style="width: 40px; height: 34px; padding: 2px; border: 1px solid #ccc; border-radius: 4px;" onchange="document.getElementById('status_color').value = this.value;" />
								<input type="text" id="status_color" name="status_color" value="<?php echo esc_attr( $edit_status['status_color'] ); ?>" class="regular-text" style="max-width: 120px;" onchange="document.getElementById('status_color_picker').value = this.value;" />
							</div>
						</div>

						<div style="margin-bottom: 15px;">
							<label for="sort_order" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Sort Order', 'tender-po-management' ); ?></label>
							<input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr( $edit_status['sort_order'] ); ?>" class="small-text" />
						</div>

						<div style="display: flex; gap: 10px; align-items: center; margin-top: 20px;">
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Update Status', 'tender-po-management' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) ); ?>" class="button">
								<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
							</a>
						</div>
					</form>
				<?php else : ?>
					<h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
						<?php esc_html_e( 'Add New Status', 'tender-po-management' ); ?>
					</h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tpm_add_status" />
						<?php wp_nonce_field( 'tpm_add_status', 'tpm_status_nonce' ); ?>

						<div style="margin-bottom: 15px;">
							<label for="new_status_name" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Status Name', 'tender-po-management' ); ?> *</label>
							<input type="text" id="new_status_name" name="status_name" placeholder="e.g. Under Evaluation" class="widefat" required />
						</div>

						<div style="margin-bottom: 15px;">
							<label for="new_status_slug" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Status Slug', 'tender-po-management' ); ?></label>
							<input type="text" id="new_status_slug" name="status_slug" placeholder="auto-generated if empty" class="widefat" />
							<p class="description" style="font-size: 11px;"><?php esc_html_e( 'Unique lowercase identifier (e.g. under-evaluation).', 'tender-po-management' ); ?></p>
						</div>

						<div style="margin-bottom: 15px;">
							<label for="new_record_type" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Assign Status To', 'tender-po-management' ); ?></label>
							<select id="new_record_type" name="record_type" class="widefat">
								<option value="both"><?php esc_html_e( 'Both (Tender & Purchase Order)', 'tender-po-management' ); ?></option>
								<option value="tender"><?php esc_html_e( 'Tender Only', 'tender-po-management' ); ?></option>
								<option value="po"><?php esc_html_e( 'Purchase Order Only', 'tender-po-management' ); ?></option>
							</select>
						</div>

						<div style="margin-bottom: 15px;">
							<label for="new_status_color" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Status Color', 'tender-po-management' ); ?></label>
							<div style="display: flex; gap: 10px; align-items: center;">
								<input type="color" id="new_status_color_picker" value="#3b82f6" style="width: 40px; height: 34px; padding: 2px; border: 1px solid #ccc; border-radius: 4px;" onchange="document.getElementById('new_status_color').value = this.value;" />
								<input type="text" id="new_status_color" name="status_color" value="#3b82f6" class="regular-text" style="max-width: 120px;" onchange="document.getElementById('new_status_color_picker').value = this.value;" />
							</div>
						</div>

						<div style="margin-bottom: 15px;">
							<label for="new_sort_order" style="font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Sort Order', 'tender-po-management' ); ?></label>
							<input type="number" id="new_sort_order" name="sort_order" value="" placeholder="auto" class="small-text" />
						</div>

						<p class="submit" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
								<?php esc_html_e( 'Add Status', 'tender-po-management' ); ?>
							</button>
						</p>
					</form>
				<?php endif; ?>
			</div>

		</div>

	<!-- ===================================================================== -->
	<!-- TAB 3: ATTACHMENT SETTINGS                                            -->
	<!-- ===================================================================== -->
	<?php elseif ( 'attachments' === $current_tab ) : ?>
		<div class="tpm-card" style="padding: 25px; max-width: 800px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tpm_save_settings" />
				<input type="hidden" name="current_tab" value="attachments" />
				<?php wp_nonce_field( 'tpm_save_settings', 'tpm_settings_nonce' ); ?>

				<h3 style="margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee;">
					<?php esc_html_e( 'Attachment Upload Constraints & Labels', 'tender-po-management' ); ?>
				</h3>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Allowed File Types -->
						<tr>
							<th scope="row"><label for="allowed_file_types"><?php esc_html_e( 'Allowed File Types', 'tender-po-management' ); ?></label></th>
							<td>
								<input type="text" id="allowed_file_types" name="allowed_file_types" value="<?php echo esc_attr( $settings['allowed_file_types'] ); ?>" class="large-text" required />
								<p class="description"><?php esc_html_e( 'Comma-separated file extensions allowed for tender/PO uploads (e.g. pdf, doc, docx, xls, xlsx, jpg, png, zip).', 'tender-po-management' ); ?></p>
							</td>
						</tr>

						<!-- Maximum File Size (MB) -->
						<tr>
							<th scope="row"><label for="max_file_size_mb"><?php esc_html_e( 'Max File Size (MB)', 'tender-po-management' ); ?></label></th>
							<td>
								<input type="number" id="max_file_size_mb" name="max_file_size_mb" value="<?php echo esc_attr( $settings['max_file_size_mb'] ); ?>" min="1" max="256" class="small-text" required />
								<p class="description"><?php esc_html_e( 'Maximum allowable size per individual upload in megabytes (1 - 256 MB).', 'tender-po-management' ); ?></p>
							</td>
						</tr>

						<!-- Predefined Attachment Labels -->
						<tr>
							<th scope="row"><label for="attachment_labels"><?php esc_html_e( 'Allowed Attachment Labels', 'tender-po-management' ); ?></label></th>
							<td>
								<textarea id="attachment_labels" name="attachment_labels" rows="5" class="large-text"><?php echo esc_textarea( str_replace( ', ', "\n", $settings['attachment_labels'] ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Enter allowed classification labels, one per line or separated by commas (e.g. Tender Document, Quotation, Purchase Order, Technical Specs, Bill of Quantities, Commercial Offer, AWB / Shipping Doc, Invoice, Receipt, Warranty / Certificate).', 'tender-po-management' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Attachment Settings', 'tender-po-management' ); ?>
					</button>
				</p>
			</form>
		</div>

	<!-- ===================================================================== -->
	<!-- TAB 4: ARCHIVE SETTINGS                                               -->
	<!-- ===================================================================== -->
	<?php elseif ( 'archive' === $current_tab ) : ?>
		<div class="tpm-card" style="padding: 25px; max-width: 800px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tpm_save_settings" />
				<input type="hidden" name="current_tab" value="archive" />
				<?php wp_nonce_field( 'tpm_save_settings', 'tpm_settings_nonce' ); ?>

				<h3 style="margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee;">
					<?php esc_html_e( 'Archive Workflow & Permanent Deletion Policy', 'tender-po-management' ); ?>
				</h3>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Hide from All Records -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Archive Visibility', 'tender-po-management' ); ?></th>
							<td>
								<label for="hide_archived_default">
									<input type="checkbox" id="hide_archived_default" name="hide_archived_default" value="1" <?php checked( $settings['hide_archived_default'], 1 ); ?> />
									<?php esc_html_e( 'Hide archived records by default from active listings and dashboards', 'tender-po-management' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When checked, archived records are quarantined exclusively inside the Archive view.', 'tender-po-management' ); ?></p>
							</td>
						</tr>

						<!-- Permanent Delete Permissions -->
						<tr>
							<th scope="row"><label for="permanent_delete_cap"><?php esc_html_e( 'Permanent Delete Permission', 'tender-po-management' ); ?></label></th>
							<td>
								<select id="permanent_delete_cap" name="permanent_delete_cap" class="regular-text">
									<option value="manage_options" <?php selected( $settings['permanent_delete_cap'], 'manage_options' ); ?>>
										<?php esc_html_e( 'Administrators Only (manage_options)', 'tender-po-management' ); ?>
									</option>
									<option value="manage_tpm_records" <?php selected( $settings['permanent_delete_cap'], 'manage_tpm_records' ); ?>>
										<?php esc_html_e( 'Procurement Managers & Admins (manage_tpm_records)', 'tender-po-management' ); ?>
									</option>
								</select>
								<p class="description"><?php esc_html_e( 'Minimum capability required to permanently purge records from the archive.', 'tender-po-management' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Archive Settings', 'tender-po-management' ); ?>
					</button>
				</p>
			</form>
		</div>

	<!-- ===================================================================== -->
	<!-- TAB 5: UNINSTALL SETTINGS                                             -->
	<!-- ===================================================================== -->
	<?php elseif ( 'uninstall' === $current_tab ) : ?>
		<div class="tpm-card" style="padding: 25px; max-width: 800px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tpm_save_settings" />
				<input type="hidden" name="current_tab" value="uninstall" />
				<?php wp_nonce_field( 'tpm_save_settings', 'tpm_settings_nonce' ); ?>

				<h3 style="margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee; color: #b32d2e;">
					<span class="dashicons dashicons-warning" style="margin-right: 4px;"></span>
					<?php esc_html_e( 'Plugin Uninstallation Data Cleanup', 'tender-po-management' ); ?>
				</h3>

				<div style="background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 4px; margin-bottom: 20px;">
					<p style="margin: 0; font-size: 13px; color: #991b1b; line-height: 1.5;">
						<strong><?php esc_html_e( 'Important Notice:', 'tender-po-management' ); ?></strong>
						<?php esc_html_e( 'By default, your business procurement data (Tenders, Purchase Orders, Customers, Partner Companies, Payments, and Activity Logs) is safely PRESERVED when this plugin is deleted from WordPress plugins page.', 'tender-po-management' ); ?>
					</p>
				</div>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Purge Data on Uninstall', 'tender-po-management' ); ?></th>
							<td>
								<label for="delete_data_on_uninstall" style="font-weight: 600; color: #b32d2e;">
									<input type="checkbox" id="delete_data_on_uninstall" name="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'], 1 ); ?> />
									<?php esc_html_e( 'Delete all plugin data on uninstall', 'tender-po-management' ); ?>
								</label>
								<p class="description" style="color: #64748b; margin-top: 6px;">
									<?php esc_html_e( 'Default: Disabled. If enabled, deleting the plugin will permanently DROP all custom database tables and delete all system settings options.', 'tender-po-management' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Uninstall Preference', 'tender-po-management' ); ?>
					</button>
				</p>
			</form>
		</div>
	<?php endif; ?>

</div>
