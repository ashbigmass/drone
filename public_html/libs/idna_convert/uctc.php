<?php
class uctc {
	private static $mechs = array('ucs4', 'ucs4array', 'utf8', 'utf7', 'utf7imap');
	private static $allow_overlong = false;
	private static $safe_mode;
	private static $safe_char;

	public static function convert($data, $from, $to, $safe_mode = false, $safe_char = 0xFFFC) {
		self::$safe_mode = ($safe_mode) ? true : false;
		self::$safe_char = ($safe_char) ? $safe_char : 0xFFFC;
		if (self::$safe_mode) self::$allow_overlong = true;
		if (!in_array($from, self::$mechs)) throw new Exception('Invalid input format specified');
		if (!in_array($to, self::$mechs)) throw new Exception('Invalid output format specified');
		if ($from != 'ucs4array') eval('$data = self::'.$from.'_ucs4array($data);');
		if ($to != 'ucs4array') eval('$data = self::ucs4array_'.$to.'($data);');
		return $data;
	}

	private static function utf8_ucs4array($input) {
		$output = array();
		$out_len = 0;
		$inp_len = strlen($input);
		$mode = 'next';
		$test = 'none';
		for ($k = 0; $k < $inp_len; ++$k) {
			$v = ord($input{$k});
			if ($v < 128) {
				$output[$out_len] = $v;
				++$out_len;
				if ('add' == $mode) {
					if (self::$safe_mode) {
						$output[$out_len-2] = self::$safe_char;
						$mode = 'next';
					} else {
						throw new Exception('Conversion from UTF-8 to UCS-4 failed: malformed input at byte '.$k);
					}
				}
				continue;
			}
			if ('next' == $mode) {
				$start_byte = $v;
				$mode = 'add';
				$test = 'range';
				if ($v >> 5 == 6) {
					$next_byte = 0;
					$v = ($v - 192) << 6;
				} elseif ($v >> 4 == 14) {
					$next_byte = 1;
					$v = ($v - 224) << 12;
				} elseif ($v >> 3 == 30) {
					$next_byte = 2;
					$v = ($v - 240) << 18;
				} elseif (self::$safe_mode) {
					$mode = 'next';
					$output[$out_len] = self::$safe_char;
					++$out_len;
					continue;
				} else {
					throw new Exception('This might be UTF-8, but I don\'t understand it at byte '.$k);
				}
				if ($inp_len-$k-$next_byte < 2) {
					$output[$out_len] = self::$safe_char;
					$mode = 'no';
					continue;
				}
				if ('add' == $mode) {
					$output[$out_len] = (int) $v;
					++$out_len;
					continue;
				}
			}
			if ('add' == $mode) {
				if (!self::$allow_overlong && $test == 'range') {
					$test = 'none';
					if (($v < 0xA0 && $start_byte == 0xE0) || ($v < 0x90 && $start_byte == 0xF0) || ($v > 0x8F && $start_byte == 0xF4)) {
						throw new Exception('Bogus UTF-8 character detected (out of legal range) at byte '.$k);
					}
				}
				if ($v >> 6 == 2) {
					$v = ($v-128) << ($next_byte*6);
					$output[($out_len-1)] += $v;
					--$next_byte;
				} else {
					if (self::$safe_mode) {
						$output[$out_len-1] = ord(self::$safe_char);
						$k--;
						$mode = 'next';
						continue;
					} else {
						throw new Exception('Conversion from UTF-8 to UCS-4 failed: malformed input at byte '.$k);
					}
				}
				if ($next_byte < 0) {
					$mode = 'next';
				}
			}
		}
		return $output;
	}

