<?php
/**
 * Dedicated Admin Menu and Screen Coordinator.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/admin
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dedicated Admin Menu and Screen Coordinator.
 *
 * Registers top-level and submenu navigation, enforces capability checks,
 * loads corresponding view templates, and enqueues screen-isolated assets.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/admin
 * @since      1.0.0
 */
class TPM_Admin {

	/**
	 * The unique ID of this plugin.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private string $version;

	/**
	 * List of registered screen hook suffixes.
	 *
	 * @since 1.0.0
	 * @var   array<string>
	 */
	private array $hook_suffixes = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name Plugin identifier.
	 * @param string $version     Plugin version.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the main top-level menu and all submenus.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menus(): void {
		// Main Menu: Tender Management
		$main_page = add_menu_page(
			__( 'Tender Management', 'tender-po-management' ),
			__( 'Tender Management', 'tender-po-management' ),
			'manage_tpm_records',
			'tender-po-management',
			array( $this, 'display_dashboard_page' ),
			'dashicons-portfolio',
			26
		);
		$this->hook_suffixes[] = $main_page;

		// 1. Dashboard (replaces default first submenu to avoid duplicate menu)
		$sub_dashboard = add_submenu_page(
			'tender-po-management',
			__( 'Dashboard - Tender Management', 'tender-po-management' ),
			__( 'Dashboard', 'tender-po-management' ),
			'view_tpm_records',
			'tender-po-management',
			array( $this, 'display_dashboard_page' )
		);
		$this->hook_suffixes[] = $sub_dashboard;

		// 2. Add New Record
		$sub_add = add_submenu_page(
			'tender-po-management',
			__( 'Add New Record - Tender Management', 'tender-po-management' ),
			__( 'Add New Record', 'tender-po-management' ),
			'create_tpm_records',
			'tpm-add-record',
			array( $this, 'display_add_record_page' )
		);
		$this->hook_suffixes[] = $sub_add;

		// 3. All Records
		$sub_records = add_submenu_page(
			'tender-po-management',
			__( 'All Records - Tender Management', 'tender-po-management' ),
			__( 'All Records', 'tender-po-management' ),
			'view_tpm_records',
			'tpm-records',
			array( $this, 'display_records_page' )
		);
		$this->hook_suffixes[] = $sub_records;

		// 4. Tenders
		$sub_tenders = add_submenu_page(
			'tender-po-management',
			__( 'Tenders - Tender Management', 'tender-po-management' ),
			__( 'Tenders', 'tender-po-management' ),
			'view_tpm_records',
			'tpm-tenders',
			array( $this, 'display_tenders_page' )
		);
		$this->hook_suffixes[] = $sub_tenders;

		// 5. Purchase Orders
		$sub_pos = add_submenu_page(
			'tender-po-management',
			__( 'Purchase Orders - Tender Management', 'tender-po-management' ),
			__( 'Purchase Orders', 'tender-po-management' ),
			'view_tpm_records',
			'tpm-purchase-orders',
			array( $this, 'display_purchase_orders_page' )
		);
		$this->hook_suffixes[] = $sub_pos;

		// 6. Archived Records
		$sub_archive = add_submenu_page(
			'tender-po-management',
			__( 'Archived Records - Tender Management', 'tender-po-management' ),
			__( 'Archived Records', 'tender-po-management' ),
			'manage_tpm_archive',
			'tpm-archived-records',
			array( $this, 'display_archived_records_page' )
		);
		$this->hook_suffixes[] = $sub_archive;

		// 7. Customers
		$sub_customers = add_submenu_page(
			'tender-po-management',
			__( 'Customers - Tender Management', 'tender-po-management' ),
			__( 'Customers', 'tender-po-management' ),
			'manage_tpm_customers',
			'tpm-customers',
			array( $this, 'display_customers_page' )
		);
		$this->hook_suffixes[] = $sub_customers;

		// 8. Partner Companies
		$sub_partners = add_submenu_page(
			'tender-po-management',
			__( 'Partner Companies - Tender Management', 'tender-po-management' ),
			__( 'Partner Companies', 'tender-po-management' ),
			'manage_tpm_partners',
			'tpm-partner-companies',
			array( $this, 'display_partner_companies_page' )
		);
		$this->hook_suffixes[] = $sub_partners;

		// 9. Reports
		$sub_reports = add_submenu_page(
			'tender-po-management',
			__( 'Reports - Tender Management', 'tender-po-management' ),
			__( 'Reports', 'tender-po-management' ),
			'view_tpm_reports',
			'tpm-reports',
			array( $this, 'display_reports_page' )
		);
		$this->hook_suffixes[] = $sub_reports;

		// 10. Settings
		$sub_settings = add_submenu_page(
			'tender-po-management',
			__( 'Settings - Tender Management', 'tender-po-management' ),
			__( 'Settings', 'tender-po-management' ),
			'manage_tpm_settings',
			'tpm-settings',
			array( $this, 'display_settings_page' )
		);
		$this->hook_suffixes[] = $sub_settings;

		// 11. Record Details (hidden submenu, accessible via direct URL)
		$sub_details = add_submenu_page(
			null,
			__( 'Record Details - Tender Management', 'tender-po-management' ),
			__( 'Record Details', 'tender-po-management' ),
			'view_tpm_records',
			'tpm-record-details',
			array( $this, 'display_record_details_page' )
		);
		$this->hook_suffixes[] = $sub_details;
	}

