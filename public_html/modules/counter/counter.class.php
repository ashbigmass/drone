<?php
class counter extends ModuleObject
{
	function moduleInstall() {
		$oCounterController = getController('counter');
		return new Object();
	}

	function checkUpdate() {
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('counter_log', 'site_srl')) return TRUE;
		if(!$oDB->isIndexExists('counter_log', 'idx_site_counter_log')) return TRUE;
		return FALSE;
	}

	function moduleUpdate() {
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('counter_log', 'site_srl')) $oDB->addColumn('counter_log', 'site_srl', 'number', 11, 0, TRUE);
		if(!$oDB->isIndexExists('counter_log', 'idx_site_counter_log')) $oDB->addIndex('counter_log', 'idx_site_counter_log', array('site_srl', 'ipaddress'), FALSE);
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
