<?php
class rss extends ModuleObject
{
	public $gzhandler_enable = false;

	function moduleInstall() {
		$oModuleController = getController('module');
		$oModuleController->insertActionForward('rss', 'view', 'rss');
		$oModuleController->insertActionForward('rss', 'view', 'atom');
		$oModuleController->insertTrigger('module.dispAdditionSetup', 'rss', 'view', 'triggerDispRssAdditionSetup', 'before');
		$oModuleController->insertTrigger('moduleHandler.proc', 'rss', 'controller', 'triggerRssUrlInsert', 'after');
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getActionForward('atom')) return true;
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'rss', 'view', 'triggerDispRssAdditionSetup', 'before')) return true;
		if(!$oModuleModel->getTrigger('moduleHandler.proc', 'rss', 'controller', 'triggerRssUrlInsert', 'after')) return true;
		if($oModuleModel->getTrigger('display', 'rss', 'controller', 'triggerRssUrlInsert', 'before')) return true;
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'rss', 'controller', 'triggerCopyModule', 'after')) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getActionForward('atom'))
			$oModuleController->insertActionForward('rss', 'view', 'atom');
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'rss', 'view', 'triggerDispRssAdditionSetup', 'before'))
			$oModuleController->insertTrigger('module.dispAdditionSetup', 'rss', 'view', 'triggerDispRssAdditionSetup', 'before');
		if(!$oModuleModel->getTrigger('moduleHandler.proc', 'rss', 'controller', 'triggerRssUrlInsert', 'after'))
			$oModuleController->insertTrigger('moduleHandler.proc', 'rss', 'controller', 'triggerRssUrlInsert', 'after');
		if($oModuleModel->getTrigger('display', 'rss', 'controller', 'triggerRssUrlInsert', 'before'))
			$oModuleController->deleteTrigger('display', 'rss', 'controller', 'triggerRssUrlInsert', 'before');
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'rss', 'controller', 'triggerCopyModule', 'after'))
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'rss', 'controller', 'triggerCopyModule', 'after');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
