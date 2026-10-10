<?php
/**
 * Administración: idiomas, cadenas, importación/exportación CSV.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Admin {

	/**
	 * Registra los hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_lgv_export_csv', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_lgv_import_csv', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_lgv_export_xliff', array( __CLASS__, 'handle_xliff_export' ) );
		add_action( 'admin_post_lgv_import_xliff', array( __CLASS__, 'handle_xliff_import' ) );
		add_action( 'admin_post_lgv_save_media', array( __CLASS__, 'handle_media_save' ) );
		add_action( 'admin_post_lgv_save_media_file', array( __CLASS__, 'handle_media_file_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_ajax_lgv_switcher_preview', array( 'LGV_Switcher', 'ajax_preview' ) );
		add_action( 'wp_ajax_lgv_discover_start', array( 'LGV_Discover', 'ajax_start' ) );
		add_action( 'wp_ajax_lgv_discover_batch', array( 'LGV_Discover', 'ajax_batch' ) );
		add_action( 'wp_ajax_lgv_auto_batch', array( 'LGV_AutoTranslate', 'ajax_batch' ) );
	}

	/**
	 * Assets del admin: color pickers, sortable y live preview del switcher.
	 *
	 * @param string $hook Hook actual.
	 */
	public static function admin_assets( $hook ) {
		if ( 'toplevel_page_lingua-visual' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_media();
		wp_enqueue_style( 'lgv-switcher', LGV_PLUGIN_URL . 'assets/css/switcher.css', array(), LGV_VERSION );
		wp_enqueue_style( 'lgv-switcher-admin', LGV_PLUGIN_URL . 'assets/css/switcher-admin.css', array(), LGV_VERSION );
		wp_enqueue_script( 'lgv-switcher', LGV_PLUGIN_URL . 'assets/js/switcher.js', array(), LGV_VERSION, true );
		wp_enqueue_script(
			'lgv-alpha-picker',
			LGV_PLUGIN_URL . 'assets/js/lgv-alpha-picker.js',
			array( 'jquery', 'wp-color-picker' ),
			LGV_VERSION,
			true
		);
		wp_enqueue_script(
			'lgv-switcher-admin',
			LGV_PLUGIN_URL . 'assets/js/switcher-admin.js',
			array( 'jquery', 'wp-color-picker', 'lgv-alpha-picker', 'jquery-ui-sortable' ),
			LGV_VERSION,
			true
		);
		wp_localize_script(
			'lgv-switcher-admin',
			'LGV_SW_ADMIN',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lgv_switcher_preview' ),
			)
		);
		wp_enqueue_script(
			'lgv-discover',
			LGV_PLUGIN_URL . 'assets/js/lgv-discover.js',
			array( 'jquery' ),
			LGV_VERSION,
			true
		);
		wp_localize_script(
			'lgv-discover',
			'LGV_DISC',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lgv_discover' ),
				'i18n'    => array(
					'starting' => __( 'Starting crawl…', 'lingua-visual' ),
					'done'     => __( '%1$d/%2$d pages · %3$d strings found · %4$d new', 'lingua-visual' ),
					'errors'   => __( 'Errors', 'lingua-visual' ),
					'finished' => __( 'Crawl finished. New strings are already in the Strings tab.', 'lingua-visual' ),
					'error'    => __( 'An error occurred. Try again.', 'lingua-visual' ),
				),
			)
		);
		wp_enqueue_script(
			'lgv-auto',
			LGV_PLUGIN_URL . 'assets/js/lgv-auto.js',
			array( 'jquery' ),
			LGV_VERSION,
			true
		);
		wp_localize_script(
			'lgv-auto',
			'LGV_AUTO',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lgv_auto' ),
				'i18n'    => array(
					'confirm'  => __( 'Automatically translate all pending strings? They\'ll be marked as automatic for you to review.', 'lingua-visual' ),
					'starting' => __( 'Translating…', 'lingua-visual' ),
					'progress' => __( '%1$d translated · %2$d characters · %3$d pending', 'lingua-visual' ),
					'errors'   => __( 'Errors', 'lingua-visual' ),
					'quota'    => __( 'Provider quota exhausted. Pending items wait for the next period.', 'lingua-visual' ),
					'finished' => __( 'Done. Review the automatic translations in the Strings tab ("Only automatic" filter).', 'lingua-visual' ),
					'error'    => __( 'An error occurred. Try again.', 'lingua-visual' ),
				),
			)
		);
	}

	/**
	 * Menú principal.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Lingua Visual', 'lingua-visual' ),
			__( 'Lingua Visual', 'lingua-visual' ),
			'manage_options',
			'lingua-visual',
			array( __CLASS__, 'page' ),
			'dashicons-translation',
			80
		);
	}

	/**
	 * Procesa los formularios POST de la página.
	 */
	private static function process_forms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['lgv_add_lang'] ) ) {
			check_admin_referer( 'lgv_langs', 'lgv_nonce' );
			$res = LGV_Languages::add(
				isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '',
				isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				isset( $_POST['flag'] ) ? wp_unslash( $_POST['flag'] ) : ''
			);
			self::notice( $res, __( 'Language added.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_remove_lang'] ) ) {
			check_admin_referer( 'lgv_langs', 'lgv_nonce' );
			$res = LGV_Languages::remove( isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '' );
			self::notice( $res, __( 'Language removed.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_set_default'] ) ) {
			check_admin_referer( 'lgv_langs', 'lgv_nonce' );
			$res = LGV_Languages::set_default( isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '' );
			self::notice( $res, __( 'Default language updated.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_add_string'] ) ) {
			check_admin_referer( 'lgv_strings', 'lgv_nonce' );
			$original = isset( $_POST['original'] ) ? trim( wp_unslash( $_POST['original'] ) ) : '';
			$lang     = isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : '';
			$text     = isset( $_POST['translation'] ) ? trim( wp_unslash( $_POST['translation'] ) ) : '';
			$ok       = '' !== $original && LGV_Strings::save( $original, $lang, $text );
			self::notice( $ok, __( 'String saved.', 'lingua-visual' ), __( 'Could not save: check the data.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_save_row'] ) ) {
			check_admin_referer( 'lgv_strings', 'lgv_nonce' );
			$string_id = isset( $_POST['string_id'] ) ? (int) $_POST['string_id'] : 0;
			$lang      = isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : '';
			$text      = isset( $_POST['translation'] ) ? trim( wp_unslash( $_POST['translation'] ) ) : '';
			$ok        = LGV_Strings::save_translation( $string_id, $lang, $text );
			self::notice( $ok, __( 'Translation saved.', 'lingua-visual' ), __( 'Could not save.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_delete_string'] ) ) {
			check_admin_referer( 'lgv_strings', 'lgv_nonce' );
			$ok = LGV_Strings::delete( isset( $_POST['string_id'] ) ? (int) $_POST['string_id'] : 0 );
			self::notice( $ok, __( 'String deleted.', 'lingua-visual' ), __( 'Could not delete.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_save_url_settings'] ) ) {
			check_admin_referer( 'lgv_urls', 'lgv_nonce' );
			LGV_Routing::save_settings(
				array(
					'url_prefixes'    => ! empty( $_POST['url_prefixes'] ) ? 1 : 0,
					'redirect_legacy' => ! empty( $_POST['redirect_legacy'] ) ? 1 : 0,
				)
			);
			self::notice( true, __( 'URL settings saved.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_save_detect'] ) ) {
			check_admin_referer( 'lgv_detect', 'lgv_nonce' );
			$raw = isset( $_POST['dt'] ) && is_array( $_POST['dt'] ) ? wp_unslash( $_POST['dt'] ) : array();
			LGV_Detect::save_settings( $raw );
			self::notice( true, __( 'Detection settings saved.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_save_switcher'] ) ) {
			check_admin_referer( 'lgv_switcher', 'lgv_nonce' );
			$sw = isset( $_POST['sw'] ) && is_array( $_POST['sw'] ) ? wp_unslash( $_POST['sw'] ) : array();
			LGV_Switcher::save( $sw );
			$variants = isset( $_POST['sw_variant'] ) && is_array( $_POST['sw_variant'] ) ? wp_unslash( $_POST['sw_variant'] ) : array();
			$labels   = isset( $_POST['sw_label'] ) && is_array( $_POST['sw_label'] ) ? wp_unslash( $_POST['sw_label'] ) : array();
			$remove   = isset( $_POST['sw_svg_remove'] ) && is_array( $_POST['sw_svg_remove'] ) ? wp_unslash( $_POST['sw_svg_remove'] ) : array();
			$files    = self::reshape_files( isset( $_FILES['sw_svg'] ) && is_array( $_FILES['sw_svg'] ) ? $_FILES['sw_svg'] : array() );
			LGV_Switcher::save_per_language( $variants, $labels, $files, $remove );
			self::notice( true, __( 'Switcher settings saved.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_save_at'] ) ) {
			check_admin_referer( 'lgv_auto', 'lgv_nonce' );
			$raw = isset( $_POST['at'] ) && is_array( $_POST['at'] ) ? wp_unslash( $_POST['at'] ) : array();
			LGV_AutoTranslate::save_settings( $raw );
			self::notice( true, __( 'Automatic translation settings saved.', 'lingua-visual' ) );
		}
		if ( isset( $_POST['lgv_mark_reviewed'] ) ) {
			check_admin_referer( 'lgv_strings', 'lgv_nonce' );
			$ok = LGV_AutoTranslate::mark_reviewed(
				isset( $_POST['string_id'] ) ? (int) $_POST['string_id'] : 0,
				isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : ''
			);
			self::notice( $ok, __( 'Marked as reviewed: it\'s now a manual translation.', 'lingua-visual' ), __( 'Could not mark.', 'lingua-visual' ) );
		}
	}

	/**
	 * Reordena $_FILES['sw_svg'] (formato name[code]) a [code => datos].
	 *
	 * @param array $arr $_FILES crudo.
	 * @return array
	 */
	private static function reshape_files( $arr ) {
		$out = array();
		if ( ! isset( $arr['name'] ) || ! is_array( $arr['name'] ) ) {
			return $out;
		}
		foreach ( $arr['name'] as $code => $name ) {
			$code          = lgv_sanitize_lang_code( $code );
			$out[ $code ] = array(
				'name'     => (string) $name,
				'tmp_name' => isset( $arr['tmp_name'][ $code ] ) ? (string) $arr['tmp_name'][ $code ] : '',
				'error'    => isset( $arr['error'][ $code ] ) ? (int) $arr['error'][ $code ] : UPLOAD_ERR_NO_FILE,
			);
		}
		return $out;
	}

	/**
	 * Muestra un notice según el resultado.
	 *
	 * @param true|WP_Error|bool $res  Resultado.
	 * @param string             $ok   Mensaje de éxito.
	 * @param string             $fail Mensaje de error genérico.
	 */
	private static function notice( $res, $ok, $fail = '' ) {
		if ( true === $res ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $ok ) . '</p></div>';
			return;
		}
		$msg = $res instanceof WP_Error ? $res->get_error_message() : $fail;
		if ( '' === $msg ) {
			$msg = __( 'An error occurred.', 'lingua-visual' );
		}
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Página principal con pestañas.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You don\'t have permission to view this page.', 'lingua-visual' ) );
		}
		if ( ! LGV_DB::tables_exist() ) {
			LGV_DB::create_tables();
		}
		self::process_forms();

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'langs';
		$tabs = array(
			'langs'    => __( 'Languages', 'lingua-visual' ),
			'switcher' => __( 'Switcher', 'lingua-visual' ),
			'urls'     => __( 'URLs', 'lingua-visual' ),
			'detect'   => __( 'Detection', 'lingua-visual' ),
			'discover' => __( 'Discover', 'lingua-visual' ),
			'auto'     => __( 'Automatic', 'lingua-visual' ),
			'strings'  => __( 'Strings', 'lingua-visual' ),
			'media'    => __( 'Media', 'lingua-visual' ),
			'tools'    => __( 'Import / Export', 'lingua-visual' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'langs';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lingua Visual', 'lingua-visual' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Visual translation with unlimited languages. Enable visual mode from the admin bar on the frontend ("Translate this page") and click any text to translate it.', 'lingua-visual' ); ?>
			</p>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, menu_page_url( 'lingua-visual', false ) ) ); ?>"
						class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>
			<?php
			if ( 'langs' === $tab ) {
				self::tab_langs();
			} elseif ( 'switcher' === $tab ) {
				self::tab_switcher();
			} elseif ( 'urls' === $tab ) {
				self::tab_urls();
			} elseif ( 'detect' === $tab ) {
				self::tab_detect();
			} elseif ( 'discover' === $tab ) {
				self::tab_discover();
			} elseif ( 'auto' === $tab ) {
				self::tab_auto();
			} elseif ( 'strings' === $tab ) {
				self::tab_strings();
			} elseif ( 'media' === $tab ) {
				self::tab_media();
			} else {
				self::tab_tools();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Pestaña: gestor de idiomas.
	 */
	private static function tab_langs() {
		$langs   = LGV_Languages::all();
		$default = LGV_Languages::get_default();
		?>
		<h2><?php esc_html_e( 'Languages', 'lingua-visual' ); ?></h2>
		<p><?php esc_html_e( 'No language limit. The default language shows without a prefix in the URL (e.g. /my-page/); the others use their prefix (e.g. /en/my-page/).', 'lingua-visual' ); ?></p>
		<table class="widefat striped" style="max-width:700px">
			<thead><tr>
				<th><?php esc_html_e( 'Code', 'lingua-visual' ); ?></th>
				<th><?php esc_html_e( 'Language', 'lingua-visual' ); ?></th>
				<th><?php esc_html_e( 'Default', 'lingua-visual' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'lingua-visual' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $langs as $code => $data ) : ?>
				<tr>
					<td><code><?php echo esc_html( $code ); ?></code></td>
					<td><?php echo esc_html( LGV_Languages::label( $code ) ); ?></td>
					<td><?php echo $code === $default ? '✔' : ''; ?></td>
					<td>
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'lgv_langs', 'lgv_nonce' ); ?>
							<input type="hidden" name="code" value="<?php echo esc_attr( $code ); ?>">
							<?php if ( $code !== $default ) : ?>
								<button type="submit" name="lgv_set_default" class="button button-small"><?php esc_html_e( 'Make default', 'lingua-visual' ); ?></button>
								<button type="submit" name="lgv_remove_lang" class="button button-small button-link-delete"
									onclick="return confirm('<?php esc_attr_e( 'Remove this language and its translations?', 'lingua-visual' ); ?>')"><?php esc_html_e( 'Remove', 'lingua-visual' ); ?></button>
							<?php endif; ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Add language', 'lingua-visual' ); ?></h3>
		<form method="post">
			<?php wp_nonce_field( 'lgv_langs', 'lgv_nonce' ); ?>
			<table class="form-table" style="max-width:700px">
				<tr>
					<th><label for="lgv-code"><?php esc_html_e( 'Code', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-code" name="code" type="text" maxlength="20" required placeholder="en" pattern="[a-z0-9-]+">
						<p class="description"><?php esc_html_e( 'Lowercase, e.g.: en, pt, fr.', 'lingua-visual' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="lgv-name"><?php esc_html_e( 'Name', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-name" name="name" type="text" required placeholder="English" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="lgv-flag"><?php esc_html_e( 'Flag (emoji)', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-flag" name="flag" type="text" maxlength="12" placeholder="🇬🇧"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Add language', 'lingua-visual' ), 'primary', 'lgv_add_lang' ); ?>
		</form>

		<h3><?php esc_html_e( 'Language switcher', 'lingua-visual' ); ?></h3>
		<p>
			<?php esc_html_e( 'Shortcode:', 'lingua-visual' ); ?> <code>[lingua_switcher]</code><br>
			<?php esc_html_e( 'With attributes:', 'lingua-visual' ); ?> <code>[lingua_switcher mode="dropdown" flag_shape="circle" name_format="iso"]</code><br>
			<span class="description"><?php esc_html_e( 'Attributes: mode (dropdown, inline, flags, text, flag_text, opposite, pill_modal), trigger (click, hover), flag_position (before, after, hidden), flag_shape (rect, rounded, circle), name_format (native, iso, none), class. Everything is customized in the Switcher tab.', 'lingua-visual' ); ?></span><br>
			<?php esc_html_e( 'Or add the "Language Switcher (Lingua Visual)" widget in Appearance → Widgets.', 'lingua-visual' ); ?>
		</p>
		<?php
	}

	/**
	 * Pestaña: ajustes de URLs por idioma.
	 */
	private static function tab_urls() {
		$s    = LGV_Routing::settings();
		$home = home_url();
		?>
		<h2><?php esc_html_e( 'URLs per language', 'lingua-visual' ); ?></h2>
		<p>
			<?php esc_html_e( 'With prefixes enabled, each language lives at its own URL (e.g. /en/my-page/). The default language has no prefix. Since each language is a different URL, page caches (WP Rocket and similar) store one translated version per language and the translation persists while browsing.', 'lingua-visual' ); ?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'lgv_urls', 'lgv_nonce' ); ?>
			<table class="form-table" style="max-width:700px">
				<tr>
					<th scope="row"><?php esc_html_e( 'Language prefixes', 'lingua-visual' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="url_prefixes" value="1" <?php checked( ! empty( $s['url_prefixes'] ) ); ?>>
							<?php esc_html_e( 'Enable language-prefixed URLs', 'lingua-visual' ); ?>
						</label>
						<p class="description">
							<?php printf( esc_html__( 'Example: %s', 'lingua-visual' ), esc_html( trailingslashit( $home ) . 'en/' ) ); ?>
							<br><?php esc_html_e( 'No custom rewrite rules are added: the prefix is detected before WordPress parsing and works with any permalink structure.', 'lingua-visual' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic redirection', 'lingua-visual' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="redirect_legacy" value="1" <?php checked( ! empty( $s['redirect_legacy'] ) ); ?>>
							<?php esc_html_e( 'Redirect ?lang= and the cookie to the prefixed URL (302)', 'lingua-visual' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'This way the language always ends up in the URL. If you disable it, ?lang= and the cookie keep working with output buffering (0.1.0 behavior).', 'lingua-visual' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save URL settings', 'lingua-visual' ), 'primary', 'lgv_save_url_settings' ); ?>
		</form>

		<h3><?php esc_html_e( 'Notes', 'lingua-visual' ); ?></h3>
		<ul class="ul-disc" style="list-style:disc;margin-left:20px;max-width:700px">
			<li><?php esc_html_e( 'The default language never gets a prefix; if someone visits /es/my-page/ while "es" is the default, it redirects (301) to /my-page/.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'System routes (wp-admin, wp-login.php, wp-json, wp-content, etc.) never get a prefix.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'Avoid using a registered language code as a page slug (e.g. don\'t create a page called "en").', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'The <head> prints the hreflang of each language plus x-default, and the canonical follows the active language.', 'lingua-visual' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Pestaña: detección automática del idioma (geolocalización IP + navegador).
	 */
	private static function tab_detect() {
		$s       = LGV_Detect::settings();
		$langs   = LGV_Languages::all();
		$default = LGV_Languages::get_default();
		$cf_now  = ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? strtoupper( (string) $_SERVER['HTTP_CF_IPCOUNTRY'] ) : '';

		// Simulador: se procesa con POST en la misma pestaña.
		$sim = null;
		if ( isset( $_POST['lgv_simulate'] ) ) {
			check_admin_referer( 'lgv_detect', 'lgv_nonce' );
			$sim_country = isset( $_POST['sim_country'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) wp_unslash( $_POST['sim_country'] ) ) ) : '';
			$sim_al      = isset( $_POST['sim_al'] ) ? sanitize_text_field( wp_unslash( $_POST['sim_al'] ) ) : '';
			$sim         = LGV_Detect::simulate( $sim_country, $sim_al, $s['method'] );
			$sim['country_input'] = $sim_country;
			$sim['al_input']      = $sim_al;
		}
		?>
		<h2><?php esc_html_e( 'Automatic language detection', 'lingua-visual' ); ?></h2>
		<p style="max-width:760px">
			<?php esc_html_e( 'On the first visit, the plugin can guess the visitor\'s language and redirect them (302) to the matching prefixed URL. The user\'s explicit choice (prefixed URL, ?lang= or cookie) always wins and is never redirected. Detection runs once per visitor (30-day lgv_detected cookie).', 'lingua-visual' ); ?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'lgv_detect', 'lgv_nonce' ); ?>
			<table class="form-table" style="max-width:760px">
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic detection', 'lingua-visual' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="dt[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
							<?php esc_html_e( 'Enable automatic detection and redirection', 'lingua-visual' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Method', 'lingua-visual' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="dt[method]" value="both" <?php checked( $s['method'], 'both' ); ?>>
							<?php esc_html_e( 'Browser + IP (recommended)', 'lingua-visual' ); ?>
							<span class="description"><?php esc_html_e( 'First the browser language; if it doesn\'t match an active language, the IP country is used.', 'lingua-visual' ); ?></span>
						</label>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="dt[method]" value="browser" <?php checked( $s['method'], 'browser' ); ?>>
							<?php esc_html_e( 'Browser language only (Accept-Language header)', 'lingua-visual' ); ?>
						</label>
						<label style="display:block">
							<input type="radio" name="dt[method]" value="ip" <?php checked( $s['method'], 'ip' ); ?>>
							<?php esc_html_e( 'IP geolocation only', 'lingua-visual' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Country-by-IP provider', 'lingua-visual' ); ?></th>
					<td>
						<p class="description" style="max-width:640px">
							<?php esc_html_e( 'Query order: 1) Cloudflare header (free, no external calls), 2) local map below, 3) external API (only if you enable it).', 'lingua-visual' ); ?>
							<br>
							<?php
							if ( '' !== $cf_now ) {
								printf(
									/* translators: %s: código de país. */
									esc_html__( 'On your current visit Cloudflare reports country: %s.', 'lingua-visual' ),
									'<code>' . esc_html( $cf_now ) . '</code>'
								);
							} else {
								esc_html_e( 'On your current visit there is no Cloudflare header (your site doesn\'t go through their proxy or doesn\'t send the country).', 'lingua-visual' );
							}
							?>
						</p>
						<label for="lgv-local-map"><strong><?php esc_html_e( 'Local map (optional)', 'lingua-visual' ); ?></strong></label>
						<textarea id="lgv-local-map" name="dt[local_map]" rows="4" cols="50" class="large-text code" placeholder="203.0.113.0/24=AR&#10;198.51.100.7=US"><?php echo esc_textarea( $s['local_map'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One rule per line: CIDR=COUNTRY or IP=COUNTRY (e.g. 203.0.113.0/24=AR). Useful for tests or known ranges. No external calls.', 'lingua-visual' ); ?></p>
						<label style="display:block;margin-top:10px">
							<input type="checkbox" name="dt[api_enabled]" value="1" <?php checked( ! empty( $s['api_enabled'] ) ); ?>>
							<?php esc_html_e( 'Enable querying an external GeoIP API', 'lingua-visual' ); ?>
						</label>
						<p class="description" style="max-width:640px">
							<?php esc_html_e( 'Privacy: only enable it if you agree to send the visitor\'s IP to a third party. The result is cached per IP (transient) to avoid hammering the API.', 'lingua-visual' ); ?>
							<?php esc_html_e( 'The ip-api.com free plan allows 45 queries per minute.', 'lingua-visual' ); ?>
						</p>
						<input name="dt[api_url]" type="url" class="large-text" value="<?php echo esc_attr( $s['api_url'] ); ?>" placeholder="http://ip-api.com/json/{ip}?fields=status,countryCode">
						<p class="description"><?php esc_html_e( 'API URL. Use {ip} as placeholder. It must return JSON with countryCode, country_code or country (2 letters).', 'lingua-visual' ); ?></p>
						<label>
							<?php esc_html_e( 'Days of cache per IP:', 'lingua-visual' ); ?>
							<input name="dt[api_cache_days]" type="number" min="1" max="365" value="<?php echo esc_attr( (int) $s['api_cache_days'] ); ?>" style="width:70px">
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lgv-fallback"><?php esc_html_e( 'Fallback language', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-fallback" name="dt[fallback_lang]">
							<option value="" <?php selected( $s['fallback_lang'], '' ); ?>>
								<?php printf( esc_html__( 'Site default language (%s)', 'lingua-visual' ), esc_html( $default ) ); ?>
							</option>
							<?php foreach ( $langs as $code => $data ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['fallback_lang'], $code ); ?>>
									<?php echo esc_html( $code . ' — ' . $data['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used when detection finds no match with your active languages (e.g. a visitor from France and you only have Spanish and English).', 'lingua-visual' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rules', 'lingua-visual' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px">
							<input type="checkbox" name="dt[exclude_bots]" value="1" <?php checked( ! empty( $s['exclude_bots'] ) ); ?>>
							<?php esc_html_e( 'Never redirect bots/crawlers (Googlebot, Bingbot, etc.)', 'lingua-visual' ); ?>
							<span class="description"><?php esc_html_e( 'Critical for SEO: search engines must see every language version.', 'lingua-visual' ); ?></span>
						</label>
						<span style="display:block;margin:6px 0 4px"><strong><?php esc_html_e( 'Scope:', 'lingua-visual' ); ?></strong></span>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="dt[scope]" value="all" <?php checked( $s['scope'], 'all' ); ?>>
							<?php esc_html_e( 'All pages', 'lingua-visual' ); ?>
						</label>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="dt[scope]" value="home" <?php checked( $s['scope'], 'home' ); ?>>
							<?php esc_html_e( 'Homepage only', 'lingua-visual' ); ?>
						</label>
						<label style="display:block">
							<input type="checkbox" name="dt[show_notice]" value="1" <?php checked( ! empty( $s['show_notice'] ) ); ?>>
							<?php esc_html_e( 'Show a "We switched you to X" notice after redirecting, with a link to go back', 'lingua-visual' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save detection settings', 'lingua-visual' ), 'primary', 'lgv_save_detect' ); ?>
		</form>

		<h3><?php esc_html_e( 'Test simulator', 'lingua-visual' ); ?></h3>
		<p class="description" style="max-width:760px"><?php esc_html_e( 'Pick a country and an Accept-Language header to see which language would result with your current configuration, without redirecting anyone.', 'lingua-visual' ); ?></p>
		<form method="post" style="max-width:760px">
			<?php wp_nonce_field( 'lgv_detect', 'lgv_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="lgv-sim-country"><?php esc_html_e( 'Country (IP)', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-sim-country" name="sim_country">
							<option value="">—</option>
							<?php foreach ( LGV_Country_Langs::countries_list() as $cc => $name ) : ?>
								<option value="<?php echo esc_attr( $cc ); ?>" <?php selected( isset( $sim['country_input'] ) ? $sim['country_input'] : '', $cc ); ?>>
									<?php echo esc_html( $cc . ' — ' . $name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lgv-sim-al"><?php esc_html_e( 'Accept-Language', 'lingua-visual' ); ?></label></th>
					<td>
						<input id="lgv-sim-al" name="sim_al" type="text" class="large-text" placeholder="fr-CA,fr;q=0.9,en;q=0.8"
							value="<?php echo esc_attr( isset( $sim['al_input'] ) ? $sim['al_input'] : '' ); ?>">
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Simulate', 'lingua-visual' ), 'secondary', 'lgv_simulate' ); ?>
		</form>
		<?php if ( null !== $sim ) : ?>
			<div class="notice notice-info inline" style="max-width:760px;margin-top:12px">
				<p><strong>
					<?php
					printf(
						/* translators: %s: idioma ganador. */
						esc_html__( 'Result: %s', 'lingua-visual' ),
						esc_html( $sim['winner'] )
					);
					echo $sim['used_fallback'] ? ' ' . esc_html__( '(fallback)', 'lingua-visual' ) : '';
					?>
				</strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $sim['steps'] as $step ) : ?>
						<li><?php echo esc_html( $step ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Notes', 'lingua-visual' ); ?></h3>
		<ul class="ul-disc" style="list-style:disc;margin-left:20px;max-width:760px">
			<li><?php esc_html_e( 'The redirect is 302 with anti-cache headers: it never gets stored as permanent in page caches.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'If URL prefixes are disabled (URLs tab), detection sets the lingua_lang cookie without redirecting and the translation is applied via output buffering.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'Private or invalid IPs can\'t be geolocated: in those cases only the browser language (or the fallback) counts.', 'lingua-visual' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Pestaña: descubridor de cadenas del frontend.
	 */
	private static function tab_discover() {
		$state  = LGV_Discover::get_state();
		$manual = get_option( LGV_Discover::MANUAL_OPTION, '' );
		?>
		<h2><?php esc_html_e( 'Discover frontend strings', 'lingua-visual' ); ?></h2>
		<p>
			<?php esc_html_e( 'Crawls your site\'s public pages and registers all visible texts and translatable attributes (title, alt, placeholder, aria-label, meta description) in the dictionary. Then you can translate them in bulk from the Automatic tab or by hand in Strings.', 'lingua-visual' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Honest limitation: content generated 100% with JavaScript after page load isn\'t captured server-side. For those cases use visual mode (click the text) or output buffering.', 'lingua-visual' ); ?>
		</p>

		<table class="form-table" style="max-width:700px">
			<tr>
				<th scope="row"><?php esc_html_e( 'Source', 'lingua-visual' ); ?></th>
				<td>
					<label style="display:block;margin-bottom:8px">
						<input type="radio" name="lgv_disc_mode" value="sitemap" checked>
						<?php esc_html_e( 'Site sitemap (automatic)', 'lingua-visual' ); ?>
						<span class="description"><?php echo esc_html( home_url( '/wp-sitemap.xml' ) ); ?></span>
					</label>
					<label style="display:block">
						<input type="radio" name="lgv_disc_mode" value="manual">
						<?php esc_html_e( 'Manual URL list (one per line)', 'lingua-visual' ); ?>
					</label>
					<textarea id="lgv-disc-urls" rows="5" cols="70" class="large-text code" placeholder="https://tusitio.com/pagina-1/"><?php echo esc_textarea( $manual ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Only URLs from this same site are accepted. wp-admin and the login are never crawled.', 'lingua-visual' ); ?></p>
				</td>
			</tr>
		</table>
		<p>
			<button id="lgv-disc-start" class="button button-primary"><?php esc_html_e( 'Start discovery', 'lingua-visual' ); ?></button>
		</p>
		<div id="lgv-disc-msg"></div>
		<div style="max-width:700px;background:#e5e5e5;border-radius:4px;height:22px;margin:12px 0">
			<div id="lgv-disc-bar-fill" style="background:#2271b1;height:22px;border-radius:4px;width:0%;transition:width .3s"></div>
		</div>
		<p><strong id="lgv-disc-bar-pct">0%</strong> — <span id="lgv-disc-stats"></span></p>
		<div id="lgv-disc-errors" class="notice notice-warning" style="display:none;max-width:700px"></div>

		<?php if ( 'idle' !== $state['status'] ) : ?>
			<h3><?php esc_html_e( 'Last crawl', 'lingua-visual' ); ?></h3>
			<ul class="ul-disc" style="list-style:disc;margin-left:20px">
				<li><?php printf( esc_html__( 'Source: %s', 'lingua-visual' ), esc_html( $state['source'] ) ); ?></li>
				<li><?php printf( esc_html__( 'Pages: %1$d of %2$d', 'lingua-visual' ), (int) $state['done'], (int) $state['total'] ); ?></li>
				<li><?php printf( esc_html__( 'Strings found: %1$d (%2$d new)', 'lingua-visual' ), (int) $state['found'], (int) $state['new'] ); ?></li>
				<li><?php printf( esc_html__( 'Errors: %d', 'lingua-visual' ), count( (array) $state['errors'] ) ); ?></li>
				<li><?php printf( esc_html__( 'Finished: %s', 'lingua-visual' ), esc_html( $state['finished'] ) ); ?></li>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Pestaña: traducción automática por API.
	 */
	private static function tab_auto() {
		$s         = LGV_AutoTranslate::settings();
		$providers = LGV_AutoTranslate::providers();
		$target    = LGV_AutoTranslate::default_target();
		$pending   = '' !== $target ? LGV_AutoTranslate::pending_count( $target ) : 0;
		?>
		<h2><?php esc_html_e( 'Automatic translation', 'lingua-visual' ); ?></h2>
		<p>
			<?php esc_html_e( 'Bulk-translate pending strings with an external provider. Automatic translations are marked as "pending review": if you edit them by hand they become manual and are never overwritten.', 'lingua-visual' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'lgv_auto', 'lgv_nonce' ); ?>
			<h3><?php esc_html_e( 'Provider', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px">
				<?php foreach ( $providers as $pid => $p ) : ?>
					<tr>
						<th scope="row" style="width:220px">
							<label>
								<input type="radio" name="at[provider]" value="<?php echo esc_attr( $pid ); ?>" <?php checked( $s['provider'], $pid ); ?>>
								<strong><?php echo esc_html( $p->label() ); ?></strong>
							</label>
						</th>
						<td>
							<?php if ( 'mymemory' === $pid ) : ?>
								<p class="description"><?php esc_html_e( 'Free with no key: works out of the box. ~5,000 chars/day anonymous, ~50,000/day with email. Max ~500 bytes per request (long texts are split automatically). Medium quality: ideal to get started, review the results.', 'lingua-visual' ); ?></p>
								<label><?php esc_html_e( 'Email (optional, raises the quota)', 'lingua-visual' ); ?><br>
									<input type="email" name="at[mymemory_email]" value="<?php echo esc_attr( $s['mymemory_email'] ); ?>" class="regular-text"></label>
							<?php elseif ( 'deepl' === $pid ) : ?>
								<p class="description"><?php esc_html_e( 'Best quality for European pairs. 500,000 chars/month free with a free DeepL account (no card).', 'lingua-visual' ); ?></p>
								<label><?php esc_html_e( 'DeepL API key', 'lingua-visual' ); ?><br>
									<input type="text" name="at[deepl_key]" value="<?php echo esc_attr( $s['deepl_key'] ); ?>" class="regular-text code" autocomplete="off" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx:fx"></label>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'EXPERIMENTAL: unofficial free Google endpoint. It may be throttled or stop working at any time. Do not use it as a dependency.', 'lingua-visual' ); ?></p>
							<?php endif; ?>
							<p class="description"><?php printf( esc_html__( 'Quota: %s', 'lingua-visual' ), esc_html( LGV_AutoTranslate::quota_label( $pid ) ) ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="lgv-at-target"><?php esc_html_e( 'Target language', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-at-target" name="at[target_lang]">
							<option value=""><?php esc_html_e( '— First non-default language —', 'lingua-visual' ); ?></option>
							<?php foreach ( LGV_Languages::all() as $code => $data ) : ?>
								<?php if ( $code === LGV_Languages::get_default() ) { continue; } ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['target_lang'], $code ); ?>><?php echo esc_html( LGV_Languages::label( $code ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'The source language is always the site default.', 'lingua-visual' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'lingua-visual' ), 'primary', 'lgv_save_at' ); ?>
		</form>

		<h3><?php esc_html_e( 'Translate pending', 'lingua-visual' ); ?></h3>
		<?php if ( '' === $target ) : ?>
			<p class="description"><?php esc_html_e( 'Add at least one language besides the default in the Languages tab to use automatic translation.', 'lingua-visual' ); ?></p>
		<?php else : ?>
			<p>
				<?php printf( esc_html__( 'Target language: %1$s — %2$d pending strings.', 'lingua-visual' ), '<strong>' . esc_html( LGV_Languages::label( $target ) ) . '</strong>', (int) $pending ); ?>
				<input type="hidden" id="lgv-auto-lang" value="<?php echo esc_attr( $target ); ?>">
			</p>
			<p>
				<button id="lgv-auto-start" class="button button-primary" <?php disabled( 0 === $pending ); ?>><?php esc_html_e( 'Translate all pending', 'lingua-visual' ); ?></button>
			</p>
			<div id="lgv-auto-msg"></div>
			<p><strong id="lgv-auto-stats"></strong></p>
			<div id="lgv-auto-errors" class="notice notice-warning" style="display:none;max-width:760px"></div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Honest notes', 'lingua-visual' ); ?></h3>
		<ul class="ul-disc" style="list-style:disc;margin-left:20px;max-width:760px">
			<li><?php esc_html_e( 'Texts are sent to the chosen provider\'s servers. If privacy is critical, translate by hand.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'No engine understands brand context (show names, "Comando" as a brand). Manual review is not optional.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'When the quota is reached, the process stops on its own and pending items wait for the next period. Nothing breaks mid-batch.', 'lingua-visual' ); ?></li>
			<li><?php esc_html_e( 'The visual mode also suggests translations with this provider (lgv_auto_translate filter).', 'lingua-visual' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Pestaña: lista de cadenas descubiertas con sus traducciones.
	 */
	private static function tab_strings() {
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$auto_only = isset( $_GET['autof'] ) && '1' === $_GET['autof'];
		$page      = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$result    = LGV_Strings::list( $search, $page, 50, array( 'auto_only' => $auto_only ) );
		$langs     = LGV_Languages::all();
		$base      = add_query_arg( 'tab', 'strings', menu_page_url( 'lingua-visual', false ) );
		?>
		<h2><?php esc_html_e( 'Discovered strings', 'lingua-visual' ); ?></h2>
		<p><?php esc_html_e( 'Strings are registered when you translate them with visual mode, when you add them here, when you import them via CSV or when the crawler discovers them.', 'lingua-visual' ); ?></p>

		<form method="get" style="margin-bottom:12px">
			<input type="hidden" name="page" value="lingua-visual">
			<input type="hidden" name="tab" value="strings">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search strings…', 'lingua-visual' ); ?>">
			<?php submit_button( __( 'Search', 'lingua-visual' ), 'secondary', '', false ); ?>
			<a class="button <?php echo $auto_only ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'autof', $auto_only ? '0' : '1', $base ) ); ?>"><?php $auto_only ? esc_html_e( 'View all', 'lingua-visual' ) : esc_html_e( 'Only automatic', 'lingua-visual' ); ?></a>
		</form>

		<h3><?php esc_html_e( 'Add string manually', 'lingua-visual' ); ?></h3>
		<form method="post">
			<?php wp_nonce_field( 'lgv_strings', 'lgv_nonce' ); ?>
			<table class="form-table" style="max-width:700px">
				<tr>
					<th><label for="lgv-original"><?php esc_html_e( 'Original text', 'lingua-visual' ); ?></label></th>
					<td><textarea id="lgv-original" name="original" rows="2" cols="60" required></textarea></td>
				</tr>
				<tr>
					<th><label for="lgv-man-lang"><?php esc_html_e( 'Language', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-man-lang" name="lang">
							<?php foreach ( $langs as $code => $data ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( LGV_Languages::label( $code ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="lgv-man-trans"><?php esc_html_e( 'Translation', 'lingua-visual' ); ?></label></th>
					<td><textarea id="lgv-man-trans" name="translation" rows="2" cols="60"></textarea>
						<p class="description"><?php esc_html_e( 'Optional: you can register just the original and translate later.', 'lingua-visual' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save string', 'lingua-visual' ), 'primary', 'lgv_add_string' ); ?>
		</form>

		<h3><?php printf( esc_html__( 'Dictionary (%d strings)', 'lingua-visual' ), (int) $result['total'] ); ?></h3>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Original', 'lingua-visual' ); ?></th>
				<?php foreach ( $langs as $code => $data ) : ?>
					<th><?php echo esc_html( LGV_Languages::label( $code ) ); ?></th>
				<?php endforeach; ?>
				<th><?php esc_html_e( 'Actions', 'lingua-visual' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $result['items'] as $item ) : ?>
				<tr>
					<td style="max-width:320px;overflow-wrap:anywhere">
						<strong><?php echo esc_html( $item['original'] ); ?></strong>
						<?php if ( '' !== $item['context'] ) : ?>
							<br><span class="description"><?php echo esc_html( $item['context'] ); ?></span>
						<?php endif; ?>
					</td>
					<?php foreach ( $langs as $code => $data ) : ?>
						<?php
						$tr      = isset( $item['translations'][ $code ] ) ? $item['translations'][ $code ] : null;
						$tr_text = is_array( $tr ) ? $tr['text'] : (string) $tr;
						$tr_auto = is_array( $tr ) && ! empty( $tr['auto'] );
						?>
						<td style="min-width:180px">
							<form method="post">
								<?php wp_nonce_field( 'lgv_strings', 'lgv_nonce' ); ?>
								<input type="hidden" name="string_id" value="<?php echo (int) $item['id']; ?>">
								<input type="hidden" name="lang" value="<?php echo esc_attr( $code ); ?>">
								<?php if ( $tr_auto ) : ?>
									<span class="description" style="display:inline-block;background:#fff8e1;border:1px solid #e6c200;border-radius:3px;padding:1px 6px;margin-bottom:4px">🤖 <?php esc_html_e( 'automatic', 'lingua-visual' ); ?></span><br>
								<?php endif; ?>
								<textarea name="translation" rows="2" style="width:100%"><?php echo esc_textarea( $tr_text ); ?></textarea>
								<button type="submit" name="lgv_save_row" class="button button-small"><?php esc_html_e( 'Save', 'lingua-visual' ); ?></button>
								<?php if ( $tr_auto ) : ?>
									<button type="submit" name="lgv_mark_reviewed" class="button button-small"><?php esc_html_e( '✓ Reviewed', 'lingua-visual' ); ?></button>
								<?php endif; ?>
							</form>
						</td>
					<?php endforeach; ?>
					<td>
						<form method="post">
							<?php wp_nonce_field( 'lgv_strings', 'lgv_nonce' ); ?>
							<input type="hidden" name="string_id" value="<?php echo (int) $item['id']; ?>">
							<button type="submit" name="lgv_delete_string" class="button button-small button-link-delete"
								onclick="return confirm('<?php esc_attr_e( 'Delete this string and its translations?', 'lingua-visual' ); ?>')"><?php esc_html_e( 'Delete', 'lingua-visual' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr><td colspan="<?php echo count( $langs ) + 2; ?>"><?php esc_html_e( 'No strings yet. Use visual mode on the frontend to get started.', 'lingua-visual' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		$total_pages = (int) ceil( $result['total'] / 50 );
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%', $base ) . ( '' !== $search ? '&s=' . rawurlencode( $search ) : '' ),
					'format'    => '',
					'current'   => $page,
					'total'     => $total_pages,
					'prev_text' => __( '« Previous', 'lingua-visual' ),
					'next_text' => __( 'Next »', 'lingua-visual' ),
				)
			);
			echo '</div></div>';
		}
	}

	/**
	 * Media tab: per-language attachment translation.
	 */
	private static function tab_media() {
		$langs = LGV_Languages::all();
		$non_default = array();
		foreach ( $langs as $code => $data ) {
			if ( $code !== LGV_Languages::get_default() ) {
				$non_default[ $code ] = $data;
			}
		}
		if ( get_transient( 'lgv_media_saved' ) ) {
			delete_transient( 'lgv_media_saved' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Media translations saved.', 'lingua-visual' ) . '</p></div>';
		}
		if ( empty( $non_default ) ) {
			echo '<p>' . esc_html__( 'Add at least one language besides the default in the Languages tab to translate media.', 'lingua-visual' ) . '</p>';
			return;
		}
		?>
		<p><?php esc_html_e( 'Translate alt text, title, caption and description of your media library per language. Values are stored in the dictionary, so the frontend output buffer translates them automatically.', 'lingua-visual' ); ?></p>
		<?php
		$edit_id = isset( $_GET['edit_media'] ) ? (int) $_GET['edit_media'] : 0;
		if ( $edit_id > 0 ) {
			self::media_edit_form( $edit_id, $non_default );
			return;
		}
		$page = isset( $_GET['mpage'] ) ? max( 1, (int) $_GET['mpage'] ) : 1;
		$data = LGV_Media::list_attachments( $page, 20 );
		$total_pages = (int) ceil( $data['total'] / 20 );
		?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Media', 'lingua-visual' ); ?></th>
				<th><?php esc_html_e( 'Alt text', 'lingua-visual' ); ?></th>
				<?php foreach ( $non_default as $code => $ldata ) : ?>
					<th><?php echo esc_html( LGV_Languages::label( $code ) ); ?></th>
				<?php endforeach; ?>
			</tr></thead>
			<tbody>
			<?php foreach ( $data['items'] as $att ) : ?>
				<tr>
					<td>
						<div style="display:flex;align-items:center;gap:10px">
							<?php echo wp_get_attachment_image( $att->ID, array( 60, 60 ), false, array( 'style' => 'border-radius:4px;flex-shrink:0' ) ); ?>
							<div>
								<strong><?php echo esc_html( get_the_title( $att->ID ) ); ?></strong>
								<br><small style="color:#646970"><?php echo esc_html( basename( (string) get_attached_file( $att->ID ) ) ); ?></small>
								<br><a class="button button-small" style="margin-top:4px" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'media', 'edit_media' => $att->ID ), menu_page_url( 'lingua-visual', false ) ) ); ?>"><?php esc_html_e( 'Translate', 'lingua-visual' ); ?></a>
							</div>
						</div>
					</td>
					<td><small><?php echo esc_html( LGV_Media::default_value( $att->ID, 'alt' ) ); ?></small></td>
					<?php foreach ( $non_default as $code => $ldata ) : ?>
						<td style="text-align:center">
							<?php if ( LGV_Media::get( $att->ID, 'alt', $code ) ) : ?>
								<span style="display:inline-block;background:#d7f0d7;color:#1a7a1a;border-radius:10px;padding:2px 10px;font-size:12px;font-weight:600">✓</span>
							<?php else : ?>
								<span style="display:inline-block;background:#f0f0f1;color:#8c8f94;border-radius:10px;padding:2px 10px;font-size:12px">—</span>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$url = add_query_arg( array( 'tab' => 'media', 'mpage' => $i ), menu_page_url( 'lingua-visual', false ) );
				echo $i === $page
					? '<span class="tablenav-pages-navspan button disabled">' . $i . '</span> '
					: '<a class="button" href="' . esc_url( $url ) . '">' . $i . '</a> ';
			}
			echo '</div></div>';
		}
	}

	/**
	 * Media attachment edit form.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $langs         Non-default languages.
	 */
	private static function media_edit_form( $attachment_id, $langs ) {
		$att = get_post( $attachment_id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			echo '<p>' . esc_html__( 'Invalid attachment.', 'lingua-visual' ) . '</p>';
			return;
		}
		$back = add_query_arg( 'tab', 'media', menu_page_url( 'lingua-visual', false ) );
		?>
		<p><a href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'Back', 'lingua-visual' ); ?></a></p>
		<h2><?php echo esc_html( get_the_title( $attachment_id ) ); ?></h2>
		<?php echo wp_get_attachment_image( $attachment_id, 'medium' ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lgv_save_media">
			<input type="hidden" name="attachment_id" value="<?php echo (int) $attachment_id; ?>">
			<?php wp_nonce_field( 'lgv_media', 'lgv_nonce' ); ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Field', 'lingua-visual' ); ?></th>
					<th><?php esc_html_e( 'Default', 'lingua-visual' ); ?></th>
					<?php foreach ( $langs as $code => $ldata ) : ?>
						<th><?php echo esc_html( LGV_Languages::label( $code ) ); ?></th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody>
				<?php
				$field_labels = array(
					'alt'         => __( 'Alt text', 'lingua-visual' ),
					'title'       => __( 'Title', 'lingua-visual' ),
					'caption'     => __( 'Caption', 'lingua-visual' ),
					'description' => __( 'Description', 'lingua-visual' ),
				);
				foreach ( LGV_Media::FIELDS as $field ) :
					?>
					<tr>
						<td><strong><?php echo esc_html( $field_labels[ $field ] ); ?></strong></td>
						<td><small><?php echo esc_html( LGV_Media::default_value( $attachment_id, $field ) ); ?></small></td>
						<?php foreach ( $langs as $code => $ldata ) : ?>
							<td><textarea name="lgv_media[<?php echo esc_attr( $code ); ?>][<?php echo esc_attr( $field ); ?>]" rows="2" cols="30"><?php echo esc_textarea( LGV_Media::get( $attachment_id, $field, $code ) ); ?></textarea></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save media translations', 'lingua-visual' ) ); ?>
		</form>

		<h2 style="margin-top:30px"><?php esc_html_e( 'Image file per language', 'lingua-visual' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use a different image file for each language (e.g. banners with text). Leave empty to use the default file.', 'lingua-visual' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lgv_save_media_file">
			<input type="hidden" name="attachment_id" value="<?php echo (int) $attachment_id; ?>">
			<?php wp_nonce_field( 'lgv_media', 'lgv_nonce' ); ?>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr>
					<th><?php esc_html_e( 'Language', 'lingua-visual' ); ?></th>
					<th><?php esc_html_e( 'Replacement file', 'lingua-visual' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $langs as $code => $ldata ) :
					$rep_id = LGV_Media::get_file( $attachment_id, $code );
					?>
					<tr>
						<td><strong><?php echo esc_html( LGV_Languages::label( $code ) ); ?></strong></td>
						<td>
							<div style="display:flex;align-items:center;gap:10px">
								<span class="lgv-file-thumb">
									<?php echo $rep_id ? wp_get_attachment_image( $rep_id, array( 60, 60 ), false, array( 'style' => 'border-radius:4px' ) ) : ''; ?>
								</span>
								<input type="hidden" name="lgv_media_file[<?php echo esc_attr( $code ); ?>]" value="<?php echo (int) $rep_id; ?>" class="lgv-file-id">
								<button type="button" class="button lgv-file-pick"><?php esc_html_e( 'Choose image', 'lingua-visual' ); ?></button>
								<?php if ( $rep_id ) : ?>
									<button type="button" class="button lgv-file-clear"><?php esc_html_e( 'Remove', 'lingua-visual' ); ?></button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save file replacements', 'lingua-visual' ) ); ?>
		</form>
		<script>
		jQuery(function($){
			var frame;
			$('.lgv-file-pick').on('click', function(e){
				e.preventDefault();
				var $row = $(this).closest('td');
				frame = wp.media({ title: 'Choose replacement image', library: { type: 'image' }, multiple: false });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$row.find('.lgv-file-id').val(att.id);
					$row.find('.lgv-file-thumb').html('<img src="'+att.sizes.thumbnail.url+'" style="border-radius:4px" width="60" height="60">');
				});
				frame.open();
			});
			$('.lgv-file-clear').on('click', function(){
				var $row = $(this).closest('td');
				$row.find('.lgv-file-id').val('0');
				$row.find('.lgv-file-thumb').html('');
				$(this).remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Pestaña: importación/exportación CSV.
	 */
	private static function tab_tools() {
		$langs = LGV_Languages::all();
		$import_result = get_transient( 'lgv_import_result' );
		if ( $import_result ) {
			delete_transient( 'lgv_import_result' );
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html(
					sprintf(
						/* translators: %1$d importadas, %2$d salteadas */
						__( 'Import done: %1$d rows imported, %2$d skipped.', 'lingua-visual' ),
						$import_result['imported'],
						$import_result['skipped']
					)
				) . '</p></div>';
			foreach ( $import_result['errors'] as $err ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $err ) . '</p></div>';
			}
		}
		?>
		<h2><?php esc_html_e( 'Export', 'lingua-visual' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lgv_export_csv">
			<?php wp_nonce_field( 'lgv_export', 'lgv_nonce' ); ?>
			<select name="lang">
				<option value=""><?php esc_html_e( 'All languages', 'lingua-visual' ); ?></option>
				<?php foreach ( $langs as $code => $data ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( LGV_Languages::label( $code ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Download CSV', 'lingua-visual' ), 'secondary', '', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Import', 'lingua-visual' ); ?></h2>
		<p><?php esc_html_e( 'The CSV needs the columns: lang, original, translation (context is optional). Languages must be registered first.', 'lingua-visual' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="lgv_import_csv">
			<?php wp_nonce_field( 'lgv_import', 'lgv_nonce' ); ?>
			<input type="file" name="lgv_csv" accept=".csv" required>
			<?php submit_button( __( 'Import CSV', 'lingua-visual' ), 'primary', '', false ); ?>
		</form>

		<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin-top:24px;max-width:800px">
		<h2 style="margin-top:0"><?php esc_html_e( 'XLIFF (professional translators)', 'lingua-visual' ); ?></h2>
		<p style="color:#646970"><?php esc_html_e( 'Export translation jobs as XLIFF 1.2 for CAT tools (memoQ, Trados, OmegaT), then re-import the translated file.', 'lingua-visual' ); ?></p>
		<?php
		$xliff_result = get_transient( 'lgv_xliff_result' );
		if ( $xliff_result ) {
			delete_transient( 'lgv_xliff_result' );
			if ( is_wp_error( $xliff_result ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $xliff_result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html(
						sprintf(
							/* translators: 1: imported, 2: skipped, 3: language */
							__( 'XLIFF imported: %1$d translated, %2$d skipped (%3$s).', 'lingua-visual' ),
							$xliff_result['imported'],
							$xliff_result['skipped'],
							$xliff_result['target_lang']
						)
					) . '</p></div>';
			}
		}
		?>
		<h3><?php esc_html_e( 'Export XLIFF', 'lingua-visual' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lgv_export_xliff">
			<?php wp_nonce_field( 'lgv_xliff_export', 'lgv_nonce' ); ?>
			<label><?php esc_html_e( 'Target language', 'lingua-visual' ); ?>:
				<select name="target_lang" required>
					<?php foreach ( $langs as $code => $data ) : ?>
						<?php if ( $code === LGV_Languages::get_default() ) continue; ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( LGV_Languages::label( $code ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><input type="checkbox" name="only_untranslated" value="1" checked> <?php esc_html_e( 'Only untranslated', 'lingua-visual' ); ?></label>
			<?php submit_button( __( 'Download XLIFF', 'lingua-visual' ), 'secondary', '', false ); ?>
		</form>

		<h3><?php esc_html_e( 'Import XLIFF', 'lingua-visual' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="lgv_import_xliff">
			<?php wp_nonce_field( 'lgv_xliff_import', 'lgv_nonce' ); ?>
			<input type="file" name="lgv_xliff" accept=".xliff,.xlf,.xml" required>
			<?php submit_button( __( 'Import XLIFF', 'lingua-visual' ), 'primary', '', false ); ?>
		</form>
		</div>
		<?php
	}

	/**
	 * admin-post: exporta el CSV.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lgv_export', 'lgv_nonce' ) ) {
			wp_die( esc_html__( 'No permission.', 'lingua-visual' ) );
		}
		$lang = isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : '';
		LGV_Strings::export_csv( $lang );
	}

	/**
	 * admin-post: importa el CSV.
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lgv_import', 'lgv_nonce' ) ) {
			wp_die( esc_html__( 'No permission.', 'lingua-visual' ) );
		}
		$result = array( 'imported' => 0, 'skipped' => 0, 'errors' => array( __( 'No file was received.', 'lingua-visual' ) ) );
		if ( isset( $_FILES['lgv_csv'] ) && is_array( $_FILES['lgv_csv'] ) && UPLOAD_ERR_OK === $_FILES['lgv_csv']['error'] ) {
			$result = LGV_Strings::import_csv( $_FILES['lgv_csv']['tmp_name'] );
		}
		set_transient( 'lgv_import_result', $result, 60 );
		wp_safe_redirect( add_query_arg( 'tab', 'tools', menu_page_url( 'lingua-visual', false ) ) );
		exit;
	}

	/**
	 * admin-post: export XLIFF.
	 */
	public static function handle_xliff_export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lgv_xliff_export', 'lgv_nonce' ) ) {
			wp_die( esc_html__( 'No permission.', 'lingua-visual' ) );
		}
		$target = isset( $_POST['target_lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['target_lang'] ) ) : '';
		if ( '' === $target || ! LGV_Languages::exists( $target ) ) {
			wp_die( esc_html__( 'Invalid target language.', 'lingua-visual' ) );
		}
		$source  = LGV_Languages::get_default();
		$filters = array(
			'only_untranslated' => ! empty( $_POST['only_untranslated'] ),
		);
		$xml = LGV_XLIFF::export( $source, $target, $filters );

		nocache_headers();
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lingua-visual-' . $target . '-' . gmdate( 'Ymd-His' ) . '.xliff"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
		exit;
	}

	/**
	 * admin-post: import XLIFF.
	 */
	public static function handle_xliff_import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lgv_xliff_import', 'lgv_nonce' ) ) {
			wp_die( esc_html__( 'No permission.', 'lingua-visual' ) );
		}
		$result = new WP_Error( 'lgv_xliff_no_file', __( 'No file was received.', 'lingua-visual' ) );
		if ( isset( $_FILES['lgv_xliff'] ) && is_array( $_FILES['lgv_xliff'] ) && UPLOAD_ERR_OK === $_FILES['lgv_xliff']['error'] ) {
			$content = file_get_contents( $_FILES['lgv_xliff']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false !== $content ) {
				$result = LGV_XLIFF::import( $content );
			}
		}
		set_transient( 'lgv_xliff_result', $result, 60 );
		wp_safe_redirect( add_query_arg( 'tab', 'tools', menu_page_url( 'lingua-visual', false ) ) );
		exit;
	}

	/**
	 * admin-post: save media translations.
	 */
	public static function handle_media_save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lgv_media', 'lgv_nonce' ) ) {
			wp_die( esc_html__( 'No permission.', 'lingua-visual' ) );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		if ( $attachment_id > 0 && isset( $_POST['lgv_media'] ) && is_array( $_POST['lgv_media'] ) ) {
			foreach ( $_POST['lgv_media'] as $lang => $fields ) {
				$lang = lgv_sanitize_lang_code( $lang );
				if ( ! LGV_Languages::exists( $lang ) || ! is_array( $fields ) ) {
					continue;
				}
				foreach ( $fields as $field => $text ) {
					if ( ! in_array( $field, LGV_Media::FIELDS, true ) ) {
						continue;
					}
					LGV_Media::save( $attachment_id, $field, $lang, sanitize_textarea_field( wp_unslash( $text ) ) );
				}
			}
			set_transient( 'lgv_media_saved', 1, 30 );
		}
		wp_safe_redirect( add_query_arg( 'tab', 'media', menu_page_url( 'lingua-visual', false ) ) );
		exit;
	}

	public static function handle_media_file_save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lgv_media', 'lgv_nonce' ) ) {
			wp_die( esc_html__( 'No permission.', 'lingua-visual' ) );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		if ( $attachment_id > 0 && isset( $_POST['lgv_media_file'] ) && is_array( $_POST['lgv_media_file'] ) ) {
			foreach ( $_POST['lgv_media_file'] as $lang => $rep_id ) {
				$lang = lgv_sanitize_lang_code( $lang );
				if ( ! LGV_Languages::exists( $lang ) ) {
					continue;
				}
				LGV_Media::save_file( $attachment_id, $lang, (int) $rep_id );
			}
			set_transient( 'lgv_media_saved', 1, 30 );
		}
		wp_safe_redirect( add_query_arg( array( 'tab' => 'media', 'edit_media' => $attachment_id ), menu_page_url( 'lingua-visual', false ) ) );
		exit;
	}

	/**
	 * Pestaña: Switcher Studio — personalización del selector de idioma.
	 */
	private static function tab_switcher() {
		$s       = LGV_Switcher::get();
		$langs   = LGV_Languages::all();
		$default = LGV_Languages::get_default();

		$modes = array(
			'dropdown'   => array( __( 'Dropdown', 'lingua-visual' ), __( 'Button with the current language and a dropdown list.', 'lingua-visual' ) ),
			'inline'     => array( __( 'Horizontal list', 'lingua-visual' ), __( 'All languages visible inline.', 'lingua-visual' ) ),
			'flags'      => array( __( 'Flags only', 'lingua-visual' ), __( 'Only the flags, no text.', 'lingua-visual' ) ),
			'text'       => array( __( 'Text only', 'lingua-visual' ), __( 'Only the names, no flags.', 'lingua-visual' ) ),
			'flag_text'  => array( __( 'Flag + text', 'lingua-visual' ), __( 'List with flag and name per language.', 'lingua-visual' ) ),
			'opposite'   => array( __( 'Opposite language', 'lingua-visual' ), __( 'A button that goes straight to the other language (only with 2 languages).', 'lingua-visual' ) ),
			'pill_modal' => array( __( 'Pill + dialog', 'lingua-visual' ), __( 'Globe + "Language" opening a selector (Airbnb style).', 'lingua-visual' ) ),
		);
		?>
		<h2><?php esc_html_e( 'Language switcher', 'lingua-visual' ); ?></h2>
		<p><?php esc_html_e( 'Customize how the language switcher looks and behaves. Everything you change is previewed instantly above.', 'lingua-visual' ); ?></p>

		<h3><?php esc_html_e( 'Live preview', 'lingua-visual' ); ?></h3>
		<p>
			<button type="button" class="button button-primary lgv-device" data-width="100%"><?php esc_html_e( 'Desktop', 'lingua-visual' ); ?></button>
			<button type="button" class="button lgv-device" data-width="430px"><?php esc_html_e( 'Mobile', 'lingua-visual' ); ?></button>
		</p>
		<div id="lgv-preview-stage" class="lgv-preview-stage" style="max-width:100%">
			<div id="lgv-sw-live"><p><?php esc_html_e( 'Loading preview…', 'lingua-visual' ); ?></p></div>
		</div>

		<form method="post" id="lgv-switcher-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'lgv_switcher', 'lgv_nonce' ); ?>

			<h3><?php esc_html_e( 'Display mode', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px"><tr><td>
				<?php foreach ( $modes as $key => $info ) : ?>
					<label style="display:block;margin-bottom:10px">
						<input type="radio" name="sw[mode]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['mode'], $key ); ?>>
						<strong><?php echo esc_html( $info[0] ); ?></strong>
						<span class="description"><?php echo esc_html( $info[1] ); ?></span>
					</label>
				<?php endforeach; ?>
			</td></tr></table>

			<h3><?php esc_html_e( 'Flags', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px">
				<tr>
					<th><label for="lgv-flag-shape"><?php esc_html_e( 'Shape', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-flag-shape" name="sw[flag_shape]">
							<?php
							foreach ( array( 'rect' => __( 'Rectangle', 'lingua-visual' ), 'rounded' => __( 'Rounded', 'lingua-visual' ), 'circle' => __( 'Circle', 'lingua-visual' ) ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['flag_shape'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="lgv-flag-size"><?php esc_html_e( 'Size (px)', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-flag-size" type="number" name="sw[flag_size]" value="<?php echo (int) $s['flag_size']; ?>" min="12" max="48" style="width:80px"></td>
				</tr>
				<tr>
					<th><label for="lgv-flag-pos"><?php esc_html_e( 'Flag position', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-flag-pos" name="sw[flag_position]">
							<?php
							foreach ( array( 'before' => __( 'Before the text', 'lingua-visual' ), 'after' => __( 'After the text', 'lingua-visual' ), 'hidden' => __( 'Hidden', 'lingua-visual' ) ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['flag_position'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
			</table>

			<h4><?php esc_html_e( 'Flag and label per language', 'lingua-visual' ); ?></h4>
			<p class="description" style="max-width:760px"><?php esc_html_e( 'Choose which flag represents each language (e.g. UK or USA for English), upload your own SVG or write a custom label.', 'lingua-visual' ); ?></p>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr>
					<th><?php esc_html_e( 'Language', 'lingua-visual' ); ?></th>
					<th><?php esc_html_e( 'Current', 'lingua-visual' ); ?></th>
					<th><?php esc_html_e( 'Variant', 'lingua-visual' ); ?></th>
					<th><?php esc_html_e( 'Own SVG', 'lingua-visual' ); ?></th>
					<th><?php esc_html_e( 'Custom label', 'lingua-visual' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				$all_variants = LGV_Switcher::flag_variants();
				foreach ( $langs as $code => $data ) :
					$opts     = isset( $all_variants[ $code ] ) ? $all_variants[ $code ] : array();
					$cur_var  = isset( $data['flag_variant'] ) ? $data['flag_variant'] : '';
					$has_svg  = ! empty( $data['flag_svg'] );
					$lbl      = isset( $data['label_custom'] ) ? $data['label_custom'] : '';
					?>
					<tr>
						<td><strong><?php echo esc_html( LGV_Switcher::native_name( $code ) ); ?></strong><br><code><?php echo esc_html( $code ); ?></code></td>
						<td style="font-size:22px"><?php echo LGV_Switcher::flag_html( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td>
							<select name="sw_variant[<?php echo esc_attr( $code ); ?>]">
								<option value="auto"><?php esc_html_e( 'Automatic', 'lingua-visual' ); ?></option>
								<?php foreach ( $opts as $cc => $clab ) : ?>
									<option value="<?php echo esc_attr( $cc ); ?>" <?php selected( $cur_var, $cc ); ?>><?php echo esc_html( $clab ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( $has_svg ) : ?>
								<span class="description"><?php esc_html_e( 'Custom SVG active.', 'lingua-visual' ); ?></span><br>
								<label><input type="checkbox" name="sw_svg_remove[<?php echo esc_attr( $code ); ?>]" value="1"> <?php esc_html_e( 'Remove', 'lingua-visual' ); ?></label><br>
							<?php endif; ?>
							<input type="file" name="sw_svg[<?php echo esc_attr( $code ); ?>]" accept=".svg">
						</td>
						<td><input type="text" name="sw_label[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( $lbl ); ?>" placeholder="<?php echo esc_attr( LGV_Switcher::native_name( $code ) ); ?>" class="regular-text"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Text', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px">
				<tr>
					<th><label for="lgv-name-format"><?php esc_html_e( 'Which text to show', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-name-format" name="sw[name_format]">
							<?php
							foreach ( array( 'native' => __( 'Native name (Español, English)', 'lingua-visual' ), 'iso' => __( 'ISO code (ES, EN)', 'lingua-visual' ), 'none' => __( 'Nothing (flag only)', 'lingua-visual' ) ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['name_format'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'The "Custom label" in the table above takes priority over this format.', 'lingua-visual' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Tooltip', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="checkbox" name="sw[show_tooltip]" value="1" <?php checked( ! empty( $s['show_tooltip'] ) ); ?>> <?php esc_html_e( 'Show the language name on hover (tooltip)', 'lingua-visual' ); ?></label>
						<p class="description"><?php esc_html_e( 'The text is shown in the site\'s active language: if the site is in English, the Spanish flag reads "Spanish".', 'lingua-visual' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Behavior', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px">
				<tr>
					<th><?php esc_html_e( 'Dropdown opening', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="radio" name="sw[trigger]" value="click" <?php checked( $s['trigger'], 'click' ); ?>> <?php esc_html_e( 'On click', 'lingua-visual' ); ?></label><br>
						<label><input type="radio" name="sw[trigger]" value="hover" <?php checked( $s['trigger'], 'hover' ); ?>> <?php esc_html_e( 'On hover', 'lingua-visual' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Opening direction', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="radio" name="sw[open_direction]" value="down" <?php checked( $s['open_direction'], 'down' ); ?>> <?php esc_html_e( 'Down', 'lingua-visual' ); ?></label><br>
						<label><input type="radio" name="sw[open_direction]" value="up" <?php checked( $s['open_direction'], 'up' ); ?>> <?php esc_html_e( 'Up', 'lingua-visual' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="lgv-anim"><?php esc_html_e( 'Animation', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-anim" name="sw[animation]">
							<?php
							foreach ( array( 'fade' => __( 'Fade', 'lingua-visual' ), 'slide' => __( 'Slide', 'lingua-visual' ), 'none' => __( 'No animation', 'lingua-visual' ) ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['animation'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Visibility', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="checkbox" name="sw[show_current]" value="1" <?php checked( ! empty( $s['show_current'] ) ); ?>> <?php esc_html_e( 'Show the current language inside the list', 'lingua-visual' ); ?></label><br>
						<label><input type="checkbox" name="sw[hide_current]" value="1" <?php checked( ! empty( $s['hide_current'] ) ); ?>> <?php esc_html_e( 'Hide the current language from the list (only the others)', 'lingua-visual' ); ?></label><br>
						<label><input type="checkbox" name="sw[show_default]" value="1" <?php checked( ! empty( $s['show_default'] ) ); ?>> <?php esc_html_e( 'Show the default language in the switcher', 'lingua-visual' ); ?></label><br>
						<label><input type="checkbox" name="sw[hide_empty]" value="1" <?php checked( ! empty( $s['hide_empty'] ) ); ?>> <?php esc_html_e( 'Hide languages with no translations loaded', 'lingua-visual' ); ?></label><br>
						<label><input type="checkbox" name="sw[close_outside]" value="1" <?php checked( ! empty( $s['close_outside'] ) ); ?>> <?php esc_html_e( 'Close when clicking outside', 'lingua-visual' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Language order', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="radio" name="sw[order_mode]" value="manual" <?php checked( $s['order_mode'], 'manual' ); ?>> <?php esc_html_e( 'Manual (drag)', 'lingua-visual' ); ?></label><br>
						<label><input type="radio" name="sw[order_mode]" value="alpha" <?php checked( $s['order_mode'], 'alpha' ); ?>> <?php esc_html_e( 'Alphabetical by native name', 'lingua-visual' ); ?></label>
						<ul id="lgv-lang-order" style="max-width:320px;margin-top:8px">
							<?php
							$ordered = LGV_Switcher::ordered_langs();
							foreach ( $ordered as $code ) :
								?>
								<li class="ui-state-default" style="padding:8px 10px;margin-bottom:4px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;cursor:move">
									<span class="lgv-drag" style="cursor:move;margin-right:8px" title="<?php esc_attr_e( 'Drag', 'lingua-visual' ); ?>">⋮⋮</span>
									<?php echo LGV_Switcher::flag_html( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php echo esc_html( LGV_Switcher::native_name( $code ) ); ?>
									<?php if ( $code === $default ) : ?><span class="description">(default)</span><?php endif; ?>
									<input type="hidden" name="sw[lang_order][]" value="<?php echo esc_attr( $code ); ?>">
								</li>
							<?php endforeach; ?>
						</ul>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Search box', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="checkbox" name="sw[search]" value="1" <?php checked( ! empty( $s['search'] ) ); ?>> <?php esc_html_e( 'Show a search box inside the dropdown/dialog', 'lingua-visual' ); ?></label><br>
						<label><?php esc_html_e( 'From how many languages:', 'lingua-visual' ); ?>
						<input type="number" name="sw[search_threshold]" value="<?php echo (int) $s['search_threshold']; ?>" min="2" max="50" style="width:70px"></label>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Style', 'lingua-visual' ); ?></h3>
			<h4><?php esc_html_e( 'One-click presets', 'lingua-visual' ); ?></h4>
			<p>
				<?php foreach ( LGV_Switcher::presets() as $slug => $p ) : ?>
					<button type="button" class="button lgv-preset" data-preset='<?php echo esc_attr( wp_json_encode( $p['settings'] ) ); ?>'><?php echo esc_html( $p['label'] ); ?></button>
				<?php endforeach; ?>
			</p>
			<table class="form-table" style="max-width:760px">
				<?php
				$colors = array(
					'bg'         => __( 'Background', 'lingua-visual' ),
					'bg_hover'   => __( 'Background (hover)', 'lingua-visual' ),
					'text'       => __( 'Text', 'lingua-visual' ),
					'text_hover' => __( 'Text (hover)', 'lingua-visual' ),
					'border'     => __( 'Border', 'lingua-visual' ),
				);
				foreach ( $colors as $key => $lab ) :
					?>
					<tr>
						<th><label><?php echo esc_html( $lab ); ?></label></th>
						<td><input type="text" class="lgv-color" name="sw[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th><label for="lgv-bw"><?php esc_html_e( 'Border width (px)', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-bw" type="number" name="sw[border_width]" value="<?php echo (int) $s['border_width']; ?>" min="0" max="8" style="width:80px"></td>
				</tr>
				<tr>
					<th><label for="lgv-br"><?php esc_html_e( 'Border radius (px)', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-br" type="number" name="sw[border_radius]" value="<?php echo (int) $s['border_radius']; ?>" min="0" max="40" style="width:80px"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Padding (px)', 'lingua-visual' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Vertical', 'lingua-visual' ); ?> <input type="number" name="sw[padding_v]" value="<?php echo (int) $s['padding_v']; ?>" min="0" max="32" style="width:70px"></label>
						<label style="margin-left:12px"><?php esc_html_e( 'Horizontal', 'lingua-visual' ); ?> <input type="number" name="sw[padding_h]" value="<?php echo (int) $s['padding_h']; ?>" min="0" max="48" style="width:70px"></label>
					</td>
				</tr>
				<tr>
					<th><label for="lgv-fs"><?php esc_html_e( 'Font size (px)', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-fs" type="number" name="sw[font_size]" value="<?php echo (int) $s['font_size']; ?>" min="10" max="28" style="width:80px"></td>
				</tr>
				<tr>
					<th><label for="lgv-fw"><?php esc_html_e( 'Font weight', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-fw" name="sw[font_weight]">
							<?php
							foreach ( array( '400' => 'Normal (400)', '500' => 'Medium (500)', '600' => 'Semibold (600)', '700' => 'Bold (700)' ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['font_weight'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="lgv-ff"><?php esc_html_e( 'Typography', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-ff" name="sw[font_family]">
							<?php
							foreach ( array( 'system' => __( 'System', 'lingua-visual' ), 'serif' => __( 'Serif', 'lingua-visual' ), 'mono' => __( 'Monospaced', 'lingua-visual' ), 'inherit' => __( 'Inherit from theme', 'lingua-visual' ) ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['font_family'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Shadow', 'lingua-visual' ); ?></th>
					<td><label><input type="checkbox" name="sw[shadow]" value="1" <?php checked( ! empty( $s['shadow'] ) ); ?>> <?php esc_html_e( 'Soft shadow (elevation)', 'lingua-visual' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="lgv-css"><?php esc_html_e( 'Custom CSS', 'lingua-visual' ); ?></label></th>
					<td><textarea id="lgv-css" name="sw[custom_css]" rows="6" cols="60" class="large-text code" placeholder=".lgv-sw-toggle { ... }"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'It\'s printed after the generated styles. You can use the --lgv-bg, --lgv-text, etc. variables.', 'lingua-visual' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Performance', 'lingua-visual' ); ?></th>
					<td><label><input type="checkbox" name="sw[disable_assets]" value="1" <?php checked( ! empty( $s['disable_assets'] ) ); ?>> <?php esc_html_e( 'Don\'t load the switcher CSS/JS (to style it 100% by hand)', 'lingua-visual' ); ?></label></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Mobile', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px">
				<tr>
					<th><?php esc_html_e( 'Enable mobile settings', 'lingua-visual' ); ?></th>
					<td><label><input type="checkbox" name="sw[mobile_enabled]" value="1" <?php checked( ! empty( $s['mobile_enabled'] ) ); ?>> <?php esc_html_e( 'Yes', 'lingua-visual' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="lgv-bp"><?php esc_html_e( 'Breakpoint (px)', 'lingua-visual' ); ?></label></th>
					<td><input id="lgv-bp" type="number" name="sw[mobile_breakpoint]" value="<?php echo (int) $s['mobile_breakpoint']; ?>" min="320" max="1200" style="width:80px">
						<p class="description"><?php esc_html_e( 'Below this width the mobile settings apply.', 'lingua-visual' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Mobile mode', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="radio" name="sw[mobile_mode]" value="same" <?php checked( $s['mobile_mode'], 'same' ); ?>> <?php esc_html_e( 'Same as desktop', 'lingua-visual' ); ?></label><br>
						<label><input type="radio" name="sw[mobile_mode]" value="flags" <?php checked( $s['mobile_mode'], 'flags' ); ?>> <?php esc_html_e( 'Flags only (compact)', 'lingua-visual' ); ?></label><br>
						<label><input type="radio" name="sw[mobile_mode]" value="drawer" <?php checked( $s['mobile_mode'], 'drawer' ); ?>> <?php esc_html_e( 'Full-screen drawer', 'lingua-visual' ); ?></label>
						<p class="description"><?php esc_html_e( 'The drawer turns the dropdown and the dialog into a full-screen panel, ideal with many languages.', 'lingua-visual' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Location', 'lingua-visual' ); ?></h3>
			<table class="form-table" style="max-width:760px">
				<tr>
					<th><label for="lgv-menu-loc"><?php esc_html_e( 'Menu item', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-menu-loc" name="sw[menu_location]">
							<option value=""><?php esc_html_e( '— Don\'t add —', 'lingua-visual' ); ?></option>
							<?php
							foreach ( (array) get_registered_nav_menus() as $loc => $desc ) {
								echo '<option value="' . esc_attr( $loc ) . '" ' . selected( $s['menu_location'], $loc, false ) . '>' . esc_html( $desc ) . ' (' . esc_html( $loc ) . ')</option>';
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Adds the switcher as the last item of the chosen menu.', 'lingua-visual' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="lgv-menu-mode"><?php esc_html_e( 'Menu mode', 'lingua-visual' ); ?></label></th>
					<td>
						<select id="lgv-menu-mode" name="sw[menu_mode]">
							<?php
							foreach ( array( 'flag_text' => __( 'Flag + text', 'lingua-visual' ), 'flags' => __( 'Flags only', 'lingua-visual' ), 'text' => __( 'Text only', 'lingua-visual' ), 'dropdown' => __( 'Dropdown', 'lingua-visual' ), 'inline' => __( 'Horizontal list', 'lingua-visual' ) ) as $k => $l ) {
								echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['menu_mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Floating button', 'lingua-visual' ); ?></th>
					<td>
						<label><input type="checkbox" name="sw[floating]" value="1" <?php checked( ! empty( $s['floating'] ) ); ?>> <?php esc_html_e( 'Show a floating button fixed on screen', 'lingua-visual' ); ?></label><br>
						<label><?php esc_html_e( 'Position:', 'lingua-visual' ); ?>
							<select name="sw[floating_position]">
								<?php
								foreach ( array( 'bottom-right' => __( 'Bottom right', 'lingua-visual' ), 'bottom-left' => __( 'Bottom left', 'lingua-visual' ), 'top-right' => __( 'Top right', 'lingua-visual' ), 'top-left' => __( 'Top left', 'lingua-visual' ) ) as $k => $l ) {
									echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['floating_position'], $k, false ) . '>' . esc_html( $l ) . '</option>';
								}
								?>
							</select></label>
						<label style="margin-left:12px"><?php esc_html_e( 'Mode:', 'lingua-visual' ); ?>
							<select name="sw[floating_mode]">
								<?php
								foreach ( array( 'dropdown' => __( 'Dropdown', 'lingua-visual' ), 'pill_modal' => __( 'Pill + dialog', 'lingua-visual' ), 'flags' => __( 'Flags only', 'lingua-visual' ), 'inline' => __( 'Horizontal list', 'lingua-visual' ) ) as $k => $l ) {
									echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['floating_mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
								}
								?>
							</select></label>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Accessibility', 'lingua-visual' ); ?></h3>
			<ul class="ul-disc" style="list-style:disc;margin-left:20px;max-width:760px">
				<li><?php esc_html_e( 'The dropdown uses ARIA listbox/option with aria-expanded and can be operated with Tab, arrows, Enter and Escape.', 'lingua-visual' ); ?></li>
				<li><?php esc_html_e( 'The modal dialog is a dialog with aria-modal, managed focus and closing with Escape or backdrop click.', 'lingua-visual' ); ?></li>
				<li><?php esc_html_e( 'Flags are decorative (aria-hidden); each option carries hreflang and the current language is marked with aria-current.', 'lingua-visual' ); ?></li>
				<li><?php esc_html_e( 'Tip: flags represent countries, not languages. If your audience is broad, prefer showing the language name too.', 'lingua-visual' ); ?></li>
			</ul>

			<?php submit_button( __( 'Save switcher settings', 'lingua-visual' ), 'primary', 'lgv_save_switcher' ); ?>
		</form>
		<?php
	}

}
