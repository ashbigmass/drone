<?php
class UpdateColumnTag extends ColumnTag
{
	var $argument;
	var $default_value;

	function UpdateColumnTag($column) {
		parent::ColumnTag($column->attrs->name);
		$dbParser = DB::getParser();
		$this->name = $dbParser->parseColumnName($this->name);
		if($column->attrs->var) {
			$this->argument = new QueryArgument($column);
		} else {
			if(strpos($column->attrs->default, '.') !== FALSE) {
				$this->default_value = "'" . $dbParser->parseColumnName($column->attrs->default) . "'";
			} else {
				$default_value = new DefaultValue($this->name, $column->attrs->default);
				if($default_value->isOperation()) {
					$this->argument = new QueryArgument($column, TRUE);
				} else {
					$this->default_value = $default_value->toString();
					if($default_value->isStringFromFunction()) $this->default_value = '"\'".' . $this->default_value . '."\'"';
					if($default_value->isString()) $this->default_value = '"' . $this->default_value . '"';
				}
			}
		}
	}

	function getExpressionString() {
		if($this->argument) {
			return sprintf('new UpdateExpression(\'%s\', ${\'%s_argument\'})'
				, $this->name
				, $this->argument->argument_name);
		} else {
			return sprintf('new UpdateExpressionWithoutArgument(\'%s\', %s)'
				, $this->name
				, $this->default_value);
		}
	}

	function getArgument() {
		return $this->argument;
	}
}
