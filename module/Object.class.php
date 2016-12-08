<?php
class Object {
	var $error = 0;
	var $message = 'success';
	var $variables = array();
	var $httpStatusCode = NULL;

	function Object($error = 0, $message = 'success') {
		$this->setError($error);
		$this->setMessage($message);
	}

	function setError($error = 0) {
		$this->error = $error;
	}

	function getError() {
		return $this->error;
	}

	function setHttpStatusCode($code = '200') {
		$this->httpStatusCode = $code;
	}

	function getHttpStatusCode() {
		return $this->httpStatusCode;
	}

	function setMessage($message = 'success', $type = NULL) {
		if($str = Context::getLang($message)) $this->message = $str;
		else $this->message = $message;
		return TRUE;
	}

	function getMessage() {
		return $this->message;
	}

	function add($key, $val) {
		$this->variables[$key] = $val;
	}

	function adds($object) {
		if(is_object($object)) $object = get_object_vars($object);
		if(is_array($object)) {
			foreach($object as $key => $val) $this->variables[$key] = $val;
		}
	}

	function get($key) {
		return $this->variables[$key];
	}

	function gets() {
		$args = func_get_args();
		$output = new stdClass();
		foreach($args as $arg) $output->{$arg} = $this->get($arg);
		return $output;
	}

	function getVariables() {
		return $this->variables;
	}

	function getObjectVars() {
		$output = new stdClass();
		foreach($this->variables as $key => $val) $output->{$key} = $val;
		return $output;
	}

	function toBool() {
		return ($this->error == 0);
	}

	function toBoolean() {
		return $this->toBool();
	}
}
