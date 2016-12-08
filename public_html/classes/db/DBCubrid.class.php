<?php
class DBCubrid extends DB
{
	var $prefix = 'xe_';
	var $cutlen = 12000;
	var $comment_syntax = '/* %s */';
	var $column_type = array(
		'bignumber' => 'numeric(20)',
		'number' => 'integer',
		'varchar' => 'character varying',
		'char' => 'character',
		'tinytext' => 'character varying(256)',
		'text' => 'character varying(1073741823)',
		'bigtext' => 'character varying(1073741823)',
		'date' => 'character varying(14)',
		'float' => 'float',
	);

	function DBCubrid() {
		$this->_setDBInfo();
		$this->_connect();
	}

	function create() {
		return new DBCubrid;
	}

	function __connect($connection) {
		$result = @cubrid_connect($connection["db_hostname"], $connection["db_port"], $connection["db_database"], $connection["db_userid"], $connection["db_password"]);
		if(!$result) {
			$this->setError(-1, 'database connect fail');
			return;
		}
		if(!defined('__CUBRID_VERSION__')) {
			$cubrid_version = cubrid_get_server_info($result);
			$cubrid_version_elem = explode('.', $cubrid_version);
			$cubrid_version = $cubrid_version_elem[0] . '.' . $cubrid_version_elem[1] . '.' . $cubrid_version_elem[2];
			define('__CUBRID_VERSION__', $cubrid_version);
		}
		if(__CUBRID_VERSION__ >= '8.4.0') cubrid_set_autocommit($result, CUBRID_AUTOCOMMIT_TRUE);
		return $result;
	}

	function _close($connection) {
		@cubrid_commit($connection);
		@cubrid_disconnect($connection);
		$this->transaction_started = FALSE;
	}

	function addQuotes($string) {
		if(version_compare(PHP_VERSION, "5.4.0", "<") && get_magic_quotes_gpc()) $string = stripslashes(str_replace("\\", "\\\\", $string));
		if(!is_numeric($string)) $string = str_replace("'", "''", $string);
		return $string;
	}

	function _begin($transactionLevel = 0) {
		if(__CUBRID_VERSION__ >= '8.4.0') {
			$connection = $this->_getConnection('master');
			if(!$transactionLevel) cubrid_set_autocommit($connection, CUBRID_AUTOCOMMIT_FALSE);
			else $this->_query("SAVEPOINT SP" . $transactionLevel, $connection);
		}
		return TRUE;
	}

	function _rollback($transactionLevel = 0) {
		$connection = $this->_getConnection('master');
		$point = $transactionLevel - 1;
		if($point) $this->_query("ROLLBACK TO SP" . $point, $connection);
		else @cubrid_rollback($connection);
		return TRUE;
	}

	function _commit() {
		$connection = $this->_getConnection('master');
		@cubrid_commit($connection);
		return TRUE;
	}

	function __query($query, $connection) {
		if($this->use_prepared_statements == 'Y') {
			$req = @cubrid_prepare($connection, $query);
			if(!$req) {
				$this->_setError();
				return false;
			}
			$position = 0;
			if($this->param) {
				foreach($this->param as $param) {
					$value = $param->getUnescapedValue();
					$type = $param->getType();
					if($param->isColumnName()) continue;
					switch($type) {
						case 'number' : $bind_type = 'numeric'; break;
						case 'varchar' : $bind_type = 'string'; break;
						default: $bind_type = 'string';
					}
					if(is_array($value)) {
						foreach($value as $v) {
							$bound = @cubrid_bind($req, ++$position, $v, $bind_type);
							if(!$bound) {
								$this->_setError();
								return false;
							}
						}
					} else {
						$bound = @cubrid_bind($req, ++$position, $value, $bind_type);
						if(!$bound) {
							$this->_setError();
							return false;
						}
					}
				}
			}
			$result = @cubrid_execute($req);
			if(!$result) {
				$this->_setError();
				return false;
			}
			return $req;
		}
		$result = @cubrid_execute($connection, $query);
		if(!$result) {
			$this->_setError();
			return false;
		}
		return $result;
	}

	function _setError() {
		$code = cubrid_error_code();
		$msg = cubrid_error_msg();
		$this->setError($code, $msg);
	}

