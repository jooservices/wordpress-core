<?php
/**
 * Container tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Container;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Container\ContainerException;
use JOOservices\ThemeCore\Container\NotFoundException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase {

	public function test_bind_builds_a_new_instance_each_time(): void {
		$c = new Container();
		$c->bind( 'obj', static fn() => new \stdClass() );
		$this->assertNotSame( $c->get( 'obj' ), $c->get( 'obj' ) );
	}

	public function test_singleton_is_cached(): void {
		$c = new Container();
		$c->singleton( 'obj', static fn() => new \stdClass() );
		$this->assertSame( $c->get( 'obj' ), $c->get( 'obj' ) );
	}

	public function test_instance_returns_the_value(): void {
		$c = new Container();
		$c->instance( 'answer', 42 );
		$this->assertSame( 42, $c->get( 'answer' ) );
		$this->assertTrue( $c->has( 'answer' ) );
	}

	public function test_factories_receive_the_container(): void {
		$c = new Container();
		$c->instance( 'a', 'A' );
		$c->singleton( 'b', static fn( Container $container ) => $container->get( 'a' ) . 'B' );
		$this->assertSame( 'AB', $c->get( 'b' ) );
	}

	public function test_extend_decorates_in_registration_order(): void {
		$c = new Container();
		$c->singleton( 's', static fn() => 'x' );
		$c->extend( 's', static fn( $s ) => $s . '1' );
		$c->extend( 's', static fn( $s ) => $s . '2' );
		$this->assertSame( 'x12', $c->get( 's' ) );
	}

	public function test_extending_a_resolved_singleton_applies_on_next_get(): void {
		$c = new Container();
		$c->singleton( 's', static fn() => 'x' );
		$this->assertSame( 'x', $c->get( 's' ) );
		$c->extend( 's', static fn( $s ) => $s . '!' );
		$this->assertSame( 'x!', $c->get( 's' ) );
	}

	public function test_unknown_id_throws_not_found(): void {
		$this->expectException( NotFoundException::class );
		( new Container() )->get( 'missing' );
	}

	public function test_has_is_false_for_unknown_ids(): void {
		$this->assertFalse( ( new Container() )->has( 'missing' ) );
	}

	public function test_circular_dependency_reports_the_chain(): void {
		$c = new Container();
		$c->singleton( 'a', static fn( Container $container ) => $container->get( 'b' ) );
		$c->singleton( 'b', static fn( Container $container ) => $container->get( 'a' ) );
		try {
			$c->get( 'a' );
			$this->fail( 'Expected a ContainerException.' );
		} catch ( ContainerException $e ) {
			$this->assertStringContainsString( 'a -> b -> a', $e->getMessage() );
		}
	}

	public function test_failing_factory_does_not_cache_a_partial_state(): void {
		$c     = new Container();
		$calls = 0;
		$c->singleton(
			's',
			static function () use ( &$calls ) {
				++$calls;
				if ( 1 === $calls ) {
					throw new \RuntimeException( 'boom' );
				}
				return 'ok';
			}
		);
		try {
			$c->get( 's' );
			$this->fail( 'Expected an exception.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}
		$this->assertSame( 'ok', $c->get( 's' ) );
	}

	public function test_rebinding_replaces_the_service(): void {
		$c = new Container();
		$c->singleton( 's', static fn() => 'old' );
		$c->get( 's' );
		$c->singleton( 's', static fn() => 'new' );
		$this->assertSame( 'new', $c->get( 's' ) );
	}
}
