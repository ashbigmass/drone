<?php
require_once 'HTTP/Request2/Adapter.php';

class HTTP_Request2_Adapter_Socket extends HTTP_Request2_Adapter
{
	const REGEXP_TOKEN = '[^\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]+';
	const REGEXP_QUOTED_STRING = '"(?:\\\\.|[^\\\\"])*"';
	protected static $sockets = array();
	protected static $challenges = array();
	protected $socket;
	protected $serverChallenge;
	protected $proxyChallenge;
	protected $deadline = null;
	protected $chunkLength = 0;
	protected $redirectCountdown = null;

	public function sendRequest(HTTP_Request2 $request) {
		$this->request = $request;
		if ($timeout = $request->getConfig('timeout')) $this->deadline = time() + $timeout;
		else $this->deadline = null;
		try {
			$keepAlive = $this->connect();
			$headers	= $this->prepareHeaders();
			if (false === @fwrite($this->socket, $headers, strlen($headers))) throw new HTTP_Request2_Exception('Error writing request');
			$this->request->setLastEvent('sentHeaders', $headers);
			$this->writeBody();
			if ($this->deadline && time() > $this->deadline) throw new HTTP_Request2_Exception('Request timed out after ' . $request->getConfig('timeout') . ' second(s)');
			$response = $this->readResponse();
			if (!$this->canKeepAlive($keepAlive, $response)) $this->disconnect();
			if ($this->shouldUseProxyDigestAuth($response)) return $this->sendRequest($request);
			if ($this->shouldUseServerDigestAuth($response)) return $this->sendRequest($request);
			if ($authInfo = $response->getHeader('authentication-info')) $this->updateChallenge($this->serverChallenge, $authInfo);
			if ($proxyInfo = $response->getHeader('proxy-authentication-info')) $this->updateChallenge($this->proxyChallenge, $proxyInfo);
		} catch (Exception $e) {
			$this->disconnect();
		}
		unset($this->request, $this->requestBody);
		if (!empty($e)) throw $e;
		if (!$request->getConfig('follow_redirects') || !$response->isRedirect()) return $response;
		else return $this->handleRedirect($request, $response);
	}

	protected function connect() {
		$secure  = 0 == strcasecmp($this->request->getUrl()->getScheme(), 'https');
		$tunnel  = HTTP_Request2::METHOD_CONNECT == $this->request->getMethod();
		$headers = $this->request->getHeaders();
		$reqHost = $this->request->getUrl()->getHost();
		if (!($reqPort = $this->request->getUrl()->getPort())) $reqPort = $secure? 443: 80;
		if ($host = $this->request->getConfig('proxy_host')) {
			if (!($port = $this->request->getConfig('proxy_port'))) throw new HTTP_Request2_Exception('Proxy port not provided');
			$proxy = true;
		} else {
			$host  = $reqHost;
			$port  = $reqPort;
			$proxy = false;
		}
		if ($tunnel && !$proxy) throw new HTTP_Request2_Exception("Trying to perform CONNECT request without proxy");
		if ($secure && !in_array('ssl', stream_get_transports())) throw new HTTP_Request2_Exception('Need OpenSSL support for https:// requests');
		if ($proxy && !$secure && !empty($headers['connection']) && 'Keep-Alive' == $headers['connection']) $this->request->setHeader('connection');
		$keepAlive = ('1.1' == $this->request->getConfig('protocol_version') &&
			  empty($headers['connection'])) ||
			 (!empty($headers['connection']) &&
			  'Keep-Alive' == $headers['connection']);
		$host = ((!$secure || $proxy)? 'tcp://': 'ssl://') . $host;
		$options = array();
		if ($secure || $tunnel) {
			foreach ($this->request->getConfig() as $name => $value) {
				if ('ssl_' == substr($name, 0, 4) && null !== $value) {
					if ('ssl_verify_host' == $name) {
						if ($value) $options['CN_match'] = $reqHost;
					} else {
						$options[substr($name, 4)] = $value;
					}
				}
			}
			ksort($options);
		}
		$remote	= $host . ':' . $port;
		$socketKey = $remote . (($secure && $proxy)? "->{$reqHost}:{$reqPort}": '') . (empty($options)? '': ':' . serialize($options));
		unset($this->socket);
		if ($keepAlive && !empty(self::$sockets[$socketKey]) && !feof(self::$sockets[$socketKey])) {
			$this->socket =& self::$sockets[$socketKey];
		} elseif ($secure && $proxy && !$tunnel) {
			$this->establishTunnel();
			$this->request->setLastEvent('connect', "ssl://{$reqHost}:{$reqPort} via {$host}:{$port}");
			self::$sockets[$socketKey] =& $this->socket;
		} else {
			$context = stream_context_create();
			foreach ($options as $name => $value) {
				if (!stream_context_set_option($context, 'ssl', $name, $value)) {
					throw new HTTP_Request2_Exception("Error setting SSL context option '{$name}'");
				}
			}
			$this->socket = @stream_socket_client($remote, $errno, $errstr, $this->request->getConfig('connect_timeout'), STREAM_CLIENT_CONNECT, $context);
			if (!$this->socket) throw new HTTP_Request2_Exception("Unable to connect to {$remote}. Error #{$errno}: {$errstr}");
			$this->request->setLastEvent('connect', $remote);
			self::$sockets[$socketKey] =& $this->socket;
		}
		return $keepAlive;
	}

