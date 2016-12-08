<?php
class commentAdminController extends comment {
	function init() {	}
	function procCommentAdminChangePublishedStatusChecked() {
		$basket = Context::get('basket');
		if(!is_array($basket)) $comment_srl_list = explode('|@|', $basket);
		else $comment_srl_list = $basket;
		$this->procCommentAdminChangeStatus();
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommentAdminList', 'search_keyword', '');
		$this->setRedirectUrl($returnUrl);
	}

	function procCommentAdminChangeStatus() {
		$will_publish = Context::get('will_publish');
		$basket = Context::get('basket');
		if(!$basket) return $this->stop('msg_basket_is_null');
		if(!is_array($basket)) $comment_srl_list = explode('|@|', $basket);
		else $comment_srl_list = $basket;
		$args = new stdClass();
		$args->status = $will_publish;
		$args->comment_srls_list = $comment_srl_list;
		$output = executeQuery('comment.updatePublishedStatus', $args);
		if(!$output->toBool()) {
			return $output;
		} else {
			$updated_documents_arr = array();
			$oDocumentController = getController('document');
			$oDocumentModel = getModel('document');
			$oCommentModel = getModel('comment');
			$logged_info = Context::get('logged_info');
			$new_status = ($will_publish) ? "published" : "unpublished";
			foreach($comment_srl_list as $comment_srl) {
				$comment = $oCommentModel->getComment($comment_srl);
				if($comment->comment_srl != $comment_srl) return new Object(-1, 'msg_invalid_request');
				$document_srl = $comment->document_srl;
				if(!in_array($document_srl, $updated_documents_arr)) {
					$updated_documents_arr[] = $document_srl;
					$comment_count = $oCommentModel->getCommentCount($document_srl);
					$output = $oDocumentController->updateCommentCount($document_srl, $comment_count, NULL, FALSE);
					$oDocument = $oDocumentModel->getDocument($document_srl);
					$author_email = $oDocument->variables['email_address'];
					$oModuleModel = getModel("module");
					$module_info = $oModuleModel->getModuleInfoByModuleSrl($comment->module_srl);
					$already_sent = array();
					$oMail = new Mail();
					$mail_title = "[XE - " . $module_info->mid . "] comment(s) status changed to " . $new_status . " on document: \"" . $oDocument->getTitleText() . "\"";
					$oMail->setTitle($mail_title);
					$mail_content = "
						The comment #" . $comment_srl . " on document \"" . $oDocument->getTitleText() . "\" has been " . $new_status . " by admin of <strong><i>" . strtoupper($module_info->mid) . "</i></strong> module.
						<br />
						<br />Comment content:
						" . $comment->content . "
						<br />
						";
					$oMail->setContent($mail_content);
					$oMail->setSender($logged_info->user_name, $logged_info->email_address);
					$document_author_email = $oDocument->variables['email_address'];
					if($document_author_email != $comment->email_address && $logged_info->email_address != $document_author_email) {
						$oMail->setReceiptor($document_author_email, $document_author_email);
						$oMail->send();
						$already_sent[] = $document_author_email;
					}
					if($module_info->admin_mail) {
						$target_mail = explode(',', $module_info->admin_mail);
						for($i = 0; $i < count($target_mail); $i++) {
							$email_address = trim($target_mail[$i]);
							if(!$email_address) continue;
							if($author_email != $email_address) {
								$oMail->setReceiptor($email_address, $email_address);
								$oMail->send();
							}
						}
					}
				}
			}
			ModuleHandler::triggerCall("comment.procCommentAdminChangeStatus", "after", $comment_srl_list);
		}

		$message_content = Context::get('message_content');
		if($message_content) $message_content = nl2br($message_content);
		if($message_content) $this->_sendMessageForComment($message_content, $comment_srl_list);
	}

	function procCommentAdminDeleteChecked() {
		$isTrash = Context::get('is_trash');
		$basket = Context::get('basket');
		if(!$basket) return $this->stop('msg_basket_is_null');
		if(!is_array($basket)) $comment_srl_list = explode('|@|', $basket);
		else $comment_srl_list = $basket;
		$comment_count = count($comment_srl_list);
		if(!$comment_count) return $this->stop('msg_basket_is_null');
		$oCommentController = getController('comment');
		$oDB = DB::getInstance();
		$oDB->begin();
		$message_content = Context::get('message_content');
		if($message_content) $message_content = nl2br($message_content);
		if($message_content) $this->_sendMessageForComment($message_content, $comment_srl_list);
		if($isTrash == 'true') $this->_moveCommentToTrash($comment_srl_list, $oCommentController, $oDB, $message_content);
		$deleted_count = 0;
		for($i = 0; $i < $comment_count; $i++) {
			$comment_srl = trim($comment_srl_list[$i]);
			if(!$comment_srl) continue;
			$output = $oCommentController->deleteComment($comment_srl, TRUE, $isTrash);
			if(!$output->toBool()) {
				$oDB->rollback();
				return $output;
			}
			$deleted_count++;
		}
		$oDB->commit();
		$msgCode = '';
		if($isTrash == 'true') $msgCode = 'success_trashed';
		else $msgCode = 'success_deleted';
		$this->setMessage($msgCode, 'info');
		$search_keyword = Context::get('search_keyword');
		$search_target = Context::get('search_target');
		$page = Context::get('page');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommentAdminList', 'search_keyword', $search_keyword, 'search_target', $search_target, 'page', $page);
		$this->setRedirectUrl($returnUrl);
	}

