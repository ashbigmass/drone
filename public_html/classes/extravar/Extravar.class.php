<?php
class ExtraVar
{
	var $module_srl = null;
	var $keys = null;

	function &getInstance($module_srl) {
		return new ExtraVar($module_srl);
	}

	function ExtraVar($module_srl) {
		$this->module_srl = $module_srl;
	}

	function setExtraVarKeys($extra_keys) {
		if(!is_array($extra_keys) || count($extra_keys) < 1) return;
		foreach($extra_keys as $val) {
			$obj = new ExtraItem($val->module_srl, $val->idx, $val->name, $val->type, $val->default, $val->desc, $val->is_required, $val->search, $val->value, $val->eid);
			$this->keys[$val->idx] = $obj;
		}
	}

	function getExtraVars() {
		return $this->keys;
	}
}

class ExtraItem
{
	var $module_srl = 0;
	var $idx = 0;
	var $name = 0;
	var $type = 'text';
	var $default = null;
	var $desc = '';
	var $is_required = 'N';
	var $search = 'N';
	var $value = null;
	var $eid = '';

	function ExtraItem($module_srl, $idx, $name, $type = 'text', $default = null, $desc = '', $is_required = 'N', $search = 'N', $value = null, $eid = '') {
		if(!$idx) return;
		$this->module_srl = $module_srl;
		$this->idx = $idx;
		$this->name = $name;
		$this->type = $type;
		$this->default = $default;
		$this->desc = $desc;
		$this->is_required = $is_required;
		$this->search = $search;
		$this->value = $value;
		$this->eid = $eid;
	}

	function setValue($value) {
		$this->value = $value;
	}

