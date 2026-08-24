<?php
/**
 * WPML APIs supplied by WPML String Translation at runtime.
 *
 * @package MigratePolylangToWPML
 */

if ( ! defined( 'ICL_STRING_TRANSLATION_COMPLETE' ) ) {
	define( 'ICL_STRING_TRANSLATION_COMPLETE', 10 );
}

if ( ! function_exists( 'icl_add_string_translation' ) ) {
	/**
	 * Records a WPML String Translation translation at runtime.
	 *
	 * @param int         $string_id WPML string ID.
	 * @param string      $language  Target language code.
	 * @param string|null $value    Translated value.
	 * @param int|bool    $status    Translation status.
	 *
	 * @return int
	 */
	function icl_add_string_translation( $string_id, $language, $value = null, $status = false ) {
		unset( $string_id, $language, $value, $status );

		return 0;
	}
}
