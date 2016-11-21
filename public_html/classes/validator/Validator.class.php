<?php
class Validator {
	var $_cache_dir = '';
	var $_last_error;
	var $_xml_ruleset = NULL;
	var $_rules;
	var $_filters;
	var $_messages;
	var $_fieldNames;
	var $_has_mb_func;
	var $_version = '1.0';
	var $_xml_path = '';

	function __construct($xml_path = '') {
		$this->_rules = array();
		$this->_filters = array();
		$this->_xml_ruleset = NULL;
		if($xml_path) $this->load($xml_path);
		$this->addRule(array(
			'email' => '/^[\w-]+((?:\.|\+|\~)[\w-]+)*@[\w-]+(\.[\w-]+)+$/',
			'userid' => '/^[a-z]+[\w-]*[a-z0-9_]+$/i',
			'url' => '/^(https?|ftp|mms):\/\/[0-9a-z-]+(\.[_0-9a-z-]+)+(:\d+)?/',
			'alpha' => '/^[a-z]*$/i',
			'alpha_number' => '/^[a-z][a-z0-9_]*$/i',
			'number' => '/^(?:[1-9]\\d*|0)$/',
			'float' => '/^\d+(\.\d+)?$/'
		));
		$this->_has_mb_func = is_callable('mb_strlen');
		$this->setCacheDir(_XE_PATH_ . 'files/cache');
	}

	function __destruct() {
		$this->_rules = NULL;
		$this->_filters = NULL;
	}

	function load($xml_path) {
		$this->_xml_ruleset = NULL;
		$xml_path = realpath($xml_path);
		if(!is_readable($xml_path)) return FALSE;
		$parser = new XmlParser();
		$xml = $parser->loadXmlFile($xml_path);
		if(!isset($xml->ruleset) || !isset($xml->ruleset->fields) || !isset($xml->ruleset->fields->field)) return FALSE;
		if(isset($xml->ruleset->customrules) && isset($xml->ruleset->customrules->rule)) {
			$customrules = $xml->ruleset->customrules->rule;
			if(!is_array($customrules)) $customrules = array($customrules);
			$rules = array();
			$messages = array();
			foreach($customrules as $rule) {
				if(!isset($rule->attrs) || !isset($rule->attrs->name)) continue;
				$message = $rule->message ? $rule->message->body : NULL;
				$rule = (array) $rule->attrs;
				$rule['message'] = $message;
				$name = $rule['name'];
				unset($rule['name']);
				$rules[$name] = $rule;
				if(isset($message)) $messages['invalid_' . $name] = $message;
			}
			if(count($rules)) $this->addRule($rules);
		}
		$fields = $xml->ruleset->fields->field;
		if(!is_array($fields)) $fields = array($fields);
		$filters = array();
		$fieldsNames = array();
		foreach($fields as $field) {
			$name = '';
			$filter = array();
			if(!isset($field->attrs) || !isset($field->attrs->name)) continue;
			$title = $field->title ? $field->title->body : NULL;
			$filter = (array) $field->attrs;
			$filter['title'] = $title;
			$name = $filter['name'];
			if(isset($title)) $fieldsNames[$name] = $title;
			unset($filter['name']);
			if(isset($field->if)) {
				$if = $field->if;
				if(!is_array($if)) $if = array($if);
				foreach($if as $idx => $cond) $if[$idx] = (array) $cond->attrs;
				$filter['if'] = $if;
			}
			$filters[$name] = $filter;
		}
		$this->_xml_ruleset = $xml->ruleset;
		$this->_filters = $filters;
		$this->_message = $messages;
		$this->_fieldNames = $fieldsNames;
		$this->_xml_path = $xml_path;
		return TRUE;
	}

	function setCacheDir($cache_dir) {
		if(is_dir($cache_dir)) $this->_cache_dir = preg_replace('@/$@', '', $cache_dir);
	}

