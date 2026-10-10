<?php
/**
 * Traducción automática: arquitectura de proveedores + cola de traducción.
 *
 * Proveedores:
 *  1. MyMemory (default, sin key, funciona de fábrica): ~5000 caracteres/día
 *     anónimo (~50000/día con email opcional), máx ~500 bytes por request.
 *  2. DeepL (BYOK): 500000 caracteres/mes gratis con cuenta gratuita.
 *  3. Google (endpoint gratuito no oficial): experimental, puede ser
 *     throttled; no es para depender de él.
 *
 * Las traducciones automáticas se guardan con auto=1 (pendientes de revisión);
 * si el usuario las edita a mano pasan a manuales y ya no se pisan.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proveedor abstracto de traducción automática.
 */
abstract class LGV_AT_Provider {

	/**
	 * ID del proveedor (ej. 'mymemory').
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Etiqueta visible.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * ¿Requiere API key?
	 *
	 * @return bool
	 */
	abstract public function needs_key();

	/**
	 * Máximo de bytes por request (para partir textos largos).
	 *
	 * @return int
	 */
	abstract public function max_bytes_per_request();

	/**
	 * Límite de caracteres por período (0 = desconocido/ilimitado).
	 *
	 * @return int
	 */
	abstract public function quota_limit();

	/**
	 * Período de la cuota: 'day' o 'month'.
	 *
	 * @return string
	 */
	abstract public function quota_period();

	/**
	 * Traduce un lote de textos.
	 *
	 * @param array  $texts Lista de textos originales.
	 * @param string $from  Código de idioma origen.
	 * @param string $to    Código de idioma destino.
	 * @return array|WP_Error [ 'map' => [ original => traducción ], 'chars' => int ]
	 */
	abstract public function translate_texts( $texts, $from, $to );
}

/**
 * MyMemory: gratis, sin key, funciona de fábrica.
 */
class LGV_AT_MyMemory extends LGV_AT_Provider {

	public function id() {
		return 'mymemory';
	}

	public function label() {
		return __( 'MyMemory (free, no key)', 'lingua-visual' );
	}

	public function needs_key() {
		return false;
	}

	public function max_bytes_per_request() {
		return 450;
	}

	public function quota_limit() {
		$s = LGV_AutoTranslate::settings();
		return '' !== trim( (string) $s['mymemory_email'] ) ? 50000 : 5000;
	}

	public function quota_period() {
		return 'day';
	}

	public function translate_texts( $texts, $from, $to ) {
		$texts = array_values( array_filter( array_map( 'trim', (array) $texts ), function ( $t ) {
			return '' !== $t;
		} ) );
		if ( empty( $texts ) ) {
			return array(
				'map'   => array(),
				'chars' => 0,
			);
		}
		$s     = LGV_AutoTranslate::settings();
		$email = trim( (string) $s['mymemory_email'] );
		$pair  = strtolower( substr( $from, 0, 2 ) ) . '|' . strtolower( substr( $to, 0, 2 ) );
		$map   = array();
		$chars = 0;
		foreach ( self::chunk( $texts, $this->max_bytes_per_request() ) as $group ) {
			$joined = implode( "\n", $group );
			$args   = array(
				'q'        => $joined,
				'langpair' => $pair,
			);
			if ( '' !== $email && is_email( $email ) ) {
				$args['de'] = $email;
			}
			$res = wp_remote_get(
				add_query_arg( $args, 'https://api.mymemory.translated.net/get' ),
				array(
					'timeout'    => 25,
					'user-agent' => 'LinguaVisual/' . LGV_VERSION,
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $body ) || ! isset( $body['responseData']['translatedText'] ) ) {
				return new WP_Error( 'lgv_mm_bad', __( 'MyMemory returned an unexpected response.', 'lingua-visual' ) );
			}
			$status = isset( $body['responseStatus'] ) ? (string) $body['responseStatus'] : '200';
			$detail = isset( $body['responseDetails'] ) ? (string) $body['responseDetails'] : '';
			if ( '200' !== $status ) {
				if ( false !== stripos( $detail, 'quota' ) || false !== stripos( $detail, 'limit' ) || '429' === $status ) {
					$err = new WP_Error( 'lgv_quota', __( 'MyMemory: daily quota exhausted. Try tomorrow or add an email in the settings.', 'lingua-visual' ) );
					$err->add_data( array( 'quota_hit' => true ) );
					return $err;
				}
				return new WP_Error( 'lgv_mm_err', sprintf( __( 'MyMemory: %s', 'lingua-visual' ), $detail ) );
			}
			$translated = (string) $body['responseData']['translatedText'];
			$parts      = explode( "\n", $translated );
			if ( count( $parts ) !== count( $group ) ) {
				// El proveedor alteró los saltos: reintentamos texto por texto.
				foreach ( $group as $single ) {
					$one = $this->translate_texts( array( $single ), $from, $to );
					if ( is_wp_error( $one ) ) {
						return $one;
					}
					$map   = array_merge( $map, $one['map'] );
					$chars += $one['chars'];
				}
				continue;
			}
			foreach ( $group as $i => $orig ) {
				$map[ $orig ] = trim( $parts[ $i ] );
				$chars       += mb_strlen( $orig, 'UTF-8' );
			}
		}
		return array(
			'map'   => $map,
			'chars' => $chars,
		);
	}

