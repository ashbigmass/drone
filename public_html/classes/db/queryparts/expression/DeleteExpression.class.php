<?php
class DeleteExpression extends Expression
{
	var $value;

	function DeleteExpression($column_name, $value) {
		parent::Expression($column_name);
		$this->value = $value;
	}

	function getExpression() {
		return "$this->column_name = $this->value";
	}

	function getValue() {
		if(!is_numeric($this->value)) return "'" . $this->value . "'";
		return $this->value;
	}

	function show() {
		if(!$this->value) return false;
		return true;
	}
}
