<?php
/**
 * Demo seeder, run with: SEED_DIR=<demo-dir> [SEED_RESET=1] wp eval-file /scripts/seed.php
 *
 * Imports the generated WXR (content.xml), media, menus and settings. Everything it creates carries the marker
 * `_joo_demo` (post/term/user meta), so a reset removes only demo content. A checksum of the inputs makes reruns no-ops.
 *
 * @package JOOservices\Tooling
 */

// phpcs:disable

if ( ! defined( 'WP_CLI' ) ) {
	exit( 1 );
}

$dir   = rtrim( (string) getenv( 'SEED_DIR' ), '/' );
$reset = '1' === getenv( 'SEED_RESET' );
if ( '' === $dir || ! is_file( $dir . '/content.xml' ) ) {
	WP_CLI::error( 'Usage: SEED_DIR=<demo-dir> [SEED_RESET=1] wp eval-file seed.php (content.xml not found)' );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

$checksum = md5( implode( '|', array_map( static fn( $f ) => md5_file( $dir . '/' . $f ), array( 'content.xml', 'menus.json', 'settings.json' ) ) ) . '|' . md5_file( __FILE__ ) );
if ( ! $reset && get_option( 'joo_demo_checksum' ) === $checksum ) {
	WP_CLI::success( 'Demo content is up to date (checksum ' . substr( $checksum, 0, 8 ) . ').' );
	return;
}

// 1. Remove previous demo content (only items carrying the marker).
// `any` skips post types that are excluded from search (the companion company type), so list the types explicitly.
$old = get_posts(
	array(
		'post_type'        => array_values( get_post_types() ),
		'post_status'      => 'any',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'meta_key'         => '_joo_demo',
		'meta_value'       => '1',
		'suppress_filters' => true,
	)
);
foreach ( $old as $id ) {
	wp_delete_post( (int) $id, true );
}
$old_terms = get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false, 'meta_key' => '_joo_demo', 'meta_value' => '1', 'fields' => 'ids' ) );
foreach ( is_array( $old_terms ) ? $old_terms : array() as $term_id ) {
	$term = get_term( (int) $term_id );
	if ( $term && ! is_wp_error( $term ) ) {
		wp_delete_term( (int) $term_id, $term->taxonomy );
	}
}
foreach ( array( 'joo-primary', 'joo-footer' ) as $menu_name ) {
	wp_delete_nav_menu( $menu_name );
}
// Navigation posts (the block theme builds one from the classic menu on first render) are rebuilt below.
foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $nav_id ) {
	wp_delete_post( (int) $nav_id, true );
}
// The pristine default content of a fresh install would pollute the demo (counts, feeds, sitemap).
foreach ( array( 'post:hello-world', 'page:sample-page', 'page:privacy-policy' ) as $default ) {
	list( $default_type, $default_slug ) = explode( ':', $default );
	$found = get_page_by_path( $default_slug, OBJECT, $default_type );
	if ( $found && 1 === (int) $found->post_author && '' === (string) get_post_meta( $found->ID, '_joo_demo', true ) ) {
		wp_delete_post( $found->ID, true );
	}
}
WP_CLI::log( sprintf( 'Removed %d previous demo posts.', count( $old ) ) );

$settings = json_decode( (string) file_get_contents( $dir . '/settings.json' ), true );
$menus    = json_decode( (string) file_get_contents( $dir . '/menus.json' ), true );

// 2. Users.
$users = array();
$ensure_user = static function ( string $login, string $role, string $name ) use ( &$users ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 24 ),
				'user_email'   => $login . '@example.test',
				'role'         => $role,
				'display_name' => $name,
			)
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		$user = get_user_by( 'id', (int) $id );
	}
	update_user_meta( $user->ID, '_joo_demo', '1' );
	$users[ $login ] = (int) $user->ID;
};
$ensure_user( 'viet', 'administrator', 'Viet VU' );
$ensure_user( 'editor', 'editor', 'Demo Editor' );

// 3. WXR parse.
libxml_use_internal_errors( true );
$xml = simplexml_load_file( $dir . '/content.xml', 'SimpleXMLElement', LIBXML_NOCDATA );
if ( false === $xml ) {
	WP_CLI::error( 'content.xml is not valid XML.' );
}
$ns      = $xml->getNamespaces( true );
$channel = $xml->channel;

