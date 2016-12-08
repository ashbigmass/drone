<?php
class HintTableTag extends TableTag
{
	var $index;

	function HintTableTag($table, $index) {
		parent::TableTag($table);
		$this->index = $index;
	}

	function getTableString() {
		$dbParser = DB::getParser();
		$dbType = ucfirst(Context::getDBType());
		$result = sprintf('new %sTableWithHint(\'%s\'%s, array('
			, $dbType == 'Mysqli' ? 'Mysql' : $dbType
			, $dbParser->escape($this->name)
			, $this->alias ? ', \'' . $dbParser->escape($this->alias) . '\'' : ', null'
		);
		foreach($this->index as $indx) {
			$result .= "new IndexHint(";
			$result .= '\'' . $dbParser->escape($indx->name) . '\', \'' . $indx->type . '\'' . ') , ';
		}
		$result = substr($result, 0, -2);
		$result .= '))';
		return $result;
	}

	function getArguments() {
		if(!isset($this->conditionsTag)) return array();
		return $this->conditionsTag->getArguments();
	}
}
