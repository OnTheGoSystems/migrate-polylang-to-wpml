<?php
/**
 *
 * @author konrad
 */
class mpw_polylang_data {
	
	public function __construct() {
		
	}
	
	public function get_languages() {
		global $wpdb;

		register_taxonomy('language', null);
		$table = $wpdb->prefix . "icl_translations";
		$wpdb->delete($table, array('element_type' => 'tax_language'));
		$polylang_languages = get_terms('language', array( 'orderby' => 'term_id' ));

		return $polylang_languages;
	}
	
	public function get_post_translations() {
		global $wpdb;
		
		register_taxonomy('post_translations', null);
		$table = $wpdb->prefix . "icl_translations";
		$wpdb->delete($table, array('element_type' => 'tax_post_translations'));
		$pll_post_translations = get_terms('post_translations', array( 'hide_empty' => false));
		
		return $pll_post_translations;
	}
	
	public function get_term_translations() {
		global $wpdb;
		
		register_taxonomy('term_translations', null);
		$table = $wpdb->prefix . "icl_translations";
		$wpdb->delete($table, array('element_type' => 'tax_term_translations'));
		$pll_term_translations = get_terms('term_translations', array( 'hide_empty' => false));
		
		return $pll_term_translations;
	}
	
	public function get_additional_languages_names() {
		$pll_languages = $this->get_languages();
		$default_language_slug = $this->get_default_language_slug();
		
		$additional_languages = array();
		
		foreach ($pll_languages as $language) {
			if ($language->slug !== $default_language_slug) {
				$additional_languages[] = $language->name;
			}
		}
		
		return $additional_languages;
	}
	
	public function get_default_language_slug() {
		$polylang_option = get_option('polylang');
		
		return $polylang_option['default_lang'];
	}
	
	public function get_default_language_name() {
		$pll_languages = $this->get_languages();
		$default_language_slug = $this->get_default_language_slug();
		
		foreach ($pll_languages as $language) {
			if ($language->slug == $default_language_slug) {
				return $language->name;
			}
		}
		
	}
	
	public function get_languages_map() {
		$polylang_languages = $this->get_languages();

		$polylang_languages_map = null;

		foreach ($polylang_languages as $language) {
			$polylang_languages_map[$language->term_id] = $language->slug;
		}

		return $polylang_languages_map;
	}


}
