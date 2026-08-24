<?php
/**
 * WPML SitePress API supplied by WPML core at runtime.
 *
 * @package MigratePolylangToWPML
 */

if ( ! class_exists( 'SitePress' ) ) {
	/**
	 * Partial SitePress definition used by static analysis.
	 */
	class SitePress {

		/**
		 * Creates a translated duplicate of a post.
		 *
		 * @param int    $original_post_id Original post ID.
		 * @param string $language_code    Target language code.
		 */
		public function make_duplicate( $original_post_id, $language_code ) {
			unset( $original_post_id, $language_code );
		}
	}
}
