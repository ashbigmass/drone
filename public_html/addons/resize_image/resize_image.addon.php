<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */

if(!defined('__XE__'))
{
	exit();
}

/**
 * @file resize_image.addon.php
 * @author NAVER (developers@xpressengine.com)
 * @brief Add-on to resize images in the body
 */

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

/* End of file resize_image.addon.php */
/* Location: ./addons/resize_image/resize_image.addon.php */
