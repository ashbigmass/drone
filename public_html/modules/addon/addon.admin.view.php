<?php
class addonAdminView extends addon {

	function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	function dispAddonAdminIndex() {
		$oAdminModel = getAdminModel('admin');
		$oAddonModel = getAdminModel('addon');
		$addon_list = $oAddonModel->getAddonListForSuperAdmin();
		$security = new Security($addon_list);
		$addon_list = $security->encodeHTML('..', '..author..');
		foreach($addon_list as $no => $addon_info) $addon_list[$no]->description = nl2br(trim($addon_info->description));
		Context::set('addon_list', $addon_list);
		Context::set('addon_count', count($addon_list));
		$this->setTemplateFile('addon_list');
	}

	function dispAddonAdminSetup() {
		$site_module_info = Context::get('site_module_info');
		$selected_addon = Context::get('selected_addon');
		$oAddonModel = getAdminModel('addon');
		$addon_info = $oAddonModel->getAddonInfoXml($selected_addon, $site_module_info->site_srl, 'site');
		Context::set('addon_info', $addon_info);
		$oModuleModel = getModel('module');
		$oModuleAdminModel = getAdminModel('module');
		$args = new stdClass();
		if($site_module_info->site_srl) $args->site_srl = $site_module_info->site_srl;
		$columnList = array('module_srl', 'module_category_srl', 'mid', 'browser_title');
		$mid_list = $oModuleModel->getMidList($args, $columnList);
		if(!$site_module_info->site_srl) {
			$module_categories = $oModuleModel->getModuleCategories();
			if(is_array($mid_list)) {
				foreach($mid_list as $module_srl => $module) $module_categories[$module->module_category_srl]->list[$module_srl] = $module;
			}
		} else {
			$module_categories = array();
			$module_categories[0] = new stdClass();
			$module_categories[0]->list = $mid_list;
		}
		Context::set('mid_list', $module_categories);
		$this->setTemplateFile('setup_addon');
		if(Context::get('module') != 'admin') {
			$this->setLayoutPath('./common/tpl');
			$this->setLayoutFile('popup_layout');
		}
		$security = new Security();
		$security->encodeHTML('addon_info.', 'addon_info.author..', 'mid_list....');
	}

	function dispAddonAdminInfo() {
		$site_module_info = Context::get('site_module_info');
		$selected_addon = Context::get('selected_addon');
		$oAddonModel = getAdminModel('addon');
		$addon_info = $oAddonModel->getAddonInfoXml($selected_addon, $site_module_info->site_srl);
		Context::set('addon_info', $addon_info);
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('addon_info');
		$security = new Security();
		$security->encodeHTML('addon_info.', 'addon_info.author..');
	}
}
