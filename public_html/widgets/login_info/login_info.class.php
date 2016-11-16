<?php
class login_info extends WidgetHandler {
	function proc($args) {
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
		Context::set('colorset', $args->colorset);
		if(Context::get('is_logged')) $tpl_file = 'login_info';
		else $tpl_file = 'login_form';
		$oModuleModel = getModel('module');
		$this->member_config = $oModuleModel->getModuleConfig('member');
		Context::set('member_config', $this->member_config);
		$ssl_mode = false;
		$useSsl = Context::getSslStatus();
		if($useSsl != 'none') if(strncasecmp('https://', Context::getRequestUri(), 8) === 0) $ssl_mode = true;
		Context::set('ssl_mode',$ssl_mode);
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}
}
