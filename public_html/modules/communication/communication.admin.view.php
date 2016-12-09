<?php
class communicationAdminView extends communication
{
	function init() {
	}

	function dispCommunicationAdminConfig() {
		$oEditorModel = getModel('editor');
		$oModuleModel = getModel('module');
		$oLayoutModel = getModel('layout');
		$oCommunicationModel = getModel('communication');
		Context::set('communication_config', $oCommunicationModel->getConfig());
		Context::set('layout_list', $oLayoutModel->getLayoutList());
		Context::set('editor_skin_list', $oEditorModel->getEditorSkinList());
		Context::set('communication_skin_list', $oModuleModel->getSkins($this->module_path));
		Context::set('communication_mobile_skin_list', $oModuleModel->getSkins($this->module_path, 'm.skins'));
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mlayout_list = $oLayoutModel->getLayoutList(0, 'M');
		Context::set('mlayout_list', $mlayout_list);
		$security = new Security();
		$security->encodeHTML('communication_config..');
		$security->encodeHTML('layout_list..');
		$security->encodeHTML('editor_skin_list..');
		$security->encodeHTML('communication_skin_list..title');
		$security->encodeHTML('communication_mobile_skin_list..title');
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups($this->site_srl);
		Context::set('group_list', $group_list);
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile('index');
	}
}
