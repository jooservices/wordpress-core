<?php
/**
 * Safe importer for the JOOservices buyer demo package.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Demo;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Import errors are reported through WP-CLI, never rendered as HTML.

use RuntimeException;
use SimpleXMLElement;
use WP_Error;

/**
 * Imports a licence-clean package without deleting or changing unowned content.
 */
final class DemoImporter {

	public const LEDGER_OPTION = 'joo_demo_import_ledger';

	private const THEME_SETTINGS_OPTION = 'joo_jooservices_settings';

	private const MARKER_META = '_joo_demo_import_id';

	/**
	 * Active rollback ledger.
	 *
	 * @var array<string, mixed>
	 */
	private array $ledger = array();

	/**
	 * Validates the package and reports conflicts without writing to WordPress.
	 *
	 * @param string $path Package ZIP path.
	 * @return array<string, mixed>
	 */
	public function inspect( string $path ): array {
		$package = new DemoPackage( $path );
		$info    = $package->validate();
		$ledger  = get_option( self::LEDGER_OPTION, array() );
		$already = is_array( $ledger ) && isset( $ledger['hash'] ) && hash_equals( (string) $ledger['hash'], (string) $info['hash'] ) && 'complete' === ( $ledger['state'] ?? '' );
		$errors  = $already ? array() : $this->conflicts( $package, $info['xml'] );
		return array(
			'hash'                    => $info['hash'],
			'size'                    => $info['size'],
			'tier'                    => $info['manifest']['tier'],
			'expected'                => $info['structure'],
			'conflicts'               => $errors,
			'alreadyImported'         => $already,
			'cleanInstallRecommended' => true,
		);
	}

	/**
	 * Imports the archive in bounded batches, or performs a no-write dry run.
	 *
	 * @param string        $path       Package ZIP path.
	 * @param bool          $dry_run    Dry-run only when true.
	 * @param int           $batch_size Items between progress checkpoints.
	 * @param callable|null $progress   Receives processed and total item counts.
	 * @return array<string, mixed>
	 */
	public function import( string $path, bool $dry_run = false, int $batch_size = 10, ?callable $progress = null ): array {
		$report = $this->inspect( $path );
		if ( $dry_run ) {
			return $report;
		}
		if ( $report['alreadyImported'] ) {
			return array(
				'status' => 'already-imported',
				'hash'   => $report['hash'],
			);
		}
		if ( array() !== $report['conflicts'] ) {
			throw new RuntimeException( 'Dry run found conflicts: ' . implode( ' ', $report['conflicts'] ) );
		}
		$active = get_option( self::LEDGER_OPTION, array() );
		if ( is_array( $active ) && isset( $active['hash'] ) ) {
			throw new RuntimeException( 'Another demo import is recorded. Run `wp joo demo rollback` before importing a different package.' );
		}

		$package      = new DemoPackage( $path );
		$info         = $package->validate();
		$settings     = $package->json( 'settings.json' );
		$menus        = $package->json( 'menus.json' );
		$this->ledger = array(
			'hash'       => $info['hash'],
			'state'      => 'importing',
			'posts'      => array(),
			'terms'      => array(),
			'users'      => array(),
			'comments'   => array(),
			'menus'      => array(),
			'managed'    => array(),
			'created_at' => gmdate( 'c' ),
		);
		$this->saveLedger();

		try {
			$users  = $this->createUsers( $info['xml']->channel, $info['xml']->getNamespaces( true ), $settings );
			$terms  = $this->createTerms( $info['xml']->channel, $info['xml']->getNamespaces( true ) );
			$media  = $this->createMedia( $package, $info['xml']->channel, $info['xml']->getNamespaces( true ) );
			$counts = $this->createContent( $info['xml']->channel, $info['xml']->getNamespaces( true ), $users, $terms, $media, max( 1, min( 100, $batch_size ) ), $progress );
			$this->createMenus( $menus, $terms );
			$this->applySettings( $settings, $package->json( 'language.json' ), $package->json( 'theme-options.json' ), $users, $terms, $media );
			$this->ledger['state'] = 'complete';
			$this->saveLedger();
			flush_rewrite_rules( false );
			wp_cache_flush();
			return array(
				'status' => 'imported',
				'hash'   => $info['hash'],
				'counts' => $counts,
			);
		} catch ( \Throwable $error ) {
			$this->rollbackLedger();
			throw new RuntimeException( 'Demo import failed and its partial changes were rolled back: ' . $error->getMessage(), 0, $error );
		}
	}

	/**
	 * Removes only objects created by the package identified in the stored ledger.
	 *
	 * @return array<string, int|string>
	 */
	public function rollback(): array {
		$ledger = get_option( self::LEDGER_OPTION, array() );
		if ( ! is_array( $ledger ) || ! isset( $ledger['hash'] ) ) {
			return array(
				'status'   => 'nothing-to-rollback',
				'posts'    => 0,
				'terms'    => 0,
				'users'    => 0,
				'comments' => 0,
			);
		}
		$this->ledger = $ledger;
		return $this->rollbackLedger();
	}

	/**
	 * Compares imported objects, menus, and settings with the package baseline.
	 *
	 * @param string $path Package ZIP path.
	 * @return array<string, mixed>
	 */
	public function diff( string $path ): array {
		$package = new DemoPackage( $path );
		$info    = $package->validate();
		$ledger  = get_option( self::LEDGER_OPTION, array() );
		if ( ! is_array( $ledger ) || ! isset( $ledger['hash'] ) || ! hash_equals( (string) $ledger['hash'], (string) $info['hash'] ) || 'complete' !== ( $ledger['state'] ?? '' ) ) {
			throw new RuntimeException( 'This package has not been imported completely.' );
		}
		$this->ledger = $ledger;
		$actual       = $this->snapshot( $info['structure'] );
		return array(
			'differences' => DemoStructure::differences( $info['structure'], $actual ),
			'expected'    => $info['structure'],
			'actual'      => $actual,
		);
	}

