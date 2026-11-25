<?php
/**
 * Covers which registered strings the strings step hands a Polylang translation to.
 * Polylang translated a text wherever it appeared; WPML can have the same text
 * registered several times, and each registration is a string of its own.
 */
class TestStringsStep extends OTGS_TestCase {

	public function setUp(): void {
		parent::setUp();

		$GLOBALS['mpw_test_string_translations'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The step reads the WordPress database global directly.
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';

			/** The rows of icl_strings: "Hello" is registered twice, by a theme and by a plugin. */
			public function get_results( string $query ): array {
				if ( false === strpos( $query, 'wp_icl_strings' ) ) {
					return array();
				}

				return array(
					(object) array(
						'id'       => 5,
						'language' => 'en',
						'value'    => 'Hello',
					),
					(object) array(
						'id'       => 6,
						'language' => 'en',
						'value'    => 'Hello',
					),
					(object) array(
						'id'       => 7,
						'language' => 'en',
						'value'    => 'Goodbye',
					),
				);
			}
		};

		// Polylang's translations for French, stored on the language term.
		WP_Mock::userFunction( 'metadata_exists', array( 'return' => true ) );
		WP_Mock::userFunction(
			'get_term_meta',
			array(
				'return' => array(
					array( 'Hello', 'Bonjour' ),
					array( 'Goodbye', 'Au revoir' ),
				),
			)
		);
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'], $GLOBALS['mpw_test_string_translations'] );
		parent::tearDown();
	}

	public function test_a_text_registered_twice_is_translated_in_both_places(): void {
		$polylang = Mockery::mock( 'mpw_polylang_data' );
		$polylang->shouldReceive( 'reset_translations' );
		$polylang->shouldReceive( 'get_languages_map' )->andReturn( array( 21 => 'fr' ) );
		$polylang->shouldReceive( 'get_default_language_slug' )->andReturn( 'en' );
		$polylang->shouldReceive( 'lang_slug_to_wpml_format' )->andReturnArg( 0 );

		$plugin = ( new ReflectionClass( 'Migrate_Polylang_To_WPML' ) )->newInstanceWithoutConstructor();

		// Bound to the plugin object, the closure can reach its private property and method.
		( function ( $polylang_data ) {
			$this->polylang_data = $polylang_data;
			$this->migrate_strings();
		} )->call( $plugin, $polylang );

		$this->assertSame(
			array(
				array( 5, 'fr', 'Bonjour' ),
				array( 6, 'fr', 'Bonjour' ),
				array( 7, 'fr', 'Au revoir' ),
			),
			$GLOBALS['mpw_test_string_translations']
		);
	}
}
