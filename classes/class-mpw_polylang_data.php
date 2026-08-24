<?php
/**
 *
 * @author konrad
 */

defined('ABSPATH') || exit;

class mpw_polylang_data {

	private static $terms;

	/**
	 * Resolved Polylang slug => WPML language code, for the life of the request.
	 *
	 * @var array
	 */
	private $wpml_code_cache = array();

	/**
	 * Polylang slug => locale, for languages that resolved to a code WPML does not know.
	 *
	 * @var array
	 */
	private $unmapped_languages = array();

	public function __construct() {

	}
	
	public function get_languages() {
		return $this->get_terms('language');
	}
	
	public function get_term_languages() {
		return $this->get_terms('term_language');
	}
	
	public function get_post_translations() {
		return $this->get_terms('post_translations');
	}
	
	public function get_term_translations() {
		return $this->get_terms('term_translations');
	}
	
	private function get_terms($tax) {

		if (!isset(self::$terms[$tax])) {
			global $wpdb;

			register_taxonomy($tax, null);
			$table = $wpdb->prefix . "icl_translations";
			$wpdb->delete($table, array('element_type' => 'tax_'.$tax));

			// The two-argument form of get_terms() has been deprecated since WordPress 4.5.
			$terms = get_terms(array(
				'taxonomy' => $tax,
				'hide_empty' => false
			));

			// get_terms() returns a WP_Error when the taxonomy is unknown. Callers all iterate
			// the result, so normalise it to an array here rather than in each of them.
			self::$terms[$tax] = is_array($terms) ? $terms : array();
		}

		return self::$terms[$tax];
	}
	
	public function get_additional_languages_names() {
		$pll_languages = $this->get_languages();
		$default_language_slug = $this->get_default_language_slug();

		$additional_languages = array();

		foreach ($pll_languages as $language) {
			if (isset($language->slug, $language->name) && $language->slug !== $default_language_slug) {
				$additional_languages[] = $language->name;
			}
		}

		return $additional_languages;
	}

	/**
	 * @return string Polylang's default language slug, or an empty string when Polylang's option
	 *                is missing or has been mangled.
	 */
	public function get_default_language_slug() {
		$polylang_option = get_option('polylang');

		if (!is_array($polylang_option) || !isset($polylang_option['default_lang']) || !is_scalar($polylang_option['default_lang'])) {
			return '';
		}

		return (string) $polylang_option['default_lang'];
	}

	/**
	 * @return string The default language's display name, or an empty string when it cannot be
	 *                resolved. Never null — callers pass this straight into sprintf().
	 */
	public function get_default_language_name() {
		$pll_languages = $this->get_languages();
		$default_language_slug = $this->get_default_language_slug();

		foreach ($pll_languages as $language) {
			if (isset($language->slug, $language->name) && $language->slug === $default_language_slug) {
				return $language->name;
			}
		}

		return '';
	}

	public function get_languages_map() {
		$polylang_languages = $this->get_languages();

		$polylang_languages_map = array();

		foreach ($polylang_languages as $language) {
			if (isset($language->term_id, $language->slug)) {
				$polylang_languages_map[$language->term_id] = $language->slug;
			}
		}

		return $polylang_languages_map;
	}
	
	/**
	 * Translates a Polylang language slug into the WPML language code.
	 *
	 * Both Polylang slugs and WPML codes can be customised independently, so the locale is used
	 * as the bridge between them.
	 *
	 * @param mixed $slug
	 *
	 * @return string A WPML language code, or an empty string for unusable input.
	 */
	public function lang_slug_to_wpml_format($slug) {

		if (!is_scalar($slug)) {
			return '';
		}

		$slug = (string) $slug;

		if ('' === $slug) {
			return '';
		}

		if (!array_key_exists($slug, $this->wpml_code_cache)) {
			$this->wpml_code_cache[$slug] = $this->resolve_wpml_code($slug);
		}

		return $this->wpml_code_cache[$slug];
	}

	/**
	 * Languages whose locale could not be mapped unambiguously to a WPML language.
	 *
	 * A Polylang slug and a WPML code may happen to be equal, but that is not evidence that they
	 * represent the same language. Callers receive an empty code for these entries so they do not
	 * write an unsupported or incorrect language into WPML.
	 *
	 * @return array Polylang slug => locale (empty string when Polylang had no locale either).
	 */
	public function get_unmapped_languages() {
		return $this->unmapped_languages;
	}

	/**
	 * @param string $slug
	 *
	 * @return string
	 */
	private function resolve_wpml_code($slug) {
		$locale = $this->get_locale_for_slug($slug);

		if ('' !== $locale) {
			$code = $this->wpml_code_for_locale($locale);

			if ('' !== $code) {
				return $code;
			}
		}

		$this->unmapped_languages[$slug] = $locale;

		return '';
	}

	/**
	 * @param string $slug
	 *
	 * @return string The locale Polylang recorded for this language, or an empty string.
	 */
	private function get_locale_for_slug($slug) {
		foreach ($this->get_languages() as $language) {
			if (!isset($language->slug) || $language->slug !== $slug) {
				continue;
			}

			if (!isset($language->description)) {
				return '';
			}

			$details = maybe_unserialize($language->description);

			if (is_array($details) && isset($details['locale']) && is_string($details['locale'])) {
				return $details['locale'];
			}

			return '';
		}

		return '';
	}

	/**
	 * @param string $locale
	 *
	 * @return string The WPML code for this locale, or an empty string.
	 */
	private function wpml_code_for_locale($locale) {
		$languages = apply_filters('wpml_active_languages', null);

		if (!is_array($languages)) {
			return '';
		}

		$codes = array();

		foreach ($languages as $language) {
			if (
				is_array($language)
				&& isset($language['language_code'], $language['default_locale'])
				&& is_string($language['language_code'])
				&& $locale === $language['default_locale']
			) {
				$codes[] = $language['language_code'];
			}
		}

		return 1 === count($codes) ? $codes[0] : '';
	}
	
	
	public function delete_data() {
		update_option('mpw_polylang_data_deleted', 1);
		$this->delete_options();
		$this->delete_posts();
		$this->delete_taxonomies();
	}
	
	private function delete_options() {
		delete_option('polylang');
		delete_option('polylang_wpml_strings');
		delete_option('polylang_widget');
	}
	
	private function delete_posts() {
		$posts = get_posts(array(
			'posts_per_page' => -1,
			'post_type' => 'polylang_mo', 
			'post_status' => 'any'
		));
		
		if ($posts) {
			foreach ($posts as $post) {
				wp_delete_post($post->ID, true);
			}
		}
	}
	
	private function delete_taxonomies() {
		$this->delete_tax('language');
		$this->delete_tax('term_language');
		$this->delete_tax('post_translations');
		$this->delete_tax('term_translations');
	}
	
	private function delete_tax($tax) {
		$method_name = "get_";
		$method_name .= $tax;
		if ($tax == "language" || $tax == "term_language") {
			$method_name .= "s";
		}
		
		$terms = $this->{$method_name}();

		if (!empty($terms)) {
			foreach ($terms as $term) {
				if (isset($term->term_id)) {
					wp_delete_term($term->term_id, $tax);
				}
			}
		}

		// The static cache still holds the terms that were just deleted.
		unset(self::$terms[$tax]);
	}


}
