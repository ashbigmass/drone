<?php
class documentView extends document {
	function init() {}

	function dispDocumentPrint() {
		$document_srl = Context::get('document_srl');
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl, $this->grant->manager);
		if(!$oDocument->isExists()) return new Object(-1,'msg_invalid_request');
		if(!$oDocument->isAccessible()) return new Object(-1,'msg_not_permitted');
		Context::setBrowserTitle($oDocument->getTitleText());
		Context::set('oDocument', $oDocument);
		Context::set('layout','none');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('print_page');
	}

	function dispDocumentPreview() {
		Context::set('layout','none');
		$content = Context::get('content');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('preview_page');
	}

	function dispDocumentManageDocument() {
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');
		$flag_list = $_SESSION['document_management'];
		if(count($flag_list)) {
			foreach($flag_list as $key => $val) {
				if(!is_bool($val)) continue;
				$document_srl_list[] = $key;
			}
		}
		if(count($document_srl_list)) {
			$oDocumentModel = getModel('document');
			$document_list = $oDocumentModel->getDocuments($document_srl_list, $this->grant->is_admin);
			Context::set('document_list', $document_list);
		}
		$oModuleModel = getModel('module');
		if(count($module_list)>1) Context::set('module_list', $module_categories);
		$module_srl=Context::get('module_srl');
		Context::set('module_srl',$module_srl);
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('mid',$module_info->mid);
		Context::set('browser_title',$module_info->browser_title);
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('popup_layout');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('checked_list');
	}

	function triggerDispDocumentAdditionSetup(&$obj) {
		$current_module_srl = Context::get('module_srl');
		$current_module_srls = Context::get('module_srls');
		if(!$current_module_srl && !$current_module_srls) {
			$current_module_info = Context::get('current_module_info');
			$current_module_srl = $current_module_info->module_srl;
			if(!$current_module_srl) return new Object();
		}
		$oModuleModel = getModel('module');
		if($current_module_srl) $document_config = $oModuleModel->getModulePartConfig('document', $current_module_srl);
		if(!$document_config) $document_config = new stdClass();
		if(!isset($document_config->use_history)) $document_config->use_history = 'N';
		Context::set('document_config', $document_config);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'document_module_config');
		$obj .= $tpl;
		return new Object();
	}

	function dispTempSavedList() {
		$this->setLayoutFile('popup_layout');
		$oMemberModel = getModel('member');
		if(!$oMemberModel->isLogged()) return $this->stop('msg_not_logged');
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->statusList = array($this->getConfigStatus('temp'));
		$args->page = (int)Context::get('page');
		$args->list_count = 10;
		$oDocumentModel = getModel('document');
		$output = $oDocumentModel->getDocumentList($args, true);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('document_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('saved_list_popup');
	}

}