	protected function establishTunnel() {
		$donor	= new self;
		$connect = new HTTP_Request2(
			$this->request->getUrl(), HTTP_Request2::METHOD_CONNECT,
			array_merge($this->request->getConfig(),
						array('adapter' => $donor))
		);
		$response = $connect->send();
		if (200 > $response->getStatus() || 300 <= $response->getStatus()) {
			throw new HTTP_Request2_Exception(
				'Failed to connect via HTTPS proxy. Proxy response: ' .
				$response->getStatus() . ' ' . $response->getReasonPhrase()
			);
		}
		$this->socket = $donor->socket;
		$modes = array(
			STREAM_CRYPTO_METHOD_TLS_CLIENT,
			STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
			STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
			STREAM_CRYPTO_METHOD_SSLv2_CLIENT
		);
		foreach ($modes as $mode) {
			if (stream_socket_enable_crypto($this->socket, true, $mode)) return;
		}
		throw new HTTP_Request2_Exception('Failed to enable secure connection when connecting through proxy');
	}

	protected function canKeepAlive($requestKeepAlive, HTTP_Request2_Response $response) {
		if (HTTP_Request2::METHOD_CONNECT == $this->request->getMethod() && 200 <= $response->getStatus() && 300 > $response->getStatus()) return true;
		$lengthKnown = 'chunked' == strtolower($response->getHeader('transfer-encoding')) || null !== $response->getHeader('content-length');
		$persistent  = 'keep-alive' == strtolower($response->getHeader('connection')) || (null === $response->getHeader('connection') && '1.1' == $response->getVersion());
		return $requestKeepAlive && $lengthKnown && $persistent;
	}

	protected function disconnect() {
		if (is_resource($this->socket)) {
			fclose($this->socket);
			$this->socket = null;
			$this->request->setLastEvent('disconnect');
		}
	}