	/**
	 * Checks conflicts before importing.
	 *
	 * @param DemoPackage     $package Open package.
	 * @param SimpleXMLElement $xml     WXR document.
	 * @return list<string>
	 */
	private function conflicts( DemoPackage $package, SimpleXMLElement $xml ): array {
		$errors     = array();
		$channel    = $xml->channel;
		$namespaces = $xml->getNamespaces( true );
		$wp_ns      = (string) ( $namespaces['wp'] ?? '' );
		$dc_ns      = 'http://purl.org/dc/elements/1.1/';
		$used_users = array();
		foreach ( $channel->children( $wp_ns )->author as $author ) {
			$fields               = $author->children( $wp_ns );
			$login                = (string) $fields->author_login;
			$email                = (string) $fields->author_email;
			$used_users[ $login ] = true;
			if ( username_exists( $login ) || email_exists( $email ) ) {
				$errors[] = 'Demo author account conflicts with an existing user: ' . $login;
			}
		}

		foreach ( $channel->item as $item ) {
			$fields = $item->children( $wp_ns );
			$type   = (string) $fields->post_type;
			$slug   = (string) $fields->post_name;
			if ( ! in_array( $type, array( 'post', 'page', 'attachment', 'joo_company' ), true ) ) {
				$errors[] = 'Unsupported post type in package: ' . $type;
				continue;
			}
			if ( 'attachment' !== $type && '' !== $slug && get_page_by_path( $slug, OBJECT, $type ) ) {
				$errors[] = 'A site item already uses the imported slug: ' . $type . '/' . $slug;
			}
			$creator = (string) $item->children( $dc_ns )->creator;
			if ( '' !== $creator && ! isset( $used_users[ $creator ] ) ) {
				$errors[] = 'WXR item references an unknown author: ' . $creator;
			}
		}
		foreach ( array( 'category', 'tag' ) as $kind ) {
			$taxonomy = 'category' === $kind ? 'category' : 'post_tag';
			foreach ( $channel->children( $wp_ns )->{$kind} as $node ) {
				$fields   = $node->children( $wp_ns );
				$slug     = 'category' === $kind ? (string) $fields->category_nicename : (string) $fields->tag_slug;
				$name     = 'category' === $kind ? (string) $fields->cat_name : (string) $fields->tag_name;
				$existing = term_exists( $slug, $taxonomy );
				if ( $existing ) { // term_exists() returns 0 or null (not false) when the term does not exist.
					$term = get_term( is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing, $taxonomy );
					if ( ! $term || is_wp_error( $term ) || $name !== $term->name ) {
						$errors[] = 'A site term conflicts with the package: ' . $taxonomy . '/' . $slug;
					}
				}
			}
		}

		if ( ! post_type_exists( 'joo_company' ) ) {
			$errors[] = 'Activate JOOservices Resume before importing company data.';
		}
		foreach ( array( 'joo-primary', 'joo-footer' ) as $menu ) {
			if ( term_exists( $menu, 'nav_menu' ) ) {
				$errors[] = 'A navigation menu already uses the imported name: ' . $menu;
			}
		}
		if ( get_page_by_path( 'joo-primary', OBJECT, 'wp_navigation' ) ) {
			$errors[] = 'A block navigation post already uses the imported slug: wp_navigation/joo-primary';
		}
		$widget_data  = $package->json( 'widgets.json' );
		$pattern_data = $package->json( 'patterns.json' );
		if ( ! empty( $widget_data['widgets'] ) || ! empty( $widget_data['sidebars'] ) || ! empty( $pattern_data['patterns'] ) || empty( $pattern_data['registeredWithTheme'] ) ) {
			$errors[] = 'This JOOservices block-theme package expects its registered theme patterns and no classic widgets.';
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Creates non-administrator demo authors without altering existing users.
	 *
	 * @param SimpleXMLElement       $channel WXR channel.
	 * @param array<string, string>  $namespaces XML namespaces.
	 * @param array<string, mixed>   $settings Package settings.
	 * @return array<string, int>
	 */
	private function createUsers( SimpleXMLElement $channel, array $namespaces, array $settings ): array {
		$users = array();
		$wp_ns = (string) ( $namespaces['wp'] ?? '' );
		$roles = array();
		foreach ( $settings['users'] ?? array() as $user ) {
			if ( is_array( $user ) && isset( $user['login'], $user['role'] ) ) {
				$roles[ (string) $user['login'] ] = (string) $user['role'];
			}
		}
		$profile_login = (string) ( $settings['profile']['user'] ?? '' );
		foreach ( $channel->children( $wp_ns )->author as $author ) {
			$fields = $author->children( $wp_ns );
			$login  = sanitize_user( (string) $fields->author_login, true );
			$role   = $login === $profile_login ? 'author' : ( $roles[ $login ] ?? 'author' );
			if ( ! in_array( $role, array( 'author', 'editor' ), true ) ) {
				$role = 'author';
			}
			$id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'user_email'   => sanitize_email( (string) $fields->author_email ),
					'role'         => $role,
					'display_name' => sanitize_text_field( (string) $fields->author_display_name ),
					'first_name'   => sanitize_text_field( (string) $fields->author_first_name ),
					'last_name'    => sanitize_text_field( (string) $fields->author_last_name ),
				)
			);
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( $id->get_error_message() );
			}
			$this->ledger['users'][] = (int) $id;
			$this->markUser( (int) $id );
			$this->saveLedger();
			$users[ $login ]                              = (int) $id;
			$users[ 'id:' . (string) $fields->author_id ] = (int) $id;
		}
		return $users;
	}

	/**
	 * Creates package categories and tags, reusing a matching existing term without changing it.
	 *
	 * @param SimpleXMLElement      $channel WXR channel.
	 * @param array<string, string> $namespaces XML namespaces.
	 * @return array<string, int>
	 */
	private function createTerms( SimpleXMLElement $channel, array $namespaces ): array {
		$term_ids = array();
		$wp_ns    = (string) ( $namespaces['wp'] ?? '' );
		foreach ( array( 'category', 'tag' ) as $kind ) {
			$taxonomy = 'category' === $kind ? 'category' : 'post_tag';
			foreach ( $channel->children( $wp_ns )->{$kind} as $node ) {
				$fields      = $node->children( $wp_ns );
				$slug        = 'category' === $kind ? (string) $fields->category_nicename : (string) $fields->tag_slug;
				$name        = 'category' === $kind ? (string) $fields->cat_name : (string) $fields->tag_name;
				$parent_slug = 'category' === $kind ? (string) $fields->category_parent : '';
				$existing    = term_exists( $slug, $taxonomy );
				if ( $existing ) { // term_exists() returns 0 or null (not false) when the term does not exist.
					$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
					$term    = get_term( $term_id, $taxonomy );
					if ( ! $term || is_wp_error( $term ) || $name !== $term->name ) {
						throw new RuntimeException( 'A term conflicts with the package: ' . $taxonomy . '/' . $slug );
					}
					$term_ids[ $taxonomy . ':' . $slug ] = $term_id;
					continue;
				}
				$parent = '' !== $parent_slug ? ( $term_ids[ 'category:' . $parent_slug ] ?? 0 ) : 0;
				$insert = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'slug'        => $slug,
						'parent'      => $parent,
						'description' => 'category' === $kind ? (string) $fields->category_description : (string) $fields->tag_description,
					)
				);
				if ( is_wp_error( $insert ) ) {
					throw new RuntimeException( $insert->get_error_message() );
				}
				$term_id                 = (int) $insert['term_id'];
				$this->ledger['terms'][] = array(
					'id'       => $term_id,
					'taxonomy' => $taxonomy,
				);
				add_term_meta( $term_id, '_joo_demo', '1', true );
				add_term_meta( $term_id, self::MARKER_META, (string) $this->ledger['hash'], true );
				foreach ( $fields->termmeta as $meta ) {
					update_term_meta( $term_id, (string) $meta->children( $wp_ns )->meta_key, (string) $meta->children( $wp_ns )->meta_value );
				}
				$term_ids[ $taxonomy . ':' . $slug ] = $term_id;
				$this->saveLedger();
			}
		}
		return $term_ids;
	}

	/**
	 * Uploads generated files and creates marked attachment records.
	 *
	 * @param DemoPackage           $package  Open package.
	 * @param SimpleXMLElement      $channel  WXR channel.
	 * @param array<string, string> $namespaces XML namespaces.
	 * @return array<string, array{id: int, file: string, url: string, wxr_id: int}>
	 */
	private function createMedia( DemoPackage $package, SimpleXMLElement $channel, array $namespaces ): array {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$media = array();
		$wp_ns = (string) ( $namespaces['wp'] ?? '' );
		foreach ( $channel->item as $item ) {
			$fields = $item->children( $wp_ns );
			if ( 'attachment' !== (string) $fields->post_type ) {
				continue;
			}
			$name  = basename( (string) $fields->attachment_url );
			$bytes = $package->read( 'media/' . $name );
			if ( ! str_starts_with( bin2hex( substr( $bytes, 0, 8 ) ), '89504e470d0a1a0a' ) ) {
				throw new RuntimeException( 'A package image is not a valid PNG: ' . $name );
			}
			$upload = wp_upload_bits( $name, null, $bytes );
			if ( ! empty( $upload['error'] ) ) {
				throw new RuntimeException( (string) $upload['error'] );
			}
			$title = sanitize_text_field( (string) $item->title );
			$id    = wp_insert_attachment(
				array(
					'post_title'     => $title,
					'post_name'      => sanitize_title( (string) $fields->post_name ),
					'post_mime_type' => 'image/png',
					'post_status'    => 'inherit',
					'guid'           => $upload['url'],
				),
				$upload['file'],
				0,
				true
			);
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $upload['file'] );
				throw new RuntimeException( $id->get_error_message() );
			}
			$this->ledger['posts'][] = (int) $id;
			$this->markPost( (int) $id );
			$metadata = wp_generate_attachment_metadata( (int) $id, $upload['file'] );
			if ( is_array( $metadata ) ) {
				wp_update_attachment_metadata( (int) $id, $metadata );
			}
			foreach ( $fields->postmeta as $meta ) {
				$key = (string) $meta->children( $wp_ns )->meta_key;
				if ( '_joo_demo' !== $key && self::MARKER_META !== $key ) {
					update_post_meta( (int) $id, $key, (string) $meta->children( $wp_ns )->meta_value );
				}
			}
			$media[ $name ] = array(
				'id'     => (int) $id,
				'file'   => $upload['file'],
				'url'    => $upload['url'],
				'wxr_id' => (int) $fields->post_id,
			);
			$this->saveLedger();
		}
		return $media;
	}

	/**
	 * Imports WXR records and comments in chunks.
	 *
	 * @param SimpleXMLElement                $channel WXR channel.
	 * @param array<string, string>           $namespaces XML namespaces.
	 * @param array<string, int>              $users User mapping.
	 * @param array<string, int>              $terms Term mapping.
	 * @param array<string, array{id: int, file: string, url: string, wxr_id: int}> $media Media mapping.
	 * @param int                              $batch_size Checkpoint interval.
	 * @param callable|null                   $progress Progress callback.
	 * @return array<string, int>
	 */
	private function createContent( SimpleXMLElement $channel, array $namespaces, array $users, array $terms, array $media, int $batch_size, ?callable $progress ): array {
		$wp_ns      = (string) ( $namespaces['wp'] ?? '' );
		$content_ns = 'http://purl.org/rss/1.0/modules/content/';
		$excerpt_ns = 'http://wordpress.org/export/1.2/excerpt/';
		$dc_ns      = 'http://purl.org/dc/elements/1.1/';
		$items      = array();
		foreach ( $channel->item as $item ) {
			if ( 'attachment' !== (string) $item->children( $wp_ns )->post_type ) {
				$items[] = $item;
			}
		}
		$total    = count( $items );
		$counts   = array();
		$post_map = array();
		foreach ( $media as $entry ) {
			$post_map[ $entry['wxr_id'] ] = $entry['id'];
		}
		$by_slug   = array();
		$deferred  = array();
		$processed = 0;
		foreach ( $items as $item ) {
			$fields = $item->children( $wp_ns );
			$type   = (string) $fields->post_type;
			$slug   = sanitize_title( (string) $fields->post_name );
			$author = (string) $item->children( $dc_ns )->creator;
			$id     = wp_insert_post(
				array(
					'post_type'      => $type,
					'post_title'     => sanitize_text_field( (string) $item->title ),
					'post_name'      => $slug,
					'post_status'    => sanitize_key( (string) $fields->status ),
					'post_password'  => (string) $fields->post_password,
					'post_content'   => $this->replaceMedia( (string) $item->children( $content_ns )->encoded, $media ),
					'post_excerpt'   => wp_strip_all_tags( (string) $item->children( $excerpt_ns )->encoded ),
					'post_date'      => (string) $fields->post_date,
					'post_date_gmt'  => (string) $fields->post_date_gmt,
					'post_author'    => $users[ $author ] ?? 0,
					'comment_status' => 'open' === (string) $fields->comment_status ? 'open' : 'closed',
					'ping_status'    => 'closed',
					'menu_order'     => (int) $fields->menu_order,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( $id->get_error_message() );
			}
			$id                      = (int) $id;
			$this->ledger['posts'][] = $id;
			$this->markPost( $id );
			$this->saveLedger();
			$post_map[ (int) $fields->post_id ] = $id;
			$by_slug[ $type . ':' . $slug ]     = $id;
			$counts[ $type ]                    = ( $counts[ $type ] ?? 0 ) + 1;
			$this->applyPostTerms( $id, $item, $terms );
			$this->applyPostMeta( $id, $fields, $terms, $deferred );
			$this->createComments( $id, $fields, $namespaces );
			++$processed;
			if ( 0 === $processed % $batch_size || $processed === $total ) {
				$this->saveLedger();
				if ( null !== $progress ) {
					$progress( $processed, $total );
				}
			}
		}
		foreach ( $deferred as $entry ) {
			if ( 'thumb' === $entry['kind'] && isset( $post_map[ (int) $entry['value'] ] ) ) {
				set_post_thumbnail( $entry['post'], $post_map[ (int) $entry['value'] ] );
			} elseif ( 'translation' === $entry['kind'] && isset( $by_slug[ 'post:' . $entry['value'] ] ) ) {
				update_post_meta( $entry['post'], '_joo_translation_of', $by_slug[ 'post:' . $entry['value'] ] );
			}
		}
		return $counts;
	}

	/**
	 * Assigns imported category and tag relationships.
	 *
	 * @param int                  $post_id Post ID.
	 * @param SimpleXMLElement     $item    WXR item.
	 * @param array<string, int>   $terms   Term mapping.
	 */
	private function applyPostTerms( int $post_id, SimpleXMLElement $item, array $terms ): void {
		$assign = array(
			'category' => array(),
			'post_tag' => array(),
		);
		foreach ( $item->category as $node ) {
			$taxonomy = 'category' === (string) $node['domain'] ? 'category' : ( 'post_tag' === (string) $node['domain'] ? 'post_tag' : '' );
			$slug     = (string) $node['nicename'];
			if ( '' !== $taxonomy && isset( $terms[ $taxonomy . ':' . $slug ] ) && is_object_in_taxonomy( get_post_type( $post_id ), $taxonomy ) ) {
				$assign[ $taxonomy ][] = $terms[ $taxonomy . ':' . $slug ];
			}
		}
		foreach ( $assign as $taxonomy => $ids ) {
			if ( array() !== $ids ) {
				wp_set_object_terms( $post_id, $ids, $taxonomy, false );
			}
		}
	}

	/**
	 * Applies WXR postmeta while deferring references resolved after all records exist.
	 *
	 * @param int                 $post_id Post ID.
	 * @param SimpleXMLElement    $fields  WXR fields.
	 * @param array<string, int>  $terms    Term mapping.
	 * @param list<array{post:int,kind:string,value:string}> $deferred Deferred references.
	 */
	private function applyPostMeta( int $post_id, SimpleXMLElement $fields, array $terms, array &$deferred ): void {
		foreach ( $fields->postmeta as $meta ) {
			$entry = $meta->children( 'http://wordpress.org/export/1.2/' );
			$key   = (string) $entry->meta_key;
			$value = (string) $entry->meta_value;
			if ( '_joo_demo' === $key || self::MARKER_META === $key ) {
				continue;
			}
			if ( '_thumbnail_id' === $key ) {
				$deferred[] = array(
					'post'  => $post_id,
					'kind'  => 'thumb',
					'value' => $value,
				);
			} elseif ( '_joo_translation_of_slug' === $key ) {
				$deferred[] = array(
					'post'  => $post_id,
					'kind'  => 'translation',
					'value' => $value,
				);
			} elseif ( '_joo_primary_category_slug' === $key ) {
				$category = $terms[ 'category:' . $value ] ?? 0;
				update_post_meta( $post_id, '_joo_primary_category', $category );
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $category );
			} elseif ( '_joo_company_pinned' === $key ) {
				update_post_meta( $post_id, $key, '1' === $value );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Imports WXR comments and marks each one for rollback.
	 *
	 * @param int                $post_id Parent post.
	 * @param SimpleXMLElement   $fields  WXR item fields.
	 * @param array<string,string> $namespaces XML namespace map.
	 */
	private function createComments( int $post_id, SimpleXMLElement $fields, array $namespaces ): void {
		$wp_ns = (string) ( $namespaces['wp'] ?? '' );
		$map   = array();
		foreach ( $fields->comment as $comment ) {
			$entry  = $comment->children( $wp_ns );
			$parent = (int) $entry->comment_parent;
			$id     = wp_insert_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => sanitize_text_field( (string) $entry->comment_author ),
					'comment_author_email' => sanitize_email( (string) $entry->comment_author_email ),
					'comment_content'      => wp_kses_post( (string) $entry->comment_content ),
					'comment_date'         => (string) $entry->comment_date,
					'comment_date_gmt'     => (string) $entry->comment_date_gmt,
					'comment_approved'     => '1',
					'comment_parent'       => $parent > 0 ? ( $map[ $parent ] ?? 0 ) : 0,
				)
			);
			if ( ! is_int( $id ) || $id <= 0 ) {
				throw new RuntimeException( 'A package comment could not be imported.' );
			}
			$this->ledger['comments'][] = $id;
			add_comment_meta( $id, '_joo_demo', '1', true );
			add_comment_meta( $id, self::MARKER_META, (string) $this->ledger['hash'], true );
			$map[ (int) $entry->comment_id ] = $id;
		}
	}

	/**
	 * Replaces local media placeholders in WXR content.
	 *
	 * @param string                                      $content WXR HTML.
	 * @param array<string, array{id:int,file:string,url:string,wxr_id:int}> $media Uploaded media map.
	 */
	private function replaceMedia( string $content, array $media ): string {
		$result = preg_replace_callback(
			'#__MEDIA_BASE__/([A-Za-z0-9._-]+)#',
			static function ( array $match ) use ( $media ): string {
				if ( ! isset( $media[ $match[1] ] ) ) {
					throw new RuntimeException( 'WXR content references a missing media file: ' . $match[1] );
				}
				return esc_url_raw( $media[ $match[1] ]['url'] );
			},
			$content
		);
		if ( ! is_string( $result ) || str_contains( $result, '__MEDIA_BASE__' ) ) {
			throw new RuntimeException( 'WXR content contains an unresolved media reference.' );
		}
		return $result;
	}

	/**
	 * Creates classic menus and a block navigation post from the package tree.
	 *
	 * @param array<string, mixed> $menus Package menu data.
	 * @param array<string, int>   $terms Imported taxonomy terms.
	 */
	private function createMenus( array $menus, array $terms ): void {
		$locations = array();
		$names     = array(
			'primary' => 'joo-primary',
			'footer'  => 'joo-footer',
		);
		$by_slug   = array();
		foreach ( get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'all',
			)
		) as $post ) {
			$by_slug[ $post->post_type . ':' . $post->post_name ] = (int) $post->ID;
		}
		foreach ( $names as $key => $name ) {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				throw new RuntimeException( $menu_id->get_error_message() );
			}
			$menu_id                 = (int) $menu_id;
			$this->ledger['menus'][] = $menu_id;
			add_term_meta( $menu_id, '_joo_demo', '1', true );
			add_term_meta( $menu_id, self::MARKER_META, (string) $this->ledger['hash'], true );
			$this->saveLedger();
			$this->addMenuEntries( $menu_id, $menus[ $key ] ?? array(), 0, $terms, $by_slug );
			$locations[ $key ] = $menu_id;
		}
		$registered = get_registered_nav_menus();
		$assigned   = array_intersect_key( $locations, $registered );
		if ( array() !== $assigned ) {
			$this->setManagedThemeMod( 'nav_menu_locations', $assigned );
		}
		$block = $this->navigationBlocks( $menus['primary'] ?? array(), $terms, $by_slug );
		$id    = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_title'   => 'Primary',
				'post_name'    => 'joo-primary',
				'post_status'  => 'publish',
				'post_content' => $block,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$this->ledger['posts'][] = (int) $id;
		$this->markPost( (int) $id );
		$this->saveLedger();
	}

	/**
	 * Inserts one menu tree and marks each menu item.
	 *
	 * @param int                $menu_id Menu term ID.
	 * @param array<int, mixed>  $entries Entries.
	 * @param int                $parent Parent menu item.
	 * @param array<string, int> $terms Term mapping.
	 * @param array<string, int> $posts Post mapping.
	 */
	private function addMenuEntries( int $menu_id, array $entries, int $parent, array $terms, array $posts ): void {
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['title'], $entry['type'], $entry['slug'] ) ) {
				throw new RuntimeException( 'A navigation entry is invalid.' );
			}
			$args = array(
				'menu-item-title'     => sanitize_text_field( (string) $entry['title'] ),
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
			);
			if ( 'category' === $entry['type'] && isset( $terms[ 'category:' . $entry['slug'] ] ) ) {
				$args += array(
					'menu-item-type'      => 'taxonomy',
					'menu-item-object'    => 'category',
					'menu-item-object-id' => $terms[ 'category:' . $entry['slug'] ],
				);
			} elseif ( 'page' === $entry['type'] && isset( $posts[ 'page:' . $entry['slug'] ] ) ) {
				$args += array(
					'menu-item-type'      => 'post_type',
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $posts[ 'page:' . $entry['slug'] ],
				);
			} else {
				throw new RuntimeException( 'Navigation target was not imported: ' . (string) $entry['slug'] );
			}
			$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( is_wp_error( $item_id ) ) {
				throw new RuntimeException( $item_id->get_error_message() );
			}
			add_post_meta( (int) $item_id, '_joo_demo', '1', true );
			add_post_meta( (int) $item_id, self::MARKER_META, (string) $this->ledger['hash'], true );
			if ( ! empty( $entry['children'] ) && is_array( $entry['children'] ) ) {
				$this->addMenuEntries( $menu_id, $entry['children'], (int) $item_id, $terms, $posts );
			}
		}
	}

	/**
	 * Builds the core navigation block representation.
	 *
	 * @param array<int, mixed>  $entries Menu entries.
	 * @param array<string, int> $terms Terms.
	 * @param array<string, int> $posts Posts.
	 */
	private function navigationBlocks( array $entries, array $terms, array $posts ): string {
		$blocks = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['title'], $entry['type'], $entry['slug'] ) ) {
				continue;
			}
			if ( 'category' === $entry['type'] && isset( $terms[ 'category:' . $entry['slug'] ] ) ) {
				$id    = $terms[ 'category:' . $entry['slug'] ];
				$attrs = sprintf( '{"label":%1$s,"type":"category","id":%2$d,"url":%3$s,"kind":"taxonomy","isTopLevelLink":true}', wp_json_encode( $entry['title'] ), $id, wp_json_encode( get_term_link( $id ) ) );
			} elseif ( 'page' === $entry['type'] && isset( $posts[ 'page:' . $entry['slug'] ] ) ) {
				$id    = $posts[ 'page:' . $entry['slug'] ];
				$attrs = sprintf( '{"label":%1$s,"type":"page","id":%2$d,"url":%3$s,"kind":"post-type","isTopLevelLink":true}', wp_json_encode( $entry['title'] ), $id, wp_json_encode( get_permalink( $id ) ) );
			} else {
				continue;
			}
			if ( ! empty( $entry['children'] ) && is_array( $entry['children'] ) ) {
				$blocks[] = '<!-- wp:navigation-submenu ' . $attrs . " -->\n" . $this->navigationBlocks( $entry['children'], $terms, $posts ) . "\n<!-- /wp:navigation-submenu -->";
			} else {
				$blocks[] = '<!-- wp:navigation-link ' . $attrs . ' /-->';
			}
		}
		return implode( "\n", $blocks );
	}

	/**
	 * Applies safe site options and profile/language data, retaining their original values for rollback.
	 *
	 * @param array<string, mixed> $settings Package settings.
	 * @param array<string, mixed> $language Language settings.
	 * @param array<string, mixed> $theme_options Theme options.
	 * @param array<string, int>   $users User map.
	 * @param array<string, int>   $terms Term map.
	 * @param array<string, array{id:int,file:string,url:string,wxr_id:int}> $media Media map.
	 */
	private function applySettings( array $settings, array $language, array $theme_options, array $users, array $terms, array $media ): void {
		$allowed = array( 'blogname', 'blogdescription', 'posts_per_page', 'show_on_front', 'default_comment_status', 'timezone_string' );
		foreach ( $settings['options'] ?? array() as $key => $value ) {
			if ( in_array( (string) $key, $allowed, true ) ) {
				$this->setManagedOption( (string) $key, $value );
			}
		}
		$locale = sanitize_text_field( (string) ( $language['locale'] ?? 'en_US' ) );
		if ( '' !== $locale && 'en_US' !== $locale ) { // English is WordPress's built-in locale: WPLANG stays unset (WordPress refuses en_US as a value).
			$this->setManagedOption( 'WPLANG', $locale );
		}
		$theme_mods = $theme_options['theme_mods'] ?? array();
		$locations  = is_array( $theme_mods ) ? ( $theme_mods['nav_menu_locations'] ?? array() ) : array();
		if ( ! is_array( $theme_mods ) || 1 !== count( $theme_mods ) || ! is_array( $locations ) || array( 'primary', 'footer' ) !== array_values( $locations ) ) {
			throw new RuntimeException( 'This package contains unsupported theme option overrides.' );
		}

		$profile = $settings['profile'] ?? array();
		$login   = (string) ( $profile['user'] ?? '' );
		if ( isset( $users[ $login ] ) ) {
			$profile_user = $users[ $login ];
			foreach ( $profile['meta'] ?? array() as $key => $value ) {
				update_user_meta( $profile_user, sanitize_key( (string) $key ), $value );
			}
			$avatar = (string) ( $profile['avatar'] ?? '' );
			if ( isset( $media[ $avatar ]['id'] ) ) {
				update_user_meta( $profile_user, 'joo_avatar_id', $media[ $avatar ]['id'] );
			}
			$theme_settings                            = get_option( self::THEME_SETTINGS_OPTION, array() );
			$theme_settings                            = is_array( $theme_settings ) ? $theme_settings : array();
			$theme_settings['content.profile_user_id'] = $profile_user;
			$this->setManagedOption( self::THEME_SETTINGS_OPTION, $theme_settings );
		}
		foreach ( $settings['topics']['images'] ?? array() as $slug => $file ) {
			$term_id = $terms[ 'post_tag:' . $slug ] ?? 0;
			if ( $term_id > 0 && isset( $media[ $file ]['id'] ) && (string) get_term_meta( $term_id, self::MARKER_META, true ) === $this->ledgerHash() ) {
				update_term_meta( $term_id, 'joo_image_id', $media[ $file ]['id'] );
			}
		}
	}

	/**
	 * Captures an option's old value and only replaces it with package data.
	 *
	 * @param string $key Option name.
	 * @param mixed  $value Package value.
	 */
	private function setManagedOption( string $key, $value ): void {
		$exists                          = $this->optionExists( $key );
		$before                          = get_option( $key );
		$this->ledger['managed'][ $key ] = array(
			'exists'   => $exists,
			'before'   => $before,
			'imported' => $value,
		);
		$this->saveLedger();
		update_option( $key, $value );
	}

	/**
	 * Captures the active theme-mods option and updates one theme modification.
	 *
	 * @param string               $key   Theme mod key.
	 * @param array<string, mixed> $value Theme mod value.
	 */
	private function setManagedThemeMod( string $key, array $value ): void {
		$stylesheet                         = get_stylesheet();
		$option                             = 'theme_mods_' . $stylesheet;
		$exists                             = $this->optionExists( $option );
		$before                             = get_option( $option, array() );
		$updated                            = is_array( $before ) ? $before : array();
		$updated[ $key ]                    = $value;
		$this->ledger['managed'][ $option ] = array(
			'exists'   => $exists,
			'before'   => $before,
			'imported' => $updated,
		);
		$this->saveLedger();
		update_option( $option, $updated );
	}

	/**
	 * Checks for an option row without confusing a stored false value with absence.
	 *
	 * @param string $key Option name.
	 */
	private function optionExists( string $key ): bool {
		$missing = new \stdClass();
		return get_option( $key, $missing ) !== $missing; // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Both operands are runtime values, so a literal-left comparison cannot apply.
	}

	/**
	 * Restores option values only when they still equal the package's imported value.
	 */
	private function restoreManagedValues(): void {
		foreach ( $this->ledger['managed'] ?? array() as $key => $record ) {
			$current = get_option( (string) $key );
			if ( ! is_array( $record ) || ( $record['imported'] ?? null ) !== $current ) {
				continue;
			}
			if ( ! empty( $record['exists'] ) ) {
				update_option( (string) $key, $record['before'] ?? null );
			} else {
				delete_option( (string) $key );
			}
		}
	}

	/**
	 * Builds the actual structural snapshot for import-owned items.
	 *
	 * @param array<string, mixed> $expected Package structural baseline.
	 * @return array<string, mixed>
	 */
	private function snapshot( array $expected ): array {
		$ids         = get_posts(
			array(
				'post_type'        => array_values( get_post_types() ),
				'post_status'      => array_keys( get_post_stati() ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'meta_key'         => self::MARKER_META,
				'meta_value'       => (string) $this->ledgerHash(),
				'suppress_filters' => true,
			)
		);
		$post_counts = array();
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post ) {
				$post_counts[ $post->post_type ][ $post->post_status ] = ( $post_counts[ $post->post_type ][ $post->post_status ] ?? 0 ) + 1;
			}
		}
		foreach ( $expected['postTypes'] as $type => $statuses ) {
			foreach ( $statuses as $status => $_expected_count ) {
				$post_counts[ $type ][ $status ] = $post_counts[ $type ][ $status ] ?? 0;
			}
		}
		$term_counts = array();
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$found                    = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'meta_key'   => self::MARKER_META,
					'meta_value' => (string) $this->ledgerHash(),
					'fields'     => 'ids',
				)
			);
			$term_counts[ $taxonomy ] = is_array( $found ) ? count( $found ) : 0;
		}
		$users       = get_users(
			array(
				'meta_key'   => self::MARKER_META,
				'meta_value' => (string) $this->ledgerHash(),
				'fields'     => 'all',
				'number'     => -1,
			)
		);
		$user_counts = array();
		foreach ( $users as $user ) {
			foreach ( $user->roles as $role ) {
				$user_counts[ $role ] = ( $user_counts[ $role ] ?? 0 ) + 1;
			}
		}
		$comment_ids = get_comments(
			array(
				'meta_key'   => self::MARKER_META,
				'meta_value' => (string) $this->ledgerHash(),
				'fields'     => 'ids',
				'number'     => 0,
			)
		);
		$menus       = $this->snapshotMenus();
		$options     = array();
		foreach ( $expected['options'] as $key => $_value ) {
			$options[ $key ] = get_option( (string) $key );
		}
		$expected_locations = $expected['themeOptions']['nav_menu_locations'] ?? array();
		$theme_mods         = get_theme_mod( 'nav_menu_locations', array() );
		$theme_options      = array( 'nav_menu_locations' => array() );
		foreach ( $expected_locations as $location => $_menu_name ) {
			$menu_id = is_array( $theme_mods ) ? (int) ( $theme_mods[ $location ] ?? 0 ) : 0;
			$menu    = $menu_id > 0 ? get_term( $menu_id, 'nav_menu' ) : false;
			$theme_options['nav_menu_locations'][ $location ] = $menu && ! is_wp_error( $menu ) ? $menu->name : null;
		}
		$settings                      = get_option( self::THEME_SETTINGS_OPTION, array() );
		$profile_id                    = is_array( $settings ) ? (int) ( $settings['content.profile_user_id'] ?? 0 ) : 0;
		$profile                       = $profile_id > 0 ? get_user_by( 'id', $profile_id ) : false;
		$theme_options['profile_user'] = $profile ? $profile->user_login : null;
		return array(
			'postTypes'    => $post_counts,
			'terms'        => $term_counts,
			'comments'     => is_array( $comment_ids ) ? count( $comment_ids ) : 0,
			'users'        => $user_counts,
			'menus'        => $menus,
			'options'      => $options,
			'themeOptions' => $theme_options,
			'language'     => array(
				'locale'  => get_option( 'WPLANG', 'en_US' ),
				'content' => $expected['language']['content'] ?? array(),
			),
		);
	}

	/**
	 * Reads the generated menu trees by their imported menu name.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function snapshotMenus(): array {
		$menus = array();
		foreach ( array(
			'primary' => 'joo-primary',
			'footer'  => 'joo-footer',
		) as $key => $name ) {
			$term          = get_term_by( 'name', $name, 'nav_menu' );
			$items         = $term ? wp_get_nav_menu_items( $term->term_id ) : false;
			$menus[ $key ] = $this->menuTree( is_array( $items ) ? $items : array(), 0 );
		}
		return $menus;
	}

	/**
	 * Normalizes menu items to the package's id-free structure.
	 *
	 * @param array<int, mixed> $items Menu items.
	 * @param int               $parent Parent item ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function menuTree( array $items, int $parent ): array {
		$out = array();
		usort( $items, static fn( $a, $b ): int => (int) $a->menu_order <=> (int) $b->menu_order );
		foreach ( $items as $item ) {
			if ( (int) $item->menu_item_parent !== $parent ) {
				continue;
			}
			$object   = (string) $item->object;
			$type     = 'category' === $object ? 'category' : ( 'page' === $object ? 'page' : '' );
			$target   = 'category' === $type ? get_term( (int) $item->object_id, 'category' ) : get_post( (int) $item->object_id );
			$slug     = ! $target ? '' : ( 'category' === $type ? (string) $target->slug : (string) $target->post_name );
			$entry    = array(
				'title' => (string) $item->title,
				'type'  => $type,
				'slug'  => $slug,
			);
			$children = $this->menuTree( $items, (int) $item->ID );
			if ( array() !== $children ) {
				$entry['children'] = $children;
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Creates marker metadata on one post.
	 *
	 * @param int $post_id Post ID.
	 */
	private function markPost( int $post_id ): void {
		update_post_meta( $post_id, '_joo_demo', '1' );
		update_post_meta( $post_id, self::MARKER_META, (string) $this->ledger['hash'] );
	}

	/**
	 * Creates marker metadata on one user.
	 *
	 * @param int $user_id User ID.
	 */
	private function markUser( int $user_id ): void {
		update_user_meta( $user_id, '_joo_demo', '1' );
		update_user_meta( $user_id, self::MARKER_META, (string) $this->ledger['hash'] );
	}

	/**
	 * Stores progress for crash-safe rollback.
	 */
	private function saveLedger(): void {
		update_option( self::LEDGER_OPTION, $this->ledger, false );
	}

	/**
	 * Returns the active package hash.
	 */
	private function ledgerHash(): string {
		return (string) ( $this->ledger['hash'] ?? '' );
	}

	/**
	 * Rolls back only IDs and settings present in the ledger.
	 *
	 * @return array<string, int|string>
	 */
	private function rollbackLedger(): array {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$counts = array(
			'posts'    => 0,
			'terms'    => 0,
			'users'    => 0,
			'comments' => 0,
		);
		foreach ( $this->ledger['comments'] ?? array() as $id ) {
			if ( get_comment( (int) $id ) && (string) get_comment_meta( (int) $id, self::MARKER_META, true ) === $this->ledgerHash() ) {
				wp_delete_comment( (int) $id, true );
				++$counts['comments'];
			}
		}
		foreach ( $this->ledger['posts'] ?? array() as $id ) {
			$post = get_post( (int) $id );
			if ( $post && (string) get_post_meta( (int) $id, self::MARKER_META, true ) === $this->ledgerHash() ) {
				wp_delete_post( (int) $id, true );
				++$counts['posts'];
			}
		}
		foreach ( $this->ledger['menus'] ?? array() as $id ) {
			$term = get_term( (int) $id, 'nav_menu' );
			if ( $term && ! is_wp_error( $term ) && (string) get_term_meta( (int) $id, self::MARKER_META, true ) === $this->ledgerHash() ) {
				wp_delete_nav_menu( (int) $id );
				++$counts['terms'];
			}
		}
		foreach ( $this->ledger['terms'] ?? array() as $term ) {
			if ( ! is_array( $term ) || ! isset( $term['id'], $term['taxonomy'] ) ) {
				continue;
			}
			$id       = (int) $term['id'];
			$taxonomy = (string) $term['taxonomy'];
			$found    = get_term( $id, $taxonomy );
			if ( $found && ! is_wp_error( $found ) && (string) get_term_meta( $id, self::MARKER_META, true ) === $this->ledgerHash() ) {
				wp_delete_term( $id, $taxonomy );
				++$counts['terms'];
			}
		}
		foreach ( $this->ledger['users'] ?? array() as $id ) {
			$user = get_user_by( 'id', (int) $id );
			if ( $user && (string) get_user_meta( (int) $id, self::MARKER_META, true ) === $this->ledgerHash() ) {
				wp_delete_user( (int) $id );
				++$counts['users'];
			}
		}
		$this->restoreManagedValues();
		delete_option( self::LEDGER_OPTION );
		flush_rewrite_rules( false );
		wp_cache_flush();
		return array_merge( array( 'status' => 'rolled-back' ), $counts );
	}
}
