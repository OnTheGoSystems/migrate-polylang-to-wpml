<?php

/*
Plugin Name: Migrate Polylang to WPML
 */

class Migrate_Polylang_To_WPML {
	private $polylang_option;
	
	public function __construct() {
		$this->polylang_option = get_option('polylang');
		
		add_action('admin_menu', array($this, 'admin_menu'));
	}
	
	public function admin_menu() {
		
		$title = __("Migrate from Polylang to WPML", 'migrate-polylang');
		
		add_submenu_page('tools.php', $title, $title, 'manage_options', 'polylang-importer', array( &$this, 'migrate_page' ) );
	}
	
	public function migrate_page() {
		?>
<div class="wrap">
	<h2>Migrate data from Polylang to WPML</h2>
	<?php 
	if (isset($_POST['migrate_wpml_action']) && $_POST['migrate_wpml_action'] == 'migrate') {
		$this->migrate();
		?>
<div class="notice notice-success is-dismissible">
Migration done			
</div>
		<?php
	}
	?>
	
	<form method="post" action="tools.php?page=polylang-importer">
		<input type="hidden" name="migrate_wpml_action" value="migrate" />
		<input type="submit" name="migrate-polylang-wpml" value="Migrate">
		
	</form>
</div>	
		<?php
	}
	
	private function migrate() {
		$this->migrate_languages();
		
		$this->migrate_posts();
		
		$this->migrate_taxonomies();
		
		// $this->migrate_strings();
		
		// $this->migrate_options();
		
		flush_rewrite_rules();
	}
	
	private function migrate_languages() {
		global $wpdb;
		
		$pll_languages = get_terms( 'language', array( 'hide_empty' => false, 'orderby' => 'term_group' ) );
		
		if (!empty($pll_languages) && is_array($pll_languages)) {
			foreach ($pll_languages as $pll_language) {
				if (isset($pll_language->slug)) {
					$wpdb->update(
							$wpdb->prefix . 'icl_languages',
							array('active' => 1),
							array('code' => $pll_language->slug)
							);
				}
			}
		}
	}
	
	private function migrate_posts() {
		
		register_taxonomy('post_translations', null);
		
		$pll_post_translations = get_terms('post_translations', array( 'hide_empty' => false));
		
		$default_language = $this->polylang_option['default_lang'];
		
		if (!empty($pll_post_translations) && is_array($pll_post_translations)) {
			foreach ($pll_post_translations as $pll_post_translation) {
				$relation = maybe_unserialize( $pll_post_translation->description );
				
				$original_post_id = $relation[$default_language];
				
				$post_type = get_post_type($original_post_id);
				
				$post_type = apply_filters( 'wpml_element_type', $post_type );
				
				do_action('wpml_set_element_language_details', array(
					'element_id' => $original_post_id,
					'element_type' => $post_type,
					'trid' => false,
					'language_code' => $default_language
				));
				
				$original_post_language_details = apply_filters('wpml_element_language_details', null, array(
					'element_id' => $original_post_id,
					'element_type' => $post_type
				));
				
				$trid = $original_post_language_details->trid;
				
				unset($relation[$default_language]);
				
				foreach ($relation as $language_code => $post_id) {
					do_action('wpml_set_element_language_details', array(
						'element_id' => $post_id,
						'element_type' => $post_type,
						'trid' => $trid,
						'language_code' => $language_code,
						'source_language_code' => $default_language
					));
				}
			}
		}
	}
	
	private function migrate_taxonomies() {
		
		register_taxonomy('term_translations', null);
		
		$pll_term_translations = get_terms('term_translations', array( 'hide_empty' => false));
		
		$default_language = $this->polylang_option['default_lang'];
		
		if (!empty($pll_term_translations) && is_array($pll_term_translations)) {
			foreach ($pll_term_translations as $pll_term_translation) {
				$relation = maybe_unserialize( $pll_term_translation->description );
				
				$original_term_id = $relation[$default_language];
				
				$original_term = get_term($original_term_id);
				
				$taxonomy = $original_term->taxonomy;
				
				$taxonomy = apply_filters( 'wpml_element_type', $taxonomy );
				
				do_action('wpml_set_element_language_details', array(
					'element_id' => $original_term_id,
					'element_type' => $taxonomy,
					'trid' => false,
					'language_code' => $default_language
				));
				
				$original_term_language_details = apply_filters('wpml_element_language_details', null, array(
					'element_id' => $original_term_id,
					'element_type' => $taxonomy
				));
				
				$trid = $original_term_language_details->trid;
				
				unset($relation[$default_language]);
				
				foreach ($relation as $language_code => $term_id) {
					do_action('wpml_set_element_language_details', array(
						'element_id' => $term_id,
						'element_type' => $taxonomy,
						'trid' => $trid,
						'language_code' => $language_code,
						'source_language_code' => $default_language
					));
				}
				
			}
		}
	}
}

$migrate_polylang_to_wpml = new Migrate_Polylang_To_WPML();