<?php
class memberController extends member {
	var $memberInfo;

	function init() {	}

	function procMemberLogin($user_id = null, $password = null, $keep_signed = null) {
		if(!$user_id && !$password && Context::getRequestMethod() == 'GET') {
			$this->setRedirectUrl(getNotEncodedUrl(''));
			return new Object(-1, 'null_user_id');
		}
		if(!$user_id) $user_id = Context::get('user_id');
		$user_id = trim($user_id);
		if(!$password) $password = Context::get('password');
		$password = trim($password);
		if(!$keep_signed) $keep_signed = Context::get('keep_signed');
		if(!$user_id) return new Object(-1,'null_user_id');
		if(!$password) return new Object(-1,'null_password');
		$output = $this->doLogin($user_id, $password, $keep_signed=='Y'?true:false);
		if (!$output->toBool()) return $output;
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		$limit_date = $config->change_password_date;
		if($limit_date > 0) {
			$oMemberModel = getModel('member');
			if($this->memberInfo->change_password_date < date ('YmdHis', strtotime ('-' . $limit_date . ' day'))) {
				$msg = sprintf(Context::getLang('msg_change_password_date'), $limit_date);
				return $this->setRedirectUrl(getNotEncodedUrl('','vid',Context::get('vid'),'mid',Context::get('mid'),'act','dispMemberModifyPassword'), new Object(-1, $msg));
			}
		}
		$args = new stdClass();
		$args->member_srl = $this->memberInfo->member_srl;
		executeQuery('member.deleteAuthMail', $args);
		if(!$config->after_login_url) 
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', '');
		else
			$returnUrl = $config->after_login_url;
		return $this->setRedirectUrl($returnUrl, $output);
	}

	function procMemberLogout() {
		$logged_info = Context::get('logged_info');
		$trigger_output = ModuleHandler::triggerCall('member.doLogout', 'before', $logged_info);
		if(!$trigger_output->toBool()) return $trigger_output;
		$this->destroySessionInfo();
		$trigger_output = ModuleHandler::triggerCall('member.doLogout', 'after', $logged_info);
		if(!$trigger_output->toBool()) return $trigger_output;
		$output = new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if($config->after_logout_url) $output->redirect_url = $config->after_logout_url;
		$this->_clearMemberCache($logged_info->member_srl);
		return $output;
	}

	function procMemberScrapDocument() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
		$logged_info = Context::get('logged_info');
		$document_srl = (int)Context::get('document_srl');
		if(!$document_srl) $document_srl = (int)Context::get('target_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->user_id = $oDocument->get('user_id');
		$args->user_name = $oDocument->get('user_name');
		$args->nick_name = $oDocument->get('nick_name');
		$args->target_member_srl = $oDocument->get('member_srl');
		$args->title = $oDocument->get('title');
		$output = executeQuery('member.getScrapDocument', $args);
		if($output->data->count) return new Object(-1, 'msg_alreay_scrapped');
		$output = executeQuery('member.addScrapDocument', $args);
		if(!$output->toBool()) return $output;
		$this->setError(-1);
		$this->setMessage('success_registed');
	}

