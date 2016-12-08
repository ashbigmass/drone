<?php
class FrontEndFileHandler extends Handler
{
	static $isSSL = null;
	var $cssMap = array();
	var $jsHeadMap = array();
	var $jsBodyMap = array();
	var $cssMapIndex = array();
	var $jsHeadMapIndex = array();
	var $jsBodyMapIndex = array();

	function isSsl() {
		if(!is_null(self::$isSSL)) return self::$isSSL;
		$url_info = parse_url(Context::getRequestUrl());
		self::$isSSL = ($url_info['scheme'] == 'https');
		return self::$isSSL;
	}

	function loadFile($args) {
		if(!is_array($args)) $args = array($args);
		$file = $this->getFileInfo($args[0], $args[2], $args[1]);
		$availableExtension = array('css' => 1, 'js' => 1);
		if(!isset($availableExtension[$file->fileExtension])) return;
		$file->index = (int) $args[3];
		if($file->fileExtension == 'css') {
			$map = &$this->cssMap;
			$mapIndex = &$this->cssMapIndex;
			$this->_arrangeCssIndex($pathInfo['dirname'], $file);
		} else if($file->fileExtension == 'js') {
			if($args[1] == 'body') {
				$map = &$this->jsBodyMap;
				$mapIndex = &$this->jsBodyMapIndex;
			} else {
				$map = &$this->jsHeadMap;
				$mapIndex = &$this->jsHeadMapIndex;
			}
		}
		(is_null($file->index)) ? $file->index = 0 : $file->index = $file->index;
		if(!isset($mapIndex[$file->key]) || $mapIndex[$file->key] > $file->index) {
			$this->unloadFile($args[0], $args[2], $args[1]);
			$map[$file->index][$file->key] = $file;
			$mapIndex[$file->key] = $file->index;
		}
	}

	private function getFileInfo($fileName, $targetIe = '', $media = 'all') {
		static $existsInfo = array();
		if(isset($existsInfo[$existsKey])) return $existsInfo[$existsKey];
		$pathInfo = pathinfo($fileName);
		$file = new stdClass();
		$file->fileName = $pathInfo['basename'];
		$file->filePath = $this->_getAbsFileUrl($pathInfo['dirname']);
		$file->fileRealPath = FileHandler::getRealPath($pathInfo['dirname']);
		$file->fileExtension = strtolower($pathInfo['extension']);
		$file->fileNameNoExt = preg_replace('/\.min$/', '', $pathInfo['filename']);
		$file->keyName = implode('.', array($file->fileNameNoExt, $file->fileExtension));
		$file->cdnPath = $this->_normalizeFilePath($pathInfo['dirname']);
		if(strpos($file->filePath, '://') === FALSE) {
			if(!__DEBUG__ && __XE_VERSION_STABLE__) {
				$minifiedFileName = implode('.', array($file->fileNameNoExt, 'min', $file->fileExtension));
				$minifiedRealPath = implode('/', array($file->fileRealPath, $minifiedFileName));
				if(file_exists($minifiedRealPath)) $file->fileName = $minifiedFileName;
			} else {
				if(file_exists(implode('/', array($file->fileRealPath, $file->keyName)))) $file->fileName = $file->keyName;
			}
		}
		$file->targetIe = $targetIe;
		if($file->fileExtension == 'css') {
			$file->media = $media;
			if(!$file->media) $file->media = 'all';
			$file->key = $file->filePath . $file->keyName . "\t" . $file->targetIe . "\t" . $file->media;
		} else if($file->fileExtension == 'js') {
			$file->key = $file->filePath . $file->keyName . "\t" . $file->targetIe;
		}
		return $file;
	}

