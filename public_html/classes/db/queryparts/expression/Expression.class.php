<?php
class Expression
{
	var $column_name;

	function Expression($column_name) {
		$this->column_name = $column_name;
	}

	function getColumnName() {
		return $this->column_name;
	}

	function show() {
		return false;
	}

	function getExpression() {
	}
}
