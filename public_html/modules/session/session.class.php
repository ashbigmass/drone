<?php
class session extends ModuleObject {
	var $lifetime = 18000;
	var $session_started = false;

	function session() {
		if(Context::isInstalled()) $this->session_started= true;
	}

	function moduleInstall() {
		$oDB = &DB::getInstance();
		$oDB->addIndex("session","idx_session_update_mid", array("member_srl","last_update","cur_mid"));
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		if(!$oDB->isTableExists('session')) return true;
		if(!$oDB->isColumnExists("session","cur_mid")) return true;
		if(!$oDB->isIndexExists("session","idx_session_update_mid")) return true;
		return false;
	}

	function moduleUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		if(!$oDB->isTableExists('session')) $oDB->createTableByXmlFile($this->module_path.'schemas/session.xml');
		if(!$oDB->isColumnExists("session","cur_mid")) $oDB->addColumn('session',"cur_mid","varchar",128);
		if(!$oDB->isIndexExists("session","idx_session_update_mid")) $oDB->addIndex("session","idx_session_update_mid", array("member_srl","last_update","cur_mid"));
	}

	function unSerializeSession($val) {
		$vars = preg_split('/([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff^|]*)\|/', $val,-1,PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		for($i=0; $vars[$i]; $i++) $result[$vars[$i++]] = unserialize($vars[$i]);
		return $result;
	}

	function serializeSession($data) {
		if(!count($data)) return;
		$str = '';
		foreach($data as $key => $val) $str .= $key.'|'.serialize($val);
		return substr($str, 0, strlen($str)-1).'}';
	}
}
