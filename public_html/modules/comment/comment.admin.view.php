<?php
class commentAdminView extends comment
{
	function init() {
	}

	function dispCommentAdminList() {
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 5;
		$args->sort_index = 'list_order';
		$args->module_srl = Context::get('module_srl');
		$oCommentModel = getModel('comment');
		$secretNameList = $oCommentModel->getSecretNameList();
		$columnList = array('comment_srl', 'document_srl','module_srl','is_secret', 'status', 'content', 'comments.member_srl', 'comments.nick_name', 'comments.regdate', 'ipaddress', 'voted_count', 'blamed_count');
		$output = $oCommentModel->getTotalCommentList($args, $columnList);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('comment_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('secret_name_list', $secretNameList);
		$oModuleModel = getModel('module');
		$module_list = array();
		$mod_srls = array();
		foreach($output->data as $val) $mod_srls[] = $val->module_srl;
		$mod_srls = array_unique($mod_srls);
		$mod_srls_count = count($mod_srls);
		if($mod_srls_count) {
			$columnList = array('module_srl', 'mid', 'browser_title');
			$module_output = $oModuleModel->getModulesInfo($mod_srls, $columnList);
			if($module_output && is_array($module_output)) {
				foreach($module_output as $module) $module_list[$module->module_srl] = $module;
			}
		}
		Context::set('module_list', $module_list);
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile('comment_list');
	}

	function dispCommentAdminDeclared() {
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 10;
		$args->sort_index = 'comment_declared.declared_count';
		$args->order_type = 'desc';
		$declared_output = executeQuery('comment.getDeclaredList', $args);
		$oCommentModel = getModel('comment');
		if($declared_output->data && count($declared_output->data)) {
			$comment_list = array();
			foreach($declared_output->data as $key => $comment) {
				$comment_list[$key] = new commentItem();
				$comment_list[$key]->setAttribute($comment);
			}
			$declared_output->data = $comment_list;
		}
		$secretNameList = $oCommentModel->getSecretNameList();
		Context::set('total_count', $declared_output->total_count);
		Context::set('total_page', $declared_output->total_page);
		Context::set('page', $declared_output->page);
		Context::set('comment_list', $declared_output->data);
		Context::set('page_navigation', $declared_output->page_navigation);
		Context::set('secret_name_list', $secretNameList);
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile('declared_list');
	}
}
