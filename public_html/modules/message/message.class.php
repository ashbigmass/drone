<?php
class message extends ModuleObject
{
	function moduleInstall() {
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('message');
		if($config->skin) {
			$config_parse = explode('.', $config->skin);
			if (count($config_parse) > 1) {
				$template_path = sprintf('./themes/%s/modules/message/', $config_parse[0]);
				if(is_dir($template_path)) return true;
			}
		}
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('message');
		if($config->skin) {
			$config_parse = explode('.', $config->skin);
			if (count($config_parse) > 1) {
				$xec_path = './themes/%s/modules/message/';
				$template_path = sprintf($xec_path, $config_parse[0]);
				if(is_dir($template_path)) {
					$config->skin = implode('|@|', $config_parse);
					$oModuleController = getController('module');
					$oModuleController->updateModuleConfig('message', $config);
				}
			}
		}
		return new Object();
	}
}
