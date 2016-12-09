<?php
class fileView extends file
{
	function init() {
	}

	function triggerDispFileAdditionSetup(&$obj) {
		$current_module_srl = Context::get('module_srl');
		$current_module_srls = Context::get('module_srls');
		if(!$current_module_srl && !$current_module_srls) {
			$current_module_info = Context::get('current_module_info');
			$current_module_srl = $current_module_info->module_srl;
			if(!$current_module_srl) return new Object();
		}
		$oFileModel = getModel('file');
		$file_config = $oFileModel->getFileModuleConfig($current_module_srl);
		Context::set('file_config', $file_config);
		$oMemberModel = getModel('member');
		$site_module_info = Context::get('site_module_info');
		$group_list = $oMemberModel->getGroups($site_module_info->site_srl);
		Context::set('group_list', $group_list);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'file_module_config');
		$obj .= $tpl;
		return new Object();
	}
}
