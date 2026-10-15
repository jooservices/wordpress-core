<?php
/**
 * Prints the SEO head tags.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Support\Hooks;

/**
 * One pass on wp_head (priority 1): description, canonical, hreflang, Open Graph, Twitter, then `joo/seo/head_end` (JSON-LD).
 * The title comes from `pre_get_document_title`, robots from `wp_robots`.
 *
 * @internal
 */
final class HeadPrinter {

	/**
	 * Request context.
	 *
	 * @var Context|null
	 */
	private ?Context $context = null;

	/**
	 * Evaluations of the context (diagnostics and tests).
	 *
	 * @var int
	 */
	private int $evaluations = 0;

	/**
	 * Services.
	 *
	 * @var array{title: TitleBuilder, description: DescriptionBuilder, canonical: Canonical, robots: RobotsBuilder, social: SocialTags, hreflang: Hreflang, policy: YieldPolicy}
	 */
	private array $services;

	/**
	 * Creates the printer.
	 *
	 * @param TitleBuilder       $title       Title.
	 * @param DescriptionBuilder $description Description.
	 * @param Canonical          $canonical   Canonical.
	 * @param RobotsBuilder      $robots      Robots.
	 * @param SocialTags         $social      Social tags.
	 * @param Hreflang           $hreflang    Alternates.
	 * @param YieldPolicy        $policy      Yield policy.
	 */
	public function __construct( TitleBuilder $title, DescriptionBuilder $description, Canonical $canonical, RobotsBuilder $robots, SocialTags $social, Hreflang $hreflang, YieldPolicy $policy ) {
		$this->services = array(
			'title'       => $title,
			'description' => $description,
			'canonical'   => $canonical,
			'robots'      => $robots,
			'social'      => $social,
			'hreflang'    => $hreflang,
			'policy'      => $policy,
		);
	}

	/**
	 * Resolves the context once per request.
	 */
	public function context(): Context {
		if ( null === $this->context ) {
			++$this->evaluations;
			$this->context = Context::fromGlobals();
		}
		return $this->context;
	}

	/**
	 * How many times the context was resolved.
	 */
	public function evaluations(): int {
		return $this->evaluations;
	}

	/**
	 * Forgets the cached context (used between simulated requests).
	 */
	public function reset(): void {
		$this->context = null;
	}

	/**
	 * Registers the front-end hooks. Nothing is hooked when the policy yields at hook time or outside the front end.
	 */
	public function hook(): void {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp', array( $this, 'attach' ), 1 );
	}

	/**
	 * Attaches the output hooks after the main query exists.
	 */
	public function attach(): void {
		$this->reset();
		if ( is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
			return;
		}
		/** @var YieldPolicy $policy */
		$policy = $this->services['policy'];
		if ( $policy->shouldYield() ) {
			if ( null !== $policy->plugin() ) {
				add_action( 'wp_head', array( $this, 'collectHead' ), 0 );
				add_action( 'wp_head', array( $this, 'flushHead' ), 99999 );
			}
			return;
		}
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
		add_filter( 'wp_robots', fn( $robots ) => $this->services['robots']->filter( (array) $robots, $this->context() ), 20 );
		add_action( 'wp_head', array( $this, 'print' ), 1 );
		add_action( 'template_redirect', array( $this, 'headers' ), 1 );
	}

	/**
	 * Starts collecting the head output so a second <title> can be dropped (see {@see self::dedupeTitles()}).
	 */
	public function collectHead(): void {
		ob_start( array( self::class, 'dedupeTitles' ) );
	}

	/**
	 * Ends the collection.
	 */
	public function flushHead(): void {
		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}

	/**
	 * Keeps the first <title> of a head. Block themes print one through core and some SEO plugins print their own
	 * as well, which would leave two.
	 *
	 * @param string $html Head output.
	 */
	public static function dedupeTitles( string $html ): string {
		$seen = false;
		return (string) preg_replace_callback(
			'#<title\b[^>]*>.*?</title>\s*#is',
			static function ( array $m ) use ( &$seen ): string {
				if ( $seen ) {
					return '';
				}
				$seen = true;
				return $m[0];
			},
			$html
		);
	}

	/**
	 * Title filter.
	 *
	 * @param string $title Title from core (ignored).
	 */
	public function title( $title = '' ): string {
		unset( $title );
		return $this->services['title']->build( $this->context() );
	}

	/**
	 * Where an attachment page should redirect (its published parent), or null.
	 *
	 * @param Context $context Context.
	 */
	public function attachmentTarget( Context $context ): ?string {
		$post = $context->post();
		if ( 'attachment' !== $context->type() || null === $post || $post->post_parent < 1 || 'publish' !== get_post_status( $post->post_parent ) ) {
			return null;
		}
		$link = get_permalink( $post->post_parent );
		return is_string( $link ) ? $link : null;
	}

	/**
	 * Sends X-Robots-Tag and redirects attachment pages.
	 */
	public function headers(): void {
		$context = $this->context();
		$target  = is_user_logged_in() ? null : $this->attachmentTarget( $context );
		if ( null !== $target && wp_safe_redirect( $target, 301, 'JOOservices' ) ) {
			exit;
		}
		$value = $this->services['robots']->header( $context );
		if ( null !== $value && ! headers_sent() ) {
			header( 'X-Robots-Tag: ' . $value );
		}
	}

	/**
	 * Tags for the current page as a list (used by print() and tests).
	 *
	 * @return list<string> Rendered tags in output order.
	 */
	public function render(): array {
		$context     = $this->context();
		$lines       = array();
		$description = '';
		if ( ! $context->isRestricted() ) {
			$description = $this->services['description']->build( $context );
			if ( 'search' !== $context->type() && '404' !== $context->type() ) {
				$lines[] = '<meta name="description" content="' . esc_attr( $description ) . '">';
			}
		}
		$canonical = $this->services['canonical']->url( $context );
		if ( null !== $canonical && ! $context->isRestricted() ) {
			$lines[] = '<link rel="canonical" href="' . esc_url( $canonical ) . '">';
		}
		$alternates = array();
		if ( ! $this->services['policy']->yieldsHreflang() ) {
			$alternates = $this->services['hreflang']->alternates( $context );
			foreach ( $alternates as $lang => $url ) {
				$lines[] = '<link rel="alternate" hreflang="' . esc_attr( (string) $lang ) . '" href="' . esc_url( $url ) . '">';
			}
		}
		if ( '' !== $description && $context->isIndexable() ) {
			$title = $this->services['title']->build( $context );
			foreach ( $this->services['social']->tags( $context, $title, $description, $canonical, $alternates ) as $tag ) {
				$is_url  = in_array( $tag[1], array( 'og:url', 'og:image', 'twitter:image', 'article:author' ), true );
				$lines[] = '<meta ' . $tag[0] . '="' . esc_attr( $tag[1] ) . '" content="' . ( $is_url ? esc_url( $tag[2] ) : esc_attr( $tag[2] ) ) . '">';
			}
		}
		return $lines;
	}

	/**
	 * Prints the tags.
	 */
	public function print(): void {
		$lines = $this->render();
		if ( array() !== $lines ) {
			echo "\n<!-- JOOservices SEO -->\n" . implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every tag is escaped in render().
		}
		Hooks::action( 'joo/seo/head_end', $this->context() );
	}
}
