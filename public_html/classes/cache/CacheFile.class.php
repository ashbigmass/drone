<?php
class CacheFile extends CacheBase
{
	var $cache_dir = 'files/cache/store/';
	function getInstance() {
		if(!$GLOBALS['__CacheFile__']) $GLOBALS['__CacheFile__'] = new CacheFile();
		return $GLOBALS['__CacheFile__'];
	}

	function CacheFile()  {
		$this->cache_dir = _XE_PATH_ . $this->cache_dir;
		FileHandler::makeDir($this->cache_dir);
	}

	function getCacheFileName($key) {
		return $this->cache_dir . str_replace(':', DIRECTORY_SEPARATOR, $key) . '.php';
	}

	function isSupport() {
		return true;
	}

	function put($key, $obj, $valid_time = 0)  {
		$cache_file = $this->getCacheFileName($key);
		$content = array();
		$content[] = '<?php';
		$content[] = 'if(!defined(\'__XE__\')) { exit(); }';
		$content[] = 'return \'' . addslashes(serialize($obj)) . '\';';
		FileHandler::writeFile($cache_file, implode(PHP_EOL, $content));
		if(function_exists('opcache_invalidate')) @opcache_invalidate($cache_file, true);
	}

	function isValid($key, $modified_time = 0) {
		$cache_file = $this->getCacheFileName($key);
		if(file_exists($cache_file)) {
			if($modified_time > 0 && filemtime($cache_file) < $modified_time) {
				FileHandler::removeFile($cache_file);
				return false;
			}
			return true;
		}
		return false;
	}

	function get($key, $modified_time = 0) {
		if(!$cache_file = FileHandler::exists($this->getCacheFileName($key))) return false;
		if($modified_time > 0 && filemtime($cache_file) < $modified_time) {
			FileHandler::removeFile($cache_file);
			return false;
		}
		$content = include($cache_file);
		return unserialize(stripslashes($content));
	}

	function _delete($_key) {
		$cache_file = $this->getCacheFileName($_key);
		if(function_exists('opcache_invalidate')) @opcache_invalidate($cache_file, true);
		FileHandler::removeFile($cache_file);
	}

	function delete($key) {
		$this->_delete($key);
	}

	function truncate() {
		FileHandler::removeFilesInDir($this->cache_dir);
	}
}
