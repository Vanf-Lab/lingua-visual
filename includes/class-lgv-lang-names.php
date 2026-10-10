<?php
/**
 * Diccionario de nombres de idiomas para los tooltips del switcher.
 *
 * Formato: [ codigo_idioma => [ 'native' => ..., 'en' => ..., 'es' => ... ] ].
 * El tooltip muestra el nombre del idioma DESTINO en el idioma ACTIVO del sitio
 * (ej.: sitio en inglés → la bandera de español dice "Spanish").
 * Si falta la traducción, se usa el nombre en inglés y por último el nativo.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Lang_Names {

	/**
	 * Tabla completa [ target => [ ui_lang => nombre ] ].
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'en' => array( 'native' => 'English', 'en' => 'English', 'es' => 'inglés', 'pt' => 'inglês', 'fr' => 'anglais', 'de' => 'Englisch', 'it' => 'inglese', 'nl' => 'Engels' ),
			'es' => array( 'native' => 'Español', 'en' => 'Spanish', 'es' => 'español', 'pt' => 'espanhol', 'fr' => 'espagnol', 'de' => 'Spanisch', 'it' => 'spagnolo', 'nl' => 'Spaans' ),
			'pt' => array( 'native' => 'Português', 'en' => 'Portuguese', 'es' => 'portugués', 'pt' => 'português', 'fr' => 'portugais', 'de' => 'Portugiesisch', 'it' => 'portoghese', 'nl' => 'Portugees' ),
			'fr' => array( 'native' => 'Français', 'en' => 'French', 'es' => 'francés', 'pt' => 'francês', 'fr' => 'français', 'de' => 'Französisch', 'it' => 'francese', 'nl' => 'Frans' ),
			'de' => array( 'native' => 'Deutsch', 'en' => 'German', 'es' => 'alemán', 'pt' => 'alemão', 'fr' => 'allemand', 'de' => 'Deutsch', 'it' => 'tedesco', 'nl' => 'Duits' ),
			'it' => array( 'native' => 'Italiano', 'en' => 'Italian', 'es' => 'italiano', 'pt' => 'italiano', 'fr' => 'italien', 'de' => 'Italienisch', 'it' => 'italiano', 'nl' => 'Italiaans' ),
			'nl' => array( 'native' => 'Nederlands', 'en' => 'Dutch', 'es' => 'neerlandés', 'pt' => 'holandês', 'fr' => 'néerlandais', 'de' => 'Niederländisch', 'it' => 'olandese', 'nl' => 'Nederlands' ),
			'ca' => array( 'native' => 'Català', 'en' => 'Catalan', 'es' => 'catalán', 'pt' => 'catalão', 'fr' => 'catalan', 'de' => 'Katalanisch', 'it' => 'catalano', 'nl' => 'Catalaans' ),
			'gl' => array( 'native' => 'Galego', 'en' => 'Galician', 'es' => 'gallego', 'pt' => 'galego', 'fr' => 'galicien', 'de' => 'Galicisch', 'it' => 'galiziano', 'nl' => 'Galicisch' ),
			'eu' => array( 'native' => 'Euskara', 'en' => 'Basque', 'es' => 'vasco', 'pt' => 'basco', 'fr' => 'basque', 'de' => 'Baskisch', 'it' => 'basco', 'nl' => 'Baskisch' ),
			'zh' => array( 'native' => '中文', 'en' => 'Chinese', 'es' => 'chino', 'pt' => 'chinês', 'fr' => 'chinois', 'de' => 'Chinesisch', 'it' => 'cinese', 'nl' => 'Chinees' ),
			'ja' => array( 'native' => '日本語', 'en' => 'Japanese', 'es' => 'japonés', 'pt' => 'japonês', 'fr' => 'japonais', 'de' => 'Japanisch', 'it' => 'giapponese', 'nl' => 'Japans' ),
			'ko' => array( 'native' => '한국어', 'en' => 'Korean', 'es' => 'coreano', 'pt' => 'coreano', 'fr' => 'coréen', 'de' => 'Koreanisch', 'it' => 'coreano', 'nl' => 'Koreaans' ),
			'ru' => array( 'native' => 'Русский', 'en' => 'Russian', 'es' => 'ruso', 'pt' => 'russo', 'fr' => 'russe', 'de' => 'Russisch', 'it' => 'russo', 'nl' => 'Russisch' ),
			'uk' => array( 'native' => 'Українська', 'en' => 'Ukrainian', 'es' => 'ucraniano', 'pt' => 'ucraniano', 'fr' => 'ukrainien', 'de' => 'Ukrainisch', 'it' => 'ucraino', 'nl' => 'Oekraïens' ),
			'be' => array( 'native' => 'Беларуская', 'en' => 'Belarusian', 'es' => 'bielorruso', 'pt' => 'bielorrusso', 'fr' => 'biélorusse', 'de' => 'Weißrussisch', 'it' => 'bielorusso', 'nl' => 'Wit-Russisch' ),
			'pl' => array( 'native' => 'Polski', 'en' => 'Polish', 'es' => 'polaco', 'pt' => 'polonês', 'fr' => 'polonais', 'de' => 'Polnisch', 'it' => 'polacco', 'nl' => 'Pools' ),
			'cs' => array( 'native' => 'Čeština', 'en' => 'Czech', 'es' => 'checo', 'pt' => 'tcheco', 'fr' => 'tchèque', 'de' => 'Tschechisch', 'it' => 'ceco', 'nl' => 'Tsjechisch' ),
			'sk' => array( 'native' => 'Slovenčina', 'en' => 'Slovak', 'es' => 'eslovaco', 'pt' => 'eslovaco', 'fr' => 'slovaque', 'de' => 'Slowakisch', 'it' => 'slovacco', 'nl' => 'Slowaaks' ),
			'sl' => array( 'native' => 'Slovenščina', 'en' => 'Slovenian', 'es' => 'esloveno', 'pt' => 'esloveno', 'fr' => 'slovène', 'de' => 'Slowenisch', 'it' => 'sloveno', 'nl' => 'Sloveens' ),
			'hu' => array( 'native' => 'Magyar', 'en' => 'Hungarian', 'es' => 'húngaro', 'pt' => 'húngaro', 'fr' => 'hongrois', 'de' => 'Ungarisch', 'it' => 'ungherese', 'nl' => 'Hongaars' ),
			'ro' => array( 'native' => 'Română', 'en' => 'Romanian', 'es' => 'rumano', 'pt' => 'romeno', 'fr' => 'roumain', 'de' => 'Rumänisch', 'it' => 'rumeno', 'nl' => 'Roemeens' ),
			'bg' => array( 'native' => 'Български', 'en' => 'Bulgarian', 'es' => 'búlgaro', 'pt' => 'búlgaro', 'fr' => 'bulgare', 'de' => 'Bulgarisch', 'it' => 'bulgaro', 'nl' => 'Bulgaars' ),
			'hr' => array( 'native' => 'Hrvatski', 'en' => 'Croatian', 'es' => 'croata', 'pt' => 'croata', 'fr' => 'croate', 'de' => 'Kroatisch', 'it' => 'croato', 'nl' => 'Kroatisch' ),
			'sr' => array( 'native' => 'Српски', 'en' => 'Serbian', 'es' => 'serbio', 'pt' => 'sérvio', 'fr' => 'serbe', 'de' => 'Serbisch', 'it' => 'serbo', 'nl' => 'Servisch' ),
			'el' => array( 'native' => 'Ελληνικά', 'en' => 'Greek', 'es' => 'griego', 'pt' => 'grego', 'fr' => 'grec', 'de' => 'Griechisch', 'it' => 'greco', 'nl' => 'Grieks' ),
			'tr' => array( 'native' => 'Türkçe', 'en' => 'Turkish', 'es' => 'turco', 'pt' => 'turco', 'fr' => 'turc', 'de' => 'Türkisch', 'it' => 'turco', 'nl' => 'Turks' ),
			'ar' => array( 'native' => 'العربية', 'en' => 'Arabic', 'es' => 'árabe', 'pt' => 'árabe', 'fr' => 'arabe', 'de' => 'Arabisch', 'it' => 'arabo', 'nl' => 'Arabisch' ),
			'he' => array( 'native' => 'עברית', 'en' => 'Hebrew', 'es' => 'hebreo', 'pt' => 'hebraico', 'fr' => 'hébreu', 'de' => 'Hebräisch', 'it' => 'ebraico', 'nl' => 'Hebreeuws' ),
			'fa' => array( 'native' => 'فارسی', 'en' => 'Persian', 'es' => 'persa', 'pt' => 'persa', 'fr' => 'persan', 'de' => 'Persisch', 'it' => 'persiano', 'nl' => 'Perzisch' ),
			'hi' => array( 'native' => 'हिन्दी', 'en' => 'Hindi', 'es' => 'hindi', 'pt' => 'híndi', 'fr' => 'hindi', 'de' => 'Hindi', 'it' => 'hindi', 'nl' => 'Hindi' ),
			'bn' => array( 'native' => 'বাংলা', 'en' => 'Bengali', 'es' => 'bengalí', 'pt' => 'bengali', 'fr' => 'bengali', 'de' => 'Bengalisch', 'it' => 'bengalese', 'nl' => 'Bengaals' ),
			'ur' => array( 'native' => 'اردو', 'en' => 'Urdu', 'es' => 'urdu', 'pt' => 'urdu', 'fr' => 'ourdou', 'de' => 'Urdu', 'it' => 'urdu', 'nl' => 'Urdu' ),
			'id' => array( 'native' => 'Bahasa Indonesia', 'en' => 'Indonesian', 'es' => 'indonesio', 'pt' => 'indonésio', 'fr' => 'indonésien', 'de' => 'Indonesisch', 'it' => 'indonesiano', 'nl' => 'Indonesisch' ),
			'ms' => array( 'native' => 'Bahasa Melayu', 'en' => 'Malay', 'es' => 'malayo', 'pt' => 'malaio', 'fr' => 'malais', 'de' => 'Malaiisch', 'it' => 'malese', 'nl' => 'Maleis' ),
			'th' => array( 'native' => 'ไทย', 'en' => 'Thai', 'es' => 'tailandés', 'pt' => 'tailandês', 'fr' => 'thaï', 'de' => 'Thailändisch', 'it' => 'thailandese', 'nl' => 'Thais' ),
			'vi' => array( 'native' => 'Tiếng Việt', 'en' => 'Vietnamese', 'es' => 'vietnamita', 'pt' => 'vietnamita', 'fr' => 'vietnamien', 'de' => 'Vietnamesisch', 'it' => 'vietnamita', 'nl' => 'Vietnamees' ),
			'tl' => array( 'native' => 'Filipino', 'en' => 'Filipino', 'es' => 'filipino', 'pt' => 'filipino', 'fr' => 'philippin', 'de' => 'Philippinisch', 'it' => 'filippino', 'nl' => 'Filipijns' ),
			'sv' => array( 'native' => 'Svenska', 'en' => 'Swedish', 'es' => 'sueco', 'pt' => 'sueco', 'fr' => 'suédois', 'de' => 'Schwedisch', 'it' => 'svedese', 'nl' => 'Zweeds' ),
			'da' => array( 'native' => 'Dansk', 'en' => 'Danish', 'es' => 'danés', 'pt' => 'dinamarquês', 'fr' => 'danois', 'de' => 'Dänisch', 'it' => 'danese', 'nl' => 'Deens' ),
			'fi' => array( 'native' => 'Suomi', 'en' => 'Finnish', 'es' => 'finés', 'pt' => 'finlandês', 'fr' => 'finnois', 'de' => 'Finnisch', 'it' => 'finlandese', 'nl' => 'Fins' ),
			'no' => array( 'native' => 'Norsk', 'en' => 'Norwegian', 'es' => 'noruego', 'pt' => 'norueguês', 'fr' => 'norvégien', 'de' => 'Norwegisch', 'it' => 'norvegese', 'nl' => 'Noors' ),
		);
	}

	/**
	 * Nombre del idioma destino en el idioma de la interfaz/sitio dado.
	 * Fallback: inglés → nombre nativo registrado → código en mayúsculas.
	 *
	 * @param string $target Código del idioma destino.
	 * @param string $ui     Código del idioma activo del sitio.
	 * @return string
	 */
	public static function name_in( $target, $ui ) {
		$all = self::all();
		$ui  = strtolower( substr( (string) $ui, 0, 2 ) );
		if ( isset( $all[ $target ][ $ui ] ) ) {
			return $all[ $target ][ $ui ];
		}
		if ( isset( $all[ $target ]['en'] ) ) {
			return $all[ $target ]['en'];
		}
		// Si el admin definió una etiqueta custom, respetarla como último recurso nativo.
		$langs = LGV_Languages::all();
		if ( isset( $langs[ $target ]['label_custom'] ) && '' !== trim( $langs[ $target ]['label_custom'] ) ) {
			return $langs[ $target ]['label_custom'];
		}
		return LGV_Switcher::native_name( $target );
	}
}
