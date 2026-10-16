<?php
/**
 * Repeater sanitisers and validators.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Unit;

use JOOservices\Resume\Model\Repeaters;
use PHPUnit\Framework\TestCase;

final class RepeatersTest extends TestCase {

	/**
	 * @param array{items?: list<mixed>, errors: list<array{code: string, field: string}>} $result Result.
	 * @return list<string>
	 */
	private function codes( array $result ): array {
		return array_map( static fn( array $error ): string => $error['code'] . '@' . $error['field'], $result['errors'] );
	}

	public function test_encode_and_decode_round_trip_with_a_version(): void {
		$this->assertSame( '', Repeaters::encode( array() ) );
		$json = Repeaters::encode( array( array( 'title' => 'Lead' ) ) );
		$this->assertSame( '{"v":1,"items":[{"title":"Lead"}]}', $json );
		$this->assertSame( array( array( 'title' => 'Lead' ) ), Repeaters::decode( $json ) );
		$this->assertSame( array( array( 'a' => 1 ) ), Repeaters::decode( array( array( 'a' => 1 ), 'skip' ) ) );
		$this->assertSame( array(), Repeaters::decode( 'not json' ) );
		$this->assertSame( array(), Repeaters::decode( 5 ) );
	}

	public function test_roles_are_validated_against_the_company_period(): void {
		$ok = Repeaters::roles(
			array(
				array(
					'title' => 'Engineer',
					'from'  => '2022-03',
					'to'    => '2023-02',
				),
				array(
					'title'   => '<b>Lead</b>',
					'from'    => '2023-03',
					'to'      => '',
					'summary' => 'Ran the team',
				),
			),
			'2022-03',
			''
		);
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( array( 'Lead', 'Engineer' ), array_column( $ok['items'], 'title' ), 'newest first, markup removed' );

		$bad = Repeaters::roles(
			array(
				array( 'title' => '' ),
				array(
					'title' => 'A',
					'from'  => '2022-13',
				),
				array(
					'title' => 'B',
					'from'  => '2024-01',
					'to'    => '2023-01',
				),
				array(
					'title' => 'C',
					'from'  => '2020-01',
					'to'    => '2021-01',
				),
				array(
					'title' => 'D',
					'from'  => '2022-03',
					'to'    => '2022-12',
				),
				array(
					'title' => 'E',
					'from'  => '2022-12',
					'to'    => '2023-06',
				),
			),
			'2022-03',
			'2025-06'
		);
		$this->assertSame(
			array( 'role_title@roles.0', 'role_month@roles.1', 'role_order@roles.2', 'role_outside@roles.3', 'role_overlap@roles.5' ),
			$this->codes( $bad )
		);
	}

	public function test_achievements_check_text_metric_and_link(): void {
		$ok = Repeaters::achievements(
			array(
				array(
					'text'   => 'Cut cost',
					'metric' => '1,200',
					'unit'   => 'USD',
					'url'    => 'https://acme.test/case',
				),
			)
		);
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( '1,200', $ok['items'][0]['metric'] );
		$bad = Repeaters::achievements(
			array(
				array( 'text' => '' ),
				array(
					'text'   => 'X',
					'metric' => 'lots',
				),
				array(
					'text' => 'Y',
					'url'  => 'http://insecure.test',
				),
			)
		);
		$this->assertSame( array( 'achievement_text@achievements.0', 'achievement_metric@achievements.1', 'achievement_url@achievements.2' ), $this->codes( $bad ) );
	}

	public function test_technologies_are_unique_trimmed_and_limited(): void {
		$this->assertSame( array( 'PHP', 'Go' ), Repeaters::technologies( array( ' PHP ', 'php', '', 'Go', array( 'x' ) ) ) );
		$this->assertCount( 40, Repeaters::technologies( array_map( static fn( int $n ): string => 'tag' . $n, range( 1, 60 ) ) ) );
	}

	public function test_definitions_and_custom_values_follow_their_type(): void {
		$defs = Repeaters::definitions(
			array(
				array(
					'key'    => 'team_size',
					'label'  => 'Team size',
					'type'   => 'number',
					'public' => true,
					'order'  => 2,
				),
				array(
					'key'   => 'left_on',
					'label' => 'Left on',
					'type'  => 'date',
					'order' => 1,
				),
				array(
					'key'   => 'Bad Key',
					'label' => 'x',
				),
				array(
					'key'   => 'no_label',
					'label' => '',
				),
				array(
					'key'   => 'team_size',
					'label' => 'Dup',
				),
				array(
					'key'   => 'site',
					'label' => 'Site',
					'type'  => 'url',
				),
				array(
					'key'   => 'stack',
					'label' => 'Stack',
					'type'  => 'list',
				),
				array(
					'key'   => 'note',
					'label' => 'Note',
					'type'  => 'text',
				),
			)
		);
		$this->assertSame( array( 'left_on', 'team_size', 'site', 'stack', 'note' ), array_column( $defs['items'], 'key' ) );
		$this->assertSame( array( 'definition_key@definitions.2', 'definition_label@definitions.3', 'definition_duplicate@definitions.4' ), $this->codes( $defs ) );

		$ok = Repeaters::customValues(
			array(
				'team_size' => '12',
				'left_on'   => '2024-02-29',
				'site'      => 'https://acme.test',
				'stack'     => array( 'Go', 'go', 'PHP' ),
				'note'      => '<i>fine</i>',
				'unknown'   => 'dropped',
				'empty'     => '',
			),
			$defs['items']
		);
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame(
			array(
				'left_on'   => '2024-02-29',
				'team_size' => 12,
				'site'      => 'https://acme.test',
				'stack'     => array( 'Go', 'PHP' ),
				'note'      => 'fine',
			),
			$ok['values']
		);
		$bad = Repeaters::customValues(
			array(
				'team_size' => 'many',
				'left_on'   => '2023-02-30',
				'site'      => 'http://x.test',
			),
			$defs['items']
		);
		$this->assertSame( array( 'custom_date@custom.left_on', 'custom_number@custom.team_size', 'custom_url@custom.site' ), $this->codes( $bad ) );
	}

	public function test_text_and_https_helpers(): void {
		$this->assertSame( 'a b', Repeaters::text( "  <p>a</p>\n b ", 10 ) );
		$this->assertSame( 'abc', Repeaters::text( 'abcdef', 3 ) );
		$this->assertSame( '', Repeaters::text( array( 'x' ), 5 ) );
		$this->assertTrue( Repeaters::isHttps( 'https://acme.test/a?b=1' ) );
		foreach ( array( 'http://acme.test', 'https://', 'javascript:alert(1)', 'acme.test' ) as $bad ) {
			$this->assertFalse( Repeaters::isHttps( $bad ), $bad );
		}
	}
}