	function _getTypeValue($type, $value) {
		$value = trim($value);
		if(!isset($value)) return;
		switch($type) {
			case 'homepage' :
				if($value && !preg_match('/^([a-z]+):\/\//i', $value)) $value = 'http://' . $value;
				return htmlspecialchars($value, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			case 'tel' :
				if(is_array($value)) $values = $value;
				elseif(strpos($value, '|@|') !== FALSE) $values = explode('|@|', $value);
				elseif(strpos($value, ',') !== FALSE) $values = explode(',', $value);
				$values = array_values($values);
				for($i = 0, $c = count($values); $i < $c; $i++) $values[$i] = trim(htmlspecialchars($values[$i], ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
				return $values;
			case 'checkbox' :
			case 'radio' :
			case 'select' :
				if(is_array($value)) $values = $value;
				elseif(strpos($value, '|@|') !== FALSE) $values = explode('|@|', $value);
				elseif(strpos($value, ',') !== FALSE) $values = explode(',', $value);
				else $values = array($value);
				$values = array_values($values);
				for($i = 0, $c = count($values); $i < $c; $i++) $values[$i] = trim(htmlspecialchars($values[$i], ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
				return $values;
			case 'kr_zip' :
				if(is_array($value)) $values = $value;
				elseif(strpos($value, '|@|') !== false) $values = explode('|@|', $value);
				else $values = array($value);
				$values = array_values($values);
				for($i = 0, $c = count($values); $i < $c; $i++) $values[$i] = trim(htmlspecialchars($values[$i], ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
				return $values;
			default :
				return htmlspecialchars($value, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		}
	}

	function getValue() {
		return $this->_getTypeValue($this->type, $this->value);
	}

	function getValueHTML() {
		$value = $this->_getTypeValue($this->type, $this->value);
		switch($this->type) {
			case 'homepage' :
				return ($value) ? (sprintf('<a href="%s" target="_blank">%s</a>', $value, strlen($value) > 60 ? substr($value, 0, 40) . '...' . substr($value, -10) : $value)) : "";
			case 'email_address' :
				return ($value) ? sprintf('<a href="mailto:%s">%s</a>', $value, $value) : "";
			case 'tel' :
				return sprintf('%s-%s-%s', $value[0], $value[1], $value[2]);
			case 'textarea' :
				return nl2br($value);
			case 'date' :
				return zdate($value, "Y-m-d");
			case 'checkbox' :
			case 'select' :
			case 'radio' :
				if(is_array($value)) return implode(',', $value);
				return $value;
			case 'kr_zip' :
				if(is_array($value)) return implode(' ', $value);
				return $value;
			default :
				return $value;
		}
	}

	function getFormHTML() {
		static $id_num = 1000;
		$type = $this->type;
		$name = $this->name;
		$value = $this->_getTypeValue($this->type, $this->value);
		$default = $this->_getTypeValue($this->type, $this->default);
		$column_name = 'extra_vars' . $this->idx;
		$tmp_id = $column_name . '-' . $id_num++;
		$buff = array();
		switch($type) {
			case 'homepage' :
				$buff[] = '<input type="text" name="' . $column_name . '" value="' . $value . '" class="homepage" />';
			break;
			case 'email_address' :
				$buff[] = '<input type="text" name="' . $column_name . '" value="' . $value . '" class="email_address" />';
			break;
			case 'tel' :
				$buff[] = '<input type="text" name="' . $column_name . '[]" value="' . $value[0] . '" size="4" maxlength="4" class="tel" />';
				$buff[] = '<input type="text" name="' . $column_name . '[]" value="' . $value[1] . '" size="4" maxlength="4" class="tel" />';
				$buff[] = '<input type="text" name="' . $column_name . '[]" value="' . $value[2] . '" size="4" maxlength="4" class="tel" />';
			break;
			case 'textarea' :
				$buff[] = '<textarea name="' . $column_name . '" rows="8" cols="42">' . $value . '</textarea>';
			break;
			case 'checkbox' :
				$buff[] = '<ul>';
				foreach($default as $v) {
					$checked = '';
					if($value && in_array(trim($v), $value)) $checked = ' checked="checked"';
					$tmp_id = $column_name . '-' . $id_num++;
					$buff[] ='  <li><input type="checkbox" name="' . $column_name . '[]" id="' . $tmp_id . '" value="' . htmlspecialchars($v, ENT_COMPAT | ENT_HTML401, 'UTF-8', false) . '" ' . $checked . ' /><label for="' . $tmp_id . '">' . $v . '</label></li>';
				}
				$buff[] = '</ul>';
			break;
			case 'select' :
				$buff[] = '<select name="' . $column_name . '" class="select">';
				foreach($default as $v) {
					$selected = '';
					if($value && in_array(trim($v), $value)) $selected = ' selected="selected"';
					$buff[] = '  <option value="' . $v . '" ' . $selected . '>' . $v . '</option>';
				}
				$buff[] = '</select>';
			break;
			case 'radio' :
				$buff[] = '<ul>';
				foreach($default as $v) {
					$checked = '';
					if($value && in_array(trim($v), $value)) $checked = ' checked="checked"';
					$tmp_id = $column_name . '-' . $id_num++;
					$buff[] = '<li><input type="radio" name="' . $column_name . '" id="' . $tmp_id . '" ' . $checked . ' value="' . $v . '"  class="radio" /><label for="' . $tmp_id . '">' . $v . '</label></li>';
				}
				$buff[] = '</ul>';
			break;
			case 'date' :
				Context::loadJavascriptPlugin('ui.datepicker');
				$buff[] = '<input type="hidden" name="' . $column_name . '" value="' . $value . '" />';
				$buff[] =	'<input type="text" id="date_' . $column_name . '" value="' . zdate($value, 'Y-m-d') . '" class="date" />';
				$buff[] =	'<input type="button" value="' . Context::getLang('cmd_delete') . '" class="btn" id="dateRemover_' . $column_name . '" />';
				$buff[] =	'<script type="text/javascript">';
				$buff[] = '//<![CDATA[';
				$buff[] =	'(function($){';
				$buff[] =	'$(function(){';
				$buff[] =	'  var option = { dateFormat: "yy-mm-dd", changeMonth:true, changeYear:true, gotoCurrent:false, yearRange:\'-100:+10\', onSelect:function(){';
				$buff[] =	'    $(this).prev(\'input[type="hidden"]\').val(this.value.replace(/-/g,""))}';
				$buff[] =	'  };';
				$buff[] =	'  $.extend(option,$.datepicker.regional[\'' . Context::getLangType() . '\']);';
				$buff[] =	'  $("#date_' . $column_name . '").datepicker(option);';
				$buff[] =	'  $("#dateRemover_' . $column_name . '").click(function(){';
				$buff[] =	'    $(this).siblings("input").val("");';
				$buff[] =	'    return false;';
				$buff[] =	'  })';
				$buff[] =	'});';
				$buff[] =	'})(jQuery);';
				$buff[] = '//]]>';
				$buff[] = '</script>';
			break;
			case "kr_zip" :
				if(($oKrzipModel = getModel('krzip')) && method_exists($oKrzipModel , 'getKrzipCodeSearchHtml' )) {
					$buff[] =  $oKrzipModel->getKrzipCodeSearchHtml($column_name, $value);
				}
			break;
			default :
				$buff[] =' <input type="text" name="' . $column_name . '" value="' . ($value ? $value : $default) . '" class="text" />';
		}
		if($this->desc) {
			$oModuleController = getController('module');
			$oModuleController->replaceDefinedLangCode($this->desc);
			$buff[] = '<p>' . htmlspecialchars($this->desc, ENT_COMPAT | ENT_HTML401, 'UTF-8', false) . '</p>';
		}
		return join(PHP_EOL, $buff);
	}
}
