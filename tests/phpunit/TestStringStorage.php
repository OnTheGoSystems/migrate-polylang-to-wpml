<?php
/**
 * Tests for Polylang string-storage precedence.
 *
 * @package MigratePolylangToWPML
 */

/**
 * Verifies storage precedence and normalization.
 */
class TestStringStorage extends OTGS_TestCase {

	/**
	 * System under test.
	 *
	 * @var MPW_Polylang_String_Storage
	 */
	private $subject;

	/**
	 * Database test double.
	 *
	 * @var StringStorageWpdb
	 */
	private $wpdb;

	/**
	 * Creates the system and database test doubles.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->wpdb    = new StringStorageWpdb();
		$this->subject = new MPW_Polylang_String_Storage( $this->wpdb );
	}

	/**
	 * Reads valid translations from current term metadata.
	 *
	 * @test
	 */
	public function it_reads_populated_term_meta() {
		$language_map = array( 7 => 'fr' );

		WP_Mock::userFunction(
			'metadata_exists',
			array(
				'args'   => array( 'term', 7, MPW_Polylang_String_Storage::META_KEY ),
				'return' => true,
			)
		);
		WP_Mock::userFunction(
			'get_term_meta',
			array(
				'args'   => array( 7, MPW_Polylang_String_Storage::META_KEY, true ),
				'return' => array( array( 'Hello', 'Bonjour' ) ),
			)
		);

		$this->assertSame(
			array( 7 => array( array( 'Hello', 'Bonjour' ) ) ),
			$this->subject->get_all( $language_map )
		);
		$this->assertSame( 0, $this->wpdb->get_row_calls );
	}

	/**
	 * Does not revive stale data when current term metadata has been cleared.
	 *
	 * @test
	 */
	public function empty_term_meta_is_authoritative() {
		$this->wpdb->row = (object) array(
			'ID'           => 42,
			'post_content' => $this->serialize_pairs( array( array( 'Hello', 'Stale translation' ) ) ),
		);

		WP_Mock::userFunction( 'metadata_exists', array( 'return' => true ) );
		WP_Mock::userFunction( 'get_term_meta', array( 'return' => array() ) );

		$this->assertSame( array(), $this->subject->get_for_language( 7 ) );
		$this->assertSame( 0, $this->wpdb->get_row_calls );
	}

	/**
	 * Reads post metadata when no current term metadata exists.
	 *
	 * @test
	 */
	public function it_reads_populated_post_meta_when_term_meta_does_not_exist() {
		$post_meta_pairs = array( array( 'Hello', 'Bonjour' ) );
		$this->wpdb->row = (object) array(
			'ID'           => 42,
			'post_content' => $this->serialize_pairs( array( array( 'Hello', 'Obsolete translation' ) ) ),
		);

		$this->expect_metadata_exists( 'term', 7, false );
		$this->expect_metadata_exists( 'post', 42, true );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, MPW_Polylang_String_Storage::META_KEY, true ),
				'return' => $post_meta_pairs,
			)
		);
		WP_Mock::userFunction( 'maybe_unserialize', array( 'times' => 0 ) );

		$this->assertSame( $post_meta_pairs, $this->subject->get_for_language( 7 ) );
	}

	/**
	 * Does not revive legacy post content when post metadata has been cleared.
	 *
	 * @test
	 */
	public function empty_post_meta_is_authoritative() {
		$this->wpdb->row = (object) array(
			'ID'           => 42,
			'post_content' => $this->serialize_pairs( array( array( 'Hello', 'Stale translation' ) ) ),
		);

		$this->expect_metadata_exists( 'term', 7, false );
		$this->expect_metadata_exists( 'post', 42, true );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => array() ) );
		WP_Mock::userFunction( 'maybe_unserialize', array( 'times' => 0 ) );

		$this->assertSame( array(), $this->subject->get_for_language( 7 ) );
	}

	/**
	 * Reads legacy post content only when neither metadata store exists.
	 *
	 * @test
	 */
	public function it_falls_back_to_legacy_post_content_when_newer_meta_does_not_exist() {
		$legacy_pairs    = array( array( 'Hello', 'Bonjour' ) );
		$legacy_content  = $this->serialize_pairs( $legacy_pairs );
		$this->wpdb->row = (object) array(
			'ID'           => 42,
			'post_content' => $legacy_content,
		);

		$this->expect_metadata_exists( 'term', 7, false );
		$this->expect_metadata_exists( 'post', 42, false );
		WP_Mock::userFunction(
			'maybe_unserialize',
			array(
				'args'   => array( $legacy_content ),
				'return' => $legacy_pairs,
			)
		);

		$this->assertSame( $legacy_pairs, $this->subject->get_for_language( 7 ) );
	}

	/**
	 * Returns an empty list when there is no storage for the language.
	 *
	 * @test
	 */
	public function it_returns_no_strings_when_the_language_has_no_storage() {
		$this->expect_metadata_exists( 'term', 7, false );

		$this->assertSame( array(), $this->subject->get_for_language( 7 ) );
	}

	/**
	 * Filters malformed and incomplete translation pairs.
	 *
	 * @test
	 */
	public function it_ignores_malformed_pairs() {
		WP_Mock::userFunction( 'metadata_exists', array( 'return' => true ) );
		WP_Mock::userFunction(
			'get_term_meta',
			array(
				'return' => array(
					array( 'Hello', 'Bonjour' ),
					array( 'Empty translation', '' ),
					array( '', 'Empty source' ),
					array( 'Missing translation' ),
					array( 123, 'Not a string source' ),
					'not an array',
				),
			)
		);

		$this->assertSame(
			array( array( 'Hello', 'Bonjour' ) ),
			$this->subject->get_for_language( 7 )
		);
	}

	/**
	 * Sets an expectation for a metadata-presence check.
	 *
	 * @param string $meta_type Object type.
	 * @param int    $object_id Object ID.
	 * @param bool   $exists    Expected result.
	 */
	private function expect_metadata_exists( $meta_type, $object_id, $exists ) {
		WP_Mock::userFunction(
			'metadata_exists',
			array(
				'args'   => array( $meta_type, $object_id, MPW_Polylang_String_Storage::META_KEY ),
				'return' => $exists,
			)
		);
	}

	/**
	 * Produces the legacy storage representation used by old Polylang versions.
	 *
	 * @param array $pairs Translation pairs.
	 *
	 * @return string
	 */
	private function serialize_pairs( array $pairs ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Legacy Polylang stored these values with serialize().
		return serialize( $pairs );
	}
}
