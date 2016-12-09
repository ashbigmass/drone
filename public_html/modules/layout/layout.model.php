<?php
class layoutModel extends layout
{
	var $useUserLayoutTemp = null;

	function init() {
	}

	function getLayoutList($site_srl = 0, $layout_type="P", $columnList = array()) {
		if(!$site_srl) {
			$site_module_info = Context::get('site_module_info');
			$site_srl = (int)$site_module_info->site_srl;
		}
		$args = new stdClass();
		$args->site_srl = $site_srl;
		$args->layout_type = $layout_type;
		$output = executeQueryArray('layout.getLayoutList', $args, $columnList);
		foreach($output->data as $no => &$val) {
			if(!$this->isExistsLayoutFile($val->layout, $layout_type)) unset($output->data[$no]);
		}
		$oLayoutAdminModel = getAdminModel('layout');
		$siteDefaultLayoutSrl = $oLayoutAdminModel->getSiteDefaultLayout($layout_type, $site_srl);
		if($siteDefaultLayoutSrl) {
			$siteDefaultLayoutInfo = $this->getlayout($siteDefaultLayoutSrl);
			$newLayout = sprintf('%s, %s', $siteDefaultLayoutInfo->title, $siteDefaultLayoutInfo->layout);
			$siteDefaultLayoutInfo->layout_srl = -1;
			$siteDefaultLayoutInfo->title = Context::getLang('use_site_default_layout');
			$siteDefaultLayoutInfo->layout = $newLayout;
			array_unshift($output->data, $siteDefaultLayoutInfo);
		}
		return $output->data;
	}

	public function getLayoutInstanceListForJSONP() {
		$siteSrl = Context::get('site_srl');
		$layoutType = Context::get('layout_type');
		$layoutList = $this->getLayoutInstanceList($siteSrl, $layoutType);
		$thumbs = array();
		foreach($layoutList as $key => $val) {
			if($thumbs[$val->layouts]) {
				$val->thumbnail = $thumbs[$val->layouts];
				continue;
			}
			$token = explode('|@|', $val->layout);
			if(count($token) == 2) $thumbnailPath = sprintf('./themes/%s/layouts/%s/thumbnail.png' , $token[0], $token[1]);
			else $thumbnailPath = sprintf('./layouts/%s/thumbnail.png' , $val->layout);
			if(is_readable($thumbnailPath)) $val->thumbnail = $thumbnailPath;
			else $val->thumbnail = sprintf('./modules/layout/tpl/img/noThumbnail.png');
			$thumbs[$val->layout] = $val->thumbnail;
		}
		$this->add('layout_list', $layoutList);
	}

	function getLayoutInstanceList($siteSrl = 0, $layoutType = 'P', $layout = null, $columnList = array()) {
		if (!$siteSrl) {
			$siteModuleInfo = Context::get('site_module_info');
			$siteSrl = (int)$siteModuleInfo->site_srl;
		}
		$args = new stdClass();
		$args->site_srl = $siteSrl;
		$args->layout_type = $layoutType;
		$args->layout = $layout;
		$output = executeQueryArray('layout.getLayoutList', $args, $columnList);
		$instanceList = array();
		if(is_array($output->data)) {
			foreach($output->data as $no => $iInfo) {
				if($this->isExistsLayoutFile($iInfo->layout, $layoutType)) $instanceList[] = $iInfo->layout;
				else unset($output->data[$no]);
			}
		}
		$downloadedList = array();
		$titleList = array();
		$_downloadedList = $this->getDownloadedLayoutList($layoutType);
		if(is_array($_downloadedList)) {
			foreach($_downloadedList as $dLayoutInfo) {
				$downloadedList[$dLayoutInfo->layout] = $dLayoutInfo->layout;
				$titleList[$dLayoutInfo->layout] = $dLayoutInfo->title;
			}
		}
		if($layout) {
			if(count($instanceList) < 1 && $downloadedList[$layout]) {
				$insertArgs = new stdClass();
				$insertArgs->site_srl = $siteSrl;
				$insertArgs->layout_srl = getNextSequence();
				$insertArgs->layout = $layout;
				$insertArgs->title = $titleList[$layout];
				$insertArgs->layout_type = $layoutType;
				$oLayoutAdminController = getAdminController('layout');
				$oLayoutAdminController->insertLayout($insertArgs);
				$isCreateInstance = TRUE;
			}
		} else {
			$noInstanceList = array_diff($downloadedList, $instanceList);
			foreach($noInstanceList as $layoutName) {
				$insertArgs = new stdClass();
				$insertArgs->site_srl = $siteSrl;
				$insertArgs->layout_srl = getNextSequence();
				$insertArgs->layout = $layoutName;
				$insertArgs->title = $titleList[$layoutName];
				$insertArgs->layout_type = $layoutType;
				$oLayoutAdminController = getAdminController('layout');
				$oLayoutAdminController->insertLayout($insertArgs);
				$isCreateInstance = TRUE;
			}
		}
		if($isCreateInstance) {
			$output = executeQueryArray('layout.getLayoutList', $args, $columnList);
			if(is_array($output->data)) {
				foreach($output->data as $no => $iInfo) {
					if(!$this->isExistsLayoutFile($iInfo->layout, $layoutType)) unset($output->data[$no]);
				}
			}
		}
		return $output->data;
	}

