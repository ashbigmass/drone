<?php
class PHPExcel_Shared_String
{
	const STRING_REGEXP_FRACTION	= '(-?)(\d+)\s+(\d+\/\d+)';
	private static $_controlCharacters = array();
	private static $_SYLKCharacters = array();
	private static $_decimalSeparator;
	private static $_thousandsSeparator;
	private static $_currencyCode;
	private static $_isMbstringEnabled;
	private static $_isIconvEnabled;

	private static function _buildControlCharacters() {
		for ($i = 0; $i <= 31; ++$i) {
			if ($i != 9 && $i != 10 && $i != 13) {
				$find = '_x' . sprintf('%04s' , strtoupper(dechex($i))) . '_';
				$replace = chr($i);
				self::$_controlCharacters[$find] = $replace;
			}
		}
	}

	private static function _buildSYLKCharacters() {
		self::$_SYLKCharacters = array(
			"\x1B 0"  => chr(0),
			"\x1B 1"  => chr(1),
			"\x1B 2"  => chr(2),
			"\x1B 3"  => chr(3),
			"\x1B 4"  => chr(4),
			"\x1B 5"  => chr(5),
			"\x1B 6"  => chr(6),
			"\x1B 7"  => chr(7),
			"\x1B 8"  => chr(8),
			"\x1B 9"  => chr(9),
			"\x1B :"  => chr(10),
			"\x1B ;"  => chr(11),
			"\x1B <"  => chr(12),
			"\x1B :"  => chr(13),
			"\x1B >"  => chr(14),
			"\x1B ?"  => chr(15),
			"\x1B!0"  => chr(16),
			"\x1B!1"  => chr(17),
			"\x1B!2"  => chr(18),
			"\x1B!3"  => chr(19),
			"\x1B!4"  => chr(20),
			"\x1B!5"  => chr(21),
			"\x1B!6"  => chr(22),
			"\x1B!7"  => chr(23),
			"\x1B!8"  => chr(24),
			"\x1B!9"  => chr(25),
			"\x1B!:"  => chr(26),
			"\x1B!;"  => chr(27),
			"\x1B!<"  => chr(28),
			"\x1B!="  => chr(29),
			"\x1B!>"  => chr(30),
			"\x1B!?"  => chr(31),
			"\x1B'?"  => chr(127),
			"\x1B(0"  => '€', // 128 in CP1252
			"\x1B(2"  => '‚', // 130 in CP1252
			"\x1B(3"  => 'ƒ', // 131 in CP1252
			"\x1B(4"  => '„', // 132 in CP1252
			"\x1B(5"  => '…', // 133 in CP1252
			"\x1B(6"  => '†', // 134 in CP1252
			"\x1B(7"  => '‡', // 135 in CP1252
			"\x1B(8"  => 'ˆ', // 136 in CP1252
			"\x1B(9"  => '‰', // 137 in CP1252
			"\x1B(:"  => 'Š', // 138 in CP1252
			"\x1B(;"  => '‹', // 139 in CP1252
			"\x1BNj"  => 'Œ', // 140 in CP1252
			"\x1B(>"  => 'Ž', // 142 in CP1252
			"\x1B)1"  => '‘', // 145 in CP1252
			"\x1B)2"  => '’', // 146 in CP1252
			"\x1B)3"  => '“', // 147 in CP1252
			"\x1B)4"  => '”', // 148 in CP1252
			"\x1B)5"  => '•', // 149 in CP1252
			"\x1B)6"  => '–', // 150 in CP1252
			"\x1B)7"  => '—', // 151 in CP1252
			"\x1B)8"  => '˜', // 152 in CP1252
			"\x1B)9"  => '™', // 153 in CP1252
			"\x1B):"  => 'š', // 154 in CP1252
			"\x1B);"  => '›', // 155 in CP1252
			"\x1BNz"  => 'œ', // 156 in CP1252
			"\x1B)>"  => 'ž', // 158 in CP1252
			"\x1B)?"  => 'Ÿ', // 159 in CP1252
			"\x1B*0"  => ' ', // 160 in CP1252
			"\x1BN!"  => '¡', // 161 in CP1252
			"\x1BN\"" => '¢', // 162 in CP1252
			"\x1BN#"  => '£', // 163 in CP1252
			"\x1BN("  => '¤', // 164 in CP1252
			"\x1BN%"  => '¥', // 165 in CP1252
			"\x1B*6"  => '¦', // 166 in CP1252
			"\x1BN'"  => '§', // 167 in CP1252
			"\x1BNH " => '¨', // 168 in CP1252
			"\x1BNS"  => '©', // 169 in CP1252
			"\x1BNc"  => 'ª', // 170 in CP1252
			"\x1BN+"  => '«', // 171 in CP1252
			"\x1B*<"  => '¬', // 172 in CP1252
			"\x1B*="  => '­', // 173 in CP1252
			"\x1BNR"  => '®', // 174 in CP1252
			"\x1B*?"  => '¯', // 175 in CP1252
			"\x1BN0"  => '°', // 176 in CP1252
			"\x1BN1"  => '±', // 177 in CP1252
			"\x1BN2"  => '²', // 178 in CP1252
			"\x1BN3"  => '³', // 179 in CP1252
			"\x1BNB " => '´', // 180 in CP1252
			"\x1BN5"  => 'µ', // 181 in CP1252
			"\x1BN6"  => '¶', // 182 in CP1252
			"\x1BN7"  => '·', // 183 in CP1252
			"\x1B+8"  => '¸', // 184 in CP1252
			"\x1BNQ"  => '¹', // 185 in CP1252
			"\x1BNk"  => 'º', // 186 in CP1252
			"\x1BN;"  => '»', // 187 in CP1252
			"\x1BN<"  => '¼', // 188 in CP1252
			"\x1BN="  => '½', // 189 in CP1252
			"\x1BN>"  => '¾', // 190 in CP1252
			"\x1BN?"  => '¿', // 191 in CP1252
			"\x1BNAA" => 'À', // 192 in CP1252
			"\x1BNBA" => 'Á', // 193 in CP1252
			"\x1BNCA" => 'Â', // 194 in CP1252
			"\x1BNDA" => 'Ã', // 195 in CP1252
			"\x1BNHA" => 'Ä', // 196 in CP1252
			"\x1BNJA" => 'Å', // 197 in CP1252
			"\x1BNa"  => 'Æ', // 198 in CP1252
			"\x1BNKC" => 'Ç', // 199 in CP1252
			"\x1BNAE" => 'È', // 200 in CP1252
			"\x1BNBE" => 'É', // 201 in CP1252
			"\x1BNCE" => 'Ê', // 202 in CP1252
			"\x1BNHE" => 'Ë', // 203 in CP1252
			"\x1BNAI" => 'Ì', // 204 in CP1252
			"\x1BNBI" => 'Í', // 205 in CP1252
			"\x1BNCI" => 'Î', // 206 in CP1252
			"\x1BNHI" => 'Ï', // 207 in CP1252
			"\x1BNb"  => 'Ð', // 208 in CP1252
			"\x1BNDN" => 'Ñ', // 209 in CP1252
			"\x1BNAO" => 'Ò', // 210 in CP1252
			"\x1BNBO" => 'Ó', // 211 in CP1252
			"\x1BNCO" => 'Ô', // 212 in CP1252
			"\x1BNDO" => 'Õ', // 213 in CP1252
			"\x1BNHO" => 'Ö', // 214 in CP1252
			"\x1B-7"  => '×', // 215 in CP1252
			"\x1BNi"  => 'Ø', // 216 in CP1252
			"\x1BNAU" => 'Ù', // 217 in CP1252
			"\x1BNBU" => 'Ú', // 218 in CP1252
			"\x1BNCU" => 'Û', // 219 in CP1252
			"\x1BNHU" => 'Ü', // 220 in CP1252
			"\x1B-="  => 'Ý', // 221 in CP1252
			"\x1BNl"  => 'Þ', // 222 in CP1252
			"\x1BN{"  => 'ß', // 223 in CP1252
			"\x1BNAa" => 'à', // 224 in CP1252
			"\x1BNBa" => 'á', // 225 in CP1252
			"\x1BNCa" => 'â', // 226 in CP1252
			"\x1BNDa" => 'ã', // 227 in CP1252
			"\x1BNHa" => 'ä', // 228 in CP1252
			"\x1BNJa" => 'å', // 229 in CP1252
			"\x1BNq"  => 'æ', // 230 in CP1252
			"\x1BNKc" => 'ç', // 231 in CP1252
			"\x1BNAe" => 'è', // 232 in CP1252
			"\x1BNBe" => 'é', // 233 in CP1252
			"\x1BNCe" => 'ê', // 234 in CP1252
			"\x1BNHe" => 'ë', // 235 in CP1252
			"\x1BNAi" => 'ì', // 236 in CP1252
			"\x1BNBi" => 'í', // 237 in CP1252
			"\x1BNCi" => 'î', // 238 in CP1252
			"\x1BNHi" => 'ï', // 239 in CP1252
			"\x1BNs"  => 'ð', // 240 in CP1252
			"\x1BNDn" => 'ñ', // 241 in CP1252
			"\x1BNAo" => 'ò', // 242 in CP1252
			"\x1BNBo" => 'ó', // 243 in CP1252
			"\x1BNCo" => 'ô', // 244 in CP1252
			"\x1BNDo" => 'õ', // 245 in CP1252
			"\x1BNHo" => 'ö', // 246 in CP1252
			"\x1B/7"  => '÷', // 247 in CP1252
			"\x1BNy"  => 'ø', // 248 in CP1252
			"\x1BNAu" => 'ù', // 249 in CP1252
			"\x1BNBu" => 'ú', // 250 in CP1252
			"\x1BNCu" => 'û', // 251 in CP1252
			"\x1BNHu" => 'ü', // 252 in CP1252
			"\x1B/="  => 'ý', // 253 in CP1252
			"\x1BN|"  => 'þ', // 254 in CP1252
			"\x1BNHy" => 'ÿ', // 255 in CP1252
		);
	}

