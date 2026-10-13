<?php
/**
 * Covers how mpw_migrate_posts turns Polylang's data into WPML translation groups:
 * a Polylang group is linked under one original, and a post that has a language but no
 * group is migrated on its own, once.
 */
class TestMigratePosts extends OTGS_TestCase {

	const TRID = 77;

	private MigratePostsWpdb $wpdb;

	/** @var array Polylang translation groups: language slug => post ID. */
	private array $groups = array();

	/** @var array The language details WPML received, in order. */
	private array $received = array();

	public function setUp(): void {
		parent::setUp();

		$this->wpdb = new MigratePostsWpdb();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The subject reads the WordPress database global in its constructor.
		$GLOBALS['wpdb'] = $this->wpdb;

		$this->groups   = array();
		$this->received = array();

		WP_Mock::userFunction( 'maybe_unserialize', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_post_type', array( 'return' => 'post' ) );
		WP_Mock::onFilter( 'wpml_element_type' )->with( 'post' )->reply( 'post_post' );
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_a_translation_group_is_linked_under_its_original(): void {
		$this->groups                  = array(
			array(
				'en' => 10,
				'pt' => 11,
			),
		);
		$this->wpdb->posts_by_language = array(
			1 => array( 10 ),
			2 => array( 11 ),
		);

		$original    = $this->watch( $this->original( 10, 'en' ) );
		$translation = $this->watch( $this->translation( 11, 'pt-pt', 'en' ) );
		$this->watch( $this->original( 11, 'pt-pt' ) );

		$this->subject()->migrate_posts();

		$this->assertSame( array( $original, $translation ), $this->received );
	}

	public function test_a_post_without_a_translation_group_is_migrated_on_its_own(): void {
		$this->groups                  = array(
			array(
				'en' => 10,
				'pt' => 11,
			),
		);
		$this->wpdb->posts_by_language = array(
			1 => array( 10 ),
			2 => array( 11, 20 ),
		);

		$original     = $this->watch( $this->original( 10, 'en' ) );
		$translation  = $this->watch( $this->translation( 11, 'pt-pt', 'en' ) );
		$untranslated = $this->watch( $this->original( 20, 'pt-pt' ) );

		$this->subject()->migrate_posts();

		$this->assertSame( array( $original, $translation, $untranslated ), $this->received );
	}

	/**
	 * Corrupt or imported data can attach one post to two language terms.
	 */
	public function test_a_post_under_two_language_terms_is_migrated_once(): void {
		$this->wpdb->posts_by_language = array(
			1 => array( 30 ),
			2 => array( 30 ),
		);

		$first = $this->watch( $this->original( 30, 'en' ) );
		$this->watch( $this->original( 30, 'pt-pt' ) );

		$this->subject()->migrate_posts();

		$this->assertSame( array( $first ), $this->received );
	}

	/**
	 * A group whose description is not a serialised array is skipped; its posts still
	 * carry a language and migrate on their own.
	 */
	public function test_a_corrupt_group_leaves_its_posts_untranslated(): void {
		$this->groups                  = array( 'not a relation' );
		$this->wpdb->posts_by_language = array(
			1 => array( 40 ),
			2 => array( 41 ),
		);

		$english    = $this->watch( $this->original( 40, 'en' ) );
		$portuguese = $this->watch( $this->original( 41, 'pt-pt' ) );
		$this->watch( $this->translation( 41, 'pt-pt', 'en' ) );

		$this->subject()->migrate_posts();

		$this->assertSame( array( $english, $portuguese ), $this->received );
	}

	private function subject(): mpw_migrate_posts {
		$polylang = Mockery::mock( 'mpw_polylang_data' );
		$polylang->shouldReceive( 'get_default_language_slug' )->andReturn( 'en' );
		$polylang->shouldReceive( 'get_languages' )->andReturn(
			array(
				(object) array(
					'slug'             => 'en',
					'term_taxonomy_id' => 1,
				),
				(object) array(
					'slug'             => 'pt',
					'term_taxonomy_id' => 2,
				),
			)
		);
		$polylang->shouldReceive( 'get_post_translations' )->andReturn(
			array_map(
				fn( $group ) => (object) array( 'description' => $group ),
				$this->groups
			)
		);
		$polylang->shouldReceive( 'lang_slug_to_wpml_format' )->andReturnUsing(
			fn( $slug ) => 'pt' === $slug ? 'pt-pt' : $slug
		);

		return new mpw_migrate_posts( $polylang );
	}

	/**
	 * Records one call to WPML when it happens. WP_Mock only reports the calls a test
	 * names, so a test also watches the calls it expects not to see.
	 *
	 * @param array $details The arguments of wpml_set_element_language_details.
	 *
	 * @return array The same details, to compare against what was received.
	 */
	private function watch( array $details ): array {
		WP_Mock::onAction( 'wpml_set_element_language_details' )
			->with( $details )
			->perform(
				function () use ( $details ) {
					$this->received[] = $details;
				}
			);

		return $details;
	}

	/**
	 * The call that starts a translation group. WPML answers it with a group ID.
	 */
	private function original( int $post_id, string $language_code ): array {
		WP_Mock::onFilter( 'wpml_element_language_details' )
			->with(
				null,
				array(
					'element_id'   => $post_id,
					'element_type' => 'post_post',
				)
			)
			->reply( (object) array( 'trid' => self::TRID ) );

		return array(
			'element_id'    => $post_id,
			'element_type'  => 'post_post',
			'trid'          => false,
			'language_code' => $language_code,
		);
	}

	private function translation( int $post_id, string $language_code, string $source_language_code ): array {
		return array(
			'element_id'           => $post_id,
			'element_type'         => 'post_post',
			'trid'                 => self::TRID,
			'language_code'        => $language_code,
			'source_language_code' => $source_language_code,
		);
	}
}