	function isExistsLayoutFile($layout, $layoutType) {
		if($layoutType == 'P') {
			$pathPrefix = _XE_PATH_ . 'layouts/';
			$themePathFormat = _XE_PATH_ . 'themes/%s/layouts/%s';
		} else {
			$pathPrefix = _XE_PATH_ . 'm.layouts/';
			$themePathFormat = _XE_PATH_ . 'themes/%s/m.layouts/%s';
		}
		if(strpos($layout, '|@|') !== FALSE) {
			list($themeName, $layoutName) = explode('|@|', $layout);
			$path = sprintf($themePathFormat, $themeName, $layoutName);
		} else {
			$path = $pathPrefix . $layout;
		}
		return is_readable($path . '/layout.html');
	}

	function getLayout($layout_srl) {
		$args = new stdClass();
		$args->layout_srl = $layout_srl;
		$output = executeQuery('layout.getLayout', $args);
		if(!$output->data) return;
		$layout_info = $this->getLayoutInfo($layout, $output->data, $output->data->layout_type);
		return $layout_info;
	}

	function getLayoutRawData($layout_srl, $columnList = array()) {
		$args = new stdClass();
		$args->layout_srl = $layout_srl;
		$output = executeQuery('layout.getLayout', $args, $columnList);
		if(!$output->toBool()) return;
		return $output->data;
	}

	function getLayoutPath($layout_name = "", $layout_type = "P") {
		$layout_parse = explode('|@|', $layout_name);
		if(count($layout_parse) > 1) $class_path = './themes/'.$layout_parse[0].'/layouts/'.$layout_parse[1].'/';
		else if($layout_name == 'faceoff') $class_path = './modules/layout/faceoff/';
		else if($layout_type == "M") $class_path = sprintf("./m.layouts/%s/", $layout_name);
		else $class_path = sprintf('./layouts/%s/', $layout_name);
		if(is_dir($class_path)) return $class_path;
		return "";
	}

	function getDownloadedLayoutList($layout_type = "P", $withAutoinstallInfo = false) {
		if ($withAutoinstallInfo) $oAutoinstallModel = getModel('autoinstall');
		$searched_list = $this->_getInstalledLayoutDirectories($layout_type);
		$searched_count = count($searched_list);
		if(!$searched_count) return;
		$list = array();
		for($i=0;$i<$searched_count;$i++) {
			$layout = $searched_list[$i];
			$layout_info = $this->getLayoutInfo($layout, null, $layout_type);
			if(!$layout_info) continue;
			if($withAutoinstallInfo) {
				$packageSrl = $oAutoinstallModel->getPackageSrlByPath($layout_info->path);
				$layout_info->remove_url = $oAutoinstallModel->getRemoveUrlByPackageSrl($packageSrl);
				$package = $oAutoinstallModel->getInstalledPackages($packageSrl);
				$layout_info->need_update = $package[$packageSrl]->need_update;
				if($layout_info->need_update) $layout_info->update_url = $oAutoinstallModel->getUpdateUrlByPackageSrl($packageSrl);
			}
			$list[] = $layout_info;
		}
		usort($list, array($this, 'sortLayoutByTitle'));
		return $list;
	}

