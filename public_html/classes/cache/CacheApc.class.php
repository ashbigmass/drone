<?php
class CacheApc extends CacheBase
{
	public static $isSupport = false;

	function getInstance($opt = null) {
		if(!$GLOBALS['__CacheApc__']) $GLOBALS['__CacheApc__'] = new CacheApc();
		return $GLOBALS['__CacheApc__'];
	}

	function CacheApc() {
	}

	function isSupport() {
		return self::$isSupport;
	}

	function put($key, $buff, $valid_time = 0) {
		if($valid_time == 0) $valid_time = $this->valid_time;
		return apc_store(md5(_XE_PATH_ . $key), array($_SERVER['REQUEST_TIME'], $buff), $valid_time);
	}

	function isValid($key, $modified_time = 0) {
		$_key = md5(_XE_PATH_ . $key);
		$obj = apc_fetch($_key, $success);
		if(!$success || !is_array($obj)) return false;
		unset($obj[1]);
		if($modified_time > 0 && $modified_time > $obj[0]) {
			$this->delete($key);
			return false;
		}
		return true;
	}

	function get($key, $modified_time = 0) {
		$_key = md5(_XE_PATH_ . $key);
		$obj = apc_fetch($_key, $success);
		if(!$success || !is_array($obj)) return false;
		if($modified_time > 0 && $modified_time > $obj[0]) {
			$this->delete($key);
			return false;
		}
		return $obj[1];
	}

	function delete($key) {
		$_key = md5(_XE_PATH_ . $key);
		return apc_delete($_key);
	}

	function truncate() {
		return apc_clear_cache('user');
	}

	function _delete($key) {
		return $this->delete($key);
	}
}

CacheApc::$isSupport  = function_exists('apc_add');
