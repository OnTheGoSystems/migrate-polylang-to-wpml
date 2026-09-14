<?php

defined('ABSPATH') || exit;

class MPW_Htaccess_Check {

	
	private $polylang_data;
	
	private $rewrite_entry = "###";
	
	private $site_url;
	
	private $lang_slug;
	
	public function __construct($polylang_data) {
		$this->polylang_data = $polylang_data;

		add_action('init', array($this, 'run'));
	}

	private function set_urls() {
		$this->site_url = get_bloginfo('url');
		$this->lang_slug = $this->polylang_data->get_default_language_slug();
		$this->rewrite_entry = "RedirectMatch 301 /{$this->lang_slug}/$ {$this->site_url}/index.php";
	}

	public function run() {
		// Built here rather than in the constructor: the constructor runs while the plugin file
		// is still being included, which is too early to be reading options or site metadata.
		$this->set_urls();

		if ('' === $this->lang_slug) {
			return;
		}

		if ($this->should_display()) {
			$this->display_notice();
		}
	}
	
	/** Set by the migration when Polylang showed the default language in URLs. */
	const APPLIES_OPTION = 'mpw_htaccess_redirect_needed';

	/**
	 * The notice is for one kind of site only: Polylang was set to show the default
	 * language in URLs, so the root redirected to /<default>/ and links to that URL now
	 * need a redirect back (wpmlbridge-393). The migration records that fact, because the
	 * cleanup step removes the Polylang options it is read from. The notice then shows on
	 * every admin screen until an administrator dismisses it or the .htaccess line is there.
	 *
	 * @return bool
	 */
	public function should_display() {
		return get_option(self::APPLIES_OPTION, false)
			&& !$this->htaccess_edited();
	}

	/**
	 * Called by the migration while the Polylang options are still there.
	 *
	 * @return bool Whether the redirect advice applies to this site.
	 */
	public function record_whether_it_applies() {
		$applies = $this->polylang_showed_the_default_language_in_urls();

		update_option(self::APPLIES_OPTION, $applies ? 1 : 0);

		return $applies;
	}

	/**
	 * Polylang's "Hide URL language information for default language" is on by default;
	 * when the site owner switched it off, Polylang redirected the root to the default
	 * language's directory.
	 *
	 * @return bool
	 */
	private function polylang_showed_the_default_language_in_urls() {
		$options = get_option('polylang');

		return is_array($options) && array_key_exists('hide_default', $options) && !$options['hide_default'];
	}

	/** Site-wide: one administrator's dismissal is everyone's. */
	public function dismiss() {
		update_option(self::APPLIES_OPTION, 0);
	}

	private function htaccess_edited() {
		if (!function_exists('get_home_path')) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}

		$file_path = get_home_path() . ".htaccess";

		if (!is_readable($file_path)) {
			return false;
		}

		// The previous fopen()/fread() pair leaked the handle and, on an empty .htaccess,
		// called fread() with a length of 0 — a ValueError on PHP 8.
		$file_content = file_get_contents($file_path);

		if (false === $file_content || '' === $file_content) {
			return false;
		}

		return false !== strpos($file_content, $this->rewrite_entry);
	}
	
	private function display_notice() {
		add_action('admin_notices', array($this, 'htaccess_notice_box'));
	}
	
	public function htaccess_notice_box() {
		
		
		$urlto = $this->site_url . "/" . $this->lang_slug;
?>
<div class="notice notice-warning" id="mpw_htaccess_notice">
	<p>
		<?php printf(
			esc_html__("Polylang used to redirect traffic from %1\$s to %2\$s but WPML isn't doing this. If you have incoming links to %3\$s, you should redirect this traffic to your site's root. To do this, add the following line to your .htaccess file:", "migrate-polylang"),
			esc_html($this->site_url),
			esc_html($urlto),
			esc_html($urlto)
		); ?>
		<br><code><?php echo esc_html($this->rewrite_entry); ?></code>
	</p>
	<p>
		<input type="button" name="" value="<?php esc_attr_e("Check .htaccess again", "migrate-polylang"); ?>" class="button" onClick="window.location.reload();">
		<input type="button" name="" value="<?php esc_attr_e("Dismiss this notice", "migrate-polylang"); ?>" class="button" id="mpw_htaccess_notice_dismiss">
		<a href="https://wpml.org/documentation/migrate-polylang-wpml/" target="_blank"><?php esc_html_e("More information and other options", "migrate-polylang"); ?></a>
	</p>
</div>
<?php	
	}
	
}
