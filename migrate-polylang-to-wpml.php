<?php

/*
Plugin Name: Migrate Polylang to WPML
Description: Import multilingual data from Polylang to WPML
Author: Konrad Karpieszuk, Harshad Mane
Plugin uri: https://wpml.org
Version: 0.1
 */

class Migrate_Polylang_To_WPML {
	private $polylang_option;

	public function __construct() {
		$this->polylang_option = get_option('polylang');

		add_action('admin_menu', array($this, 'admin_menu'));

		add_action('admin_init', array($this, 'handle_migration'));

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_enabling_script') );
	}

	public function enqueue_enabling_script() {
		if ($this->pre_check_ready_all()) {
			wp_register_script('migrate-enabling-script', plugins_url('scripts/enabling.js', __FILE__), array('jquery'), '', true);
			wp_enqueue_script('migrate-enabling-script');
		}
	}

	public function admin_menu() {

		$title = __("Migrate from Polylang to WPML", 'migrate-polylang');

		add_submenu_page('tools.php', $title, $title, 'manage_options', 'polylang-importer', array( &$this, 'migrate_page' ) );
	}

	public function migrate_page() {
		?>
<div class="wrap">
	<h2><?php _e('Migrate data from Polylang to WPML', 'migrate-polylang'); ?></h2>
<?php echo $this->pre_check_text();
if ($this->pre_check_ready_all()) :
?>
	<form method="post" action="tools.php?page=polylang-importer">
		<label for='migrate_polylang_to_wpml_confirm_db_backup'>
		<input type='checkbox' id='migrate_polylang_to_wpml_confirm_db_backup' name='migrate_polylang_to_wpml_confirm_db_backup'>
		 <?php _e("I confirm that I've created <a href='https://codex.wordpress.org/Backing_Up_Your_Database' target='_blank'>database backup</a>", "migrate-polylang"); ?>
		</label> <br>
		<input type="hidden" name="migrate_wpml_action" value="migrate" />
		<input type="submit"
			   name="migrate-polylang-wpml"
			   id="migrate_polylang_wpml"
			   value="<?php _e('Migrate', 'migrate-polylang'); ?>"
			   class="button button-primary" disabled >

	</form>
<?php
else :
?>
	<div style="color:red;font-weight: bold;"><?php _e("Please make sure all requirements have been met", "migrate-polylang"); ?></div>
<?php endif; ?>
</div>
		<?php
	}

	private function pre_check_text() {

	$ok = "style='color:green'>✔ ";
	$bad = "style='color:red'>✘ ";

	$poly_check = $this->pre_check_polylang() ? $ok : $bad;

	$wpml_check = $this->pre_check_wpml() ? $ok : $bad;

	$wpml_wizard = $this->pre_check_wizard_complete() ? $ok : $bad;

$text = "
	<h3>" . __("Before you click migrate you must be sure that:", "migrate-polylang") . "</h3>
	<ul>
	<li><span $poly_check" . __("Polylang is deactivated", "migrate-polylang") . "</span></li>
	<li><span $wpml_check" . __("WPML Multilingual Blog/Cms is active", "migrate-polylang") . "</span></li>
	<li><span $wpml_wizard" . __("You have finished WPML configuration wizard", "migrate-polylang") . "</span></li>
	<li>". __("If you want to import also strings translation, you must have WPML String Translation plugin activated (Not required)", "migrate-polylang") . "</li>
	<li>". __("If you want to import also widgets language settings, you must have <a href='https://wordpress.org/plugins/wpml-widgets/' target='_blank'>WPML Widgets plugin</a> activated (Not required)", "migrate-polylang") . "</li>
	</ul>
		";

	return $text;
	}

	private function pre_check_polylang() {
		return !defined('POLYLANG_VERSION');
	}

	private function pre_check_wpml() {
		return defined('ICL_SITEPRESS_VERSION');
	}

	private function pre_check_wizard_complete() {
		return apply_filters( 'wpml_setting', false, 'setup_complete' );
	}
	
	private function pre_check_wpml_widgets() {
		return class_exists('WPML_Widgets');
	}

	private function pre_check_ready_all() {
		return $this->pre_check_polylang() and $this->pre_check_wpml() and $this->pre_check_wizard_complete();
	}

	public function handle_migration() {
		if (isset($_POST['migrate_wpml_action']) && $_POST['migrate_wpml_action'] == 'migrate') {
			$this->migrate();
			add_action( 'admin_notices', array($this, 'migrate_page_done_notice') );
		}
	}

	public function migrate_page_done_notice() {
		?>
		<div class="notice notice-success is-dismissible">
		<?php _e('Migration done. Now you can disable this plugin forever. Thank you for choosing WPML!', 'migrate-polylang'); ?>
		</div>
		<?php
	}

