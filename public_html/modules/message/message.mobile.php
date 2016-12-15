<?php
require_once(_XE_PATH_.'modules/message/message.view.php');
class messageMobile extends messageView
{
	function init() {
	}

	function dispMessage() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('message');
		if(!is_object($config)) $config = new stdClass;
		if(!$config->mskin) $config->mskin = 'default';
		$template_path = sprintf('%sm.skins/%s', $this->module_path, $config->mskin);
		$oModuleModel = getModel('module');
		$member_config = $oModuleModel->getModuleConfig('member');
		Context::set('member_config', $member_config);
		$ssl_mode = false;
		if($member_config->enable_ssl == 'Y') if(strncasecmp('https://', Context::getRequestUri(), 8) === 0) $ssl_mode = true;
		Context::set('ssl_mode',$ssl_mode);
		Context::set('system_message', nl2br($this->getMessage()));
		Context::set('act', 'procMemberLogin');
		Context::set('mid', '');
		$this->setTemplatePath($template_path);
		$this->setTemplateFile('system_message');
	}
}
