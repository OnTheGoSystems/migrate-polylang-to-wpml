<?php
/**
 *
 * @author konrad
 */

defined('ABSPATH') || exit;

class mpw_polylang_data {
	
	private static $terms;
	
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
	
	public function lang_slug_to_wpml_format($slug) {

		if (!is_scalar($slug)) {
			return '';
		}

		$slug = (string) $slug;

		$different = array(
			'pt' => 'pt-pt',
			'zh' => 'zh-hans'
		);

		$languages = $this->get_languages();

		foreach ($languages as $language) {
			if (isset($language->slug, $language->description) && $language->slug === 'pt') {
				$pt_language_details = maybe_unserialize($language->description);
				if (is_array($pt_language_details) && isset($pt_language_details['locale']) && $pt_language_details['locale'] === "pt_BR") {
					$different['pt'] = 'pt-br';
				}
			}
		}
		if (isset($different[$slug])) {
			$slug = $different[$slug];
		}
		
		return $slug;
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
