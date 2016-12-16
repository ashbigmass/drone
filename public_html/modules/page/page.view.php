<?php
class pageView extends page
{
	var $module_srl = 0;
	var $list_count = 20;
	var $page_count = 10;
	var $cache_file;
	var $interval;
	var $path;

	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
		switch($this->module_info->page_type) {
			case 'WIDGET' :
				{
					$this->cache_file = sprintf("%sfiles/cache/page/%d.%s.%s.cache.php", _XE_PATH_, $this->module_info->module_srl, Context::getLangType(), Context::getSslStatus());
					$this->interval = (int)($this->module_info->page_caching_interval);
					break;
				}
			case 'OUTSIDE' :
				{
					$this->cache_file = sprintf("%sfiles/cache/opage/%d.%s.cache.php", _XE_PATH_, $this->module_info->module_srl, Context::getSslStatus());
					$this->interval = (int)($this->module_info->page_caching_interval);
					$this->path = $this->module_info->path;
					break;
				}
		}
	}

	function dispPageIndex() {
		if($this->module_srl) Context::set('module_srl',$this->module_srl);
		$page_type_name = strtolower($this->module_info->page_type);
		$method = '_get' . ucfirst($page_type_name) . 'Content';
		if(method_exists($this, $method)) $page_content = $this->{$method}();
		else return new Object(-1, sprintf('%s method is not exists', $method));
		Context::set('module_info', $this->module_info);
		Context::set('page_content', $page_content);
		$this->setTemplateFile('content');
	}

	function _getWidgetContent() {
		if($this->interval>0) {
			if(!file_exists($this->cache_file)) $mtime = 0;
			else $mtime = filemtime($this->cache_file);
			if($mtime + $this->interval*60 > $_SERVER['REQUEST_TIME']) {
				$page_content = FileHandler::readFile($this->cache_file);
				$page_content = preg_replace('@<\!--#Meta:@', '<!--Meta:', $page_content);
			} else {
				$oWidgetController = getController('widget');
				$page_content = $oWidgetController->transWidgetCode($this->module_info->content);
				FileHandler::writeFile($this->cache_file, $page_content);
			}
		} else {
			if(file_exists($this->cache_file)) FileHandler::removeFile($this->cache_file);
			$page_content = $this->module_info->content;
		}
		return $page_content;
	}

	function _getArticleContent() {
		$oTemplate = &TemplateHandler::getInstance();
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument(0, true);
		if($this->module_info->document_srl) {
			$document_srl = $this->module_info->document_srl;
			$oDocument->setDocument($document_srl);
			Context::set('document_srl', $document_srl);
		}
		Context::set('oDocument', $oDocument);
		if ($this->module_info->skin) $templatePath = (sprintf($this->module_path.'skins/%s', $this->module_info->skin));
		else $templatePath = ($this->module_path.'skins/default');
		$page_content = $oTemplate->compile($templatePath, 'content');
		return $page_content;
	}

	function _getOutsideContent() {
		if($this->path) {
			if(preg_match("/^([a-z]+):\/\//i",$this->path)) $content = $this->getHtmlPage($this->path, $this->interval, $this->cache_file);
			else $content = $this->executeFile($this->path, $this->interval, $this->cache_file);
		}
		return $content;
	}

	function getHtmlPage($path, $caching_interval, $cache_file) {
		if($caching_interval > 0 && file_exists($cache_file) && filemtime($cache_file) + $caching_interval*60 > $_SERVER['REQUEST_TIME']) {
			$content = FileHandler::readFile($cache_file);
		} else {
			FileHandler::getRemoteFile($path, $cache_file);
			$content = FileHandler::readFile($cache_file);
		}
		$oPageController = getController('page');
		$content = $oPageController->replaceSrc($content, $path);
		$buff = new stdClass;
		$buff->content = $content;
		$buff = Context::convertEncoding($buff);
		$content = $buff->content;
		$title = $oPageController->getTitle($content);
		if($title) Context::setBrowserTitle($title);
		$head_script = $oPageController->getHeadScript($content);
		if($head_script) Context::addHtmlHeader($head_script);
		$body_script = $oPageController->getBodyScript($content);
		if(!$body_script) $body_script = $content;
		return $content;
	}

	function executeFile($target_file, $caching_interval, $cache_file) {
		if(!file_exists(FileHandler::getRealPath($target_file))) return;
		$tmp_path = explode('/',$cache_file);
		$filename = $tmp_path[count($tmp_path)-1];
		$filepath = preg_replace('/'.$filename."$/i","",$cache_file);
		$cache_file = FileHandler::getRealPath($cache_file);
		$level = ob_get_level();
		if($caching_interval <1 || !file_exists($cache_file) || filemtime($cache_file) + $caching_interval*60 <= $_SERVER['REQUEST_TIME'] || filemtime($cache_file)<filemtime($target_file)) {
			if(file_exists($cache_file)) FileHandler::removeFile($cache_file);
			ob_start();
			include(FileHandler::getRealPath($target_file));
			$content = ob_get_clean();
			$this->path = str_replace('\\', '/', realpath(dirname($target_file))) . '/';
			$content = preg_replace_callback('/(target=|src=|href=|url\()("|\')?([^"\'\)]+)("|\'\))?/is',array($this,'_replacePath'),$content);
			$content = preg_replace_callback('/(<!--%import\()(\")([^"]+)(\")/is',array($this,'_replacePath'),$content);
			FileHandler::writeFile($cache_file, $content);
			if(!file_exists($cache_file)) return;
			$oTemplate = &TemplateHandler::getInstance();
			$script = $oTemplate->compileDirect($filepath, $filename);
			FileHandler::writeFile($cache_file, $script);
		}
		$__Context = &$GLOBALS['__Context__'];
		$__Context->tpl_path = $filepath;
		ob_start();
		include($cache_file);
		$contents = '';
		while (ob_get_level() - $level > 0) {
			$contents .= ob_get_contents();
			ob_end_clean();
		}
		return $contents;
	}

	function _replacePath($matches) {
		$val = trim($matches[3]);
		if(strpos($val, '.') === FALSE || preg_match('@^((?:http|https|ftp|telnet|mms)://|(?:mailto|javascript):|[/#{])@i',$val)) {
				return $matches[0];
		} else if(strncasecmp('..', $val, 2) === 0) {
			$p = Context::pathToUrl($this->path);
			return sprintf("%s%s%s%s",$matches[1],$matches[2],$p.$val,$matches[4]);
		}
		if(strncasecmp('..', $val, 2) === 0) $val = substr($val,2);
		$p = Context::pathToUrl($this->path);
		$path = sprintf("%s%s%s%s",$matches[1],$matches[2],$p.$val,$matches[4]);
		return $path;
	}
}
