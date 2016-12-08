<?php
class DBMysql extends DB
{
	var $prefix = 'xe_';
	var $comment_syntax = '/* %s */';
	var $column_type = array( 'bignumber' => 'bigint', 'number' => 'bigint', 'varchar' => 'varchar', 'char' => 'char', 'text' => 'text', 'bigtext' => 'longtext', 'date' => 'varchar(14)', 'float' => 'float');

	function DBMysql() {
		$this->_setDBInfo();
		$this->_connect();
	}

	function create() {
		return new DBMysql;
	}

	function __connect($connection) {
		if(strpos($connection["db_hostname"], ':') === false && $connection["db_port"]) $connection["db_hostname"] .= ':' . $connection["db_port"];
		$result = @mysql_connect($connection["db_hostname"], $connection["db_userid"], $connection["db_password"]);
		if(!$result) exit('XE cannot connect to DB.');
		if(mysql_error()) {
			$this->setError(mysql_errno(), mysql_error());
			return;
		}
		if(version_compare(mysql_get_server_info($result), '4.1', '<')) {
			$this->setError(-1, 'XE cannot be installed under the version of mysql 4.1. Current mysql version is ' . mysql_get_server_info());
			return;
		}
		@mysql_select_db($connection["db_database"], $result);
		if(mysql_error()) {
			$this->setError(mysql_errno(), mysql_error());
			return;
		}
		return $result;
	}

	function _afterConnect($connection) 	{
		$this->_query("set names 'utf8'", $connection);
	}

	function _close($connection) {
		@mysql_close($connection);
	}

	function addQuotes($string) {
		if(version_compare(PHP_VERSION, "5.4.0", "<") && get_magic_quotes_gpc()) $string = stripslashes(str_replace("\\", "\\\\", $string));
		if(!is_numeric($string)) $string = @mysql_real_escape_string($string);
		return $string;
	}

	function _begin($transactionLevel = 0) {
		return true;
	}

	function _rollback($transactionLevel = 0) {
		return true;
	}

	function _commit() {
		return true;
	}

	function __query($query, $connection) {
		if(!$connection) exit('XE cannot handle DB connection.');
		$result = mysql_query($query, $connection);
		if(mysql_error($connection)) $this->setError(mysql_errno($connection), mysql_error($connection));
		return $result;
	}

	function _fetch($result, $arrayIndexEndValue = NULL) {
		$output = array();
		if(!$this->isConnected() || $this->isError() || !$result) return $output;
		while($tmp = $this->db_fetch_object($result)) {
			if($arrayIndexEndValue) $output[$arrayIndexEndValue--] = $tmp;
			else $output[] = $tmp;
		}
		if(count($output) == 1) {
			if(isset($arrayIndexEndValue)) return $output;
			else return $output[0];
		}
		$this->db_free_result($result);
		return $output;
	}

	function getNextSequence() {
		$query = sprintf("insert into `%ssequence` (seq) values ('0')", $this->prefix);
		$this->_query($query);
		$sequence = $this->db_insert_id();
		if($sequence % 10000 == 0) {
			$query = sprintf("delete from  `%ssequence` where seq < %d", $this->prefix, $sequence);
			$this->_query($query);
		}
		return $sequence;
	}

	function isValidOldPassword($password, $saved_password) {
		$query = sprintf("select password('%s') as password, old_password('%s') as old_password", $this->addQuotes($password), $this->addQuotes($password));
		$result = $this->_query($query);
		$tmp = $this->_fetch($result);
		if($tmp->password === $saved_password || $tmp->old_password === $saved_password) return true;
		return false;
	}

	function isTableExists($target_name) {
		$query = sprintf("show tables like '%s%s'", $this->prefix, $this->addQuotes($target_name));
		$result = $this->_query($query);
		$tmp = $this->_fetch($result);
		if(!$tmp) return false;
		return true;
	}

	function addColumn($table_name, $column_name, $type = 'number', $size = '', $default = null, $notnull = false) {
		$type = $this->column_type[$type];
		if(strtoupper($type) == 'INTEGER') $size = '';
		$query = sprintf("alter table `%s%s` add `%s` ", $this->prefix, $table_name, $column_name);
		if($size) $query .= sprintf(" %s(%s) ", $type, $size);
		else $query .= sprintf(" %s ", $type);
		if(isset($default)) $query .= sprintf(" default '%s' ", $default);
		if($notnull) $query .= " not null ";
		return $this->_query($query);
	}

	function dropColumn($table_name, $column_name) {
		$query = sprintf("alter table `%s%s` drop `%s` ", $this->prefix, $table_name, $column_name);
		$this->_query($query);
	}

