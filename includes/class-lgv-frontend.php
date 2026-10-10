<?php
/**
 * Frontend: detección de idioma, redirecciones a URLs con prefijo,
 * reescritura de enlaces internos, hreflang, output buffering y switcher.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Frontend {

	/**
	 * Guard anti-reentrancia para el filtro home_url (nuestros helpers
	 * internos también llaman a home_url()).
	 *
	 * @var bool
	 */
	private static $in_home_url_filter = false;

	/**
	 * Registra los hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_lang' ), 1 );
		// Prioridad 2 (no 20): el buffer debe arrancar ANTES que cualquier
		// callback que renderice y haga exit en template_redirect (ej.
		// Jws_Streamvid_Profile::the_main en prioridad 20, que en páginas
		// de autor carga la plantilla y termina con exit). PHP invoca el
		// callback del buffer al salir, así la traducción aplica igual.
		// maybe_redirect_lang (prioridad 1) ya resolvió las redirecciones
		// propias antes; redirect_canonical (10) sigue pudiendo redirigir
		// porque la salida está en buffer, no enviada.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 2 );
		// AJAX del frontend (admin-ajax.php) no dispara template_redirect:
		// enganchar el buffer en init. El idioma se detecta por cookie.
		add_action( 'init', array( __CLASS__, 'maybe_start_buffer_ajax' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_hreflang' ), 1 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'filter_canonical_url' ), 10, 2 );
		add_shortcode( 'lingua_switcher', array( __CLASS__, 'switcher_shortcode' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );

		// Reescritura de enlaces internos con el prefijo del idioma activo.
		$link_filters = array(
			'post_link',
			'page_link',
			'post_type_link',
			'attachment_link',
			'post_type_archive_link',
			'term_link',
			'author_link',
			'day_link',
			'month_link',
			'year_link',
			'search_link',
			'feed_link',
			'post_comments_feed_link',
			'get_pagenum_link',
		);
		foreach ( $link_filters as $filter ) {
			add_filter( $filter, array( __CLASS__, 'filter_internal_link' ), 20, 1 );
		}
		add_filter( 'home_url', array( __CLASS__, 'filter_home_url' ), 20, 4 );
		add_filter( 'wp_get_nav_menu_items', array( __CLASS__, 'filter_menu_items' ), 20, 1 );

		// Switcher Studio: ítem de menú y botón flotante.
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'maybe_add_menu_switcher' ), 20, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_floating' ), 20 );
	}

	/**
	 * ¿Se deben reescribir los enlaces en este contexto?
	 * Nunca en admin, AJAX, cron ni REST.
	 *
	 * @return bool
	 */
	private static function should_rewrite_links() {
		if ( ! LGV_Routing::prefixes_enabled() ) {
			return false;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Redirección de idioma en template_redirect (antes que redirect_canonical).
	 *
	 * - Si la URL trae el prefijo del idioma default → 301 a la canónica sin prefijo.
	 * - Si viene ?lang= válido o cookie lingua_lang → 302 a la URL con prefijo,
	 *   para que el idioma siempre termine en la URL y el caché de página
	 *   guarde una entrada por idioma.
	 * - Si la URL ya tiene el prefijo correcto pero quedó ?lang= colgado,
	 *   se limpia el parámetro con un 302.
	 */
	public static function maybe_redirect_lang() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		if ( ! LGV_Routing::prefixes_enabled() || ! LGV_Routing::legacy_redirect_enabled() ) {
			// Sin redirecciones: comportamiento 0.1.0 (?lang= válido fija la cookie).
			if ( isset( $_GET['lang'] ) ) {
				$asked = lgv_sanitize_lang_code( wp_unslash( $_GET['lang'] ) );
				if ( LGV_Languages::exists( $asked ) ) {
					self::set_lang_cookie( $asked );
				}
			}
			return;
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$req_path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH );
		foreach ( array( '/wp-login.php', '/xmlrpc.php', '/wp-cron.php' ) as $sys ) {
			if ( substr( $req_path, -strlen( $sys ) ) === $sys ) {
				return;
			}
		}

		$default  = LGV_Languages::get_default();
		$url_lang = LGV_Routing::$url_lang;

		// 1. Prefijo del idioma default en la URL → 301 a la canónica sin prefijo.
		if ( null !== $url_lang && $url_lang === $default && ! isset( $_GET['lang'] ) ) {
			$current = lgv_current_page_url();
			$target  = LGV_Routing::swap_prefix( $current, '' );
			if ( $target !== $current ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
			return;
		}

		// 2. ?lang= válido o cookie → 302 a la URL con prefijo.
		$wanted     = null;
		$from_query = false;
		if ( isset( $_GET['lang'] ) ) {
			$asked = lgv_sanitize_lang_code( wp_unslash( $_GET['lang'] ) );
			if ( LGV_Languages::exists( $asked ) ) {
				$wanted     = $asked;
				$from_query = true;
			}
		}
		if ( null === $wanted && isset( $_COOKIE['lingua_lang'] ) ) {
			$asked = lgv_sanitize_lang_code( wp_unslash( $_COOKIE['lingua_lang'] ) );
			if ( LGV_Languages::exists( $asked ) ) {
				$wanted = $asked;
			}
		}
		if ( null === $wanted ) {
			return;
		}
		self::set_lang_cookie( $wanted );

		$norm = static function ( $l ) use ( $default ) {
			return ( null === $l || $l === $default ) ? '' : $l;
		};
		if ( $norm( $wanted ) !== $norm( $url_lang ) ) {
			wp_safe_redirect( lgv_url_for_lang( lgv_current_page_url(), $wanted ), 302 );
			exit;
		}
		if ( $from_query ) {
			$current = lgv_current_page_url();
			$clean   = remove_query_arg( 'lang', $current );
			if ( $clean !== $current ) {
				wp_safe_redirect( $clean, 302 );
				exit;
			}
		}
	}

	/**
	 * Fija la cookie lingua_lang (compatibilidad con 0.1.0).
	 *
	 * @param string $lang Código de idioma.
	 */
	private static function set_lang_cookie( $lang ) {
		setcookie( 'lingua_lang', $lang, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['lingua_lang'] = $lang;
	}

	/**
	 * Arranca el output buffering en el frontend cuando hay idioma no-default.
	 * Segunda capa: traduce el HTML aunque el idioma haya llegado por
	 * ?lang= o cookie (cuando las redirecciones están desactivadas).
	 * También cubre AJAX del frontend (ej. pestañas de perfil que cargan
	 * HTML por admin-ajax.php): el idioma se detecta por cookie, ya que
	 * admin-ajax.php no lleva prefijo en la URL.
	 */
	public static function maybe_start_buffer() {
		// is_admin() es true en admin-ajax.php: permitir AJAX del frontend.
		if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( wp_doing_ajax() && ! apply_filters( 'lgv_translate_ajax', true ) ) {
			return;
		}
		$lang = lgv_current_lang();
		if ( $lang === LGV_Languages::get_default() ) {
			return;
		}
		ob_start( array( __CLASS__, 'translate_buffer' ) );
	}

	/**
	 * Arranca el buffer para requests AJAX del frontend.
	 * admin-ajax.php no dispara template_redirect, así que se engancha en
	 * init (prioridad 1). El idioma se detecta por cookie lingua_lang, ya
	 * que admin-ajax.php no lleva prefijo de idioma en la URL.
	 */
	public static function maybe_start_buffer_ajax() {
		if ( ! wp_doing_ajax() ) {
			return;
		}
		self::maybe_start_buffer();
	}

	/**
	 * Callback del buffer: reemplaza cadenas registradas por su traducción.
	 *
	 * Los bloques <script> y <style> se dejan intactos para no romper JS/CSS.
	 * Las respuestas JSON (AJAX de datos) se traducen en sus valores string
	 * (ej. {"html": "..."}), preservando la estructura; si no es JSON
	 * válido, no se toca.
	 * Las cadenas sin traducción quedan en el idioma original (fallback).
	 *
	 * @param string $buffer HTML completo.
	 * @return string HTML traducido.
	 */
	public static function translate_buffer( $buffer ) {
		$lang = lgv_current_lang();
		$map  = LGV_Strings::translation_map( $lang );
		if ( empty( $map ) || '' === $buffer ) {
			return $buffer;
		}
		$map = apply_filters( 'lgv_translation_map', $map, $lang );
		// Respuestas JSON (AJAX): traducir valores string, preservar estructura.
		$trimmed = ltrim( $buffer );
		if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {
			$decoded = json_decode( $buffer, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return wp_json_encode( self::translate_data_strings( $decoded, $map ) );
			}
			return $buffer;
		}
		$map = apply_filters( 'lgv_translation_map', $map, $lang );
		// Partimos por <script>/<style> y traducimos solo los otros fragmentos.
		$parts = preg_split(
			'/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>)/is',
			$buffer,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i % 2 ) {
				$parts[ $i ] = strtr( $part, $map );
			}
		}
		return implode( '', $parts );
	}

	/**
	 * Traduce recursivamente los valores string de un array/dato decodificado
	 * de JSON (las claves se dejan intactas para no romper el contrato con el JS).
	 *
	 * @param mixed $data Dato decodificado.
	 * @param array $map  Mapa original => traducción.
	 * @return mixed Dato con strings traducidos.
	 */
	private static function translate_data_strings( $data, $map ) {
		if ( is_string( $data ) ) {
			return strtr( $data, $map );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::translate_data_strings( $v, $map );
			}
		}
		return $data;
	}

	/**
	 * Agrega el prefijo del idioma activo a un enlace interno.
	 *
	 * @param string $url URL generada por WordPress.
	 * @return string URL con prefijo si corresponde.
	 */
	public static function filter_internal_link( $url ) {
		if ( ! self::should_rewrite_links() ) {
			return $url;
		}
		return lgv_add_lang_prefix( $url );
	}

	/**
	 * Filtro home_url: prefijo en el home y rutas internas (nunca wp-json,
	 * admin ni otras rutas del sistema).
	 *
	 * @param string $url         URL completa.
	 * @param string $path        Path pedido.
	 * @param string $orig_scheme Esquema.
	 * @param int    $blog_id     ID del blog.
	 * @return string
	 */
	public static function filter_home_url( $url, $path, $orig_scheme, $blog_id ) {
		if ( self::$in_home_url_filter ) {
			return $url;
		}
		if ( ! self::should_rewrite_links() ) {
			return $url;
		}
		self::$in_home_url_filter = true;
		$result = lgv_add_lang_prefix( $url );
		self::$in_home_url_filter = false;
		return $result;
	}

	/**
	 * Reescribe las URLs de los ítems de menú que apuntan al propio sitio
	 * (cubre los enlaces personalizados que no pasan por get_permalink).
	 *
	 * @param array $items Ítems del menú.
	 * @return array
	 */
	public static function filter_menu_items( $items ) {
		if ( ! self::should_rewrite_links() || ! is_array( $items ) ) {
			return $items;
		}
		foreach ( $items as $item ) {
			if ( isset( $item->url ) && is_string( $item->url ) && '' !== $item->url ) {
				$item->url = lgv_add_lang_prefix( $item->url );
			}
		}
		return $items;
	}

	/**
	 * Asegura que la URL canónica lleve el prefijo del idioma activo.
	 *
	 * @param string   $canonical_url Canónica calculada por WordPress.
	 * @param WP_Post|null $post      Post (si aplica).
	 * @return string
	 */
	public static function filter_canonical_url( $canonical_url, $post ) {
		if ( ! self::should_rewrite_links() ) {
			return $canonical_url;
		}
		return lgv_add_lang_prefix( $canonical_url );
	}

	/**
	 * Imprime los <link rel="alternate" hreflang> en el <head>:
	 * uno por idioma activo más x-default apuntando al idioma default.
	 */
	public static function output_hreflang() {
		if ( ! LGV_Routing::prefixes_enabled() ) {
			return;
		}
		if ( is_404() || is_search() || is_feed() ) {
			return;
		}
		$langs = LGV_Languages::all();
		if ( count( $langs ) < 2 ) {
			return;
		}
		$default = LGV_Languages::get_default();
		$base    = LGV_Routing::remove_prefix( remove_query_arg( array( 'lang', LGV_Detect::NOTICE_ARG ), lgv_current_page_url() ) );

		echo "\n<!-- Lingua Visual: hreflang -->\n";
		foreach ( $langs as $code => $data ) {
			$href = LGV_Routing::swap_prefix( $base, $code === $default ? '' : $code );
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $code ),
				esc_url( $href )
			);
		}
		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( LGV_Routing::swap_prefix( $base, '' ) )
		);
	}

	/**
	 * Shortcode [lingua_switcher]: delega en el Switcher Studio.
	 * Atributos: mode, trigger, flag_position, flag_shape, name_format, class.
	 * Backward compatible: [lingua_switcher] y show_names="0" (solo banderas).
	 *
	 * @param array $atts Atributos.
	 * @return string HTML.
	 */
	public static function switcher_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_names'    => '',
				'mode'          => '',
				'trigger'       => '',
				'flag_position' => '',
				'flag_shape'    => '',
				'name_format'   => '',
				'class'         => '',
			),
			$atts,
			'lingua_switcher'
		);
		return LGV_Switcher::render( $atts );
	}

	/**
	 * Agrega el switcher como último ítem del menú configurado.
	 *
	 * @param string   $items HTML de los ítems.
	 * @param stdClass $args  Argumentos del menú.
	 * @return string
	 */
	public static function maybe_add_menu_switcher( $items, $args ) {
		$s = LGV_Switcher::get();
		if ( '' === $s['menu_location'] || ! isset( $args->theme_location ) || $args->theme_location !== $s['menu_location'] ) {
			return $items;
		}
		if ( is_admin() ) {
			return $items;
		}
		$sw = LGV_Switcher::render(
			array(
				'mode'  => $s['menu_mode'],
				'class' => 'lgv-in-menu',
			)
		);
		if ( '' === $sw ) {
			return $items;
		}
		return $items . '<li class="menu-item lgv-sw-menu-item">' . $sw . '</li>';
	}

	/**
	 * Botón flotante fijo (si está activado en el Switcher Studio).
	 */
	public static function maybe_render_floating() {
		$s = LGV_Switcher::get();
		if ( empty( $s['floating'] ) || is_admin() ) {
			return;
		}
		$sw = LGV_Switcher::render(
			array(
				'mode'  => $s['floating_mode'],
				'class' => 'lgv-sw-float lgv-float-' . $s['floating_position'],
			)
		);
		echo $sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Registra el widget del selector.
	 */
	public static function register_widget() {
		register_widget( 'LGV_Switcher_Widget' );
	}
}

/**
 * Widget: selector de idioma.
 */
class LGV_Switcher_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'lgv_switcher',
			__( 'Language Switcher (Lingua Visual)', 'lingua-visual' ),
			array( 'description' => __( 'Displays the Lingua Visual language switcher.', 'lingua-visual' ) )
		);
	}

	/**
	 * @param array $args     Argumentos del sidebar.
	 * @param array $instance Instancia.
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== $title ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo LGV_Frontend::switcher_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array $instance Instancia.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Language', 'lingua-visual' );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'lingua-visual' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	/**
	 * @param array $new_instance Nueva.
	 * @param array $old_instance Vieja.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		return $instance;
	}
}
