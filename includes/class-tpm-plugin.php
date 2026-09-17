<?php
/**
 * The core plugin class.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * Used to define internationalization, admin-specific hooks, and loader coordination.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes
 * @since      1.0.0
 */
class TPM_Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since  1.0.0
	 * @var    TPM_Loader
	 */
	protected TPM_Loader $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected string $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected string $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'tender-po-management';
		$this->version     = TPM_VERSION;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function load_dependencies(): void {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		$this->loader = new TPM_Loader();

		/**
		 * The business logic service for creating and managing records.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-record-service.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/admin/class-tpm-admin.php';

		/**
		 * WP_List_Table implementation for managing records.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/admin/class-tpm-records-list-table.php';

		/**
		 * Customer service and list table.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-customer-service.php';
		require_once TPM_PLUGIN_DIR . 'includes/admin/class-tpm-customers-list-table.php';

		/**
		 * Partner company service and list table.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-partner-service.php';
		require_once TPM_PLUGIN_DIR . 'includes/admin/class-tpm-partners-list-table.php';

		/**
		 * Payment service.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-payment-service.php';

		/**
		 * Dashboard management service.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-dashboard-service.php';

		/**
		 * Reporting service.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-report-service.php';

		/**
		 * Settings and status management service.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-settings-service.php';

		/**
		 * Roles and permissions service.
		 */
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-roles-service.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function set_locale(): void {
		$this->loader->add_action(
			'init',
			$this,
			'load_textdomain'
		);
		$this->loader->add_action(
			'init',
			$this,
			'check_database_migrations'
		);
	}

	/**
	 * Run database migrations if version mismatch detected.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_database_migrations(): void {
		TPM_Database::migrate();
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'tender-po-management',
			false,
			dirname( TPM_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$plugin_admin = new TPM_Admin( $this->get_plugin_name(), $this->get_version() );

		// Register styles and scripts.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Register menus.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menus' );

		// Form submission and record actions.
		$this->loader->add_action( 'admin_post_tpm_save_record', $plugin_admin, 'handle_save_record' );
		$this->loader->add_action( 'admin_post_tpm_archive_record', $plugin_admin, 'handle_archive_record' );
		$this->loader->add_action( 'admin_post_tpm_unarchive_record', $plugin_admin, 'handle_unarchive_record' );
		$this->loader->add_action( 'admin_post_tpm_delete_record', $plugin_admin, 'handle_delete_record' );
		$this->loader->add_action( 'admin_post_tpm_update_notes', $plugin_admin, 'handle_update_notes' );
		$this->loader->add_action( 'admin_post_tpm_add_attachments', $plugin_admin, 'handle_add_attachments' );
		$this->loader->add_action( 'admin_post_tpm_delete_attachment', $plugin_admin, 'handle_delete_attachment' );

		// Phase 7: List table bulk and row actions.
		$this->loader->add_action( 'admin_post_tpm_bulk_records', $plugin_admin, 'handle_bulk_records' );
		$this->loader->add_action( 'admin_post_tpm_quick_archive', $plugin_admin, 'handle_quick_archive' );
		$this->loader->add_action( 'admin_post_tpm_quick_restore', $plugin_admin, 'handle_quick_restore' );
		$this->loader->add_action( 'admin_post_tpm_quick_delete', $plugin_admin, 'handle_quick_delete' );

		// Phase 8: Edit record.
		$this->loader->add_action( 'admin_post_tpm_update_record', $plugin_admin, 'handle_update_record' );

		// Phase 9: Customer management actions.
		$this->loader->add_action( 'admin_post_tpm_save_customer', $plugin_admin, 'handle_save_customer' );
		$this->loader->add_action( 'admin_post_tpm_update_customer', $plugin_admin, 'handle_update_customer' );
		$this->loader->add_action( 'admin_post_tpm_delete_customer', $plugin_admin, 'handle_delete_customer' );
		$this->loader->add_action( 'admin_post_tpm_deactivate_customer', $plugin_admin, 'handle_deactivate_customer' );
		$this->loader->add_action( 'admin_post_tpm_reassign_customer', $plugin_admin, 'handle_reassign_customer' );

		// Phase 10: Partner company management actions.
		$this->loader->add_action( 'admin_post_tpm_save_partner', $plugin_admin, 'handle_save_partner' );
		$this->loader->add_action( 'admin_post_tpm_update_partner', $plugin_admin, 'handle_update_partner' );
		$this->loader->add_action( 'admin_post_tpm_delete_partner', $plugin_admin, 'handle_delete_partner' );
		$this->loader->add_action( 'admin_post_tpm_deactivate_partner', $plugin_admin, 'handle_deactivate_partner' );
		$this->loader->add_action( 'admin_post_tpm_reassign_partner', $plugin_admin, 'handle_reassign_partner' );

		// Phase 11: Payment tracking actions.
		$this->loader->add_action( 'admin_post_tpm_add_payment', $plugin_admin, 'handle_add_payment' );
		$this->loader->add_action( 'admin_post_tpm_update_payment', $plugin_admin, 'handle_update_payment' );
		$this->loader->add_action( 'admin_post_tpm_delete_payment', $plugin_admin, 'handle_delete_payment' );

		// Phase 12: Archive management & permanent deletion actions.
		$this->loader->add_action( 'admin_post_tpm_permanent_delete', $plugin_admin, 'handle_permanent_delete_record' );

		// Phase 14: Reporting system data exports.
		$this->loader->add_action( 'admin_post_tpm_export_report', $plugin_admin, 'handle_export_report' );

		// Phase 15: Settings and Status management actions.
		$this->loader->add_action( 'admin_post_tpm_save_settings', $plugin_admin, 'handle_save_settings' );
		$this->loader->add_action( 'admin_post_tpm_add_status', $plugin_admin, 'handle_add_status' );
		$this->loader->add_action( 'admin_post_tpm_edit_status', $plugin_admin, 'handle_edit_status' );
		$this->loader->add_action( 'admin_post_tpm_toggle_status', $plugin_admin, 'handle_toggle_status' );
		$this->loader->add_action( 'admin_post_tpm_delete_status', $plugin_admin, 'handle_delete_status' );
		$this->loader->add_action( 'admin_post_tpm_reorder_statuses', $plugin_admin, 'handle_reorder_statuses' );
	}

	/**
	 * Register all of the hooks related to public-facing functionality.
	 *
	 * IMPORTANT: This plugin is intended strictly for the WordPress wp-admin backend.
	 * No public frontend functionality, dashboards, or auth forms are registered here.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function define_public_hooks(): void {
		// Backend-only application: Public hooks are deliberately empty.
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since  1.0.0
	 * @return TPM_Loader
	 */
	public function get_loader(): TPM_Loader {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}
}
