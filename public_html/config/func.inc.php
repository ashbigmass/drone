<?php
if(!defined('__XE__')) exit();
if(!function_exists('iconv')) {
	eval('
		function iconv($in_charset, $out_charset, $str) {
			return $str;
		}
	');
}

$time_zone = array(
	'-1200' => '[GMT -12:00] Baker Island Time',
	'-1100' => '[GMT -11:00] Niue Time, Samoa Standard Time',
	'-1000' => '[GMT -10:00] Hawaii-Aleutian Standard Time, Cook Island Time',
	'-0930' => '[GMT -09:30] Marquesas Islands Time',
	'-0900' => '[GMT -09:00] Alaska Standard Time, Gambier Island Time',
	'-0800' => '[GMT -08:00] Pacific Standard Time',
	'-0700' => '[GMT -07:00] Mountain Standard Time',
	'-0600' => '[GMT -06:00] Central Standard Time',
	'-0500' => '[GMT -05:00] Eastern Standard Time',
	'-0400' => '[GMT -04:00] Atlantic Standard Time',
	'-0330' => '[GMT -03:30] Newfoundland Standard Time',
	'-0300' => '[GMT -03:00] Amazon Standard Time, Central Greenland Time',
	'-0200' => '[GMT -02:00] Fernando de Noronha Time, South Georgia &amp; the South Sandwich Islands Time',
	'-0100' => '[GMT -01:00] Azores Standard Time, Cape Verde Time, Eastern Greenland Time',
	'0000' => '[GMT  00:00] Western European Time, Greenwich Mean Time',
	'+0100' => '[GMT +01:00] Central European Time, West African Time',
	'+0200' => '[GMT +02:00] Eastern European Time, Central African Time',
	'+0300' => '[GMT +03:00] Moscow Standard Time, Eastern African Time',
	'+0330' => '[GMT +03:30] Iran Standard Time',
	'+0400' => '[GMT +04:00] Gulf Standard Time, Samara Standard Time',
	'+0430' => '[GMT +04:30] Afghanistan Time',
	'+0500' => '[GMT +05:00] Pakistan Standard Time, Yekaterinburg Standard Time',
	'+0530' => '[GMT +05:30] Indian Standard Time, Sri Lanka Time',
	'+0545' => '[GMT +05:45] Nepal Time',
	'+0600' => '[GMT +06:00] Bangladesh Time, Bhutan Time, Novosibirsk Standard Time',
	'+0630' => '[GMT +06:30] Cocos Islands Time, Myanmar Time',
	'+0700' => '[GMT +07:00] Indochina Time, Krasnoyarsk Standard Time',
	'+0800' => '[GMT +08:00] China Standard Time, Australian Western Standard Time, Irkutsk Standard Time',
	'+0845' => '[GMT +08:45] Southeastern Western Australia Standard Time',
	'+0900' => '[GMT +09:00] Korea Standard Time, Japan Standard Time',
	'+0930' => '[GMT +09:30] Australian Central Standard Time',
	'+1000' => '[GMT +10:00] Australian Eastern Standard Time, Vladivostok Standard Time',
	'+1030' => '[GMT +10:30] Lord Howe Standard Time',
	'+1100' => '[GMT +11:00] Solomon Island Time, Magadan Standard Time',
	'+1130' => '[GMT +11:30] Norfolk Island Time',
	'+1200' => '[GMT +12:00] New Zealand Time, Fiji Time, Kamchatka Standard Time',
	'+1245' => '[GMT +12:45] Chatham Islands Time',
	'+1300' => '[GMT +13:00] Tonga Time, Phoenix Islands Time',
	'+1400' => '[GMT +14:00] Line Island Time'
);

function getModule($module_name, $type = 'view', $kind = '') {
	return ModuleHandler::getModuleInstance($module_name, $type, $kind);
}

function getController($module_name) {
	return getModule($module_name, 'controller');
}

function getAdminController($module_name) {
	return getModule($module_name, 'controller', 'admin');
}

function getView($module_name) {
	return getModule($module_name, 'view');
}

function &getMobile($module_name) {
	return getModule($module_name, 'mobile');
}

function getAdminView($module_name) {
	return getModule($module_name, 'view', 'admin');
}

function getModel($module_name) {
	return getModule($module_name, 'model');
}

function getAdminModel($module_name) {
	return getModule($module_name, 'model', 'admin');
}

function getAPI($module_name) {
	return getModule($module_name, 'api');
}

function getWAP($module_name) {
	return getModule($module_name, 'wap');
}

function getClass($module_name) {
	return getModule($module_name, 'class');
}

function executeQuery($query_id, $args = NULL, $arg_columns = NULL) {
	$oDB = DB::getInstance();
	return $oDB->executeQuery($query_id, $args, $arg_columns);
}

function executeQueryArray($query_id, $args = NULL, $arg_columns = NULL) {
	$oDB = DB::getInstance();
	$output = $oDB->executeQuery($query_id, $args, $arg_columns);
	if(!is_array($output->data) && count($output->data) > 0) $output->data = array($output->data);
	return $output;
}

function getNextSequence() {
	$oDB = DB::getInstance();
	$seq = $oDB->getNextSequence();
	setUserSequence($seq);
	return $seq;
}

function setUserSequence($seq) {
	$arr_seq = array();
	if(isset($_SESSION['seq'])) $arr_seq = $_SESSION['seq'];
	$arr_seq[] = $seq;
	$_SESSION['seq'] = $arr_seq;
}

function checkUserSequence($seq) {
	if(!isset($_SESSION['seq'])) return false;
	if(!in_array($seq, $_SESSION['seq'])) return false;
	return true;
}

function getUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	if($num_args) $url = Context::getUrl($num_args, $args_list);
	else $url = Context::getRequestUri();
	return preg_replace('@\berror_return_url=[^&]*|\w+=(?:&|$)@', '', $url);
}

function getNotEncodedUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	if($num_args) $url = Context::getUrl($num_args, $args_list, NULL, FALSE);
	else $url = Context::getRequestUri();
	return preg_replace('@\berror_return_url=[^&]*|\w+=(?:&|$)@', '', $url);
}

function getAutoEncodedUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	if($num_args) $url = Context::getUrl($num_args, $args_list, NULL, TRUE, TRUE);
	else $url = Context::getRequestUri();
	return preg_replace('@\berror_return_url=[^&]*|\w+=(?:&|$)@', '', $url);
}

function getFullUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	$request_uri = Context::getRequestUri();
	if(!$num_args) return $request_uri;
	$url = Context::getUrl($num_args, $args_list);
	if(strncasecmp('http', $url, 4) !== 0) {
		preg_match('/^(http|https):\/\/([^\/]+)\//', $request_uri, $match);
		return substr($match[0], 0, -1) . $url;
	}
	return $url;
}

function getNotEncodedFullUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	$request_uri = Context::getRequestUri();
	if(!$num_args) return $request_uri;
	$url = Context::getUrl($num_args, $args_list, NULL, FALSE);
	if(strncasecmp('http', $url, 4) !== 0) {
		preg_match('/^(http|https):\/\/([^\/]+)\//', $request_uri, $match);
		$url = Context::getUrl($num_args, $args_list, NULL, FALSE);
		return substr($match[0], 0, -1) . $url;
	}
	return $url;
}

function getSiteUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	if(!$num_args) return Context::getRequestUri();
	$domain = array_shift($args_list);
	$num_args = count($args_list);
	return Context::getUrl($num_args, $args_list, $domain);
}

function getNotEncodedSiteUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	if(!$num_args) return Context::getRequestUri();
	$domain = array_shift($args_list);
	$num_args = count($args_list);
	return Context::getUrl($num_args, $args_list, $domain, FALSE);
}

function getFullSiteUrl() {
	$num_args = func_num_args();
	$args_list = func_get_args();
	$request_uri = Context::getRequestUri();
	if(!$num_args) return $request_uri;
	$domain = array_shift($args_list);
	$num_args = count($args_list);
	$url = Context::getUrl($num_args, $args_list, $domain);
	if(strncasecmp('http', $url, 4) !== 0) {
		preg_match('/^(http|https):\/\/([^\/]+)\//', $request_uri, $match);
		return substr($match[0], 0, -1) . $url;
	}
	return $url;
}

function getCurrentPageUrl() {
	$protocol = $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';
	$url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	return htmlspecialchars($url, ENT_COMPAT, 'UTF-8', FALSE);
}

function isSiteID($domain) {
	return preg_match('/^([a-zA-Z0-9\_]+)$/', $domain);
}

