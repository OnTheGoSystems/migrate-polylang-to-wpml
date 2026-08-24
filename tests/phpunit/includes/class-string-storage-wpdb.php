<?php
/**
 * Database test double for the string-storage tests.
 *
 * @package MigratePolylangToWPML
 */

/**
 * Minimal wpdb replacement for string-storage tests.
 */
class StringStorageWpdb {

	/**
	 * WordPress posts-table name.
	 *
	 * @var string
	 */
	public $posts = 'wp_posts';

	/**
	 * Row returned by get_row().
	 *
	 * @var object|null
	 */
	public $row;

	/**
	 * Number of get_row() calls.
	 *
	 * @var int
	 */
	public $get_row_calls = 0;

	/**
	 * Records enough of wpdb::prepare() for the reader under test.
	 *
	 * @param string $query      SQL query.
	 * @param string $post_title Polylang MO post title.
	 *
	 * @return string
	 */
	public function prepare( $query, $post_title ) {
		return $query . $post_title;
	}

	/**
	 * Returns the configured database row.
	 *
	 * @param string $query Prepared query.
	 *
	 * @return object|null
	 */
	public function get_row( $query ) {
		unset( $query );
		++$this->get_row_calls;

		return $this->row;
	}
}
