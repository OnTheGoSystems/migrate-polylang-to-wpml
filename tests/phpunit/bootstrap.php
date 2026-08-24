<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

require_once ABSPATH . 'vendor/autoload.php';
require_once ABSPATH . 'vendor/otgs/unit-tests-framework/phpunit/bootstrap.php';
require_once __DIR__ . '/includes/class-string-storage-wpdb.php';
require_once ABSPATH . 'classes/class-mpw_polylang_data.php';
require_once ABSPATH . 'classes/class-mpw_polylang_string_storage.php';
