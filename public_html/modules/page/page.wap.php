<?php
class pageWap extends page
{
	function procWAP(&$oMobile) {
		if(!$this->grant->access) return $oMobile->setContent(Context::getLang('msg_not_permitted'));
		$oWidgetController = getController('widget');
		$content = $oWidgetController->transWidgetCode($this->module_info->content);
		$oMobile->setContent($content);
	}
}
