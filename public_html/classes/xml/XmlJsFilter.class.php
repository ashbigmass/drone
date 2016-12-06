<?php
class XmlJsFilter extends XmlParser
{
	var $version = '0.2.5';
	var $compiled_path = './files/cache/js_filter_compiled/';
	var $xml_file = NULL;
	var $js_file = NULL;

	function XmlJsFilter($path, $xml_file) {
		if(substr($path, -1) !== '/') $path .= '/';
		$this->xml_file = sprintf("%s%s", $path, $xml_file);
		$this->js_file = $this->_getCompiledFileName($this->xml_file);
	}

	function compile() {
		if(!file_exists($this->xml_file)) return;
		if(!file_exists($this->js_file)) $this->_compile();
		else if(filemtime($this->xml_file) > filemtime($this->js_file)) $this->_compile();
		Context::loadFile(array($this->js_file, 'body', '', null));
	}

	function _compile() {
		global $lang;
		$buff = FileHandler::readFile($this->xml_file);
		$xml_obj = parent::parse($buff);
		$attrs = $xml_obj->filter->attrs;
		$rules = $xml_obj->filter->rules;
		$filter_name = $attrs->name;
		$confirm_msg_code = $attrs->confirm_msg_code;
		$module = $attrs->module;
		$act = $attrs->act;
		$extend_filter = $attrs->extend_filter;
		$field_node = $xml_obj->filter->form->node;
		if($field_node && !is_array($field_node)) $field_node = array($field_node);
		$parameter_param = $xml_obj->filter->parameter->param;
		if($parameter_param && !is_array($parameter_param)) $parameter_param = array($parameter_param);
		$response_tag = $xml_obj->filter->response->tag;
		if($response_tag && !is_array($response_tag)) $response_tag = array($response_tag);
		if($extend_filter) {
			$this->js_file .= '.nocache.js';
			list($module_name, $method) = explode('.', $extend_filter);
			if($module_name && $method) {
				$oExtendFilter = getModel($module_name);
				if(method_exists($oExtendFilter, $method)) {
					$extend_filter_list = $oExtendFilter->{$method}(TRUE);
					$extend_filter_count = count($extend_filter_list);
					for($i = 0; $i < $extend_filter_count; $i++) {
						$name = $extend_filter_list[$i]->name;
						$lang_value = $extend_filter_list[$i]->lang;
						if($lang_value) $lang->{$name} = $lang_value;
					}
				}
			}
		}
		$target_list = array();
		$target_type_list = array();
		$js_rules = array();
		$js_messages = array();
		$fields = array();
		if($rules && $rules->rule) {
			if(!is_array($rules->rule)) $rules->rule = array($rules->rule);
			foreach($rules->rule as $r) {
				if($r->attrs->type == 'regex') $js_rules[] = "v.cast('ADD_RULE', ['{$r->attrs->name}', {$r->body}]);";
			}
		}
		$node_count = count($field_node);
		if($node_count) {
			foreach($field_node as $key => $node) {
				$attrs = $node->attrs;
				$target = trim($attrs->target);
				if(!$target) continue;
				$rule = trim($attrs->rule ? $attrs->rule : $attrs->filter);
				$equalto = trim($attrs->equalto);
				$field = array();
				if($attrs->required == 'true') $field[] = 'required:true';
				if($attrs->minlength > 0) $field[] = 'minlength:' . $attrs->minlength;
				if($attrs->maxlength > 0) $field[] = 'maxlength:' . $attrs->maxlength;
				if($equalto) $field[] = "equalto:'{$attrs->equalto}'";
				if($rule) $field[] = "rule:'{$rule}'";
				$fields[] = "'{$target}': {" . implode(',', $field) . "}";
				if(!in_array($target, $target_list)) $target_list[] = $target;
				if(!$target_type_list[$target]) $target_type_list[$target] = $filter;
			}
		}
		$rule_types = array('homepage' => 'homepage', 'email_address' => 'email');
		for($i = 0; $i < $extend_filter_count; $i++) {
			$filter_item = $extend_filter_list[$i];
			$target = trim($filter_item->name);
			if(!$target) continue;
			$type = $filter_item->type;
			$rule = $rule_types[$type] ? $rule_types[$type] : '';
			$required = ($filter_item->required == 'true');
			$field = array();
			if($required) $field[] = 'required:true';
			if($rule) $field[] = "rule:'{$rule}'";
			$fields[] = "\t\t'{$target}' : {" . implode(',', $field) . "}";
			if(!in_array($target, $target_list)) $target_list[] = $target;
			if(!$target_type_list[$target]) $target_type_list[$target] = $type;
		}
		$rename_params = array();
		$parameter_count = count($parameter_param);
		if($parameter_count) {
			foreach($parameter_param as $key => $param) {
				$attrs = $param->attrs;
				$name = trim($attrs->name);
				$target = trim($attrs->target);
				if($name && $target && ($name != $target)) $rename_params[] = "'{$target}':'{$name}'";
				if($name && !in_array($name, $target_list)) $target_list[] = $name;
			}
			for($i = 0; $i < $extend_filter_count; $i++) {
				$filter_item = $extend_filter_list[$i];
				$target = $name = trim($filter_item->name);
				if(!$name || !$target) continue;
				if(!in_array($name, $target_list)) $target_list[] = $name;
			}
		}
		$response_count = count($response_tag);
		$responses = array();
		for($i = 0; $i < $response_count; $i++) {
			$attrs = $response_tag[$i]->attrs;
			$name = $attrs->name;
			$responses[] = "'{$name}'";
		}
		$target_count = count($target_list);
		for($i = 0; $i < $target_count; $i++) {
			$target = $target_list[$i];
			if(!$lang->{$target}) $lang->{$target} = $target;
			$text = preg_replace('@\r?\n@', '\\n', addslashes($lang->{$target}));
			$js_messages[] = "v.cast('ADD_MESSAGE',['{$target}','{$text}']);";
		}
		foreach($lang->filter as $key => $val) {
			if(!$val) $val = $key;
			$val = preg_replace('@\r?\n@', '\\n', addslashes($val));
			$js_messages[] = sprintf("v.cast('ADD_MESSAGE',['%s','%s']);", $key, $val);
		}
		$callback_func = $xml_obj->filter->response->attrs->callback_func;
		if(!$callback_func) $callback_func = "filterAlertMessage";
		$confirm_msg = '';
		if($confirm_msg_code) $confirm_msg = $lang->{$confirm_msg_code};
		$jsdoc = array();
		$jsdoc[] = "function {$filter_name}(form){ return legacy_filter('{$filter_name}', form, '{$module}', '{$act}', {$callback_func}, [" . implode(',', $responses) . "], '" . addslashes($confirm_msg) . "', {" . implode(',', $rename_params) . "}) };";
		$jsdoc[] = '(function($){';
		$jsdoc[] = "\tvar v=xe.getApp('validator')[0];if(!v)return false;";
		$jsdoc[] = "\t" . 'v.cast("ADD_FILTER", ["' . $filter_name . '", {' . implode(',', $fields) . '}]);';
		$jsdoc[] = "\t" . implode("\n\t", $js_rules);
		$jsdoc[] = "\t" . implode("\n\t", $js_messages);
		$jsdoc[] = '})(jQuery);';
		$jsdoc = implode("\n", $jsdoc);
		FileHandler::writeFile($this->js_file, $jsdoc);
	}

	function _getCompiledFileName($xml_file) {
		return sprintf('%s%s.%s.compiled.js', $this->compiled_path, md5($this->version . $xml_file), Context::getLangType());
	}
}
