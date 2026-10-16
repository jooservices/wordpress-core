<?php
/**
 * Import and export mapping.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Unit;

use JOOservices\Resume\Transfer\Mapper;
use PHPUnit\Framework\TestCase;

final class MapperTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function raw(): array {
		return array(
			'name'          => 'Example Systems',
			'role'          => 'CTO',
			'from'          => '2022-03',
			'to'            => '',
			'pinned'        => true,
			'url'           => 'https://example.test',
			'summary'       => 'Built it.',
			'city'          => 'Hanoi',
			'country'       => 'Vietnam',
			'employment'    => 'founder',
			'remote'        => true,
			'roles'         => array(
				array(
					'title' => 'CTO',
					'from'  => '2023-01',
					'to'    => '',
				),
			),
			'achievements'  => array(
				array(
					'text'   => 'Cut cost',
					'metric' => '40',
					'unit'   => '%',
				),
			),
			'technologies'  => array( 'PHP', 'Go' ),
			'custom'        => array( 'team_size' => '12' ),
			'compensation'  => '100k',
			'public_fields' => array( 'compensation', 'bogus' ),
			'menu_order'    => 3,
		);
	}

	public function test_a_valid_row_is_normalised(): void {
		$defs   = array(
			array(
				'key'    => 'team_size',
				'label'  => 'Team',
				'type'   => 'number',
				'public' => true,
				'order'  => 0,
			),
		);
		$result = Mapper::company( $this->raw(), $defs );
		$this->assertSame( array(), $result['errors'] );
		$company = $result['company'];
		$this->assertTrue( $company['pinned'] );
		$this->assertSame( array( 'team_size' => 12 ), $company['custom'] );
		$this->assertSame( array( 'compensation' ), $company['public_fields'] );
		$this->assertSame( 3, $company['menu_order'] );
		$this->assertSame( 'CTO', $company['roles'][0]['title'] );
	}

	public function test_a_closed_company_cannot_stay_pinned_and_bad_rows_report_every_problem(): void {
		$closed       = $this->raw();
		$closed['to'] = '2025-06';
		$this->assertFalse( Mapper::company( $closed, array() )['company']['pinned'] );

		$bad   = Mapper::company(
			array(
				'name'       => '',
				'from'       => '2025-06',
				'to'         => '2024-01',
				'url'        => 'http://x.test',
				'employment' => 'slave',
				'roles'      => array( array( 'title' => '' ) ),
			),
			array()
		);
		$codes = array_column( $bad['errors'], 'code' );
		$this->assertSame( array( 'company_name', 'company_order', 'company_url', 'employment', 'role_title' ), $codes );
		$this->assertSame(
			array( 'company_month' ),
			array_column(
				Mapper::company(
					array(
						'name' => 'A',
						'from' => '2022-13',
					),
					array()
				)['errors'],
				'code'
			)
		);
	}

	public function test_json_resume_work_entries_become_companies(): void {
		$json   = (string) json_encode(
			array(
				'work' => array(
					array(
						'name'       => 'Acme',
						'position'   => 'Developer',
						'url'        => 'https://acme.test',
						'startDate'  => '2019-04-15',
						'endDate'    => '2021-08',
						'summary'    => 'Worked',
						'location'   => 'Berlin',
						'highlights' => array( 'Shipped X', 'Shipped Y' ),
					),
				),
			)
		);
		$result = Mapper::parse( $json, array() );
		$this->assertSame( 'json-resume', $result['format'] );
		$this->assertSame( array(), $result['errors'] );
		$company = $result['rows'][0]['company'];
		$this->assertSame( array( 'Acme', 'Developer', '2019-04', '2021-08', 'Berlin' ), array( $company['name'], $company['role'], $company['from'], $company['to'], $company['city'] ) );
		$this->assertSame( array( 'Shipped X', 'Shipped Y' ), array_column( $company['achievements'], 'text' ) );
		$this->assertSame( '2019-13-01', Mapper::month( '2019-13-01' ), 'an invalid date stays as is so validation reports it' );
	}

	public function test_documents_that_cannot_be_read_are_reported(): void {
		$this->assertSame( 'import_json', Mapper::parse( 'not json', array() )['errors'][0]['code'] );
		$this->assertSame( 'import_format', Mapper::parse( '{"hello":1}', array() )['errors'][0]['code'] );
		$this->assertSame( 'import_empty', Mapper::parse( '{"work":[]}', array() )['errors'][0]['code'] );
		$many = (string) json_encode(
			array(
				'work' => array_fill(
					0,
					201,
					array(
						'name'      => 'A',
						'startDate' => '2020-01',
					)
				),
			)
		);
		$this->assertSame( 'import_rows', Mapper::parse( $many, array() )['errors'][0]['code'] );
	}

	public function test_own_export_round_trips_without_loss(): void {
		$defs    = array(
			array(
				'key'    => 'team_size',
				'label'  => 'Team',
				'type'   => 'number',
				'public' => true,
				'order'  => 0,
			),
		);
		$company = Mapper::company( $this->raw(), $defs )['company'];
		$json    = Mapper::toOwn( array( $company ) );
		$again   = Mapper::parse( $json, $defs );
		$this->assertSame( Mapper::FORMAT, $again['format'] );
		$this->assertSame( array(), $again['rows'][0]['errors'] );
		$this->assertSame( $company, $again['rows'][0]['company'] );
	}

	public function test_json_resume_and_csv_exports_carry_public_data_only(): void {
		$company = Mapper::company( $this->raw(), array() )['company'];
		$resume  = (string) Mapper::toJsonResume( array( $company ) );
		$this->assertStringNotContainsString( '100k', $resume );
		$data = json_decode( $resume, true );
		$this->assertSame( 'CTO', $data['work'][0]['position'] );
		$this->assertArrayNotHasKey( 'endDate', $data['work'][0] );
		$closed       = $company;
		$closed['to'] = '2025-06';
		$this->assertSame( '2025-06', json_decode( Mapper::toJsonResume( array( $closed ) ), true )['work'][0]['endDate'] );

		$company['name'] = '=HYPERLINK("http://evil")';
		$csv             = Mapper::toCsv( array( $company ) );
		$this->assertStringContainsString( "\"'=HYPERLINK(\"\"http://evil\"\")\"", $csv );
		$this->assertStringNotContainsString( '100k', $csv );
		$this->assertStringContainsString( '"PHP; Go"', $csv );
		$this->assertStringStartsWith( '"name","role"', $csv );
	}

	public function test_a_full_resume_round_trips_without_loss(): void {
		$defs      = array(
			array(
				'key'    => 'team_size',
				'label'  => 'Team size',
				'type'   => 'number',
				'public' => true,
				'order'  => 0,
			),
			array(
				'key'    => 'stack',
				'label'  => 'Stack',
				'type'   => 'list',
				'public' => true,
				'order'  => 1,
			),
			array(
				'key'    => 'site',
				'label'  => 'Site',
				'type'   => 'url',
				'public' => false,
				'order'  => 2,
			),
		);
		$companies = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$raw                 = $this->raw();
			$raw['name']         = 'Company ' . $i;
			$raw['from']         = sprintf( '%d-01', 2010 + $i * 2 );
			$raw['to']           = 5 === $i ? '' : sprintf( '%d-12', 2011 + $i * 2 );
			$raw['pinned']       = 5 === $i;
			$raw['menu_order']   = $i;
			$raw['roles']        = array(
				array(
					'title' => 'Junior',
					'from'  => $raw['from'],
					'to'    => sprintf( '%d-06', 2010 + $i * 2 ),
				),
				array(
					'title' => 'Mid',
					'from'  => sprintf( '%d-07', 2010 + $i * 2 ),
					'to'    => sprintf( '%d-12', 2010 + $i * 2 ),
				),
				array(
					'title' => 'Senior',
					'from'  => sprintf( '%d-01', 2011 + $i * 2 ),
					'to'    => $raw['to'],
				),
			);
			$raw['achievements'] = array_map(
				static fn( int $n ): array => array(
					'text'   => 'Achievement ' . $n,
					'metric' => (string) ( $n * 10 ),
					'unit'   => '%',
					'url'    => 'https://example.test/' . $n,
				),
				range( 1, 10 )
			);
			$raw['custom']       = array(
				'team_size' => 5 + $i,
				'stack'     => array( 'PHP', 'Go' ),
				'site'      => 'https://example.test',
			);
			$result              = Mapper::company( $raw, $defs );
			$this->assertSame( array(), $result['errors'], 'company ' . $i );
			$companies[] = $result['company'];
		}
		$parsed = Mapper::parse( Mapper::toOwn( $companies ), $defs );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertSame( $companies, array_column( $parsed['rows'], 'company' ) );
		$this->assertCount( 3, $parsed['rows'][0]['company']['roles'] );
		$this->assertCount( 10, $parsed['rows'][0]['company']['achievements'] );
		$this->assertCount( 3, $parsed['rows'][0]['company']['custom'] );
	}
}
