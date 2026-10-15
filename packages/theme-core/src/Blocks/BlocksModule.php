<?php
/**
 * Blocks module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Blocks\Blocks\CategoryTree;
use JOOservices\ThemeCore\Blocks\Blocks\Companies;
use JOOservices\ThemeCore\Blocks\Blocks\RelatedPosts;
use JOOservices\ThemeCore\Blocks\Blocks\TagList;
use JOOservices\ThemeCore\Blocks\Blocks\TopTopics;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Lazy\FragmentLazy;
use JOOservices\ThemeCore\Module\AbstractModule;

/**
 * Dynamic block library foundation: registrar, patterns, colour scheme and the editor bundle.
 *
 * @internal
 */
final class BlocksModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'blocks';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'assets', 'content', 'lazy', 'compat', 'seo' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( BlockRegistrar::class, static fn( Container $c ) => new BlockRegistrar( $c ) );
		$container->singleton( PatternLoader::class, static fn( Container $c ) => new PatternLoader( $c->get( Manifest::class ) ) );
		$container->singleton( ThemeScheme::class, static fn( Container $c ) => new ThemeScheme( $c->get( Manifest::class ) ) );
		$container->singleton( HeadingAnchors::class, static fn() => new HeadingAnchors() );
		$container->singleton( PostPages::class, static fn() => new PostPages() );
		$container->singleton( CodeEnhancer::class, static fn() => new CodeEnhancer() );
		$container->singleton( EditorBundle::class, static fn( Container $c ) => new EditorBundle( $c->get( BlockRegistrar::class ) ) );
	}

	/**
	 * Hooks.
	 */
	protected function doBoot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		$c->get( BlockRegistrar::class )->hook();
		$c->get( PatternLoader::class )->hook();
		$c->get( ThemeScheme::class )->hook();
		$c->get( EditorBundle::class )->hook();
		$c->get( HeadingAnchors::class )->hook();
		$c->get( CodeEnhancer::class )->hook();
		$c->get( PostPages::class )->hook();

		/** @var AssetRegistry $assets */
		$assets = $c->get( AssetRegistry::class );
		$assets->module( 'joo-code-copy', 'code-copy', array( 'lazy' => true ) );

		/** @var FragmentLazy $fragments */
		$fragments = $c->get( FragmentLazy::class );
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars   = is_array( $vars ) ? $vars : array();
				$vars[] = Companies::QUERY_VAR;
				return $vars;
			}
		);
		foreach ( array( Companies::class, CategoryTree::class, TopTopics::class, TagList::class ) as $class ) {
			$fragments->register(
				$class::fragment(),
				static function ( array $args ) use ( $c, $class ): string {
					$block = $c->get( BlockRegistrar::class )->instance( $class::name() );
					return $block instanceof FragmentBlock ? $block->html( $args ) : '';
				},
				$class::schema(),
				$class::height()
			);
		}
		$fragments->register(
			RelatedPosts::FRAGMENT,
			static function ( array $args ) use ( $c ): string {
				$block = $c->get( BlockRegistrar::class )->instance( 'joo/related-posts' );
				return $block instanceof RelatedPosts ? $block->html( (int) $args['post'], (int) $args['limit'], (bool) ( $args['image'] ?? true ), (bool) ( $args['excerpt'] ?? false ) ) : '';
			},
			array(
				'post'    => array(
					'type' => 'int',
					'min'  => 1,
				),
				'limit'   => array(
					'type'    => 'int',
					'default' => 3,
					'min'     => 1,
					'max'     => 8,
				),
				'image'   => array(
					'type'    => 'bool',
					'default' => true,
				),
				'excerpt' => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
			360
		);
	}
}
