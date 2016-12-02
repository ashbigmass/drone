<?php
class SuperCacheFileDriver
{
	protected $_dir;

	public function __construct() {
		$this->_dir = _XE_PATH_ . 'files/supercache';
		if (!file_exists($this->_dir)) FileHandler::makeDir($this->_dir);
	}

	public function get($key, $max_age = 0) {
		$filename = $this->getFilename($key);
		if (!file_exists($filename)) return false;
		$data = (include $filename);
		if (!is_array($data) || count($data) < 2 || ($data[0] > 0 && $data[0] < time())) {
			@unlink($filename);
			return false;
		} else {
			return $data[1];
		}
	}

	public function put($key, $value, $ttl = 0) {
		$filename = $this->getFilename($key);
		$filedir = dirname($filename);
		if (!file_exists($filedir)) FileHandler::makeDir($filedir);
		$data = array($ttl ? (time() + $ttl) : 0, $value);
		$data = '<' . '?php /* ' . $key . ' */' . PHP_EOL . 'return unserialize(' . var_export(serialize($data), true) . ');' . PHP_EOL;
		$tmpfilename = $filename . '.tmp.' . microtime(true);
		$result = @file_put_contents($tmpfilename, $data, LOCK_EX);
		if (!$result) return false;
		$result = @rename($tmpfilename, $filename);
		if (!$result) {
			@unlink($filename);
			$result = @rename($tmpfilename, $filename);
		}
		if (function_exists('opcache_invalidate')) @opcache_invalidate($filename, true);
		return $result ? true : false;
	}

	public function delete($key) {
		return @unlink($this->getFilename($key));
	}

	public function isValid($key) {
		return $this->get($key) !== null;
	}

	public function incr($key, $amount) {
		$value = intval($this->get($key));
		$success = $this->put($key, $value + $amount, 0);
		return $success ? ($value + $amount) : false;
	}

	public function decr($key, $amount) {
		return $this->incr($key, 0 - $amount);
	}

	public function truncate() {
		$tempdirname = $this->_dir . '_' . time();
		$renamed = @rename($this->_dir, $tempdirname);
		if (!$renamed) return false;
		return $this->deleteDirectory($tempdirname);
	}

	public function getFilename($key) {
		$key = strtr($key, ':', '/');
		return $this->_dir . '/' . $key . '.php';
	}

	public function getGroupKey($group_key, $key) {
		return $key;
	}

	public function invalidateGroupKey($group_key) {
		return $this->truncate();
	}

	public function invalidateSubgroupKey($subgroup_key, $index) {
		return $this->deleteDirectory($this->_dir . '/' . strtr($subgroup_key, ':', '/') . '/' . $index, false);
	}

	public function deleteDirectory($dir, $fallback = true) {
		if (function_exists('exec') && !preg_match('/(?<!_)exec/', ini_get('disable_functions'))) {
			if (strncasecmp(\PHP_OS, 'win', 3) == 0) @exec('rmdir /S /Q ' . escapeshellarg($dir));
			else @exec('rm -rf ' . escapeshellarg($dir));
		}
		if (file_exists($dir)) {
			if ($fallback) {
				FileHandler::removeDir($dir);
				clearstatcache($dir);
				return file_exists($dir);
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
}
