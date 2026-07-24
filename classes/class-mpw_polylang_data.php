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
	 * A Polylang slug is whatever the site owner typed when they created the language. A WPML code
	 * comes from a fixed list of about 250. The dependable bridge between them is the locale:
	 * Polylang keeps it in the `language` term's serialised description, and WPML keeps it in
	 * `icl_languages.default_locale`, with per-site overrides in `icl_locale_map`.
	 *
	 * WPML resolves a code to a locale in WPML_Locale::get_all_locales() by preferring the override
	 * and falling back to the default. This runs that same lookup backwards.
	 *
	 * Order of resolution:
	 *   1. the locale from Polylang, matched against WPML's own tables
	 *   2. the historical pt/zh special cases, if WPML's tables have nothing to say
	 *   3. the slug unchanged, which is already correct wherever the two systems agree
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
	 * Languages whose slug reached WPML unmapped and which WPML does not recognise.
	 *
	 * Writing one of these into icl_translations is not an error WPML will report — it stores the
	 * code verbatim — so the migration reports them instead.
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

		$legacy_code = $this->legacy_code_for_slug($slug, $locale);

		if ('' !== $legacy_code) {
			return $legacy_code;
		}

		if (!$this->is_known_wpml_code($slug)) {
			$this->unmapped_languages[$slug] = $locale;
		}

		return $slug;
	}

	/**
	 * The mapping this plugin shipped before WPML's tables were consulted.
	 *
	 * Kept as a fallback for sites where the WPML tables cannot answer. The Chinese case is
	 * corrected here: the old code sent every `zh` slug to `zh-hans`, so a Traditional Chinese
	 * site was migrated as Simplified.
	 *
	 * @param string $slug
	 * @param string $locale
	 *
	 * @return string Empty string when this slug has no special case.
	 */
	private function legacy_code_for_slug($slug, $locale) {
		if ('pt' === $slug) {
			return 'pt_BR' === $locale ? 'pt-br' : 'pt-pt';
		}

		if ('zh' === $slug) {
			return in_array($locale, array('zh_TW', 'zh_HK', 'zh_MO'), true) ? 'zh-hant' : 'zh-hans';
		}

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
		global $wpdb;

		if (!$this->wpml_tables_available()) {
			return '';
		}

		// ORDER BY code so a site that has hand-added a second icl_locale_map row for one locale
		// still resolves to a single, deterministic code rather than whatever the engine returns first.
		$code = $wpdb->get_var($wpdb->prepare(
			"SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE locale = %s ORDER BY code LIMIT 1",
			$locale
		));

		if (!$code) {
			$code = $wpdb->get_var($wpdb->prepare(
				"SELECT code FROM {$wpdb->prefix}icl_languages WHERE default_locale = %s ORDER BY code LIMIT 1",
				$locale
			));
		}

		return is_string($code) ? $code : '';
	}

	/**
	 * @param string $code
	 *
	 * @return bool
	 */
	private function is_known_wpml_code($code) {
		global $wpdb;

		if (!$this->wpml_tables_available()) {
			// Without WPML there is nothing to check against, so don't claim the code is wrong.
			return true;
		}

		return (bool) $wpdb->get_var($wpdb->prepare(
			"SELECT code FROM {$wpdb->prefix}icl_languages WHERE code = %s LIMIT 1",
			$code
		));
	}

	/**
	 * @return bool
	 */
	private function wpml_tables_available() {
		return defined('ICL_SITEPRESS_VERSION');
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
