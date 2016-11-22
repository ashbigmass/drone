<?php
if(!defined('__XE__')) exit();
$logged_info = Context::get('logged_info');
if($logged_info && $logged_info->is_admin == 'Y' && stripos(Context::get('act'), 'admin') !== false && $called_position == 'before_module_proc') {
	$oAdminloggingController = getController('adminlogging');
	$oAdminloggingController->insertLog($this->module, $this->act);
}
