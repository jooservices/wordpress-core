<?php
/**
 * Server-rendered resume timeline shared by the shortcode and the block.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Frontend;

use JOOservices\Resume\Admin\Settings;
use JOOservices\Resume\Model\CompanyRecord;
use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Plugin;

/**
 * One markup for every surface: semantic lists, `<time datetime>`, escaped output. Only the public projection of a company
 * is used, so private fields and private custom values can never be printed.
 */
final class Renderer {

	public const STYLE = 'joo-resume';

	/**
	 * Registers the stylesheet (enqueued only when a resume is rendered).
	 */
	public static function registerStyle(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/resume.css', JOO_RESUME_FILE ), array(), Plugin::VERSION );
	}

	/**
	 * Renders the timeline.
	 *
	 * @param array{order?: string, limit?: int, current_only?: bool} $args Arguments.
	 */
	public function render( array $args = array() ): string {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			self::registerStyle();
		}
		wp_enqueue_style( self::STYLE );
		$repo        = new CompanyRepository();
		$definitions = $repo->definitions();
		$records     = $repo->all( 'date' === ( $args['order'] ?? 'manual' ) ? 'date' : 'manual' );
		if ( ! empty( $args['current_only'] ) ) {
			$records = array_values( array_filter( $records, static fn( CompanyRecord $record ): bool => $record->isCurrent() ) );
		}
		$limit = (int) ( $args['limit'] ?? 0 );
		if ( $limit > 0 ) {
			$records = array_slice( $records, 0, $limit );
		}
		if ( array() === $records ) {
			return '<div class="joo-resume"><p class="joo-resume__empty">' . esc_html__( 'No companies to show yet.', 'jooservices-resume' ) . '</p></div>';
		}
		$settings = Settings::get();
		$now      = gmdate( 'Y-m' );
		$html     = '<div class="joo-resume"><ol class="joo-resume__list">';
		foreach ( $records as $record ) {
			$html .= $this->item( $record->toPublic( $definitions, $now ), $definitions, $settings );
		}
		return $html . '</ol></div>';
	}

	/**
	 * One company.
	 *
	 * @param array<string, mixed>       $company     Public projection.
	 * @param list<array<string, mixed>> $definitions Custom field definitions.
	 * @param array<string, mixed>       $settings    Display settings.
	 */
	private function item( array $company, array $definitions, array $settings ): string {
		$name = esc_html( (string) $company['name'] );
		if ( '' !== $company['url'] ) {
			$name = '<a href="' . esc_url( (string) $company['url'] ) . '" rel="noopener">' . $name . '</a>';
		}
		$html  = '<li class="joo-resume__item' . ( $company['current'] ? ' is-current' : '' ) . '"><article>';
		$html .= '<header class="joo-resume__header">';
		if ( (int) $company['logo_id'] > 0 ) {
			$html .= wp_get_attachment_image(
				(int) $company['logo_id'],
				'thumbnail',
				false,
				array(
					'class'   => 'joo-resume__logo',
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
		}
		$html .= '<h3 class="joo-resume__company">' . $name . '</h3>';
		$html .= '<p class="joo-resume__role">' . esc_html( $this->roleLine( $company ) ) . '</p>';
		$html .= '<p class="joo-resume__period">' . $this->period( $company, $settings ) . '</p></header>';
		if ( '' !== $company['summary'] ) {
			$html .= '<p class="joo-resume__summary">' . esc_html( (string) $company['summary'] ) . '</p>';
		}
		if ( '' !== $company['description'] ) {
			$html .= '<div class="joo-resume__description">' . wp_kses_post( (string) $company['description'] ) . '</div>';
		}
		return $html . $this->roles( $company, $settings ) . $this->achievements( $company ) . $this->tags( $company ) . $this->custom( $company, $definitions ) . '</article></li>';
	}

	/**
	 * Role, employment type, location and remote flag as one line.
	 *
	 * @param array<string, mixed> $company Company.
	 */
	private function roleLine( array $company ): string {
		$labels = array(
			'full_time' => __( 'Full time', 'jooservices-resume' ),
			'contract'  => __( 'Contract', 'jooservices-resume' ),
			'freelance' => __( 'Freelance', 'jooservices-resume' ),
			'founder'   => __( 'Founder', 'jooservices-resume' ),
		);
		$parts  = array_filter(
			array(
				(string) $company['role'],
				$labels[ (string) $company['employment'] ] ?? '',
				implode( ', ', array_filter( array( (string) $company['location']['city'], (string) $company['location']['country'] ) ) ),
				$company['remote'] ? __( 'Remote', 'jooservices-resume' ) : '',
			)
		);
		return implode( ' · ', $parts );
	}

	/**
	 * The period with machine-readable dates, the "Current" text marker and the duration.
	 *
	 * @param array<string, mixed> $company  Company.
	 * @param array<string, mixed> $settings Settings.
	 */
	private function period( array $company, array $settings ): string {
		$html  = $this->time( (string) $company['from'], $settings ) . ' – ';
		$html .= '' === $company['to'] ? '<span class="joo-resume__current">' . esc_html__( 'Present', 'jooservices-resume' ) . '</span>' : $this->time( (string) $company['to'], $settings );
		if ( ! empty( $settings['show_duration'] ) && '' !== $company['duration'] ) {
			$html .= ' <span class="joo-resume__duration">(' . esc_html( (string) $company['duration'] ) . ')</span>';
		}
		return $html;
	}

	/**
	 * A month as `<time>`.
	 *
	 * @param string               $month    `YYYY-MM`.
	 * @param array<string, mixed> $settings Settings.
	 */
	private function time( string $month, array $settings ): string {
		if ( '' === $month ) {
			return '';
		}
		$label = 'iso' === ( $settings['date_format'] ?? '' ) ? $month : wp_date( 'F Y', (int) strtotime( $month . '-01 12:00:00 UTC' ), new \DateTimeZone( 'UTC' ) );
		return '<time datetime="' . esc_attr( $month ) . '">' . esc_html( (string) $label ) . '</time>';
	}

	/**
	 * Roles inside the company.
	 *
	 * @param array<string, mixed> $company  Company.
	 * @param array<string, mixed> $settings Settings.
	 */
	private function roles( array $company, array $settings ): string {
		if ( count( $company['roles'] ) < 2 ) {
			return '';
		}
		$html = '<ul class="joo-resume__roles">';
		foreach ( $company['roles'] as $role ) {
			$html .= '<li><strong>' . esc_html( (string) $role['title'] ) . '</strong> ' . $this->time( (string) $role['from'], $settings ) . ' – ' . ( '' === $role['to'] ? esc_html__( 'Present', 'jooservices-resume' ) : $this->time( (string) $role['to'], $settings ) );
			if ( '' !== $role['summary'] ) {
				$html .= '<br>' . esc_html( (string) $role['summary'] );
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Achievements with metric, unit and link.
	 *
	 * @param array<string, mixed> $company Company.
	 */
	private function achievements( array $company ): string {
		if ( array() === $company['achievements'] ) {
			return '';
		}
		$html = '<ul class="joo-resume__achievements">';
		foreach ( $company['achievements'] as $achievement ) {
			$text = esc_html( (string) $achievement['text'] );
			if ( '' !== $achievement['url'] ) {
				$text = '<a href="' . esc_url( (string) $achievement['url'] ) . '" rel="noopener">' . $text . '</a>';
			}
			$metric = '' === $achievement['metric'] ? '' : ' <strong class="joo-resume__metric">' . esc_html( trim( $achievement['metric'] . ' ' . $achievement['unit'] ) ) . '</strong>';
			$html  .= '<li>' . $text . $metric . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Technologies.
	 *
	 * @param array<string, mixed> $company Company.
	 */
	private function tags( array $company ): string {
		if ( array() === $company['technologies'] ) {
			return '';
		}
		$html = '<ul class="joo-resume__tags" aria-label="' . esc_attr__( 'Technologies', 'jooservices-resume' ) . '">';
		foreach ( $company['technologies'] as $tag ) {
			$html .= '<li>' . esc_html( (string) $tag ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Public custom fields as a description list.
	 *
	 * @param array<string, mixed>       $company     Company.
	 * @param list<array<string, mixed>> $definitions Definitions.
	 */
	private function custom( array $company, array $definitions ): string {
		$html = '';
		foreach ( $definitions as $definition ) {
			$key = (string) $definition['key'];
			if ( ! isset( $company['custom'][ $key ] ) ) {
				continue;
			}
			$value = $company['custom'][ $key ];
			$value = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			$cell  = 'url' === $definition['type'] ? '<a href="' . esc_url( $value ) . '" rel="noopener">' . esc_html( $value ) . '</a>' : esc_html( $value );
			$html .= '<div><dt>' . esc_html( (string) $definition['label'] ) . '</dt><dd>' . $cell . '</dd></div>';
		}
		return '' === $html ? '' : '<dl class="joo-resume__custom">' . $html . '</dl>';
	}
}
