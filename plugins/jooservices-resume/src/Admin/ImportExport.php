<?php
/**
 * Import and export of companies: own JSON, JSON Resume (`work`) and CSV.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Admin;

use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Model\CompanyWriter;
use JOOservices\Resume\Modules\CompanyDataModel;
use JOOservices\Resume\PostTypes\Company;
use JOOservices\Resume\Transfer\Mapper;

/**
 * `Companies > Import / Export`. Capability `manage_options` and a nonce on every action. An import is validated as a
 * whole first (dry run); nothing is created unless every company is valid.
 */
final class ImportExport {

	public const SLUG      = 'joo-resume-transfer';
	public const MAX_BYTES = 1048576;

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_joo_resume_export', array( $this, 'export' ) );
		add_action( 'admin_post_joo_resume_import_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_joo_resume_import_apply', array( $this, 'apply' ) );
	}

	/**
	 * Adds the submenu page.
	 */
	public function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Company::TYPE, __( 'Import and export', 'jooservices-resume' ), __( 'Import / Export', 'jooservices-resume' ), 'manage_options', self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Stops without a capability or a valid nonce.
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Nonce field or query argument.
	 * @param bool   $post   Whether the nonce is in the POST body.
	 */
	private function guard( string $action, string $field, bool $post ): void {
		$source = $post ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification -- verified just below.
		$nonce  = isset( $source[ $field ] ) ? sanitize_text_field( wp_unslash( (string) $source[ $field ] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'jooservices-resume' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Rows of every company, private fields included (the owner exports their own data).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function rows(): array {
		return array_map( static fn( $record ): array => $record->toRow(), ( new CompanyRepository() )->all( 'manual', false ) );
	}

	/**
	 * The download for a format, or null for an unknown one.
	 *
	 * @param string $format `own`, `jsonresume` or `csv`.
	 * @return array{type: string, name: string, body: string}|null
	 */
	public function document( string $format ): ?array {
		$rows  = $this->rows();
		$files = array(
			'own'        => array( 'application/json', 'jooservices-resume.json', Mapper::toOwn( $rows ) ),
			'jsonresume' => array( 'application/json', 'resume-work.json', Mapper::toJsonResume( $rows ) ),
			'csv'        => array( 'text/csv', 'resume-companies.csv', Mapper::toCsv( $rows ) ),
		);
		return isset( $files[ $format ] ) ? array(
			'type' => $files[ $format ][0],
			'name' => $files[ $format ][1],
			'body' => $files[ $format ][2],
		) : null;
	}

	/**
	 * Downloads an export.
	 */
	public function export(): void {
		$this->guard( 'joo_resume_export', '_wpnonce', false );
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : 'own'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in guard().
		$file   = $this->document( $format );
		if ( null === $file ) {
			wp_die( esc_html__( 'Unknown export format.', 'jooservices-resume' ), '', array( 'response' => 400 ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . $file['type'] . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file['name'] . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $file['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a download, not HTML.
		exit;
	}

	/**
	 * Dry run: reads the upload, validates every company and stores the result for the apply step.
	 */
	public function preview(): void {
		$this->guard( 'joo_resume_import', 'joo_import_nonce', true );
		$file  = isset( $_FILES['joo_import_file'] ) && is_array( $_FILES['joo_import_file'] ) ? $_FILES['joo_import_file'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in guard(); read as data only.
		$error = $this->uploadError( $file );
		if ( '' === $error ) {
			$result = Mapper::parse( (string) file_get_contents( (string) $file['tmp_name'] ), ( new CompanyRepository() )->definitions() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
			$this->back( array( 'token' => $this->stash( $result ) ) );
		}
		set_transient( 'joo_resume_import_error_' . get_current_user_id(), $error, 60 );
		$this->back( array() );
	}

	/**
	 * Keeps a dry-run result for the apply step and returns its token.
	 *
	 * @param array<string, mixed> $result Parsed document.
	 */
	public function stash( array $result ): string {
		$token = wp_generate_password( 20, false );
		set_transient( 'joo_resume_import_' . get_current_user_id() . '_' . $token, $result, 15 * MINUTE_IN_SECONDS );
		return $token;
	}

	/**
	 * Why an upload cannot be read, or an empty string.
	 *
	 * @param array<string, mixed> $file       The `$_FILES` entry.
	 * @param callable|null        $isUploaded Check that the file came from an upload (tests pass their own).
	 */
	public function uploadError( array $file, ?callable $isUploaded = null ): string {
		$isUploaded ??= 'is_uploaded_file';
		if ( array() === $file || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! $isUploaded( (string) ( $file['tmp_name'] ?? '' ) ) ) {
			return __( 'Choose a JSON file to import.', 'jooservices-resume' );
		}
		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_BYTES ) {
			return __( 'The file is larger than 1 MB.', 'jooservices-resume' );
		}
		if ( 'json' !== strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) {
			return __( 'Only .json files can be imported.', 'jooservices-resume' );
		}
		return '';
	}

	/**
	 * Creates the companies of a validated preview. Nothing is created when any company is invalid.
	 */
	public function apply(): void {
		$this->guard( 'joo_resume_import_apply', 'joo_apply_nonce', true );
		$token  = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( (string) $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$key    = 'joo_resume_import_' . get_current_user_id() . '_' . $token;
		$result = get_transient( $key );
		if ( ! is_array( $result ) || array() !== $result['errors'] || self::invalidRows( $result['rows'] ) > 0 ) {
			set_transient( 'joo_resume_import_error_' . get_current_user_id(), __( 'The import expired or contains errors. Upload the file again.', 'jooservices-resume' ), 60 );
			$this->back( array() );
		}
		delete_transient( $key );
		$writer = new CompanyWriter();
		$count  = 0;
		foreach ( $result['rows'] as $row ) {
			if ( ! is_wp_error( $writer->save( $row['company'] ) ) ) {
				++$count;
			}
		}
		$this->back( array( 'imported' => $count ) );
	}

	/**
	 * Number of rows that have errors.
	 *
	 * @param list<array{company: array<string, mixed>, errors: list<array{code: string, field: string}>}> $rows Rows.
	 */
	public static function invalidRows( array $rows ): int {
		return count( array_filter( $rows, static fn( array $row ): bool => array() !== $row['errors'] ) );
	}

	/**
	 * Redirects to the page.
	 *
	 * @param array<string, scalar> $args Query arguments.
	 */
	private function back( array $args ): void {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=' . Company::TYPE . '&page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Renders the page: export links, upload form and the dry-run report.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'jooservices-resume' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Import and export', 'jooservices-resume' ) . '</h1>';
		$error = get_transient( 'joo_resume_import_error_' . get_current_user_id() );
		delete_transient( 'joo_resume_import_error_' . get_current_user_id() );
		if ( is_string( $error ) && '' !== $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only result flag.
		if ( isset( $_GET['imported'] ) ) {
			/* translators: %d: number of companies. */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d company imported.', '%d companies imported.', absint( wp_unslash( $_GET['imported'] ) ), 'jooservices-resume' ), absint( wp_unslash( $_GET['imported'] ) ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only result flag.
		}
		echo '<h2>' . esc_html__( 'Export', 'jooservices-resume' ) . '</h2><p>' . esc_html__( 'The JOOservices Resume file contains every field, including private ones: keep it safe. JSON Resume and CSV contain public data only.', 'jooservices-resume' ) . '</p><p>';
		foreach ( array(
			'own'        => __( 'JOOservices Resume (JSON)', 'jooservices-resume' ),
			'jsonresume' => __( 'JSON Resume (work section)', 'jooservices-resume' ),
			'csv'        => __( 'List (CSV)', 'jooservices-resume' ),
		) as $format => $label ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=joo_resume_export&format=' . $format ), 'joo_resume_export' );
			printf( '<a class="button" href="%1$s">%2$s</a> ', esc_url( $url ), esc_html( $label ) );
		}
		echo '</p><h2>' . esc_html__( 'Import', 'jooservices-resume' ) . '</h2><p>' . esc_html__( 'Choose a JOOservices Resume export or a JSON Resume file (up to 1 MB, 200 companies). The file is checked first and nothing is created unless every company is valid.', 'jooservices-resume' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="joo_resume_import_preview">';
		wp_nonce_field( 'joo_resume_import', 'joo_import_nonce' );
		echo '<p><label for="joo_import_file">' . esc_html__( 'File', 'jooservices-resume' ) . '</label> <input type="file" id="joo_import_file" name="joo_import_file" accept=".json,application/json"></p>';
		submit_button( __( 'Check the file', 'jooservices-resume' ), 'secondary' );
		echo '</form>';
		$token = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( (string) $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only token, the apply step verifies a nonce.
		if ( '' !== $token ) {
			$this->report( $token );
		}
		echo '</div>';
	}

	/**
	 * The dry-run report and the apply button.
	 *
	 * @param string $token Preview token.
	 */
	private function report( string $token ): void {
		$result = get_transient( 'joo_resume_import_' . get_current_user_id() . '_' . $token );
		echo '<h2>' . esc_html__( 'Check result', 'jooservices-resume' ) . '</h2>';
		if ( ! is_array( $result ) ) {
			echo '<p>' . esc_html__( 'The check expired. Upload the file again.', 'jooservices-resume' ) . '</p>';
			return;
		}
		foreach ( $result['errors'] as $error ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( CompanyDataModel::message( $error ) ) . '</p></div>';
		}
		$invalid = self::invalidRows( $result['rows'] );
		if ( array() !== $result['rows'] ) {
			echo '<table class="widefat striped joo-import-report"><thead><tr><th scope="col">#</th><th scope="col">' . esc_html__( 'Company', 'jooservices-resume' ) . '</th><th scope="col">' . esc_html__( 'Result', 'jooservices-resume' ) . '</th></tr></thead><tbody>';
			foreach ( $result['rows'] as $i => $row ) {
				$messages = array_map( array( CompanyDataModel::class, 'message' ), $row['errors'] );
				printf(
					'<tr><td>%1$d</td><td>%2$s</td><td>%3$s</td></tr>',
					(int) $i + 1,
					esc_html( (string) ( $row['company']['name'] ?: '—' ) ),
					esc_html( array() === $messages ? __( 'OK', 'jooservices-resume' ) : implode( ' ', $messages ) )
				);
			}
			echo '</tbody></table>';
		}
		if ( array() === $result['errors'] && 0 === $invalid && array() !== $result['rows'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="joo_resume_import_apply"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
			wp_nonce_field( 'joo_resume_import_apply', 'joo_apply_nonce' );
			/* translators: %d: number of companies. */
			submit_button( sprintf( _n( 'Import %d company', 'Import %d companies', count( $result['rows'] ), 'jooservices-resume' ), count( $result['rows'] ) ) );
			echo '</form>';
		} elseif ( $invalid > 0 ) {
			/* translators: %d: number of companies with errors. */
			echo '<p>' . esc_html( sprintf( _n( '%d company has errors. Fix the file and check it again.', '%d companies have errors. Fix the file and check it again.', $invalid, 'jooservices-resume' ), $invalid ) ) . '</p>';
		}
	}
}
