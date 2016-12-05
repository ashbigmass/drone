<?php
require_once 'HTTP/Request2/Exception.php';

class HTTP_Request2_SocketWrapper
{
	protected $connectionWarnings = array();
	protected $socket;
	protected $deadline;
	protected $timeout;

	public function __construct($address, $timeout, array $contextOptions = array()) {
		if (!empty($contextOptions) && !isset($contextOptions['socket']) && !isset($contextOptions['ssl'])) {
			$contextOptions = array('ssl' => $contextOptions);
		}
		$context = stream_context_create();
		foreach ($contextOptions as $wrapper => $options) {
			foreach ($options as $name => $value) {
				if (!stream_context_set_option($context, $wrapper, $name, $value)) {
					throw new HTTP_Request2_LogicException("Error setting '{$wrapper}' wrapper context option '{$name}'");
				}
			}
		}
		set_error_handler(array($this, 'connectionWarningsHandler'));
		$this->socket = stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
		restore_error_handler();
		if ($this->connectionWarnings) {
			if ($this->socket) fclose($this->socket);
			$error = $errstr ? $errstr : implode("\n", $this->connectionWarnings);
			throw new HTTP_Request2_ConnectionException("Unable to connect to {$address}. Error: {$error}", 0, $errno);
		}
	}

	public function __destruct() {
		fclose($this->socket);
	}

	public function read($length) {
		if ($this->deadline) stream_set_timeout($this->socket, max($this->deadline - time(), 1));
		$data = fread($this->socket, $length);
		$this->checkTimeout();
		return $data;
	}

	public function readLine($bufferSize, $localTimeout = null) {
		$line = '';
		while (!feof($this->socket)) {
			if (null !== $localTimeout) stream_set_timeout($this->socket, $localTimeout);
			elseif ($this->deadline) stream_set_timeout($this->socket, max($this->deadline - time(), 1));
			$line .= @fgets($this->socket, $bufferSize);
			if (null === $localTimeout) {
				$this->checkTimeout();
			} else {
				$info = stream_get_meta_data($this->socket);
				if (!$this->deadline) {
					$default = (int)@ini_get('default_socket_timeout');
					stream_set_timeout($this->socket, $default > 0 ? $default : PHP_INT_MAX);
				}
				if ($info['timed_out']) {
					throw new HTTP_Request2_MessageException("readLine() call timed out", HTTP_Request2_Exception::TIMEOUT);
				}
			}
			if (substr($line, -1) == "\n") return rtrim($line, "\r\n");
		}
		return $line;
	}

	public function write($data) {
		if ($this->deadline) stream_set_timeout($this->socket, max($this->deadline - time(), 1));
		$written = fwrite($this->socket, $data);
		$this->checkTimeout();
		if ($written < strlen($data)) throw new HTTP_Request2_MessageException('Error writing request');
		return $written;
	}

	public function eof() {
		return feof($this->socket);
	}

	public function setDeadline($deadline, $timeout) {
		$this->deadline = $deadline;
		$this->timeout  = $timeout;
	}

	public function enableCrypto() {
		$modes = array(
			STREAM_CRYPTO_METHOD_TLS_CLIENT,
			STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
			STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
			STREAM_CRYPTO_METHOD_SSLv2_CLIENT
		);
		foreach ($modes as $mode) if (stream_socket_enable_crypto($this->socket, true, $mode)) return;
		throw new HTTP_Request2_ConnectionException('Failed to enable secure connection when connecting through proxy');
	}

	protected function checkTimeout() {
		$info = stream_get_meta_data($this->socket);
		if ($info['timed_out'] || $this->deadline && time() > $this->deadline) {
			$reason = $this->deadline ? "after {$this->timeout} second(s)" : 'due to default_socket_timeout php.ini setting';
			throw new HTTP_Request2_MessageException("Request timed out {$reason}", HTTP_Request2_Exception::TIMEOUT);
		}
	}

	protected function connectionWarningsHandler($errno, $errstr) {
		if ($errno & E_WARNING) array_unshift($this->connectionWarnings, $errstr);
		return true;
	}
}
