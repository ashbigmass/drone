<?php
require_once('DBMysql.class.php');

class DBMysqli_innodb extends DBMysql
{
	function DBMysqli_innodb() {
		$this->_setDBInfo();
		$this->_connect();
	}

	function create() {
		return new DBMysqli_innodb;
	}

	function __connect($connection) {
		if($connection["db_port"]) {
			$result = @mysqli_connect($connection["db_hostname"]
				, $connection["db_userid"]
				, $connection["db_password"]
				, $connection["db_database"]
				, $connection["db_port"]);
		} else {
			$result = @mysqli_connect($connection["db_hostname"]
				, $connection["db_userid"]
				, $connection["db_password"]
				, $connection["db_database"]);
		}
		$error = mysqli_connect_errno();
		if($error) {
			$this->setError($error, mysqli_connect_error());
			return;
		}
		mysqli_set_charset($result, 'utf8');
		return $result;
	}

	function _close($connection) {
		mysqli_close($connection);
	}

	function _begin($transactionLevel = 0) {
		$connection = $this->_getConnection('master');
		if(!$transactionLevel) $this->_query("begin");
		else $this->_query("SAVEPOINT SP" . $transactionLevel, $connection);
		return true;
	}

	function _rollback($transactionLevel = 0) {
		$connection = $this->_getConnection('master');
		$point = $transactionLevel - 1;
		if($point) {
			$this->_query("ROLLBACK TO SP" . $point, $connection);
		} else {
			mysqli_rollback($connection);
			$this->setQueryLog( array("query"=>"rollback") );
		}
		return true;
	}

	function _commit() {
		$connection = $this->_getConnection('master');
		mysqli_commit($connection);
		$this->setQueryLog( array("query"=>"commit") );
		return true;
	}

	function addQuotes($string) {
		if(version_compare(PHP_VERSION, "5.4.0", "<") && get_magic_quotes_gpc()) $string = stripslashes(str_replace("\\", "\\\\", $string));
		if(!is_numeric($string)) {
			$connection = $this->_getConnection('master');
			$string = mysqli_escape_string($connection, $string);
		}
		return $string;
	}

	function __query($query, $connection) {
		if($this->use_prepared_statements == 'Y') {
			$stmt = mysqli_prepare($connection, $query);
			if($stmt) {
				$types = '';
				$params = array();
				$this->_prepareQueryParameters($types, $params);
				if(!empty($params)) {
					$args[0] = $stmt;
					$args[1] = $types;
					$i = 2;
					foreach($params as $key => $param) {
						$copy[$key] = $param;
						$args[$i++] = &$copy[$key];
					}
					$status = call_user_func_array('mysqli_stmt_bind_param', $args);
					if(!$status) $this->setError(-1, "Invalid arguments: $query" . mysqli_error($connection) . PHP_EOL . print_r($args, true));
				}
				$status = mysqli_stmt_execute($stmt);
				if(!$status) $this->setError(-1, "Prepared statement failed: $query" . mysqli_error($connection) . PHP_EOL . print_r($args, true));
				return $stmt;
			}
		}
		$result = mysqli_query($connection, $query);
		$error = mysqli_error($connection);
		if($error) $this->setError(mysqli_errno($connection), $error);
		return $result;
	}

	function _prepareQueryParameters(&$types, &$params) {
		$types = '';
		$params = array();
		if(!$this->param) return;
		foreach($this->param as $k => $o) {
			$value = $o->getUnescapedValue();
			$type = $o->getType();
			if($o->isColumnName()) continue;
			switch($type) {
				case 'number' : $type = 'i'; break;
				case 'varchar' : $type = 's'; break;
				default: $type = 's';
			}
			if(is_array($value)) {
				foreach($value as $v) {
					$params[] = $v;
					$types .= $type;
				}
			} else {
				$params[] = $value;
				$types .= $type;
			}
		}
	}

	function _fetch($result, $arrayIndexEndValue = NULL) {
		if($this->use_prepared_statements != 'Y') return parent::_fetch($result, $arrayIndexEndValue);
		$output = array();
		if(!$this->isConnected() || $this->isError() || !$result) return $output;
		$stmt = $result;
		$meta = mysqli_stmt_result_metadata($stmt);
		$fields = mysqli_fetch_fields($meta);
		$longtext_exists = false;
		foreach($fields as $field) {
			if(isset($resultArray[$field->name])) $field->name = 'repeat_' . $field->name;
			$row[$field->name] = "";
			$resultArray[$field->name] = &$row[$field->name];
			if($field->type == 252) $longtext_exists = true;
		}
		$resultArray = array_merge(array($stmt), $resultArray);
		if($longtext_exists) mysqli_stmt_store_result($stmt);
		call_user_func_array('mysqli_stmt_bind_result', $resultArray);
		$rows = array();
		while(mysqli_stmt_fetch($stmt)) {
			$resultObject = new stdClass();
			foreach($resultArray as $key => $value) {
				if($key === 0) continue;
				if(strpos($key, 'repeat_')) $key = substr($key, 6);
				$resultObject->$key = $value;
			}
			$rows[] = $resultObject;
		}
		mysqli_stmt_close($stmt);
		if($arrayIndexEndValue) {
			foreach($rows as $row) $output[$arrayIndexEndValue--] = $row;
		} else {
			$output = $rows;
		}
		if(count($output) == 1) {
			if(isset($arrayIndexEndValue)) return $output;
			else return $output[0];
		}
		return $output;
	}

	function _executeInsertAct($queryObject, $with_values = false) {
		if($this->use_prepared_statements != 'Y') return parent::_executeInsertAct($queryObject);
		$this->param = $queryObject->getArguments();
		$result = parent::_executeInsertAct($queryObject, $with_values);
		unset($this->param);
		return $result;
	}

	function _executeUpdateAct($queryObject, $with_values = false) {
		if($this->use_prepared_statements != 'Y') return parent::_executeUpdateAct($queryObject);
		$this->param = $queryObject->getArguments();
		$result = parent::_executeUpdateAct($queryObject, $with_values);
		unset($this->param);
		return $result;
	}

	function _executeDeleteAct($queryObject, $with_values = false) {
		if($this->use_prepared_statements != 'Y') return parent::_executeDeleteAct($queryObject);
		$this->param = $queryObject->getArguments();
		$result = parent::_executeDeleteAct($queryObject, $with_values);
		unset($this->param);
		return $result;
	}

	function _executeSelectAct($queryObject, $connection = null, $with_values = false) {
		if($this->use_prepared_statements != 'Y') return parent::_executeSelectAct($queryObject, $connection);
		$this->param = $queryObject->getArguments();
		$result = parent::_executeSelectAct($queryObject, $connection, $with_values);
		unset($this->param);
		return $result;
	}

	function db_insert_id() {
		$connection = $this->_getConnection('master');
		return mysqli_insert_id($connection);
	}

	function db_fetch_object(&$result) {
		return mysqli_fetch_object($result);
	}

	function db_free_result(&$result) {
		return mysqli_free_result($result);
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

DBMysqli_innodb::$isSupported = function_exists('mysqli_connect');