	function _fetch($result, $arrayIndexEndValue = NULL) {
		$output = array();
		if(!$this->isConnected() || $this->isError() || !$result) return array();
		if($this->use_prepared_statements == 'Y') {
		}
		$col_types = cubrid_column_types($result);
		$col_names = cubrid_column_names($result);
		$max = count($col_types);
		for($count = 0; $count < $max; $count++) {
			if(preg_match("/^char/", $col_types[$count]) > 0) $char_type_fields[] = $col_names[$count];
		}
		while($tmp = cubrid_fetch($result, CUBRID_OBJECT)) {
			if(is_array($char_type_fields)) {
				foreach($char_type_fields as $val) $tmp->{$val} = rtrim($tmp->{$val});
			}
			if($arrayIndexEndValue) $output[$arrayIndexEndValue--] = $tmp;
			else $output[] = $tmp;
		}
		unset($char_type_fields);
		if($result) cubrid_close_request($result);
		if(count($output) == 1) {
			if(isset($arrayIndexEndValue)) return $output;
			else return $output[0];
		}
		return $output;
	}

	function getNextSequence() {
		$this->_makeSequence();
		$query = sprintf("select \"%ssequence\".\"nextval\" as \"seq\" from db_root", $this->prefix);
		$result = $this->_query($query);
		$output = $this->_fetch($result);
		return $output->seq;
	}

	function _makeSequence() {
		if($_GLOBALS['XE_EXISTS_SEQUENCE']) return;
		$query = sprintf('select count(*) as "count" from "db_serial" where name=\'%ssequence\'', $this->prefix);
		$result = $this->_query($query);
		$output = $this->_fetch($result);
		if($output->count == 0) {
			$query = sprintf('select max("a"."srl") as "srl" from ' .
				'( select max("document_srl") as "srl" from ' .
				'"%sdocuments" UNION ' .
				'select max("comment_srl") as "srl" from ' .
				'"%scomments" UNION ' .
				'select max("member_srl") as "srl" from ' .
				'"%smember"' .
				') as "a"', $this->prefix, $this->prefix, $this->prefix);
			$result = $this->_query($query);
			$output = $this->_fetch($result);
			$srl = $output->srl;
			if($srl < 1) $start = 1;
			else $start = $srl + 1000000;
			$query = sprintf('create serial "%ssequence" start with %s increment by 1 minvalue 1 maxvalue 10000000000000000000000000000000000000 nocycle;', $this->prefix, $start);
			$this->_query($query);
		}
		$_GLOBALS['XE_EXISTS_SEQUENCE'] = TRUE;
	}

	function isTableExists($target_name) {
		if($target_name == 'sequence') $query = sprintf("select \"name\" from \"db_serial\" where \"name\" = '%s%s'", $this->prefix, $target_name);
		else $query = sprintf("select \"class_name\" from \"db_class\" where \"class_name\" = '%s%s'", $this->prefix, $target_name);
		$result = $this->_query($query);
		if(cubrid_num_rows($result) > 0) $output = TRUE;
		else $output = FALSE;
		if($result) cubrid_close_request($result);
		return $output;
	}

	function addColumn($table_name, $column_name, $type = 'number', $size = '', $default = null, $notnull = FALSE) {
		$type = strtoupper($this->column_type[$type]);
		if($type == 'INTEGER') $size = '';
		$query = sprintf("alter class \"%s%s\" add \"%s\" ", $this->prefix, $table_name, $column_name);
		if($type == 'char' || $type == 'varchar') {
			if($size) $size = $size * 3;
		}
		if($size) $query .= sprintf("%s(%s) ", $type, $size);
		else $query .= sprintf("%s ", $type);
		if(isset($default)) {
			if($type == 'INTEGER' || $type == 'BIGINT' || $type == 'INT') $query .= sprintf("default %d ", $default);
			else $query .= sprintf("default '%s' ", $default);
		}
		if($notnull) $query .= "not null ";
		return $this->_query($query);
	}

	function dropColumn($table_name, $column_name) {
		$query = sprintf("alter class \"%s%s\" drop \"%s\" ", $this->prefix, $table_name, $column_name);
		$this->_query($query);
	}

	function isColumnExists($table_name, $column_name) {
		$query = sprintf("select \"attr_name\" from \"db_attribute\" where " . "\"attr_name\" ='%s' and \"class_name\" = '%s%s'", $column_name, $this->prefix, $table_name);
		$result = $this->_query($query);
		if(cubrid_num_rows($result) > 0) $output = TRUE;
		else $output = FALSE;
		if($result) cubrid_close_request($result);
		return $output;
	}

	function addIndex($table_name, $index_name, $target_columns, $is_unique = FALSE) {
		if(!is_array($target_columns)) $target_columns = array($target_columns);
		$query = sprintf("create %s index \"%s\" on \"%s%s\" (%s);", $is_unique ? 'unique' : '', $index_name, $this->prefix, $table_name, '"' . implode('","', $target_columns) . '"');
		$this->_query($query);
	}

	function dropIndex($table_name, $index_name, $is_unique = FALSE) {
		$query = sprintf("drop %s index \"%s\" on \"%s%s\"", $is_unique ? 'unique' : '', $index_name, $this->prefix, $table_name);
		$this->_query($query);
	}

