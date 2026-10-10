<?php
/**
 * XLIFF 1.2 import/export for professional translator workflows.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles XLIFF export (translation jobs) and import (translated XLIFF).
 */
class LGV_XLIFF {

	/**
	 * Export strings as XLIFF 1.2.
	 *
	 * @param string $source_lang Source language code.
	 * @param string $target_lang Target language code.
	 * @param array  $filters     Optional. Array with 'only_untranslated' bool, 'search' string.
	 * @return string XLIFF XML.
	 */
	public static function export( $source_lang, $target_lang, $filters = array() ) {
		global $wpdb;
		$strings_table = LGV_DB::strings_table();
		$trans_table   = LGV_DB::translations_table();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['only_untranslated'] ) ) {
			$where[] = "NOT EXISTS (SELECT 1 FROM {$trans_table} t WHERE t.string_id = s.id AND t.lang = %s AND t.translation <> '')";
			$params[] = $target_lang;
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[]  = '(s.original LIKE %s OR s.context LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.hash, s.original, s.context, t.translation
				FROM {$strings_table} s
				LEFT JOIN {$trans_table} t ON t.string_id = s.id AND t.lang = %s
				WHERE " . implode( ' AND ', $where ) . '
				ORDER BY s.id ASC
				LIMIT 10000',
				array_merge( array( $target_lang ), $params )
			)
		);

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$xliff = $dom->createElement( 'xliff' );
		$xliff->setAttribute( 'version', '1.2' );
		$xliff->setAttribute( 'xmlns', 'urn:oasis:names:tc:xliff:document:1.2' );
		$dom->appendChild( $xliff );

		$file = $dom->createElement( 'file' );
		$file->setAttribute( 'original', 'lingua-visual' );
		$file->setAttribute( 'source-language', $source_lang );
		$file->setAttribute( 'target-language', $target_lang );
		$file->setAttribute( 'datatype', 'plaintext' );
		$xliff->appendChild( $file );

		$body = $dom->createElement( 'body' );
		$file->appendChild( $body );

		foreach ( (array) $rows as $row ) {
			$tu = $dom->createElement( 'trans-unit' );
			$tu->setAttribute( 'id', (string) $row->id );
			$tu->setAttribute( 'resname', $row->hash . ( $row->context ? ':' . $row->context : '' ) );

			$source = $dom->createElement( 'source' );
			$source->appendChild( $dom->createTextNode( $row->original ) );
			$tu->appendChild( $source );

			$target = $dom->createElement( 'target' );
			if ( null !== $row->translation && '' !== $row->translation ) {
				$target->appendChild( $dom->createTextNode( $row->translation ) );
				$target->setAttribute( 'state', 'translated' );
			} else {
				$target->setAttribute( 'state', 'new' );
			}
			$tu->appendChild( $target );

			if ( $row->context ) {
				$note = $dom->createElement( 'note' );
				$note->appendChild( $dom->createTextNode( $row->context ) );
				$tu->appendChild( $note );
			}

			$body->appendChild( $tu );
		}

		return $dom->saveXML();
	}

	/**
	 * Import a translated XLIFF file.
	 *
	 * @param string $xml_content Raw XLIFF XML.
	 * @return array|WP_Error Array with 'imported', 'skipped', 'target_lang' or WP_Error.
	 */
	public static function import( $xml_content ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_content );
		if ( false === $xml ) {
			return new WP_Error( 'lgv_xliff_parse', __( 'Could not parse the XLIFF file.', 'lingua-visual' ) );
		}

		$xml->registerXPathNamespace( 'x', 'urn:oasis:names:tc:xliff:document:1.2' );
		$files = $xml->xpath( '//x:file' );
		if ( empty( $files ) ) {
			$files = $xml->xpath( '//file' );
		}
		if ( empty( $files ) ) {
			return new WP_Error( 'lgv_xliff_no_file', __( 'No <file> element found in the XLIFF.', 'lingua-visual' ) );
		}

		$imported = 0;
		$skipped  = 0;
		$target_lang = '';

		foreach ( $files as $file ) {
			$target_lang = (string) $file['target-language'];
			if ( '' === $target_lang ) {
				continue;
			}

			// Verify target language is registered.
			if ( ! LGV_Languages::exists( $target_lang ) ) {
				return new WP_Error(
					'lgv_xliff_unknown_lang',
					sprintf(
						/* translators: %s: language code */
						__( 'Target language "%s" is not registered. Register it first.', 'lingua-visual' ),
						$target_lang
					)
				);
			}

			$file->registerXPathNamespace( 'x', 'urn:oasis:names:tc:xliff:document:1.2' );
			$units = $file->xpath( './/x:trans-unit' );
			if ( empty( $units ) ) {
				$units = $file->xpath( './/trans-unit' );
			}

			foreach ( (array) $units as $tu ) {
				$tu->registerXPathNamespace( 'x', 'urn:oasis:names:tc:xliff:document:1.2' );
				$targets = $tu->xpath( './x:target' );
				if ( empty( $targets ) ) {
					$targets = $tu->xpath( './target' );
				}
				$translation = ! empty( $targets ) ? (string) $targets[0] : '';
				if ( '' === trim( $translation ) ) {
					$skipped++;
					continue;
				}

				$string_id = self::resolve_string_id( $tu );
				if ( ! $string_id ) {
					$skipped++;
					continue;
				}

				LGV_Strings::save_translation( $string_id, $target_lang, $translation, 0 );
				$imported++;
			}
		}

		return array(
			'imported'    => $imported,
			'skipped'     => $skipped,
			'target_lang' => $target_lang,
		);
	}

	/**
	 * Resolve a trans-unit to a string ID, by id attribute or resname hash.
	 *
	 * @param SimpleXMLElement $tu Trans-unit element.
	 * @return int String ID or 0.
	 */
	private static function resolve_string_id( $tu ) {
		global $wpdb;
		$strings_table = LGV_DB::strings_table();

		$string_id = (int) $tu['id'];
		if ( $string_id > 0 ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$strings_table} WHERE id = %d", $string_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				return (int) $exists;
			}
		}

		$resname = (string) $tu['resname'];
		$hash = explode( ':', $resname )[0];
		if ( '' !== $hash ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$strings_table} WHERE hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				return (int) $exists;
			}
		}

		return 0;
	}
}
