<?php
class InsertColumnsTag
{
	var $columns;

	function InsertColumnsTag($xml_columns) {
		$this->columns = array();
		if(!$xml_columns) return;
		if(!is_array($xml_columns)) $xml_columns = array($xml_columns);
		foreach($xml_columns as $column) {
			if($column->name === 'query') {
				$this->columns[] = new QueryTag($column, TRUE);
			} else if(!isset($column->attrs->var) && !isset($column->attrs->default)) {
				$this->columns[] = new InsertColumnTagWithoutArgument($column);
			} else {
				$this->columns[] = new InsertColumnTag($column);
			}
		}
	}

	function toString() {
		$output_columns = 'array(' . PHP_EOL;
		foreach($this->columns as $column) $output_columns .= $column->getExpressionString() . PHP_EOL . ',';
		$output_columns = substr($output_columns, 0, -1);
		$output_columns .= ')';
		return $output_columns;
	}

	function getArguments() {
		$arguments = array();
		foreach($this->columns as $column) $arguments[] = $column->getArgument();
		return $arguments;
	}
}
