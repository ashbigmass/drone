<?php
if(!defined('__XE__')) exit();

if($called_position == 'after_module_proc' && Context::getResponseMethod() == "HTML") {
	if(Mobile::isFromMobilePhone()) {
		Context::addJsFile('./common/js/jquery.min.js', false, '', -1000000);
		Context::addJsFile('./common/js/xe.min.js', false, '', -1000000);
	}
	Context::loadFile(array('./addons/autolink/autolink.js', 'body', '', null), true);
}
