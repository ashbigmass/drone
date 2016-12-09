<?php
class editorController extends editor
{
	function init() {
	}

	function procEditorSaveDoc() {
		$this->deleteSavedDoc(false);
		$args = new stdClass;
		$args->document_srl = Context::get('document_srl');
		$args->content = Context::get('content');
		$args->title = Context::get('title');
		$output = $this->doSaveDoc($args);
		$this->setMessage('msg_auto_saved');
	}

	function procEditorRemoveSavedDoc() {
		$oEditorController = getController('editor');
		$oEditorController->deleteSavedDoc(true);
	}

	function procEditorCall() {
		$component = Context::get('component');
		$method = Context::get('method');
		if(!$component) return new Object(-1, sprintf(Context::getLang('msg_component_is_not_founded'), $component));
		$oEditorModel = getModel('editor');
		$oComponent = &$oEditorModel->getComponentObject($component);
		if(!$oComponent->toBool()) return $oComponent;
		if(!method_exists($oComponent, $method)) return new Object(-1, sprintf(Context::getLang('msg_component_is_not_founded'), $component));
		if(method_exists($oComponent, $method)) $output = $oComponent->{$method}();
		else return new Object(-1,sprintf('%s method is not exists', $method));
		if((is_a($output, 'Object') || is_subclass_of($output, 'Object')) && !$output->toBool()) return $output;
		$this->setError($oComponent->getError());
		$this->setMessage($oComponent->getMessage());
		$vars = $oComponent->getVariables();
		if(count($vars)) {
			foreach($vars as $key => $val) $this->add($key, $val);
		}
	}

	function procEditorInsertModuleConfig() {
		$module_srl = Context::get('target_module_srl');
		if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
		else $module_srl = array($module_srl);
		$editor_config = new stdClass;
		$editor_config->editor_skin = Context::get('editor_skin');
		$editor_config->comment_editor_skin = Context::get('comment_editor_skin');
		$editor_config->content_style = Context::get('content_style');
		$editor_config->comment_content_style = Context::get('comment_content_style');
		$editor_config->content_font = Context::get('content_font');
		if($editor_config->content_font) {
			$font_list = array();
			$fonts = explode(',',$editor_config->content_font);
			for($i=0,$c=count($fonts);$i<$c;$i++) {
				$font = trim(str_replace(array('"','\''),'',$fonts[$i]));
				if(!$font) continue;
				$font_list[] = $font;
			}
			if(count($font_list)) $editor_config->content_font = '"'.implode('","',$font_list).'"';
		}
		$editor_config->content_font_size = Context::get('content_font_size');
		$editor_config->sel_editor_colorset = Context::get('sel_editor_colorset');
		$editor_config->sel_comment_editor_colorset = Context::get('sel_comment_editor_colorset');
		$grants = array('enable_html_grant','enable_comment_html_grant','upload_file_grant','comment_upload_file_grant','enable_default_component_grant','enable_comment_default_component_grant','enable_component_grant','enable_comment_component_grant');
		foreach($grants as $key) {
			$grant = Context::get($key);
			if(!$grant) $editor_config->{$key} = array();
			else if(is_array($grant)) $editor_config->{$key} = $grant;
			else $editor_config->{$key} = explode('|@|', $grant);
		}
		$editor_config->editor_height = (int)Context::get('editor_height');
		$editor_config->comment_editor_height = (int)Context::get('comment_editor_height');
		$editor_config->enable_autosave = Context::get('enable_autosave');
		if($editor_config->enable_autosave != 'Y') $editor_config->enable_autosave = 'N';
		$oModuleController = getController('module');
		for($i=0;$i<count($module_srl);$i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			$oModuleController->insertModulePartConfig('editor',$srl,$editor_config);
		}
		$this->setError(-1);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispBoardAdminContent');
		$this->setRedirectUrl($returnUrl);
	}

