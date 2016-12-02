<?php
require_once 'HTTP/Request2/Adapter.php';

class HTTP_Request2_Adapter_Curl extends HTTP_Request2_Adapter
{
	protected static $headerMap = array(
		'accept-encoding' => CURLOPT_ENCODING,
		'cookie'		  => CURLOPT_COOKIE,
		'referer'		 => CURLOPT_REFERER,
		'user-agent'	  => CURLOPT_USERAGENT
	);
	protected static $sslContextMap = array(
		'ssl_verify_peer' => CURLOPT_SSL_VERIFYPEER,
		'ssl_cafile'	  => CURLOPT_CAINFO,
		'ssl_capath'	  => CURLOPT_CAPATH,
		'ssl_local_cert'  => CURLOPT_SSLCERT,
		'ssl_passphrase'  => CURLOPT_SSLCERTPASSWD
	);
	protected $response;
	protected $eventSentHeaders = false;
	protected $eventReceivedHeaders = false;
	protected $position = 0;
	protected $lastInfo;

	public function sendRequest(HTTP_Request2 $request) {
		if (!extension_loaded('curl')) throw new HTTP_Request2_Exception('cURL extension not available');
		$this->request			  = $request;
		$this->response			 = null;
		$this->position			 = 0;
		$this->eventSentHeaders	 = false;
		$this->eventReceivedHeaders = false;
		try {
			if (false === curl_exec($ch = $this->createCurlHandle())) {
				$errorMessage = 'Error sending request: #' . curl_errno($ch) . ' ' . curl_error($ch);
			}
		} catch (Exception $e) {
		}
		$this->lastInfo = curl_getinfo($ch);
		curl_close($ch);
		$response = $this->response;
		unset($this->request, $this->requestBody, $this->response);
		if (!empty($e)) throw $e;
		elseif (!empty($errorMessage)) throw new HTTP_Request2_Exception($errorMessage);
		if (0 < $this->lastInfo['size_download']) $request->setLastEvent('receivedBody', $response);
		return $response;
	}

	public function getInfo() {
		return $this->lastInfo;
	}

