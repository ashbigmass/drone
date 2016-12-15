<?php
class memberModel extends member
{
	var $join_form_list = NULL;

	function init() {
	}

	function getMemberConfig() {
		static $member_config;
		if($member_config) return $member_config;
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('member');
		if(!$config->signupForm || !is_array($config->signupForm)) {
			$oMemberAdminController = getAdminController('member');
			$identifier = ($config->identifier) ? $config->identifier : 'email_address';
			$config->signupForm = $oMemberAdminController->createSignupForm($identifier);
		}
		foreach($config->signupForm AS $key=>$value) {
			$config->signupForm[$key]->title = ($value->isDefaultForm) ? Context::getLang($value->name) : $value->title;
			if($config->signupForm[$key]->isPublic != 'N') $config->signupForm[$key]->isPublic = 'Y';
			if($value->name == 'find_account_question') $config->signupForm[$key]->isPublic = 'N';
		}
		$config->agreement = memberModel::_getAgreement();
		if(!$config->webmaster_name) $config->webmaster_name = 'webmaster';
		if(!$config->image_name_max_width) $config->image_name_max_width = 90;
		if(!$config->image_name_max_height) $config->image_name_max_height = 20;
		if(!$config->image_mark_max_width) $config->image_mark_max_width = 20;
		if(!$config->image_mark_max_height) $config->image_mark_max_height = 20;
		if(!$config->profile_image_max_width) $config->profile_image_max_width = 90;
		if(!$config->profile_image_max_height) $config->profile_image_max_height = 90;
		if(!$config->skin) $config->skin = 'default';
		if(!$config->colorset) $config->colorset = 'white';
		if(!$config->editor_skin || $config->editor_skin == 'default') $config->editor_skin = 'ckeditor';
		if(!$config->group_image_mark) $config->group_image_mark = "N";
		if(!$config->identifier) $config->identifier = 'user_id';
		if(!$config->max_error_count) $config->max_error_count = 10;
		if(!$config->max_error_count_time) $config->max_error_count_time = 300;
		if(!$config->signature_editor_skin || $config->signature_editor_skin == 'default') $config->signature_editor_skin = 'ckeditor';
		if(!$config->sel_editor_colorset) $config->sel_editor_colorset = 'moono';
		$member_config = $config;
		return $config;
	}

	function _getAgreement() {
		$agreement_file = _XE_PATH_.'files/member_extra_info/agreement_' . Context::get('lang_type') . '.txt';
		if(is_readable($agreement_file)) return FileHandler::readFile($agreement_file);
		$db_info = Context::getDBInfo();
		$agreement_file = _XE_PATH_.'files/member_extra_info/agreement_' . $db_info->lang_type . '.txt';
		if(is_readable($agreement_file)) return FileHandler::readFile($agreement_file);
		$lang_selected = Context::loadLangSelected();
		foreach($lang_selected as $key => $val) {
			$agreement_file = _XE_PATH_.'files/member_extra_info/agreement_' . $key . '.txt';
			if(is_readable($agreement_file)) return FileHandler::readFile($agreement_file);
		}
		return null;
	}

