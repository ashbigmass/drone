<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */

/**
 * @class ModuleObject
 * @author NAVER (developers@xpressengine.com)
 * base class of ModuleHandler
 * */
class ModuleObject extends Object {

	var $mid = NULL; ///< string to represent run-time instance of Module (XE Module)
	var $module = NULL; ///< Class name of Xe Module that is identified by mid
	var $module_srl = NULL; ///< integer value to represent a run-time instance of Module (XE Module)
	var $module_info = NULL; ///< an object containing the module information
	var $origin_module_info = NULL;
	var $xml_info = NULL; ///< an object containing the module description extracted from XML file
	var $module_path = NULL; ///< a path to directory where module source code resides
	var $act = NULL; ///< a string value to contain the action name
	var $template_path = NULL; ///< a path of directory where template files reside
	var $template_file = NULL; ///< name of template file
	var $layout_path = ''; ///< a path of directory where layout files reside
	var $layout_file = ''; ///< name of layout file
	var $edited_layout_file = ''; ///< name of temporary layout files that is modified in an admin mode
	var $stop_proc = FALSE; ///< a flag to indicating whether to stop the execution of code.
	var $module_config = NULL;
	var $ajaxRequestMethod = array('XMLRPC', 'JSON');
	var $gzhandler_enable = TRUE;

	function setModule($module) {
		$this->module = $module;
	}

	function setModulePath($path) {
		if(substr_compare($path, '/', -1) !== 0) {
			$path.='/';
		}
		$this->module_path = $path;
	}

	function setRedirectUrl($url = './', $output = NULL) 	{
		$ajaxRequestMethod = array_flip($this->ajaxRequestMethod);
		if(!isset($ajaxRequestMethod[Context::getRequestMethod()])) {
			$this->add('redirect_url', $url);
		}
		if($output !== NULL && is_object($output)) return $output;
	}

	function getRedirectUrl() {
		return $this->get('redirect_url');
	}

	function setMessage($message = 'success', $type = NULL) {
		parent::setMessage($message);
		$this->setMessageType($type);
	}

	function setMessageType($type) {
		$this->add('message_type', $type);
	}

	function getMessageType() {
		$type = $this->get('message_type');
		$typeList = array('error' => 1, 'info' => 1, 'update' => 1);
		if(!isset($typeList[$type])) $type = $this->getError() ? 'error' : 'info';
		return $type;
	}

	function setModuleInfo($module_info, $xml_info) {
		$this->mid = $module_info->mid;
		$this->module_srl = $module_info->module_srl;
		$this->module_info = $module_info;
		$this->origin_module_info = $module_info;
		$this->xml_info = $xml_info;
		$this->skin_vars = $module_info->skin_vars;
		$is_logged = Context::get('is_logged');
		$logged_info = Context::get('logged_info');
		$oModuleModel = getModel('module');
		$module_srl = Context::get('module_srl');
		if(!$module_info->mid && !is_array($module_srl) && preg_match('/^([0-9]+)$/', $module_srl)) {
			$request_module = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if($request_module->module_srl == $module_srl) $grant = $oModuleModel->getGrant($request_module, $logged_info);
		} else {
			$grant = $oModuleModel->getGrant($module_info, $logged_info, $xml_info);
			if(substr_count($this->act, 'Member') || substr_count($this->act, 'Communication')) $grant->access = 1;
		}

		if(!$grant->manager) {
			$permission_target = $xml_info->permission->{$this->act};
			if(!$permission_target && substr_count($this->act, 'Admin')) $permission_target = 'manager';
			switch($permission_target) {
				case 'root' :
				case 'manager' :
					$this->stop('msg_is_not_administrator');
					return;
				case 'member' :
					if(!$is_logged) {
						$this->stop('msg_not_permitted_act');
						return;
					}
					break;
			}
		}
		$this->grant = $grant;
		Context::set('grant', $grant);
		$this->module_config = $oModuleModel->getModuleConfig($this->module, $module_info->site_srl);
		if(method_exists($this, 'init'))$this->init();
	}

	function stop($msg_code) {
		$this->stop_proc = TRUE;
		$this->setError(-1);
		$this->setMessage($msg_code);
		$type = Mobile::isFromMobilePhone() ? 'mobile' : 'view';
		$oMessageObject = ModuleHandler::getModuleInstance('message', $type);
		$oMessageObject->setError(-1);
		$oMessageObject->setMessage($msg_code);
		$oMessageObject->dispMessage();
		$this->setTemplatePath($oMessageObject->getTemplatePath());
		$this->setTemplateFile($oMessageObject->getTemplateFile());
		return $this;
	}

