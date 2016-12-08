<?php
class Security
{
	var $_targetVar = NULL;

	function Security($var = NULL) {
		$this->_targetVar = $var;
	}

	function encodeHTML() {
		$varNames = func_get_args();
		if(count($varNames) < 0) return FALSE;
		$use_context = is_null($this->_targetVar);
		if(!$use_context) {
			if(!count($varNames) || (!is_object($this->_targetVar) && !is_array($this->_targetVar))) {
				return $this->_encodeHTML($this->_targetVar);
			}
			$is_object = is_object($this->_targetVar);
		}
		foreach($varNames as $varName) {
			$varName = explode('.', $varName);
			$varName0 = array_shift($varName);
			if($use_context) $var = Context::get($varName0);
			elseif($varName0) $var = $is_object ? $this->_targetVar->{$varName0} : $this->_targetVar[$varName0];
			else $var = $this->_targetVar;
			$var = $this->_encodeHTML($var, $varName);
			if($var === FALSE) continue;
			if($use_context) {
				Context::set($varName0, $var);
			} elseif($varName0) {
				if($is_object) $this->_targetVar->{$varName0} = $var;
				else $this->_targetVar[$varName0] = $var;
			} else {
				$this->_targetVar = $var;
			}
		}
		if(!$use_context) return $this->_targetVar;
	}

	function _encodeHTML($var, $name = array()) {
		if(is_string($var)) {
			if(strncmp('$user_lang->', $var, 12) !== 0) $var = htmlspecialchars($var, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			return $var;
		}
		if(!count($name) || (!is_array($var) && !is_object($var))) return false;
		$is_object = is_object($var);
		$name0 = array_shift($name);
		if(strlen($name0)) {
			$target = $is_object ? $var->{$name0} : $var[$name0];
			$target = $this->_encodeHTML($target, $name);
			if($target === false) return $var;
			if($is_object) $var->{$name0} = $target;
			else $var[$name0] = $target;
			return $var;
		}
		foreach($var as $key => $target) {
			$cloned_name = array_slice($name, 0);
			$target = $this->_encodeHTML($target, $name);
			$name = $cloned_name;
			if($target === false) continue;
			if($is_object) $var->{$key} = $target;
			else $var[$key] = $target;
		}
		return $var;
	}

	static function detectingXEE($xml) {
		if(!$xml) return FALSE;
		if(strpos($xml, '<!ENTITY') !== FALSE) return TRUE;
		$header = preg_replace('/<\?xml.*?\?'.'>/s', '', substr($xml, 0, 100), 1);
		$xml = trim(substr_replace($xml, $header, 0, 100));
		if($xml == '') return TRUE;
		$header = preg_replace('/^<!DOCTYPE[^>]*+>/i', '', substr($xml, 0, 200), 1);
		$xml = trim(substr_replace($xml, $header, 0, 200));
		if($xml == '') return TRUE;
		$root_tag = substr($xml, 0, strcspn(substr($xml, 0, 20), "> \t\r\n"));
		if(strtoupper($root_tag) == '<!DOCTYPE') return TRUE;
		if(!in_array($root_tag, array('<methodCall', '<methodResponse', '<fault'))) return TRUE;
		return FALSE;
	}
}
