<?php
class point extends ModuleObject
{
	function moduleInstall() {
		$oModuleController = getController('module');
		FileHandler::makeDir('./files/member_extra_info/point');
		$oModuleController = getController('module');
		$config = new stdClass;
		$config->able_module = 'N';
		$config->max_level = 30;
		for($i=1;$i<=30;$i++) $config->level_step[$i] = pow($i,2)*90;
		$config->signup_point = 10;
		$config->login_point = 5;
		$config->point_name = 'point';
		$config->level_icon = "default";
		$config->disable_download = false;
		$config->insert_document = 10;
		$config->insert_document_act = 'procBoardInsertDocument';
		$config->delete_document_act = 'procBoardDeleteDocument';
		$config->insert_comment = 5;
		$config->insert_comment_act = 'procBoardInsertComment,procBlogInsertComment';
		$config->delete_comment_act = 'procBoardDeleteComment,procBlogDeleteComment';
		$config->upload_file = 5;
		$config->upload_file_act = 'procFileUpload';
		$config->delete_file_act = 'procFileDelete';
		$config->download_file = -5;
		$config->download_file_act = 'procFileDownload';
		$config->read_document = 0;
		$config->voted = 0;
		$config->blamed = 0;
		$oModuleController->insertModuleConfig('point', $config);
		$oPointController = getAdminController('point');
		$oPointController->cacheActList();
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		if($config->able_module != 'N') {
			if(!$oModuleModel->getTrigger('member.insertMember', 'point', 'controller', 'triggerInsertMember', 'after')) return true;
			if(!$oModuleModel->getTrigger('document.insertDocument', 'point', 'controller', 'triggerInsertDocument', 'after')) return true;
			if(!$oModuleModel->getTrigger('document.deleteDocument', 'point', 'controller', 'triggerBeforeDeleteDocument', 'before')) return true;
			if(!$oModuleModel->getTrigger('document.deleteDocument', 'point', 'controller', 'triggerDeleteDocument', 'after')) return true;
			if(!$oModuleModel->getTrigger('comment.insertComment', 'point', 'controller', 'triggerInsertComment', 'after')) return true;
			if(!$oModuleModel->getTrigger('comment.deleteComment', 'point', 'controller', 'triggerDeleteComment', 'after')) return true;
			if(!$oModuleModel->getTrigger('file.insertFile', 'point', 'controller', 'triggerInsertFile', 'after')) return true;
			if(!$oModuleModel->getTrigger('file.deleteFile', 'point', 'controller', 'triggerDeleteFile', 'after')) return true;
			if(!$oModuleModel->getTrigger('file.downloadFile', 'point', 'controller', 'triggerBeforeDownloadFile', 'before')) return true;
			if(!$oModuleModel->getTrigger('file.downloadFile', 'point', 'controller', 'triggerDownloadFile', 'after')) return true;
			if(!$oModuleModel->getTrigger('member.doLogin', 'point', 'controller', 'triggerAfterLogin', 'after')) return true;
			if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'point', 'view', 'triggerDispPointAdditionSetup', 'after')) return true;
			if(!$oModuleModel->getTrigger('document.updateReadedCount', 'point', 'controller', 'triggerUpdateReadedCount', 'after')) return true;
			if(!$oModuleModel->getTrigger('document.updateVotedCount', 'point', 'controller', 'triggerUpdateVotedCount', 'after')) return true;
			if(!$oModuleModel->getTrigger('document.updateDocument', 'point', 'controller', 'triggerUpdateDocument', 'before')) return true;
			if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'point', 'controller', 'triggerCopyModule', 'after')) return true;
		}
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('member.insertMember', 'point', 'controller', 'triggerInsertMember', 'after'))
			$oModuleController->insertTrigger('member.insertMember', 'point', 'controller', 'triggerInsertMember', 'after');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'point', 'controller', 'triggerInsertDocument', 'after'))
			$oModuleController->insertTrigger('document.insertDocument', 'point', 'controller', 'triggerInsertDocument', 'after');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'point', 'controller', 'triggerBeforeDeleteDocument', 'before'))
			$oModuleController->insertTrigger('document.deleteDocument', 'point', 'controller', 'triggerBeforeDeleteDocument', 'before');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'point', 'controller', 'triggerDeleteDocument', 'after'))
			$oModuleController->insertTrigger('document.deleteDocument', 'point', 'controller', 'triggerDeleteDocument', 'after');
		if(!$oModuleModel->getTrigger('comment.insertComment', 'point', 'controller', 'triggerInsertComment', 'after'))
			$oModuleController->insertTrigger('comment.insertComment', 'point', 'controller', 'triggerInsertComment', 'after');
		if(!$oModuleModel->getTrigger('comment.deleteComment', 'point', 'controller', 'triggerDeleteComment', 'after'))
			$oModuleController->insertTrigger('comment.deleteComment', 'point', 'controller', 'triggerDeleteComment', 'after');
		if(!$oModuleModel->getTrigger('file.insertFile', 'point', 'controller', 'triggerInsertFile', 'after'))
			$oModuleController->insertTrigger('file.insertFile', 'point', 'controller', 'triggerInsertFile', 'after');
		if(!$oModuleModel->getTrigger('file.deleteFile', 'point', 'controller', 'triggerDeleteFile', 'after'))
			$oModuleController->insertTrigger('file.deleteFile', 'point', 'controller', 'triggerDeleteFile', 'after');
		if(!$oModuleModel->getTrigger('file.downloadFile', 'point', 'controller', 'triggerBeforeDownloadFile', 'before'))
			$oModuleController->insertTrigger('file.downloadFile', 'point', 'controller', 'triggerBeforeDownloadFile', 'before');
		if(!$oModuleModel->getTrigger('file.downloadFile', 'point', 'controller', 'triggerDownloadFile', 'after'))
			$oModuleController->insertTrigger('file.downloadFile', 'point', 'controller', 'triggerDownloadFile', 'after');
		if(!$oModuleModel->getTrigger('member.doLogin', 'point', 'controller', 'triggerAfterLogin', 'after'))
			$oModuleController->insertTrigger('member.doLogin', 'point', 'controller', 'triggerAfterLogin', 'after');
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'point', 'view', 'triggerDispPointAdditionSetup', 'after'))
			$oModuleController->insertTrigger('module.dispAdditionSetup', 'point', 'view', 'triggerDispPointAdditionSetup', 'after');
		if(!$oModuleModel->getTrigger('document.updateReadedCount', 'point', 'controller', 'triggerUpdateReadedCount', 'after'))
			$oModuleController->insertTrigger('document.updateReadedCount', 'point', 'controller', 'triggerUpdateReadedCount', 'after');
		if(!$oModuleModel->getTrigger('document.updateVotedCount', 'point', 'controller', 'triggerUpdateVotedCount', 'after'))
			$oModuleController->insertTrigger('document.updateVotedCount', 'point', 'controller', 'triggerUpdateVotedCount', 'after');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'point', 'controller', 'triggerUpdateDocument', 'before'))
			$oModuleController->insertTrigger('document.updateDocument', 'point', 'controller', 'triggerUpdateDocument', 'before');
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'point', 'controller', 'triggerCopyModule', 'after'))
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'point', 'controller', 'triggerCopyModule', 'after');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
		$oPointAdminController = getAdminController('point');
		$oPointAdminController->cacheActList();
	}
}
