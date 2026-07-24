<?php

/*
Plugin Name: Migrate Polylang to WPML
Description: Import multilingual data from Polylang to WPML | <a href="https://wpml.org/documentation/related-projects/migrate-polylang-wpml/">Documentation</a>
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com/
Plugin uri: https://wpml.org
Version: 0.5.2
 */

defined('ABSPATH') || exit;

class Migrate_Polylang_To_WPML {

	/**
	 * Nonce action shared by every AJAX endpoint of this plugin.
	 */
	const NONCE_ACTION = 'mpw_migrate';

	/**
	 * Capability required to run or undo a migration.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Meta key Polylang stores its string translations under, on the `language` term since
	 * Polylang 3.4 and on the polylang_mo post from 2.1 to 3.3.
	 */
	const PLL_STRINGS_META_KEY = '_pll_strings_translations';

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
				'nonce' => wp_create_nonce(self::NONCE_ACTION),

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
		<?php esc_html_e("Before using WPML, you have to deactivate Polylang first!", "migrate-polylang"); ?>
	</p>
</div>
<?php
	}

	private function guide_notice_activate_wpml() {
?>
<div class="notice notice-error">
	<p>
		<?php esc_html_e("If you want to import Polylang data, please activate WPML Multilingual CMS/Blog first.", "migrate-polylang"); ?>
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
		<?php printf(
			wp_kses_post(__("You are ready to start migration from Polylang to WPML. Go to <a href='%s'>Tools &gt; Migrate from Polylang to WPML</a> page.", "migrate-polylang")),
			esc_url($this->migration_page_url())
		); ?>
	</p>
</div>
<?php
	}

	private function migration_page_url() {
		return get_admin_url(null, "tools.php?page=polylang-importer");
	}

	public function admin_menu() {

		$title = __("Migrate from Polylang to WPML", 'migrate-polylang');

		add_submenu_page('tools.php', $title, $title, self::CAPABILITY, 'polylang-importer', array( &$this, 'migrate_page' ) );
	}

	public function migrate_page() {

		// add_submenu_page() already gates the menu entry, but the callback can be reached by
		// other means, so re-check rather than assume.
		if (!current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__("You don't have permission to access this page.", 'migrate-polylang'));
		}

		?>
<div class="wrap">
	<h2><?php esc_html_e('Migrate data from Polylang to WPML', 'migrate-polylang'); ?></h2>
<?php

echo wp_kses_post($this->introduction_text());

echo wp_kses_post($this->pre_check_text());
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
		 <?php echo wp_kses_post(__("I confirm that I've created <a href='https://codex.wordpress.org/Backing_Up_Your_Database' target='_blank'>database backup</a>", "migrate-polylang")); ?>
		</label> <br>
		<input type="hidden" name="migrate_wpml_action" value="migrate" />
		<input type="submit"
			   name="migrate-polylang-wpml"
			   id="migrate_polylang_wpml"
			   value="<?php echo esc_attr($migrate_button_label); ?>"
			   class="button button-primary" disabled >
		<div id="mpw_ajax_result"></div>
		<div id="remove_polylang_data_part" style="<?php echo esc_attr($hide_delete_button); ?>">
			<h3><?php esc_html_e("Optional: erase Polylang data", "migrate-polylang"); ?></h3>
			<label for="remove_polylang_data_accept_1">
				<input type="checkbox" class="remove_polylang_data_accept" name="remove_polylang_data_accept_1" id="remove_polylang_data_accept_1" value="1">
					<?php esc_html_e("I understand that this will remove all data by Polylang. There is no undo to restore the data.", "migrate-polylang"); ?> <br>
			</label>
			<label for="remove_polylang_data_accept_2">
				<input type="checkbox" class="remove_polylang_data_accept" name="remove_polylang_data_accept_2" id="remove_polylang_data_accept_2" value="1">
					<?php esc_html_e("I verified the migration and I see that my site displays fine with WPML. ", "migrate-polylang"); ?> <br>
			</label>
		<input type="submit"
			   name="remove-polylang-data"
			   id="remove_polylang_data"
			   value="<?php esc_attr_e("Erase Polylang old data from database (Optional) ", "migrate-polylang"); ?>"
			   class="button button-secondary"
			   style="margin-top: 5px;" disabled >
		</div>
		<div id="remove_polylang_data_result"></div>

	</form>