function cut_str($string, $cut_size = 0, $tail = '...') {
	if($cut_size < 1 || !$string) return $string;
	if($GLOBALS['use_mb_strimwidth'] || function_exists('mb_strimwidth')) {
		$GLOBALS['use_mb_strimwidth'] = TRUE;
		return mb_strimwidth($string, 0, $cut_size + 4, $tail, 'utf-8');
	}
	$chars = array(12, 4, 3, 5, 7, 7, 11, 8, 4, 5, 5, 6, 6, 4, 6, 4, 6, 6, 6, 6, 6, 6, 6, 6, 6, 6, 6, 4, 4, 8, 6, 8, 6, 10, 8, 8, 9, 8, 8, 7, 9, 8, 3, 6, 7, 7, 11, 8, 9, 8, 9, 8, 8, 7, 8, 8, 10, 8, 8, 8, 6, 11, 6, 6, 6, 4, 7, 7, 7, 7, 7, 3, 7, 7, 3, 3, 6, 3, 9, 7, 7, 7, 7, 4, 7, 3, 7, 6, 10, 6, 6, 7, 6, 6, 6, 9);
	$max_width = $cut_size * $chars[0] / 2;
	$char_width = 0;
	$string_length = strlen($string);
	$char_count = 0;
	$idx = 0;
	while($idx < $string_length && $char_count < $cut_size && $char_width <= $max_width) {
		$c = ord(substr($string, $idx, 1));
		$char_count++;
		if($c < 128) {
			$char_width += (int) $chars[$c - 32];
			$idx++;
		} else if(191 < $c && $c < 224) {
			$char_width += $chars[4];
			$idx += 2;
		} else {
			$char_width += $chars[0];
			$idx += 3;
		}
	}
	$output = substr($string, 0, $idx);
	if(strlen($output) < $string_length) $output .= $tail;
	return $output;
}

function zgap() {
	$time_zone = $GLOBALS['_time_zone'];
	if($time_zone < 0) $to = -1;
	else $to = 1;
	$t_hour = substr($time_zone, 1, 2) * $to;
	$t_min = substr($time_zone, 3, 2) * $to;
	$server_time_zone = date("O");
	if($server_time_zone < 0) $so = -1;
	else $so = 1;
	$c_hour = substr($server_time_zone, 1, 2) * $so;
	$c_min = substr($server_time_zone, 3, 2) * $so;
	$g_min = $t_min - $c_min;
	$g_hour = $t_hour - $c_hour;
	$gap = $g_min * 60 + $g_hour * 60 * 60;
	return $gap;
}

function ztime($str) {
	if(!$str) return;
	$hour = (int) substr($str, 8, 2);
	$min = (int) substr($str, 10, 2);
	$sec = (int) substr($str, 12, 2);
	$year = (int) substr($str, 0, 4);
	$month = (int) substr($str, 4, 2);
	$day = (int) substr($str, 6, 2);
	if(strlen($str) <= 8) $gap = 0;
	else $gap = zgap();
	return mktime($hour, $min, $sec, $month ? $month : 1, $day ? $day : 1, $year) + $gap;
}

function getTimeGap($date, $format = 'Y.m.d') {
	$gap = $_SERVER['REQUEST_TIME'] + zgap() - ztime($date);
	$lang_time_gap = Context::getLang('time_gap');
	if($gap < 60) $buff = sprintf($lang_time_gap['min'], (int) ($gap / 60) + 1);
	elseif($gap < 60 * 60) $buff = sprintf($lang_time_gap['mins'], (int) ($gap / 60) + 1);
	elseif($gap < 60 * 60 * 2) $buff = sprintf($lang_time_gap['hour'], (int) ($gap / 60 / 60) + 1);
	elseif($gap < 60 * 60 * 24) $buff = sprintf($lang_time_gap['hours'], (int) ($gap / 60 / 60) + 1);
	else $buff = zdate($date, $format);
	return $buff;
}

function getMonthName($month, $short = TRUE) {
	$short_month = array('', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	$long_month = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
	return !$short ? $long_month[$month] : $short_month[$month];
}

function zdate($str, $format = 'Y-m-d H:i:s', $conversion = TRUE) {
	if(!$str) return;
	if($conversion == TRUE) {
		switch(Context::getLangType()) {
			case 'en' :
			case 'es' :
				if($format == 'Y-m-d') $format = 'M d, Y';
				elseif($format == 'Y-m-d H:i:s') $format = 'M d, Y H:i:s';
				elseif($format == 'Y-m-d H:i') $format = 'M d, Y H:i';
			break;
			case 'vi' :
				if($format == 'Y-m-d') $format = 'd-m-Y';
				elseif($format == 'Y-m-d H:i:s') $format = 'H:i:s d-m-Y';
				elseif($format == 'Y-m-d H:i') $format = 'H:i d-m-Y';
				break;
		}
	}
	if((int) substr($str, 0, 4) < 1970) {
		$hour = (int) substr($str, 8, 2);
		$min = (int) substr($str, 10, 2);
		$sec = (int) substr($str, 12, 2);
		$year = (int) substr($str, 0, 4);
		$month = (int) substr($str, 4, 2);
		$day = (int) substr($str, 6, 2);
		$trans = array(
			'Y' => $year,
			'y' => sprintf('%02d', $year % 100),
			'm' => sprintf('%02d', $month),
			'n' => $month,
			'd' => sprintf('%02d', $day),
			'j' => $day,
			'G' => $hour,
			'H' => sprintf('%02d', $hour),
			'g' => $hour % 12,
			'h' => sprintf('%02d', $hour % 12),
			'i' => sprintf('%02d', $min),
			's' => sprintf('%02d', $sec),
			'M' => getMonthName($month),
			'F' => getMonthName($month, FALSE)
		);
		$string = strtr($format, $trans);
	} else {
		$string = date($format, ztime($str));
	}
	$unit_week = Context::getLang('unit_week');
	$unit_meridiem = Context::getLang('unit_meridiem');
	$string = str_replace(array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), $unit_week, $string);
	$string = str_replace(array('am', 'pm', 'AM', 'PM'), $unit_meridiem, $string);
	return $string;
}

