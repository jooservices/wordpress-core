<?php
/**
 * Demo importer lifecycle against real WordPress tables.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Integration;

use JOOservices\Resume\Demo\DemoImporter;
use JOOservices\Resume\Plugin;
use JOOservices\TestKit\IntegrationTestCase;
use RuntimeException;

final class DemoImporterTest extends IntegrationTestCase {

	/**
	 * Fixture package path.
	 *
	 * @var string
	 */
	private string $package;

	public function set_up(): void {
		parent::set_up();
		Plugin::register();
		register_nav_menus(
			array(
				'primary' => 'Primary menu',
				'footer'  => 'Footer menu',
			)
		);
		$this->package = DemoPackageFixture::create();
	}

	public function tear_down(): void {
		if ( isset( $this->package ) && is_file( $this->package ) ) {
			( new DemoImporter() )->rollback();
			unlink( $this->package );
		}
		parent::tear_down();
	}

	public function test_dry_run_import_diff_idempotence_and_scoped_rollback(): void {
		$survivor           = self::factory()->post->create(
			array(
				'post_title' => 'My existing article',
				'post_name'  => 'my-existing-article',
			)
		);
		$before_description = get_option( 'blogdescription' );
		$before_settings    = get_option( 'joo_jooservices_settings', array() );
		$importer           = new DemoImporter();
		$before             = $importer->inspect( $this->package );
		$this->assertSame( array(), $before['conflicts'] );
		$this->assertFalse( $before['alreadyImported'] );
		$this->assertSame( array(), get_option( DemoImporter::LEDGER_OPTION, array() ) );
		$this->assertNotFalse( get_post( $survivor ) );

		$result = $importer->import( $this->package, false, 2 );
		$this->assertSame( 'imported', $result['status'] );
		$this->assertSame( 'Package Site', get_option( 'blogname' ) );
		$this->assertSame( (int) get_user_by( 'login', 'taylor' )->ID, get_option( 'joo_jooservices_settings' )['content.profile_user_id'] );
		$this->assertSame( 'Engineer', get_post_meta( $this->postId( 'joo_company', 'example-company' ), '_joo_company_role', true ) );
		$this->assertSame( 'Software Engineer', get_user_meta( (int) get_user_by( 'login', 'taylor' )->ID, 'joo_title', true ) );
		$this->assertSame( 'en_US', get_option( 'WPLANG' ) );
		$this->assertCount( 1, get_comments( array( 'post_id' => $this->postId( 'post', 'demo-article' ) ) ) );
		$this->assertSame( array(), $importer->diff( $this->package )['differences'] );

		$posts_before_rerun = $this->importedPostCount();
		$this->assertSame( 'already-imported', $importer->import( $this->package )['status'] );
		$this->assertSame( $posts_before_rerun, $this->importedPostCount() );

		update_option( 'blogname', 'Changed by site owner after import' );
		$rollback = $importer->rollback();
		$this->assertSame( 'rolled-back', $rollback['status'] );
		$this->assertSame( 'Changed by site owner after import', get_option( 'blogname' ), 'rollback leaves later owner edits in place' );
		$this->assertSame( $before_description, get_option( 'blogdescription' ), 'unchanged options restore their pre-import values' );
		$this->assertSame( $before_settings, get_option( 'joo_jooservices_settings', array() ), 'theme profile selection is restored' );
		$this->assertNotFalse( get_post( $survivor ), 'site content that predates import remains' );
		$this->assertFalse( get_user_by( 'login', 'taylor' ), 'the imported author account is removed' );
		$this->assertSame( 0, $this->importedPostCount() );
		$this->assertSame( 'nothing-to-rollback', ( new DemoImporter() )->rollback()['status'] );
	}

	public function test_conflicts_are_reported_without_mutating_or_deleting_site_content(): void {
		$existing_user = self::factory()->user->create(
			array(
				'user_login' => 'taylor',
				'user_email' => 'taylor@example.test',
			)
		);
		$existing_post = self::factory()->post->create(
			array(
				'post_name'  => 'demo-article',
				'post_title' => 'Keep me',
			)
		);
		$report        = ( new DemoImporter() )->inspect( $this->package );

		$this->assertNotEmpty( $report['conflicts'] );
		$this->assertStringContainsString( 'conflicts with an existing user: taylor', implode( ' ', $report['conflicts'] ) );
		$this->assertStringContainsString( 'post/demo-article', implode( ' ', $report['conflicts'] ) );
		$this->assertNotFalse( get_user_by( 'id', $existing_user ) );
		$this->assertSame( 'Keep me', get_the_title( $existing_post ) );
		$this->assertSame( array(), get_option( DemoImporter::LEDGER_OPTION, array() ) );
	}

	public function test_import_errors_on_a_conflict_and_diff_requires_the_same_completed_package(): void {
		$existing = self::factory()->post->create( array( 'post_name' => 'demo-article' ) );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Dry run found conflicts' );
		( new DemoImporter() )->import( $this->package );
		$this->assertNotFalse( get_post( $existing ) );
	}

	public function test_rollback_without_a_ledger_is_a_noop(): void {
		$this->assertSame( 'nothing-to-rollback', ( new DemoImporter() )->rollback()['status'] );
	}

	private function postId( string $type, string $slug ): int {
		$post = get_page_by_path( $slug, OBJECT, $type );
		$this->assertNotFalse( $post );
		return (int) $post->ID;
	}

	private function importedPostCount(): int {
		$ledger = get_option( DemoImporter::LEDGER_OPTION, array() );
		if ( ! is_array( $ledger ) || empty( $ledger['hash'] ) ) {
			return 0;
		}
		$posts = get_posts(
			array(
				'post_type'        => array_values( get_post_types() ),
				'post_status'      => array_keys( get_post_stati() ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'meta_key'         => '_joo_demo_import_id',
				'meta_value'       => $ledger['hash'],
				'suppress_filters' => true,
			)
		);
		return count( $posts );
	}
}
