<?php
class widgetView extends widget
{
	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispWidgetInfo() {
		if(Context::get('skin')) return $this->dispWidgetSkinInfo();
		$oWidgetModel = getModel('widget');
		$widget_info = $oWidgetModel->getWidgetInfo(Context::get('selected_widget'));
		Context::set('widget_info', $widget_info);
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('widget_detail_info');
	}

	function dispWidgetSkinInfo() {
		$widget = Context::get('selected_widget');
		$skin = Context::get('skin');
		$path = sprintf('./widgets/%s/', $widget);
		$oModuleModel = getModel('module');
		$skin_info = $oModuleModel->loadSkinInfo($path, $skin);
		Context::set('skin_info',$skin_info);
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('skin_info');
	}

	function dispWidgetGenerateCode() {
		$oWidgetModel = getModel('widget');
		$widget_list = $oWidgetModel->getDownloadedWidgetList();
		$selected_widget = Context::get('selected_widget');
		if(!$selected_widget) $selected_widget = $widget_list[0]->widget;
		$widget_info = $oWidgetModel->getWidgetInfo($selected_widget);
		Context::set('widget_info', $widget_info);
		Context::set('widget_list', $widget_list);
		Context::set('selected_widget', $selected_widget);
		$oModuleModel = getModel('module');
		$module_categories = $oModuleModel->getModuleCategories();
		$site_module_info = Context::get('site_module_info');
		$args = new stdClass();
		$args->site_srl = $site_module_info->site_srl;
		$columnList = array('module_srl', 'module_category_srl', 'browser_title', 'mid');
		$mid_list = $oModuleModel->getMidList($args, $columnList);
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups($site_module_info->site_srl);
		Context::set('group_list', $group_list);
		if($module_categories) {
			foreach($mid_list as $module_srl => $module) {
				$module_categories[$module->module_category_srl]->list[$module_srl] = $module;
			}
		} else {
			$module_categories[0] = new stdClass();
			$module_categories[0]->list = $mid_list;
		}
		Context::set('mid_list',$module_categories);
		$output = executeQueryArray('menu.getMenus');
		Context::set('menu_list',$output->data);
		$skin_list = $oModuleModel->getSkins($widget_info->path);
		Context::set('skin_list', $skin_list);
		$this->setLayoutFile('popup_layout');
		$this->setTemplateFile('widget_generate_code');
	}

	function dispWidgetGenerateCodeInPage() {
		$oWidgetModel = getModel('widget');
		$widget_list = $oWidgetModel->getDownloadedWidgetList();
		Context::set('widget_list',$widget_list);
		if(!Context::get('selected_widget')) Context::set('selected_widget',$widget_list[0]->widget);
		$this->dispWidgetGenerateCode();
		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('widget_generate_code_in_page');
	}

	function dispWidgetStyleGenerateCodeInPage() {
		$oWidgetModel = getModel('widget');
		$widgetStyle_list = $oWidgetModel->getDownloadedWidgetStyleList();
		Context::set('widgetStyle_list',$widgetStyle_list);
		$widgetstyle = Context::get('widgetstyle');
		$widgetstyle_info = $oWidgetModel->getWidgetStyleInfo($widgetstyle);
		if($widgetstyle && $widgetstyle_info) Context::set('widgetstyle_info',$widgetstyle_info);
		$this->dispWidgetGenerateCode();
		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('widget_style_generate_code_in_page');
	}
}
