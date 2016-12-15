<?php
class messageAdminView extends message
{
	function init() {
	}

	function dispMessageAdminConfig() {
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getskins($this->module_path);
		Context::set('skin_list', $skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		$config = $oModuleModel->getModuleConfig('message');
		Context::set('config',$config);
		$this->setTemplatePath($this->module_path.'tpl');
		$security = new Security();
		$security->encodeHTML('skin_list..title', 'mskin_list..title');
		$this->setTemplateFile('config');
	}
}