	/**
	 * Parte textos en grupos que no superen N bytes.
	 *
	 * @param array $texts Textos.
	 * @param int   $max   Máximo de bytes por grupo.
	 * @return array Grupos.
	 */
	private static function chunk( $texts, $max ) {
		$groups  = array();
		$current = array();
		$bytes   = 0;
		foreach ( $texts as $t ) {
			$b = strlen( $t ) + 1;
			if ( $current && $bytes + $b > $max ) {
				$groups[] = $current;
				$current  = array();
				$bytes    = 0;
			}
			$current[] = $t;
			$bytes    += $b;
		}
		if ( $current ) {
			$groups[] = $current;
		}
		return $groups;
	}
}

/**
 * DeepL: 500K caracteres/mes gratis, requiere API key gratuita.
 */
class LGV_AT_DeepL extends LGV_AT_Provider {

	public function id() {
		return 'deepl';
	}

	public function label() {
		return __( 'DeepL (free key)', 'lingua-visual' );
	}

	public function needs_key() {
		return true;
	}

	public function max_bytes_per_request() {
		return 28000; // DeepL acepta hasta 50 textos por request.
	}

	public function quota_limit() {
		return 500000;
	}

	public function quota_period() {
		return 'month';
	}

	public function translate_texts( $texts, $from, $to ) {
		$texts = array_values( array_filter( array_map( 'trim', (array) $texts ), function ( $t ) {
			return '' !== $t;
		} ) );
		if ( empty( $texts ) ) {
			return array(
				'map'   => array(),
				'chars' => 0,
			);
		}
		$s   = LGV_AutoTranslate::settings();
		$key = trim( (string) $s['deepl_key'] );
		if ( '' === $key ) {
			return new WP_Error( 'lgv_dl_key', __( 'DeepL: API key missing. Paste it in the settings.', 'lingua-visual' ) );
		}
		$map   = array();
		$chars = 0;
		foreach ( array_chunk( $texts, 50 ) as $group ) {
			$body = array(
				'target_lang' => strtoupper( substr( $to, 0, 2 ) ),
				'source_lang' => strtoupper( substr( $from, 0, 2 ) ),
			);
			// wp_remote_post con arrays anidados genera text[0], text[1]...
			$body['text'] = array_values( $group );
			$res          = wp_remote_post(
				'https://api-free.deepl.com/v2/translate',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'DeepL-Auth-Key ' . $key,
					),
					'body'    => $body,
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$data = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( 456 === $code ) {
				$err = new WP_Error( 'lgv_quota', __( 'DeepL: monthly quota exhausted (500K characters).', 'lingua-visual' ) );
				$err->add_data( array( 'quota_hit' => true ) );
				return $err;
			}
			if ( 403 === $code ) {
				return new WP_Error( 'lgv_dl_auth', __( 'DeepL: invalid API key (403).', 'lingua-visual' ) );
			}
			if ( 200 !== $code || ! isset( $data['translations'] ) || count( $data['translations'] ) !== count( $group ) ) {
				return new WP_Error( 'lgv_dl_err', sprintf( __( 'DeepL: HTTP error %d.', 'lingua-visual' ), $code ) );
			}
			foreach ( $group as $i => $orig ) {
				$map[ $orig ] = trim( (string) $data['translations'][ $i ]['text'] );
				$chars       += mb_strlen( $orig, 'UTF-8' );
			}
		}
		return array(
			'map'   => $map,
			'chars' => $chars,
		);
	}
}

/**
 * Google (endpoint gratuito no oficial): EXPERIMENTAL.
 *
 * No es una API oficial: puede ser throttled o dejar de funcionar en cualquier
 * momento. Úsalo solo como último recurso, nunca como dependencia.
 */
