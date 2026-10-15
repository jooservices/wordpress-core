<?php
/**
 * Test factories.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Tests\Integration;

use JOOservices\TestKit\Factories\CompanyFactory;
use JOOservices\TestKit\Factories\PostFactory;
use JOOservices\TestKit\Factories\TermFactory;
use JOOservices\TestKit\Factories\UserFactory;
use JOOservices\TestKit\IntegrationTestCase;

final class FactoriesTest extends IntegrationTestCase {

	public function test_post_presets(): void {
		$cats = TermFactory::tree(
			array(
				'Research' => array( 'Homelab' ),
				'AI',
			)
		);
		$id   = PostFactory::create(
			array( 'title' => 'Preset post' ),
			array(
				'categories'      => array( $cats['Homelab'] ),
				'tags'            => array( 'a', 'b' ),
				'featured'        => 3,
				'lang'            => 'vi',
				'primaryCategory' => $cats['Homelab'],
				'noindex'         => true,
				'image'           => array(
					'width'  => 800,
					'height' => 400,
				),
			)
		);
		$this->assertSame( 'Preset post', get_the_title( $id ) );
		$this->assertSame( array( $cats['Homelab'] ), wp_get_post_categories( $id ) );
		$this->assertCount( 2, wp_get_post_tags( $id ) );
		$this->assertTrue( (bool) get_post_meta( $id, '_joo_featured', true ) );
		$this->assertSame( 3, (int) get_post_meta( $id, '_joo_featured_order', true ) );
		$this->assertSame( 'vi', get_post_meta( $id, '_joo_lang', true ) );
		$this->assertSame( $cats['Homelab'], (int) get_post_meta( $id, '_joo_primary_category', true ) );
		$this->assertTrue( (bool) get_post_meta( $id, '_joo_seo_noindex', true ) );
		$this->assertGreaterThan( 0, (int) get_post_thumbnail_id( $id ) );
	}

	public function test_translations_link_to_the_original(): void {
		$en = PostFactory::create( array(), array( 'lang' => 'en' ) );
		$vi = PostFactory::translationOf( $en, 'vi' );
		$this->assertSame( (string) $en, (string) get_post_meta( $vi, '_joo_translation_of', true ) );
	}

	public function test_term_tree_builds_the_hierarchy_and_tags_take_meta(): void {
		$ids = TermFactory::tree(
			array(
				'Research' => array( 'Homelab' => array( 'Rack' ) ),
				'AI',
			)
		);
		$this->assertSame( $ids['Research'], (int) get_term( $ids['Homelab'], 'category' )->parent );
		$this->assertSame( $ids['Homelab'], (int) get_term( $ids['Rack'], 'category' )->parent );
		$this->assertSame( 0, (int) get_term( $ids['AI'], 'category' )->parent );
		$tag = TermFactory::tag( 'Docker', array( 'joo_intro' => 'Containers' ) );
		$this->assertSame( 'Containers', get_term_meta( $tag, 'joo_intro', true ) );
	}

	public function test_users_and_companies(): void {
		$owner = UserFactory::owner( array( 'display_name' => 'Owner' ), array( 'joo_title' => 'CTO' ) );
		$this->assertTrue( user_can( $owner, 'manage_options' ) );
		$this->assertSame( 'CTO', get_user_meta( $owner, 'joo_title', true ) );
		$this->assertTrue( user_can( UserFactory::withRole( 'editor' ), 'edit_others_posts' ) );
		$company = CompanyFactory::create(
			array(
				'name'   => 'ACME',
				'pinned' => true,
				'from'   => '2022-03',
			)
		);
		$this->assertSame( 'joo_company', get_post_type( $company ) );
		$this->assertSame( '2022-03', get_post_meta( $company, '_joo_company_from', true ) );
		$this->assertTrue( (bool) get_post_meta( $company, '_joo_company_pinned', true ) );
	}
}