	/**
	 * Verify if the current screen belongs exclusively to this plugin.
	 *
	 * @since 1.0.0
	 * @param string $hook Screen hook name.
	 * @return bool
	 */
	public function is_plugin_screen( string $hook ): bool {
		if ( in_array( $hook, $this->hook_suffixes, true ) ) {
			return true;
		}

		if ( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'tender-po-management' === $page || 0 === strpos( $page, 'tpm-' );
		}

		return false;
	}

	/**
	 * Enqueue stylesheets strictly on plugin admin pages.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name . '-admin',
			TPM_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue JavaScript strictly on plugin admin pages.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		// Enqueue WordPress Media Library uploader scripts/styles.
		wp_enqueue_media();

		wp_enqueue_script(
			$this->plugin_name . '-admin',
			TPM_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name . '-admin',
			'tpmAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'adminPostUrl'   => admin_url( 'admin-post.php' ),
				'nonce'          => wp_create_nonce( 'tpm_admin_nonce' ),
				'currencySymbol' => TPM_Settings_Service::get_currency_symbol(),
				'currencyCode'   => TPM_Settings_Service::get_currency_code(),
				'attachmentLabels' => TPM_Record_Service::get_attachment_labels(),
				'strings'        => array(
					'confirmDelete'     => __( 'Are you sure you want to delete this item?', 'tender-po-management' ),
					'confirmBulkDelete' => __( 'Are you sure you want to permanently delete the selected records? This action cannot be undone.', 'tender-po-management' ),
					'selectRecords'     => __( 'Please select at least one record to perform this action.', 'tender-po-management' ),
					'removeAttachment'  => __( 'Remove', 'tender-po-management' ),
					'chooseFile'        => __( 'Select or Upload Documents', 'tender-po-management' ),
					'selectFilesBtn'    => __( 'Attach Selected Files', 'tender-po-management' ),
					'loading'           => __( 'Loading, please wait...', 'tender-po-management' ),
					'fileAlreadyAdded'  => __( 'This file has already been added.', 'tender-po-management' ),
				),
			)
		);
	}

	/**
	 * 1. Display Dashboard Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_dashboard_page(): void {
		if ( ! current_user_can( 'view_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access the Dashboard.', 'tender-po-management' ) );
		}

		$this->load_view( 'dashboard.php' );
	}

	/**
	 * 2. Display Add / Edit Record Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_add_record_page(): void {
		$is_edit = ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! empty( $_GET['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $is_edit ) {
			if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to edit records.', 'tender-po-management' ) );
			}

			$record_id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$record    = TPM_Record_Service::get_record( $record_id );
			if ( ! $record ) {
				wp_die( esc_html__( 'Record not found or has been deleted.', 'tender-po-management' ) );
			}
		} else {
			if ( ! current_user_can( 'create_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to create records.', 'tender-po-management' ) );
			}
		}

		$this->load_view( 'add-record.php' );
	}

	/**
	 * 3. Display All Records Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_records_page(): void {
		if ( ! current_user_can( 'view_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to view records.', 'tender-po-management' ) );
		}

		// Backward compatibility: redirect view action to canonical tpm-record-details page
		if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$record_id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$created   = ! empty( $_GET['created'] ) ? '&created=1' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . $created ) );
			exit;
		}

		// Edit action redirect to tpm-add-record with edit action
		if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$record_id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-add-record&action=edit&id=' . $record_id ) );
			exit;
		}

		$this->load_view( 'records.php' );
	}

	/**
	 * 4. Display Tenders Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_tenders_page(): void {
		if ( ! current_user_can( 'view_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to view tenders.', 'tender-po-management' ) );
		}

		$this->load_view( 'tenders.php' );
	}

	/**
	 * 5. Display Purchase Orders Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_purchase_orders_page(): void {
		if ( ! current_user_can( 'view_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to view purchase orders.', 'tender-po-management' ) );
		}

		$this->load_view( 'purchase-orders.php' );
	}

	/**
	 * 6. Display Archived Records Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_archived_records_page(): void {
		if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to view archived records.', 'tender-po-management' ) );
		}

		$this->load_view( 'archived-records.php' );
	}

	/**
	 * 7. Display Customers Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_customers_page(): void {
		if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage customers.', 'tender-po-management' ) );
		}

		$this->load_view( 'customers.php' );
	}

	/**
	 * 8. Display Partner Companies Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_partner_companies_page(): void {
		if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage partner companies.', 'tender-po-management' ) );
		}

		$this->load_view( 'partner-companies.php' );
	}

	/**
	 * 9. Display Reports Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_reports_page(): void {
		if ( ! current_user_can( 'view_tpm_reports' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to view reports.', 'tender-po-management' ) );
		}

		$this->load_view( 'reports.php' );
	}

	/**
	 * 10. Display Settings Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_settings_page(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access settings.', 'tender-po-management' ) );
		}

		$this->load_view( 'settings.php' );
	}

	/**
	 * Process form submission for creating a new Tender or Purchase Order record.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_save_record(): void {
		// Capability check
		if ( ! current_user_can( 'create_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to create records.', 'tender-po-management' ) );
		}

		// Security nonce check
		check_admin_referer( 'tpm_save_record_action', 'tpm_record_nonce' );

		$user_id = get_current_user_id();

		// Delegate creation and validation to TPM_Record_Service
		$result = TPM_Record_Service::create( $_POST );

		if ( is_wp_error( $result ) ) {
			// Save error notice and entered data to transient for redisplay
			set_transient( 'tpm_record_errors_' . $user_id, $result->get_error_message(), 60 );
			set_transient( 'tpm_record_form_data_' . $user_id, $_POST, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-add-record&error=1' ) );
			exit;
		}

		// Clear temporary form transients
		delete_transient( 'tpm_record_errors_' . $user_id );
		delete_transient( 'tpm_record_form_data_' . $user_id );

		// Set success notice transient
		$record_type = sanitize_key( $_POST['record_type'] ?? 'tender' );
		$type_label  = ( 'po' === $record_type ) ? __( 'Purchase Order', 'tender-po-management' ) : __( 'Tender', 'tender-po-management' );
		/* translators: %s: Record type name */
		$success_msg = sprintf( __( '%s created successfully!', 'tender-po-management' ), $type_label );
		set_transient( 'tpm_record_success_' . $user_id, $success_msg, 60 );