	function triggerEditorComponentCompile(&$content) {
		if(Context::getResponseMethod()!='HTML') return new Object();
		$module_info = Context::get('module_info');
		$module_srl = $module_info->module_srl;
		if($module_srl) {
			$oEditorModel = getModel('editor');
			$editor_config = $oEditorModel->getEditorConfig($module_srl);
			$content_style = $editor_config->content_style;
			if($content_style) {
				$path = _XE_PATH_ . 'modules/editor/styles/'.$content_style.'/';
				if(is_dir($path) && file_exists($path . 'style.ini')) {
					$ini = file($path.'style.ini');
					for($i = 0, $c = count($ini); $i < $c; $i++) {
						$file = trim($ini[$i]);
						if(!$file) continue;
						if(substr_compare($file, '.css', -4) === 0) Context::addCSSFile('./modules/editor/styles/'.$content_style.'/'.$file, false);
						elseif(substr_compare($file, '.js', -3) === 0) Context::addJsFile('./modules/editor/styles/'.$content_style.'/'.$file, false);
					}
				}
			}
			$content_font = $editor_config->content_font;
			$content_font_size = $editor_config->content_font_size;
			if($content_font || $content_font_size) {
				$buff = array();
				$buff[] = '<style> .xe_content { ';
				if($content_font) $buff[] = 'font-family:'.$content_font.';';
				if($content_font_size) $buff[] = 'font-size:'.$content_font_size.';';
				$buff[] = ' }</style>';
				Context::addHtmlHeader(implode('', $buff));
			}
		}
		$content = $this->transComponent($content);
		return new Object();
	}

	function transComponent($content) {
		$content = preg_replace_callback('!<(?:(div)|img)([^>]*)editor_component=([^>]*)>(?(1)(.*?)</div>)!is', array($this,'transEditorComponent'), $content);
		return $content;
	}

	function transEditorComponent($match) {
		$script = " {$match[2]} editor_component={$match[3]}";
		$script = preg_replace('/([\w:-]+)\s*=(?:\s*(["\']))?((?(2).*?|[^ ]+))\2/i', '\1="\3"', $script);
		preg_match_all('/([a-z0-9_-]+)="([^"]+)"/is', $script, $m);
		$xml_obj = new stdClass;
		$xml_obj->attrs = new stdClass;
		for($i=0,$c=count($m[0]);$i<$c;$i++) {
			if(!isset($xml_obj->attrs)) $xml_obj->attrs = new stdClass;
			$xml_obj->attrs->{$m[1][$i]} = $m[2][$i];
		}
		$xml_obj->body = $match[4];
		if(!$xml_obj->attrs->editor_component) return $match[0];
		$oEditorModel = getModel('editor');
		$oComponent = &$oEditorModel->getComponentObject($xml_obj->attrs->editor_component, 0);
		if(!is_object($oComponent)||!method_exists($oComponent, 'transHTML')) return $match[0];
		return $oComponent->transHTML($xml_obj);
	}

	function doSaveDoc($args) {
		if(!$args->document_srl) $args->document_srl = $_SESSION['upload_info'][$editor_sequence]->upload_target_srl;
		if(Context::get('is_logged')) {
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
		} else {
			$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		}
		if(!$args->module_srl) $args->module_srl = Context::get('module_srl');
		if(!$args->module_srl) {
			$current_module_info = Context::get('current_module_info');
			$args->module_srl = $current_module_info->module_srl;
		}
		return executeQuery('editor.insertSavedDoc', $args);
	}

	function procEditorLoadSavedDocument() {
		$editor_sequence = Context::get('editor_sequence');
		$primary_key = Context::get('primary_key');
		$oEditorModel = getModel('editor');
		$oFileController = getController('file');
		$saved_doc = $oEditorModel->getSavedDoc(null);
		$oFileController->setUploadInfo($editor_sequence, $saved_doc->document_srl);
		$vars = $this->getVariables();
		$this->add("editor_sequence", $editor_sequence);
		$this->add("key", $primary_key);
		$this->add("title", $saved_doc->title);
		$this->add("content", $saved_doc->content);
		$this->add("document_srl", $saved_doc->document_srl);
	}

	function triggerDeleteSavedDoc(&$obj) {
		$this->deleteSavedDoc(false);
		return new Object();
	}

	function deleteSavedDoc($mode = false) {
		$args = new stdClass();
		if(Context::get('is_logged')) {
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
		} else {
			$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		}
		$args->module_srl = Context::get('module_srl');
		if(!$args->module_srl) {
			$current_module_info = Context::get('current_module_info');
			$args->module_srl = $current_module_info->module_srl;
		}
		$output = executeQuery('editor.getSavedDocument', $args);
		$saved_doc = $output->data;
		if(!$saved_doc) return;
		$oDocumentModel = getModel('document');
		$oSaved = $oDocumentModel->getDocument($saved_doc->document_srl);
		if(!$oSaved->isExists()) {
			if($mode) {
				$output = executeQuery('editor.getSavedDocument', $args);
				$output = ModuleHandler::triggerCall('editor.deleteSavedDoc', 'after', $saved_doc);
			}
		}
		return executeQuery('editor.deleteSavedDoc', $args);
	}

