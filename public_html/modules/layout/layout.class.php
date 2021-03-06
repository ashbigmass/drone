<?php
class layout extends ModuleObject
{
	function moduleInstall() {
		FileHandler::makeDir('./files/cache/layout');
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('layouts', 'site_srl')) return true;
		$files = FileHandler::readDir('./files/cache/layout');
		for($i=0,$c=count($files);$i<$c;$i++) {
			$filename = $files[$i];
			if(preg_match('/([0-9]+)\.html/i',$filename)) return true;
		}
		if(!$oDB->isColumnExists('layouts', 'layout_type')) return true;
		$args = new stdClass();
		$args->layout = '.';
		$output = executeQueryArray('layout.getLayoutDotList', $args);
		if($output->data && count($output->data) > 0) {
			foreach($output->data as $layout) {
				$layout_path = explode('.', $layout->layout);
				if(count($layout_path) != 2) continue;
				if(is_dir(sprintf(_XE_PATH_ . 'themes/%s/layouts/%s', $layout_path[0], $layout_path[1]))) return true;
			}
		}
		return false;
	}

	function moduleUpdate() {
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('layouts', 'site_srl')) $oDB->addColumn('layouts','site_srl','number',11,0,true);
		$oLayoutModel = getModel('layout');
		$files = FileHandler::readDir('./files/cache/layout');
		for($i=0,$c=count($files);$i<$c;$i++) {
			$filename = $files[$i];
			if(!preg_match('/([0-9]+)\.html/i',$filename,$match)) continue;
			$layout_srl = $match[1];
			if(!$layout_srl) continue;
			$path = $oLayoutModel->getUserLayoutPath($layout_srl);
			if(!is_dir($path)) FileHandler::makeDir($path);
			FileHandler::copyFile('./files/cache/layout/'.$filename, $path.'layout.html');
			@unlink('./files/cache/layout/'.$filename);
		}
		if(!$oDB->isColumnExists('layouts', 'layout_type')) $oDB->addColumn('layouts','layout_type','char',1,'P',true);
		$args->layout = '.';
		$output = executeQueryArray('layout.getLayoutDotList', $args);
		if($output->data && count($output->data) > 0) {
			foreach($output->data as $layout) {
				$layout_path = explode('.', $layout->layout);
				if(count($layout_path) != 2) continue;
				if(is_dir(sprintf(_XE_PATH_ . 'themes/%s/layouts/%s', $layout_path[0], $layout_path[1]))) {
					$args->layout = implode('|@|', $layout_path);
					$args->layout_srl = $layout->layout_srl;
					$output = executeQuery('layout.updateLayout', $args);
				}
			}
		}
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
		$path = './files/cache/layout';
		if(!is_dir($path)) {
			FileHandler::makeDir($path);
			return;
		}
	}
}