	private function _sendMessageForComment($message_content, $comment_srl_list) {
		$oCommunicationController = getController('communication');
		$oCommentModel = getModel('comment');
		$logged_info = Context::get('logged_info');
		$title = cut_str($message_content, 10, '...');
		$sender_member_srl = $logged_info->member_srl;
		$comment_count = count($comment_srl_list);
		for($i = 0; $i < $comment_count; $i++) {
			$comment_srl = $comment_srl_list[$i];
			$oComment = $oCommentModel->getComment($comment_srl, TRUE);
			if(!$oComment->get('member_srl') || $oComment->get('member_srl') == $sender_member_srl) continue;
			$content = sprintf("<div>%s</div><hr /><div style=\"font-weight:bold\">%s</div>", $message_content, $oComment->getContentText(20));
			$oCommunicationController->sendMessage($sender_member_srl, $oComment->get('member_srl'), $title, $content, FALSE);
		}
	}

	function _moveCommentToTrash($commentSrlList, &$oCommentController, &$oDB, $message_content = NULL) {
		require_once(_XE_PATH_ . 'modules/trash/model/TrashVO.php');
		if(is_array($commentSrlList)) {
			$logged_info = Context::get('logged_info');
			$oCommentModel = getModel('comment');
			$commentItemList = $oCommentModel->getComments($commentSrlList);
			$oTrashAdminController = getAdminController('trash');
			foreach($commentItemList AS $key => $oComment) {
				$oTrashVO = new TrashVO();
				$oTrashVO->setTrashSrl(getNextSequence());
				$oTrashVO->setTitle(trim(strip_tags($oComment->variables['content'])));
				$oTrashVO->setOriginModule('comment');
				$oTrashVO->setSerializedObject(serialize($oComment->variables));
				$oTrashVO->setDescription($message_content);
				$output = $oTrashAdminController->insertTrash($oTrashVO);
				if(!$output->toBool()) {
					$oDB->rollback();
					return $output;
				}
			}
		}
	}

	function procCommentAdminMoveToTrash() {
		$oDB = DB::getInstance();
		$oDB->begin();
		$comment_srl = Context::get('comment_srl');
		$oCommentModel = getModel('comment');
		$oCommentController = getController('comment');
		$oComment = $oCommentModel->getComment($comment_srl, false);
		if(!$oComment->isGranted()) return $this->stop('msg_not_permitted');
		$message_content = "";
		$this->_moveCommentToTrash(array($comment_srl), $oCommentController, $oDB, $message_content);
		$isTrash = true;
		$output = $oCommentController->deleteComment($comment_srl, TRUE, $isTrash);
		$oDB->commit();
		$returnUrl = Context::get('cur_url');
		$this->add('redirect_url', $returnUrl);
	}

	function procCommentAdminCancelDeclare() {
		$comment_srl = trim(Context::get('comment_srl'));
		if($comment_srl) {
			$args = new stdClass();
			$args->comment_srl = $comment_srl;
			$output = executeQuery('comment.deleteDeclaredComments', $args);
			if(!$output->toBool()) return $output;
		}
	}

	function procCommentAdminAddbasket() {
		$comment_srl = (int) Context::get('comment_srl');
		$oCommentModel = getModel('comment');
		$columnList = array('comment_srl');
		$commentSrlList = array($comment_srl);
		$output = $oCommentModel->getComments($commentSrlList);
		if(is_array($output)) {
			foreach($output AS $key => $value) {
				if($_SESSION['comment_management'][$key]) unset($_SESSION['comment_management'][$key]);
				else $_SESSION['comment_management'][$key] = TRUE;
			}
		}
	}

	function deleteModuleComments($module_srl) {
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('comment.deleteModuleComments', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('comment.deleteModuleCommentsList', $args);
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) $oCacheHandler->invalidateGroupKey('newestCommentsList');
		return $output;
	}

	function restoreTrash($originObject) {
		if(is_array($originObject)) $originObject = (object) $originObject;
		$obj = new stdClass();
		$obj->document_srl = $originObject->document_srl;
		$obj->comment_srl = $originObject->comment_srl;
		$obj->parent_srl = $originObject->parent_srl;
		$obj->content = $originObject->content;
		$obj->password = $originObject->password;
		$obj->nick_name = $originObject->nick_name;
		$obj->member_srl = $originObject->member_srl;
		$obj->email_address = $originObject->email_address;
		$obj->homepage = $originObject->homepage;
		$obj->is_secret = $originObject->is_secret;
		$obj->notify_message = $originObject->notify_message;
		$obj->module_srl = $originObject->module_srl;
		$oCommentController = getController('comment');
		$output = $oCommentController->insertComment($obj, true);
		return $output;
	}

	function emptyTrash($originObject) {
		$originObject = unserialize($originObject);
		if(is_array($originObject)) $originObject = (object) $originObject;
		$oComment = new commentItem();
		$oComment->setAttribute($originObject);
		$oCommentController = getController('comment');
		$args = new stdClass();
		$args->comment_srl = $oComment->get('comment_srl');
		$output = $oCommentController->deleteCommentLog($args);
		return $output;
	}
}
