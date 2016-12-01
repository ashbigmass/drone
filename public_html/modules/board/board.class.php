<?php
class board extends ModuleObject
	var $search_option = array('title_content','title','content','comment','user_name','nick_name','user_id','tag');
	var $order_target = array('list_order', 'update_order', 'regdate', 'voted_count', 'blamed_count', 'readed_count', 'comment_count', 'title', 'nick_name', 'user_name', 'user_id');
	var $skin = "default";
	var $list_count = 20;
	var $page_count = 10;
	var $category_list = NULL;

	function board() {
		if(!Context::isInstalled()) return;
		if(!Context::isExistsSSLAction('dispBoardWrite') && Context::getSslStatus() == 'optional') {
			$ssl_actions = array('dispBoardWrite', 'dispBoardWriteComment', 'dispBoardReplyComment', 'dispBoardModifyComment', 'dispBoardDelete', 'dispBoardDeleteComment', 'procBoardInsertDocument', 'procBoardDeleteDocument', 'procBoardInsertComment', 'procBoardDeleteComment', 'procBoardVerificationPassword');
			Context::addSSLActions($ssl_actions);
		}
		if(!Context::isExistsSSLAction('dispTempSavedList') && Context::getSslStatus() == 'optional') Context::addSSLAction('dispTempSavedList');
	}

	function moduleInstall() {
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');
		$oModuleController->insertTrigger('member.getMemberMenu', 'board', 'controller', 'triggerMemberMenu', 'after');
		$args = new stdClass;
		$args->site_srl = 0;
		$output = executeQuery('module.getSite', $args);
		if(!$output->data->index_module_srl) {
			$args->mid = 'board';
			$args->module = 'board';
			$args->browser_title = 'XpressEngine';
			$args->skin = 'default';
			$args->site_srl = 0;
			$output = $oModuleController->insertModule($args);
			if($output->toBool()) {
				$module_srl = $output->get('module_srl');
				$site_args = new stdClass;
				$site_args->site_srl = 0;
				$site_args->index_module_srl = $module_srl;
				$oModuleController = getController('module');
				$oModuleController->updateSite($site_args);
			}
		}
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('member.getMemberMenu', 'board', 'controller', 'triggerMemberMenu', 'after')) return true;
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'board', 'model', 'triggerModuleListInSitemap', 'after')) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('member.getMemberMenu', 'board', 'controller', 'triggerMemberMenu', 'after')) {
			$oModuleController->insertTrigger('member.getMemberMenu', 'board', 'controller', 'triggerMemberMenu', 'after');
		}
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'board', 'model', 'triggerModuleListInSitemap', 'after')) {
			$oModuleController->insertTrigger('menu.getModuleListInSitemap', 'board', 'model', 'triggerModuleListInSitemap', 'after');
		}
		return new Object(0, 'success_updated');
	}

	function moduleUninstall() {
		$output = executeQueryArray("board.getAllBoard");
		if(!$output->data) return new Object();
		@set_time_limit(0);
		$oModuleController = getController('module');
		foreach($output->data as $board) $oModuleController->deleteModule($board->module_srl);
		return new Object();
	}
}