function getEncodeEmailAddress($email) {
	$return = '';
	for($i = 0, $c = strlen($email); $i < $c; $i++) {
		$return .= '&#' . (rand(0, 1) == 0 ? ord($email[$i]) : 'X' . dechex(ord($email[$i]))) . ';';
	}
	return $return;
}

function debugPrint($debug_output = NULL, $display_option = TRUE, $file = '_debug_message.php') {
	static $debug_file;
	if(!(__DEBUG__ & 1)) return;
	static $firephp;
	$bt = debug_backtrace();
	if(is_array($bt)) {
		$bt_debug_print = array_shift($bt);
		$bt_called_function = array_shift($bt);
	}
	$file_name = str_replace(_XE_PATH_, '', $bt_debug_print['file']);
	$line_num = $bt_debug_print['line'];
	$function = $bt_called_function['class'] . $bt_called_function['type'] . $bt_called_function['function'];
	if(__DEBUG_OUTPUT__ == 2 && version_compare(PHP_VERSION, '6.0.0') === -1) {
		if(!isset($firephp)) $firephp = FirePHP::getInstance(TRUE);
		$type = FirePHP::INFO;
		$label = sprintf('[%s:%d] %s() (Memory usage: current=%s, peak=%s)', $file_name, $line_num, $function, FileHandler::filesize(memory_get_usage()), FileHandler::filesize(memory_get_peak_usage()));
		if($display_option === 'TABLE') $label = $display_option;
		if($display_option === 'ERROR') $type = $display_option;
		if(__DEBUG_PROTECT__ === 1 && __DEBUG_PROTECT_IP__ != $_SERVER['REMOTE_ADDR']) {
			$debug_output = 'The IP address is not allowed. Change the value of __DEBUG_PROTECT_IP__ into your IP address in config/config.user.inc.php or config/config.inc.php';
			$label = NULL;
		}
		$firephp->fb($debug_output, $label, $type);
	} else {
		if(__DEBUG_PROTECT__ === 1 && __DEBUG_PROTECT_IP__ != $_SERVER['REMOTE_ADDR']) return;
		$print = array();
		if(!$debug_file) $debug_file = _XE_PATH_ . 'files/' . $file;
		if(!file_exists($debug_file)) $print[] = '<?php exit() ?>';
		if($display_option === TRUE || $display_option === 'ERROR') {
			$print[] = sprintf("[%s %s:%d] %s() - mem(%s)", date('Y-m-d H:i:s'), $file_name, $line_num, $function, FileHandler::filesize(memory_get_usage()));;
			$print[] = str_repeat('=', 80);
		}
		$type = gettype($debug_output);
		if(!in_array($type, array('array', 'object', 'resource'))) {
			if($display_option === 'ERROR') {
				$print[] = 'ERROR : ' . var_export($debug_output, TRUE);
			} else {
				$print[] = 'DEBUG : ' . $type . '(' . var_export($debug_output, TRUE) . ')';
			}
		} else {
			$print[] = 'DEBUG : ' . trim(preg_replace('/\r?\n/', "\n" . '        ', print_r($debug_output, true)));
		}
		$backtrace_args = defined('\DEBUG_BACKTRACE_IGNORE_ARGS') ? \DEBUG_BACKTRACE_IGNORE_ARGS : 0;
		$backtrace = debug_backtrace($backtrace_args);
		if(count($backtrace) > 1 && $backtrace[1]['function'] === 'debugPrint' && !$backtrace[1]['class']) array_shift($backtrace);
		foreach($backtrace as $val) $print[] = '        - ' . $val['file'] . ' : ' . $val['line'];
		$print[] = PHP_EOL;
		@file_put_contents($debug_file, implode(PHP_EOL, $print), FILE_APPEND|LOCK_EX);
	}
}

