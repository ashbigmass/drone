<?php
class HTMLDisplayHandler
{
	function toDoc(&$oModule) {
		$oTemplate = TemplateHandler::getInstance();
		$template_path = $oModule->getTemplatePath();
		if(!is_dir($template_path)) {
			if($oModule->module_info->module == $oModule->module) $skin = $oModule->origin_module_info->skin;
			else $skin = $oModule->module_config->skin;
			if(Context::get('module') != 'admin' && strpos(Context::get('act'), 'Admin') === false) {
				if($skin && is_string($skin)) {
					$theme_skin = explode('|@|', $skin);
					$template_path = $oModule->getTemplatePath();
					if(count($theme_skin) == 2) {
						$theme_path = sprintf('./themes/%s', $theme_skin[0]);
						if(substr($theme_path, 0, strlen($theme_path)) != $theme_path) $template_path = sprintf('%s/modules/%s/', $theme_path, $theme_skin[1]);
					}
				} else {
					$template_path = $oModule->getTemplatePath();
				}
			} else {
				$template_path = $oModule->getTemplatePath();
			}
		}
		$tpl_file = $oModule->getTemplateFile();
		$output = $oTemplate->compile($template_path, $tpl_file);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('is_keyword');
		if(Context::getResponseMethod() == 'HTML') {
			if(Context::get('module') != 'admin' && strpos(Context::get('act'), 'Admin') > 0 && Context::get('act') != 'dispPageAdminContentModify' && Context::get('act') != 'dispPageAdminMobileContentModify') {
				$output = '<div class="x">' . $output . '</div>';
			}
			if(Context::get('layout') != 'none') {
				if(__DEBUG__ == 3) $start = getMicroTime();
				Context::set('content', $output, false);
				$layout_path = $oModule->getLayoutPath();
				$layout_file = $oModule->getLayoutFile();
				$edited_layout_file = $oModule->getEditedLayoutFile();
				$oLayoutModel = getModel('layout');
				$layout_info = Context::get('layout_info');
				$layout_srl = $layout_info->layout_srl;
				if($layout_srl > 0) {
					if($layout_info && $layout_info->type == 'faceoff') {
						$oLayoutModel->doActivateFaceOff($layout_info);
						Context::set('layout_info', $layout_info);
					}
					$edited_layout_css = $oLayoutModel->getUserLayoutCss($layout_srl);
					if(FileHandler::exists($edited_layout_css)) Context::loadFile(array($edited_layout_css, 'all', '', 100));
				}
				if(!$layout_path) $layout_path = './common/tpl';
				if(!$layout_file) $layout_file = 'default_layout';
				$output = $oTemplate->compile($layout_path, $layout_file, $edited_layout_file);
				$realLayoutPath = FileHandler::getRealPath($layout_path);
				if(substr_compare($realLayoutPath, '/', -1) !== 0) $realLayoutPath .= '/';
				$pathInfo = pathinfo($layout_file);
				$onlyLayoutFile = $pathInfo['filename'];
				if(__DEBUG__ == 3) $GLOBALS['__layout_compile_elapsed__'] = getMicroTime() - $start;
				if(stripos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE && (Context::get('_use_ssl') == 'optional' || Context::get('_use_ssl') == 'always')) {
					Context::addHtmlFooter('<iframe id="xeTmpIframe" name="xeTmpIframe" style="width:1px;height:1px;position:absolute;top:-2px;left:-2px;"></iframe>');
				}
			}
		}
		return $output;
	}

