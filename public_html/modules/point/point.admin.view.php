<?php
{
	function init() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		Context::set('config', $config);
		$security = new Security();
		$security->encodeHTML('config.point_name','config.level_icon');
		$security->encodeHTML('module_info..');
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispPointAdminConfig() {
		$level_icon_list = FileHandler::readDir("./modules/point/icons");
		Context::set('level_icon_list', $level_icon_list);
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups();
		$selected_group_list = array();
		if(count($group_list)) {
			foreach($group_list as $key => $val) $selected_group_list[$key] = $val;
		}
		Context::set('group_list', $selected_group_list);
		$security = new Security();
		$security->encodeHTML('group_list..title','group_list..description');
		$this->setTemplateFile('config');
	}

	function dispPointAdminModuleConfig() {
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'mid', 'browser_title');
		$mid_list = $oModuleModel->getMidList(null, $columnList);
		Context::set('mid_list', $mid_list);
		Context::set('module_config', $oModuleModel->getModulePartConfigs('point'));
		$security = new Security();
		$security->encodeHTML('mid_list..browser_title','mid_list..mid');
		$this->setTemplateFile('module_config');
	}

	function dispPointAdminActConfig() {
		$this->setTemplateFile('action_config');
	}

	function dispPointAdminPointList() {
		$oPointModel = getModel('point');
		$args = new stdClass();
		$args->list_count = 20;
		$args->page = Context::get('page');
		$oMemberModel = getModel('member');
		$memberConfig = $oMemberModel->getMemberConfig();
		Context::set('identifier', $memberConfig->identifier);
		$columnList = array('member.member_srl', 'member.user_id', 'member.email_address', 'member.nick_name', 'point.point');
		$output = $oPointModel->getMemberList($args, $columnList);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('member_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$oMemberModel = getModel('member');
		$this->group_list = $oMemberModel->getGroups();
		Context::set('group_list', $this->group_list);
		$security = new Security();
		$security->encodeHTML('group_list..title','group_list..description');
		$security->encodeHTML('member_list..');
		$this->setTemplateFile('member_list');
	}
}
