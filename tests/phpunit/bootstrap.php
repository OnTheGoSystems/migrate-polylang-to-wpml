<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'ICL_SITEPRESS_VERSION', 'test' );

require_once ABSPATH . 'vendor/autoload.php';
require_once ABSPATH . 'vendor/otgs/unit-tests-framework/phpunit/bootstrap.php';
require_once __DIR__ . '/includes/class-language-mapping-wpdb.php';
require_once __DIR__ . '/includes/class-string-storage-wpdb.php';
require_once __DIR__ . '/includes/class-polylang-data-wpdb.php';
require_once ABSPATH . 'classes/class-mpw_polylang_data.php';
require_once __DIR__ . '/includes/wp-admin-file-functions.php';
require_once ABSPATH . 'classes/class-mpw_htaccess_check.php';
require_once ABSPATH . 'classes/class-mpw_polylang_string_storage.php';
