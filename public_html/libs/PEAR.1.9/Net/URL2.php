<?php
class Net_URL2
{
	const OPTION_STRICT = 'strict';
	const OPTION_USE_BRACKETS = 'use_brackets';
	const OPTION_ENCODE_KEYS = 'encode_keys';
	const OPTION_SEPARATOR_INPUT = 'input_separator';
	const OPTION_SEPARATOR_OUTPUT = 'output_separator';
	private $_options = array(
		self::OPTION_STRICT		   => true,
		self::OPTION_USE_BRACKETS	 => true,
		self::OPTION_ENCODE_KEYS	  => true,
		self::OPTION_SEPARATOR_INPUT  => '&',
		self::OPTION_SEPARATOR_OUTPUT => '&',
		);
	private $_scheme = false;
	private $_userinfo = false;
	private $_host = false;
	private $_port = false;
	private $_path = '';
	private $_query = false;
	private $_fragment = false;

	public function __construct($url, array $options = array()) {
		foreach ($options as $optionName => $value) {
			if (array_key_exists($optionName, $this->_options)) $this->_options[$optionName] = $value;
		}
		preg_match('!^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?!', $url, $matches);
		$this->_scheme = !empty($matches[1]) ? $matches[2] : false;
		$this->setAuthority(!empty($matches[3]) ? $matches[4] : false);
		$this->_path = $matches[5];
		$this->_query = !empty($matches[6]) ? $matches[7] : false;
		$this->_fragment = !empty($matches[8]) ? $matches[9] : false;
	}

	public function __set($var, $arg) {
		$method = 'set' . $var;
		if (method_exists($this, $method)) $this->$method($arg);
	}

	public function __get($var) {
		$method = 'get' . $var;
		if (method_exists($this, $method)) return $this->$method();
		return false;
	}

	public function getScheme() {
		return $this->_scheme;
	}

	public function setScheme($scheme) {
		$this->_scheme = $scheme;
	}

	public function getUser() {
		return $this->_userinfo !== false ? preg_replace('@:.*$@', '', $this->_userinfo) : false;
	}

	public function getPassword() {
		return $this->_userinfo !== false ? substr(strstr($this->_userinfo, ':'), 1) : false;
	}

	public function getUserinfo() {
		return $this->_userinfo;
	}

	public function setUserinfo($userinfo, $password = false) {
		$this->_userinfo = $userinfo;
		if ($password !== false) $this->_userinfo .= ':' . $password;
	}

	public function getHost() {
		return $this->_host;
	}

	public function setHost($host) {
		$this->_host = $host;
	}

	public function getPort() {
		return $this->_port;
	}

	public function setPort($port) {
		$this->_port = $port;
	}

	public function getAuthority() {
		if (!$this->_host) return false;
		$authority = '';
		if ($this->_userinfo !== false) $authority .= $this->_userinfo . '@';
		$authority .= $this->_host;
		if ($this->_port !== false) $authority .= ':' . $this->_port;
		return $authority;
	}

	public function setAuthority($authority) {
		$this->_userinfo = false;
		$this->_host	 = false;
		$this->_port	 = false;
		if (preg_match('@^(([^\@]*)\@)?([^:]+)(:(\d*))?$@', $authority, $reg)) {
			if ($reg[1]) $this->_userinfo = $reg[2];
			$this->_host = $reg[3];
			if (isset($reg[5])) $this->_port = $reg[5];
		}
	}

	public function getPath() {
		return $this->_path;
	}

	public function setPath($path) {
		$this->_path = $path;
	}

	public function getQuery() {
		return $this->_query;
	}

	public function setQuery($query) {
		$this->_query = $query;
	}

	public function getFragment() {
		return $this->_fragment;
	}

	public function setFragment($fragment) {
		$this->_fragment = $fragment;
	}

	public function getQueryVariables() {
		$pattern = '/[' . preg_quote($this->getOption(self::OPTION_SEPARATOR_INPUT), '/') . ']/';
		$parts   = preg_split($pattern, $this->_query, -1, PREG_SPLIT_NO_EMPTY);
		$return  = array();
		foreach ($parts as $part) {
			if (strpos($part, '=') !== false) {
				list($key, $value) = explode('=', $part, 2);
			} else {
				$key   = $part;
				$value = null;
			}
			if ($this->getOption(self::OPTION_ENCODE_KEYS)) $key = rawurldecode($key);
			$value = rawurldecode($value);
			if ($this->getOption(self::OPTION_USE_BRACKETS) && preg_match('#^(.*)\[([0-9a-z_-]*)\]#i', $key, $matches)) {
				$key = $matches[1];
				$idx = $matches[2];
				if (empty($return[$key]) || !is_array($return[$key])) $return[$key] = array();
				if ($idx === '') $return[$key][] = $value;
				else $return[$key][$idx] = $value;
			} elseif (!$this->getOption(self::OPTION_USE_BRACKETS) && !empty($return[$key])) {
				$return[$key]   = (array) $return[$key];
				$return[$key][] = $value;
			} else {
				$return[$key] = $value;
			}
		}
		return $return;
	}

	public function setQueryVariables(array $array) {
		if (!$array) {
			$this->_query = false;
		} else {
			foreach ($array as $name => $value) {
				if ($this->getOption(self::OPTION_ENCODE_KEYS)) $name = self::urlencode($name);
				if (is_array($value)) {
					foreach ($value as $k => $v) {
						$parts[] = $this->getOption(self::OPTION_USE_BRACKETS) ? sprintf('%s[%s]=%s', $name, $k, $v) : ($name . '=' . $v);
					}
				} elseif (!is_null($value)) {
					$parts[] = $name . '=' . self::urlencode($value);
				} else {
					$parts[] = $name;
				}
			}
			$this->_query = implode($this->getOption(self::OPTION_SEPARATOR_OUTPUT), $parts);
		}
	}

