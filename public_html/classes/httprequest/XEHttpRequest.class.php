<?php
class XEHttpRequest
{
	var $m_host;
	var $m_port;
	var $m_scheme;
	var $m_headers;

	function XEHttpRequest($host, $port, $scheme='') {
		$this->m_host = $host;
		$this->m_port = $port;
		$this->m_scheme = $scheme;
		$this->m_headers = array();
	}

	function addToHeader($key, $value) {
		$this->m_headers[$key] = $value;
	}

	function send($target = '/', $method = 'GET', $timeout = 3, $post_vars = NULL) {
		static $allow_methods = NULL;
		$this->addToHeader('Host', $this->m_host);
		$this->addToHeader('Connection', 'close');
		$method = strtoupper($method);
		if(!$allow_methods) $allow_methods = explode(' ', 'GET POST PUT');
		if(!in_array($method, $allow_methods)) $method = $allow_methods[0];
		$timout = max((int) $timeout, 0);
		if(!is_array($post_vars)) $post_vars = array();
		if(FALSE && is_callable('curl_init')) return $this->sendWithCurl($target, $method, $timeout, $post_vars);
		else return $this->sendWithSock($target, $method, $timeout, $post_vars);
	}

	function sendWithSock($target, $method, $timeout, $post_vars) {
		static $crlf = "\r\n";
		$scheme = '';
		if($this->m_scheme=='https') $scheme = 'ssl://';
		$sock = @fsockopen($scheme . $this->m_host, $this->m_port, $errno, $errstr, $timeout);
		if(!$sock) return new Object(-1, 'socket_connect_failed');
		$headers = $this->m_headers + array();
		if(!isset($headers['Accept-Encoding'])) $headers['Accept-Encoding'] = 'identity';
		$post_body = '';
		if($method == 'POST' && count($post_vars)) {
			foreach($post_vars as $key => $value) $post_body .= urlencode($key) . '=' . urlencode($value) . '&';
			$post_body = substr($post_body, 0, -1);
			$headers['Content-Length'] = strlen($post_body);
			$headers['Content-Type'] = 'application/x-www-form-urlencoded';
		}
		$request = "$method $target HTTP/1.1$crlf";
		foreach($headers as $equiv => $content) $request .= "$equiv: $content$crlf";
		$request .= $crlf . $post_body;
		fwrite($sock, $request);
		list($httpver, $code, $status) = preg_split('/ +/', rtrim(fgets($sock)), 3);
		$is_chunked = FALSE;
		while(strlen(trim($line = fgets($sock)))) {
			list($equiv, $content) = preg_split('/ *: */', rtrim($line), 2);
			if(!strcasecmp($equiv, 'Transfer-Encoding') && $content == 'chunked') $is_chunked = TRUE;
		}
		$body = '';
		while(!feof($sock)) {
			if($is_chunked) {
				$chunk_size = hexdec(fgets($sock));
				if($chunk_size) $body .= fgets($sock, $chunk_size+1);
			} else {
				$body .= fgets($sock, 512);
			}
		}
		fclose($sock);
		$ret = new stdClass;
		$ret->result_code = $code;
		$ret->body = $body;
		return $ret;
	}

	function sendWithCurl($target, $method, $timeout, $post_vars) {
		$headers = $this->m_headers + array();
		$ch = curl_init();
		$headers['Expect'] = '';
		curl_setopt($ch, CURLOPT_URL, "http://{$this->m_host}{$target}");
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_PORT, $this->m_port);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		switch($method) {
			case 'GET': curl_setopt($ch, CURLOPT_HTTPGET, true); break;
			case 'PUT': curl_setopt($ch, CURLOPT_PUT, true); break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
			break;
		}
		$arr_headers = array();
		foreach($headers as $key => $value) $arr_headers[] = "$key: $value";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $arr_headers);
		$body = curl_exec($ch);
		if(curl_errno($ch)) return new Object(-1, 'socket_connect_failed');
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$ret = new stdClass;
		$ret->result_code = $code;
		$ret->body = $body;
		return $ret;
	}
}
