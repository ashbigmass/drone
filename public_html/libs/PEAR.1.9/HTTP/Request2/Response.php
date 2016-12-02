<?php
require_once 'HTTP/Request2/Exception.php';

class HTTP_Request2_Response
{
	protected $version;
	protected $code;
	protected $reasonPhrase;
	protected $headers = array();
	protected $cookies = array();
	protected $lastHeader = null;
	protected $body = '';
	protected $bodyEncoded;
	protected static $phrases = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',  // 1.1
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		509 => 'Bandwidth Limit Exceeded',
	);

	public function __construct($statusLine, $bodyEncoded = true) {
		if (!preg_match('!^HTTP/(\d\.\d) (\d{3})(?: (.+))?!', $statusLine, $m)) throw new HTTP_Request2_Exception("Malformed response: {$statusLine}");
		$this->version = $m[1];
		$this->code	= intval($m[2]);
		if (!empty($m[3])) $this->reasonPhrase = trim($m[3]);
		elseif (!empty(self::$phrases[$this->code])) $this->reasonPhrase = self::$phrases[$this->code];
		$this->bodyEncoded = (bool)$bodyEncoded;
	}

	public function parseHeaderLine($headerLine) {
		$headerLine = trim($headerLine, "\r\n");
		if ('' == $headerLine) {
			if (!empty($this->headers['set-cookie'])) {
				$cookies = is_array($this->headers['set-cookie']) ? $this->headers['set-cookie'] : array($this->headers['set-cookie']);
				foreach ($cookies as $cookieString) $this->parseCookie($cookieString);
				unset($this->headers['set-cookie']);
			}
			foreach (array_keys($this->headers) as $k) {
				if (is_array($this->headers[$k])) $this->headers[$k] = implode(', ', $this->headers[$k]);
			}
		} elseif (preg_match('!^([^\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]+):(.+)$!', $headerLine, $m)) {
			$name  = strtolower($m[1]);
			$value = trim($m[2]);
			if (empty($this->headers[$name])) {
				$this->headers[$name] = $value;
			} else {
				if (!is_array($this->headers[$name])) $this->headers[$name] = array($this->headers[$name]);
				$this->headers[$name][] = $value;
			}
			$this->lastHeader = $name;
		} elseif (preg_match('!^\s+(.+)$!', $headerLine, $m) && $this->lastHeader) {
			if (!is_array($this->headers[$this->lastHeader])) {
				$this->headers[$this->lastHeader] .= ' ' . trim($m[1]);
			} else {
				$key = count($this->headers[$this->lastHeader]) - 1;
				$this->headers[$this->lastHeader][$key] .= ' ' . trim($m[1]);
			}
		}
	}

	protected function parseCookie($cookieString) {
		$cookie = array('expires' => null, 'domain'  => null, 'path'	=> null, 'secure'  => false);
		if (!strpos($cookieString, ';')) {
			$pos = strpos($cookieString, '=');
			$cookie['name']  = trim(substr($cookieString, 0, $pos));
			$cookie['value'] = trim(substr($cookieString, $pos + 1));
		} else {
			$elements = explode(';', $cookieString);
			$pos = strpos($elements[0], '=');
			$cookie['name']  = trim(substr($elements[0], 0, $pos));
			$cookie['value'] = trim(substr($elements[0], $pos + 1));
			for ($i = 1; $i < count($elements); $i++) {
				if (false === strpos($elements[$i], '=')) {
					$elName  = trim($elements[$i]);
					$elValue = null;
				} else {
					list ($elName, $elValue) = array_map('trim', explode('=', $elements[$i]));
				}
				$elName = strtolower($elName);
				if ('secure' == $elName) $cookie['secure'] = true;
				elseif ('expires' == $elName) $cookie['expires'] = str_replace('"', '', $elValue);
				elseif ('path' == $elName || 'domain' == $elName) $cookie[$elName] = urldecode($elValue);
				else $cookie[$elName] = $elValue;
			}
		}
		$this->cookies[] = $cookie;
	}

	public function appendBody($bodyChunk) {
		$this->body .= $bodyChunk;
	}

	public function getStatus() {
		return $this->code;
	}

	public function getReasonPhrase() {
		return $this->reasonPhrase;
	}

	public function isRedirect() {
		return in_array($this->code, array(300, 301, 302, 303, 307)) && isset($this->headers['location']);
	}

	public function getHeader($headerName = null) {
		if (null === $headerName) {
			return $this->headers;
		} else {
			$headerName = strtolower($headerName);
			return isset($this->headers[$headerName])? $this->headers[$headerName]: null;
		}
	}

	public function getCookies() {
		return $this->cookies;
	}

	public function getBody() {
		if (!$this->bodyEncoded || !in_array(strtolower($this->getHeader('content-encoding')), array('gzip', 'deflate'))) {
			return $this->body;
		} else {
			if (extension_loaded('mbstring') && (2 & ini_get('mbstring.func_overload'))) {
				$oldEncoding = mb_internal_encoding();
				mb_internal_encoding('iso-8859-1');
			}
			try {
				switch (strtolower($this->getHeader('content-encoding'))) {
					case 'gzip': $decoded = self::decodeGzip($this->body); break;
					case 'deflate': $decoded = self::decodeDeflate($this->body);
				}
			} catch (Exception $e) {
			}
			if (!empty($oldEncoding)) mb_internal_encoding($oldEncoding);
			if (!empty($e)) throw $e;
			return $decoded;
		}
	}

	public function getVersion() {
		return $this->version;
	}

	public static function decodeGzip($data) {
		$length = strlen($data);
		if (18 > $length || strcmp(substr($data, 0, 2), "\x1f\x8b")) return $data;
		if (!function_exists('gzinflate')) throw new HTTP_Request2_Exception('Unable to decode body: gzip extension not available');
		$method = ord(substr($data, 2, 1));
		if (8 != $method) throw new HTTP_Request2_Exception('Error parsing gzip header: unknown compression method');
		$flags = ord(substr($data, 3, 1));
		if ($flags & 224) throw new HTTP_Request2_Exception('Error parsing gzip header: reserved bits are set');
		$headerLength = 10;
		if ($flags & 4) {
			if ($length - $headerLength - 2 < 8) throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			$extraLength = unpack('v', substr($data, 10, 2));
			if ($length - $headerLength - 2 - $extraLength[1] < 8) throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			$headerLength += $extraLength[1] + 2;
		}
		if ($flags & 8) {
			if ($length - $headerLength - 1 < 8) throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			$filenameLength = strpos(substr($data, $headerLength), chr(0));
			if (false === $filenameLength || $length - $headerLength - $filenameLength - 1 < 8) {
				throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			}
			$headerLength += $filenameLength + 1;
		}
		if ($flags & 16) {
			if ($length - $headerLength - 1 < 8) throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			$commentLength = strpos(substr($data, $headerLength), chr(0));
			if (false === $commentLength || $length - $headerLength - $commentLength - 1 < 8) {
				throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			}
			$headerLength += $commentLength + 1;
		}
		if ($flags & 2) {
			if ($length - $headerLength - 2 < 8) throw new HTTP_Request2_Exception('Error parsing gzip header: data too short');
			$crcReal   = 0xffff & crc32(substr($data, 0, $headerLength));
			$crcStored = unpack('v', substr($data, $headerLength, 2));
			if ($crcReal != $crcStored[1]) throw new HTTP_Request2_Exception('Header CRC check failed');
			$headerLength += 2;
		}
		$tmp = unpack('V2', substr($data, -8));
		$dataCrc  = $tmp[1];
		$dataSize = $tmp[2];
		$unpacked = gzinflate(substr($data, $headerLength, -8));
		if (false === $unpacked) throw new HTTP_Request2_Exception('gzinflate() call failed');
		elseif ($dataSize != strlen($unpacked)) throw new HTTP_Request2_Exception('Data size check failed');
		elseif ((0xffffffff & $dataCrc) != (0xffffffff & crc32($unpacked))) throw new HTTP_Request2_Exception('Data CRC check failed');
		return $unpacked;
	}

	public static function decodeDeflate($data) {
		if (!function_exists('gzuncompress')) throw new HTTP_Request2_Exception('Unable to decode body: gzip extension not available');
		$header = unpack('n', substr($data, 0, 2));
		return (0 == $header[1] % 31)? gzuncompress($data): gzinflate($data);
	}
}