	function sortLayoutByTitle($a, $b) {
		if(!$a->title) $a->title = $a->layout;
		if(!$b->title) $b->title = $b->layout;
		$aTitle = strtolower($a->title);
		$bTitle = strtolower($b->title);
		if($aTitle == $bTitle) return 0;
		return ($aTitle < $bTitle) ? -1 : 1;
	}

	function getInstalledLayoutCount($layoutType = 'P') {
		$searchedList = $this->_getInstalledLayoutDirectories($layoutType);
		return  count($searchedList);
	}

	function _getInstalledLayoutDirectories($layoutType = 'P') {
		if($layoutType == 'M') {
			$directory = './m.layouts';
			$globalValueKey = 'MOBILE_LAYOUT_DIRECTOIES';
		} else {
			$directory = './layouts';
			$globalValueKey = 'PC_LAYOUT_DIRECTORIES';
		}
		if($GLOBALS[$globalValueKey]) return $GLOBALS[$globalValueKey];
		$searchedList = FileHandler::readDir($directory);
		if (!$searchedList) $searchedList = array();
		$GLOBALS[$globalValueKey] = $searchedList;
		return $searchedList;
	}

	function getLayoutInfo($layout, $info = null, $layout_type = "P") {
		if($info) {
			$layout_title = $info->title;
			$layout = $info->layout;
			$layout_srl = $info->layout_srl;
			$site_srl = $info->site_srl;
			$vars = unserialize($info->extra_vars);
			if($info->module_srl) {
				$layout_path = preg_replace('/([a-zA-Z0-9\_\.]+)(\.html)$/','',$info->layout_path);
				$xml_file = sprintf('%sskin.xml', $layout_path);
			}
		}
		if(!$layout_path) $layout_path = $this->getLayoutPath($layout, $layout_type);
		if(!is_dir($layout_path)) return;
		if(!$xml_file) $xml_file = sprintf("%sconf/info.xml", $layout_path);
		if(!file_exists($xml_file)) {
			$layout_info = new stdClass;
			$layout_info->title = $layout;
			$layout_info->layout = $layout;
			$layout_info->path = $layout_path;
			$layout_info->layout_title = $layout_title;
			if(!$layout_info->layout_type) $layout_info->layout_type =  $layout_type;
			return $layout_info;
		}
		if(!$layout_srl) $cache_file = $this->getLayoutCache($layout, Context::getLangType(), $layout_type);
		else $cache_file = $this->getUserLayoutCache($layout_srl, Context::getLangType());
		if(file_exists($cache_file)&&filemtime($cache_file)>filemtime($xml_file)) {
			include($cache_file);
			if($layout_info->extra_var && $vars) {
				foreach($vars as $key => $value) {
					if(!$layout_info->extra_var->{$key} && !$layout_info->{$key}) $layout_info->{$key} = $value;
				}
			}
			if(!$layout_info->title) $layout_info->title = $layout;
			return $layout_info;
		}
		$oXmlParser = new XmlParser();
		$tmp_xml_obj = $oXmlParser->loadXmlFile($xml_file);
		if($tmp_xml_obj->layout) $xml_obj = $tmp_xml_obj->layout;
		elseif($tmp_xml_obj->skin) $xml_obj = $tmp_xml_obj->skin;
		if(!$xml_obj) return;
		$buff = array();
		$buff[] = '$layout_info = new stdClass;';
		$buff[] = sprintf('$layout_info->site_srl = "%s";', $site_srl);
		if($xml_obj->version && $xml_obj->attrs->version == '0.2') {
			sscanf($xml_obj->date->body, '%d-%d-%d', $date_obj->y, $date_obj->m, $date_obj->d);
			$date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
			$buff[] = sprintf('$layout_info->layout = "%s";', $layout);
			$buff[] = sprintf('$layout_info->type = "%s";', $xml_obj->attrs->type);
			$buff[] = sprintf('$layout_info->path = "%s";', $layout_path);
			$buff[] = sprintf('$layout_info->title = "%s";', $xml_obj->title->body);
			$buff[] = sprintf('$layout_info->description = "%s";', $xml_obj->description->body);
			$buff[] = sprintf('$layout_info->version = "%s";', $xml_obj->version->body);
			$buff[] = sprintf('$layout_info->date = "%s";', $date);
			$buff[] = sprintf('$layout_info->homepage = "%s";', $xml_obj->link->body);
			$buff[] = sprintf('$layout_info->layout_srl = $layout_srl;');
			$buff[] = sprintf('$layout_info->layout_title = $layout_title;');
			$buff[] = sprintf('$layout_info->license = "%s";', $xml_obj->license->body);
			$buff[] = sprintf('$layout_info->license_link = "%s";', $xml_obj->license->attrs->link);
			$buff[] = sprintf('$layout_info->layout_type = "%s";', $layout_type);
			if(!is_array($xml_obj->author)) $author_list[] = $xml_obj->author;
			else $author_list = $xml_obj->author;
			$buff[] = '$layout_info->author = array();';
			for($i=0, $c=count($author_list); $i<$c; $i++) {
				$buff[] = sprintf('$layout_info->author[%d] = new stdClass;', $i);
				$buff[] = sprintf('$layout_info->author[%d]->name = "%s";', $i, $author_list[$i]->name->body);
				$buff[] = sprintf('$layout_info->author[%d]->email_address = "%s";', $i, $author_list[$i]->attrs->email_address);
				$buff[] = sprintf('$layout_info->author[%d]->homepage = "%s";', $i, $author_list[$i]->attrs->link);
			}
			$extra_var_groups = $xml_obj->extra_vars->group;
			if(!$extra_var_groups) $extra_var_groups = $xml_obj->extra_vars;
			if(!is_array($extra_var_groups)) $extra_var_groups = array($extra_var_groups);
			$buff[] = '$layout_info->extra_var = new stdClass;';
			$extra_var_count = 0;
			foreach($extra_var_groups as $group) {
				$extra_vars = $group->var;
				if($extra_vars) {
					if(!is_array($extra_vars)) $extra_vars = array($extra_vars);
					$count = count($extra_vars);
					$extra_var_count += $count;
					for($i=0;$i<$count;$i++) {
						unset($var, $options);
						$var = $extra_vars[$i];
						$name = $var->attrs->name;
						$buff[] = sprintf('$layout_info->extra_var->%s = new stdClass;', $name);
						$buff[] = sprintf('$layout_info->extra_var->%s->group = "%s";', $name, $group->title->body);
						$buff[] = sprintf('$layout_info->extra_var->%s->title = "%s";', $name, $var->title->body);
						$buff[] = sprintf('$layout_info->extra_var->%s->type = "%s";', $name, $var->attrs->type);
						$buff[] = sprintf('$layout_info->extra_var->%s->value = $vars->%s;', $name, $name);
						$buff[] = sprintf('$layout_info->extra_var->%s->description = "%s";', $name, str_replace('"','\"',$var->description->body));
						$options = $var->options;
						if(!$options) continue;
						if(!is_array($options)) $options = array($options);
						$buff[] = sprintf('$layout_info->extra_var->%s->options = array();', $var->attrs->name);
						$options_count = count($options);
						$thumbnail_exist = false;
						for($j=0; $j < $options_count; $j++) {
							$buff[] = sprintf('$layout_info->extra_var->%s->options["%s"] = new stdClass;', $var->attrs->name, $options[$j]->attrs->value);
							$thumbnail = $options[$j]->attrs->src;
							if($thumbnail) {
								$thumbnail = $layout_path.$thumbnail;
								if(file_exists($thumbnail)) {
									$buff[] = sprintf('$layout_info->extra_var->%s->options["%s"]->thumbnail = "%s";', $var->attrs->name, $options[$j]->attrs->value, $thumbnail);
									if(!$thumbnail_exist) {
										$buff[] = sprintf('$layout_info->extra_var->%s->thumbnail_exist = true;', $var->attrs->name);
										$thumbnail_exist = true;
									}
								}
							}
							$buff[] = sprintf('$layout_info->extra_var->%s->options["%s"]->val = "%s";', $var->attrs->name, $options[$j]->attrs->value, $options[$j]->title->body);
						}
					}
				}
			}
			$buff[] = sprintf('$layout_info->extra_var_count = "%s";', $extra_var_count);
			if($xml_obj->menus->menu) {
				$menus = $xml_obj->menus->menu;
				if(!is_array($menus)) $menus = array($menus);
				$menu_count = count($menus);
				$buff[] = sprintf('$layout_info->menu_count = "%s";', $menu_count);
				$buff[] = '$layout_info->menu = new stdClass;';
				for($i=0;$i<$menu_count;$i++) {
					$name = $menus[$i]->attrs->name;
					if($menus[$i]->attrs->default == "true") $buff[] = sprintf('$layout_info->default_menu = "%s";', $name);
					$buff[] = sprintf('$layout_info->menu->%s = new stdClass;', $name);
					$buff[] = sprintf('$layout_info->menu->%s->name = "%s";',$name, $menus[$i]->attrs->name);
					$buff[] = sprintf('$layout_info->menu->%s->title = "%s";',$name, $menus[$i]->title->body);
					$buff[] = sprintf('$layout_info->menu->%s->maxdepth = "%s";',$name, $menus[$i]->attrs->maxdepth);
					$buff[] = sprintf('$layout_info->menu->%s->menu_srl = $vars->%s;', $name, $name);
					$buff[] = sprintf('$layout_info->menu->%s->xml_file = "./files/cache/menu/".$vars->%s.".xml.php";',$name, $name);
					$buff[] = sprintf('$layout_info->menu->%s->php_file = "./files/cache/menu/".$vars->%s.".php";',$name, $name);
				}
			}
		} else {
			sscanf($xml_obj->author->attrs->date, '%d. %d. %d', $date_obj->y, $date_obj->m, $date_obj->d);
			$date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
			$buff[] = sprintf('$layout_info->layout = "%s";', $layout);
			$buff[] = sprintf('$layout_info->path = "%s";', $layout_path);
			$buff[] = sprintf('$layout_info->title = "%s";', $xml_obj->title->body);
			$buff[] = sprintf('$layout_info->description = "%s";', $xml_obj->author->description->body);
			$buff[] = sprintf('$layout_info->version = "%s";', $xml_obj->attrs->version);
			$buff[] = sprintf('$layout_info->date = "%s";', $date);
			$buff[] = sprintf('$layout_info->layout_srl = $layout_srl;');
			$buff[] = sprintf('$layout_info->layout_title = $layout_title;');
			$buff[] = sprintf('$layout_info->author[0]->name = "%s";', $xml_obj->author->name->body);
			$buff[] = sprintf('$layout_info->author[0]->email_address = "%s";', $xml_obj->author->attrs->email_address);
			$buff[] = sprintf('$layout_info->author[0]->homepage = "%s";', $xml_obj->author->attrs->link);
			$extra_var_groups = $xml_obj->extra_vars->group;
			if(!$extra_var_groups) $extra_var_groups = $xml_obj->extra_vars;
			if(!is_array($extra_var_groups)) $extra_var_groups = array($extra_var_groups);
			foreach($extra_var_groups as $group) {
				$extra_vars = $group->var;
				if($extra_vars) {
					if(!is_array($extra_vars)) $extra_vars = array($extra_vars);
					$extra_var_count = count($extra_vars);
					$buff[] = sprintf('$layout_info->extra_var_count = "%s";', $extra_var_count);
					for($i=0;$i<$extra_var_count;$i++) {
						unset($var, $options);
						$var = $extra_vars[$i];
						$name = $var->attrs->name;
						$buff[] = sprintf('$layout_info->extra_var->%s->group = "%s";', $name, $group->title->body);
						$buff[] = sprintf('$layout_info->extra_var->%s->title = "%s";', $name, $var->title->body);
						$buff[] = sprintf('$layout_info->extra_var->%s->type = "%s";', $name, $var->attrs->type);
						$buff[] = sprintf('$layout_info->extra_var->%s->value = $vars->%s;', $name, $name);
						$buff[] = sprintf('$layout_info->extra_var->%s->description = "%s";', $name, str_replace('"','\"',$var->description->body));
						$options = $var->options;
						if(!$options) continue;
						if(!is_array($options)) $options = array($options);
						$options_count = count($options);
						for($j=0;$j<$options_count;$j++) {
							$buff[] = sprintf('$layout_info->extra_var->%s->options["%s"]->val = "%s";', $var->attrs->name, $options[$j]->value->body, $options[$j]->title->body);
						}
					}
				}
			}
			if($xml_obj->menus->menu) {
				$menus = $xml_obj->menus->menu;
				if(!is_array($menus)) $menus = array($menus);
				$menu_count = count($menus);
				$buff[] = sprintf('$layout_info->menu_count = "%s";', $menu_count);
				for($i=0;$i<$menu_count;$i++) {
					$name = $menus[$i]->attrs->name;
					if($menus[$i]->attrs->default == "true") $buff[] = sprintf('$layout_info->default_menu = "%s";', $name);
					$buff[] = sprintf('$layout_info->menu->%s->name = "%s";',$name, $name);
					$buff[] = sprintf('$layout_info->menu->%s->title = "%s";',$name, $menus[$i]->title->body);
					$buff[] = sprintf('$layout_info->menu->%s->maxdepth = "%s";',$name, $menus[$i]->maxdepth->body);
					$buff[] = sprintf('$layout_info->menu->%s->menu_srl = $vars->%s;', $name, $name);
					$buff[] = sprintf('$layout_info->menu->%s->xml_file = "./files/cache/menu/".$vars->%s.".xml.php";',$name, $name);
					$buff[] = sprintf('$layout_info->menu->%s->php_file = "./files/cache/menu/".$vars->%s.".php";',$name, $name);
				}
			}
		}
		$oModuleModel = getModel('module');
		$layout_config = $oModuleModel->getModulePartConfig('layout', $layout_srl);
		$header_script = trim($layout_config->header_script);
		if($header_script) $buff[] = sprintf(' $layout_info->header_script = "%s"; ', str_replace(array('$','"'),array('\$','\\"'),$header_script));
		FileHandler::writeFile($cache_file, '<?php if(!defined("__XE__")) exit(); ' . join(PHP_EOL, $buff));
		if(FileHandler::exists($cache_file)) include($cache_file);
		if(!$layout_info->title) $layout_info->title = $layout;
		return $layout_info;
	}

