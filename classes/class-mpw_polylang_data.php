<?php
/**
 *
 * @author konrad
 */
class mpw_polylang_data {
	
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
		global $wpdb;
		
		register_taxonomy($tax, null);
		$table = $wpdb->prefix . "icl_translations";
		$wpdb->delete($table, array('element_type' => 'tax_'.$tax));
		$terms = get_terms($tax, array( 'hide_empty' => false));
		
		return $terms;
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
	
	public function lang_slug_to_wpml_format($slug) {
		$different = array(
			'pt' => 'pt-pt',
			'zh' => 'zh-hans'
		);
		
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
		
		if ($terms && !empty($terms)) {
			foreach ($terms as $term) {
				wp_delete_term($term->term_id, $tax);
			}
		}
	}


}
