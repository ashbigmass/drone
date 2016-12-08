<?php
class InsertColumnTagWithoutArgument extends ColumnTag
{
	function InsertColumnTagWithoutArgument($column) {
		parent::ColumnTag($column->attrs->name);
		$dbParser = DB::getParser();
		$this->name = $dbParser->parseColumnName($this->name);
	}

	function getExpressionString() {
		return sprintf('new Expression(\'%s\')', $this->name);
	}

	function getArgument() {
		return NULL;
	}
}