	public static function getIsMbstringEnabled() {
		if (isset(self::$_isMbstringEnabled)) return self::$_isMbstringEnabled;
		self::$_isMbstringEnabled = function_exists('mb_convert_encoding') ? true : false;
		return self::$_isMbstringEnabled;
	}

	public static function getIsIconvEnabled() {
		if (isset(self::$_isIconvEnabled)) return self::$_isIconvEnabled;
		if (!function_exists('iconv')) {
			self::$_isIconvEnabled = false;
			return false;
		}
		if (!@iconv('UTF-8', 'UTF-16LE', 'x')) {
			self::$_isIconvEnabled = false;
			return false;
		}
		if (!@iconv_substr('A', 0, 1, 'UTF-8')) {
			self::$_isIconvEnabled = false;
			return false;
		}
		if ( defined('PHP_OS') && @stristr(PHP_OS, 'AIX')
				&& defined('ICONV_IMPL') && (@strcasecmp(ICONV_IMPL, 'unknown') == 0)
				&& defined('ICONV_VERSION') && (@strcasecmp(ICONV_VERSION, 'unknown') == 0) )
		{
			self::$_isIconvEnabled = false;
			return false;
		}
		self::$_isIconvEnabled = true;
		return true;
	}

	public static function buildCharacterSets() {
		if(empty(self::$_controlCharacters)) self::_buildControlCharacters();
		if(empty(self::$_SYLKCharacters)) self::_buildSYLKCharacters();
	}