function writeSlowlog($type, $elapsed_time, $obj) {
	if(!__LOG_SLOW_TRIGGER__ && !__LOG_SLOW_ADDON__ && !__LOG_SLOW_WIDGET__ && !__LOG_SLOW_QUERY__) return;
	static $log_filename = array(
		'query' => 'files/_slowlog_query.php',
		'trigger' => 'files/_slowlog_trigger.php',
		'addon' => 'files/_slowlog_addon.php',
		'widget' => 'files/_slowlog_widget.php'
	);
	$write_file = true;
	$log_file = _XE_PATH_ . $log_filename[$type];
	$buff = array();
	$buff[] = '<?php exit(); ?>';
	$buff[] = date('c');
	if($type == 'trigger' && __LOG_SLOW_TRIGGER__ > 0 && $elapsed_time > __LOG_SLOW_TRIGGER__) {
		$buff[] = "\tCaller : " . $obj->caller;
		$buff[] = "\tCalled : " . $obj->called;
	} else if($type == 'addon' && __LOG_SLOW_ADDON__ > 0 && $elapsed_time > __LOG_SLOW_ADDON__) {
		$buff[] = "\tAddon : " . $obj->called;
		$buff[] = "\tCalled position : " . $obj->caller;
	} else if($type == 'widget' && __LOG_SLOW_WIDGET__ > 0 && $elapsed_time > __LOG_SLOW_WIDGET__) {
		$buff[] = "\tWidget : " . $obj->called;
	} else if($type == 'query' && __LOG_SLOW_QUERY__ > 0 && $elapsed_time > __LOG_SLOW_QUERY__) {
		$buff[] = $obj->query;
		$buff[] = "\tQuery ID   : " . $obj->query_id;
		$buff[] = "\tCaller     : " . $obj->caller;
		$buff[] = "\tConnection : " . $obj->connection;
	} else {
		$write_file = false;
	}
	if($write_file) {
		$buff[] = sprintf("\t%0.6f sec", $elapsed_time);
		$buff[] = PHP_EOL . PHP_EOL;
		file_put_contents($log_file, implode(PHP_EOL, $buff), FILE_APPEND);
	}
	if($type != 'query') {
		$trigger_args = $obj;
		$trigger_args->_log_type = $type;
		$trigger_args->_elapsed_time = $elapsed_time;
		ModuleHandler::triggerCall('XE.writeSlowlog', 'after', $trigger_args);
	}
}

function flushSlowlog() {
	$trigger_args = new stdClass();
	$trigger_args->_log_type = 'flush';
	$trigger_args->_elapsed_time = 0;
	ModuleHandler::triggerCall('XE.writeSlowlog', 'after', $trigger_args);
}

function getMicroTime() {
	list($time1, $time2) = explode(' ', microtime());
	return (float) $time1 + (float) $time2;
}

function delObjectVars($target_obj, $del_obj) {
	if(!is_object($target_obj)) return;
	if(!is_object($del_obj)) return;
	$target_vars = get_object_vars($target_obj);
	$del_vars = get_object_vars($del_obj);
	$target = array_keys($target_vars);
	$del = array_keys($del_vars);
	if(!count($target) || !count($del)) return $target_obj;
	$return_obj = new stdClass();
	$target_count = count($target);
	for($i = 0; $i < $target_count; $i++) {
		$target_key = $target[$i];
		if(!in_array($target_key, $del)) $return_obj->{$target_key} = $target_obj->{$target_key};
	}
	return $return_obj;
}

function getDestroyXeVars(&$vars) {
	$del_vars = array('error_return_url', 'success_return_url', 'ruleset', 'xe_validator_id');
	foreach($del_vars as $var) {
		if(is_array($vars)) unset($vars[$var]);
		else if(is_object($vars)) unset($vars->$var);
	}
	return $vars;
}

function handleError($errno, $errstr, $file, $line) {
	if(!__DEBUG__) return;
	$errors = array(E_USER_ERROR, E_ERROR, E_PARSE);
	if(!in_array($errno, $errors)) return;
	$output = sprintf("Fatal error : %s - %d", $file, $line);
	$output .= sprintf("%d - %s", $errno, $errstr);
	debugPrint($output);
}

