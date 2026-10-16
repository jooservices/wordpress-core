<?php
/**
 * Small deterministic archive for importer integration tests.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Integration;

use RuntimeException;
use ZipArchive;

/**
 * Writes a contract-complete sample package without depending on release artifacts.
 */
final class DemoPackageFixture {

	/**
	 * Builds a compact buyer archive.
	 */
	public static function create(): string {
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/nV8AAAAASUVORK5CYII=', true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Encodes a one-pixel PNG fixture.
		if ( ! is_string( $png ) ) {
			throw new RuntimeException( 'Could not create fixture image.' );
		}
		$files     = array(
			'ATTRIBUTION.md'     => "# Attribution\nJOOservices Demo Content, CC BY 4.0.\n",
			'LICENSES.md'        => "# Demo licence\nCC BY 4.0. No third-party text or images.\n",
			'content.xml'        => self::wxr(),
			'language.json'      => '{"locale":"en_US","content":["en","vi"]}',
			'manifest.json'      => '{"packageVersion":1,"tier":"B","license":"CC-BY-4.0","expected":{"mediaFiles":1}}',
			'media/sample.png'   => $png,
			'menus.json'         => self::menusJson(),
			'patterns.json'      => '{"source":"JOOservices theme","registeredWithTheme":true,"patterns":[]}',
			'settings.json'      => self::settingsJson(),
			'structure.json'     => self::structureJson(),
			'theme-options.json' => '{"theme_mods":{"nav_menu_locations":["primary","footer"]}}',
			'widgets.json'       => '{"sidebars":[],"widgets":[]}',
		);
		$checksums = array();
		foreach ( $files as $name => $contents ) {
			$checksums[ $name ] = hash( 'sha256', $contents );
		}
		$files['checksums.json'] = (string) wp_json_encode( $checksums );
		$path                    = tempnam( sys_get_temp_dir(), 'joo-demo-integration-' );
		if ( false === $path ) {
			throw new RuntimeException( 'Could not allocate a fixture path.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Could not open the fixture ZIP.' );
		}
		foreach ( $files as $name => $contents ) {
			$zip->addFromString( $name, $contents );
		}
		$zip->close();
		return $path;
	}