<?php
else :
?>
	<div style="color:red;font-weight: bold;"><?php esc_html_e("Please make sure all requirements have been met", "migrate-polylang"); ?></div>
	<?php if ($this->polylang_data_deleted()) {
		esc_html_e("You have already deleted Polylang data so there is nothing to migrate from.", "migrate-polylang");
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
		return !$this->polylang_data_deleted()
			&& $this->pre_check_polylang()
			&& $this->pre_check_wpml()
			&& $this->pre_check_wizard_complete();
	}

	/**
	 * Rejects any AJAX request that isn't a deliberate action by an authorised administrator.
	 *
	 * Both halves matter. The capability check stops lower-privileged users from calling these
	 * endpoints directly; the nonce stops a third-party page from driving them through a
	 * logged-in administrator's browser.
	 *
	 * Sends a JSON error and terminates the request when either check fails.
	 *
	 * @return bool True when the request may proceed.
	 */
	private function verify_ajax_request() {
		if (!current_user_can(self::CAPABILITY)) {
			wp_send_json_error(array(
				'msg' => __("You don't have permission to perform this action.", 'migrate-polylang'),
				'res' => 'error'
			), 403);

			return false;
		}

		if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
			wp_send_json_error(array(
				'msg' => __("Security check failed. Please reload the page and try again.", 'migrate-polylang'),
				'res' => 'error'
			), 403);

			return false;
		}

		return true;
	}

	public function ajax_migrate_languages() {
		$this->verify_ajax_request();

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
		$this->verify_ajax_request();

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
		$this->verify_ajax_request();

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
		$this->verify_ajax_request();

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
		$this->verify_ajax_request();

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
		$this->verify_ajax_request();

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

	/**
	 * Links every Polylang term translation group into a WPML translation group.
	 *
	 * Polylang keys a relation by its own language *slugs*, so every array lookup here uses a
	 * slug. Slugs are converted to WPML language codes only at the point of handing them to WPML.
	 * Mixing the two is what made this method skip Portuguese and Chinese groups entirely.
	 */
	private function migrate_taxonomies() {
		$pll_term_translations = $this->polylang_data->get_term_translations();

		if (empty($pll_term_translations) || !is_array($pll_term_translations)) {
			return;
		}

		$default_language_slug = $this->polylang_data->get_default_language_slug();

		foreach ($pll_term_translations as $pll_term_translation) {
			// Polylang can leave stale translation groups behind after terms are relinked.
			if (empty($pll_term_translation->count)) {
				continue;
			}

			if (!isset($pll_term_translation->description)) {
				continue;
			}

			$relation = maybe_unserialize($pll_term_translation->description);

			// Polylang can leave behind a corrupted or partially written description.
			if (!is_array($relation)) {
				continue;
			}

			unset($relation['sync']);

			$original_slug = $this->pick_original_language_slug($relation, $default_language_slug);

			if (null === $original_slug) {
				continue;
			}

			$original_term = $this->get_term_by_term_id($relation[$original_slug]);

			if (!isset($original_term->taxonomy, $original_term->term_taxonomy_id)) {
				continue;
			}

			$element_type = apply_filters('wpml_element_type', $original_term->taxonomy);
			$original_language_code = $this->polylang_data->lang_slug_to_wpml_format($original_slug);

			do_action('wpml_set_element_language_details', array(
				'element_id' => $original_term->term_taxonomy_id,
				'element_type' => $element_type,
				'trid' => false,
				'language_code' => $original_language_code
			));

			$original_language_details = apply_filters('wpml_element_language_details', null, array(
				'element_id' => $original_term->term_taxonomy_id,
				'element_type' => $element_type
			));

			// WPML returns null when it refuses the element, for instance when the taxonomy has
			// not been set translatable. Nothing can be linked to it, so move on.
			if (!isset($original_language_details->trid)) {
				continue;
			}

			$trid = $original_language_details->trid;

			unset($relation[$original_slug]);

			foreach ($relation as $translation_slug => $term_id) {
				$translated_term = $this->get_term_by_term_id($term_id);

				if (!isset($translated_term->term_taxonomy_id)) {
					continue;
				}

				do_action('wpml_set_element_language_details', array(
					'element_id' => $translated_term->term_taxonomy_id,
					'element_type' => $element_type,
					'trid' => $trid,
					'language_code' => $this->polylang_data->lang_slug_to_wpml_format($translation_slug),
					'source_language_code' => $original_language_code
				));
			}
		}
	}

	/**
	 * Chooses which entry of a Polylang translation group acts as the original.
	 *
	 * The site's default language wins when it is present. Groups that only exist in secondary
	 * languages used to be dropped on the floor, leaving those terms with no language at all in
	 * WPML; fall back to the first usable entry so they migrate as a linked group instead.
	 *
	 * @param array  $relation              Translation group, keyed by Polylang language slug.
	 * @param string $default_language_slug Polylang's default language slug.
	 *
	 * @return string|null A language slug, or null when the group holds nothing usable.
	 */
	private function pick_original_language_slug($relation, $default_language_slug) {
		if ('' !== $default_language_slug && !empty($relation[$default_language_slug])) {
			return $default_language_slug;
		}

		foreach ($relation as $slug => $object_id) {
			if (is_string($slug) && !empty($object_id)) {
				return $slug;
			}
		}

		return null;
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

	/**
	 * Collects Polylang's string translations for every language.
	 *
	 * Polylang has moved this data twice and neither move was picked up here, so the plugin has
	 * been reading a post_content that Polylang stopped writing in 2017:
	 *
	 *   >= 3.4    term meta `_pll_strings_translations` on the `language` term
	 *   2.1 - 3.3 post meta `_pll_strings_translations` on the polylang_mo post
	 *   < 2.1     serialised array in the polylang_mo post's post_content
	 *
	 * Polylang deliberately leaves the older copies behind when it upgrades, so that users can
	 * roll back. Read newest first and stop at the first location that holds anything.
	 *
	 * @param array $polylang_languages_map language term_id => language slug
	 *
	 * @return array language term_id => list of array($source, $translation)
	 */
	private function get_polylang_strings_array($polylang_languages_map) {
		$polylang_strings_array = array();

		foreach (array_keys($polylang_languages_map) as $lang_id) {
			$strings = $this->get_polylang_language_strings($lang_id);

			if ($strings) {
				$polylang_strings_array[$lang_id] = $strings;
			}
		}

		return $polylang_strings_array;
	}

	/**
	 * @param int $lang_id A `language` taxonomy term ID.
	 *
	 * @return array List of array($source, $translation); empty when nothing is stored.
	 */
	private function get_polylang_language_strings($lang_id) {

		// Polylang >= 3.4.
		$strings = $this->normalize_string_pairs(get_term_meta($lang_id, self::PLL_STRINGS_META_KEY, true));

		if ($strings) {
			return $strings;
		}

		$mo_post = $this->get_polylang_mo_post($lang_id);

		if (!isset($mo_post->ID)) {
			return array();
		}

		// Polylang 2.1 - 3.3.
		$strings = $this->normalize_string_pairs(get_post_meta($mo_post->ID, self::PLL_STRINGS_META_KEY, true));

		if ($strings) {
			return $strings;
		}

		// Polylang < 2.1.
		return $this->normalize_string_pairs(maybe_unserialize($mo_post->post_content));
	}

	/**
	 * @param int $lang_id A `language` taxonomy term ID.
	 *
	 * @return object|null The polylang_mo post carrying this language's strings, if it exists.
	 */
	private function get_polylang_mo_post($lang_id) {
		global $wpdb;

		$query = "SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_type = 'polylang_mo' AND post_title = %s
			ORDER BY ID DESC LIMIT 1";

		return $wpdb->get_row($wpdb->prepare($query, 'polylang_mo_' . $lang_id));
	}

	/**
	 * Keeps only the well-formed entries of a Polylang string table.
	 *
	 * Every storage location holds the same shape — a list of array($source, $translation) — but
	 * a partially written or hand-edited row can hold anything. Entries with an empty source are
	 * skipped (as Polylang skips them on read), and so are entries with an empty translation: there
	 * is nothing to hand WPML, and writing a blank translation would be worse than writing none.
	 *
	 * @param mixed $value
	 *
	 * @return array List of array($source, $translation), both non-empty strings.
	 */
	private function normalize_string_pairs($value) {
		if (!is_array($value)) {
			return array();
		}

		$pairs = array();

		foreach ($value as $pair) {
			if (!is_array($pair) || !isset($pair[0], $pair[1])) {
				continue;
			}

			if (!is_string($pair[0]) || !is_string($pair[1]) || '' === $pair[0] || '' === $pair[1]) {
				continue;
			}

			$pairs[] = array($pair[0], $pair[1]);
		}

		return $pairs;
	}

	/**
	 * Hands one language's Polylang string translations to WPML String Translation.
	 *
	 * `$wpml_string_translations` is indexed by `icl_strings.language`, which holds WPML language
	 * codes. Both lookups therefore have to use the converted code, not the Polylang slug.
	 *
	 * @param int   $lang_id                  A `language` taxonomy term ID.
	 * @param array $string_groups            List of array($source, $translation).
	 * @param array $polylang_languages_map   language term_id => Polylang slug.
	 * @param array $wpml_string_translations WPML code => string value => row.
	 */
	private function migrate_string_groups($lang_id, $string_groups, $polylang_languages_map, $wpml_string_translations) {
		if (!isset($polylang_languages_map[$lang_id]) || !function_exists('icl_add_string_translation')) {
			return;
		}

		$from = $this->polylang_data->lang_slug_to_wpml_format($this->polylang_data->get_default_language_slug());
		$to = $this->polylang_data->lang_slug_to_wpml_format($polylang_languages_map[$lang_id]);

		if ('' === $from || '' === $to || $from === $to) {
			return;
		}

		$status = defined('ICL_STRING_TRANSLATION_COMPLETE') ? ICL_STRING_TRANSLATION_COMPLETE : 10;

		foreach ($string_groups as $group) {
			// Polylang stores (source in the default language, translation in $to). WPML may have
			// registered the string under either language, and occasionally under the translated
			// value rather than the source, so try each combination in turn.
			$candidates = array(
				array($from, $group[0], $to, $group[1]),
				array($to, $group[0], $from, $group[1]),
				array($from, $group[1], $to, $group[0]),
				array($to, $group[1], $from, $group[0]),
			);

			foreach ($candidates as $candidate) {
				list($registered_language, $registered_value, $target_language, $target_value) = $candidate;

				if (!isset($wpml_string_translations[$registered_language][$registered_value])) {
					continue;
				}

				icl_add_string_translation(
					$wpml_string_translations[$registered_language][$registered_value]->id,
					$target_language,
					$target_value,
					$status
				);

				break;
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
						if (is_numeric($key) && is_array($val) && isset($val['pll_lang'])) {
							$option[$key]['wpml_language'] = $this->polylang_data->lang_slug_to_wpml_format($val['pll_lang']);
						}
					}
					update_option($widget->option_name, $option);
				}
			}
		}
	}



}

// Everything this plugin does lives in wp-admin or admin-ajax.php, both of which satisfy
// is_admin(). Loading it on front-end requests only cost two file includes and an init hook.
if (is_admin()) {
	$migrate_polylang_to_wpml = new Migrate_Polylang_To_WPML();
}
