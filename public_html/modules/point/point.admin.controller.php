<?php
class pointAdminController extends point
{
	function init() {
	}

	function procPointAdminInsertConfig() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$args = Context::getRequestVars();
		if($args->able_module == 'Y') {
			if($config->able_module == 'N') $this->moduleUpdate();
			$config->able_module = 'Y';
			$config->point_name = $args->point_name;
			if(!$config->point_name) $config->point_name = 'point';
			$config->signup_point = (int)$args->signup_point;
			$config->login_point = (int)$args->login_point;
			$config->insert_document = (int)$args->insert_document;
			$config->read_document = (int)$args->read_document;
			$config->insert_comment = (int)$args->insert_comment;
			$config->upload_file = (int)$args->upload_file;
			$config->download_file = (int)$args->download_file;
			$config->voted = (int)$args->voted;
			$config->blamed = (int)$args->blamed;
			$config->max_level = $args->max_level;
			if($config->max_level>1000) $config->max_level = 1000;
			if($config->max_level<1) $config->max_level = 1;
			$config->level_icon = $args->level_icon;
			if($args->disable_download == 'Y') $config->disable_download = 'Y';
			else $config->disable_download = 'N';
			if($args->disable_read_document == 'Y') $config->disable_read_document = 'Y';
			else $config->disable_read_document = 'N';
			$oMemberModel = getModel('member');
			$group_list = $oMemberModel->getGroups();
			$config->point_group = array();
			foreach($group_list as $group) {
				if($group->is_admin == 'Y' || $group->is_default == 'Y') continue;
				$group_srl = $group->group_srl;
				if(isset($args->{'point_group_'.$group_srl})) {
					if($args->{'point_group_'.$group_srl} > $args->max_level) $args->{'point_group_'.$group_srl} = $args->max_level;
					if($args->{'point_group_'.$group_srl} < 1) $args->{'point_group_'.$group_srl} = 1;
					$config->point_group[$group_srl] = $args->{'point_group_'.$group_srl};
				}
			}
			$config->group_reset = $args->group_reset;
			unset($config->level_step);
			for($i=1;$i<=$config->max_level;$i++) {
				$key = "level_step_".$i;
				$config->level_step[$i] = (int)$args->{$key};
			}
			$config->expression = $args->expression;
		} else {
			$config->able_module = 'N';
			$oModuleController = getController('module');
			$oModuleController->deleteModuleTriggers('point');
		}
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('point', $config);
		$this->setMessage('success_updated');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointAdminConfig');
		$this->setRedirectUrl($returnUrl);
	}

	function procPointAdminInsertModuleConfig() {
		$args = Context::getRequestVars();
		$configTypeList = array('insert_document', 'insert_comment', 'upload_file', 'download_file', 'read_document', 'voted', 'blamed');
		foreach($configTypeList AS $config) {
			if(is_array($args->{$config})) {
				foreach($args->{$config} AS $key=>$value) $module_config[$key][$config] = $value;
			}
		}
		$oModuleController = getController('module');
		if(count($module_config)) {
			foreach($module_config as $module_srl => $config) $oModuleController->insertModulePartConfig('point',$module_srl,$config);
		}
		$this->setMessage('success_updated');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointAdminModuleConfig');
			header('location:'.$returnUrl);
			return;
		}
	}

	function procPointAdminInsertPointModuleConfig() {
		$module_srl = Context::get('target_module_srl');
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');
		if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
		else $module_srl = array($module_srl);
		$oModuleController = getController('module');
		for($i=0;$i<count($module_srl);$i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			unset($config);
			$config['insert_document'] = (int)Context::get('insert_document');
			$config['insert_comment'] = (int)Context::get('insert_comment');
			$config['upload_file'] = (int)Context::get('upload_file');
			$config['download_file'] = (int)Context::get('download_file');
			$config['read_document'] = (int)Context::get('read_document');
			$config['voted'] = (int)Context::get('voted');
			$config['blamed'] = (int)Context::get('blamed');
			$oModuleController->insertModulePartConfig('point', $srl, $config);
		}
		$this->setError(-1);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispBoardAdminContent');
		$this->setRedirectUrl($returnUrl);
	}

	function procPointAdminUpdatePoint() {
		$member_srl = Context::get('member_srl');
		$point = Context::get('point');
		preg_match('/^(\+|-)?([1-9][0-9]*)$/', $point, $m);
		$action = '';
		switch($m[1]) {
			case '+': $action = 'add'; break;
			case '-': $action = 'minus'; break;
			default: $action = 'update'; break;
		}
		$point = $m[2];
		$oPointController = getController('point');
		$output = $oPointController->setPoint($member_srl, (int)$point, $action);
		$this->setError(-1);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointAdminPointList');
		return $this->setRedirectUrl($returnUrl, $output);
	}

	function procPointAdminReCal() {
		@set_time_limit(0);
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfigs('point');
		$member = array();
		$output = executeQueryArray('point.getMemberCount');
		if(!$output->toBool()) return $output;
		if($output->data) {
			foreach($output->data as $key => $val) {
				if(!$val->member_srl) continue;
				$member[$val->member_srl] = 0;
			}
		}
		$output = executeQueryArray('point.getDocumentPoint');
		if(!$output->toBool()) return $output;
		if($output->data) {
			foreach($output->data as $key => $val) {
				if($module_config[$val->module_srl]['insert_document']) $insert_point = $module_config[$val->module_srl]['insert_document'];
				else $insert_point = $config->insert_document;
				if(!$val->member_srl) continue;
				$point = $insert_point * $val->count;
				$member[$val->member_srl] += $point;
			}
		}
		$output = null;
		$output = executeQueryArray('point.getCommentPoint');
		if(!$output->toBool()) return $output;
		if($output->data) {
			foreach($output->data as $key => $val) {
				if($module_config[$val->module_srl]['insert_comment']) $insert_point = $module_config[$val->module_srl]['insert_comment'];
				else $insert_point = $config->insert_comment;
				if(!$val->member_srl) continue;
				$point = $insert_point * $val->count;
				$member[$val->member_srl] += $point;
			}
		}
		$output = null;
		$output = executeQueryArray('point.getFilePoint');
		if(!$output->toBool()) return $output;
		if($output->data) {
			foreach($output->data as $key => $val) {
				if($module_config[$val->module_srl]['upload_file']) $insert_point = $module_config[$val->module_srl]['upload_file'];
				else $insert_point = $config->upload_file;
				if(!$val->member_srl) continue;
				$point = $insert_point * $val->count;
				$member[$val->member_srl] += $point;
			}
		}
		$output = null;
		$output = executeQuery("point.initMemberPoint");
		if(!$output->toBool()) return $output;
		$str = '';
		foreach($member as $key => $val) {
			$val += (int)$config->signup_point;
			$str .= $key.','.$val."\r\n";
		}
		@file_put_contents('./files/cache/pointRecal.txt', $str, LOCK_EX);
		$this->add('total', count($member));
		$this->add('position', 0);
		$this->setMessage( sprintf(Context::getLang('point_recal_message'), 0, $this->get('total')) );
	}

	function procPointAdminApplyPoint() {
		$position = (int)Context::get('position');
		$total = (int)Context::get('total');
		if(!file_exists('./files/cache/pointRecal.txt')) return new Object(-1, 'msg_invalid_request');
		$idx = 0;
		$f = fopen("./files/cache/pointRecal.txt","r");
		while(!feof($f)) {
			$str = trim(fgets($f, 1024));
			$idx ++;
			if($idx > $position) {
				list($member_srl, $point) = explode(',',$str);
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->point = $point;
				$output = executeQuery('point.insertPoint',$args);
				if($idx%5000==0) break;
			}
		}
		if(feof($f)) {
			FileHandler::removeFile('./files/cache/pointRecal.txt');
			$idx = $total;
			FileHandler::rename('./files/member_extra_info/point','./files/member_extra_info/point.old');
			FileHandler::removeDir('./files/member_extra_info/point.old');
		}
		fclose($f);
		$this->add('total', $total);
		$this->add('position', $idx);
		$this->setMessage(sprintf(Context::getLang('point_recal_message'), $idx, $total));
	}

	function procPointAdminReset() {
		$module_srl = Context::get('module_srls');
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');
		if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
		else $module_srl = array($module_srl);
		$oModuleController = getController('module');
		for($i=0;$i<count($module_srl);$i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			$args = new stdClass();
			$args->module = 'point';
			$args->module_srl = $srl;
			executeQuery('module.deleteModulePartConfig', $args);
		}
		$oCacheHandler = CacheHandler::getInstance('object', null, true);
		if($oCacheHandler->isSupport()) $oCacheHandler->invalidateGroupKey('site_and_module');
		$this->setMessage('success_updated');
	}

	function cacheActList() {
		return;
	}
}
