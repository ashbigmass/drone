<?php
class ConditionWithoutArgument extends Condition
{
	function ConditionWithoutArgument($column_name, $argument, $operation, $pipe = "") {
		parent::Condition($column_name, $argument, $operation, $pipe);
		$tmpArray = array('in' => 1, 'notin' => 1, 'not_in' => 1);
		if(isset($tmpArray[$operation])) {
			if(is_array($argument)) $argument = implode($argument, ',');
			$this->_value = '(' . $argument . ')';
		} else {
			$this->_value = $argument;
		}
	}
}
