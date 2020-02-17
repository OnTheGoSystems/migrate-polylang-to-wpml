<?php

/*
Plugin Name: Migrate Polylang to WPML
Description: Import multilingual data from Polylang to WPML
Author: Konrad Karpieszuk, Harshad Mane
Plugin uri: https://wpml.org
Version: 0.4-dev
 */

class Migrate_Polylang_To_WPML {
	private $polylang_data;
	private $mpw_htaccess_check;

	public function __construct() {
		
		require_once('classes/class-mpw_polylang_data.php');
		$this->polylang_data = new mpw_polylang_data(); 
		
		require_once 'classes/class-mpw_htaccess_check.php';
		$this->mpw_htaccess_check = new MPW_Htaccess_Check($this->polylang_data);		

		add_action('admin_menu', array($this, 'admin_menu'));

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts') );
		
		add_action( 'wp_ajax_mpw_migrate_languages', array($this, 'ajax_migrate_languages') );
		add_action( 'wp_ajax_mpw_migrate_posts', array($this, 'ajax_migrate_posts') );
		add_action( 'wp_ajax_mpw_migrate_taxonomies', array($this, 'ajax_migrate_taxonomies') );
		add_action( 'wp_ajax_mpw_migrate_strings', array($this, 'ajax_migrate_strings') );
		add_action( 'wp_ajax_mpw_migrate_widgets', array($this, 'ajax_migrate_widgets') );
		
		add_action( 'wp_ajax_mpw_delete_polylang_data', array($this, 'ajax_delete_polylang_data') );
		
		add_action('admin_notices', array($this, 'guide_admin_notices'));
		
	}

	public function enqueue_scripts() {
		if ($this->pre_check_ready_all()) {
			wp_register_script('migrate-enabling-script', plugins_url('scripts/enabling.js', __FILE__), array('jquery'), '', true);
			wp_enqueue_script('migrate-enabling-script');
			
			
			wp_register_script('migrate-ajax',  plugins_url('scripts/ajax.js', __FILE__), array('jquery'), '', true);
			$ajax_strings_translations = array(
				'mig_start' => __("Migration started, please don't close this window...", 'migrate-polylang'),
				'lan_start' => __("Moving your language settings...", 'migrate-polylang'),
				'posts_start' => __("Setting languages for posts...", 'migrate-polylang'),
				'tax_start' => __("Settings languages for taxonomies...", 'migrate-polylang'),
				'str_start' => __("Translating strings (only if WPML String Translation is activated)...", 'migrate-polylang'),
				'widg_start' => __("Localizing widgets (only if WPML Widgets is activated)...", 'migrate-polylang'),
				'mig_done' => __("Migration done! Please check if everything is correct and deactivate or unistall Migrate Polylang to WPML plugin", 'migrate-polylang'),
				'mig_again_label' => __("Migrate again", "migrate-polylang"),
				
				'delete_confirm' => __("Warning: after deleting Polylang data you will not be able to do this migration again or return to Polylang without setting up everything from beginning.\nAre you sure you want to do this?", 'migrate-polylang'),
				'del_start' => __("Deleting Polylang data...", 'migrate-polylang'),
			);
			wp_localize_script('migrate-ajax', 'mpw_ajax_str', $ajax_strings_translations);
			wp_enqueue_script('migrate-ajax');
		}
		
		if (is_admin() && !$this->pre_check_wizard_complete()) {
			wp_register_script('migrate-tooltips',  plugins_url('tooltips/js/helpcursor-min.js', __FILE__), array('jquery'), '', true);
			wp_enqueue_script('migrate-tooltips');
			
			wp_register_script('migrate-tooltips-uses',  plugins_url('scripts/tooltips.js', __FILE__), array('migrate-tooltips'), '', true);
			
			$tooltips_texts = array(
				'before_mig' => __('Before migrating Polylang, please finish WPML wizard', 'migrate-polylang'),
				'default_language' => sprintf(__('With Polylang your default language was %s', 'migrate-polylang'), $this->polylang_data->get_default_language_name()),
				'additional_languages' => sprintf(__('Polylang additional languages: %s', 'migrate-polylang'), 
						join(", ", $this->polylang_data->get_additional_languages_names()))
			);
			wp_localize_script('migrate-tooltips-uses', 'tooltips_texts', $tooltips_texts);
			wp_enqueue_script('migrate-tooltips-uses');
			
			wp_register_style('migrate-tooltips-css',  plugins_url('tooltips/css/helpcursor.css', __FILE__));
			wp_enqueue_style('migrate-tooltips-css');
		}
		
		if (is_admin()) {
			wp_register_script('migrate-htaccess',  plugins_url('scripts/htaccess.js', __FILE__), array('jquery'), '', true);
			wp_enqueue_script('migrate-htaccess');
		}
	}
	
