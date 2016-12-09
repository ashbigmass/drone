<?php
class layoutAdminView extends layout
{
	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispLayoutAdminInstalledList() {
		$type = Context::get('type');
		$type = ($type != 'M') ? 'P' : 'M';
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getDownloadedLayoutList($type, true);
		if(!is_array($layout_list)) $layout_list = array();
		if($type == 'P') {
			$oAdminModel = getAdminModel('admin');
			$themeList = $oAdminModel->getThemeList();
			$themeLayoutList = array();
			foreach($themeList as $themeInfo) {
				if(strpos($themeInfo->layout_info->name, '.') === false) continue;
				$themeLayoutList[] = $oLayoutModel->getLayoutInfo($themeInfo->layout_info->name, null, 'P');
			}
			$layout_list = array_merge($layout_list, $themeLayoutList);
			$layout_list[] = $oLayoutModel->getLayoutInfo('faceoff', null, 'P');
		}
		$pcLayoutCount = $oLayoutModel->getInstalledLayoutCount('P');
		$mobileLayoutCount = $oLayoutModel->getInstalledLayoutCount('M');
		Context::set('pcLayoutCount', $pcLayoutCount);
		Context::set('mobileLayoutCount', $mobileLayoutCount);
		$this->setTemplateFile('installed_layout_list');
		$security = new Security($layout_list);
		$layout_list = $security->encodeHTML('..', '..author..');
		$security = new Security();
		$security->encodeHTML('layout_list..layout','layout_list..title');
		foreach($layout_list as $no => $layout_info) $layout_list[$no]->description = nl2br(trim($layout_info->description));
		Context::set('layout_list', $layout_list);
	}

	function dispLayoutAdminAllInstanceList() {
		$type = Context::get('type');
		if(!in_array($type, array('P', 'M'))) $type = 'P';
		$oLayoutModel = getModel('layout');
		$pcLayoutCount = $oLayoutModel->getInstalledLayoutCount('P');
		$mobileLayoutCount = $oLayoutModel->getInstalledLayoutCount('M');
		Context::set('pcLayoutCount', $pcLayoutCount);
		Context::set('mobileLayoutCount', $mobileLayoutCount);
		$columnList = array('layout_srl', 'layout', 'module_srl', 'title', 'regdate');
		$_layout_list = $oLayoutModel->getLayoutInstanceList(0, $type, null, $columnList);
		$layout_list = array();
		foreach($_layout_list as $item) {
			if(!$layout_list[$item->layout]) {
				$layout_list[$item->layout] = array();
				$layout_info = $oLayoutModel->getLayoutInfo($item->layout, null, $type);
				if ($layout_info) $layout_list[$item->layout]['title'] = $layout_info->title;
			}
			$layout_list[$item->layout][] = $item;
		}
		usort($layout_list, array($this, 'sortLayoutInstance'));
		Context::set('layout_list', $layout_list);
		$this->setTemplateFile('layout_all_instance_list');
		$security = new Security();
		$security->encodeHTML('layout_list..');
	}

	function sortLayoutInstance($a, $b) {
		$aTitle = strtolower($a['title']);
		$bTitle = strtolower($b['title']);
		if($aTitle == $bTitle) return 0;
		return ($aTitle < $bTitle) ? -1 : 1;
	}

	function dispLayoutAdminInstanceList() {
		$type = Context::get('type');
		$layout = Context::get('layout');
		if(!in_array($type, array('P', 'M'))) $type = 'P';
		if(!$layout) return $this->stop('msg_invalid_request');
		$oLayoutModel = getModel('layout');
		$layout_info = $oLayoutModel->getLayoutInfo($layout, null, $type);
		if(!$layout_info) return $this->stop('msg_invalid_request');
		Context::set('layout_info', $layout_info);
		$columnList = array('layout_srl', 'layout', 'module_srl', 'title', 'regdate');
		$layout_list = $oLayoutModel->getLayoutInstanceList(0, $type, $layout, $columnList);
		Context::set('layout_list', $layout_list);
		$this->setTemplateFile('layout_instance_list');
		$security = new Security();
		$security->encodeHTML('layout_list..');
	}

