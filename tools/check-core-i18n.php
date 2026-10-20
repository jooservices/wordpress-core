<?php
/**
 * Check core PHP output, block render templates, and the theme footer for untranslated literal text.
 *
 * @package JOOservices
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * Collects static output and display metadata literals for one PHP file.
 */
final class JOO_Core_I18n_Scan_Visitor extends NodeVisitorAbstract {
	/** @var list<string> */
	public array $errors = array();

	public function __construct( private string $path ) {}

	public function enterNode( Node $node ): ?int {
		if ( $node instanceof Node\Stmt\Echo_ ) {
			foreach ( $node->exprs as $expression ) {
				$this->checkOutput( $expression );
			}
		}

		if ( $node instanceof Node\Expr\Print_ ) {
			$this->checkOutput( $node->expr );
		}
		if ( $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name ) {
			$name = strtolower( $node->name->getLast() );
			if ( in_array( $name, array( 'wp_die', 'submit_button' ), true ) && isset( $node->args[0] ) ) {
				$this->checkOutput( $node->args[0]->value );
			}
			if ( in_array( $name, array( 'printf', 'vprintf' ), true ) && isset( $node->args[0] ) ) {
				$this->checkOutput( $node->args[0]->value );
			}
			if ( 'add_settings_error' === $name && isset( $node->args[2] ) ) {
				$this->checkOutput( $node->args[2]->value );
			}
		}
		if ( $node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name && 'wp_cli' === strtolower( $node->class->getLast() ) && $node->name instanceof Node\Identifier && in_array( strtolower( $node->name->toString() ), array( 'confirm', 'error', 'line', 'log', 'success', 'warning' ), true ) && isset( $node->args[0] ) ) {
			$this->checkOutput( $node->args[0]->value );
		}
		if ( $node instanceof Node\Expr\New_ && $node->class instanceof Node\Name && 'wp_error' === strtolower( $node->class->getLast() ) && isset( $node->args[1] ) ) {
			$this->checkOutput( $node->args[1]->value );
		}

		if ( $node instanceof Node\Expr\Array_ ) {
			if ( is_file_header_map( $node ) ) {
				return null;
			}
			foreach ( $node->items as $item ) {
				if ( null === $item || ! $item->key instanceof Node\Scalar\String_ ) {
					continue;
				}
				if ( ! in_array( strtolower( $item->key->value ), joo_core_display_keys(), true ) ) {
					continue;
				}
				$this->checkOutput( $item->value );
			}
		}

		return null;
	}

	private function checkOutput( Node\Expr $expression ): void {
		foreach ( visible_html_strings( output_template( $expression ) ) as $text ) {
			if ( ! is_translatable_text( $text ) ) {
				continue;
			}
			$this->errors[] = sprintf( '%s:%d: hard-coded visible string: %s', $this->path, $expression->getStartLine(), normalize_text( $text ) );
		}
	}
}

$root  = dirname( __DIR__ );
$paths = array_slice( $argv, 1 );

if ( array() === $paths ) {
	$paths    = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/packages/theme-core/src' ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$paths[] = $file->getPathname();
		}
	}
	$paths = array_merge(
		$paths,
		glob( $root . '/packages/theme-core/resources/blocks/*/render.php' ) ?: array(),
		array(
			$root . '/themes/jooservices/parts/footer.html',
			$root . '/themes/jooservices/patterns/site-footer.php',
		)
	);
}

$paths  = array_values( array_unique( $paths ) );
$parser = ( new ParserFactory() )->createForNewestSupportedVersion();
$failed = false;

