<?php
/**
 * Site Health tests and debug information.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Module\ModuleRegistry;
use JOOservices\ThemeCore\Support\Requirements;
use JOOservices\ThemeCore\Support\Version;

/**
 * Core tests live here; other modules add theirs with addTest().
 *
 * @internal
 */
final class SiteHealth {

	/**
	 * Registered direct tests: id => [label, callable].
	 *
	 * @var array<string, array{label: string, test: callable}>
	 */
	private array $tests = array();

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Creates the service and registers the core tests.
	 *
	 * @param Container $container Container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
		$this->addTest( 'joo_core_requirements', 'JOOservices core requirements', array( $this, 'testRequirements' ) );
		$this->addTest( 'joo_manifest_valid', 'JOOservices theme manifest', array( $this, 'testManifest' ) );
		$this->addTest( 'joo_modules_status', 'JOOservices modules', array( $this, 'testModules' ) );
		$this->addTest( 'joo_resume_plugin', 'JOOservices Resume plugin', array( $this, 'testResumePlugin' ) );
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'site_status_tests', array( $this, 'registerTests' ) );
		add_filter( 'debug_information', array( $this, 'debugInformation' ) );
	}

	/**
	 * Adds a direct test.
	 *
	 * @param string   $id    Test id.
	 * @param string   $label Label.
	 * @param callable $test  Returns a Site Health result array.
	 */
	public function addTest( string $id, string $label, callable $test ): void {
		$this->tests[ $id ] = array(
			'label' => $label,
			'test'  => $test,
		);
	}

	/**
	 * Registered test ids.
	 *
	 * @return list<string>
	 */
	public function ids(): array {
		return array_keys( $this->tests );
	}

	/**
	 * Runs a test by id.
	 *
	 * @param string $id Test id.
	 * @return array<string, mixed>
	 */
	public function run( string $id ): array {
		return ( $this->tests[ $id ]['test'] )();
	}

	/**
	 * Filter callback for site_status_tests.
	 *
	 * @param array<string, mixed> $tests WordPress tests.
	 * @return array<string, mixed>
	 */
	public function registerTests( array $tests ): array {
		foreach ( $this->tests as $id => $definition ) {
			$tests['direct'][ $id ] = array(
				'label' => $definition['label'],
				'test'  => $definition['test'],
			);
		}
		return $tests;
	}

	/**
	 * Requirements test.
	 *
	 * @return array<string, mixed>
	 */
	public function testRequirements(): array {
		$problems = Requirements::check();
		if ( array() === $problems ) {
			return $this->result( 'good', 'JOOservices core requirements', 'PHP, WordPress and required extensions meet the core requirements.' );
		}
		return $this->result( 'critical', 'JOOservices core requirements are not met', implode( ' ', array_column( $problems, 'message' ) ) );
	}

	/**
	 * Manifest test.
	 *
	 * @return array<string, mixed>
	 */
	public function testManifest(): array {
		/** @var Manifest $manifest */
		$manifest = $this->container->get( Manifest::class );
		if ( $manifest->isValid() ) {
			return $this->result( 'good', 'The theme manifest is valid', sprintf( 'Theme "%s" requires core %s.', $manifest->name(), $manifest->coreRange() ) );
		}
		return $this->result( 'critical', 'The theme manifest is invalid', implode( '; ', $manifest->errors() ) );
	}

	/**
	 * Module status test.
	 *
	 * @return array<string, mixed>
	 */
	public function testModules(): array {
		$status = Kernel::status();
		$bad    = array_filter( $status, static fn( string $s ) => in_array( $s, array( ModuleRegistry::STATUS_FAILED, ModuleRegistry::STATUS_BLOCKED ), true ) );
		if ( array() === $bad ) {
			return $this->result( 'good', 'All JOOservices modules are running', sprintf( '%d module(s) booted.', count( array_filter( $status, static fn( string $s ) => ModuleRegistry::STATUS_BOOTED === $s ) ) ) );
		}
		$registry = Kernel::registry();
		$lines    = array();
		foreach ( $bad as $id => $state ) {
			$lines[] = sprintf( '%s (%s): %s', $id, $state, null === $registry ? '' : $registry->message( (string) $id ) );
		}
		return $this->result( in_array( ModuleRegistry::STATUS_FAILED, $bad, true ) ? 'critical' : 'recommended', 'Some JOOservices modules are not running', implode( ' ', $lines ) );
	}

	/**
	 * Resume plugin test.
	 *
	 * @return array<string, mixed>
	 */
	public function testResumePlugin(): array {
		if ( post_type_exists( 'joo_company' ) ) {
			return $this->result( 'good', 'JOOservices Resume is active', 'Company history, profile and editorial fields are available.' );
		}
		return $this->result( 'recommended', 'JOOservices Resume is not active', 'Install and activate JOOservices Resume to manage company history, profile and editorial fields. Blocks that need this data stay empty until then.' );
	}

	/**
	 * Adds the debug information section.
	 *
	 * @param array<string, mixed> $info Debug info.
	 * @return array<string, mixed>
	 */
	public function debugInformation( array $info ): array {
		/** @var Manifest $manifest */
		$manifest = $this->container->get( Manifest::class );
		/** @var Settings $settings */
		$settings         = $this->container->get( Settings::class );
		$counts           = array_count_values( array_map( fn( string $key ) => $settings->source( $key ), array_keys( $settings->schema()->all() ) ) );
		$fields           = array(
			'version'  => array(
				'label' => __( 'Core version', 'joo-core' ),
				'value' => Version::core(),
			),
			'theme'    => array(
				'label' => __( 'Manifest', 'joo-core' ),
				'value' => $manifest->slug() . ' (requires core ' . $manifest->coreRange() . ')',
			),
			'modules'  => array(
				'label' => __( 'Modules', 'joo-core' ),
				'value' => wp_json_encode( Kernel::status() ),
			),
			'settings' => array(
				'label' => __( 'Settings by source', 'joo-core' ),
				'value' => wp_json_encode( $counts ),
			),
		);
		$info['joo-core'] = array(
			'label'  => __( 'JOOservices core', 'joo-core' ),
			'fields' => $fields,
		);
		return $info;
	}

	/**
	 * Builds a Site Health result.
	 *
	 * @param string $status      good|recommended|critical.
	 * @param string $label       Short result label.
	 * @param string $description Description.
	 * @return array<string, mixed>
	 */
	private function result( string $status, string $label, string $description ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'JOOservices', 'joo-core' ),
				'color' => 'critical' === $status ? 'red' : ( 'good' === $status ? 'blue' : 'orange' ),
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '',
			'test'        => 'joo_' . sanitize_key( $label ),
		);
	}
}