function getNumberingPath($no, $size = 3) {
	$mod = pow(10, $size);
	$output = sprintf('%0' . $size . 'd/', $no % $mod);
	if($no >= $mod) $output .= getNumberingPath((int) $no / $mod, $size);
	return $output;
}

function url_decode($str) {
	return preg_replace('/%u([[:alnum:]]{4})/', '&#x\\1;', $str);
}

function purifierHtml(&$content) {
	require_once(_XE_PATH_ . 'classes/security/Purifier.class.php');
	$oPurifier = Purifier::getInstance();
	$oPurifier->purify($content);
}

function removeHackTag($content) {
	require_once(_XE_PATH_ . 'classes/security/EmbedFilter.class.php');
	$oEmbedFilter = EmbedFilter::getInstance();
	$oEmbedFilter->check($content);
	purifierHtml($content);
	$content = preg_replace('@<(\/?(?:html|body|head|title|meta|base|link|script|style|applet)(/*).*?>)@i', '&lt;$1', $content);
	$content = preg_replace_callback('@<(/?)([a-z]+[0-9]?)((?>"[^"]*"|\'[^\']*\'|[^>])*?\b(?:on[a-z]+|data|style|background|href|(?:dyn|low)?src)\s*=[\s\S]*?)(/?)($|>|<)@i', 'removeSrcHack', $content);
	$content = checkXmpTag($content);
	$content = blockWidgetCode($content);
	return $content;
}

function blockWidgetCode($content) {
	$content = preg_replace('/(<(?:img|div)(?:[^>]*))(widget)(?:(=([^>]*?)>))/is', '$1blocked-widget$3', $content);
	return $content;
}

function checkUploadedFile($file) {
	require_once(_XE_PATH_ . 'classes/security/UploadFileFilter.class.php');
	return UploadFileFilter::check($file);
}

function checkXmpTag($content) {
	$content = preg_replace('@<(/?)xmp.*?>@i', '<\1xmp>', $content);
	if(($start_xmp = strrpos($content, '<xmp>')) !== FALSE) {
		if(($close_xmp = strrpos($content, '</xmp>')) === FALSE) {
			$content .= '</xmp>';
		} else if($close_xmp < $start_xmp) {
			$content .= '</xmp>';
		}
	}
	return $content;
}

function removeSrcHack($match) {
	$tag = strtolower($match[2]);
	if($tag == 'xmp') return "<{$match[1]}xmp>";
	if($match[1]) return $match[0];
	if($match[4]) $match[4] = ' ' . $match[4];
	$attrs = array();
	if(preg_match_all('/([\w:-]+)\s*=(?:\s*(["\']))?(?(2)(.*?)\2|([^ ]+))/s', $match[3], $m)) {
		foreach($m[1] as $idx => $name) {
			if(strlen($name) >= 2 && substr_compare($name, 'on', 0, 2) === 0) continue;
			$val = preg_replace_callback('/&#(?:x([a-fA-F0-9]+)|0*(\d+));/', function($n) {return chr($n[1] ? ('0x00' . $n[1]) : ($n[2] + 0)); }, $m[3][$idx] . $m[4][$idx]);
			$val = preg_replace('/^\s+|[\t\n\r]+/', '', $val);
			if(preg_match('/^[a-z]+script:/i', $val)) continue;
			$attrs[$name] = $val;
		}
	}
	$filter_arrts = array('style', 'src', 'href');
	if($tag === 'object') array_push($filter_arrts, 'data');
	if($tag === 'param') array_push($filter_arrts, 'value');
	foreach($filter_arrts as $attr) {
		if(!isset($attrs[$attr])) continue;
		$attr_value = rawurldecode($attrs[$attr]);
		$attr_value = htmlspecialchars_decode($attr_value, ENT_COMPAT);
		$attr_value = preg_replace('/\s+|[\t\n\r]+/', '', $attr_value);
		if(preg_match('@(\?|&|;)(act=(\w+))@i', $attr_value, $m) && $m[3] !== 'procFileDownload') unset($attrs[$attr]);
	}
	if(isset($attrs['style']) && preg_match('@(?:/\*|\*/|\n|:\s*expression\s*\()@i', $attrs['style'])) unset($attrs['style']);
	$attr = array();
	foreach($attrs as $name => $val) {
		if($tag == 'object' || $tag == 'embed' || $tag == 'a') {
			$attribute = strtolower(trim($name));
			if($attribute == 'data' || $attribute == 'src' || $attribute == 'href') if(stripos($val, 'data:') === 0) continue;
		}
		if($tag == 'img') {
			$attribute = strtolower(trim($name));
			if(stripos($val, 'data:') === 0) continue;
		}
		$val = str_replace('"', '&quot;', $val);
		$attr[] = $name . "=\"{$val}\"";
	}
	$attr = count($attr) ? ' ' . implode(' ', $attr) : '';
	return "<{$match[1]}{$tag}{$attr}{$match[4]}>";
}

