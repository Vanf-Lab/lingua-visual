<?php
/**
 * Per-language media (attachment) translation: alt, title, caption, description.
 *
 * Values are stored in the regular dictionary with context "media:{id}:{field}",
 * so the existing output buffer translates them automatically. An explicit
 * filter covers wp_get_attachment_image_attributes() for robustness.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media translation handling.
 */
class LGV_Media {

	/**
	 * Translatable attachment fields.
	 *
	 * @var array
	 */
	const FIELDS = array( 'alt', 'title', 'caption', 'description' );

	/**
	 * Build the dictionary context for a media field.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 * @return string
	 */
	public static function context( $attachment_id, $field ) {
		return 'media:' . (int) $attachment_id . ':' . $field;
	}

	/**
	 * Get the default-language value of a media field.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 * @return string
	 */
	public static function default_value( $attachment_id, $field ) {
		$attachment_id = (int) $attachment_id;
		switch ( $field ) {
			case 'alt':
				return (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			case 'title':
				return (string) get_the_title( $attachment_id );
			case 'caption':
				return (string) wp_get_attachment_caption( $attachment_id );
			case 'description':
				$post = get_post( $attachment_id );
				return $post ? (string) $post->post_content : '';
			default:
				return '';
		}
	}

	/**
	 * Get the translated value of a media field for a language.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 * @param string $lang          Language code.
	 * @return string Translated value or '' if none.
	 */
	public static function get( $attachment_id, $field, $lang ) {
		$default = self::default_value( $attachment_id, $field );
		if ( '' === $default ) {
			return '';
		}
		if ( $lang === LGV_Languages::get_default() ) {
			return $default;
		}
		global $wpdb;
		$strings = LGV_DB::strings_table();
		$trans   = LGV_DB::translations_table();
		$hash    = LGV_Strings::hash( $default, self::context( $attachment_id, $field ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$translation = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.translation FROM {$trans} t INNER JOIN {$strings} s ON s.id = t.string_id WHERE s.hash = %s AND t.lang = %s",
				$hash,
				$lang
			)
		);
		return null !== $translation ? (string) $translation : '';
	}

	/**
	 * Save a media field translation (registers the string if needed).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 * @param string $lang          Language code.
	 * @param string $translation   Translated text ('' deletes).
	 * @return void
	 */
	public static function save( $attachment_id, $field, $lang, $translation ) {
		$default = self::default_value( $attachment_id, $field );
		if ( '' === $default ) {
			return;
		}
		$context   = self::context( $attachment_id, $field );
		$string_id = LGV_Strings::register( $default, $context );
		if ( '' === trim( $translation ) ) {
			LGV_Strings::save_translation( $string_id, $lang, '', 0 );
			return;
		}
		LGV_Strings::save_translation( $string_id, $lang, $translation, 0 );
	}

	/**
	 * List attachments for the admin table.
	 *
	 * @param int $page  Page number (1-based).
	 * @param int $per   Per page.
	 * @return array [ 'items' => [...], 'total' => int ].
	 */
	public static function list_attachments( $page = 1, $per = 20 ) {
		$page  = max( 1, (int) $page );
		$offset = ( $page - 1 ) * $per;
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $per,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return array(
			'items' => $query->posts,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Get the replacement attachment ID for a language (0 = none).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $lang          Language code.
	 * @return int
	 */
	public static function get_file( $attachment_id, $lang ) {
		$attachment_id = (int) $attachment_id;
		if ( $lang === LGV_Languages::get_default() ) {
			return 0;
		}
		$replacement = (int) get_post_meta( $attachment_id, '_lgv_file_' . sanitize_key( $lang ), true );
		// Validate: must exist and be an image.
		if ( $replacement > 0 && 'attachment' === get_post_type( $replacement ) && wp_attachment_is_image( $replacement ) ) {
			return $replacement;
		}
		return 0;
	}

	/**
	 * Save/clear the replacement attachment for a language.
	 *
	 * @param int    $attachment_id   Attachment ID.
	 * @param string $lang            Language code.
	 * @param int    $replacement_id  Replacement attachment ID (0 clears).
	 */
	public static function save_file( $attachment_id, $lang, $replacement_id ) {
		$attachment_id  = (int) $attachment_id;
		$replacement_id = (int) $replacement_id;
		$key = '_lgv_file_' . sanitize_key( $lang );
		if ( $replacement_id > 0 ) {
			update_post_meta( $attachment_id, $key, $replacement_id );
		} else {
			delete_post_meta( $attachment_id, $key );
		}
	}

	/**
	 * Register frontend filters.
	 *
	 * @return void
	 */
	public static function init_frontend() {
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'filter_image_attributes' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'filter_image_src' ), 10, 4 );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 10, 2 );
	}

	/**
	 * Swap image source when a per-language file exists.
	 *
	 * @param array|false  $image         Image data or false.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Size.
	 * @param bool         $icon          Icon.
	 * @return array|false
	 */
	public static function filter_image_src( $image, $attachment_id, $size, $icon ) {
		static $guard = false;
		if ( $guard ) {
			return $image;
		}
		$lang = LGV_Routing::detect_request_lang();
		if ( $lang === LGV_Languages::get_default() ) {
			return $image;
		}
		$replacement = self::get_file( $attachment_id, $lang );
		if ( ! $replacement ) {
			return $image;
		}
		$guard = true;
		$swapped = wp_get_attachment_image_src( $replacement, $size, $icon );
		$guard = false;
		return false !== $swapped ? $swapped : $image;
	}

	/**
	 * Swap attachment URL when a per-language file exists.
	 *
	 * @param string $url           URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public static function filter_attachment_url( $url, $attachment_id ) {
		static $guard = false;
		if ( $guard ) {
			return $url;
		}
		$lang = LGV_Routing::detect_request_lang();
		if ( $lang === LGV_Languages::get_default() ) {
			return $url;
		}
		$replacement = self::get_file( $attachment_id, $lang );
		if ( ! $replacement ) {
			return $url;
		}
		$guard = true;
		$swapped = wp_get_attachment_url( $replacement );
		$guard = false;
		return $swapped ? $swapped : $url;
	}

	/**
	 * Swap the alt attribute when a translation exists.
	 *
	 * @param array       $attr       Attributes.
	 * @param WP_Post     $attachment Attachment post.
	 * @return array
	 */
	public static function filter_image_attributes( $attr, $attachment ) {
		if ( ! ( $attachment instanceof WP_Post ) ) {
			return $attr;
		}
		$lang = LGV_Routing::detect_request_lang();
		if ( $lang === LGV_Languages::get_default() ) {
			return $attr;
		}
		$translated = self::get( $attachment->ID, 'alt', $lang );
		if ( '' !== $translated ) {
			$attr['alt'] = $translated;
		}
		return $attr;
	}
}
