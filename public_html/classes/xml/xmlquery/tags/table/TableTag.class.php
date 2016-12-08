<?php
class TableTag
{
	var $unescaped_name;
	var $name;
	var $alias;
	var $join_type;
	var $conditions;
	var $conditionsTag;

	function TableTag($table) {
		$dbParser = DB::getParser();
		$this->unescaped_name = $table->attrs->name;
		$this->name = $dbParser->parseTableName($table->attrs->name);
		$this->alias = $table->attrs->alias;
		if(!$this->alias) $this->alias = $table->attrs->name;
		$this->join_type = $table->attrs->type;
		$this->conditions = $table->conditions;
		if($this->isJoinTable()) $this->conditionsTag = new JoinConditionsTag($this->conditions);
	}

	function isJoinTable() {
		$joinList = array('left join' => 1, 'left outer join' => 1, 'right join' => 1, 'right outer join' => 1);
		if(isset($joinList[$this->join_type]) && count($this->conditions)) return true;
		return false;
	}

	function getTableAlias() {
		return $this->alias;
	}

	function getTableName() {
		return $this->unescaped_name;
	}

	function getTableString() {
		$dbParser = DB::getParser();
		if($this->isJoinTable()) {
			return sprintf('new JoinTable(\'%s\', \'%s\', "%s", %s)'
				, $dbParser->escape($this->name)
				, $dbParser->escape($this->alias)
				, $this->join_type, $this->conditionsTag->toString());
		}
		return sprintf('new Table(\'%s\'%s)'
			, $dbParser->escape($this->name)
			, $this->alias ? ', \'' . $dbParser->escape($this->alias) . '\'' : '');
	}

	function getArguments() {
		if(!isset($this->conditionsTag)) return array();
		return $this->conditionsTag->getArguments();
	}
}
