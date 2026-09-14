<?php
class TestLanguageMapping extends OTGS_TestCase {

	private LanguageMappingWpdb $wpdb;

	public function setUp(): void {
		parent::setUp();

		$this->wpdb = new LanguageMappingWpdb();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The subject reads the WordPress database global directly.
		$GLOBALS['wpdb'] = $this->wpdb;

		WP_Mock::userFunction( 'is_serialized', array( 'return' => false ) );
	}

	/**
	 * @dataProvider localeMappings
	 */
	public function test_it_maps_polylang_slugs_to_wpml_codes( string $slug, string $locale, string $code ): void {
		$this->wpdb->languages[ $code ] = array(
			'locale' => $locale,
			'active' => 1,
		);

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
		$this->wpdb->languages['customers-own-code'] = array(
			'locale' => 'pt_PT',
			'active' => 1,
		);

		$subject = $this->subject_with_languages( array( $this->language( 'pt', 'pt_PT' ) ) );

		$this->assertSame( 'customers-own-code', $subject->lang_slug_to_wpml_format( 'pt' ) );
	}

	/**
	 * WPML 5.0 keeps `en` next to `en-us`, both en_US. The wizard activated `en-us`.
	 */
	public function test_it_prefers_the_active_code_when_a_locale_matches_several(): void {
		$this->wpdb->languages['en']    = array(
			'locale' => 'en_US',
			'active' => 0,
		);
		$this->wpdb->languages['en-us'] = array(
			'locale' => 'en_US',
			'active' => 1,
		);

		$subject = $this->subject_with_languages( array( $this->language( 'en', 'en_US' ) ) );

		$this->assertSame( 'en-us', $subject->lang_slug_to_wpml_format( 'en' ) );
	}

	/**
	 * An upgraded site keeps its legacy `es-es` active; the 5.0 preset `es` is not.
	 */
	public function test_the_active_code_wins_over_the_preset_default(): void {
		$this->wpdb->languages['es']    = array(
			'locale' => 'es_ES',
			'active' => 0,
		);
		$this->wpdb->languages['es-es'] = array(
			'locale' => 'es_ES',
			'active' => 1,
		);
		$this->wpdb->preset_defaults    = array( 'es_ES' => 'es' );

		$subject = $this->subject_with_languages( array( $this->language( 'es', 'es_ES' ) ) );

		$this->assertSame( 'es-es', $subject->lang_slug_to_wpml_format( 'es' ) );
	}

	/**
	 * The wizard did not add the language: WPML 5.0's preset catalogue names the code.
	 */
	public function test_it_falls_back_to_the_preset_default_when_none_is_active(): void {
		$this->wpdb->languages['en']    = array(
			'locale' => 'en_US',
			'active' => 0,
		);
		$this->wpdb->languages['en-us'] = array(
			'locale' => 'en_US',
			'active' => 0,
		);
		$this->wpdb->preset_defaults    = array( 'en_US' => 'en-us' );

		$subject = $this->subject_with_languages( array( $this->language( 'en', 'en_US' ) ) );

		$this->assertSame( 'en-us', $subject->lang_slug_to_wpml_format( 'en' ) );
	}

	/**
	 * A core without the preset table has one code per locale, so the first one is it.
	 */
	public function test_it_takes_the_first_code_without_a_preset_table(): void {
		$this->wpdb->languages['pt-br'] = array(
			'locale' => 'pt_BR',
			'active' => 0,
		);

		$subject = $this->subject_with_languages( array( $this->language( 'pt', 'pt_BR' ) ) );

		$this->assertSame( 'pt-br', $subject->lang_slug_to_wpml_format( 'pt' ) );
	}

	/**
	 * Same Polylang slug, different locales: the locale decides, never the slug.
	 */
	public function test_it_tells_regional_variants_apart_by_locale(): void {
		$this->wpdb->languages['es']    = array(
			'locale' => 'es_ES',
			'active' => 1,
		);
		$this->wpdb->languages['es-mx'] = array(
			'locale' => 'es_MX',
			'active' => 1,
		);
		$this->wpdb->languages['pt-pt'] = array(
			'locale' => 'pt_PT',
			'active' => 1,
		);
		$this->wpdb->languages['pt-br'] = array(
			'locale' => 'pt_BR',
			'active' => 1,
		);

		$subject = $this->subject_with_languages( array( $this->language( 'es', 'es_MX' ), $this->language( 'pt', 'pt_BR' ) ) );

		$this->assertSame( 'es-mx', $subject->lang_slug_to_wpml_format( 'es' ) );
		$this->assertSame( 'pt-br', $subject->lang_slug_to_wpml_format( 'pt' ) );
	}

	/**
	 * The migration writes only to active WPML languages; the rest are listed for the owner.
	 */
	public function test_it_lists_the_polylang_languages_wpml_does_not_have_active(): void {
		$this->wpdb->languages['en-us'] = array(
			'locale' => 'en_US',
			'active' => 1,
		);
		$this->wpdb->languages['ja']    = array(
			'locale' => 'ja',
			'active' => 0,
		);

		$subject = $this->subject_with_languages(
			array(
				$this->language( 'en', 'en_US' ),
				$this->language( 'ja', 'ja' ),
				$this->language( 'klingon', 'tlh_AA' ),
			)
		);

		$this->assertSame(
			array(
				'ja'      => 'ja',
				'klingon' => 'tlh_AA',
			),
			$subject->get_languages_not_active_in_wpml()
		);
	}

	public function test_it_records_an_unknown_slug(): void {
		$subject = $this->subject_with_languages( array( $this->language( 'klingon', 'tlh_AA' ) ) );

		$this->assertSame( '', $subject->lang_slug_to_wpml_format( 'klingon' ) );
		$this->assertSame( array( 'klingon' => 'tlh_AA' ), $subject->get_unmapped_languages() );
	}

	public function test_equal_codes_do_not_map_without_a_matching_locale(): void {
		$this->wpdb->languages['es'] = array(
			'locale' => 'es_MX',
			'active' => 1,
		);

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
