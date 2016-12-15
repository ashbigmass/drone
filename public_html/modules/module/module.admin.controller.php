<?php
class moduleAdminController extends module
{
	function init() {
	}

	function procModuleAdminInsertCategory() {
		$args = new stdClass();
		$args->title = Context::get('title');
		$output = executeQuery('module.insertModuleCategory', $args);
		if(!$output->toBool()) return $output;
		$this->setMessage("success_registed");
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminCategory');
		$this->setRedirectUrl($returnUrl);
	}

	function procModuleAdminUpdateCategory() {
		$output = $this->doUpdateModuleCategory();
		if(!$output->toBool()) return $output;
		$this->setMessage('success_updated');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminCategory');
		$this->setRedirectUrl($returnUrl);
	}

	function procModuleAdminDeleteCategory() {
		$output = $this->doDeleteModuleCategory();
		if(!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminCategory');
		$this->setRedirectUrl($returnUrl);
	}

	function doUpdateModuleCategory() {
		$args = new stdClass();
		$args->title = Context::get('title');
		$args->module_category_srl = Context::get('module_category_srl');
		return executeQuery('module.updateModuleCategory', $args);
	}

	function doDeleteModuleCategory() {
		$args = new stdClass;
		$args->module_category_srl = Context::get('module_category_srl');
		return executeQuery('module.deleteModuleCategory', $args);
	}

	function procModuleAdminCopyModule($args = NULL) {
		$isProc = false;
		if(!$args) {
			$isProc = true;
			$module_srl = Context::get('module_srl');
			$args = Context::getRequestVars();
		} else {
			$module_srl = $args->module_srl;
		}
		if(!$module_srl) return $this->_returnByProc($isProc);
		$clones = array();
		for($i=1;$i<=10;$i++) {
			$mid = trim($args->{"mid_".$i});
			if(!$mid) continue;
			if(!preg_match("/^[a-zA-Z]([a-zA-Z0-9_]*)$/i", $mid)) return new Object(-1, 'msg_limit_mid');
			$browser_title = $args->{"browser_title_".$i};
			if(!$mid) continue;
			if($mid && !$browser_title) $browser_title = $mid;
			$clones[$mid] = $browser_title;
		}
		if(count($clones) < 1) return $this->_returnByProc($isProc);
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$columnList = array('module', 'module_category_srl', 'layout_srl', 'use_mobile', 'mlayout_srl', 'menu_srl', 'site_srl', 'skin', 'mskin', 'description', 'mcontent', 'open_rss', 'header_text', 'footer_text', 'regdate');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		$module_args = new stdClass();
		$module_args->module_srl = $module_srl;
		$output = executeQueryArray('module.getModuleGrants', $module_args);
		$grant = array();
		if($output->data) foreach($output->data as $val) $grant[$val->name][] = $val->group_srl;
		$extra_args = new stdClass();
		$extra_args->module_srl = $module_srl;
		$extra_output = executeQueryArray('module.getModuleExtraVars', $extra_args);
		$extra_vars = new stdClass();
		if($extra_output->toBool() && is_array($extra_output->data)) {
			foreach($extra_output->data as $info) $extra_vars->{$info->name} = $info->value;
		}
		$tmpModuleSkinVars = $oModuleModel->getModuleSkinVars($module_srl);
		$tmpModuleMobileSkinVars = $oModuleModel->getModuleMobileSkinVars($module_srl);
		if($tmpModuleSkinVars) {
			foreach($tmpModuleSkinVars as $key=>$value) $moduleSkinVars->{$key} = $value->value;
		}
		if($tmpModuleMobileSkinVars) {
			foreach($tmpModuleMobileSkinVars as $key=>$value) $moduleMobileSkinVars->{$key} = $value->value;
		}
		$oDB = &DB::getInstance();
		$oDB->begin();
		$triggerObj = new stdClass();
		$triggerObj->originModuleSrl = $module_srl;
		$triggerObj->moduleSrlList = array();
		$errorLog = array();
		foreach($clones as $mid => $browser_title) {
			$clone_args = new stdClass;
			$clone_args = clone $module_info;
			$clone_args->module_srl = null;
			$clone_args->content = null;
			$clone_args->mid = $mid;
			$clone_args->browser_title = $browser_title;
			$clone_args->is_default = 'N';
			$clone_args->isMenuCreate = $args->isMenuCreate;
			unset($clone_args->menu_srl);
			$output = $oModuleController->insertModule($clone_args);
			if(!$output->toBool()) {
				$errorLog[] = $mid . ' : '. $output->message;
				continue;
			}
			$module_srl = $output->get('module_srl');
			if($module_info->module == 'page' && $extra_vars->page_type == 'ARTICLE') {
				$oDocumentAdminController = getAdminController('document');
				$copyOutput = $oDocumentAdminController->copyDocumentModule(array($extra_vars->document_srl), $module_srl, $module_info->category_srl);
				$document_srls = $copyOutput->get('copied_srls');
				if($document_srls && count($document_srls) > 0) $extra_vars->document_srl = array_pop($document_srls);
				if($extra_vars->mdocument_srl) {
					$copyOutput = $oDocumentAdminController->copyDocumentModule(array($extra_vars->mdocument_srl), $module_srl, $module_info->category_srl);
					$copiedSrls = $copyOutput->get('copied_srls');
					if($copiedSrls && count($copiedSrls) > 0) $extra_vars->mdocument_srl = array_pop($copiedSrls);
				}
			}
			if(count($grant) > 0) $oModuleController->insertModuleGrants($module_srl, $grant);
			if($extra_vars) $oModuleController->insertModuleExtraVars($module_srl, $extra_vars);
			if($moduleSkinVars) $oModuleController->insertModuleSkinVars($module_srl, $moduleSkinVars);
			if($moduleMobileSkinVars) $oModuleController->insertModuleMobileSkinVars($module_srl, $moduleMobileSkinVars);
			$triggerObj->moduleSrlList[] = $module_srl;
		}
		$output = ModuleHandler::triggerCall('module.procModuleAdminCopyModule', 'after', $triggerObj);
		$oDB->commit();
		if(count($errorLog) > 0) {
			$message = implode('\n', $errorLog);
			$this->setMessage($message);
		} else {
			$message = $lang->success_registed;
			$this->setMessage('success_registed');
		}
		if($isProc) {
			if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
				global $lang;
				htmlHeader();
				alertScript($message);
				reload(true);
				closePopupScript();
				htmlFooter();
				Context::close();
				exit;
			}
		}

		return $module_srl;
	}

