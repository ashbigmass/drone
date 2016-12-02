<?php
require_once 'HTTP/Request2/Adapter.php';

class HTTP_Request2_Adapter_Mock extends HTTP_Request2_Adapter
{
	protected $responses = array();

	public function sendRequest(HTTP_Request2 $request) {
		if (count($this->responses) > 0) {
			$response = array_shift($this->responses);
			if ($response instanceof HTTP_Request2_Response) {
				return $response;
			} else {
				$class   = get_class($response);
				$message = $response->getMessage();
				$code	= $response->getCode();
				throw new $class($message, $code);
			}
		} else {
			return self::createResponseFromString("HTTP/1.1 400 Bad Request\r\n\r\n");
		}
	}

	public function addResponse($response) {
		if (is_string($response)) {
			$response = self::createResponseFromString($response);
		} elseif (is_resource($response)) {
			$response = self::createResponseFromFile($response);
		} elseif (!$response instanceof HTTP_Request2_Response &&
				  !$response instanceof Exception
		) {
			throw new HTTP_Request2_Exception('Parameter is not a valid response');
		}
		$this->responses[] = $response;
	}

	public static function createResponseFromString($str) {
		$parts	   = preg_split('!(\r?\n){2}!m', $str, 2);
		$headerLines = explode("\n", $parts[0]);
		$response	= new HTTP_Request2_Response(array_shift($headerLines));
		foreach ($headerLines as $headerLine) $response->parseHeaderLine($headerLine);
		$response->parseHeaderLine('');
		if (isset($parts[1])) $response->appendBody($parts[1]);
		return $response;
	}

	public static function createResponseFromFile($fp) {
		$response = new HTTP_Request2_Response(fgets($fp));
		do {
			$headerLine = fgets($fp);
			$response->parseHeaderLine($headerLine);
		} while ('' != trim($headerLine));
		while (!feof($fp)) $response->appendBody(fread($fp, 8192));
		return $response;
	}
}