	function getUserLayoutImageList($layout_srl) {
		return FileHandler::readDir($this->getUserLayoutImagePath($layout_srl));
	}

	function getUserLayoutIniConfig($layout_srl, $layout_name=null) {
		$file = $this->getUserLayoutIni($layout_srl);
		if($layout_name && FileHandler::exists($file) === FALSE) {
			FileHandler::copyFile($this->getDefaultLayoutIni($layout_name),$this->getUserLayoutIni($layout_srl));
		}
		return FileHandler::readIniFile($file);
	}

	function getUserLayoutPath($layout_srl) {
		return sprintf("./files/faceOff/%s", getNumberingPath($layout_srl,3));
	}

	function getUserLayoutImagePath($layout_srl) {
		return $this->getUserLayoutPath($layout_srl). 'images/';
	}

	function getUserLayoutCss($layout_srl) {
		return $this->getUserLayoutPath($layout_srl). 'layout.css';
	}

	function getUserLayoutFaceOffCss($layout_srl) {
		if($this->useUserLayoutTemp == 'temp') return;
		return $this->_getUserLayoutFaceOffCss($layout_srl);
	}

	function _getUserLayoutFaceOffCss($layout_srl) {
		return $this->getUserLayoutPath($layout_srl). 'faceoff.css';
	}

