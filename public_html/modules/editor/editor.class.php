<?php
class editor extends ModuleObject
{
	function moduleInstall() {
		$oModuleController = getController('module');
		$oEditorController = getAdminController('editor');
		$oEditorController->insertComponent('colorpicker_text',true);
		$oEditorController->insertComponent('colorpicker_bg',true);
		$oEditorController->insertComponent('emoticon',true);
		$oEditorController->insertComponent('url_link',true);
		$oEditorController->insertComponent('image_link',true);
		$oEditorController->insertComponent('multimedia_link',true);
		$oEditorController->insertComponent('quotation',true);
		$oEditorController->insertComponent('table_maker',true);
		$oEditorController->insertComponent('poll_maker',true);
		$oEditorController->insertComponent('image_gallery',true);
		FileHandler::makeDir('./files/cache/editor');
		$oModuleController->insertTrigger('document.insertDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after');
		$oModuleController->insertTrigger('document.updateDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after');
		$oModuleController->insertTrigger('module.dispAdditionSetup', 'editor', 'view', 'triggerDispEditorAdditionSetup', 'before');
		$oModuleController->insertTrigger('display', 'editor', 'controller', 'triggerEditorComponentCompile', 'before');
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists("editor_autosave","module_srl")) return true;
		if(!$oDB->isIndexExists("editor_autosave","idx_module_srl")) return true;
		if(!$oModuleModel->getTrigger('document.insertDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after')) return true;
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'editor', 'view', 'triggerDispEditorAdditionSetup', 'before')) return true;
		if(!$oModuleModel->getTrigger('display', 'editor', 'controller', 'triggerEditorComponentCompile', 'before')) return true;
		if($oModuleModel->getTrigger('file.getIsPermitted', 'editor', 'controller', 'triggerSrlSetting', 'before')) return true;
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'editor', 'controller', 'triggerCopyModule', 'after')) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists("editor_autosave","module_srl")) $oDB->addColumn("editor_autosave","module_srl","number",11);
		if(!$oDB->isIndexExists("editor_autosave","idx_module_srl")) $oDB->addIndex("editor_autosave","idx_module_srl", "module_srl");
		if(!$oModuleModel->getTrigger('document.insertDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after'))
			$oModuleController->insertTrigger('document.insertDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after'))
			$oModuleController->insertTrigger('document.updateDocument', 'editor', 'controller', 'triggerDeleteSavedDoc', 'after');
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'editor', 'view', 'triggerDispEditorAdditionSetup', 'before'))
			$oModuleController->insertTrigger('module.dispAdditionSetup', 'editor', 'view', 'triggerDispEditorAdditionSetup', 'before');
		if(!$oModuleModel->getTrigger('display', 'editor', 'controller', 'triggerEditorComponentCompile', 'before'))
			$oModuleController->insertTrigger('display', 'editor', 'controller', 'triggerEditorComponentCompile', 'before');
		if($oModuleModel->getTrigger('file.getIsPermitted', 'editor', 'controller', 'triggerSrlSetting', 'before'))
			$oModuleController->deleteTrigger('file.getIsPermitted', 'editor', 'controller', 'triggerSrlSetting', 'before');
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'editor', 'controller', 'triggerCopyModule', 'after')) {
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'editor', 'controller', 'triggerCopyModule', 'after');
		}
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
