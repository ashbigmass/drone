<?php
class ConditionArgument extends Argument
{
	var $operation;

	function ConditionArgument($name, $value, $operation) {
		$operationList = array('in' => 1, 'notin' => 1, 'not_in' => 1, 'between' => 1);
		if(isset($value) && isset($operationList[$operation]) && !is_array($value) && $value != '') {
			$value = str_replace(' ', '', $value);
			$value = str_replace('\'', '', $value);
			$value = explode(',', $value);
		}
		parent::Argument($name, $value);
		$this->operation = $operation;
	}

	function createConditionValue() {
		if(!isset($this->value)) return;
		$operation = $this->operation;
		$value = $this->value;
		switch($operation) {
			case 'like_prefix' :
				if(defined('__CUBRID_VERSION__') && __CUBRID_VERSION__ >= '8.4.1') $this->value = '^' . str_replace('%', '(.*)', preg_quote($value));
				else $this->value = $value . '%';
			break;
			case 'like_tail' :
				if(defined('__CUBRID_VERSION__') && __CUBRID_VERSION__ >= '8.4.1') $this->value = str_replace('%', '(.*)', preg_quote($value)) . '$';
				else $this->value = '%' . $value;
			break;
			case 'like' :
				if(defined('__CUBRID_VERSION__') && __CUBRID_VERSION__ >= '8.4.1') $this->value = str_replace('%', '(.*)', preg_quote($value));
				else $this->value = '%' . $value . '%';
			break;
			case 'notlike' :
				$this->value = '%' . $value . '%';
			break;
			case 'notlike_prefix' :
				$this->value = $value . '%';
			break;
			case 'notlike_tail' :
				$this->value = '%' . $value;
			break;
			case 'in':
				if(!is_array($value)) $this->value = array($value);
			break;
			case 'notin':
			case 'not_in':
				if(!is_array($value)) $this->value = array($value);
			break;
		}
	}

	function getType() {
		if($this->type) return $this->type;
		else if(!is_numeric($this->value)) return 'varchar';
		else return '';
	}

	function setColumnType($column_type) {
		if(!isset($this->value)) return;
		if($column_type === '') return;
		$this->type = $column_type;
	}
}
