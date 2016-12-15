<?php
class messageAdminController extends message
{
	function init() {
	}

	function procMessageAdminInsertConfig() 	{
		$args = Context::gets('skin', 'mskin', 'colorset', 'mcolorset');
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('message',$args);
		if(!$output->toBool()) return $output;
		$this->setMessage('success_updated');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMessageAdminConfig');
		$this->setRedirectUrl($returnUrl);
	}
}
