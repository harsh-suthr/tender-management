<?php
/**
 * Automated Verification & Test Suite for Phase 1.
 *
 * Verifies plugin architecture, activator, deactivator, loader, plugin coordinator,
 * admin menus, scripts, constants, and security guards.
 *
 * Can be run via CLI: php tests/test-phase-1.php
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/tests
 * @since      1.0.0
 */

// 1. Setup Mock Environment for standalone CLI execution.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['wp_options'] = array();
$GLOBALS['wp_actions'] = array();
$GLOBALS['wp_filters'] = array();
$GLOBALS['wp_roles']   = array();
$GLOBALS['wp_version'] = '6.4.3';

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_actions'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $accepted_args,
		);
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_filters'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $accepted_args,
		);
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		$GLOBALS['activation_hook'] = $callback;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		$GLOBALS['deactivation_hook'] = $callback;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), "/\\" ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['wp_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value ) {
		if ( ! isset( $GLOBALS['wp_options'][ $name ] ) ) {
			$GLOBALS['wp_options'][ $name ] = $value;
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['wp_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['wp_options'][ $name ] );
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $name ) {
		return true;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules() {
		$GLOBALS['flush_rewrite_rules_called'] = true;
	}
}

if ( ! function_exists( 'get_role' ) ) {
	class MockWP_Role {
		public array $caps = array();
		public function add_cap( $cap ) {
			$this->caps[ $cap ] = true;
		}
		public function remove_cap( $cap ) {
			unset( $this->caps[ $cap ] );
		}
	}
	function get_role( $role ) {
		if ( ! isset( $GLOBALS['wp_roles'][ $role ] ) ) {
			$GLOBALS['wp_roles'][ $role ] = new MockWP_Role();
		}
		return $GLOBALS['wp_roles'][ $role ];
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'mock_nonce_12345';
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['enqueued_styles'][ $handle ] = compact( 'handle', 'src', 'deps', 'ver', 'media' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['enqueued_scripts'][ $handle ] = compact( 'handle', 'src', 'deps', 'ver', 'in_footer' );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['localized_scripts'][ $handle ] = compact( 'handle', 'name', 'data' );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
		$hook = 'toplevel_page_' . $menu_slug;
		$GLOBALS['admin_menus'][ $menu_slug ] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
		return $hook;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		$hook = $parent_slug . '_page_' . $menu_slug;
		$GLOBALS['admin_submenus'][ $menu_slug ] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
		return $hook;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return '6.4.3';
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		$GLOBALS['loaded_textdomain'] = $domain;
		return true;
	}
}

// ----------------------------------------------------
// Test Execution
// ----------------------------------------------------
echo "====================================================\n";
echo " TENDER & PO MANAGEMENT SYSTEM - PHASE 1 TEST SUITE\n";
echo "====================================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function assert_test( string $description, bool $condition ): void {
	global $tests_passed, $tests_failed;
	if ( $condition ) {
		echo " [PASS] " . $description . "\n";
		$tests_passed++;
	} else {
		echo " [FAIL] " . $description . "\n";
		$tests_failed++;
	}
}

// 1. Test Main Plugin File Loading & Constants
require_once dirname( __DIR__ ) . '/tender-po-management.php';

assert_test( 'Constant TPM_VERSION is defined as 1.0.0', defined( 'TPM_VERSION' ) && '1.0.0' === TPM_VERSION );
assert_test( 'Constant TPM_PLUGIN_FILE is defined', defined( 'TPM_PLUGIN_FILE' ) );
assert_test( 'Constant TPM_PLUGIN_DIR is defined', defined( 'TPM_PLUGIN_DIR' ) && is_dir( TPM_PLUGIN_DIR ) );
assert_test( 'Constant TPM_PLUGIN_URL is defined', defined( 'TPM_PLUGIN_URL' ) );
assert_test( 'Constant TPM_PLUGIN_BASENAME is defined', defined( 'TPM_PLUGIN_BASENAME' ) );

// 2. Test Activation Hook & TPM_Activator
assert_test( 'Activation hook registered to TPM_Activator::activate', isset( $GLOBALS['activation_hook'] ) && $GLOBALS['activation_hook'] === array( 'TPM_Activator', 'activate' ) );

TPM_Activator::activate();
assert_test( 'TPM_Activator sets tpm_version option', get_option( 'tpm_version' ) === '1.0.0' );
assert_test( 'TPM_Activator sets tpm_db_version option scaffold', get_option( 'tpm_db_version' ) === '1.0.0' );

$admin_role = get_role( 'administrator' );
assert_test( 'TPM_Activator provisions capabilities to administrator', isset( $admin_role->caps['view_tpm_records'] ) && isset( $admin_role->caps['manage_tpm_system'] ) );

// 3. Test Deactivation Hook & TPM_Deactivator
assert_test( 'Deactivation hook registered to TPM_Deactivator::deactivate', isset( $GLOBALS['deactivation_hook'] ) && $GLOBALS['deactivation_hook'] === array( 'TPM_Deactivator', 'deactivate' ) );

TPM_Deactivator::deactivate();
assert_test( 'TPM_Deactivator does NOT delete user data options', get_option( 'tpm_version' ) === '1.0.0' );

// 4. Test TPM_Loader
$loader = new TPM_Loader();
$test_obj = new stdClass();
$loader->add_action( 'test_action', $test_obj, 'dummy_method', 15, 2 );
$loader->add_filter( 'test_filter', $test_obj, 'dummy_filter', 20, 1 );
$loader->run();

assert_test( 'TPM_Loader registers actions with priority', isset( $GLOBALS['wp_actions']['test_action'] ) && $GLOBALS['wp_actions']['test_action'][0]['priority'] === 15 );
assert_test( 'TPM_Loader registers filters with priority', isset( $GLOBALS['wp_filters']['test_filter'] ) && $GLOBALS['wp_filters']['test_filter'][0]['priority'] === 20 );

// 5. Test TPM_Admin Menu Registration & Enqueues
$admin = new TPM_Admin( 'tender-po-management', '1.0.0' );
$admin->add_admin_menus();

assert_test( 'Top-level admin menu registered (tender-po-management)', isset( $GLOBALS['admin_menus']['tender-po-management'] ) );
assert_test( 'Submenu Dashboard registered', isset( $GLOBALS['admin_submenus']['tender-po-management'] ) );
assert_test( 'Submenu All Records registered', isset( $GLOBALS['admin_submenus']['tpm-records'] ) );
assert_test( 'Submenu Tenders registered', isset( $GLOBALS['admin_submenus']['tpm-tenders'] ) );
assert_test( 'Submenu Purchase Orders registered', isset( $GLOBALS['admin_submenus']['tpm-pos'] ) );
assert_test( 'Submenu Add New registered', isset( $GLOBALS['admin_submenus']['tpm-new-record'] ) );
assert_test( 'Submenu Customers & Partners registered', isset( $GLOBALS['admin_submenus']['tpm-customers'] ) );
assert_test( 'Submenu Settings registered', isset( $GLOBALS['admin_submenus']['tpm-settings'] ) );

// 6. Test Screen Scoping for Styles & Scripts
$tpm_hook = 'toplevel_page_tender-po-management';
$unrelated_hook = 'edit.php';

$admin->enqueue_styles( $unrelated_hook );
assert_test( 'Styles are NOT enqueued on unrelated WordPress screens', ! isset( $GLOBALS['enqueued_styles']['tender-po-management-admin'] ) );

$admin->enqueue_styles( $tpm_hook );
assert_test( 'Styles ARE enqueued on TPM admin screens', isset( $GLOBALS['enqueued_styles']['tender-po-management-admin'] ) );

$admin->enqueue_scripts( $tpm_hook );
assert_test( 'Scripts ARE enqueued on TPM admin screens', isset( $GLOBALS['enqueued_scripts']['tender-po-management-admin'] ) );
assert_test( 'Admin script localizes security nonce (tpm_admin_nonce)', isset( $GLOBALS['localized_scripts']['tender-po-management-admin']['data']['nonce'] ) );

// 7. Verify Directory Scaffolding
$required_dirs = array(
	'includes/admin',
	'includes/database',
	'includes/models',
	'includes/services',
	'includes/helpers',
	'admin/css',
	'admin/js',
	'admin/views',
	'public/css',
	'public/js',
	'languages',
);

foreach ( $required_dirs as $dir ) {
	$path = TPM_PLUGIN_DIR . $dir;
	assert_test( "Directory exists: {$dir}", is_dir( $path ) );
}

// 8. Verify readme.txt and uninstall.php
assert_test( 'Standard readme.txt exists', file_exists( TPM_PLUGIN_DIR . 'readme.txt' ) );
assert_test( 'uninstall.php exists', file_exists( TPM_PLUGIN_DIR . 'uninstall.php' ) );

echo "\n----------------------------------------------------\n";
echo " TEST SUMMARY: {$tests_passed} Passed, {$tests_failed} Failed\n";
echo "----------------------------------------------------\n";

if ( $tests_failed > 0 ) {
	exit( 1 );
}
exit( 0 );
