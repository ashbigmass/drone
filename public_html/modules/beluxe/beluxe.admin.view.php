<?php
class beluxeAdminView extends beluxe
{
	function init() {
		$cmModule = &getModel('module');
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl && $this->module_srl) {
			$mod_srl = $this->module_srl;
			Context::set('module_srl', $mod_srl);
		}
		if ($mod_srl) {
			$oMi = $cmModule->getModuleInfoByModuleSrl($mod_srl);
			if (!$oMi) {
				Context::set('module_srl', '');
				$this->act = 'list';
			} else {
				ModuleModel::syncModuleToSite($oMi);
				$this->module_info = $oMi;
			}
		}
		$oMi = &$this->module_info;
		if($oMi && $oMi->module == 'beluxe')
		{
			if (!$oMi->skin || $oMi->skin == '/USE_DEFAULT/') {
				$oMi->skin = 'default';
				$oMi->mskin = 'default/mobile';
			}
			$this->module_srl = $oMi->module_srl;
			Context::set('module_srl', $this->module_srl);
			Context::set('module_info', $oMi);
		}
		Context::loadLang($this->module_path . 'lang/admin');
		$order = explode(',', __XEFM_ORDER__);
		foreach ($order as $key) $order_target[$key] = Context::getLang($key);
		$order_target['list_order'] = Context::getLang('document_srl');
		$order_target['update_order'] = Context::getLang('last_update');
		Context::set('order_target', $order_target);
		$module_category = $cmModule->getModuleCategories();
		Context::set('module_category', $module_category);
		$this->setTemplatePath($this->module_path . 'tpl/');
		if ((int)Context::get('is_poped')) {
			$this->setLayoutPath('./common/tpl');
			$this->setLayoutFile('popup_layout');
		}
	}

	function dispBeluxeAdminContent() {
		$this->dispBeluxeAdminList();
	}

	function dispBeluxeAdminList() {
		$cmAdmThis = &getAdminModel(__XEFM_NAME__);
		$out = $cmAdmThis->getBeluxeList();
		Context::set('beluxe_list', $out->data);
		Context::set('total_count', $out->total_count);
		Context::set('total_page', $out->total_page);
		Context::set('page', $out->page);
		Context::set('page_navigation', $out->page_navigation);
		$security = new Security();
		$security->encodeHTML('beluxe_list..browser_title', 'beluxe_list..mid');
		$this->setTemplateFile('list');
	}

	function dispBeluxeAdminInsert() {
		$cmModule = &getModel('module');
		$skin_lst = $cmModule->getSkins($this->module_path);
		Context::set('skin_list', $skin_lst);
		$cmLayout = &getModel('layout');
		$layout_lst = $cmLayout->getLayoutList();
		Context::set('layout_list', $layout_lst);
		$mobile_llst = $cmLayout->getLayoutList(0, 'M');
		Context::set('mlayout_list', $mobile_llst);
		$security = new Security();
		$security->encodeHTML('skin_list..title');
		$security->encodeHTML('layout_list..title', 'layout_list..layout');
		$security->encodeHTML('mlayout_list..title', 'mlayout_list..layout');
		$cmDocument = &getModel('document');
		$stat_lst = $cmDocument->getStatusNameList();
		Context::set('document_status_list', $stat_lst);
		$m_target = Context::get('m_target');
		$m_targets = Context::get('m_targets');
		if ($m_target || $m_targets) {
			if ($m_target) {
				$m_target = explode(',', $m_target);
				$m_target = $m_target[0];
				$site_srl = Context::get('site_srl');
				$oMi = $cmModule->getModuleInfoByMid($m_target, $site_srl);
				if ($oMi) ModuleModel::syncModuleToSite($oMi);
				else return $this->stop($m_target);
				Context::set('m_copy_target', $m_target);
			} else {
				Context::set('m_allset_targets', $m_targets);
			}
			Context::set('module_info', $oMi);
			$security = new Security();
			$security->encodeHTML('module_info.');
		} else {
			$cmAdmThis = &getAdminModel(__XEFM_NAME__);
			$_tmp = $cmAdmThis->getTypeList($this->module_srl ? $this->module_info->skin : 'default');
			Context::set('default_type_list', $_tmp);
			if (is_string($this->module_info->backup_options)) {
				$_tmp = unserialize($this->module_info->backup_options);
				$a = array();
				foreach ($_tmp as $key => $val) $a[$key] = $val;
				Context::set('compulsory_options', $a);
			}
			if (is_string($_SESSION['BELUXE_MODULE_BACKUP_OPTIONS'])) {
				$_tmp = unserialize($_SESSION['BELUXE_MODULE_BACKUP_OPTIONS']);
				$a = array();
				$arr_sk = array(
					'use_update_vote_count', 'use_vote_point_check', 'use_vote_point_recover', 'use_vote_point_range',
					'use_lock_owner_comment', 'use_lock_comment_count'
				);
				foreach ($_tmp as $key => $val) {
					if (in_array($key, $arr_sk)) continue;
					$a[$key] = $val;
				}
				Context::set('module_backup_options', $a);
				unset($_SESSION['BELUXE_MODULE_BACKUP_OPTIONS']);
			}
			if ($this->module_srl) {
				$doc_cfg = $cmModule->getModulePartConfig('document', $this->module_srl);
				$part_config->use_history = $doc_cfg->use_history;
				$doc_cfg = $cmModule->getModulePartConfig('comment', $this->module_srl);
				$doc_cfg = $cmModule->getModulePartConfig('trackback', $this->module_srl);
				$part_config->enable_trackback = $doc_cfg->enable_trackback != 'N' ? 'Y' : 'N';
				Context::set('part_config', $part_config);
			}
			$security = new Security();
			$security->encodeHTML('compulsory_options.', 'module_backup_options.');
		}
		$this->setTemplateFile('insert');
	}

	function dispBeluxeAdminModuleInfo() {
		$this->dispBeluxeAdminInsert();
	}

	function dispBeluxeAdminCategoryInfo() {
		$cmAdmThis = &getAdminModel(__XEFM_NAME__);
		$out = $cmAdmThis->getCategories($this->module_srl);
		Context::set('menu', $out->data);
		$cmMember = &getModel('member');
		$gru_lst = $cmMember->getGroups($this->module_info->site_srl);
		Context::set('group_list', $gru_lst);
		$types = $cmAdmThis->getTypeList($this->module_info->skin);
		Context::set('type_list', $types);
		Context::loadJavascriptPlugin('ui.colorpicker');
		$this->setTemplateFile('category');
	}

	function dispBeluxeAdminSkinInfo() {
		$oMi = $this->module_info;
		$skin = $oMi->skin;
		$module_path = _XE_PATH_ . 'modules/'.$oMi->module;
		$tpl_path = sprintf('%s/skins/%s/', $module_path, $skin);
		if (!is_dir($tpl_path)) {
			Context::set('XE_VALIDATOR_MESSAGE_TYPE', 'error');
			Context::set('XE_VALIDATOR_MESSAGE', Context::getLang('msg_skin_does_not_exist'));
		}
		$cmModule = &getModel('module');
		$skin_info = $cmModule->loadSkinInfo($module_path, $skin);
		$skin_vars = $cmModule->getModuleSkinVars($this->module_srl);
		Context::set('mid', $oMi->mid);
		Context::set('skin_info', $skin_info);
		Context::set('skin_vars', $skin_vars);
		$this->setTemplateFile('skin');
	}

	function dispBeluxeAdminMobileSkinInfo() {
		$oMi = $this->module_info;
		$mskin = $oMi->mskin;
		$module_path = _XE_PATH_ . 'modules/'.$oMi->module;
		$tpl_path = sprintf('%s/skins/%s', $module_path, $mskin);
		if (!is_dir($tpl_path)) {
			Context::set('XE_VALIDATOR_MESSAGE_TYPE', 'error');
			Context::set('XE_VALIDATOR_MESSAGE', Context::getLang('msg_skin_does_not_exist'));
		}
		$cmModule = &getModel('module');
		$skin_info = $cmModule->loadSkinInfo($module_path, $mskin);
		$skin_vars = $cmModule->getModuleMobileSkinVars($this->module_srl);
		Context::set('mid', $oMi->mid);
		Context::set('skin_info', $skin_info);
		Context::set('skin_vars', $skin_vars);
		$this->setTemplateFile('skin');
	}

	function dispBeluxeAdminGrantInfo() {
		$cmAdmModule = &getAdminModel('module');
		$grant_content = $cmAdmModule->getModuleGrantHTML($this->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);
		$this->setTemplateFile('grant');
	}

	function dispBeluxeAdminAdditionSetting() {
		$content = '';
		$out = ModuleHandler::triggerCall('module.dispAdditionSetup', 'before', $content);
		$out = ModuleHandler::triggerCall('module.dispAdditionSetup', 'after', $content);
		Context::set('setup_content', $content);
		$this->setTemplateFile('addition');
	}

	function dispBeluxeAdminColumnInfo() {
		$cmThis = &getModel(__XEFM_NAME__);
		$lst_cfg = $cmThis->getColumnInfo($this->module_srl);
		Context::set('column_info', $lst_cfg);
		Context::loadJavascriptPlugin('ui.colorpicker');
		$this->setTemplateFile('column');
	}

	function dispBeluxeAdminExtraKeys() {
		$cmDocument = &getModel('document');
		$extra_keys = $cmDocument->getExtraKeys($this->module_srl);
		Context::set('extra_keys', $extra_keys);
		$this->setTemplateFile('extra.keys');
	}
}
