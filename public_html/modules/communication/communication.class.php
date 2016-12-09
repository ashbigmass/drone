<?php
class communication extends ModuleObject
{
	function moduleInstall() {
		FileHandler::makeDir('./files/member_extra_info/new_message_flags');
		return new Object();
	}

	function checkUpdate() {
		if(!is_dir("./files/member_extra_info/new_message_flags")) return TRUE;
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('message');
		if($config->skin) {
			$config_parse = explode('.', $config->skin);
			if(count($config_parse) > 1) {
				$template_path = sprintf('./themes/%s/modules/communication/', $config_parse[0]);
				if(is_dir($template_path)) return TRUE;
			}
		}
		return FALSE;
	}

	function moduleUpdate() {
		if(!is_dir("./files/member_extra_info/new_message_flags")) FileHandler::makeDir('./files/member_extra_info/new_message_flags');
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('message');
		if(!is_object($config)) $config = new stdClass();
		if($config->skin) {
			$config_parse = explode('.', $config->skin);
			if(count($config_parse) > 1) {
				$template_path = sprintf('./themes/%s/modules/communication/', $config_parse[0]);
				if(is_dir($template_path)) {
					$config->skin = implode('|@|', $config_parse);
					$oModuleController = getController('module');
					$oModuleController->updateModuleConfig('communication', $config);
				}
			}
		}
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