foreach ( $paths as $path ) {
	$path = str_starts_with( $path, '/' ) ? $path : $root . '/' . $path;
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Core i18n scan: file not found: {$path}\n" );
		$failed = true;
		continue;
	}

	$source = file_get_contents( $path );
	if ( false === $source ) {
		fwrite( STDERR, "Core i18n scan: could not read: {$path}\n" );
		$failed = true;
		continue;
	}

	try {
		$statements = $parser->parse( $source ) ?? array();
	} catch ( Throwable $error ) {
		fwrite( STDERR, sprintf( "Core i18n scan: could not parse %s: %s\n", $path, $error->getMessage() ) );
		$failed = true;
		continue;
	}

	$relative  = str_starts_with( $path, $root . '/' ) ? substr( $path, strlen( $root ) + 1 ) : $path;
	$traverser = new NodeTraverser();
	$visitor   = new JOO_Core_I18n_Scan_Visitor( $relative );
	$traverser->addVisitor( new ParentConnectingVisitor() );
	$traverser->addVisitor( $visitor );
	$traverser->traverse( $statements );
	foreach ( $visitor->errors as $error ) {
		fwrite( STDERR, $error . "\n" );
		$failed = true;
	}

	$inlineHtml = '';
	foreach ( token_get_all( $source ) as $token ) {
		if ( is_array( $token ) && T_INLINE_HTML === $token[0] ) {
			$inlineHtml .= $token[1];
		}
	}
	foreach ( visible_html_strings( $inlineHtml ) as $text ) {
		if ( ! is_translatable_text( $text ) ) {
			continue;
		}
		fwrite( STDERR, sprintf( '%s: hard-coded visible string: %s' . "\n", $relative, normalize_text( $text ) ) );
		$failed = true;
	}
}

if ( $failed ) {
	exit( 1 );
}

echo "Core PHP i18n scan passed.\n";

/**
 * Turn a PHP output expression into its statically visible portion.
 */
function output_template( Node\Expr $expression ): string {
	$placeholder = "\u{E000}";
	if ( $expression instanceof Node\Scalar\String_ ) {
		return preg_replace( '/%(?:\d+\$)?[-+ 0]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', $placeholder, $expression->value ) ?? $expression->value;
	}
	if ( $expression instanceof Node\Scalar\Encapsed ) {
		$output = '';
		foreach ( $expression->parts as $part ) {
			$output .= $part instanceof Node\Scalar\EncapsedStringPart ? $part->value : $placeholder;
		}
		return $output;
	}
	if ( $expression instanceof Node\Expr\BinaryOp\Concat ) {
		return output_template( $expression->left ) . output_template( $expression->right );
	}
	if ( $expression instanceof Node\Expr\Ternary ) {
		return ( null === $expression->if ? $placeholder : output_template( $expression->if ) ) . output_template( $expression->else );
	}
	if ( $expression instanceof Node\Expr\Cast\String_ ) {
		return output_template( $expression->expr );
	}
	if ( $expression instanceof Node\Expr\FuncCall ) {
		$name = $expression->name instanceof Node\Name ? strtolower( $expression->name->getLast() ) : '';
		if ( in_array( $name, joo_core_translation_functions(), true ) ) {
			return $placeholder;
		}
		if ( in_array( $name, array( 'esc_html', 'esc_attr', 'esc_textarea', 'sprintf', 'printf', 'wp_sprintf' ), true ) && isset( $expression->args[0] ) ) {
			return output_template( $expression->args[0]->value );
		}
		return $placeholder;
	}
	if ( $expression instanceof Node\Expr\StaticCall && $expression->class instanceof Node\Name && 'wp_cli' === strtolower( $expression->class->getLast() ) && isset( $expression->args[0] ) ) {
		return output_template( $expression->args[0]->value );
	}
	if ( $expression instanceof Node\Expr\MethodCall && $expression->name instanceof Node\Identifier && 'link' === strtolower( $expression->name->toString() ) && isset( $expression->args[1] ) ) {
		return '<a>' . output_template( $expression->args[1]->value ) . '</a>';
	}

	return $placeholder;
}

/**
 * Extract text nodes and user-facing attributes from a static HTML fragment.
 *
 * @return list<string>
 */
