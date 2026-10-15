<?php
/**
 * Fragment cache with tag invalidation.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

/**
 * Remembers expensive results. Keys carry a global version and one version per tag, so bumping a tag
 * invalidates every entry that used it. Uses the persistent object cache when present and transients otherwise.
 * A soft TTL with a short lock lets one request rebuild while others serve the stale value (stampede protection).
 *
 * @api
 */
final class FragmentCache {

	public const GROUP = 'joo';

	/**
	 * Cache hits during this request.
	 *
	 * @var int
	 */
	private int $hits = 0;

	/**
	 * Cache misses during this request.
	 *
	 * @var int
	 */
	private int $misses = 0;

	/**
	 * Cached tag versions.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $tags = null;

	/**
	 * Hooks the invalidation events.
	 */
	public function hook(): void {
		add_action( 'save_post', array( $this, 'onPostChange' ), 10, 2 );
		add_action( 'deleted_post', fn() => $this->bump( 'posts' ) );
		add_action( 'transition_post_status', fn() => $this->bump( 'posts' ) );
		foreach ( array( 'created_term', 'edited_term', 'delete_term', 'set_object_terms' ) as $hook ) {
			add_action( $hook, fn() => $this->bump( 'terms' ) );
		}
		add_action( 'wp_update_nav_menu', fn() => $this->bump( 'menus' ) );
		add_action( 'profile_update', fn() => $this->bump( 'profile' ) );
		add_action( 'updated_user_meta', fn() => $this->bump( 'profile' ) );
		add_action( 'added_user_meta', fn() => $this->bump( 'profile' ) );
		add_action( 'updated_option', array( $this, 'onOptionChange' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'onPostMeta' ), 10, 3 );
		}
		foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $hook ) {
			add_action( $hook, fn() => $this->bump( 'terms' ) );
		}
		add_action( 'update_option_sticky_posts', fn() => $this->bump( 'posts' ) );
		add_action( 'add_option_sticky_posts', fn() => $this->bump( 'posts' ) );
	}

	/**
	 * Theme meta changes (featured flags, company fields) invalidate the matching tag.
	 *
	 * @param mixed  $metaId  Meta id.
	 * @param mixed  $postId  Post id.
	 * @param string $metaKey Meta key.
	 */
	public function onPostMeta( $metaId, $postId, $metaKey ): void {
		unset( $metaId, $postId );
		if ( 0 !== strpos( (string) $metaKey, '_joo_' ) ) {
			return;
		}
		$this->bump( 1 === preg_match( '/^_joo_company_/', (string) $metaKey ) ? 'companies' : 'posts' );
	}

	/**
	 * Post saved: bump posts (and companies for the company post type).
	 *
	 * @param int           $post_id Post id.
	 * @param \WP_Post|null $post    Post.
	 */
	public function onPostChange( int $post_id, $post = null ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$this->bump( 'posts' );
		if ( $post instanceof \WP_Post && 'joo_company' === $post->post_type ) {
			$this->bump( 'companies' );
		}
	}

	/**
	 * An option changed: bump settings when it is a core settings option.
	 *
	 * @param string $option Option name.
	 */
	public function onOptionChange( string $option ): void {
		if ( 0 === strpos( $option, 'joo_' ) && '_settings' === substr( $option, -9 ) ) {
			$this->bump( 'settings' );
		}
	}

	/**
	 * Global cache version.
	 */
	public function version(): int {
		return max( 1, (int) get_option( 'joo_cache_version', 1 ) );
	}

	/**
	 * Invalidates everything tagged with $tag.
	 *
	 * @param string $tag Tag name.
	 */
	public function bump( string $tag ): void {
		$tags         = $this->tagVersions();
		$tags[ $tag ] = ( $tags[ $tag ] ?? 1 ) + 1;
		$this->tags   = $tags;
		update_option( 'joo_cache_tags', $tags, true );
	}

	/**
	 * Builds a cache key.
	 *
	 * @param string       $module Module id.
	 * @param string       $key    Entry key.
	 * @param list<string> $tags   Tags.
	 */
	public function key( string $module, string $key, array $tags = array() ): string {
		$tag_versions = $this->tagVersions();
		$signature    = implode( '.', array_map( static fn( string $tag ) => $tag . ( $tag_versions[ $tag ] ?? 1 ), $tags ) );
		$full         = sprintf( 'joo:%1$s:%2$s:%3$d:%4$s', $module, $key, $this->version(), $signature );
		return strlen( $full ) > 150 ? 'joo:' . $module . ':' . md5( $full ) : $full;
	}

	/**
	 * Returns a cached value or builds and stores it.
	 *
	 * @param string       $key      Entry key (unique within the module).
	 * @param int          $ttl      Seconds the value is considered fresh.
	 * @param callable     $producer Builds the value.
	 * @param list<string> $tags     Tags the value depends on.
	 * @param string       $module   Module id used in the key.
	 * @return mixed
	 */
	public function remember( string $key, int $ttl, callable $producer, array $tags = array(), string $module = 'content' ) {
		$full   = $this->key( $module, $key, $tags );
		$stored = $this->read( $full );
		if ( is_array( $stored ) && array_key_exists( 'value', $stored ) ) {
			if ( ( $stored['fresh_until'] ?? 0 ) >= time() ) {
				++$this->hits;
				return $stored['value'];
			}
			// Stale: one request rebuilds, the others keep serving the old value.
			if ( ! $this->lock( $full ) ) {
				++$this->hits;
				return $stored['value'];
			}
		}
		++$this->misses;
		$value = $producer();
		$this->write(
			$full,
			array(
				'value'       => $value,
				'fresh_until' => time() + $ttl,
			),
			$ttl * 4
		);
		return $value;
	}

	/**
	 * Stores a value without a producer (used for snapshots taken at render time).
	 *
	 * @param string       $key    Entry key.
	 * @param mixed        $value  Value.
	 * @param int          $ttl    Seconds.
	 * @param list<string> $tags   Tags.
	 * @param string       $module Module id.
	 */
	public function put( string $key, $value, int $ttl, array $tags = array(), string $module = 'content' ): void {
		$this->write(
			$this->key( $module, $key, $tags ),
			array(
				'value'       => $value,
				'fresh_until' => time() + $ttl,
			),
			$ttl * 4
		);
	}

	/**
	 * Reads a stored value (null when missing). Stale values are returned until the hard expiry.
	 *
	 * @param string       $key    Entry key.
	 * @param list<string> $tags   Tags.
	 * @param string       $module Module id.
	 * @return mixed
	 */
	public function peek( string $key, array $tags = array(), string $module = 'content' ) {
		$stored = $this->read( $this->key( $module, $key, $tags ) );
		return is_array( $stored ) && array_key_exists( 'value', $stored ) ? $stored['value'] : null;
	}

	/**
	 * Hits and misses of this request.
	 *
	 * @return array{hits: int, misses: int}
	 */
	public function stats(): array {
		return array(
			'hits'   => $this->hits,
			'misses' => $this->misses,
		);
	}

	/**
	 * Tag versions.
	 *
	 * @return array<string, int>
	 */
	private function tagVersions(): array {
		if ( null === $this->tags ) {
			$stored     = get_option( 'joo_cache_tags', array() );
			$this->tags = is_array( $stored ) ? array_map( 'intval', $stored ) : array();
		}
		return $this->tags;
	}

	/**
	 * Reads an entry.
	 *
	 * @param string $key Full key.
	 * @return mixed
	 */
	private function read( string $key ) {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $key, self::GROUP );
		}
		return get_transient( $key );
	}

	/**
	 * Writes an entry.
	 *
	 * @param string $key   Full key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Hard expiry in seconds.
	 */
	private function write( string $key, $value, int $ttl ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $value, self::GROUP, $ttl );
			return;
		}
		set_transient( $key, $value, $ttl );
	}

	/**
	 * Takes a short rebuild lock.
	 *
	 * @param string $key Full key.
	 */
	private function lock( string $key ): bool {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $key . ':lock', 1, self::GROUP, 10 );
		}
		if ( false !== get_transient( $key . ':lock' ) ) {
			return false;
		}
		set_transient( $key . ':lock', 1, 10 );
		return true;
	}
}