	private static function ucs4array_utf8($input) {
		$output = '';
		foreach ($input as $v) {
			if ($v < 128) {
				$output .= chr($v);
			} elseif ($v < (1 << 11)) {
				$output .= chr(192+($v >> 6)).chr(128+($v & 63));
			} elseif ($v < (1 << 16)) {
				$output .= chr(224+($v >> 12)).chr(128+(($v >> 6) & 63)).chr(128+($v & 63));
			} elseif ($v < (1 << 21)) {
				$output .= chr(240+($v >> 18)).chr(128+(($v >> 12) & 63)).chr(128+(($v >> 6) & 63)).chr(128+($v & 63));
			} elseif (self::$safe_mode) {
				$output .= self::$safe_char;
			} else {
				throw new Exception('Conversion from UCS-4 to UTF-8 failed: malformed input at byte '.$k);
			}
		}
		return $output;
	}

	private static function utf7imap_ucs4array($input) {
		return self::utf7_ucs4array(str_replace(',', '/', $input), '&');
	}

	private static function utf7_ucs4array($input, $sc = '+') {
		$output  = array();
		$out_len = 0;
		$inp_len = strlen($input);
		$mode	= 'd';
		$b64	 = '';
		for ($k = 0; $k < $inp_len; ++$k) {
			$c = $input{$k};
			if (0 == ord($c)) continue;
			if ('b' == $mode) {
				if (!preg_match('![A-Za-z0-9/'.preg_quote($sc, '!').']!', $c)) {
					if ('-' == $c) {
						if ($b64 == '') {
							$output[$out_len] = ord($sc);
							$out_len++;
							$mode = 'd';
							continue;
						}
					}
					$tmp = base64_decode($b64);
					$tmp = substr($tmp, -1 * (strlen($tmp) % 2));
					for ($i = 0; $i < strlen($tmp); $i++) {
						if ($i % 2) {
							$output[$out_len] += ord($tmp{$i});
							$out_len++;
						} else {
							$output[$out_len] = ord($tmp{$i}) << 8;
						}
					}
					$mode = 'd';
					$b64 = '';
					continue;
				} else {
					$b64 .= $c;
				}
			}
			if ('d' == $mode) {
				if ($sc == $c) {
					$mode = 'b';
					continue;
				}
				$output[$out_len] = ord($c);
				$out_len++;
			}
		}
		return $output;
	}

	private static function ucs4array_utf7imap($input) {
		return str_replace('/', ',', self::ucs4array_utf7($input, '&'));
	}

	private static function ucs4array_utf7($input, $sc = '+') {
		$output = '';
		$mode = 'd';
		$b64 = '';
		while (true) {
			$v = (!empty($input)) ? array_shift($input) : false;
			$is_direct = (false !== $v) ? (0x20 <= $v && $v <= 0x7e && $v != ord($sc)) : true;
			if ($mode == 'b') {
				if ($is_direct) {
					if ($b64 == chr(0).$sc) {
						$output .= $sc.'-';
						$b64 = '';
					} elseif ($b64) {
						$output .= $sc.str_replace('=', '', base64_encode($b64)).'-';
						$b64 = '';
					}
					$mode = 'd';
				} elseif (false !== $v) {
					$b64 .= chr(($v >> 8) & 255). chr($v & 255);
				}
			}
			if ($mode == 'd' && false !== $v) {
				if ($is_direct) {
					$output .= chr($v);
				} else {
					$b64 = chr(($v >> 8) & 255). chr($v & 255);
					$mode = 'b';
				}
			}
			if (false === $v && $b64 == '') break;
		}
		return $output;
	}

	private static function ucs4array_ucs4($input) {
		$output = '';
		foreach ($input as $v) $output .= chr(($v >> 24) & 255).chr(($v >> 16) & 255).chr(($v >> 8) & 255).chr($v & 255);
		return $output;
	}

	private static function ucs4_ucs4array($input) {
		$output = array();
		$inp_len = strlen($input);
		if ($inp_len % 4) throw new Exception('Input UCS4 string is broken');
		if (!$inp_len) return $output;
		for ($i = 0, $out_len = -1; $i < $inp_len; ++$i) {
			if (!($i % 4)) {
				$out_len++;
				$output[$out_len] = 0;
			}
			$output[$out_len] += ord($input{$i}) << (8 * (3 - ($i % 4) ) );
		}
		return $output;
	}
}