	function dispLayoutAdminInsert() {
		$oModel = getModel('layout');
		$type = Context::get('type');
		if(!in_array($type, array('P', 'M'))) $type = 'P';
		$security = new Security();
		$security->encodeHTML('layout_list..layout','layout_list..title');
		$layout = Context::get('layout');
		if($layout == 'faceoff') return $this->stop('not supported');
		$layout_info = $oModel->getLayoutInfo($layout, null, $type);
		if(!$layout_info) return $this->stop('msg_invalid_request');
		$oMenuAdminModel = getAdminModel('menu');
		$menu_list = $oMenuAdminModel->getMenus();
		Context::set('menu_list', $menu_list);
		$security = new Security();
		$security->encodeHTML('menu_list..');
		$security = new Security($layout_info);
		$layout_info = $security->encodeHTML('.', 'author..', 'extra_var..', 'extra_var....');
		$layout_info->description = nl2br(trim($layout_info->description));
		if(!is_object($layout_info->extra_var)) $layout_info->extra_var = new StdClass();
		foreach($layout_info->extra_var as $var_name => $val) {
			if(isset($layout_info->{$var_name}->description)) $layout_info->{$var_name}->description = nl2br(trim($val->description));
		}
		Context::set('selected_layout', $layout_info);
		$this->setTemplateFile('layout_modify');
	}

	function dispLayoutAdminModify() {
		$oLayoutAdminModel = getAdminModel('layout');
		$oLayoutAdminModel->setLayoutAdminSetInfoView();
		Context::set('is_sitemap', '0');
		$script = '<script src="./modules/layout/tpl/js/layout_modify.js"></script>';
		$oTemplate = &TemplateHandler::getInstance();
		$content = $oTemplate->compile($this->module_path.'tpl/', 'layout_info_view');
		Context::set('content', $content);
		$this->setTemplateFile('layout_modify');
	}

	function dispLayoutAdminEdit() {
		$layout_srl = Context::get('layout_srl');
		$oLayoutModel = getModel('layout');
		$layout_info = $oLayoutModel->getLayout($layout_srl);
		if(!$layout_info) return $this->dispLayoutAdminInstalledList();
		if($oLayoutModel->useDefaultLayout($layout_info->layout_srl)) {
			$layout_file  = $oLayoutModel->getDefaultLayoutHtml($layout_info->layout);
			$layout_css_file  = $oLayoutModel->getDefaultLayoutCss($layout_info->layout);
		} else {
			$layout_file = $oLayoutModel->getUserLayoutHtml($layout_info->layout_srl);
			$layout_css_file = $oLayoutModel->getUserLayoutCss($layout_info->layout_srl);
			if(!file_exists($layout_file)) $layout_file = $layout_info->path . 'layout.html';
			if(!file_exists($layout_css_file)) $layout_css_file = $layout_info->path . 'layout.css';
		}
		if(file_exists($layout_css_file)) {
			$layout_code_css = FileHandler::readFile($layout_css_file);
			Context::set('layout_code_css', $layout_code_css);
		}
		$layout_code = FileHandler::readFile($layout_file);
		Context::set('layout_code', $layout_code);
		$layout_image_list = $oLayoutModel->getUserLayoutImageList($layout_info->layout_srl);
		Context::set('layout_image_list', $layout_image_list);
		$layout_image_path = $oLayoutModel->getUserLayoutImagePath($layout_info->layout_srl);
		Context::set('layout_image_path', $layout_image_path);
		$oWidgetModel = getModel('widget');
		$widget_list = $oWidgetModel->getDownloadedWidgetList();
		Context::set('widget_list', $widget_list);
		$this->setTemplateFile('layout_edit');
		$security = new Security($layout_info);
		$layout_info = $security->encodeHTML('.', '.author..');
		Context::set('selected_layout', $layout_info);
		$security = new Security();
		$security->encodeHTML('layout_list..');
		$security->encodeHTML('layout_list..author..');
		$security = new Security();
		$security->encodeHTML('layout_code_css', 'layout_code', 'widget_list..title');
	}

