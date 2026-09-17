<?php
/**
 * View: Add New Record
 *
 * Provides a responsive, organized form for creating Tenders and Purchase Orders.
 * Features 8 structured sections, real-time calculations, and WP Media integration.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/admin/views
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check mode: Add or Edit
$is_edit   = ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! empty( $_GET['id'] ) );
$record_id = $is_edit ? absint( $_GET['id'] ) : 0;
$record    = null;

// Capability check.
if ( $is_edit ) {
	if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to edit records.', 'tender-po-management' ) );
	}
	$record = TPM_Record_Service::get_record( $record_id );
	if ( ! $record ) {
		wp_die( esc_html__( 'Record not found or has been deleted.', 'tender-po-management' ) );
	}
} else {
	if ( ! current_user_can( 'create_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to create records.', 'tender-po-management' ) );
	}
}

global $wpdb;

// Retrieve any transient validation errors or preserved form data.
$user_id       = get_current_user_id();
$error_notice  = get_transient( 'tpm_record_errors_' . $user_id );
$form_data     = get_transient( 'tpm_record_form_data_' . $user_id ) ?: array();

// Clear transients once read.
if ( false !== $error_notice ) {
	delete_transient( 'tpm_record_errors_' . $user_id );
}
if ( ! empty( $form_data ) ) {
	delete_transient( 'tpm_record_form_data_' . $user_id );
}

// In edit mode without pending transient errors, prefill from existing record.
if ( $is_edit && empty( $form_data ) && $record ) {
	$form_data = (array) $record;
}

// Fetch existing attachments in edit mode
$existing_attachments = $is_edit ? TPM_Record_Service::get_attachments( $record_id ) : array();
$attachment_labels    = TPM_Record_Service::get_attachment_labels();
$currency_symbol      = TPM_Settings_Service::get_currency_symbol();

// Helper function to safely output preserved or default form values.
$val = function( $field_key, $default = '' ) use ( $form_data ) {
	return isset( $form_data[ $field_key ] ) ? esc_attr( (string) $form_data[ $field_key ] ) : esc_attr( (string) $default );
};

// Fetch active statuses from tpm_statuses.
$statuses_table = $wpdb->prefix . 'tpm_statuses';
$statuses       = $wpdb->get_results( "SELECT status_name, status_slug, record_type, status_color FROM `{$statuses_table}` WHERE is_active = 1 ORDER BY sort_order ASC" );
if ( ! is_array( $statuses ) ) {
	$statuses = array();
}

// Fetch active customers from tpm_customers (including assigned customer if in edit mode).
$customers_table  = $wpdb->prefix . 'tpm_customers';
$assigned_cust_id = ! empty( $form_data['customer_id'] ) ? absint( $form_data['customer_id'] ) : 0;
if ( $assigned_cust_id > 0 ) {
	$customers = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, name, company_name, status FROM `{$customers_table}` WHERE status = 'active' OR id = %d ORDER BY company_name ASC, name ASC",
			$assigned_cust_id
		)
	);
} else {
	$customers = $wpdb->get_results( "SELECT id, name, company_name, status FROM `{$customers_table}` WHERE status = 'active' ORDER BY company_name ASC, name ASC" );
}
if ( ! is_array( $customers ) ) {
	$customers = array();
}

// Fetch active partner companies from tpm_partner_companies (including assigned partner if in edit mode).
$partners_table      = $wpdb->prefix . 'tpm_partner_companies';
$assigned_partner_id = ! empty( $form_data['partner_company_id'] ) ? absint( $form_data['partner_company_id'] ) : 0;
if ( $assigned_partner_id > 0 ) {
	$partners = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, company_name, contact_person, status FROM `{$partners_table}` WHERE status = 'active' OR id = %d ORDER BY company_name ASC",
			$assigned_partner_id
		)
	);
} else {
	$partners = $wpdb->get_results( "SELECT id, company_name, contact_person, status FROM `{$partners_table}` WHERE status = 'active' ORDER BY company_name ASC" );
}
if ( ! is_array( $partners ) ) {
	$partners = array();
}

$current_type = ! empty( $form_data['record_type'] ) ? sanitize_key( $form_data['record_type'] ) : 'tender';
?>

<div class="wrap tpm-admin-wrap">

	<!-- Header Banner -->
	<div class="tpm-header-banner">
		<div class="tpm-header-left">
			<h1>
				<?php if ( $is_edit && $record ) : ?>
					<?php
					$record_title = ( 'po' === $current_type )
						? sprintf( __( 'Edit Purchase Order #%s', 'tender-po-management' ), $record->customer_po_number ?: $record->id )
						: sprintf( __( 'Edit Tender #%s', 'tender-po-management' ), $record->tender_number ?: $record->id );
					echo esc_html( $record_title );
					?>
					<span class="tpm-version-badge tpm-badge-edit"><?php echo esc_html( sprintf( __( 'Editing ID: #%d', 'tender-po-management' ), $record_id ) ); ?></span>
				<?php else : ?>
					<?php esc_html_e( 'Add New Record', 'tender-po-management' ); ?>
					<span class="tpm-version-badge"><?php esc_html_e( 'Drafting', 'tender-po-management' ); ?></span>
				<?php endif; ?>
			</h1>
			<p>
				<?php if ( $is_edit ) : ?>
					<?php esc_html_e( 'Modify record fields, status, pricing, milestone dates, shipping logistics, and linked documents.', 'tender-po-management' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Create a new Tender submission or register an authorized Purchase Order.', 'tender-po-management' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="tpm-header-actions">
			<?php if ( $is_edit ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to Record Details', 'tender-po-management' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-btn tpm-btn-secondary">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Back to All Records', 'tender-po-management' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $error_notice ) ) : ?>
		<div class="notice notice-error tpm-notice is-dismissible">
			<p><strong><?php esc_html_e( 'Please correct the following errors:', 'tender-po-management' ); ?></strong></p>
			<div><?php echo wp_kses_post( $error_notice ); ?></div>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tpm-record-form" class="tpm-form">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="action" value="tpm_update_record">
			<input type="hidden" name="record_id" value="<?php echo esc_attr( $record_id ); ?>">
			<?php wp_nonce_field( 'tpm_update_record_action', 'tpm_record_nonce' ); ?>
		<?php else : ?>
			<input type="hidden" name="action" value="tpm_save_record">
			<?php wp_nonce_field( 'tpm_save_record_action', 'tpm_record_nonce' ); ?>
		<?php endif; ?>

		<div class="tpm-form-layout">

			<!-- Main Column -->
			<div class="tpm-form-main">

				<!-- SECTION 1: BASIC INFORMATION -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-basic">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Section 1: Basic Information', 'tender-po-management' ); ?>
						</h2>
						<span class="tpm-badge tpm-badge-review"><?php esc_html_e( 'Required', 'tender-po-management' ); ?></span>
					</div>
					<div class="tpm-card-body">
						<!-- Record Type Selector -->
						<div class="tpm-form-group">
							<label class="tpm-label tpm-required"><?php esc_html_e( 'Record Type', 'tender-po-management' ); ?></label>
							<div class="tpm-record-type-selector">
								<label class="tpm-radio-card <?php echo ( 'tender' === $current_type ) ? 'tpm-radio-selected' : ''; ?>" for="record_type_tender">
									<input type="radio" name="record_type" id="record_type_tender" value="tender" <?php checked( $current_type, 'tender' ); ?>>
									<span class="tpm-radio-icon"><span class="dashicons dashicons-clipboard"></span></span>
									<span class="tpm-radio-text">
										<strong><?php esc_html_e( 'Tender', 'tender-po-management' ); ?></strong>
										<small><?php esc_html_e( 'Competitive bidding or submission', 'tender-po-management' ); ?></small>
									</span>
								</label>
								<label class="tpm-radio-card <?php echo ( 'po' === $current_type ) ? 'tpm-radio-selected' : ''; ?>" for="record_type_po">
									<input type="radio" name="record_type" id="record_type_po" value="po" <?php checked( $current_type, 'po' ); ?>>
									<span class="tpm-radio-icon"><span class="dashicons dashicons-cart"></span></span>
									<span class="tpm-radio-text">
										<strong><?php esc_html_e( 'Purchase Order', 'tender-po-management' ); ?></strong>
										<small><?php esc_html_e( 'Operational procurement order', 'tender-po-management' ); ?></small>
									</span>
								</label>
							</div>
						</div>

						<div class="tpm-form-row tpm-cols-3">
							<!-- Status Dropdown -->
							<div class="tpm-form-group">
								<label for="status" class="tpm-label tpm-required"><?php esc_html_e( 'Status', 'tender-po-management' ); ?></label>
								<select name="status" id="status" class="tpm-select" required>
									<?php
									$selected_status = $val( 'status', 'draft' );
									foreach ( $statuses as $status_obj ) :
										$selected = ( $status_obj->status_slug === $selected_status ) ? 'selected' : '';
										?>
										<option value="<?php echo esc_attr( $status_obj->status_slug ); ?>" <?php echo $selected; ?>>
											<?php echo esc_html( $status_obj->status_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<!-- Tender Number -->
							<div class="tpm-form-group">
								<label for="tender_number" class="tpm-label">
									<span id="tender-number-label"><?php esc_html_e( 'Tender Number', 'tender-po-management' ); ?></span>
								</label>
								<input type="text" name="tender_number" id="tender_number" class="tpm-input" value="<?php echo $val( 'tender_number' ); ?>" placeholder="e.g. TND-2026-001">
								<span class="tpm-field-hint"><?php esc_html_e( 'Unique identifier for tender bidding', 'tender-po-management' ); ?></span>
							</div>

							<!-- Fill Ref Number -->
							<div class="tpm-form-group">
								<label for="fill_ref_number" class="tpm-label"><?php esc_html_e( 'Fill Ref Number', 'tender-po-management' ); ?></label>
								<input type="text" name="fill_ref_number" id="fill_ref_number" class="tpm-input" value="<?php echo $val( 'fill_ref_number' ); ?>" placeholder="e.g. REF-8842">
								<span class="tpm-field-hint"><?php esc_html_e( 'Internal filing reference index', 'tender-po-management' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- SECTION 2: CUSTOMER INFORMATION -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-customer">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-businessman"></span>
							<?php esc_html_e( 'Section 2: Customer Information', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-form-row tpm-cols-3">
							<!-- Customer Select -->
							<div class="tpm-form-group">
								<label for="customer_id" class="tpm-label"><?php esc_html_e( 'Customer', 'tender-po-management' ); ?></label>
								<select name="customer_id" id="customer_id" class="tpm-select">
									<option value=""><?php esc_html_e( '-- Select Customer (Optional) --', 'tender-po-management' ); ?></option>
									<?php
									$selected_cust = absint( $val( 'customer_id', 0 ) );
									foreach ( $customers as $cust ) :
										$c_name = ! empty( $cust->company_name ) ? $cust->company_name . ' (' . $cust->name . ')' : $cust->name;
										if ( ! empty( $cust->status ) && 'inactive' === $cust->status ) {
											$c_name .= ' [' . __( 'Inactive', 'tender-po-management' ) . ']';
										}
										$sel = ( (int) $cust->id === $selected_cust ) ? 'selected' : '';
										?>
										<option value="<?php echo esc_attr( $cust->id ); ?>" <?php echo $sel; ?>>
											<?php echo esc_html( $c_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<span class="tpm-field-hint">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-customers&action=new' ) ); ?>" target="_blank">
										<?php esc_html_e( '+ Add New Customer', 'tender-po-management' ); ?>
									</a>
								</span>
							</div>

							<!-- Customer PO Number -->
							<div class="tpm-form-group">
								<label for="customer_po_number" class="tpm-label"><?php esc_html_e( 'Customer PO Number', 'tender-po-management' ); ?></label>
								<input type="text" name="customer_po_number" id="customer_po_number" class="tpm-input" value="<?php echo $val( 'customer_po_number' ); ?>" placeholder="e.g. CPO-94102">
							</div>

							<!-- Customer PO Name -->
							<div class="tpm-form-group">
								<label for="customer_po_name" class="tpm-label"><?php esc_html_e( 'Customer PO Name', 'tender-po-management' ); ?></label>
								<input type="text" name="customer_po_name" id="customer_po_name" class="tpm-input" value="<?php echo $val( 'customer_po_name' ); ?>" placeholder="e.g. Ministry of Health Expansion">
							</div>
						</div>
					</div>
				</div>

				<!-- SECTION 3: ITEM INFORMATION -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-items">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-products"></span>
							<?php esc_html_e( 'Section 3: Item Information', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-form-group">
							<label for="item_description" class="tpm-label"><?php esc_html_e( 'Item Description', 'tender-po-management' ); ?></label>
							<textarea name="item_description" id="item_description" class="tpm-textarea" rows="4" placeholder="<?php esc_attr_e( 'Provide detailed technical specifications, quantity, line items, part numbers, and manufacturer data...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $form_data['item_description'] ?? '' ); ?></textarea>
						</div>

						<div class="tpm-form-row tpm-cols-2">
							<div class="tpm-form-group">
								<label for="hs_code" class="tpm-label"><?php esc_html_e( 'HS Code (Harmonized System)', 'tender-po-management' ); ?></label>
								<input type="text" name="hs_code" id="hs_code" class="tpm-input" value="<?php echo $val( 'hs_code' ); ?>" placeholder="e.g. 8471.30.01">
								<span class="tpm-field-hint"><?php esc_html_e( 'International tariff classification code', 'tender-po-management' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- SECTION 4: IMPORTANT DATES -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-dates">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php esc_html_e( 'Section 4: Important Dates', 'tender-po-management' ); ?>
						</h2>
						<span class="tpm-badge"><?php esc_html_e( 'Milestones', 'tender-po-management' ); ?></span>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-form-row tpm-cols-3">
							<!-- Tender Due Date -->
							<div class="tpm-form-group">
								<label for="tender_due_date" class="tpm-label"><?php esc_html_e( 'Tender Due Date', 'tender-po-management' ); ?></label>
								<input type="date" name="tender_due_date" id="tender_due_date" class="tpm-input" value="<?php echo $val( 'tender_due_date' ); ?>">
							</div>

							<!-- Tender Submission Date -->
							<div class="tpm-form-group">
								<label for="tender_submission_date" class="tpm-label"><?php esc_html_e( 'Tender Submission Date', 'tender-po-management' ); ?></label>
								<input type="date" name="tender_submission_date" id="tender_submission_date" class="tpm-input" value="<?php echo $val( 'tender_submission_date' ); ?>">
							</div>

							<!-- Quote Expiration Date -->
							<div class="tpm-form-group">
								<label for="quote_expiration_date" class="tpm-label"><?php esc_html_e( 'Quote Expiration Date', 'tender-po-management' ); ?></label>
								<input type="date" name="quote_expiration_date" id="quote_expiration_date" class="tpm-input" value="<?php echo $val( 'quote_expiration_date' ); ?>">
							</div>
						</div>

						<div class="tpm-form-row tpm-cols-4">
							<!-- PO Date -->
							<div class="tpm-form-group">
								<label for="po_date" class="tpm-label"><?php esc_html_e( 'PO Date', 'tender-po-management' ); ?></label>
								<input type="date" name="po_date" id="po_date" class="tpm-input" value="<?php echo $val( 'po_date' ); ?>">
							</div>

							<!-- Payment Due Date -->
							<div class="tpm-form-group">
								<label for="payment_due_date" class="tpm-label"><?php esc_html_e( 'Payment Due Date', 'tender-po-management' ); ?></label>
								<input type="date" name="payment_due_date" id="payment_due_date" class="tpm-input" value="<?php echo $val( 'payment_due_date' ); ?>">
							</div>

							<!-- Shipping Date -->
							<div class="tpm-form-group">
								<label for="shipping_date" class="tpm-label"><?php esc_html_e( 'Shipping Date', 'tender-po-management' ); ?></label>
								<input type="date" name="shipping_date" id="shipping_date" class="tpm-input" value="<?php echo $val( 'shipping_date' ); ?>">
							</div>

							<!-- Settled Date -->
							<div class="tpm-form-group">
								<label for="settled_date" class="tpm-label"><?php esc_html_e( 'Settled Date', 'tender-po-management' ); ?></label>
								<input type="date" name="settled_date" id="settled_date" class="tpm-input" value="<?php echo $val( 'settled_date' ); ?>">
							</div>
						</div>
					</div>
				</div>

				<!-- SECTION 5: FINANCIAL INFORMATION & CALCULATIONS -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-financial">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-money-alt"></span>
							<?php esc_html_e( 'Section 5: Financial Information & Live Calculator', 'tender-po-management' ); ?>
						</h2>
						<span class="tpm-badge tpm-badge-active"><?php esc_html_e( 'Auto-Calculated', 'tender-po-management' ); ?></span>
					</div>
					<div class="tpm-card-body">

						<!-- Live Profit Display Bar -->
						<div class="tpm-calc-summary-bar">
							<div class="tpm-calc-metric">
								<span class="tpm-metric-label"><?php esc_html_e( 'Gross Profit (Sell - Purchase)', 'tender-po-management' ); ?></span>
								<span class="tpm-metric-val tpm-metric-positive" id="calc-gross-profit"><?php echo esc_html( $currency_symbol ); ?>0.00</span>
							</div>
							<div class="tpm-calc-metric">
								<span class="tpm-metric-label"><?php esc_html_e( 'Net Profit (Gross - Expenses)', 'tender-po-management' ); ?></span>
								<span class="tpm-metric-val tpm-metric-positive" id="calc-net-profit"><?php echo esc_html( $currency_symbol ); ?>0.00</span>
							</div>
							<div class="tpm-calc-metric">
								<span class="tpm-metric-label"><?php esc_html_e( 'Est. Balance Due (Sell - Payment)', 'tender-po-management' ); ?></span>
								<span class="tpm-metric-val" id="calc-balance-due"><?php echo esc_html( $currency_symbol ); ?>0.00</span>
							</div>
						</div>

						<div class="tpm-form-row tpm-cols-3">
							<!-- Purchase Price -->
							<div class="tpm-form-group">
								<label for="purchase_price" class="tpm-label"><?php printf( esc_html__( 'Purchase Price (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
								<input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="tpm-input tpm-calc-trigger" value="<?php echo $val( 'purchase_price', '0.00' ); ?>" placeholder="0.00">
							</div>

							<!-- Sell Price -->
							<div class="tpm-form-group">
								<label for="sell_price" class="tpm-label"><?php printf( esc_html__( 'Sell Price (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
								<input type="number" step="0.01" min="0" name="sell_price" id="sell_price" class="tpm-input tpm-calc-trigger" value="<?php echo $val( 'sell_price', '0.00' ); ?>" placeholder="0.00">
							</div>

							<!-- Expenses -->
							<div class="tpm-form-group">
								<label for="expenses" class="tpm-label"><?php printf( esc_html__( 'Expenses (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
								<input type="number" step="0.01" min="0" name="expenses" id="expenses" class="tpm-input tpm-calc-trigger" value="<?php echo $val( 'expenses', '0.00' ); ?>" placeholder="0.00">
							</div>
						</div>

						<div class="tpm-form-row tpm-cols-2">
							<!-- Actual Payment -->
							<div class="tpm-form-group">
								<label for="actual_payment" class="tpm-label"><?php printf( esc_html__( 'Actual Payment Received (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
								<input type="number" step="0.01" min="0" name="actual_payment" id="actual_payment" class="tpm-input tpm-calc-trigger" value="<?php echo $val( 'actual_payment', '0.00' ); ?>" placeholder="0.00">
							</div>

							<!-- Balance Payment Due -->
							<div class="tpm-form-group">
								<div class="tpm-label-with-action">
									<label for="balance_payment_due" class="tpm-label"><?php printf( esc_html__( 'Balance Payment Due (%s)', 'tender-po-management' ), esc_html( $currency_symbol ) ); ?></label>
									<button type="button" class="button-link" id="tpm-btn-autofill-balance" title="<?php esc_attr_e( 'Set to Sell Price - Actual Payment', 'tender-po-management' ); ?>">
										<?php esc_html_e( 'Auto-fill Balance', 'tender-po-management' ); ?>
									</button>
								</div>
								<input type="number" step="0.01" min="0" name="balance_payment_due" id="balance_payment_due" class="tpm-input" value="<?php echo $val( 'balance_payment_due', '0.00' ); ?>" placeholder="0.00">
							</div>
						</div>

						<div class="tpm-form-row tpm-cols-2">
							<!-- Partner Company -->
							<div class="tpm-form-group">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
									<label for="partner_company_id" class="tpm-label" style="margin-bottom: 0;"><?php esc_html_e( 'Partner Company', 'tender-po-management' ); ?></label>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-partner-companies&action=new' ) ); ?>" target="_blank" class="tpm-link-add-new" style="font-size: 11px; text-decoration: none;">
										<span class="dashicons dashicons-plus" style="font-size: 13px; vertical-align: text-bottom; width: 13px; height: 13px;"></span>
										<?php esc_html_e( 'Add Partner', 'tender-po-management' ); ?>
									</a>
								</div>
								<select name="partner_company_id" id="partner_company_id" class="tpm-select">
									<option value=""><?php esc_html_e( '-- Select Partner Company (Optional) --', 'tender-po-management' ); ?></option>
									<?php
									$selected_partner = absint( $val( 'partner_company_id', 0 ) );
									foreach ( $partners as $partner ) :
										$sel   = ( (int) $partner->id === $selected_partner ) ? 'selected' : '';
										$label = esc_html( $partner->company_name );
										if ( ! empty( $partner->contact_person ) ) {
											$label .= ' (' . esc_html( $partner->contact_person ) . ')';
										}
										if ( isset( $partner->status ) && 'inactive' === $partner->status ) {
											$label .= ' [' . esc_html__( 'Inactive', 'tender-po-management' ) . ']';
										}
										?>
										<option value="<?php echo esc_attr( $partner->id ); ?>" <?php echo $sel; ?>>
											<?php echo $label; ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<!-- Partner Percentage -->
							<div class="tpm-form-group">
								<label for="partner_percentage" class="tpm-label"><?php esc_html_e( 'Partner Percentage (%)', 'tender-po-management' ); ?></label>
								<input type="number" step="0.01" min="0" max="100" name="partner_percentage" id="partner_percentage" class="tpm-input" value="<?php echo $val( 'partner_percentage', '0.00' ); ?>" placeholder="0.00">
								<span class="tpm-field-hint"><?php esc_html_e( 'Allowed range: 0.00% to 100.00%', 'tender-po-management' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- SECTION 6: SHIPPING & LOGISTICS -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-shipping">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-location-alt"></span>
							<?php esc_html_e( 'Section 6: Shipping & Logistics', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-form-row tpm-cols-2">
							<!-- Tracking Number -->
							<div class="tpm-form-group">
								<label for="tracking_number" class="tpm-label"><?php esc_html_e( 'Tracking Number', 'tender-po-management' ); ?></label>
								<input type="text" name="tracking_number" id="tracking_number" class="tpm-input" value="<?php echo $val( 'tracking_number' ); ?>" placeholder="e.g. DHL-7789421034">
							</div>

							<!-- Freight Forwarder Location -->
							<div class="tpm-form-group">
								<label for="ff_location" class="tpm-label"><?php esc_html_e( 'FF Location (Freight Forwarder Hub)', 'tender-po-management' ); ?></label>
								<input type="text" name="ff_location" id="ff_location" class="tpm-input" value="<?php echo $val( 'ff_location' ); ?>" placeholder="e.g. Dubai Cargo Terminal 2">
							</div>
						</div>

						<!-- Original AWB/BoL Checkbox -->
						<div class="tpm-form-group">
							<label class="tpm-checkbox-label" for="original_awb_received">
								<input type="checkbox" name="original_awb_received" id="original_awb_received" value="1" <?php checked( (int) $val( 'original_awb_received', 0 ), 1 ); ?>>
								<span><strong><?php esc_html_e( 'Original AWB / BoL Received', 'tender-po-management' ); ?></strong></span>
							</label>
							<span class="tpm-field-hint"><?php esc_html_e( 'Confirm physical reception of the Air Waybill or Bill of Lading original documents.', 'tender-po-management' ); ?></span>
						</div>
					</div>
				</div>

				<!-- SECTION 7: ADDITIONAL INFORMATION -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-additional">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-index-card"></span>
							<?php esc_html_e( 'Section 7: Additional Information', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-form-row tpm-cols-2">
							<!-- Fill Invoice Number -->
							<div class="tpm-form-group">
								<label for="fill_invoice_number" class="tpm-label"><?php esc_html_e( 'Fill Invoice Number', 'tender-po-management' ); ?></label>
								<input type="text" name="fill_invoice_number" id="fill_invoice_number" class="tpm-input" value="<?php echo $val( 'fill_invoice_number' ); ?>" placeholder="e.g. INV-2026-902">
							</div>

							<!-- Fill PO -->
							<div class="tpm-form-group">
								<label for="fill_po" class="tpm-label"><?php esc_html_e( 'Fill PO Number', 'tender-po-management' ); ?></label>
								<input type="text" name="fill_po" id="fill_po" class="tpm-input" value="<?php echo $val( 'fill_po' ); ?>" placeholder="e.g. FPO-4091">
							</div>
						</div>

						<!-- Notes -->
						<div class="tpm-form-group">
							<label for="notes" class="tpm-label"><?php esc_html_e( 'Internal Notes & Audit Remarks', 'tender-po-management' ); ?></label>
							<textarea name="notes" id="notes" class="tpm-textarea" rows="4" placeholder="<?php esc_attr_e( 'Add internal notes, historical context, or custom instructions...', 'tender-po-management' ); ?>"><?php echo esc_textarea( $form_data['notes'] ?? '' ); ?></textarea>
						</div>
					</div>
				</div>

				<!-- SECTION 8: ATTACHMENTS (WP MEDIA LIBRARY) -->
				<div class="tpm-card tpm-collapsible-panel" id="tpm-section-attachments">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-paperclip"></span>
							<?php esc_html_e( 'Section 8: Document Attachments', 'tender-po-management' ); ?>
						</h2>
						<span class="tpm-badge"><?php esc_html_e( 'PDF, DOC, XLS, PNG, JPG', 'tender-po-management' ); ?></span>
					</div>
					<div class="tpm-card-body">
						<?php if ( $is_edit && ! empty( $existing_attachments ) ) : ?>
							<div class="tpm-existing-attachments-section">
								<h3 class="tpm-subsection-title">
									<span class="dashicons dashicons-media-default"></span>
									<?php esc_html_e( 'Existing Attachments Linked to this Record', 'tender-po-management' ); ?>
									<span class="tpm-badge tpm-badge-count"><?php echo count( $existing_attachments ); ?></span>
								</h3>
								<p class="description">
									<?php esc_html_e( 'Existing documents are preserved automatically. You can update labels or remove the relationship without deleting the physical file from your Media Library.', 'tender-po-management' ); ?>
								</p>

								<div class="tpm-existing-attachments-list" id="tpm-existing-attachments-list">
									<?php foreach ( $existing_attachments as $att ) : ?>
										<?php
										$ext = strtolower( pathinfo( $att->file_name, PATHINFO_EXTENSION ) );
										$icon = 'dashicons-media-default';
										if ( 'pdf' === $ext ) {
											$icon = 'dashicons-pdf';
										} elseif ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
											$icon = 'dashicons-format-image';
										} elseif ( in_array( $ext, array( 'doc', 'docx' ), true ) ) {
											$icon = 'dashicons-media-document';
										} elseif ( in_array( $ext, array( 'xls', 'xlsx', 'csv' ), true ) ) {
											$icon = 'dashicons-media-spreadsheet';
										}
										$file_size_fmt = ! empty( $att->file_size ) ? size_format( (int) $att->file_size ) : '';
										?>
										<div class="tpm-attachment-row tpm-existing-att-row" id="tpm-existing-att-<?php echo esc_attr( $att->id ); ?>">
											<div class="tpm-att-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></div>
											<div class="tpm-att-details">
												<div class="tpm-att-name">
													<?php echo esc_html( $att->file_name ); ?>
													<span class="tpm-badge tpm-badge-sm tpm-badge-existing"><?php esc_html_e( 'Saved', 'tender-po-management' ); ?></span>
												</div>
												<div class="tpm-att-meta">
													<?php echo esc_html( $att->file_type ?: 'document' ); ?>
													<?php if ( $file_size_fmt ) : ?>
														&bull; <?php echo esc_html( $file_size_fmt ); ?>
													<?php endif; ?>
													<?php if ( ! empty( $att->created_at ) ) : ?>
														&bull; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $att->created_at ) ) ); ?>
													<?php endif; ?>
												</div>
											</div>
											<div class="tpm-att-label-select">
												<label class="screen-reader-text" for="existing_att_label_<?php echo esc_attr( $att->id ); ?>"><?php esc_html_e( 'Attachment Label', 'tender-po-management' ); ?></label>
												<select name="existing_attachment_labels[<?php echo esc_attr( $att->id ); ?>]" id="existing_att_label_<?php echo esc_attr( $att->id ); ?>" class="tpm-select tpm-select-sm">
													<?php foreach ( $attachment_labels as $label_val => $label_text ) : ?>
														<option value="<?php echo esc_attr( $label_val ); ?>" <?php selected( $att->attachment_label, $label_val ); ?>>
															<?php echo esc_html( $label_text ); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</div>
											<div class="tpm-att-remove">
												<button type="button" class="tpm-btn-icon-danger tpm-remove-existing-att-btn" title="<?php esc_attr_e( 'Remove relationship', 'tender-po-management' ); ?>" data-id="<?php echo esc_attr( $att->id ); ?>">
													<span class="dashicons dashicons-trash"></span>
												</button>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
								<div class="tpm-no-attachments" id="tpm-no-existing-attachments-msg" style="display:none;">
									<p><?php esc_html_e( 'All existing attachment relationships marked for removal upon save.', 'tender-po-management' ); ?></p>
								</div>
								<hr class="tpm-divider" style="margin: 20px 0 15px;">
							</div>
						<?php endif; ?>

						<h3 class="tpm-subsection-title">
							<span class="dashicons dashicons-cloud-upload"></span>
							<?php echo $is_edit ? esc_html__( 'Add New Attachments', 'tender-po-management' ) : esc_html__( 'Upload or Link Documents', 'tender-po-management' ); ?>
						</h3>
						<p class="description">
							<?php esc_html_e( 'Attach files directly from the WordPress Media Library or upload new documents. Files will be linked upon saving.', 'tender-po-management' ); ?>
						</p>

						<div class="tpm-attachment-toolbar">
							<button type="button" class="tpm-btn tpm-btn-secondary" id="tpm-add-attachments-btn">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php esc_html_e( 'Select or Upload Files via Media Library', 'tender-po-management' ); ?>
							</button>
						</div>

						<!-- Attachments Container -->
						<div class="tpm-attachments-container" id="tpm-attachments-list">
							<div class="tpm-no-attachments" id="tpm-no-attachments-placeholder">
								<span class="dashicons dashicons-media-document"></span>
								<p><?php echo $is_edit ? esc_html__( 'No new attachments staged.', 'tender-po-management' ) : esc_html__( 'No attachments selected yet.', 'tender-po-management' ); ?></p>
							</div>
						</div>
					</div>
				</div>

			</div>

			<!-- Sidebar Actions Column -->
			<div class="tpm-form-side">

				<!-- Publishing Actions Card -->
				<div class="tpm-card tpm-action-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Actions', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body">
						<div class="tpm-action-meta-list">
							<div class="tpm-meta-item">
								<span class="tpm-meta-label"><?php esc_html_e( 'Record Target:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-val" id="sidebar-record-type-badge">
									<strong><?php echo ( 'po' === $current_type ) ? esc_html__( 'Purchase Order', 'tender-po-management' ) : esc_html__( 'Tender', 'tender-po-management' ); ?></strong>
								</span>
							</div>
							<div class="tpm-meta-item">
								<span class="tpm-meta-label"><?php esc_html_e( 'Status Target:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-val" id="sidebar-status-badge">
									<?php echo esc_html( ucfirst( $val( 'status', 'draft' ) ) ); ?>
								</span>
							</div>
							<div class="tpm-meta-item">
								<span class="tpm-meta-label"><?php esc_html_e( 'Est. Gross Profit:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-val" id="sidebar-gross-profit"><?php echo esc_html( $currency_symbol ); ?>0.00</span>
							</div>
							<div class="tpm-meta-item">
								<span class="tpm-meta-label"><?php esc_html_e( 'Est. Net Profit:', 'tender-po-management' ); ?></span>
								<span class="tpm-meta-val" id="sidebar-net-profit"><?php echo esc_html( $currency_symbol ); ?>0.00</span>
							</div>
						</div>

						<div class="tpm-action-buttons">
							<button type="submit" class="tpm-btn tpm-btn-primary tpm-btn-block" id="tpm-submit-record-btn">
								<?php if ( $is_edit ) : ?>
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e( 'Update Record', 'tender-po-management' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e( 'Create Record', 'tender-po-management' ); ?>
								<?php endif; ?>
							</button>

							<?php if ( $is_edit ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id ) ); ?>" class="tpm-btn tpm-btn-secondary tpm-btn-block">
									<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpm-records' ) ); ?>" class="tpm-btn tpm-btn-secondary tpm-btn-block">
									<?php esc_html_e( 'Cancel', 'tender-po-management' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Quick Guidelines Card -->
				<div class="tpm-card">
					<div class="tpm-card-header">
						<h2>
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'Instructions', 'tender-po-management' ); ?>
						</h2>
					</div>
					<div class="tpm-card-body tpm-quick-help">
						<ul class="tpm-help-list">
							<li>
								<strong><?php esc_html_e( 'Tender Submission:', 'tender-po-management' ); ?></strong>
								<?php esc_html_e( 'Enter the bidding tender number and submission deadline.', 'tender-po-management' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Purchase Order:', 'tender-po-management' ); ?></strong>
								<?php esc_html_e( 'Link customer and internal PO references to match incoming payments.', 'tender-po-management' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Live Calculations:', 'tender-po-management' ); ?></strong>
								<?php esc_html_e( 'Gross and net profit automatically calculate as you update prices and expenses.', 'tender-po-management' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Audit Trail:', 'tender-po-management' ); ?></strong>
								<?php esc_html_e( 'An activity log is automatically recorded upon creation.', 'tender-po-management' ); ?>
							</li>
						</ul>
					</div>
				</div>

			</div>

		</div>
	</form>

</div>