	function isColumnExists($table_name, $column_name) {
		$query = sprintf("show fields from `%s%s`", $this->prefix, $table_name);
		$result = $this->_query($query);
		if($this->isError()) return;
		$output = $this->_fetch($result);
		if($output) {
			$column_name = strtolower($column_name);
			foreach($output as $key => $val) {
				$name = strtolower($val->Field);
				if($column_name == $name) return true;
			}
		}
		return false;
	}

	function addIndex($table_name, $index_name, $target_columns, $is_unique = false) {
		if(!is_array($target_columns)) $target_columns = array($target_columns);
		$query = sprintf("alter table `%s%s` add %s index `%s` (%s);", $this->prefix, $table_name, $is_unique ? 'unique' : '', $index_name, implode(',', $target_columns));
		$this->_query($query);
	}

	function dropIndex($table_name, $index_name, $is_unique = false) {
		$query = sprintf("alter table `%s%s` drop index `%s`", $this->prefix, $table_name, $index_name);
		$this->_query($query);
	}

	function isIndexExists($table_name, $index_name) {
		$query = sprintf("show indexes from `%s%s`", $this->prefix, $table_name);
		$result = $this->_query($query);
		if($this->isError()) return;
		$output = $this->_fetch($result);
		if(!$output) return;
		if(!is_array($output)) $output = array($output);
		for($i = 0; $i < count($output); $i++) if($output[$i]->Key_name == $index_name) return true;
		return false;
	}

	function createTableByXml($xml_doc) {
		return $this->_createTable($xml_doc);
	}

	function createTableByXmlFile($file_name) {
		if(!file_exists($file_name)) return;
		$buff = FileHandler::readFile($file_name);
		return $this->_createTable($buff);
	}

	function _createTable($xml_doc) {
		$oXml = new XmlParser();
		$xml_obj = $oXml->parse($xml_doc);
		$table_name = $xml_obj->table->attrs->name;
		if($this->isTableExists($table_name)) return;
		$table_name = $this->prefix . $table_name;
		if(!is_array($xml_obj->table->column)) $columns[] = $xml_obj->table->column;
		else $columns = $xml_obj->table->column;
		$primary_list = array();
		$unique_list = array();
		$index_list = array();
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
		$schema = sprintf('create table `%s` (%s%s) %s;', $this->addQuotes($table_name), "\n", implode($column_schema, ",\n"), "ENGINE = MYISAM  CHARACTER SET utf8 COLLATE utf8_general_ci");
		$output = $this->_query($schema);
		if(!$output) return false;
	}

	function _executeInsertAct($queryObject, $with_values = true) {
		$query = $this->getInsertSql($queryObject, $with_values, true);
		$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		if(is_a($query, 'Object')) return;
		return $this->_query($query);
	}

	function _executeUpdateAct($queryObject, $with_values = true) {
		$query = $this->getUpdateSql($queryObject, $with_values, true);
		if(is_a($query, 'Object')) {
			if(!$query->toBool()) return $query;
			else return;
		}
		$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		return $this->_query($query);
	}

	function _executeDeleteAct($queryObject, $with_values = true) {
		$query = $this->getDeleteSql($queryObject, $with_values, true);
		$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		if(is_a($query, 'Object')) return;
		return $this->_query($query);
	}

	function _executeSelectAct($queryObject, $connection = null, $with_values = true) {
		$limit = $queryObject->getLimit();
		$result = NULL;
		if($limit && $limit->isPageHandler()) {
			return $this->queryPageLimit($queryObject, $result, $connection, $with_values);
		} else {
			$query = $this->getSelectSql($queryObject, $with_values);
			if(is_a($query, 'Object')) return;
			$query .= (__DEBUG_QUERY__ & 1 && $queryObject->queryID) ? sprintf(' ' . $this->comment_syntax, $queryObject->queryID) : '';
			$result = $this->_query($query, $connection);
			if($this->isError()) return $this->queryError($queryObject);
			$data = $this->_fetch($result);
			$buff = new Object ();
			$buff->data = $data;
			if($queryObject->usesClickCount()) {
				$update_query = $this->getClickCountQuery($queryObject);
				$this->_executeUpdateAct($update_query, $with_values);
			}
			return $buff;
		}
	}

	function db_insert_id() {
		$connection = $this->_getConnection('master');
		return mysql_insert_id($connection);
	}

	function db_fetch_object(&$result) {
		return mysql_fetch_object($result);
	}

