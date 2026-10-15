<?php
// phpcs:ignoreFile
/**
 * Minimal declarations of third-party plugin APIs that adapters call behind function_exists/class_exists guards.
 * Used by PHPStan only; never loaded at runtime.
 */

class WPSEO_Primary_Term {
	public function __construct( string $taxonomy, int $postId ) {}
	/** @return int|false */
	public function get_primary_term() {
		return false; }
}

class autoptimizeCache {
	public static function clearall(): void {}
}

class Jetpack {
	public static function is_module_active( string $module ): bool {
		return false; }
}

/** @return object */
function aioseo() {
	return new stdClass(); }
/** @return object|null */
function aioseo_get_post( int $postId ) {
	return null; }

/** @return array<string, array<string, mixed>>|string */
function pll_the_languages( array $args = array() ) {
	return array(); }
/** @return string|false */
function pll_current_language( string $field = 'slug' ) {
	return false; }
/** @return string|false */
function pll_default_language( string $field = 'slug' ) {
	return false; }
/** @return array<string, int> */
function pll_get_post_translations( int $postId ): array {
	return array(); }
/** @return array<string, int> */
function pll_get_term_translations( int $termId ): array {
	return array(); }
function pll_home_url( string $slug = '' ): string {
	return ''; }

/** @return array<string, string> */
function trp_get_languages(): array {
	return array(); }
/** @return object */
function trp_get_url_converter() {
	return new stdClass(); }

function rocket_clean_domain(): void {}
function w3tc_flush_all(): void {}
/** @return object */
function w3tc_config() {
	return new stdClass(); }
function wc_get_page_permalink( string $page ): string {
	return ''; }
/** @return mixed */
function get_field( string $name, $postId = false ) {
	return null; }