	protected function handleRedirect(HTTP_Request2 $request, HTTP_Request2_Response $response) {
		if (is_null($this->redirectCountdown)) $this->redirectCountdown = $request->getConfig('max_redirects');
		if (0 == $this->redirectCountdown) throw new HTTP_Request2_Exception('Maximum (' . $request->getConfig('max_redirects') . ') redirects followed');
		$redirectUrl = new Net_URL2(
			$response->getHeader('location'),
			array(Net_URL2::OPTION_USE_BRACKETS => $request->getConfig('use_brackets'))
		);
		if ($redirectUrl->isAbsolute() && !in_array($redirectUrl->getScheme(), array('http', 'https'))) {
			throw new HTTP_Request2_Exception('Refusing to redirect to a non-HTTP URL ' . $redirectUrl->__toString());
		}
		if (!$redirectUrl->isAbsolute()) $redirectUrl = $request->getUrl()->resolve($redirectUrl);
		$redirect = clone $request;
		$redirect->setUrl($redirectUrl);
		if (303 == $response->getStatus() || (!$request->getConfig('strict_redirects') && in_array($response->getStatus(), array(301, 302)))) {
			$redirect->setMethod(HTTP_Request2::METHOD_GET);
			$redirect->setBody('');
		}
		if (0 < $this->redirectCountdown) $this->redirectCountdown--;
		return $this->sendRequest($redirect);
	}

	protected function shouldUseServerDigestAuth(HTTP_Request2_Response $response) {
		if (401 != $response->getStatus() || !$this->request->getAuth()) return false;
		if (!$challenge = $this->parseDigestChallenge($response->getHeader('www-authenticate'))) return false;
		$url	= $this->request->getUrl();
		$scheme = $url->getScheme();
		$host	= $scheme . '://' . $url->getHost();
		if ($port = $url->getPort()) {
			if ((0 == strcasecmp($scheme, 'http') && 80 != $port) || (0 == strcasecmp($scheme, 'https') && 443 != $port)) $host .= ':' . $port;
		}
		if (!empty($challenge['domain'])) {
			$prefixes = array();
			foreach (preg_split('/\\s+/', $challenge['domain']) as $prefix) {
				if ('/' == substr($prefix, 0, 1)) $prefixes[] = $host . $prefix;
			}
		}
		if (empty($prefixes)) $prefixes = array($host . '/');
		$ret = true;
		foreach ($prefixes as $prefix) {
			if (!empty(self::$challenges[$prefix]) && (empty($challenge['stale']) || strcasecmp('true', $challenge['stale']))) $ret = false;
			self::$challenges[$prefix] =& $challenge;
		}
		return $ret;
	}

	protected function shouldUseProxyDigestAuth(HTTP_Request2_Response $response) {
		if (407 != $response->getStatus() || !$this->request->getConfig('proxy_user')) return false;
		if (!($challenge = $this->parseDigestChallenge($response->getHeader('proxy-authenticate')))) return false;
		$key = 'proxy://' . $this->request->getConfig('proxy_host') . ':' . $this->request->getConfig('proxy_port');
		if (!empty(self::$challenges[$key]) && (empty($challenge['stale']) || strcasecmp('true', $challenge['stale']))) $ret = false;
		else $ret = true;
		self::$challenges[$key] = $challenge;
		return $ret;
	}

	protected function parseDigestChallenge($headerValue) {
		$authParam	= '(' . self::REGEXP_TOKEN . ')\\s*=\\s*(' . self::REGEXP_TOKEN . '|' . self::REGEXP_QUOTED_STRING . ')';
		$challenge	= "!(?<=^|\\s|,)Digest ({$authParam}\\s*(,\\s*|$))+!";
		if (!preg_match($challenge, $headerValue, $matches)) return false;
		preg_match_all('!' . $authParam . '!', $matches[0], $params);
		$paramsAry	= array();
		$knownParams = array('realm', 'domain', 'nonce', 'opaque', 'stale', 'algorithm', 'qop');
		for ($i = 0; $i < count($params[0]); $i++) {
			if (in_array($params[1][$i], $knownParams)) {
				if ('"' == substr($params[2][$i], 0, 1)) $paramsAry[$params[1][$i]] = substr($params[2][$i], 1, -1);
				else $paramsAry[$params[1][$i]] = $params[2][$i];
			}
		}
		if (!empty($paramsAry['qop']) && !in_array('auth', array_map('trim', explode(',', $paramsAry['qop'])))) {
			throw new HTTP_Request2_Exception("Only 'auth' qop is currently supported in digest authentication, " . "server requested '{$paramsAry['qop']}'");
		}
		if (!empty($paramsAry['algorithm']) && 'MD5' != $paramsAry['algorithm']) {
			throw new HTTP_Request2_Exception("Only 'MD5' algorithm is currently supported in digest authentication, " . "server requested '{$paramsAry['algorithm']}'");
		}
		return $paramsAry;
	}

