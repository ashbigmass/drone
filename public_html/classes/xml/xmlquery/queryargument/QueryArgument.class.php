<?php
class QueryArgument
{
	var $argument_name;
	var $variable_name;
	var $argument_validator;
	var $column_name;
	var $table_name;
	var $operation;
	var $ignore_value;

	function QueryArgument($tag, $ignore_value = FALSE) {
		static $number_of_arguments = 0;
		$this->argument_name = $tag->attrs->var;
		if(!$this->argument_name) $this->argument_name = str_replace('.', '_', $tag->attrs->name);
		if(!$this->argument_name) $this->argument_name = str_replace('.', '_', $tag->attrs->column);
		$this->variable_name = $this->argument_name;
		$number_of_arguments++;
		$this->argument_name .= $number_of_arguments;
		$name = $tag->attrs->name;
		if(!$name) $name = $tag->attrs->column;
		if(strpos($name, '.') === FALSE) {
			$this->column_name = $name;
		} else {
			list($prefix, $name) = explode('.', $name);
			$this->column_name = $name;
			$this->table_name = $prefix;
		}
		if($tag->attrs->operation) $this->operation = $tag->attrs->operation;
		$this->argument_validator = new QueryArgumentValidator($tag, $this);
		$this->ignore_value = $ignore_value;
	}

	function getArgumentName() {
		return $this->argument_name;
	}

	function getColumnName() {
		return $this->column_name;
	}

	function getTableName() {
		return $this->table_name;
	}

	function getValidatorString() {
		return $this->argument_validator->toString();
	}

	function isConditionArgument() {
		if($this->operation) return TRUE;
		return FALSE;
	}

	function toString() {
		if($this->isConditionArgument()) {
			$arg = sprintf("\n" . '${\'%s_argument\'} = new ConditionArgument(\'%s\', %s, \'%s\');' . "\n"
				, $this->argument_name
				, $this->variable_name
				, '$args->' . $this->variable_name
				, $this->operation
			);
			$arg .= $this->argument_validator->toString();
			$arg .= sprintf('${\'%s_argument\'}->createConditionValue();' . "\n"
				, $this->argument_name
			);
			$arg .= sprintf('if(!${\'%s_argument\'}->isValid()) return ${\'%s_argument\'}->getErrorMessage();' . "\n"
				, $this->argument_name
				, $this->argument_name
			);
		} else {
			$arg = sprintf("\n" . '${\'%s_argument\'} = new Argument(\'%s\', %s);' . "\n"
				, $this->argument_name
				, $this->variable_name
				, $this->ignore_value ? 'NULL' : '$args->{\'' . $this->variable_name . '\'}');
			$arg .= $this->argument_validator->toString();
			$arg .= sprintf('if(!${\'%s_argument\'}->isValid()) return ${\'%s_argument\'}->getErrorMessage();' . "\n"
				, $this->argument_name
				, $this->argument_name
			);
		}
		if($this->argument_validator->isIgnorable()) {
			$arg = sprintf("if(isset(%s)) {", '$args->' . $this->variable_name)
				. $arg
				. sprintf("} else\n" . '${\'%s_argument\'} = NULL;', $this->argument_name);
		}
		return $arg;
	}
}
