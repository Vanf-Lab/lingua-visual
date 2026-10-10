<?php
/**
 * Detección automática del idioma: geolocalización por IP + idioma del navegador.
 *
 * Cadena de prioridad total:
 *   1) Elección explícita del usuario (prefijo /{lang}/ en la URL, ?lang= o
 *      cookie lingua_lang) — SIEMPRE gana y nunca se redirige.
 *   2) Idioma del navegador (header Accept-Language) — si el método lo incluye.
 *   3) Geolocalización por IP (país → tabla país→idiomas → intersección con
 *      los idiomas activos del sitio) — si el método lo incluye.
 *   4) Idioma de respaldo configurado (o el default del sitio).
 *
 * La detección solo actúa en la PRIMERA visita (cookie lgv_detected) y nunca
 * redirige bots, el admin, AJAX, REST ni CRON. La redirección es 302 con
 * headers anti-caché hacia la URL con prefijo (routing 0.2.0); cada idioma
 * queda en su propia URL y los cachés de página guardan una entrada por idioma.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Detect {

	const SETTINGS_OPTION = 'lgv_detect_settings';
	const DETECTED_COOKIE = 'lgv_detected';
	const NOTICE_ARG      = 'lgv_redir';

	/**
	 * Patrones de user-agent que identifican bots/crawlers (nunca redirigir).
	 *
	 * @var array
	 */
	private static $bot_patterns = array(
		'googlebot',
		'bingbot',
		'slurp',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'sogou',
		'exabot',
		'facebot',
		'facebookexternalhit',
		'twitterbot',
		'linkedinbot',
		'whatsapp',
		'telegrambot',
		'applebot',
		'mj12bot',
		'ahrefsbot',
		'semrushbot',
		'dotbot',
		'petalbot',
		'adsbot-google',
		'mediapartners-google',
		'embedly',
		'quora link preview',
		'showyoubot',
		'outbrain',
		'pinterest',
		'slackbot',
		'discordbot',
	);

	/**
	 * Registra los hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_auto_redirect' ), 0 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_notice' ), 5 );
	}

	/**
	 * Ajustes de detección con sus defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$defaults = array(
			'enabled'       => 0,    // Detección automática (off por default: el admin la enciende).
			'method'        => 'both', // both | browser | ip.
			'api_enabled'   => 0,    // Consultar API externa de GeoIP (off: privacidad).
			'api_url'       => 'http://ip-api.com/json/{ip}?fields=status,countryCode',
			'api_cache_days' => 30,  // Días de caché del país por IP (transient).
			'local_map'     => '',   // Mapa local CIDR=PAIS (una regla por línea).
			'fallback_lang' => '',   // '' = idioma default del sitio.
			'exclude_bots'  => 1,    // Nunca redirigir bots.
			'scope'         => 'all', // all | home.
			'show_notice'   => 0,    // Aviso "te cambiamos a X" tras redirigir.
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * Guarda los ajustes (saneados).
	 *
	 * @param array $raw Valores crudos del formulario.
	 */
	public static function save_settings( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$method = isset( $raw['method'] ) ? sanitize_key( $raw['method'] ) : 'both';
		if ( ! in_array( $method, array( 'both', 'browser', 'ip' ), true ) ) {
			$method = 'both';
		}
		$scope = isset( $raw['scope'] ) ? sanitize_key( $raw['scope'] ) : 'all';
		if ( ! in_array( $scope, array( 'all', 'home' ), true ) ) {
			$scope = 'all';
		}
		$fallback = isset( $raw['fallback_lang'] ) ? lgv_sanitize_lang_code( $raw['fallback_lang'] ) : '';
		if ( '' !== $fallback && ! LGV_Languages::exists( $fallback ) ) {
			$fallback = '';
		}
		$clean = array(
			'enabled'        => ! empty( $raw['enabled'] ) ? 1 : 0,
			'method'         => $method,
			'api_enabled'    => ! empty( $raw['api_enabled'] ) ? 1 : 0,
			'api_url'        => isset( $raw['api_url'] ) ? esc_url_raw( trim( (string) $raw['api_url'] ) ) : '',
			'api_cache_days' => isset( $raw['api_cache_days'] ) ? max( 1, min( 365, (int) $raw['api_cache_days'] ) ) : 30,
			'local_map'      => isset( $raw['local_map'] ) ? self::sanitize_local_map( (string) $raw['local_map'] ) : '',
			'fallback_lang'  => $fallback,
			'exclude_bots'   => ! empty( $raw['exclude_bots'] ) ? 1 : 0,
			'scope'          => $scope,
			'show_notice'    => ! empty( $raw['show_notice'] ) ? 1 : 0,
		);
		update_option( self::SETTINGS_OPTION, $clean );
	}

	/**
	 * Sanea el mapa local: conserva solo líneas CIDR=PAIS o IP=PAIS válidas.
	 *
	 * @param string $raw Texto crudo del textarea.
	 * @return string Texto saneado.
	 */
	private static function sanitize_local_map( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
				continue;
			}
			$parts = explode( '=', $line, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$range   = trim( $parts[0] );
			$country = strtoupper( trim( $parts[1] ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
				continue;
			}
			if ( false !== strpos( $range, '/' ) ) {
				list( $base, $bits ) = explode( '/', $range, 2 );
				if ( ! filter_var( trim( $base ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
					continue;
				}
				$bits = (int) trim( $bits );
				if ( $bits < 0 || $bits > 32 ) {
					continue;
				}
			} elseif ( ! filter_var( $range, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				continue;
			}
			$out[] = $range . '=' . $country;
		}
		return implode( "\n", $out );
	}

	/**
	 * IP del visitante: Cloudflare → X-Forwarded-For (primera) → REMOTE_ADDR.
	 * Prefiere IPs públicas; como fallback acepta cualquiera válida.
	 *
	 * @return string IP o ''.
	 */
	public static function client_ip() {
		$candidates = array();
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts          = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$candidates[]   = trim( $parts[0] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = $_SERVER['REMOTE_ADDR'];
		}
		foreach ( $candidates as $ip ) {
			$ip = trim( (string) $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $ip;
			}
		}
		foreach ( $candidates as $ip ) {
			$ip = trim( (string) $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}

	/**
	 * País desde el header de Cloudflare (gratis, sin llamadas externas).
	 *
	 * @return string Código de país o ''.
	 */
	private static function country_from_cloudflare() {
		foreach ( array( 'HTTP_CF_IPCOUNTRY', 'CF-IPCountry' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$cc = strtoupper( trim( (string) $_SERVER[ $key ] ) );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) && 'XX' !== $cc && 'T1' !== $cc ) {
				return $cc;
			}
		}
		return '';
	}

	/**
	 * País desde el mapa local CIDR=PAIS configurado por el admin.
	 *
	 * @param string $ip IP del visitante.
	 * @return string Código de país o ''.
	 */
	private static function country_from_local( $ip ) {
		$s = self::settings();
		if ( '' === trim( $s['local_map'] ) || '' === $ip ) {
			return '';
		}
		$ip_long = ip2long( $ip );
		if ( false === $ip_long ) {
			return '';
		}
		$lines = preg_split( '/\r\n|\r|\n/', $s['local_map'] );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			list( $range, $country ) = explode( '=', $line, 2 );
			$range = trim( $range );
			if ( false !== strpos( $range, '/' ) ) {
				list( $base, $bits ) = explode( '/', $range, 2 );
				$base_long = ip2long( trim( $base ) );
				$bits      = (int) trim( $bits );
				if ( false === $base_long || $bits < 0 || $bits > 32 ) {
					continue;
				}
				$mask = -1 << ( 32 - $bits );
				if ( ( $ip_long & $mask ) === ( $base_long & $mask ) ) {
					return $country;
				}
			} elseif ( $range === $ip ) {
				return $country;
			}
		}
		return '';
	}

	/**
	 * País desde una API externa (solo si el admin la habilitó), con caché
	 * agresivo en transients. Fallo silencioso: devuelve ''.
	 *
	 * @param string $ip IP del visitante.
	 * @return string Código de país o ''.
	 */
	private static function country_from_api( $ip ) {
		$s = self::settings();
		if ( empty( $s['api_enabled'] ) || '' === $ip || '' === $s['api_url'] ) {
			return '';
		}
		$cache_key = 'lgv_geoip_' . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return 'NONE' === $cached ? '' : $cached;
		}
		$url = str_replace( '{ip}', rawurlencode( $ip ), $s['api_url'] );
		$res = wp_remote_get(
			$url,
			array(
				'timeout'    => 5,
				'user-agent' => 'LinguaVisual/' . LGV_VERSION,
			)
		);
		if ( is_wp_error( $res ) ) {
			set_transient( $cache_key, 'NONE', HOUR_IN_SECONDS );
			return '';
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$cc   = '';
		if ( is_array( $body ) ) {
			foreach ( array( 'countryCode', 'country_code', 'country' ) as $field ) {
				if ( ! empty( $body[ $field ] ) && is_string( $body[ $field ] ) ) {
					$candidate = strtoupper( trim( $body[ $field ] ) );
					if ( preg_match( '/^[A-Z]{2}$/', $candidate ) ) {
						$cc = $candidate;
						break;
					}
				}
			}
		}
		set_transient( $cache_key, '' === $cc ? 'NONE' : $cc, DAY_IN_SECONDS * (int) $s['api_cache_days'] );
		return $cc;
	}

	/**
	 * País para una IP con la cadena de proveedores: Cloudflare → mapa
	 * local → API externa (si está habilitada).
	 *
	 * @param string $ip IP del visitante.
	 * @return array [ 'country' => string, 'source' => 'cloudflare'|'local'|'api'|'' ]
	 */
	public static function country_for_ip( $ip ) {
		$cc = self::country_from_cloudflare();
		if ( '' !== $cc ) {
			return array( 'country' => $cc, 'source' => 'cloudflare' );
		}
		$cc = self::country_from_local( $ip );
		if ( '' !== $cc ) {
			return array( 'country' => $cc, 'source' => 'local' );
		}
		$cc = self::country_from_api( $ip );
		if ( '' !== $cc ) {
			return array( 'country' => $cc, 'source' => 'api' );
		}
		return array( 'country' => '', 'source' => '' );
	}

	/**
	 * Parsea el header Accept-Language a subtags primarios ordenados por q.
	 * Ej.: "fr-CA,fr;q=0.9,en;q=0.8" → [ 'fr', 'en' ].
	 *
	 * @param string $header Valor del header.
	 * @return array Lista de códigos (minúsculas, sin duplicados).
	 */
	public static function parse_accept_language( $header ) {
		$out   = array();
		$parts = explode( ',', (string) $header );
		$rated = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			$sub = explode( ';', $part, 2 );
			$tag = strtolower( trim( $sub[0] ) );
			if ( '' === $tag || '*' === $tag ) {
				continue;
			}
			$q = 1.0;
			if ( isset( $sub[1] ) && preg_match( '/q=([0-9.]+)/', $sub[1], $m ) ) {
				$q = (float) $m[1];
			}
			$primary = explode( '-', $tag );
			$primary = preg_replace( '/[^a-z0-9]/', '', $primary[0] );
			if ( '' === $primary ) {
				continue;
			}
			if ( ! isset( $rated[ $primary ] ) || $q > $rated[ $primary ] ) {
				$rated[ $primary ] = $q;
			}
		}
		arsort( $rated );
		foreach ( $rated as $code => $q ) {
			$out[] = $code;
		}
		return $out;
	}

	/**
	 * Resuelve el idioma ganador: cruza candidatos (navegador y/o país) con
	 * los idiomas activos del sitio. El primero que coincida gana.
	 *
	 * @param string $country      Código de país (puede ser '').
	 * @param array  $browser_langs Idiomas del navegador (ordenados).
	 * @param string $method       both | browser | ip.
	 * @param array  $active       Idiomas activos [ código => datos ].
	 * @return array [ 'winner' => string|null, 'tried' => array ]
	 */
	public static function resolve( $country, $browser_langs, $method, $active ) {
		$candidates = array();
		if ( 'browser' === $method || 'both' === $method ) {
			foreach ( (array) $browser_langs as $b ) {
				$b = lgv_sanitize_lang_code( $b );
				if ( '' !== $b && isset( $active[ $b ] ) && ! in_array( $b, $candidates, true ) ) {
					$candidates[] = $b;
				}
			}
		}
		if ( 'ip' === $method || 'both' === $method ) {
			foreach ( LGV_Country_Langs::for_country( $country ) as $l ) {
				if ( isset( $active[ $l ] ) && ! in_array( $l, $candidates, true ) ) {
					$candidates[] = $l;
				}
			}
		}
		return array(
			'winner' => ! empty( $candidates ) ? $candidates[0] : null,
			'tried'  => $candidates,
		);
	}

	/**
	 * Idioma de respaldo cuando la detección no encuentra coincidencia.
	 *
	 * @return string Código de idioma.
	 */
	public static function fallback_lang() {
		$s = self::settings();
		if ( '' !== $s['fallback_lang'] && LGV_Languages::exists( $s['fallback_lang'] ) ) {
			return $s['fallback_lang'];
		}
		return LGV_Languages::get_default();
	}

	/**
	 * ¿El user-agent es un bot/crawler?
	 *
	 * @param string $ua User-Agent.
	 * @return bool
	 */
	public static function is_bot( $ua ) {
		$ua = strtolower( (string) $ua );
		if ( '' === $ua ) {
			return false;
		}
		foreach ( self::$bot_patterns as $pattern ) {
			if ( false !== strpos( $ua, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Simulador para el admin: dado un país y un header Accept-Language,
	 * muestra qué idioma resultaría y por qué (sin tocar cookies ni redirigir).
	 *
	 * @param string $country        Código de país (ej. 'FR').
	 * @param string $accept_language Header Accept-Language crudo.
	 * @param string $method         both | browser | ip.
	 * @return array Resultado con la cadena de decisión.
	 */
	public static function simulate( $country, $accept_language, $method ) {
		$country     = strtoupper( trim( (string) $country ) );
		$active      = LGV_Languages::all();
		$browser     = self::parse_accept_language( $accept_language );
		$country_ok  = (bool) preg_match( '/^[A-Z]{2}$/', $country );
		$country_langs = $country_ok ? LGV_Country_Langs::for_country( $country ) : array();
		$res         = self::resolve( $country_ok ? $country : '', $browser, $method, $active );
		$winner      = $res['winner'];
		$used_fallback = false;
		if ( null === $winner ) {
			$winner        = self::fallback_lang();
			$used_fallback = true;
		}
		$steps   = array();
		$steps[] = sprintf(
			/* translators: %s: método (navegador+IP, navegador, IP). */
			__( 'Method: %s.', 'lingua-visual' ),
			'both' === $method ? __( 'browser + IP (recommended)', 'lingua-visual' ) : ( 'browser' === $method ? __( 'browser only', 'lingua-visual' ) : __( 'IP only', 'lingua-visual' ) )
		);
		if ( 'browser' === $method || 'both' === $method ) {
			$steps[] = empty( $browser )
				? __( 'Browser: no languages in the header.', 'lingua-visual' )
				/* translators: %s: lista de idiomas. */
				: sprintf( __( 'Browser: %s.', 'lingua-visual' ), implode( ', ', $browser ) );
		}
		if ( 'ip' === $method || 'both' === $method ) {
			$steps[] = $country_ok
				/* translators: 1: país, 2: lista de idiomas. */
				? sprintf( __( 'IP → country %1$s (%2$s) → likely languages: %3$s.', 'lingua-visual' ), $country, LGV_Country_Langs::country_name( $country ), empty( $country_langs ) ? '—' : implode( ', ', $country_langs ) )
				: __( 'IP → invalid or unknown country.', 'lingua-visual' );
		}
		/* translators: %s: lista de idiomas activos. */
		$steps[] = sprintf( __( 'Site active languages: %s.', 'lingua-visual' ), empty( $active ) ? '—' : implode( ', ', array_keys( $active ) ) );
		if ( $used_fallback ) {
			$steps[] = sprintf(
				/* translators: %s: idioma. */
				__( 'No matches → fallback language: %s.', 'lingua-visual' ),
				$winner
			);
		} else {
			$steps[] = sprintf(
				/* translators: %s: idioma. */
				__( 'Match → winning language: %s.', 'lingua-visual' ),
				$winner
			);
		}
		return array(
			'winner'        => $winner,
			'used_fallback' => $used_fallback,
			'steps'         => $steps,
			'browser_langs' => $browser,
			'country_langs' => $country_langs,
		);
	}

	/**
	 * Fija la cookie de "ya detectado" (30 días): la detección solo corre
	 * en la primera visita.
	 */
	private static function set_detected_cookie() {
		setcookie( self::DETECTED_COOKIE, '1', time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::DETECTED_COOKIE ] = '1';
	}

	/**
	 * Redirección automática en la primera visita (template_redirect, prioridad 0).
	 *
	 * Guardas: desactivado → no; admin/AJAX/CRON/REST → no; headers enviados →
	 * no; método no GET/HEAD → no; bot (si está activado el filtro) → no;
	 * elección explícita (prefijo en URL, ?lang=, cookie) → no; ya detectado →
	 * no; alcance "home" y no es la portada → no.
	 */
	public static function maybe_auto_redirect() {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( (string) $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		if ( ! empty( $s['exclude_bots'] ) && self::is_bot( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) ) {
			return;
		}
		// 1) Elección explícita del usuario: siempre gana, nunca se redirige.
		if ( LGV_Routing::prefixes_enabled() ) {
			$parsed = LGV_Routing::parse_lang_prefix( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/' );
			if ( null !== $parsed['lang'] ) {
				return;
			}
		}
		if ( isset( $_GET['lang'] ) ) {
			return; // Lo maneja la redirección legacy de 0.2.0.
		}
		if ( isset( $_COOKIE['lingua_lang'] ) ) {
			return;
		}
		if ( isset( $_COOKIE[ self::DETECTED_COOKIE ] ) ) {
			return;
		}
		if ( 'home' === $s['scope'] && ! is_front_page() ) {
			return;
		}

		$ip      = self::client_ip();
		$geo     = self::country_for_ip( $ip );
		$browser = self::parse_accept_language( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '' );
		$res     = self::resolve( $geo['country'], $browser, $s['method'], LGV_Languages::all() );
		$winner  = $res['winner'];
		if ( null === $winner ) {
			$winner = self::fallback_lang();
		}

		// Marcar como detectado siempre: la detección es solo primera visita.
		self::set_detected_cookie();

		$default = LGV_Languages::get_default();
		if ( $winner === $default ) {
			return; // Ya está en el idioma correcto: nada que hacer.
		}

		if ( LGV_Routing::prefixes_enabled() ) {
			$target = lgv_url_for_lang( lgv_current_page_url(), $winner );
			if ( ! empty( $s['show_notice'] ) ) {
				$target = add_query_arg( self::NOTICE_ARG, '1', $target );
			}
			nocache_headers(); // El 302 no debe quedar cacheado como definitivo.
			wp_safe_redirect( $target, 302 );
			exit;
		}

		// Sin prefijos en la URL: fijar la cookie para que el output
		// buffering traduzca (comportamiento 0.1.0).
		setcookie( 'lingua_lang', $winner, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['lingua_lang'] = $winner;
	}

	/**
	 * Aviso post-redirección: "Te cambiamos a X" con link para volver.
	 * Solo cuando la URL trae ?lgv_redir=1.
	 */
	public static function maybe_render_notice() {
		$s = self::settings();
		if ( empty( $s['enabled'] ) || empty( $s['show_notice'] ) ) {
			return;
		}
		if ( ! isset( $_GET[ self::NOTICE_ARG ] ) ) {
			return;
		}
		$current = lgv_current_lang();
		$default = LGV_Languages::get_default();
		if ( $current === $default ) {
			return;
		}
		$back_url = esc_url( lgv_url_for_lang( remove_query_arg( self::NOTICE_ARG, lgv_current_page_url() ), $default ) );
		$lang_name = esc_html( LGV_Languages::all()[ $current ]['name'] ?? $current );
		?>
		<div id="lgv-redir-notice" style="position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:99999;background:#1d2327;color:#fff;padding:10px 16px;border-radius:8px;font-size:14px;box-shadow:0 4px 16px rgba(0,0,0,.3);display:flex;gap:12px;align-items:center;max-width:90vw;">
			<span>
				<?php
				printf(
					/* translators: %s: nombre del idioma. */
					esc_html__( 'We switched you to %s based on your location.', 'lingua-visual' ),
					$lang_name
				);
				?>
				<a href="<?php echo $back_url; ?>" style="color:#72aee6;margin-left:8px;"><?php esc_html_e( 'Back', 'lingua-visual' ); ?></a>
			</span>
			<button type="button" onclick="document.getElementById('lgv-redir-notice').remove()" aria-label="<?php esc_attr_e( 'Close', 'lingua-visual' ); ?>" style="background:none;border:none;color:#fff;cursor:pointer;font-size:16px;line-height:1;">×</button>
		</div>
		<?php
	}
}
