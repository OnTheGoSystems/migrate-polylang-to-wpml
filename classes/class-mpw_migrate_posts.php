<?php

class mpw_migrate_posts {
	
	private $polylang_data;
	private $wpdb;
	private $polylang_default_language;
	
	public function __construct($polylang_data) {
		global $wpdb;
		
		$this->polylang_data = $polylang_data;
		$this->wpdb = &$wpdb;
		$this->polylang_default_language = $this->polylang_data->get_default_language_slug();
	}
	
	public function migrate_posts() {
		$posts_grouped_by_polylang_lang_relation = $this->posts_grouped_by_polylang_lang_relation();
		
		foreach ($posts_grouped_by_polylang_lang_relation as $relation) {
			
			$default_language_code = $this->get_default_language_code($relation);

			list($trid, $post_type) = $this->set_original_post_language_details($relation, $default_language_code);
			
			$this->set_other_posts_language_details($relation, $default_language_code, $post_type, $trid);
			
		}
	}
	
	private function posts_grouped_by_polylang_lang_relation() {
		$pll_post_translations = $this->polylang_data->get_post_translations();
		$posts_per_polylang_language = $this->posts_per_polylang_language();
		
		
		foreach ($pll_post_translations as $pll_post_translation) {
				$relations[] = maybe_unserialize( $pll_post_translation->description );
		}
		
		foreach($posts_per_polylang_language as $code => $id ){
			foreach($id as $value){
				if($this->in_array_r($value, $relations) === FALSE){
						$relations[] = array($code => $value, 'language_code' => $code);
				}
			}
		}
		
		return $relations;
	}
	
	private function posts_per_polylang_language() {
		$posts = null;
		$get_languages = $this->polylang_data->get_languages();
		
		if (!empty($get_languages) && is_array($get_languages)) {
			foreach ($get_languages as $get_language) {
				$query = $this->wpdb->prepare(
									"SELECT p.ID AS post_id FROM {$this->wpdb->prefix}posts p INNER JOIN {$this->wpdb->prefix}term_relationships r ON r.object_id=p.ID WHERE r.term_taxonomy_id=%d", 
									$get_language->term_taxonomy_id
								);
				$results = $this->wpdb->get_results($query);
				foreach ($results as $result){
					$posts[$get_language->slug][] = $result->post_id;
				}
			} 
		} 
		
		return $posts;
		
	}	
	
	private function in_array_r($needle, $haystack, $strict = false) {
	    foreach ($haystack as $item) {
	        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
	            return true;
	        }
	    }
	    return false;
	}
	
	private function get_default_language_code($relation) {
		$default_language_code['polylang'] = isset($relation['language_code']) ? $relation['language_code'] : $this->polylang_default_language;
		$default_language_code['wpml'] = $this->polylang_data->lang_slug_to_wpml_format($default_language_code['polylang']);
		
		return $default_language_code;
	}

	private function set_original_post_language_details($relation, $default_language_code) {
		$original_post_id = $relation[$default_language_code['polylang']];
		$post_type = apply_filters( 'wpml_element_type', get_post_type($original_post_id) );

		do_action('wpml_set_element_language_details', array(
				'element_id' => $original_post_id,
				'element_type' => $post_type,
				'trid' => false,
				'language_code' => $default_language_code['wpml']
		));

		$original_post_language_details = apply_filters('wpml_element_language_details', null, array(
				'element_id' => $original_post_id,
				'element_type' => $post_type
		));

		$trid = $original_post_language_details->trid;
		
		return array($trid, $post_type);
	}
	
	private function set_other_posts_language_details($relation, $default_language_code, $post_type, $trid) {
		if (isset($relation[$default_language_code['polylang']])) { 
			unset($relation[$default_language_code['polylang']]);
		}
			
		foreach ($relation as $next_post_language_code => $post_id) {

			$next_post_language_code_wpml_format = $this->polylang_data->lang_slug_to_wpml_format($next_post_language_code);

			do_action('wpml_set_element_language_details', array(
				'element_id' => $post_id,
				'element_type' => $post_type,
				'trid' => $trid,
				'language_code' => $next_post_language_code_wpml_format,
				'source_language_code' => $default_language_code['wpml']
			));
		}
	}
	
}