	private function migrate() {
		if ($this->pre_check_ready_all()) {
			$this->migrate_languages();

			$this->migrate_posts();

			$this->migrate_taxonomies();

			$this->migrate_strings();
			
			if ($this->pre_check_wpml_widgets()) {
				$this->migrate_widgets();
			}

			flush_rewrite_rules();
		}
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

	private function in_array_r($needle, $haystack, $strict = false) {
	    foreach ($haystack as $item) {
	        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
	            return true;
	        }
	    }
	    return false;
	}

	private function migrate_posts() {
		global $wpdb;
		register_taxonomy('language', null);
		register_taxonomy('post_translations', null);
		$get_languages = get_terms('language', array( 'hide_empty' => false));
		$pll_post_translations = get_terms('post_translations', array( 'hide_empty' => false));
		$default_language = $this->polylang_option['default_lang'];
		if (!empty($get_languages) && is_array($get_languages)) {
			foreach ($get_languages as $get_language) {
				$language[$get_language->slug] = $get_language->term_taxonomy_id;
				$query = $wpdb->prepare("SELECT p.ID AS post_id FROM {$wpdb->prefix}posts p INNER JOIN {$wpdb->prefix}term_relationships r ON r.object_id=p.ID WHERE r.term_taxonomy_id=%d", $get_language->term_taxonomy_id);
				$results = $wpdb->get_results($query);
				foreach ($results as $result){
					$posts[$get_language->slug][] = $result->post_id;
				}
			} //end $get_languages for each
		} //end if $get_languages
			foreach ($pll_post_translations as $pll_post_translation) {
				$relations[] = maybe_unserialize( $pll_post_translation->description );
			} // end for each $pll_post_translations
			foreach($posts as $code => $id ){
				foreach($id as $value){
					if($this->in_array_r($value, $relations) === FALSE){
							$relations[] = array($code => $value, 'language_code' => $code);
					}
				}
			}// end for each posts
		  foreach ($relations as $relation) {
				if($relation['language_code']){
					$language_code = $relation['language_code'];
				}else{
					$language_code = $default_language;
			}
			$original_post_id = $relation[$language_code];
			$post_type = get_post_type($original_post_id);
			$post_type = apply_filters( 'wpml_element_type', $post_type );
			do_action('wpml_set_element_language_details', array(
					'element_id' => $original_post_id,
					'element_type' => $post_type,
					'trid' => false,
					'language_code' => $language_code
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
				} // end foreach $relation
			} //end foreach  $relations
	}

	private function migrate_taxonomies() {
		global $wpdb;

		register_taxonomy('term_translations', null);

		// remove just registered taxonomy from icl_translations
		$table = $wpdb->prefix . "icl_translations";

		$wpdb->delete($table, array('element_type' => 'tax_term_translations'));

		$pll_term_translations = get_terms('term_translations', array( 'hide_empty' => false));

		$default_language = $this->polylang_option['default_lang'];

		if (!empty($pll_term_translations) && is_array($pll_term_translations)) {
			foreach ($pll_term_translations as $pll_term_translation) {
				$relation = maybe_unserialize( $pll_term_translation->description );

				if(count($relation) > 1 ){
					$language_code = $default_language;
				}else{
					$language_code = key($relation);
				}
				
				if (isset($relation[$language_code])) {
					$original_term_id = $relation[$language_code];

					$original_term = $this->get_term_by_term_id( $original_term_id );

					$original_term_taxonomy_id = $original_term->term_taxonomy_id;

					if (isset($original_term->taxonomy)) {
						$taxonomy = $original_term->taxonomy;

						$taxonomy = apply_filters( 'wpml_element_type', $taxonomy );

						try {
							do_action('wpml_set_element_language_details', array(
								'element_id' => $original_term_taxonomy_id,
								'element_type' => $taxonomy,
								'trid' => false,
								'language_code' => $language_code
							));
						} catch (Exception $e) {
							echo $e->getMessage();
							echo "<br>Setting original term language details failed<br>";
							printf('element_id was %s, element_type was %s, language_code was %s',
									$original_term_taxonomy_id, $taxonomy, $default_language);
							exit();
						}


						$original_term_language_details = apply_filters('wpml_element_language_details', null, array(
							'element_id' => $original_term_taxonomy_id,
							'element_type' => $taxonomy
						));

						$trid = $original_term_language_details->trid;

						unset($relation[$default_language]);

						foreach ($relation as $language_code => $term_id) {
							$translated_term = $this->get_term_by_term_id( $term_id );

							$translated_term_taxonomy_id = $translated_term->term_taxonomy_id;

							try {
								do_action('wpml_set_element_language_details', array(
									'element_id' => $translated_term_taxonomy_id,
									'element_type' => $taxonomy,
									'trid' => $trid,
									'language_code' => $language_code,
									'source_language_code' => $default_language
								));
							} catch (Exception $e) {
								echo $e->getMessage();
								echo "<br>Setting translated term language details failed<br>";
								printf('element_id was %s, element_type was %s, trid %s, language_code was %s, original language %s',
										$translated_term_taxonomy_id, $taxonomy, $trid, $language_code, $default_language);
								exit();
							}
						}
					}

				}

			}
		}
	}

	private function get_term_by_term_id($id) {
		global $wpdb;

		$table_name = $wpdb->prefix . "term_taxonomy";

		$select_statement = "SELECT * FROM $table_name WHERE term_id = %d";

		$select_sanitized = $wpdb->prepare($select_statement, $id);

		$term_taxonomy = $wpdb->get_row($select_sanitized);

		$term = false;

		if (isset($term_taxonomy->term_taxonomy_id) && isset($term_taxonomy->taxonomy)) {
			$term = get_term_by('id', $id, $term_taxonomy->taxonomy);
		}

		return $term;

	}

	private function migrate_strings() {
		$polylang_languages_map = $this->get_polylang_languages_map();
		
		$wpml_string_translations = $this->get_wpml_string_translations();

		
		$polylang_strings_array = $this->get_polylang_strings_array($polylang_languages_map);

		if ($polylang_strings_array) {

			foreach ($polylang_strings_array as $lang_id => $string_groups) {
				$this->migrate_string_groups($lang_id, $string_groups, $polylang_languages_map, $wpml_string_translations);
			}
		}
		
	}

	private function get_wpml_string_translations() {
		global $wpdb;

		$table = $wpdb->prefix . "icl_strings";

		$query = "SELECT id, language, value FROM $table";

		$results = $wpdb->get_results($query);

		$langcode_indexed = array();

		if ($results) {
			foreach($results as $string) {
				$langcode_indexed[$string->language][$string->value] = $string;
			}
		}

		return $langcode_indexed;

	}

	private function get_polylang_languages() {
		global $wpdb;

		register_taxonomy('language', null);
		$table = $wpdb->prefix . "icl_translations";
		$wpdb->delete($table, array('element_type' => 'tax_language'));

		$polylang_languages = get_terms('language', array( 'orderby' => 'term_id' ));

		return $polylang_languages;
	}

	private function get_polylang_languages_map() {
		$polylang_languages = $this->get_polylang_languages();

		$polylang_languages_map = null;

		foreach ($polylang_languages as $language) {
			$polylang_languages_map[$language->term_id] = $language->slug;
		}

		return $polylang_languages_map;
	}

	private function get_polylang_strings_array($polylang_languages_map) {
		global $wpdb;

		$polylang_strings_array = null;
		
		foreach(array_keys($polylang_languages_map) as $lang_id) {
			$post_with_polylang_strings = "SELECT post_content FROM ".$wpdb->prefix."posts where post_type = 'polylang_mo'  AND post_title=%s order by ID desc limit 1";

			$polylang_strings = $wpdb->get_var($wpdb->prepare($post_with_polylang_strings, "polylang_mo_".$lang_id));


			if ($polylang_strings) {
				$polylang_strings_array[$lang_id] = maybe_unserialize($polylang_strings);
			}
		}

		

		return $polylang_strings_array;
	}

	private function migrate_string_groups($lang_id, $string_groups, $polylang_languages_map, $wpml_string_translations) {
		
		$indexed_polylang_string_group = array();
		
		$default_language = $this->polylang_option['default_lang'];
		
		$to_language = $polylang_languages_map[$lang_id];
		
		foreach($string_groups as $group) {
			if (isset($wpml_string_translations[$default_language][ $group[0] ])) {
				icl_add_string_translation(
					$wpml_string_translations[$default_language][$group[0]]->id,
					$to_language,
					$group[1],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} else if (isset($wpml_string_translations[$to_language][ $group[0] ])) {
					icl_add_string_translation(
					$wpml_string_translations[$to_language][$group[0]]->id,
					$default_language,
					$group[1],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} else if (isset($wpml_string_translations[$default_language][ $group[1] ])) {
				icl_add_string_translation(
					$wpml_string_translations[$default_language][$group[1]]->id,
					$to_language,
					$group[0],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} else if (isset($wpml_string_translations[$to_language][ $group[1] ])) {
					icl_add_string_translation(
					$wpml_string_translations[$to_language][$group[1]]->id,
					$default_language,
					$group[0],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} 
		}
	}
	
	private function migrate_widgets() {
		global $wpdb;
		
		$options_table = $wpdb->prefix . "options";
		
		$all_widgets_query = "SELECT option_name FROM $options_table WHERE option_name LIKE 'widget_%'";
		
		$all_widgets = $wpdb->get_results($all_widgets_query);
		
		if ($all_widgets) {
			foreach ($all_widgets as $widget) {
				$option = get_option($widget->option_name); 
				if ($option && is_array($option)) {
					foreach ($option as $key => $val) {
						if (is_numeric($key) && isset($val['pll_lang'])) {
							$option[$key]['wpml_language'] = $val['pll_lang'];
						}
					}
					update_option($widget->option_name, $option);
				}
			}
		}
	}
}

$migrate_polylang_to_wpml = new Migrate_Polylang_To_WPML();