	protected function createCurlHandle() {
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_HEADERFUNCTION => array($this, 'callbackWriteHeader'),
			CURLOPT_WRITEFUNCTION  => array($this, 'callbackWriteBody'),
			CURLOPT_BUFFERSIZE	 => $this->request->getConfig('buffer_size'),
			CURLOPT_CONNECTTIMEOUT => $this->request->getConfig('connect_timeout'),
			CURLINFO_HEADER_OUT	=> true,
			CURLOPT_URL			=> $this->request->getUrl()->getUrl()
		));
		if (!$this->request->getConfig('follow_redirects')) {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		} else {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, $this->request->getConfig('max_redirects'));
			if (defined('CURLOPT_REDIR_PROTOCOLS')) {
				curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}
			if ($this->request->getConfig('strict_redirects') && defined('CURLOPT_POSTREDIR ')) {
				curl_setopt($ch, CURLOPT_POSTREDIR, 3);
			}
		}
		if ($timeout = $this->request->getConfig('timeout')) curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		switch ($this->request->getConfig('protocol_version')) {
			case '1.0': curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); break;
			case '1.1': curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		}
		switch ($this->request->getMethod()) {
			case HTTP_Request2::METHOD_GET: curl_setopt($ch, CURLOPT_HTTPGET, true); break;
			case HTTP_Request2::METHOD_POST: curl_setopt($ch, CURLOPT_POST, true); break;
			case HTTP_Request2::METHOD_HEAD: curl_setopt($ch, CURLOPT_NOBODY, true); break;
			default: curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->request->getMethod());
		}
		if ($host = $this->request->getConfig('proxy_host')) {
			if (!($port = $this->request->getConfig('proxy_port'))) throw new HTTP_Request2_Exception('Proxy port not provided');
			curl_setopt($ch, CURLOPT_PROXY, $host . ':' . $port);
			if ($user = $this->request->getConfig('proxy_user')) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user . ':' .
					$this->request->getConfig('proxy_password'));
				switch ($this->request->getConfig('proxy_auth_scheme')) {
					case HTTP_Request2::AUTH_BASIC:
						curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
					break;
					case HTTP_Request2::AUTH_DIGEST:
						curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_DIGEST);
				}
			}
		}
		if ($auth = $this->request->getAuth()) {
			curl_setopt($ch, CURLOPT_USERPWD, $auth['user'] . ':' . $auth['password']);
			switch ($auth['scheme']) {
				case HTTP_Request2::AUTH_BASIC:
					curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				break;
				case HTTP_Request2::AUTH_DIGEST:
					curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
			}
		}
		if (0 == strcasecmp($this->request->getUrl()->getScheme(), 'https')) {
			foreach ($this->request->getConfig() as $name => $value) {
				if ('ssl_verify_host' == $name && null !== $value) curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $value? 2: 0);
				elseif (isset(self::$sslContextMap[$name]) && null !== $value) curl_setopt($ch, self::$sslContextMap[$name], $value);
			}
		}
		$headers = $this->request->getHeaders();
		if (!isset($headers['accept-encoding'])) $headers['accept-encoding'] = '';
		foreach (self::$headerMap as $name => $option) {
			if (isset($headers[$name])) {
				curl_setopt($ch, $option, $headers[$name]);
				unset($headers[$name]);
			}
		}
		$this->calculateRequestLength($headers);
		if (isset($headers['content-length'])) $this->workaroundPhpBug47204($ch, $headers);
		$headersFmt = array();
		foreach ($headers as $name => $value) {
			$canonicalName = implode('-', array_map('ucfirst', explode('-', $name)));
			$headersFmt[]  = $canonicalName . ': ' . $value;
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headersFmt);
		return $ch;
	}

	protected function workaroundPhpBug47204($ch, &$headers) {
		if (!$this->request->getConfig('follow_redirects') && (!($auth = $this->request->getAuth()) || HTTP_Request2::AUTH_DIGEST != $auth['scheme'])) {
			curl_setopt($ch, CURLOPT_READFUNCTION, array($this, 'callbackReadBody'));
		} else {
			if ($this->requestBody instanceof HTTP_Request2_MultipartBody) {
				$this->requestBody = $this->requestBody->__toString();
			} elseif (is_resource($this->requestBody)) {
				$fp = $this->requestBody;
				$this->requestBody = '';
				while (!feof($fp)) $this->requestBody .= fread($fp, 16384);
			}
			unset($headers['content-length']);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
		}
	}

	protected function callbackReadBody($ch, $fd, $length) {
		if (!$this->eventSentHeaders) {
			$this->request->setLastEvent('sentHeaders', curl_getinfo($ch, CURLINFO_HEADER_OUT));
			$this->eventSentHeaders = true;
		}
		if (in_array($this->request->getMethod(), self::$bodyDisallowed) || 0 == $this->contentLength || $this->position >= $this->contentLength) return '';
		if (is_string($this->requestBody)) $string = substr($this->requestBody, $this->position, $length);
		elseif (is_resource($this->requestBody)) $string = fread($this->requestBody, $length);
		else $string = $this->requestBody->read($length);
		$this->request->setLastEvent('sentBodyPart', strlen($string));
		$this->position += strlen($string);
		return $string;
	}

	protected function callbackWriteHeader($ch, $string) {
		if ($this->eventReceivedHeaders || !$this->eventSentHeaders) {
			if (!$this->eventSentHeaders || $this->response->getStatus() >= 200) {
				$this->request->setLastEvent('sentHeaders', curl_getinfo($ch, CURLINFO_HEADER_OUT));
			}
			$upload = curl_getinfo($ch, CURLINFO_SIZE_UPLOAD);
			if ($upload > $this->position) {
				$this->request->setLastEvent('sentBodyPart', $upload - $this->position);
				$this->position = $upload;
			}
			$this->eventSentHeaders = true;
			if ($this->eventReceivedHeaders) {
				$this->eventReceivedHeaders = false;
				$this->response			 = null;
			}
		}
		if (empty($this->response)) {
			$this->response = new HTTP_Request2_Response($string, false);
		} else {
			$this->response->parseHeaderLine($string);
			if ('' == trim($string)) {
				if (200 <= $this->response->getStatus()) $this->request->setLastEvent('receivedHeaders', $this->response);
				if ($this->request->getConfig('follow_redirects') && !defined('CURLOPT_REDIR_PROTOCOLS') && $this->response->isRedirect()) {
					$redirectUrl = new Net_URL2($this->response->getHeader('location'));
					if ($redirectUrl->isAbsolute() && !in_array($redirectUrl->getScheme(), array('http', 'https'))) return -1;
				}
				$this->eventReceivedHeaders = true;
			}
		}
		return strlen($string);
	}

	protected function callbackWriteBody($ch, $string) {
		if (empty($this->response)) throw new HTTP_Request2_Exception("Malformed response: {$string}");
		if ($this->request->getConfig('store_body')) $this->response->appendBody($string);
		$this->request->setLastEvent('receivedBodyPart', $string);
		return strlen($string);
	}
}