	public static function ControlCharacterOOXML2PHP($value = '') {
		return str_replace( array_keys(self::$_controlCharacters), array_values(self::$_controlCharacters), $value );
	}

	public static function ControlCharacterPHP2OOXML($value = '') {
		return str_replace( array_values(self::$_controlCharacters), array_keys(self::$_controlCharacters), $value );
	}

	public static function SanitizeUTF8($value) {
		if (self::getIsIconvEnabled()) {
			$value = @iconv('UTF-8', 'UTF-8', $value);
			return $value;
		}
		if (self::getIsMbstringEnabled()) {
			$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
			return $value;
		}
		return $value;
	}

	public static function IsUTF8($value = '') {
		return utf8_encode(utf8_decode($value)) === $value;
	}

	public static function FormatNumber($value) {
		if (is_float($value)) return str_replace(',', '.', $value);
		return (string) $value;
	}

	public static function UTF8toBIFF8UnicodeShort($value, $arrcRuns = array()) {
		$ln = self::CountCharacters($value, 'UTF-8');
		if(empty($arrcRuns)){
			$opt = (self::getIsIconvEnabled() || self::getIsMbstringEnabled()) ? 0x0001 : 0x0000;
			$data = pack('CC', $ln, $opt);
			$data .= self::ConvertEncoding($value, 'UTF-16LE', 'UTF-8');
		} else {
			$data = pack('vC', $ln, 0x08);
			$data .= pack('v', count($arrcRuns));
			$data .= $value;
			foreach ($arrcRuns as $cRun){
				$data .= pack('v', $cRun['strlen']);
				$data .= pack('v', $cRun['fontidx']);
			}
		}
		return $data;
	}

	public static function UTF8toBIFF8UnicodeLong($value) {
		$ln = self::CountCharacters($value, 'UTF-8');
		$opt = (self::getIsIconvEnabled() || self::getIsMbstringEnabled()) ? 0x0001 : 0x0000;
		$chars = self::ConvertEncoding($value, 'UTF-16LE', 'UTF-8');
		$data = pack('vC', $ln, $opt) . $chars;
		return $data;
	}

