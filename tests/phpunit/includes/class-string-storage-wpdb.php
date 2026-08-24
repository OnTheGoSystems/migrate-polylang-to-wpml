<?php
class StringStorageWpdb {

	public string $posts  = 'wp_posts';
	public ?object $row   = null;
	public array $queries = array();

	public function prepare( string $query, string $post_title ): string {
		return $query . $post_title;
	}

	public function get_row( string $query ): ?object {
		$this->queries[] = $query;

		return $this->row;
	}
}