	function prepareToPrint(&$output) {
		if(Context::getResponseMethod() != 'HTML') return;
		if(__DEBUG__ == 3) $start = getMicroTime();
		$output = preg_replace_callback('!<style(.*?)>(.*?)<\/style>!is', array($this, '_moveStyleToHeader'), $output);
		$output = preg_replace_callback('!<link(.*?)/>!is', array($this, '_moveLinkToHeader'), $output);
		$output = preg_replace_callback('!<meta(.*?)(?:\/|)>!is', array($this, '_moveMetaToHeader'), $output);
		$output = preg_replace_callback('/<!--(#)?Meta:([a-z0-9\_\-\/\.\@\:]+)-->/is', array($this, '_transMeta'), $output);
		if(Context::isAllowRewrite()) {
			$url = parse_url(Context::getRequestUri());
			$real_path = $url['path'];
			$pattern = '/src=("|\'){1}(\.\/)?(files\/attach|files\/cache|files\/faceOff|files\/member_extra_info|modules|common|widgets|widgetstyle|layouts|addons)\/([^"\']+)\.(jpg|jpeg|png|gif)("|\'){1}/s';
			$output = preg_replace($pattern, 'src=$1' . $real_path . '$3/$4.$5$6', $output);
			$pattern = '/href=("|\'){1}(\?[^"\']+)/s';
			$output = preg_replace($pattern, 'href=$1' . $real_path . '$2', $output);
			if(Context::get('vid')) {
				$pattern = '/\/' . Context::get('vid') . '\?([^=]+)=/is';
				$output = preg_replace($pattern, '/?$1=', $output);
			}
		}
		$output = preg_replace('/url\((["\']?)none(["\']?)\)/is', 'none', $output);
		if(is_array(Context::get('INPUT_ERROR'))) {
			$INPUT_ERROR = Context::get('INPUT_ERROR');
			$keys = array_keys($INPUT_ERROR);
			$keys = '(' . implode('|', $keys) . ')';
			$output = preg_replace_callback('@(<input)([^>]*?)\sname="' . $keys . '"([^>]*?)/?>@is', array(&$this, '_preserveValue'), $output);
			$output = preg_replace_callback('@<select[^>]*\sname="' . $keys . '".+</select>@isU', array(&$this, '_preserveSelectValue'), $output);
			$output = preg_replace_callback('@<textarea[^>]*\sname="' . $keys . '".+</textarea>@isU', array(&$this, '_preserveTextAreaValue'), $output);
		}
		if(__DEBUG__ == 3) $GLOBALS['__trans_content_elapsed__'] = getMicroTime() - $start;
		$output = preg_replace('/member\_\-([0-9]+)/s', 'member_0', $output);
		$oAdminModel = getAdminModel('admin');
		$favicon_url = $oAdminModel->getFaviconUrl(false);
		$mobicon_url = $oAdminModel->getMobileIconUrl(false);
		Context::set('favicon_url', $favicon_url);
		Context::set('mobicon_url', $mobicon_url);
		Context::set('content', $output);
		$oTemplate = TemplateHandler::getInstance();
		if(Mobile::isFromMobilePhone()) {
			$this->_loadMobileJSCSS();
			$output = $oTemplate->compile('./common/tpl', 'mobile_layout');
		} else {
			$this->_loadJSCSS();
			$output = $oTemplate->compile('./common/tpl', 'common_layout');
		}
		$oModuleController = getController('module');
		$oModuleController->replaceDefinedLangCode($output);
	}

	function _preserveValue($match) {
		$INPUT_ERROR = Context::get('INPUT_ERROR');
		$str = $match[1] . $match[2] . ' name="' . $match[3] . '"' . $match[4];
		$type = 'text';
		if(preg_match('/\stype="([a-z]+)"/i', $str, $m)) $type = strtolower($m[1]);
		switch($type) {
			case 'text':
			case 'hidden':
			case 'email':
			case 'search':
			case 'tel':
			case 'url':
			case 'email':
			case 'datetime':
			case 'date':
			case 'month':
			case 'week':
			case 'time':
			case 'datetime-local':
			case 'number':
			case 'range':
			case 'color':
				$str = preg_replace('@\svalue="[^"]*?"@', ' ', $str) . ' value="' . htmlspecialchars($INPUT_ERROR[$match[3]], ENT_COMPAT | ENT_HTML401, 'UTF-8', false) . '"';
			break;
			case 'password':
				$str = preg_replace('@\svalue="[^"]*?"@', ' ', $str);
			break;
			case 'radio':
			case 'checkbox':
				$str = preg_replace('@\schecked(="[^"]*?")?@', ' ', $str);
				if(@preg_match('@\s(?i:value)="' . $INPUT_ERROR[$match[3]] . '"@', $str)) $str .= ' checked="checked"';
			break;
		}
		return $str . ' />';
	}

	function _preserveSelectValue($matches) {
		$INPUT_ERROR = Context::get('INPUT_ERROR');
		preg_replace('@\sselected(="[^"]*?")?@', ' ', $matches[0]);
		preg_match('@<select.*?>@is', $matches[0], $mm);
		preg_match_all('@<option[^>]*\svalue="([^"]*)".+</option>@isU', $matches[0], $m);
		$key = array_search($INPUT_ERROR[$matches[1]], $m[1]);
		if($key === FALSE) return $matches[0];
		$m[0][$key] = preg_replace('@(\svalue=".*?")@is', '$1 selected="selected"', $m[0][$key]);
		return $mm[0] . implode('', $m[0]) . '</select>';
	}