	private function _returnByProc($isProc, $msg='msg_invalid_request') {
		if(!$isProc) return;
		else {
			return new Object(-1, $msg);
		}
	}

	function procModuleAdminInsertGrant() {
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');
		$module_srl = Context::get('module_srl');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		if(!$module_info) return new Object(-1,'msg_invalid_request');
		$oModuleController->deleteAdminId($module_srl);
		$admin_member = Context::get('admin_member');
		if($admin_member) {
			$admin_members = explode(',',$admin_member);
			foreach($admin_members as $admin_id) {
				$admin_id = trim($admin_id);
				if(!$admin_id) continue;
				$oModuleController->insertAdminId($module_srl, $admin_id);
			}
		}
		$xml_info = $oModuleModel->getModuleActionXML($module_info->module);
		$grant_list = $xml_info->grant;
		$grant_list->access = new stdClass();
		$grant_list->access->default = 'guest';
		$grant_list->manager = new stdClass();
		$grant_list->manager->default = 'manager';
		$grant = new stdClass();
		foreach($grant_list as $grant_name => $grant_info) {
			$default = Context::get($grant_name.'_default');
			$grant->{$grant_name} = array();
			if(strlen($default)) {
				$grant->{$grant_name}[] = $default;
				continue;
			} else {
				$group_srls = Context::get($grant_name);
				if($group_srls) {
					if(strpos($group_srls,'|@|')!==false) $group_srls = explode('|@|',$group_srls);
					elseif(strpos($group_srls,',')!==false) $group_srls = explode(',',$group_srls);
					else $group_srls = array($group_srls);
					$grant->{$grant_name} = $group_srls;
				}
				continue;
			}
			$grant->{$group_srls} = array();
		}
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('module.deleteModuleGrants', $args);
		if(!$output->toBool()) return $output;
		foreach($grant as $grant_name => $group_srls) {
			foreach($group_srls as $val) {
				$args = new stdClass();
				$args->module_srl = $module_srl;
				$args->name = $grant_name;
				$args->group_srl = $val;
				$output = executeQuery('module.insertModuleGrant', $args);
				if(!$output->toBool()) return $output;
			}
		}
		$this->setMessage('success_registed');
	}