	public function setQueryVariable($name, $value) {
		$array = $this->getQueryVariables();
		$array[$name] = $value;
		$this->setQueryVariables($array);
	}

	public function unsetQueryVariable($name) {
		$array = $this->getQueryVariables();
		unset($array[$name]);
		$this->setQueryVariables($array);
	}

	public function getURL() {
		$url = "";
		if ($this->_scheme !== false) $url .= $this->_scheme . ':';
		$authority = $this->getAuthority();
		if ($authority !== false) $url .= '//' . $authority;
		$url .= $this->_path;
		if ($this->_query !== false) $url .= '?' . $this->_query;
		if ($this->_fragment !== false) $url .= '#' . $this->_fragment;
		return $url;
	}

	public function __toString() {
		return $this->getURL();
	}

	public function getNormalizedURL() {
		$url = clone $this;
		$url->normalize();
		return $url->getUrl();
	}

	public function normalize() {
		if ($this->_scheme) $this->_scheme = strtolower($this->_scheme);
		if ($this->_host) $this->_host = strtolower($this->_host);
		if ($this->_port && $this->_scheme && $this->_port == getservbyname($this->_scheme, 'tcp')) $this->_port = false;
		foreach (array('_userinfo', '_host', '_path') as $part) {
			if ($this->$part) $this->$part = preg_replace('/%[0-9a-f]{2}/ie', 'strtoupper("\0")', $this->$part);
		}
		$this->_path = self::removeDotSegments($this->_path);
		if ($this->_host && !$this->_path) $this->_path = '/';
	}

	public function isAbsolute() {
		return (bool) $this->_scheme;
	}

	public function resolve($reference) {
		if (!$reference instanceof Net_URL2) $reference = new self($reference);
		if (!$this->isAbsolute()) throw new Exception('Base-URL must be absolute');
		if (!$this->getOption(self::OPTION_STRICT) && $reference->_scheme == $this->_scheme) $reference->_scheme = false;
		$target = new self('');
		if ($reference->_scheme !== false) {
			$target->_scheme = $reference->_scheme;
			$target->setAuthority($reference->getAuthority());
			$target->_path  = self::removeDotSegments($reference->_path);
			$target->_query = $reference->_query;
		} else {
			$authority = $reference->getAuthority();
			if ($authority !== false) {
				$target->setAuthority($authority);
				$target->_path  = self::removeDotSegments($reference->_path);
				$target->_query = $reference->_query;
			} else {
				if ($reference->_path == '') {
					$target->_path = $this->_path;
					if ($reference->_query !== false) $target->_query = $reference->_query;
					else $target->_query = $this->_query;
				} else {
					if (substr($reference->_path, 0, 1) == '/') {
						$target->_path = self::removeDotSegments($reference->_path);
					} else {
						if ($this->_host !== false && $this->_path == '') {
							$target->_path = '/' . $this->_path;
						} else {
							$i = strrpos($this->_path, '/');
							if ($i !== false) $target->_path = substr($this->_path, 0, $i + 1);
							$target->_path .= $reference->_path;
						}
						$target->_path = self::removeDotSegments($target->_path);
					}
					$target->_query = $reference->_query;
				}
				$target->setAuthority($this->getAuthority());
			}
			$target->_scheme = $this->_scheme;
		}
		$target->_fragment = $reference->_fragment;
		return $target;
	}

	public static function removeDotSegments($path) {
		$output = '';
		$j = 0;
		while ($path && $j++ < 100) {
			if (substr($path, 0, 2) == './') {
				$path = substr($path, 2);
			} elseif (substr($path, 0, 3) == '../') {
				$path = substr($path, 3);
			} elseif (substr($path, 0, 3) == '/./' || $path == '/.') {
				$path = '/' . substr($path, 3);
			} elseif (substr($path, 0, 4) == '/../' || $path == '/..') {
				$path   = '/' . substr($path, 4);
				$i	  = strrpos($output, '/');
				$output = $i === false ? '' : substr($output, 0, $i);
			} elseif ($path == '.' || $path == '..') {
				$path = '';
			} else {
				$i = strpos($path, '/');
				if ($i === 0) $i = strpos($path, '/', 1);
				if ($i === false) $i = strlen($path);
				$output .= substr($path, 0, $i);
				$path = substr($path, $i);
			}
		}
		return $output;
	}

	public static function urlencode($string) {
		$encoded = rawurlencode($string);
		$encoded = str_replace('%7E', '~', $encoded);
		return $encoded;
	}

	public static function getCanonical() {
		if (!isset($_SERVER['REQUEST_METHOD'])) throw new Exception('Script was not called through a webserver');
		$url = new self($_SERVER['PHP_SELF']);
		$url->_scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
		$url->_host   = $_SERVER['SERVER_NAME'];
		$port = $_SERVER['SERVER_PORT'];
		if ($url->_scheme == 'http' && $port != 80 || $url->_scheme == 'https' && $port != 443) $url->_port = $port;
		return $url;
	}

	public static function getRequestedURL() {
		return self::getRequested()->getUrl();
	}

	public static function getRequested() {
		if (!isset($_SERVER['REQUEST_METHOD'])) throw new Exception('Script was not called through a webserver');
		$url = new self($_SERVER['REQUEST_URI']);
		$url->_scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
		$url->setAuthority($_SERVER['HTTP_HOST']);
		return $url;
	}

	function getOption($optionName) {
		return isset($this->_options[$optionName]) ? $this->_options[$optionName] : false;
	}
}
