<?php
/**
 * Gestor de idiomas ilimitados (guardados en opciones, sin límite de cantidad).
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Languages {

	const OPTION         = 'lgv_languages';
	const DEFAULT_OPTION = 'lgv_default_lang';

	/**
	 * Todos los idiomas registrados: [ código => [ 'name' => ..., 'flag' => ... ] ].
	 *
	 * @return array
	 */
	public static function all() {
		$langs = get_option( self::OPTION, array() );
		return is_array( $langs ) ? $langs : array();
	}

	/**
	 * ¿Existe el idioma?
	 *
	 * @param string $code Código.
	 * @return bool
	 */
	public static function exists( $code ) {
		if ( '' === $code ) {
			return false;
		}
		$langs = self::all();
		return isset( $langs[ $code ] );
	}

	/**
	 * Agrega (o actualiza) un idioma.
	 *
	 * @param string $code Código (ej. 'en').
	 * @param string $name Nombre visible (ej. 'English').
	 * @param string $flag Emoji de bandera (ej. '🇬🇧').
	 * @return true|WP_Error
	 */
	public static function add( $code, $name, $flag = '' ) {
		$code = lgv_sanitize_lang_code( $code );
		$name = sanitize_text_field( $name );
		$flag = sanitize_text_field( $flag );
		if ( '' === $code || '' === $name ) {
			return new WP_Error( 'lgv_invalid', __( 'The language code and name are required.', 'lingua-visual' ) );
		}
		$langs              = self::all();
		$langs[ $code ]     = array(
			'name' => $name,
			'flag' => $flag,
		);
		update_option( self::OPTION, $langs );
		return true;
	}

	/**
	 * Quita un idioma y sus traducciones. No permite quitar el idioma default.
	 *
	 * @param string $code Código.
	 * @return true|WP_Error
	 */
	public static function remove( $code ) {
		$code = lgv_sanitize_lang_code( $code );
		if ( $code === self::get_default() ) {
			return new WP_Error( 'lgv_default', __( 'You can\'t remove the default language. Change it first.', 'lingua-visual' ) );
		}
		$langs = self::all();
		if ( ! isset( $langs[ $code ] ) ) {
			return new WP_Error( 'lgv_missing', __( 'That language does not exist.', 'lingua-visual' ) );
		}
		unset( $langs[ $code ] );
		update_option( self::OPTION, $langs );

		global $wpdb;
		$wpdb->delete( LGV_DB::translations_table(), array( 'lang' => $code ), array( '%s' ) );
		return true;
	}

	/**
	 * Idioma default del sitio.
	 *
	 * @return string
	 */
	public static function get_default() {
		$default = get_option( self::DEFAULT_OPTION, '' );
		if ( '' !== $default && self::exists( $default ) ) {
			return $default;
		}
		$langs = self::all();
		if ( ! empty( $langs ) ) {
			$keys = array_keys( $langs );
			return $keys[0];
		}
		return 'es';
	}

	/**
	 * Cambia el idioma default.
	 *
	 * @param string $code Código.
	 * @return true|WP_Error
	 */
	public static function set_default( $code ) {
		$code = lgv_sanitize_lang_code( $code );
		if ( ! self::exists( $code ) ) {
			return new WP_Error( 'lgv_missing', __( 'That language does not exist.', 'lingua-visual' ) );
		}
		update_option( self::DEFAULT_OPTION, $code );
		return true;
	}

	/**
	 * Siembra el idioma default a partir del locale del sitio (solo si no hay ninguno).
	 */
	public static function seed_default() {
		$langs = self::all();
		if ( ! empty( $langs ) ) {
			return;
		}
		$locale = get_locale(); // ej. es_ES
		$code   = lgv_sanitize_lang_code( substr( $locale, 0, 2 ) );
		if ( '' === $code ) {
			$code = 'es';
		}
		self::add( $code, self::guess_name( $code ), '' );
		update_option( self::DEFAULT_OPTION, $code );
	}

	/**
	 * Nombre tentativo para un código conocido.
	 *
	 * @param string $code Código.
	 * @return string
	 */
	private static function guess_name( $code ) {
		$known = array(
			'es' => 'Español',
			'en' => 'English',
			'pt' => 'Português',
			'fr' => 'Français',
			'de' => 'Deutsch',
			'it' => 'Italiano',
		);
		return isset( $known[ $code ] ) ? $known[ $code ] : strtoupper( $code );
	}

	/**
	 * Etiqueta visible: "🇬🇧 English".
	 *
	 * @param string $code Código.
	 * @return string
	 */
	public static function label( $code ) {
		$langs = self::all();
		if ( ! isset( $langs[ $code ] ) ) {
			return $code;
		}
		$flag = trim( $langs[ $code ]['flag'] );
		return ( '' !== $flag ? $flag . ' ' : '' ) . $langs[ $code ]['name'];
	}
}
