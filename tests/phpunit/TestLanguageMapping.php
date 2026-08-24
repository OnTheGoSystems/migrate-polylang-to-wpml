<?php
class TestLanguageMapping extends OTGS_TestCase {

	private LanguageMappingWpdb $wpdb;

	public function setUp(): void {
		parent::setUp();

		$this->wpdb = new LanguageMappingWpdb();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The subject reads the WordPress database global directly.
		$GLOBALS['wpdb'] = $this->wpdb;

		WP_Mock::userFunction( 'maybe_unserialize', array( 'return_arg' => 0 ) );
	}

	/**
	 * @dataProvider localeMappings
	 */
	public function test_it_maps_polylang_slugs_to_wpml_codes( string $slug, string $locale, string $code ): void {
		$this->wpdb->default_locales[ $locale ] = $code;

		$subject = $this->subject_with_languages( array( $this->language( $slug, $locale ) ) );

		$this->assertSame( $code, $subject->lang_slug_to_wpml_format( $slug ) );
	}

	public function localeMappings(): array {
		return array(
			'Traditional Chinese' => array( 'zh', 'zh_TW', 'zh-hant' ),
			'Simplified Chinese'  => array( 'zh', 'zh_CN', 'zh-hans' ),
			'Norwegian Bokmal'    => array( 'no', 'nb_NO', 'nb' ),
			'custom English slug' => array( 'english', 'en_US', 'en' ),
			'Portuguese'          => array( 'pt', 'pt_PT', 'pt-pt' ),
		);
	}

	public function test_locale_override_takes_precedence_over_the_default_locale(): void {
		$this->wpdb->locale_overrides['pt_PT'] = 'custom-pt';
		$this->wpdb->default_locales['pt_PT']  = 'pt-pt';

		$subject = $this->subject_with_languages( array( $this->language( 'pt', 'pt_PT' ) ) );

		$this->assertSame( 'custom-pt', $subject->lang_slug_to_wpml_format( 'pt' ) );
	}

	/**
	 * @dataProvider legacyMappings
	 */
	public function test_it_keeps_legacy_fallbacks_when_wpml_tables_do_not_match( string $slug, string $locale, string $code ): void {
		$subject = $this->subject_with_languages( array( $this->language( $slug, $locale ) ) );

		$this->assertSame( $code, $subject->lang_slug_to_wpml_format( $slug ) );
	}

	public function legacyMappings(): array {
		return array(
			'Brazilian Portuguese' => array( 'pt', 'pt_BR', 'pt-br' ),
			'Traditional Chinese'  => array( 'zh', 'zh_HK', 'zh-hant' ),
			'Simplified Chinese'   => array( 'zh', 'zh_CN', 'zh-hans' ),
		);
	}

	public function test_it_records_an_unknown_slug(): void {
		$subject = $this->subject_with_languages( array( $this->language( 'klingon', 'tlh_AA' ) ) );

		$this->assertSame( 'klingon', $subject->lang_slug_to_wpml_format( 'klingon' ) );
		$this->assertSame( array( 'klingon' => 'tlh_AA' ), $subject->get_unmapped_languages() );
	}

	public function test_it_accepts_a_known_slug_when_its_locale_does_not_match(): void {
		$this->wpdb->known_codes = array( 'es' );

		$subject = $this->subject_with_languages( array( $this->language( 'es', '' ) ) );

		$this->assertSame( 'es', $subject->lang_slug_to_wpml_format( 'es' ) );
		$this->assertSame( array(), $subject->get_unmapped_languages() );
	}

	/**
	 * @dataProvider unusableSlugs
	 */
	public function test_it_rejects_unusable_slugs( $slug ): void {
		$subject = $this->subject_with_languages( array() );

		$this->assertSame( '', $subject->lang_slug_to_wpml_format( $slug ) );
	}

	public function unusableSlugs(): array {
		return array(
			'empty string' => array( '' ),
			'array'        => array( array( 'en' ) ),
			'object'       => array( (object) array( 'slug' => 'en' ) ),
		);
	}

	private function subject_with_languages( array $languages ): mpw_polylang_data {
		$terms = new ReflectionProperty( 'mpw_polylang_data', 'terms' );
		$terms->setAccessible( true );
		$terms->setValue( null, array( 'language' => $languages ) );

		return new mpw_polylang_data();
	}

	private function language( string $slug, string $locale ): object {
		return (object) array(
			'slug'        => $slug,
			'description' => array( 'locale' => $locale ),
		);
	}
}
