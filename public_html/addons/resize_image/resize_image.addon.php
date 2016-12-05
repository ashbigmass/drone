<?php
if(!defined('__XE__')) exit();

$LF[0] = './addons/resize_image/css/resize_image.mobile.css';
$LF[1] = './addons/resize_image/js/resize_image.min.js';
if($called_position == 'after_module_proc' && Context::getResponseMethod() == "HTML" && !isCrawler()) {
	if(Mobile::isFromMobilePhone()) {
		Context::loadFile($LF[0], true);
	} else {
		Context::loadJavascriptPlugin('ui');
		Context::loadFile(array($LF[1], 'body', '', null), true);
	}
}
