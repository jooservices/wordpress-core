<?php
/**
 * Schema validator tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Config;

use JOOservices\ThemeCore\Config\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase {

	private SchemaValidator $v;

	protected function setUp(): void {
		$this->v = new SchemaValidator();
	}

	public function test_type_mismatch_is_reported_with_a_pointer(): void {
		$errors = $this->v->validate(
			array( 'n' => 'x' ),
			array(
				'type'       => 'object',
				'properties' => array( 'n' => array( 'type' => 'integer' ) ),
			)
		);
		$this->assertSame( array( '/n: expected integer, got string' ), $errors );
	}

	public function test_required_and_additional_properties(): void {
		$schema = array(
			'type'                 => 'object',
			'required'             => array( 'a' ),
			'additionalProperties' => false,
			'properties'           => array( 'a' => array( 'type' => 'string' ) ),
		);
		$this->assertSame( array(), $this->v->validate( array( 'a' => 'x' ), $schema ) );
		$this->assertCount( 1, $this->v->validate( array(), $schema ) );
		$this->assertCount(
			1,
			$this->v->validate(
				array(
					'a' => 'x',
					'b' => 1,
				),
				$schema
			)
		);
	}

	public function test_x_prefixed_properties_are_allowed_by_pattern_properties(): void {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'patternProperties'    => array( '^x-' => array() ),
			'properties'           => array(),
		);
		$this->assertSame( array(), $this->v->validate( array( 'x-custom' => 1 ), $schema ) );
		$this->assertCount( 1, $this->v->validate( array( 'custom' => 1 ), $schema ) );
	}

	public function test_enum_pattern_and_lengths(): void {
		$this->assertCount( 1, $this->v->validate( 'c', array( 'enum' => array( 'a', 'b' ) ) ) );
		$this->assertCount(
			1,
			$this->v->validate(
				'Bad',
				array(
					'type'    => 'string',
					'pattern' => '^[a-z]+$',
				)
			)
		);
		$this->assertCount(
			1,
			$this->v->validate(
				'x',
				array(
					'type'      => 'string',
					'minLength' => 2,
				)
			)
		);
		$this->assertCount(
			1,
			$this->v->validate(
				'xxx',
				array(
					'type'      => 'string',
					'maxLength' => 2,
				)
			)
		);
		$this->assertSame(
			array(),
			$this->v->validate(
				'ok',
				array(
					'type'      => 'string',
					'minLength' => 2,
					'maxLength' => 2,
					'pattern'   => '^o',
				)
			)
		);
	}

	public function test_numeric_bounds_and_integer_type(): void {
		$this->assertCount(
			1,
			$this->v->validate(
				0,
				array(
					'type'    => 'integer',
					'minimum' => 1,
				)
			)
		);
		$this->assertCount(
			1,
			$this->v->validate(
				9,
				array(
					'type'    => 'integer',
					'maximum' => 5,
				)
			)
		);
		$this->assertCount( 1, $this->v->validate( 1.5, array( 'type' => 'integer' ) ) );
		$this->assertSame(
			array(),
			$this->v->validate(
				3,
				array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 5,
				)
			)
		);
	}

	public function test_arrays_items_and_min_items(): void {
		$schema = array(
			'type'     => 'array',
			'minItems' => 1,
			'items'    => array( 'type' => 'string' ),
		);
		$this->assertCount( 1, $this->v->validate( array(), $schema ) );
		$this->assertSame( array( '/1: expected string, got integer' ), $this->v->validate( array( 'a', 5 ), $schema ) );
	}

	public function test_boolean_is_not_an_integer(): void {
		$this->assertCount( 1, $this->v->validate( true, array( 'type' => 'integer' ) ) );
	}

	public function test_additional_properties_schema_validates_values(): void {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'boolean' ),
		);
		$this->assertSame( array(), $this->v->validate( array( 'a' => true ), $schema ) );
		$this->assertCount( 1, $this->v->validate( array( 'a' => 'yes' ), $schema ) );
	}

	public function test_shipped_manifest_schema_only_uses_supported_keywords(): void {
		$schema = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/schema/manifest.schema.json' ), true );
		$this->assertSame( array(), $this->v->unsupportedKeywords( $schema ) );
	}

	public function test_unsupported_keywords_are_detected(): void {
		$this->assertSame(
			array( 'oneOf' ),
			$this->v->unsupportedKeywords(
				array(
					'type'       => 'object',
					'properties' => array( 'a' => array( 'oneOf' => array() ) ),
				)
			)
		);
	}
}