	public function guide_admin_notices() {
		if (!$this->pre_check_polylang()) {
			return $this->guide_notice_disable_polylang();
		}
		
		if (!$this->pre_check_wpml()) {
			return $this->guide_notice_activate_wpml();
		}
		
		if ($this->pre_check_wizard_complete() && !$this->migration_page_displayed() && !get_option('mpw_migration_done', false)) {
			return $this->guide_notice_goto_migration_page();
		}
		
	}
	
	private function guide_notice_disable_polylang() {
?>
<div class="notice notice-error">
	<p>
		<?php _e("Before using WPML, you have to deactivate Polylang first!", "migrate-polylang"); ?>
	</p>
</div>
<?php
	}
	
	private function guide_notice_activate_wpml() {
?>
<div class="notice notice-error">
	<p>
		<?php _e("If you want to import Polylang data, please activate WPML Multilingual CMS/Blog first.", "migrate-polylang"); ?>
	</p>
</div>
<?php	
	}
	
	private function migration_page_displayed() {
		global $pagenow, $hook_suffix;
		
		return isset($pagenow) && $pagenow == 'tools.php' && isset($hook_suffix) && $hook_suffix == "tools_page_polylang-importer";
	}
	
	private function guide_notice_goto_migration_page() {
?>
<div class="notice notice-success is-dismissible">
	<p>
		<?php printf(__("You are ready to start migration from Polylang to WPML. Go to <a href='%s'>Tools &gt; Migrate from Polylang to WPML</a> page.", "migrate-polylang"), $this->migration_page_url()); ?>
	</p>
</div>
<?php			
	}
	
	private function migration_page_url() {
		return get_admin_url(null, "tools.php?page=polylang-importer");
	}
	
	public function admin_menu() {

		$title = __("Migrate from Polylang to WPML", 'migrate-polylang');

		add_submenu_page('tools.php', $title, $title, 'manage_options', 'polylang-importer', array( &$this, 'migrate_page' ) );
	}

	public function migrate_page() {
		?>
<div class="wrap">
	<h2><?php _e('Migrate data from Polylang to WPML', 'migrate-polylang'); ?></h2>
<?php 

echo $this->introduction_text();

echo $this->pre_check_text();
if ($this->pre_check_ready_all()) :
	if (get_option('mpw_migration_done', false)) {
		$migrate_button_label = __('Migrate again', 'migrate-polylang');
		$hide_delete_button = "";
	} else {
		$migrate_button_label = __('Migrate', 'migrate-polylang');
		$hide_delete_button = "display: none;";
	}

	
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
			   value="<?php echo $migrate_button_label; ?>"
			   class="button button-primary" disabled >
		<div id="mpw_ajax_result"></div>
		<div id="remove_polylang_data_part" style="<?php echo $hide_delete_button; ?>">
			<h3><?php _e("Optional: erase Polylang data", "migrate-polylang"); ?></h3>
			<label for="remove_polylang_data_accept_1">
				<input type="checkbox" class="remove_polylang_data_accept" name="remove_polylang_data_accept_1" id="remove_polylang_data_accept_1" value="1"> 
					<?php _e("I understand that this will remove all data by Polylang. There is no undo to restore the data.", "migrate-polylang"); ?> <br>
			</label>
			<label for="remove_polylang_data_accept_2">
				<input type="checkbox" class="remove_polylang_data_accept" name="remove_polylang_data_accept_2" id="remove_polylang_data_accept_2" value="1"> 
					<?php _e("I verified the migration and I see that my site displays fine with WPML. ", "migrate-polylang"); ?> <br>
			</label>
		<input type="submit"
			   name="remove-polylang-data"
			   id="remove_polylang_data"
			   value="<?php _e("Erase Polylang old data from database (Optional) ", "migrate-polylang"); ?>"
			   class="button button-secondary"
			   style="margin-top: 5px;" disabled >
		</div>
		<div id="remove_polylang_data_result"></div>

	</form>
<?php
else :
?>
	<div style="color:red;font-weight: bold;"><?php _e("Please make sure all requirements have been met", "migrate-polylang"); ?></div>
	<?php if ($this->polylang_data_deleted()) {
		_e("You have already deleted Polylang data so there is nothing to migrate from.", "migrate-polylang");
	}
endif; ?>
</div>
		<?php
	}
	
