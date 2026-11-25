<?php
/**
 * Stands in for WPML String Translation's icl_add_string_translation(), which the
 * strings step only calls when the function exists.
 *
 * Every call is kept in $GLOBALS['mpw_test_string_translations']. A string ID listed in
 * $GLOBALS['mpw_test_refused_strings'] is refused the way WPML refuses one, with false.
 */
function icl_add_string_translation( $string_id, $language, $value ) {
	$GLOBALS['mpw_test_string_translations'][] = array( $string_id, $language, $value );

	return in_array( $string_id, $GLOBALS['mpw_test_refused_strings'] ?? array(), true ) ? false : 1;
}