	function procModuleAdminUpdateSkinInfo() {
		$module_srl = Context::get('module_srl');
		$mode = Context::get('_mode');
		$mode = $mode === 'P' ? 'P' : 'M';
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module', 'skin', 'mskin', 'is_skin_fix', 'is_mskin_fix');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		if($module_info->module_srl) {
			if($mode === 'M') {
				if($module_info->is_mskin_fix == 'Y') $skin = $module_info->mskin;
				else $skin = $oModuleModel->getModuleDefaultSkin($module_info->module, 'M');
			} else {
				if($module_info->is_skin_fix == 'Y') $skin = $module_info->skin;
				else $skin = $oModuleModel->getModuleDefaultSkin($module_info->module, 'P');
			}
			$module_path = _XE_PATH_ . 'modules/'.$module_info->module;
			if($mode === 'M') {
				$skin_info = $oModuleModel->loadSkinInfo($module_path, $skin, 'm.skins');
				$skin_vars = $oModuleModel->getModuleMobileSkinVars($module_srl);
			} else {
				$skin_info = $oModuleModel->loadSkinInfo($module_path, $skin);
				$skin_vars = $oModuleModel->getModuleSkinVars($module_srl);
			}
			$obj = Context::getRequestVars();
			unset($obj->act);
			unset($obj->error_return_url);
			unset($obj->module_srl);
			unset($obj->page);
			unset($obj->mid);
			unset($obj->module);
			unset($obj->_mode);
			if($skin_info->extra_vars) {
				foreach($skin_info->extra_vars as $vars) {
					if($vars->type!='image') continue;
					$image_obj = $obj->{$vars->name};
					$del_var = $obj->{"del_".$vars->name};
					unset($obj->{"del_".$vars->name});
					if($del_var == 'Y') {
						FileHandler::removeFile($skin_vars[$vars->name]->value);
						continue;
					}
					if(!$image_obj['tmp_name']) {
						$obj->{$vars->name} = $skin_vars[$vars->name]->value;
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
					FileHandler::removeFile($skin_vars[$vars->name]->value);
					unset($obj->{$vars->name});
					$obj->{$vars->name} = $filename;
				}
			}
			$oModuleController = getController('module');
			if($mode === 'M') $output = $oModuleController->insertModuleMobileSkinVars($module_srl, $obj);
			else $output = $oModuleController->insertModuleSkinVars($module_srl, $obj);
			if(!$output->toBool()) return $output;
		}
		$this->setMessage('success_saved');
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	function procModuleAdminModuleSetup() {
		$vars = Context::getRequestVars();
		if(!$vars->module_srls) return new Object(-1,'msg_invalid_request');
		$module_srls = explode(',',$vars->module_srls);
		if(count($module_srls) < 1) return new Object(-1,'msg_invalid_request');
		$oModuleModel = getModel('module');
		$oModuleController= getController('module');
		$columnList = array('module_srl', 'module', 'menu_srl', 'site_srl', 'mid', 'browser_title', 'is_default', 'content', 'mcontent', 'open_rss', 'regdate');
		$updateList = array('module_category_srl','layout_srl','skin','mlayout_srl','mskin','description','header_text','footer_text', 'use_mobile');
		foreach($updateList as $key=>$val) {
			if(!strlen($vars->{$val})) {
				unset($updateList[$key]);
				$columnList[] = $val;
			}
		}
		foreach($module_srls as $module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
			foreach($updateList as $val) $module_info->{$val} = $vars->{$val};
			$output = $oModuleController->updateModule($module_info);
		}
		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			if(Context::get('success_return_url')) {
				$this->setRedirectUrl(Context::get('success_return_url'));
			} else {
				global $lang;
				htmlHeader();
				alertScript($lang->success_registed);
				closePopupScript();
				htmlFooter();
				Context::close();
				exit;
			}
		}
	}