class LGV_AT_GoogleFree extends LGV_AT_Provider {

	public function id() {
		return 'google';
	}

	public function label() {
		return __( 'Google (experimental, unofficial)', 'lingua-visual' );
	}

	public function needs_key() {
		return false;
	}

	public function max_bytes_per_request() {
		return 450;
	}

	public function quota_limit() {
		return 0; // Desconocido: el throttling es impredecible.
	}

	public function quota_period() {
		return 'day';
	}

	public function translate_texts( $texts, $from, $to ) {
		$texts = array_values( array_filter( array_map( 'trim', (array) $texts ), function ( $t ) {
			return '' !== $t;
		} ) );
		if ( empty( $texts ) ) {
			return array(
				'map'   => array(),
				'chars' => 0,
			);
		}
		$sl    = strtolower( substr( $from, 0, 2 ) );
		$tl    = strtolower( substr( $to, 0, 2 ) );
		$map   = array();
		$chars = 0;
		foreach ( self::chunk( $texts, $this->max_bytes_per_request() ) as $group ) {
			$joined = implode( "\n", $group );
			$res    = wp_remote_get(
				add_query_arg(
					array(
						'client' => 'gtx',
						'sl'     => $sl,
						'tl'     => $tl,
						'dt'     => 't',
						'q'      => $joined,
					),
					'https://translate.googleapis.com/translate_a/single'
				),
				array(
					'timeout'    => 25,
					'user-agent' => 'Mozilla/5.0',
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return new WP_Error( 'lgv_gg_err', sprintf( __( 'Google: HTTP %d (throttled or down).', 'lingua-visual' ), (int) wp_remote_retrieve_response_code( $res ) ) );
			}
			$data = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $data ) || ! isset( $data[0] ) || ! is_array( $data[0] ) ) {
				return new WP_Error( 'lgv_gg_bad', __( 'Google: unexpected response.', 'lingua-visual' ) );
			}
			$segments = array();
			foreach ( $data[0] as $seg ) {
				if ( is_array( $seg ) && isset( $seg[0] ) ) {
					$segments[] = $seg[0];
				}
			}
			$translated = implode( '', $segments );
			$parts      = explode( "\n", $translated );
			if ( count( $parts ) !== count( $group ) ) {
				foreach ( $group as $single ) {
					$one = $this->translate_texts( array( $single ), $from, $to );
					if ( is_wp_error( $one ) ) {
						return $one;
					}
					$map   = array_merge( $map, $one['map'] );
					$chars += $one['chars'];
				}
				continue;
			}
			foreach ( $group as $i => $orig ) {
				$map[ $orig ] = trim( $parts[ $i ] );
				$chars       += mb_strlen( $orig, 'UTF-8' );
			}
		}
		return array(
			'map'   => $map,
			'chars' => $chars,
		);
	}

	/**
	 * Parte textos en grupos que no superen N bytes.
	 *
	 * @param array $texts Textos.
	 * @param int   $max   Máximo de bytes por grupo.
	 * @return array Grupos.
	 */
	private static function chunk( $texts, $max ) {
		$groups  = array();
		$current = array();
		$bytes   = 0;
		foreach ( $texts as $t ) {
			$b = strlen( $t ) + 1;
			if ( $current && $bytes + $b > $max ) {
				$groups[] = $current;
				$current  = array();
				$bytes    = 0;
			}
			$current[] = $t;
			$bytes    += $b;
		}
		if ( $current ) {
			$groups[] = $current;
		}
		return $groups;
	}
}

/**
 * Orquestador de traducción automática.
 */
class LGV_AutoTranslate {

	const OPTION       = 'lgv_at_settings';
	const QUOTA_OPTION = 'lgv_at_quota';

	/**
	 * Registra hooks.
	 */
	public static function init() {
		// El hook lgv_auto_translate del modo visual ahora sugiere con el proveedor configurado.
		add_filter( 'lgv_auto_translate', array( __CLASS__, 'filter_suggest' ), 10, 3 );
	}

	/**
	 * Proveedores disponibles: [ id => instancia ].
	 *
	 * @return LGV_AT_Provider[]
	 */
	public static function providers() {
		return array(
			'mymemory' => new LGV_AT_Mymemory(),
			'deepl'    => new LGV_AT_DeepL(),
			'google'   => new LGV_AT_GoogleFree(),
		);
	}

