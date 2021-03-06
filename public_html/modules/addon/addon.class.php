<?php
class addon extends ModuleObject {

	function moduleInstall() {
		$oAddonController = getAdminController('addon');
		$oAddonController->doInsert('autolink', 0, 'site', 'Y');
		$oAddonController->doInsert('blogapi');
		$oAddonController->doInsert('member_communication', 0, 'site', 'Y');
		$oAddonController->doInsert('member_extra_info', 0, 'site', 'Y');
		$oAddonController->doInsert('mobile', 0, 'site', 'Y');
		$oAddonController->doInsert('resize_image', 0, 'site', 'Y');
		$oAddonController->doInsert('openid_delegation_id');
		$oAddonController->doInsert('point_level_icon');
		$oAddonController->makeCacheFile(0);
		return new Object();
	}

	function checkUpdate() {
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists("addons", "is_used_m")) return TRUE;
		if(!$oDB->isColumnExists("addons_site", "is_used_m")) return TRUE;
		if(!$oDB->isColumnExists('addons', 'is_fixed')) return TRUE;
		return FALSE;
	}

	function moduleUpdate() {
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists("addons", "is_used_m")) $oDB->addColumn("addons", "is_used_m", "char", 1, "N", TRUE);
		if(!$oDB->isColumnExists("addons_site", "is_used_m")) $oDB->addColumn("addons_site", "is_used_m", "char", 1, "N", TRUE);
		if(!$oDB->isColumnExists('addons', 'is_fixed')) {
			$oDB->addColumn('addons', 'is_fixed', 'char', 1, 'N', TRUE);
			$output = executeQueryArray('addon.getAddons');
			if($output->data) {
				foreach($output->data as $row) {
					$args = new stdClass();
					$args->site_srl = 0;
					$args->addon = $row->addon;
					$args->is_used = $row->is_used;
					$args->is_used_m = $row->is_used_m;
					$args->extra_vars = $row->extra_vars;
					executeQuery('addon.insertSiteAddon', $args);
				}
			}
		}
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
