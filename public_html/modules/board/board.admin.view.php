<?php
class boardAdminView extends board {

	function init() {
		$module_srl = Context::get('module_srl');
		if(!$module_srl && $this->module_srl) {
			$module_srl = $this->module_srl;
			Context::set('module_srl', $module_srl);
		}
		$oModuleModel = getModel('module');
		if($module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info) {
				Context::set('module_srl','');
				$this->act = 'list';
			} else {
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				$this->module_info->use_status = explode('|@|', $module_info->use_status);
				Context::set('module_info',$module_info);
			}
		}
		if($module_info && $module_info->module != 'board') return $this->stop("msg_invalid_request");
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		$security = new Security();
		$security->encodeHTML('module_info.');
		$security->encodeHTML('module_category..');
		$template_path = sprintf("%stpl/",$this->module_path);
		$this->setTemplatePath($template_path);
		foreach($this->order_target as $key) $order_target[$key] = Context::getLang($key);
		$order_target['list_order'] = Context::getLang('document_srl');
		$order_target['update_order'] = Context::getLang('last_update');
		Context::set('order_target', $order_target);
	}

	function dispBoardAdminContent() {
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		switch ($search_target){
			case 'mid': $args->s_mid = $search_keyword; break;
			case 'browser_title': $args->s_browser_title = $search_keyword; break;
		}
		$output = executeQueryArray('board.getBoardList', $args);
		ModuleModel::syncModuleToSite($output->data);
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		$oModuleAdminModel = getAdminModel('module');
		$selected_manage_content = $oModuleAdminModel->getSelectedManageHTML($this->xml_info->grant);
		Context::set('selected_manage_content', $selected_manage_content);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('board_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$security = new Security();
		$security->encodeHTML('board_list..browser_title','board_list..mid');
		$security->encodeHTML('skin_list..title','mskin_list..title');
		$security->encodeHTML('layout_list..title','layout_list..layout');
		$security->encodeHTML('mlayout_list..title','mlayout_list..layout');
		$this->setTemplateFile('index');
	}

	function dispBoardAdminBoardInfo() {
		$this->dispBoardAdminInsertBoard();
	}

	function dispBoardAdminInsertBoard() {
		if(!in_array($this->module_info->module, array('admin', 'board','blog','guestbook'))) return $this->alertMessage('msg_invalid_request');
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		$security = new Security();
		$security->encodeHTML('skin_list..title','mskin_list..title');
		$security->encodeHTML('layout_list..title','layout_list..layout');
		$security->encodeHTML('mlayout_list..title','mlayout_list..layout');
		$oDocumentModel = getModel('document');
		$documentStatusList = $oDocumentModel->getStatusNameList();
		Context::set('document_status_list', $documentStatusList);
		$oBoardModel = getModel('board');
		$extra_vars = $oBoardModel->getDefaultListConfig($this->module_info->module_srl);
		Context::set('extra_vars', $extra_vars);
		Context::set('list_config', $oBoardModel->getListConfig($this->module_info->module_srl));
		$module_extra_vars = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
		$extra_order_target = array();
		foreach($module_extra_vars as $oExtraItem) $extra_order_target[$oExtraItem->eid] = $oExtraItem->name;
		Context::set('extra_order_target', $extra_order_target);
		$security = new Security();
		$security->encodeHTML('extra_vars..name','list_config..name');
		$this->setTemplateFile('board_insert');
	}

	function dispBoardAdminBoardAdditionSetup() {
		$content = '';
		$output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'before', $content);
		$output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'after', $content);
		Context::set('setup_content', $content);
		$this->setTemplateFile('addition_setup');
	}

	function dispBoardAdminDeleteBoard() {
		if(!Context::get('module_srl')) return $this->dispBoardAdminContent();
		if(!in_array($this->module_info->module, array('admin', 'board','blog','guestbook'))) return $this->alertMessage('msg_invalid_request');
		$module_info = Context::get('module_info');
		$oDocumentModel = getModel('document');
		$document_count = $oDocumentModel->getDocumentCount($module_info->module_srl);
		$module_info->document_count = $document_count;
		Context::set('module_info',$module_info);
		$security = new Security();
		$security->encodeHTML('module_info..mid','module_info..module','module_info..document_count');
		$this->setTemplateFile('board_delete');
	}

	function dispBoardAdminCategoryInfo() {
		$oDocumentModel = getModel('document');
		$category_content = $oDocumentModel->getCategoryHTML($this->module_info->module_srl);
		Context::set('category_content', $category_content);
		Context::set('module_info', $this->module_info);
		$this->setTemplateFile('category_list');
	}

	function dispBoardAdminGrantInfo() {
		$oModuleAdminModel = getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);
		$this->setTemplateFile('grant_list');
	}

	function dispBoardAdminExtraVars() {
		$oDocumentModel = getModel('document');
		$extra_vars_content = $oDocumentModel->getExtraVarsHTML($this->module_info->module_srl);
		Context::set('extra_vars_content', $extra_vars_content);
		$this->setTemplateFile('extra_vars');
	}

	function dispBoardAdminSkinInfo() {
		$oModuleAdminModel = getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skin_info');
	}

	function dispBoardAdminMobileSkinInfo() {
		$oModuleAdminModel = getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skin_info');
	}

	function alertMessage($message) {
		$script =  sprintf('<script> xAddEventListener(window,"load", function() { alert("%s"); } );</script>', Context::getLang($message));
		Context::addHtmlHeader( $script );
	}
}
