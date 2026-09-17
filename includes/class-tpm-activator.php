<?php
/**
 * Fired during plugin activation.
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
 * Fired during plugin activation.
 *
 * Handles version validation, initial options setup, rewrite rule management,
 * and prepares the database installation scaffolding.
 *
 * @since 1.0.0
 */
class TPM_Activator {

	/**
	 * Minimum required PHP version.
	 *
	 * @var string
	 */
	public const MIN_PHP_VERSION = '8.1';

	/**
	 * Minimum required WordPress version.
	 *
	 * @var string
	 */
	public const MIN_WP_VERSION = '6.0';

	/**
	/**
	 * Run plugin activation tasks.
	 *
	 * Supports standard single-site and WordPress Multisite network-wide activation.
	 *
	 * @since 1.0.0
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		// 1. Validate PHP version requirement.
		self::check_php_version();

		// 2. Validate WordPress version requirement.
		self::check_wp_version();

		// 3. Handle Multisite network-wide activation.
		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			if ( function_exists( 'get_sites' ) ) {
				$sites = get_sites( array( 'number' => 0 ) );
				foreach ( $sites as $site ) {
					switch_to_blog( (int) $site->blog_id );
					self::activate_single_site();
					restore_current_blog();
				}
			}
		} else {
			self::activate_single_site();
		}
	}

	/**
	 * Execute activation procedures for a single blog instance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate_single_site(): void {
		// 1. Register or update plugin version option.
		if ( ! get_option( 'tpm_version' ) ) {
			add_option( 'tpm_version', TPM_VERSION );
		} else {
			update_option( 'tpm_version', TPM_VERSION );
		}

		// 2. Prepare database installation scaffold.
		self::prepare_database();

		// 3. Setup default capabilities and custom roles.
		self::setup_capabilities();

		// 4. Conditionally flush rewrite rules only if needed.
		self::flush_rewrite_rules_if_needed();
	}

	/**
	 * Fired when a new site is initialized in a WordPress Multisite network.
	 *
	 * @since 1.0.0
	 * @param int|object $blog_id Blog ID or WP_Site object.
	 * @return void
	 */
	public static function on_new_blog( $blog_id ): void {
		if ( is_object( $blog_id ) && isset( $blog_id->id ) ) {
			$blog_id = (int) $blog_id->id;
		} else {
			$blog_id = (int) $blog_id;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active_for_network( TPM_PLUGIN_BASENAME ) ) {
			switch_to_blog( $blog_id );
			self::activate_single_site();
			restore_current_blog();
		}
	}

	/**
	 * Ensure the server environment meets the minimum PHP version.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function check_php_version(): void {
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			deactivate_plugins( TPM_PLUGIN_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: Required PHP version, 2: Current PHP version */
					esc_html__( 'Tender & PO Management System requires PHP version %1$s or higher. Your server is running PHP %2$s. Plugin activation aborted.', 'tender-po-management' ),
					esc_html( self::MIN_PHP_VERSION ),
					esc_html( PHP_VERSION )
				),
				esc_html__( 'Plugin Activation Error', 'tender-po-management' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Ensure the WordPress install meets the minimum WP version.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function check_wp_version(): void {
		global $wp_version;

		if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			deactivate_plugins( TPM_PLUGIN_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: Required WP version, 2: Current WP version */
					esc_html__( 'Tender & PO Management System requires WordPress version %1$s or higher. Your site is running WordPress %2$s.', 'tender-po-management' ),
					esc_html( self::MIN_WP_VERSION ),
					esc_html( $wp_version )
				),
				esc_html__( 'Plugin Activation Error', 'tender-po-management' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Scaffolding for database installation.
	 *
	 * Prepares database schema tracking without creating unneeded tables yet.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function prepare_database(): void {
		TPM_Database::install();
	}

	/**
	 * Setup administrator default capabilities and optional procurement roles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function setup_capabilities(): void {
		require_once TPM_PLUGIN_DIR . 'includes/services/class-tpm-roles-service.php';
		TPM_Roles_Service::setup_roles_and_capabilities();
	}

	/**
	 * Conditionally flush rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function flush_rewrite_rules_if_needed(): void {
		// Only flush rewrite rules if custom query endpoints or rewrite tags are registered.
		// For admin-only plugins, avoiding unconditional flushing preserves server performance.
		if ( get_option( 'tpm_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'tpm_flush_rewrite_rules' );
		}
	}
}
