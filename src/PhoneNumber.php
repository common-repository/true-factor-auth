<?php

namespace TrueFactor;

class PhoneNumber {
	/**
	 * @return array List of country codes and their corresponding dial codes.
	 */
	public static function dial_codes() {
		return [
			'af' => [
				0 => 'Afghanistan (‫افغانستان‬‎)',
				1 => '93',
			],
			'al' => [
				0 => 'Albania (Shqipëri)',
				1 => '355',
			],
			'dz' => [
				0 => 'Algeria (‫الجزائر‬‎)',
				1 => '213',
			],
			'as' => [
				0 => 'American Samoa',
				1 => '1',
				2 => 5,
				3 => [
					0 => '684',
				],
			],
			'ad' => [
				0 => 'Andorra',
				1 => '376',
			],
			'ao' => [
				0 => 'Angola',
				1 => '244',
			],
			'ai' => [
				0 => 'Anguilla',
				1 => '1',
				2 => 6,
				3 => [
					0 => '264',
				],
			],
			'ag' => [
				0 => 'Antigua and Barbuda',
				1 => '1',
				2 => 7,
				3 => [
					0 => '268',
				],
			],
			'ar' => [
				0 => 'Argentina',
				1 => '54',
			],
			'am' => [
				0 => 'Armenia (Հայաստան)',
				1 => '374',
			],
			'aw' => [
				0 => 'Aruba',
				1 => '297',
			],
			'au' => [
				0 => 'Australia',
				1 => '61',
				2 => 0,
			],
			'at' => [
				0 => 'Austria (Österreich)',
				1 => '43',
			],
			'az' => [
				0 => 'Azerbaijan (Azərbaycan)',
				1 => '994',
			],
			'bs' => [
				0 => 'Bahamas',
				1 => '1',
				2 => 8,
				3 => [
					0 => '242',
				],
			],
			'bh' => [
				0 => 'Bahrain (‫البحرين‬‎)',
				1 => '973',
			],
			'bd' => [
				0 => 'Bangladesh (বাংলাদেশ)',
				1 => '880',
			],
			'bb' => [
				0 => 'Barbados',
				1 => '1',
				2 => 9,
				3 => [
					0 => '246',
				],
			],
			'by' => [
				0 => 'Belarus (Беларусь)',
				1 => '375',
			],
			'be' => [
				0 => 'Belgium (België)',
				1 => '32',
			],
			'bz' => [
				0 => 'Belize',
				1 => '501',
			],
			'bj' => [
				0 => 'Benin (Bénin)',
				1 => '229',
			],
			'bm' => [
				0 => 'Bermuda',
				1 => '1',
				2 => 10,
				3 => [
					0 => '441',
				],
			],
			'bt' => [
				0 => 'Bhutan (འབྲུག)',
				1 => '975',
			],
			'bo' => [
				0 => 'Bolivia',
				1 => '591',
			],
			'ba' => [
				0 => 'Bosnia and Herzegovina (Босна и Херцеговина)',
				1 => '387',
			],
			'bw' => [
				0 => 'Botswana',
				1 => '267',
			],
			'br' => [
				0 => 'Brazil (Brasil)',
				1 => '55',
			],
			'io' => [
				0 => 'British Indian Ocean Territory',
				1 => '246',
			],
			'vg' => [
				0 => 'British Virgin Islands',
				1 => '1',
				2 => 11,
				3 => [
					0 => '284',
				],
			],
			'bn' => [
				0 => 'Brunei',
				1 => '673',
			],
			'bg' => [
				0 => 'Bulgaria (България)',
				1 => '359',
			],
			'bf' => [
				0 => 'Burkina Faso',
				1 => '226',
			],
			'bi' => [
				0 => 'Burundi (Uburundi)',
				1 => '257',
			],
			'kh' => [
				0 => 'Cambodia (កម្ពុជា)',
				1 => '855',
			],
			'cm' => [
				0 => 'Cameroon (Cameroun)',
				1 => '237',
			],
			'ca' => [
				0 => 'Canada',
				1 => '1',
				2 => 1,
				3 => [
					0  => '204',
					1  => '226',
					2  => '236',
					3  => '249',
					4  => '250',
					5  => '289',
					6  => '306',
					7  => '343',
					8  => '365',
					9  => '387',
					10 => '403',
					11 => '416',
					12 => '418',
					13 => '431',
					14 => '437',
					15 => '438',
					16 => '450',
					17 => '506',
					18 => '514',
					19 => '519',
					20 => '548',
					21 => '579',
					22 => '581',
					23 => '587',
					24 => '604',
					25 => '613',
					26 => '639',
					27 => '647',
					28 => '672',
					29 => '705',
					30 => '709',
					31 => '742',
					32 => '778',
					33 => '780',
					34 => '782',
					35 => '807',
					36 => '819',
					37 => '825',
					38 => '867',
					39 => '873',
					40 => '902',
					41 => '905',
				],
			],
			'cv' => [
				0 => 'Cape Verde (Kabu Verdi)',
				1 => '238',
			],
			'bq' => [
				0 => 'Caribbean Netherlands',
				1 => '599',
				2 => 1,
				3 => [
					0 => '3',
					1 => '4',
					2 => '7',
				],
			],
			'ky' => [
				0 => 'Cayman Islands',
				1 => '1',
				2 => 12,
				3 => [
					0 => '345',
				],
			],
			'cf' => [
				0 => 'Central African Republic (République centrafricaine)',
				1 => '236',
			],
			'td' => [
				0 => 'Chad (Tchad)',
				1 => '235',
			],
			'cl' => [
				0 => 'Chile',
				1 => '56',
			],
			'cn' => [
				0 => 'China (中国)',
				1 => '86',
			],
			'cx' => [
				0 => 'Christmas Island',
				1 => '61',
				2 => 2,
			],
			'cc' => [
				0 => 'Cocos (Keeling) Islands',
				1 => '61',
				2 => 1,
			],
			'co' => [
				0 => 'Colombia',
				1 => '57',
			],
			'km' => [
				0 => 'Comoros (‫جزر القمر‬‎)',
				1 => '269',
			],
			'cd' => [
				0 => 'Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)',
				1 => '243',
			],
			'cg' => [
				0 => 'Congo (Republic) (Congo-Brazzaville)',
				1 => '242',
			],
			'ck' => [
				0 => 'Cook Islands',
				1 => '682',
			],
			'cr' => [
				0 => 'Costa Rica',
				1 => '506',
			],
			'ci' => [
				0 => 'Côte d’Ivoire',
				1 => '225',
			],
			'hr' => [
				0 => 'Croatia (Hrvatska)',
				1 => '385',
			],
			'cu' => [
				0 => 'Cuba',
				1 => '53',
			],
			'cw' => [
				0 => 'Curaçao',
				1 => '599',
				2 => 0,
			],
			'cy' => [
				0 => 'Cyprus (Κύπρος)',
				1 => '357',
			],
			'cz' => [
				0 => 'Czech Republic (Česká republika)',
				1 => '420',
			],
			'dk' => [
				0 => 'Denmark (Danmark)',
				1 => '45',
			],
			'dj' => [
				0 => 'Djibouti',
				1 => '253',
			],
			'dm' => [
				0 => 'Dominica',
				1 => '1',
				2 => 13,
				3 => [
					0 => '767',
				],
			],
			'do' => [
				0 => 'Dominican Republic (República Dominicana)',
				1 => '1',
				2 => 2,
				3 => [
					0 => '809',
					1 => '829',
					2 => '849',
				],
			],
			'ec' => [
				0 => 'Ecuador',
				1 => '593',
			],
			'eg' => [
				0 => 'Egypt (‫مصر‬‎)',
				1 => '20',
			],
			'sv' => [
				0 => 'El Salvador',
				1 => '503',
			],
			'gq' => [
				0 => 'Equatorial Guinea (Guinea Ecuatorial)',
				1 => '240',
			],
			'er' => [
				0 => 'Eritrea',
				1 => '291',
			],
			'ee' => [
				0 => 'Estonia (Eesti)',
				1 => '372',
			],
			'et' => [
				0 => 'Ethiopia',
				1 => '251',
			],
			'fk' => [
				0 => 'Falkland Islands (Islas Malvinas)',
				1 => '500',
			],
			'fo' => [
				0 => 'Faroe Islands (Føroyar)',
				1 => '298',
			],
			'fj' => [
				0 => 'Fiji',
				1 => '679',
			],
			'fi' => [
				0 => 'Finland (Suomi)',
				1 => '358',
				2 => 0,
			],
			'fr' => [
				0 => 'France',
				1 => '33',
			],
			'gf' => [
				0 => 'French Guiana (Guyane française)',
				1 => '594',
			],
			'pf' => [
				0 => 'French Polynesia (Polynésie française)',
				1 => '689',
			],
			'ga' => [
				0 => 'Gabon',
				1 => '241',
			],
			'gm' => [
				0 => 'Gambia',
				1 => '220',
			],
			'ge' => [
				0 => 'Georgia (საქართველო)',
				1 => '995',
			],
			'de' => [
				0 => 'Germany (Deutschland)',
				1 => '49',
			],
			'gh' => [
				0 => 'Ghana (Gaana)',
				1 => '233',
			],
			'gi' => [
				0 => 'Gibraltar',
				1 => '350',
			],
			'gr' => [
				0 => 'Greece (Ελλάδα)',
				1 => '30',
			],
			'gl' => [
				0 => 'Greenland (Kalaallit Nunaat)',
				1 => '299',
			],
			'gd' => [
				0 => 'Grenada',
				1 => '1',
				2 => 14,
				3 => [
					0 => '473',
				],
			],
			'gp' => [
				0 => 'Guadeloupe',
				1 => '590',
				2 => 0,
			],
			'gu' => [
				0 => 'Guam',
				1 => '1',
				2 => 15,
				3 => [
					0 => '671',
				],
			],
			'gt' => [
				0 => 'Guatemala',
				1 => '502',
			],
			'gg' => [
				0 => 'Guernsey',
				1 => '44',
				2 => 1,
				3 => [
					0 => '1481',
					1 => '7781',
					2 => '7839',
					3 => '7911',
				],
			],
			'gn' => [
				0 => 'Guinea (Guinée)',
				1 => '224',
			],
			'gw' => [
				0 => 'Guinea-Bissau (Guiné Bissau)',
				1 => '245',
			],
			'gy' => [
				0 => 'Guyana',
				1 => '592',
			],
			'ht' => [
				0 => 'Haiti',
				1 => '509',
			],
			'hn' => [
				0 => 'Honduras',
				1 => '504',
			],
			'hk' => [
				0 => 'Hong Kong (香港)',
				1 => '852',
			],
			'hu' => [
				0 => 'Hungary (Magyarország)',
				1 => '36',
			],
			'is' => [
				0 => 'Iceland (Ísland)',
				1 => '354',
			],
			'in' => [
				0 => 'India (भारत)',
				1 => '91',
			],
			'id' => [
				0 => 'Indonesia',
				1 => '62',
			],
			'ir' => [
				0 => 'Iran (‫ایران‬‎)',
				1 => '98',
			],
			'iq' => [
				0 => 'Iraq (‫العراق‬‎)',
				1 => '964',
			],
			'ie' => [
				0 => 'Ireland',
				1 => '353',
			],
			'im' => [
				0 => 'Isle of Man',
				1 => '44',
				2 => 2,
				3 => [
					0 => '1624',
					1 => '74576',
					2 => '7524',
					3 => '7924',
					4 => '7624',
				],
			],
			'il' => [
				0 => 'Israel (‫ישראל‬‎)',
				1 => '972',
			],
			'it' => [
				0 => 'Italy (Italia)',
				1 => '39',
				2 => 0,
			],
			'jm' => [
				0 => 'Jamaica',
				1 => '1',
				2 => 4,
				3 => [
					0 => '876',
					1 => '658',
				],
			],
			'jp' => [
				0 => 'Japan (日本)',
				1 => '81',
			],
			'je' => [
				0 => 'Jersey',
				1 => '44',
				2 => 3,
				3 => [
					0 => '1534',
					1 => '7509',
					2 => '7700',
					3 => '7797',
					4 => '7829',
					5 => '7937',
				],
			],
			'jo' => [
				0 => 'Jordan (‫الأردن‬‎)',
				1 => '962',
			],
			'kz' => [
				0 => 'Kazakhstan (Казахстан)',
				1 => '7',
				2 => 1,
				3 => [
					0 => '33',
					1 => '7',
				],
			],
			'ke' => [
				0 => 'Kenya',
				1 => '254',
			],
			'ki' => [
				0 => 'Kiribati',
				1 => '686',
			],
			'xk' => [
				0 => 'Kosovo',
				1 => '383',
			],
			'kw' => [
				0 => 'Kuwait (‫الكويت‬‎)',
				1 => '965',
			],
			'kg' => [
				0 => 'Kyrgyzstan (Кыргызстан)',
				1 => '996',
			],
			'la' => [
				0 => 'Laos (ລາວ)',
				1 => '856',
			],
			'lv' => [
				0 => 'Latvia (Latvija)',
				1 => '371',
			],
			'lb' => [
				0 => 'Lebanon (‫لبنان‬‎)',
				1 => '961',
			],
			'ls' => [
				0 => 'Lesotho',
				1 => '266',
			],
			'lr' => [
				0 => 'Liberia',
				1 => '231',
			],
			'ly' => [
				0 => 'Libya (‫ليبيا‬‎)',
				1 => '218',
			],
			'li' => [
				0 => 'Liechtenstein',
				1 => '423',
			],
			'lt' => [
				0 => 'Lithuania (Lietuva)',
				1 => '370',
			],
			'lu' => [
				0 => 'Luxembourg',
				1 => '352',
			],
			'mo' => [
				0 => 'Macau (澳門)',
				1 => '853',
			],
			'mk' => [
				0 => 'Macedonia (FYROM) (Македонија)',
				1 => '389',
			],
			'mg' => [
				0 => 'Madagascar (Madagasikara)',
				1 => '261',
			],
			'mw' => [
				0 => 'Malawi',
				1 => '265',
			],
			'my' => [
				0 => 'Malaysia',
				1 => '60',
			],
			'mv' => [
				0 => 'Maldives',
				1 => '960',
			],
			'ml' => [
				0 => 'Mali',
				1 => '223',
			],
			'mt' => [
				0 => 'Malta',
				1 => '356',
			],
			'mh' => [
				0 => 'Marshall Islands',
				1 => '692',
			],
			'mq' => [
				0 => 'Martinique',
				1 => '596',
			],
			'mr' => [
				0 => 'Mauritania (‫موريتانيا‬‎)',
				1 => '222',
			],
			'mu' => [
				0 => 'Mauritius (Moris)',
				1 => '230',
			],
			'yt' => [
				0 => 'Mayotte',
				1 => '262',
				2 => 1,
				3 => [
					0 => '269',
					1 => '639',
				],
			],
			'mx' => [
				0 => 'Mexico (México)',
				1 => '52',
			],
			'fm' => [
				0 => 'Micronesia',
				1 => '691',
			],
			'md' => [
				0 => 'Moldova (Republica Moldova)',
				1 => '373',
			],
			'mc' => [
				0 => 'Monaco',
				1 => '377',
			],
			'mn' => [
				0 => 'Mongolia (Монгол)',
				1 => '976',
			],
			'me' => [
				0 => 'Montenegro (Crna Gora)',
				1 => '382',
			],
			'ms' => [
				0 => 'Montserrat',
				1 => '1',
				2 => 16,
				3 => [
					0 => '664',
				],
			],
			'ma' => [
				0 => 'Morocco (‫المغرب‬‎)',
				1 => '212',
				2 => 0,
			],
			'mz' => [
				0 => 'Mozambique (Moçambique)',
				1 => '258',
			],
			'mm' => [
				0 => 'Myanmar (Burma) (မြန်မာ)',
				1 => '95',
			],
			'na' => [
				0 => 'Namibia (Namibië)',
				1 => '264',
			],
			'nr' => [
				0 => 'Nauru',
				1 => '674',
			],
			'np' => [
				0 => 'Nepal (नेपाल)',
				1 => '977',
			],
			'nl' => [
				0 => 'Netherlands (Nederland)',
				1 => '31',
			],
			'nc' => [
				0 => 'New Caledonia (Nouvelle-Calédonie)',
				1 => '687',
			],
			'nz' => [
				0 => 'New Zealand',
				1 => '64',
			],
			'ni' => [
				0 => 'Nicaragua',
				1 => '505',
			],
			'ne' => [
				0 => 'Niger (Nijar)',
				1 => '227',
			],
			'ng' => [
				0 => 'Nigeria',
				1 => '234',
			],
			'nu' => [
				0 => 'Niue',
				1 => '683',
			],
			'nf' => [
				0 => 'Norfolk Island',
				1 => '672',
			],
			'kp' => [
				0 => 'North Korea (조선 민주주의 인민 공화국)',
				1 => '850',
			],
			'mp' => [
				0 => 'Northern Mariana Islands',
				1 => '1',
				2 => 17,
				3 => [
					0 => '670',
				],
			],
			'no' => [
				0 => 'Norway (Norge)',
				1 => '47',
				2 => 0,
			],
			'om' => [
				0 => 'Oman (‫عُمان‬‎)',
				1 => '968',
			],
			'pk' => [
				0 => 'Pakistan (‫پاکستان‬‎)',
				1 => '92',
			],
			'pw' => [
				0 => 'Palau',
				1 => '680',
			],
			'ps' => [
				0 => 'Palestine (‫فلسطين‬‎)',
				1 => '970',
			],
			'pa' => [
				0 => 'Panama (Panamá)',
				1 => '507',
			],
			'pg' => [
				0 => 'Papua New Guinea',
				1 => '675',
			],
			'py' => [
				0 => 'Paraguay',
				1 => '595',
			],
			'pe' => [
				0 => 'Peru (Perú)',
				1 => '51',
			],
			'ph' => [
				0 => 'Philippines',
				1 => '63',
			],
			'pl' => [
				0 => 'Poland (Polska)',
				1 => '48',
			],
			'pt' => [
				0 => 'Portugal',
				1 => '351',
			],
			'pr' => [
				0 => 'Puerto Rico',
				1 => '1',
				2 => 3,
				3 => [
					0 => '787',
					1 => '939',
				],
			],
			'qa' => [
				0 => 'Qatar (‫قطر‬‎)',
				1 => '974',
			],
			're' => [
				0 => 'Réunion (La Réunion)',
				1 => '262',
				2 => 0,
			],
			'ro' => [
				0 => 'Romania (România)',
				1 => '40',
			],
			'ru' => [
				0 => 'Russia (Россия)',
				1 => '7',
				2 => 0,
			],
			'rw' => [
				0 => 'Rwanda',
				1 => '250',
			],
			'bl' => [
				0 => 'Saint Barthélemy',
				1 => '590',
				2 => 1,
			],
			'sh' => [
				0 => 'Saint Helena',
				1 => '290',
			],
			'kn' => [
				0 => 'Saint Kitts and Nevis',
				1 => '1',
				2 => 18,
				3 => [
					0 => '869',
				],
			],
			'lc' => [
				0 => 'Saint Lucia',
				1 => '1',
				2 => 19,
				3 => [
					0 => '758',
				],
			],
			'mf' => [
				0 => 'Saint Martin (Saint-Martin (partie française))',
				1 => '590',
				2 => 2,
			],
			'pm' => [
				0 => 'Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)',
				1 => '508',
			],
			'vc' => [
				0 => 'Saint Vincent and the Grenadines',
				1 => '1',
				2 => 20,
				3 => [
					0 => '784',
				],
			],
			'ws' => [
				0 => 'Samoa',
				1 => '685',
			],
			'sm' => [
				0 => 'San Marino',
				1 => '378',
			],
			'st' => [
				0 => 'São Tomé and Príncipe (São Tomé e Príncipe)',
				1 => '239',
			],
			'sa' => [
				0 => 'Saudi Arabia (‫المملكة العربية السعودية‬‎)',
				1 => '966',
			],
			'sn' => [
				0 => 'Senegal (Sénégal)',
				1 => '221',
			],
			'rs' => [
				0 => 'Serbia (Србија)',
				1 => '381',
			],
			'sc' => [
				0 => 'Seychelles',
				1 => '248',
			],
			'sl' => [
				0 => 'Sierra Leone',
				1 => '232',
			],
			'sg' => [
				0 => 'Singapore',
				1 => '65',
			],
			'sx' => [
				0 => 'Sint Maarten',
				1 => '1',
				2 => 21,
				3 => [
					0 => '721',
				],
			],
			'sk' => [
				0 => 'Slovakia (Slovensko)',
				1 => '421',
			],
			'si' => [
				0 => 'Slovenia (Slovenija)',
				1 => '386',
			],
			'sb' => [
				0 => 'Solomon Islands',
				1 => '677',
			],
			'so' => [
				0 => 'Somalia (Soomaaliya)',
				1 => '252',
			],
			'za' => [
				0 => 'South Africa',
				1 => '27',
			],
			'kr' => [
				0 => 'South Korea (대한민국)',
				1 => '82',
			],
			'ss' => [
				0 => 'South Sudan (‫جنوب السودان‬‎)',
				1 => '211',
			],
			'es' => [
				0 => 'Spain (España)',
				1 => '34',
			],
			'lk' => [
				0 => 'Sri Lanka (ශ්‍රී ලංකාව)',
				1 => '94',
			],
			'sd' => [
				0 => 'Sudan (‫السودان‬‎)',
				1 => '249',
			],
			'sr' => [
				0 => 'Suriname',
				1 => '597',
			],
			'sj' => [
				0 => 'Svalbard and Jan Mayen',
				1 => '47',
				2 => 1,
				3 => [
					0 => '79',
				],
			],
			'sz' => [
				0 => 'Swaziland',
				1 => '268',
			],
			'se' => [
				0 => 'Sweden (Sverige)',
				1 => '46',
			],
			'ch' => [
				0 => 'Switzerland (Schweiz)',
				1 => '41',
			],
			'sy' => [
				0 => 'Syria (‫سوريا‬‎)',
				1 => '963',
			],
			'tw' => [
				0 => 'Taiwan (台灣)',
				1 => '886',
			],
			'tj' => [
				0 => 'Tajikistan',
				1 => '992',
			],
			'tz' => [
				0 => 'Tanzania',
				1 => '255',
			],
			'th' => [
				0 => 'Thailand (ไทย)',
				1 => '66',
			],
			'tl' => [
				0 => 'Timor-Leste',
				1 => '670',
			],
			'tg' => [
				0 => 'Togo',
				1 => '228',
			],
			'tk' => [
				0 => 'Tokelau',
				1 => '690',
			],
			'to' => [
				0 => 'Tonga',
				1 => '676',
			],
			'tt' => [
				0 => 'Trinidad and Tobago',
				1 => '1',
				2 => 22,
				3 => [
					0 => '868',
				],
			],
			'tn' => [
				0 => 'Tunisia (‫تونس‬‎)',
				1 => '216',
			],
			'tr' => [
				0 => 'Turkey (Türkiye)',
				1 => '90',
			],
			'tm' => [
				0 => 'Turkmenistan',
				1 => '993',
			],
			'tc' => [
				0 => 'Turks and Caicos Islands',
				1 => '1',
				2 => 23,
				3 => [
					0 => '649',
				],
			],
			'tv' => [
				0 => 'Tuvalu',
				1 => '688',
			],
			'vi' => [
				0 => 'U.S. Virgin Islands',
				1 => '1',
				2 => 24,
				3 => [
					0 => '340',
				],
			],
			'ug' => [
				0 => 'Uganda',
				1 => '256',
			],
			'ua' => [
				0 => 'Ukraine (Україна)',
				1 => '380',
			],
			'ae' => [
				0 => 'United Arab Emirates (‫الإمارات العربية المتحدة‬‎)',
				1 => '971',
			],
			'gb' => [
				0 => 'United Kingdom',
				1 => '44',
				2 => 0,
			],
			'us' => [
				0 => 'United States',
				1 => '1',
				2 => 0,
			],
			'uy' => [
				0 => 'Uruguay',
				1 => '598',
			],
			'uz' => [
				0 => 'Uzbekistan (Oʻzbekiston)',
				1 => '998',
			],
			'vu' => [
				0 => 'Vanuatu',
				1 => '678',
			],
			'va' => [
				0 => 'Vatican City (Città del Vaticano)',
				1 => '39',
				2 => 1,
				3 => [
					0 => '06698',
				],
			],
			've' => [
				0 => 'Venezuela',
				1 => '58',
			],
			'vn' => [
				0 => 'Vietnam (Việt Nam)',
				1 => '84',
			],
			'wf' => [
				0 => 'Wallis and Futuna (Wallis-et-Futuna)',
				1 => '681',
			],
			'eh' => [
				0 => 'Western Sahara (‫الصحراء الغربية‬‎)',
				1 => '212',
				2 => 1,
				3 => [
					0 => '5288',
					1 => '5289',
				],
			],
			'ye' => [
				0 => 'Yemen (‫اليمن‬‎)',
				1 => '967',
			],
			'zm' => [
				0 => 'Zambia',
				1 => '260',
			],
			'zw' => [
				0 => 'Zimbabwe',
				1 => '263',
			],
			'ax' => [
				0 => 'Åland Islands',
				1 => '358',
				2 => 1,
				3 => [
					0 => '18',
				],
			],
		];
	}

