<?php
/**
 * `get_home_path()` lives in wp-admin/includes/file.php, which the subject requires when
 * the function is missing. Defining it here keeps that require out of the test run and
 * lets a test point the home path at a directory of its own.
 */
function get_home_path() {
	return $GLOBALS['mpw_test_home_path'] ?? '/nonexistent/';
}