if(!function_exists('hexrgb')) {
	function hexrgb($hexstr) {
		$int = hexdec($hexstr);
		return array('red' => 0xFF & ($int >> 0x10), 'green' => 0xFF & ($int >> 0x8), 'blue' => 0xFF & $int);
	}
}

function mysql_pre4_hash_password($password) {
	$nr = 1345345333;
	$add = 7;
	$nr2 = 0x12345671;
	settype($password, "string");
	for($i = 0; $i < strlen($password); $i++) {
		if($password[$i] == ' ' || $password[$i] == '\t') continue;
		$tmp = ord($password[$i]);
		$nr ^= ((($nr & 63) + $add) * $tmp) + ($nr << 8);
		$nr2 += ($nr2 << 8) ^ $nr;
		$add += $tmp;
	}
	$result1 = sprintf("%08lx", $nr & ((1 << 31) - 1));
	$result2 = sprintf("%08lx", $nr2 & ((1 << 31) - 1));
	if($result1 == '80000000') $nr += 0x80000000;
	if($result2 == '80000000') $nr2 += 0x80000000;
	return sprintf("%08lx%08lx", $nr, $nr2);
}

function getScriptPath() {
	static $url = NULL;
	if($url == NULL) {
		$script_path = filter_var($_SERVER['SCRIPT_NAME'], FILTER_SANITIZE_STRING);
		$url = str_ireplace('/tools/', '/', preg_replace('/index.php.*/i', '', str_replace('\\', '/', $script_path)));
	}
	return $url;
}

function getRequestUriByServerEnviroment() {
	return str_replace('<', '&lt;', $_SERVER['REQUEST_URI']);
}

function utf8RawUrlDecode($source) {
	$decodedStr = '';
	$pos = 0;
	$len = strlen($source);
	while($pos < $len) {
		$charAt = substr($source, $pos, 1);
		if($charAt == '%') {
			$pos++;
			$charAt = substr($source, $pos, 1);
			if($charAt == 'u') {
				$pos++;
				$unicodeHexVal = substr($source, $pos, 4);
				$unicode = hexdec($unicodeHexVal);
				$decodedStr .= _code2utf($unicode);
				$pos += 4;
			} else {
				$hexVal = substr($source, $pos, 2);
				$decodedStr .= chr(hexdec($hexVal));
				$pos += 2;
			}
		} else {
			$decodedStr .= $charAt;
			$pos++;
		}
	}
	return $decodedStr;
}

