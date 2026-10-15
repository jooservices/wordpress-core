<?php
/**
 * Settings page generated from the settings descriptor.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Paths;

/**
 * Appearance > JOOservices. Tabs come from the descriptor groups plus Compatibility and Tools.
 *
 * @internal
 */
final class SettingsPage {

	public const SLUG       = 'joo-settings';
	public const CAPABILITY = 'edit_theme_options';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the page.
	 *
	 * @param Settings $settings Settings.
	 * @param Manifest $manifest Manifest.
	 */
	public function __construct( Settings $settings, Manifest $manifest ) {
		$this->settings = $settings;
		$this->manifest = $manifest;
	}

	/**
	 * Hooks the page.
	 */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'addMenu' ) );
		add_action( 'admin_post_joo_save_settings', array( $this, 'handleSave' ) );
		add_action( 'admin_post_joo_reset_settings', array( $this, 'handleReset' ) );
		add_action( 'admin_post_joo_export_settings', array( $this, 'handleExport' ) );
		add_action( 'admin_post_joo_import_settings', array( $this, 'handleImport' ) );
		add_action( 'admin_post_joo_flush_caches', array( $this, 'handleFlush' ) );
		add_action( 'admin_post_joo_selftest', array( $this, 'handleSelfTest' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds Appearance > JOOservices.
	 */
	public function addMenu(): void {
		add_theme_page( $this->manifest->name(), $this->manifest->name(), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Loads the page assets only on this screen.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( 'appearance_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'joo-settings', Paths::url( 'resources/admin/settings.css' ), array(), Paths::version( 'resources/admin/settings.css' ) );
		wp_enqueue_script( 'joo-settings', Paths::url( 'resources/admin/settings.js' ), array(), Paths::version( 'resources/admin/settings.js' ), true );
	}

	/**
	 * Tabs: id => label.
	 *
	 * @return array<string, string>
	 */
	public function tabs(): array {
		$tabs = array();
		foreach ( $this->settings->schema()->groups() as $group ) {
			$tabs[ $group['id'] ] = $group['title'];
		}
		$tabs['compatibility'] = __( 'Compatibility', 'joo-core' );
		$tabs['tools']         = __( 'Tools', 'joo-core' );
		return $tabs;
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'joo-core' ), 403 );
		}
		$tabs = $this->tabs();
		$tab  = $this->currentTab();
		echo '<div class="wrap joo-settings">';
		echo '<h1>' . esc_html( $this->manifest->name() ) . '</h1>';
		$this->renderNotices();
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'joo-core' ) . '">';
		foreach ( $tabs as $id => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( $this->tabUrl( $id ) ),
				$id === $tab ? ' nav-tab-active' : '',
				$id === $tab ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
		if ( 'tools' === $tab ) {
			$this->renderTools();
		} elseif ( 'compatibility' === $tab ) {
			$this->renderCompatibility();
		} else {
			$this->renderGroup( $tab );
		}
		echo '</div>';
	}

	/**
	 * Renders a descriptor group as a form.
	 *
	 * @param string $group Group id.
	 */
	public function renderGroup( string $group ): void {
		$keys = $this->settings->schema()->keysInGroup( $group );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="joo-settings__form">';
		echo '<input type="hidden" name="action" value="joo_save_settings">';
		echo '<input type="hidden" name="joo_tab" value="' . esc_attr( $group ) . '">';
		wp_nonce_field( 'joo_settings_' . $group );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $keys as $key ) {
			$this->renderRow( $key );
		}
		echo '</tbody></table>';
		submit_button( __( 'Save changes', 'joo-core' ) );
		echo '</form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="joo-settings__reset">';
		echo '<input type="hidden" name="action" value="joo_reset_settings"><input type="hidden" name="joo_tab" value="' . esc_attr( $group ) . '">';
		wp_nonce_field( 'joo_settings_' . $group );
		echo '<button type="submit" class="button-link button-link-delete" data-joo-confirm="' . esc_attr__( 'Reset this tab to defaults?', 'joo-core' ) . '">' . esc_html__( 'Reset this tab to defaults', 'joo-core' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Renders one settings row.
	 *
	 * @param string $key Dotted key.
	 */
	private function renderRow( string $key ): void {
		$definition = (array) $this->settings->schema()->get( $key );
		$source     = $this->settings->source( $key );
		$locked     = Settings::SOURCE_FILTER === $source;
		$value      = $this->settings->get( $key );
		$name       = 'joo_settings[' . $key . ']';
		$id         = 'joo-' . str_replace( array( '.', '_' ), '-', $key );
		$error      = $this->fieldErrors()[ $key ] ?? '';
		echo '<tr data-joo-key="' . esc_attr( $key ) . '"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( (string) $definition['title'] ) . '</label></th><td>';
		$this->renderControl( $definition, $id, $name, $value, $locked, $error );
		echo ' <span class="joo-badge joo-badge--' . esc_attr( $source ) . '">' . esc_html( $this->badgeLabel( $source ) ) . '</span>';
		echo '<p class="description" id="' . esc_attr( $id ) . '-desc">' . esc_html( (string) $definition['description'] ) . '</p>';
		if ( '' !== $error ) {
			echo '<p class="joo-field-error" id="' . esc_attr( $id ) . '-error" role="alert">' . esc_html( $error ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Renders the control for a definition.
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @param string               $id         Element id.
	 * @param string               $name       Input name.
	 * @param mixed                $value      Current value.
	 * @param bool                 $locked     Whether code overrides the value.
	 * @param string               $error      Field error.
	 */
	private function renderControl( array $definition, string $id, string $name, $value, bool $locked, string $error ): void {
		$disabled  = $locked ? ' disabled' : '';
		$described = ' aria-describedby="' . esc_attr( $id . '-desc' . ( '' !== $error ? ' ' . $id . '-error' : '' ) ) . '"';
		switch ( (string) $definition['ui'] ) {
			case 'toggle':
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"' . esc_attr( $disabled ) . '>';
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . esc_attr( $disabled ) . $described . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $disabled ) . $described . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( (array) $definition['enum'] as $option ) {
					echo '<option value="' . esc_attr( (string) $option ) . '"' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( (string) $option ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</select>';
				break;
			case 'number':
				printf(
					'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s"%6$s%7$s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $definition['minimum'] ?? '' ) ),
					esc_attr( (string) ( $definition['maximum'] ?? '' ) ),
					esc_attr( $disabled ),
					$described // phpcs:ignore WordPress.Security.EscapeOutput
				);
				break;
			case 'media':
				$url = (int) $value > 0 ? (string) wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
				echo '<span class="joo-media" data-joo-media>';
				echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" min="0"' . esc_attr( $disabled ) . $described . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo ' <button type="button" class="button" data-joo-media-pick hidden>' . esc_html__( 'Choose image', 'joo-core' ) . '</button> ';
				echo '<img src="' . esc_url( $url ) . '" alt="" class="joo-media__preview" width="48" height="48"' . ( '' === $url ? ' hidden' : '' ) . '>';
				echo '</span>';
				break;
			case 'user':
				wp_dropdown_users(
					array(
						'name'              => $name,
						'id'                => $id,
						'selected'          => (int) $value,
						'show_option_none'  => __( 'Automatic (first administrator)', 'joo-core' ),
						'option_none_value' => 0,
						'capability'        => 'edit_posts',
						'class'             => $locked ? 'joo-locked' : '',
					)
				);
				break;
			case 'terms':
				$this->renderTermChoices( $name, array_values( (array) $value ), $id );
				break;
			default:
				$text = is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : (string) $value;
				if ( 'array' === ( $definition['type'] ?? '' ) ) {
					echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $disabled ) . $described . '>' . esc_textarea( $text ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $text ) . '"' . esc_attr( $disabled ) . $described . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
		}
	}

	/**
	 * Tag choices for curated topics.
	 *
	 * @param string       $name     Input name.
	 * @param list<mixed>  $selected Selected term ids.
	 * @param string       $id       Id prefix.
	 */
	private function renderTermChoices( string $name, array $selected, string $id ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		echo '<fieldset id="' . esc_attr( $id ) . '"><legend class="screen-reader-text">' . esc_html__( 'Tags', 'joo-core' ) . '</legend>';
		if ( ! is_array( $terms ) || array() === $terms ) {
			echo '<em>' . esc_html__( 'No tags yet.', 'joo-core' ) . '</em>';
		} else {
			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				printf(
					'<label class="joo-term-choice"><input type="checkbox" name="%1$s[]" value="%2$d"%3$s> %4$s <small>(%5$d)</small></label> ',
					esc_attr( $name ),
					(int) $term->term_id,
					checked( in_array( (int) $term->term_id, array_map( 'intval', $selected ), true ), true, false ), // phpcs:ignore WordPress.Security.EscapeOutput
					esc_html( $term->name ),
					(int) $term->count
				);
			}
		}
		echo '</fieldset>';
	}

	/**
	 * Badge text for a value source.
	 *
	 * @param string $source Source id.
	 */
	private function badgeLabel( string $source ): string {
		switch ( $source ) {
			case Settings::SOURCE_FILTER:
				return __( 'Overridden by code', 'joo-core' );
			case Settings::SOURCE_USER:
				return __( 'Saved', 'joo-core' );
			case Settings::SOURCE_MANIFEST:
				return __( 'Theme', 'joo-core' );
			default:
				return __( 'Default', 'joo-core' );
		}
	}

	/**
	 * Compatibility tab.
	 */
	public function renderCompatibility(): void {
		echo '<h2>' . esc_html__( 'Detected plugins', 'joo-core' ) . '</h2>';
		/** @var list<array{label: string, version: string, provides: list<string>}> $rows */
		$rows = Hooks::filter( 'joo/compat/report', array() );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Plugin', 'joo-core' ) . '</th><th>' . esc_html__( 'Version', 'joo-core' ) . '</th><th>' . esc_html__( 'Provides', 'joo-core' ) . '</th><th>' . esc_html__( 'Adapter', 'joo-core' ) . '</th><th>' . esc_html__( 'Notes', 'joo-core' ) . '</th></tr></thead><tbody>';
		if ( ! is_array( $rows ) || array() === $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No supported plugins detected.', 'joo-core' ) . '</td></tr>';
			$rows = array();
		}
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $row['version'] ) . '</td><td>' . esc_html( implode( ', ', $row['provides'] ) ) . '</td><td>' . esc_html( (string) ( $row['adapter'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['notice'] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Tools tab: export, import, flush caches.
	 */
	public function renderTools(): void {
		echo '<h2>' . esc_html__( 'Export settings', 'joo-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="joo_export_settings">';
		wp_nonce_field( 'joo_settings_tools' );
		submit_button( __( 'Download JSON', 'joo-core' ), 'secondary' );
		echo '</form>';
		echo '<h2>' . esc_html__( 'Import settings', 'joo-core' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="joo_import_settings">';
		wp_nonce_field( 'joo_settings_tools' );
		echo '<p><label for="joo-import-file">' . esc_html__( 'Settings file (JSON)', 'joo-core' ) . '</label> <input type="file" id="joo-import-file" name="joo_import_file" accept="application/json,.json"></p>';
		submit_button( __( 'Import', 'joo-core' ), 'secondary' );
		echo '</form>';
		$this->renderPerformance();
		echo '<h2>' . esc_html__( 'Caches', 'joo-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="joo_flush_caches">';
		wp_nonce_field( 'joo_settings_tools' );
		submit_button( __( 'Flush JOOservices caches', 'joo-core' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * Performance snapshot and the self-test button (Tools tab).
	 */
	private function renderPerformance(): void {
		echo '<h2>' . esc_html__( 'Performance snapshot', 'joo-core' ) . '</h2>';
		$diagnostics = $this->diagnostics();
		if ( null === $diagnostics ) {
			echo '<p>' . esc_html__( 'The performance module is not running.', 'joo-core' ) . '</p>';
			return;
		}
		$snapshot = $diagnostics->snapshot();
		echo '<ul class="joo-snapshot"><li>' . esc_html( sprintf( /* translators: %s: yes or no. */ __( 'OPcache enabled: %s', 'joo-core' ), $snapshot['opcache'] ? __( 'yes', 'joo-core' ) : __( 'no', 'joo-core' ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %s: yes or no. */ __( 'Persistent object cache: %s', 'joo-core' ), $snapshot['object'] ? __( 'yes', 'joo-core' ) : __( 'no', 'joo-core' ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %s: yes or no. */ __( 'Response compression available: %s', 'joo-core' ), $snapshot['compress'] ? __( 'yes', 'joo-core' ) : __( 'no', 'joo-core' ) ) ) . '</li></ul>';
		$result = get_transient( 'joo_selftest_' . get_current_user_id() );
		if ( is_array( $result ) ) {
			delete_transient( 'joo_selftest_' . get_current_user_id() );
			if ( ! empty( $result['ok'] ) ) {
				echo '<p class="joo-selftest">' . esc_html( sprintf( /* translators: 1: HTTP status, 2: milliseconds, 3: bytes, 4: scripts, 5: stylesheets, 6: images. */ __( 'Home page: HTTP %1$d in %2$d ms, %3$d bytes, %4$d script tags, %5$d stylesheets, %6$d images.', 'joo-core' ), (int) $result['status'], (int) $result['ttfb_ms'], (int) $result['bytes'], (int) $result['scripts'], (int) $result['styles'], (int) $result['images'] ) ) . '</p>';
			} else {
				echo '<p class="joo-selftest">' . esc_html__( 'The self-test could not reach the home page (loopback requests may be blocked).', 'joo-core' ) . '</p>';
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="joo_selftest">';
		wp_nonce_field( 'joo_settings_tools' );
		submit_button( __( 'Run self-test', 'joo-core' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * Runs the performance self-test and returns to the Tools tab.
	 */
	public function handleSelfTest(): void {
		$this->guard( null, 'tools' );
		$diagnostics = $this->diagnostics();
		if ( null !== $diagnostics ) {
			set_transient( 'joo_selftest_' . get_current_user_id(), $diagnostics->selfTest(), 60 );
		}
		$this->redirect( 'tools', array(), array() );
	}

	/**
	 * Diagnostics service when the performance module runs.
	 */
	private function diagnostics(): ?\JOOservices\ThemeCore\Performance\Diagnostics {
		$container = \JOOservices\ThemeCore\Kernel::container();
		if ( null === $container || ! $container->has( \JOOservices\ThemeCore\Performance\Diagnostics::class ) ) {
			return null;
		}
		/** @var \JOOservices\ThemeCore\Performance\Diagnostics $diagnostics */
		$diagnostics = $container->get( \JOOservices\ThemeCore\Performance\Diagnostics::class );
		return $diagnostics;
	}

	/**
	 * Saves one tab.
	 */
	public function handleSave(): void {
		$tab    = $this->guard( 'joo_tab' );
		$raw    = isset( $_POST['joo_settings'] ) && is_array( $_POST['joo_settings'] ) ? wp_unslash( $_POST['joo_settings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce and capability are checked in guard(); values are sanitised by Settings::save().
		$result = $this->process( $tab, $raw );
		$this->redirect( $tab, $result['errors'], array_keys( $result['saved'] ) );
	}

	/**
	 * Saves values of a tab and returns the result (separated from the request handling for tests).
	 *
	 * @param string               $tab Tab id.
	 * @param array<string, mixed> $raw Raw values by dotted key.
	 * @return array{saved: array<string, mixed>, errors: array<string, string>}
	 */
	public function process( string $tab, array $raw ): array {
		$allowed = $this->settings->schema()->keysInGroup( $tab );
		$input   = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$input[ $key ] = $raw[ $key ];
			} elseif ( 'terms' === ( $this->settings->schema()->get( $key )['ui'] ?? '' ) ) {
				$input[ $key ] = array(); // An unchecked checkbox list posts nothing.
			}
		}
		// Keys forced by code are not editable.
		foreach ( array_keys( $input ) as $key ) {
			if ( Settings::SOURCE_FILTER === $this->settings->source( $key ) ) {
				unset( $input[ $key ] );
			}
		}
		return $this->settings->save( $input );
	}

	/**
	 * Resets one tab to defaults.
	 */
	public function handleReset(): void {
		$tab = $this->guard( 'joo_tab' );
		foreach ( $this->settings->schema()->keysInGroup( $tab ) as $key ) {
			$this->settings->repository()->reset( $key );
		}
		$this->redirect( $tab, array(), array( '*' ) );
	}

	/**
	 * Streams the saved settings as JSON.
	 */
	public function handleExport(): void {
		$this->guard( null, 'tools' );
		$payload = array(
			'joo_settings_export' => 1,
			'theme'               => $this->manifest->slug(),
			'core'                => defined( 'JOO_CORE_VERSION' ) ? JOO_CORE_VERSION : '',
			'settings'            => $this->settings->repository()->all(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="joo-settings-' . sanitize_file_name( $this->manifest->slug() ) . '.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Imports settings from an uploaded JSON file.
	 */
	public function handleImport(): void {
		$this->guard( null, 'tools' );
		$errors = array();
		$saved  = array();
		$tmp    = isset( $_FILES['joo_import_file']['tmp_name'] ) ? (string) $_FILES['joo_import_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce checked in guard().
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$errors['import'] = __( 'Choose a settings file.', 'joo-core' );
		} else {
			$result = $this->import( (string) file_get_contents( $tmp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$errors = $result['errors'];
			$saved  = array_keys( $result['saved'] );
		}
		$this->redirect( 'tools', $errors, $saved );
	}

	/**
	 * Applies an exported settings document.
	 *
	 * @param string $json JSON text.
	 * @return array{saved: array<string, mixed>, errors: array<string, string>}
	 */
	public function import( string $json ): array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['joo_settings_export'] ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return array(
				'saved'  => array(),
				'errors' => array( 'import' => __( 'This is not a JOOservices settings export.', 'joo-core' ) ),
			);
		}
		return $this->settings->save( $data['settings'] );
	}

	/**
	 * Bumps the cache version so every cached fragment is rebuilt.
	 */
	public function handleFlush(): void {
		$this->guard( null, 'tools' );
		update_option( 'joo_cache_version', time(), true );
		$this->redirect( 'tools', array(), array( 'cache' ) );
	}

	/**
	 * Verifies capability and nonce and returns the tab.
	 *
	 * @param string|null $tab_field POST field holding the tab id, or null.
	 * @param string      $tab       Fixed tab id when no field is used.
	 */
	private function guard( ?string $tab_field, string $tab = '' ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'joo-core' ), 403 );
		}
		if ( null !== $tab_field ) {
			$tab = isset( $_POST[ $tab_field ] ) ? sanitize_key( wp_unslash( $_POST[ $tab_field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked right below, once the tab is known.
			if ( ! array_key_exists( $tab, $this->tabs() ) ) {
				wp_die( esc_html__( 'Unknown settings tab.', 'joo-core' ), 400 );
			}
		}
		check_admin_referer( 'joo_settings_' . $tab );
		return $tab;
	}

	/**
	 * Stores a result message and redirects back to the tab.
	 *
	 * @param string               $tab    Tab id.
	 * @param array<string,string> $errors Field errors.
	 * @param list<string>         $saved  Saved keys.
	 */
	private function redirect( string $tab, array $errors, array $saved ): void {
		set_transient(
			'joo_settings_notice_' . get_current_user_id(),
			array(
				'errors' => $errors,
				'saved'  => $saved,
			),
			60
		);
		wp_safe_redirect( $this->tabUrl( $tab ) );
		exit;
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab Tab id.
	 */
	public function tabUrl( string $tab ): string {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'tab'  => $tab,
			),
			admin_url( 'themes.php' )
		);
	}

	/**
	 * Active tab (first tab by default).
	 */
	private function currentTab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $tab, $this->tabs() ) ? $tab : (string) array_key_first( $this->tabs() );
	}

	/**
	 * Field errors from the last save (only on the page request that follows it).
	 *
	 * @return array<string, string>
	 */
	private function fieldErrors(): array {
		$notice = $this->notice();
		return is_array( $notice ) ? (array) $notice['errors'] : array();
	}

	/**
	 * Last save result (read once per request).
	 *
	 * @var array{errors: array<string,string>, saved: list<string>}|null
	 */
	private ?array $noticeCache = null;

	/**
	 * Whether the stored notice was already loaded.
	 *
	 * @var bool
	 */
	private bool $noticeLoaded = false;

	/**
	 * Loads and clears the stored notice.
	 *
	 * @return array{errors: array<string,string>, saved: list<string>}|null
	 */
	private function notice(): ?array {
		if ( ! $this->noticeLoaded ) {
			$this->noticeLoaded = true;
			$key                = 'joo_settings_notice_' . get_current_user_id();
			$stored             = get_transient( $key );
			if ( is_array( $stored ) ) {
				$this->noticeCache = array(
					'errors' => (array) ( $stored['errors'] ?? array() ),
					'saved'  => array_values( (array) ( $stored['saved'] ?? array() ) ),
				);
				delete_transient( $key );
			}
		}
		return $this->noticeCache;
	}

	/**
	 * Prints the success or error banner.
	 */
	private function renderNotices(): void {
		$notice = $this->notice();
		if ( null === $notice ) {
			return;
		}
		if ( array() !== $notice['errors'] ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Some values were not saved. Check the highlighted fields.', 'joo-core' ) . '</p></div>';
		} elseif ( array() !== $notice['saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'joo-core' ) . '</p></div>';
		}
	}
}
