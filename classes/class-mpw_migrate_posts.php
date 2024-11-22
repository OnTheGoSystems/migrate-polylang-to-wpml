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

			$originalPostId = $this->getOriginalPostId( $relation, $default_language_code );
			if ( ! $originalPostId ) {
				continue;
			}
			
			$originalPostElementType = apply_filters( 'wpml_element_type', get_post_type( $originalPostId ) );
			if ( ! $originalPostElementType ) {
				continue;
			}

			$trid = $this->set_original_post_language_details( $originalPostId, $originalPostElementType, $default_language_code );
			if ( ! $trid ) {
				continue;
			}
			
			$this->set_other_posts_language_details($relation, $default_language_code, $originalPostElementType, $trid);
		}
	}

	/**
	 * @return array
	 */
	private function posts_grouped_by_polylang_lang_relation() {
		$pll_post_translations       = $this->polylang_data->get_post_translations();
		$posts_per_polylang_language = $this->posts_per_polylang_language();
		$relations                   = [];
		
		
		foreach ($pll_post_translations as $pll_post_translation) {
			$relationCandidate = maybe_unserialize( $pll_post_translation->description );
			if ( is_array( $relationCandidate ) ) {
				$relations[] = $relationCandidate;
			}
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

	/**
	 * @return array
	 */
	private function posts_per_polylang_language() {
		$posts         = [];
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

	/**
	 * @param array $relation
	 * @param array $defaultLanguageCode
	 *
	 * @return string|null
	 */
	private function getOriginalPostId( $relation, $defaultLanguageCode ) {
		$defaultPolylangCode = $defaultLanguageCode['polylang'];

		if (
			array_key_exists( 'sync', $relation )
			&& is_array( $relation['sync'] )
			&& array_key_exists( $defaultPolylangCode, $relation['sync'] )
		) {
			return $relation['sync'][ $defaultPolylangCode ];
		} elseif ( array_key_exists( $defaultPolylangCode, $relation ) ) {
			return $relation[ $defaultPolylangCode ];
		}

		return null;
	}

	/**
	 * @param string $originalPostId
	 * @param string $originalPostElementType
	 * @param array  $default_language_code
	 *
	 * @return string|null
	 */
	private function set_original_post_language_details( $originalPostId, $originalPostElementType, $default_language_code ) {
		do_action('wpml_set_element_language_details', array(
				'element_id'    => $originalPostId,
				'element_type'  => $originalPostElementType,
				'trid'          => false,
				'language_code' => $default_language_code['wpml']
		));

		$originalPostLanguageDetails = apply_filters('wpml_element_language_details', null, array(
				'element_id'   => $originalPostId,
				'element_type' => $originalPostElementType
		));

		if ( ! $originalPostLanguageDetails ) {
			return null;
		}

		return $originalPostLanguageDetails->trid;
	}

	/**
	 * @param array  $relation
	 * @param array  $default_language_code
	 * @param string $post_type
	 * @param string $trid
	 */
	private function set_other_posts_language_details($relation, $default_language_code, $post_type, $trid) {
		if ( array_key_exists( $default_language_code['polylang'], $relation ) ) {
			unset( $relation[ $default_language_code['polylang'] ] );
		}
		if ( array_key_exists( 'sync', $relation ) ) {
			unset( $relation['sync'] );
		}

		foreach ($relation as $next_post_language_code => $post_id) {

			$next_post_language_code_wpml_format = $this->polylang_data->lang_slug_to_wpml_format($next_post_language_code);

			do_action('wpml_set_element_language_details', array(
				'element_id'           => $post_id,
				'element_type'         => $post_type,
				'trid'                 => $trid,
				'language_code'        => $next_post_language_code_wpml_format,
				'source_language_code' => $default_language_code['wpml']
			));
		}
	}
	
}