		// Redirect to Record Details view
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . absint( $result ) . '&created=1' ) );
		exit;
	}

	/**
	 * Process form submission for updating an existing Tender or Purchase Order record.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_update_record(): void {
		$record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : ( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		// Capability check
		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to edit records.', 'tender-po-management' ) );
		}

		// Security nonce check
		check_admin_referer( 'tpm_update_record_action', 'tpm_record_nonce' );

		$user_id = get_current_user_id();

		// Delegate update to TPM_Record_Service
		$result = TPM_Record_Service::update( $record_id, $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			// Save error notice and entered data to transient for redisplay
			set_transient( 'tpm_record_errors_' . $user_id, $result->get_error_message(), 60 );
			set_transient( 'tpm_record_form_data_' . $user_id, $_POST, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-add-record&action=edit&id=' . $record_id . '&error=1' ) );
			exit;
		}

		// Clear temporary form transients
		delete_transient( 'tpm_record_errors_' . $user_id );
		delete_transient( 'tpm_record_form_data_' . $user_id );

		// Set success notice transient
		$record_type = sanitize_key( $_POST['record_type'] ?? 'tender' );
		$type_label  = ( 'po' === $record_type ) ? __( 'Purchase Order', 'tender-po-management' ) : __( 'Tender', 'tender-po-management' );
		/* translators: %s: Record type name */
		$success_msg = sprintf( __( '%s updated successfully!', 'tender-po-management' ), $type_label );
		set_transient( 'tpm_record_success_' . $user_id, $success_msg, 60 );

		// Redirect to Record Details view with updated flag
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '&updated=1' ) );
		exit;
	}

	/**
	 * 11. Display Record Details Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_record_details_page(): void {
		if ( ! current_user_can( 'view_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to view record details.', 'tender-po-management' ) );
		}

		$this->load_view( 'record-details.php' );
	}

	/**
	 * Archive a record from record details.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_archive_record(): void {
		$record_id = isset( $_REQUEST['record_id'] ) ? absint( $_REQUEST['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to archive this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_archive_record_' . $record_id, 'tpm_nonce' );

		TPM_Record_Service::archive_record( $record_id, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Record moved to archive successfully.', 'tender-po-management' ), 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id ) );
		exit;
	}

	/**
	 * Restore an archived record from record details.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_unarchive_record(): void {
		$record_id = isset( $_REQUEST['record_id'] ) ? absint( $_REQUEST['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to restore this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_unarchive_record_' . $record_id, 'tpm_nonce' );

		TPM_Record_Service::unarchive_record( $record_id, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Record restored from archive successfully.', 'tender-po-management' ), 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id ) );
		exit;
	}

	/**
	 * Delete a record from record details.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_record(): void {
		$record_id = isset( $_REQUEST['record_id'] ) ? absint( $_REQUEST['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'delete_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_delete_record_' . $record_id, 'tpm_nonce' );

		TPM_Record_Service::delete_record( $record_id, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Record deleted successfully.', 'tender-po-management' ), 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-records' ) );
		exit;
	}

	/**
	 * Update internal notes for a record.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_update_notes(): void {
		$record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit notes.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_update_notes_' . $record_id, 'tpm_nonce' );

		$notes = isset( $_POST['notes'] ) ? (string) $_POST['notes'] : '';
		TPM_Record_Service::update_notes( $record_id, $notes, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Notes updated successfully.', 'tender-po-management' ), 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '#notes' ) );
		exit;
	}

	/**
	 * Handle adding attachments to an existing record.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_add_attachments(): void {
		$record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to attach documents to this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_add_attachments_action', 'tpm_nonce' );

		$attachments = isset( $_POST['attachments'] ) && is_array( $_POST['attachments'] ) ? $_POST['attachments'] : array();
		$result      = TPM_Record_Service::add_attachments_to_record( $record_id, $attachments, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_record_notice_' . get_current_user_id(), $result->get_error_message(), 60 );
		} else {
			/* translators: %d: Number of files */
			$msg = sprintf( _n( '%d document attached successfully.', '%d documents attached successfully.', (int) $result, 'tender-po-management' ), (int) $result );
			set_transient( 'tpm_record_notice_' . get_current_user_id(), $msg, 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '#attachments' ) );
		exit;
	}

	/**
	 * Handle deleting an attachment relationship from a record.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_attachment(): void {
		$attachment_row_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$record_id         = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;

		if ( ! $attachment_row_id || ! $record_id ) {
			wp_die( esc_html__( 'Invalid request parameters.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this attachment.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_delete_attachment_' . $attachment_row_id, 'tpm_nonce' );

		$result = TPM_Record_Service::delete_attachment( $attachment_row_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_record_notice_' . get_current_user_id(), $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Attachment relationship deleted successfully.', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '#attachments' ) );
		exit;
	}

	/**
	 * Handle List Table bulk operations (Archive, Restore, Delete).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_bulk_records(): void {
		check_admin_referer( 'bulk-records' );

		$action = '';
		if ( isset( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			$action = sanitize_key( $_POST['action'] );
		} elseif ( isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$action = sanitize_key( $_POST['action2'] );
		}

		$record_ids = isset( $_POST['record_ids'] ) && is_array( $_POST['record_ids'] )
			? array_filter( array_map( 'absint', $_POST['record_ids'] ) )
			: array();

		$sendback = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=tpm-records' );

		if ( empty( $record_ids ) || empty( $action ) ) {
			wp_safe_redirect( $sendback );
			exit;
		}

		$current_user_id = get_current_user_id();

		if ( 'archive' === $action ) {
			if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
				wp_die( esc_html__( 'You do not have permission to archive records.', 'tender-po-management' ) );
			}
			$count = TPM_Record_Service::bulk_archive( $record_ids, $current_user_id );
			/* translators: %d: Number of records */
			$msg = sprintf( _n( '%d record moved to archive.', '%d records moved to archive.', $count, 'tender-po-management' ), $count );
			set_transient( 'tpm_record_notice_' . $current_user_id, $msg, 60 );
		} elseif ( 'restore' === $action ) {
			if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
				wp_die( esc_html__( 'You do not have permission to restore records.', 'tender-po-management' ) );
			}
			$count = TPM_Record_Service::bulk_restore( $record_ids, $current_user_id );
			/* translators: %d: Number of records */
			$msg = sprintf( _n( '%d record restored from archive.', '%d records restored from archive.', $count, 'tender-po-management' ), $count );
			set_transient( 'tpm_record_notice_' . $current_user_id, $msg, 60 );
		} elseif ( 'permanent_delete' === $action || 'delete' === $action ) {
			if ( ! current_user_can( 'delete_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
				wp_die( esc_html__( 'You do not have permission to permanently delete records.', 'tender-po-management' ) );
			}
			$count = TPM_Record_Service::bulk_permanent_delete( $record_ids, $current_user_id );
			/* translators: %d: Number of records */
			$msg = sprintf( _n( '%d record permanently deleted.', '%d records permanently deleted.', $count, 'tender-po-management' ), $count );
			set_transient( 'tpm_record_notice_' . $current_user_id, $msg, 60 );
		}

		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Handle quick archive row action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_quick_archive(): void {
		$record_id = isset( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to archive this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_quick_archive_' . $record_id );

		TPM_Record_Service::archive_record( $record_id, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Record moved to archive.', 'tender-po-management' ), 60 );

		$sendback = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=tpm-records' );
		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Handle quick restore row action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_quick_restore(): void {
		$record_id = isset( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_archive' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to restore this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_quick_restore_' . $record_id );

		TPM_Record_Service::unarchive_record( $record_id, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Record restored from archive.', 'tender-po-management' ), 60 );

		$sendback = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=tpm-records' );
		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Handle quick delete row action (delegates to permanent delete with cascade).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_quick_delete(): void {
		$record_id = isset( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'delete_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this record.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_quick_delete_' . $record_id );

		TPM_Record_Service::permanent_delete_record( $record_id, get_current_user_id() );
		set_transient( 'tpm_record_notice_' . get_current_user_id(), __( 'Record permanently deleted.', 'tender-po-management' ), 60 );

		$sendback = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=tpm-records' );
		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Handle permanent deletion of a record.
	 *
	 * Requires high-level capability (delete_tpm_records or manage_tpm_records).
	 * Requires confirmation & nonce verification.
	 * Carefully cascades cleanup of related data (attachments, payments, logs, record).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_permanent_delete_record(): void {
		$record_id = isset( $_REQUEST['record_id'] ) ? absint( $_REQUEST['record_id'] ) : ( isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0 );
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'delete_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have permission to permanently delete records.', 'tender-po-management' ) );
		}

		$nonce_action = 'tpm_permanent_delete_' . $record_id;
		$nonce_name   = isset( $_REQUEST['tpm_nonce'] ) ? 'tpm_nonce' : '_wpnonce';
		check_admin_referer( $nonce_action, $nonce_name );

		$user_id = get_current_user_id();
		$success = TPM_Record_Service::permanent_delete_record( $record_id, $user_id );

		if ( $success ) {
			set_transient( 'tpm_record_notice_' . $user_id, __( 'Record permanently deleted and associated data cleaned.', 'tender-po-management' ), 60 );
		} else {
			set_transient( 'tpm_record_errors_' . $user_id, __( 'Failed to delete record or record not found.', 'tender-po-management' ), 60 );
		}

		$sendback = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=tpm-archived-records' );
		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Process form submission for creating a new customer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_save_customer(): void {
		if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage customers.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_save_customer_action', 'tpm_customer_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Customer_Service::create( $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_customer_errors_' . $user_id, $result->get_error_message(), 60 );
			set_transient( 'tpm_customer_form_data_' . $user_id, $_POST, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers&action=new&error=1' ) );
			exit;
		}

		delete_transient( 'tpm_customer_errors_' . $user_id );
		delete_transient( 'tpm_customer_form_data_' . $user_id );

		set_transient( 'tpm_customer_notice_' . $user_id, __( 'Customer created successfully!', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers&action=view&id=' . absint( $result ) . '&created=1' ) );
		exit;
	}

	/**
	 * Process form submission for updating an existing customer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_update_customer(): void {
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : ( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $customer_id ) {
			wp_die( esc_html__( 'Invalid customer identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to edit customers.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_update_customer_action', 'tpm_customer_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Customer_Service::update( $customer_id, $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_customer_errors_' . $user_id, $result->get_error_message(), 60 );
			set_transient( 'tpm_customer_form_data_' . $user_id, $_POST, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers&action=edit&id=' . $customer_id . '&error=1' ) );
			exit;
		}

		delete_transient( 'tpm_customer_errors_' . $user_id );
		delete_transient( 'tpm_customer_form_data_' . $user_id );

		set_transient( 'tpm_customer_notice_' . $user_id, __( 'Customer updated successfully!', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers&action=view&id=' . $customer_id . '&updated=1' ) );
		exit;
	}

	/**
	 * Process request to delete a customer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_customer(): void {
		$customer_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $customer_id ) {
			wp_die( esc_html__( 'Invalid customer identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to delete customers.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_delete_customer_' . $customer_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Customer_Service::delete_customer( $customer_id, $user_id );

		if ( is_wp_error( $result ) ) {
			// Cannot delete due to record dependencies, redirect to reassignment/deactivation screen
			set_transient( 'tpm_customer_errors_' . $user_id, $result->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers&action=delete&id=' . $customer_id ) );
			exit;
		}

		set_transient( 'tpm_customer_notice_' . $user_id, __( 'Customer deleted successfully.', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers' ) );
		exit;
	}

	/**
	 * Process request to deactivate a customer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_deactivate_customer(): void {
		$customer_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $customer_id ) {
			wp_die( esc_html__( 'Invalid customer identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage customers.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_deactivate_customer_' . $customer_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		TPM_Customer_Service::deactivate_customer( $customer_id, $user_id );

		set_transient( 'tpm_customer_notice_' . $user_id, __( 'Customer deactivated. Historic records preserved.', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers' ) );
		exit;
	}

	/**
	 * Process request to reassign all records to another customer and delete the source customer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_reassign_customer(): void {
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;

		if ( ! $source_id || ! $target_id ) {
			wp_die( esc_html__( 'Both source and target customer identifiers are required.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_customers' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage customers.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_reassign_customer_action', 'tpm_customer_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Customer_Service::reassign_and_delete( $source_id, $target_id, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_customer_errors_' . $user_id, $result->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers&action=delete&id=' . $source_id ) );
			exit;
		}

		set_transient( 'tpm_customer_notice_' . $user_id, __( 'Records successfully reassigned and source customer deleted.', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-customers' ) );
		exit;
	}

	/**
	 * Process form submission for creating a new partner company.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_save_partner(): void {
		if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage partner companies.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_save_partner_action', 'tpm_partner_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Partner_Service::create( $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_partner_errors_' . $user_id, $result->get_error_message(), 60 );
			set_transient( 'tpm_partner_form_data_' . $user_id, $_POST, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies&action=new&error=1' ) );
			exit;
		}

		delete_transient( 'tpm_partner_errors_' . $user_id );
		delete_transient( 'tpm_partner_form_data_' . $user_id );

		set_transient( 'tpm_partner_notice_' . $user_id, __( 'Partner company created successfully!', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . absint( $result ) . '&created=1' ) );
		exit;
	}

	/**
	 * Process form submission for updating an existing partner company.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_update_partner(): void {
		$partner_id = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : ( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $partner_id ) {
			wp_die( esc_html__( 'Invalid partner company identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to edit partner companies.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_update_partner_action', 'tpm_partner_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Partner_Service::update( $partner_id, $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_partner_errors_' . $user_id, $result->get_error_message(), 60 );
			set_transient( 'tpm_partner_form_data_' . $user_id, $_POST, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies&action=edit&id=' . $partner_id . '&error=1' ) );
			exit;
		}

		delete_transient( 'tpm_partner_errors_' . $user_id );
		delete_transient( 'tpm_partner_form_data_' . $user_id );

		set_transient( 'tpm_partner_notice_' . $user_id, __( 'Partner company updated successfully!', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . $partner_id . '&updated=1' ) );
		exit;
	}

	/**
	 * Process request to delete a partner company.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_partner(): void {
		$partner_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $partner_id ) {
			wp_die( esc_html__( 'Invalid partner company identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to delete partner companies.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_delete_partner_' . $partner_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Partner_Service::delete_partner( $partner_id, $user_id );

		if ( is_wp_error( $result ) ) {
			// Cannot delete due to record dependencies, redirect to reassignment/deactivation screen
			set_transient( 'tpm_partner_errors_' . $user_id, $result->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies&action=delete&id=' . $partner_id ) );
			exit;
		}

		set_transient( 'tpm_partner_notice_' . $user_id, __( 'Partner company deleted successfully.', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies' ) );
		exit;
	}

	/**
	 * Process request to deactivate a partner company.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_deactivate_partner(): void {
		$partner_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( ! $partner_id ) {
			wp_die( esc_html__( 'Invalid partner company identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage partner companies.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_deactivate_partner_' . $partner_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		TPM_Partner_Service::deactivate_partner( $partner_id, $user_id );

		set_transient( 'tpm_partner_notice_' . $user_id, __( 'Partner company deactivated. Historic records preserved.', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies' ) );
		exit;
	}

	/**
	 * Process request to reassign all records to another partner company and delete the source partner.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_reassign_partner(): void {
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;

		if ( ! $source_id || ! $target_id ) {
			wp_die( esc_html__( 'Both source and target partner identifiers are required.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'manage_tpm_partners' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage partner companies.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_reassign_partner_action', 'tpm_partner_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Partner_Service::reassign_and_delete( $source_id, $target_id, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_partner_errors_' . $user_id, $result->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies&action=delete&id=' . $source_id ) );
			exit;
		}

		set_transient( 'tpm_partner_notice_' . $user_id, __( 'Records successfully reassigned and source partner deleted.', 'tender-po-management' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-partner-companies' ) );
		exit;
	}

	/**
	 * Process request to add a new payment to a record.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_add_payment(): void {
		$record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		if ( ! $record_id ) {
			wp_die( esc_html__( 'Invalid record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to record payments.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_add_payment_' . $record_id, 'tpm_payment_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Payment_Service::create_payment( $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_payment_errors_' . $user_id, $result->get_error_message(), 60 );
		} else {
			$amount = (float) str_replace( array( ',', ' ' ), '', (string) ( $_POST['payment_amount'] ?? '0' ) );
			/* translators: %s: Formatted payment amount */
			$msg = sprintf( __( 'Payment of $%s recorded successfully!', 'tender-po-management' ), number_format( $amount, 2 ) );
			set_transient( 'tpm_payment_notice_' . $user_id, $msg, 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '#payments' ) );
		exit;
	}

	/**
	 * Process request to update an existing payment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_update_payment(): void {
		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		$record_id  = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;

		if ( ! $payment_id || ! $record_id ) {
			wp_die( esc_html__( 'Invalid payment or record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to update payments.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_update_payment_' . $payment_id, 'tpm_payment_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Payment_Service::update_payment( $payment_id, $_POST, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_payment_errors_' . $user_id, $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_payment_notice_' . $user_id, __( 'Payment updated successfully!', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '#payments' ) );
		exit;
	}

	/**
	 * Process request to delete a payment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_payment(): void {
		$payment_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		$record_id  = isset( $_REQUEST['record_id'] ) ? absint( $_REQUEST['record_id'] ) : 0;

		if ( ! $payment_id || ! $record_id ) {
			wp_die( esc_html__( 'Invalid payment or record identifier.', 'tender-po-management' ) );
		}

		if ( ! current_user_can( 'edit_tpm_records' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to delete payments.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_delete_payment_' . $payment_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Payment_Service::delete_payment( $payment_id, $user_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_payment_errors_' . $user_id, $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_payment_notice_' . $user_id, __( 'Payment deleted successfully.', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id . '#payments' ) );
		exit;
	}

	/**
	 * Handle CSV Export for Reports.
	 *
	 * Validates user capability, verifies nonce, sanitizes filter inputs,
	 * and streams the safe, Excel-compatible CSV download.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_export_report(): void {
		if ( ! current_user_can( 'view_tpm_reports' ) && ! current_user_can( 'manage_tpm_records' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to export reports.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_export_report', 'tpm_nonce' );

		$report_service = new TPM_Report_Service();
		$filters        = $report_service->sanitize_filters( $_GET );
		$report_type    = $filters['report_type'];

		$report_service->export_csv( $report_type, $filters );
		exit;
	}

	/**
	 * Handle saving general plugin settings.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to modify settings.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_save_settings', 'tpm_settings_nonce' );

		$user_id = get_current_user_id();
		TPM_Settings_Service::update_settings( $_POST );

		set_transient( 'tpm_settings_notice_' . $user_id, __( 'Settings saved successfully.', 'tender-po-management' ), 60 );

		$current_tab = sanitize_key( $_POST['current_tab'] ?? 'general' );
		wp_safe_redirect( admin_url( 'admin.php?page=tpm-settings&tab=' . $current_tab ) );
		exit;
	}

	/**
	 * Handle adding a new status.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_add_status(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage statuses.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_add_status', 'tpm_status_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Settings_Service::add_status( $_POST );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_settings_error_' . $user_id, $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_settings_notice_' . $user_id, __( 'Status added successfully.', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) );
		exit;
	}

	/**
	 * Handle editing an existing status.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_edit_status(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage statuses.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_edit_status', 'tpm_status_nonce' );

		$user_id   = get_current_user_id();
		$status_id = absint( $_POST['status_id'] ?? 0 );
		$result    = TPM_Settings_Service::update_status( $status_id, $_POST );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_settings_error_' . $user_id, $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_settings_notice_' . $user_id, __( 'Status updated successfully.', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) );
		exit;
	}

	/**
	 * Handle toggling status active state.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_toggle_status(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage statuses.', 'tender-po-management' ) );
		}

		$status_id = absint( $_GET['status_id'] ?? 0 );
		check_admin_referer( 'tpm_toggle_status_' . $status_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		$result  = TPM_Settings_Service::toggle_status_active( $status_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_settings_error_' . $user_id, $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_settings_notice_' . $user_id, __( 'Status status changed successfully.', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) );
		exit;
	}

	/**
	 * Handle deleting a status with in-use safety warning.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_delete_status(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage statuses.', 'tender-po-management' ) );
		}

		$status_id = absint( $_REQUEST['status_id'] ?? 0 );
		check_admin_referer( 'tpm_delete_status_' . $status_id, 'tpm_nonce' );

		$user_id = get_current_user_id();
		$force   = ! empty( $_REQUEST['force'] );
		$result  = TPM_Settings_Service::delete_status( $status_id, $force );

		if ( is_wp_error( $result ) ) {
			set_transient( 'tpm_settings_error_' . $user_id, $result->get_error_message(), 60 );
		} else {
			set_transient( 'tpm_settings_notice_' . $user_id, __( 'Status deleted successfully.', 'tender-po-management' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) );
		exit;
	}

	/**
	 * Handle reordering statuses.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_reorder_statuses(): void {
		if ( ! current_user_can( 'manage_tpm_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage statuses.', 'tender-po-management' ) );
		}

		check_admin_referer( 'tpm_reorder_statuses', 'tpm_nonce' );

		$user_id = get_current_user_id();
		$orders  = isset( $_POST['status_orders'] ) && is_array( $_POST['status_orders'] ) ? $_POST['status_orders'] : array();

		TPM_Settings_Service::reorder_statuses( $orders );
		set_transient( 'tpm_settings_notice_' . $user_id, __( 'Status order updated successfully.', 'tender-po-management' ), 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=tpm-settings&tab=statuses' ) );
		exit;
	}

	/**
	 * Helper to locate and load a view template.
	 *
	 * @since 1.0.0
	 * @param string $view_file File name in admin/views/.
	 * @return void
	 */
	private function load_view( string $view_file ): void {
		$full_path = TPM_PLUGIN_DIR . 'admin/views/' . $view_file;

		if ( file_exists( $full_path ) ) {
			require_once $full_path;
		} else {
			wp_die(
				sprintf(
					/* translators: %s: Missing view file name */
					esc_html__( 'View template file not found: %s', 'tender-po-management' ),
					esc_html( $view_file )
				)
			);
		}
	}
}
