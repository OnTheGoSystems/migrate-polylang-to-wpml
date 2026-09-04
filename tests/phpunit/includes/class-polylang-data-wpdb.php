<?php
/**
 * Recording $wpdb double: captures delete() calls so tests can assert whether
 * the icl_translations cleanup ran.
 */
class PolylangDataWpdb {

	public string $prefix = 'wp_';
	public array $deletes = array();

	public function delete( string $table, array $where ): int {
		$this->deletes[] = array(
			'table' => $table,
			'where' => $where,
		);

		return 1;
	}
}