	function validate($fields_ = null) {
		if(is_array($fields_)) {
			$fields = $fields_;
		} else {
			$args = array_keys($this->_filters);
			$fields = (array) Context::getRequestVars();
		}
		if(!is_array($fields)) return TRUE;
		$filter_default = array(
			'required' => 'false',
			'default' => '',
			'modifiers' => array(),
			'length' => 0,
			'equalto' => 0,
			'rule' => 0,
			'if' => array()
		);
		$fields = array_map(array($this, 'arrayTrim'), $fields);
		$field_names = array_keys($fields);
		$filters = array();
		foreach($this->_filters as $key => $filter) {
			$names = array();
			if($key{0} == '^') {
				$names = preg_grep('/^' . preg_quote(substr($key, 1)) . '/', $field_names);
			} elseif(substr($key, -2) == '[]') {
				$filters[substr($key, 0, -2)] = $filter;
				unset($filters[$key]);
			} else {
				$filters[$key] = $filter;
			}
			if(!count($names)) continue;
			foreach($names as $name) $filters[$name] = $filter;
			unset($filters[$key]);
		}
		foreach($filters as $key => $filter) {
			$fname = preg_replace('/\[\]$/', '', $key);
			$filter = array_merge($filter_default, $filter);
			if(preg_match("/(^[a-z_]*)[\[](?:\'|\")?([a-z_]*)(?:\'|\")?[\]]$/i", $key, $matches)) {
				$exists = array_key_exists($matches[1], $fields);
				$value = $exists ? $fields[$matches[1]][$matches[2]] : NULL;
			} else {
				$exists = array_key_exists($key, $fields);
				$value = $exists ? $fields[$fname] : NULL;
			}
			if(is_array($value)) {
				if(!isset($value[tmp_name])) $value = implode('', $value);
				else $value = $value['name'];
			}
			foreach($filter['if'] as $cond) {
				if(!isset($cond['test']) || !isset($cond['attr'])) continue;
				$func_body = preg_replace('/\\$(\w+)/', '$c[\'$1\']', $cond['test']);
				$func = create_function('$c', "return !!({$func_body});");
				if($func($fields)) $filter[$cond['attr']] = $cond['value'];
			}
			if(!$value && strlen($default = trim($filter['default']))) {
				$value = $default;
				if(is_null($fields_)) Context::set($fname, $value);
				else $fields_[$fname] = $value;
			}
			$value_len = strlen($value);
			if(is_string($modifiers = $filter['modifiers'])) $modifiers = explode(',', trim($modifiers));
			if($filter['required'] === 'true' && !$value_len) return $this->error($key, 'isnull');
			if(!$exists && !$value_len) continue;
			if($length = $filter['length']) {
				list($min, $max) = explode(':', trim($length));
				$is_min_b = (substr($min, -1) === 'b');
				$is_max_b = (substr($max, -1) === 'b');
				list($min, $max) = array((int) $min, (int) $max);
				$strbytes = strlen($value);
				if(!$is_min_b || !$is_max_b) $strlength = $this->_has_mb_func ? mb_strlen($value, 'utf-8') : $this->mbStrLen($value);
				if(($min && $min > ($is_min_b ? $strbytes : $strlength)) || ($max && $max < ($is_max_b ? $strbytes : $strlength))) return $this->error($key, 'outofrange');
			}
			if($equalto = $filter['equalto']) {
				if(!array_key_exists($equalto, $fields) || trim($fields[$equalto]) !== $value) return $this->error($key, 'equalto');
			}
			if($rules = $filter['rule']) {
				$rules = explode(',', $rules);
				foreach($rules as $rule) {
					$result = $this->applyRule($rule, $value);
					if(in_array('not', $modifiers)) $result = !$result;
					if(!$result) return $this->error($key, 'invalid_' . $rule);
				}
			}
		}
		return TRUE;
	}

	function arrayTrim($array) {
		if(!is_array($array)) return trim($array);
		foreach($array as $key => $value) $array[$key] = $this->arrayTrim($value);
		return $array;
	}

	function error($field, $msg) {
		if(isset($this->_message[$msg])) {
			$msg = $this->_message[$msg];
		} else {
			$lang_filter = Context::getLang('filter');
			$msg = isset($lang_filter->{$msg}) ? $lang_filter->{$msg} : $lang_filter->invalid;
		}
		if(isset($this->_fieldNames[$field])) {
			$fieldName = $this->_fieldNames[$field];
		} else {
			$fieldName = Context::getLang($field);
		}
		$msg = sprintf($msg, $fieldName);
		$this->_last_error = array('field' => $field, 'msg' => $msg);
		return FALSE;
	}

	function getLastError() {
		return $this->_last_error;
	}

	function addRule($name, $rule = '') {
		if(is_array($name)) $args = $name;
		else $args = array($name => $rule);
		foreach($args as $name => $rule) {
			if(!$rule) continue;
			if(is_string($rule)) $rule = array('type' => 'regex', 'test' => $rule);
			if($rule['type'] == 'enum') {
				$delim = isset($rule['delim']) ? $rule['delim'] : ',';
				$rule['test'] = explode($delim, $rule['test']);
			}
			$this->_rules[$name] = $rule;
		}
	}

	function removeRule($name) {
		unset($this->_rules[$name]);
	}

	function addFilter($name, $filter = '') {
		if(is_array($name)) $args = $name;
		else $args = array($name => $filter);
		foreach($args as $name => $filter) {
			if(!$filter) continue;
			if(isset($filter['if'])) {
				if(is_array($filter['if']) && count($filter['if'])) {
					$key = key($filter['if']);
					if(!is_int($key)) $filter['if'] = array($filter['if']);
				} else {
					unset($filter['if']);
				}
			}
			$this->_filters[$name] = $filter;
		}
	}

	function removeFilter($name) {
		unset($this->_filters[$name]);
	}

