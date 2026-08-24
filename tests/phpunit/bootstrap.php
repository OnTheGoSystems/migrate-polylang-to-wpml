<?php
/**
 * PHPUnit bootstrap.
 *
 * @package MigratePolylangToWPML
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

require_once ABSPATH . 'vendor/autoload.php';
require_once ABSPATH . 'vendor/otgs/unit-tests-framework/phpunit/bootstrap.php';

// Keep the plugin from constructing its admin controller while its class is loaded for tests.
if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Prevents the plugin from creating its admin controller during test bootstrap.
	 *
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}

require_once __DIR__ . '/includes/class-string-storage-wpdb.php';
require_once ABSPATH . 'migrate-polylang-to-wpml.php';
