<?php
define('__XEFM_NAME__', 'beluxe');
define('__XEFM_PATH__', _XE_PATH_.'modules/' . __XEFM_NAME__ . '/');
define('__XEFM_ORDER__', 'list_order,regdate,update_order,readed_count,voted_count,blamed_count,comment_count,title,random');

if (file_exists(__XEFM_PATH__ . 'classes.item.php')) require_once (__XEFM_PATH__ . 'classes.item.php');

class beluxe extends ModuleObject
{
	function moduleInstall() {
		$cmModule = &getModel('module');
		$ccModule = &getController('module');
		if (file_exists(__XEFM_PATH__ . 'schemas/file_downloaded_log.xml')) {
			if (!$cmModule->getTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerBeforeDownloadFile', 'before')) $ccModule->insertTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerBeforeDownloadFile', 'before');
			if (!$cmModule->getTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerDownloadFile', 'after')) $ccModule->insertTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerDownloadFile', 'after');
		}
		if(!$cmModule->getTrigger('menu.getModuleListInSitemap', 'beluxe', 'model', 'triggerModuleListInSitemap', 'after')) $ccModule->insertTrigger('menu.getModuleListInSitemap', 'beluxe', 'model', 'triggerModuleListInSitemap', 'after');
		return new Object();
	}

	function checkUpdate() {
		$cmModule = &getModel('module');
		if (file_exists(__XEFM_PATH__ . 'schemas/file_downloaded_log.xml')) {
			if (!$cmModule->getTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerBeforeDownloadFile', 'before')) return TRUE;
			if (!$cmModule->getTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerDownloadFile', 'after')) return TRUE;
		}
		if(!$cmModule->getTrigger('menu.getModuleListInSitemap', 'beluxe', 'model', 'triggerModuleListInSitemap', 'after')) return TRUE;
		return FALSE;
	}

	function moduleUpdate() {
		$this->moduleInstall();
		return new Object(0, 'success_updated');
	}

	function moduleUninstall() {
		$ccModule = &getController('module');
		$ccModule->deleteTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerBeforeDownloadFile', 'before');
		$ccModule->deleteTrigger('file.downloadFile', 'beluxe', 'controller', 'triggerDownloadFile', 'after');
		$ccModule->deleteTrigger('menu.getModuleListInSitemap', 'beluxe', 'model', 'triggerModuleListInSitemap', 'after');
		$this->recompileCache();
		return new Object();
	}

	function recompileCache() {
	}
}
