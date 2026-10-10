<?php
/**
 * Modo visual: botón en la admin bar + JS click-para-traducir + AJAX.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Visual {

	/**
	 * Registra los hooks.
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_button' ), 100 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'wp_ajax_lgv_save_visual_translation', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_lgv_visual_suggest', array( __CLASS__, 'ajax_suggest' ) );
	}

	/**
	 * ¿Está activo el modo visual en esta request?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return ! is_admin()
			&& current_user_can( 'manage_options' )
			&& isset( $_GET['lgv_visual'] )
			&& '1' === (string) $_GET['lgv_visual'];
	}

	/**
	 * Botón "Traducir esta página" en la admin bar (solo manage_options, solo frontend).
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar_button( $bar ) {
		if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::is_active() ) {
			$bar->add_node(
				array(
					'id'    => 'lgv-visual-exit',
					'title' => __( '✕ Exit translation mode', 'lingua-visual' ),
					'href'  => esc_url( remove_query_arg( 'lgv_visual' ) ),
					'meta'  => array( 'class' => 'lgv-visual-exit' ),
				)
			);
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'lgv-visual-enter',
				'title' => __( '🌐 Translate this page', 'lingua-visual' ),
				'href'  => esc_url( add_query_arg( 'lgv_visual', '1' ) ),
				'meta'  => array( 'class' => 'lgv-visual-enter' ),
			)
		);
	}

	/**
	 * Encola el JS del modo visual solo cuando corresponde.
	 */
	public static function maybe_enqueue() {
		if ( ! self::is_active() ) {
			return;
		}
		wp_enqueue_script(
			'lgv-visual-mode',
			LGV_PLUGIN_URL . 'assets/js/visual-mode.js',
			array(),
			LGV_VERSION,
			true
		);
		wp_enqueue_style(
			'lgv-visual-mode',
			LGV_PLUGIN_URL . 'assets/css/visual-mode.css',
			array(),
			LGV_VERSION
		);
		$langs = array();
		foreach ( LGV_Languages::all() as $code => $data ) {
			$langs[] = array(
				'code'  => $code,
				'label' => LGV_Languages::label( $code ),
			);
		}
		wp_localize_script(
			'lgv-visual-mode',
			'lgvVisual',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'lgv_visual' ),
				'langs'       => $langs,
				'currentLang' => lgv_current_lang(),
				'i18n'        => array(
					'title'       => __( 'Translate text', 'lingua-visual' ),
					'original'    => __( 'Original text', 'lingua-visual' ),
					'lang'        => __( 'Target language', 'lingua-visual' ),
					'translation' => __( 'Translation', 'lingua-visual' ),
					'save'        => __( 'Save translation', 'lingua-visual' ),
					'cancel'      => __( 'Cancel', 'lingua-visual' ),
					'saved'       => __( 'Translation saved!', 'lingua-visual' ),
					'error'       => __( 'Could not save. Try again.', 'lingua-visual' ),
					'hint'        => __( 'Click any text on the page to translate it. You can also select a snippet and click it.', 'lingua-visual' ),
				),
			)
		);
	}

	/**
	 * AJAX: guarda una traducción creada en el modo visual.
	 */
	public static function ajax_save() {
		check_ajax_referer( 'lgv_visual', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'lingua-visual' ) ), 403 );
		}
		$original = isset( $_POST['original'] ) ? wp_unslash( $_POST['original'] ) : '';
		$original = trim( wp_strip_all_tags( (string) $original ) );
		$lang     = isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : '';
		$text     = isset( $_POST['translation'] ) ? wp_unslash( $_POST['translation'] ) : '';
		$text     = trim( wp_strip_all_tags( (string) $text, true ) );
		$context  = isset( $_POST['context'] ) ? esc_url_raw( wp_unslash( $_POST['context'] ) ) : '';
		$context  = substr( $context, 0, 191 );

		if ( '' === $original || '' === $lang || '' === $text ) {
			wp_send_json_error( array( 'message' => __( 'Missing data: original, language and translation are required.', 'lingua-visual' ) ), 400 );
		}
		if ( ! LGV_Languages::exists( $lang ) ) {
			wp_send_json_error( array( 'message' => __( 'That language is not registered.', 'lingua-visual' ) ), 400 );
		}
		if ( mb_strlen( $original ) > 2000 ) {
			wp_send_json_error( array( 'message' => __( 'The original text is too long (max. 2000 characters).', 'lingua-visual' ) ), 400 );
		}
		$ok = LGV_Strings::save( $original, $lang, $text, $context );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not save to the database.', 'lingua-visual' ) ), 500 );
		}
		wp_send_json_success(
			array(
				'message' => __( 'Translation saved.', 'lingua-visual' ),
				'lang'    => $lang,
			)
		);
	}

	/**
	 * AJAX: sugerencia de traducción.
	 *
	 * Por defecto el diccionario es 100% manual (sin APIs pagas). Este endpoint
	 * aplica el filtro `lgv_auto_translate` para que un snippet o un futuro
	 * addon provea una sugerencia automática opcional.
	 *
	 * @example add_filter( 'lgv_auto_translate', function( $s, $original, $lang ) { ... }, 10, 3 );
	 */
	public static function ajax_suggest() {
		check_ajax_referer( 'lgv_visual', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'lingua-visual' ) ), 403 );
		}
		$original = isset( $_POST['original'] ) ? trim( wp_strip_all_tags( wp_unslash( $_POST['original'] ) ) ) : '';
		$lang     = isset( $_POST['lang'] ) ? lgv_sanitize_lang_code( wp_unslash( $_POST['lang'] ) ) : '';
		$suggest  = apply_filters( 'lgv_auto_translate', '', $original, $lang );
		wp_send_json_success( array( 'suggestion' => is_string( $suggest ) ? $suggest : '' ) );
	}
}
