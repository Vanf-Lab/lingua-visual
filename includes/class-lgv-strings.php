<?php
/**
 * CRUD de cadenas originales y traducciones + importación/exportación CSV.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Strings {

	/**
	 * Hash estable de una cadena (original + contexto).
	 *
	 * @param string $original Texto original.
	 * @param string $context  Contexto opcional (ej. URL donde se vio).
	 * @return string md5 de 32 chars.
	 */
	public static function hash( $original, $context = '' ) {
		return md5( $context . "\0" . $original );
	}

	/**
	 * Registra una cadena original si no existe y devuelve su id.
	 *
	 * @param string $original Texto original.
	 * @param string $context  Contexto opcional.
	 * @return int|false ID de la cadena.
	 */
	public static function register( $original, $context = '' ) {
		global $wpdb;
		$original = trim( (string) $original );
		$context  = substr( sanitize_text_field( $context ), 0, 191 );
		if ( '' === $original ) {
			return false;
		}
		$table = LGV_DB::strings_table();
		$hash  = self::hash( $original, $context );

		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE hash = %s", $hash ) );
		if ( $id ) {
			return (int) $id;
		}
		$wpdb->insert(
			$table,
			array(
				'hash'       => $hash,
				'original'   => $original,
				'context'    => $context,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Guarda (inserta o actualiza) una traducción.
	 *
	 * @param int    $string_id  ID de la cadena.
	 * @param string $lang       Código de idioma.
	 * @param string $text       Traducción.
	 * @param int    $auto       1 = traducción automática (pendiente de revisión), 0 = manual.
	 * @return bool
	 */
	public static function save_translation( $string_id, $lang, $text, $auto = 0 ) {
		global $wpdb;
		$string_id = (int) $string_id;
		$lang      = lgv_sanitize_lang_code( $lang );
		$text      = trim( (string) $text );
		if ( $string_id <= 0 || '' === $lang || '' === $text ) {
			return false;
		}
		if ( ! LGV_Languages::exists( $lang ) ) {
			return false;
		}
		$table = LGV_DB::translations_table();
		$done  = $wpdb->replace(
			$table,
			array(
				'string_id'   => $string_id,
				'lang'        => $lang,
				'translation' => $text,
				'auto'        => $auto ? 1 : 0,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
		return false !== $done;
	}

	/**
	 * Atajo: registra la cadena y guarda su traducción en un paso.
	 *
	 * @param string $original Texto original.
	 * @param string $lang     Código de idioma.
	 * @param string $text     Traducción.
	 * @param string $context  Contexto opcional.
	 * @param int    $auto     1 = automática (pendiente de revisión).
	 * @return bool
	 */
	public static function save( $original, $lang, $text, $context = '', $auto = 0 ) {
		$id = self::register( $original, $context );
		if ( ! $id ) {
			return false;
		}
		return self::save_translation( $id, $lang, $text, $auto );
	}

	/**
	 * Mapa original => traducción para un idioma (lo que el frontend reemplaza).
	 *
	 * Solo trae traducciones no vacías. Se ordena por longitud descendente del
	 * original para que las cadenas largas ganen sobre sus subcadenas.
	 *
	 * @param string $lang Código de idioma.
	 * @return array
	 */
	public static function translation_map( $lang ) {
		global $wpdb;
		static $cache = array();
		if ( isset( $cache[ $lang ] ) ) {
			return $cache[ $lang ];
		}
		$map = array();
		if ( ! LGV_Languages::exists( $lang ) || $lang === LGV_Languages::get_default() ) {
			$cache[ $lang ] = $map;
			return $map;
		}
		$strings = LGV_DB::strings_table();
		$trans   = LGV_DB::translations_table();
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.original, t.translation
				 FROM {$trans} t
				 INNER JOIN {$strings} s ON s.id = t.string_id
				 WHERE t.lang = %s AND t.translation <> ''
				 ORDER BY CHAR_LENGTH(s.original) DESC",
				$lang
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $map[ $row['original'] ] ) ) {
				$map[ $row['original'] ] = $row['translation'];
			}
		}
		$cache[ $lang ] = $map;
		return $map;
	}

	/**
	 * Lista paginada de cadenas con sus traducciones por idioma.
	 *
	 * @param string $search  Búsqueda opcional.
	 * @param int    $page    Página (1-based).
	 * @param int    $per     Por página.
	 * @param array  $filters Filtros opcionales: [ 'auto_only' => bool ].
	 * @return array [ 'items' => [...], 'total' => int ]
	 */
	public static function list( $search = '', $page = 1, $per = 50, $filters = array() ) {
		global $wpdb;
		$strings = LGV_DB::strings_table();
		$trans   = LGV_DB::translations_table();
		$where   = '';
		$params  = array();
		if ( '' !== $search ) {
			$where    = 'WHERE s.original LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( ! empty( $filters['auto_only'] ) ) {
			$where .= ( '' === $where ? 'WHERE ' : ' AND ' )
				. "EXISTS (SELECT 1 FROM {$trans} t2 WHERE t2.string_id = s.id AND t2.auto = 1)";
		}
		$total = (int) $wpdb->get_var(
			$params ? $wpdb->prepare( "SELECT COUNT(*) FROM {$strings} s {$where}", ...$params ) : "SELECT COUNT(*) FROM {$strings} s {$where}"
		);
		$page   = max( 1, (int) $page );
		$offset = ( $page - 1 ) * $per;
		$sql    = "SELECT s.* FROM {$strings} s {$where} ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
		$params = array_merge( $params, array( $per, $offset ) );
		$items  = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

		$langs = array_keys( LGV_Languages::all() );
		if ( $items && $langs ) {
			$ids   = array_map( 'intval', wp_list_pluck( $items, 'id' ) );
			$in    = implode( ',', $ids );
			$trows = $wpdb->get_results( "SELECT string_id, lang, translation, auto FROM {$trans} WHERE string_id IN ({$in})", ARRAY_A );
			$by_id = array();
			foreach ( (array) $trows as $t ) {
				$by_id[ (int) $t['string_id'] ][ $t['lang'] ] = array(
					'text' => $t['translation'],
					'auto' => (int) $t['auto'],
				);
			}
			foreach ( $items as &$item ) {
				$item['translations'] = isset( $by_id[ (int) $item['id'] ] ) ? $by_id[ (int) $item['id'] ] : array();
			}
			unset( $item );
		}
		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Borra una cadena y todas sus traducciones.
	 *
	 * @param int $string_id ID.
	 * @return bool
	 */
	public static function delete( $string_id ) {
		global $wpdb;
		$string_id = (int) $string_id;
		if ( $string_id <= 0 ) {
			return false;
		}
		$wpdb->delete( LGV_DB::translations_table(), array( 'string_id' => $string_id ), array( '%d' ) );
		return (bool) $wpdb->delete( LGV_DB::strings_table(), array( 'id' => $string_id ), array( '%d' ) );
	}

	/**
	 * Exporta el diccionario a CSV: lang,original,translation,context.
	 *
	 * @param string $lang Código, o '' para todos.
	 */
	public static function export_csv( $lang = '' ) {
		global $wpdb;
		$strings = LGV_DB::strings_table();
		$trans   = LGV_DB::translations_table();
		if ( '' !== $lang ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.lang, s.original, t.translation, s.context
					 FROM {$trans} t INNER JOIN {$strings} s ON s.id = t.string_id
					 WHERE t.lang = %s ORDER BY s.original",
					$lang
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT t.lang, s.original, t.translation, s.context
				 FROM {$trans} t INNER JOIN {$strings} s ON s.id = t.string_id
				 ORDER BY t.lang, s.original",
				ARRAY_A
			);
		}
		$filename = 'lingua-visual-' . ( '' !== $lang ? $lang . '-' : 'all-' ) . gmdate( 'Ymd-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo "\xEF\xBB\xBF"; // BOM para Excel.
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'lang', 'original', 'translation', 'context' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv( $out, array( $row['lang'], $row['original'], $row['translation'], $row['context'] ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Importa un CSV con columnas lang,original,translation[,context].
	 *
	 * @param string $path Ruta del archivo subido.
	 * @return array [ 'imported' => int, 'skipped' => int, 'errors' => array ]
	 */
	public static function import_csv( $path ) {
		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			$result['errors'][] = __( 'Could not open the file.', 'lingua-visual' );
			return $result;
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			$result['errors'][] = __( 'The CSV is empty.', 'lingua-visual' );
			fclose( $handle );
			return $result;
		}
		$header = array_map(
			function ( $h ) {
				return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) );
			},
			$header
		);
		$idx_lang  = array_search( 'lang', $header, true );
		$idx_orig  = array_search( 'original', $header, true );
		$idx_trans = array_search( 'translation', $header, true );
		$idx_ctx   = array_search( 'context', $header, true );
		if ( false === $idx_lang || false === $idx_orig || false === $idx_trans ) {
			$result['errors'][] = __( 'The CSV needs the columns: lang, original, translation.', 'lingua-visual' );
			fclose( $handle );
			return $result;
		}
		$line = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$line++;
			$lang  = lgv_sanitize_lang_code( isset( $row[ $idx_lang ] ) ? $row[ $idx_lang ] : '' );
			$orig  = isset( $row[ $idx_orig ] ) ? trim( $row[ $idx_orig ] ) : '';
			$trans = isset( $row[ $idx_trans ] ) ? trim( $row[ $idx_trans ] ) : '';
			$ctx   = ( false !== $idx_ctx && isset( $row[ $idx_ctx ] ) ) ? $row[ $idx_ctx ] : '';
			if ( '' === $lang || '' === $orig || '' === $trans ) {
				$result['skipped']++;
				continue;
			}
			if ( ! LGV_Languages::exists( $lang ) ) {
				$result['errors'][] = sprintf(
					/* translators: %1$d línea, %2$s código de idioma */
					__( 'Line %1$d: language "%2$s" is not registered, skipped.', 'lingua-visual' ),
					$line,
					$lang
				);
				$result['skipped']++;
				continue;
			}
			if ( self::save( $orig, $lang, $trans, $ctx ) ) {
				$result['imported']++;
			} else {
				$result['skipped']++;
			}
		}
		fclose( $handle );
		return $result;
	}
}
