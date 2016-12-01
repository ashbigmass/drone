<?php
class boardAdminModel extends board

	function init() {
	}

	public function getBoardAdminSimpleSetup($moduleSrl, $setupUrl) {
		if(!$moduleSrl)return;
		Context::set('module_srl', $moduleSrl);
		$oModuleModel = getModel('module');
		$moduleInfo = $oModuleModel->getModuleInfoByModuleSrl($moduleSrl);
		$moduleInfo->use_status = explode('|@|', $moduleInfo->use_status);
		if($moduleInfo) Context::set('module_info', $moduleInfo);
		$oDocumentModel = getModel('document');
		$documentStatusList = $oDocumentModel->getStatusNameList();
		Context::set('document_status_list', $documentStatusList);
		foreach($this->order_target AS $key) $order_target[$key] = Context::getLang($key);
		$order_target['list_order'] = Context::getLang('document_srl');
		$order_target['update_order'] = Context::getLang('last_update');
		Context::set('order_target', $order_target);
		$oAdmin = getClass('admin');
		Context::set('setupUrl', $setupUrl);
		$admin_member = $oModuleModel->getAdminId($moduleSrl);
		Context::set('admin_member', $admin_member);
		$oTemplate = &TemplateHandler::getInstance();
		$html = $oTemplate->compile($this->module_path.'tpl/', 'board_setup_basic');
		return $html;
	}
}
