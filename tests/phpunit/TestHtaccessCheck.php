<?php
class TestHtaccessCheck extends OTGS_TestCase {

	private string $home;

	public function setUp(): void {
		parent::setUp();

		$this->home = sys_get_temp_dir() . '/mpw-htaccess-' . uniqid() . '/';
		mkdir( $this->home ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- A scratch directory for the test only.

		$_GET['page'] = 'polylang-importer';

		WP_Mock::userFunction( 'get_bloginfo', array( 'return' => 'https://example.test' ) );
		$GLOBALS['mpw_test_home_path'] = $this->home;
		WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 7 ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
	}

	public function tearDown(): void {
		unset( $_GET['page'] );
		rmdir( $this->home ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- The scratch directory of this test.
		parent::tearDown();
	}

	public function test_it_shows_when_polylang_showed_the_default_language_in_urls(): void {
		$this->dismissed( false );
		$this->options(
			array(
				'mpw_migration_done' => 1,
				'polylang'           => array( 'hide_default' => false ),
			)
		);

		$this->assertTrue( $this->subject()->should_display() );
	}

	public function test_it_stays_silent_with_polylang_default_setting(): void {
		$this->dismissed( false );
		$this->options(
			array(
				'mpw_migration_done' => 1,
				'polylang'           => array( 'hide_default' => true ),
			)
		);

		$this->assertFalse( $this->subject()->should_display() );
	}

	public function test_it_stays_silent_when_polylang_data_is_gone(): void {
		$this->dismissed( false );
		$this->options(
			array(
				'mpw_migration_done' => 1,
				'polylang'           => false,
			)
		);

		$this->assertFalse( $this->subject()->should_display() );
	}

	public function test_it_shows_only_on_the_migration_page(): void {
		$this->dismissed( false );
		$_GET['page'] = 'plugins';
		$this->options(
			array(
				'mpw_migration_done' => 1,
				'polylang'           => array( 'hide_default' => false ),
			)
		);

		$this->assertFalse( $this->subject()->should_display() );
	}

	public function test_it_stays_dismissed_for_the_user(): void {
		$this->dismissed( true );
		$this->options(
			array(
				'mpw_migration_done' => 1,
				'polylang'           => array( 'hide_default' => false ),
			)
		);

		$this->assertFalse( $this->subject()->should_display() );
	}

	private function dismissed( bool $dismissed ): void {
		WP_Mock::userFunction( 'get_user_meta', array( 'return' => $dismissed ? '1' : '' ) );
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
