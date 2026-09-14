<?php
/**
 * A stand-in for $wpdb with a WPML language table in it.
 *
 * `languages` holds one row per WPML code: array( 'locale' => 'en_US', 'active' => 0 ).
 * `preset_defaults` holds the code WPML 5.0's preset catalogue offers per locale;
 * leave it empty to model a core without that table (4.9 and older).
 */
class LanguageMappingWpdb {

	public string $prefix         = 'wp_';
	public array $languages       = array();
	public array $preset_defaults = array();

	private string $prepared_value = '';

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( string $query, string $value ): string {
		$this->prepared_value = $value;

		return $query;
	}

	/** The locale lookup: rows for the prepared locale, active first, then by code. */
	public function get_results( string $query ): array {
		if ( false === strpos( $query, 'COALESCE' ) ) {
			return array();
		}

		$rows = array();
		foreach ( $this->languages as $code => $language ) {
			if ( $language['locale'] === $this->prepared_value ) {
				$rows[] = (object) array(
					'code'   => $code,
					'active' => (int) $language['active'],
				);
			}
		}

		usort( $rows, fn( $a, $b ) => array( $b->active, $a->code ) <=> array( $a->active, $b->code ) );

		return $rows;
	}

	/** The table check and the preset lookup. */
	public function get_var( string $query ) {
		if ( 0 === strpos( $query, 'SHOW TABLES' ) ) {
			return $this->preset_defaults ? $this->prefix . 'icl_language_preset_countries' : null;
		}

		if ( false !== strpos( $query, 'SELECT active' ) ) {
			return isset( $this->languages[ $this->prepared_value ] ) ? (int) $this->languages[ $this->prepared_value ]['active'] : null;
		}

		return $this->preset_defaults[ $this->prepared_value ] ?? null;
	}
}
