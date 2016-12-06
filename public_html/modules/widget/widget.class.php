<?php
class widget extends ModuleObject
{
	function moduleInstall() {
		FileHandler::makeDir('./files/cache/widget');
		FileHandler::makeDir('./files/cache/widget_cache');
		$oModuleController = getController('module');
		$oModuleController->insertTrigger('display', 'widget', 'controller', 'triggerWidgetCompile', 'before');
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('display', 'widget', 'controller', 'triggerWidgetCompile', 'before')) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('display', 'widget', 'controller', 'triggerWidgetCompile', 'before'))
			$oModuleController->insertTrigger('display', 'widget', 'controller', 'triggerWidgetCompile', 'before');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
