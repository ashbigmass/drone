<?php
class CacheWincache extends CacheBase
{
	public static $isSupport = false;

	function getInstance($opt = null) {
		if(!$GLOBALS['__CacheWincache__']) $GLOBALS['__CacheWincache__'] = new CacheWincache();
		return $GLOBALS['__CacheWincache__'];
	}

	function CacheWincache() {
	}

	function isSupport() {
		return self::$isSupport;
	}

	function put($key, $buff, $valid_time = 0) {
		if($valid_time == 0) $valid_time = $this->valid_time;
		return wincache_ucache_set(md5(_XE_PATH_ . $key), array($_SERVER['REQUEST_TIME'], $buff), $valid_time);
	}

	function isValid($key, $modified_time = 0) {
		$_key = md5(_XE_PATH_ . $key);
		$obj = wincache_ucache_get($_key, $success);
		if(!$success || !is_array($obj)) return false;
		unset($obj[1]);
		if($modified_time > 0 && $modified_time > $obj[0]) {
			$this->_delete($_key);
			return false;
		}
		return true;
	}

	function get($key, $modified_time = 0) {
		$_key = md5(_XE_PATH_ . $key);
		$obj = wincache_ucache_get($_key, $success);
		if(!$success || !is_array($obj)) return false;
		if($modified_time > 0 && $modified_time > $obj[0]) {
			$this->_delete($_key);
			return false;
		}
		return $obj[1];
	}

	function _delete($_key) {
		wincache_ucache_delete($_key);
	}

	function delete($key) {
		$_key = md5(_XE_PATH_ . $key);
		$this->_delete($_key);
	}

	function truncate() {
		return wincache_ucache_clear();
	}
}

CacheWincache::$isSupport = function_exists('wincache_ucache_set');