	function getMemberMenu() {
		$member_srl = Context::get('target_srl');
		$mid = Context::get('cur_mid');
		$logged_info = Context::get('logged_info');
		$act = Context::get('cur_act');
		if($member_srl == $logged_info->member_srl) $member_info = $logged_info;
		else $member_info = $this->getMemberInfoByMemberSrl($member_srl);
		$member_srl = $member_info->member_srl;
		if(!$member_srl) return;
		$user_id = $member_info->user_id;
		$user_name = $member_info->user_name;
		ModuleHandler::triggerCall('member.getMemberMenu', 'before', $null);
		$oMemberController = getController('member');
		if($logged_info->member_srl) {
			$url = getUrl('','mid',$mid,'act','dispMemberInfo','member_srl',$member_srl);
			$oMemberController->addMemberPopupMenu($url,'cmd_view_member_info',$icon_path,'self');
		}
		if($member_srl != $logged_info->member_srl && $logged_info->member_srl) {
			foreach($this->module_config->signupForm as $field) {
				if($field->name == 'email_address') {
					$email_config = $field;
					break;
				}
			}
			if(($logged_info->is_admin == 'Y' || $email_config->isPublic == 'Y') && $member_info->email_address) {
				$url = 'mailto:'.htmlspecialchars($member_info->email_address, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
				$oMemberController->addMemberPopupMenu($url,'cmd_send_email',$icon_path);
			}
		}
		if($member_info->homepage) $oMemberController->addMemberPopupMenu(htmlspecialchars($member_info->homepage, ENT_COMPAT | ENT_HTML401, 'UTF-8', false), 'homepage', '', 'blank');
		if($member_info->blog) $oMemberController->addMemberPopupMenu(htmlspecialchars($member_info->blog, ENT_COMPAT | ENT_HTML401, 'UTF-8', false), 'blog', '', 'blank');
		ModuleHandler::triggerCall('member.getMemberMenu', 'after', $null);
		if($logged_info->is_admin == 'Y') {
			$url = getUrl('','module','admin','act','dispMemberAdminInsert','member_srl',$member_srl);
			$oMemberController->addMemberPopupMenu($url,'cmd_manage_member_info',$icon_path,'MemberModifyInfo');
			$url = getUrl('','module','admin','act','dispDocumentAdminList','search_target','member_srl','search_keyword',$member_srl);
			$oMemberController->addMemberPopupMenu($url,'cmd_trace_document',$icon_path,'TraceMemberDocument');
			$url = getUrl('','module','admin','act','dispCommentAdminList','search_target','member_srl','search_keyword',$member_srl);
			$oMemberController->addMemberPopupMenu($url,'cmd_trace_comment',$icon_path,'TraceMemberComment');
		}
		$menus = Context::get('member_popup_menu_list');
		$menus_count = count($menus);
		for($i=0;$i<$menus_count;$i++) $menus[$i]->str = Context::getLang($menus[$i]->str);
		$this->add('menus', $menus);
	}

	function isLogged() {
		if($_SESSION['is_logged']) {
			if(Mobile::isFromMobilePhone()) {
				return true;
			} elseif(filter_var($_SESSION['ipaddress'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				if(strncmp(inet_pton($_SESSION['ipaddress']), inet_pton($_SERVER['REMOTE_ADDR']), 6) == 0) return true;
			} else {
				if(ip2long($_SESSION['ipaddress']) >> 8 == ip2long($_SERVER['REMOTE_ADDR']) >> 8) return true;
			}
		}
		$_SESSION['is_logged'] = false;
		return false;
	}

	function getLoggedInfo() {
		if($this->isLogged()) {
			$logged_info = Context::get('logged_info');
			$site_module_info = Context::get('site_module_info');
			if($site_module_info->site_srl) {
				$logged_info->group_list = $this->getMemberGroups($logged_info->member_srl, $site_module_info->site_srl);
				$oModuleModel = getModel('module');
				if($oModuleModel->isSiteAdmin($logged_info)) $logged_info->is_site_admin = true;
				else $logged_info->is_site_admin = false;
			} else {
				if(count($logged_info->group_list) === 0) {
					$default_group = $this->getDefaultGroup(0);
					$oMemberController = getController('member');
					$oMemberController->addMemberToGroup($logged_info->member_srl, $default_group->group_srl, 0);
					$groups[$default_group->group_srl] = $default_group->title;
					$logged_info->group_list = $groups;
				}
				$logged_info->is_site_admin = false;
			}
			Context::set('logged_info', $logged_info);
			return $logged_info;
		}
		return NULL;
	}

	function getMemberInfoByUserID($user_id, $columnList = array()) {
		if(!$user_id) return;
		$args = new stdClass;
		$args->user_id = $user_id;
		$output = executeQuery('member.getMemberInfo', $args);
		if(!$output->toBool()) return $output;
		if(!$output->data) return;
		$member_info = $this->arrangeMemberInfo($output->data);
		return $member_info;
	}

	function getMemberInfoByEmailAddress($email_address) {
		if(!$email_address) return;
		$args = new stdClass();
			$db_info = Context::getDBInfo ();
		if($db_info->master_db['db_type'] == "cubrid") {
			$args->email_address = strtolower($email_address);
			$output = executeQuery('member.getMemberInfoByEmailAddressForCubrid', $args);
		} else {
			$args->email_address = $email_address;
			$output = executeQuery('member.getMemberInfoByEmailAddress', $args);
		}
		if(!$output->toBool()) return $output;
		if(!$output->data) return;
		$member_info = $this->arrangeMemberInfo($output->data);
		return $member_info;
	}

	function getMemberInfoByMemberSrl($member_srl, $site_srl = 0, $columnList = array()) {
		if(!$member_srl) return;
		if(!$GLOBALS['__member_info__'][$member_srl] || count($columnList) == 0) {
			$GLOBALS['__member_info__'][$member_srl] = false;
			$oCacheHandler = CacheHandler::getInstance('object');
			if($oCacheHandler->isSupport()) {
				$columnList = array();
				$object_key = 'member_info:' . getNumberingPath($member_srl) . $member_srl;
				$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
				$GLOBALS['__member_info__'][$member_srl] = $oCacheHandler->get($cache_key);
			}
			if($GLOBALS['__member_info__'][$member_srl] === false) {
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$output = executeQuery('member.getMemberInfoByMemberSrl', $args, $columnList);
				if(!$output->data) {
					if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, new stdClass);
					return;
				}
				$this->arrangeMemberInfo($output->data, $site_srl);
				if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $GLOBALS['__member_info__'][$member_srl]);
			}
		}
		return $GLOBALS['__member_info__'][$member_srl];
	}

	function arrangeMemberInfo($info, $site_srl = 0) {
		if(!$GLOBALS['__member_info__'][$info->member_srl]) {
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('member');
			$info->profile_image = $this->getProfileImage($info->member_srl);
			$info->image_name = $this->getImageName($info->member_srl);
			$info->image_mark = $this->getImageMark($info->member_srl);
			if($config->group_image_mark=='Y') $info->group_mark = $this->getGroupImageMark($info->member_srl,$site_srl);
			$info->signature = $this->getSignature($info->member_srl);
			$info->group_list = $this->getMemberGroups($info->member_srl, $site_srl);
			$extra_vars = unserialize($info->extra_vars);
			unset($info->extra_vars);
			if($extra_vars) {
				foreach($extra_vars as $key => $val) {
					if(!is_array($val) && strpos($val, '|@|') !== FALSE) $val = explode('|@|', $val);
					if(!$info->{$key}) $info->{$key} = $val;
				}
			}
			if(strlen($info->find_account_answer) == 32 && preg_match('/[a-zA-Z0-9]+/', $info->find_account_answer)) $info->find_account_answer = null;
			$oSecurity = new Security($info);
			$oSecurity->encodeHTML('user_id', 'user_name', 'nick_name', 'find_account_answer', 'description', 'address.', 'group_list..');
			$info->homepage = strip_tags($info->homepage);
			$info->blog = strip_tags($info->blog);
			if($extra_vars) {
				foreach($extra_vars as $key => $val) {
					if(is_array($val)) $oSecurity->encodeHTML($key . '.');
					else $oSecurity->encodeHTML($key);
				}
			}
			$oValidator = new Validator();
			if(!$oValidator->applyRule('url', $info->homepage)) $info->homepage = '';
			if(!$oValidator->applyRule('url', $info->blog)) $info->blog = '';
			$GLOBALS['__member_info__'][$info->member_srl] = $info;
		}
		return $GLOBALS['__member_info__'][$info->member_srl];
	}

	function getMemberSrlByUserID($user_id) {
		$args = new stdClass();
		$args->user_id = $user_id;
		$output = executeQuery('member.getMemberSrl', $args);
		return $output->data->member_srl;
	}

	function getMemberSrlByEmailAddress($email_address) {
		$args = new stdClass();
		$args->email_address = $email_address;
		$output = executeQuery('member.getMemberSrl', $args);
		return $output->data->member_srl;
	}

	function getMemberSrlByNickName($nick_name) {
		$args = new stdClass();
		$args->nick_name = $nick_name;
		$output = executeQuery('member.getMemberSrl', $args);
		return $output->data->member_srl;
	}

	function getLoggedMemberSrl() {
		if(!$this->isLogged()) return;
		return $_SESSION['member_srl'];
	}

	function getLoggedUserID() {
		if(!$this->isLogged()) return;
		$logged_info = Context::get('logged_info');
		return $logged_info->user_id;
	}

	function getMemberGroups($member_srl, $site_srl = 0, $force_reload = false) {
		static $member_groups = array();
		$group_list = false;
		$oCacheHandler = CacheHandler::getInstance('object', null, true);
		if($oCacheHandler->isSupport()) {
			$object_key = 'member_groups:' . getNumberingPath($member_srl) . $member_srl . '_'.$site_srl;
			$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
			$group_list = $oCacheHandler->get($cache_key);
		}
		if(!$member_groups[$member_srl][$site_srl] || $force_reload) {
			if($group_list === false) {
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->site_srl = $site_srl;
				$output = executeQueryArray('member.getMemberGroups', $args);
				$group_list = $output->data;
				if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $group_list);
			}
			if(!$group_list) return array();
			foreach($group_list as $group) $result[$group->group_srl] = $group->title;
			$member_groups[$member_srl][$site_srl] = $result;
		}
		return $member_groups[$member_srl][$site_srl];
	}

