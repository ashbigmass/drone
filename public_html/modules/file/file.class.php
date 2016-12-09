<?php
class file extends ModuleObject
{
	function moduleInstall() {
		$oModuleController = getController('module');
		$config = new stdClass;
		$config->allowed_filesize = '2';
		$config->allowed_attach_size = '2';
		$config->allowed_filetypes = '*.*';
		$oModuleController->insertModuleConfig('file', $config);
		FileHandler::makeDir('./files/attach/images');
		FileHandler::makeDir('./files/attach/binaries');
		$oModuleController->insertTrigger('document.insertDocument', 'file', 'controller', 'triggerCheckAttached', 'before');
		$oModuleController->insertTrigger('document.insertDocument', 'file', 'controller', 'triggerAttachFiles', 'after');
		$oModuleController->insertTrigger('document.updateDocument', 'file', 'controller', 'triggerCheckAttached', 'before');
		$oModuleController->insertTrigger('document.updateDocument', 'file', 'controller', 'triggerAttachFiles', 'after');
		$oModuleController->insertTrigger('document.deleteDocument', 'file', 'controller', 'triggerDeleteAttached', 'after');
		$oModuleController->insertTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before');
		$oModuleController->insertTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after');
		$oModuleController->insertTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before');
		$oModuleController->insertTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after');
		$oModuleController->insertTrigger('comment.deleteComment', 'file', 'controller', 'triggerCommentDeleteAttached', 'after');
		$oModuleController->insertTrigger('editor.deleteSavedDoc', 'file', 'controller', 'triggerDeleteAttached', 'after');
		$oModuleController->insertTrigger('module.deleteModule', 'file', 'controller', 'triggerDeleteModuleFiles', 'after');
		$oModuleController->insertTrigger('module.dispAdditionSetup', 'file', 'view', 'triggerDispFileAdditionSetup', 'before');
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'file', 'controller', 'triggerCheckAttached', 'before')) return true;
		if(!$oModuleModel->getTrigger('document.insertDocument', 'file', 'controller', 'triggerAttachFiles', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'file', 'controller', 'triggerCheckAttached', 'before')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'file', 'controller', 'triggerAttachFiles', 'after')) return true;
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'file', 'controller', 'triggerDeleteAttached', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before')) return true;
		if(!$oModuleModel->getTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before')) return true;
		if(!$oModuleModel->getTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after')) return true;
		if(!$oModuleModel->getTrigger('comment.deleteComment', 'file', 'controller', 'triggerCommentDeleteAttached', 'after')) return true;
		if(!$oModuleModel->getTrigger('editor.deleteSavedDoc', 'file', 'controller', 'triggerDeleteAttached', 'after')) return true;
		if(!$oModuleModel->getTrigger('module.deleteModule', 'file', 'controller', 'triggerDeleteModuleFiles', 'after')) return true;
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'file', 'view', 'triggerDispFileAdditionSetup', 'before')) return true;
		if(!$oDB->isColumnExists('files', 'upload_target_type')) return true;
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'file', 'controller', 'triggerCopyModule', 'after')) return true;
		if(!$oDB->isColumnExists('files', 'cover_image')) return true;
		return false;
	}

	function moduleUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'file', 'controller', 'triggerCheckAttached', 'before'))
			$oModuleController->insertTrigger('document.insertDocument', 'file', 'controller', 'triggerCheckAttached', 'before');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'file', 'controller', 'triggerAttachFiles', 'after'))
			$oModuleController->insertTrigger('document.insertDocument', 'file', 'controller', 'triggerAttachFiles', 'after');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'file', 'controller', 'triggerCheckAttached', 'before'))
			$oModuleController->insertTrigger('document.updateDocument', 'file', 'controller', 'triggerCheckAttached', 'before');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'file', 'controller', 'triggerAttachFiles', 'after'))
			$oModuleController->insertTrigger('document.updateDocument', 'file', 'controller', 'triggerAttachFiles', 'after');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'file', 'controller', 'triggerDeleteAttached', 'after'))
			$oModuleController->insertTrigger('document.deleteDocument', 'file', 'controller', 'triggerDeleteAttached', 'after');
		if(!$oModuleModel->getTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before'))
			$oModuleController->insertTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before');
		if(!$oModuleModel->getTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after'))
			$oModuleController->insertTrigger('comment.insertComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after');
		if(!$oModuleModel->getTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before'))
			$oModuleController->insertTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentCheckAttached', 'before');
		if(!$oModuleModel->getTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after'))
			$oModuleController->insertTrigger('comment.updateComment', 'file', 'controller', 'triggerCommentAttachFiles', 'after');
		if(!$oModuleModel->getTrigger('comment.deleteComment', 'file', 'controller', 'triggerCommentDeleteAttached', 'after'))
			$oModuleController->insertTrigger('comment.deleteComment', 'file', 'controller', 'triggerCommentDeleteAttached', 'after');
		if(!$oModuleModel->getTrigger('editor.deleteSavedDoc', 'file', 'controller', 'triggerDeleteAttached', 'after'))
			$oModuleController->insertTrigger('editor.deleteSavedDoc', 'file', 'controller', 'triggerDeleteAttached', 'after');
		if(!$oModuleModel->getTrigger('module.deleteModule', 'file', 'controller', 'triggerDeleteModuleFiles', 'after'))
			$oModuleController->insertTrigger('module.deleteModule', 'file', 'controller', 'triggerDeleteModuleFiles', 'after');
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'file', 'view', 'triggerDispFileAdditionSetup', 'before'))
			$oModuleController->insertTrigger('module.dispAdditionSetup', 'file', 'view', 'triggerDispFileAdditionSetup', 'before');
		if(!$oDB->isColumnExists('files', 'upload_target_type')) $oDB->addColumn('files', 'upload_target_type', 'char', '3');
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'file', 'controller', 'triggerCopyModule', 'after'))
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'file', 'controller', 'triggerCopyModule', 'after');
		if(!$oDB->isColumnExists('files', 'cover_image')) $oDB->addColumn('files', 'cover_image', 'char', '1', 'N');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