function _code2utf($num) {
	if($num < 128) return chr($num);
	if($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	if($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	if($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	return '';
}

function detectUTF8($string, $return_convert = FALSE, $urldecode = TRUE) {
	if($urldecode) $string = urldecode($string);
	$sample = iconv('utf-8', 'utf-8', $string);
	$is_utf8 = (md5($sample) === md5($string));
	if(!$urldecode) $string = urldecode($string);
	if($return_convert) return ($is_utf8) ? $string : iconv('euc-kr', 'utf-8', $string);
	return $is_utf8;
}

function json_encode2($data) {
	switch(gettype($data)) {
		case 'boolean': return $data ? 'true' : 'false'; break;
		case 'integer': case 'double': return $data; break;
		case 'string': return '"' . strtr($data, array('\\' => '\\\\', '"' => '\\"')) . '"'; break;
		case 'object': $data = get_object_vars($data); break;
		case 'array':
			$rel = FALSE;
			$key = array_keys($data);
			foreach($key as $v) {
				if(!is_int($v)) {
					$rel = TRUE;
					break;
				}
			}
			$arr = array();
			foreach($data as $k => $v) $arr[] = ($rel ? '"' . strtr($k, array('\\' => '\\\\', '"' => '\\"')) . '":' : '') . json_encode2($v);
			return $rel ? '{' . join(',', $arr) . '}' : '[' . join(',', $arr) . ']';
		break;
		default: return '""'; break;
	}
}

function isCrawler($agent = NULL) {
	if(!$agent) $agent = $_SERVER['HTTP_USER_AGENT'];
	$check_agent = array('bot', 'spider', 'spyder', 'crawl', 'http://', 'google', 'yahoo', 'slurp', 'yeti', 'daum', 'teoma', 'fish', 'hanrss', 'facebook', 'yandex', 'infoseek', 'askjeeves', 'stackrambler');
	$check_ip = array('125.209.230.167', '211.244.82.50' '110.45.243.25', '180.70.134.60', '222.231.41.200');
	foreach($check_agent as $str) if(stristr($agent, $str) != FALSE) return TRUE;
	return IpFilter::filter($check_ip);
}

function stripEmbedTagForAdmin(&$content, $writer_member_srl) {
	if(!Context::get('is_logged')) return;
	$oModuleModel = getModel('module');
	$logged_info = Context::get('logged_info');
	if($writer_member_srl != $logged_info->member_srl && ($logged_info->is_admin == "Y" || $oModuleModel->isSiteAdmin($logged_info))) {
		if($writer_member_srl) {
			$oMemberModel = getModel('member');
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($writer_member_srl);
			if($member_info->is_admin == "Y") return;
		}
		$security_msg = "<div style='border: 1px solid #DDD; background: #FAFAFA; text-align:center; margin: 1em 0;'><p style='margin: 1em;'>" . Context::getLang('security_warning_embed') . "</p></div>";
		$content = preg_replace('/<object[^>]+>(.*?<\/object>)?/is', $security_msg, $content);
		$content = preg_replace('/<embed[^>]+>(\s*<\/embed>)?/is', $security_msg, $content);
		$content = preg_replace('/<img[^>]+editor_component="multimedia_link"[^>]*>(\s*<\/img>)?/is', $security_msg, $content);
	}
	return;
}

function requirePear() {
	static $required = false;
	if($required) return;
	if(version_compare(PHP_VERSION, "5.3.0") < 0) set_include_path(_XE_PATH_ . "libs/PEAR" . PATH_SEPARATOR . get_include_path());
	else set_include_path(_XE_PATH_ . "libs/PEAR.1.9.5" . PATH_SEPARATOR . get_include_path());
	$required = true;
}

function checkCSRF() {
	if($_SERVER['REQUEST_METHOD'] != 'POST') return FALSE;
	$default_url = Context::getDefaultUrl();
	$referer = $_SERVER["HTTP_REFERER"];
	if(strpos($default_url, 'xn--') !== FALSE && strpos($referer, 'xn--') === FALSE) {
		require_once(_XE_PATH_ . 'libs/idna_convert/idna_convert.class.php');
		$IDN = new idna_convert(array('idn_version' => 2008));
		$referer = $IDN->encode($referer);
	}
	$default_url = parse_url($default_url);
	$referer = parse_url($referer);
	$oModuleModel = getModel('module');
	$siteModuleInfo = $oModuleModel->getDefaultMid();
	if($siteModuleInfo->site_srl == 0) {
		if($default_url['host'] !== $referer['host']) return FALSE;
	} else {
		$virtualSiteInfo = $oModuleModel->getSiteInfo($siteModuleInfo->site_srl);
		if(strtolower($virtualSiteInfo->domain) != strtolower(Context::get('vid')) && !strstr(strtolower($virtualSiteInfo->domain), strtolower($referer['host']))) return FALSE;
	}
	return TRUE;
}

function recurciveExposureCheck(&$menu) {
	if(is_array($menu)) {
		foreach($menu AS $key=>$value) {
			if(!$value['isShow']) unset($menu[$key]);
			if(is_array($value['list']) && count($value['list']) > 0) recurciveExposureCheck($menu[$key]['list']);
		}
	}
}

function changeValueInUrl($key, $requestKey, $dbKey, $urlName = 'success_return_url') {
	if($requestKey != $dbKey) {
		$arrayUrl = parse_url(Context::get('success_return_url'));
		if($arrayUrl['query']) {
			parse_str($arrayUrl['query'], $parsedStr);
			if(isset($parsedStr[$key])) {
				$parsedStr[$key] = $requestKey;
				$successReturnUrl .= $arrayUrl['path'].'?'.http_build_query($parsedStr);
				Context::set($urlName, $successReturnUrl);
			}
		}
	}
}

function htmlHeader() {
	echo '<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8" />
</head>
<body>';
}

function htmlFooter() {
	echo '</body></html>';
}

function alertScript($msg) {
	if(!$msg) return;
	echo '<script type="text/javascript">
//<![CDATA[
alert("' . $msg . '");
//]]>
</script>';
}

function closePopupScript() {
	echo '<script type="text/javascript">
//<![CDATA[
window.close();
//]]>
</script>';
}

function reload($isOpener = FALSE) {
	$reloadScript = $isOpener ? 'window.opener.location.reload()' : 'document.location.reload()';
	echo '<script type="text/javascript">
//<![CDATA[
' . $reloadScript . '
//]]>
</script>';
}
