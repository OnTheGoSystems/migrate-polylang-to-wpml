<?php

// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn -- PHPStan reads this analysis-only declaration without executing it.
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
}
// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn
