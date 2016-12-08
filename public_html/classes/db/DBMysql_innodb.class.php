<?php
require_once('DBMysql.class.php');

class DBMysql_innodb extends DBMysql
{
	function DBMysql_innodb() {
		$this->_setDBInfo();
		$this->_connect();
	}

	function create() {
		return new DBMysql_innodb;
	}

	function _close($connection) {
		@mysql_close($connection);
	}

	function _begin($transactionLevel = 0) {
		$connection = $this->_getConnection('master');
		if(!$transactionLevel) $this->_query("START TRANSACTION", $connection);
		else $this->_query("SAVEPOINT SP" . $transactionLevel, $connection);
		return true;
	}

	function _rollback($transactionLevel = 0) {
		$connection = $this->_getConnection('master');
		$point = $transactionLevel - 1;
		if($point) $this->_query("ROLLBACK TO SP" . $point, $connection);
		else $this->_query("ROLLBACK", $connection);
		return true;
	}

	function _commit() {
		$connection = $this->_getConnection('master');
		$this->_query("commit", $connection);
		return true;
	}

	function __query($query, $connection) {
		if(!$connection) exit('XE cannot handle DB connection.');
		$result = @mysql_query($query, $connection);
		if(mysql_error($connection)) $this->setError(mysql_errno($connection), mysql_error($connection));
		return $result;
	}

	function _createTable($xml_doc) {
		$oXml = new XmlParser();
		$xml_obj = $oXml->parse($xml_doc);
		$table_name = $xml_obj->table->attrs->name;
		if($this->isTableExists($table_name)) return;
		$table_name = $this->prefix . $table_name;
		if(!is_array($xml_obj->table->column)) $columns[] = $xml_obj->table->column;
		else $columns = $xml_obj->table->column;
		foreach($columns as $column) {
			$name = $column->attrs->name;
			$type = $column->attrs->type;
			$size = $column->attrs->size;
			$notnull = $column->attrs->notnull;
			$primary_key = $column->attrs->primary_key;
			$index = $column->attrs->index;
			$unique = $column->attrs->unique;
			$default = $column->attrs->default;
			$auto_increment = $column->attrs->auto_increment;
			$column_schema[] = sprintf('`%s` %s%s %s %s %s', $name, $this->column_type[$type], $size ? '(' . $size . ')' : '', isset($default) ? "default '" . $default . "'" : '', $notnull ? 'not null' : '', $auto_increment ? 'auto_increment' : '');
			if($primary_key) $primary_list[] = $name;
			else if($unique) $unique_list[$unique][] = $name;
			else if($index) $index_list[$index][] = $name;
		}
		if(count($primary_list)) $column_schema[] = sprintf("primary key (%s)", '`' . implode($primary_list, '`,`') . '`');
		if(count($unique_list)) {
			foreach($unique_list as $key => $val) $column_schema[] = sprintf("unique %s (%s)", $key, '`' . implode($val, '`,`') . '`');
		}
		if(count($index_list)) {
			foreach($index_list as $key => $val) $column_schema[] = sprintf("index %s (%s)", $key, '`' . implode($val, '`,`') . '`');
		}
		$schema = sprintf('create table `%s` (%s%s) %s;', $this->addQuotes($table_name), "\n", implode($column_schema, ",\n"), "ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_general_ci");
		$output = $this->_query($schema);
		if(!$output) return false;
	}
}

DBMysql_innodb::$isSupported = function_exists('mysql_connect');
