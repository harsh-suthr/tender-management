<?php
/**
 * Service: User Roles & Permissions
 *
 * Defines and registers capability-based access control, custom user roles
 * (Tender Manager, Tender Staff, Tender Viewer), and provides centralized
 * permission helpers.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/services
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TPM_Roles_Service
 *
 * Manages plugin roles and capabilities across the procurement system.
 *
 * @since 1.0.0
 */
class TPM_Roles_Service {

	/**
	 * Role slugs.
	 */
	public const ROLE_MANAGER = 'tpm_manager';
	public const ROLE_STAFF   = 'tpm_staff';
	public const ROLE_VIEWER  = 'tpm_viewer';

	/**
	 * Capability identifiers.
	 */
	public const CAP_MANAGE_RECORDS   = 'manage_tpm_records';
	public const CAP_VIEW_RECORDS     = 'view_tpm_records';
	public const CAP_CREATE_RECORDS   = 'create_tpm_records';
	public const CAP_EDIT_RECORDS     = 'edit_tpm_records';
	public const CAP_DELETE_RECORDS   = 'delete_tpm_records';
	public const CAP_MANAGE_CUSTOMERS = 'manage_tpm_customers';
	public const CAP_MANAGE_PARTNERS  = 'manage_tpm_partners';
	public const CAP_VIEW_REPORTS     = 'view_tpm_reports';
	public const CAP_MANAGE_SETTINGS  = 'manage_tpm_settings';
	public const CAP_MANAGE_ARCHIVE   = 'manage_tpm_archive';

	/**
	 * Retrieve list of all custom capabilities defined by the plugin.
	 *
	 * @since  1.0.0
	 * @return string[] Array of capability slugs.
	 */
	public static function get_all_capabilities(): array {
		return array(
			self::CAP_MANAGE_RECORDS,
			self::CAP_VIEW_RECORDS,
			self::CAP_CREATE_RECORDS,
			self::CAP_EDIT_RECORDS,
			self::CAP_DELETE_RECORDS,
			self::CAP_MANAGE_CUSTOMERS,
			self::CAP_MANAGE_PARTNERS,
			self::CAP_VIEW_REPORTS,
			self::CAP_MANAGE_SETTINGS,
			self::CAP_MANAGE_ARCHIVE,
		);
	}

	/**
	 * Retrieve role definitions with their display names and assigned capabilities.
	 *
	 * @since  1.0.0
	 * @return array<string, array{name: string, capabilities: array<string, bool>}>
	 */
	public static function get_role_definitions(): array {
		return array(
			self::ROLE_MANAGER => array(
				'name'         => __( 'Tender Manager', 'tender-po-management' ),
				'capabilities' => array(
					'read'                    => true,
					self::CAP_MANAGE_RECORDS   => true,
					self::CAP_VIEW_RECORDS     => true,
					self::CAP_CREATE_RECORDS   => true,
					self::CAP_EDIT_RECORDS     => true,
					self::CAP_DELETE_RECORDS   => true,
					self::CAP_MANAGE_CUSTOMERS => true,
					self::CAP_MANAGE_PARTNERS  => true,
					self::CAP_VIEW_REPORTS     => true,
					self::CAP_MANAGE_ARCHIVE   => true,
					'upload_files'            => true,
					// Explicitly lacks manage_tpm_settings.
				),
			),
			self::ROLE_STAFF   => array(
				'name'         => __( 'Tender Staff', 'tender-po-management' ),
				'capabilities' => array(
					'read'                  => true,
					self::CAP_VIEW_RECORDS   => true,
					self::CAP_CREATE_RECORDS => true,
					self::CAP_EDIT_RECORDS   => true,
					'upload_files'          => true,
					// Explicitly lacks manage_tpm_records, delete, customers, partners, reports, archive, settings.
				),
			),
			self::ROLE_VIEWER  => array(
				'name'         => __( 'Tender Viewer', 'tender-po-management' ),
				'capabilities' => array(
					'read'                => true,
					self::CAP_VIEW_RECORDS => true,
					// Explicitly lacks create, edit, delete, customers, partners, reports, archive, settings, upload.
				),
			),
		);
	}

	/**
	 * Register custom roles and grant capabilities to administrator.
	 *
	 * Idempotent: can be safely executed on plugin activation or role sync.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function setup_roles_and_capabilities(): void {
		// 1. Grant all 10 capabilities to WordPress administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_capabilities() as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}

		// 2. Register / synchronize custom procurement roles.
		$definitions = self::get_role_definitions();
		foreach ( $definitions as $role_slug => $role_data ) {
			$existing_role = get_role( $role_slug );

			if ( ! $existing_role ) {
				add_role( $role_slug, $role_data['name'], $role_data['capabilities'] );
			} else {
				// Ensure capabilities match specifications.
				foreach ( $role_data['capabilities'] as $cap => $grant ) {
					if ( $grant ) {
						$existing_role->add_cap( $cap );
					} else {
						$existing_role->remove_cap( $cap );
					}
				}
			}
		}
	}

	/**
	 * Remove custom roles and revoke capabilities from administrator.
	 *
	 * Used during full plugin uninstallation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function remove_roles_and_capabilities(): void {
		// 1. Revoke capabilities from administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_capabilities() as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}

		// 2. Remove custom roles.
		remove_role( self::ROLE_MANAGER );
		remove_role( self::ROLE_STAFF );
		remove_role( self::ROLE_VIEWER );
	}
}
