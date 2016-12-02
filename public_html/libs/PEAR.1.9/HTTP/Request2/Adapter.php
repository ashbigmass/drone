<?php
require_once 'HTTP/Request2/Response.php';
abstract class HTTP_Request2_Adapter
{
	protected static $bodyDisallowed = array('TRACE');
	protected static $bodyRequired = array('POST', 'PUT');
	protected $request;
	protected $requestBody;
	protected $contentLength;
	abstract public function sendRequest(HTTP_Request2 $request);
	protected function calculateRequestLength(&$headers) {
		$this->requestBody = $this->request->getBody();
		if (is_string($this->requestBody)) {
			$this->contentLength = strlen($this->requestBody);
		} elseif (is_resource($this->requestBody)) {
			$stat = fstat($this->requestBody);
			$this->contentLength = $stat['size'];
			rewind($this->requestBody);
		} else {
			$this->contentLength = $this->requestBody->getLength();
			$headers['content-type'] = 'multipart/form-data; boundary=' . $this->requestBody->getBoundary();
			$this->requestBody->rewind();
		}
		if (in_array($this->request->getMethod(), self::$bodyDisallowed) || 0 == $this->contentLength) {
			if (in_array($this->request->getMethod(), self::$bodyRequired)) {
				$headers['content-length'] = 0;
			} else {
				unset($headers['content-length']);
				unset($headers['content-type']);
			}
		} else {
			if (empty($headers['content-type'])) $headers['content-type'] = 'application/x-www-form-urlencoded';
			$headers['content-length'] = $this->contentLength;
		}
	}
}
?>
