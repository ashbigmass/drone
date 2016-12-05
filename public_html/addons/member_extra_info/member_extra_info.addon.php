<?php
if(!defined('__XE__')) exit();

if($called_position != "before_display_content" || Context::get('act') == 'dispPageAdminContentModify' || Context::getResponseMethod() != 'HTML' || isCrawler()) return;
require_once('./addons/member_extra_info/member_extra_info.lib.php');
$temp_output = preg_replace_callback('!<(div|span|a)([^\>]*)member_([0-9]+)([^\>]*)>(.*?)\<\/(div|span|a)\>!is', 'memberTransImageName', $output);
if($temp_output) $output = $temp_output;
unset($temp_output);
