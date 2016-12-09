<?php
class loginlogController extends loginlog
{
	public function init() {
	}

	public function deleteLogsByCron($type = 'ALL', $period = 1) {
		$args = new stdClass;
		switch($type) {
			case 'ALL':
			break;
			case 'DAILY':
				$str = '-'. $period. ' day';
				$args->expire_date = date('Ymd000000', strtotime($str));
			break;
			case 'WEEKLY':
				$str = '-'. $period. ' week';
				$args->expire_date = date('Ymd000000', strtotime($str));
			break;
			case 'MONTHLY':
				$str = '-'. $period. ' month';
				$args->expire_date = date('Ymd000000', strtotime($str));
			break;
			case 'YEARLY':
				$str = '-'. $period. ' year';
				$args->expire_date = date('Y00000000', strtotime($str));
			break;
		}
		executeQuery('loginlog.initLoginlogs', $args);
	}

	public function deleteLogsByCronUsingDate($start_date, $end_date) {
		$args = new stdClass;
		$args->start_date = $start_date;
		$args->expire_date = $end_date;
		executeQuery('loginlog.initLoginlogs', $args);
	}

	public function triggerBeforeLogin(&$obj) {
		if(!$obj->user_id) return new Object();
		if(!$obj->password) return new Object();
		$output = executeQuery('loginlog.getMemberPassword', $obj);
		if(!$output->data) return new Object();
		$member_srl = $output->data->member_srl;
		$password = $output->data->password;
		$oMemberModel = getModel('member');
		if($oMemberModel->isValidPassword($password, $obj->password)) return new Object();
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		if(is_array($config->target_group) && count($config->target_group) > 0) {
			$isTargetGroup  = FALSE;
			$group_list = $oMemberModel->getMemberGroups($member_srl);
			foreach($group_list as $group_srl => &$group_title) {
				if(in_array($group_srl, $config->target_group)) {
					$isTargetGroup = TRUE;
					break;
				}
			}
			if(!$isTargetGroup) return new Object();
		}
		require _XE_PATH_ . 'modules/loginlog/libs/Browser.php';
		$browser = new Browser();
		$browserName = $browser->getBrowser();
		$browserVersion = $browser->getVersion();
		$platform = $browser->getPlatform();
		$user_id = $output->data->user_id;
		$email_address = $output->data->email_address;
		$log_info = new stdClass;
		$log_info->member_srl = $member_srl;
		$log_info->platform = $platform;
		$log_info->browser = $browserName . ' ' . $browserVersion;
		$log_info->user_id = $user_id;
		$log_info->email_address = $email_address;
		$this->insertLoginlog($log_info, false);
		return new Object();
	}

	public function triggerAfterLogin(&$member_info) {
		if(!$member_info->member_srl) return new Object();
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		if($config->admin_user_log != 'Y' && $member_info->is_admin == 'Y') return new Object();
		if(is_array($config->target_group) && count($config->target_group) > 0) {
			$isTargetGroup  = FALSE;
			$oMemberModel = getModel('member');
			$group_list = $oMemberModel->getMemberGroups($member_info->member_srl);
			foreach($group_list as $group_srl => &$group_title) {
				if(in_array($group_srl, $config->target_group)) {
					$isTargetGroup = TRUE;
					break;
				}
			}
			if(!$isTargetGroup) return new Object();
		}
		require _XE_PATH_ . 'modules/loginlog/libs/Browser.php';
		$browser = new Browser();
		$browserName = $browser->getBrowser();
		$browserVersion = $browser->getVersion();
		$platform = $browser->getPlatform();
		$log_info = new stdClass;
		$log_info->member_srl = $member_info->member_srl;
		$log_info->platform = $platform;
		$log_info->browser = $browserName . ' ' . $browserVersion;
		$log_info->user_id = $member_info->user_id;
		$log_info->email_address = $member_info->email_address;
		$this->insertLoginlog($log_info);
		return new Object();
	}

	public function triggerDeleteMember(&$obj) {
		if(!$obj->member_srl) return new Object();
		$oModel = getModel('loginlog');
		$config = $oModel->getModuleConfig();
		if($config->delete_logs != 'Y') return new Object();
		executeQuery('loginlog.deleteMemberLoginlogs', $obj);
		return new Object();
	}

	public function triggerBeforeModuleInit(&$obj) {
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new Object();
		$oMemberController = getController('member');
		$oMemberController->addMemberMenu('dispLoginlogHistories', 'cmd_view_loginlog');
	}

	public function triggerBeforeModuleProc() {
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new Object();
		if($this->act == 'getMemberMenu' && $logged_info->is_admin == 'Y') {
			$oMemberController = getController('member');
			$member_srl = Context::get('target_srl');
			$url = getUrl('', 'module', 'admin', 'act', 'dispLoginlogAdminList', 'search_target', 'member_srl', 'search_keyword', $member_srl);
			$oMemberController->addMemberPopupMenu($url, Context::getLang('cmd_trace_loginlog'), '', '_blank');
		}
	}

	public function insertLoginlog($log_info, $isSucceed = true) {
		$args = new stdClass;
		$args->log_srl = getNextSequence();
		$args->member_srl = &$log_info->member_srl;
		$args->is_succeed = $isSucceed ? 'Y' : 'N';
		$args->regdate = date('YmdHis');
		$args->platform = &$log_info->platform;
		$args->browser = &$log_info->browser;
		$args->user_id = &$log_info->user_id;
		$args->email_address = &$log_info->email_address;
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) $args->ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
		return executeQuery('loginlog.insertLoginlog', $args);
	}
}