// 4. Media (uploaded once, mapped by file name).
$upload = wp_upload_dir();
$media  = array(); // File name => array( id, url ).
foreach ( glob( $dir . '/media/*' ) ?: array() as $file ) {
	$name   = basename( $file );
	$bits   = wp_upload_bits( $name, null, (string) file_get_contents( $file ) );
	if ( ! empty( $bits['error'] ) ) {
		WP_CLI::error( 'Upload failed: ' . $bits['error'] );
	}
	$media[ $name ] = array( 'file' => $bits['file'], 'url' => $bits['url'] );
}
$replace_media = static function ( string $text ) use ( &$media ): string {
	return (string) preg_replace_callback(
		'#__MEDIA_BASE__/([A-Za-z0-9._-]+)#',
		static fn( $m ) => $media[ $m[1] ]['url'] ?? $m[0],
		$text
	);
};

// 5. Terms.
$term_ids = array();
foreach ( $channel->children( $ns['wp'] )->category as $node ) {
	$w     = $node->children( $ns['wp'] );
	$slug  = (string) $w->category_nicename;
	$par   = (string) $w->category_parent;
	$entry = wp_insert_term(
		(string) $w->cat_name,
		'category',
		array(
			'slug'        => $slug,
			'description' => (string) $w->category_description,
			'parent'      => '' !== $par ? ( $term_ids[ 'category:' . $par ] ?? 0 ) : 0,
		)
	);
	$id = is_wp_error( $entry ) ? (int) ( $entry->get_error_data()['term_id'] ?? 0 ) : (int) $entry['term_id'];
	$term_ids[ 'category:' . $slug ] = $id;
	foreach ( $w->termmeta as $tm ) {
		update_term_meta( $id, (string) $tm->meta_key, (string) $tm->meta_value );
	}
}
foreach ( $channel->children( $ns['wp'] )->tag as $node ) {
	$w     = $node->children( $ns['wp'] );
	$slug  = (string) $w->tag_slug;
	$entry = wp_insert_term( (string) $w->tag_name, 'post_tag', array( 'slug' => $slug, 'description' => (string) $w->tag_description ) );
	$id    = is_wp_error( $entry ) ? (int) ( $entry->get_error_data()['term_id'] ?? 0 ) : (int) $entry['term_id'];
	$term_ids[ 'post_tag:' . $slug ] = $id;
	foreach ( $w->termmeta as $tm ) {
		update_term_meta( $id, (string) $tm->meta_key, (string) $tm->meta_value );
	}
}

// 6. Items: attachments first, then everything else.
$items       = array();
foreach ( $channel->item as $item ) {
	$items[] = $item;
}
$type_of     = static fn( $item ) => (string) $item->children( $ns['wp'] )->post_type;
usort( $items, static fn( $a, $b ) => ( 'attachment' === $type_of( $b ) ) <=> ( 'attachment' === $type_of( $a ) ) );

