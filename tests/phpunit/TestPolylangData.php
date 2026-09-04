<?php

require_once ABSPATH . 'classes/class-mpw_polylang_data.php';

/**
 * Covers mpw_polylang_data: the getters are pure reads and must not modify
 * icl_translations; the cleanup lives in reset_translations(), which only runs
 * for a user with manage_options.
 */
class TestPolylangData extends OTGS_TestCase {

	/** @var PolylangDataWpdb */
	private $wpdb;

	public function setUp(): void {
		parent::setUp();

		// Reset the getter's static per-taxonomy cache between tests.
		$prop = new ReflectionProperty( 'mpw_polylang_data', 'terms' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		$this->wpdb = new PolylangDataWpdb();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The subject under test reads the global $wpdb; swapping in the recording double is the point of the test.
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_get_languages_reads_without_deleting_translations() {
		WP_Mock::userFunction( 'register_taxonomy', array( 'return' => true ) );
		WP_Mock::userFunction(
			'get_terms',
			array(
				'return' => array(
					(object) array(
						'slug'    => 'fr',
						'name'    => 'French',
						'term_id' => 7,
					),
				),
			)
		);

		$subject = new mpw_polylang_data();
		$result  = $subject->get_languages();

		$this->assertCount( 1, $result );
		$this->assertSame( array(), $this->wpdb->deletes, 'a getter must not modify icl_translations' );
	}

	public function test_reset_translations_deletes_for_an_authorized_admin() {
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'args'   => array( 'manage_options' ),
				'return' => true,
			)
		);

		$subject = new mpw_polylang_data();
		$subject->reset_translations( 'language' );

		$this->assertCount( 1, $this->wpdb->deletes );
		$this->assertSame( 'wp_icl_translations', $this->wpdb->deletes[0]['table'] );
		$this->assertSame( array( 'element_type' => 'tax_language' ), $this->wpdb->deletes[0]['where'] );
	}

	public function test_reset_translations_is_a_no_op_without_manage_options() {
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'args'   => array( 'manage_options' ),
				'return' => false,
			)
		);

		$subject = new mpw_polylang_data();
		$subject->reset_translations( 'language' );

		$this->assertSame( array(), $this->wpdb->deletes, 'reset_translations() must do nothing without manage_options' );
	}
}
