<?php
class menu extends ModuleObject 
	function moduleInstall() {
		FileHandler::makeDir('./files/cache/menu');
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('menu', 'site_srl')) return true;
		if(!$oDB->isIndexExists("menu", "idx_title")) return true;
		if(!$oDB->isColumnExists('menu_item', 'is_shortcut')) return TRUE;
		$oMenuAdminModel = getAdminModel('menu');
		$args = new stdClass();
		$args->title = array("Temporary menu");
		$temp_menus = executeQueryArray('menu.getMenuByTitle', $args);
		if($temp_menus->toBool() && count($temp_menus->data)) return true;
		if(!$oDB->isColumnExists('menu_item', 'desc')) return true;
		return false;
	}

	function moduleUpdate() { 
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('menu', 'site_srl')) $oDB->addColumn('menu','site_srl','number',11,0,true);
		if(!$oDB->isIndexExists("menu","idx_title")) $oDB->addIndex('menu', 'idx_title', array('title'));
		if(!$oDB->isColumnExists('menu_item', 'desc')) $oDB->addColumn('menu_item', 'desc','varchar',250,"",true);
		if(!$oDB->isColumnExists('menu_item', 'is_shortcut')) {
			$oDB->addColumn('menu_item', 'is_shortcut', 'char', 1, 'N');
			$oMenuAdminModel = getAdminModel('menu');
			$output = $oMenuAdminModel->getMenus();
			if(is_array($output)) {
				$menuItemUniqueList = array();
				$menuItemAllList = array();
				foreach($output  AS $key=>$value) {
					unset($args);
					$args->menu_srl = $value->menu_srl;
					$output2 = executeQueryArray('menu.getMenuItems', $args);
					if(is_array($output2->data)) {
						foreach($output2->data AS $key2=>$value2) {
							$menuItemAllList[$value2->menu_item_srl] = $value2->url;
							if(!in_array($value2->url, $menuItemUniqueList)) $menuItemUniqueList[$value2->menu_item_srl] = $value2->url;
							if($value2->is_shortcut == 'N' && (!$value2->url || strncasecmp('http', $value2->url, 4) === 0)) {
								$value2->is_shortcut = 'Y';
								$output3 = executeQuery('menu.updateMenuItem', $value2);
							}
						}
					}
				}
				$oModuleModel = getModel('module');
				$shortcutItemList = array_diff_assoc($menuItemAllList, $menuItemUniqueList);
				foreach($output AS $key=>$value) {
					unset($args);
					$args->menu_srl = $value->menu_srl;
					$output2 = executeQueryArray('menu.getMenuItems', $args);
					if(is_array($output2->data)) {
						foreach($output2->data AS $key2=>$value2) {
							if(!empty($value2->url) && strncasecmp('http', $value2->url, 4) !== 0) {
								$moduleInfo = $oModuleModel->getModuleInfoByMid($value2->url);
								if(!$moduleInfo->module_srl) {
									$value2->url = Context::getDefaultUrl();
									if(!$value2->url) $value2->url = '#';
									$value2->is_shortcut = 'Y';
									$updateOutput = executeQuery('menu.updateMenuItem', $value2);
								}
							}
							if($shortcutItemList[$value2->menu_item_srl]) {
								$value2->is_shortcut = 'Y';
								$output3 = executeQuery('menu.updateMenuItem', $value2);
							}
						}
					}
				}
			}
			$this->recompileCache();
		}
		$oMenuAdminModel = getAdminModel('menu');
		$args = new stdClass();
		$args->title = array("Temporary menu");
		$temp_menus = executeQueryArray('menu.getMenuByTitle', $args);
		$args = new stdClass();
		if($temp_menus->toBool() && count($temp_menus->data)) {
			$oMenuAdminController = getAdminController('menu');
			foreach($temp_menus->data as $menu) {
				$args->current_menu_srl = $menu->menu_srl;
				$args->menu_srl = $oMenuAdminController->getUnlinkedMenu();
				$output3 = executeQuery('menu.updateMenuItems', $args);
				if($output3->toBool()) $oMenuAdminController->deleteMenu($menu->menu_srl);
			}
			$this->recompileCache();
		}
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
		$oMenuAdminController = getAdminController('menu');
		$oMenuAdminModel = getAdminModel('menu');
		$oModuleModel = getModel('module');
		$columnList = array('modules.mid',);
		$output = $oModuleModel->getSiteInfo(0, $columnList);
		$homeModuleMid = $output->mid;
		$homeMenuSrl = NULL;
		$output = executeQueryArray("menu.getMenus");
		$list = $output->data;
		if(!count($list)) return;
		foreach($list as $menu_item) {
			$menu_srl = $menu_item->menu_srl;
			$oMenuAdminController->makeXmlFile($menu_srl);
			if(!$homeMenuSrl) {
				$menuItemList = $oMenuAdminModel->getMenuItems($menu_srl);
				if(is_array($menuItemList->data)) {
					foreach($menuItemList->data AS $key=>$value) {
						if($homeModuleMid == $value->url) {
							$homeMenuSrl = $menu_srl;
							break;
						}
					}
				}
			}
		}
		if($homeMenuSrl) $oMenuAdminController->makeHomemenuCacheFile($homeMenuSrl);
	}
}