	/**
	 * Ajustes actuales.
	 *
	 * @return array
	 */
	public static function settings() {
		$defaults = array(
			'provider'       => 'mymemory',
			'deepl_key'      => '',
			'mymemory_email' => '',
			'target_lang'    => '',
		);
		$saved    = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * Guarda ajustes saneados.
	 *
	 * @param array $raw Datos crudos del formulario.
	 */
	public static function save_settings( $raw ) {
		$providers = self::providers();
		$provider  = isset( $raw['provider'] ) ? sanitize_key( $raw['provider'] ) : 'mymemory';
		if ( ! isset( $providers[ $provider ] ) ) {
			$provider = 'mymemory';
		}
		$target = isset( $raw['target_lang'] ) ? lgv_sanitize_lang_code( $raw['target_lang'] ) : '';
		if ( ! LGV_Languages::exists( $target ) || $target === LGV_Languages::get_default() ) {
			$target = '';
		}
		update_option(
			self::OPTION,
			array(
				'provider'       => $provider,
				'deepl_key'      => isset( $raw['deepl_key'] ) ? sanitize_text_field( $raw['deepl_key'] ) : '',
				'mymemory_email' => isset( $raw['mymemory_email'] ) ? sanitize_email( $raw['mymemory_email'] ) : '',
				'target_lang'    => $target,
			),
			false
		);
	}

	/**
	 * Idioma destino por defecto: el primer idioma no-default.
	 *
	 * @return string
	 */
	public static function default_target() {
		$s = self::settings();
		if ( '' !== $s['target_lang'] && LGV_Languages::exists( $s['target_lang'] ) ) {
			return $s['target_lang'];
		}
		$default = LGV_Languages::get_default();
		foreach ( LGV_Languages::all() as $code => $data ) {
			if ( $code !== $default ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Caracteres usados en el período actual por proveedor.
	 *
	 * @param string $provider_id ID del proveedor.
	 * @return int
	 */
	public static function quota_used( $provider_id ) {
		$q = get_option( self::QUOTA_OPTION, array() );
		if ( ! is_array( $q ) || ! isset( $q[ $provider_id ] ) || ! is_array( $q[ $provider_id ] ) ) {
			return 0;
		}
		$providers = self::providers();
		if ( ! isset( $providers[ $provider_id ] ) ) {
			return 0;
		}
		$period = $providers[ $provider_id ]->quota_period();
		$key    = 'day' === $period ? gmdate( 'Y-m-d' ) : gmdate( 'Y-m' );
		$slot   = 'day' === $period ? 'day' : 'month';
		if ( ! isset( $q[ $provider_id ][ $slot ] ) || $q[ $provider_id ][ $slot ] !== $key ) {
			return 0;
		}
		return (int) $q[ $provider_id ]['chars'];
	}

	/**
	 * Suma caracteres al contador de cuota.
	 *
	 * @param string $provider_id ID del proveedor.
	 * @param int    $chars Caracteres.
	 */
	public static function quota_add( $provider_id, $chars ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider_id ] ) || $chars <= 0 ) {
			return;
		}
		$period = $providers[ $provider_id ]->quota_period();
		$slot   = 'day' === $period ? 'day' : 'month';
		$key    = 'day' === $period ? gmdate( 'Y-m-d' ) : gmdate( 'Y-m' );
		$q      = get_option( self::QUOTA_OPTION, array() );
		if ( ! is_array( $q ) ) {
			$q = array();
		}
		if ( ! isset( $q[ $provider_id ] ) || ! is_array( $q[ $provider_id ] ) || ! isset( $q[ $provider_id ][ $slot ] ) || $q[ $provider_id ][ $slot ] !== $key ) {
			$q[ $provider_id ] = array(
				$slot  => $key,
				'chars' => 0,
			);
		}
		$q[ $provider_id ]['chars'] = (int) $q[ $provider_id ]['chars'] + (int) $chars;
		update_option( self::QUOTA_OPTION, $q, false );
	}

	/**
	 * Caracteres restantes de cuota (PHP_INT_MAX si se desconoce).
	 *
	 * @param string $provider_id ID del proveedor.
	 * @return int
	 */
	public static function quota_remaining( $provider_id ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider_id ] ) ) {
			return 0;
		}
		$limit = $providers[ $provider_id ]->quota_limit();
		if ( $limit <= 0 ) {
			return PHP_INT_MAX;
		}
		return max( 0, $limit - self::quota_used( $provider_id ) );
	}

	/**
	 * Cadenas sin traducción (o vacía) para un idioma.
	 *
	 * @param string $lang  Código de idioma destino.
	 * @param int    $limit Máximo de cadenas.
	 * @return array [ [ 'id' => int, 'original' => string ], ... ]
	 */
	public static function pending_strings( $lang, $limit = 40 ) {
		global $wpdb;
		$lang    = lgv_sanitize_lang_code( $lang );
		$strings = LGV_DB::strings_table();
		$trans   = LGV_DB::translations_table();
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.original FROM {$strings} s
				 LEFT JOIN {$trans} t ON t.string_id = s.id AND t.lang = %s
				 WHERE t.translation IS NULL OR t.translation = ''
				 ORDER BY s.id ASC LIMIT %d",
				$lang,
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
		return $rows ? $rows : array();
	}

	/**
	 * Cuenta las cadenas pendientes para un idioma.
	 *
	 * @param string $lang Código de idioma destino.
	 * @return int
	 */
	public static function pending_count( $lang ) {
		global $wpdb;
		$lang    = lgv_sanitize_lang_code( $lang );
		$strings = LGV_DB::strings_table();
		$trans   = LGV_DB::translations_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$strings} s
				 LEFT JOIN {$trans} t ON t.string_id = s.id AND t.lang = %s
				 WHERE t.translation IS NULL OR t.translation = ''",
				$lang
			)
		);
	}

	/**
	 * Traduce un lote de cadenas pendientes.
	 *
	 * Atómico por cadena: un fallo no rompe el resto del lote.
	 *
	 * @param string $lang  Idioma destino.
	 * @param int    $batch Cantidad de cadenas por lote.
	 * @return array|WP_Error Estadísticas o error.
	 */
	public static function run_batch( $lang, $batch = 30 ) {
		$lang = lgv_sanitize_lang_code( $lang );
		if ( ! LGV_Languages::exists( $lang ) || $lang === LGV_Languages::get_default() ) {
			return new WP_Error( 'lgv_lang', __( 'Invalid target language.', 'lingua-visual' ) );
		}
		$s         = self::settings();
		$providers = self::providers();
		$pid       = $s['provider'];
		if ( ! isset( $providers[ $pid ] ) ) {
			return new WP_Error( 'lgv_prov', __( 'Unknown provider.', 'lingua-visual' ) );
		}
		$provider = $providers[ $pid ];
		if ( $provider->needs_key() ) {
			$key = trim( (string) $s['deepl_key'] );
			if ( '' === $key ) {
				return new WP_Error( 'lgv_key', __( 'This provider needs an API key. Paste it in the settings.', 'lingua-visual' ) );
			}
		}
		$pending = self::pending_strings( $lang, $batch );
		if ( empty( $pending ) ) {
			return array(
				'done'       => true,
				'translated' => 0,
				'chars'      => 0,
				'errors'     => array(),
				'quota_hit'  => false,
				'remaining'  => self::pending_count( $lang ),
			);
		}
		if ( self::quota_remaining( $pid ) <= 0 ) {
			$err = new WP_Error( 'lgv_quota', __( 'Provider quota exhausted for this period.', 'lingua-visual' ) );
			$err->add_data( array( 'quota_hit' => true ) );
			return $err;
		}
		$from       = LGV_Languages::get_default();
		$translated = 0;
		$chars      = 0;
		$errors     = array();
		$quota_hit  = false;

		// Agrupamos respetando el máximo por request del proveedor.
		$groups  = array();
		$current = array();
		$bytes   = 0;
		$max_b   = $provider->max_bytes_per_request();
		foreach ( $pending as $row ) {
			$b = strlen( $row['original'] ) + 1;
			if ( $current && $bytes + $b > $max_b ) {
				$groups[] = $current;
				$current  = array();
				$bytes    = 0;
			}
			$current[] = $row;
			$bytes    += $b;
		}
		if ( $current ) {
			$groups[] = $current;
		}

		foreach ( $groups as $group ) {
			if ( self::quota_remaining( $pid ) <= 0 ) {
				$quota_hit = true;
				break;
			}
			$texts = wp_list_pluck( $group, 'original' );
			$res   = $provider->translate_texts( $texts, $from, $lang );
			if ( is_wp_error( $res ) ) {
				$data = $res->get_error_data();
				if ( is_array( $data ) && ! empty( $data['quota_hit'] ) ) {
					$quota_hit = true;
					break;
				}
				foreach ( $group as $row ) {
					$errors[] = mb_substr( $row['original'], 0, 40, 'UTF-8' ) . '… → ' . $res->get_error_message();
				}
				continue;
			}
			self::quota_add( $pid, $res['chars'] );
			$chars += $res['chars'];
			foreach ( $group as $row ) {
				$orig = $row['original'];
				if ( ! isset( $res['map'][ $orig ] ) || '' === trim( (string) $res['map'][ $orig ] ) ) {
					$errors[] = mb_substr( $orig, 0, 40, 'UTF-8' ) . __( '… → untranslated.', 'lingua-visual' );
					continue;
				}
				if ( LGV_Strings::save_translation( (int) $row['id'], $lang, $res['map'][ $orig ], 1 ) ) {
					$translated++;
				}
			}
		}

		return array(
			'done'       => false,
			'translated' => $translated,
			'chars'      => $chars,
			'errors'     => array_slice( $errors, 0, 10 ),
			'n_errors'   => count( $errors ),
			'quota_hit'  => $quota_hit,
			'remaining'  => self::pending_count( $lang ),
		);
	}

	/**
	 * Marca una traducción automática como revisada (pasa a manual).
	 *
	 * @param int    $string_id ID de la cadena.
	 * @param string $lang      Código de idioma.
	 * @return bool
	 */
	public static function mark_reviewed( $string_id, $lang ) {
		global $wpdb;
		$lang = lgv_sanitize_lang_code( $lang );
		return (bool) $wpdb->update(
			LGV_DB::translations_table(),
			array( 'auto' => 0 ),
			array(
				'string_id' => (int) $string_id,
				'lang'      => $lang,
			),
			array( '%d' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Filtro lgv_auto_translate: sugerencia con el proveedor configurado.
	 *
	 * @param string $s        Valor actual del filtro.
	 * @param string $original Texto original.
	 * @param string $lang     Idioma destino.
	 * @return string Sugerencia o ''.
	 */
	public static function filter_suggest( $s, $original, $lang ) {
		if ( '' !== $s || '' === trim( (string) $original ) ) {
			return $s;
		}
		$st        = self::settings();
		$providers = self::providers();
		if ( ! isset( $providers[ $st['provider'] ] ) ) {
			return $s;
		}
		$provider = $providers[ $st['provider'] ];
		if ( $provider->needs_key() && '' === trim( (string) $st['deepl_key'] ) ) {
			return $s;
		}
		if ( self::quota_remaining( $st['provider'] ) <= 0 ) {
			return $s;
		}
		$res = $provider->translate_texts( array( $original ), LGV_Languages::get_default(), $lang );
		if ( is_wp_error( $res ) || empty( $res['map'][ $original ] ) ) {
			return $s;
		}
		self::quota_add( $st['provider'], $res['chars'] );
		return (string) $res['map'][ $original ];
	}

	/**
	 * AJAX: procesa un lote de traducción automática.
	 */
	public static function ajax_batch() {
		// Discard any stray output (PHP warnings from other plugins) that would corrupt the JSON response.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		@ini_set( 'display_errors', '0' );
		@error_reporting( 0 );
		check_ajax_referer( 'lgv_auto', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'lingua-visual' ) ), 403 );
		}
		$lang = isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : '';
		if ( '' === $lang ) {
			$lang = self::default_target();
		}
		$res = self::run_batch( $lang, 30 );
		if ( is_wp_error( $res ) ) {
			$data      = $res->get_error_data();
			$quota_hit = is_array( $data ) && ! empty( $data['quota_hit'] );
			wp_send_json_error(
				array(
					'message'   => $res->get_error_message(),
					'quota_hit' => $quota_hit,
				)
			);
		}
		$res['remaining'] = self::pending_count( $lang );
		wp_send_json_success( $res );
	}

	/**
	 * Etiqueta legible del período de cuota.
	 *
	 * @param string $pid ID del proveedor.
	 * @return string
	 */
	public static function quota_label( $pid ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $pid ] ) ) {
			return '';
		}
		$limit = $providers[ $pid ]->quota_limit();
		$used  = self::quota_used( $pid );
		if ( $limit <= 0 ) {
			return sprintf( __( '%s characters used (unknown limit)', 'lingua-visual' ), number_format_i18n( $used ) );
		}
		$period = 'day' === $providers[ $pid ]->quota_period() ? __( 'today', 'lingua-visual' ) : __( 'this month', 'lingua-visual' );
		return sprintf(
			/* translators: %1$s usados, %2$s límite, %3$s período */
			__( '%1$s / %2$s characters %3$s', 'lingua-visual' ),
			number_format_i18n( $used ),
			number_format_i18n( $limit ),
			$period
		);
	}
}
