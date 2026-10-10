<?php
/**
 * Tablas propias del plugin.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_DB {

	/**
	 * Nombre de la tabla de cadenas originales.
	 *
	 * @return string
	 */
	public static function strings_table() {
		global $wpdb;
		return $wpdb->prefix . 'lingua_strings';
	}

	/**
	 * Nombre de la tabla de traducciones.
	 *
	 * @return string
	 */
	public static function translations_table() {
		global $wpdb;
		return $wpdb->prefix . 'lingua_translations';
	}

	/**
	 * Crea las tablas con dbDelta. Idempotente.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$strings = self::strings_table();
		$trans   = self::translations_table();

		$sql_strings = "CREATE TABLE {$strings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			hash char(32) NOT NULL,
			original longtext NOT NULL,
			context varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY hash (hash),
			KEY context (context)
		) {$charset};";

		$sql_trans = "CREATE TABLE {$trans} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			string_id bigint(20) unsigned NOT NULL,
			lang varchar(20) NOT NULL,
			translation longtext NOT NULL,
			auto tinyint(1) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY string_lang (string_id, lang),
			KEY lang (lang),
			KEY auto (auto)
		) {$charset};";

		dbDelta( $sql_strings );
		dbDelta( $sql_trans );

		update_option( 'lgv_db_version', LGV_DB_VERSION );
	}

	/**
	 * ¿Existen ambas tablas?
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		$strings = self::strings_table();
		$trans   = self::translations_table();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $strings ) ) === $strings
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $trans ) ) === $trans;
	}
}
