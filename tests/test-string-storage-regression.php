<?php
/**
 * Regression test for the string-translation reader's storage precedence.
 *
 * Polylang leaves the old string stores in place when it upgrades them, so a site that has
 * *cleared* its string translations still carries stale copies in the older locations. The
 * reader must therefore treat a newest store that exists but is empty as authoritative, and
 * never fall through to re-import those stale copies.
 *
 * Self-contained on purpose: it runs with plain `php` and no WordPress, database or Composer,
 * so it can be executed before any test infrastructure exists. Exit code 0 = pass, 1 = fail.
 *
 * Run: php tests/test-string-storage-regression.php
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');

// --- Minimal WordPress/WPML stubs: just enough to load the plugin and drive migrate_strings().

$GLOBALS['options']     = array();
$GLOBALS['term_meta']   = array();  // term_id => meta_key => value
$GLOBALS['post_meta']   = array();  // post_id => meta_key => value
$GLOBALS['mo_posts']    = array();  // lang term_id => (object) ID, post_content
$GLOBALS['icl_strings'] = array();  // list of (object) id, language, value
$GLOBALS['st_calls']    = array();  // every icl_add_string_translation() call
$GLOBALS['languages']   = array();  // `language` taxonomy terms as Polylang stores them

if (!defined('ICL_STRING_TRANSLATION_COMPLETE')) {
	define('ICL_STRING_TRANSLATION_COMPLETE', 10);
}

function add_action($hook, $cb = null, $p = 10, $a = 1) {}
function add_filter($hook, $cb = null, $p = 10, $a = 1) {}
function apply_filters($tag, $value) { return $value; }
function do_action($tag, $args = null) {}
function register_taxonomy($tax, $object_type = null, $args = array()) {}
function is_admin() { return true; }
function __($text, $domain = null) { return $text; }

function get_option($name, $default = false) {
	return array_key_exists($name, $GLOBALS['options']) ? $GLOBALS['options'][$name] : $default;
}

// Presence is checked with array_key_exists on purpose: an empty-but-present store must read
// as existing, which is exactly what metadata_exists() guarantees in WordPress.
function metadata_exists($meta_type, $object_id, $meta_key) {
	$store = isset($GLOBALS[$meta_type . '_meta']) ? $GLOBALS[$meta_type . '_meta'] : array();
	return isset($store[$object_id]) && array_key_exists($meta_key, $store[$object_id]);
}

function get_term_meta($id, $key, $single = false) {
	return $GLOBALS['term_meta'][$id][$key] ?? ($single ? '' : array());
}

function get_post_meta($id, $key, $single = false) {
	return $GLOBALS['post_meta'][$id][$key] ?? ($single ? '' : array());
}

function is_serialized($data) {
	if (!is_string($data)) { return false; }
	$data = trim($data);
	if (strlen($data) < 4 || ':' !== $data[1]) { return false; }
	return (bool) preg_match('/^[aOsibdN]:/', $data);
}

function maybe_unserialize($value) {
	if (is_serialized($value)) { return @unserialize(trim($value)); }
	return $value;
}

function icl_add_string_translation($string_id, $language, $value = null, $status = false) {
	$GLOBALS['st_calls'][] = compact('string_id', 'language', 'value', 'status');
	return count($GLOBALS['st_calls']);
}

function get_terms($args) {
	$taxonomy = is_array($args) && isset($args['taxonomy']) ? $args['taxonomy'] : $args;
	return 'language' === $taxonomy ? $GLOBALS['languages'] : array();
}

class Regression_WPDB {
	public $prefix = 'wp_';
	public $posts  = 'wp_posts';

	public function delete($table, $where) { return 1; }

	public function prepare($query, ...$args) {
		return vsprintf(str_replace(array('%d', '%s'), '%s', $query), $args);
	}

	// Resolves the polylang_mo post lookup from get_polylang_mo_post().
	public function get_row($query) {
		if (preg_match('/post_title\s*=\s*polylang_mo_(\d+)/', $query, $m)) {
			return $GLOBALS['mo_posts'][(int) $m[1]] ?? null;
		}
		return null;
	}

	public function get_results($query) {
		if (false !== strpos($query, 'icl_strings')) { return $GLOBALS['icl_strings']; }
		return array();
	}
}

$GLOBALS['wpdb'] = new Regression_WPDB();

// --- Load the plugin.

require dirname(__DIR__) . '/migrate-polylang-to-wpml.php';

/**
 * Runs one migration against the given storage setup and returns the WPML String Translation
 * calls the plugin made.
 */