	private function introduction_text() {
	$text = "<h3>".__("During migration this plugin will:", "migrate-polylang")."</h3>";
	$text .= "<ul>";
	$text .= "<li><strong>".__("Migrate languages.", "migrate-polylang")."</strong> ".__("It will check what languages were active in Polylang and it will activate them in WPML.")."</li>";
	$text .= "<li><strong>".__("Migrate posts.", "migrate-polylang")."</strong> ".__("Plugin will set correct language for every post and join each other in language relation. This includes also Pages and other custom post types.")."</li>";
	$text .= "<li><strong>".__("Migrate taxonomies.", "migrate-polylang")."</strong> ".__("Similar like with posts: your every category, tag and other custom taxonomies will get correct language assigment and language relation.")."</li>";
	$text .= "<li><strong>".__("Migrate admin strings (only if you are using WPML String Translation).", "migrate-polylang")."</strong> ".__("Plugin will try to find if you have translated any admin string in Polylang and it will try to migrate this translation to WPML. Bear in mind that this probably will not migrate every string - this is because Polylang is handling string translation in much different way than WPML")."</li>";
	$text .= "<li><strong>".__("Migrate widgets (only if you are using <a href='https://wordpress.org/plugins/wpml-widgets/' target='_blank'>WPML Widgets</a>.", "migrate-polylang")."</strong> ".__("If you have created some WordPress widgets while using Polylang and you've set language for them, this plugin will migrate it as well.")."</li>";
	$text .= "</ul>";
		
	return $text;
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
	
	private function polylang_data_deleted() {
		return get_option('mpw_polylang_data_deleted', false);
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
	
	private function pre_check_wpml_st() {
		return defined('WPML_ST_VERSION');
	}

	private function pre_check_ready_all() {
		return !$this->polylang_data_deleted() && $this->pre_check_polylang() and $this->pre_check_wpml() and $this->pre_check_wizard_complete();
	}
	
	public function ajax_migrate_languages() {
		if ($this->pre_check_ready_all()) {
			$this->migrate_languages();
			$response = array(
				'msg' => __("Language settings has been migrated", 'migrate-polylang'),
				'res' => 'ok'
			);
			wp_send_json_success($response);
		}
	}
	
	public function ajax_migrate_posts() {
		if ($this->pre_check_ready_all()) {
			require_once 'classes/class-mpw_migrate_posts.php';
			$mpw_migrate_posts = new mpw_migrate_posts($this->polylang_data);
			$mpw_migrate_posts->migrate_posts();
			$response = array(
				'msg' => __("Posts, pages and custom post types has been migrated", 'migrate-polylang'),
				'res' => 'ok'
			);
			wp_send_json_success($response);
		}
	}
	
	public function ajax_migrate_taxonomies() {
		if ($this->pre_check_ready_all()) {
			$this->migrate_taxonomies();
			$response = array(
				'msg' => __("Taxonomies has been migrated", 'migrate-polylang'),
				'res' => 'ok'
			);
			wp_send_json_success($response);
		}
	}
	
	public function ajax_migrate_strings() {
		if ($this->pre_check_ready_all() && $this->pre_check_wpml_st()) {
			$this->migrate_strings();
			$response = array(
				'msg' => __("String translations has been migrated", 'migrate-polylang'),
				'res' => 'ok'
			);
		} else {
			$response = array(
				'msg' => __("WPML String Translation isn't active, string translation skipped.", 'migrate-polylang'),
				'res' => 'pass'
			);
		}
		wp_send_json_success($response);
	}
	
	public function ajax_migrate_widgets() {
		if ($this->pre_check_ready_all() && $this->pre_check_wpml_widgets()) {
			$this->migrate_widgets();
			$response = array(
				'msg' => __("Widgets has been localized", 'migrate-polylang'),
				'res' => 'ok'
			);
		} else {
			$response = array(
				'msg' => __("WPML Widgets isn't active, localization skipped.", 'migrate-polylang'),
				'res' => 'pass'
			);
		}
		update_option('mpw_migration_done', 1);
		wp_send_json_success($response);
	}
	
	public function ajax_delete_polylang_data() {
		$this->polylang_data->delete_data();
		$response = array(
			'msg' => __("Polylang data removed from database", 'migrate-polylang'),
			'res' => 'ok'
		);
		wp_send_json_success($response);
	}

	private function migrate_languages() {
		global $wpdb;

		$pll_languages = $this->polylang_data->get_languages();

		if (!empty($pll_languages) && is_array($pll_languages)) {
			foreach ($pll_languages as $pll_language) {
				if (isset($pll_language->slug)) {
					$slug = $this->polylang_data->lang_slug_to_wpml_format($pll_language->slug);
					$wpdb->update(
							$wpdb->prefix . 'icl_languages',
							array('active' => 1),
							array('code' => $slug)
							);
				}
			}
		}
	}

	private function migrate_taxonomies() {
		global $wpdb;

		$pll_term_translations = $this->polylang_data->get_term_translations();
		$default_language = $this->polylang_data->lang_slug_to_wpml_format( $this->polylang_data->get_default_language_slug() );

		if (!empty($pll_term_translations) && is_array($pll_term_translations)) {
			foreach ($pll_term_translations as $pll_term_translation) {
				$relation = maybe_unserialize( $pll_term_translation->description );

				if(count($relation) > 1 ){
					$language_code = $default_language;
				}else{
					$language_code = key($relation);
				}
				$language_code = $this->polylang_data->lang_slug_to_wpml_format($language_code);
				
				if (isset($relation[$language_code])) {
					$original_term_id = $relation[$language_code];

					$original_term = $this->get_term_by_term_id( $original_term_id );

					if ( isset( $original_term->taxonomy, $original_term->term_taxonomy_id ) ) {
						$original_term_taxonomy_id = $original_term->term_taxonomy_id;

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
		$polylang_languages_map = $this->polylang_data->get_languages_map();
		
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
		
		$default_language = $this->polylang_data->get_default_language_slug();
		
		$to_language = $polylang_languages_map[$lang_id];
		
		$default_language_wpml_format = $this->polylang_data->lang_slug_to_wpml_format($default_language);
		
		$to_language_wpml_format = $this->polylang_data->lang_slug_to_wpml_format($to_language);
		
		foreach($string_groups as $group) {
			if (isset($wpml_string_translations[$default_language][ $group[0] ])) {
				icl_add_string_translation(
					$wpml_string_translations[$default_language][$group[0]]->id,
					$to_language_wpml_format,
					$group[1],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} else if (isset($wpml_string_translations[$to_language][ $group[0] ])) {
					icl_add_string_translation(
					$wpml_string_translations[$to_language][$group[0]]->id,
					$default_language_wpml_format,
					$group[1],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} else if (isset($wpml_string_translations[$default_language][ $group[1] ])) {
				icl_add_string_translation(
					$wpml_string_translations[$default_language][$group[1]]->id,
					$to_language_wpml_format,
					$group[0],
					ICL_STRING_TRANSLATION_COMPLETE
					);
			} else if (isset($wpml_string_translations[$to_language][ $group[1] ])) {
					icl_add_string_translation(
					$wpml_string_translations[$to_language][$group[1]]->id,
					$default_language_wpml_format,
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
							$option[$key]['wpml_language'] = $this->polylang_data->lang_slug_to_wpml_format($val['pll_lang']);
						}
					}
					update_option($widget->option_name, $option);
				}
			}
		}
	}
	
	
	
}

$migrate_polylang_to_wpml = new Migrate_Polylang_To_WPML();
