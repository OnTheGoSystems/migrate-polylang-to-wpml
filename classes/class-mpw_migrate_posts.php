<?php

defined('ABSPATH') || exit;

class mpw_migrate_posts {

	/**
	 * Keys that appear inside a Polylang relation but are not language slugs.
	 *
	 * `sync` is Polylang's duplicated-post map. `language_code` is added by this plugin when it
	 * synthesises a relation for a post that has a language but no translation group.
	 */
	const RESERVED_RELATION_KEYS = array( 'sync', 'language_code' );

	private $polylang_data;
	private $wpdb;
	private $polylang_default_language;

    /** @var array $translatable_wpml_post_types */
	private $translatable_wpml_post_types = [];

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

			// get_post_type() returns false for a post that no longer exists. Feeding that to
			// wpml_element_type is unsafe: the filter's in_array() checks are non-strict, so on
			// PHP 7 `false` matches the first registered taxonomy and the filter hands back the
			// string "tax_".
			$originalPostType = get_post_type( $originalPostId );
			if ( ! is_string( $originalPostType ) || '' === $originalPostType ) {
				continue;
			}

			$originalPostElementType = apply_filters( 'wpml_element_type', $originalPostType );
			if ( ! is_string( $originalPostElementType ) || '' === $originalPostElementType ) {
				continue;
			}

			$this->set_post_type_translatable( $originalPostElementType );

			$trid = $this->set_original_post_language_details( $originalPostId, $originalPostElementType, $default_language_code );
			if ( ! $trid ) {
				continue;
			}

			$this->set_other_posts_language_details( $relation, $default_language_code, $originalPostElementType, $trid, $originalPostId );
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
		$default_language_code = array( 'polylang' => '', 'wpml' => '' );

		$original_slug = $this->get_original_language_slug( $relation );

		if ( null === $original_slug ) {
			return $default_language_code;
		}

		$default_language_code['polylang'] = $original_slug;
		$default_language_code['wpml'] = $this->polylang_data->lang_slug_to_wpml_format($original_slug);

		return $default_language_code;
	}

	/**
	 * Picks the language slug whose post acts as the original of a translation group.
	 *
	 * Relations synthesised for untranslated posts carry an explicit `language_code`. Real
	 * Polylang groups use the site default when it is present; groups that exist only in
	 * secondary languages used to be dropped entirely, so fall back to the first usable entry
	 * rather than losing them.
	 *
	 * @param array $relation Translation group, keyed by Polylang language slug.
	 *
	 * @return string|null A language slug, or null when the group holds nothing usable.
	 */
	private function get_original_language_slug( $relation ) {
		if ( ! empty( $relation['language_code'] ) && is_string( $relation['language_code'] ) ) {
			return $relation['language_code'];
		}

		if ( ! empty( $relation[ $this->polylang_default_language ] ) ) {
			return $this->polylang_default_language;
		}

		foreach ( $relation as $slug => $post_id ) {
			if ( in_array( $slug, self::RESERVED_RELATION_KEYS, true ) ) {
				continue;
			}

			if ( is_string( $slug ) && ! empty( $post_id ) ) {
				return $slug;
			}
		}

		return null;
	}

	/**
	 * @param array $relation
	 * @param array $defaultLanguageCode
	 *
	 * @return string|null
	 */
	private function getOriginalPostId( $relation, $defaultLanguageCode ) {
		$defaultPolylangCode = $defaultLanguageCode['polylang'];

		if ( array_key_exists( $defaultPolylangCode, $relation ) ) {
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
	 * @param string $originalPostId
	 */
	private function set_other_posts_language_details( $relation, $default_language_code, $post_type, $trid, $originalPostId ) {
		/** @var \SitePress $sitepress */
		global $sitepress;

		$sync = isset( $relation['sync'] ) && is_array( $relation['sync'] ) ? $relation['sync'] : [];

		if ( array_key_exists( $default_language_code['polylang'], $relation ) ) {
			unset( $relation[ $default_language_code['polylang'] ] );
		}

		// Strip everything that isn't a language slug. `language_code` used to survive this,
		// so every untranslated post produced a bogus WPML call with the slug string passed as
		// the element ID and "language_code" passed as the language.
		foreach ( self::RESERVED_RELATION_KEYS as $reserved_key ) {
			unset( $relation[ $reserved_key ] );
		}

		foreach ($relation as $next_post_language_code => $post_id) {
			if ( empty( $post_id ) ) {
				continue;
			}

			$next_post_language_code_wpml_format = $this->polylang_data->lang_slug_to_wpml_format($next_post_language_code);

			do_action('wpml_set_element_language_details', array(
				'element_id'           => $post_id,
				'element_type'         => $post_type,
				'trid'                 => $trid,
				'language_code'        => $next_post_language_code_wpml_format,
				'source_language_code' => $default_language_code['wpml']
			));
		}

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'make_duplicate' ) ) {
			return;
		}

		// Polylang's sync map is keyed by its own slugs; make_duplicate() wants a WPML code.
		foreach ( $sync as $targetLang => $sourceLang ) {
			if ( $targetLang !== $sourceLang ) {
				$sitepress->make_duplicate( $originalPostId, $this->polylang_data->lang_slug_to_wpml_format( $targetLang ) );
			}
		}
	}

	/**
	 * @param string $wpml_post_type
	 *
	 * @return void
	 */
	private function set_post_type_translatable( $wpml_post_type ) {
		if ( ! in_array( $wpml_post_type, $this->translatable_wpml_post_types, true ) ) {
			$post_type = preg_replace( '/^post_/', '', $wpml_post_type );
			do_action( 'wpml_set_translation_mode_for_post_type', $post_type, 'translate' );
			$this->translatable_wpml_post_types[] = $wpml_post_type;
		}
	}
}