	function getMembersGroups($member_srls, $site_srl = 0) {
		$args->member_srls = implode(',',$member_srls);
		$args->site_srl = $site_srl;
		$args->sort_index = 'list_order';
		$output = executeQueryArray('member.getMembersGroups', $args);
		if(!$output->data) return array();
		$result = array();
		foreach($output->data as $key=>$val) $result[$val->member_srl][] = $val->title;
		return $result;
	}

	function getDefaultGroup($site_srl = 0, $columnList = array()) {
		$default_group = false;
		$oCacheHandler = CacheHandler::getInstance('object', null, true);
		if($oCacheHandler->isSupport()) {
			$columnList = array();
			$object_key = 'default_group_' . $site_srl;
			$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
			$default_group = $oCacheHandler->get($cache_key);
		}
		if($default_group === false) {
			$args = new stdClass();
			$args->site_srl = $site_srl;
			$output = executeQuery('member.getDefaultGroup', $args, $columnList);
			$default_group = $output->data;
			if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $default_group);
		}
		return $default_group;
	}

	function getAdminGroup($columnList = array()) {
		$output = executeQuery('member.getAdminGroup', $args, $columnList);
		return $output->data;
	}

	function getGroup($group_srl, $columnList = array()) {
		$args = new stdClass;
		$args->group_srl = $group_srl;
		$output = executeQuery('member.getGroup', $args, $columnList);
		return $output->data;
	}

	function getGroups($site_srl = 0) {
		if(!$GLOBALS['__group_info__'][$site_srl]) {
			$result = array();
			if(!isset($site_srl)) $site_srl = 0;
			$group_list = false;
			$oCacheHandler = CacheHandler::getInstance('object', null, true);
			if($oCacheHandler->isSupport()) {
				$object_key = 'member_groups:site_'.$site_srl;
				$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
				$group_list = $oCacheHandler->get($cache_key);
			}
			if($group_list === false) {
				$args = new stdClass();
				$args->site_srl = $site_srl;
				$args->sort_index = 'list_order';
				$args->order_type = 'asc';
				$output = executeQueryArray('member.getGroups', $args);
				$group_list = $output->data;
				if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $group_list);
			}
			if(!$group_list) return array();
			foreach($group_list as $val) $result[$val->group_srl] = $val;
			$GLOBALS['__group_info__'][$site_srl] = $result;
		}
		return $GLOBALS['__group_info__'][$site_srl];
	}

	public function getApiGroups() {
		$siteSrl = Context::get('siteSrl');
		$groupInfo = $this->getGroups($siteSrl);
		$this->add($groupInfo);
	}

	function getJoinFormList($filter_response = false) {
		global $lang;
		$logged_info = Context::get('logged_info');
		if(!$this->join_form_list) {
			$args = new stdClass();
			$args->sort_index = "list_order";
			$output = executeQuery('member.getJoinFormList', $args);
			$join_form_list = $output->data;
			if(!$join_form_list) return NULL;
			if(!is_array($join_form_list)) $join_form_list = array($join_form_list);
			$join_form_count = count($join_form_list);
			for($i=0;$i<$join_form_count;$i++) {
				$join_form_list[$i]->column_name = strtolower($join_form_list[$i]->column_name);
				$member_join_form_srl = $join_form_list[$i]->member_join_form_srl;
				$column_type = $join_form_list[$i]->column_type;
				$column_name = $join_form_list[$i]->column_name;
				$column_title = $join_form_list[$i]->column_title;
				$default_value = $join_form_list[$i]->default_value;
				$lang->extend_vars[$column_name] = $column_title;
				if(in_array($column_type, array('checkbox','select','radio'))) {
					$join_form_list[$i]->default_value = unserialize($default_value);
					if(!$join_form_list[$i]->default_value[0]) $join_form_list[$i]->default_value = '';
				} else {
					$join_form_list[$i]->default_value = '';
				}
				$list[$member_join_form_srl] = $join_form_list[$i];
			}
			$this->join_form_list = $list;
		}
		if($filter_response && count($this->join_form_list)) {
			foreach($this->join_form_list as $key => $val) {
				if($val->is_active != 'Y') continue;
				unset($obj);
				$obj->type = $val->column_type;
				$obj->name = $val->column_name;
				$obj->lang = $val->column_title;
				if($logged_info->is_admin != 'Y') $obj->required = $val->required=='Y'?true:false;
				else $obj->required = false;
				$filter_output[] = $obj;
				unset($open_obj);
				$open_obj->name = 'open_'.$val->column_name;
				$open_obj->required = false;
				$filter_output[] = $open_obj;
			}
			return $filter_output;
		}
		return $this->join_form_list;
	}

	function getUsedJoinFormList() {
		$args = new stdClass();
		$args->sort_index = "list_order";
		$output = executeQueryArray('member.getJoinFormList', $args);
		if(!$output->toBool()) return array();
		$joinFormList = array();
		foreach($output->data as $val) {
			if($val->is_active != 'Y') continue;
			$joinFormList[] = $val;
		}
		return $joinFormList;
	}

	function getCombineJoinForm($member_info) {
		$extend_form_list = $this->getJoinFormlist();
		if(!$extend_form_list) return;
		$logged_info = Context::get('logged_info');
		foreach($extend_form_list as $srl => $item) {
			$column_name = $item->column_name;
			$value = $member_info->{$column_name};
			switch($item->column_type) {
				case 'checkbox' : if($value && !is_array($value)) $value = array($value); break;
				case 'text' :
				case 'homepage' :
				case 'email_address' :
				case 'tel' :
				case 'textarea' :
				case 'select' :
				case 'kr_zip' : break;
			}
			$extend_form_list[$srl]->value = $value;
			if($member_info->{'open_'.$column_name}=='Y') $extend_form_list[$srl]->is_opened = true;
			else $extend_form_list[$srl]->is_opened = false;
		}
		return $extend_form_list;
	}

	function getJoinForm($member_join_form_srl) {
		$args->member_join_form_srl = $member_join_form_srl;
		$output = executeQuery('member.getJoinForm', $args);
		$join_form = $output->data;
		if(!$join_form) return NULL;
		$column_type = $join_form->column_type;
		$default_value = $join_form->default_value;
		if(in_array($column_type, array('checkbox','select','radio'))) $join_form->default_value = unserialize($default_value);
		else $join_form->default_value = '';
		return $join_form;
	}

	function getDeniedIDList() {
		if(!$this->denied_id_list) {
			$args->sort_index = "list_order";
			$args->page = Context::get('page');
			$args->list_count = 40;
			$args->page_count = 10;
			$output = executeQuery('member.getDeniedIDList', $args);
			$this->denied_id_list = $output;
		}
		return $this->denied_id_list;
	}

	function getDeniedIDs() {
		$output = executeQueryArray('member.getDeniedIDs');
		if(!$output->toBool()) return array();
		return $output->data;
	}

	function getDeniedNickNames() {
		$output = executeQueryArray('member.getDeniedNickNames');
		if(!$output->toBool()) return array();
		return $output->data;
	}

	function isDeniedID($user_id) {
		$args = new stdClass();
		$args->user_id = $user_id;
		$output = executeQuery('member.chkDeniedID', $args);
		if($output->data->count) return true;
		return false;
	}

	function isDeniedNickName($nickName) {
		$args = new stdClass();
		$args->nick_name = $nickName;
		$output = executeQuery('member.chkDeniedNickName', $args);
		if($output->data->count) return true;
		if(!$output->toBool()) return true;
		return false;
	}

	function getProfileImage($member_srl) {
		if(!isset($GLOBALS['__member_info__']['profile_image'][$member_srl])) {
			$GLOBALS['__member_info__']['profile_image'][$member_srl] = null;
			$exts = array('gif','jpg','png');
			for($i=0;$i<3;$i++) {
				$image_name_file = sprintf('files/member_extra_info/profile_image/%s%d.%s', getNumberingPath($member_srl), $member_srl, $exts[$i]);
				if(file_exists($image_name_file)) {
					list($width, $height, $type, $attrs) = getimagesize($image_name_file);
					$info = new stdClass();
					$info->width = $width;
					$info->height = $height;
					$info->src = Context::getRequestUri().$image_name_file . '?' . date('YmdHis', filemtime($image_name_file));
					$info->file = './'.$image_name_file;
					$GLOBALS['__member_info__']['profile_image'][$member_srl] = $info;
					break;
				}
			}
		}
		return $GLOBALS['__member_info__']['profile_image'][$member_srl];
	}

	function getImageName($member_srl) {
		if(!isset($GLOBALS['__member_info__']['image_name'][$member_srl])) {
			$image_name_file = sprintf('files/member_extra_info/image_name/%s%d.gif', getNumberingPath($member_srl), $member_srl);
			if(file_exists($image_name_file)) {
				list($width, $height, $type, $attrs) = getimagesize($image_name_file);
				$info = new stdClass;
				$info->width = $width;
				$info->height = $height;
				$info->src = Context::getRequestUri().$image_name_file. '?' . date('YmdHis', filemtime($image_name_file));
				$info->file = './'.$image_name_file;
				$GLOBALS['__member_info__']['image_name'][$member_srl] = $info;
			}
			else $GLOBALS['__member_info__']['image_name'][$member_srl] = null;
		}
		return $GLOBALS['__member_info__']['image_name'][$member_srl];
	}

	function getImageMark($member_srl) {
		if(!isset($GLOBALS['__member_info__']['image_mark'][$member_srl])) {
			$image_mark_file = sprintf('files/member_extra_info/image_mark/%s%d.gif', getNumberingPath($member_srl), $member_srl);
			if(file_exists($image_mark_file)) {
				list($width, $height, $type, $attrs) = getimagesize($image_mark_file);
				$info->width = $width;
				$info->height = $height;
				$info->src = Context::getRequestUri().$image_mark_file . '?' . date('YmdHis', filemtime($image_mark_file));
				$info->file = './'.$image_mark_file;
				$GLOBALS['__member_info__']['image_mark'][$member_srl] = $info;
			}
			else $GLOBALS['__member_info__']['image_mark'][$member_srl] = null;
		}
		return $GLOBALS['__member_info__']['image_mark'][$member_srl];
	}

	function getGroupImageMark($member_srl,$site_srl=0) {
		if(!isset($GLOBALS['__member_info__']['group_image_mark'][$member_srl])) {
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('member');
			if($config->group_image_mark!='Y') return null;
			$member_group = $this->getMemberGroups($member_srl,$site_srl);
			$groups_info = $this->getGroups($site_srl);
			if(count($member_group) > 0 && is_array($member_group)) {
				$memberGroups = array_keys($member_group);
				foreach($groups_info as $group_srl=>$group_info) {
					if(in_array($group_srl, $memberGroups)) {
						if($group_info->image_mark) {
							$info = new stdClass();
							$info->title = $group_info->title;
							$info->description = $group_info->description;
							$info->src = $group_info->image_mark;
							$GLOBALS['__member_info__']['group_image_mark'][$member_srl] = $info;
							break;
						}
					}
				}
			}
			if (!$info) $GLOBALS['__member_info__']['group_image_mark'][$member_srl] == 'N';
		}
		if ($GLOBALS['__member_info__']['group_image_mark'][$member_srl] == 'N') return null;
		return $GLOBALS['__member_info__']['group_image_mark'][$member_srl];
	}

	function getSignature($member_srl) {
		if(!isset($GLOBALS['__member_info__']['signature'][$member_srl])) {
			$filename = sprintf('files/member_extra_info/signature/%s%d.signature.php', getNumberingPath($member_srl), $member_srl);
			if(file_exists($filename)) {
				$buff = FileHandler::readFile($filename);
				$signature = preg_replace('/<\?.*\?>/', '', $buff);
				$GLOBALS['__member_info__']['signature'][$member_srl] = $signature;
			}
			else $GLOBALS['__member_info__']['signature'][$member_srl] = null;
		}
		return $GLOBALS['__member_info__']['signature'][$member_srl];
	}

	function isValidPassword($hashed_password, $password_text, $member_srl=null) {
		if(!$password_text) return false;
		$oPassword = new Password();
		$current_algorithm = $oPassword->checkAlgorithm($hashed_password);
		$match = $oPassword->checkPassword($password_text, $hashed_password, $current_algorithm);
		if(!$match) return false;
		$config = $this->getMemberConfig();
		if($member_srl > 0 && $config->password_hashing_auto_upgrade != 'N') {
			$need_upgrade = false;
			if(!$need_upgrade) {
				$required_algorithm = $oPassword->getCurrentlySelectedAlgorithm();
				if($required_algorithm !== $current_algorithm) $need_upgrade = true;
			}
			if(!$need_upgrade) {
				$required_work_factor = $oPassword->getWorkFactor();
				$current_work_factor = $oPassword->checkWorkFactor($hashed_password);
				if($current_work_factor !== false && $required_work_factor > $current_work_factor) $need_upgrade = true;
			}
			if($need_upgrade === true) {
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->hashed_password = $this->hashPassword($password_text, $required_algorithm);
				$oMemberController = getController('member');
				$oMemberController->updateMemberPassword($args);
			}
		}
		return true;
	}

	function hashPassword($password_text, $algorithm = null) {
		$oPassword = new Password();
		return $oPassword->createHash($password_text, $algorithm);
	}

	function checkPasswordStrength($password, $strength) {
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;
		if($strength == NULL) {
			$config = $this->getMemberConfig();
			$strength = $config->password_strength?$config->password_strength:'normal';
		}
		$length = strlen($password);
		switch ($strength) {
			case 'high':
				if($length < 8 || !preg_match('/[^a-zA-Z0-9]/', $password)) return false;
			case 'normal':
				if($length < 6 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) return false;
				break;
			case 'low':
				if($length < 4) return false;
				break;
		}
		return true;
	}

	function getAdminGroupSrl($site_srl = 0) {
		$groupSrl = 0;
		$output = $this->getGroups($site_srl);
		if(is_array($output)) {
			foreach($output AS $key=>$value) {
				if($value->is_admin == 'Y') {
					$groupSrl = $value->group_srl;
					break;
				}
			}
		}
		return $groupSrl;
	}
}
