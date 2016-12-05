<?php
if(!defined('__XE__')) exit();

if($called_position != 'before_module_init') return;
if(!$addon_info->server || !$addon_info->delegate || !$addon_info->xrds) return;
$header_script = sprintf(
		'<link rel="openid.server" href="%s" />' . "\n" .
		'<link rel="openid.delegate" href="%s" />' . "\n" .
		'<meta http-equiv="X-XRDS-Location" content="%s" />',
		$addon_info->server,
		$addon_info->delegate,
		$addon_info->xrds
);
Context::addHtmlHeader($header_script);
