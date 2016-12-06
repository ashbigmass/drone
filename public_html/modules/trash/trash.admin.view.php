<?php
class trashAdminView extends trash
{

	function init() {
		$template_path = sprintf("%stpl/",$this->module_path);
		$this->setTemplatePath($template_path);
	}

	function dispTrashAdminList() {
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 5;
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');
		$oTrashModel = getModel('trash');
		$output = $oTrashModel->getTrashList($args);
		$oCommentModel = getModel('comment');
		$oDocumentModel = getModel('document');
		Context::set('trash_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		$oModuleModel = getModel('module');
		$module_list = array();
		$mod_srls = array();
		foreach($output->data as $oTrashVO) $mod_srls[] = $oTrashVO->unserializedObject['module_srl'];
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
		$this->setTemplateFile('trash_list');
	}

	function dispTrashAdminView() {
		$trash_srl = Context::get('trash_srl');
		$oTrashModel = getModel('trash');
		$output = $oTrashModel->getTrash($trash_srl);
		if(!$output->data->getTrashSrl()) return new Object(-1, 'msg_invalid_request');
		$originObject = unserialize($output->data->getSerializedObject());
		if(is_array($originObject)) $originObject = (object)$originObject;
		Context::set('oTrashVO',$output->data);
		Context::set('oOrigin',$originObject);
		$oMemberModel = &getModel('member');
		$remover_info = $oMemberModel->getMemberInfoByMemberSrl($output->data->getRemoverSrl());
		Context::set('remover_info', $remover_info);
		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($originObject->module_srl);
		Context::set('module_info', $module_info);
		if($originObject) {
			$args_extra->module_srl = $originObject->module_srl;
			$args_extra->document_srl = $originObject->document_srl;
			$output_extra = executeQueryArray('trash.getDocumentExtraVars', $args_extra);
			Context::set('oOriginExtraVars',$output_extra->data);
		}
		$this->setTemplateFile('trash_view');
	}
}
