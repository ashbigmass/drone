<?php
class stats extends ModuleObject
{
	protected $session_type;

    public function __construct() {
    	$this->session_type = 'ip_address';
	}

	function moduleInstall() {
		return new Object();
	}

	function checkUpdate() {
		return FALSE;
	}

	function moduleUpdate() {
		return FALSE;
	}

	function recompileCache() {
	}

	function getConfig() {
		$config = getModel('module')->getModuleConfig('stats');
		if(!$config->stats_ignore_admin) $config->stats_ignore_admin = 'N';
		if(!$config->stats_ignore_bot) $config->stats_ignore_bot = 'N';
		if(!$config->stats_ignore_ip) $config->stats_ignore_ip = '';
		if(!$config->stats_enable_admin_layer) $config->stats_enable_admin_layer = 'Y';
		return $config;
	}
}