<?php
class SelectExpression extends Expression
{
	var $column_alias;

	function SelectExpression($column_name, $alias = NULL) {
		parent::Expression($column_name);
		$this->column_alias = $alias;
	}

	function getExpression() {
		return sprintf("%s%s", $this->column_name, $this->column_alias ? " as " . $this->column_alias : "");
	}

	function show() {
		return true;
	}

	function getArgument() {
		return null;
	}

	function getArguments() {
		return array();
	}

	function isSubquery() {
		return false;
	}
}