	/**
	 * Generate options for country code
	 *
	 * @return array
	 */
	public static function get_simple_dial_codes() {
		$countries = self::dial_codes();

		$options = [];
		foreach ( $countries as $iso => $info ) {
			$options[ $iso ] = $info;
		};

		return $options;
	}

	public static function normalize( $number ) {
		if ( ! is_string( $number ) ) {
			return null;
		}

		$number = preg_replace( '/[^0-9]/', '', $number );
		if ( ! $number ) {
			return null;
		}

		return $number;
	}

	/**
	 * Detects country code by phone number.
	 *
	 * @param $number
	 *
	 * @return string[]|null
	 */
	public static function get_number_info( $number ) {
		$number    = self::normalize( $number );
		$countries = self::dial_codes();
		$matches   = [];
		foreach ( $countries as $iso => $c ) {
			if ( substr( $number, 0, strlen( $c[1] ) ) != $c[1] ) {
				continue;
			}
			if ( ! empty( $c[3] ) ) {
				$matched = false;
				foreach ( $c[3] as $area_code ) {
					if ( substr( $number, 0, strlen( $c[1] ) + strlen( $area_code ) ) == $c[1] . $area_code ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched ) {
					continue;
				}
			}
			$matches[] = $iso;
		}
		if ( ! $matches ) {
			return null;
		}
		uasort( $matches, function ( $a, $b ) use ( $countries ) {
			if ( empty( $countries[ $a ][2] ) ) {
				return empty( $countries[ $b ][2] ) ? 0 : - 1;
			}

			return empty( $countries[ $b ][2] ) ? 1
				: ( $countries[ $a ][2] < $countries[ $b ][2] ? - 1 : (int) ( $countries[ $a ][2] > $countries[ $b ][2] ) );
		} );

		$iso   = reset( $matches );
		$match = $countries[ $iso ];

		return [
			'country_iso' => $iso,
			'country'     => $match[0],
			'dial_code'   => $match[1],
			'number'      => substr( $number, strlen( $match[1] ) ),
		];
	}
}