	function getUserLayoutTempFaceOffCss($layout_srl) {
		return $this->getUserLayoutPath($layout_srl). 'tmp.faceoff.css';
	}

	function getUserLayoutHtml($layout_srl) {
		$src = $this->getUserLayoutPath($layout_srl). 'layout.html';
		if($this->useUserLayoutTemp == 'temp') {
			$temp = $this->getUserLayoutTempHtml($layout_srl);
			if(FileHandler::exists($temp) === FALSE) FileHandler::copyFile($src,$temp);
			return $temp;
		}
		return $src;
	}

	function getUserLayoutTempHtml($layout_srl) {
		return $this->getUserLayoutPath($layout_srl). 'tmp.layout.html';
	}

	function getUserLayoutIni($layout_srl) {
		$src = $this->getUserLayoutPath($layout_srl). 'layout.ini';
		if($this->useUserLayoutTemp == 'temp') {
			$temp = $this->getUserLayoutTempIni($layout_srl);
			if(!file_exists(FileHandler::getRealPath($temp))) FileHandler::copyFile($src,$temp);
			return $temp;
		}
		return $src;
	}

	function getUserLayoutTempIni($layout_srl) {
		return $this->getUserLayoutPath($layout_srl). 'tmp.layout.ini';
	}

	function getUserLayoutCache($layout_srl,$lang_type) {
		return $this->getUserLayoutPath($layout_srl). "{$lang_type}.cache.php";
	}