	function dispLayoutAdminPreview() {
		$layout_srl = Context::get('layout_srl');
		$code = Context::get('code');
		$code_css = Context::get('code_css');
		if(!$layout_srl || !$code) return new Object(-1, 'msg_invalid_request');
		$oLayoutModel = getModel('layout');
		$layout_info = $oLayoutModel->getLayout($layout_srl);
		if(!$layout_info) return new Object(-1, 'msg_invalid_request');
		if($layout_info && $layout_info->type == 'faceoff') $oLayoutModel->doActivateFaceOff($layout_info);
		Context::addHtmlHeader("<style type=\"text/css\" charset=\"UTF-8\">".$code_css."</style>");
		if($layout_info->extra_var_count) {
			foreach($layout_info->extra_var as $var_id => $val) $layout_info->{$var_id} = $val->value;
		}
		if($layout_info->menu_count) {
			foreach($layout_info->menu as $menu_id => $menu) {
				$menu->php_file = FileHandler::getRealPath($menu->php_file);
				if(FileHandler::exists($menu->php_file)) include($menu->php_file);
				Context::set($menu_id, $menu);
			}
		}
		Context::set('layout_info', $layout_info);
		Context::set('content', Context::getLang('layout_preview_content'));
		$edited_layout_file = sprintf('./files/cache/layout/tmp.tpl');
		FileHandler::writeFile($edited_layout_file, $code);
		$oTemplate = &TemplateHandler::getInstance();
		$layout_path = $layout_info->path;
		$layout_file = 'layout';
		$layout_tpl = $oTemplate->compile($layout_path, $layout_file, $edited_layout_file);
		Context::set('layout','none');
		$oContext = &Context::getInstance();
		Context::set('layout_tpl', $layout_tpl);
		FileHandler::removeFile($edited_layout_file);
		$this->setTemplateFile('layout_preview');
	}

	function dispLayoutAdminLayoutModify() {
		$current_module_info = Context::get('current_module_info');
		$layout_srl = $current_module_info->layout_srl;
		$delete_tmp = Context::get('delete_tmp');
		if($delete_tmp =='Y') {
			$oLayoutAdminController = getAdminController('layout');
			$oLayoutAdminController->deleteUserLayoutTempFile($layout_srl);
		}
		$oLayoutModel = getModel('layout');
		$oLayoutModel->setUseUserLayoutTemp();
		$faceoffcss = $oLayoutModel->_getUserLayoutFaceOffCss($current_module_info->layout_srl);
		$css = FileHandler::readFile($faceoffcss);
		$match = null;
		preg_match_all('/([^\{]+)\{([^\}]*)\}/is',$css,$match);
		for($i=0,$c=count($match[1]);$i<$c;$i++) {
			$name = trim($match[1][$i]);
			$css = trim($match[2][$i]);
			if(!$css) continue;
			$css = str_replace('./images/',Context::getRequestUri().$oLayoutModel->getUserLayoutImagePath($layout_srl),$css);
			$style[] .= sprintf('"%s":"%s"',$name,$css);
		}
		if(count($style)) {
			$script = '<script type="text/javascript"> var faceOffStyle = {'.implode(',',$style).'}; </script>';
			Context::addHtmlHeader($script);
		}
		$oTemplate = &TemplateHandler::getInstance();
		Context::set('content', $oTemplate->compile($this->module_path.'tpl','about_faceoff'));
		$oWidgetController = getController('widget');
		$oWidgetController->setWidgetCodeInJavascriptMode();
		$this->setTemplateFile('faceoff_layout_edit');
	}

	function dispLayoutAdminCopyLayout() {
		$layoutSrl = Context::get('layout_srl');
		$oLayoutModel = getModel('layout');
		$layout = $oLayoutModel->getLayout($layoutSrl);
		Context::set('layout', $layout);
		$this->setTemplateFile('copy_layout');
	}
}
