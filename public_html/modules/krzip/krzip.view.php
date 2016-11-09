<?php
class krzipView extends krzip {
	function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	function dispKrzipSearchForm($api_handler) {
		$oKrzipModel = getModel('krzip');
		$module_config = $oKrzipModel->getConfig();
		$module_config->sequence_id = ++self::$sequence_id;
		if(!isset($api_handler) || !isset(self::$api_list[$api_handler])) $api_handler = $module_config->api_handler;
		Context::set('template_config', $module_config);
		$this->setTemplateFile('searchForm.' . self::$api_list[$api_handler]);
		$this->setLayoutPath($this->common_path);
		$this->setLayoutFile('popup_layout');
	}
}
