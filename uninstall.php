<?php
/**
 * Desinstalación: borra tablas y opciones propias.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lingua_translations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lingua_strings" );

delete_option( 'lgv_languages' );
delete_option( 'lgv_default_lang' );
delete_option( 'lgv_db_version' );
delete_transient( 'lgv_import_result' );