	function getLayoutCache($layout_name,$lang_type,$layout_type='P') {
		if($layout_type=='P') return sprintf("%sfiles/cache/layout/%s.%s.cache.php", _XE_PATH_, $layout_name,$lang_type);
		else return sprintf("%sfiles/cache/layout/m.%s.%s.cache.php", _XE_PATH_, $layout_name,$lang_type);
	}

	function getDefaultLayoutIni($layout_name) {
		return $this->getDefaultLayoutPath($layout_name). 'layout.ini';
	}

	function getDefaultLayoutHtml($layout_name) {
		return $this->getDefaultLayoutPath($layout_name). 'layout.html';
	}

	function getDefaultLayoutCss($layout_name) {
		return $this->getDefaultLayoutPath($layout_name). 'css/layout.css';
	}

	function getDefaultLayoutPath() {
		return "./modules/layout/faceoff/";
	}

	function useDefaultLayout($layout_name) {
		$info = $this->getLayoutInfo($layout_name);
		return ($info->type == 'faceoff');
	}

	function setUseUserLayoutTemp($flag='temp') {
		$this->useUserLayoutTemp = $flag;
	}

	function getUserLayoutTempFileList($layout_srl) {
		return array(
			$this->getUserLayoutTempHtml($layout_srl),
			$this->getUserLayoutTempFaceOffCss($layout_srl),
			$this->getUserLayoutTempIni($layout_srl)
			);
	}

