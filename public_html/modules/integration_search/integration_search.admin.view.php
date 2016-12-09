<?php
class integration_searchAdminView extends integration_search
{
	var $config = null;

	function init() {
		$oModuleModel = getModel('module');
		$this->config = $oModuleModel->getModuleConfig('integration_search');
		Context::set('config',$this->config);
		$this->setTemplatePath($this->module_path."/tpl/");
	}

	function dispIntegration_searchAdminContent() {
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$module_categories = $oModuleModel->getModuleCategories();
		$obj = new stdClass();
		$obj->site_srl = 0;
		$module_categories[$module->module_category_srl]->list[$module_srl] = $module;
		$security = new Security();
		$security->encodeHTML('skin_list..title');
		Context::set('sample_code', htmlspecialchars('<form action="{getUrl()}" method="get"><input type="hidden" name="vid" value="{$vid}" /><input type="hidden" name="mid" value="{$mid}" /><input type="hidden" name="act" value="IS" /><input type="text" name="is_keyword"  value="{$is_keyword}" /><input class="btn" type="submit" value="{$lang->cmd_search}" /></form>', ENT_COMPAT | ENT_HTML401, 'UTF-8', false) );
		$this->setTemplateFile("index");
	}

	function dispIntegration_searchAdminSkinInfo() {
		$oModuleModel = getModel('module');
		$skin_info = $oModuleModel->loadSkinInfo($this->module_path, $this->config->skin);
		$skin_vars = unserialize($this->config->skin_vars);
		if(count($skin_info->extra_vars)) {
			foreach($skin_info->extra_vars as $key => $val) {
				$name = $val->name;
				$type = $val->type;
				$value = $skin_vars->{$name};
				if($type=="checkbox"&&!$value) $value = array();
				$skin_info->extra_vars[$key]->value= $value;
			}
		}
		Context::set('skin_info', $skin_info);
		Context::set('skin_vars', $skin_vars);
		$config = $oModuleModel->getModuleConfig('integration_search');
		Context::set('module_info', unserialize($config->skin_vars));
		$security = new Security();
		$security->encodeHTML('skin_info...');
		$this->setTemplateFile("skin_info");
	}
}
