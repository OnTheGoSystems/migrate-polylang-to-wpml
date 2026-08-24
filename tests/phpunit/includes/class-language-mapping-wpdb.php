<?php
class LanguageMappingWpdb {

	public string $prefix          = 'wp_';
	public array $locale_overrides = array();
	public array $default_locales  = array();
	public array $known_codes      = array();

	private string $prepared_value = '';

	public function prepare( string $query, string $value ): string {
		$this->prepared_value = $value;

		return $query;
	}

	public function get_var( string $query ) {
		if ( false !== strpos( $query, 'icl_locale_map' ) ) {
			return $this->locale_overrides[ $this->prepared_value ] ?? null;
		}

		if ( false !== strpos( $query, 'default_locale' ) ) {
			return $this->default_locales[ $this->prepared_value ] ?? null;
		}

		return in_array( $this->prepared_value, $this->known_codes, true )
			? $this->prepared_value
			: null;
	}
}
