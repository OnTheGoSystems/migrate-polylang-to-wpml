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
		$this->wpdb->effective_locales[ $code ] = $locale;

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

	public function test_it_supports_an_arbitrary_custom_wpml_code(): void {
		$this->wpdb->effective_locales['customers-own-code'] = 'pt_PT';

		$subject = $this->subject_with_languages( array( $this->language( 'pt', 'pt_PT' ) ) );

		$this->assertSame( 'customers-own-code', $subject->lang_slug_to_wpml_format( 'pt' ) );
	}

	public function test_it_records_an_unknown_slug(): void {
		$subject = $this->subject_with_languages( array( $this->language( 'klingon', 'tlh_AA' ) ) );

		$this->assertSame( '', $subject->lang_slug_to_wpml_format( 'klingon' ) );
		$this->assertSame( array( 'klingon' => 'tlh_AA' ), $subject->get_unmapped_languages() );
	}

	public function test_equal_codes_do_not_map_without_a_matching_locale(): void {
		$this->wpdb->effective_locales['es'] = 'es_MX';

		$subject = $this->subject_with_languages( array( $this->language( 'es', 'es_ES' ) ) );

		$this->assertSame( '', $subject->lang_slug_to_wpml_format( 'es' ) );
		$this->assertSame( array( 'es' => 'es_ES' ), $subject->get_unmapped_languages() );
	}

	public function test_an_ambiguous_locale_is_not_mapped_arbitrarily(): void {
		$this->wpdb->effective_locales = array(
			'custom-one' => 'en_US',
			'custom-two' => 'en_US',
		);

		$subject = $this->subject_with_languages( array( $this->language( 'english', 'en_US' ) ) );

		$this->assertSame( '', $subject->lang_slug_to_wpml_format( 'english' ) );
		$this->assertSame( array( 'english' => 'en_US' ), $subject->get_unmapped_languages() );
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