	function applyRule($name, $value) {
		$rule = $this->_rules[$name];
		if(is_array($value) && isset($value['tmp_name'])) $value = $value['name'];
		switch($rule['type']) {
			case 'regex':
				return (preg_match($rule['test'], $value) > 0);
			break;
			case 'enum':
				return in_array($value, $rule['test']);
			break;
			case 'expr':
				if(!$rule['func_test']) $rule['func_test'] = create_function('$a', 'return (' . preg_replace('/\$\$/', '$a', html_entity_decode($rule['test'])) . ');');
				return $rule['func_test']($value);
			break;
		}
		return TRUE;
	}

	function mbStrLen($str) {
		$arr = count_chars($str);
		for($i = 0x80; $i < 0xc0; $i++) unset($arr[$i]);
		return array_sum($arr);
	}

	function getJsPath() {
		if(!$this->_cache_dir) return FALSE;
		$dir = $this->_cache_dir . '/ruleset';
		if(!is_dir($dir) && !mkdir($dir)) return FALSE;
		if(!$this->_xml_path) return FALSE;
		$lang_type = class_exists('Context', false) ? Context::getLangType() : 'en';
		$filepath = $dir . '/' . md5($this->_version . ' ' . $this->_xml_path) . ".{$lang_type}.js";
		if(is_readable($filepath) && filemtime($filepath) > filemtime($this->_xml_path)) return $filepath;
		$content = $this->_compile2js();
		if($content === FALSE) return FALSE;
		@file_put_contents($filepath, $content, LOCK_EX);
		return $filepath;
	}

	function _compile2js() {
		global $lang;
		$ruleset = basename($this->_xml_path, '.xml');
		$content = array();
		if(preg_match('@(^|/)files/ruleset/\w+\.xml$@i', $this->_xml_path)) $ruleset = '@' . $ruleset;
		list($ruleset) = explode('.', $ruleset);
		$lang_type = class_exists('Context', false) ? Context::getLangType() : 'en';
		$addrules = array();
		foreach($this->_rules as $name => $rule) {
			$name = strtolower($name);
			if(in_array($name, array('email', 'userid', 'url', 'alpha', 'alpha_number', 'number', 'float'))) continue;
			switch($rule['type']) {
				case 'regex':
					$addrules[] = "v.cast('ADD_RULE', ['{$name}', {$rule['test']}]);";
				break;
				case 'enum':
					$enums = '"' . implode('","', $rule['test']) . '"';
					$addrules[] = "v.cast('ADD_RULE', ['{$name}', function($$){ return ($.inArray($$,[{$enums}]) > -1); }]);";
				break;
				case 'expr':
					$addrules[] = "v.cast('ADD_RULE', ['{$name}', function($$){ return ({$rule['test']}); }]);";
				break;
			}
			if(isset($rule['message'])) {
				$text = preg_replace('@\r?\n@', '\\n', addslashes($rule['message']));
				$addrules[] = "v.cast('ADD_MESSAGE',['invalid_{$name}','{$text}']);";
			}
		}
		$addrules = implode('', $addrules);
		$content = array();
		$messages = array();
		foreach($this->_filters as $name => $filter) {
			$field = array();
			if(isset($filter['title'])) {
				$field_lang = addslashes($filter['title']);
				$messages[] = "v.cast('ADD_MESSAGE',['{$name}','{$field_lang}']);";
			} elseif(isset($lang->{$name})) {
				$field_lang = addslashes($lang->{$name});
				$messages[] = "v.cast('ADD_MESSAGE',['{$name}','{$field_lang}']);";
			}
			if($filter['required'] == 'true') $field[] = 'required:true';
			if($filter['rule']) $field[] = "rule:'" . strtolower($filter['rule']) . "'";
			if($filter['default']) $field[] = "default:'{$filter['default']}'";
			if($filter['modifier']) $field[] = "modifier:'{$filter['modifier']}'";
			if($filter['length']) {
				list($min, $max) = explode(':', $filter['length']);
				if($min) $field[] = "minlength:'{$min}'";
				if($max) $field[] = "maxlength:'{$max}'";
			}
			if($filter['if']) {
				$ifs = array();
				if(!isset($filter['if'][0])) $filter['if'] = array($filter['if']);
				foreach($filter['if'] as $if) $ifs[] = "{test:'" . addslashes($if['test']) . "', attr:'{$if['attr']}', value:'" . addslashes($if['value']) . "'}";
				$field[] = "'if':[" . implode(',', $ifs) . "]";
			}
			if(count($field)) {
				$field = '{' . implode(',', $field) . '}';
				$content[] = "'{$name}':{$field}";
			}
		}
		if(!$content) return '/* Error : empty ruleset  */';
		foreach($lang->filter as $key => $text) {
			if($text) {
				$text = preg_replace('@\r?\n@', '\\n', addslashes($text));
				$messages[] = "v.cast('ADD_MESSAGE',['{$key}','{$text}']);";
			}
		}
		$content = implode(',', $content);
		$messages = implode("\n", $messages);
		return "(function($,v){\nv=xe.getApp('validator')[0];if(!v)return;\n{$addrules}\nv.cast('ADD_FILTER',['{$ruleset}', {{$content}}]);\n{$messages}\n})(jQuery);";
	}
}