function run_scenario($name, $setup) {
	$GLOBALS['options']     = array('polylang' => array('default_lang' => 'es'));
	$GLOBALS['term_meta']   = $setup['term_meta'] ?? array();
	$GLOBALS['post_meta']   = $setup['post_meta'] ?? array();
	$GLOBALS['mo_posts']    = $setup['mo_posts'] ?? array();
	$GLOBALS['icl_strings'] = $setup['icl_strings'] ?? array();
	$GLOBALS['st_calls']    = array();

	$es_term = (object) array(
		'term_id' => 2,
		'term_taxonomy_id' => 2,
		'slug' => 'es',
		'name' => 'Espanol',
		'description' => serialize(array('locale' => 'es_ES', 'rtl' => 0, 'flag_code' => 'es')),
	);
	$en_term = (object) array(
		'term_id' => 5,
		'term_taxonomy_id' => 5,
		'slug' => 'en',
		'name' => 'English',
		'description' => serialize(array('locale' => 'en_US', 'rtl' => 0, 'flag_code' => 'us')),
	);
	$GLOBALS['languages'] = array($es_term, $en_term);

	// Reset the terms cache mpw_polylang_data keeps between requests.
	$terms_cache = new ReflectionProperty('mpw_polylang_data', 'terms');
	$terms_cache->setAccessible(true);
	$terms_cache->setValue(null, null);

	$plugin  = new Migrate_Polylang_To_WPML();
	$strings = new ReflectionMethod('Migrate_Polylang_To_WPML', 'migrate_strings');
	$strings->setAccessible(true);
	$strings->invoke($plugin);

	echo "\n=== $name ===\n";
	foreach ($GLOBALS['st_calls'] as $call) {
		printf("  string_id=%-4s -> lang=%-8s value=%s\n", $call['string_id'], $call['language'], var_export($call['value'], true));
	}
	if (!$GLOBALS['st_calls']) {
		echo "  (no strings migrated)\n";
	}

	return $GLOBALS['st_calls'];
}

$failures = 0;
function check($label, $condition) {
	global $failures;
	if ($condition) {
		echo "  PASS  $label\n";
	} else {
		echo "  FAIL  $label\n";
		$failures++;
	}
}

$pairs = array(
	array('Buscar', 'Search'),
	array('Comentarios', 'Comments'),
);

// WPML registers the source strings under the site's default language ("es").
$registry = array(
	(object) array('id' => 11, 'language' => 'es', 'value' => 'Buscar'),
	(object) array('id' => 12, 'language' => 'es', 'value' => 'Comentarios'),
);

// Positive control — populated modern storage migrates normally.
$calls = run_scenario('Populated term meta migrates into WPML ST', array(
	'term_meta'   => array(5 => array('_pll_strings_translations' => $pairs)),
	'icl_strings' => $registry,
));
check('both pairs migrated to the en code', 2 === count($calls)
	&& 'en' === $calls[0]['language'] && 'Search' === $calls[0]['value']
	&& 'Comments' === $calls[1]['value']);

// The regression: the site cleared its translations, so the >= 3.4 term meta exists but is
// empty while the pre-2.1 polylang_mo content still holds the stale copies Polylang kept for
// rollback. The empty-but-present newest store wins; nothing may be imported.
$calls = run_scenario('Cleared translations do not fall back to stale copies', array(
	'term_meta'   => array(5 => array('_pll_strings_translations' => array())),
	'mo_posts'    => array(5 => (object) array('ID' => 900, 'post_content' => serialize($pairs))),
	'icl_strings' => $registry,
));
check('an empty term-meta store is authoritative: the stale copies are not imported', array() === $calls);

echo "\n" . ($failures ? "$failures CHECK(S) FAILED\n" : "ALL CHECKS PASSED\n");
exit($failures ? 1 : 0);
