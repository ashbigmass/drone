<?php
require_once 'Net/URL2.php';
require_once 'HTTP/Request2/Exception.php';

class HTTP_Request2 implements SplSubject
{
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_GET	 = 'GET';
	const METHOD_HEAD	= 'HEAD';
	const METHOD_POST	= 'POST';
	const METHOD_PUT	 = 'PUT';
	const METHOD_DELETE  = 'DELETE';
	const METHOD_TRACE   = 'TRACE';
	const METHOD_CONNECT = 'CONNECT';
	const AUTH_BASIC  = 'basic';
	const AUTH_DIGEST = 'digest';
	const REGEXP_INVALID_TOKEN = '![\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]!';
	const REGEXP_INVALID_COOKIE = '/[\s,;]/';
	private static $_fileinfoDb;
	protected $observers = array();
	protected $url;
	protected $method = self::METHOD_GET;
	protected $auth;
	protected $headers = array();
	protected $config = array(
		'adapter'		   => 'HTTP_Request2_Adapter_Socket',
		'connect_timeout'   => 10,
		'timeout'		   => 0,
		'use_brackets'	  => true,
		'protocol_version'  => '1.1',
		'buffer_size'	   => 16384,
		'store_body'		=> true,
		'proxy_host'		=> '',
		'proxy_port'		=> '',
		'proxy_user'		=> '',
		'proxy_password'	=> '',
		'proxy_auth_scheme' => self::AUTH_BASIC,
		'ssl_verify_peer'   => true,
		'ssl_verify_host'   => true,
		'ssl_cafile'		=> null,
		'ssl_capath'		=> null,
		'ssl_local_cert'	=> null,
		'ssl_passphrase'	=> null,
		'digest_compat_ie'  => false,
		'follow_redirects'  => false,
		'max_redirects'	 => 5,
		'strict_redirects'  => false
	);
	protected $lastEvent = array(
		'name' => 'start',
		'data' => null
	);
	protected $body = '';
	protected $postParams = array();
	protected $uploads = array();
	protected $adapter;

	public function __construct($url = null, $method = self::METHOD_GET, array $config = array()) {
		$this->setConfig($config);
		if (!empty($url)) $this->setUrl($url);
		if (!empty($method)) $this->setMethod($method);
		$this->setHeader('user-agent', 'HTTP_Request2/0.5.2 ' . '(http://pear.php.net/package/http_request2) ' . 'PHP/' . phpversion());
	}

	public function setUrl($url) {
		if (is_string($url)) $url = new Net_URL2($url, array(Net_URL2::OPTION_USE_BRACKETS => $this->config['use_brackets']));
		if (!$url instanceof Net_URL2) throw new HTTP_Request2_Exception('Parameter is not a valid HTTP URL');
		if ($url->getUserinfo()) {
			$username = $url->getUser();
			$password = $url->getPassword();
			$this->setAuth(rawurldecode($username), $password? rawurldecode($password): '');
			$url->setUserinfo('');
		}
		if ('' == $url->getPath()) $url->setPath('/');
		$this->url = $url;
		return $this;
	}

	public function getUrl() {
		return $this->url;
	}

	public function setMethod($method) {
		if (preg_match(self::REGEXP_INVALID_TOKEN, $method)) throw new HTTP_Request2_Exception("Invalid request method '{$method}'");
		$this->method = $method;
		return $this;
	}

	public function getMethod() {
		return $this->method;
	}

	public function setConfig($nameOrConfig, $value = null) {
		if (is_array($nameOrConfig)) {
			foreach ($nameOrConfig as $name => $value) $this->setConfig($name, $value);
		} else {
			if (!array_key_exists($nameOrConfig, $this->config)) throw new HTTP_Request2_Exception("Unknown configuration parameter '{$nameOrConfig}'");
			$this->config[$nameOrConfig] = $value;
		}
		return $this;
	}