	function db_free_result(&$result) {
		return mysql_free_result($result);
	}

	function &getParser($force = FALSE) {
		$dbParser = new DBParser('`', '`', $this->prefix);
		return $dbParser;
	}

	function queryError($queryObject) {
		$limit = $queryObject->getLimit();
		if($limit && $limit->isPageHandler()) {
			$buff = new Object ();
			$buff->total_count = 0;
			$buff->total_page = 0;
			$buff->page = 1;
			$buff->data = array();
			$buff->page_navigation = new PageHandler(0, 1, 1, 10);
			return $buff;
		} else {
			return;
		}
	}

	function queryPageLimit($queryObject, $result, $connection, $with_values = true) {
		$limit = $queryObject->getLimit();
		$temp_where = $queryObject->getWhereString($with_values, false);
		$count_query = sprintf('select count(*) as "count" %s %s', 'FROM ' . $queryObject->getFromString($with_values), ($temp_where === '' ? '' : ' WHERE ' . $temp_where));
		$temp_select = $queryObject->getSelectString($with_values);
		$uses_distinct = stripos($temp_select, "distinct") !== false;
		$uses_groupby = $queryObject->getGroupByString() != '';
		if($uses_distinct || $uses_groupby) {
			$count_query = sprintf('select %s %s %s %s'
				, $temp_select == '*' ? '1' : $temp_select
				, 'FROM ' . $queryObject->getFromString($with_values)
				, ($temp_where === '' ? '' : ' WHERE ' . $temp_where)
				, ($uses_groupby ? ' GROUP BY ' . $queryObject->getGroupByString() : '')
			);
			$count_query = sprintf('select count(*) as "count" from (%s) xet', $count_query);
		}
		$count_query .= (__DEBUG_QUERY__ & 1 && $queryObject->queryID) ? sprintf(' ' . $this->comment_syntax, $queryObject->queryID) : '';
		$result_count = $this->_query($count_query, $connection);
		$count_output = $this->_fetch($result_count);
		$total_count = (int) (isset($count_output->count) ? $count_output->count : NULL);
		$list_count = $limit->list_count->getValue();
		if(!$list_count) $list_count = 20;
		$page_count = $limit->page_count->getValue();
		if(!$page_count) $page_count = 10;
		$page = $limit->page->getValue();
		if(!$page || $page < 1) $page = 1;
		if($total_count) $total_page = (int) (($total_count - 1) / $list_count) + 1;
		else $total_page = 1;
		if($page > $total_page) {
			$buff = new Object ();
			$buff->total_count = $total_count;
			$buff->total_page = $total_page;
			$buff->page = $page;
			$buff->data = array();
			$buff->page_navigation = new PageHandler($total_count, $total_page, $page, $page_count);
			return $buff;
		}
		$start_count = ($page - 1) * $list_count;
		$query = $this->getSelectPageSql($queryObject, $with_values, $start_count, $list_count);
		$query .= (__DEBUG_QUERY__ & 1 && $queryObject->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		$result = $this->_query($query, $connection);
		if($this->isError()) return $this->queryError($queryObject);
		$virtual_no = $total_count - ($page - 1) * $list_count;
		$data = $this->_fetch($result, $virtual_no);
		$buff = new Object ();
		$buff->total_count = $total_count;
		$buff->total_page = $total_page;
		$buff->page = $page;
		$buff->data = $data;
		$buff->page_navigation = new PageHandler($total_count, $total_page, $page, $page_count);
		return $buff;
	}

	function getSelectPageSql($query, $with_values = true, $start_count = 0, $list_count = 0) {
		$select = $query->getSelectString($with_values);
		if($select == '') return new Object(-1, "Invalid query");
		$select = 'SELECT ' . $select;
		$from = $query->getFromString($with_values);
		if($from == '') return new Object(-1, "Invalid query");
		$from = ' FROM ' . $from;
		$where = $query->getWhereString($with_values);
		if($where != '') $where = ' WHERE ' . $where;
		$groupBy = $query->getGroupByString();
		if($groupBy != '') $groupBy = ' GROUP BY ' . $groupBy;
		$orderBy = $query->getOrderByString();
		if($orderBy != '') $orderBy = ' ORDER BY ' . $orderBy;
		$limit = $query->getLimitString();
		if($limit != '') $limit = sprintf(' LIMIT %d, %d', $start_count, $list_count);
		return $select . ' ' . $from . ' ' . $where . ' ' . $groupBy . ' ' . $orderBy . ' ' . $limit;
	}

}

DBMysql::$isSupported = function_exists('mysql_connect');
