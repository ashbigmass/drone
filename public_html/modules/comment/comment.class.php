<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */

require_once(_XE_PATH_ . 'modules/comment/comment.item.php');

class comment extends ModuleObject {

	function moduleInstall() {
		$oDB = DB::getInstance();
		$oModuleController = getController('module');

		$oDB->addIndex(
				"comments", "idx_module_list_order", array("module_srl", "list_order"), TRUE
		);

		$oModuleController->insertTrigger('document.deleteDocument', 'comment', 'controller', 'triggerDeleteDocumentComments', 'after');
		$oModuleController->insertTrigger('module.deleteModule', 'comment', 'controller', 'triggerDeleteModuleComments', 'after');
		$oModuleController->insertTrigger('module.dispAdditionSetup', 'comment', 'view', 'triggerDispCommentAdditionSetup', 'before');

		if(!is_dir('./files/cache/tmp')) FileHandler::makeDir('./files/cache/tmp');
		return new Object();
	}

	function checkUpdate() {
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'comment', 'controller', 'triggerDeleteDocumentComments', 'after')) return TRUE;
		if(!$oModuleModel->getTrigger('module.deleteModule', 'comment', 'controller', 'triggerDeleteModuleComments', 'after')) return TRUE;
		if(!$oDB->isColumnExists("comments", "voted_count")) return TRUE;
		if(!$oDB->isColumnExists("comments", "notify_message")) return TRUE;
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'comment', 'view', 'triggerDispCommentAdditionSetup', 'before')) return TRUE;
		if(!$oDB->isColumnExists("comments", "blamed_count")) return TRUE;
		if(!$oDB->isColumnExists("comment_voted_log", "point")) return TRUE;
		if(!$oDB->isIndexExists("comments", "idx_module_list_order")) return TRUE;
		if(!$oDB->isColumnExists("comments", "status")) return TRUE;
		if(!$oDB->isIndexExists("comments", "idx_status")) return TRUE;
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'comment', 'controller', 'triggerCopyModule', 'after')) return TRUE;
		return FALSE;
	}

	function moduleUpdate() {
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'comment', 'controller', 'triggerDeleteDocumentComments', 'after'))
			$oModuleController->insertTrigger('document.deleteDocument', 'comment', 'controller', 'triggerDeleteDocumentComments', 'after');
		if(!$oModuleModel->getTrigger('module.deleteModule', 'comment', 'controller', 'triggerDeleteModuleComments', 'after'))
			$oModuleController->insertTrigger('module.deleteModule', 'comment', 'controller', 'triggerDeleteModuleComments', 'after');
		if(!$oDB->isColumnExists("comments", "voted_count")) {
			$oDB->addColumn("comments", "voted_count", "number", "11");
			$oDB->addIndex("comments", "idx_voted_count", array("voted_count"));
		}

		if(!$oDB->isColumnExists("comments", "notify_message")) $oDB->addColumn("comments", "notify_message", "char", "1");
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'comment', 'view', 'triggerDispCommentAdditionSetup', 'before'))
			$oModuleController->insertTrigger('module.dispAdditionSetup', 'comment', 'view', 'triggerDispCommentAdditionSetup', 'before');
		if(!$oDB->isColumnExists("comments", "blamed_count")) {
			$oDB->addColumn('comments', 'blamed_count', 'number', 11, 0, TRUE);
			$oDB->addIndex('comments', 'idx_blamed_count', array('blamed_count'));
		}
		if(!$oDB->isColumnExists("comment_voted_log", "point")) $oDB->addColumn('comment_voted_log', 'point', 'number', 11, 0, TRUE);
		if(!$oDB->isIndexExists("comments", "idx_module_list_order")) {
			$oDB->addIndex(
					"comments", "idx_module_list_order", array("module_srl", "list_order"), TRUE
			);
		}
		if(!$oDB->isColumnExists("comments", "status")) $oDB->addColumn("comments", "status", "number", 1, 1, TRUE);
		if(!$oDB->isIndexExists("comments", "idx_status")) {
			$oDB->addIndex(
					"comments", "idx_status", array("status", "comment_srl", "module_srl", "document_srl"), TRUE
			);
		}
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'comment', 'controller', 'triggerCopyModule', 'after'))
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'comment', 'controller', 'triggerCopyModule', 'after');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
		if(!is_dir('./files/cache/tmp')) {
			FileHandler::makeDir('./files/cache/tmp');
		}
	}
}
