<?php

/*
Plugin Name: Migrate Polylang to WPML
Description: Import multilingual data from Polylang to WPML | <a href="https://wpml.org/documentation/migrate-polylang-wpml/">Documentation</a>
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com/
Plugin uri: https://wpml.org
Version: 5.0.0
 */

defined('ABSPATH') || exit;

define('MPW_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/classes/class-migrate-polylang-to-wpml.php';

// Everything this plugin does lives in wp-admin or admin-ajax.php, both of which satisfy
// is_admin(). Loading it on front-end requests only cost two file includes and an init hook.
if (is_admin()) {
	$migrate_polylang_to_wpml = new Migrate_Polylang_To_WPML();
}