	function _preserveTextAreaValue($matches) {
		$INPUT_ERROR = Context::get('INPUT_ERROR');
		preg_match('@<textarea.*?>@is', $matches[0], $mm);
		return $mm[0] . $INPUT_ERROR[$matches[1]] . '</textarea>';
	}

	function _moveStyleToHeader($matches) {
		if(isset($matches[1]) && stristr($matches[1], 'scoped')) return $matches[0];
		Context::addHtmlHeader($matches[0]);
	}

	function _moveLinkToHeader($matches) {
		Context::addHtmlHeader($matches[0]);
	}

	function _moveMetaToHeader($matches) {
		Context::addHtmlHeader($matches[0]);
	}

	function _transMeta($matches) {
		if($matches[1]) return '';
		Context::loadFile($matches[2]);
	}

	function _loadJSCSS() {
		$oContext = Context::getInstance();
		$lang_type = Context::getLangType();
		if(__DEBUG__ || !__XE_VERSION_STABLE__) {
			$oContext->loadFile(array('./common/js/jquery-1.x.js', 'head', 'lt IE 9', -111000), true);
			$oContext->loadFile(array('./common/js/jquery.js', 'head', 'gte IE 9', -110000), true);
			$oContext->loadFile(array('./common/js/modernizr.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/x.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/common.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/js_app.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/xml_handler.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/xml_js_filter.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/css/xe.css', '', '', -1000000), true);
		} else {
			$oContext->loadFile(array('./common/js/jquery-1.x.min.js', 'head', 'lt IE 9', -111000), true);
			$oContext->loadFile(array('./common/js/jquery.min.js', 'head', 'gte IE 9', -110000), true);
			$oContext->loadFile(array('./common/js/x.min.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/xe.min.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/css/xe.min.css', '', '', -1000000), true);
		}
		if(Context::get('module') == 'admin' || strpos(Context::get('act'), 'Admin') > 0) {
			if(__DEBUG__ || !__XE_VERSION_STABLE__) {
				$oContext->loadFile(array('./modules/admin/tpl/css/admin.css', '', '', 10), true);
				$oContext->loadFile(array("./modules/admin/tpl/css/admin_{$lang_type}.css", '', '', 10), true);
				$oContext->loadFile(array("./modules/admin/tpl/css/admin.iefix.css", '', 'ie', 10), true);
				$oContext->loadFile('./modules/admin/tpl/js/admin.js', true);
				$oContext->loadFile(array('./modules/admin/tpl/css/admin.bootstrap.css', '', '', 1), true);
				$oContext->loadFile(array('./modules/admin/tpl/js/jquery.tmpl.js', '', '', 1), true);
				$oContext->loadFile(array('./modules/admin/tpl/js/jquery.jstree.js', '', '', 1), true);
			} else {
				$oContext->loadFile(array('./modules/admin/tpl/css/admin.min.css', '', '', 10), true);
				$oContext->loadFile(array("./modules/admin/tpl/css/admin_{$lang_type}.css", '', '', 10), true);
				$oContext->loadFile(array("./modules/admin/tpl/css/admin.iefix.css", '', 'ie', 10), true);
				$oContext->loadFile('./modules/admin/tpl/js/admin.min.js', true);
				$oContext->loadFile(array('./modules/admin/tpl/css/admin.bootstrap.min.css', '', '', 1), true);
				$oContext->loadFile(array('./modules/admin/tpl/js/jquery.tmpl.js', '', '', 1), true);
				$oContext->loadFile(array('./modules/admin/tpl/js/jquery.jstree.js', '', '', 1), true);
			}
		}
	}

	private function _loadMobileJSCSS() {
		$oContext = Context::getInstance();
		$lang_type = Context::getLangType();
		if(__DEBUG__ || !__XE_VERSION_STABLE__) {
			$oContext->loadFile(array('./common/js/jquery.js', 'head', '', -110000), true);
			$oContext->loadFile(array('./common/js/modernizr.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/x.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/common.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/js_app.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/xml_handler.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/xml_js_filter.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/css/xe.css', '', '', -1000000), true);
			$oContext->loadFile(array('./common/css/mobile.css', '', '', -1000000), true);
		} else {
			$oContext->loadFile(array('./common/js/jquery.min.js', 'head', '', -110000), true);
			$oContext->loadFile(array('./common/js/x.min.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/js/xe.min.js', 'head', '', -100000), true);
			$oContext->loadFile(array('./common/css/xe.min.css', '', '', -1000000), true);
			$oContext->loadFile(array('./common/css/mobile.min.css', '', '', -1000000), true);
		}
	}
}
