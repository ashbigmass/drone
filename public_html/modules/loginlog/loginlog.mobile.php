<?php
require_once(_XE_PATH_.'modules/loginlog/loginlog.view.php');

class loginlogMobile extends loginlogView
{
	function init() {
		$oLoginlogModel = getModel('loginlog');
		$loginlog_config = $oLoginlogModel->getModuleConfig();
		Context::set('loginlog_config', $loginlog_config);
		$mskin = $loginlog_config->design->mskin;
		$template_path = sprintf('%sm.skins/%s',$this->module_path, $mskin);
		if(!is_dir($template_path)||!$mskin) {
			$mskin = 'default';
			$template_path = sprintf('%sm.skins/%s', $this->module_path, $mskin);
		} else {
			$template_path = sprintf('%sm.skins/%s', $this->module_path, $mskin);
		}
		$this->setTemplatePath($template_path);
	}

	function dispLoginlogHistories() {
		parent::dispLoginlogHistories();
	}
}
