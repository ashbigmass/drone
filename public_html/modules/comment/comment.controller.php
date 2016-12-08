<?php
class commentController extends comment
{
	function init() {
	}

	function procCommentVoteUp() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$comment_srl = Context::get('target_srl');
		if(!$comment_srl) return new Object(-1, 'msg_invalid_request');
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, FALSE, FALSE);
		$module_srl = $oComment->get('module_srl');
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		$comment_config = $oModuleModel->getModulePartConfig('comment', $module_srl);
		if($comment_config->use_vote_up == 'N') return new Object(-1, 'msg_invalid_request');
		$point = 1;
		$output = $this->updateVotedCount($comment_srl, $point);
		$this->add('voted_count', $output->get('voted_count'));
		return $output;
	}

	function procCommentVoteDown() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$comment_srl = Context::get('target_srl');
		if(!$comment_srl) return new Object(-1, 'msg_invalid_request');
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, FALSE, FALSE);
		$module_srl = $oComment->get('module_srl');
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		$comment_config = $oModuleModel->getModulePartConfig('comment', $module_srl);
		if($comment_config->use_vote_down == 'N') return new Object(-1, 'msg_invalid_request');
		$point = -1;
		$output = $this->updateVotedCount($comment_srl, $point);
		$this->add('blamed_count', $output->get('blamed_count'));
		return $output;
	}

	function procCommentDeclare() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$comment_srl = Context::get('target_srl');
		if(!$comment_srl) return new Object(-1, 'msg_invalid_request');
		return $this->declaredComment($comment_srl);
	}

	function triggerDeleteDocumentComments(&$obj) {
		$document_srl = $obj->document_srl;
		if(!$document_srl) return new Object();
		return $this->deleteComments($document_srl, $obj);
	}

	function triggerDeleteModuleComments(&$obj) {
		$module_srl = $obj->module_srl;
		if(!$module_srl) return new Object();
		$oCommentController = getAdminController('comment');
		return $oCommentController->deleteModuleComments($module_srl);
	}

	function addGrant($comment_srl) {
		$_SESSION['own_comment'][$comment_srl] = TRUE;
	}

	function isModuleUsingPublishValidation($module_srl = NULL) {
		if($module_srl == NULL) return FALSE;
		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		$module_part_config = $oModuleModel->getModulePartConfig('comment', $module_info->module_srl);
		$use_validation = FALSE;
		if(isset($module_part_config->use_comment_validation) && $module_part_config->use_comment_validation == "Y") $use_validation = TRUE;
		return $use_validation;
	}

	function insertComment($obj, $manual_inserted = FALSE) {
		if(!$manual_inserted && !checkCSRF()) return new Object(-1, 'msg_invalid_request');
		if(!is_object($obj)) $obj = new stdClass();
		$using_validation = $this->isModuleUsingPublishValidation($obj->module_srl);
		if(!$manual_inserted) {
			if(Context::get('is_logged')) {
				$logged_info = Context::get('logged_info');
				if($logged_info->is_admin == 'Y') $is_admin = TRUE;
				else $is_admin = FALSE;
			}
		} else {
			$is_admin = FALSE;
		}
		if(!$using_validation) {
			$obj->status = 1;
		} else {
			if($is_admin) $obj->status = 1;
			else $obj->status = 0;
		}
		$obj->__isupdate = FALSE;
		$output = ModuleHandler::triggerCall('comment.insertComment', 'before', $obj);
		if(!$output->toBool()) return $output;
		$document_srl = $obj->document_srl;
		if(!$document_srl) return new Object(-1, 'msg_invalid_document');
		$oDocumentModel = getModel('document');
		if($obj->password) $obj->password = getModel('member')->hashPassword($obj->password);
		if(!$manual_inserted) {
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if($document_srl != $oDocument->document_srl) return new Object(-1, 'msg_invalid_document');
			if($oDocument->isLocked()) return new Object(-1, 'msg_invalid_request');
			if($obj->homepage) {
				$obj->homepage = removeHackTag($obj->homepage);
				if(!preg_match('/^[a-z]+:\/\//i',$obj->homepage)) $obj->homepage = 'http://'.$obj->homepage;
			}
			if(Context::get('is_logged')) {
				$logged_info = Context::get('logged_info');
				$obj->member_srl = $logged_info->member_srl;
				$obj->user_id = htmlspecialchars_decode($logged_info->user_id);
				$obj->user_name = htmlspecialchars_decode($logged_info->user_name);
				$obj->nick_name = htmlspecialchars_decode($logged_info->nick_name);
				$obj->email_address = $logged_info->email_address;
				$obj->homepage = $logged_info->homepage;
			}
		}
		if(!$logged_info->member_srl && !$obj->nick_name) return new Object(-1, 'msg_invalid_request');
		if(!$obj->comment_srl) $obj->comment_srl = getNextSequence();
		elseif(!$is_admin && !$manual_inserted && !checkUserSequence($obj->comment_srl)) return new Object(-1, 'msg_not_permitted');
		$obj->list_order = getNextSequence() * -1;
		$obj->content = preg_replace('!<\!--(Before|After)(Document|Comment)\(([0-9]+),([0-9]+)\)-->!is', '', $obj->content);
		if(Mobile::isFromMobilePhone()) {
			if($obj->use_html != 'Y') $obj->content = htmlspecialchars($obj->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			$obj->content = nl2br($obj->content);
		}
		if(!$obj->regdate) $obj->regdate = date("YmdHis");
		if($logged_info->is_admin != 'Y') $obj->content = removeHackTag($obj->content);
		if(!$obj->notify_message) $obj->notify_message = 'N';
		if(!$obj->is_secret) $obj->is_secret = 'N';
		$oDB = DB::getInstance();
		$oDB->begin();
		$list_args = new stdClass();
		$list_args->comment_srl = $obj->comment_srl;
		$list_args->document_srl = $obj->document_srl;
		$list_args->module_srl = $obj->module_srl;
		$list_args->regdate = $obj->regdate;
		if(!$obj->parent_srl) {
			$list_args->head = $list_args->arrange = $obj->comment_srl;
			$list_args->depth = 0;
		} else {
			$parent_args = new stdClass();
			$parent_args->comment_srl = $obj->parent_srl;
			$parent_output = executeQuery('comment.getCommentListItem', $parent_args);
			if(!$parent_output->toBool() || !$parent_output->data) return;
			$parent = $parent_output->data;
			$list_args->head = $parent->head;
			$list_args->depth = $parent->depth + 1;
			if($list_args->depth < 2) {
				$list_args->arrange = $obj->comment_srl;
			} else {
				$p_args = new stdClass();
				$p_args->head = $parent->head;
				$p_args->arrange = $parent->arrange;
				$p_args->depth = $parent->depth;
				$output = executeQuery('comment.getCommentParentNextSibling', $p_args);
				if($output->data->arrange) {
					$list_args->arrange = $output->data->arrange;
					$output = executeQuery('comment.updateCommentListArrange', $list_args);
				} else {
					$list_args->arrange = $obj->comment_srl;
				}
			}
		}
		$output = executeQuery('comment.insertCommentList', $list_args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('comment.insertComment', $obj);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$oCommentModel = getModel('comment');
		$comment_count = $oCommentModel->getCommentCount($document_srl);
		$oDocumentController = getController('document');
		if(!$using_validation) {
			$output = $oDocumentController->updateCommentCount($document_srl, $comment_count, $obj->nick_name, TRUE);
		} else {
			if($is_admin) $output = $oDocumentController->updateCommentCount($document_srl, $comment_count, $obj->nick_name, TRUE);
		}
		if(!$manual_inserted) $this->addGrant($obj->comment_srl);
		if($output->toBool()) {
			$trigger_output = ModuleHandler::triggerCall('comment.insertComment', 'after', $obj);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
		}
		$oDB->commit();
		if(!$manual_inserted) {
			$oDocument->notify(Context::getLang('comment'), $obj->content);
			if($obj->parent_srl) {
				$oParent = $oCommentModel->getComment($obj->parent_srl);
				if($oParent->get('member_srl') != $oDocument->get('member_srl')) $oParent->notify(Context::getLang('comment'), $obj->content);
			}
		}
		$this->sendEmailToAdminAfterInsertComment($obj);
		$output->add('comment_srl', $obj->comment_srl);
		return $output;
	}

	function sendEmailToAdminAfterInsertComment($obj) {
		$using_validation = $this->isModuleUsingPublishValidation($obj->module_srl);
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);
		$oMemberModel = getModel("member");
		if(isset($obj->member_srl) && !is_null($obj->member_srl)) {
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($obj->member_srl);
		} else {
			$member_info = new stdClass();
			$member_info->is_admin = "N";
			$member_info->nick_name = $obj->nick_name;
			$member_info->user_name = $obj->user_name;
			$member_info->email_address = $obj->email_address;
		}
		$oCommentModel = getModel("comment");
		$nr_comments_not_approved = $oCommentModel->getCommentAllCount(NULL, FALSE);
		$oModuleModel = getModel("module");
		$module_info = $oModuleModel->getModuleInfoByDocumentSrl($obj->document_srl);
		if($module_info->admin_mail && $member_info->is_admin != 'Y') {
			$oMail = new Mail();
			$oMail->setSender($obj->email_address, $obj->email_address);
			$mail_title = "[XE - " . Context::get('mid') . "] A new comment was posted on document: \"" . $oDocument->getTitleText() . "\"";
			$oMail->setTitle($mail_title);
			$url_comment = getFullUrl('','document_srl',$obj->document_srl).'#comment_'.$obj->comment_srl;
			if($using_validation) {
				$url_approve = getFullUrl('', 'module', 'admin', 'act', 'procCommentAdminChangePublishedStatusChecked', 'cart[]', $obj->comment_srl, 'will_publish', '1', 'search_target', 'is_published', 'search_keyword', 'N');
				$url_trash = getFullUrl('', 'module', 'admin', 'act', 'procCommentAdminDeleteChecked', 'cart[]', $obj->comment_srl, 'search_target', 'is_trash', 'search_keyword', 'true');
				$mail_content = "
					A new comment on the document \"" . $oDocument->getTitleText() . "\" is waiting for your approval.
					<br />
					<br />
					Author: " . $member_info->nick_name . "
					<br />Author e-mail: " . $member_info->email_address . "
					<br />From : <a href=\"" . $url_comment . "\">" . $url_comment . "</a>
					<br />Comment:
					<br />\"" . $obj->content . "\"
					<br />Document:
					<br />\"" . $oDocument->getContentText(). "\"
					<br />
					<br />
					Approve it: <a href=\"" . $url_approve . "\">" . $url_approve . "</a>
					<br />Trash it: <a href=\"" . $url_trash . "\">" . $url_trash . "</a>
					<br />Currently " . $nr_comments_not_approved . " comments on \"" . Context::get('mid') . "\" module are waiting for approval. Please visit the moderation panel:
					<br /><a href=\"" . getFullUrl('', 'module', 'admin', 'act', 'dispCommentAdminList', 'search_target', 'module', 'search_keyword', $obj->module_srl) . "\">" . getFullUrl('', 'module', 'admin', 'act', 'dispCommentAdminList', 'search_target', 'module', 'search_keyword', $obj->module_srl) . "</a>
					";
				$oMail->setContent($mail_content);
			} else {
				$mail_content = "
					Author: " . $member_info->nick_name . "
					<br />Author e-mail: " . $member_info->email_address . "
					<br />From : <a href=\"" . $url_comment . "\">" . $url_comment . "</a>
					<br />Comment:
					<br />\"" . $obj->content . "\"
					<br />Document:
					<br />\"" . $oDocument->getContentText(). "\"
					";
				$oMail->setContent($mail_content);
				$document_author_email = $oDocument->variables['email_address'];
				$logged_info = Context::get('logged_info');
			}
			$admins_emails = $module_info->admin_mail;
			$target_mail = explode(',', $admins_emails);
			for($i = 0; $i < count($target_mail); $i++) {
				$email_address = trim($target_mail[$i]);
				if(!$email_address) continue;
				$oMail->setReceiptor($email_address, $email_address);
				$oMail->send();
			}
		}
		$comment_srl_list = array(0 => $obj->comment_srl);
		ModuleHandler::triggerCall("comment.sendEmailToAdminAfterInsertComment", "after", $comment_srl_list);
		return;
	}

	function updateComment($obj, $is_admin = FALSE, $manual_updated = FALSE) {
		if(!$manual_updated && !checkCSRF()) return new Object(-1, 'msg_invalid_request');
		if(!is_object($obj)) $obj = new stdClass();
		$obj->__isupdate = TRUE;
		$output = ModuleHandler::triggerCall('comment.updateComment', 'before', $obj);
		if(!$output->toBool()) return $output;
		$oCommentModel = getModel('comment');
		$source_obj = $oCommentModel->getComment($obj->comment_srl);
		if(!$source_obj->getMemberSrl()) {
			$obj->member_srl = $source_obj->get('member_srl');
			$obj->user_name = $source_obj->get('user_name');
			$obj->nick_name = $source_obj->get('nick_name');
			$obj->email_address = $source_obj->get('email_address');
			$obj->homepage = $source_obj->get('homepage');
		}
		if(!$is_admin && !$source_obj->isGranted()) return new Object(-1, 'msg_not_permitted');
		if($obj->password) $obj->password = getModel('member')->hashPassword($obj->password);
		if($obj->homepage)  {
			$obj->homepage = removeHackTag($obj->homepage);
			if(!preg_match('/^[a-z]+:\/\//i',$obj->homepage)) $obj->homepage = 'http://'.$obj->homepage;
		}
		if(Context::get('is_logged')) {
			$logged_info = Context::get('logged_info');
			if($source_obj->member_srl == $logged_info->member_srl) {
				$obj->member_srl = $logged_info->member_srl;
				$obj->user_name = $logged_info->user_name;
				$obj->nick_name = $logged_info->nick_name;
				$obj->email_address = $logged_info->email_address;
				$obj->homepage = $logged_info->homepage;
			}
		}
		if($source_obj->get('member_srl') && !$obj->nick_name) {
			$obj->member_srl = $source_obj->get('member_srl');
			$obj->user_name = $source_obj->get('user_name');
			$obj->nick_name = $source_obj->get('nick_name');
			$obj->email_address = $source_obj->get('email_address');
			$obj->homepage = $source_obj->get('homepage');
		}
		if(!$obj->content) $obj->content = $source_obj->get('content');
		$obj->content = preg_replace('!<\!--(Before|After)(Document|Comment)\(([0-9]+),([0-9]+)\)-->!is', '', $obj->content);
		if(Mobile::isFromMobilePhone()) {
			if($obj->use_html != 'Y') $obj->content = htmlspecialchars($obj->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			$obj->content = nl2br($obj->content);
		}
		if($logged_info->is_admin != 'Y') $obj->content = removeHackTag($obj->content);
		$oDB = DB::getInstance();
		$oDB->begin();
		$output = executeQuery('comment.updateComment', $obj);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		if($output->toBool()) {
			$trigger_output = ModuleHandler::triggerCall('comment.updateComment', 'after', $obj);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
		}
		$oDB->commit();
		$output->add('comment_srl', $obj->comment_srl);
		return $output;
	}

	function deleteComment($comment_srl, $is_admin = FALSE, $isMoveToTrash = FALSE) {
		$oCommentModel = getModel('comment');
		$comment = $oCommentModel->getComment($comment_srl);
		if($comment->comment_srl != $comment_srl) return new Object(-1, 'msg_invalid_request');
		$document_srl = $comment->document_srl;
		$output = ModuleHandler::triggerCall('comment.deleteComment', 'before', $comment);
		if(!$output->toBool()) return $output;
		if(!$is_admin && !$comment->isGranted()) return new Object(-1, 'msg_not_permitted');
		$childs = $oCommentModel->getChildComments($comment_srl);
		if(count($childs) > 0) {
			$deleteAllComment = TRUE;
			if(!$is_admin) {
				$logged_info = Context::get('logged_info');
				foreach($childs as $val) {
					if($val->member_srl != $logged_info->member_srl) {
						$deleteAllComment = FALSE;
						break;
					}
				}
			}
			if(!$deleteAllComment) {
				return new Object(-1, 'fail_to_delete_have_children');
			} else {
				foreach($childs as $val) {
					$output = $this->deleteComment($val->comment_srl, $is_admin, $isMoveToTrash);
					if(!$output->toBool()) return $output;
				}
			}
		}
		$oDB = DB::getInstance();
		$oDB->begin();
		$args = new stdClass();
		$args->comment_srl = $comment_srl;
		$output = executeQuery('comment.deleteComment', $args);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$output = executeQuery('comment.deleteCommentList', $args);
		$comment_count = $oCommentModel->getCommentCount($document_srl);
		if(isset($comment_count)) {
			$oDocumentController = getController('document');
			$output = $oDocumentController->updateCommentCount($document_srl, $comment_count, NULL, FALSE);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
		}
		if($output->toBool()) {
			$comment->isMoveToTrash = $isMoveToTrash;
			$trigger_output = ModuleHandler::triggerCall('comment.deleteComment', 'after', $comment);
			if(!$trigger_output->toBool()) {
				$oDB->rollback();
				return $trigger_output;
			}
			unset($comment->isMoveToTrash);
		}
		if(!$isMoveToTrash) {
			$this->_deleteDeclaredComments($args);
			$this->_deleteVotedComments($args);
		} else {
			$args = new stdClass();
			$args->upload_target_srl = $comment_srl;
			$args->isvalid = 'N';
			$output = executeQuery('file.updateFileValid', $args);
		}
		$oDB->commit();
		$output->add('document_srl', $document_srl);
		return $output;
	}

	function deleteCommentLog($args) {
		$this->_deleteDeclaredComments($args);
		$this->_deleteVotedComments($args);
		return new Object(0, 'success');
	}

	function deleteComments($document_srl, $obj = NULL) {
		$oDocumentModel = getModel('document');
		$oCommentModel = getModel('comment');
		if(is_object($obj)) {
			$oDocument = new documentItem();
			$oDocument->setAttribute($obj);
		} else {
			$oDocument = $oDocumentModel->getDocument($document_srl);
		}
		if(!$oDocument->isExists() || !$oDocument->isGranted()) return new Object(-1, 'msg_not_permitted');
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$comments = executeQueryArray('comment.getAllComments', $args);
		if($comments->data) {
			$commentSrlList = array();
			foreach($comments->data as $comment) {
				$commentSrlList[] = $comment->comment_srl;
				$output = ModuleHandler::triggerCall('comment.deleteComment', 'before', $comment);
				if(!$output->toBool()) continue;
				$output = ModuleHandler::triggerCall('comment.deleteComment', 'after', $comment);
				if(!$output->toBool()) continue;
			}
		}
		$args->document_srl = $document_srl;
		$output = executeQuery('comment.deleteComments', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('comment.deleteCommentsList', $args);
		if(is_array($commentSrlList) && count($commentSrlList) > 0) {
			$args = new stdClass();
			$args->comment_srl = join(',', $commentSrlList);
			$this->_deleteDeclaredComments($args);
			$this->_deleteVotedComments($args);
		}
		return $output;
	}

	function _deleteDeclaredComments($commentSrls) {
		executeQuery('comment.deleteDeclaredComments', $commentSrls);
		executeQuery('comment.deleteCommentDeclaredLog', $commentSrls);
	}

	function _deleteVotedComments($commentSrls) {
		executeQuery('comment.deleteCommentVotedLog', $commentSrls);
	}

	function updateVotedCount($comment_srl, $point = 1) {
		if($point > 0) {
			$failed_voted = 'failed_voted';
			$success_message = 'success_voted';
		} else {
			$failed_voted = 'failed_blamed';
			$success_message = 'success_blamed';
		}
		if($_SESSION['voted_comment'][$comment_srl]) return new Object(-1, $failed_voted);
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, FALSE, FALSE);
		if($oComment->get('ipaddress') == $_SERVER['REMOTE_ADDR']) {
			$_SESSION['voted_comment'][$comment_srl] = TRUE;
			return new Object(-1, $failed_voted);
		}
		if($oComment->get('member_srl')) {
			$oMemberModel = getModel('member');
			$member_srl = $oMemberModel->getLoggedMemberSrl();
			if($member_srl && $member_srl == abs($oComment->get('member_srl'))) {
				$_SESSION['voted_comment'][$comment_srl] = TRUE;
				return new Object(-1, $failed_voted);
			}
		}
		$args = new stdClass();
		if($member_srl) $args->member_srl = $member_srl;
		else $args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$args->comment_srl = $comment_srl;
		$output = executeQuery('comment.getCommentVotedLogInfo', $args);
		if($output->data->count) {
			$_SESSION['voted_comment'][$comment_srl] = TRUE;
			return new Object(-1, $failed_voted);
		}
		$oDB = DB::getInstance();
		$oDB->begin();
		if($point < 0) {
			$args->blamed_count = $oComment->get('blamed_count') + $point;
			$output = executeQuery('comment.updateBlamedCount', $args);
		} else {
			$args->voted_count = $oComment->get('voted_count') + $point;
			$output = executeQuery('comment.updateVotedCount', $args);
		}
		$args->point = $point;
		$output = executeQuery('comment.insertCommentVotedLog', $args);
		$obj = new stdClass();
		$obj->member_srl = $oComment->get('member_srl');
		$obj->module_srl = $oComment->get('module_srl');
		$obj->comment_srl = $oComment->get('comment_srl');
		$obj->update_target = ($point < 0) ? 'blamed_count' : 'voted_count';
		$obj->point = $point;
		$obj->before_point = ($point < 0) ? $oComment->get('blamed_count') : $oComment->get('voted_count');
		$obj->after_point = ($point < 0) ? $args->blamed_count : $args->voted_count;
		$trigger_output = ModuleHandler::triggerCall('comment.updateVotedCount', 'after', $obj);
		if(!$trigger_output->toBool()) {
			$oDB->rollback();
			return $trigger_output;
		}
		$oDB->commit();
		$_SESSION['voted_comment'][$comment_srl] = TRUE;
		$output = new Object(0, $success_message);
		if($point > 0) $output->add('voted_count', $obj->after_point);
		else $output->add('blamed_count', $obj->after_point);
		return $output;
	}

	function declaredComment($comment_srl) {
		if($_SESSION['declared_comment'][$comment_srl]) return new Object(-1, 'failed_declared');
		$args = new stdClass();
		$args->comment_srl = $comment_srl;
		$output = executeQuery('comment.getDeclaredComment', $args);
		if(!$output->toBool()) return $output;
		$declared_count = ($output->data->declared_count) ? $output->data->declared_count : 0;
		$trigger_obj = new stdClass();
		$trigger_obj->comment_srl = $comment_srl;
		$trigger_obj->declared_count = $declared_count;
		$trigger_output = ModuleHandler::triggerCall('comment.declaredComment', 'before', $trigger_obj);
		if(!$trigger_output->toBool()) return $trigger_output;
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, FALSE, FALSE);
		if($oComment->get('ipaddress') == $_SERVER['REMOTE_ADDR']) {
			$_SESSION['declared_comment'][$comment_srl] = TRUE;
			return new Object(-1, 'failed_declared');
		}
		if($oComment->get('member_srl')) {
			$oMemberModel = getModel('member');
			$member_srl = $oMemberModel->getLoggedMemberSrl();
			if($member_srl && $member_srl == abs($oComment->get('member_srl'))) {
				$_SESSION['declared_comment'][$comment_srl] = TRUE;
				return new Object(-1, 'failed_declared');
			}
		}
		if($member_srl) $args->member_srl = $member_srl;
		else $args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$args->comment_srl = $comment_srl;
		$log_output = executeQuery('comment.getCommentDeclaredLogInfo', $args);
		if($log_output->data->count) {
			$_SESSION['declared_comment'][$comment_srl] = TRUE;
			return new Object(-1, 'failed_declared');
		}
		$oDB = &DB::getInstance();
		$oDB->begin();
		if($output->data->declared_count > 0) $output = executeQuery('comment.updateDeclaredComment', $args);
		else $output = executeQuery('comment.insertDeclaredComment', $args);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$output = executeQuery('comment.insertCommentDeclaredLog', $args);
		$trigger_obj->declared_count = $declared_count + 1;
		$trigger_output = ModuleHandler::triggerCall('comment.declaredComment', 'after', $trigger_obj);
		if(!$trigger_output->toBool()) {
			$oDB->rollback();
			return $trigger_output;
		}
		$oDB->commit();
		$_SESSION['declared_comment'][$comment_srl] = TRUE;
		$this->setMessage('success_declared');
	}

	function addCommentPopupMenu($url, $str, $icon = '', $target = 'self') {
		$comment_popup_menu_list = Context::get('comment_popup_menu_list');
		if(!is_array($comment_popup_menu_list)) $comment_popup_menu_list = array();
		$obj = new stdClass();
		$obj->url = $url;
		$obj->str = $str;
		$obj->icon = $icon;
		$obj->target = $target;
		$comment_popup_menu_list[] = $obj;
		Context::set('comment_popup_menu_list', $comment_popup_menu_list);
	}

	function procCommentInsertModuleConfig() {
		$module_srl = Context::get('target_module_srl');
		if(preg_match('/^([0-9,]+)$/', $module_srl)) $module_srl = explode(',', $module_srl);
		else $module_srl = array($module_srl);
		$comment_config = new stdClass();
		$comment_config->comment_count = (int) Context::get('comment_count');
		if(!$comment_config->comment_count) $comment_config->comment_count = 50;
		$comment_config->use_vote_up = Context::get('use_vote_up');
		if(!$comment_config->use_vote_up) $comment_config->use_vote_up = 'Y';
		$comment_config->use_vote_down = Context::get('use_vote_down');
		if(!$comment_config->use_vote_down) $comment_config->use_vote_down = 'Y';
		$comment_config->use_comment_validation = Context::get('use_comment_validation');
		if(!$comment_config->use_comment_validation) $comment_config->use_comment_validation = 'N';
		for($i = 0; $i < count($module_srl); $i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			$output = $this->setCommentModuleConfig($srl, $comment_config);
		}
		$this->setError(-1);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispBoardAdminContent');
		$this->setRedirectUrl($returnUrl);
	}

	function setCommentModuleConfig($srl, $comment_config) {
		$oModuleController = getController('module');
		$oModuleController->insertModulePartConfig('comment', $srl, $comment_config);
		return new Object();
	}

	function procCommentGetList() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_permitted');
		$commentSrls = Context::get('comment_srls');
		if($commentSrls) $commentSrlList = explode(',', $commentSrls);
		if(count($commentSrlList) > 0) {
			$oCommentModel = getModel('comment');
			$commentList = $oCommentModel->getComments($commentSrlList);
			if(is_array($commentList)) {
				foreach($commentList as $value) $value->content = strip_tags($value->content);
			}
		} else {
			global $lang;
			$commentList = array();
			$this->setMessage($lang->no_documents);
		}
		$oSecurity = new Security($commentList);
		$oSecurity->encodeHTML('..variables.', '..');
		$this->add('comment_list', $commentList);
	}

	function triggerCopyModule(&$obj) {
		$oModuleModel = getModel('module');
		$commentConfig = $oModuleModel->getModulePartConfig('comment', $obj->originModuleSrl);
		$oModuleController = getController('module');
		if(is_array($obj->moduleSrlList)) {
			foreach($obj->moduleSrlList as $moduleSrl) $oModuleController->insertModulePartConfig('comment', $moduleSrl, $commentConfig);
		}
	}
}
