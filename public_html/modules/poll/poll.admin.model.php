<?php
class pollAdminModel extends poll
{
	function init() {
	}

	function getPollList($args) {
		$output = executeQueryArray('poll.getPollList', $args);
		if(!$output->toBool()) return $output;
		return $output;
	}

	function getPollListWithMember($args) {
		$output = executeQueryArray('poll.getPollListWithMember', $args);
		if(!$output->toBool()) return $output;
		return $output;
	}

	function getPollAdminTarget() {
		$poll_srl = Context::get('poll_srl');
		$upload_target_srl = Context::get('upload_target_srl');
		$oDocumentModel = getModel('document');
		$oCommentModel = getModel('comment');
		$oDocument = $oDocumentModel->getDocument($upload_target_srl);
		if(!$oDocument->isExists()) $oComment = $oCommentModel->getComment($upload_target_srl);
		if($oComment && $oComment->isExists()) {
			$this->add('document_srl', $oComment->get('document_srl'));
			$this->add('comment_srl', $oComment->get('comment_srl'));
		} elseif($oDocument->isExists()) {
			$this->add('document_srl', $oDocument->get('document_srl'));
		}
		else return new Object(-1, 'msg_not_founded');
	}
}
