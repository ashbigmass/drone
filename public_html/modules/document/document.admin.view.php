<?php
class documentAdminView extends document {
	function init() {
		$oModuleModel = getModel('module');
		$info = $oModuleModel->getModuleActionXml('document');
		foreach($info->menu AS $key => $menu) {
			if(in_array($this->act, $menu->acts)) {
				Context::set('currentMenu', $key);
				break;
			}
		}
	}

	function dispDocumentAdminList() {
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 5;
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');
		$args->sort_index = 'list_order';
		$args->module_srl = Context::get('module_srl');
		$oDocumentModel = getModel('document');
		$columnList = array('document_srl', 'module_srl', 'title', 'member_srl', 'nick_name', 'readed_count', 'voted_count', 'blamed_count', 'regdate', 'ipaddress', 'status', 'category_srl');
		$output = $oDocumentModel->getDocumentList($args, false, true, $columnList);
		$statusNameList = $oDocumentModel->getStatusNameList();
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('document_list', $output->data);
		Context::set('status_name_list', $statusNameList);
		Context::set('page_navigation', $output->page_navigation);
		$count_search_option = count($this->search_option);
		for($i=0;$i<$count_search_option;$i++) $search_option[$this->search_option[$i]] = Context::getLang($this->search_option[$i]);
		Context::set('search_option', $search_option);
		$oModuleModel = getModel('module');
		$module_list = array();
		$mod_srls = array();
		foreach($output->data as $oDocument) $mod_srls[] = $oDocument->get('module_srl');
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
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('document_list');
	}

	function dispDocumentAdminConfig() {
		$oDocumentModel = getModel('document');
		$config = $oDocumentModel->getDocumentConfig();
		Context::set('config',$config);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('document_config');
	}

	function dispDocumentAdminDeclared() {
		$args =new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 10;
		$args->sort_index = 'document_declared.declared_count';
		$args->order_type = 'desc';
		$oDocumentModel = getModel('document');
		$statusNameList = $oDocumentModel->getStatusNameList();
		$declared_output = executeQuery('document.getDeclaredList', $args);
		if($declared_output->data && count($declared_output->data)) {
			$document_list = array();
			foreach($declared_output->data as $key => $document) {
				$document_list[$key] = new documentItem();
				$document_list[$key]->setAttribute($document);
			}
			$declared_output->data = $document_list;
		}
		Context::set('total_count', $declared_output->total_count);
		Context::set('total_page', $declared_output->total_page);
		Context::set('page', $declared_output->page);
		Context::set('document_list', $declared_output->data);
		Context::set('page_navigation', $declared_output->page_navigation);
		Context::set('status_name_list', $statusNameList);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('declared_list');
	}

	function dispDocumentAdminAlias() {
		$args->document_srl = Context::get('document_srl');
		if(!$args->document_srl) return $this->dispDocumentAdminList();
		$oModel = getModel('document');
		$oDocument = $oModel->getDocument($args->document_srl);
		if(!$oDocument->isExists()) return $this->dispDocumentAdminList();
		Context::set('oDocument', $oDocument);
		$output = executeQueryArray('document.getAliases', $args);
		if(!$output->data) $aliases = array();
		else $aliases = $output->data;
		Context::set('aliases', $aliases);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('document_alias');
	}

	function dispDocumentAdminTrashList() {
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 10;
		$args->sort_index = 'list_order';
		$args->order_type = 'desc';
		$args->module_srl = Context::get('module_srl');
		$oDocumentAdminModel = getAdminModel('document');
		$output = $oDocumentAdminModel->getDocumentTrashList($args);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('document_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('document_trash_list');
	}
}
