<?php
/**
 * Fired when the plugin is uninstalled via WordPress admin.
 *
 * @package    Tender_PO_Management
 * @since      1.0.0
 */

// If uninstall not called from WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Safe Uninstall Procedure:
 *
 * In accordance with enterprise best practices and user requirements,
 * user business data (Tenders, Purchase Orders, Customers, Financial Records)
 * is PRESERVED by default upon plugin deletion.
 *
 * An optional setting "Delete all plugin data on uninstall" can be enabled
 * by administrators in the plugin settings screen in future phases.
 */
$tpm_settings = get_option( 'tpm_settings', array() );
$delete_data  = ! empty( $tpm_settings['delete_data_on_uninstall'] );

if ( $delete_data ) {
	global $wpdb;

	// Drop custom database tables if requested.
	$tables = array(
		$wpdb->prefix . 'tpm_records',
		$wpdb->prefix . 'tpm_customers',
		$wpdb->prefix . 'tpm_partner_companies',
		$wpdb->prefix . 'tpm_attachments',
		$wpdb->prefix . 'tpm_payments',
		$wpdb->prefix . 'tpm_activity_logs',
		$wpdb->prefix . 'tpm_statuses',
		$wpdb->prefix . 'tpm_record_items',
		$wpdb->prefix . 'tpm_notes',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Remove registered options.
	delete_option( 'tpm_version' );
	delete_option( 'tpm_db_version' );
	delete_option( 'tpm_settings' );

	// Clean capabilities from administrator.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$capabilities = array(
			'manage_tpm_records',
			'view_tpm_records',
			'create_tpm_records',
			'edit_tpm_records',
			'delete_tpm_records',
			'manage_tpm_settings',
			'manage_tpm_customers',
			'manage_tpm_partners',
			'view_tpm_reports',
			'manage_tpm_archive',
			'manage_tpm_system',
		);
		foreach ( $capabilities as $cap ) {
			$admin->remove_cap( $cap );
		}
	}

	// Remove custom procurement roles.
	remove_role( 'tpm_manager' );
	remove_role( 'tpm_staff' );
	remove_role( 'tpm_viewer' );
}
