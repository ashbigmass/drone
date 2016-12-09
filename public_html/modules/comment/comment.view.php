<?php
class commentView extends comment
{
	function init() {
	}

	function triggerDispCommentAdditionSetup(&$obj) {
		$current_module_srl = Context::get('module_srl');
		$current_module_srls = Context::get('module_srls');
		if(!$current_module_srl && !$current_module_srls) {
			$current_module_info = Context::get('current_module_info');
			$current_module_srl = $current_module_info->module_srl;
			if(!$current_module_srl) return new Object();
		}
		$oCommentModel = getModel('comment');
		$comment_config = $oCommentModel->getCommentConfig($current_module_srl);
		Context::set('comment_config', $comment_config);
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups();
		Context::set('group_list', $group_list);
		$oTemplate = TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path . 'tpl', 'comment_module_config');
		$obj .= $tpl;
		return new Object();
	}
}
