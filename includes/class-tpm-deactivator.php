<?php
/**
 * Fired during plugin deactivation.
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
 * Fired during plugin deactivation.
 *
 * Handles transient cleanups and deactivation tasks without deleting any business data.
 *
 * @since 1.0.0
 */
class TPM_Deactivator {

	/**
	 * Run plugin deactivation tasks.
	 *
	 * Note: In compliance with WordPress standards, business data, records,
	 * customers, and settings are strictly PRESERVED during deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate(): void {
		// Clean up any temporary transients.
		delete_transient( 'tpm_system_notices' );

		// Flush rewrite rules to keep system clean.
		flush_rewrite_rules();
	}
}
