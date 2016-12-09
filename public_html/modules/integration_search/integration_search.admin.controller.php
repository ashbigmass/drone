<?php
class integration_searchAdminController extends integration_search
{
	function init() {
	}

	function procIntegration_searchAdminInsertConfig() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('integration_search');
		$args = new stdClass;
		$args->skin = Context::get('skin');
		$args->target = Context::get('target');
		$args->target_module_srl = Context::get('target_module_srl');
		if(!$args->target_module_srl) $args->target_module_srl = '';
		$args->skin_vars = $config->skin_vars;
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('integration_search',$args);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispIntegration_searchAdminContent');
		return $this->setRedirectUrl($returnUrl, $output);
	}

	function procIntegration_searchAdminInsertSkin() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('integration_search');
		$args = new stdClass;
		$args->skin = $config->skin;
		$args->target_module_srl = $config->target_module_srl;
		$skin_info = $oModuleModel->loadSkinInfo($this->module_path, $config->skin);
		$obj = Context::getRequestVars();
		unset($obj->act);
		unset($obj->module_srl);
		unset($obj->page);
		if($skin_info->extra_vars) {
			foreach($skin_info->extra_vars as $vars) {
				if($vars->type!='image') continue;
				$image_obj = $obj->{$vars->name};
				$del_var = $obj->{"del_".$vars->name};
				unset($obj->{"del_".$vars->name});
				if($del_var == 'Y') {
					FileHandler::removeFile($module_info->{$vars->name});
					continue;
				}
				if(!$image_obj['tmp_name']) {
					$obj->{$vars->name} = $module_info->{$vars->name};
					continue;
				}
				if(!is_uploaded_file($image_obj['tmp_name']) || !checkUploadedFile($image_obj['tmp_name'])) {
					unset($obj->{$vars->name});
					continue;
				}
				if(!preg_match("/\.(jpg|jpeg|gif|png)$/i", $image_obj['name'])) {
					unset($obj->{$vars->name});
					continue;
				}
				$path = sprintf("./files/attach/images/%s/", $module_srl);
				if(!FileHandler::makeDir($path)) return false;
				$filename = $path.$image_obj['name'];
				if(!move_uploaded_file($image_obj['tmp_name'], $filename)) {
					unset($obj->{$vars->name});
					continue;
				}
				unset($obj->{$vars->name});
				$obj->{$vars->name} = $filename;
			}
		}
		$args->skin_vars = serialize($obj);
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('integration_search',$args);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispIntegration_searchAdminSkinInfo');
		return $this->setRedirectUrl($returnUrl, $output);
	}
}