	function procModuleAdminModuleGrantSetup() {
		$module_srls = Context::get('module_srls');
		if(!$module_srls) return new Object(-1,'msg_invalid_request');
		$modules = explode(',',$module_srls);
		if(count($modules) < 1) return new Object(-1,'msg_invalid_request');
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($modules[0], $columnList);
		$xml_info = $oModuleModel->getModuleActionXml($module_info->module);
		$grant_list = $xml_info->grant;
		$grant_list->access = new stdClass();
		$grant_list->access->default = 'guest';
		$grant_list->manager = new stdClass();
		$grant_list->manager->default = 'manager';
		$grant = new stdClass;
		foreach($grant_list as $grant_name => $grant_info) {
			$default = Context::get($grant_name.'_default');
			$grant->{$grant_name} = array();
			if(strlen($default)) {
				$grant->{$grant_name}[] = $default;
				continue;
			} else {
				$group_srls = Context::get($grant_name);
				if($group_srls) {
					if(!is_array($group_srls)) {
						if(strpos($group_srls,'|@|')!==false) $group_srls = explode('|@|',$group_srls);
						elseif(strpos($group_srls,',')!==false) $group_srls = explode(',',$group_srls);
						else $group_srls = array($group_srls);
					}
					$grant->{$grant_name} = $group_srls;
				}
				continue;
			}
			$grant->{$group_srls} = array();
		}
		foreach($modules as $module_srl) {
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQuery('module.deleteModuleGrants', $args);
			if(!$output->toBool()) continue;
			foreach($grant as $grant_name => $group_srls) {
				foreach($group_srls as $val) {
					$args = new stdClass();
					$args->module_srl = $module_srl;
					$args->name = $grant_name;
					$args->group_srl = $val;
					$output = executeQuery('module.insertModuleGrant', $args);
					if(!$output->toBool()) return $output;
				}
			}
		}
		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			if(Context::get('success_return_url')) {
				$this->setRedirectUrl(Context::get('success_return_url'));
			} else {
				global $lang;
				htmlHeader();
				alertScript($lang->success_registed);
				closePopupScript();
				htmlFooter();
				Context::close();
				exit;
			}
		}
	}

	function procModuleAdminInsertLang() {
		$site_module_info = Context::get('site_module_info');
		$target = Context::get('target');
		$module = Context::get('module');
		$args = new stdClass();
		$args->site_srl = (int)$site_module_info->site_srl;
		$args->name = str_replace(' ','_',Context::get('lang_code'));
		$args->lang_name = str_replace(' ','_',Context::get('lang_name'));
		if(!empty($args->lang_name)) $args->name = $args->lang_name;
		if(empty($args->name)) $args->name = 'userLang'.date('YmdHis').''.sprintf('%03d', mt_rand(0, 100));
		if(!$args->name) return new Object(-1,'msg_invalid_request');
		$output = executeQueryArray('module.getLang', $args);
		if(!$output->toBool()) return $output;
		if($output->data) $output = executeQuery('module.deleteLang', $args);
		if(!$output->toBool()) return $output;
		$lang_supported = Context::get('lang_supported');
		foreach($lang_supported as $key => $val) {
			$args->lang_code = $key;
			$args->value = trim(Context::get($key));
			if(Context::getRequestMethod() == 'JSON' && version_compare(PHP_VERSION, "5.4.0", "<") && get_magic_quotes_gpc()) $args->value = stripslashes($args->value);
			if($args->value) {
				$output = executeQuery('module.insertLang', $args);
				if(!$output->toBool()) return $output;
			}
		}
		$this->makeCacheDefinedLangCode($args->site_srl);
		$this->add('name', $args->name);
		$this->setMessage("success_saved", 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', $module, 'target', $target, 'act', 'dispModuleAdminLangcode');
		$this->setRedirectUrl($returnUrl);
	}

	function procModuleAdminDeleteLang() {
		$site_module_info = Context::get('site_module_info');
		$args = new stdClass();
		$args->site_srl = (int)$site_module_info->site_srl;
		$args->name = str_replace(' ','_',Context::get('name'));
		$args->lang_name = str_replace(' ','_',Context::get('lang_name'));
		if(!empty($args->lang_name)) $args->name = $args->lang_name;
		if(!$args->name) return new Object(-1,'msg_invalid_request');
		$output = executeQuery('module.deleteLang', $args);
		if(!$output->toBool()) return $output;
		$this->makeCacheDefinedLangCode($args->site_srl);
		$this->setMessage("success_deleted", 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminLangcode');
		$this->setRedirectUrl($returnUrl);
	}

	function procModuleAdminGetList() {
		if(!Context::get('is_logged')) return new Object(-1, 'msg_not_permitted');
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');
		$site_keyword = Context::get('site_keyword');
		$site_srl = Context::get('site_srl');
		$vid = Context::get('vid');
		$args = new stdClass;
		$logged_info = Context::get('logged_info');
		$site_module_info = Context::get('site_module_info');
		if($site_keyword) $args->site_keyword = $site_keyword;
		if(!$site_srl) {
			if($logged_info->is_admin == 'Y' && !$site_keyword && !$vid) $args->site_srl = 0;
			else $args->site_srl = (int)$site_module_info->site_srl;
		} else $args->site_srl = $site_srl;
		$args->sort_index1 = 'sites.domain';
		$moduleCategorySrl = array();
		$output = executeQueryArray('module.getSiteModules', $args);
		$mid_list = array();
		if(count($output->data) > 0) {
			foreach($output->data as $val) {
				$module = trim($val->module);
				if(!$module) continue;
				$oModuleController->replaceDefinedLangCode($val->browser_title);
				$obj = new stdClass();
				$obj->module_srl = $val->module_srl;
				$obj->layout_srl = $val->layout_srl;
				$obj->browser_title = $val->browser_title;
				$obj->mid = $val->mid;
				$obj->module_category_srl = $val->module_category_srl;
				if($val->module_category_srl > 0) $moduleCategorySrl[] = $val->module_category_srl;
				$mid_list[$module]->list[$val->mid] = $obj;
			}
		}
		$moduleCategorySrl = array_unique($moduleCategorySrl);
		$output = $oModuleModel->getModuleCategories($moduleCategorySrl);
		$categoryNameList = array();
		if(is_array($output)) {
			foreach($output as $value) $categoryNameList[$value->module_category_srl] = $value->title;
		}
		$selected_module = Context::get('selected_module');
		if(count($mid_list) > 0) {
			foreach($mid_list as $module => $val) {
				if(!$selected_module) $selected_module = $module;
				$xml_info = $oModuleModel->getModuleInfoXml($module);
				if(!$xml_info) {
					unset($mid_list[$module]);
					continue;
				}
				$mid_list[$module]->title = $xml_info->title;
				if(is_array($val->list)) {
					foreach($val->list as $key=>$value) {
						if($value->module_category_srl > 0) {
							$categorySrl = $mid_list[$module]->list[$key]->module_category_srl;
							if(isset($categoryNameList[$categorySrl])) $mid_list[$module]->list[$key]->module_category_srl = $categoryNameList[$categorySrl];
						} else {
							$mid_list[$module]->list[$key]->module_category_srl = Context::getLang('none_category');
						}
					}
				}
			}
		}
		$security = new Security($mid_list);
		$security->encodeHTML('....browser_title');
		$this->add('module_list', $mid_list);
	}

	function makeCacheDefinedLangCode($site_srl = 0) {
		$args = new stdClass();
		if(!$site_srl) {
			$site_module_info = Context::get('site_module_info');
			$args->site_srl = (int)$site_module_info->site_srl;
		} else {
			$args->site_srl = $site_srl;
		}
		$output = executeQueryArray('module.getLang', $args);
		if(!$output->toBool() || !$output->data) return;
		$cache_path = _XE_PATH_.'files/cache/lang_defined/';
		FileHandler::makeDir($cache_path);
		$langMap = array();
		foreach($output->data as $val) $langMap[$val->lang_code][$val->name] = $val->value;
		$lang_supported = Context::get('lang_supported');
		$dbInfo = Context::getDBInfo();
		$defaultLang = $dbInfo->lang_type;
		if(!is_array($langMap[$defaultLang])) $langMap[$defaultLang] = array();
		foreach($lang_supported as $langCode => $langName) {
			if(!is_array($langMap[$langCode])) $langMap[$langCode] = array();
			$langMap[$langCode] += $langMap[$defaultLang];
			foreach($lang_supported as $targetLangCode => $targetLangName) {
				if($langCode == $targetLangCode || $langCode == $defaultLang) continue;
				if(!is_array($langMap[$targetLangCode])) $langMap[$targetLangCode] = array();
				$langMap[$langCode] += $langMap[$targetLangCode];
			}
			$buff = array("<?php if(!defined('__XE__')) exit();");
			foreach($langMap[$langCode] as $code => $value) $buff[] = sprintf('$lang[\'%s\'] = \'%s\';', $code, addcslashes($value, "'"));
			if (!@file_put_contents(sprintf('%s/%d.%s.php', $cache_path, $args->site_srl, $langCode), join(PHP_EOL, $buff), LOCK_EX)) return;
		}
	}

	public function procModuleAdminSetDesignInfo() {
		$moduleSrl = Context::get('target_module_srl');
		$mid = Context::get('target_mid');
		$skinType = Context::get('skin_type');
		$skinType = ($skinType == 'M') ? 'M' : 'P';
		$layoutSrl = Context::get('layout_srl');
		$isSkinFix = Context::get('is_skin_fix');
		if($isSkinFix) $isSkinFix = ($isSkinFix == 'N') ? 'N' : 'Y';
		$skinName = Context::get('skin_name');
		$skinVars = Context::get('skin_vars');
		$output = $this->setDesignInfo($moduleSrl, $mid, $skinType, $layoutSrl, $isSkinFix, $skinName, $skinVars);
		return $output;
	}

	public function setDesignInfo($moduleSrl = 0, $mid = '', $skinType = 'P', $layoutSrl = 0, $isSkinFix = 'Y', $skinName = '', $skinVars = NULL) {
		if(!$moduleSrl && !$mid) return $this->stop(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		if($mid) $moduleInfo = $oModuleModel->getModuleInfoByMid($mid);
		else $moduleInfo = $oModuleModel->getModuleInfoByModuleSrl($moduleSrl);
		if(!$moduleInfo) return $this->stop(-1, 'msg_module_not_exists');
		$skinTargetValue = ($skinType == 'M') ? 'mskin' : 'skin';
		$layoutTargetValue = ($skinType == 'M') ? 'mlayout_srl' : 'layout_srl';
		$skinFixTargetValue = ($skinType == 'M') ? 'is_mskin_fix' : 'is_skin_fix';
		if(strlen($layoutSrl)) $moduleInfo->{$layoutTargetValue} = $layoutSrl;
		if(strlen($isSkinFix)) $moduleInfo->{$skinFixTargetValue} = $isSkinFix;
		if($isSkinFix == 'Y') {
			$moduleInfo->{$skinTargetValue} = $skinName;
			$skinVars = json_decode($skinVars);
			if(is_array($skinVars)) {
				foreach($skinVars as $key => $val) {
					if(empty($val)) continue;
					$moduleInfo->{$key} = $val;
				}
			}
		}
		$oModuleController = getController('module');
		$output = $oModuleController->updateModule($moduleInfo);
		return $output;
	}

	public function procModuleAdminUpdateUseMobile() {
		$menuItemSrl = Context::get('menu_item_srl');
		$useMobile = Context::get('use_mobile');
		if(!$menuItemSrl) return $this->stop(-1, 'msg_invalid_request');
		$oModuleModel = getModel('module');
		$moduleInfo = $oModuleModel->getModuleInfoByMenuItemSrl($menuItemSrl);
		unset($moduleInfo->designSettings);
		$useMobile = $useMobile != 'Y' ? 'N' : 'Y';
		$moduleInfo->use_mobile = $useMobile;
		$oModuleController = getController('module');
		$output = $oModuleController->updateModule($moduleInfo);
		return $output;
	}
}
