<?php
class UpdateExpressionWithoutArgument extends UpdateExpression
{
	var $argument;

	function UpdateExpressionWithoutArgument($column_name, $argument) {
		parent::Expression($column_name);
		$this->argument = $argument;
	}

	function getExpression($with_value = true) {
		return "$this->column_name = $this->argument";
	}

	function getValue() {
		$value = $this->argument;
		if(!is_numeric($value)) return "'" . $value . "'";
		return $value;
	}

	function show() {
		if(!$this->argument) return false;
		$value = $this->argument;
		if(!isset($value)) return false;
		return true;
	}

	function getArgument() {
		return null;
	}

	function getArguments() {
		return array();
	}
}
