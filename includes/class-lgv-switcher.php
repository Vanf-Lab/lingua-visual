<?php
/**
 * Switcher Studio: ajustes, banderas SVG y render del selector de idioma.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Switcher {

	const OPTION = 'lgv_switcher_settings';

	/**
	 * ¿Ya se imprimieron los assets del switcher en esta request?
	 *
	 * @var bool
	 */
	private static $assets_done = false;

	/**
	 * Defaults de todos los ajustes del switcher.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Modo y comportamiento.
			'mode'              => 'dropdown', // dropdown|inline|flags|text|flag_text|opposite|pill_modal
			'trigger'           => 'click',    // click|hover (dropdown)
			'open_direction'    => 'down',     // down|up
			'animation'         => 'fade',     // none|fade|slide
			'show_current'      => 1,          // mostrar el idioma actual dentro de la lista
			'show_default'      => 1,          // mostrar el idioma default en el switcher
			'hide_current'      => 0,          // ocultar el idioma actual de la lista (estilo Polylang)
			'order_mode'        => 'manual',   // manual|alpha
			'lang_order'        => array(),
			'hide_empty'        => 0,          // ocultar idiomas sin ninguna traducción
			'search'            => 1,          // buscador dentro del dropdown/modal
			'search_threshold'  => 6,          // mostrar buscador desde N idiomas
			'close_outside'     => 1,
			// Banderas y texto.
			'flag_position'     => 'before',   // before|after|hidden
			'flag_shape'        => 'rounded',  // rect|rounded|circle
			'flag_size'         => 20,
			'name_format'       => 'native',   // native|iso|none (+ etiqueta custom por idioma)
			'show_tooltip'      => 1,          // tooltip con el nombre del idioma al pasar el mouse
			// Estilo.
			'bg'                => '#ffffff',
			'bg_hover'          => '#f1f5f9',
			'text'              => '#1f2937',
			'text_hover'        => '#111827',
			'border'            => '#d1d5db',
			'border_width'      => 1,
			'border_radius'     => 10,
			'padding_v'         => 8,
			'padding_h'         => 12,
			'font_size'         => 14,
			'font_weight'       => '500',
			'font_family'       => 'system',   // system|serif|mono|inherit
			'shadow'            => 1,
			'custom_css'        => '',
			'disable_assets'    => 0,
			// Móvil.
			'mobile_enabled'    => 1,
			'mobile_breakpoint' => 768,
			'mobile_mode'       => 'flags',    // same|flags|drawer
			// Ubicación.
			'menu_location'     => '',
			'menu_mode'         => 'flag_text',
			'floating'          => 0,
			'floating_position' => 'bottom-right',
			'floating_mode'     => 'dropdown',
			// Píldora.
			'pill_label'        => 'auto',     // auto = "Idioma"/"Language" según idioma activo
		);
	}

	/**
	 * Ajustes actuales fusionados con defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Sanea los ajustes del formulario admin (sin persistir).
	 *
	 * @param array $input Crudo de $_POST['sw'].
	 * @return array Ajustes saneados.
	 */
	public static function sanitize_settings( $input ) {
		$d     = self::defaults();
		$clean = array();

		$in_list = function ( $key, $allowed ) use ( $input, $d ) {
			$v = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : $d[ $key ];
			return in_array( $v, $allowed, true ) ? $v : $d[ $key ];
		};

		$clean['mode']           = $in_list( 'mode', array( 'dropdown', 'inline', 'flags', 'text', 'flag_text', 'opposite', 'pill_modal' ) );
		$clean['trigger']        = $in_list( 'trigger', array( 'click', 'hover' ) );
		$clean['open_direction'] = $in_list( 'open_direction', array( 'down', 'up' ) );
		$clean['animation']      = $in_list( 'animation', array( 'none', 'fade', 'slide' ) );
		$clean['flag_position']  = $in_list( 'flag_position', array( 'before', 'after', 'hidden' ) );
		$clean['flag_shape']     = $in_list( 'flag_shape', array( 'rect', 'rounded', 'circle' ) );
		$clean['name_format']    = $in_list( 'name_format', array( 'native', 'iso', 'none' ) );
		$clean['order_mode']     = $in_list( 'order_mode', array( 'manual', 'alpha' ) );
		$clean['font_family']    = $in_list( 'font_family', array( 'system', 'serif', 'mono', 'inherit' ) );
		$clean['font_weight']    = $in_list( 'font_weight', array( '400', '500', '600', '700' ) );
		$clean['mobile_mode']    = $in_list( 'mobile_mode', array( 'same', 'flags', 'drawer' ) );
		$clean['menu_mode']      = $in_list( 'menu_mode', array( 'dropdown', 'inline', 'flags', 'text', 'flag_text' ) );
		$clean['floating_mode']  = $in_list( 'floating_mode', array( 'dropdown', 'inline', 'flags', 'text', 'flag_text', 'pill_modal' ) );
		$clean['floating_position'] = $in_list(
			'floating_position',
			array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' )
		);

		foreach ( array( 'show_current', 'show_default', 'hide_current', 'hide_empty', 'search', 'close_outside', 'shadow', 'disable_assets', 'mobile_enabled', 'floating', 'show_tooltip' ) as $k ) {
			$clean[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
		}

		$clean['flag_size']         = max( 12, min( 48, absint( isset( $input['flag_size'] ) ? $input['flag_size'] : $d['flag_size'] ) ) );
		$clean['search_threshold']  = max( 2, min( 50, absint( isset( $input['search_threshold'] ) ? $input['search_threshold'] : $d['search_threshold'] ) ) );
		$clean['border_width']      = max( 0, min( 8, absint( isset( $input['border_width'] ) ? $input['border_width'] : $d['border_width'] ) ) );
		$clean['border_radius']     = max( 0, min( 40, absint( isset( $input['border_radius'] ) ? $input['border_radius'] : $d['border_radius'] ) ) );
		$clean['padding_v']         = max( 0, min( 32, absint( isset( $input['padding_v'] ) ? $input['padding_v'] : $d['padding_v'] ) ) );
		$clean['padding_h']         = max( 0, min( 48, absint( isset( $input['padding_h'] ) ? $input['padding_h'] : $d['padding_h'] ) ) );
		$clean['font_size']         = max( 10, min( 28, absint( isset( $input['font_size'] ) ? $input['font_size'] : $d['font_size'] ) ) );
		$clean['mobile_breakpoint'] = max( 320, min( 1200, absint( isset( $input['mobile_breakpoint'] ) ? $input['mobile_breakpoint'] : $d['mobile_breakpoint'] ) ) );

		foreach ( array( 'bg', 'bg_hover', 'text', 'text_hover', 'border' ) as $k ) {
			$c           = isset( $input[ $k ] ) ? self::sanitize_color( $input[ $k ] ) : '';
			$clean[ $k ] = '' !== $c ? $c : $d[ $k ];
		}

		$clean['custom_css'] = isset( $input['custom_css'] ) ? self::sanitize_css( (string) $input['custom_css'] ) : '';

		$menus               = array_keys( (array) get_registered_nav_menus() );
		$ml                  = isset( $input['menu_location'] ) ? sanitize_key( $input['menu_location'] ) : '';
		$clean['menu_location'] = in_array( $ml, $menus, true ) ? $ml : '';

		$order = array();
		if ( isset( $input['lang_order'] ) && is_array( $input['lang_order'] ) ) {
			foreach ( $input['lang_order'] as $c ) {
				$c = lgv_sanitize_lang_code( $c );
				if ( LGV_Languages::exists( $c ) ) {
					$order[] = $c;
				}
			}
		}
		$clean['lang_order'] = array_values( array_unique( $order ) );

		return $clean;
	}

	/**
	 * Sanea y persiste los ajustes del switcher.
	 *
	 * @param array $input Crudo de $_POST['sw'].
	 * @return array Ajustes saneados guardados.
	 */
	public static function save( $input ) {
		$clean = self::sanitize_settings( $input );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * Saneo básico de CSS custom: sin tags, sin cierres de <style>.
	 *
	 * @param string $css CSS crudo.
	 * @return string
	 */
	private static function sanitize_css( $css ) {
		$css = wp_strip_all_tags( $css );
		$css = str_ireplace( array( '</style', '<!--' ), '', $css );
		return trim( substr( $css, 0, 20000 ) );
	}

	/**
	 * Sanea un color: acepta hex (#rgb, #rrggbb), rgb()/rgba() y "transparent".
	 * Normaliza a hex en minúsculas cuando no hay transparencia, o a rgba().
	 * Backward compatible con los hex ya guardados.
	 *
	 * @param string $value Valor crudo.
	 * @return string Color saneado o '' si es inválido.
	 */
	public static function sanitize_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			$hex = strtolower( $value );
			if ( 4 === strlen( $hex ) ) {
				$hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
			}
			return $hex;
		}
		if ( 'transparent' === strtolower( $value ) ) {
			return 'rgba(0, 0, 0, 0)';
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9]*\.?[0-9]+))?\s*\)$/i', $value, $m ) ) {
			$r = min( 255, (int) $m[1] );
			$g = min( 255, (int) $m[2] );
			$b = min( 255, (int) $m[3] );
			$a = ( isset( $m[4] ) && '' !== $m[4] ) ? max( 0.0, min( 1.0, (float) $m[4] ) ) : 1.0;
			if ( $a >= 1.0 ) {
				return sprintf( '#%02x%02x%02x', $r, $g, $b );
			}
			$as = rtrim( rtrim( number_format( $a, 2, '.', '' ), '0' ), '.' );
			return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $as );
		}
		return '';
	}

	/**
	 * Presets de diseño de un clic.
	 *
	 * @return array [ slug => [ 'label' => ..., 'settings' => [...] ] ]
	 */
	public static function presets() {
		return array(
			'minimal'     => array(
				'label'    => __( 'Minimalist', 'lingua-visual' ),
				'settings' => array(
					'bg' => '#ffffff', 'bg_hover' => '#f8fafc', 'text' => '#334155',
					'text_hover' => '#0f172a', 'border' => '#e2e8f0', 'border_width' => 1,
					'border_radius' => 8, 'shadow' => 0, 'font_weight' => '500',
				),
			),
			'pill'        => array(
				'label'    => __( 'Pill', 'lingua-visual' ),
				'settings' => array(
					'bg' => '#ffffff', 'bg_hover' => '#f1f5f9', 'text' => '#1f2937',
					'text_hover' => '#111827', 'border' => '#d1d5db', 'border_width' => 1,
					'border_radius' => 40, 'shadow' => 1, 'font_weight' => '600',
				),
			),
			'dark'        => array(
				'label'    => __( 'Dark', 'lingua-visual' ),
				'settings' => array(
					'bg' => '#1f2937', 'bg_hover' => '#374151', 'text' => '#f9fafb',
					'text_hover' => '#ffffff', 'border' => '#374151', 'border_width' => 1,
					'border_radius' => 10, 'shadow' => 1, 'font_weight' => '500',
				),
			),
			'corporate'   => array(
				'label'    => __( 'Corporate blue', 'lingua-visual' ),
				'settings' => array(
					'bg' => '#1d4ed8', 'bg_hover' => '#1e40af', 'text' => '#ffffff',
					'text_hover' => '#ffffff', 'border' => '#1e40af', 'border_width' => 1,
					'border_radius' => 6, 'shadow' => 1, 'font_weight' => '600',
				),
			),
			'flags-only'  => array(
				'label'    => __( 'Flags only', 'lingua-visual' ),
				'settings' => array(
					'mode' => 'flags', 'bg' => 'transparent', 'border_width' => 0,
					'shadow' => 0, 'flag_size' => 24, 'flag_shape' => 'circle',
				),
			),
			'underline'   => array(
				'label'    => __( 'Editorial underline', 'lingua-visual' ),
				'settings' => array(
					'mode' => 'inline', 'bg' => 'transparent', 'border_width' => 0,
					'shadow' => 0, 'text' => '#1f2937', 'text_hover' => '#b91c1c',
					'flag_position' => 'hidden', 'name_format' => 'native', 'font_weight' => '600',
				),
			),
		);
	}

	/* ================= Banderas ================= */

	/**
	 * Variantes regionales de bandera por idioma: [ lang => [ cc => etiqueta ] ].
	 * Basado en lo que GTranslate/WPML/Weglot ofrecen y los usuarios piden.
	 *
	 * @return array
	 */
	public static function flag_variants() {
		return array(
			'en' => array( 'gb' => 'Reino Unido', 'us' => 'Estados Unidos', 'ca' => 'Canadá', 'au' => 'Australia' ),
			'es' => array( 'es' => 'España', 'mx' => 'México', 'ar' => 'Argentina', 'co' => 'Colombia' ),
			'pt' => array( 'pt' => 'Portugal', 'br' => 'Brasil' ),
			'fr' => array( 'fr' => 'Francia', 'ca' => 'Canadá (Quebec)' ),
			'de' => array( 'de' => 'Alemania', 'at' => 'Austria', 'ch' => 'Suiza' ),
			'zh' => array( 'cn' => 'China (simplificado)', 'tw' => 'Taiwán' ),
			'nl' => array( 'nl' => 'Países Bajos', 'be' => 'Bélgica' ),
			'ar' => array( 'sa' => 'Arabia Saudita', 'eg' => 'Egipto', 'ma' => 'Marruecos' ),
		);
	}

	/**
	 * Variante default para un idioma (la que se usa si no se eligió otra).
	 *
	 * @param string $code Código de idioma.
	 * @return string Código de país (cc) o '' si no hay SVG.
	 */
	public static function default_variant( $code ) {
		$map = array(
			'en' => 'gb', 'es' => 'es', 'pt' => 'pt', 'fr' => 'fr',
			'de' => 'de', 'zh' => 'cn', 'nl' => 'nl', 'ar' => 'sa',
		);
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		// Si el código de idioma coincide con un país con SVG, usarlo (it→it, ja→jp no).
		$guess = array(
			'it' => 'it', 'ja' => 'jp', 'ko' => 'kr', 'ru' => 'ru', 'hi' => 'in',
			'tr' => 'tr', 'pl' => 'pl', 'sv' => 'se', 'da' => 'dk', 'fi' => 'fi',
			'no' => 'no', 'el' => 'gr', 'he' => 'il', 'cs' => 'cz', 'ro' => 'ro',
			'hu' => 'hu', 'uk' => 'ua', 'id' => 'id', 'ms' => 'my', 'th' => 'th',
			'vi' => 'vn', 'fa' => 'ir', 'ur' => 'pk', 'bn' => 'bd', 'ta' => 'in',
			'te' => 'in', 'mr' => 'in', 'pa' => 'in', 'gu' => 'in', 'kn' => 'in',
		);
		return isset( $guess[ $code ] ) ? $guess[ $code ] : '';
	}

	/**
	 * Lista de códigos de país con SVG disponible (cacheada).
	 *
	 * @return array
	 */
	private static function available_flags() {
		static $list = null;
		if ( null === $list ) {
			$list  = array();
			$files = glob( LGV_PLUGIN_DIR . 'assets/flags/*.svg' );
			if ( is_array( $files ) ) {
				foreach ( $files as $f ) {
					$list[] = basename( $f, '.svg' );
				}
			}
		}
		return $list;
	}

	/**
	 * País (cc) resuelto para la bandera de un idioma.
	 *
	 * @param string $code Código de idioma.
	 * @return string cc o '' (entonces se usa emoji legacy o globo).
	 */
	public static function flag_country( $code ) {
		$langs = LGV_Languages::all();
		$cc    = '';
		if ( isset( $langs[ $code ]['flag_variant'] ) && '' !== $langs[ $code ]['flag_variant'] ) {
			$cc = sanitize_key( $langs[ $code ]['flag_variant'] );
		} else {
			$cc = self::default_variant( $code );
		}
		if ( '' !== $cc && in_array( $cc, self::available_flags(), true ) ) {
			return $cc;
		}
		return '';
	}

	/**
	 * HTML de la bandera de un idioma: SVG custom > variante SVG > emoji legacy > globo.
	 *
	 * @param string $code Código de idioma.
	 * @return string HTML.
	 */
	public static function flag_html( $code ) {
		$langs = LGV_Languages::all();
		$d     = isset( $langs[ $code ] ) ? $langs[ $code ] : array();

		if ( ! empty( $d['flag_svg'] ) ) {
			return '<span class="lgv-sw-flag lgv-sw-flag-custom" aria-hidden="true">' . $d['flag_svg'] . '</span>';
		}
		$cc = self::flag_country( $code );
		if ( '' === $cc && ! empty( $d['flag'] ) ) {
			return '<span class="lgv-sw-flag lgv-sw-flag-emoji" aria-hidden="true">' . esc_html( $d['flag'] ) . '</span>';
		}
		if ( '' === $cc ) {
			$cc = 'globe';
		}
		$src = LGV_PLUGIN_URL . 'assets/flags/' . $cc . '.svg';
		return '<span class="lgv-sw-flag" aria-hidden="true"><img src="' . esc_url( $src ) . '" alt="" loading="lazy" draggable="false"></span>';
	}

	/**
	 * Globo SVG inline (para la píldora estilo Airbnb).
	 *
	 * @return string
	 */
	public static function globe_svg() {
		return '<svg class="lgv-sw-globe" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><ellipse cx="12" cy="12" rx="4" ry="9" fill="none" stroke="currentColor" stroke-width="1.4"/><line x1="3" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.4"/><line x1="5.5" y1="7.5" x2="18.5" y2="7.5" stroke="currentColor" stroke-width="1.1"/><line x1="5.5" y1="16.5" x2="18.5" y2="16.5" stroke="currentColor" stroke-width="1.1"/></svg>';
	}

	/* ================= Etiquetas ================= */

	/**
	 * Nombre nativo registrado del idioma.
	 *
	 * @param string $code Código.
	 * @return string
	 */
	public static function native_name( $code ) {
		$langs = LGV_Languages::all();
		return isset( $langs[ $code ]['name'] ) ? $langs[ $code ]['name'] : strtoupper( $code );
	}

	/**
	 * Etiqueta visible según el formato configurado (+ override custom por idioma).
	 *
	 * @param string $code Código de idioma.
	 * @param array  $s    Ajustes.
	 * @return string
	 */
	public static function label_for( $code, $s ) {
		$langs = LGV_Languages::all();
		if ( isset( $langs[ $code ]['label_custom'] ) && '' !== trim( $langs[ $code ]['label_custom'] ) ) {
			return $langs[ $code ]['label_custom'];
		}
		switch ( $s['name_format'] ) {
			case 'iso':
				return strtoupper( $code );
			case 'none':
				return '';
			case 'native':
			default:
				return self::native_name( $code );
		}
	}

	/**
	 * Etiqueta de la píldora ("Idioma"/"Language"/… según idioma activo).
	 *
	 * @param string $current Idioma activo.
	 * @return string
	 */
	public static function pill_label( $current ) {
		$map = array(
			'es' => 'Idioma', 'en' => 'Language', 'pt' => 'Idioma', 'fr' => 'Langue',
			'de' => 'Sprache', 'it' => 'Lingua', 'ca' => 'Idioma', 'gl' => 'Idioma',
			'eu' => 'Hizkuntza', 'nl' => 'Taal',
		);
		return isset( $map[ $current ] ) ? $map[ $current ] : 'Language';
	}

	/* ================= Orden y visibilidad ================= */

	/**
	 * Códigos de idioma a mostrar, con orden y filtros de visibilidad aplicados.
	 *
	 * @return array
	 */
	public static function ordered_langs() {
		$s       = self::get();
		$langs   = LGV_Languages::all();
		$default = LGV_Languages::get_default();

		if ( empty( $s['show_default'] ) ) {
			unset( $langs[ $default ] );
		}
		if ( ! empty( $s['hide_empty'] ) ) {
			foreach ( $langs as $code => $data ) {
				if ( $code === $default ) {
					continue; // el default es el idioma fuente, siempre se muestra
				}
				$map = LGV_Strings::translation_map( $code );
				if ( empty( $map ) ) {
					unset( $langs[ $code ] );
				}
			}
		}
		$codes = array_keys( $langs );
		if ( 'alpha' === $s['order_mode'] ) {
			usort(
				$codes,
				function ( $a, $b ) {
					return strcasecmp( self::native_name( $a ), self::native_name( $b ) );
				}
			);
		} else {
			$order = array();
			foreach ( (array) $s['lang_order'] as $c ) {
				if ( isset( $langs[ $c ] ) ) {
					$order[] = $c;
				}
			}
			foreach ( $codes as $c ) {
				if ( ! in_array( $c, $order, true ) ) {
					$order[] = $c;
				}
			}
			$codes = $order;
		}
		return $codes;
	}

	/**
	 * URL base forzada para el preview del admin (en AJAX no hay página real).
	 *
	 * @var string|null
	 */
	public static $preview_base = null;

	/**
	 * URL del switcher hacia un idioma (misma página, otro idioma).
	 *
	 * @param string      $code Idioma destino.
	 * @param string|null $base URL base (default: página actual).
	 * @return string
	 */
	public static function url_for( $code, $base = null ) {
		if ( null === $base ) {
			$base = null !== self::$preview_base ? self::$preview_base : lgv_current_page_url();
		}
		if ( LGV_Routing::prefixes_enabled() ) {
			return lgv_url_for_lang( $base, $code );
		}
		return add_query_arg( 'lang', $code, $base );
	}

	/* ================= Render ================= */

	/**
	 * Renderiza el switcher completo según modo y ajustes.
	 *
	 * @param array $atts Atributos del shortcode (override parcial).
	 * @return string HTML.
	 */
	public static function render( $atts = array() ) {
		$s = self::get();

		// Overrides del shortcode (backward compatible con show_names).
		if ( isset( $atts['show_names'] ) ) {
			if ( '0' === (string) $atts['show_names'] || 'false' === strtolower( (string) $atts['show_names'] ) ) {
				$s['mode'] = 'flags';
			}
		}
		foreach ( array( 'mode', 'trigger', 'flag_position', 'flag_shape', 'name_format' ) as $k ) {
			if ( isset( $atts[ $k ] ) && '' !== $atts[ $k ] ) {
				$s[ $k ] = sanitize_key( $atts[ $k ] );
			}
		}
		$extra_class = isset( $atts['class'] ) ? sanitize_html_class( $atts['class'] ) : '';

		$codes = self::ordered_langs();
		if ( count( $codes ) < 2 ) {
			return '';
		}
		$current = lgv_current_lang();
		if ( ! in_array( $current, $codes, true ) ) {
			$current = LGV_Languages::get_default();
		}

		$mode = $s['mode'];
		if ( 'opposite' === $mode && 2 !== count( $codes ) ) {
			$mode = 'dropdown'; // el modo opuesto solo tiene sentido con 2 idiomas
		}

		self::enqueue_assets( $s );

		$uid         = 'lgvsw' . wp_rand( 1000, 9999 );
		$shape_class = 'lgv-flag-' . $s['flag_shape'];
		$anim_class  = 'lgv-anim-' . $s['animation'];
		$dir_class   = 'down' === $s['open_direction'] ? 'lgv-open-down' : 'lgv-open-up';
		$classes     = array( 'lgv-sw', 'lgv-sw-' . $mode, $shape_class, $anim_class, $dir_class );
		if ( '' !== $extra_class ) {
			$classes[] = $extra_class;
		}
		if ( ! empty( $s['mobile_enabled'] ) && 'same' !== $s['mobile_mode'] ) {
			$classes[] = 'lgv-m-' . $s['mobile_mode'];
		}
		$attrs = sprintf(
			'data-lgv data-lgv-uid="%s" data-lgv-mode="%s" data-lgv-trigger="%s" data-lgv-close-outside="%d"',
			esc_attr( $uid ),
			esc_attr( $mode ),
			esc_attr( $s['trigger'] ),
			! empty( $s['close_outside'] ) ? 1 : 0
		);

		switch ( $mode ) {
			case 'inline':
			case 'flags':
			case 'text':
			case 'flag_text':
				$html = self::render_list( $codes, $current, $s, $mode );
				break;
			case 'opposite':
				$html = self::render_opposite( $codes, $current, $s );
				break;
			case 'pill_modal':
				$html = self::render_pill_modal( $codes, $current, $s, $uid );
				break;
			case 'dropdown':
			default:
				$html = self::render_dropdown( $codes, $current, $s, $uid );
				break;
		}

		return sprintf(
			'<div class="%s" %s>%s</div>',
			esc_attr( implode( ' ', $classes ) ),
			$attrs,
			$html
		);
	}

	/**
	 * HTML de un ítem de idioma (enlace).
	 *
	 * @param string $code    Código.
	 * @param string $current Idioma activo.
	 * @param array  $s       Ajustes.
	 * @param string $role    Rol ARIA.
	 * @return string
	 */
	private static function item_html( $code, $current, $s, $role = 'option' ) {
		$url        = self::url_for( $code );
		$label      = self::label_for( $code, $s );
		$is_current = $code === $current;
		$flag       = ( 'hidden' !== $s['flag_position'] ) ? self::flag_html( $code ) : '';

		// Tooltip: nombre del idioma destino en el idioma activo del sitio (on por default).
		$tip_name = LGV_Lang_Names::name_in( $code, $current );
		$tip      = ! empty( $s['show_tooltip'] ) ? ' data-lgv-tip="' . esc_attr( $tip_name ) . '"' : '';

		$inner = '';
		if ( 'after' === $s['flag_position'] ) {
			$inner = ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' ) . $flag;
		} else {
			$inner = $flag . ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' );
		}
		if ( '' === trim( wp_strip_all_tags( $inner ) ) && '' === $flag ) {
			$inner = '<span class="lgv-sw-label">' . esc_html( strtoupper( $code ) ) . '</span>';
		}

		return sprintf(
			'<a class="lgv-sw-item%s" href="%s" hreflang="%s" role="%s" tabindex="0" data-lang="%s" data-search="%s" aria-label="%s"%s%s>%s</a>',
			$is_current ? ' is-current' : '',
			esc_url( $url ),
			esc_attr( $code ),
			esc_attr( $role ),
			esc_attr( $code ),
			esc_attr( strtolower( self::native_name( $code ) . ' ' . $code ) ),
			esc_attr( $tip_name ),
			$is_current ? ' aria-current="true"' : '',
			$tip,
			$inner
		);
	}

	/**
	 * Ítem del idioma actual como texto plano (no enlace), estilo WPML.
	 *
	 * @param string $code Idioma.
	 * @param array  $s    Ajustes.
	 * @return string
	 */
	private static function current_text_html( $code, $s ) {
		$label = self::label_for( $code, $s );
		$flag  = ( 'hidden' !== $s['flag_position'] ) ? self::flag_html( $code ) : '';
		$inner = 'after' === $s['flag_position']
			? ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' ) . $flag
			: $flag . ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' );
		return '<span class="lgv-sw-item is-current is-text" aria-current="true">' . $inner . '</span>';
	}

	/**
	 * Lista de idiomas para dropdown/modal, aplicando show_current/hide_current.
	 *
	 * @param array  $codes   Códigos ordenados.
	 * @param string $current Activo.
	 * @param array  $s       Ajustes.
	 * @return array
	 */
	private static function list_codes( $codes, $current, $s ) {
		$out = array();
		foreach ( $codes as $code ) {
			if ( $code === $current ) {
				if ( ! empty( $s['hide_current'] ) ) {
					continue;
				}
				if ( empty( $s['show_current'] ) ) {
					continue;
				}
				$out[] = array( 'code' => $code, 'text' => true );
			} else {
				$out[] = array( 'code' => $code, 'text' => false );
			}
		}
		return $out;
	}

	/**
	 * Buscador dentro del dropdown/modal (nadie lo ofrece: diferenciador).
	 *
	 * @param array  $s     Ajustes.
	 * @param int    $count Cantidad de idiomas.
	 * @param string $id    ID único.
	 * @return string
	 */
	private static function search_html( $s, $count, $id ) {
		if ( empty( $s['search'] ) || $count < (int) $s['search_threshold'] ) {
			return '';
		}
		return sprintf(
			'<div class="lgv-sw-search"><input type="search" placeholder="%s" aria-label="%s" data-lgv-search="%s"></div>',
			esc_attr__( 'Search language…', 'lingua-visual' ),
			esc_attr__( 'Search language', 'lingua-visual' ),
			esc_attr( $id )
		);
	}

	/**
	 * Botón del dropdown con el idioma actual.
	 *
	 * @param string $current Idioma activo.
	 * @param array  $s       Ajustes.
	 * @return string
	 */
	private static function toggle_html( $current, $s ) {
		$label = self::label_for( $current, $s );
		$flag  = ( 'hidden' !== $s['flag_position'] ) ? self::flag_html( $current ) : '';
		$inner = 'after' === $s['flag_position']
			? ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' ) . $flag
			: $flag . ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' );
		return sprintf(
			'<button type="button" class="lgv-sw-toggle" aria-haspopup="listbox" aria-expanded="false"><span class="lgv-sw-toggle-inner">%s</span><svg class="lgv-sw-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>',
			$inner
		);
	}

	/**
	 * Modo dropdown.
	 */
	private static function render_dropdown( $codes, $current, $s, $uid ) {
		$items = '';
		foreach ( self::list_codes( $codes, $current, $s ) as $entry ) {
			$items .= $entry['text']
				? self::current_text_html( $entry['code'], $s )
				: self::item_html( $entry['code'], $current, $s, 'option' );
		}
		return self::toggle_html( $current, $s )
			. sprintf(
				'<div class="lgv-sw-menu" role="listbox" aria-label="%s" hidden>%s<div class="lgv-sw-menu-items">%s</div></div>',
				esc_attr__( 'Available languages', 'lingua-visual' ),
				self::search_html( $s, count( $codes ), $uid ),
				$items
			);
	}

	/**
	 * Modos de lista: inline | flags | text | flag_text.
	 */
	private static function render_list( $codes, $current, $s, $mode ) {
		$list_s = $s;
		if ( 'flags' === $mode ) {
			$list_s['name_format'] = 'none';
		} elseif ( 'text' === $mode ) {
			$list_s['flag_position'] = 'hidden';
		}
		$items = '';
		foreach ( self::list_codes( $codes, $current, $list_s ) as $entry ) {
			$items .= $entry['text']
				? self::current_text_html( $entry['code'], $list_s )
				: self::item_html( $entry['code'], $current, $list_s, 'link' );
		}
		return sprintf(
			'<nav class="lgv-sw-list" aria-label="%s">%s</nav>',
			esc_attr__( 'Language', 'lingua-visual' ),
			$items
		);
	}

	/**
	 * Modo "idioma opuesto": un solo botón que lleva al otro idioma (TranslatePress).
	 * Solo con exactamente 2 idiomas; si no, el render() ya hizo fallback.
	 */
	private static function render_opposite( $codes, $current, $s ) {
		$other = $codes[0] === $current ? $codes[1] : $codes[0];
		$label = self::label_for( $other, $s );
		$flag  = ( 'hidden' !== $s['flag_position'] ) ? self::flag_html( $other ) : '';
		$tip   = ! empty( $s['show_tooltip'] ) ? ' data-lgv-tip="' . esc_attr( LGV_Lang_Names::name_in( $other, $current ) ) . '"' : '';
		$inner = 'after' === $s['flag_position']
			? ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' ) . $flag
			: $flag . ( '' !== $label ? '<span class="lgv-sw-label">' . esc_html( $label ) . '</span>' : '' );
		return sprintf(
			'<a class="lgv-sw-opposite" href="%s" hreflang="%s" aria-label="%s"%s>%s</a>',
			esc_url( self::url_for( $other ) ),
			esc_attr( $other ),
			esc_attr( sprintf( __( 'Switch to %s', 'lingua-visual' ), LGV_Lang_Names::name_in( $other, $current ) ) ),
			$tip,
			$inner
		);
	}

	/**
	 * Píldora con globo + modal/drawer (patrón Airbnb/UE: diferenciador nuestro).
	 */
	private static function render_pill_modal( $codes, $current, $s, $uid ) {
		$items = '';
		foreach ( self::list_codes( $codes, $current, $s ) as $entry ) {
			$items .= $entry['text']
				? self::current_text_html( $entry['code'], $s )
				: self::item_html( $entry['code'], $current, $s, 'option' );
		}
		$pill = 'auto' === $s['pill_label'] ? self::pill_label( $current ) : self::label_for( $current, $s );
		return sprintf(
			'<button type="button" class="lgv-sw-pillbtn" aria-haspopup="dialog" aria-expanded="false">%s<span class="lgv-sw-label">%s</span></button>',
			self::globe_svg(),
			esc_html( $pill )
		)
		. sprintf(
			'<div class="lgv-sw-overlay" hidden><div class="lgv-sw-modal" role="dialog" aria-modal="true" aria-label="%s">'
			. '<div class="lgv-sw-modal-head"><strong>%s</strong><button type="button" class="lgv-sw-close" aria-label="%s">×</button></div>'
			. '%s<div class="lgv-sw-modal-list" role="listbox">%s</div></div></div>',
			esc_attr__( 'Choose language', 'lingua-visual' ),
			esc_html__( 'Choose your language', 'lingua-visual' ),
			esc_attr__( 'Close', 'lingua-visual' ),
			self::search_html( $s, count( $codes ), $uid ),
			$items
		);
	}

	/* ================= Assets ================= */

	/**
	 * Imprime CSS + JS del switcher una sola vez por request.
	 *
	 * @param array $s Ajustes.
	 */
	public static function enqueue_assets( $s ) {
		if ( self::$assets_done || ! empty( $s['disable_assets'] ) ) {
			return;
		}
		self::$assets_done = true;
		echo '<style id="lgv-switcher-css">' . self::css( $s ) . "</style>\n";
		$css_src = LGV_PLUGIN_URL . 'assets/css/switcher.css';
		echo '<link rel="stylesheet" href="' . esc_url( $css_src ) . '?ver=' . esc_attr( LGV_VERSION ) . '">' . "\n";
		$src = LGV_PLUGIN_URL . 'assets/js/switcher.js';
		echo '<script src="' . esc_url( $src ) . '?ver=' . esc_attr( LGV_VERSION ) . '" defer></script>' . "\n";
	}

	/**
	 * CSS del switcher con variables generadas desde los ajustes.
	 *
	 * @param array $s Ajustes.
	 * @return string CSS.
	 */
	public static function css( $s ) {
		$fonts = array(
			'system'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
			'serif'   => "Georgia, 'Times New Roman', serif",
			'mono'    => "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
			'inherit' => 'inherit',
		);
		$font = isset( $fonts[ $s['font_family'] ] ) ? $fonts[ $s['font_family'] ] : $fonts['system'];
		$bp   = (int) $s['mobile_breakpoint'];

		$css = sprintf(
			'.lgv-sw{--lgv-bg:%s;--lgv-bg-hover:%s;--lgv-text:%s;--lgv-text-hover:%s;--lgv-border:%s;'
			. '--lgv-bw:%dpx;--lgv-radius:%dpx;--lgv-padv:%dpx;--lgv-padh:%dpx;--lgv-fs:%dpx;--lgv-fw:%s;'
			. '--lgv-font:%s;--lgv-flag:%dpx;--lgv-shadow:%s;display:inline-block;position:relative;'
			. 'font-family:var(--lgv-font);font-size:var(--lgv-fs);font-weight:var(--lgv-fw);color:var(--lgv-text);line-height:1.4}',
			esc_html( $s['bg'] ), esc_html( $s['bg_hover'] ), esc_html( $s['text'] ),
			esc_html( $s['text_hover'] ), esc_html( $s['border'] ),
			(int) $s['border_width'], (int) $s['border_radius'],
			(int) $s['padding_v'], (int) $s['padding_h'], (int) $s['font_size'],
			esc_html( $s['font_weight'] ), $font, (int) $s['flag_size'],
			! empty( $s['shadow'] ) ? '0 8px 24px rgba(15,23,42,.12)' : 'none'
		);

		if ( '' !== trim( $s['custom_css'] ) ) {
			$css .= "\n" . $s['custom_css'];
		}

		// Ajustes móviles.
		if ( ! empty( $s['mobile_enabled'] ) ) {
			$css .= sprintf(
				'@media (max-width:%dpx){'
				. '.lgv-sw.lgv-m-flags .lgv-sw-label{display:none!important}'
				. '.lgv-sw.lgv-m-flags .lgv-sw-pillbtn .lgv-sw-label{display:none!important}'
				. '.lgv-sw.lgv-m-drawer .lgv-sw-overlay{align-items:stretch!important;justify-content:stretch!important;padding:0!important}'
				. '.lgv-sw.lgv-m-drawer .lgv-sw-modal{max-width:none!important;width:100%%!important;height:100%%!important;'
				. 'max-height:none!important;border-radius:0!important}'
				. '.lgv-sw.lgv-m-drawer .lgv-sw-menu{position:fixed!important;left:0!important;right:0!important;'
				. 'top:auto!important;bottom:0!important;max-height:70vh!important;border-radius:16px 16px 0 0!important}'
				. '}',
				$bp
			);
		}
		return $css;
	}

	/**
	 * Endpoint AJAX para el preview en vivo del admin.
	 */
	public static function ajax_preview() {
		// Capture ALL output (PHP warnings, echoed assets) so the JSON stays clean.
		// We strip warning lines and keep legitimate <style>/<link>/<script> output.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		@ini_set( 'display_errors', '0' );
		@error_reporting( 0 );
		ob_start();
		if ( ! current_user_can( 'manage_options' ) ) {
			ob_end_clean();
			wp_send_json_error( 'forbidden', 403 );
		}
		check_ajax_referer( 'lgv_switcher_preview', 'nonce' );
		$input = isset( $_POST['sw'] ) && is_array( $_POST['sw'] ) ? wp_unslash( $_POST['sw'] ) : array();
		$clean = self::sanitize_settings( $input );
		$had   = get_option( self::OPTION, null );
		update_option( self::OPTION, $clean );
		self::$preview_base = home_url( '/' );
		$html = self::render( array( 'class' => 'lgv-preview-instance' ) );
		self::$preview_base = null;
		if ( null === $had ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $had );
		}
		// Grab echoed output, strip PHP warning lines, keep real markup.
		$echoed = ob_get_clean();
		$echoed = preg_replace( '/^(E_)?(DEPRECATED|Deprecated|WARNING|Warning|NOTICE|Notice|Fatal error|Parse error):.*$/m', '', (string) $echoed );
		$echoed = trim( (string) $echoed );
		if ( '' !== $echoed ) {
			$html = $echoed . "\n" . $html;
		}
		if ( false === strpos( $html, 'lgv-sw' ) ) {
			wp_send_json_success(
				array(
					'html'  => '<p class="lgv-preview-empty">' . esc_html__( 'Preview unavailable: add at least two languages in the Languages tab.', 'lingua-visual' ) . '</p>',
					'empty' => true,
				)
			);
		}
		wp_send_json_success( array( 'html' => $html ) );
	}
	/* ================= Banderas y etiquetas por idioma (admin) ================= */

	/**
	 * Tags permitidos en un SVG custom subido por el admin.
	 *
	 * @return array
	 */
	private static function allowed_svg_tags() {
		$attrs = array(
			'fill' => true, 'stroke' => true, 'stroke-width' => true, 'd' => true,
			'points' => true, 'cx' => true, 'cy' => true, 'r' => true, 'x' => true,
			'y' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
			'width' => true, 'height' => true, 'rx' => true, 'ry' => true,
			'transform' => true, 'opacity' => true, 'viewBox' => true,
			'stroke-linecap' => true, 'stroke-linejoin' => true,
		);
		return array(
			'svg'      => array_merge( array( 'xmlns' => true ), $attrs ),
			'g'        => $attrs,
			'path'     => $attrs,
			'rect'     => $attrs,
			'circle'   => $attrs,
			'ellipse'  => $attrs,
			'line'     => $attrs,
			'polygon'  => $attrs,
			'polyline' => $attrs,
			'clipPath' => array( 'id' => true ),
		);
	}

	/**
	 * Guarda variante de bandera, SVG custom y etiqueta custom por idioma.
	 * Se llama desde el formulario de la pestaña Switcher.
	 *
	 * @param array $variants [ code => cc ].
	 * @param array $labels   [ code => etiqueta ].
	 * @param array $files    $_FILES filtrado (sw_flag_svg).
	 * @param array $remove   [ code => 1 ] para quitar el SVG custom.
	 */
	public static function save_per_language( $variants, $labels, $files, $remove ) {
		$langs = LGV_Languages::all();
		foreach ( $langs as $code => $data ) {
			if ( isset( $variants[ $code ] ) ) {
				$cc = sanitize_key( $variants[ $code ] );
				if ( '' === $cc || 'auto' === $cc ) {
					unset( $langs[ $code ]['flag_variant'] );
				} else {
					$langs[ $code ]['flag_variant'] = $cc;
				}
			}
			if ( isset( $labels[ $code ] ) ) {
				$lab = sanitize_text_field( wp_unslash( $labels[ $code ] ) );
				if ( '' === $lab ) {
					unset( $langs[ $code ]['label_custom'] );
				} else {
					$langs[ $code ]['label_custom'] = $lab;
				}
			}
			if ( ! empty( $remove[ $code ] ) ) {
				unset( $langs[ $code ]['flag_svg'] );
			}
			if ( isset( $files[ $code ] ) && is_array( $files[ $code ] )
				&& UPLOAD_ERR_OK === (int) $files[ $code ]['error']
				&& preg_match( '/\.svg$/i', (string) $files[ $code ]['name'] ) ) {
				$raw = file_get_contents( $files[ $code ]['tmp_name'] );
				if ( is_string( $raw ) && false !== stripos( $raw, '<svg' ) && strlen( $raw ) < 100000 ) {
					$langs[ $code ]['flag_svg'] = wp_kses( $raw, self::allowed_svg_tags() );
				}
			}
		}
		update_option( LGV_Languages::OPTION, $langs );
	}
}

