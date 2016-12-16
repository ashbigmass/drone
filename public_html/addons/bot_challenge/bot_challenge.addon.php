<?php
if(!defined('__XE__')) exit();
$self_addon_name = 'bot_challenge';
if( $called_position ==='before_module_init')
	if(empty($addon_info->site_secret)) $addon_info->site_secret = 'a';
	$no_spam_target_act = array(
		'procMemberInsert',
		'procBoardInsertDocument',
		'procBoardInsertComment',
		'procMemberFindAccount',
		'procIssuetrackerInsertIssue',
		'procIssuetrackerInsertHistory',
		'procTextyleInsertComment',
		'procCommunicationSendMessage',
	);
	if($this->act === 'procBot_challengeTest') {
		if(isset($_SESSION[$self_addon_name]->status) === false || $_SESSION[$self_addon_name]->status === true){
			Context::close();
			exit("ERR 0");
		}
		if($_SERVER['HTTP_X_CSRF_PROTECT'] !== $_SESSION[$self_addon_name]->csrf || checkCSRF() !== true )
		{
			Context::close();
			exit("CSRF ERROR");
		}
		$challenge = Context::get('challenge');
		if(empty($challenge)) {
			Context::close();
			exit('ERR 1');
		}
		$algo = strtolower($_SESSION[$self_addon_name]->hash_type);
		if($_SESSION[$self_addon_name]->return_type === 'Hex')
			$server_test = hash_hmac($algo, $_SESSION[$self_addon_name]->challenge, $addon_info->site_secret);
		elseif($_SESSION[$self_addon_name]->return_type === 'Base64')
			$server_test = base64_encode(hash_hmac($algo, $_SESSION[$self_addon_name]->challenge, $addon_info->site_secret, true));
		if($server_test !== $challenge){
			Context::close();
			exit('ERR 2');
		}else{
			$_SESSION[$self_addon_name]->status = true;
			Context::close();
			exit('success');
		}
	} elseif( (isset($_SESSION[$self_addon_name]->status) === false || $_SESSION[$self_addon_name]->status === false) &&
		(in_array($this->act,$no_spam_target_act) === true))
	{
		context::close();
		header('x-anti-spam: spam blocked',true, 500);
		echo('<h1> 500 Internal ERROR XE</h1><h3>please contact admin</h3>');
		exit();
	}
} elseif($called_position === 'before_display_content')
	if(empty($addon_info->site_secret)) $addon_info->site_secret ='a';
	if($addon_info->contribute === 'Y' &&
		(Context::get('is_logged') === true && isset(Context::get('logged_info')->is_admin) && Context::get('logged_info')->is_admin === 'Y') === false )
	{
		Context::addHtmlFooter('<script>(function(a,b,c,d,e){function f(){var a=b.createElement("script");a.async=!0;a.src="//radar.cedexis.com/1/11475/radar.js";b.body.appendChild(a)}/\bMSIE 6/i.test(a.navigator.userAgent)||(a[c]?a[c](e,f,!1):a[d]&&a[d]("on"+e,f))})(window,document,"addEventListener","attachEvent","load");</script>');
	}
	if (isset($_SESSION[$self_addon_name]->status) === true && $_SESSION[$self_addon_name]->status === true){
		Context::addHtmlHeader("<!-- T-S -->");
		return;
	}
	if (isset($_SESSION[$self_addon_name]) === false) {
		$_SESSION[$self_addon_name] = new stdClass();
		$_SESSION[$self_addon_name]->status = false;
		$temp = unpack('S*', openssl_random_pseudo_bytes(3));
		$what_hash = $temp[1] % 4;
		$temp = unpack('S*', openssl_random_pseudo_bytes(3));
		$what_return_type = $temp[1] % 2;
		$temp = unpack('S*', openssl_random_pseudo_bytes(3));
		$what_length = ($temp[1] % 60) + 20;
		if (mt_rand(0, 1) === 1) {
			$what_challenge = base64_encode(openssl_random_pseudo_bytes($what_length));
		} else {
			$temp =unpack('H*',openssl_random_pseudo_bytes($what_length));
			$what_challenge = $temp[1];
		}
		switch ($what_hash) {
			case 0:
				$what_hash_string = 'MD5';
				break;
			case 1;
				$what_hash_string = 'SHA1';
				break;
			case 2;
				$what_hash_string = 'SHA256';
				break;
			case 3;
				$what_hash_string = 'SHA512';
				break;
		}
		switch ($what_return_type) {
			case 0: $what_return_type_string = 'Base64'; break;
			case 1: $what_return_type_string = 'Hex'; break;
		}
		$csrf = str_replace(array('+','/','_'),array('-','_',''),base64_encode(openssl_random_pseudo_bytes(15)));
		$_SESSION[$self_addon_name]->csrf = $csrf;
		$_SESSION[$self_addon_name]->hash_type = $what_hash_string;
		$_SESSION[$self_addon_name]->return_type = $what_return_type_string;
		$_SESSION[$self_addon_name]->challenge = $what_challenge;
	}
	if(isset($_SESSION[$self_addon_name]->status) === true &&  $_SESSION[$self_addon_name]->status !== true) {
		$csrf = $_SESSION[$self_addon_name]->csrf;
		$what_hash_string = $_SESSION[$self_addon_name]->hash_type;
		$what_return_type_string = $_SESSION[$self_addon_name]->return_type;
		$what_challenge = $_SESSION[$self_addon_name]->challenge;
		$request_uri = Context::getRequestUri();
		$backup_url = $request_uri.'addons/bot_challenge/backup.js';
		$site_secret = $addon_info->site_secret;
		$js = <<<EOT
		<script> if(typeof CryptoJS === 'undefined'){document.write(decodeURI('%3Cscript%20src=%22$backup_url%22%3E%3C/script%3E'));};</script><script>jQuery.ajax('$request_uri', s = {data : jQuery.param({ 'act' : 'procBot_challengeTest', 'challenge' : CryptoJS.enc.$what_return_type_string.stringify(CryptoJS.Hmac$what_hash_string("$what_challenge","$site_secret"))}),dataType  : 'json',type : 'post',headers :{ 'X-CSRF-Protect' : '$csrf'}});</script>
EOT;
		Context::loadFile(array('https://cdn.jsdelivr.net/crypto-js/3.1.2/components/core-min.js','head','',1));
		Context::loadFile(array('https://cdn.jsdelivr.net/crypto-js/3.1.2/components/enc-base64-min.js','head','',1));
		Context::loadFile(array('https://cdn.jsdelivr.net/crypto-js/3.1.2/rollups/hmac-md5.js','head','',1));
		Context::loadFile(array('https://cdn.jsdelivr.net/crypto-js/3.1.2/rollups/hmac-sha1.js','head','',1));
		Context::loadFile(array('https://cdn.jsdelivr.net/crypto-js/3.1.2/rollups/hmac-sha256.js','head','',1));
		Context::loadFile(array('https://cdn.jsdelivr.net/crypto-js/3.1.2/rollups/hmac-sha512.js','head','',1));
		Context::addHtmlHeader($js);
	}
}