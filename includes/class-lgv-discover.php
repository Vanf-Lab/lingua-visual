<?php
/**
 * Descubridor de cadenas del frontend.
 *
 * Rastrea el sitemap.xml del sitio (o una lista manual de URLs), descarga cada
 * página pública y extrae los textos visibles + atributos traducibles
 * (title, alt, placeholder, aria-label, meta description...) para registrarlos
 * en el diccionario. Funciona en lotes vía AJAX para no agotar timeouts.
 *
 * Limitación honesta: el contenido renderizado 100% por JavaScript después de
 * la carga no se captura del lado servidor (para eso están el modo visual y
 * el output buffering).
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Discover {

	const STATE_OPTION  = 'lgv_discover_state';
	const MANUAL_OPTION = 'lgv_discover_manual';
	const BATCH_URLS    = 5;
	const MAX_URLS      = 300;

	/**
	 * Estado inicial del rastreo.
	 *
	 * @return array
	 */
	private static function fresh_state() {
		return array(
			'status'   => 'idle', // idle|running|done|error
			'queue'    => array(),
			'total'    => 0,
			'done'     => 0,
			'found'    => 0,
			'new'      => 0,
			'errors'   => array(),
			'started'  => '',
			'finished' => '',
			'source'   => '',
		);
	}

	/**
	 * Lee el estado actual.
	 *
	 * @return array
	 */
	public static function get_state() {
		$state = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			return self::fresh_state();
		}
		return array_merge( self::fresh_state(), $state );
	}

	/**
	 * Guarda el estado.
	 *
	 * @param array $state Estado.
	 */
	private static function save_state( $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * URLs del sitemap.xml del sitio (índice + sub-sitemaps de WP).
	 *
	 * @return array|WP_Error Lista de URLs o error.
	 */
	public static function sitemap_urls() {
		$index_url = home_url( '/wp-sitemap.xml' );
		$xml       = self::fetch_xml( $index_url );
		if ( is_wp_error( $xml ) ) {
			return $xml;
		}
		$urls = array();
		foreach ( $xml->getElementsByTagName( 'sitemap' ) as $sm ) {
			$locs = $sm->getElementsByTagName( 'loc' );
			if ( 0 === $locs->length ) {
				continue;
			}
			$sub = self::fetch_xml( trim( $locs->item( 0 )->textContent ) );
			if ( is_wp_error( $sub ) ) {
				continue;
			}
			foreach ( $sub->getElementsByTagName( 'url' ) as $u ) {
				$locs2 = $u->getElementsByTagName( 'loc' );
				if ( 0 === $locs2->length ) {
					continue;
				}
				$urls[] = trim( $locs2->item( 0 )->textContent );
				if ( count( $urls ) >= self::MAX_URLS ) {
					break 2;
				}
			}
		}
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		if ( empty( $urls ) ) {
			return new WP_Error( 'lgv_no_urls', __( 'The sitemap returned no URLs.', 'lingua-visual' ) );
		}
		return $urls;
	}

	/**
	 * Descarga y parsea un XML.
	 *
	 * @param string $url URL del XML.
	 * @return DOMDocument|WP_Error
	 */
	private static function fetch_xml( $url ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'LinguaVisual-Discover/' . LGV_VERSION,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'lgv_http', sprintf( __( 'HTTP %d reading %s', 'lingua-visual' ), (int) wp_remote_retrieve_response_code( $res ), $url ) );
		}
		$body = wp_remote_retrieve_body( $res );
		if ( '' === trim( (string) $body ) ) {
			return new WP_Error( 'lgv_empty', __( 'Empty response.', 'lingua-visual' ) );
		}
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$ok  = $dom->loadXML( (string) $body );
		libxml_clear_errors();
		if ( ! $ok ) {
			return new WP_Error( 'lgv_xml', __( 'Could not parse the XML.', 'lingua-visual' ) );
		}
		return $dom;
	}

	/**
	 * Inicia un rastreo nuevo.
	 *
	 * @param string $mode 'sitemap' o 'manual'.
	 * @param array  $manual_urls URLs manuales (modo manual).
	 * @return array|WP_Error Estado inicial o error.
	 */
	public static function start( $mode = 'sitemap', $manual_urls = array() ) {
		if ( 'manual' === $mode ) {
			$urls = self::sanitize_manual_urls( $manual_urls );
			if ( is_wp_error( $urls ) ) {
				return $urls;
			}
		} else {
			$urls = self::sitemap_urls();
			if ( is_wp_error( $urls ) ) {
				return $urls;
			}
		}
		$state             = self::fresh_state();
		$state['status']   = 'running';
		$state['queue']    = array_values( $urls );
		$state['total']    = count( $urls );
		$state['started']  = current_time( 'mysql' );
		$state['source']   = 'sitemap' === $mode ? __( 'sitemap.xml', 'lingua-visual' ) : __( 'manual list', 'lingua-visual' );
		self::save_state( $state );
		return $state;
	}

	/**
	 * Sanea URLs manuales: una por línea, solo del mismo host.
	 *
	 * @param array $raw Líneas crudas.
	 * @return array|WP_Error
	 */
	public static function sanitize_manual_urls( $raw ) {
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$out       = array();
		foreach ( (array) $raw as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$url = esc_url_raw( $line );
			if ( '' === $url ) {
				continue;
			}
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( '' === $host ) {
				// Ruta relativa: la completamos con el home.
				$url  = home_url( '/' . ltrim( $line, '/' ) );
				$host = $home_host;
			}
			if ( strtolower( $host ) !== strtolower( $home_host ) ) {
				continue;
			}
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( preg_match( '#^/(wp-admin|wp-login|wp-json)#i', $path ) ) {
				continue;
			}
			$out[] = $url;
			if ( count( $out ) >= self::MAX_URLS ) {
				break;
			}
		}
		$out = array_values( array_unique( $out ) );
		if ( empty( $out ) ) {
			return new WP_Error( 'lgv_no_urls', __( 'No valid URLs from this site in the list.', 'lingua-visual' ) );
		}
		return $out;
	}

	/**
	 * Procesa un lote de URLs de la cola.
	 *
	 * @param int $n Cantidad de URLs por lote.
	 * @return array Estado actualizado.
	 */
	public static function run_batch( $n = self::BATCH_URLS ) {
		$state = self::get_state();
		if ( 'running' !== $state['status'] || empty( $state['queue'] ) ) {
			$state['status'] = 'done';
			if ( '' === $state['finished'] ) {
				$state['finished'] = current_time( 'mysql' );
			}
			self::save_state( $state );
			return $state;
		}
		$batch = array_splice( $state['queue'], 0, max( 1, (int) $n ) );
		$start = microtime( true );
		foreach ( $batch as $url ) {
			$res = self::fetch_and_extract( $url );
			$state['done']++;
			if ( is_wp_error( $res ) ) {
				$state['errors'][] = $url . ' → ' . $res->get_error_message();
				if ( count( $state['errors'] ) > 50 ) {
					array_shift( $state['errors'] );
				}
			} else {
				$state['found'] += $res['found'];
				$state['new']   += $res['new'];
			}
			// Rate-limit cortés + guardia de tiempo por lote.
			usleep( 250000 );
			if ( microtime( true ) - $start > 20 ) {
				break;
			}
		}
		if ( empty( $state['queue'] ) ) {
			$state['status']   = 'done';
			$state['finished'] = current_time( 'mysql' );
		}
		self::save_state( $state );
		return $state;
	}

	/**
	 * Descarga una URL y registra sus cadenas.
	 *
	 * @param string $url URL a rastrear.
	 * @return array|WP_Error [ 'found' => int, 'new' => int ]
	 */
	public static function fetch_and_extract( $url ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'user-agent'  => 'LinguaVisual-Discover/' . LGV_VERSION,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'lgv_http', sprintf( __( 'HTTP %d', 'lingua-visual' ), (int) wp_remote_retrieve_response_code( $res ) ) );
		}
		$ctype = (string) wp_remote_retrieve_header( $res, 'content-type' );
		if ( '' !== $ctype && false === stripos( $ctype, 'html' ) ) {
			return new WP_Error( 'lgv_ctype', __( 'Not HTML.', 'lingua-visual' ) );
		}
		$body = wp_remote_retrieve_body( $res );
		if ( '' === trim( (string) $body ) ) {
			return new WP_Error( 'lgv_empty', __( 'Empty HTML.', 'lingua-visual' ) );
		}
		$strings = self::extract_strings( (string) $body );
		if ( is_wp_error( $strings ) ) {
			return $strings;
		}
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		$context = '' === $path ? '/' : $path;
		$new     = 0;
		foreach ( $strings as $s ) {
			$before = self::count_strings();
			$id     = LGV_Strings::register( $s, $context );
			if ( $id && self::count_strings() > $before ) {
				$new++;
			}
		}
		return array(
			'found' => count( $strings ),
			'new'   => $new,
		);
	}

	/**
	 * Cuenta las cadenas registradas (para detectar "nuevas").
	 *
	 * @return int
	 */
	private static function count_strings() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . LGV_DB::strings_table() );
	}

	/**
	 * Extrae textos visibles y atributos traducibles de un HTML.
	 *
	 * @param string $html HTML crudo.
	 * @return array|WP_Error Lista de cadenas normalizadas y deduplicadas.
	 */
	public static function extract_strings( $html ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$ok  = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		if ( ! $ok ) {
			return new WP_Error( 'lgv_parse', __( 'Could not parse the HTML.', 'lingua-visual' ) );
		}
		$xpath = new DOMXPath( $dom );
		$found = array();

		// Nodos de texto visibles (fuera de script/style/noscript/textarea).
		$texts = $xpath->query( '//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::noscript) and not(ancestor::textarea)]' );
		if ( $texts ) {
			foreach ( $texts as $node ) {
				$t = self::normalize( $node->textContent );
				if ( self::is_translatable( $t ) ) {
					$found[ md5( $t ) ] = $t;
				}
			}
		}

		// Atributos traducibles (solo en el body: excluimos <link> y demás del head).
		$with_attrs = $xpath->query( '//body//*[@title or @alt or @placeholder or @aria-label]' );
		if ( $with_attrs ) {
			foreach ( $with_attrs as $el ) {
				foreach ( array( 'title', 'alt', 'placeholder', 'aria-label' ) as $attr ) {
					if ( $el->hasAttribute( $attr ) ) {
						$t = self::normalize( $el->getAttribute( $attr ) );
						if ( self::is_translatable( $t ) ) {
							$found[ md5( 'attr:' . $attr . ':' . $t ) ] = $t;
						}
					}
				}
			}
		}

		// Meta description y Open Graph.
		$metas = $xpath->query( '//meta[@name="description" or @property="og:title" or @property="og:description" or @property="og:site_name"]/@content' );
		if ( $metas ) {
			foreach ( $metas as $m ) {
				$t = self::normalize( $m->textContent );
				if ( self::is_translatable( $t ) ) {
					$found[ md5( 'meta:' . $t ) ] = $t;
				}
			}
		}

		// Botones de formulario.
		$inputs = $xpath->query( '//input[@type="submit" or @type="button"]/@value' );
		if ( $inputs ) {
			foreach ( $inputs as $i ) {
				$t = self::normalize( $i->textContent );
				if ( self::is_translatable( $t ) ) {
					$found[ md5( 'input:' . $t ) ] = $t;
				}
			}
		}

		return array_values( $found );
	}

	/**
	 * Normaliza espacios de una cadena.
	 *
	 * @param string $s Cadena cruda.
	 * @return string
	 */
	private static function normalize( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( (string) $s );
	}

	/**
	 * ¿Vale la pena traducir esta cadena?
	 *
	 * @param string $s Cadena normalizada.
	 * @return bool
	 */
	private static function is_translatable( $s ) {
		if ( '' === $s || mb_strlen( $s, 'UTF-8' ) < 2 ) {
			return false;
		}
		// Al menos una letra (descarta precios, fechas sueltas, códigos).
		if ( ! preg_match( '/\p{L}/u', $s ) ) {
			return false;
		}
		// Descarta URLs/emails sueltos.
		if ( preg_match( '#^(https?://|www\.|[\w.+-]+@[\w-]+\.)#i', $s ) ) {
			return false;
		}
		return true;
	}

	/**
	 * AJAX: inicia el rastreo.
	 */
	public static function ajax_start() {
		// Discard any stray output (PHP warnings from other plugins) that would corrupt the JSON response.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		@ini_set( 'display_errors', '0' );
		@error_reporting( 0 );
		check_ajax_referer( 'lgv_discover', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'lingua-visual' ) ), 403 );
		}
		$mode = isset( $_POST['mode'] ) && 'manual' === $_POST['mode'] ? 'manual' : 'sitemap';
		$raw  = isset( $_POST['urls'] ) ? explode( "\n", wp_unslash( $_POST['urls'] ) ) : array();
		if ( 'manual' === $mode ) {
			update_option( self::MANUAL_OPTION, implode( "\n", array_map( 'trim', $raw ) ), false );
		}
		$res = self::start( $mode, $raw );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( self::public_state( $res ) );
	}

	/**
	 * AJAX: procesa un lote.
	 */
	public static function ajax_batch() {
		// Discard any stray output (PHP warnings from other plugins) that would corrupt the JSON response.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		@ini_set( 'display_errors', '0' );
		@error_reporting( 0 );
		check_ajax_referer( 'lgv_discover', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'lingua-visual' ) ), 403 );
		}
		$state = self::run_batch();
		wp_send_json_success( self::public_state( $state ) );
	}

	/**
	 * Estado sanitizado para el JS.
	 *
	 * @param array $state Estado interno.
	 * @return array
	 */
	public static function public_state( $state ) {
		return array(
			'status'   => $state['status'],
			'total'    => (int) $state['total'],
			'done'     => (int) $state['done'],
			'found'    => (int) $state['found'],
			'new'      => (int) $state['new'],
			'errors'   => array_slice( (array) $state['errors'], -5 ),
			'n_errors' => count( (array) $state['errors'] ),
			'source'   => $state['source'],
			'finished' => $state['finished'],
		);
	}
}
