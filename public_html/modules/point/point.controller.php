<?php
class pointController extends point
{
	function init() {
	}

	function triggerInsertMember(&$obj) {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$member_srl = $obj->member_srl;
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $config->signup_point;
		$cur_point += $point;
		$this->setPoint($member_srl,$cur_point, 'signup');
		return new Object();
	}

	function triggerAfterLogin(&$obj) {
		$member_srl = $obj->member_srl;
		if(!$member_srl) return new Object();
		if(substr($obj->last_login,0,8)==date("Ymd")) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $config->login_point;
		$cur_point += $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function triggerInsertDocument(&$obj) {
		$oDocumentModel = getModel('document');
		if($obj->status != $oDocumentModel->getConfigStatus('temp')) {
			$module_srl = $obj->module_srl;
			$member_srl = $obj->member_srl;
			if(!$module_srl || !$member_srl) return new Object();
			if($module_srl == $member_srl) return new Object();
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('point');
			$module_config = $oModuleModel->getModulePartConfig('point',$module_srl);
			$oPointModel = getModel('point');
			$cur_point = $oPointModel->getPoint($member_srl, true);
			$point = $module_config['insert_document'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->insert_document;
			$cur_point += $point;
			$point = $module_config['upload_file'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->upload_file;
			if($obj->uploaded_count) $cur_point += $point * $obj->uploaded_count;
			$this->setPoint($member_srl,$cur_point);
		}
		return new Object();
	}

	function triggerUpdateDocument(&$obj) {
		$oDocumentModel = getModel('document');
		$document_srl = $obj->document_srl;
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if($oDocument->get('status') == $oDocumentModel->getConfigStatus('temp') && $obj->status != $oDocumentModel->getConfigStatus('temp')) {
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('point');
			$module_config = $oModuleModel->getModulePartConfig('point',$obj->module_srl);
			$oPointModel = getModel('point');
			$cur_point = $oPointModel->getPoint($oDocument->get('member_srl'), true);
			$point = $module_config['insert_document'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->insert_document;
			$cur_point += $point;
			$point = $module_config['upload_file'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->upload_file;
			if($obj->uploaded_count) $cur_point += $point * $obj->uploaded_count;
			$this->setPoint($oDocument->get('member_srl'), $cur_point);
		}
		return new Object();
	}

	function triggerBeforeDeleteDocument(&$obj) {
		$document_srl = $obj->document_srl;
		$member_srl = $obj->member_srl;
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists()) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point',$oDocument->get('module_srl'));
		$comment_point = $module_config['insert_comment'];
		if(strlen($comment_point) == 0 && !is_int($comment_point)) $comment_point = $config->insert_comment;
		if($comment_point>0) return new Object();
		$cp_args = new stdClass();
		$cp_args->document_srl = $document_srl;
		$output = executeQueryArray('point.getCommentUsers', $cp_args);
		if(!$output->data) return new Object();
		$member_srls = array();
		$cnt = count($output->data);
		for($i=0;$i<$cnt;$i++) {
			if($output->data[$i]->member_srl<1) continue;
			$member_srls[abs($output->data[$i]->member_srl)] = $output->data[$i]->count;
		}
		if($member_srl) unset($member_srls[abs($member_srl)]);
		if(!count($member_srls)) return new Object();
		$oPointModel = getModel('point');
		$point = $module_config['download_file'];
		foreach($member_srls as $member_srl => $cnt) {
			$cur_point = $oPointModel->getPoint($member_srl, true);
			$cur_point -= $cnt * $comment_point;
			$this->setPoint($member_srl,$cur_point);
		}
		return new Object();
	}

	function triggerDeleteDocument(&$obj) {
		$oDocumentModel = getModel('document');
		if($obj->status != $oDocumentModel->getConfigStatus('temp')) {
			$module_srl = $obj->module_srl;
			$member_srl = $obj->member_srl;
			if(!$module_srl || !$member_srl) return new Object();
			$logged_info = Context::get('logged_info');
			if(!$logged_info->member_srl) return new Object();
			$oPointModel = getModel('point');
			$cur_point = $oPointModel->getPoint($member_srl, true);
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('point');
			$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
			$point = $module_config['insert_document'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->insert_document;
			if($point < 0) return new Object();
			$cur_point -= $point;
			$point = $module_config['upload_file'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->upload_file;
			if($obj->uploaded_count) $cur_point -= $point * $obj->uploaded_count;
			$this->setPoint($member_srl,$cur_point);
		}
		return new Object();
	}

	function triggerInsertComment(&$obj) {
		$module_srl = $obj->module_srl;
		$member_srl = $obj->member_srl;
		if(!$module_srl || !$member_srl) return new Object();
		$document_srl = $obj->document_srl;
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists() || abs($oDocument->get('member_srl'))==abs($member_srl)) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $module_config['insert_comment'];
		if(strlen($point) == 0 && !is_int($point)) $point = $config->insert_comment;
		$cur_point += $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function triggerDeleteComment(&$obj) {
		$oModuleModel = getModel('module');
		$oPointModel = getModel('point');
		$oDocumentModel = getModel('document');
		$module_srl = $obj->module_srl;
		$member_srl = abs($obj->member_srl);
		$document_srl = $obj->document_srl;
		if(!$module_srl || !$member_srl) return new Object();
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists()) return new Object();
		if($oDocument->get('member_srl')==$member_srl) return new Object();
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $module_config['insert_comment'];
		if(strlen($point) == 0 && !is_int($point)) $point = $config->insert_comment;
		if($point < 0) return new Object();
		$cur_point -= $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function triggerInsertFile(&$obj) {
		return new Object();
	}

	function triggerDeleteFile(&$obj) {
		if($obj->isvalid != 'Y') return new Object();
		$module_srl = $obj->module_srl;
		$member_srl = $obj->member_srl;
		if(!$module_srl || !$member_srl) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $module_config['upload_file'];
		if(strlen($point) == 0 && !is_int($point)) $point = $config->upload_file;
		$cur_point -= $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function triggerBeforeDownloadFile(&$obj) {
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$module_srl = $obj->module_srl;
		if(!$module_srl) return new Object();
		if(abs($obj->member_srl) == abs($member_srl)) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
		if(!Context::get('is_logged')) {
			if($config->disable_download == 'Y' && strlen($module_config['download_file']) == 0 && !is_int($module_config['download_file'])) return new Object(-1,'msg_not_permitted_download');
			else return new Object();
		}
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $module_config['download_file'];
		if(strlen($point) == 0 && !is_int($point)) $point = $config->download_file;
		if($cur_point + $point < 0 && $config->disable_download == 'Y') return new Object(-1,'msg_cannot_download');
		return new Object();
	}

	function triggerDownloadFile(&$obj) {
		$logged_info = Context::get('logged_info');
		if(!$logged_info->member_srl) return new Object();
		$module_srl = $obj->module_srl;
		$member_srl = $logged_info->member_srl;
		if(!$module_srl) return new Object();
		if(abs($obj->member_srl) == abs($member_srl)) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		$point = $module_config['download_file'];
		if(strlen($point) == 0 && !is_int($point)) $point = $config->download_file;
		$cur_point += $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function triggerUpdateReadedCount(&$obj) {
		$oModuleModel = getModel('module');
		$oPointModel = getModel('point');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$target_member_srl = abs($obj->get('member_srl'));
		if($target_member_srl == $member_srl) return new Object();
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $obj->get('module_srl'));
		$point = $module_config['read_document'];
		if(strlen($point) == 0 && !is_int($point)) $point = $config->read_document;
		if(!$point) return new Object();
		if($member_srl) {
			$args->member_srl = $member_srl;
			$args->document_srl = $obj->document_srl;
			$output = executeQuery('document.getDocumentReadedLogInfo', $args);
			if($output->data->count) return new Object();
			$cur_point = $oPointModel->getPoint($member_srl, true);
		} else {
			$cur_point = 0;
		}
		$config = $oModuleModel->getModuleConfig('point');
		$_SESSION['banned_document'][$obj->document_srl] = false;
		if($config->disable_read_document == 'Y' && $point < 0 && abs($point)>$cur_point) {
			$message = sprintf(Context::getLang('msg_disallow_by_point'), abs($point), $cur_point);
			$obj->add('content', $message);
			$_SESSION['banned_document'][$obj->document_srl] = true;
			return new Object(-1, $message);
		}
		if(!$logged_info->member_srl) return new Object();
		if(!$point) return new Object();
		$output = executeQuery('document.insertDocumentReadedLog', $args);
		$cur_point += $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function triggerUpdateVotedCount(&$obj) {
		$module_srl = $obj->module_srl;
		$member_srl = $obj->member_srl;
		if(!$module_srl || !$member_srl) return new Object();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		$module_config = $oModuleModel->getModulePartConfig('point', $module_srl);
		$oPointModel = getModel('point');
		$cur_point = $oPointModel->getPoint($member_srl, true);
		if( $obj->point > 0 ) {
			$point = $module_config['voted'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->voted;
		} else {
			$point = $module_config['blamed'];
			if(strlen($point) == 0 && !is_int($point)) $point = $config->blamed;
		}
		if(!$point) return new Object();
		$cur_point += $point;
		$this->setPoint($member_srl,$cur_point);
		return new Object();
	}

	function setPoint($member_srl, $point, $mode = null) {
		$member_srl = abs($member_srl);
		$mode_arr = array('add', 'minus', 'update', 'signup');
		if(!$mode || !in_array($mode,$mode_arr)) $mode = 'update';
		$oMemberModel = getModel('member');
		$oModuleModel = getModel('module');
		$oPointModel = getModel('point');
		$config = $oModuleModel->getModuleConfig('point');
		$current_point = $oPointModel->getPoint($member_srl, true);
		$current_level = $oPointModel->getLevel($current_point, $config->level_step);
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->point = $current_point;
		switch($mode) {
			case 'add' : $args->point += $point; break;
			case 'minus' : $args->point -= $point; break;
			case 'update' : case 'signup' : $args->point = $point; break;
		}
		if($args->point < 0) $args->point = 0;
		$point = $args->point;
		$trigger_obj = new stdClass();
		$trigger_obj->member_srl = $args->member_srl;
		$trigger_obj->mode = $mode;
		$trigger_obj->current_point = $current_point;
		$trigger_obj->current_level = $current_level;
		$trigger_obj->set_point = $point;
		$trigger_output = ModuleHandler::triggerCall('point.setPoint', 'before', $trigger_obj);
		if(!$trigger_output->toBool()) return $trigger_output;
		$oDB = &DB::getInstance();
		$oDB->begin();
		$oPointModel = getModel('point');
		if($oPointModel->isExistsPoint($member_srl)) executeQuery("point.updatePoint", $args);
		else executeQuery("point.insertPoint", $args);
		$level = $oPointModel->getLevel($point, $config->level_step);
		if($level != $current_level) {
			$point_group = $config->point_group;
			if($point_group && is_array($point_group) && count($point_group) ) {
				$default_group = $oMemberModel->getDefaultGroup();
				$del_group_list = array();
				$new_group_list = array();
				asort($point_group);
				if($config->group_reset != 'N') {
					if(in_array($level, $point_group)) {
						foreach($point_group as $group_srl => $target_level) {
							$del_group_list[] = $group_srl;
							if($target_level == $level) $new_group_list[] = $group_srl;
						}
					} else {
						$i = $level;
						while($i > 0) {
							if(in_array($i, $point_group)) {
								foreach($point_group as $group_srl => $target_level) {
									if($target_level == $i) $new_group_list[] = $group_srl;
								}
								$i = 0;
							}
							$i--;
						}
					}
					foreach($point_group as $group_srl => $target_level) {
						if($target_level > $level) $del_group_list[] = $group_srl;
					}
					$del_group_list[] = $default_group->group_srl;
				} else {
					foreach($point_group as $group_srl => $target_level) {
						$del_group_list[] = $group_srl;
						if($target_level <= $level) $new_group_list[] = $group_srl;
					}
				}
				if(!$new_group_list[0]) $new_group_list[0] = $default_group->group_srl;
				if($del_group_list && count($del_group_list)) {
					$del_group_args = new stdClass;
					$del_group_args->member_srl = $member_srl;
					$del_group_args->group_srl = implode(',', $del_group_list);
					$del_group_output = executeQuery('point.deleteMemberGroup', $del_group_args);
				}
				foreach($new_group_list as $group_srl) {
					$new_group_args = new stdClass;
					$new_group_args->member_srl = $member_srl;
					$new_group_args->group_srl = $group_srl;
					executeQuery('member.addMemberToGroup', $new_group_args);
				}
			}
		}
		$trigger_obj->new_group_list = $new_group_list;
		$trigger_obj->del_group_list = $del_group_list;
		$trigger_obj->new_level = $level;
		$trigger_output = ModuleHandler::triggerCall('point.setPoint', 'after', $trigger_obj);
		if(!$trigger_output->toBool()) {
			$oDB->rollback();
			return $trigger_output;
		}
		$oDB->commit();
		$cache_path = sprintf('./files/member_extra_info/point/%s/', getNumberingPath($member_srl));
		FileHandler::makedir($cache_path);
		$cache_filename = sprintf('%s%d.cache.txt', $cache_path, $member_srl);
		FileHandler::writeFile($cache_filename, $point);
		$oCacheHandler = CacheHandler::getInstance('object', null, true);
		if($new_group_list && $del_group_list && $oCacheHandler->isSupport()) {
			$object_key = 'member_groups:' . getNumberingPath($member_srl) . $member_srl . '_0';
			$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
			$oCacheHandler->delete($cache_key);
		}
		$oCacheHandler = CacheHandler::getInstance('object');
		if($new_group_list && $del_group_list && $oCacheHandler->isSupport()) {
			$object_key = 'member_info:' . getNumberingPath($member_srl) . $member_srl;
			$cache_key = $oCacheHandler->getGroupKey('member', $object_key);
			$oCacheHandler->delete($cache_key);
		}
		return $output;
	}

	function triggerCopyModule(&$obj) {
		$oModuleModel = getModel('module');
		$pointConfig = $oModuleModel->getModulePartConfig('point', $obj->originModuleSrl);
		$oModuleController = getController('module');
		if(is_array($obj->moduleSrlList)) {
			foreach($obj->moduleSrlList AS $key=>$moduleSrl) $oModuleController->insertModulePartConfig('point', $moduleSrl, $pointConfig);
		}
	}
}