	public function getConfig($name = null) {
		if (null === $name) return $this->config;
		elseif (!array_key_exists($name, $this->config)) throw new HTTP_Request2_Exception("Unknown configuration parameter '{$name}'");
		return $this->config[$name];
	}

	public function setAuth($user, $password = '', $scheme = self::AUTH_BASIC) {
		if (empty($user)) $this->auth = null;
		else $this->auth = array('user'	 => (string)$user, 'password' => (string)$password, 'scheme'   => $scheme);
		return $this;
	}

	public function getAuth() {
		return $this->auth;
	}

	public function setHeader($name, $value = null) {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				if (is_string($k)) $this->setHeader($k, $v);
				else $this->setHeader($v);
			}
		} else {
			if (null === $value && strpos($name, ':')) list($name, $value) = array_map('trim', explode(':', $name, 2));
			if (preg_match(self::REGEXP_INVALID_TOKEN, $name)) throw new HTTP_Request2_Exception("Invalid header name '{$name}'");
			$name = strtolower($name);
			if (null === $value) unset($this->headers[$name]);
			else $this->headers[$name] = $value;
		}
		return $this;
	}

	public function getHeaders() {
		return $this->headers;
	}

	public function addCookie($name, $value) {
		$cookie = $name . '=' . $value;
		if (preg_match(self::REGEXP_INVALID_COOKIE, $cookie)) throw new HTTP_Request2_Exception("Invalid cookie: '{$cookie}'");
		$cookies = empty($this->headers['cookie'])? '': $this->headers['cookie'] . '; ';
		$this->setHeader('cookie', $cookies . $cookie);
		return $this;
	}

	public function setBody($body, $isFilename = false) {
		if (!$isFilename) {
			if (!$body instanceof HTTP_Request2_MultipartBody) $this->body = (string)$body;
			else $this->body = $body;
		} else {
			if (!($fp = @fopen($body, 'rb'))) throw new HTTP_Request2_Exception("Cannot open file {$body}");
			$this->body = $fp;
			if (empty($this->headers['content-type'])) $this->setHeader('content-type', self::detectMimeType($body));
		}
		$this->postParams = $this->uploads = array();
		return $this;
	}

	public function getBody() {
		if (self::METHOD_POST == $this->method && (!empty($this->postParams) || !empty($this->uploads))) {
			if ('application/x-www-form-urlencoded' == $this->headers['content-type']) {
				$body = http_build_query($this->postParams, '', '&');
				if (!$this->getConfig('use_brackets')) $body = preg_replace('/%5B\d+%5D=/', '=', $body);
				return str_replace('%7E', '~', $body);
			} elseif ('multipart/form-data' == $this->headers['content-type']) {
				require_once 'HTTP/Request2/MultipartBody.php';
				return new HTTP_Request2_MultipartBody($this->postParams, $this->uploads, $this->getConfig('use_brackets'));
			}
		}
		return $this->body;
	}

	public function addUpload($fieldName, $filename, $sendFilename = null, $contentType = null) {
		if (!is_array($filename)) {
			if (!($fp = @fopen($filename, 'rb'))) throw new HTTP_Request2_Exception("Cannot open file {$filename}");
			$this->uploads[$fieldName] = array(
				'fp'		=> $fp,
				'filename'  => empty($sendFilename)? basename($filename): $sendFilename,
				'size'	  => filesize($filename),
				'type'	  => empty($contentType)? self::detectMimeType($filename): $contentType
			);
		} else {
			$fps = $names = $sizes = $types = array();
			foreach ($filename as $f) {
				if (!is_array($f)) $f = array($f);
				if (!($fp = @fopen($f[0], 'rb'))) throw new HTTP_Request2_Exception("Cannot open file {$f[0]}");
				$fps[]   = $fp;
				$names[] = empty($f[1])? basename($f[0]): $f[1];
				$sizes[] = filesize($f[0]);
				$types[] = empty($f[2])? self::detectMimeType($f[0]): $f[2];
			}
			$this->uploads[$fieldName] = array('fp' => $fps, 'filename' => $names, 'size' => $sizes, 'type' => $types);
		}
		if (empty($this->headers['content-type']) || 'application/x-www-form-urlencoded' == $this->headers['content-type'] ) {
			$this->setHeader('content-type', 'multipart/form-data');
		}
		return $this;
	}

	public function addPostParameter($name, $value = null) {
		if (!is_array($name)) {
			$this->postParams[$name] = $value;
		} else {
			foreach ($name as $k => $v) $this->addPostParameter($k, $v);
		}
		if (empty($this->headers['content-type'])) $this->setHeader('content-type', 'application/x-www-form-urlencoded');
		return $this;
	}

	public function attach(SplObserver $observer) {
		foreach ($this->observers as $attached) {
			if ($attached === $observer) return;
		}
		$this->observers[] = $observer;
	}

	public function detach(SplObserver $observer) {
		foreach ($this->observers as $key => $attached) {
			if ($attached === $observer) {
				unset($this->observers[$key]);
				return;
			}
		}
	}

	public function notify() {
		foreach ($this->observers as $observer) $observer->update($this);
	}

	public function setLastEvent($name, $data = null) {
		$this->lastEvent = array('name' => $name, 'data' => $data);
		$this->notify();
	}

	public function getLastEvent() {
		return $this->lastEvent;
	}

	public function setAdapter($adapter) {
		if (is_string($adapter)) {
			if (!class_exists($adapter, false)) {
				if (false === strpos($adapter, '_')) $adapter = 'HTTP_Request2_Adapter_' . ucfirst($adapter);
				if (preg_match('/^HTTP_Request2_Adapter_([a-zA-Z0-9]+)$/', $adapter)) include_once str_replace('_', DIRECTORY_SEPARATOR, $adapter) . '.php';
				if (!class_exists($adapter, false)) throw new HTTP_Request2_Exception("Class {$adapter} not found");
			}
			$adapter = new $adapter;
		}
		if (!$adapter instanceof HTTP_Request2_Adapter) throw new HTTP_Request2_Exception('Parameter is not a HTTP request adapter');
		$this->adapter = $adapter;
		return $this;
	}

	public function send() {
		if (!$this->url instanceof Net_URL2) throw new HTTP_Request2_Exception('No URL given');
		elseif (!$this->url->isAbsolute()) throw new HTTP_Request2_Exception('Absolute URL required');
		elseif (!in_array(strtolower($this->url->getScheme()), array('https', 'http'))) throw new HTTP_Request2_Exception('Not a HTTP URL');
		if (empty($this->adapter)) $this->setAdapter($this->getConfig('adapter'));
		if ($magicQuotes = get_magic_quotes_runtime()) set_magic_quotes_runtime(false);
		if (extension_loaded('mbstring') && (2 & ini_get('mbstring.func_overload'))) {
			$oldEncoding = mb_internal_encoding();
			mb_internal_encoding('iso-8859-1');
		}
		try {
			$response = $this->adapter->sendRequest($this);
		} catch (Exception $e) {
		}
		if ($magicQuotes) set_magic_quotes_runtime(true);
		if (!empty($oldEncoding)) mb_internal_encoding($oldEncoding);
		if (!empty($e)) throw $e;
		return $response;
	}

	protected static function detectMimeType($filename) {
		if (function_exists('finfo_open')) {
			if (!isset(self::$_fileinfoDb)) self::$_fileinfoDb = @finfo_open(FILEINFO_MIME);
			if (self::$_fileinfoDb) $info = finfo_file(self::$_fileinfoDb, $filename);
		}
		if (empty($info) && function_exists('mime_content_type')) return mime_content_type($filename);
		}
		return empty($info)? 'application/octet-stream': $info;
	}
}
?>