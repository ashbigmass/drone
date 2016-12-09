<?php
class krzipAdminView extends krzip
{
	function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile(lcfirst(str_replace('dispKrzipAdmin', '', $this->act)));
	}

	function dispKrzipAdminConfig() {
		$oKrzipModel = getModel('krzip');
		$module_config = $oKrzipModel->getConfig();
		Context::set('module_config', $module_config);
	}
}