	function procMemberDeleteScrap() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
		$logged_info = Context::get('logged_info');
		$document_srl = (int)Context::get('document_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');
		$args = new stdClass;
		$args->member_srl = $logged_info->member_srl;
		$args->document_srl = $document_srl;
		return executeQuery('member.deleteScrapDocument', $args);
	}

	function procMemberSaveDocument() {
		return new Object(0, 'Deprecated method');
	}

	function procMemberDeleteSavedDocument() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
		$logged_info = Context::get('logged_info');
		$document_srl = (int)Context::get('document_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');
		$oDocumentController = getController('document');
		$oDocumentController->deleteDocument($document_srl, true);
	}

	function procMemberCheckValue() {
		$name = Context::get('name');
		$value = Context::get('value');
		if(!$value) return;
		$oMemberModel = getModel('member');
		$logged_info = Context::get('logged_info');
		switch($name) {
			case 'user_id' :
				if($oMemberModel->isDeniedID($value)) return new Object(0,'denied_user_id');
				$member_srl = $oMemberModel->getMemberSrlByUserID($value);
				if($member_srl && $logged_info->member_srl != $member_srl ) return new Object(0,'msg_exists_user_id');
			break;
			case 'nick_name' :
				if($oMemberModel->isDeniedNickName($value)) return new Object(0,'denied_nick_name');
				$member_srl = $oMemberModel->getMemberSrlByNickName($value);
				if($member_srl && $logged_info->member_srl != $member_srl ) return new Object(0,'msg_exists_nick_name');
			break;
			case 'email_address' :
				$member_srl = $oMemberModel->getMemberSrlByEmailAddress($value);
				if($member_srl && $logged_info->member_srl != $member_srl ) return new Object(0,'msg_exists_email_address');
			break;
		}
	}

	function procMemberInsert() {
		if (Context::getRequestMethod () == "GET") return new Object (-1, "msg_invalid_request");
		$oMemberModel = &getModel ('member');
		$config = $oMemberModel->getMemberConfig();
		$trigger_output = ModuleHandler::triggerCall ('member.procMemberInsert', 'before', $config);
		if(!$trigger_output->toBool ()) return $trigger_output;
		if($config->enable_join != 'Y') return $this->stop ('msg_signup_disabled');
		if($config->agreement && Context::get('accept_agreement')!='Y') return $this->stop('msg_accept_agreement');
		$getVars = array();
		if($config->signupForm) {
			foreach($config->signupForm as $formInfo) {
				if($formInfo->isDefaultForm && ($formInfo->isUse || $formInfo->required || $formInfo->mustRequired)) $getVars[] = $formInfo->name;
			}
		}
		$args = new stdClass;
		foreach($getVars as $val) {
			$args->{$val} = Context::get($val);
			if($val == 'birthday') $args->birthday_ui = Context::get('birthday_ui');
		}
		$args->birthday = intval(strtr($args->birthday, array('-'=>'', '/'=>'', '.'=>'', ' '=>'')));
		if(!$args->birthday && $args->birthday_ui) $args->birthday = intval(strtr($args->birthday_ui, array('-'=>'', '/'=>'', '.'=>'', ' '=>'')));
		$args->find_account_answer = Context::get('find_account_answer');
		$args->allow_mailing = Context::get('allow_mailing');
		$args->allow_message = Context::get('allow_message');
		if($args->password1) $args->password = $args->password1;
		if(!$oMemberModel->checkPasswordStrength($args->password, $config->password_strength)) {
			$message = Context::getLang('about_password_strength');
			return new Object(-1, $message[$config->password_strength]);
		}
		$all_args = Context::getRequestVars();
		unset($all_args->module);
		unset($all_args->act);
		unset($all_args->is_admin);
		unset($all_args->member_srl);
		unset($all_args->description);
		unset($all_args->group_srl_list);
		unset($all_args->body);
		unset($all_args->accept_agreement);
		unset($all_args->signature);
		unset($all_args->password);
		unset($all_args->password2);
		unset($all_args->mid);
		unset($all_args->error_return_url);
		unset($all_args->ruleset);
		unset($all_args->captchaType);
		unset($all_args->secret_text);
		if($config->enable_confirm == 'Y') $args->denied = 'Y';
		$extra_vars = delObjectVars($all_args, $args);
		$args->extra_vars = serialize($extra_vars);
		$checkInfos = array('user_id', 'user_name', 'nick_name', 'email_address');
		foreach($checkInfos as $val) if(isset($args->{$val})) $args->{$val} = preg_replace('/[\pZ\pC]+/u', '', $args->{$val});
		$output = $this->insertMember($args);
		if(!$output->toBool()) return $output;
		$profile_image = $_FILES['profile_image'];
		if(is_uploaded_file($profile_image['tmp_name'])) $this->insertProfileImage($args->member_srl, $profile_image['tmp_name']);
		$image_mark = $_FILES['image_mark'];
		if(is_uploaded_file($image_mark['tmp_name'])) $this->insertImageMark($args->member_srl, $image_mark['tmp_name']);
		$image_name = $_FILES['image_name'];
		if(is_uploaded_file($image_name['tmp_name'])) $this->insertImageName($args->member_srl, $image_name['tmp_name']);
		$site_module_info = Context::get('site_module_info');
		if($site_module_info->site_srl > 0) {
			$columnList = array('site_srl', 'group_srl');
			$default_group = $oMemberModel->getDefaultGroup($site_module_info->site_srl, $columnList);
			if($default_group->group_srl) $this->addMemberToGroup($args->member_srl, $default_group->group_srl, $site_module_info->site_srl);
		}
		if($config->enable_confirm != 'Y') {
			if($config->identifier == 'email_address') $output = $this->doLogin($args->email_address);
			else $output = $this->doLogin($args->user_id);
			if(!$output->toBool()) {
				if($output->error == -9)
					$output->error = -11;
				return $this->setRedirectUrl(getUrl('', 'act', 'dispMemberLoginForm'), $output);
			}
		}
		$this->add('member_srl', $args->member_srl);
		if($config->redirect_url) $this->add('redirect_url', $config->redirect_url);
		if($config->enable_confirm == 'Y') {
			$msg = sprintf(Context::getLang('msg_confirm_mail_sent'), $args->email_address);
			$this->setMessage($msg);
			return $this->setRedirectUrl(getUrl('', 'act', 'dispMemberLoginForm'), new Object(-12, $msg));
		}
		else $this->setMessage('success_registed');
		$trigger_output = ModuleHandler::triggerCall('member.procMemberInsert', 'after', $config);
		if(!$trigger_output->toBool()) return $trigger_output;
		if($config->redirect_url) $returnUrl = $config->redirect_url;
		else {
			if(Context::get('success_return_url')) {
				$returnUrl = Context::get('success_return_url');
			} else if($_COOKIE['XE_REDIRECT_URL']) {
				$returnUrl = $_COOKIE['XE_REDIRECT_URL'];
				setcookie("XE_REDIRECT_URL", '', 1);
			}
		}
		$this->_clearMemberCache($args->member_srl, $site_module_info->site_srl);
		$this->setRedirectUrl($returnUrl);
	}

	function procMemberModifyInfoBefore() {
		if($_SESSION['rechecked_password_step'] != 'INPUT_PASSWORD') return $this->stop('msg_invalid_request');
		if(!Context::get('is_logged')) return $this->stop('msg_not_logged');
		$password = Context::get('password');
		if(!$password) return $this->stop('msg_invalid_request');
		$oMemberModel = getModel('member');
		if(!$this->memberInfo->password) { 
			$logged_info = Context::get('logged_info');
			$member_srl = $logged_info->member_srl;
			$columnList = array('member_srl', 'password');
			$memberInfo = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);
			$this->memberInfo->password = $memberInfo->password;
		}
		if(!$oMemberModel->isValidPassword($this->memberInfo->password, $password)) return new Object(-1, 'invalid_password');
		$_SESSION['rechecked_password_step'] = 'VALIDATE_PASSWORD';
		if(Context::get('success_return_url')) $redirectUrl = Context::get('success_return_url');
		else $redirectUrl = getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberModifyInfo');
		$this->setRedirectUrl($redirectUrl);
	}

	function procMemberModifyInfo() {
		if(!Context::get('is_logged')) return $this->stop('msg_not_logged');
		if($_SESSION['rechecked_password_step'] != 'INPUT_DATA') return $this->stop('msg_invalid_request');
		unset($_SESSION['rechecked_password_step']);
		$oMemberModel = &getModel ('member');
		$config = $oMemberModel->getMemberConfig ();
		$getVars = array('find_account_answer','allow_mailing','allow_message');
		if($config->signupForm) {
			foreach($config->signupForm as $formInfo) {
				if($formInfo->isDefaultForm && ($formInfo->isUse || $formInfo->required || $formInfo->mustRequired)) $getVars[] = $formInfo->name;
			}
		}
		$args = new stdClass;
		foreach($getVars as $val) {
			$args->{$val} = Context::get($val);
			if($val == 'birthday') $args->birthday_ui = Context::get('birthday_ui');
		}
		$logged_info = Context::get('logged_info');
		$args->member_srl = $logged_info->member_srl;
		$args->birthday = intval(strtr($args->birthday, array('-'=>'', '/'=>'', '.'=>'', ' '=>'')));
		if(!$args->birthday && $args->birthday_ui) $args->birthday = intval(strtr($args->birthday_ui, array('-'=>'', '/'=>'', '.'=>'', ' '=>'')));
		$all_args = Context::getRequestVars();
		unset($all_args->module);
		unset($all_args->act);
		unset($all_args->member_srl);
		unset($all_args->is_admin);
		unset($all_args->description);
		unset($all_args->group_srl_list);
		unset($all_args->body);
		unset($all_args->accept_agreement);
		unset($all_args->signature);
		unset($all_args->_filter);
		unset($all_args->mid);
		unset($all_args->error_return_url);
		unset($all_args->ruleset);
		unset($all_args->password);
		$extra_vars = delObjectVars($all_args, $args);
		$args->extra_vars = serialize($extra_vars);
		$checkInfos = array('user_id', 'user_name', 'nick_name', 'email_address');
		foreach($checkInfos as $val) {
			if(isset($args->{$val})) $args->{$val} = preg_replace('/[\pZ\pC]+/u', '', $args->{$val});
		}
		$output = $this->updateMember($args);
		if(!$output->toBool()) return $output;
		$profile_image = $_FILES['profile_image'];
		if(is_uploaded_file($profile_image['tmp_name'])) $this->insertProfileImage($args->member_srl, $profile_image['tmp_name']);
		$image_mark = $_FILES['image_mark'];
		if(is_uploaded_file($image_mark['tmp_name'])) $this->insertImageMark($args->member_srl, $image_mark['tmp_name']);
		$image_name = $_FILES['image_name'];
		if(is_uploaded_file($image_name['tmp_name'])) $this->insertImageName($args->member_srl, $image_name['tmp_name']);
		$signature = Context::get('signature');
		$this->putSignature($args->member_srl, $signature);
		$this->memberInfo = $oMemberModel->getMemberInfoByMemberSrl($args->member_srl);
		$trigger_output = ModuleHandler::triggerCall('member.procMemberModifyInfo', 'after', $this->memberInfo);
		if(!$trigger_output->toBool()) return $trigger_output;
		$this->setSessionInfo();
		$this->add('member_srl', $args->member_srl);
		$this->setMessage('success_updated');
		$site_module_info = Context::get('site_module_info');
		$this->_clearMemberCache($args->member_srl, $site_module_info->site_srl);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberInfo');
		$this->setRedirectUrl($returnUrl);
	}

	function procMemberModifyPassword() {
		if(!Context::get('is_logged')) return $this->stop('msg_not_logged');
		$current_password = trim(Context::get('current_password'));
		$password = trim(Context::get('password1'));
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$oMemberModel = getModel('member');
		$columnList = array('member_srl', 'password');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);
		if(!$oMemberModel->isValidPassword($member_info->password, $current_password, $member_srl)) return new Object(-1, 'invalid_password');
		if($current_password == $password) return new Object(-1, 'invalid_new_password');
		$args = new stdClass;
		$args->member_srl = $member_srl;
		$args->password = $password;
		$output = $this->updateMemberPassword($args);
		if(!$output->toBool()) return $output;
		$this->add('member_srl', $args->member_srl);
		$this->setMessage('success_updated');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberInfo');
		$this->setRedirectUrl($returnUrl);
	}

	function procMemberLeave() {
		if(!Context::get('is_logged')) return $this->stop('msg_not_logged');
		$password = trim(Context::get('password'));
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$oMemberModel = getModel('member');
		if(!$this->memberInfo->password) {
			$columnList = array('member_srl', 'password');
			$memberInfo = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);
			$this->memberInfo->password = $memberInfo->password;
		}
		if(!$oMemberModel->isValidPassword($this->memberInfo->password, $password)) return new Object(-1, 'invalid_password');
		$output = $this->deleteMember($member_srl);
		if(!$output->toBool()) return $output;
		$this->destroySessionInfo();
		$this->setMessage('success_leaved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', '');
		$this->setRedirectUrl($returnUrl);
	}

	function procMemberInsertProfileImage() {
		$file = $_FILES['profile_image'];
		if(!is_uploaded_file($file['tmp_name'])) return $this->stop('msg_not_uploaded_profile_image');
		$member_srl = Context::get('member_srl');
		if(!$member_srl) return $this->stop('msg_not_uploaded_profile_image');
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin != 'Y' && $logged_info->member_srl != $member_srl) return $this->stop('msg_not_uploaded_profile_image');
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if($logged_info->is_admin != 'Y' && $config->profile_image != 'Y') return $this->stop('msg_not_uploaded_profile_image');
		$this->insertProfileImage($member_srl, $file['tmp_name']);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberModifyInfo');
		$this->setRedirectUrl($returnUrl);
	}

	function insertProfileImage($member_srl, $target_file) {
		if(!checkUploadedFile($target_file)) return;
		$oMemberModel = getModel('member');
		$config = $oMemberModel->getMemberConfig();
		$max_width = $config->profile_image_max_width;
		if(!$max_width) $max_width = "90";
		$max_height = $config->profile_image_max_height;
		if(!$max_height) $max_height = "90";
		$target_path = sprintf('files/member_extra_info/profile_image/%s', getNumberingPath($member_srl));
		FileHandler::makeDir($target_path);
		list($width, $height, $type, $attrs) = @getimagesize($target_file);
		if(IMAGETYPE_PNG == $type) $ext = 'png';
		elseif(IMAGETYPE_JPEG == $type) $ext = 'jpg';
		elseif(IMAGETYPE_GIF == $type) $ext = 'gif';
		else return;
		FileHandler::removeFilesInDir($target_path);
		$target_filename = sprintf('%s%d.%s', $target_path, $member_srl, $ext);
		if(($width > $max_width || $height > $max_height ) && $type != 1) FileHandler::createImageFile($target_file, $target_filename, $max_width, $max_height, $ext);
		else @copy($target_file, $target_filename);
	}

	function procMemberInsertImageName() {
		$file = $_FILES['image_name'];
		if(!is_uploaded_file($file['tmp_name'])) return $this->stop('msg_not_uploaded_image_name');
		$member_srl = Context::get('member_srl');
		if(!$member_srl) return $this->stop('msg_not_uploaded_image_name');
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin != 'Y' && $logged_info->member_srl != $member_srl) return $this->stop('msg_not_uploaded_image_name');
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if($logged_info->is_admin != 'Y' && $config->image_name != 'Y') return $this->stop('msg_not_uploaded_image_name');
		$this->insertImageName($member_srl, $file['tmp_name']);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberModifyInfo');
		$this->setRedirectUrl($returnUrl);
	}

	function insertImageName($member_srl, $target_file) {
		if(!checkUploadedFile($target_file)) return;
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		$max_width = $config->image_name_max_width;
		if(!$max_width) $max_width = "90";
		$max_height = $config->image_name_max_height;
		if(!$max_height) $max_height = "20";
		$target_path = sprintf('files/member_extra_info/image_name/%s/', getNumberingPath($member_srl));
		FileHandler::makeDir($target_path);
		$target_filename = sprintf('%s%d.gif', $target_path, $member_srl);
		list($width, $height, $type, $attrs) = @getimagesize($target_file);
		if($width > $max_width || $height > $max_height || $type!=1) FileHandler::createImageFile($target_file, $target_filename, $max_width, $max_height, 'gif');
		else @copy($target_file, $target_filename);
	}

	function procMemberDeleteProfileImage($_memberSrl = 0) {
		$member_srl = ($_memberSrl) ? $_memberSrl : Context::get('member_srl');
		if(!$member_srl) return new Object(0,'success');
		$logged_info = Context::get('logged_info');
		if($logged_info && ($logged_info->is_admin == 'Y' || $logged_info->member_srl == $member_srl)) {
			$oMemberModel = getModel('member');
			$profile_image = $oMemberModel->getProfileImage($member_srl);
			FileHandler::removeFile($profile_image->file);
		}
		return new Object(0,'success');
	}

	function procMemberDeleteImageName($_memberSrl = 0) {
		$member_srl = ($_memberSrl) ? $_memberSrl : Context::get('member_srl');
		if(!$member_srl) return new Object(0,'success');
		$logged_info = Context::get('logged_info');
		if($logged_info && ($logged_info->is_admin == 'Y' || $logged_info->member_srl == $member_srl)) {
			$oMemberModel = getModel('member');
			$image_name = $oMemberModel->getImageName($member_srl);
			FileHandler::removeFile($image_name->file);
		}
		return new Object(0,'success');
	}

	function procMemberInsertImageMark() {
		$file = $_FILES['image_mark'];
		if(!is_uploaded_file($file['tmp_name'])) return $this->stop('msg_not_uploaded_image_mark');
		$member_srl = Context::get('member_srl');
		if(!$member_srl) return $this->stop('msg_not_uploaded_image_mark');
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin != 'Y' && $logged_info->member_srl != $member_srl) return $this->stop('msg_not_uploaded_image_mark');
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if($logged_info->is_admin != 'Y' && $config->image_mark != 'Y') return $this->stop('msg_not_uploaded_image_mark');
		$this->insertImageMark($member_srl, $file['tmp_name']);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberModifyInfo');
		$this->setRedirectUrl($returnUrl);
	}

	function insertImageMark($member_srl, $target_file) {
		if(!checkUploadedFile($target_file)) return;
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		$max_width = $config->image_mark_max_width;
		if(!$max_width) $max_width = "20";
		$max_height = $config->image_mark_max_height;
		if(!$max_height) $max_height = "20";
		$target_path = sprintf('files/member_extra_info/image_mark/%s/', getNumberingPath($member_srl));
		FileHandler::makeDir($target_path);
		$target_filename = sprintf('%s%d.gif', $target_path, $member_srl);
		list($width, $height, $type, $attrs) = @getimagesize($target_file);
		if($width > $max_width || $height > $max_height || $type!=1) FileHandler::createImageFile($target_file, $target_filename, $max_width, $max_height, 'gif');
		else @copy($target_file, $target_filename);
	}

	function procMemberDeleteImageMark($_memberSrl = 0) {
		$member_srl = ($_memberSrl) ? $_memberSrl : Context::get('member_srl');
		if(!$member_srl) return new Object(0,'success');
		$logged_info = Context::get('logged_info');
		if($logged_info && ($logged_info->is_admin == 'Y' || $logged_info->member_srl == $member_srl)) {
			$oMemberModel = getModel('member');
			$image_mark = $oMemberModel->getImageMark($member_srl);
			FileHandler::removeFile($image_mark->file);
		}
		return new Object(0,'success');
	}

	function procMemberFindAccount() {
		$email_address = Context::get('email_address');
		if(!$email_address) return new Object(-1, 'msg_invalid_request');
		$oMemberModel = getModel('member');
		$oModuleModel = getModel('module');
		$member_srl = $oMemberModel->getMemberSrlByEmailAddress($email_address);
		if(!$member_srl) return new Object(-1, 'msg_email_not_exists');
		$columnList = array('denied', 'member_srl', 'user_id', 'user_name', 'email_address', 'nick_name');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);
		if($member_info->denied == 'Y') {
			$chk_args = new stdClass;
			$chk_args->member_srl = $member_info->member_srl;
			$output = executeQuery('member.chkAuthMail', $chk_args);
			if($output->toBool() && $output->data->count != '0') return new Object(-1, 'msg_user_not_confirmed');
		}
		$oPassword = new Password();
		$args = new stdClass();
		$args->user_id = $member_info->user_id;
		$args->member_srl = $member_info->member_srl;
		$args->new_password = $oPassword->createTemporaryPassword(8);
		$args->auth_key = $oPassword->createSecureSalt(40);
		$args->is_register = 'N';
		$output = executeQuery('member.insertAuthMail', $args);
		if(!$output->toBool()) return $output;
		Context::set('auth_args', $args);
		$member_config = $oModuleModel->getModuleConfig('member');
		$memberInfo = array();
		global $lang;
		if(is_array($member_config->signupForm)) {
			$exceptForm=array('password', 'find_account_question');
			foreach($member_config->signupForm as $form) {
				if(!in_array($form->name, $exceptForm) && $form->isDefaultForm && ($form->required || $form->mustRequired)) {
					$memberInfo[$lang->{$form->name}] = $member_info->{$form->name};
				}
			}
		} else {
			$memberInfo[$lang->user_id] = $args->user_id;
			$memberInfo[$lang->user_name] = $args->user_name;
			$memberInfo[$lang->nick_name] = $args->nick_name;
			$memberInfo[$lang->email_address] = $args->email_address;
		}
		Context::set('memberInfo', $memberInfo);
		if(!$member_config->skin) $member_config->skin = "default";
		if(!$member_config->colorset) $member_config->colorset = "white";
		Context::set('member_config', $member_config);
		$tpl_path = sprintf('%sskins/%s', $this->module_path, $member_config->skin);
		if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
		$find_url = getFullUrl ('', 'module', 'member', 'act', 'procMemberAuthAccount', 'member_srl', $member_info->member_srl, 'auth_key', $args->auth_key);
		Context::set('find_url', $find_url);
		$oTemplate = &TemplateHandler::getInstance();
		$content = $oTemplate->compile($tpl_path, 'find_member_account_mail');
		$oModuleModel = getModel('module');
		$member_config = $oModuleModel->getModuleConfig('member');
		$oMail = new Mail();
		$oMail->setTitle( Context::getLang('msg_find_account_title') );
		$oMail->setContent($content);
		$oMail->setSender( $member_config->webmaster_name?$member_config->webmaster_name:'webmaster', $member_config->webmaster_email);
		$oMail->setReceiptor( $member_info->user_name, $member_info->email_address );
		$oMail->send();
		$msg = sprintf(Context::getLang('msg_auth_mail_sent'), $member_info->email_address);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMemberFindAccount');
			$this->setRedirectUrl($returnUrl);
		}
		return new Object(0,$msg);
	}

	function procMemberFindAccountByQuestion() {
		$oMemberModel = getModel('member');
		$config = $oMemberModel->getMemberConfig();
		$email_address = Context::get('email_address');
		$user_id = Context::get('user_id');
		$find_account_question = trim(Context::get('find_account_question'));
		$find_account_answer = trim(Context::get('find_account_answer'));
		if(($config->identifier == 'user_id' && !$user_id) || !$email_address || !$find_account_question || !$find_account_answer) return new Object(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		$member_srl = $oMemberModel->getMemberSrlByEmailAddress($email_address);
		if(!$member_srl) return new Object(-1, 'msg_email_not_exists');
		$columnList = array('member_srl', 'find_account_question', 'find_account_answer');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);
		if(!$member_info->find_account_question || !$member_info->find_account_answer) return new Object(-1, 'msg_question_not_exists');
		if(trim($member_info->find_account_question) != $find_account_question || trim($member_info->find_account_answer) != $find_account_answer) return new Object(-1, 'msg_answer_not_matches');
		if($config->identifier == 'email_address') $user_id = $email_address;
		$oPassword =  new Password();
		$temp_password = $oPassword->createTemporaryPassword(8);
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->password = $temp_password;
		$args->change_password_date = '1';
		$output = $this->updateMemberPassword($args);
		if(!$output->toBool()) return $output;
		$_SESSION['xe_temp_password_' . $user_id] = $temp_password;
		$this->add('user_id',$user_id);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', '');
		$this->setRedirectUrl($returnUrl.'&user_id='.$user_id);
	}

	function procMemberAuthAccount() {
		$oMemberModel = getModel('member');
		$member_srl = Context::get('member_srl');
		$auth_key = Context::get('auth_key');
		if(!$member_srl || !$auth_key) return $this->stop('msg_invalid_request');
		$args = new stdClass;
		$args->member_srl = $member_srl;
		$args->auth_key = $auth_key;
		$output = executeQuery('member.getAuthMail', $args);
		if(!$output->toBool() || $output->data->auth_key != $auth_key) {
			if(strlen($output->data->auth_key) !== strlen($auth_key)) executeQuery('member.deleteAuthMail', $args);
			return $this->stop('msg_invalid_auth_key');
		}
		if(ztime($output->data->regdate) < $_SERVER['REQUEST_TIME'] + zgap() - 86400) {
			executeQuery('member.deleteAuthMail', $args);
			return $this->stop('msg_invalid_auth_key');
		}
		$args->password = $output->data->new_password;
		if($output->data->is_register == 'Y') $args->denied = 'N';
		else $args->password = $oMemberModel->hashPassword($args->password);
		$is_register = $output->data->is_register;
		$output = executeQuery('member.updateMemberPassword', $args);
		if(!$output->toBool()) return $this->stop($output->getMessage());
		executeQuery('member.deleteAuthMail',$args);
		$this->_clearMemberCache($args->member_srl);
		Context::set('is_register', $is_register);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('msg_success_authed');
	}

	function procMemberResendAuthMail() {
		$email_address = Context::get('email_address');
		if(!$email_address) return new Object(-1, 'msg_invalid_request');
		$oMemberModel = getModel('member');
		$args = new stdClass;
		$args->email_address = $email_address;
		$memberSrl = $oMemberModel->getMemberSrlByEmailAddress($email_address);
		if(!$memberSrl) return new Object(-1, 'msg_not_exists_member');
		$columnList = array('member_srl', 'user_id', 'user_name', 'nick_name', 'email_address');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($memberSrl, 0, $columnList);
		$oModuleModel = getModel('module');
		$member_config = $oModuleModel->getModuleConfig('member');
		if(!$member_config->skin) $member_config->skin = "default";
		if(!$member_config->colorset) $member_config->colorset = "white";
		$chk_args = new stdClass;
		$chk_args->member_srl = $member_info->member_srl;
		$output = executeQuery('member.chkAuthMail', $chk_args);
		if($output->toBool() && $output->data->count == '0') return new Object(-1, 'msg_invalid_request');
		$auth_args = new stdClass;
		$auth_args->member_srl = $member_info->member_srl;
		$output = executeQueryArray('member.getAuthMailInfo', $auth_args);
		if(!$output->data || !$output->data[0]->auth_key)  return new Object(-1, 'msg_invalid_request');
		$auth_info = $output->data[0];
		$renewal_args = new stdClass;
		$renewal_args->member_srl = $member_info->member_srl;
		$renewal_args->auth_key = $auth_info->auth_key;
		$output = executeQuery('member.updateAuthMail', $renewal_args);		
		$memberInfo = array();
		global $lang;
		if(is_array($member_config->signupForm)) {
			$exceptForm=array('password', 'find_account_question');
			foreach($member_config->signupForm as $form) {
				if(!in_array($form->name, $exceptForm) && $form->isDefaultForm && ($form->required || $form->mustRequired)) {
					$memberInfo[$lang->{$form->name}] = $member_info->{$form->name};
				}
			}
		} else {
			$memberInfo[$lang->user_id] = $member_info->user_id;
			$memberInfo[$lang->user_name] = $member_info->user_name;
			$memberInfo[$lang->nick_name] = $member_info->nick_name;
			$memberInfo[$lang->email_address] = $member_info->email_address;
		}
		Context::set('memberInfo', $memberInfo);
		Context::set('member_config', $member_config);
		$tpl_path = sprintf('%sskins/%s', $this->module_path, $member_config->skin);
		if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
		$auth_url = getFullUrl('','module','member','act','procMemberAuthAccount','member_srl',$member_info->member_srl, 'auth_key',$auth_info->auth_key);
		Context::set('auth_url', $auth_url);
		$oTemplate = &TemplateHandler::getInstance();
		$content = $oTemplate->compile($tpl_path, 'confirm_member_account_mail');
		$oMail = new Mail();
		$oMail->setTitle( Context::getLang('msg_confirm_account_title') );
		$oMail->setContent($content);
		$oMail->setSender( $member_config->webmaster_name?$member_config->webmaster_name:'webmaster', $member_config->webmaster_email);
		$oMail->setReceiptor( $args->user_name, $args->email_address );
		$oMail->send();
		$msg = sprintf(Context::getLang('msg_confirm_mail_sent'), $args->email_address);
		$this->setMessage($msg);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', '');
		$this->setRedirectUrl($returnUrl);
	}

	function procMemberResetAuthMail() {
		$memberInfo = $_SESSION['auth_member_info'];
		unset($_SESSION['auth_member_info']);
		if(!$memberInfo) return $this->stop('msg_invalid_request');
		$newEmail = Context::get('email_address');
		if(!$newEmail) return $this->stop('msg_invalid_request');
		$oMemberModel = getModel('member');
		$member_srl = $oMemberModel->getMemberSrlByEmailAddress($newEmail);
		if($member_srl) return new Object(-1,'msg_exists_email_address');
		$args = new stdClass;
		$args->member_srl = $memberInfo->member_srl;
		$output = executeQuery('member.deleteAuthMail', $args);
		if(!$output->toBool()) return $output;
		$args->email_address = $newEmail;
		list($args->email_id, $args->email_host) = explode('@', $newEmail);
		$output = executeQuery('member.updateMemberEmailAddress', $args);
		if(!$output->toBool()) return $this->stop($output->getMessage());
		$this->_clearMemberCache($args->member_srl);
		$oPassword = new Password();
		$auth_args = new stdClass();
		$auth_args->user_id = $memberInfo->user_id;
		$auth_args->member_srl = $memberInfo->member_srl;
		$auth_args->new_password = $memberInfo->password;
		$auth_args->auth_key = $oPassword->createSecureSalt(40);
		$auth_args->is_register = 'Y';
		$output = executeQuery('member.insertAuthMail', $auth_args);
		if(!$output->toBool()) return $output;
		$memberInfo->email_address = $newEmail;
		$this->_sendAuthMail($auth_args, $memberInfo);
		$msg = sprintf(Context::getLang('msg_confirm_mail_sent'), $memberInfo->email_address);
		$this->setMessage($msg);
		$returnUrl = getUrl('');
		$this->setRedirectUrl($returnUrl);
	}

	function _sendAuthMail($auth_args, $member_info) {
		$oMemberModel = getModel('member');
		$member_config = $oMemberModel->getMemberConfig();
		Context::set('auth_args', $auth_args);
		$memberInfo = array();
		global $lang;
		if(is_array($member_config->signupForm)) {
			$exceptForm=array('password', 'find_account_question');
			foreach($member_config->signupForm as $form) {
				if(!in_array($form->name, $exceptForm) && $form->isDefaultForm && ($form->required || $form->mustRequired)) {
					$memberInfo[$lang->{$form->name}] = $member_info->{$form->name};
				}
			}
		} else {
			$memberInfo[$lang->user_id] = $member_info->user_id;
			$memberInfo[$lang->user_name] = $member_info->user_name;
			$memberInfo[$lang->nick_name] = $member_info->nick_name;
			$memberInfo[$lang->email_address] = $member_info->email_address;
		}
		Context::set('memberInfo', $memberInfo);
		if(!$member_config->skin) $member_config->skin = "default";
		if(!$member_config->colorset) $member_config->colorset = "white";
		Context::set('member_config', $member_config);
		$tpl_path = sprintf('%sskins/%s', $this->module_path, $member_config->skin);
		if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
		$auth_url = getFullUrl('','module','member','act','procMemberAuthAccount','member_srl',$member_info->member_srl, 'auth_key',$auth_args->auth_key);
		Context::set('auth_url', $auth_url);
		$oTemplate = &TemplateHandler::getInstance();
		$content = $oTemplate->compile($tpl_path, 'confirm_member_account_mail');
		$oMail = new Mail();
		$oMail->setTitle( Context::getLang('msg_confirm_account_title') );
		$oMail->setContent($content);
		$oMail->setSender( $member_config->webmaster_name?$member_config->webmaster_name:'webmaster', $member_config->webmaster_email);
		$oMail->setReceiptor( $member_info->user_name, $member_info->email_address );
		$oMail->send();
	}

	function procMemberSiteSignUp() {
		$site_module_info = Context::get('site_module_info');
		$logged_info = Context::get('logged_info');
		if(!$site_module_info->site_srl || !Context::get('is_logged') || count($logged_info->group_srl_list) ) return new Object(-1,'msg_invalid_request');
		$oMemberModel = getModel('member');
		$columnList = array('site_srl', 'group_srl', 'title');
		$default_group = $oMemberModel->getDefaultGroup($site_module_info->site_srl, $columnList);
		$this->addMemberToGroup($logged_info->member_srl, $default_group->group_srl, $site_module_info->site_srl);
		$groups[$default_group->group_srl] = $default_group->title;
		$logged_info->group_list = $groups;
	}

	function procMemberSiteLeave() {
		$site_module_info = Context::get('site_module_info');
		$logged_info = Context::get('logged_info');
		if(!$site_module_info->site_srl || !Context::get('is_logged') || count($logged_info->group_srl_list) ) return new Object(-1,'msg_invalid_request');
		$args = new stdClass;
		$args->site_srl= $site_module_info->site_srl;
		$args->member_srl = $logged_info->member_srl;
		$output = executeQuery('member.deleteMembersGroup', $args);
		if(!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
		$this->_clearMemberCache($args->member_srl, $site_module_info->site_srl);
	}

	function setMemberConfig($args) {
		if(!$args->skin) $args->skin = "default";
		if(!$args->colorset) $args->colorset = "white";
		if(!$args->editor_skin) $args->editor_skin= "ckeditor";
		if(!$args->editor_colorset) $args->editor_colorset = "moono";
		if($args->enable_join!='Y') $args->enable_join = 'N';
		$args->enable_openid= 'N';
		if($args->profile_image !='Y') $args->profile_image = 'N';
		if($args->image_name!='Y') $args->image_name = 'N';
		if($args->image_mark!='Y') $args->image_mark = 'N';
		if($args->group_image_mark!='Y') $args->group_image_mark = 'N';
		if(!trim(strip_tags($args->agreement))) $args->agreement = null;
		$args->limit_day = (int)$args->limit_day;
		$agreement = trim($args->agreement);
		unset($args->agreement);
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('member',$args);
		if(!$output->toBool()) return $output;
		$agreement_file = _XE_PATH_.'files/member_extra_info/agreement.txt';
		FileHandler::writeFile($agreement_file, $agreement);
		return new Object();
	}

	function putSignature($member_srl, $signature) {
		$signature = trim(removeHackTag($signature));
		$signature = preg_replace('/<(\/?)(embed|object|param)/is', '&lt;$1$2', $signature);
		$check_signature = trim(str_replace(array('&nbsp;',"\n","\r"),'',strip_tags($signature,'<img><object>')));
		$path = sprintf('files/member_extra_info/signature/%s/', getNumberingPath($member_srl));
		$filename = sprintf('%s%d.signature.php', $path, $member_srl);
		if(!$check_signature) return FileHandler::removeFile($filename);
		$buff = sprintf('<?php if(!defined("__XE__")) exit();?>%s', $signature);
		FileHandler::makeDir($path);
		FileHandler::writeFile($filename, $buff);
	}

	function delSignature($member_srl) {
		$filename = sprintf('files/member_extra_info/signature/%s%d.gif', getNumberingPath($member_srl), $member_srl);
		FileHandler::removeFile($filename);
	}

	function addMemberToGroup($member_srl, $group_srl, $site_srl=0)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->group_srl = $group_srl;
		if($site_srl) $args->site_srl = $site_srl;

		// Add
		$output = executeQuery('member.addMemberToGroup',$args);
		$output2 = ModuleHandler::triggerCall('member.addMemberToGroup', 'after', $args);

		$this->_clearMemberCache($member_srl, $site_srl);

		return $output;
	}

	/**
	 * Change a group of certain members
	 * Available only when a member has a single group
	 *
	 * @param object $args
	 *
	 * @return Object
	 */
	function replaceMemberGroup($args)
	{
		$obj = new stdClass;
		$obj->site_srl = $args->site_srl;
		$obj->member_srl = implode(',',$args->member_srl);

		$output = executeQueryArray('member.getMembersGroup', $obj);
		if($output->data) foreach($output->data as $key => $val) $date[$val->member_srl] = $val->regdate;

		$output = executeQuery('member.deleteMembersGroup', $obj);
		if(!$output->toBool()) return $output;

		$inserted_members = array();
		foreach($args->member_srl as $key => $val)
		{
			if($inserted_members[$val]) continue;
			$inserted_members[$val] = true;

			unset($obj);
			$obj = new stdClass;
			$obj->member_srl = $val;
			$obj->group_srl = $args->group_srl;
			$obj->site_srl = $args->site_srl;
			$obj->regdate = $date[$obj->member_srl];
			$output = executeQuery('member.addMemberToGroup', $obj);
			if(!$output->toBool()) return $output;

			$this->_clearMemberCache($obj->member_srl, $args->site_srl);
		}

		return new Object();
	}


	/**
	 * Auto-login
	 *
	 * @return void
	 */
	function doAutologin()
	{
		// Get a key value of auto log-in
		$args = new stdClass;
		$args->autologin_key = $_COOKIE['xeak'];
		// Get information of the key
		$output = executeQuery('member.getAutologin', $args);
		// If no information exists, delete a cookie
		if(!$output->toBool() || !$output->data)
		{
			setCookie('xeak',null,$_SERVER['REQUEST_TIME']+60*60*24*365, '/');
			return;
		}

		$oMemberModel = getModel('member');
		$config = $oMemberModel->getMemberConfig();

		$user_id = ($config->identifier == 'user_id') ? $output->data->user_id : $output->data->email_address;
		$password = $output->data->password;

		if(!$user_id || !$password)
		{
			setCookie('xeak',null,$_SERVER['REQUEST_TIME']+60*60*24*365, '/');
			return;
		}

		$do_auto_login = false;

		// Compare key values based on the information
		$check_key = strtolower($user_id).$password.$_SERVER['HTTP_USER_AGENT'];
		$check_key = substr(hash_hmac('sha256', $check_key, substr($args->autologin_key, 0, 32)), 0, 32);

		if($check_key === substr($args->autologin_key, 32))
		{
			// Check change_password_date
			$oModuleModel = getModel('module');
			$member_config = $oModuleModel->getModuleConfig('member');
			$limit_date = $member_config->change_password_date;

			// Check if change_password_date is set
			if($limit_date > 0)
			{
				$oMemberModel = getModel('member');
				$columnList = array('member_srl', 'change_password_date');

				if($config->identifier == 'user_id')
				{
					$member_info = $oMemberModel->getMemberInfoByUserID($user_id, $columnList);
				}
				else
				{
					$member_info = $oMemberModel->getMemberInfoByEmailAddress($user_id, $columnList);
				}

				if($member_info->change_password_date >= date('YmdHis', strtotime('-'.$limit_date.' day')) ){
					$do_auto_login = true;
				}

			}
			else
			{
				$do_auto_login = true;
			}
		}

		if($do_auto_login)
		{
			$output = $this->doLogin($user_id);
		}
		else
		{
			executeQuery('member.deleteAutologin', $args);
			setCookie('xeak',null,$_SERVER['REQUEST_TIME']+60*60*24*365, '/');
		}
	}

	/**
	 * Log-in
	 *
	 * @param string $user_id
	 * @param string $password
	 * @param boolean $keep_signed
	 *
	 * @return Object
	 */
	function doLogin($user_id, $password = '', $keep_signed = false)
	{
		$user_id = strtolower($user_id);
		if(!$user_id) return new Object(-1, 'null_user_id');
		// Call a trigger before log-in (before)
		$trigger_obj = new stdClass();
		$trigger_obj->user_id = $user_id;
		$trigger_obj->password = $password;
		$trigger_output = ModuleHandler::triggerCall('member.doLogin', 'before', $trigger_obj);
		if(!$trigger_output->toBool()) return $trigger_output;
		// Create a member model object
		$oMemberModel = getModel('member');

		// check IP access count.
		$config = $oMemberModel->getMemberConfig();
		$args = new stdClass();
		$args->ipaddress = $_SERVER['REMOTE_ADDR'];

		// check identifier
		if($config->identifier == 'email_address')
		{
			// Get user_id information
			$this->memberInfo = $oMemberModel->getMemberInfoByEmailAddress($user_id);
			// Set an invalid user if no value returned
			if(!$user_id || strtolower($this->memberInfo->email_address) != strtolower($user_id)) return $this->recordLoginError(-1, 'invalid_email_address');

		}
		else
		{
			// Get user_id information
			$this->memberInfo = $oMemberModel->getMemberInfoByUserID($user_id);
			// Set an invalid user if no value returned
			if(!$user_id || strtolower($this->memberInfo->user_id) != strtolower($user_id)) return $this->recordLoginError(-1, 'invalid_user_id');
		}

		$output = executeQuery('member.getLoginCountByIp', $args);
		$errorCount = $output->data->count;
		if($errorCount >= $config->max_error_count)
		{
			$last_update = strtotime($output->data->last_update);
			$term = intval($_SERVER['REQUEST_TIME']-$last_update);
			if($term < $config->max_error_count_time)
			{
				$term = $config->max_error_count_time - $term;
				if($term < 60) $term = intval($term).Context::getLang('unit_sec');
				elseif(60 <= $term && $term < 3600) $term = intval($term/60).Context::getLang('unit_min');
				elseif(3600 <= $term && $term < 86400) $term = intval($term/3600).Context::getLang('unit_hour');
				else $term = intval($term/86400).Context::getLang('unit_day');

				return new Object(-1, sprintf(Context::getLang('excess_ip_access_count'),$term));
			}
			else
			{
				$args->ipaddress = $_SERVER['REMOTE_ADDR'];
				$output = executeQuery('member.deleteLoginCountByIp', $args);
			}
		}

		// Password Check
		if($password && !$oMemberModel->isValidPassword($this->memberInfo->password, $password, $this->memberInfo->member_srl))
		{
			return $this->recordMemberLoginError(-1, 'invalid_password',$this->memberInfo);
		}

		// If denied == 'Y', notify
		if($this->memberInfo->denied == 'Y')
		{
			$args->member_srl = $this->memberInfo->member_srl;
			$output = executeQuery('member.chkAuthMail', $args);
			if ($output->toBool() && $output->data->count != '0')
			{
				$_SESSION['auth_member_srl'] = $this->memberInfo->member_srl;
				$redirectUrl = getUrl('', 'act', 'dispMemberResendAuthMail');
				return $this->setRedirectUrl($redirectUrl, new Object(-1,'msg_user_not_confirmed'));
			}
			return new Object(-1,'msg_user_denied');
		}
		// Notify if denied_date is less than the current time
		if($this->memberInfo->limit_date && substr($this->memberInfo->limit_date,0,8) >= date("Ymd")) return new Object(-9,sprintf(Context::getLang('msg_user_limited'),zdate($this->memberInfo->limit_date,"Y-m-d")));
		// Update the latest login time
		$args->member_srl = $this->memberInfo->member_srl;
		$output = executeQuery('member.updateLastLogin', $args);

		$site_module_info = Context::get('site_module_info');
		$this->_clearMemberCache($args->member_srl, $site_module_info->site_srl);

		// Check if there is recoding table.
		$oDB = &DB::getInstance();
		if($oDB->isTableExists('member_count_history') && $config->enable_login_fail_report != 'N')
		{
			// check if there is login fail records.
			$output = executeQuery('member.getLoginCountHistoryByMemberSrl', $args);
			if($output->data && $output->data->content)
			{
				$title = Context::getLang('login_fail_report');
				$message = '<ul>';
				$content = unserialize($output->data->content);
				if(count($content) > $config->max_error_count)
				{
					foreach($content as $val)
					{
						$message .= '<li>'.Context::getLang('regdate').': '.date('Y-m-d h:i:sa',$val[2]).'<ul><li>'.Context::getLang('ipaddress').': '.$val[0].'</li><li>'.Context::getLang('message').': '.$val[1].'</li></ul></li>';
					}
					$message .= '</ul>';
					$content = sprintf(Context::getLang('login_fail_report_contents'),$message,date('Y-m-d h:i:sa'));

					//send message
					$oCommunicationController = getController('communication');
					$oCommunicationController->sendMessage($args->member_srl, $args->member_srl, $title, $content, true);

					if($this->memberInfo->email_address && $this->memberInfo->allow_mailing == 'Y')
					{
						$view_url = Context::getRequestUri();
						$content = sprintf("%s<hr /><p>From: <a href=\"%s\" target=\"_blank\">%s</a><br />To: %s(%s)</p>",$content, $view_url, $view_url, $this->memberInfo->nick_name, $this->memberInfo->email_id);
						$oMail = new Mail();
						$oMail->setTitle($title);
						$oMail->setContent($content);
						$oMail->setSender($config->webmaster_name?$config->webmaster_name:'webmaster', $config->webmaster_email);
						$oMail->setReceiptor($this->memberInfo->email_id.'('.$this->memberInfo->nick_name.')', $this->memberInfo->email_address);
						$oMail->send();
					}
					$output = executeQuery('member.deleteLoginCountHistoryByMemberSrl', $args);
				}
			}
		}
		// Call a trigger after successfully log-in (after)
		$trigger_output = ModuleHandler::triggerCall('member.doLogin', 'after', $this->memberInfo);
		if(!$trigger_output->toBool()) return $trigger_output;
		// When user checked to use auto-login
		if($keep_signed)
		{
			// Key generate for auto login
			$oPassword = new Password();
			$random_key = $oPassword->createSecureSalt(32, 'hex');
			$extra_key = strtolower($user_id).$this->memberInfo->password.$_SERVER['HTTP_USER_AGENT'];
			$extra_key = substr(hash_hmac('sha256', $extra_key, $random_key), 0, 32);
			$autologin_args = new stdClass;
			$autologin_args->autologin_key = $random_key.$extra_key;
			$autologin_args->member_srl = $this->memberInfo->member_srl;
			executeQuery('member.deleteAutologin', $autologin_args);
			$autologin_output = executeQuery('member.insertAutologin', $autologin_args);
			if($autologin_output->toBool()) setCookie('xeak',$autologin_args->autologin_key, $_SERVER['REQUEST_TIME']+31536000, '/');
		}
		if($this->memberInfo->is_admin == 'Y')
		{
			$oMemberAdminModel = getAdminModel('member');
			if(!$oMemberAdminModel->getMemberAdminIPCheck())
			{
				$_SESSION['denied_admin'] = 'Y';
			}
		}

		$this->setSessionInfo();

		return $output;
	}

	/**
	 * Update or create session information
	 */
	function setSessionInfo()
	{
		$oMemberModel = getModel('member');
		// If your information came through the current session information to extract information from the users
		if(!$this->memberInfo && $_SESSION['member_srl'] && $oMemberModel->isLogged() )
		{
			$this->memberInfo = $oMemberModel->getMemberInfoByMemberSrl($_SESSION['member_srl']);
			// If you do not destroy the session Profile
			if($this->memberInfo->member_srl != $_SESSION['member_srl'])
			{
				$this->destroySessionInfo();
				return;
			}
		}
		// Stop using the session id is destroyed
		if($this->memberInfo->denied=='Y')
		{
			$this->destroySessionInfo();
			return;
		}
		// Log in for treatment sessions set
		$_SESSION['is_logged'] = true;
		$_SESSION['ipaddress'] = $_SERVER['REMOTE_ADDR'];
		$_SESSION['member_srl'] = $this->memberInfo->member_srl;
		$_SESSION['is_admin'] = '';
		setcookie('xe_logged', 'true', 0, '/');
		// Do not save your password in the session jiwojum;;
		//unset($this->memberInfo->password);
		// User Group Settings
		/*
		   if($this->memberInfo->group_list) {
		   $group_srl_list = array_keys($this->memberInfo->group_list);
		   $_SESSION['group_srls'] = $group_srl_list;
		// If the group is designated as an administrator administrator
		$oMemberModel = getModel('member');
		$admin_group = $oMemberModel->getAdminGroup();
		if($admin_group->group_srl && in_array($admin_group->group_srl, $group_srl_list)) $_SESSION['is_admin'] = 'Y';
		}
		 */

		// Information stored in the session login user
		Context::set('is_logged', true);
		Context::set('logged_info', $this->memberInfo);

		// Only the menu configuration of the user (such as an add-on to the menu can be changed)
		$this->addMemberMenu( 'dispMemberInfo', 'cmd_view_member_info');
		$this->addMemberMenu( 'dispMemberScrappedDocument', 'cmd_view_scrapped_document');
		$this->addMemberMenu( 'dispMemberSavedDocument', 'cmd_view_saved_document');
		$this->addMemberMenu( 'dispMemberOwnDocument', 'cmd_view_own_document');
	}

	/**
	 * Logged method for providing a personalized menu
	 * Login information is used in the output widget, or personalized page
	 */
	function addMemberMenu($act, $str)
	{
		$logged_info = Context::get('logged_info');

		$logged_info->menu_list[$act] = Context::getLang($str);

		Context::set('logged_info', $logged_info);
	}

	/**
	 * Nickname and click Log In to add a pop-up menu that appears when the method
	 */
	function addMemberPopupMenu($url, $str, $icon = '', $target = 'self')
	{
		$member_popup_menu_list = Context::get('member_popup_menu_list');
		if(!is_array($member_popup_menu_list)) $member_popup_menu_list = array();

		$obj = new stdClass;
		$obj->url = $url;
		$obj->str = $str;
		$obj->icon = $icon;
		$obj->target = $target;
		$member_popup_menu_list[] = $obj;

		Context::set('member_popup_menu_list', $member_popup_menu_list);
	}

	/**
	 * Add users to the member table
	 */
	function insertMember(&$args, $password_is_hashed = false)
	{
		// Call a trigger (before)
		$output = ModuleHandler::triggerCall('member.insertMember', 'before', $args);
		if(!$output->toBool()) return $output;
		// Terms and Conditions portion of the information set up by members reaffirmed
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');

		$logged_info = Context::get('logged_info');
		// If the date of the temporary restrictions limit further information on the date of
		if($config->limit_day) $args->limit_date = date("YmdHis", $_SERVER['REQUEST_TIME']+$config->limit_day*60*60*24);

		$args->member_srl = getNextSequence();
		$args->list_order = -1 * $args->member_srl;

		// Execute insert or update depending on the value of member_srl
		if(!$args->user_id) $args->user_id = 't'.$args->member_srl;
		// Enter the user's identity changed to lowercase
		else $args->user_id = strtolower($args->user_id);
		if(!$args->user_name) $args->user_name = $args->member_srl;
		if(!$args->nick_name) $args->nick_name = $args->member_srl;

		// Control of essential parameters
		if($args->allow_mailing!='Y') $args->allow_mailing = 'N';
		if($args->denied!='Y') $args->denied = 'N';
		$args->allow_message= 'Y';

		if($logged_info->is_admin == 'Y')
		{
			if($args->is_admin!='Y') $args->is_admin = 'N';
		}
		else
		{
			unset($args->is_admin);
		}

		list($args->email_id, $args->email_host) = explode('@', $args->email_address);

		// Sanitize user ID, username, nickname, homepage, blog
		$args->user_id = htmlspecialchars($args->user_id, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->user_name = htmlspecialchars($args->user_name, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->nick_name = htmlspecialchars($args->nick_name, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->homepage = htmlspecialchars($args->homepage, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->blog = htmlspecialchars($args->blog, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		if($args->homepage && !preg_match("/^[a-z]+:\/\//i",$args->homepage)) $args->homepage = 'http://'.$args->homepage;
		if($args->blog && !preg_match("/^[a-z]+:\/\//i",$args->blog)) $args->blog = 'http://'.$args->blog;

		// Create a model object
		$oMemberModel = getModel('member');

		// Check password strength
		if($args->password && !$password_is_hashed)
		{
			if(!$oMemberModel->checkPasswordStrength($args->password, $config->password_strength))
			{
				$message = Context::getLang('about_password_strength');
				return new Object(-1, $message[$config->password_strength]);
			}
			$args->password = $oMemberModel->hashPassword($args->password);
		}
		elseif(!$args->password)
		{
			unset($args->password);
		}

		// Check if ID is prohibited
		if($oMemberModel->isDeniedID($args->user_id))
		{
			return new Object(-1,'denied_user_id');
		}

		// Check if ID is duplicate
		$member_srl = $oMemberModel->getMemberSrlByUserID($args->user_id);
		if($member_srl)
		{
			return new Object(-1,'msg_exists_user_id');
		}

		// Check if nickname is prohibited
		if($oMemberModel->isDeniedNickName($args->nick_name))
		{
			return new Object(-1,'denied_nick_name');
		}

		// Check if nickname is duplicate
		$member_srl = $oMemberModel->getMemberSrlByNickName($args->nick_name);
		if($member_srl)
		{
			return new Object(-1,'msg_exists_nick_name');
		}

		// Check if email address is duplicate
		$member_srl = $oMemberModel->getMemberSrlByEmailAddress($args->email_address);
		if($member_srl)
		{
			return new Object(-1,'msg_exists_email_address');
		}

		// Insert data into the DB
		$args->list_order = -1 * $args->member_srl;

		if(!$args->user_id) $args->user_id = 't'.$args->member_srl;
		if(!$args->user_name) $args->user_name = $args->member_srl;

		$oDB = &DB::getInstance();
		$oDB->begin();

		$output = executeQuery('member.insertMember', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		if(is_array($args->group_srl_list)) $group_srl_list = $args->group_srl_list;
		else $group_srl_list = explode('|@|', $args->group_srl_list);
		// If no value is entered the default group, the value of group registration
		if(!$args->group_srl_list)
		{
			$columnList = array('site_srl', 'group_srl');
			$default_group = $oMemberModel->getDefaultGroup(0, $columnList);
			if($default_group)
			{
				// Add to the default group
				$output = $this->addMemberToGroup($args->member_srl,$default_group->group_srl);
				if(!$output->toBool())
				{
					$oDB->rollback();
					return $output;
				}
			}
			// If the value is the value of the group entered the group registration
		}
		else
		{
			for($i=0;$i<count($group_srl_list);$i++)
			{
				$output = $this->addMemberToGroup($args->member_srl,$group_srl_list[$i]);

				if(!$output->toBool())
				{
					$oDB->rollback();
					return $output;
				}
			}
		}

		$member_config = $oModuleModel->getModuleConfig('member');
		// When using email authentication mode (when you subscribed members denied a) certified mail sent
		if($args->denied == 'Y')
		{
			// Insert data into the authentication DB
			$oPassword = new Password();
			$auth_args = new stdClass();
			$auth_args->user_id = $args->user_id;
			$auth_args->member_srl = $args->member_srl;
			$auth_args->new_password = $args->password;
			$auth_args->auth_key = $oPassword->createSecureSalt(40);
			$auth_args->is_register = 'Y';

			$output = executeQuery('member.insertAuthMail', $auth_args);
			if(!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
			$this->_sendAuthMail($auth_args, $args);
		}
		// Call a trigger (after)
		if($output->toBool())
		{
			$trigger_output = ModuleHandler::triggerCall('member.insertMember', 'after', $args);
			if(!$trigger_output->toBool())
			{
				$oDB->rollback();
				return $trigger_output;
			}
		}

		$oDB->commit(true);

		$output->add('member_srl', $args->member_srl);
		return $output;
	}

	/**
	 * Modify member information
	 *
	 * @param bool $is_admin , modified 2013-11-22
	 */
	function updateMember($args, $is_admin = FALSE)
	{
		// Call a trigger (before)
		$output = ModuleHandler::triggerCall('member.updateMember', 'before', $args);
		if(!$output->toBool()) return $output;
		// Create a model object
		$oMemberModel = getModel('member');

		$logged_info = Context::get('logged_info');
		// Get what you want to modify the original information
		if(!$this->memberInfo) $this->memberInfo = $oMemberModel->getMemberInfoByMemberSrl($args->member_srl);
		// Control of essential parameters
		if($args->allow_mailing!='Y') $args->allow_mailing = 'N';
		if($args->allow_message && !in_array($args->allow_message, array('Y','N','F'))) $args->allow_message = 'Y';

		if($logged_info->is_admin == 'Y')
		{
			if($args->denied!='Y') $args->denied = 'N';
			if($args->is_admin!='Y' && $logged_info->member_srl != $args->member_srl) $args->is_admin = 'N';
		}
		else
		{
			unset($args->is_admin);
			if($is_admin == false)
				unset($args->denied);
			if($logged_info->member_srl != $args->member_srl && $is_admin == false)
			{
				return $this->stop('msg_invalid_request');
			}
		}

		// Sanitize user ID, username, nickname, homepage, blog
		if($args->user_id) $args->user_id = htmlspecialchars($args->user_id, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->user_name = htmlspecialchars($args->user_name, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->nick_name = htmlspecialchars($args->nick_name, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->homepage = htmlspecialchars($args->homepage, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->blog = htmlspecialchars($args->blog, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		if($args->homepage && !preg_match("/^[a-z]+:\/\//is",$args->homepage)) $args->homepage = 'http://'.$args->homepage;
		if($args->blog && !preg_match("/^[a-z]+:\/\//is",$args->blog)) $args->blog = 'http://'.$args->blog;

		// check member identifier form
		$config = $oMemberModel->getMemberConfig();

		$output = executeQuery('member.getMemberInfoByMemberSrl', $args);
		$orgMemberInfo = $output->data;

		// Check if email address or user ID is duplicate
		if($config->identifier == 'email_address')
		{
			$member_srl = $oMemberModel->getMemberSrlByEmailAddress($args->email_address);
			if($member_srl && $args->member_srl != $member_srl)
			{
				return new Object(-1,'msg_exists_email_address');
			}
			$args->email_address = $orgMemberInfo->email_address;
		}
		else
		{
			$member_srl = $oMemberModel->getMemberSrlByUserID($args->user_id);
			if($member_srl && $args->member_srl != $member_srl)
			{
				return new Object(-1,'msg_exists_user_id');
			}

			$args->user_id = $orgMemberInfo->user_id;
		}

		if($logged_info->is_admin !== 'Y')
		{
			// Check if ID is prohibited
			if($args->user_id && $oMemberModel->isDeniedID($args->user_id))
			{
				return new Object(-1,'denied_user_id');
			}

			// Check if nickname is prohibited
			if($args->nick_name && $oMemberModel->isDeniedNickName($args->nick_name))
			{
				return new Object(-1, 'denied_nick_name');
			}
		}

		// Check if ID is duplicate
		if($args->user_id)
		{
			$member_srl = $oMemberModel->getMemberSrlByUserID($args->user_id);
			if($member_srl && $args->member_srl != $member_srl)
			{
				return new Object(-1,'msg_exists_user_id');
			}
		}

		// Check if nickname is duplicate
		$member_srl = $oMemberModel->getMemberSrlByNickName($args->nick_name);
 		if($member_srl && $args->member_srl != $member_srl)
 		{
 			return new Object(-1,'msg_exists_nick_name');
 		}

		list($args->email_id, $args->email_host) = explode('@', $args->email_address);

		$oDB = &DB::getInstance();
		$oDB->begin();

		// Check password strength
		if($args->password)
		{
			if(!$oMemberModel->checkPasswordStrength($args->password, $config->password_strength))
			{
				$message = Context::getLang('about_password_strength');
				return new Object(-1, $message[$config->password_strength]);
			}
			$args->password = $oMemberModel->hashPassword($args->password);
		}
		else
		{
			$args->password = $orgMemberInfo->password;
		}
		
		if(!$args->user_name) $args->user_name = $orgMemberInfo->user_name;
		if(!$args->user_id) $args->user_id = $orgMemberInfo->user_id;
		if(!$args->nick_name) $args->nick_name = $orgMemberInfo->nick_name;
		if(!$args->description) $args->description = '';
		if(!$args->birthday) $args->birthday = '';

		$output = executeQuery('member.updateMember', $args);

		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		if($args->group_srl_list)
		{
			if(is_array($args->group_srl_list)) $group_srl_list = $args->group_srl_list;
			else $group_srl_list = explode('|@|', $args->group_srl_list);
			// If the group information, group information changes
			if(count($group_srl_list) > 0)
			{
				$args->site_srl = 0;
				// One of its members to delete all the group
				$output = executeQuery('member.deleteMemberGroupMember', $args);
				if(!$output->toBool())
				{
					$oDB->rollback();
					return $output;
				}
				// Enter one of the loop a
				for($i=0;$i<count($group_srl_list);$i++)
				{
					$output = $this->addMemberToGroup($args->member_srl,$group_srl_list[$i]);
					if(!$output->toBool())
					{
						$oDB->rollback();
						return $output;
					}
				}

				// if group is changed, point changed too.
				$this->_updatePointByGroup($orgMemberInfo->member_srl, $group_srl_list);
			}
		}
		// Call a trigger (after)
		if($output->toBool()) {
			$trigger_output = ModuleHandler::triggerCall('member.updateMember', 'after', $args);
			if(!$trigger_output->toBool())
			{
				$oDB->rollback();
				return $trigger_output;
			}
		}

		$oDB->commit();

		//remove from cache
		$this->_clearMemberCache($args->member_srl, $args->site_srl);

		// Save Session
		if(!$this->memberInfo) $this->memberInfo = $oMemberModel->getMemberInfoByMemberSrl($args->member_srl);
		$logged_info = Context::get('logged_info');

		$output->add('member_srl', $args->member_srl);
		return $output;
	}

	/**
	 * Modify member password
	 */
	function updateMemberPassword($args)
	{
		if($args->password)
		{

			// check password strength
			$oMemberModel = getModel('member');
			$config = $oMemberModel->getMemberConfig();

			if(!$oMemberModel->checkPasswordStrength($args->password, $config->password_strength))
			{
				$message = Context::getLang('about_password_strength');
				return new Object(-1, $message[$config->password_strength]);
			}

			$args->password = $oMemberModel->hashPassword($args->password);
		}
		else if($args->hashed_password)
		{
			$args->password = $args->hashed_password;
		}

		$output = executeQuery('member.updateMemberPassword', $args);
		if($output->toBool())
		{
			$result = executeQuery('member.updateChangePasswordDate', $args);
		}

		$this->_clearMemberCache($args->member_srl);

		return $output;
	}

	/**
	 * Delete User
	 */
	function deleteMember($member_srl)
	{
		// Call a trigger (before)
		$trigger_obj = new stdClass();
		$trigger_obj->member_srl = $member_srl;
		$output = ModuleHandler::triggerCall('member.deleteMember', 'before', $trigger_obj);
		if(!$output->toBool()) return $output;
		// Create a model object
		$oMemberModel = getModel('member');
		// Bringing the user's information
		if(!$this->memberInfo || $this->memberInfo->member_srl != $member_srl || !isset($this->memberInfo->is_admin))
		{
			$columnList = array('member_srl', 'is_admin');
			$this->memberInfo = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);
		}
		if(!$this->memberInfo) return new Object(-1, 'msg_not_exists_member');
		// If managers can not be deleted
		if($this->memberInfo->is_admin == 'Y') return new Object(-1, 'msg_cannot_delete_admin');

		$oDB = &DB::getInstance();
		$oDB->begin();

		$args = new stdClass();
		$args->member_srl = $member_srl;
		// Delete the entries in member_auth_mail
		$output = executeQuery('member.deleteAuthMail', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		// TODO: If the table is not an upgrade may fail.
		/*
		   if(!$output->toBool()) {
		   $oDB->rollback();
		   return $output;
		   }
		 */
		// Delete the entries in member_group_member
		$output = executeQuery('member.deleteMemberGroupMember', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}
		// member removed from the table
		$output = executeQuery('member.deleteMember', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}
		// Call a trigger (after)
		if($output->toBool())
		{
			$trigger_output = ModuleHandler::triggerCall('member.deleteMember', 'after', $trigger_obj);
			if(!$trigger_output->toBool())
			{
				$oDB->rollback();
				return $trigger_output;
			}
		}

		$oDB->commit();
		// Name, image, image, mark, sign, delete
		$this->procMemberDeleteImageName($member_srl);
		$this->procMemberDeleteImageMark($member_srl);
		$this->procMemberDeleteProfileImage($member_srl);
		$this->delSignature($member_srl);

		$this->_clearMemberCache($member_srl);

		return $output;
	}

	/**
	 * Destroy all session information
	 */
	function destroySessionInfo()
	{
		if(!$_SESSION || !is_array($_SESSION)) return;

		$memberInfo = Context::get('logged_info');
		$memberSrl = $memberInfo->member_srl;

		foreach($_SESSION as $key => $val)
		{
			$_SESSION[$key] = '';
		}

		session_destroy();
		setcookie(session_name(), '', $_SERVER['REQUEST_TIME']-42000, '/');
		setcookie('sso','',$_SERVER['REQUEST_TIME']-42000, '/');
		setcookie('xeak','',$_SERVER['REQUEST_TIME']-42000, '/');
		setcookie('xe_logged', 'false', $_SERVER['REQUEST_TIME'] - 42000, '/');

		if($memberSrl || $_COOKIE['xeak'])
		{
			$args = new stdClass();
			$args->member_srl = $memberSrl;
			$args->autologin_key = $_COOKIE['xeak'];
			$output = executeQuery('member.deleteAutologin', $args);
		}
	}

	function _updatePointByGroup($memberSrl, $groupSrlList)
	{
		$oModuleModel = getModel('module');
		$pointModuleConfig = $oModuleModel->getModuleConfig('point');
		$pointGroup = $pointModuleConfig->point_group;

		$levelGroup = array();
		if(is_array($pointGroup) && count($pointGroup)>0)
		{
			$levelGroup = array_flip($pointGroup);
			ksort($levelGroup);
		}
		$maxLevel = 0;
		$resultGroup = array_intersect($levelGroup, $groupSrlList);
		if(count($resultGroup) > 0)
			$maxLevel = max(array_flip($resultGroup));

		if($maxLevel > 0)
		{
			$oPointModel = getModel('point');
			$originPoint = $oPointModel->getPoint($memberSrl);

			if($pointModuleConfig->level_step[$maxLevel] > $originPoint)
			{
				$oPointController = getController('point');
				$oPointController->setPoint($memberSrl, $pointModuleConfig->level_step[$maxLevel], 'update');
			}
		}
	}

	function procMemberModifyEmailAddress()
	{
		if(!Context::get('is_logged')) return $this->stop('msg_not_logged');

		$member_info = Context::get('logged_info');
		$newEmail = Context::get('email_address');

		if(!$newEmail) return $this->stop('msg_invalid_request');

		$oMemberModel = getModel('member');
		$member_srl = $oMemberModel->getMemberSrlByEmailAddress($newEmail);
		if($member_srl) return new Object(-1,'msg_exists_email_address');

		if($_SESSION['rechecked_password_step'] != 'INPUT_DATA')
		{
			return $this->stop('msg_invalid_request');
		}
		unset($_SESSION['rechecked_password_step']);

		$oPassword = new Password();
		$auth_args = new stdClass();
		$auth_args->user_id = $newEmail;
		$auth_args->member_srl = $member_info->member_srl;
		$auth_args->auth_key = $oPassword->createSecureSalt(40);
		$auth_args->new_password = 'XE_change_emaill_address';

		$oDB = &DB::getInstance();
		$oDB->begin();
		$output = executeQuery('member.insertAuthMail', $auth_args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$oModuleModel = getModel('module');
		$member_config = $oModuleModel->getModuleConfig('member');

		$tpl_path = sprintf('%sskins/%s', $this->module_path, $member_config->skin);
		if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');

		global $lang;

		$memberInfo = array();
		$memberInfo[$lang->email_address] = $member_info->email_address;
		$memberInfo[$lang->nick_name] = $member_info->nick_name;

		Context::set('memberInfo', $memberInfo);

		Context::set('newEmail', $newEmail);

		$auth_url = getFullUrl('','module','member','act','procMemberAuthEmailAddress','member_srl',$member_info->member_srl, 'auth_key',$auth_args->auth_key);
		Context::set('auth_url', $auth_url);

		$oTemplate = &TemplateHandler::getInstance();
		$content = $oTemplate->compile($tpl_path, 'confirm_member_new_email');

		$oMail = new Mail();
		$oMail->setTitle( Context::getLang('title_modify_email_address') );
		$oMail->setContent($content);
		$oMail->setSender( $member_config->webmaster_name?$member_config->webmaster_name:'webmaster', $member_config->webmaster_email);
		$oMail->setReceiptor( $member_info->nick_name, $newEmail );
		$result = $oMail->send();

		$msg = sprintf(Context::getLang('msg_confirm_mail_sent'), $newEmail);
		$this->setMessage($msg);

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', '');
		$this->setRedirectUrl($returnUrl);
	}

	function procMemberAuthEmailAddress()
	{
		$member_srl = Context::get('member_srl');
		$auth_key = Context::get('auth_key');
		if(!$member_srl || !$auth_key) return $this->stop('msg_invalid_request');

		// Test logs for finding password by user_id and authkey
		$args = new stdClass;
		$args->member_srl = $member_srl;
		$args->auth_key = $auth_key;
		$output = executeQuery('member.getAuthMail', $args);
		if(!$output->toBool() || $output->data->auth_key != $auth_key)
		{
			if(strlen($output->data->auth_key) !== strlen($auth_key)) executeQuery('member.deleteAuthChangeEmailAddress', $args);
			return $this->stop('msg_invalid_modify_email_auth_key');
		}

		$newEmail = $output->data->user_id;
		$args->email_address = $newEmail;
		list($args->email_id, $args->email_host) = explode('@', $newEmail);

		$output = executeQuery('member.updateMemberEmailAddress', $args);
		if(!$output->toBool()) return $this->stop($output->getMessage());

		// Remove all values having the member_srl and new_password equal to 'XE_change_emaill_address' from authentication table
		executeQuery('member.deleteAuthChangeEmailAddress',$args);

		$this->_clearMemberCache($args->member_srl);

		// Notify the result
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('msg_success_modify_email_address');
	}

	/**
	 * trigger for document.getDocumentMenu. Append to popup menu a button for procMemberSpammerManage()
	 *
	 * @param array &$menu_list
	 *
	 * @return object
	**/
	function triggerGetDocumentMenu(&$menu_list)
	{
		if(!Context::get('is_logged')) return new Object();

		$logged_info = Context::get('logged_info');
		$document_srl = Context::get('target_srl');

		$oDocumentModel = getModel('document');
		$columnList = array('document_srl', 'module_srl', 'member_srl', 'ipaddress');
		$oDocument = $oDocumentModel->getDocument($document_srl, false, false, $columnList);
		$member_srl = $oDocument->get('member_srl');
		$module_srl = $oDocument->get('module_srl');

		if(!$member_srl) return new Object();
		if($oDocumentModel->grant->manager != 1 || $member_srl==$logged_info->member_srl) return new Object();

		$oDocumentController = getController('document');
		$url = getUrl('','module','member','act','dispMemberSpammer','member_srl',$member_srl,'module_srl',$module_srl);
		$oDocumentController->addDocumentPopupMenu($url,'cmd_spammer','','popup');

		return new Object();
	}

	/**
	 * trigger for comment.getCommentMenu. Append to popup menu a button for procMemberSpammerManage()
	 *
	 * @param array &$menu_list
	 *
	 * @return object
	**/
	function triggerGetCommentMenu(&$menu_list)
	{
		if(!Context::get('is_logged')) return new Object();

		$logged_info = Context::get('logged_info');
		$comment_srl = Context::get('target_srl');

		$oCommentModel = getModel('comment');
		$columnList = array('comment_srl', 'module_srl', 'member_srl', 'ipaddress');
		$oComment = $oCommentModel->getComment($comment_srl, FALSE, $columnList);
		$module_srl = $oComment->get('module_srl');
		$member_srl = $oComment->get('member_srl');

		if(!$member_srl) return new Object();
		if($oCommentModel->grant->manager != 1 || $member_srl==$logged_info->member_srl) return new Object();

		$oCommentController = getController('comment');
		$url = getUrl('','module','member','act','dispMemberSpammer','member_srl',$member_srl,'module_srl',$module_srl);
		$oCommentController->addCommentPopupMenu($url,'cmd_spammer','','popup');

		return new Object();
	}

	/**
	 * Spammer manage. Denied user login. And delete or trash all documents. Response Ajax string
	 *
	 * @return object
	**/
	function procMemberSpammerManage()
	{
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');

		$logged_info = Context::get('logged_info');
		$member_srl = Context::get('member_srl');
		$module_srl = Context::get('module_srl');
		$cnt_loop = Context::get('cnt_loop');
		$proc_type = Context::get('proc_type');
		$isMoveToTrash = true;
		if($proc_type == "delete")
			$isMoveToTrash = false;

		// check grant
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		$grant = $oModuleModel->getGrant($module_info, $logged_info);

		if(!$grant->manager) return new Object(-1,'msg_not_permitted');

		$proc_msg = "";

		$oDocumentModel = getModel('document');
		$oCommentModel = getModel('comment');

		// delete or trash destination
		// proc member
		if($cnt_loop == 1)
			$this->_spammerMember($member_srl);
		// proc document and comment
		elseif($cnt_loop>1)
			$this->_spammerDocuments($member_srl, $isMoveToTrash);

		// get destination count
		$cnt_document = $oDocumentModel->getDocumentCountByMemberSrl($member_srl);
		$cnt_comment = $oCommentModel->getCommentCountByMemberSrl($member_srl);

		$total_count = Context::get('total_count');
		$remain_count = $cnt_document + $cnt_comment;
		if($cnt_loop == 1) $total_count = $remain_count;

		// get progress percent
		if($total_count > 0)
			$progress = intval( ( ( $total_count - $remain_count ) / $total_count ) * 100 );
		else
			$progress = 100;

		$this->add('total_count', $total_count);
		$this->add('remain_count', $remain_count);
		$this->add('progress', $progress);
		$this->add('member_srl', $member_srl);
		$this->add('module_srl', $module_srl);
		$this->add('cnt_loop', ++$cnt_loop);
		$this->add('proc_type', $proc_type);

		return new Object(0);
	}

	/**
	 * Denied user login and write description
	 *
	 * @param int $member_srl
	 *
	 * @return object
	**/
	private function _spammerMember($member_srl) {
		$logged_info = Context::get('logged_info');
		$spam_description = trim( Context::get('spam_description') );

		$oMemberModel = getModel('member');
		$columnList = array('member_srl', 'email_address', 'user_id', 'nick_name', 'description');
		// get member current infomation
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl, 0, $columnList);

		$oDocumentModel = getModel('document');
		$oCommentModel = getModel('comment');
		$cnt_comment = $oCommentModel->getCommentCountByMemberSrl($member_srl);
		$cnt_document = $oDocumentModel->getDocumentCountByMemberSrl($member_srl);
		$total_count = $cnt_comment + $cnt_document;

		$args = new stdClass();
		$args->member_srl = $member_info->member_srl;
		$args->email_address = $member_info->email_address;
		$args->user_id = $member_info->user_id;
		$args->nick_name = $member_info->nick_name;
		$args->denied = "Y";
		$args->description = trim( $member_info->description );
		if( $args->description != "" ) $args->description .= "\n";	// add new line

		$args->description .= Context::getLang('cmd_spammer') . "[" . date("Y-m-d H:i:s") . " from:" . $logged_info->user_id . " info:" . $spam_description . " docuemnts count:" . $total_count . "]";

		$output = $this->updateMember($args, true);

		$this->_clearMemberCache($args->member_srl);

		return $output;
	}

	/**
	 * Delete or trash all documents
	 *
	 * @param int $member_srl
	 * @param bool $isMoveToTrash
	 *
	 * @return object
	**/
	private function _spammerDocuments($member_srl, $isMoveToTrash) {
		$oDocumentController = getController('document');
		$oDocumentModel = getModel('document');
		$oCommentController = getController('comment');
		$oCommentModel = getModel('comment');

		// delete count by one request
		$getContentsCount = 10;

		// 1. proc comment, 2. proc document
		$cnt_comment = $oCommentModel->getCommentCountByMemberSrl($member_srl);
		$cnt_document = $oDocumentModel->getDocumentCountByMemberSrl($member_srl);
		if($cnt_comment > 0)
		{
			$columnList = array();
			$commentList = $oCommentModel->getCommentListByMemberSrl($member_srl, $columnList, 0, false, $getContentsCount);
			if($commentList) {
				foreach($commentList as $v) {
					$oCommentController->deleteComment($v->comment_srl, true, $isMoveToTrash);
				}
			}
		} elseif($cnt_document > 0) {
			$columnList = array();
			$documentList = $oDocumentModel->getDocumentListByMemberSrl($member_srl, $columnList, 0, false, $getContentsCount);
			if($documentList) {
				foreach($documentList as $v) {
					if($isMoveToTrash) $oDocumentController->moveDocumentToTrash($v);
					else $oDocumentController->deleteDocument($v->document_srl);
				}
			}
		}

		return array();
	}

	function _clearMemberCache($member_srl, $site_srl = 0)
	{
		$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
		if($oCacheHandler->isSupport())
		{
			$object_key = 'member_groups:' . getNumberingPath($member_srl) . $member_srl . '_' . $site_srl;
			$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
			$oCacheHandler->delete($cache_key);

			if($site_srl !== 0)
			{
				$object_key = 'member_groups:' . getNumberingPath($member_srl) . $member_srl . '_0';
				$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
				$oCacheHandler->delete($cache_key);
			}
		}

		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$object_key = 'member_info:' . getNumberingPath($member_srl) . $member_srl;
			$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
			$oCacheHandler->delete($cache_key);
		}
	}
}
/* End of file member.controller.php */
/* Location: ./modules/member/member.controller.php */
