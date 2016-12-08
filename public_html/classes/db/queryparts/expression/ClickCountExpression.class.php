<?php
class ClickCountExpression extends SelectExpression
{
	var $click_count;

	function ClickCountExpression($column_name, $alias = NULL, $click_count = false) {
		parent::SelectExpression($column_name, $alias);
		if(!is_bool($click_count)) $this->click_count = false;
		$this->click_count = $click_count;
	}

	function show() {
		return $this->click_count;
	}

	function getExpression() {
		$db_type = Context::getDBType();
		if($db_type == 'cubrid') return "INCR($this->column_name)";
		else return "$this->column_name";
	}
}