	/**
	 * WXR records for posts, pages, media, comments, and a company.
	 */
	private static function wxr(): string {
		return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
<title>Buyer Demo</title><link>https://example.test</link><description>Fictional sample site</description><wp:wxr_version>1.2</wp:wxr_version>
<wp:author><wp:author_id>2</wp:author_id><wp:author_login><![CDATA[taylor]]></wp:author_login><wp:author_email><![CDATA[taylor@example.test]]></wp:author_email><wp:author_display_name><![CDATA[Taylor Example]]></wp:author_display_name><wp:author_first_name><![CDATA[Taylor]]></wp:author_first_name><wp:author_last_name><![CDATA[Example]]></wp:author_last_name></wp:author>
<wp:author><wp:author_id>3</wp:author_id><wp:author_login><![CDATA[editor]]></wp:author_login><wp:author_email><![CDATA[editor@example.test]]></wp:author_email><wp:author_display_name><![CDATA[Demo Editor]]></wp:author_display_name><wp:author_first_name><![CDATA[Demo]]></wp:author_first_name><wp:author_last_name><![CDATA[Editor]]></wp:author_last_name></wp:author>
<wp:category><wp:category_nicename>news</wp:category_nicename><wp:category_parent></wp:category_parent><wp:cat_name><![CDATA[News]]></wp:cat_name><wp:category_description><![CDATA[Generated news.]]></wp:category_description><wp:termmeta><wp:meta_key>_joo_demo</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:termmeta></wp:category>
<wp:tag><wp:tag_slug>php</wp:tag_slug><wp:tag_name><![CDATA[php]]></wp:tag_name><wp:tag_description><![CDATA[Posts about PHP.]]></wp:tag_description><wp:termmeta><wp:meta_key>_joo_demo</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:termmeta></wp:tag>
<item><title>Generated image</title><dc:creator><![CDATA[taylor]]></dc:creator><wp:post_id>11</wp:post_id><wp:post_date><![CDATA[2026-01-01 09:00:00]]></wp:post_date><wp:post_date_gmt><![CDATA[2026-01-01 09:00:00]]></wp:post_date_gmt><wp:post_name><![CDATA[sample]]></wp:post_name><wp:status><![CDATA[inherit]]></wp:status><wp:post_type><![CDATA[attachment]]></wp:post_type><wp:attachment_url><![CDATA[__MEDIA_BASE__/sample.png]]></wp:attachment_url><wp:postmeta><wp:meta_key>_wp_attachment_image_alt</wp:meta_key><wp:meta_value><![CDATA[Generated image]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_joo_demo</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:postmeta></item>
<item><title>Generated article</title><dc:creator><![CDATA[taylor]]></dc:creator><content:encoded><![CDATA[<!-- wp:paragraph --><p>Original sample text.</p><!-- /wp:paragraph --><img src="__MEDIA_BASE__/sample.png" alt="Generated image"/>]]></content:encoded><excerpt:encoded xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><![CDATA[An original sample.]]></excerpt:encoded><wp:post_id>12</wp:post_id><wp:post_date><![CDATA[2026-01-01 10:00:00]]></wp:post_date><wp:post_date_gmt><![CDATA[2026-01-01 10:00:00]]></wp:post_date_gmt><wp:comment_status><![CDATA[open]]></wp:comment_status><wp:post_name><![CDATA[demo-article]]></wp:post_name><wp:status><![CDATA[publish]]></wp:status><wp:post_type><![CDATA[post]]></wp:post_type><category domain="category" nicename="news"><![CDATA[News]]></category><category domain="post_tag" nicename="php"><![CDATA[php]]></category><wp:postmeta><wp:meta_key>_joo_demo</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_joo_lang</wp:meta_key><wp:meta_value><![CDATA[en]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value><![CDATA[11]]></wp:meta_value></wp:postmeta><wp:comment><wp:comment_id>5</wp:comment_id><wp:comment_author><![CDATA[Reader One]]></wp:comment_author><wp:comment_author_email><![CDATA[reader@example.test]]></wp:comment_author_email><wp:comment_date><![CDATA[2026-01-02 10:00:00]]></wp:comment_date><wp:comment_date_gmt><![CDATA[2026-01-02 10:00:00]]></wp:comment_date_gmt><wp:comment_content><![CDATA[An original comment.]]></wp:comment_content><wp:comment_approved>1</wp:comment_approved><wp:comment_parent>0</wp:comment_parent></wp:comment></item>
<item><title>About</title><dc:creator><![CDATA[taylor]]></dc:creator><content:encoded><![CDATA[<!-- wp:paragraph --><p>Fictional profile page.</p><!-- /wp:paragraph -->]]></content:encoded><wp:post_id>13</wp:post_id><wp:post_date><![CDATA[2026-01-01 11:00:00]]></wp:post_date><wp:post_date_gmt><![CDATA[2026-01-01 11:00:00]]></wp:post_date_gmt><wp:post_name><![CDATA[about]]></wp:post_name><wp:status><![CDATA[publish]]></wp:status><wp:post_type><![CDATA[page]]></wp:post_type><wp:postmeta><wp:meta_key>_joo_demo</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:postmeta></item>
<item><title>Example Company</title><dc:creator><![CDATA[taylor]]></dc:creator><content:encoded><![CDATA[]]></content:encoded><excerpt:encoded xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><![CDATA[A fictional company.]]></excerpt:encoded><wp:post_id>14</wp:post_id><wp:post_date><![CDATA[2026-01-01 12:00:00]]></wp:post_date><wp:post_date_gmt><![CDATA[2026-01-01 12:00:00]]></wp:post_date_gmt><wp:post_name><![CDATA[example-company]]></wp:post_name><wp:status><![CDATA[publish]]></wp:status><wp:post_type><![CDATA[joo_company]]></wp:post_type><wp:postmeta><wp:meta_key>_joo_demo</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_joo_company_role</wp:meta_key><wp:meta_value><![CDATA[Engineer]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_joo_company_from</wp:meta_key><wp:meta_value><![CDATA[2020-01]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_joo_company_pinned</wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key>_joo_company_url</wp:meta_key><wp:meta_value><![CDATA[https://example.invalid]]></wp:meta_value></wp:postmeta></item>
</channel></rss>
XML;
	}

	/**
	 * Package menu tree.
	 *
	 * @return string
	 */
	private static function menusJson(): string {
		return (string) wp_json_encode(
			array(
				'primary' => array(
					array(
						'title' => 'News',
						'type'  => 'category',
						'slug'  => 'news',
					),
					array(
						'title' => 'About',
						'type'  => 'page',
						'slug'  => 'about',
					),
				),
				'footer'  => array(
					array(
						'title' => 'About',
						'type'  => 'page',
						'slug'  => 'about',
					),
				),
			),
			JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Package site settings.
	 */
	private static function settingsJson(): string {
		return (string) wp_json_encode(
			array(
				'options' => array(
					'blogname'               => 'Package Site',
					'blogdescription'        => 'Generated package fixture',
					'posts_per_page'         => '10',
					'show_on_front'          => 'posts',
					'default_comment_status' => 'open',
					'timezone_string'        => 'UTC',
				),
				'profile' => array(
					'user'   => 'taylor',
					'meta'   => array(
						'joo_title'     => 'Software Engineer',
						'joo_short_bio' => 'A fictional author.',
					),
					'avatar' => 'sample.png',
				),
				'users'   => array(
					array(
						'login' => 'editor',
						'role'  => 'editor',
					),
				),
				'topics'  => array( 'images' => array( 'php' => 'sample.png' ) ),
			),
			JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Structural baseline for this fixture package.
	 */
	private static function structureJson(): string {
		return (string) wp_json_encode(
			array(
				'postTypes'    => array(
					'post'          => array( 'publish' => 1 ),
					'page'          => array( 'publish' => 1 ),
					'joo_company'   => array( 'publish' => 1 ),
					'attachment'    => array( 'inherit' => 1 ),
					'wp_navigation' => array( 'publish' => 1 ),
					'nav_menu_item' => array( 'publish' => 3 ),
				),
				'terms'        => array(
					'category' => 1,
					'post_tag' => 1,
				),
				'comments'     => 1,
				'users'        => array(
					'author' => 1,
					'editor' => 1,
				),
				'menus'        => array(
					'primary' => array(
						array(
							'title' => 'News',
							'type'  => 'category',
							'slug'  => 'news',
						),
						array(
							'title' => 'About',
							'type'  => 'page',
							'slug'  => 'about',
						),
					),
					'footer'  => array(
						array(
							'title' => 'About',
							'type'  => 'page',
							'slug'  => 'about',
						),
					),
				),
				'options'      => array(
					'blogname'               => 'Package Site',
					'blogdescription'        => 'Generated package fixture',
					'posts_per_page'         => '10',
					'show_on_front'          => 'posts',
					'default_comment_status' => 'open',
					'timezone_string'        => 'UTC',
				),
				'themeOptions' => array(
					'nav_menu_locations' => array(
						'primary' => 'joo-primary',
						'footer'  => 'joo-footer',
					),
					'profile_user'       => 'taylor',
				),
				'language'     => array(
					'locale'  => 'en_US',
					'content' => array( 'en', 'vi' ),
				),
			),
			JSON_UNESCAPED_SLASHES
		);
	}
}