	function getUserLayoutFileList($layout_srl) {
		$file_list = array(
			basename($this->getUserLayoutHtml($layout_srl)),
			basename($this->getUserLayoutFaceOffCss($layout_srl)),
			basename($this->getUserLayoutIni($layout_srl)),
			basename($this->getUserLayoutCss($layout_srl))
		);
		$image_path = $this->getUserLayoutImagePath($layout_srl);
		$image_list = FileHandler::readDir($image_path,'/(.*(?:swf|jpg|jpeg|gif|bmp|png)$)/i');
		foreach($image_list as $image) $file_list[] = 'images/' . $image;
		return $file_list;
	}

	function doActivateFaceOff(&$layout_info) {
		$layout_info->faceoff_ini_config = $this->getUserLayoutIniConfig($layout_info->layout_srl, $layout_info->layout);
		Context::addCSSFile($this->getDefaultLayoutCss($layout_info->layout));
		$faceoff_layout_css = $this->getUserLayoutFaceOffCss($layout_info->layout_srl);
		if($faceoff_layout_css) Context::addCSSFile($faceoff_layout_css);
		Context::loadFile($this->module_path.'/tpl/css/widget.css', true);
		if($layout_info->extra_var->colorset->value == 'black') Context::loadFile($this->module_path.'/tpl/css/widget@black.css', true);
		else Context::loadFile($this->module_path.'/tpl/css/widget@white.css', true);
		$logged_info = Context::get('logged_info');
		if(Context::get('module')!='admin' && strpos(Context::get('act'),'Admin')===false && ($logged_info->is_admin == 'Y' || $logged_info->is_site_admin)) {
			Context::addHtmlFooter('<div class="faceOffManager" style="height: 23px; position: fixed; right: 3px; top: 3px;"><a href="'.getUrl('','mid',Context::get('mid'),'act','dispLayoutAdminLayoutModify','delete_tmp','Y').'">'.Context::getLang('cmd_layout_edit').'</a></div>');
		}
		if(Context::get('act')=='dispLayoutAdminLayoutModify' && ($logged_info->is_admin == 'Y' || $logged_info->is_site_admin)) {
			$oTemplate = &TemplateHandler::getInstance();
			Context::addBodyHeader($oTemplate->compile($this->module_path.'/tpl', 'faceoff_layout_menu'));
		}
	}
}
