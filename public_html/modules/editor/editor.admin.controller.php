<?php

class editorAdminController extends editor {

	function init() {}

	function procEditorAdminCheckUseListOrder() {
		$site_module_info = Context::get('site_module_info');
		$enables = Context::get('enables');
		$component_names = Context::get('component_names');
		if(!is_array($component_names)) $component_names = array();
		if(!is_array($enables)) $enables = array();
		$unables = array_diff($component_names, $enables);
		$componentList = array();
		foreach($enables as $component_name) $componentList[$component_name] = 'Y';
		foreach($unables as $component_name) $componentList[$component_name] = 'N';
		$output = $this->editorListOrder($component_names,$site_module_info->site_srl);
		if(!$output->toBool()) return new Object();
		$output = $this->editorCheckUse($componentList,$site_module_info->site_srl);
		if(!$output->toBool()) return new Object();
		$oEditorController = getController('editor');
		$oEditorController->removeCache($site_module_info->site_srl);
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	function editorCheckUse($componentList, $site_srl = 0) {
		$args = new stdClass();
		$args->site_srl = $site_srl;
		foreach($componentList as $componentName => $value) {
			$args->component_name = $componentName;
			$args->enabled = $value;
			$output = ($site_srl == 0) ? executeQuery('editor.updateComponent', $args) : executeQuery('editor.updateSiteComponent', $args);
		}
		if(!$output->toBool()) return new Object();
		unset($componentList);
		return $output;
	}

	function editorListOrder($component_names, $site_srl = 0) {
		$args = new stdClass();
		$args->site_srl = $site_srl;
		$list_order_num = '30';
		if(is_array($component_names)) {
			foreach($component_names as $name) {
				$args->list_order = $list_order_num;
				$args->component_name = $name;
				$output = ($site_srl == 0) ? executeQuery('editor.updateComponent', $args) : executeQuery('editor.updateSiteComponent', $args);
				if(!$output->toBool()) return new Object();
				$list_order_num++;
			}
		}
		unset($component_names);
		return $output;
	}

	function procEditorAdminSetupComponent() {
		$site_module_info = Context::get('site_module_info');
		$component_name = Context::get('component_name');
		$extra_vars = Context::getRequestVars();
		unset($extra_vars->component_name);
		unset($extra_vars->module);
		unset($extra_vars->act);
		unset($extra_vars->body);
		$args = new stdClass;
		$args->component_name = $component_name;
		$args->extra_vars = serialize($extra_vars);
		$args->site_srl = (int)$site_module_info->site_srl;
		$ouput = (!$args->site_srl) ? executeQuery('editor.updateComponent', $args) : executeQuery('editor.updateSiteComponent', $args);
		if(!$output->toBool()) return $output;
		$oEditorController = getController('editor');
		$oEditorController->removeCache($args->site_srl);
		$this->setMessage('success_updated');
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	function procEditorAdminGeneralConfig() {
		$oModuleController = getController('module');
		$configVars = Context::getRequestVars();
		$config = new stdClass;
		if($configVars->font_defined != 'Y') $config->font_defined = $configVars->font_defined = 'N';
		else $config->font_defined = 'Y';
		if($config->font_defined == 'Y') $config->content_font = $configVars->content_font_defined;
		else $config->content_font = $configVars->content_font;
		$config->editor_skin = $configVars->editor_skin;
		$config->editor_height = $configVars->editor_height;
		$config->comment_editor_skin = $configVars->comment_editor_skin;
		$config->comment_editor_height = $configVars->comment_editor_height;
		$config->content_style = $configVars->content_style;
		$config->content_font_size= $configVars->content_font_size.'px';
		$config->sel_editor_colorset= $configVars->sel_editor_colorset;
		$config->sel_comment_editor_colorset= $configVars->sel_comment_editor_colorset;
		$oModuleController->insertModuleConfig('editor',$config);
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	function insertComponent($component_name, $enabled = false, $site_srl = 0) {
		$enabled = (!$enabled) ? 'N' : 'Y';
		$args = new stdClass;
		$args->component_name = $component_name;
		$args->enabled = $enabled;
		$args->site_srl = $site_srl;
		$output  =  (!$site_srl) ? executeQuery('editor.isComponentInserted', $args) : executeQuery('editor.isSiteComponentInserted', $args);
		if($output->data->count) return new Object(-1, 'msg_component_is_not_founded');
		$args->list_order = getNextSequence();
		$output = (!$site_srl) ? executeQuery('editor.insertComponent', $args) : executeQuery('editor.insertSiteComponent', $args);
		$oEditorController = getController('editor');
		$oEditorController->removeCache($site_srl);
		return $output;
	}
}
