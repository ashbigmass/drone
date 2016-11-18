<?php
class documentController extends document
	function init() {}

	function procDocumentVoteUp() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$document_srl = Context::get('target_srl');
		if(!$document_srl) return new Object(-1, 'msg_invalid_request');
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl, false, false);
		$module_srl = $oDocument->get('module_srl');
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		$document_config = $oModuleModel->getModulePartConfig('document',$module_srl);
		if($document_config->use_vote_up=='N') return new Object(-1, 'msg_invalid_request');
		$point = 1;
		$output = $this->updateVotedCount($document_srl, $point);
		$this->add('voted_count', $output->get('voted_count'));
		return $output;
	}

	function insertAlias($module_srl, $document_srl, $alias_title) {
		$args = new stdClass;
		$args->alias_srl = getNextSequence();
		$args->module_srl = $module_srl;
		$args->document_srl = $document_srl;
		$args->alias_title = urldecode($alias_title);
		$query = "document.insertAlias";
		$output = executeQuery($query, $args);
		return $output;
	}

	function procDocumentVoteDown() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$document_srl = Context::get('target_srl');
		if(!$document_srl) return new Object(-1, 'msg_invalid_request');
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl, false, false);
		$module_srl = $oDocument->get('module_srl');
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		$document_config = $oModuleModel->getModulePartConfig('document',$module_srl);
		if($document_config->use_vote_down=='N') return new Object(-1, 'msg_invalid_request');
		$point = -1;
		$output = $this->updateVotedCount($document_srl, $point);
		$this->add('blamed_count', $output->get('blamed_count'));
		return $output;
	}

	function procDocumentDeclare() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$document_srl = Context::get('target_srl');
		if(!$document_srl) return new Object(-1, 'msg_invalid_request');
		return $this->declaredDocument($document_srl);
	}

	function deleteDocumentAliasByModule($module_srl) {
		$args = new stdClass();
		$args->module_srl = $module_srl;
		executeQuery("document.deleteAlias", $args);
	}

	function deleteDocumentAliasByDocument($document_srl) {
		$args = new stdClass();
		$args->document_srl = $document_srl;
		executeQuery("document.deleteAlias", $args);
	}

	function deleteDocumentHistory($history_srl, $document_srl, $module_srl) {
		$args = new stdClass();
		$args->history_srl = $history_srl;
		$args->module_srl = $module_srl;
		$args->document_srl = $document_srl;
		if(!$args->history_srl && !$args->module_srl && !$args->document_srl) return;
		executeQuery("document.deleteHistory", $args);
	}

	function triggerDeleteModuleDocuments(&$obj) {
		$module_srl = $obj->module_srl;
		if(!$module_srl) return new Object();
		$oDocumentAdminController = getAdminController('document');
		$output = $oDocumentAdminController->deleteModuleDocument($module_srl);
		if(!$output->toBool()) return $output;
		$oDocumentController = getController('document');
		$output = $oDocumentController->deleteModuleCategory($module_srl);
		if(!$output->toBool()) return $output;
		$this->deleteDocumentExtraKeys($module_srl);
		$this->deleteDocumentAliasByModule($module_srl);
		$this->deleteDocumentHistory(null, null, $module_srl);
		return new Object();
	}

	function addGrant($document_srl) {
		$_SESSION['own_document'][$document_srl] = true;
	}

	function insertDocument($obj, $manual_inserted = false, $isRestore = false, $isLatest = true) {
		if(!$manual_inserted && !checkCSRF()) return new Object(-1, 'msg_invalid_request');
		$oDB = &DB::getInstance();
		$oDB->begin();
		if($obj->comment_status) $obj->commentStatus = $obj->comment_status;
		if(!$obj->commentStatus) $obj->commentStatus = 'DENY';
		if($obj->commentStatus == 'DENY') $this->_checkCommentStatusForOldVersion($obj);
		if($obj->allow_trackback!='Y') $obj->allow_trackback = 'N';
		if($obj->homepage)  {
			$obj->homepage = removeHackTag($obj->homepage);
			if(!preg_match('/^[a-z]+:\/\//i',$obj->homepage)) $obj->homepage = 'http://'.$obj->homepage;
		}
		if($obj->notify_message != 'Y') $obj->notify_message = 'N';
		if(!$obj->email_address) $obj->email_address = '';
		if(!$isRestore) $obj->ipaddress = $_SERVER['REMOTE_ADDR'];
		$grant = Context::get('grant');
		if(!$grant->manager) unset($obj->regdate);
		if(!is_string($obj->extra_vars)) $obj->extra_vars = serialize($obj->extra_vars);
		unset($obj->_saved_doc_srl);
		unset($obj->_saved_doc_title);
		unset($obj->_saved_doc_content);
		unset($obj->_saved_doc_message);
		$output = ModuleHandler::triggerCall('document.insertDocument', 'before', $obj);
		if(!$output->toBool()) return $output;
		if(!$obj->document_srl) $obj->document_srl = getNextSequence();
		elseif(!$manual_inserted && !$isRestore && !checkUserSequence($obj->document_srl)) return new Object(-1, 'msg_not_permitted');
		$oDocumentModel = getModel('document');
		if($obj->category_srl) {
			$category_list = $oDocumentModel->getCategoryList($obj->module_srl);
			if(count($category_list) > 0 && !$category_list[$obj->category_srl]->grant) return new Object(-1, 'msg_not_permitted');
			if(count($category_list) > 0 && !$category_list[$obj->category_srl]) $obj->category_srl = 0;
		}
		if(!$obj->readed_count) $obj->readed_count = 0;
		if($isLatest) $obj->update_order = $obj->list_order = $obj->document_srl * -1;
		else $obj->update_order = $obj->list_order;
		if($obj->password && !$obj->password_is_hashed) $obj->password = getModel('member')->hashPassword($obj->password);
		$logged_info = Context::get('logged_info');
		if(Context::get('is_logged') && !$manual_inserted && !$isRestore) {
			$obj->member_srl = $logged_info->member_srl;
			$obj->user_id = htmlspecialchars_decode($logged_info->user_id);
			$obj->user_name = htmlspecialchars_decode($logged_info->user_name);
			$obj->nick_name = htmlspecialchars_decode($logged_info->nick_name);
			$obj->email_address = $logged_info->email_address;
			$obj->homepage = $logged_info->homepage;
		}
		$obj->title = htmlspecialchars($obj->title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		settype($obj->title, "string");
		if($obj->title == '') $obj->title = cut_str(trim(strip_tags(nl2br($obj->content))),20,'...');
		if($obj->title == '') $obj->title = 'Untitled';
		$obj->content = preg_replace('!<\!--(Before|After)(Document|Comment)\(([0-9]+),([0-9]+)\)-->!is', '', $obj->content);
		if(Mobile::isFromMobilePhone()) {
			if($obj->use_html != 'Y') $obj->content = htmlspecialchars($obj->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			$obj->content = nl2br($obj->content);
		}
		if($logged_info->is_admin != 'Y') $obj->content = removeHackTag($obj->content);
		if(!$logged_info->member_srl && !$obj->nick_name) return new Object(-1,'msg_invalid_request');
		$obj->lang_code = Context::getLangType();
		if(!$obj->status) $this->_checkDocumentStatusForOldVersion($obj);
		$output = executeQuery('document.insertDocument', $obj);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$extra_keys = $oDocumentModel->getExtraKeys($obj->module_srl);
		if(count($extra_keys)) {
			foreach($extra_keys as $idx => $extra_item) {
				$value = NULL;
				if(isset($obj->{'extra_vars'.$idx})) {
					$tmp = $obj->{'extra_vars'.$idx};
					if(is_array($tmp)) $value = implode('|@|', $tmp);
					else $value = trim($tmp);
				} else if(isset($obj->{$extra_item->name})) {
					$value = trim($obj->{$extra_item->name});
				}
				if($value == NULL) continue;
				$this->insertDocumentExtraVar($obj->module_srl, $obj->document_srl, $idx, $value, $extra_item->eid);
			}
		}
		if($obj->category_srl) $this->updateCategoryCount($obj->module_srl, $obj->category_srl);
		if($output->toBool()) {
			$trigger_output = ModuleHandler::triggerCall('document.insertDocument', 'after', $obj);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
		}
		$oDB->commit();
		if(!$manual_inserted) $this->addGrant($obj->document_srl);
		$output->add('document_srl',$obj->document_srl);
		$output->add('category_srl',$obj->category_srl);
		return $output;
	}

	function updateDocument($source_obj, $obj, $manual_updated = FALSE) {
		$logged_info = Context::get('logged_info');
		if(!$manual_updated && !checkCSRF()) return new Object(-1, 'msg_invalid_request');
		if(!$source_obj->document_srl || !$obj->document_srl) return new Object(-1,'msg_invalied_request');
		if(!$obj->status && $obj->is_secret == 'Y') $obj->status = 'SECRET';
		if(!$obj->status) $obj->status = 'PUBLIC';
		$output = ModuleHandler::triggerCall('document.updateDocument', 'before', $obj);
		if(!$output->toBool()) return $output;
		$oDB = &DB::getInstance();
		$oDB->begin();
		$oModuleModel = getModel('module');
		if(!$obj->module_srl) $obj->module_srl = $source_obj->get('module_srl');
		$module_srl = $obj->module_srl;
		$document_config = $oModuleModel->getModulePartConfig('document', $module_srl);
		if(!$document_config) $document_config = new stdClass();
		if(!isset($document_config->use_history)) $document_config->use_history = 'N';
		$bUseHistory = $document_config->use_history == 'Y' || $document_config->use_history == 'Trace';
		if($bUseHistory) {
			$args = new stdClass;
			$args->history_srl = getNextSequence();
			$args->document_srl = $obj->document_srl;
			$args->module_srl = $module_srl;
			if($document_config->use_history == 'Y') $args->content = $source_obj->get('content');
			$args->nick_name = $source_obj->get('nick_name');
			$args->member_srl = $source_obj->get('member_srl');
			$args->regdate = $source_obj->get('last_update');
			$args->ipaddress = $source_obj->get('ipaddress');
			$output = executeQuery("document.insertHistory", $args);
		} else {
			$obj->ipaddress = $source_obj->get('ipaddress');
		}
		if($obj->comment_status) $obj->commentStatus = $obj->comment_status;
		if(!$obj->commentStatus) $obj->commentStatus = 'DENY';
		if($obj->commentStatus == 'DENY') $this->_checkCommentStatusForOldVersion($obj);
		if($obj->allow_trackback!='Y') $obj->allow_trackback = 'N';
		if($obj->homepage) {
			$obj->homepage = removeHackTag($obj->homepage);
			if(!preg_match('/^[a-z]+:\/\//i',$obj->homepage)) $obj->homepage = 'http://'.$obj->homepage;
		}
		if($obj->notify_message != 'Y') $obj->notify_message = 'N';
		$grant = Context::get('grant');
		if(!$grant->manager) unset($obj->regdate);
		if(!is_string($obj->extra_vars)) $obj->extra_vars = serialize($obj->extra_vars);
		unset($obj->_saved_doc_srl);
		unset($obj->_saved_doc_title);
		unset($obj->_saved_doc_content);
		unset($obj->_saved_doc_message);
		$oDocumentModel = getModel('document');
		if($source_obj->get('category_srl')!=$obj->category_srl) {
			$category_list = $oDocumentModel->getCategoryList($obj->module_srl);
			if(!$category_list[$obj->category_srl]) $obj->category_srl = 0;
		}
		$obj->update_order = getNextSequence() * -1;
		if($obj->password) $obj->password = getModel('member')->hashPassword($obj->password);
		if(Context::get('is_logged') && !$manual_updated) {
			if($source_obj->get('member_srl')==$logged_info->member_srl) {
				$obj->member_srl = $logged_info->member_srl;
				$obj->user_name = htmlspecialchars_decode($logged_info->user_name);
				$obj->nick_name = htmlspecialchars_decode($logged_info->nick_name);
				$obj->email_address = $logged_info->email_address;
				$obj->homepage = $logged_info->homepage;
			}
		}
		if($source_obj->get('member_srl')&& !$obj->nick_name) {
			$obj->member_srl = $source_obj->get('member_srl');
			$obj->user_name = $source_obj->get('user_name');
			$obj->nick_name = $source_obj->get('nick_name');
			$obj->email_address = $source_obj->get('email_address');
			$obj->homepage = $source_obj->get('homepage');
		}
		$obj->title = htmlspecialchars($obj->title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		settype($obj->title, "string");
		if($obj->title == '') $obj->title = cut_str(strip_tags($obj->content),20,'...');
		if($obj->title == '') $obj->title = 'Untitled';
		$obj->content = preg_replace('!<\!--(Before|After)(Document|Comment)\(([0-9]+),([0-9]+)\)-->!is', '', $obj->content);
		if(Mobile::isFromMobilePhone()) {
			if($obj->use_html != 'Y') $obj->content = htmlspecialchars($obj->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			$obj->content = nl2br($obj->content);
		}
		if($source_obj->get('lang_code') != Context::getLangType()) {
			if(!$source_obj->get('lang_code')) {
				$lang_code_args->document_srl = $source_obj->get('document_srl');
				$lang_code_args->lang_code = Context::getLangType();
				$output = executeQuery('document.updateDocumentsLangCode', $lang_code_args);
			} else {
				$extra_content = new stdClass;
				$extra_content->title = $obj->title;
				$extra_content->content = $obj->content;
				$document_args = new stdClass;
				$document_args->document_srl = $source_obj->get('document_srl');
				$document_output = executeQuery('document.getDocument', $document_args);
				$obj->title = $document_output->data->title;
				$obj->content = $document_output->data->content;
			}
		}
		if($logged_info->is_admin != 'Y') $obj->content = removeHackTag($obj->content);
		if($source_obj->get('status') == $this->getConfigStatus('temp')) $obj->regdate = date('YmdHis');
		$output = executeQuery('document.updateDocument', $obj);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		if(Context::get('act')!='procFileDelete') {
			$this->deleteDocumentExtraVars($source_obj->get('module_srl'), $obj->document_srl, null, Context::getLangType());
			$extra_keys = $oDocumentModel->getExtraKeys($obj->module_srl);
			if(count($extra_keys)) {
				foreach($extra_keys as $idx => $extra_item) {
					$value = NULL;
					if(isset($obj->{'extra_vars'.$idx})) {
						$tmp = $obj->{'extra_vars'.$idx};
						if(is_array($tmp)) $value = implode('|@|', $tmp);
						else $value = trim($tmp);
					} else if(isset($obj->{$extra_item->name})) {
						$value = trim($obj->{$extra_item->name});
					}
					if($value == NULL) continue;
					$this->insertDocumentExtraVar($obj->module_srl, $obj->document_srl, $idx, $value, $extra_item->eid);
				}
			}
			if($extra_content->title) $this->insertDocumentExtraVar($obj->module_srl, $obj->document_srl, -1, $extra_content->title, 'title_'.Context::getLangType());
			if($extra_content->content) $this->insertDocumentExtraVar($obj->module_srl, $obj->document_srl, -2, $extra_content->content, 'content_'.Context::getLangType());
		}
		if($source_obj->get('category_srl') != $obj->category_srl || $source_obj->get('module_srl') == $logged_info->member_srl) {
			if($source_obj->get('category_srl') != $obj->category_srl) $this->updateCategoryCount($obj->module_srl, $source_obj->get('category_srl'));
			if($obj->category_srl) $this->updateCategoryCount($obj->module_srl, $obj->category_srl);
		}
		if($output->toBool()) {
			$trigger_output = ModuleHandler::triggerCall('document.updateDocument', 'after', $obj);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
		}
		$oDB->commit();
		FileHandler::removeDir(sprintf('files/thumbnails/%s',getNumberingPath($obj->document_srl, 3)));
		$output->add('document_srl',$obj->document_srl);
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:'. getNumberingPath($obj->document_srl) . $obj->document_srl;
			$oCacheHandler->delete($cache_key);
		}
		return $output;
	}

	function deleteDocument($document_srl, $is_admin = false, $isEmptyTrash = false, $oDocument = null) {
		$trigger_obj = new stdClass();
		$trigger_obj->document_srl = $document_srl;
		$output = ModuleHandler::triggerCall('document.deleteDocument', 'before', $trigger_obj);
		if(!$output->toBool()) return $output;
		$oDB = &DB::getInstance();
		$oDB->begin();
		if(!$isEmptyTrash) {
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl, $is_admin);
		} else if($isEmptyTrash && $oDocument == null)
			return new Object(-1, 'document is not exists');
		if(!$oDocument->isExists() || $oDocument->document_srl != $document_srl) return new Object(-1, 'msg_invalid_document');
		if(!$oDocument->isGranted()) return new Object(-1, 'msg_not_permitted');
		$args = new stdClass();
		$args->document_srl = $document_srl;
		if(!$isEmptyTrash) {
			$output = executeQuery('document.deleteDocument', $args);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
		}
		$this->deleteDocumentAliasByDocument($document_srl);
		$this->deleteDocumentHistory(null, $document_srl, null);
		if($oDocument->get('category_srl')) $this->updateCategoryCount($oDocument->get('module_srl'),$oDocument->get('category_srl'));
		executeQuery('document.deleteDeclared', $args);
		$this->deleteDocumentExtraVars($oDocument->get('module_srl'), $oDocument->document_srl);
		if($output->toBool()) {
			$trigger_obj = $oDocument->getObjectVars();
			$trigger_output = ModuleHandler::triggerCall('document.deleteDocument', 'after', $trigger_obj);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
		}
		$this->_deleteDeclaredDocuments($args);
		$this->_deleteDocumentReadedLog($args);
		$this->_deleteDocumentVotedLog($args);
		FileHandler::removeDir(sprintf('files/thumbnails/%s',getNumberingPath($document_srl, 3)));
		$oDB->commit();
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);
		}
		return $output;
	}

	function _deleteDeclaredDocuments($documentSrls) {
		executeQuery('document.deleteDeclaredDocuments', $documentSrls);
		executeQuery('document.deleteDocumentDeclaredLog', $documentSrls);
	}

	function _deleteDocumentReadedLog($documentSrls) {
		executeQuery('document.deleteDocumentReadedLog', $documentSrls);
	}

	function _deleteDocumentVotedLog($documentSrls) {
		executeQuery('document.deleteDocumentVotedLog', $documentSrls);
	}

	function moveDocumentToTrash($obj) {
		$trash_args = new stdClass();
		if(!$obj->trash_srl) $trash_args->trash_srl = getNextSequence();
		else $trash_args->trash_srl = $obj->trash_srl;
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);
		$trash_args->module_srl = $oDocument->get('module_srl');
		$obj->module_srl = $oDocument->get('module_srl');
		if($trash_args->module_srl == 0) return false;
		$trash_args->document_srl = $obj->document_srl;
		$trash_args->description = $obj->description;
		if(Context::get('is_logged')&&!$manual_inserted) {
			$logged_info = Context::get('logged_info');
			$trash_args->member_srl = $logged_info->member_srl;
			$trash_args->user_id = htmlspecialchars_decode($logged_info->user_id);
			$trash_args->user_name = htmlspecialchars_decode($logged_info->user_name);
			$trash_args->nick_name = htmlspecialchars_decode($logged_info->nick_name);
		}
		$document_args = new stdClass;
		$document_args->module_srl = 0;
		$document_args->document_srl = $obj->document_srl;
		$oDB = &DB::getInstance();
		$oDB->begin();
		require_once(_XE_PATH_.'modules/trash/model/TrashVO.php');
		$oTrashVO = new TrashVO();
		$oTrashVO->setTrashSrl(getNextSequence());
		$oTrashVO->setTitle($oDocument->variables['title']);
		$oTrashVO->setOriginModule('document');
		$oTrashVO->setSerializedObject(serialize($oDocument->variables));
		$oTrashVO->setDescription($obj->description);
		$oTrashAdminController = getAdminController('trash');
		$output = $oTrashAdminController->insertTrash($oTrashVO);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$output = executeQuery('document.deleteDocument', $trash_args);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		if($oDocument->get('category_srl')) $this->updateCategoryCount($oDocument->get('module_srl'),$oDocument->get('category_srl'));
		FileHandler::removeDir(sprintf('files/thumbnails/%s',getNumberingPath($obj->document_srl, 3)));
		if($oDocument->hasUploadedFiles()) {
			$args = new stdClass();
			$args->upload_target_srl = $oDocument->document_srl;
			$args->isvalid = 'N';
			executeQuery('file.updateFileValid', $args);
		}
		if($output->toBool()) {
			$trigger_output = ModuleHandler::triggerCall('document.moveDocumentToTrash', 'after', $obj);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
		}
		$oDB->commit();
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:'. getNumberingPath($oDocument->document_srl) . $oDocument->document_srl;
			$oCacheHandler->delete($cache_key);
		}
		return $output;
	}

	function updateReadedCount(&$oDocument) {
		if(isCrawler()) return false;
		$document_srl = $oDocument->document_srl;
		$member_srl = $oDocument->get('member_srl');
		$logged_info = Context::get('logged_info');
		$trigger_output = ModuleHandler::triggerCall('document.updateReadedCount', 'before', $oDocument);
		if(!$trigger_output->toBool()) return $trigger_output;
		if($_SESSION['readed_document'][$document_srl]) return false;
		if($oDocument->get('ipaddress') == $_SERVER['REMOTE_ADDR']) {
			$_SESSION['readed_document'][$document_srl] = true;
			return false;
		}
		if($member_srl && $logged_info->member_srl == $member_srl) {
			$_SESSION['readed_document'][$document_srl] = true;
			return false;
		}
		$oDB = DB::getInstance();
		$oDB->begin();
		$args = new stdClass;
		$args->document_srl = $document_srl;
		$output = executeQuery('document.updateReadedCount', $args);
		$trigger_output = ModuleHandler::triggerCall('document.updateReadedCount', 'after', $oDocument);
		if(!$trigger_output->toBool()) {
			$oDB->rollback();
			return $trigger_output;
		}
		$oDB->commit();
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);
		}
		if(!$_SESSION['banned_document'][$document_srl])  $_SESSION['readed_document'][$document_srl] = true;
		return TRUE;
	}

	function insertDocumentExtraKey($module_srl, $var_idx, $var_name, $var_type, $var_is_required = 'N', $var_search = 'N', $var_default = '', $var_desc = '', $eid) {
		if(!$module_srl || !$var_idx || !$var_name || !$var_type || !$eid) return new Object(-1,'msg_invalid_request');
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		$obj->var_idx = $var_idx;
		$obj->var_name = $var_name;
		$obj->var_type = $var_type;
		$obj->var_is_required = $var_is_required=='Y'?'Y':'N';
		$obj->var_search = $var_search=='Y'?'Y':'N';
		$obj->var_default = $var_default;
		$obj->var_desc = $var_desc;
		$obj->eid = $eid;
		$output = executeQuery('document.getDocumentExtraKeys', $obj);
		if(!$output->data) {
			$output = executeQuery('document.insertDocumentExtraKey', $obj);
		} else {
			$output = executeQuery('document.updateDocumentExtraKey', $obj);
			$output = executeQuery('document.updateDocumentExtraVar', $obj);
		}
		$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
		if($oCacheHandler->isSupport()) {
			$object_key = 'module_document_extra_keys:'.$module_srl;
			$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
			$oCacheHandler->delete($cache_key);
		}
		return $output;
	}

	function deleteDocumentExtraKeys($module_srl, $var_idx = null) {
		if(!$module_srl) return new Object(-1,'msg_invalid_request');
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		if(!is_null($var_idx)) $obj->var_idx = $var_idx;
		$oDB = DB::getInstance();
		$oDB->begin();
		$output = $oDB->executeQuery('document.deleteDocumentExtraKeys', $obj);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		if($var_idx != NULL) {
			$output = $oDB->executeQuery('document.updateDocumentExtraKeyIdxOrder', $obj);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
		}
		$output =  executeQuery('document.deleteDocumentExtraVars', $obj);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		if($var_idx != NULL) {
			$output = $oDB->executeQuery('document.updateDocumentExtraVarIdxOrder', $obj);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
		}
		$oDB->commit();
		$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
		if($oCacheHandler->isSupport()) {
			$object_key = 'module_document_extra_keys:'.$module_srl;
			$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
			$oCacheHandler->delete($cache_key);
		}
		return new Object();
	}

	function insertDocumentExtraVar($module_srl, $document_srl, $var_idx, $value, $eid = null, $lang_code = '') {
		if(!$module_srl || !$document_srl || !$var_idx || !isset($value)) return new Object(-1,'msg_invalid_request');
		if(!$lang_code) $lang_code = Context::getLangType();
		$obj = new stdClass;
		$obj->module_srl = $module_srl;
		$obj->document_srl = $document_srl;
		$obj->var_idx = $var_idx;
		$obj->value = $value;
		$obj->lang_code = $lang_code;
		$obj->eid = $eid;
		executeQuery('document.insertDocumentExtraVar', $obj);
	}

	function deleteDocumentExtraVars($module_srl, $document_srl = null, $var_idx = null, $lang_code = null, $eid = null) {
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		if(!is_null($document_srl)) $obj->document_srl = $document_srl;
		if(!is_null($var_idx)) $obj->var_idx = $var_idx;
		if(!is_null($lang_code)) $obj->lang_code = $lang_code;
		if(!is_null($eid)) $obj->eid = $eid;
		$output = executeQuery('document.deleteDocumentExtraVars', $obj);
		return $output;
	}

	function updateVotedCount($document_srl, $point = 1) {
		if($point > 0) $failed_voted = 'failed_voted';
		else $failed_voted = 'failed_blamed';
		if($_SESSION['voted_document'][$document_srl]) return new Object(-1, $failed_voted);
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl, false, false);
		if($oDocument->get('ipaddress') == $_SERVER['REMOTE_ADDR']) {
			$_SESSION['voted_document'][$document_srl] = true;
			return new Object(-1, $failed_voted);
		}
		$oMemberModel = getModel('member');
		$member_srl = $oMemberModel->getLoggedMemberSrl();
		if($oDocument->get('member_srl')) {
			if($member_srl && $member_srl == abs($oDocument->get('member_srl'))) {
				$_SESSION['voted_document'][$document_srl] = true;
				return new Object(-1, $failed_voted);
			}
		}
		$args = new stdClass;
		if($member_srl) {
			$args->member_srl = $member_srl;
		} else {
			$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		}
		$args->document_srl = $document_srl;
		$output = executeQuery('document.getDocumentVotedLogInfo', $args);
		if($output->data->count) {
			$_SESSION['voted_document'][$document_srl] = true;
			return new Object(-1, $failed_voted);
		}
		$oDB = DB::getInstance();
		$oDB->begin();
		if($point < 0) {
			$args->blamed_count = $oDocument->get('blamed_count') + $point;
			$output = executeQuery('document.updateBlamedCount', $args);
		} else {
			$args->voted_count = $oDocument->get('voted_count') + $point;
			$output = executeQuery('document.updateVotedCount', $args);
		}
		if(!$output->toBool()) return $output;
		$args->point = $point;
		$output = executeQuery('document.insertDocumentVotedLog', $args);
		if(!$output->toBool()) return $output;
		$obj = new stdClass;
		$obj->member_srl = $oDocument->get('member_srl');
		$obj->module_srl = $oDocument->get('module_srl');
		$obj->document_srl = $oDocument->get('document_srl');
		$obj->update_target = ($point < 0) ? 'blamed_count' : 'voted_count';
		$obj->point = $point;
		$obj->before_point = ($point < 0) ? $oDocument->get('blamed_count') : $oDocument->get('voted_count');
		$obj->after_point = ($point < 0) ? $args->blamed_count : $args->voted_count;
		$trigger_output = ModuleHandler::triggerCall('document.updateVotedCount', 'after', $obj);
		if(!$trigger_output->toBool()) {
			$oDB->rollback();
			return $trigger_output;
		}
		$oDB->commit();
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);
		}
		$_SESSION['voted_document'][$document_srl] = true;
		$output = new Object();
		if($point > 0) {
			$output->setMessage('success_voted');
			$output->add('voted_count', $obj->after_point);
		} else {
			$output->setMessage('success_blamed');
			$output->add('blamed_count', $obj->after_point);
		}
		return $output;
	}

	function declaredDocument($document_srl) {
		if($_SESSION['declared_document'][$document_srl]) return new Object(-1, 'failed_declared');
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$output = executeQuery('document.getDeclaredDocument', $args);
		if(!$output->toBool()) return $output;
		$declared_count = ($output->data->declared_count) ? $output->data->declared_count : 0;
		$trigger_obj = new stdClass();
		$trigger_obj->document_srl = $document_srl;
		$trigger_obj->declared_count = $declared_count;
		$trigger_output = ModuleHandler::triggerCall('document.declaredDocument', 'before', $trigger_obj);
		if(!$trigger_output->toBool()) return $trigger_output;
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl, false, false);
		if($oDocument->get('ipaddress') == $_SERVER['REMOTE_ADDR']) {
			$_SESSION['declared_document'][$document_srl] = true;
			return new Object(-1, 'failed_declared');
		}
		if($oDocument->get('member_srl')) {
			$oMemberModel = getModel('member');
			$member_srl = $oMemberModel->getLoggedMemberSrl();
			if($member_srl && $member_srl == abs($oDocument->get('member_srl'))) {
				$_SESSION['declared_document'][$document_srl] = true;
				return new Object(-1, 'failed_declared');
			}
		}
		$args = new stdClass;
		if($member_srl) $args->member_srl = $member_srl;
		else $args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$args->document_srl = $document_srl;
		$output = executeQuery('document.getDocumentDeclaredLogInfo', $args);
		if($output->data->count) {
			$_SESSION['declared_document'][$document_srl] = true;
			return new Object(-1, 'failed_declared');
		}
		$oDB = &DB::getInstance();
		$oDB->begin();
		if($declared_count > 0) $output = executeQuery('document.updateDeclaredDocument', $args);
		else $output = executeQuery('document.insertDeclaredDocument', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('document.insertDocumentDeclaredLog', $args);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$this->add('declared_count', $declared_count+1);
		$trigger_obj->declared_count = $declared_count + 1;
		$trigger_output = ModuleHandler::triggerCall('document.declaredDocument', 'after', $trigger_obj);
		if(!$trigger_output->toBool()) {
			$oDB->rollback();
			return $trigger_output;
		}
		$oDB->commit();
		$_SESSION['declared_document'][$document_srl] = true;
		$this->setMessage('success_declared');
	}

	function updateCommentCount($document_srl, $comment_count, $last_updater, $comment_inserted = false) {
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->comment_count = $comment_count;
		if($comment_inserted) {
			$args->update_order = -1*getNextSequence();
			$args->last_updater = $last_updater;
			$oCacheHandler = CacheHandler::getInstance('object');
			if($oCacheHandler->isSupport()) {
				$cache_key = 'document_item:'. getNumberingPath($document_srl) . $document_srl;
				$oCacheHandler->delete($cache_key);
			}
		}
		return executeQuery('document.updateCommentCount', $args);
	}

	function updateTrackbackCount($document_srl, $trackback_count) {
		$args = new stdClass;
		$args->document_srl = $document_srl;
		$args->trackback_count = $trackback_count;
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);
		}
		return executeQuery('document.updateTrackbackCount', $args);
	}

	function insertCategory($obj) {
		if($obj->parent_srl) {
			$oDocumentModel = getModel('document');
			$parent_category = $oDocumentModel->getCategory($obj->parent_srl);
			$obj->list_order = $parent_category->list_order;
			$this->updateCategoryListOrder($parent_category->module_srl, $parent_category->list_order+1);
			if(!$obj->category_srl) $obj->category_srl = getNextSequence();
		} else {
			$obj->list_order = $obj->category_srl = getNextSequence();
		}
		$output = executeQuery('document.insertCategory', $obj);
		if($output->toBool()) {
			$output->add('category_srl', $obj->category_srl);
			$this->makeCategoryFile($obj->module_srl);
		}
		return $output;
	}

	function updateCategoryListOrder($module_srl, $list_order) {
		$args = new stdClass;
		$args->module_srl = $module_srl;
		$args->list_order = $list_order;
		return executeQuery('document.updateCategoryOrder', $args);
	}

	function updateCategoryCount($module_srl, $category_srl, $document_count = 0) {
		$oDocumentModel = getModel('document');
		if(!$document_count) $document_count = $oDocumentModel->getCategoryDocumentCount($module_srl,$category_srl);
		$args = new stdClass;
		$args->category_srl = $category_srl;
		$args->document_count = $document_count;
		$output = executeQuery('document.updateCategoryCount', $args);
		if($output->toBool()) $this->makeCategoryFile($module_srl);
		return $output;
	}

	function updateCategory($obj) {
		$output = executeQuery('document.updateCategory', $obj);
		if($output->toBool()) $this->makeCategoryFile($obj->module_srl);
		return $output;
	}

	function deleteCategory($category_srl) {
		$args = new stdClass();
		$args->category_srl = $category_srl;
		$oDocumentModel = getModel('document');
		$category_info = $oDocumentModel->getCategory($category_srl);
		$output = executeQuery('document.getChildCategoryCount', $args);
		if(!$output->toBool()) return $output;
		if($output->data->count>0) return new Object(-1, 'msg_cannot_delete_for_child');
		$output = executeQuery('document.deleteCategory', $args);
		if(!$output->toBool()) return $output;
		$this->makeCategoryFile($category_info->module_srl);
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$page = 0;
			while(true) {
				$args = new stdClass();
				$args->category_srl = $category_srl;
				$args->list_count = 100;
				$args->page = ++$page;
				$output = executeQuery('document.getDocumentList', $args, array('document_srl'));
				if($output->data == array()) break;
				foreach($output->data as $val) {
					$cache_key = 'document_item:'. getNumberingPath($val->document_srl) . $val->document_srl;
					$oCacheHandler->delete($cache_key);
				}
			}
		}
		$args = new stdClass();
		$args->target_category_srl = 0;
		$args->source_category_srl = $category_srl;
		$output = executeQuery('document.updateDocumentCategory', $args);
		return $output;
	}

	function deleteModuleCategory($module_srl) {
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('document.deleteModuleCategory', $args);
		return $output;
	}

	function moveCategoryUp($category_srl) {
		$oDocumentModel = getModel('document');
		$args = new stdClass;
		$args->category_srl = $category_srl;
		$output = executeQuery('document.getCategory', $args);
		$category = $output->data;
		$list_order = $category->list_order;
		$module_srl = $category->module_srl;
		$category_list = $oDocumentModel->getCategoryList($module_srl);
		$category_srl_list = array_keys($category_list);
		if(count($category_srl_list)<2) return new Object();
		$prev_category = NULL;
		foreach($category_list as $key => $val) {
			if($key==$category_srl) break;
			$prev_category = $val;
		}
		if(!$prev_category) return new Object(-1,Context::getLang('msg_category_not_moved'));
		if($category_srl_list[0]==$category_srl) return new Object(-1,Context::getLang('msg_category_not_moved'));
		$cur_args = new stdClass;
		$cur_args->category_srl = $category_srl;
		$cur_args->list_order = $prev_category->list_order;
		$cur_args->title = $category->title;
		$this->updateCategory($cur_args);
		$prev_args = new stdClass;
		$prev_args->category_srl = $prev_category->category_srl;
		$prev_args->list_order = $list_order;
		$prev_args->title = $prev_category->title;
		$this->updateCategory($prev_args);
		return new Object();
	}

	function moveCategoryDown($category_srl) {
		$oDocumentModel = getModel('document');
		$args = new stdClass;
		$args->category_srl = $category_srl;
		$output = executeQuery('document.getCategory', $args);
		$category = $output->data;
		$list_order = $category->list_order;
		$module_srl = $category->module_srl;
		$category_list = $oDocumentModel->getCategoryList($module_srl);
		$category_srl_list = array_keys($category_list);
		if(count($category_srl_list)<2) return new Object();
		for($i=0;$i<count($category_srl_list);$i++) if($category_srl_list[$i]==$category_srl) break;
		$next_category_srl = $category_srl_list[$i+1];
		if(!$category_list[$next_category_srl]) return new Object(-1,Context::getLang('msg_category_not_moved'));
		$next_category = $category_list[$next_category_srl];
		$cur_args = new stdClass;
		$cur_args->category_srl = $category_srl;
		$cur_args->list_order = $next_category->list_order;
		$cur_args->title = $category->title;
		$this->updateCategory($cur_args);
		$next_args = new stdClass;
		$next_args->category_srl = $next_category->category_srl;
		$next_args->list_order = $list_order;
		$next_args->title = $next_category->title;
		$this->updateCategory($next_args);
		return new Object();
	}

	function addXmlJsFilter($module_srl) {
		$oDocumentModel = getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($module_srl);
		if(!count($extra_keys)) return;
		$js_code = array();
		$js_code[] = '<script>//<![CDATA[';
		$js_code[] = '(function($){';
		$js_code[] = 'var validator = xe.getApp("validator")[0];';
		$js_code[] = 'if(!validator) return false;';
		$logged_info = Context::get('logged_info');
		foreach($extra_keys as $idx => $val) {
			$idx = $val->idx;
			if($val->type == 'kr_zip') $idx .= '[]';
			$name = str_ireplace(array('<script', '</script'), array('<scr" + "ipt', '</scr" + "ipt'), $val->name);
			$js_code[] = sprintf('validator.cast("ADD_MESSAGE", ["extra_vars%s","%s"]);', $idx, $name);
			if($val->is_required == 'Y') $js_code[] = sprintf('validator.cast("ADD_EXTRA_FIELD", ["extra_vars%s", { required:true }]);', $idx);
		}
		$js_code[] = '})(jQuery);';
		$js_code[] = '//]]></script>';
		$js_code   = implode("\n", $js_code);
		Context::addHtmlHeader($js_code);
	}

	function procDocumentInsertCategory($args = null) {
		if(!$args) $args = Context::gets('module_srl','category_srl','parent_srl','category_title','category_description','expand','group_srls','category_color','mid');
		$args->title = $args->category_title;
		$args->description = $args->category_description;
		$args->color = $args->category_color;
		if(!$args->module_srl && $args->mid) {
			$mid = $args->mid;
			unset($args->mid);
			$args->module_srl = $this->module_srl;
		}
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl, $columnList);
		$grant = $oModuleModel->getGrant($module_info, Context::get('logged_info'));
		if(!$grant->manager) return new Object(-1,'msg_not_permitted');
		if($args->expand !="Y") $args->expand = "N";
		if(!is_array($args->group_srls)) $args->group_srls = str_replace('|@|',',',$args->group_srls);
		else $args->group_srls = implode(',', $args->group_srls);
		$args->parent_srl = (int)$args->parent_srl;
		$oDocumentModel = getModel('document');
		$oDB = &DB::getInstance();
		$oDB->begin();
		if($args->category_srl) {
			$category_info = $oDocumentModel->getCategory($args->category_srl);
			if($category_info->category_srl != $args->category_srl) $args->category_srl = null;
		}
		if($args->category_srl) {
			$output = $this->updateCategory($args);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
		} else {
			$output = $this->insertCategory($args);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
		}
		$xml_file = $this->makeCategoryFile($args->module_srl);
		$oDB->commit();
		$this->add('xml_file', $xml_file);
		$this->add('module_srl', $args->module_srl);
		$this->add('category_srl', $args->category_srl);
		$this->add('parent_srl', $args->parent_srl);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : Context::get('error_return_url');
		$this->setRedirectUrl($returnUrl);
	}

	function procDocumentMoveCategory() {
		$source_category_srl = Context::get('source_srl');
		$parent_category_srl = Context::get('parent_srl');
		$target_category_srl = Context::get('target_srl');
		$oDocumentModel = getModel('document');
		$source_category = $oDocumentModel->getCategory($source_category_srl);
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($source_category->module_srl, $columnList);
		$grant = $oModuleModel->getGrant($module_info, Context::get('logged_info'));
		if(!$grant->manager) return new Object(-1,'msg_not_permitted');
		$source_args = new stdClass;
		if($parent_category_srl > 0 || ($parent_category_srl == 0 && $target_category_srl == 0)) {
			$parent_category = $oDocumentModel->getCategory($parent_category_srl);
			$args = new stdClass;
			$args->module_srl = $source_category->module_srl;
			$args->parent_srl = $parent_category_srl;
			$output = executeQuery('document.getChildCategoryMinListOrder', $args);
			if(!$output->toBool()) return $output;
			$args->list_order = (int)$output->data->list_order;
			if(!$args->list_order) $args->list_order = 0;
			$args->list_order--;
			$source_args->category_srl = $source_category_srl;
			$source_args->parent_srl = $parent_category_srl;
			$source_args->list_order = $args->list_order;
			$output = $this->updateCategory($source_args);
			if(!$output->toBool()) return $output;
		} else if($target_category_srl > 0) {
			$target_category = $oDocumentModel->getCategory($target_category_srl);
			$output = $this->updateCategoryListOrder($target_category->module_srl, $target_category->list_order+1);
			if(!$output->toBool()) return $output;
			$source_args->category_srl = $source_category_srl;
			$source_args->parent_srl = $target_category->parent_srl;
			$source_args->list_order = $target_category->list_order+1;
			$output = $this->updateCategory($source_args);
			if(!$output->toBool()) return $output;
		}
		$xml_file = $this->makeCategoryFile($source_category->module_srl);
		$this->add('xml_file', $xml_file);
		$this->add('source_category_srl', $source_category_srl);
	}

	function procDocumentDeleteCategory() {
		$args = Context::gets('module_srl','category_srl');
		$oDB = &DB::getInstance();
		$oDB->begin();
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl, $columnList);
		$grant = $oModuleModel->getGrant($module_info, Context::get('logged_info'));
		if(!$grant->manager) return new Object(-1,'msg_not_permitted');
		$oDocumentModel = getModel('document');
		$category_info = $oDocumentModel->getCategory($args->category_srl);
		if($category_info->parent_srl) $parent_srl = $category_info->parent_srl;
		if($oDocumentModel->getCategoryChlidCount($args->category_srl)) return new Object(-1, 'msg_cannot_delete_for_child');
		$output = $this->deleteCategory($args->category_srl);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$xml_file = $this->makeCategoryFile($args->module_srl);
		$oDB->commit();
		$this->add('xml_file', $xml_file);
		$this->add('category_srl', $parent_srl);
		$this->setMessage('success_deleted');
	}

	function procDocumentMakeXmlFile() {
		$module_srl = Context::get('module_srl');
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		$grant = $oModuleModel->getGrant($module_info, Context::get('logged_info'));
		if(!$grant->manager) return new Object(-1,'msg_not_permitted');
		$xml_file = $this->makeCategoryFile($module_srl);
		$this->add('xml_file',$xml_file);
	}

	function makeCategoryFile($module_srl) {
		if(!$module_srl) return false;
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'mid', 'site_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		$mid = $module_info->mid;
		if(!is_dir('./files/cache/document_category')) FileHandler::makeDir('./files/cache/document_category');
		$xml_file = sprintf("./files/cache/document_category/%s.xml.php", $module_srl);
		$php_file = sprintf("./files/cache/document_category/%s.php", $module_srl);
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$args->sort_index = 'list_order';
		$output = executeQueryArray('document.getCategoryList', $args);
		$category_list = $output->data;
		if(!is_array($category_list)) $category_list = array($category_list);
		$category_count = count($category_list);
		for($i=0;$i<$category_count;$i++) {
			$category_srl = $category_list[$i]->category_srl;
			if(!preg_match('/^[0-9,]+$/', $category_list[$i]->group_srls)) $category_list[$i]->group_srls = '';
			$list[$category_srl] = $category_list[$i];
		}
		if(!$list) {
			$xml_buff = "<root />";
			FileHandler::writeFile($xml_file, $xml_buff);
			FileHandler::writeFile($php_file, '<?php if(!defined("__XE__")) exit(); ?>');
			return $xml_file;
		}
		if(!is_array($list)) $list = array($list);
		foreach($list as $category_srl => $node) {
			$node->mid = $mid;
			$parent_srl = (int)$node->parent_srl;
			$tree[$parent_srl][$category_srl] = $node;
		}
		$header_script =
			'$lang_type = Context::getLangType(); '.
			'$is_logged = Context::get(\'is_logged\'); '.
			'$logged_info = Context::get(\'logged_info\'); '.
			'if($is_logged) {'.
			'if($logged_info->is_admin=="Y") $is_admin = true; '.
			'else $is_admin = false; '.
			'$group_srls = array_keys($logged_info->group_list); '.
			'} else { '.
			'$is_admin = false; '.
			'$group_srsl = array(); '.
			'} '."\n";
		$xml_header_buff = '';
		$xml_body_buff = $this->getXmlTree($tree[0], $tree, $module_info->site_srl, $xml_header_buff);
		$xml_buff = sprintf(
			'<?php '.
			'define(\'__XE__\', true); '.
			'require_once(\''.FileHandler::getRealPath('./config/config.inc.php').'\'); '.
			'$oContext = &Context::getInstance(); '.
			'$oContext->init(); '.
			'header("Content-Type: text/xml; charset=UTF-8"); '.
			'header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); '.
			'header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); '.
			'header("Cache-Control: no-store, no-cache, must-revalidate"); '.
			'header("Cache-Control: post-check=0, pre-check=0", false); '.
			'header("Pragma: no-cache"); '.
			'%s'.
			'%s '.
			'$oContext->close();'.
			'?>'.
			'<root>%s</root>',
			$header_script,
			$xml_header_buff,
			$xml_body_buff
		);
		$php_header_buff = '$_titles = array();';
		$php_header_buff .= '$_descriptions = array();';
		$php_output = $this->getPhpCacheCode($tree[0], $tree, $module_info->site_srl, $php_header_buff);
		$php_buff = sprintf(
			'<?php '.
			'if(!defined("__XE__")) exit(); '.
			'%s'.
			'%s'.
			'$menu = new stdClass;'.
			'$menu->list = array(%s); ',
			$header_script,
			$php_header_buff,
			$php_output['buff']
		);
		FileHandler::writeFile($xml_file, $xml_buff);
		FileHandler::writeFile($php_file, $php_buff);
		return $xml_file;
	}

	function getXmlTree($source_node, $tree, $site_srl, &$xml_header_buff) {
		if(!$source_node) return;
		foreach($source_node as $category_srl => $node) {
			$child_buff = "";
			if($category_srl && $tree[$category_srl]) $child_buff = $this->getXmlTree($tree[$category_srl], $tree, $site_srl, $xml_header_buff);
			$expand = $node->expand;
			$group_srls = $node->group_srls;
			$mid = $node->mid;
			$module_srl = $node->module_srl;
			$parent_srl = $node->parent_srl;
			$color = $node->color;
			$description = $node->description;
			if($group_srls) $group_check_code = sprintf('($is_admin==true||(is_array($group_srls)&&count(array_intersect($group_srls, array(%s)))))',$group_srls);
			else $group_check_code = "true";
			$title = $node->title;
			$oModuleAdminModel = getAdminModel('module');
			$langs = $oModuleAdminModel->getLangCode($site_srl, $title);
			if(count($langs)) {
				foreach($langs as $key => $val) {
					$xml_header_buff .= sprintf('$_titles[%d]["%s"] = "%s"; ', $category_srl, $key, str_replace('"','\\"',htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false)));
				}
			}
			$langx = $oModuleAdminModel->getLangCode($site_srl, $description);
			if(count($langx)) {
				foreach($langx as $key => $val) {
					$xml_header_buff .= sprintf('$_descriptions[%d]["%s"] = "%s"; ', $category_srl, $key, str_replace('"','\\"',htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false)));
				}
			}
			$attribute = sprintf(
				'mid="%s" module_srl="%d" node_srl="%d" parent_srl="%d" category_srl="%d" text="<?php echo (%s?($_titles[%d][$lang_type]):"")?>" url="%s" expand="%s" color="%s" description="<?php echo (%s?($_descriptions[%d][$lang_type]):"")?>" document_count="%d" ',
				$mid,
				$module_srl,
				$category_srl,
				$parent_srl,
				$category_srl,
				$group_check_code,
				$category_srl,
				getUrl('','mid',$node->mid,'category',$category_srl),
				$expand,
				htmlspecialchars($color, ENT_COMPAT | ENT_HTML401, 'UTF-8', false),
				$group_check_code,
				$category_srl,
				$node->document_count
			);
			if($child_buff) $buff .= sprintf('<node %s>%s</node>', $attribute, $child_buff);
			else $buff .=  sprintf('<node %s />', $attribute);
		}
		return $buff;
	}

	function getPhpCacheCode($source_node, $tree, $site_srl, &$php_header_buff) {
		$output = array("buff"=>"", "category_srl_list"=>array());
		if(!$source_node) return $output;
		foreach($source_node as $category_srl => $node) {
			if($category_srl && $tree[$category_srl]){
				$child_output = $this->getPhpCacheCode($tree[$category_srl], $tree, $site_srl, $php_header_buff);
			} else {
				$child_output = array("buff"=>"", "category_srl_list"=>array());
			}
			$child_output['category_srl_list'][] = $node->category_srl;
			$output['category_srl_list'] = array_merge($output['category_srl_list'], $child_output['category_srl_list']);
			if($node->group_srls) {
				$group_check_code = sprintf('($is_admin==true||(is_array($group_srls)&&count(array_intersect($group_srls, array(%s)))))',$node->group_srls);
			} else {
				$group_check_code = "true";
			}
			$selected = '"' . implode('","', $child_output['category_srl_list']) . '"';
			$child_buff = $child_output['buff'];
			$expand = $node->expand;
			$title = $node->title;
			$description = $node->description;
			$oModuleAdminModel = getAdminModel('module');
			$langs = $oModuleAdminModel->getLangCode($site_srl, $title);
			if(count($langs)) {
				foreach($langs as $key => $val) {
					$val = htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
					$php_header_buff .= sprintf(
						'$_titles[%d]["%s"] = "%s"; ',
						$category_srl,
						$key,
						str_replace('"','\\"', $val)
					);
				}
			}
			$langx = $oModuleAdminModel->getLangCode($site_srl, $description);
			if(count($langx)) {
				foreach($langx as $key => $val) {
					$val = htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
					$php_header_buff .= sprintf(
						'$_descriptions[%d]["%s"] = "%s"; ',
						$category_srl,
						$key,
						str_replace('"','\\"', $val)
					);
				}
			}
			$attribute = sprintf(
				'"mid" => "%s", "module_srl" => "%d","node_srl"=>"%s","category_srl"=>"%s","parent_srl"=>"%s","text"=>$_titles[%d][$lang_type],"selected"=>(in_array(Context::get("category"),array(%s))?1:0),"expand"=>"%s","color"=>"%s","description"=>$_descriptions[%d][$lang_type],"list"=>array(%s),"document_count"=>"%d","grant"=>%s?true:false',
				$node->mid,
				$node->module_srl,
				$node->category_srl,
				$node->category_srl,
				$node->parent_srl,
				$node->category_srl,
				$selected,
				$expand,
				$node->color,
				$node->category_srl,
				$child_buff,
				$node->document_count,
				$group_check_code
			);
			$output['buff'] .=  sprintf('%s=>array(%s),', $node->category_srl, $attribute);
		}
		return $output;
	}

	function addDocumentPopupMenu($url, $str, $icon = '', $target = 'self') {
		$document_popup_menu_list = Context::get('document_popup_menu_list');
		if(!is_array($document_popup_menu_list)) $document_popup_menu_list = array();
		$obj = new stdClass();
		$obj->url = $url;
		$obj->str = $str;
		$obj->icon = $icon;
		$obj->target = $target;
		$document_popup_menu_list[] = $obj;
		Context::set('document_popup_menu_list', $document_popup_menu_list);
	}

	function procDocumentAddCart() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_permitted');
		$srls = explode(',',Context::get('srls'));
		for($i = 0; $i < count($srls); $i++) {
			$srl = trim($srls[$i]);
			if(!$srl) continue;
			$document_srls[] = $srl;
		}
		if(!count($document_srls)) return;
		$args = new stdClass;
		$args->list_count = count($document_srls);
		$args->document_srls = implode(',',$document_srls);
		$args->order_type = 'asc';
		$output = executeQueryArray('document.getDocuments', $args);
		if(!$output->data) return new Object();
		unset($document_srls);
		foreach($output->data as $key => $val) $document_srls[$val->module_srl][] = $val->document_srl;
		if(!$document_srls || !count($document_srls)) return new Object();
		$oModuleModel = getModel('module');
		$module_srls = array_keys($document_srls);
		for($i=0;$i<count($module_srls);$i++) {
			$module_srl = $module_srls[$i];
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			$logged_info = Context::get('logged_info');
			if($logged_info->is_admin != 'Y') {
				if(!$module_info) {
					unset($document_srls[$module_srl]);
					continue;
				}
				$grant = $oModuleModel->getGrant($module_info, $logged_info);
				if(!$grant->manager) {
					unset($document_srls[$module_srl]);
					continue;
				}
			}
		}
		if(!count($document_srls)) return new Object();
		foreach($document_srls as $module_srl => $documents) {
			$cnt = count($documents);
			for($i=0;$i<$cnt;$i++) {
				$document_srl = (int)trim($documents[$i]);
				if(!$document_srls) continue;
				if($_SESSION['document_management'][$document_srl]) unset($_SESSION['document_management'][$document_srl]);
				else $_SESSION['document_management'][$document_srl] = true;
			}
		}
	}

	function procDocumentManageCheckedDocument() {
		@set_time_limit(0);
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');
		if(!checkCSRF()) return new Object(-1, 'msg_invalid_request');
		$type = Context::get('type');
		$target_module = Context::get('target_module');
		$module_srl = Context::get('module_srl');
		if($target_module && !$module_srl) $module_srl = $target_module;
		$category_srl = Context::get('target_category');
		$message_content = Context::get('message_content');
		if($message_content) $message_content = nl2br($message_content);
		$cart = Context::get('cart');
		if(!is_array($cart)) $document_srl_list = explode('|@|', $cart);
		else $document_srl_list = $cart;
		$document_srl_count = count($document_srl_list);
		$oDocumentModel = getModel('document');
		$document_items = array();
		foreach($document_srl_list as $document_srl) {
			$oDocument = $oDocumentModel->getDocument($document_srl);
			$document_items[] = $oDocument;
			if(!$oDocument->isGranted()) return $this->stop('msg_not_permitted');
		}
		if($message_content) {
			$oCommunicationController = getController('communication');
			$logged_info = Context::get('logged_info');
			$title = cut_str($message_content,10,'...');
			$sender_member_srl = $logged_info->member_srl;
			foreach($document_items as $oDocument) {
				if(!$oDocument->get('member_srl') || $oDocument->get('member_srl')==$sender_member_srl) continue;
				if($type=='move') $purl = sprintf("<a href=\"%s\" onclick=\"window.open(this.href);return false;\">%s</a>", $oDocument->getPermanentUrl(), $oDocument->getPermanentUrl());
				else $purl = "";
				$content = sprintf("<div>%s</div><hr />%s<div style=\"font-weight:bold\">%s</div>%s",$message_content, $purl, $oDocument->getTitleText(), $oDocument->getContent(false, false, false));
				$oCommunicationController->sendMessage($sender_member_srl, $oDocument->get('member_srl'), $title, $content, false);
			}
		}
		$oSpamController = getController('spamfilter');
		$oSpamController->setAvoidLog();
		$oDocumentAdminController = getAdminController('document');
		if($type == 'move') {
			if(!$module_srl) return new Object(-1, 'fail_to_move');
			$output = $oDocumentAdminController->moveDocumentModule($document_srl_list, $module_srl, $category_srl);
			if(!$output->toBool()) return new Object(-1, 'fail_to_move');
			$msg_code = 'success_moved';
		} else if($type == 'copy') {
			if(!$module_srl) return new Object(-1, 'fail_to_move');
			$output = $oDocumentAdminController->copyDocumentModule($document_srl_list, $module_srl, $category_srl);
			if(!$output->toBool()) return new Object(-1, 'fail_to_move');
			$msg_code = 'success_copied';
		} else if($type =='delete') {
			$oDB = &DB::getInstance();
			$oDB->begin();
			for($i=0;$i<$document_srl_count;$i++) {
				$document_srl = $document_srl_list[$i];
				$output = $this->deleteDocument($document_srl, true);
				if(!$output->toBool()) return new Object(-1, 'fail_to_delete');
			}
			$oDB->commit();
			$msg_code = 'success_deleted';
		} else if($type == 'trash') {
			$args = new stdClass();
			$args->description = $message_content;
			$oDB = &DB::getInstance();
			$oDB->begin();
			for($i=0;$i<$document_srl_count;$i++) {
				$args->document_srl = $document_srl_list[$i];
				$output = $this->moveDocumentToTrash($args);
				if(!$output || !$output->toBool()) return new Object(-1, 'fail_to_trash');
			}
			$oDB->commit();
			$msg_code = 'success_trashed';
		} else if($type == 'cancelDeclare') {
			$args->document_srl = $document_srl_list;
			$output = executeQuery('document.deleteDeclaredDocuments', $args);
			$msg_code = 'success_declare_canceled';
		}
		$_SESSION['document_management'] = array();
		$this->setMessage($msg_code);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDocumentAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	function procDocumentInsertModuleConfig() {
		$module_srl = Context::get('target_module_srl');
		if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
		else $module_srl = array($module_srl);
		$document_config = new stdClass();
		$document_config->use_history = Context::get('use_history');
		if(!$document_config->use_history) $document_config->use_history = 'N';
		$document_config->use_vote_up = Context::get('use_vote_up');
		if(!$document_config->use_vote_up) $document_config->use_vote_up = 'Y';
		$document_config->use_vote_down = Context::get('use_vote_down');
		if(!$document_config->use_vote_down) $document_config->use_vote_down = 'Y';
		$document_config->use_status = Context::get('use_status');
		$oModuleController = getController('module');
		for($i=0;$i<count($module_srl);$i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			$output = $oModuleController->insertModulePartConfig('document',$srl,$document_config);
		}
		$this->setError(-1);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispBoardAdminContent');
		$this->setRedirectUrl($returnUrl);
	}

	function procDocumentTempSave() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
		$module_info = Context::get('module_info');
		$logged_info = Context::get('logged_info');
		$obj = Context::getRequestVars();
		$obj->module_srl = $module_info->module_srl;
		$obj->status = $this->getConfigStatus('temp');
		unset($obj->is_notice);
		if(!$obj->title) $obj->title = cut_str(strip_tags($obj->content), 20, '...');
		$oDocumentModel = getModel('document');
		$oDocumentController = getController('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);
		if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl) {
			if($oDocument->get('module_srl') != $obj->module_srl) return new Object(-1, 'msg_invalid_request');
			if(!$oDocument->isGranted()) return new Object(-1, 'msg_invalid_request');
			$obj->status = $oDocument->get('status');
			$output = $oDocumentController->updateDocument($oDocument, $obj);
			$msg_code = 'success_updated';
		} else {
			$output = $oDocumentController->insertDocument($obj);
			$msg_code = 'success_registed';
			$obj->document_srl = $output->get('document_srl');
			$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);
		}
		if($oDocument->hasUploadedFiles()) {
			$args = new stdClass;
			$args->upload_target_srl = $oDocument->document_srl;
			$args->isvalid = 'N';
			executeQuery('file.updateFileValid', $args);
		}
		$this->setMessage('success_saved');
		$this->add('document_srl', $obj->document_srl);
	}

	function procDocumentGetList() {
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');
		$documentSrls = Context::get('document_srls');
		if($documentSrls) $documentSrlList = explode(',', $documentSrls);
		if(count($documentSrlList) > 0) {
			$oDocumentModel = getModel('document');
			$columnList = array('document_srl', 'title', 'nick_name', 'status');
			$documentList = $oDocumentModel->getDocuments($documentSrlList, $this->grant->is_admin, false, $columnList);
		} else {
			global $lang;
			$documentList = array();
			$this->setMessage($lang->no_documents);
		}
		$oSecurity = new Security($documentList);
		$oSecurity->encodeHTML('..variables.');
		$this->add('document_list', $documentList);
	}

	function _checkCommentStatusForOldVersion(&$obj) {
		if(!isset($obj->allow_comment)) $obj->allow_comment = 'N';
		if(!isset($obj->lock_comment)) $obj->lock_comment = 'N';
		if($obj->allow_comment == 'Y' && $obj->lock_comment == 'N') $obj->commentStatus = 'ALLOW';
		else $obj->commentStatus = 'DENY';
	}

	function _checkDocumentStatusForOldVersion(&$obj) {
		if(!$obj->status && $obj->is_secret == 'Y') $obj->status = $this->getConfigStatus('secret');
		if(!$obj->status && $obj->is_secret != 'Y') $obj->status = $this->getConfigStatus('public');
	}

	public function updateUploaedCount($documentSrlList) {
		$oDocumentModel = getModel('document');
		$oFileModel = getModel('file');
		if(is_array($documentSrlList)) {
			$documentSrlList = array_unique($documentSrlList);
			foreach($documentSrlList AS $key => $documentSrl) {
				$fileCount = $oFileModel->getFilesCount($documentSrl);
				$args = new stdClass();
				$args->document_srl = $documentSrl;
				$args->uploaded_count = $fileCount;
				executeQuery('document.updateUploadedCount', $args);
			}
		}
	}

	function triggerCopyModuleExtraKeys(&$obj) {
		$oDocumentModel = getModel('document');
		$documentExtraKeys = $oDocumentModel->getExtraKeys($obj->originModuleSrl);
		if(is_array($documentExtraKeys) && is_array($obj->moduleSrlList)) {
			$oDocumentController=getController('document');
			foreach($obj->moduleSrlList AS $key=>$value) {
				foreach($documentExtraKeys AS $extraItem) {
					$oDocumentController->insertDocumentExtraKey($value, $extraItem->idx, $extraItem->name, $extraItem->type, $extraItem->is_required , $extraItem->search , $extraItem->default , $extraItem->desc, $extraItem->eid) ;
				}
			}
		}
	}

	function triggerCopyModule(&$obj) {
		$oModuleModel = getModel('module');
		$documentConfig = $oModuleModel->getModulePartConfig('document', $obj->originModuleSrl);

		$oModuleController = getController('module');
		if(is_array($obj->moduleSrlList)) {
			foreach($obj->moduleSrlList AS $key=>$moduleSrl) {
				$oModuleController->insertModulePartConfig('document', $moduleSrl, $documentConfig);
			}
		}
	}
}
