<?php
class loginlogModel extends loginlog
{
	public function init() {
	}

	public function getModuleConfig() {
		static $config;
		if(!isset($config)) {
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('loginlog');
			if(!isset($config)) $config = new stdClass;
			if(!$config->admin_user_log) $config->admin_user_log = 'N';
			if(!isset($config->target_group)) $config->target_group = array();
			if(!is_array($config->listSetting)) {
				if($config->listSetting) $config->listSetting = explode('|@|', $config->listSetting);
				else $config->listSetting = array();
			}
			if(!isset($config->exportConfig)) $config->exportConfig = new stdClass;
			if(!$config->exportConfig->listCount) $config->exportConfig->listCount = 100;
			if(!$config->exportConfig->pageCount) $config->exportConfig->pageCount = 10;
			if(!$config->exportConfig->includeGroup || !is_array($config->exportConfig->includeGroup)) {
				if($config->exportConfig->includeGroup) $config->exportConfig->includeGroup = explode('|@|', $config->exportConfig->includeGroup);
				else $config->exportConfig->includeGroup = array();
			}
			if(!is_array($config->exportConfig->excludeGroup)) {
				if($config->exportConfig->excludeGroup) $config->exportConfig->excludeGroup = explode('|@|', $config->exportConfig->excludeGroup);
				else $config->exportConfig->excludeGroup = array();
			}
			if(!isset($config->design)) $config->design = new stdClass;
		}
		return $config;
	}

	public function getLoginlogListByMemberSrl($memberSrl, $searchObj = NULL, $columnList = array()) {
		$args = new stdClass;
		if($searchObj != NULL) {
			$args->daterange_start = $searchObj->daterange_start;
			$args->daterange_end = $searchObj->daterange_end;
			$args->s_browser = $searchObj->s_browser;
			$args->s_platform = $searchObj->s_platform;
		}
		$args->member_srl = $memberSrl;
		return executeQueryArray('loginlog.getLoginlogListByMemberSrl', $args, $columnList);
	}
}