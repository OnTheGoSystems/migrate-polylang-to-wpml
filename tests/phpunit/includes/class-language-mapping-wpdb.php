<?php
class LanguageMappingWpdb {

	public string $prefix           = 'wp_';
	public array $effective_locales = array();

	private string $prepared_value = '';

	public function prepare( string $query, string $value ): string {
		$this->prepared_value = $value;

		return $query;
	}

	public function get_col( string $query ): array {
		if ( false === strpos( $query, 'COALESCE' ) ) {
			return array();
		}

		return array_keys(
			array_filter(
				$this->effective_locales,
				fn( string $locale ): bool => $this->prepared_value === $locale
			)
		);
	}
}
