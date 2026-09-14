<?php
class TestHtaccessCheck extends OTGS_TestCase {

	private string $home;

	public function setUp(): void {
		parent::setUp();

		$this->home = sys_get_temp_dir() . '/mpw-htaccess-' . uniqid() . '/';
		mkdir( $this->home ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- A scratch directory for the test only.
		$GLOBALS['mpw_test_home_path'] = $this->home;

		WP_Mock::userFunction( 'get_bloginfo', array( 'return' => 'https://example.test' ) );
		WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 7 ) );
	}

	public function tearDown(): void {
		rmdir( $this->home ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- The scratch directory of this test.
		parent::tearDown();
	}

	public function test_the_migration_records_that_polylang_showed_the_default_language_in_urls(): void {
		$this->options( array( 'polylang' => array( 'hide_default' => false ) ) );
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array( MPW_Htaccess_Check::APPLIES_OPTION, 1 ),
			)
		);

		$this->assertTrue( $this->subject()->record_whether_it_applies() );
	}

	public function test_the_migration_records_nothing_to_do_with_polylang_default_setting(): void {
		$this->options( array( 'polylang' => array( 'hide_default' => true ) ) );
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array( MPW_Htaccess_Check::APPLIES_OPTION, 0 ),
			)
		);

		$this->assertFalse( $this->subject()->record_whether_it_applies() );
	}

	public function test_the_migration_records_nothing_to_do_without_polylang_options(): void {
		$this->options( array( 'polylang' => false ) );
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array( MPW_Htaccess_Check::APPLIES_OPTION, 0 ),
			)
		);

		$this->assertFalse( $this->subject()->record_whether_it_applies() );
	}

	public function test_it_shows_while_the_redirect_is_needed(): void {
		$this->options( array( MPW_Htaccess_Check::APPLIES_OPTION => 1 ) );

		$this->assertTrue( $this->subject()->should_display() );
	}

	public function test_it_stays_silent_when_the_migration_recorded_nothing_to_do(): void {
		$this->options( array( MPW_Htaccess_Check::APPLIES_OPTION => 0 ) );

		$this->assertFalse( $this->subject()->should_display() );
	}

	public function test_dismissing_it_is_site_wide(): void {
		$this->options( array( MPW_Htaccess_Check::APPLIES_OPTION => 1 ) );
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array( MPW_Htaccess_Check::APPLIES_OPTION, 0 ),
			)
		);

		$this->expectNotToPerformAssertions();

		$this->subject()->dismiss();
	}

	public function test_it_stops_once_the_htaccess_line_is_there(): void {
		$this->options( array( MPW_Htaccess_Check::APPLIES_OPTION => 1 ) );
		file_put_contents( $this->home . '.htaccess', "RedirectMatch 301 /en/$ https://example.test/index.php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- The scratch .htaccess of this test.

		$this->assertFalse( $this->subject()->should_display() );

		unlink( $this->home . '.htaccess' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- The scratch .htaccess of this test.
	}

	private function options( array $options ): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => fn( string $name, $fallback = false ) => $options[ $name ] ?? $fallback,
			)
		);
	}

	private function subject(): MPW_Htaccess_Check {
		$polylang_data = $this->getMockBuilder( mpw_polylang_data::class )->disableOriginalConstructor()->getMock();
		$polylang_data->method( 'get_default_language_slug' )->willReturn( 'en' );

		WP_Mock::expectActionAdded( 'init', array( WP_Mock\Functions::type( MPW_Htaccess_Check::class ), 'run' ) );

		$subject = new MPW_Htaccess_Check( $polylang_data );
		$subject->run();

		return $subject;
	}
}