function visible_html_strings( string $fragment ): array {
	$visible = array();
	if ( preg_match_all( '#<!--\s*wp:[a-z0-9/-]+\s+(\{.*?\})\s*/?-->#is', $fragment, $blockComments ) ) {
		foreach ( $blockComments[1] as $json ) {
			$attributes = json_decode( $json, true );
			if ( is_array( $attributes ) ) {
				block_attribute_strings( $attributes, $visible );
			}
		}
	}
	$fragment = preg_replace( '#<!--.*?-->#s', '', $fragment ) ?? $fragment;
	$fragment = preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $fragment ) ?? $fragment;

	foreach ( array( 'aria-label', 'aria-description', 'aria-roledescription', 'placeholder', 'title', 'alt' ) as $attribute ) {
		$pattern = '/\b' . preg_quote( $attribute, '/' ) . '\s*=\s*(["\'])(.*?)\1/is';
		if ( preg_match_all( $pattern, $fragment, $matches ) ) {
			foreach ( $matches[2] as $value ) {
				$visible[] = html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
	}
	if ( preg_match_all( '/<input\b[^>]*>/is', $fragment, $inputs ) ) {
		foreach ( $inputs[0] as $input ) {
			if ( preg_match( '/\btype\s*=\s*(["\'])hidden\1/i', $input ) ) {
				continue;
			}
			if ( preg_match( '/\bvalue\s*=\s*(["\'])(.*?)\1/is', $input, $value ) ) {
				$visible[] = html_entity_decode( strip_tags( $value[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
	}

	$fragment  = preg_replace( '/<[^>]*$/s', '', $fragment ) ?? $fragment;
	$textNodes = preg_split( '/<[^>]*>/s', $fragment ) ?: array( $fragment );
	foreach ( $textNodes as $text ) {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( '' !== trim( $text ) ) {
			$visible[] = $text;
		}
	}

	return $visible;
}

/**
 * Collect user-facing string attributes from a decoded WordPress block comment.
 *
 * @param array<mixed> $attributes Decoded block attributes.
 * @param array<mixed> $visible    Collected strings.
 */
function block_attribute_strings( array $attributes, array &$visible ): void {
	$keys = array_merge( joo_core_display_keys(), array( 'arialabel', 'buttontext', 'content', 'linklabel', 'placeholdertext' ) );
	foreach ( $attributes as $key => $value ) {
		if ( is_array( $value ) ) {
			block_attribute_strings( $value, $visible );
		} elseif ( is_string( $value ) && is_string( $key ) && in_array( strtolower( $key ), $keys, true ) ) {
			$visible[] = html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
	}
}

/**
 * Whether a literal resembles user-facing prose or a label.
 */
function is_translatable_text( string $text ): bool {
	$text = normalize_text( $text );
	if ( '' === $text || ! preg_match( '/\p{L}/u', $text ) ) {
		return false;
	}
	if ( preg_match( '/^(?:[a-z][a-z0-9:_-]*=(?:[^ ]+)?\s*)+$/i', $text ) || in_array( strtolower( $text ), array( 'hidden', 'required', 'disabled', 'checked', 'selected' ), true ) ) {
		return false;
	}

	return ! (bool) preg_match( '/^(?:JOOservices:|curl(?:\s|$)|HTTP\/\d(?:\.\d)?\s+\d{3}|cache-control:|x-robots-tag:|\{"error":)/i', $text );
}

/**
 * Whether this array contains metadata field names for get_file_data(), rather than UI copy.
 */
function is_file_header_map( Node\Expr\Array_ $array ): bool {
	$parent = $array->getAttribute( 'parent' );
	while ( $parent instanceof Node ) {
		if ( $parent instanceof Node\Expr\FuncCall && $parent->name instanceof Node\Name && 'get_file_data' === strtolower( $parent->name->getLast() ) ) {
			return true;
		}
		$parent = $parent->getAttribute( 'parent' );
	}

	return false;
}

/**
 * Normalize whitespace and remove placeholders introduced by the expression scanner.
 */
function normalize_text( string $text ): string {
	return trim( preg_replace( '/\s+/u', ' ', str_replace( "\u{E000}", '', $text ) ) ?? $text );
}

/**
 * Array keys whose values are displayed to users by core UI or block templates.
 *
 * @return list<string>
 */
function joo_core_display_keys(): array {
	return array( 'aria-description', 'aria-label', 'aria-roledescription', 'caption', 'description', 'help', 'label', 'message', 'notice', 'placeholder', 'summary', 'text', 'title' );
}

/**
 * WordPress gettext helpers; their first argument is already localized.
 *
 * @return list<string>
 */
function joo_core_translation_functions(): array {
	return array( '__', '_e', '_x', '_ex', '_n', '_nx', '_n_noop', '_nx_noop', 'esc_html__', 'esc_html_e', 'esc_html_x', 'esc_attr__', 'esc_attr_e', 'esc_attr_x', 'translate', 'translate_with_gettext_context', 'translate_nooped_plural' );
}