	function unloadFile($fileName, $targetIe = '', $media = 'all') {
		$file = $this->getFileInfo($fileName, $targetIe, $media);
		if($file->fileExtension == 'css') {
			if(isset($this->cssMapIndex[$file->key])) {
				$index = $this->cssMapIndex[$file->key];
				unset($this->cssMap[$index][$file->key], $this->cssMapIndex[$file->key]);
			}
		} else {
			if(isset($this->jsHeadMapIndex[$file->key])) {
				$index = $this->jsHeadMapIndex[$file->key];
				unset($this->jsHeadMap[$index][$file->key], $this->jsHeadMapIndex[$file->key]);
			}
			if(isset($this->jsBodyMapIndex[$file->key])) {
				$index = $this->jsBodyMapIndex[$file->key];
				unset($this->jsBodyMap[$index][$file->key], $this->jsBodyMapIndex[$file->key]);
			}
		}
	}

	function unloadAllFiles($type = 'all') {
		if($type == 'css' || $type == 'all') {
			$this->cssMap = array();
			$this->cssMapIndex = array();
		}
		if($type == 'js' || $type == 'all') {
			$this->jsHeadMap = array();
			$this->jsBodyMap = array();
			$this->jsHeadMapIndex = array();
			$this->jsBodyMapIndex = array();
		}
	}

	function getCssFileList() {
		$map = &$this->cssMap;
		$mapIndex = &$this->cssMapIndex;
		$this->_sortMap($map, $mapIndex);
		$result = array();
		foreach($map as $indexedMap) {
			foreach($indexedMap as $file) {
				$noneCache = (is_readable($file->cdnPath . '/' . $file->fileName)) ? '?' . date('YmdHis', filemtime($file->cdnPath . '/' . $file->fileName)) : '';
				$fullFilePath = $file->filePath . '/' . $file->fileName . $noneCache;
								$result[] = array('file' => $fullFilePath, 'media' => $file->media, 'targetie' => $file->targetIe);
			}
		}
		return $result;
	}

	function getJsFileList($type = 'head') {
		if($type == 'head') {
			$map = &$this->jsHeadMap;
			$mapIndex = &$this->jsHeadMapIndex;
		} else {
			$map = &$this->jsBodyMap;
			$mapIndex = &$this->jsBodyMapIndex;
		}
		$this->_sortMap($map, $mapIndex);
		$result = array();
		foreach($map as $indexedMap) {
			foreach($indexedMap as $file) {
				$noneCache = (is_readable($file->cdnPath . '/' . $file->fileName)) ? '?' . date('YmdHis', filemtime($file->cdnPath . '/' . $file->fileName)) : '';
				$fullFilePath = $file->filePath . '/' . $file->fileName . $noneCache;
				$result[] = array('file' => $fullFilePath, 'targetie' => $file->targetIe);
			}
		}
		return $result;
	}

	function _sortMap(&$map, &$index) {
		ksort($map);
	}

	function _normalizeFilePath($path) {
		if(strpos($path, '://') === FALSE && $path{0} != '/' && $path{0} != '.') $path = './' . $path;
		elseif(!strncmp($path, '//', 2)) return preg_replace('#^//+#', '//', $path);
		$path = preg_replace('@/\./|(?<!:)\/\/@', '/', $path);
		while(strpos($path, '/../')) $path = preg_replace('/\/([^\/]+)\/\.\.\//s', '/', $path, 1);
		return $path;
	}

	function _getAbsFileUrl($path) {
		$path = $this->_normalizeFilePath($path);
		$script_path = getScriptPath();
		if(strpos($path, './') === 0) {
			if($script_path == '/' || $script_path == '\\') $path = '/' . substr($path, 2);
			else $path = $script_path . substr($path, 2);
		} else if(strpos($file, '../') === 0) {
			$path = $this->_normalizeFilePath($script_path . $path);
		}
		return $path;
	}

	function _arrangeCssIndex($dirName, &$file) {
		if($file->index !== 0) return;
		$dirName = str_replace('./', '', $dirName);
		$tmp = explode('/', $dirName);
		$cssSortList = array('common' => -100000, 'layouts' => -90000, 'modules' => -80000, 'widgets' => -70000, 'addons' => -60000);
		$file->index = $cssSortList[$tmp[0]];
	}
}
