<?php
class poll extends ModuleObject {
	function moduleInstall() {
		$oModuleController = getController('module');
		$oModuleController = getController('module');
		$config = new stdClass;
		$config->skin = 'default';
		$config->colorset = 'normal';
		$oModuleController->insertModuleConfig('poll', $config);
		$oModuleController->insertTrigger('document.insertDocument', 'poll', 'controller', 'triggerInsertDocumentPoll', 'after');
		$oModuleController->insertTrigger('comment.insertComment', 'poll', 'controller', 'triggerInsertCommentPoll', 'after');
		$oModuleController->insertTrigger('document.updateDocument', 'poll', 'controller', 'triggerUpdateDocumentPoll', 'after');
		$oModuleController->insertTrigger('comment.updateComment', 'poll', 'controller', 'triggerUpdateCommentPoll', 'after');
		$oModuleController->insertTrigger('document.deleteDocument', 'poll', 'controller', 'triggerDeleteDocumentPoll', 'after');
		$oModuleController->insertTrigger('comment.deleteComment', 'poll', 'controller', 'triggerDeleteCommentPoll', 'after');
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'poll', 'controller', 'triggerInsertDocumentPoll', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.insertComment', 'poll', 'controller', 'triggerInsertCommentPoll', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'poll', 'controller', 'triggerUpdateDocumentPoll', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.updateComment', 'poll', 'controller', 'triggerUpdateCommentPoll', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'poll', 'controller', 'triggerDeleteDocumentPoll', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.deleteComment', 'poll', 'controller', 'triggerDeleteCommentPoll', 'after')) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'poll', 'controller', 'triggerDeleteDocumentPoll', 'after'))
			$oModuleController->insertTrigger('document.deleteDocument', 'poll', 'controller', 'triggerDeleteDocumentPoll', 'after');
		if(!$oModuleModel->getTrigger('comment.deleteComment', 'poll', 'controller', 'triggerDeleteCommentPoll', 'after'))
			$oModuleController->insertTrigger('comment.deleteComment', 'poll', 'controller', 'triggerDeleteCommentPoll', 'after');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'poll', 'controller', 'triggerInsertDocumentPoll', 'after'))
			$oModuleController->insertTrigger('document.insertDocument', 'poll', 'controller', 'triggerInsertDocumentPoll', 'after');
		if(!$oModuleModel->getTrigger('comment.insertComment', 'poll', 'controller', 'triggerInsertCommentPoll', 'after'))
			$oModuleController->insertTrigger('comment.insertComment', 'poll', 'controller', 'triggerInsertCommentPoll', 'after');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'poll', 'controller', 'triggerUpdateDocumentPoll', 'after'))
			$oModuleController->insertTrigger('document.updateDocument', 'poll', 'controller', 'triggerUpdateDocumentPoll', 'after');
		if(!$oModuleModel->getTrigger('comment.updateComment', 'poll', 'controller', 'triggerUpdateCommentPoll', 'after'))
			$oModuleController->insertTrigger('comment.updateComment', 'poll', 'controller', 'triggerUpdateCommentPoll', 'after');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
