<?php
if(!defined('__XE__')) exit();

if(Context::get('module') == 'admin') return;
if($called_position != 'before_module_proc' && $called_position != 'after_module_proc') return;
require_once(_XE_PATH_ . 'addons/mobile/classes/mobile.class.php');
if(!mobileXE::getBrowserType()) return;
$oMobile = &mobileXE::getInstance();
if(!$oMobile) return;
$oMobile->setCharSet($addon_info->charset);
$oMobile->setModuleInfo($this->module_info);
$oMobile->setModuleInstance($this);
if($called_position == 'before_module_proc') {
	if($oMobile->isLangChange()) {
		$oMobile->setLangType();
		$oMobile->displayLangSelect();
	}
	if($oMobile->isNavigationMode()) $oMobile->displayNavigationContent();
	else $oMobile->displayModuleContent();
} else if($called_position == 'after_module_proc') {
	$oMobile->displayContent();
}
