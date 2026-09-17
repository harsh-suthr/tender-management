<?php
/**
 * Plugin Name:       Tender & PO Management System
 * Plugin URI:        https://example.com/tender-po-management
 * Description:       A complete Tender and Purchase Order management system built natively for the WordPress wp-admin backend.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Enterprise Systems
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tender-po-management
 * Domain Path:       /languages
 *
 * @package           Tender_PO_Management
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currently active plugin and database versions.
 */
define( 'TPM_VERSION', '1.0.0' );
define( 'TPM_DB_VERSION', '1.0.0' );

/**
 * File, directory, and URL constants.
 */
define( 'TPM_PLUGIN_FILE', __FILE__ );
define( 'TPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Require core database and lifecycle classes.
 */
require_once TPM_PLUGIN_DIR . 'includes/database/class-tpm-database.php';
require_once TPM_PLUGIN_DIR . 'includes/class-tpm-activator.php';
require_once TPM_PLUGIN_DIR . 'includes/class-tpm-deactivator.php';
require_once TPM_PLUGIN_DIR . 'includes/class-tpm-loader.php';
require_once TPM_PLUGIN_DIR . 'includes/class-tpm-plugin.php';

/**
 * Register activation and deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'TPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TPM_Deactivator', 'deactivate' ) );

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function run_tender_po_management(): void {
	$plugin = new TPM_Plugin();
	$plugin->run();
}

run_tender_po_management();
