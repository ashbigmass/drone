<?php
class moduleAdminView extends module
{
	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispModuleAdminContent() {
		$this->dispModuleAdminList();
	}

	function dispModuleAdminList() {
		$oAdminModel = getAdminModel('admin');
		$oModuleModel = getModel('module');
		$oAutoinstallModel = getModel('autoinstall');
		$module_list = $oModuleModel->getModuleList();
		if(is_array($module_list)) {
			foreach($module_list as $key => $val) {
				$module_list[$key]->delete_url = $oAutoinstallModel->getRemoveUrlByPath($val->path);
				$packageSrl = $oAutoinstallModel->getPackageSrlByPath($val->path);
				$package = $oAutoinstallModel->getInstalledPackages($packageSrl);
				$module_list[$key]->need_autoinstall_update = $package[$packageSrl]->need_update;
				if($module_list[$key]->need_autoinstall_update == 'Y') $module_list[$key]->update_url = $oAutoinstallModel->getUpdateUrlByPackageSrl($packageSrl);
			}
		}
		$output = $oAdminModel->getFavoriteList('0');
		$favoriteList = $output->get('favoriteList');
		$favoriteModuleList = array();
		if($favoriteList) {
			foreach($favoriteList as $favorite => $favorite_info) $favoriteModuleList[] = $favorite_info->module;
		}
		Context::set('favoriteModuleList', $favoriteModuleList);
		Context::set('module_list', $module_list);
		$security = new Security();
		$security->encodeHTML('module_list....');
		$this->setTemplateFile('module_list');
	}

	function dispModuleAdminInfo() {
		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoXml(Context::get('selected_module'));
		Context::set('module_info', $module_info);
		$security = new Security();
		$security->encodeHTML('module_info...');
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('module_info');
	}

	function dispModuleAdminCategory() {
		$module_category_srl = Context::get('module_category_srl');
		$oModuleModel = getModel('module');
		$security = new Security();
		if($module_category_srl) {
			$selected_category  = $oModuleModel->getModuleCategory($module_category_srl);
			Context::set('selected_category', $selected_category);
			$security->encodeHTML('selected_category.title');
			$this->setTemplateFile('category_update_form');
		} else {
			$category_list = $oModuleModel->getModuleCategories();
			Context::set('category_list', $category_list);
			$security->encodeHTML('category_list..title');
			$this->setTemplateFile('category_list');
		}
	}

	function dispModuleAdminCopyModule() {
		$module_srl = Context::get('module_srl');
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module', 'mid', 'browser_title');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		Context::set('module_info', $module_info);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('module_info.');
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('copy_module');
	}

	function dispModuleAdminModuleSetup() {
		$module_srls = Context::get('module_srls');
		$modules = explode(',',$module_srls);
		if(!count($modules)) if(!$module_srls) return new Object(-1,'msg_invalid_request');
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($modules[0], $columnList);
		$skin_list = $oModuleModel->getSkins(_XE_PATH_ . 'modules/'.$module_info->module);
		Context::set('skin_list',$skin_list);
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		$security = new Security();
		$security->encodeHTML('layout_list..title','layout_list..layout');
		$security->encodeHTML('skin_list....');
		$security->encodeHTML('module_category...');
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('module_setup');
	}

	function dispModuleAdminModuleAdditionSetup() {
		$module_srls = Context::get('module_srls');
		$modules = explode(',',$module_srls);
		if(!count($modules)) if(!$module_srls) return new Object(-1,'msg_invalid_request');
		$content = '';
		$output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'before', $content);
		$output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'after', $content);
		Context::set('setup_content', $content);
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('module_addition_setup');
	}

	function dispModuleAdminModuleGrantSetup() {
		$module_srls = Context::get('module_srls');
		$modules = explode(',',$module_srls);
		if(!count($modules)) if(!$module_srls) return new Object(-1,'msg_invalid_request');
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module', 'site_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($modules[0], $columnList);
		$xml_info = $oModuleModel->getModuleActionXml($module_info->module);
		$source_grant_list = $xml_info->grant;
		$grant_list->access->title = Context::getLang('grant_access');
		$grant_list->access->default = 'guest';
		if(count($source_grant_list)) {
			foreach($source_grant_list as $key => $val) {
				if(!$val->default) $val->default = 'guest';
				if($val->default == 'root') $val->default = 'manager';
				$grant_list->{$key} = $val;
			}
		}
		$grant_list->manager->title = Context::getLang('grant_manager');
		$grant_list->manager->default = 'manager';
		Context::set('grant_list', $grant_list);
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups($module_info->site_srl);
		Context::set('group_list', $group_list);
		$security = new Security();
		$security->encodeHTML('group_list..title');
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('module_grant_setup');
	}

	function dispModuleAdminLangcode() {
		$site_module_info = Context::get('site_module_info');
		$args = new stdClass();
		$args->site_srl = (int)$site_module_info->site_srl;
		$args->langCode = Context::get('lang_type');
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 5;
		$args->sort_index = 'name';
		$args->order_type = 'asc';
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');
		$oModuleAdminModel = getAdminModel('module');
		$output = $oModuleAdminModel->getLangListByLangcode($args);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('lang_code_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		if(Context::get('module') != 'admin') {
			$this->setLayoutPath('./common/tpl');
			$this->setLayoutFile('popup_layout');
		}
		$this->setTemplateFile('module_langcode');
	}

	function dispModuleAdminFileBox() {
		$oModuleModel = getModel('module');
		$output = $oModuleModel->getModuleFileBoxList();
		$page = Context::get('page');
		$page = $page?$page:1;
		Context::set('filebox_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('page', $page);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('filebox_list..comment', 'filebox_list..attributes.');
		$this->setTemplateFile('adminFileBox');
	}
}