	function setTemplateFile($filename) {
		if(isset($filename) && substr_compare($filename, '.html', -5) !== 0) $filename .= '.html';
		$this->template_file = $filename;
	}

	function getTemplateFile() {
		return $this->template_file;
	}

	function setTemplatePath($path) {
		if(!$path) return;
		if((strlen($path) >= 1 && substr_compare($path, '/', 0, 1) !== 0) && (strlen($path) >= 2 && substr_compare($path, './', 0, 2) !== 0)) $path = './' . $path;
		if(substr_compare($path, '/', -1) !== 0) $path .= '/';
		$this->template_path = $path;
	}

	function getTemplatePath() {
		return $this->template_path;
	}

	function setEditedLayoutFile($filename) {
		if(!$filename) return;
		if(substr_compare($filename, '.html', -5) !== 0) $filename .= '.html';
		$this->edited_layout_file = $filename;
	}

	function proc() {
		if($this->stop_proc) {
			debugPrint($this->message, 'ERROR');
			return FALSE;
		}
		$triggerOutput = ModuleHandler::triggerCall('moduleObject.proc', 'before', $this);
		if(!$triggerOutput->toBool()) {
			$this->setError($triggerOutput->getError());
			$this->setMessage($triggerOutput->getMessage());
			return FALSE;
		}
		$called_position = 'before_module_proc';
		$oAddonController = getController('addon');
		$addon_file = $oAddonController->getCacheFilePath(Mobile::isFromMobilePhone() ? "mobile" : "pc");
		if(FileHandler::exists($addon_file)) include($addon_file);

		if(isset($this->xml_info->action->{$this->act}) && method_exists($this, $this->act)) {
			if($this->module_srl && !$this->grant->access) {
				$this->stop("msg_not_permitted_act");
				return FALSE;
			}
			$is_default_skin = ((!Mobile::isFromMobilePhone() && $this->module_info->is_skin_fix == 'N') || (Mobile::isFromMobilePhone() && $this->module_info->is_mskin_fix == 'N'));
			$usedSkinModule = !($this->module == 'page' && ($this->module_info->page_type == 'OUTSIDE' || $this->module_info->page_type == 'WIDGET'));
			if($usedSkinModule && $is_default_skin && $this->module != 'admin' && strpos($this->act, 'Admin') === false && $this->module == $this->module_info->module) {
				$dir = (Mobile::isFromMobilePhone()) ? 'm.skins' : 'skins';
				$valueName = (Mobile::isFromMobilePhone()) ? 'mskin' : 'skin';
				$oModuleModel = getModel('module');
				$skinType = (Mobile::isFromMobilePhone()) ? 'M' : 'P';
				$skinName = $oModuleModel->getModuleDefaultSkin($this->module, $skinType);
				if($this->module == 'page') {
					$this->module_info->{$valueName} = $skinName;
				} else {
					$isTemplatPath = (strpos($this->getTemplatePath(), '/tpl/') !== FALSE);
					if(!$isTemplatPath) {
						$this->setTemplatePath(sprintf('%s%s/%s/', $this->module_path, $dir, $skinName));
					}
				}
			}

			$oModuleModel = getModel('module');
			$oModuleModel->syncSkinInfoToModuleInfo($this->module_info);
			Context::set('module_info', $this->module_info);
			$output = $this->{$this->act}();
		} else {
			return FALSE;
		}
		$triggerOutput = ModuleHandler::triggerCall('moduleObject.proc', 'after', $this);
		if(!$triggerOutput->toBool()) {
			$this->setError($triggerOutput->getError());
			$this->setMessage($triggerOutput->getMessage());
			return FALSE;
		}
		$called_position = 'after_module_proc';
		$oAddonController = getController('addon');
		$addon_file = $oAddonController->getCacheFilePath(Mobile::isFromMobilePhone() ? "mobile" : "pc");
		if(FileHandler::exists($addon_file)) include($addon_file);

		if(is_a($output, 'Object') || is_subclass_of($output, 'Object')) {
			$this->setError($output->getError());
			$this->setMessage($output->getMessage());

			if(!$output->toBool()) return FALSE;
		}
		if($this->module_info->module_type == 'view') {
			if(Context::getResponseMethod() == 'XMLRPC' || Context::getResponseMethod() == 'JSON') {
				$oAPI = getAPI($this->module_info->module, 'api');
				if(method_exists($oAPI, $this->act)) $oAPI->{$this->act}($this);
			}
		}
		return TRUE;
	}

}