$post_map    = array(); // WXR id => WP id.
$by_slug     = array();
$deferred    = array();
$counts      = array();
foreach ( $items as $item ) {
	$w      = $item->children( $ns['wp'] );
	$type   = (string) $w->post_type;
	$wxr_id = (int) $w->post_id;
	$meta   = array();
	foreach ( $w->postmeta as $pm ) {
		$meta[ (string) $pm->meta_key ] = (string) $pm->meta_value;
	}

	if ( 'attachment' === $type ) {
		$name = basename( (string) $w->attachment_url );
		if ( ! isset( $media[ $name ] ) ) {
			continue;
		}
		$attachment = array(
			'post_title'     => (string) $item->title,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => $media[ $name ]['url'],
		);
		$id = wp_insert_attachment( $attachment, $media[ $name ]['file'] );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $media[ $name ]['file'] ) );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		$media[ $name ]['id'] = $id;
		$post_map[ $wxr_id ]  = $id;
		$counts['attachment'] = ( $counts['attachment'] ?? 0 ) + 1;
		continue;
	}

	$author = $users[ (string) $item->children( 'http://purl.org/dc/elements/1.1/' )->creator ] ?? $users['viet'];
	$status = (string) $w->status;
	$id     = wp_insert_post(
		array(
			'post_type'      => $type,
			'post_title'     => (string) $item->title,
			'post_name'      => (string) $w->post_name,
			'post_status'    => $status,
			'post_password'  => (string) $w->post_password,
			'post_content'   => $replace_media( (string) $item->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded ),
			'post_excerpt'   => (string) $item->children( 'http://wordpress.org/export/1.2/excerpt/' )->encoded,
			'post_date'      => (string) $w->post_date,
			'post_date_gmt'  => (string) $w->post_date_gmt,
			'post_author'    => $author,
			'comment_status' => (string) $w->comment_status,
			'ping_status'    => 'closed',
			'menu_order'     => (int) $w->menu_order,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( 'Insert failed for ' . (string) $item->title . ': ' . $id->get_error_message() );
	}
	$post_map[ $wxr_id ]                 = (int) $id;
	$by_slug[ $type . ':' . (string) $w->post_name ] = (int) $id;
	$counts[ $type ]                     = ( $counts[ $type ] ?? 0 ) + 1;

	$cats = array();
	$tags = array();
	foreach ( $item->category as $cat ) {
		$domain = (string) $cat['domain'];
		$slug   = (string) $cat['nicename'];
		if ( 'category' === $domain && isset( $term_ids[ 'category:' . $slug ] ) ) {
			$cats[] = $term_ids[ 'category:' . $slug ];
		} elseif ( 'post_tag' === $domain && isset( $term_ids[ 'post_tag:' . $slug ] ) ) {
			$tags[] = $term_ids[ 'post_tag:' . $slug ];
		}
	}
	if ( 'post' === $type ) {
		wp_set_post_categories( $id, $cats );
		wp_set_post_terms( $id, $tags, 'post_tag' );
	}
	foreach ( $meta as $k => $v ) {
		if ( '_thumbnail_id' === $k ) {
			$deferred[] = array( $id, 'thumb', $v );
		} elseif ( '_joo_translation_of_slug' === $k ) {
			$deferred[] = array( $id, 'translation', $v );
		} elseif ( '_joo_primary_category_slug' === $k ) {
			update_post_meta( $id, '_joo_primary_category', $term_ids[ 'category:' . $v ] ?? 0 );
			// Yoast (when active) builds the %category% part of the permalink from its own primary category.
			update_post_meta( $id, '_yoast_wpseo_primary_category', $term_ids[ 'category:' . $v ] ?? 0 );
		} elseif ( '_joo_company_pinned' === $k ) {
			update_post_meta( $id, $k, '1' === $v );
		} else {
			update_post_meta( $id, $k, $v );
		}
	}

	// Comments (parents resolved in order).
	$comment_map = array();
	foreach ( $w->comment as $c ) {
		$cw     = $c->children( $ns['wp'] );
		$parent = (int) $cw->comment_parent;
		$cid    = wp_insert_comment(
			array(
				'comment_post_ID'      => $id,
				'comment_author'       => (string) $cw->comment_author,
				'comment_author_email' => (string) $cw->comment_author_email,
				'comment_content'      => (string) $cw->comment_content,
				'comment_date'         => (string) $cw->comment_date,
				'comment_date_gmt'     => (string) $cw->comment_date_gmt,
				'comment_approved'     => 1,
				'comment_parent'       => $parent > 0 ? ( $comment_map[ $parent ] ?? 0 ) : 0,
			)
		);
		$comment_map[ (int) $cw->comment_id ] = (int) $cid;
		$counts['comment']                    = ( $counts['comment'] ?? 0 ) + 1;
	}
}
foreach ( $deferred as list( $id, $kind, $value ) ) {
	if ( 'thumb' === $kind && isset( $post_map[ (int) $value ] ) ) {
		set_post_thumbnail( $id, $post_map[ (int) $value ] );
	}
	if ( 'translation' === $kind && isset( $by_slug[ 'post:' . $value ] ) ) {
		update_post_meta( $id, '_joo_translation_of', $by_slug[ 'post:' . $value ] );
	}
}
// Companies whose "to" is empty keep it empty; the Company meta sanitizer already ran through update_post_meta.

