<?php
if(!defined('__XE__')) exit();

if($called_position == 'before_module_init' && Context::get('module') != 'admin' && Context::getResponseMethod() == 'HTML' && Context::isInstalled() && !isCrawler())
	$oCounterController = getController('counter');
	$oCounterController->counterExecute();
}
