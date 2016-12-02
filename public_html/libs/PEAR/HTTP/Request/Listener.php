<?php
class HTTP_Request_Listener
{
	var $_id;

	function HTTP_Request_Listener() {
		$this->_id = md5(uniqid('http_request_', 1));
	}

	function getId() {
		return $this->_id;
	}

	function update(&$subject, $event, $data = null) {
		echo "Notified of event: '$event'\n";
		if (null !== $data) {
			echo "Additional data: ";
			var_dump($data);
		}
	}
}