	protected function updateChallenge(&$challenge, $headerValue) {
		$authParam	= '!(' . self::REGEXP_TOKEN . ')\\s*=\\s*(' . self::REGEXP_TOKEN . '|' . self::REGEXP_QUOTED_STRING . ')!';
		$paramsAry	= array();
		preg_match_all($authParam, $headerValue, $params);
		for ($i = 0; $i < count($params[0]); $i++) {
			if ('"' == substr($params[2][$i], 0, 1)) $paramsAry[$params[1][$i]] = substr($params[2][$i], 1, -1);
			else $paramsAry[$params[1][$i]] = $params[2][$i];
		}
		if (!empty($paramsAry['nextnonce'])) {
			$challenge['nonce'] = $paramsAry['nextnonce'];
			$challenge['nc']	= 1;
		}
	}

	protected function createDigestResponse($user, $password, $url, &$challenge) {
		if (false !== ($q = strpos($url, '?')) && $this->request->getConfig('digest_compat_ie')) $url = substr($url, 0, $q);
		$a1 = md5($user . ':' . $challenge['realm'] . ':' . $password);
		$a2 = md5($this->request->getMethod() . ':' . $url);
		if (empty($challenge['qop'])) {
			$digest = md5($a1 . ':' . $challenge['nonce'] . ':' . $a2);
		} else {
			$challenge['cnonce'] = 'Req2.' . rand();
			if (empty($challenge['nc'])) $challenge['nc'] = 1;
			$nc	 = sprintf('%08x', $challenge['nc']++);
			$digest = md5($a1 . ':' . $challenge['nonce'] . ':' . $nc . ':' . $challenge['cnonce'] . ':auth:' . $a2);
		}
		return 'Digest username="' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $user) . '", ' .
			'realm="' . $challenge['realm'] . '", ' .
			'nonce="' . $challenge['nonce'] . '", ' .
			'uri="' . $url . '", ' .
			'response="' . $digest . '"' .
			(!empty($challenge['opaque'])?
			', opaque="' . $challenge['opaque'] . '"':
			'') .
			(!empty($challenge['qop'])?
			', qop="auth", nc=' . $nc . ', cnonce="' . $challenge['cnonce'] . '"':
			'');
	}

	protected function addAuthorizationHeader(&$headers, $requestHost, $requestUrl) {
		if (!($auth = $this->request->getAuth())) return;
		switch ($auth['scheme']) {
			case HTTP_Request2::AUTH_BASIC:
				$headers['authorization'] = 'Basic ' . base64_encode($auth['user'] . ':' . $auth['password']);
			break;
			case HTTP_Request2::AUTH_DIGEST:
				unset($this->serverChallenge);
				$fullUrl = ('/' == $requestUrl[0])?
					$this->request->getUrl()->getScheme() . '://' .
					$requestHost . $requestUrl:
					$requestUrl;
				foreach (array_keys(self::$challenges) as $key) {
					if ($key == substr($fullUrl, 0, strlen($key))) {
						$headers['authorization'] = $this->createDigestResponse($auth['user'], $auth['password'], $requestUrl, self::$challenges[$key]);
						$this->serverChallenge =& self::$challenges[$key];
						break;
					}
				}
			break;
			default:
				throw new HTTP_Request2_Exception("Unknown HTTP authentication scheme '{$auth['scheme']}'");
		}
	}

	protected function addProxyAuthorizationHeader(&$headers, $requestUrl) {
		if (!$this->request->getConfig('proxy_host') ||
			!($user = $this->request->getConfig('proxy_user')) ||
			(0 == strcasecmp('https', $this->request->getUrl()->getScheme()) &&
			 HTTP_Request2::METHOD_CONNECT != $this->request->getMethod())
		) {
			return;
		}
		$password = $this->request->getConfig('proxy_password');
		switch ($this->request->getConfig('proxy_auth_scheme')) {
			case HTTP_Request2::AUTH_BASIC:
				$headers['proxy-authorization'] = 'Basic ' . base64_encode($user . ':' . $password);
			break;
			case HTTP_Request2::AUTH_DIGEST:
				unset($this->proxyChallenge);
				$proxyUrl = 'proxy://' . $this->request->getConfig('proxy_host') . ':' . $this->request->getConfig('proxy_port');
				if (!empty(self::$challenges[$proxyUrl])) {
					$headers['proxy-authorization'] = $this->createDigestResponse($user, $password, $requestUrl, self::$challenges[$proxyUrl]);
					$this->proxyChallenge =& self::$challenges[$proxyUrl];
				}
			break;
			default:
				throw new HTTP_Request2_Exception("Unknown HTTP authentication scheme '" . $this->request->getConfig('proxy_auth_scheme') . "'");
		}
	}

	protected function prepareHeaders() {
		$headers = $this->request->getHeaders();
		$url	 = $this->request->getUrl();
		$connect = HTTP_Request2::METHOD_CONNECT == $this->request->getMethod();
		$host	= $url->getHost();
		$defaultPort = 0 == strcasecmp($url->getScheme(), 'https')? 443: 80;
		if (($port = $url->getPort()) && $port != $defaultPort || $connect) $host .= ':' . (empty($port)? $defaultPort: $port);
		if (!isset($headers['host'])) $headers['host'] = $host;
		if ($connect) {
			$requestUrl = $host;
		} else {
			if (!$this->request->getConfig('proxy_host') || 0 == strcasecmp($url->getScheme(), 'https')) {
				$requestUrl = '';
			} else {
				$requestUrl = $url->getScheme() . '://' . $host;
			}
			$path		= $url->getPath();
			$query		= $url->getQuery();
			$requestUrl .= (empty($path)? '/': $path) . (empty($query)? '': '?' . $query);
		}
		if ('1.1' == $this->request->getConfig('protocol_version') && extension_loaded('zlib') && !isset($headers['accept-encoding'])) {
			$headers['accept-encoding'] = 'gzip, deflate';
		}
		$this->addAuthorizationHeader($headers, $host, $requestUrl);
		$this->addProxyAuthorizationHeader($headers, $requestUrl);
		$this->calculateRequestLength($headers);
		$headersStr = $this->request->getMethod() . ' ' . $requestUrl . ' HTTP/' . $this->request->getConfig('protocol_version') . "\r\n";
		foreach ($headers as $name => $value) {
			$canonicalName = implode('-', array_map('ucfirst', explode('-', $name)));
			$headersStr	.= $canonicalName . ': ' . $value . "\r\n";
		}
		return $headersStr . "\r\n";
	}

	protected function writeBody() {
		if (in_array($this->request->getMethod(), self::$bodyDisallowed) || 0 == $this->contentLength) return;
		$position	= 0;
		$bufferSize = $this->request->getConfig('buffer_size');
		while ($position < $this->contentLength) {
			if (is_string($this->requestBody)) $str = substr($this->requestBody, $position, $bufferSize);
			elseif (is_resource($this->requestBody)) $str = fread($this->requestBody, $bufferSize);
			else $str = $this->requestBody->read($bufferSize);
			if (false === @fwrite($this->socket, $str, strlen($str))) throw new HTTP_Request2_Exception('Error writing request');
			$this->request->setLastEvent('sentBodyPart', strlen($str));
			$position += strlen($str);
		}
	}

	protected function readResponse() {
		$bufferSize = $this->request->getConfig('buffer_size');
		do {
			$response = new HTTP_Request2_Response($this->readLine($bufferSize), true);
			do {
				$headerLine = $this->readLine($bufferSize);
				$response->parseHeaderLine($headerLine);
			} while ('' != $headerLine);
		} while (in_array($response->getStatus(), array(100, 101)));
		$this->request->setLastEvent('receivedHeaders', $response);
		if (HTTP_Request2::METHOD_HEAD == $this->request->getMethod() ||
			(HTTP_Request2::METHOD_CONNECT == $this->request->getMethod() &&
			 200 <= $response->getStatus() && 300 > $response->getStatus()) ||
			in_array($response->getStatus(), array(204, 304))
		) {
			return $response;
		}
		$chunked = 'chunked' == $response->getHeader('transfer-encoding');
		$length  = $response->getHeader('content-length');
		$hasBody = false;
		if ($chunked || null === $length || 0 < intval($length)) {
			$toRead = ($chunked || null === $length)? null: $length;
			$this->chunkLength = 0;
			while (!feof($this->socket) && (is_null($toRead) || 0 < $toRead)) {
				if ($chunked) {
					$data = $this->readChunked($bufferSize);
				} elseif (is_null($toRead)) {
					$data = $this->fread($bufferSize);
				} else {
					$data	= $this->fread(min($toRead, $bufferSize));
					$toRead -= strlen($data);
				}
				if ('' == $data && (!$this->chunkLength || feof($this->socket))) break;
				$hasBody = true;
				if ($this->request->getConfig('store_body')) $response->appendBody($data);
				if (!in_array($response->getHeader('content-encoding'), array('identity', null))) {
					$this->request->setLastEvent('receivedEncodedBodyPart', $data);
				} else {
					$this->request->setLastEvent('receivedBodyPart', $data);
				}
			}
		}
		if ($hasBody) $this->request->setLastEvent('receivedBody', $response);
		return $response;
	}

	protected function readLine($bufferSize) {
		$line = '';
		while (!feof($this->socket)) {
			if ($this->deadline) stream_set_timeout($this->socket, max($this->deadline - time(), 1));
			$line .= @fgets($this->socket, $bufferSize);
			$info  = stream_get_meta_data($this->socket);
			if ($info['timed_out'] || $this->deadline && time() > $this->deadline) {
				$reason = $this->deadline ? 'after ' . $this->request->getConfig('timeout') . ' second(s)' : 'due to default_socket_timeout php.ini setting';
				throw new HTTP_Request2_Exception("Request timed out {$reason}");
			}
			if (substr($line, -1) == "\n") return rtrim($line, "\r\n");
		}
		return $line;
	}

	protected function fread($length) {
		if ($this->deadline) stream_set_timeout($this->socket, max($this->deadline - time(), 1));
		$data = fread($this->socket, $length);
		$info = stream_get_meta_data($this->socket);
		if ($info['timed_out'] || $this->deadline && time() > $this->deadline) {
			$reason = $this->deadline ? 'after ' . $this->request->getConfig('timeout') . ' second(s)' : 'due to default_socket_timeout php.ini setting';
			throw new HTTP_Request2_Exception("Request timed out {$reason}");
		}
		return $data;
	}

	protected function readChunked($bufferSize) {
		if (0 == $this->chunkLength) {
			$line = $this->readLine($bufferSize);
			if (!preg_match('/^([0-9a-f]+)/i', $line, $matches)) {
				throw new HTTP_Request2_Exception("Cannot decode chunked response, invalid chunk length '{$line}'");
			} else {
				$this->chunkLength = hexdec($matches[1]);
				if (0 == $this->chunkLength) {
					$this->readLine($bufferSize);
					return '';
				}
			}
		}
		$data = $this->fread(min($this->chunkLength, $bufferSize));
		$this->chunkLength -= strlen($data);
		if (0 == $this->chunkLength) $this->readLine($bufferSize);
		return $data;
	}
}