	function removeEditorConfig($site_srl) {
		$args->site_srl = $site_srl;
		executeQuery('editor.deleteSiteComponent', $args);
	}

	function makeCache($filter_enabled = true, $site_srl) {
		$oEditorModel = getModel('editor');
		$args = new stdClass;
		if($filter_enabled) $args->enabled = "Y";
		if($site_srl) {
			$args->site_srl = $site_srl;
			$output = executeQuery('editor.getSiteComponentList', $args);
		}
		else $output = executeQuery('editor.getComponentList', $args);
		$db_list = $output->data;
		$downloaded_list = FileHandler::readDir(_XE_PATH_.'modules/editor/components');
		$is_logged = Context::get('is_logged');
		if($is_logged) {
			$logged_info = Context::get('logged_info');
			if($logged_info->group_list && is_array($logged_info->group_list)) $group_list = array_keys($logged_info->group_list);
			else $group_list = array();
		}
		if(!is_array($db_list)) $db_list = array($db_list);
		$component_list = new stdClass();
		foreach($db_list as $component) {
			if(in_array($component->component_name, array('colorpicker_text','colorpicker_bg'))) continue;
			$component_name = $component->component_name;
			if(!$component_name) continue;
			if(!in_array($component_name, $downloaded_list)) continue;
			unset($xml_info);
			$xml_info = $oEditorModel->getComponentXmlInfo($component_name);
			$xml_info->enabled = $component->enabled;
			if($component->extra_vars) {
				$extra_vars = unserialize($component->extra_vars);
				if($extra_vars->target_group) $xml_info->target_group = $extra_vars->target_group;
				if($extra_vars->mid_list && count($extra_vars->mid_list)) $xml_info->mid_list = $extra_vars->mid_list;
				if($xml_info->extra_vars) {
					foreach($xml_info->extra_vars as $key => $val) $xml_info->extra_vars->{$key}->value = $extra_vars->{$key};
				}
			}
			$component_list->{$component_name} = $xml_info;
			$icon_file = _XE_PATH_.'modules/editor/components/'.$component_name.'/icon.gif';
			$component_icon_file = _XE_PATH_.'modules/editor/components/'.$component_name.'/component_icon.gif';
			if(file_exists($icon_file)) $component_list->{$component_name}->icon = true;
			if(file_exists($component_icon_file)) $component_list->{$component_name}->component_icon = true;
		}
		if($filter_enabled) {
			$cache_file = $oEditorModel->getCacheFile($filter_enabled, $site_srl);
			$buff = sprintf('<?php if(!defined("__XE__")) exit(); $component_list = unserialize("%s"); ?>', str_replace('"','\\"',serialize($component_list)));
			FileHandler::writeFile($cache_file, $buff);
			return $component_list;
		}
		foreach($downloaded_list as $component_name) {
			if(in_array($component_name, array('colorpicker_text','colorpicker_bg'))) continue;
			if($component_list->{$component_name}) continue;
			$oEditorController = getAdminController('editor');
			$oEditorController->insertComponent($component_name, false, $site_srl);
			unset($xml_info);
			$xml_info = $oEditorModel->getComponentXmlInfo($component_name);
			$xml_info->enabled = 'N';
			$component_list->{$component_name} = $xml_info;
		}
		$cache_file = $oEditorModel->getCacheFile($filter_enabled, $site_srl);
		$buff = sprintf('<?php if(!defined("__XE__")) exit(); $component_list = unserialize("%s"); ?>', str_replace('"','\\"',serialize($component_list)));
		FileHandler::writeFile($cache_file, $buff);
		return $component_list;
	}

	function removeCache($site_srl = 0) {
		$oEditorModel = getModel('editor');
		FileHandler::removeFile($oEditorModel->getCacheFile(true, $site_srl));
		FileHandler::removeFile($oEditorModel->getCacheFile(false, $site_srl));
	}

	function triggerCopyModule(&$obj) {
		$oModuleModel = getModel('module');
		$editorConfig = $oModuleModel->getModulePartConfig('editor', $obj->originModuleSrl);
		$oModuleController = getController('module');
		if(is_array($obj->moduleSrlList)) {
			foreach($obj->moduleSrlList AS $key=>$moduleSrl) $oModuleController->insertModulePartConfig('editor', $moduleSrl, $editorConfig);
		}
	}
}
