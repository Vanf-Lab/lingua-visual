<?php
/**
 * Ruteo por prefijo de idioma en la URL (/en/mi-pagina/).
 *
 * Cómo funciona:
 * - En el filtro 'do_parse_request' (lo primero que corre en WP::parse_request,
 *   antes de que WordPress lea REQUEST_URI) se detecta si el primer segmento
 *   del path es un idioma registrado. Si lo es, se quita de REQUEST_URI para
 *   que WordPress resuelva el resto con sus reglas de rewrite normales.
 *   No se agregan reglas propias: funciona con cualquier estructura de
 *   permalinks y no necesita flush.
 * - En el hook 'wp' se restaura la URI original para que redirect_canonical
 *   y demás comparen contra la URL real (con prefijo).
 * - Cada idioma queda en una URL distinta: los cachés de página (WP Rocket
 *   y cía.) guardan una entrada por idioma y la traducción persiste al navegar.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Routing {

	const SETTINGS_OPTION = 'lgv_url_settings';

	/**
	 * Idioma detectado en el prefijo de la URL en esta request (null si no hay).
	 *
	 * @var string|null
	 */
	public static $url_lang = null;

	/**
	 * REQUEST_URI original (con prefijo), cuando se quitó para el parseo.
	 *
	 * @var string|null
	 */
	public static $original_uri = null;

	/**
	 * Registra los hooks.
	 */
	public static function init() {
		add_filter( 'do_parse_request', array( __CLASS__, 'strip_lang_prefix' ), 1, 3 );
		add_action( 'wp', array( __CLASS__, 'restore_request_uri' ), 1 );
	}

	/**
	 * Ajustes de URLs con sus defaults.
	 *
	 * @return array [ 'url_prefixes' => 1, 'redirect_legacy' => 1 ]
	 */
	public static function settings() {
		$defaults = array(
			'url_prefixes'    => 1, // URLs tipo /en/mi-pagina/.
			'redirect_legacy' => 1, // ?lang= / cookie → 302 a la URL con prefijo.
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * Guarda los ajustes de URLs.
	 *
	 * @param array $settings Claves url_prefixes y redirect_legacy.
	 */
	public static function save_settings( $settings ) {
		$clean = array(
			'url_prefixes'    => ! empty( $settings['url_prefixes'] ) ? 1 : 0,
			'redirect_legacy' => ! empty( $settings['redirect_legacy'] ) ? 1 : 0,
		);
		update_option( self::SETTINGS_OPTION, $clean );
	}

	/**
	 * ¿Están activos los prefijos de idioma en las URLs?
	 *
	 * @return bool
	 */
	public static function prefixes_enabled() {
		$s = self::settings();
		return ! empty( $s['url_prefixes'] );
	}

	/**
	 * ¿Se redirige ?lang= / cookie a la URL con prefijo?
	 *
	 * @return bool
	 */
	public static function legacy_redirect_enabled() {
		$s = self::settings();
		return ! empty( $s['redirect_legacy'] );
	}

	/**
	 * Base del home para instalaciones en subdirectorio ('' o '/blog').
	 *
	 * Usa get_option('home') directo (sin filtros) para no reentrar en
	 * nuestro propio filtro de home_url.
	 *
	 * @return string
	 */
	public static function home_base_path() {
		$path = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );
		return '/' === $path ? '' : $path;
	}

	/**
	 * Detecta un prefijo de idioma al inicio de una URI.
	 *
	 * @param string $uri REQUEST_URI cruda (con query string opcional).
	 * @return array [ 'lang' => string|null, 'rest_path' => string, 'stripped_uri' => string ]
	 */
	public static function parse_lang_prefix( $uri ) {
		$result = array(
			'lang'         => null,
			'rest_path'    => '',
			'stripped_uri' => $uri,
		);
		$parts = explode( '?', $uri, 2 );
		$path  = $parts[0];
		$query = isset( $parts[1] ) ? '?' . $parts[1] : '';

		$base = self::home_base_path();
		$rel  = $path;
		if ( '' !== $base ) {
			if ( 0 !== strpos( strtolower( $rel ) . '/', strtolower( $base ) . '/' ) ) {
				return $result;
			}
			$rel = substr( $rel, strlen( $base ) );
		}
		$segments = explode( '/', trim( $rel, '/' ) );
		$first    = isset( $segments[0] ) ? lgv_sanitize_lang_code( $segments[0] ) : '';
		if ( '' === $first || ! LGV_Languages::exists( $first ) ) {
			return $result;
		}
		array_shift( $segments );
		$rest = '/' . implode( '/', $segments );

		$result['lang']         = $first;
		$result['rest_path']    = $rest;
		$result['stripped_uri'] = $base . $rest . $query;
		return $result;
	}

	/**
	 * Filtro 'do_parse_request': quita el prefijo de idioma de REQUEST_URI
	 * antes de que WordPress parsee la request.
	 *
	 * @param bool $do_parse         Si se debe parsear (se devuelve intacto).
	 * @param WP   $wp               Instancia de WP.
	 * @param mixed $extra_query_vars Vars extra.
	 * @return bool
	 */
	public static function strip_lang_prefix( $do_parse, $wp, $extra_query_vars ) {
		if ( ! self::prefixes_enabled() ) {
			return $do_parse;
		}
		if ( ( defined( 'WP_ADMIN' ) && WP_ADMIN ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $do_parse;
		}
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $do_parse;
		}
		$parsed = self::parse_lang_prefix( $_SERVER['REQUEST_URI'] );
		if ( null === $parsed['lang'] ) {
			return $do_parse;
		}
		self::$url_lang     = $parsed['lang'];
		self::$original_uri = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = $parsed['stripped_uri'];
		return $do_parse;
	}

	/**
	 * Restaura la REQUEST_URI original (con prefijo) una vez parseada la
	 * request, para que redirect_canonical compare contra la URL real.
	 */
	public static function restore_request_uri() {
		if ( null !== self::$original_uri ) {
			$_SERVER['REQUEST_URI'] = self::$original_uri;
		}
	}

	/**
	 * Idioma de esta request.
	 *
	 * Prioridad: prefijo en la URL → ?lang= válido → cookie lingua_lang →
	 * idioma default.
	 *
	 * @return string Código de idioma.
	 */
	public static function detect_request_lang() {
		$default = LGV_Languages::get_default();
		if ( self::prefixes_enabled() ) {
			if ( null !== self::$url_lang ) {
				return self::$url_lang;
			}
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$parsed = self::parse_lang_prefix( $_SERVER['REQUEST_URI'] );
				if ( null !== $parsed['lang'] ) {
					return $parsed['lang'];
				}
			}
		}
		if ( isset( $_GET['lang'] ) ) {
			$asked = lgv_sanitize_lang_code( wp_unslash( $_GET['lang'] ) );
			if ( LGV_Languages::exists( $asked ) ) {
				return $asked;
			}
		}
		if ( isset( $_COOKIE['lingua_lang'] ) ) {
			$asked = lgv_sanitize_lang_code( wp_unslash( $_COOKIE['lingua_lang'] ) );
			if ( LGV_Languages::exists( $asked ) ) {
				return $asked;
			}
		}
		return $default;
	}

	/**
	 * ¿Es una URL del propio sitio (o relativa)?
	 *
	 * @param string $url URL a evaluar.
	 * @return bool
	 */
	public static function is_internal_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return false;
		}
		if ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( ! isset( $parts['host'] ) ) {
			return true; // Relativa: se asume interna.
		}
		$home_host = strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
		return strtolower( $parts['host'] ) === $home_host;
	}

	/**
	 * ¿Es una ruta del sistema que nunca debe llevar prefijo?
	 * (admin, login, REST, contenidos, cron, xmlrpc, well-known).
	 *
	 * @param string $url URL a evaluar.
	 * @return bool
	 */
	public static function is_excluded_path( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$base = self::home_base_path();
		$rel  = $path;
		if ( '' !== $base && 0 === strpos( strtolower( $rel ) . '/', strtolower( $base ) . '/' ) ) {
			$rel = substr( $rel, strlen( $base ) );
		}
		$rel = strtolower( '/' . ltrim( $rel, '/' ) );
		$excluded = array(
			'/wp-admin',
			'/wp-login.php',
			'/wp-json',
			'/wp-content',
			'/wp-includes',
			'/xmlrpc.php',
			'/wp-cron.php',
			'/.well-known',
			'/index.php',
		);
		foreach ( $excluded as $ex ) {
			if ( $rel === $ex || 0 === strpos( $rel, $ex . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Cambia el prefijo de idioma de una URL interna.
	 * '' como idioma nuevo = URL sin prefijo (canónica del default).
	 *
	 * @param string $url      URL interna.
	 * @param string $new_lang Nuevo código de idioma o ''.
	 * @return string URL con el prefijo cambiado.
	 */
	public static function swap_prefix( $url, $new_lang ) {
		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return $url;
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$base = self::home_base_path();
		$rel  = $path;
		if ( '' !== $base ) {
			if ( 0 !== strpos( strtolower( $rel ) . '/', strtolower( $base ) . '/' ) ) {
				return $url;
			}
			$rel = substr( $rel, strlen( $base ) );
		}
		$had_trailing_slash = '/' !== $rel && '/' === substr( $rel, -1 );
		$segments = explode( '/', trim( $rel, '/' ) );
		// Quitar prefijo existente (cualquier idioma registrado, incluido el default).
		if ( isset( $segments[0] ) && '' !== $segments[0] && LGV_Languages::exists( lgv_sanitize_lang_code( $segments[0] ) ) ) {
			array_shift( $segments );
		}
		$new_lang = lgv_sanitize_lang_code( $new_lang );
		if ( '' !== $new_lang ) {
			array_unshift( $segments, $new_lang );
		}
		// Filtrar el segmento vacío que deja trim('/') para no duplicar barras.
		$segments = array_values( array_filter( $segments, function ( $s ) { return '' !== $s; } ) );
		$new_rel  = '/' . implode( '/', $segments );
		if ( $had_trailing_slash ) {
			$new_rel .= '/';
		}
		$parts['path'] = $base . $new_rel;
		return self::unparse_url( $parts );
	}

	/**
	 * Agrega el prefijo de idioma a una URL interna (idempotente).
	 * El idioma default y las URLs externas/sistema se devuelven intactas.
	 *
	 * @param string $url  URL.
	 * @param string $lang Código de idioma (default: el activo).
	 * @return string
	 */
	public static function add_prefix( $url, $lang = '' ) {
		if ( '' === $lang ) {
			$lang = lgv_current_lang();
		}
		$lang    = lgv_sanitize_lang_code( $lang );
		$default = LGV_Languages::get_default();
		if ( '' === $lang || $lang === $default || ! LGV_Languages::exists( $lang ) ) {
			return $url;
		}
		if ( ! self::is_internal_url( $url ) || self::is_excluded_path( $url ) ) {
			return $url;
		}
		return self::swap_prefix( $url, $lang );
	}

	/**
	 * Quita cualquier prefijo de idioma de una URL interna.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function remove_prefix( $url ) {
		if ( ! self::is_internal_url( $url ) ) {
			return $url;
		}
		return self::swap_prefix( $url, '' );
	}

	/**
	 * Reconstruye una URL desde sus partes (inverso de wp_parse_url).
	 *
	 * @param array $parts Partes de la URL.
	 * @return string
	 */
	private static function unparse_url( $parts ) {
		$url = '';
		if ( isset( $parts['scheme'] ) ) {
			$url .= $parts['scheme'] . '://';
		}
		if ( isset( $parts['user'] ) ) {
			$url .= $parts['user'];
			if ( isset( $parts['pass'] ) ) {
				$url .= ':' . $parts['pass'];
			}
			$url .= '@';
		}
		if ( isset( $parts['host'] ) ) {
			$url .= $parts['host'];
		}
		if ( isset( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}
		if ( isset( $parts['path'] ) ) {
			$url .= $parts['path'];
		}
		if ( isset( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}
		if ( isset( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}
		return $url;
	}
}
