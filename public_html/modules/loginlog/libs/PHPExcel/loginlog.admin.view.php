<?php
class loginlogAdminView extends loginlog
{
	public function init() 	{
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	public function dispLoginlogAdminList() {
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		if(!isset($config->listSetting) || !is_array($config->listSetting) || count($config->listSetting) < 1) {
			$config->listSetting = array(
				'member.nick_name',
				'member.user_id',
				'member.email_address',
				'loginlog.ipaddress',
				'loginlog.regdate',
				'loginlog.platform',
				'loginlog.browser'
			);
		}
		$columnList = $config->listSetting;
		$columnList[] = 'loginlog.is_succeed';
		$columnList[] = 'loginlog.log_srl';
		$columnList[] = 'loginlog.member_srl';
		$columnList[] = 'loginlog.platform';
		$columnList[] = 'loginlog.browser';
		Context::set('loginlog_config', $config);
		$args = new stdClass;
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 10;
		$args->sort_index = 'loginlog.regdate';
		$args->order_type = 'desc';
		$args->daterange_start = (int)(str_replace('-', '', Context::get('daterange_start')) . '000000');
		$args->daterange_end = (int)(str_replace('-', '', Context::get('daterange_end')) . '235959');
		$args->isSucceed  = Context::get('isSucceed');
		$ynList = array('Y' => 1, 'N' => 1);
		if(!isset($ynList[$args->isSucceed])) unset($args->isSucceed);
		$search_keyword = Context::get('search_keyword');
		$search_target = trim(Context::get('search_target'));
		if($search_keyword) {
			switch($search_target) {
				case 'member_srl':
					$args->member_srl = (int)$search_keyword;
					break;
				case 'user_id':
					$args->s_user_id = $search_keyword;
					array_push($columnList, 'member.user_id');
					break;
				case 'user_name':
					$args->s_user_name = $search_keyword;
					array_push($columnList, 'member.user_name');
					break;
				case 'nick_name':
					$args->s_nick_name = $search_keyword;
					array_push($columnList, 'member.nick_name');
					break;
				case 'ipaddress':
					$args->s_ipaddress = $search_keyword;
					array_push($columnList, 'loginlog.ipaddress');
					break;
				case 'os':
					$args->s_os = $search_keyword;
					break;
				case 'browser':
					$args->s_browser = $search_keyword;
					break;
			}
		}
		$columnList = array_unique($columnList);
		$output = executeQueryArray('loginlog.getLoginlogListWithinMember', $args, $columnList);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('log_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$this->setTemplateFile('list');
	}

	public function dispLoginlogAdminSetting() {
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups();
		Context::set('group_list', $group_list);
		Context::set('config', $config);
		$this->setTemplateFile('setting');
	}

	public function dispLoginlogAdminArrange() {
		$this->setTemplateFile('arrange');
	}

	public function dispLoginlogAdminDesign() {
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		Context::set('config', $config);
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list', $skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, 'm.skins');
		Context::set('mskin_list', $mskin_list);
		$this->setTemplateFile('design');
	}
}
