<?php
/**
 * Plugin Name:       Lingua Visual
 * Plugin URI:        https://vanflab.com/plugins/lingua-visual
 * Description:       Visual multilingual translation with UNLIMITED languages, free. Click any frontend text and translate it on the spot. Own manual dictionary, no paid APIs. Per-language URLs (/en/page/) that persist while browsing and play nice with caching. Automatic language detection via IP geolocation + browser language. Frontend string discovery + automatic translation (free MyMemory, DeepL).
 * Version:           0.7.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Vanf Lab
 * Author URI:        https://vanflab.com
 * License:           GPL-2.0-or-later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lingua-visual
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Buffer output during OUR AJAX requests from the very start, so PHP warnings
// from core/other plugins can't corrupt our JSON responses.
if ( isset( $_SERVER['REQUEST_URI'], $_REQUEST['action'] )
	&& false !== strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' )
	&& 0 === strpos( (string) $_REQUEST['action'], 'lgv_' ) ) {
	ob_start();
}

define( 'LGV_VERSION', '0.7.0' );
define( 'LGV_PLUGIN_FILE', __FILE__ );
define( 'LGV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LGV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LGV_DB_VERSION', '2' );

require_once LGV_PLUGIN_DIR . 'includes/class-lgv-db.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-languages.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-routing.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-strings.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-frontend.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-switcher.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-lang-names.php';
require_once LGV_PLUGIN_DIR . 'includes/data-lgv-country-langs.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-detect.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-discover.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-autotranslate.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-visual.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-xliff.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-media.php';
require_once LGV_PLUGIN_DIR . 'includes/class-lgv-admin.php';

register_activation_hook( __FILE__, 'lgv_activate' );
/**
 * Activación: crea las tablas propias, siembra el idioma default del sitio
 * y regenera los rewrite rules.
 */
function lgv_activate() {
	LGV_DB::create_tables();
	LGV_Languages::seed_default();
	flush_rewrite_rules();
	update_option( 'lgv_version', LGV_VERSION, false );
}

add_action( 'plugins_loaded', 'lgv_maybe_upgrade', 1 );
/**
 * Al actualizar el plugin (el hook de activación no corre en updates),
 * regenera los rewrite rules y aplica migraciones de DB una vez por versión.
 */
function lgv_maybe_upgrade() {
	if ( get_option( 'lgv_version', '' ) !== LGV_VERSION ) {
		LGV_DB::create_tables(); // dbDelta es idempotente: agrega columnas nuevas.
		flush_rewrite_rules();
		update_option( 'lgv_version', LGV_VERSION, false );
	}
}

/**
 * Código de idioma activo en esta request.
 *
 * Prioridad: prefijo en la URL (/en/...) → ?lang=xx válido →
 * cookie lingua_lang → idioma default.
 *
 * @return string Código de idioma (ej. 'en').
 */
function lgv_current_lang() {
	static $lang = null;
	if ( null !== $lang ) {
		return $lang;
	}
	$lang = LGV_Routing::detect_request_lang();
	return $lang;
}

/**
 * Sanea un código de idioma: minúsculas, letras/números/guion, máx 20 chars.
 *
 * @param string $code Código crudo.
 * @return string Código saneado.
 */
function lgv_sanitize_lang_code( $code ) {
	$code = strtolower( trim( (string) $code ) );
	$code = preg_replace( '/[^a-z0-9-]/', '', $code );
	return substr( $code, 0, 20 );
}

/**
 * Agrega el prefijo de idioma a una URL interna (idempotente).
 * El idioma default y las URLs externas o del sistema quedan intactas.
 *
 * @param string $url  URL.
 * @param string $lang Código de idioma (default: el activo en la request).
 * @return string URL con prefijo.
 */
function lgv_add_lang_prefix( $url, $lang = '' ) {
	return LGV_Routing::add_prefix( $url, $lang );
}

/**
 * Quita cualquier prefijo de idioma de una URL interna.
 *
 * @param string $url URL.
 * @return string URL sin prefijo.
 */
function lgv_remove_lang_prefix( $url ) {
	return LGV_Routing::remove_prefix( $url );
}

/**
 * Devuelve la URL de una página en otro idioma (misma página, otro prefijo).
 * El idioma default no lleva prefijo.
 *
 * @param string $url  URL de la página actual.
 * @param string $code Código del idioma destino.
 * @return string URL con el prefijo del idioma destino.
 */
function lgv_url_for_lang( $url, $code ) {
	$code = lgv_sanitize_lang_code( $code );
	if ( ! LGV_Languages::exists( $code ) ) {
		return $url;
	}
	$url        = remove_query_arg( 'lang', $url );
	$new_prefix = ( $code === LGV_Languages::get_default() ) ? '' : $code;
	return LGV_Routing::swap_prefix( $url, $new_prefix );
}

/**
 * URL completa de la página actual (con prefijo si lo hay).
 *
 * @return string URL absoluta.
 */
function lgv_current_page_url() {
	$scheme = is_ssl() ? 'https://' : 'http://';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
	if ( '' === $host ) {
		$host = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	return $scheme . $host . $uri;
}

add_action( 'plugins_loaded', 'lgv_boot' );
/**
 * Arranque del plugin: instanciá los módulos según el contexto.
 */
function lgv_boot() {
	LGV_Routing::init();
	LGV_Frontend::init();
	LGV_Detect::init();
	LGV_Visual::init();
	LGV_AutoTranslate::init();
	LGV_Media::init_frontend();
	if ( is_admin() ) {
		LGV_Admin::init();
	}
}
