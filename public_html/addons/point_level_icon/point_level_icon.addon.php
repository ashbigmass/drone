<?php
if(!defined('__XE__')) exit();

if($called_position != "before_display_content" || Context::get('act') == 'dispPageAdminContentModify' || Context::getResponseMethod() != 'HTML' || isCrawler()) return;
require_once(_XE_PATH_ . 'addons/point_level_icon/point_level_icon.lib.php');
$temp_output = preg_replace_callback('!<(div|span|a)([^\>]*)member_([0-9\-]+)([^\>]*)>(.*?)\<\/(div|span|a)\>!is', 'pointLevelIconTrans', $output);
if($temp_output) $output = $temp_output;
unset($temp_output);