	public static function ConvertEncoding($value, $to, $from) {
		if (self::getIsIconvEnabled()) {
			$value = iconv($from, $to, $value);
			return $value;
		}
		if (self::getIsMbstringEnabled()) {
			$value = mb_convert_encoding($value, $to, $from);
			return $value;
		}
		if($from == 'UTF-16LE') return self::utf16_decode($value, false);
		else if($from == 'UTF-16BE') return self::utf16_decode($value);
		return $value;
	}

	public static function utf16_decode( $str, $bom_be=true ) {
		if( strlen($str) < 2 ) return $str;
		$c0 = ord($str{0});
		$c1 = ord($str{1});
		if( $c0 == 0xfe && $c1 == 0xff ) { $str = substr($str,2); }
		elseif( $c0 == 0xff && $c1 == 0xfe ) { $str = substr($str,2); $bom_be = false; }
		$len = strlen($str);
		$newstr = '';
		for($i=0;$i<$len;$i+=2) {
			if( $bom_be ) { $val = ord($str{$i})   << 4; $val += ord($str{$i+1}); }
			else {        $val = ord($str{$i+1}) << 4; $val += ord($str{$i}); }
			$newstr .= ($val == 0x228) ? "\n" : chr($val);
		}
		return $newstr;
	}

	public static function CountCharacters($value, $enc = 'UTF-8') {
		if (self::getIsIconvEnabled()) return iconv_strlen($value, $enc);
		if (self::getIsMbstringEnabled()) return mb_strlen($value, $enc);
		return strlen($value);
	}

	public static function Substring($pValue = '', $pStart = 0, $pLength = 0) {
		if (self::getIsIconvEnabled()) return iconv_substr($pValue, $pStart, $pLength, 'UTF-8');
		if (self::getIsMbstringEnabled()) return mb_substr($pValue, $pStart, $pLength, 'UTF-8');
		return substr($pValue, $pStart, $pLength);
	}

	public static function convertToNumberIfFraction(&$operand) {
		if (preg_match('/^'.self::STRING_REGEXP_FRACTION.'$/i', $operand, $match)) {
			$sign = ($match[1] == '-') ? '-' : '+';
			$fractionFormula = '='.$sign.$match[2].$sign.$match[3];
			$operand = PHPExcel_Calculation::getInstance()->_calculateFormulaValue($fractionFormula);
			return true;
		}
		return false;
	}

	public static function getDecimalSeparator() {
		if (!isset(self::$_decimalSeparator)) {
			$localeconv = localeconv();
			self::$_decimalSeparator = ($localeconv['decimal_point'] != '') ? $localeconv['decimal_point'] : $localeconv['mon_decimal_point'];
			if (self::$_decimalSeparator == '') self::$_decimalSeparator = '.';
		}
		return self::$_decimalSeparator;
	}

	public static function setDecimalSeparator($pValue = '.') {
		self::$_decimalSeparator = $pValue;
	}

	public static function getThousandsSeparator() {
		if (!isset(self::$_thousandsSeparator)) {
			$localeconv = localeconv();
			self::$_thousandsSeparator = ($localeconv['thousands_sep'] != '') ? $localeconv['thousands_sep'] : $localeconv['mon_thousands_sep'];
		}
		return self::$_thousandsSeparator;
	}

	public static function setThousandsSeparator($pValue = ',') {
		self::$_thousandsSeparator = $pValue;
	}

	public static function getCurrencyCode() {
		if (!isset(self::$_currencyCode)) {
			$localeconv = localeconv();
			self::$_currencyCode = ($localeconv['currency_symbol'] != '') ? $localeconv['currency_symbol'] : $localeconv['int_curr_symbol'];
			if (self::$_currencyCode == '') self::$_currencyCode = '$';
		}
		return self::$_currencyCode;
	}

	public static function setCurrencyCode($pValue = '$') {
		self::$_currencyCode = $pValue;
	}

	public static function SYLKtoUTF8($pValue = '') {
		if (strpos($pValue, '') === false) return $pValue;
		foreach (self::$_SYLKCharacters as $k => $v) $pValue = str_replace($k, $v, $pValue);
		return $pValue;
	}

	public static function testStringAsNumeric($value) {
		if (is_numeric($value)) return $value;
		$v = floatval($value);
		return (is_numeric(substr($value,0,strlen($v)))) ? $v : $value;
	}
}
