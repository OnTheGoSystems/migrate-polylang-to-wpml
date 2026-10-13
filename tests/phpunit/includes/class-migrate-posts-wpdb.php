<?php
/**
 * A stand-in for $wpdb that answers the one query mpw_migrate_posts runs itself:
 * the posts attached to a Polylang language term.
 *
 * `posts_by_language` holds the post IDs per language term_taxonomy_id, in the order
 * the query returns them.
 */
class MigratePostsWpdb {

	public string $prefix           = 'wp_';
	public array $posts_by_language = array();

	private int $term_taxonomy_id = 0;

	public function prepare( string $query, int $term_taxonomy_id ): string {
		$this->term_taxonomy_id = $term_taxonomy_id;

		return $query;
	}

	public function get_results( string $query ): array {
		if ( false === strpos( $query, 'term_relationships' ) ) {
			return array();
		}

		$rows = array();
		foreach ( $this->posts_by_language[ $this->term_taxonomy_id ] ?? array() as $post_id ) {
			// The real wpdb hands every column back as a string.
			$rows[] = (object) array( 'post_id' => (string) $post_id );
		}

		return $rows;
	}
}