// 7. Menus.
$locations = array();
foreach ( array( 'primary' => 'joo-primary', 'footer' => 'joo-footer' ) as $key => $name ) {
	$menu_id = wp_create_nav_menu( $name );
	if ( is_wp_error( $menu_id ) ) {
		WP_CLI::error( $menu_id->get_error_message() );
	}
	$add_items = static function ( array $entries, int $parent ) use ( &$add_items, $menu_id, $term_ids, $by_slug ): void {
		foreach ( $entries as $entry ) {
			$args = array(
				'menu-item-title'     => $entry['title'],
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
			);
			if ( 'category' === $entry['type'] && isset( $term_ids[ 'category:' . $entry['slug'] ] ) ) {
				$args += array( 'menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $term_ids[ 'category:' . $entry['slug'] ] );
			} elseif ( 'page' === $entry['type'] && isset( $by_slug[ 'page:' . $entry['slug'] ] ) ) {
				$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $by_slug[ 'page:' . $entry['slug'] ] );
			} else {
				continue;
			}
			$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( ! is_wp_error( $item_id ) && ! empty( $entry['children'] ) ) {
				$add_items( $entry['children'], (int) $item_id );
			}
		}
	};
	$add_items( $menus[ $key ] ?? array(), 0 );
	$locations[ $key ] = $menu_id;
}
$registered = array_keys( get_registered_nav_menus() );
$assign     = array();
foreach ( $locations as $key => $menu_id ) {
	if ( in_array( $key, $registered, true ) ) {
		$assign[ $key ] = $menu_id;
	}
}
if ( array() !== $assign ) {
	set_theme_mod( 'nav_menu_locations', $assign );
}

// 8. Settings, profile, topic images.
foreach ( $settings['options'] as $key => $value ) {
	update_option( $key, $value );
}
$profile = $settings['profile'];
foreach ( $profile['meta'] as $key => $value ) {
	update_user_meta( $users[ $profile['user'] ], $key, $value );
}
if ( isset( $media[ $profile['avatar'] ]['id'] ) ) {
	update_user_meta( $users[ $profile['user'] ], 'joo_avatar_id', $media[ $profile['avatar'] ]['id'] );
}
foreach ( $settings['topics']['images'] as $slug => $file ) {
	if ( isset( $term_ids[ 'post_tag:' . $slug ], $media[ $file ]['id'] ) ) {
		update_term_meta( $term_ids[ 'post_tag:' . $slug ], 'joo_image_id', $media[ $file ]['id'] );
	}
}
// Navigation block: the primary menu as a wp_navigation post (the header uses the most recent one).
$nav_blocks = static function ( array $entries ) use ( &$nav_blocks, $term_ids, $by_slug ): string {
	$out = array();
	foreach ( $entries as $entry ) {
		if ( 'category' === $entry['type'] && isset( $term_ids[ 'category:' . $entry['slug'] ] ) ) {
			$id    = $term_ids[ 'category:' . $entry['slug'] ];
			$attrs = sprintf( '{"label":%1$s,"type":"category","id":%2$d,"url":%3$s,"kind":"taxonomy","isTopLevelLink":true}', wp_json_encode( $entry['title'] ), $id, wp_json_encode( get_term_link( $id ) ) );
		} elseif ( 'page' === $entry['type'] && isset( $by_slug[ 'page:' . $entry['slug'] ] ) ) {
			$id    = $by_slug[ 'page:' . $entry['slug'] ];
			$attrs = sprintf( '{"label":%1$s,"type":"page","id":%2$d,"url":%3$s,"kind":"post-type","isTopLevelLink":true}', wp_json_encode( $entry['title'] ), $id, wp_json_encode( get_permalink( $id ) ) );
		} else {
			continue;
		}
		if ( ! empty( $entry['children'] ) ) {
			$out[] = '<!-- wp:navigation-submenu ' . $attrs . " -->\n" . $nav_blocks( $entry['children'] ) . "\n<!-- /wp:navigation-submenu -->";
		} else {
			$out[] = '<!-- wp:navigation-link ' . $attrs . ' /-->';
		}
	}
	return implode( "\n", $out );
};
$links = array( $nav_blocks( $menus['primary'] ?? array() ) );
$nav_post = wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Primary', 'post_name' => 'primary', 'post_content' => implode( "\n", $links ) ) );
if ( ! is_wp_error( $nav_post ) ) {
	update_post_meta( $nav_post, '_joo_demo', '1' );
}

// The owner profile is the demo author, not the Docker administrator.
$container = class_exists( '\JOOservices\ThemeCore\Kernel' ) ? \JOOservices\ThemeCore\Kernel::container() : null;
if ( null !== $container ) {
	$container->get( \JOOservices\ThemeCore\Config\SettingsRepository::class )->save( array( 'content.profile_user_id' => $users[ $profile['user'] ] ) );
}
flush_rewrite_rules( true );
wp_cache_flush();
update_option( 'joo_demo_checksum', $checksum );

WP_CLI::success( 'Seeded: ' . wp_json_encode( $counts ) . ' (checksum ' . substr( $checksum, 0, 8 ) . ')' );
