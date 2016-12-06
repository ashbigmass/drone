<?php
class tag extends ModuleObject
{
	function moduleInstall() {
		$oModuleController = getController('module');
		$oDB = &DB::getInstance();
		$oDB->addIndex("tags","idx_tag", array("document_srl","tag"));
		$oModuleController->insertTrigger('document.insertDocument', 'tag', 'controller', 'triggerArrangeTag', 'before');
		$oModuleController->insertTrigger('document.insertDocument', 'tag', 'controller', 'triggerInsertTag', 'after');
		$oModuleController->insertTrigger('document.updateDocument', 'tag', 'controller', 'triggerArrangeTag', 'before');
		$oModuleController->insertTrigger('document.updateDocument', 'tag', 'controller', 'triggerInsertTag', 'after');
		$oModuleController->insertTrigger('document.deleteDocument', 'tag', 'controller', 'triggerDeleteTag', 'after');
		$oModuleController->insertTrigger('module.deleteModule', 'tag', 'controller', 'triggerDeleteModuleTags', 'after');
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		$oDB = &DB::getInstance();
		if(!$oModuleModel->getTrigger('document.insertDocument', 'tag', 'controller', 'triggerArrangeTag', 'before')) return true;
		if(!$oModuleModel->getTrigger('document.insertDocument', 'tag', 'controller', 'triggerInsertTag', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'tag', 'controller', 'triggerArrangeTag', 'before')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'tag', 'controller', 'triggerInsertTag', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'tag', 'controller', 'triggerDeleteTag', 'after')) return true;
		if(!$oModuleModel->getTrigger('module.deleteModule', 'tag', 'controller', 'triggerDeleteModuleTags', 'after')) return true;
		if(!$oDB->isIndexExists("tags","idx_tag")) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$oDB = &DB::getInstance();
		if(!$oModuleModel->getTrigger('document.insertDocument', 'tag', 'controller', 'triggerArrangeTag', 'before'))
			$oModuleController->insertTrigger('document.insertDocument', 'tag', 'controller', 'triggerArrangeTag', 'before');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'tag', 'controller', 'triggerInsertTag', 'after'))
			$oModuleController->insertTrigger('document.insertDocument', 'tag', 'controller', 'triggerInsertTag', 'after');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'tag', 'controller', 'triggerArrangeTag', 'before'))
			$oModuleController->insertTrigger('document.updateDocument', 'tag', 'controller', 'triggerArrangeTag', 'before');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'tag', 'controller', 'triggerInsertTag', 'after'))
			$oModuleController->insertTrigger('document.updateDocument', 'tag', 'controller', 'triggerInsertTag', 'after');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'tag', 'controller', 'triggerDeleteTag', 'after'))
			$oModuleController->insertTrigger('document.deleteDocument', 'tag', 'controller', 'triggerDeleteTag', 'after');
		if(!$oModuleModel->getTrigger('module.deleteModule', 'tag', 'controller', 'triggerDeleteModuleTags', 'after'))
			$oModuleController->insertTrigger('module.deleteModule', 'tag', 'controller', 'triggerDeleteModuleTags', 'after');
		if(!$oDB->isIndexExists("tags","idx_tag"))
			$oDB->addIndex("tags","idx_tag", array("document_srl","tag"));
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
