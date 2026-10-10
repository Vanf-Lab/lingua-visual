<?php
/**
 * Tabla país → idiomas probables (ISO 3166-1 alpha-2 → códigos de idioma).
 *
 * Archivo de datos versionable: el orden de los idiomas por país es
 * prioridad (el primero es el más probable). La detección cruza esta lista
 * con los idiomas ACTIVOS del sitio; el primero que coincida gana.
 *
 * @package Lingua_Visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGV_Country_Langs {

	/**
	 * Tabla completa: código país => [ 'name' => nombre en español, 'langs' => [...] ].
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'AD' => array( 'name' => 'Andorra', 'langs' => array( 'ca', 'es', 'fr' ) ),
			'AE' => array( 'name' => 'Emiratos Árabes Unidos', 'langs' => array( 'ar', 'en' ) ),
			'AF' => array( 'name' => 'Afganistán', 'langs' => array( 'fa', 'ps' ) ),
			'AG' => array( 'name' => 'Antigua y Barbuda', 'langs' => array( 'en' ) ),
			'AI' => array( 'name' => 'Anguila', 'langs' => array( 'en' ) ),
			'AL' => array( 'name' => 'Albania', 'langs' => array( 'sq' ) ),
			'AM' => array( 'name' => 'Armenia', 'langs' => array( 'hy' ) ),
			'AO' => array( 'name' => 'Angola', 'langs' => array( 'pt' ) ),
			'AQ' => array( 'name' => 'Antártida', 'langs' => array( 'en' ) ),
			'AR' => array( 'name' => 'Argentina', 'langs' => array( 'es' ) ),
			'AS' => array( 'name' => 'Samoa Americana', 'langs' => array( 'en', 'sm' ) ),
			'AT' => array( 'name' => 'Austria', 'langs' => array( 'de' ) ),
			'AU' => array( 'name' => 'Australia', 'langs' => array( 'en' ) ),
			'AW' => array( 'name' => 'Aruba', 'langs' => array( 'nl', 'es' ) ),
			'AX' => array( 'name' => 'Islas Åland', 'langs' => array( 'sv' ) ),
			'AZ' => array( 'name' => 'Azerbaiyán', 'langs' => array( 'az' ) ),
			'BA' => array( 'name' => 'Bosnia y Herzegovina', 'langs' => array( 'bs', 'hr', 'sr' ) ),
			'BB' => array( 'name' => 'Barbados', 'langs' => array( 'en' ) ),
			'BD' => array( 'name' => 'Bangladés', 'langs' => array( 'bn', 'en' ) ),
			'BE' => array( 'name' => 'Bélgica', 'langs' => array( 'nl', 'fr', 'de' ) ),
			'BF' => array( 'name' => 'Burkina Faso', 'langs' => array( 'fr' ) ),
			'BG' => array( 'name' => 'Bulgaria', 'langs' => array( 'bg' ) ),
			'BH' => array( 'name' => 'Baréin', 'langs' => array( 'ar', 'en' ) ),
			'BI' => array( 'name' => 'Burundi', 'langs' => array( 'fr', 'rn' ) ),
			'BJ' => array( 'name' => 'Benín', 'langs' => array( 'fr' ) ),
			'BL' => array( 'name' => 'San Bartolomé', 'langs' => array( 'fr' ) ),
			'BM' => array( 'name' => 'Bermudas', 'langs' => array( 'en' ) ),
			'BN' => array( 'name' => 'Brunéi', 'langs' => array( 'ms', 'en' ) ),
			'BO' => array( 'name' => 'Bolivia', 'langs' => array( 'es' ) ),
			'BQ' => array( 'name' => 'Bonaire, San Eustaquio y Saba', 'langs' => array( 'nl' ) ),
			'BR' => array( 'name' => 'Brasil', 'langs' => array( 'pt' ) ),
			'BS' => array( 'name' => 'Bahamas', 'langs' => array( 'en' ) ),
			'BT' => array( 'name' => 'Bután', 'langs' => array( 'dz' ) ),
			'BV' => array( 'name' => 'Isla Bouvet', 'langs' => array( 'no' ) ),
			'BW' => array( 'name' => 'Botsuana', 'langs' => array( 'en', 'tn' ) ),
			'BY' => array( 'name' => 'Bielorrusia', 'langs' => array( 'ru', 'be' ) ),
			'BZ' => array( 'name' => 'Belice', 'langs' => array( 'en', 'es' ) ),
			'CA' => array( 'name' => 'Canadá', 'langs' => array( 'en', 'fr' ) ),
			'CC' => array( 'name' => 'Islas Cocos', 'langs' => array( 'en' ) ),
			'CD' => array( 'name' => 'R.D. del Congo', 'langs' => array( 'fr' ) ),
			'CF' => array( 'name' => 'República Centroafricana', 'langs' => array( 'fr', 'sg' ) ),
			'CG' => array( 'name' => 'República del Congo', 'langs' => array( 'fr' ) ),
			'CH' => array( 'name' => 'Suiza', 'langs' => array( 'de', 'fr', 'it' ) ),
			'CI' => array( 'name' => 'Costa de Marfil', 'langs' => array( 'fr' ) ),
			'CK' => array( 'name' => 'Islas Cook', 'langs' => array( 'en' ) ),
			'CL' => array( 'name' => 'Chile', 'langs' => array( 'es' ) ),
			'CM' => array( 'name' => 'Camerún', 'langs' => array( 'fr', 'en' ) ),
			'CN' => array( 'name' => 'China', 'langs' => array( 'zh' ) ),
			'CO' => array( 'name' => 'Colombia', 'langs' => array( 'es' ) ),
			'CR' => array( 'name' => 'Costa Rica', 'langs' => array( 'es' ) ),
			'CU' => array( 'name' => 'Cuba', 'langs' => array( 'es' ) ),
			'CV' => array( 'name' => 'Cabo Verde', 'langs' => array( 'pt' ) ),
			'CW' => array( 'name' => 'Curazao', 'langs' => array( 'nl', 'es' ) ),
			'CX' => array( 'name' => 'Isla de Navidad', 'langs' => array( 'en' ) ),
			'CY' => array( 'name' => 'Chipre', 'langs' => array( 'el', 'tr', 'en' ) ),
			'CZ' => array( 'name' => 'Chequia', 'langs' => array( 'cs' ) ),
			'DE' => array( 'name' => 'Alemania', 'langs' => array( 'de' ) ),
			'DJ' => array( 'name' => 'Yibuti', 'langs' => array( 'fr', 'ar' ) ),
			'DK' => array( 'name' => 'Dinamarca', 'langs' => array( 'da' ) ),
			'DM' => array( 'name' => 'Dominica', 'langs' => array( 'en' ) ),
			'DO' => array( 'name' => 'República Dominicana', 'langs' => array( 'es' ) ),
			'DZ' => array( 'name' => 'Argelia', 'langs' => array( 'ar', 'fr' ) ),
			'EC' => array( 'name' => 'Ecuador', 'langs' => array( 'es' ) ),
			'EE' => array( 'name' => 'Estonia', 'langs' => array( 'et', 'ru' ) ),
			'EG' => array( 'name' => 'Egipto', 'langs' => array( 'ar' ) ),
			'EH' => array( 'name' => 'Sáhara Occidental', 'langs' => array( 'ar', 'es' ) ),
			'ER' => array( 'name' => 'Eritrea', 'langs' => array( 'ti', 'ar', 'en' ) ),
			'ES' => array( 'name' => 'España', 'langs' => array( 'es' ) ),
			'ET' => array( 'name' => 'Etiopía', 'langs' => array( 'am' ) ),
			'FI' => array( 'name' => 'Finlandia', 'langs' => array( 'fi', 'sv' ) ),
			'FJ' => array( 'name' => 'Fiyi', 'langs' => array( 'en', 'fj' ) ),
			'FK' => array( 'name' => 'Islas Malvinas', 'langs' => array( 'en' ) ),
			'FM' => array( 'name' => 'Micronesia', 'langs' => array( 'en' ) ),
			'FO' => array( 'name' => 'Islas Feroe', 'langs' => array( 'fo', 'da' ) ),
			'FR' => array( 'name' => 'Francia', 'langs' => array( 'fr' ) ),
			'GA' => array( 'name' => 'Gabón', 'langs' => array( 'fr' ) ),
			'GB' => array( 'name' => 'Reino Unido', 'langs' => array( 'en' ) ),
			'GD' => array( 'name' => 'Granada', 'langs' => array( 'en' ) ),
			'GE' => array( 'name' => 'Georgia', 'langs' => array( 'ka' ) ),
			'GF' => array( 'name' => 'Guayana Francesa', 'langs' => array( 'fr' ) ),
			'GG' => array( 'name' => 'Guernsey', 'langs' => array( 'en' ) ),
			'GH' => array( 'name' => 'Ghana', 'langs' => array( 'en' ) ),
			'GI' => array( 'name' => 'Gibraltar', 'langs' => array( 'en', 'es' ) ),
			'GL' => array( 'name' => 'Groenlandia', 'langs' => array( 'kl', 'da' ) ),
			'GM' => array( 'name' => 'Gambia', 'langs' => array( 'en' ) ),
			'GN' => array( 'name' => 'Guinea', 'langs' => array( 'fr' ) ),
			'GP' => array( 'name' => 'Guadalupe', 'langs' => array( 'fr' ) ),
			'GQ' => array( 'name' => 'Guinea Ecuatorial', 'langs' => array( 'es', 'fr', 'pt' ) ),
			'GR' => array( 'name' => 'Grecia', 'langs' => array( 'el' ) ),
			'GS' => array( 'name' => 'Georgias del Sur', 'langs' => array( 'en' ) ),
			'GT' => array( 'name' => 'Guatemala', 'langs' => array( 'es' ) ),
			'GU' => array( 'name' => 'Guam', 'langs' => array( 'en', 'ch' ) ),
			'GW' => array( 'name' => 'Guinea-Bisáu', 'langs' => array( 'pt' ) ),
			'GY' => array( 'name' => 'Guyana', 'langs' => array( 'en' ) ),
			'HK' => array( 'name' => 'Hong Kong', 'langs' => array( 'zh', 'en' ) ),
			'HM' => array( 'name' => 'Islas Heard y McDonald', 'langs' => array( 'en' ) ),
			'HN' => array( 'name' => 'Honduras', 'langs' => array( 'es' ) ),
			'HR' => array( 'name' => 'Croacia', 'langs' => array( 'hr' ) ),
			'HT' => array( 'name' => 'Haití', 'langs' => array( 'fr', 'ht' ) ),
			'HU' => array( 'name' => 'Hungría', 'langs' => array( 'hu' ) ),
			'ID' => array( 'name' => 'Indonesia', 'langs' => array( 'id' ) ),
			'IE' => array( 'name' => 'Irlanda', 'langs' => array( 'en', 'ga' ) ),
			'IL' => array( 'name' => 'Israel', 'langs' => array( 'he', 'ar' ) ),
			'IM' => array( 'name' => 'Isla de Man', 'langs' => array( 'en' ) ),
			'IN' => array( 'name' => 'India', 'langs' => array( 'en', 'hi' ) ),
			'IO' => array( 'name' => 'Territorio Británico del Índico', 'langs' => array( 'en' ) ),
			'IQ' => array( 'name' => 'Irak', 'langs' => array( 'ar', 'ku' ) ),
			'IR' => array( 'name' => 'Irán', 'langs' => array( 'fa' ) ),
			'IS' => array( 'name' => 'Islandia', 'langs' => array( 'is' ) ),
			'IT' => array( 'name' => 'Italia', 'langs' => array( 'it' ) ),
			'JE' => array( 'name' => 'Jersey', 'langs' => array( 'en' ) ),
			'JM' => array( 'name' => 'Jamaica', 'langs' => array( 'en' ) ),
			'JO' => array( 'name' => 'Jordania', 'langs' => array( 'ar' ) ),
			'JP' => array( 'name' => 'Japón', 'langs' => array( 'ja' ) ),
			'KE' => array( 'name' => 'Kenia', 'langs' => array( 'en', 'sw' ) ),
			'KG' => array( 'name' => 'Kirguistán', 'langs' => array( 'ky', 'ru' ) ),
			'KH' => array( 'name' => 'Camboya', 'langs' => array( 'km' ) ),
			'KI' => array( 'name' => 'Kiribati', 'langs' => array( 'en' ) ),
			'KM' => array( 'name' => 'Comoras', 'langs' => array( 'ar', 'fr' ) ),
			'KN' => array( 'name' => 'San Cristóbal y Nieves', 'langs' => array( 'en' ) ),
			'KP' => array( 'name' => 'Corea del Norte', 'langs' => array( 'ko' ) ),
			'KR' => array( 'name' => 'Corea del Sur', 'langs' => array( 'ko' ) ),
			'KW' => array( 'name' => 'Kuwait', 'langs' => array( 'ar', 'en' ) ),
			'KY' => array( 'name' => 'Islas Caimán', 'langs' => array( 'en' ) ),
			'KZ' => array( 'name' => 'Kazajistán', 'langs' => array( 'kk', 'ru' ) ),
			'LA' => array( 'name' => 'Laos', 'langs' => array( 'lo' ) ),
			'LB' => array( 'name' => 'Líbano', 'langs' => array( 'ar', 'fr' ) ),
			'LC' => array( 'name' => 'Santa Lucía', 'langs' => array( 'en' ) ),
			'LI' => array( 'name' => 'Liechtenstein', 'langs' => array( 'de' ) ),
			'LK' => array( 'name' => 'Sri Lanka', 'langs' => array( 'si', 'ta', 'en' ) ),
			'LR' => array( 'name' => 'Liberia', 'langs' => array( 'en' ) ),
			'LS' => array( 'name' => 'Lesoto', 'langs' => array( 'en', 'st' ) ),
			'LT' => array( 'name' => 'Lituania', 'langs' => array( 'lt' ) ),
			'LU' => array( 'name' => 'Luxemburgo', 'langs' => array( 'lb', 'fr', 'de' ) ),
			'LV' => array( 'name' => 'Letonia', 'langs' => array( 'lv', 'ru' ) ),
			'LY' => array( 'name' => 'Libia', 'langs' => array( 'ar' ) ),
			'MA' => array( 'name' => 'Marruecos', 'langs' => array( 'ar', 'fr' ) ),
			'MC' => array( 'name' => 'Mónaco', 'langs' => array( 'fr' ) ),
			'MD' => array( 'name' => 'Moldavia', 'langs' => array( 'ro', 'ru' ) ),
			'ME' => array( 'name' => 'Montenegro', 'langs' => array( 'sr' ) ),
			'MF' => array( 'name' => 'San Martín (parte francesa)', 'langs' => array( 'fr' ) ),
			'MG' => array( 'name' => 'Madagascar', 'langs' => array( 'fr', 'mg' ) ),
			'MH' => array( 'name' => 'Islas Marshall', 'langs' => array( 'en', 'mh' ) ),
			'MK' => array( 'name' => 'Macedonia del Norte', 'langs' => array( 'mk' ) ),
			'ML' => array( 'name' => 'Malí', 'langs' => array( 'fr' ) ),
			'MM' => array( 'name' => 'Birmania (Myanmar)', 'langs' => array( 'my' ) ),
			'MN' => array( 'name' => 'Mongolia', 'langs' => array( 'mn' ) ),
			'MO' => array( 'name' => 'Macao', 'langs' => array( 'zh', 'pt' ) ),
			'MP' => array( 'name' => 'Islas Marianas del Norte', 'langs' => array( 'en' ) ),
			'MQ' => array( 'name' => 'Martinica', 'langs' => array( 'fr' ) ),
			'MR' => array( 'name' => 'Mauritania', 'langs' => array( 'ar', 'fr' ) ),
			'MS' => array( 'name' => 'Montserrat', 'langs' => array( 'en' ) ),
			'MT' => array( 'name' => 'Malta', 'langs' => array( 'mt', 'en' ) ),
			'MU' => array( 'name' => 'Mauricio', 'langs' => array( 'en', 'fr' ) ),
			'MV' => array( 'name' => 'Maldivas', 'langs' => array( 'dv' ) ),
			'MW' => array( 'name' => 'Malaui', 'langs' => array( 'en', 'ny' ) ),
			'MX' => array( 'name' => 'México', 'langs' => array( 'es' ) ),
			'MY' => array( 'name' => 'Malasia', 'langs' => array( 'ms', 'en' ) ),
			'MZ' => array( 'name' => 'Mozambique', 'langs' => array( 'pt' ) ),
			'NA' => array( 'name' => 'Namibia', 'langs' => array( 'en' ) ),
			'NC' => array( 'name' => 'Nueva Caledonia', 'langs' => array( 'fr' ) ),
			'NE' => array( 'name' => 'Níger', 'langs' => array( 'fr' ) ),
			'NF' => array( 'name' => 'Isla Norfolk', 'langs' => array( 'en' ) ),
			'NG' => array( 'name' => 'Nigeria', 'langs' => array( 'en' ) ),
			'NI' => array( 'name' => 'Nicaragua', 'langs' => array( 'es' ) ),
			'NL' => array( 'name' => 'Países Bajos', 'langs' => array( 'nl' ) ),
			'NO' => array( 'name' => 'Noruega', 'langs' => array( 'no' ) ),
			'NP' => array( 'name' => 'Nepal', 'langs' => array( 'ne' ) ),
			'NR' => array( 'name' => 'Nauru', 'langs' => array( 'en', 'na' ) ),
			'NU' => array( 'name' => 'Niue', 'langs' => array( 'en' ) ),
			'NZ' => array( 'name' => 'Nueva Zelanda', 'langs' => array( 'en', 'mi' ) ),
			'OM' => array( 'name' => 'Omán', 'langs' => array( 'ar' ) ),
			'PA' => array( 'name' => 'Panamá', 'langs' => array( 'es' ) ),
			'PE' => array( 'name' => 'Perú', 'langs' => array( 'es' ) ),
			'PF' => array( 'name' => 'Polinesia Francesa', 'langs' => array( 'fr' ) ),
			'PG' => array( 'name' => 'Papúa Nueva Guinea', 'langs' => array( 'en' ) ),
			'PH' => array( 'name' => 'Filipinas', 'langs' => array( 'en', 'fil' ) ),
			'PK' => array( 'name' => 'Pakistán', 'langs' => array( 'ur', 'en' ) ),
			'PL' => array( 'name' => 'Polonia', 'langs' => array( 'pl' ) ),
			'PM' => array( 'name' => 'San Pedro y Miquelón', 'langs' => array( 'fr' ) ),
			'PN' => array( 'name' => 'Islas Pitcairn', 'langs' => array( 'en' ) ),
			'PR' => array( 'name' => 'Puerto Rico', 'langs' => array( 'es', 'en' ) ),
			'PS' => array( 'name' => 'Palestina', 'langs' => array( 'ar' ) ),
			'PT' => array( 'name' => 'Portugal', 'langs' => array( 'pt' ) ),
			'PW' => array( 'name' => 'Palaos', 'langs' => array( 'en' ) ),
			'PY' => array( 'name' => 'Paraguay', 'langs' => array( 'es', 'gn' ) ),
			'QA' => array( 'name' => 'Catar', 'langs' => array( 'ar', 'en' ) ),
			'RE' => array( 'name' => 'Reunión', 'langs' => array( 'fr' ) ),
			'RO' => array( 'name' => 'Rumania', 'langs' => array( 'ro' ) ),
			'RS' => array( 'name' => 'Serbia', 'langs' => array( 'sr' ) ),
			'RU' => array( 'name' => 'Rusia', 'langs' => array( 'ru' ) ),
			'RW' => array( 'name' => 'Ruanda', 'langs' => array( 'rw', 'en', 'fr' ) ),
			'SA' => array( 'name' => 'Arabia Saudita', 'langs' => array( 'ar' ) ),
			'SB' => array( 'name' => 'Islas Salomón', 'langs' => array( 'en' ) ),
			'SC' => array( 'name' => 'Seychelles', 'langs' => array( 'en', 'fr' ) ),
			'SD' => array( 'name' => 'Sudán', 'langs' => array( 'ar', 'en' ) ),
			'SE' => array( 'name' => 'Suecia', 'langs' => array( 'sv' ) ),
			'SG' => array( 'name' => 'Singapur', 'langs' => array( 'en', 'zh', 'ms' ) ),
			'SH' => array( 'name' => 'Santa Elena', 'langs' => array( 'en' ) ),
			'SI' => array( 'name' => 'Eslovenia', 'langs' => array( 'sl' ) ),
			'SJ' => array( 'name' => 'Svalbard y Jan Mayen', 'langs' => array( 'no' ) ),
			'SK' => array( 'name' => 'Eslovaquia', 'langs' => array( 'sk' ) ),
			'SL' => array( 'name' => 'Sierra Leona', 'langs' => array( 'en' ) ),
			'SM' => array( 'name' => 'San Marino', 'langs' => array( 'it' ) ),
			'SN' => array( 'name' => 'Senegal', 'langs' => array( 'fr' ) ),
			'SO' => array( 'name' => 'Somalia', 'langs' => array( 'so', 'ar' ) ),
			'SR' => array( 'name' => 'Surinam', 'langs' => array( 'nl' ) ),
			'SS' => array( 'name' => 'Sudán del Sur', 'langs' => array( 'en' ) ),
			'ST' => array( 'name' => 'Santo Tomé y Príncipe', 'langs' => array( 'pt' ) ),
			'SV' => array( 'name' => 'El Salvador', 'langs' => array( 'es' ) ),
			'SX' => array( 'name' => 'Sint Maarten (parte holandesa)', 'langs' => array( 'nl', 'en' ) ),
			'SY' => array( 'name' => 'Siria', 'langs' => array( 'ar' ) ),
			'SZ' => array( 'name' => 'Esuatini', 'langs' => array( 'en', 'ss' ) ),
			'TC' => array( 'name' => 'Islas Turcas y Caicos', 'langs' => array( 'en' ) ),
			'TD' => array( 'name' => 'Chad', 'langs' => array( 'fr', 'ar' ) ),
			'TF' => array( 'name' => 'Tierras Australes Francesas', 'langs' => array( 'fr' ) ),
			'TG' => array( 'name' => 'Togo', 'langs' => array( 'fr' ) ),
			'TH' => array( 'name' => 'Tailandia', 'langs' => array( 'th' ) ),
			'TJ' => array( 'name' => 'Tayikistán', 'langs' => array( 'tg', 'ru' ) ),
			'TK' => array( 'name' => 'Tokelau', 'langs' => array( 'en' ) ),
			'TL' => array( 'name' => 'Timor Oriental', 'langs' => array( 'pt', 'tet' ) ),
			'TM' => array( 'name' => 'Turkmenistán', 'langs' => array( 'tk', 'ru' ) ),
			'TN' => array( 'name' => 'Túnez', 'langs' => array( 'ar', 'fr' ) ),
			'TO' => array( 'name' => 'Tonga', 'langs' => array( 'to', 'en' ) ),
			'TR' => array( 'name' => 'Turquía', 'langs' => array( 'tr' ) ),
			'TT' => array( 'name' => 'Trinidad y Tobago', 'langs' => array( 'en' ) ),
			'TV' => array( 'name' => 'Tuvalu', 'langs' => array( 'en' ) ),
			'TW' => array( 'name' => 'Taiwán', 'langs' => array( 'zh' ) ),
			'TZ' => array( 'name' => 'Tanzania', 'langs' => array( 'sw', 'en' ) ),
			'UA' => array( 'name' => 'Ucrania', 'langs' => array( 'uk', 'ru' ) ),
			'UG' => array( 'name' => 'Uganda', 'langs' => array( 'en', 'sw' ) ),
			'UM' => array( 'name' => 'Islas Ultramarinas de EE.UU.', 'langs' => array( 'en' ) ),
			'US' => array( 'name' => 'Estados Unidos', 'langs' => array( 'en' ) ),
			'UY' => array( 'name' => 'Uruguay', 'langs' => array( 'es' ) ),
			'UZ' => array( 'name' => 'Uzbekistán', 'langs' => array( 'uz', 'ru' ) ),
			'VA' => array( 'name' => 'Ciudad del Vaticano', 'langs' => array( 'it', 'la' ) ),
			'VC' => array( 'name' => 'San Vicente y las Granadinas', 'langs' => array( 'en' ) ),
			'VE' => array( 'name' => 'Venezuela', 'langs' => array( 'es' ) ),
			'VG' => array( 'name' => 'Islas Vírgenes Británicas', 'langs' => array( 'en' ) ),
			'VI' => array( 'name' => 'Islas Vírgenes de EE.UU.', 'langs' => array( 'en' ) ),
			'VN' => array( 'name' => 'Vietnam', 'langs' => array( 'vi' ) ),
			'VU' => array( 'name' => 'Vanuatu', 'langs' => array( 'en', 'fr' ) ),
			'WF' => array( 'name' => 'Wallis y Futuna', 'langs' => array( 'fr' ) ),
			'WS' => array( 'name' => 'Samoa', 'langs' => array( 'sm', 'en' ) ),
			'YE' => array( 'name' => 'Yemen', 'langs' => array( 'ar' ) ),
			'YT' => array( 'name' => 'Mayotte', 'langs' => array( 'fr' ) ),
			'ZA' => array( 'name' => 'Sudáfrica', 'langs' => array( 'en', 'af', 'zu' ) ),
			'ZM' => array( 'name' => 'Zambia', 'langs' => array( 'en' ) ),
			'ZW' => array( 'name' => 'Zimbabue', 'langs' => array( 'en', 'sn' ) ),
		);
	}

	/**
	 * Idiomas probables para un país (en orden de prioridad).
	 *
	 * @param string $country Código ISO de 2 letras (ej. 'AR').
	 * @return array Lista de códigos de idioma (vacía si el país no existe).
	 */
	public static function for_country( $country ) {
		$country = strtoupper( trim( (string) $country ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return array();
		}
		$all = self::all();
		return isset( $all[ $country ] ) ? $all[ $country ]['langs'] : array();
	}

	/**
	 * Nombre del país en español.
	 *
	 * @param string $country Código ISO de 2 letras.
	 * @return string Nombre o el código si no existe.
	 */
	public static function country_name( $country ) {
		$country = strtoupper( trim( (string) $country ) );
		$all     = self::all();
		return isset( $all[ $country ] ) ? $all[ $country ]['name'] : $country;
	}

	/**
	 * Lista de países para selects: [ código => nombre ].
	 *
	 * @return array
	 */
	public static function countries_list() {
		$out = array();
		foreach ( self::all() as $code => $data ) {
			$out[ $code ] = $data['name'];
		}
		return $out;
	}
}
