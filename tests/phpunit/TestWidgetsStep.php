<?php
/**
 * Covers when the widgets step runs: WPML alone is enough, because WPML itself reads the
 * `wpml_language` key the step writes. The test run loads WPML's own widget class and
 * not the separate WPML Widgets plugin.
 */
class TestWidgetsStep extends OTGS_TestCase {

	private array $options = array();

	/** @var mixed What the step sent to the page. */
	private $reply;

	public function setUp(): void {
		parent::setUp();

		$this->reply   = null;
		$this->options = array(
			'widget_text' => array(
				2              => array( 'pll_lang' => 'fr' ),
				3              => array( 'title' => 'Shown in every language' ),
				'_multiwidget' => 1,
			),
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The step reads the WordPress database global directly.
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';

			public function get_results( string $query ): array {
				return false !== strpos( $query, "LIKE 'widget_%'" ) ? array( (object) array( 'option_name' => 'widget_text' ) ) : array();
			}
		};

		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'check_ajax_referer', array( 'return' => 1 ) );
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => fn( $name, $fallback = false ) => $this->options[ $name ] ?? $fallback,
			)
		);
		WP_Mock::userFunction(
			'wp_send_json_success',
			array(
				'return' => function ( $data = null ) {
					$this->reply = $data;
				},
			)
		);
		WP_Mock::onFilter( 'wpml_setting' )->with( false, 'setup_complete' )->reply( true );
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_a_widget_gets_its_wpml_language_without_the_wpml_widgets_plugin(): void {
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array(
					'widget_text',
					Mockery::on( fn( $widgets ) => 'fr' === $widgets[2]['wpml_language'] && ! isset( $widgets[3]['wpml_language'] ) ),
				),
			)
		);
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array( 'mpw_migration_done', 1 ),
			)
		);

		$this->plugin()->ajax_migrate_widgets();

		$this->assertSame( 'ok', $this->reply['res'], 'The step should run, not report itself as skipped.' );
	}

	/**
	 * A plugin instance that skipped its constructor, which would register the admin
	 * hooks, with the two objects the step works with.
	 */
	private function plugin(): Migrate_Polylang_To_WPML {
		$polylang = Mockery::mock( 'mpw_polylang_data' );
		$polylang->shouldReceive( 'reset_translations' );
		$polylang->shouldReceive( 'get_languages_not_active_in_wpml' )->andReturn( array() );
		$polylang->shouldReceive( 'lang_slug_to_wpml_format' )->with( 'fr' )->andReturn( 'fr' );

		$htaccess_check = Mockery::mock( 'MPW_Htaccess_Check' );
		$htaccess_check->shouldReceive( 'record_whether_it_applies' )->once();

		$plugin = ( new ReflectionClass( 'Migrate_Polylang_To_WPML' ) )->newInstanceWithoutConstructor();

		// Bound to the plugin object, the closure can set its private properties.
		( function ( $polylang_data, $mpw_htaccess_check ) {
			$this->polylang_data      = $polylang_data;
			$this->mpw_htaccess_check = $mpw_htaccess_check;
		} )->call( $plugin, $polylang, $htaccess_check );

		return $plugin;
	}
}
