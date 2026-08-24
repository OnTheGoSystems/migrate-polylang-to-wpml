<?php
/**
 * Reads Polylang string translations from their version-specific storage.
 *
 * @package MigratePolylangToWPML
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides access to strings stored by different Polylang versions.
 */
class MPW_Polylang_String_Storage {

	/**
	 * Meta key used by current and recent Polylang versions.
	 */
	const META_KEY = '_pll_strings_translations';

	/**
	 * WordPress database access.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Creates storage access using the supplied WordPress database object.
	 *
	 * @param wpdb $wpdb WordPress database access.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Collects Polylang's string translations for every language.
	 *
	 * @param array $polylang_languages_map Language term ID => language slug.
	 *
	 * @return array Language term ID => list of array($source, $translation).
	 */
	public function get_all( $polylang_languages_map ) {
		$polylang_strings = array();

		foreach ( array_keys( $polylang_languages_map ) as $language_id ) {
			$strings = $this->get_for_language( $language_id );

			if ( $strings ) {
				$polylang_strings[ $language_id ] = $strings;
			}
		}

		return $polylang_strings;
	}

	/**
	 * Gets one language's translations from the newest storage location that exists.
	 *
	 * Polylang has moved this data twice:
	 *
	 *   >= 3.4    term meta `_pll_strings_translations` on the `language` term.
	 *   2.1 - 3.3 post meta `_pll_strings_translations` on the polylang_mo post.
	 *   < 2.1     Serialized array in the polylang_mo post's post_content.
	 *
	 * Polylang deliberately leaves older copies behind to support rollbacks. The newest existing
	 * location is therefore authoritative, even when its value is empty.
	 *
	 * @param int $language_id A `language` taxonomy term ID.
	 *
	 * @return array List of array($source, $translation); empty when nothing is stored.
	 */
	public function get_for_language( $language_id ) {
		if ( metadata_exists( 'term', $language_id, self::META_KEY ) ) {
			return $this->normalize_pairs( get_term_meta( $language_id, self::META_KEY, true ) );
		}

		$mo_post = $this->get_mo_post( $language_id );

		if ( ! isset( $mo_post->ID ) ) {
			return array();
		}

		if ( metadata_exists( 'post', $mo_post->ID, self::META_KEY ) ) {
			return $this->normalize_pairs( get_post_meta( $mo_post->ID, self::META_KEY, true ) );
		}

		return $this->normalize_pairs( maybe_unserialize( $mo_post->post_content ) );
	}

	/**
	 * Finds the legacy polylang_mo post for a language.
	 *
	 * @param int $language_id A `language` taxonomy term ID.
	 *
	 * @return object|null The post carrying this language's strings, if it exists.
	 */
	private function get_mo_post( $language_id ) {
		$query = "SELECT ID, post_content FROM {$this->wpdb->posts}
			WHERE post_type = 'polylang_mo' AND post_title = %s
			ORDER BY ID DESC LIMIT 1";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The query is prepared immediately above through the injected wpdb object; this migration must read Polylang's internal storage directly.
		return $this->wpdb->get_row( $this->wpdb->prepare( $query, 'polylang_mo_' . $language_id ) );
	}

	/**
	 * Keeps only well-formed entries from a Polylang string table.
	 *
	 * @param mixed $value Potential list of translation pairs.
	 *
	 * @return array List of array($source, $translation), both non-empty strings.
	 */
	private function normalize_pairs( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$pairs = array();

		foreach ( $value as $pair ) {
			if ( ! is_array( $pair ) || ! isset( $pair[0], $pair[1] ) ) {
				continue;
			}

			if ( ! is_string( $pair[0] ) || ! is_string( $pair[1] ) || '' === $pair[0] || '' === $pair[1] ) {
				continue;
			}

			$pairs[] = array( $pair[0], $pair[1] );
		}

		return $pairs;
	}
}