	function isIndexExists($table_name, $index_name) {
		$query = sprintf("select \"index_name\" from \"db_index\" where " . "\"class_name\" = '%s%s' and (\"index_name\" = '%s' or \"index_name\" = '%s') ", $this->prefix, $table_name, $this->prefix . $index_name, $index_name);
		$result = $this->_query($query);
		if($this->isError()) return FALSE;
		$output = $this->_fetch($result);
		if(!$output) return FALSE;
		return TRUE;
	}

	function deleteDuplicateIndexes() {
		$query = sprintf("
			select \"class_name\"
			, case
			when substr(\"index_name\", 0, %d) = '%s'
			then substr(\"index_name\", %d)
			else \"index_name\" end as unprefixed_index_name
			, \"is_unique\"
			from \"db_index\"
			where \"class_name\" like %s
			group by \"class_name\"
			, case
			when substr(\"index_name\", 0, %d) = '%s'
			then substr(\"index_name\", %d)
			else \"index_name\"
			end
			having count(*) > 1
			", strlen($this->prefix)
			, $this->prefix
			, strlen($this->prefix) + 1
			, "'" . $this->prefix . '%' . "'"
			, strlen($this->prefix)
			, $this->prefix
			, strlen($this->prefix) + 1
		);
		$result = $this->_query($query);
		if($this->isError()) return FALSE;
		$output = $this->_fetch($result);
		if(!$output) return FALSE;
		if(!is_array($output)) $indexes_to_be_deleted = array($output);
		else $indexes_to_be_deleted = $output;
		foreach($indexes_to_be_deleted as $index) {
			$this->dropIndex(substr($index->class_name, strlen($this->prefix))
				, $this->prefix . $index->unprefixed_index_name
				, $index->is_unique == 'YES' ? TRUE : FALSE);
		}
		return TRUE;
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
		if($table_name == 'sequence') {
			$query = sprintf('create serial "%s" start with 1 increment by 1' .
				' minvalue 1 ' .
				'maxvalue 10000000000000000000000000000000000000' . ' nocycle;', $this->prefix . $table_name);
			return $this->_query($query);
		}
		$table_name = $this->prefix . $table_name;
		$query = sprintf('create class "%s";', $table_name);
		$this->_query($query);
		if(!is_array($xml_obj->table->column)) $columns[] = $xml_obj->table->column;
		else $columns = $xml_obj->table->column;
		$query = sprintf("alter class \"%s\" add attribute ", $table_name);
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
			switch($this->column_type[$type]) {
				case 'integer' : $size = NULL; break;
				case 'text' : $size = NULL; break;
			}
			if(isset($default) && ($type == 'varchar' || $type == 'char' || $type == 'text' || $type == 'tinytext' || $type == 'bigtext')) $default = sprintf("'%s'", $default);
			if($type == 'varchar' || $type == 'char') if($size) $size = $size * 3;
			$column_schema[] = sprintf('"%s" %s%s %s %s', $name, $this->column_type[$type], $size ? '(' . $size . ')' : '', isset($default) ? "default " . $default : '', $notnull ? 'not null' : '');
			if($primary_key) $primary_list[] = $name;
			else if($unique) $unique_list[$unique][] = $name;
			else if($index) $index_list[$index][] = $name;
		}
		$query .= implode(',', $column_schema) . ';';
		$this->_query($query);
		if(count($primary_list)) {
			$query = sprintf("alter class \"%s\" add attribute constraint " . "\"pkey_%s\" PRIMARY KEY(%s);", $table_name, $table_name, '"' . implode('","', $primary_list) . '"');
			$this->_query($query);
		}
		if(count($unique_list)) {
			foreach($unique_list as $key => $val) {
				$query = sprintf("create unique index \"%s\" on \"%s\" " . "(%s);", $key, $table_name, '"' . implode('","', $val) . '"');
				$this->_query($query);
			}
		}
		if(count($index_list)) {
			foreach($index_list as $key => $val) {
				$query = sprintf("create index \"%s\" on \"%s\" (%s);", $key, $table_name, '"' . implode('","', $val) . '"');
				$this->_query($query);
			}
		}
	}

	function _executeInsertAct($queryObject, $with_values = TRUE) {
		if($this->use_prepared_statements == 'Y') {
			$this->param = $queryObject->getArguments();
			$with_values = FALSE;
		}
		$query = $this->getInsertSql($queryObject, $with_values);
		if(is_a($query, 'Object')) {
			unset($this->param);
			return;
		}
		$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		$result = $this->_query($query);
		if($result && !$this->transaction_started) $this->_commit();
		unset($this->param);
		return $result;
	}

	function _executeUpdateAct($queryObject, $with_values = TRUE) {
		if($this->use_prepared_statements == 'Y') {
			$this->param = $queryObject->getArguments();
			$with_values = FALSE;
		}
		$query = $this->getUpdateSql($queryObject, $with_values);
		if(is_a($query, 'Object')) {
			unset($this->param);
			return;
		}
		$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		$result = $this->_query($query);
		if($result && !$this->transaction_started) $this->_commit();
		unset($this->param);
		return $result;
	}

	function _executeDeleteAct($queryObject, $with_values = TRUE) {
		if($this->use_prepared_statements == 'Y') {
			$this->param = $queryObject->getArguments();
			$with_values = FALSE;
		}
		$query = $this->getDeleteSql($queryObject, $with_values);
		if(is_a($query, 'Object')) {
			unset($this->param);
			return;
		}
		$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		$result = $this->_query($query);
		if($result && !$this->transaction_started) $this->_commit();
		unset($this->param);
		return $result;
	}

	function _executeSelectAct($queryObject, $connection = NULL, $with_values = TRUE) {
		if($this->use_prepared_statements == 'Y') {
			$this->param = $queryObject->getArguments();
			$with_values = FALSE;
		}
		$limit = $queryObject->getLimit();
		if($limit && $limit->isPageHandler()) {
			return $this->queryPageLimit($queryObject, $connection, $with_values);
		} else {
			$query = $this->getSelectSql($queryObject, $with_values);
			if(is_a($query, 'Object')) {
				unset($this->param);
				return;
			}
			$query .= (__DEBUG_QUERY__ & 1 && $this->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
			$result = $this->_query($query, $connection);
			if($this->isError()) {
				unset($this->param);
				return $this->queryError($queryObject);
			}
			$data = $this->_fetch($result);
			$buff = new Object ();
			$buff->data = $data;
			unset($this->param);
			return $buff;
		}
	}

	function queryError($queryObject) {
		$limit = $queryObject->getLimit();
		if($limit && $limit->isPageHandler()) {
			$buff = new Object ();
			$buff->total_count = 0;
			$buff->total_page = 0;
			$buff->page = 1;
			$buff->data = array();
			$buff->page_navigation = new PageHandler(/* $total_count */0, /* $total_page */1, /* $page */1, /* $page_count */10); //default page handler values
			return $buff;
		}else
			return;
	}

	function queryPageLimit($queryObject, $connection, $with_values) {
		$limit = $queryObject->getLimit();
		$temp_where = $queryObject->getWhereString($with_values, FALSE);
		$count_query = sprintf('select count(*) as "count" %s %s', 'FROM ' . $queryObject->getFromString($with_values), ($temp_where === '' ? '' : ' WHERE ' . $temp_where));
		$temp_select = $queryObject->getSelectString($with_values);
		$uses_distinct = stripos($temp_select, "distinct") !== FALSE;
		$uses_groupby = $queryObject->getGroupByString() != '';
		if($uses_distinct || $uses_groupby) {
			$count_query = sprintf('select %s %s %s %s'
				, $temp_select
				, 'FROM ' . $queryObject->getFromString($with_values)
				, ($temp_where === '' ? '' : ' WHERE ' . $temp_where)
				, ($uses_groupby ? ' GROUP BY ' . $queryObject->getGroupByString() : '')
			);
			$count_query = sprintf('select count(*) as "count" from (%s) xet', $count_query);
		}
		$count_query .= (__DEBUG_QUERY__ & 1 && $queryObject->queryID) ? sprintf(' ' . $this->comment_syntax, $queryObject->queryID) : '';
		$result = $this->_query($count_query, $connection);
		$count_output = $this->_fetch($result);
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
			unset($this->param);
			return $buff;
		}
		$start_count = ($page - 1) * $list_count;
		$query = $this->getSelectPageSql($queryObject, $with_values, $start_count, $list_count);
		$query .= (__DEBUG_QUERY__ & 1 && $queryObject->query_id) ? sprintf(' ' . $this->comment_syntax, $this->query_id) : '';
		$result = $this->_query($query, $connection);
		if($this->isError()) {
			unset($this->param);
			return $this->queryError($queryObject);
		}
		$virtual_no = $total_count - ($page - 1) * $list_count;
		$data = $this->_fetch($result, $virtual_no);
		$buff = new Object ();
		$buff->total_count = $total_count;
		$buff->total_page = $total_page;
		$buff->page = $page;
		$buff->data = $data;
		$buff->page_navigation = new PageHandler($total_count, $total_page, $page, $page_count);
		unset($this->param);
		return $buff;
	}

	function getParser($force = FALSE) {
		return new DBParser('"', '"', $this->prefix);
	}

	function getSelectPageSql($query, $with_values = TRUE, $start_count = 0, $list_count = 0) {
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

DBCubrid::$isSupported = function_exists('cubrid_connect');
