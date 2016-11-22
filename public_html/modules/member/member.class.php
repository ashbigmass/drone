<?php
class member extends ModuleObject {
	var $useSha1 = false;

	function member() {
		if(!Context::isInstalled()) return;
		$oModuleModel = getModel('module');
		$member_config = $oModuleModel->getModuleConfig('member');
		if(!Context::isExistsSSLAction('dispMemberModifyPassword') && Context::getSslStatus() == 'optional') {
			$ssl_actions = array('dispMemberModifyPassword', 'dispMemberSignUpForm', 'dispMemberModifyInfo', 'dispMemberModifyEmailAddress', 'dispMemberGetTempPassword', 'dispMemberResendAuthMail', 'dispMemberLoginForm', 'dispMemberFindAccount', 'dispMemberLeave', 'procMemberLogin', 'procMemberModifyPassword', 'procMemberInsert', 'procMemberModifyInfo', 'procMemberFindAccount', 'procMemberModifyEmailAddress', 'procMemberResendAuthMail', 'procMemberLeave'/*, 'getMemberMenu'*/, 'procMemberFindAccountByQuestion');
			Context::addSSLActions($ssl_actions);
		}
	}

	function moduleInstall() {
		$oModuleController = getController('module');
		$oDB = &DB::getInstance();
		$oDB->addIndex("member_group","idx_site_title", array("site_srl","title"),true);
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if(empty($config)) {
			$isNotInstall = true;
			$config = new stdClass;
		}
		$config->enable_join = 'Y';
		$config->enable_openid = 'N';
		if(!$config->enable_auth_mail) $config->enable_auth_mail = 'N';
		if(!$config->image_name) $config->image_name = 'Y';
		if(!$config->image_mark) $config->image_mark = 'Y';
		if(!$config->profile_image) $config->profile_image = 'Y';
		if(!$config->image_name_max_width) $config->image_name_max_width = '90';
		if(!$config->image_name_max_height) $config->image_name_max_height = '20';
		if(!$config->image_mark_max_width) $config->image_mark_max_width = '20';
		if(!$config->image_mark_max_height) $config->image_mark_max_height = '20';
		if(!$config->profile_image_max_width) $config->profile_image_max_width = '90';
		if(!$config->profile_image_max_height) $config->profile_image_max_height = '90';
		if($config->group_image_mark!='Y') $config->group_image_mark = 'N';
		if(!$config->password_strength) $config->password_strength = 'normal';
		if(!$config->password_hashing_algorithm) {
			$oPassword = new Password();
			$config->password_hashing_algorithm = $oPassword->getBestAlgorithm();
		}
		if(!$config->password_hashing_work_factor) $config->password_hashing_work_factor = 8;
		if(!$config->password_hashing_auto_upgrade) $config->password_hashing_auto_upgrade = 'Y';
		global $lang;
		$oMemberModel = getModel('member');
		$oMemberController = getController('member');
		$oMemberAdminController = getAdminController('member');
		if(!$config->signupForm || !is_array($config->signupForm)) {
			$identifier = $isNotInstall ? 'email_address' : 'user_id';
			$config->signupForm = $oMemberAdminController->createSignupForm($identifier);
			$config->identifier = $identifier;
			FileHandler::makeDir('./files/ruleset');
			$oMemberAdminController->_createSignupRuleset($config->signupForm);
			$oMemberAdminController->_createLoginRuleset($config->identifier);
			$oMemberAdminController->_createFindAccountByQuestion($config->identifier);
		}
		$oModuleController->insertModuleConfig('member',$config);
		$groups = $oMemberModel->getGroups();
		if(!count($groups)) {
			$group_args = new stdClass;
			$group_args->title = Context::getLang('admin_group');
			$group_args->is_default = 'N';
			$group_args->is_admin = 'Y';
			$output = $oMemberAdminController->insertGroup($group_args);
			$group_args = new stdClass;
			$group_args->title = Context::getLang('default_group_1');
			$group_args->is_default = 'Y';
			$group_args->is_admin = 'N';
			$output = $oMemberAdminController->insertGroup($group_args);
			$group_args = new stdClass;
			$group_args->title = Context::getLang('default_group_2');
			$group_args->is_default = 'N';
			$group_args->is_admin = 'N';
			$oMemberAdminController->insertGroup($group_args);
		}
		$admin_args = new stdClass;
		$admin_args->is_admin = 'Y';
		$output = executeQuery('member.getMemberList', $admin_args);
		if(!$output->data) {
			$admin_info = Context::gets('password','nick_name','email_address', 'user_id');
			if($admin_info->email_address) {
				$admin_info->user_name = 'admin';
				$oMemberAdminController->insertAdmin($admin_info);
				$output = $oMemberController->doLogin($admin_info->email_address);
			}
		}
		$oModuleModel = getModel('module');
		$module_list = $oModuleModel->getModuleList();
		foreach($module_list as $key => $val) $oMemberAdminController->insertDeniedID($val->module,'');
		$oMemberAdminController->insertDeniedID('www','');
		$oMemberAdminController->insertDeniedID('root','');
		$oMemberAdminController->insertDeniedID('administrator','');
		$oMemberAdminController->insertDeniedID('telnet','');
		$oMemberAdminController->insertDeniedID('ftp','');
		$oMemberAdminController->insertDeniedID('http','');
		FileHandler::makeDir('./files/member_extra_info/image_name');
		FileHandler::makeDir('./files/member_extra_info/image_mark');
		FileHandler::makeDir('./files/member_extra_info/profile_image');
		FileHandler::makeDir('./files/member_extra_info/signature');
		$oModuleController->insertTrigger('document.getDocumentMenu', 'member', 'controller', 'triggerGetDocumentMenu', 'after');
		$oModuleController->insertTrigger('comment.getCommentMenu', 'member', 'controller', 'triggerGetCommentMenu', 'after');
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		if(!is_dir("./files/member_extra_info")) return true;
		if(!is_dir("./files/member_extra_info/profile_image")) return true;
		$act = $oDB->isColumnExists("member_auth_mail", "is_register");
		if(!$act) return true;
		if(!$oDB->isColumnExists("member_group_member", "site_srl")) return true;
		if(!$oDB->isColumnExists("member_group", "site_srl")) return true;
		if($oDB->isIndexExists("member_group","uni_member_group_title")) return true;
		if(!$oDB->isColumnExists("member_group", "list_order")) return true;
		if(!$oDB->isColumnExists("member_group", "image_mark")) return true;
		if(!$oDB->isColumnExists("member", "change_password_date")) return true;
		if(!$oDB->isColumnExists("member", "find_account_question")) return true;
		if(!$oDB->isColumnExists("member", "find_account_answer")) return true;
		if(!$oDB->isColumnExists("member", "list_order")) return true;
		if(!$oDB->isIndexExists("member","idx_list_order")) return true;
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if(!$config->signupForm) return true;
		if($config->agreement) return true;
		if($config->skin) {
			$config_parse = explode('.', $config->skin);
			if(count($con fig_parse) > 1) {
				$template_path = sprintf('./themes/%s/modules/member/', $config_parse[0]);
				if(is_dir($template_path)) return true;
			}
		}
		if(is_readable('./files/member_extra_info/agreement.txt')) return true;
		if(!is_readable('./files/ruleset/insertMember.xml')) return true;
		if(!is_readable('./files/ruleset/login.xml')) return true;
		if(!is_readable('./files/ruleset/find_member_account_by_question.xml')) return true;
		if(!$oModuleModel->getTrigger('document.getDocumentMenu', 'member', 'controller', 'triggerGetDocumentMenu', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.getCommentMenu', 'member', 'controller', 'triggerGetCommentMenu', 'after')) return true;
		return false;
	}

	function moduleUpdate() {
		$oDB = &DB::getInstance();
		$oModuleController = getController('module');
		FileHandler::makeDir('./files/member_extra_info/image_name');
		FileHandler::makeDir('./files/member_extra_info/image_mark');
		FileHandler::makeDir('./files/member_extra_info/signature');
		FileHandler::makeDir('./files/member_extra_info/profile_image');
		if(!$oDB->isColumnExists("member_auth_mail", "is_register")) $oDB->addColumn("member_auth_mail", "is_register", "char", 1, "N", true);
		if(!$oDB->isColumnExists("member_group_member", "site_srl")) {
			$oDB->addColumn("member_group_member", "site_srl", "number", 11, 0, true);
			$oDB->addIndex("member_group_member", "idx_site_srl", "site_srl", false);
		}
		if(!$oDB->isColumnExists("member_group", "site_srl")) {
			$oDB->addColumn("member_group", "site_srl", "number", 11, 0, true);
			$oDB->addIndex("member_group","idx_site_title", array("site_srl","title"),true);
		}
		if($oDB->isIndexExists("member_group","uni_member_group_title")) $oDB->dropIndex("member_group","uni_member_group_title",true);
		if(!$oDB->isColumnExists("member_group", "list_order")) {
			$oDB->addColumn("member_group", "list_order", "number", 11, '', true);
			$oDB->addIndex("member_group","idx_list_order", "list_order",false);
			$output = executeQuery('member.updateAllMemberGroupListOrder');
		}
		if(!$oDB->isColumnExists("member_group", "image_mark")) $oDB->addColumn("member_group", "image_mark", "text");
		if(!$oDB->isColumnExists("member", "change_password_date")) {
			$oDB->addColumn("member", "change_password_date", "date");
			executeQuery('member.updateAllChangePasswordDate');
		}
		if(!$oDB->isColumnExists("member", "find_account_question")) $oDB->addColumn("member", "find_account_question", "number", 11);
		if(!$oDB->isColumnExists("member", "find_account_answer")) $oDB->addColumn("member", "find_account_answer", "varchar", 250);
		if(!$oDB->isColumnExists("member", "list_order")) {
			$oDB->addColumn("member", "list_order", "number", 11);
			@set_time_limit(0);
			$args->list_order = 'member_srl';
			executeQuery('member.updateMemberListOrderAll',$args);
			executeQuery('member.updateMemberListOrderAll');
		}
		if(!$oDB->isIndexExists("member","idx_list_order")) $oDB->addIndex("member","idx_list_order", array("list_order"));
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		$oModuleController = getController('module');
		if($config->agreement) {
			$agreement_file = _XE_PATH_.'files/member_extra_info/agreement_' . Context::get('lang_type') . '.txt';
			$output = FileHandler::writeFile($agreement_file, $config->agreement);
			$config->agreement = NULL;
			$output = $oModuleController->updateModuleConfig('member', $config);
		}
		$oMemberAdminController = getAdminController('member');
		if(!$config->signupForm || !is_array($config->signupForm)) {
			$identifier = 'user_id';
			$config->signupForm = $oMemberAdminController->createSignupForm($identifier);
			$config->identifier = $identifier;
			unset($config->agreement);
			$output = $oModuleController->updateModuleConfig('member', $config);
		}
		if($config->skin) {
			$config_parse = explode('.', $config->skin);
			if (count($config_parse) > 1) {
				$template_path = sprintf('./themes/%s/modules/member/', $config_parse[0]);
				if(is_dir($template_path)) {
					$config->skin = implode('|@|', $config_parse);
					$oModuleController = getController('module');
					$oModuleController->updateModuleConfig('member', $config);
				}
			}
		}
		if(is_readable('./files/member_extra_info/agreement.txt')) {
			$source_file = _XE_PATH_.'files/member_extra_info/agreement.txt';
			$target_file = _XE_PATH_.'files/member_extra_info/agreement_' . Context::get('lang_type') . '.txt';
			FileHandler::rename($source_file, $target_file);
		}
		FileHandler::makeDir('./files/ruleset');
		if(!is_readable('./files/ruleset/insertMember.xml')) $oMemberAdminController->_createSignupRuleset($config->signupForm);
		if(!is_readable('./files/ruleset/login.xml')) $oMemberAdminController->_createLoginRuleset($config->identifier);
		if(!is_readable('./files/ruleset/find_member_account_by_question.xml')) $oMemberAdminController->_createFindAccountByQuestion($config->identifier);
		if(!$oModuleModel->getTrigger('document.getDocumentMenu', 'member', 'controller', 'triggerGetDocumentMenu', 'after'))
			$oModuleController->insertTrigger('document.getDocumentMenu', 'member', 'controller', 'triggerGetDocumentMenu', 'after');
		if(!$oModuleModel->getTrigger('comment.getCommentMenu', 'member', 'controller', 'triggerGetCommentMenu', 'after'))
			$oModuleController->insertTrigger('comment.getCommentMenu', 'member', 'controller', 'triggerGetCommentMenu', 'after');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {}

	function recordLoginError($error = 0, $message = 'success') {
		if($error == 0) return new Object($error, $message);
		$oMemberModel = getModel('member');
		$config = $oMemberModel->getMemberConfig();
		$oDB = &DB::getInstance();
		if(!$oDB->isTableExists('member_login_count') || $config->enable_login_fail_report == 'N') return new Object($error, $message);
		$args = new stdClass();
		$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$output = executeQuery('member.getLoginCountByIp', $args);
		if($output->data && $output->data->count) {
			$last_update = strtotime($output->data->last_update);
			$term = intval($_SERVER['REQUEST_TIME']-$last_update);
			if($term < $config->max_error_count_time) $args->count = $output->data->count + 1;
			else $args->count = 1;
			unset($oMemberModel);
			unset($config);
			$output = executeQuery('member.updateLoginCountByIp', $args);
		} else {
			$args->count = 1;
			$output = executeQuery('member.insertLoginCountByIp', $args);
		}
		return new Object($error, $message);
	}

	function recordMemberLoginError($error = 0, $message = 'success', $args = NULL) {
		if($error == 0 || !$args->member_srl) return new Object($error, $message);
		$oMemberModel = getModel('member');
		$config = $oMemberModel->getMemberConfig();
		$oDB = &DB::getInstance();
		if(!$oDB->isTableExists('member_count_history') || $config->enable_login_fail_report == 'N') return new Object($error, $message);
		$output = executeQuery('member.getLoginCountHistoryByMemberSrl', $args);
		if($output->data && $output->data->content) {
			$content = unserialize($output->data->content);
			$content[] = array($_SERVER['REMOTE_ADDR'],Context::getLang($message),$_SERVER['REQUEST_TIME']);
			$args->content = serialize($content);
			$output = executeQuery('member.updateLoginCountHistoryByMemberSrl', $args);
		} else {
			$content[0] = array($_SERVER['REMOTE_ADDR'],Context::getLang($message),$_SERVER['REQUEST_TIME']);
			$args->content = serialize($content);
			$output = executeQuery('member.insertLoginCountHistoryByMemberSrl', $args);
		}
		return $this->recordLoginError($error, $message);
	}
}
