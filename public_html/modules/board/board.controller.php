<?php
class boardController extends board
	function init() {
	}

	function procBoardInsertDocument() {
		if($this->module_info->module != "board") return new Object(-1, "msg_invalid_request");
		if(!$this->grant->write_document) return new Object(-1, 'msg_not_permitted');
		$logged_info = Context::get('logged_info');
		$obj = Context::getRequestVars();
		$obj->module_srl = $this->module_srl;
		if($obj->is_notice!='Y'||!$this->grant->manager) $obj->is_notice = 'N';
		$obj->commentStatus = $obj->comment_status;
		settype($obj->title, "string");
		if($obj->title == '') $obj->title = cut_str(trim(strip_tags(nl2br($obj->content))),20,'...');
		if($obj->title == '') $obj->title = 'Untitled';
		if(!$this->grant->manager) {
			unset($obj->title_color);
			unset($obj->title_bold);
		}
		$oDocumentModel = getModel('document');
		$oDocumentController = getController('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);
		$is_update = false;
		if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl) $is_update = true;
		if($this->module_info->use_anonymous == 'Y') {
			$this->module_info->admin_mail = '';
			$obj->notify_message = 'N';
			if($is_update===false) $obj->member_srl = -1*$logged_info->member_srl;
			$obj->email_address = $obj->homepage = $obj->user_id = '';
			$obj->user_name = $obj->nick_name = 'anonymous';
			$bAnonymous = true;
			if($is_update===false) $oDocument->add('member_srl', $obj->member_srl);
		} else {
			$bAnonymous = false;
		}
		if($obj->is_secret == 'Y' || strtoupper($obj->status == 'SECRET')) {
			$use_status = explode('|@|', $this->module_info->use_status);
			if(!is_array($use_status) || !in_array('SECRET', $use_status)) {
				unset($obj->is_secret);
				$obj->status = 'PUBLIC';
			}
		}
		if($is_update) {
			if(!$oDocument->isGranted()) return new Object(-1,'msg_not_permitted');
			if($this->module_info->use_anonymous == 'Y') {
				$obj->member_srl = abs($oDocument->get('member_srl')) * -1;
				$oDocument->add('member_srl', $obj->member_srl);
			}
			if($this->module_info->protect_content=="Y" && $oDocument->get('comment_count')>0 && $this->grant->manager==false) {
				return new Object(-1,'msg_protect_content');
			}
			if(!$this->grant->manager) {
				$obj->is_notice = $oDocument->get('is_notice');
				$obj->title_color = $oDocument->get('title_color');
				$obj->title_bold = $oDocument->get('title_bold');
			}
			if($oDocument->get('status') == 'TEMP') {
				$obj->last_update = $obj->regdate = date('YmdHis');
				$obj->update_order = $obj->list_order = (getNextSequence() * -1);
			}
			$output = $oDocumentController->updateDocument($oDocument, $obj, true);
			$msg_code = 'success_updated';
		} else {
			$output = $oDocumentController->insertDocument($obj, $bAnonymous);
			$msg_code = 'success_registed';
			$obj->document_srl = $output->get('document_srl');
			if($output->toBool() && $this->module_info->admin_mail) {
				$oModuleModel = getModel('module');
				$member_config = $oModuleModel->getModuleConfig('member');
				$oMail = new Mail();
				$oMail->setTitle($obj->title);
				$oMail->setContent( sprintf("From : <a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $obj->content));
				$oMail->setSender($obj->user_name ? $obj->user_name : 'anonymous', $obj->email_address ? $obj->email_address : $member_config->webmaster_email);
				$target_mail = explode(',',$this->module_info->admin_mail);
				for($i=0;$i<count($target_mail);$i++) {
					$email_address = trim($target_mail[$i]);
					if(!$email_address) continue;
					$oMail->setReceiptor($email_address, $email_address);
					$oMail->send();
				}
			}
		}
		if(!$output->toBool()) return $output;
		$this->add('mid', Context::get('mid'));
		$this->add('document_srl', $output->get('document_srl'));
		if(Context::get('xeVirtualRequestMethod') !== 'xml') $this->setMessage($msg_code);
	}

	function procBoardDeleteDocument() {
		$document_srl = Context::get('document_srl');
		if(!$document_srl) return $this->doError('msg_invalid_document');
		$oDocumentModel = &getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if($this->module_info->protect_content=="Y" && $oDocument->get('comment_count')>0 && $this->grant->manager==false) return new Object(-1, 'msg_protect_content');
		$oDocumentController = getController('document');
		$output = $oDocumentController->deleteDocument($document_srl, $this->grant->manager);
		if(!$output->toBool()) return $output;
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', '', 'page', Context::get('page'), 'document_srl', ''));
		$this->add('mid', Context::get('mid'));
		$this->add('page', Context::get('page'));
		if(Context::get('xeVirtualRequestMethod') !== 'xml') $this->setMessage('success_deleted');
	}

	function procBoardVoteDocument() {
		$oDocumentController = getController('document');
		$document_srl = Context::get('document_srl');
		return $oDocumentController->updateVotedCount($document_srl);
	}

	function procBoardInsertComment() {
		if(!$this->grant->write_comment) return new Object(-1, 'msg_not_permitted');
		$logged_info = Context::get('logged_info');
		$obj = Context::getRequestVars();
		$obj->module_srl = $this->module_srl;
		if(!$this->module_info->use_status) $this->module_info->use_status = 'PUBLIC';
		if(!is_array($this->module_info->use_status)) $this->module_info->use_status = explode('|@|', $this->module_info->use_status);
		if(in_array('SECRET', $this->module_info->use_status)) {
			$this->module_info->secret = 'Y';
		} else {
			unset($obj->is_secret);
			$this->module_info->secret = 'N';
		}
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);
		if(!$oDocument->isExists()) return new Object(-1,'msg_not_founded');
		if($this->module_info->use_anonymous == 'Y') {
			$this->module_info->admin_mail = '';
			$obj->notify_message = 'N';
			$obj->member_srl = -1*$logged_info->member_srl;
			$obj->email_address = $obj->homepage = $obj->user_id = '';
			$obj->user_name = $obj->nick_name = 'anonymous';
			$bAnonymous = true;
		} else {
			$bAnonymous = false;
		}
		$oCommentModel = getModel('comment');
		$oCommentController = getController('comment');
		if(!$obj->comment_srl) $obj->comment_srl = getNextSequence();
		else $comment = $oCommentModel->getComment($obj->comment_srl, $this->grant->manager);
		if($comment->comment_srl != $obj->comment_srl) {
			if($obj->parent_srl) {
				$parent_comment = $oCommentModel->getComment($obj->parent_srl);
				if(!$parent_comment->comment_srl) return new Object(-1, 'msg_invalid_request');
				$output = $oCommentController->insertComment($obj, $bAnonymous);
			} else {
				$output = $oCommentController->insertComment($obj, $bAnonymous);
			}
		} else {
			if(!$comment->isGranted()) return new Object(-1,'msg_not_permitted');
			$obj->parent_srl = $comment->parent_srl;
			$output = $oCommentController->updateComment($obj, $this->grant->manager);
			$comment_srl = $obj->comment_srl;
		}
		if(!$output->toBool()) return $output;
		if(Context::get('xeVirtualRequestMethod') !== 'xml') $this->setMessage('success_registed');
		$this->add('mid', Context::get('mid'));
		$this->add('document_srl', $obj->document_srl);
		$this->add('comment_srl', $obj->comment_srl);
	}

	function procBoardDeleteComment() {
		$comment_srl = Context::get('comment_srl');
		if(!$comment_srl) return $this->doError('msg_invalid_request');
		$oCommentController = getController('comment');
		$output = $oCommentController->deleteComment($comment_srl, $this->grant->manager);
		if(!$output->toBool()) return $output;
		$this->add('mid', Context::get('mid'));
		$this->add('page', Context::get('page'));
		$this->add('document_srl', $output->get('document_srl'));
		if(Context::get('xeVirtualRequestMethod') !== 'xml') $this->setMessage('success_deleted');
	}

	function procBoardDeleteTrackback() {
		$trackback_srl = Context::get('trackback_srl');
		$oTrackbackController = getController('trackback');
		if(!$oTrackbackController) return;
		$output = $oTrackbackController->deleteTrackback($trackback_srl, $this->grant->manager);
		if(!$output->toBool()) return $output;
		$this->add('mid', Context::get('mid'));
		$this->add('page', Context::get('page'));
		$this->add('document_srl', $output->get('document_srl'));
		if(Context::get('xeVirtualRequestMethod') !== 'xml') $this->setMessage('success_deleted');
	}

	function procBoardVerificationPassword() {
		$password = Context::get('password');
		$document_srl = Context::get('document_srl');
		$comment_srl = Context::get('comment_srl');
		$oMemberModel = getModel('member');
		if($comment_srl) {
			$oCommentModel = getModel('comment');
			$oComment = $oCommentModel->getComment($comment_srl);
			if(!$oComment->isExists()) return new Object(-1, 'msg_invalid_request');
			if(!$oMemberModel->isValidPassword($oComment->get('password'),$password)) return new Object(-1, 'msg_invalid_password');
			$oComment->setGrant();
		} else {
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if(!$oDocument->isExists()) return new Object(-1, 'msg_invalid_request');
			if(!$oMemberModel->isValidPassword($oDocument->get('password'),$password)) return new Object(-1, 'msg_invalid_password');
			$oDocument->setGrant();
		}
	}

	function triggerMemberMenu(&$obj) {
		$member_srl = Context::get('target_srl');
		$mid = Context::get('cur_mid');
		if(!$member_srl || !$mid) return new Object();
		$logged_info = Context::get('logged_info');
		$oModuleModel = getModel('module');
		$columnList = array('module');
		$cur_module_info = $oModuleModel->getModuleInfoByMid($mid, 0, $columnList);
		if($cur_module_info->module != 'board') return new Object();
		if($member_srl == $logged_info->member_srl) {
			$member_info = $logged_info;
		} else {
			$oMemberModel = getModel('member');
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		}
		if(!$member_info->user_id) return new Object();
		$url = getUrl('','mid',$mid,'search_target','nick_name','search_keyword',$member_info->nick_name);
		$oMemberController = getController('member');
		$oMemberController->addMemberPopupMenu($url, 'cmd_view_own_document', '');
		return new Object();
	}
}
