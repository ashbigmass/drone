<?php
class ggmailingAdminController extends ggmailing
{
	function init() {
	}

	function curl_request_async($url, $params, $type='POST', $output) {
		foreach ($params as $key => &$val) {
			if (is_array($val)) $val = implode(',', $val);
			$post_params[] = $key.'='.urlencode($val);
		}
		$post_string = implode('&', $post_params);
		$parts=parse_url($url);
		if ($parts['scheme'] == 'http') $fp = fsockopen($parts['host'], isset($parts['port'])?$parts['port']:80, $errno, $errstr, 30);
		elseif ($parts['scheme'] == 'https') $fp = fsockopen("ssl://" . $parts['host'], isset($parts['port'])?$parts['port']:443, $errno, $errstr, 30);
		if('GET' == $type) $parts['path'] .= '?'.$post_string;
		$out = "$type ".$parts['path']." HTTP/1.1\r\n";
		$out.= "Host: ".$parts['host']."\r\n";
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out.= "Content-Length: ".strlen($post_string)."\r\n";
		$out.= "Connection: Close\r\n\r\n";
		if ('POST' == $type && isset($post_string)) $out.= $post_string;
		fwrite($fp, $out);
		if($output == 'json') {
			while (!feof($fp)) $buffer .= fread($fp,1024);
			if($buffer) {
				$pos = strpos($buffer, "\r\n\r\n");
				$buffer = substr($buffer, $pos + 4);
				return $buffer;
			}
		}
		fclose($fp);
	}

	function procGgmailingAdminSmsAllSendOk() {
		$args = Context::getRequestVars();
		$args->is_sendok = 'N';
		$output = executeQueryArray('ggmailing.getGgmailingAdminSmsSmsSend',$args);
		if(!$output->toBool()) return $output;
		foreach($output->data as $key => $val) {
			$ggmailing_sms_send_srl = $val->ggmailing_sms_send_srl;
			$this->procGgmailingAdminSmsSend($ggmailing_sms_send_srl,null);
		}
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsSend','page',$args->page);
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSmsSendOk() {
		$args = Context::getRequestVars();
		$ggmailing_sms_send_srl = $args->ggmailing_sms_send_srl;
		$this->procGgmailingAdminSmsSend($ggmailing_sms_send_srl,'W');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsSend','page',$args->page);
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSmsSend($ggmailing_sms_send_srl,$is_sendok = 'W') {
		$args->ggmailing_sms_send_srl = $ggmailing_sms_send_srl;
		$output = executeQueryArray('ggmailing.getGgmailingAdminSmsSmsSend',$args);
		if(!$output->toBool()) return $output;
		$wwoutput = $output->data[0];
		$args->is_sendok = $is_sendok?$is_sendok:'W';
		$wwoutput->DEST_PHONE = substr($wwoutput->ggmailing_sms_receive_number,0,-1);
		$wwoutput->DEST_NAME = substr($wwoutput->ggmailing_sms_receive_nickname,0,-1);
		$wwoutput->SEND_PHONE = $wwoutput->ggmailing_sms_sender_number;
		$wwoutput->SEND_NAME = $wwoutput->ggmailing_sms_sender_nickname;
		$wwoutput->MSG_BODY = nl2br(strip_tags($wwoutput->ggmailing_sms_content));
		$mms_file1 = _XE_PATH_."files/ggmailing/mms/".$wwoutput->ggmailing_sms_document_srl."_mms_file1.jpg";
		if(fopen($mms_file1,'r')) $mms_file1_ok = "@".$mms_file1.";type=image/jpeg";
		$mms_file2 = _XE_PATH_."files/ggmailing/mms/".$wwoutput->ggmailing_sms_document_srl."_mms_file2.jpg";
		if(fopen($mms_file2,'r')) $mms_file2_ok = "@".$mms_file2.";type=image/jpeg";
		$mms_file3 = _XE_PATH_."files/ggmailing/mms/".$wwoutput->ggmailing_sms_document_srl."_mms_file3.jpg";
		if(fopen($mms_file3,'r')) $mms_file3_ok = "@".$mms_file3.";type=image/jpeg";
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ggmailing');
		$i_authkey = $config->ggmailing_authkey;
		$ggmailing_serv_url = $config->ggmailing_serv_url;
		if($config->ggmailing_ssl == 'N' || !$config->ggmailing_ssl) { $ggmailing_ssl = 'http://'; $ggmailing_ssl_port = ''; } elseif($config->ggmailing_ssl == 'Y') { $ggmailing_ssl = 'https://'; $ggmailing_ssl_port = ':' . $config->ggmailing_ssl_port; }
		$url = $ggmailing_ssl . $ggmailing_serv_url . $ggmailing_ssl_port . '/index.php';
		$i_mid = 'auth_woorimail';
		$i_act = 'procWwapimanagerSmsInsertSenderData';
		$post_data = array(
				"mms_file1" => $mms_file1_ok,
				"mms_file2" => $mms_file2_ok,
				"mms_file3" => $mms_file3_ok,
				"authkey" => $i_authkey,
				"mid" => $i_mid,
				"act" => $i_act,
				"type" => 'ggmailing',
				"wwsms_srl" => $wwoutput->ggmailing_sms_document_srl,
				"wwsms_send_srl" => $wwoutput->ggmailing_sms_send_srl,
				"wwsms_flag" => 'W',
				"MSG_TYPE" => $wwoutput->MSG_TYPE,
				"REQUEST_TIME" => $wwoutput->REQUEST_TIME,
				"SEND_TIME" => $wwoutput->SEND_TIME,
				"REPORT_TIME" => $wwoutput->REPORT_TIME,
				"DEST_PHONE" => $wwoutput->DEST_PHONE,
				"DEST_NAME" => $wwoutput->DEST_NAME,
				"SEND_PHONE" => $wwoutput->SEND_PHONE,
				"SEND_NAME" => $wwoutput->SEND_NAME,
				"SUBJECT" => $wwoutput->SUBJECT,
				"MSG_BODY" => str_replace('<br />','\n',$wwoutput->MSG_BODY),
				"WAP_URL" => $wwoutput->WAP_URL,
				"COVER_FLAG" => $wwoutput->COVER_FLAG,
				"SMS_FLAG" => $wwoutput->SMS_FLAG,
				"REPLY_FLAG" => $wwoutput->REPLY_FLAG,
				"REPLY_CNT" => $wwoutput->REPLY_CNT,
				"FAX_FILE" => $wwoutput->FAX_FILE,
				"VXML_FILE" => $wwoutput->VXML_FILE,
				"USE_PAGE" => $wwoutput->USE_PAGE,
				"is_sendok" => $args->is_sendok
			);
		$curl = $this->curl_request_async($url, $post_data, $type='POST', $output='');
		$args->is_sendok = 'W';
		if($authcheck) executeQuery('ggmailing.updateGgmailingAdminSmsSend',$args);
	}

	function procGgmailingAdminAllSendOk() {
		$args = Context::getRequestVars();
		$args->is_sendok = 'N';
		$output = executeQueryArray('ggmailing.getGgmailingAdminSendEmail',$args);
		if(!$output->toBool()) return $output;
		foreach($output->data as $key => $val) {
			$ggmailing_send_srl = $val->ggmailing_send_srl;
			$this->procGgmailingAdminSend($ggmailing_send_srl,null);
		}
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend','page',$args->page);
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSendOk() {
		$args = Context::getRequestVars();
		$ggmailing_send_srl = $args->ggmailing_send_srl;
		$this->procGgmailingAdminSend($ggmailing_send_srl,'G');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend','page',$args->page);
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSend($ggmailing_send_srl,$is_sendok = 'W') {
		$args->ggmailing_send_srl = $ggmailing_send_srl;
		$output = executeQueryArray('ggmailing.getGgmailingAdminSendEmail',$args);
		if(!$output->toBool()) return $output;
		$wwoutput = $output->data[0];
		if($wwoutput->ggmailing_sender_email == 'NOREPLY@woorimail.com') $wwoutput->type_replymail = 'F';
		elseif($wwoutput->ggmailing_sender_email == 'SERVICE@woorimail.com') $wwoutput->type_replymail = 'Y';
		else $wwoutput->type_replymail = 'G';
		$args->is_sendok = $is_sendok?$is_sendok:'W';
		$sep_countmail = explode(',',substr(trim($wwoutput->ggmailing_receive_email),0,-1));
		$countmail->gmail = 0;$countmail->naver = 0;$countmail->daum = 0;$countmail->hanmail = 0;$countmail->yahoo = 0;$countmail->hotmail = 0;$countmail->etc = 0;$countmail->nate = 0;
		for($i=0;$i<count($sep_countmail);$i++) {
			$sep_domain = explode('@',$sep_countmail[$i]);
			if($sep_domain[1] == 'gmail.com') $countmail->gmail = $countmail->gmail+1;
			elseif($sep_domain[1] == 'naver.com') $countmail->naver = $countmail->naver+1;
			elseif($sep_domain[1] == 'daum.net') $countmail->daum = $countmail->daum+1;
			elseif($sep_domain[1] == 'hanmail.net') $countmail->hanmail = $countmail->hanmail+1;
			elseif($sep_domain[1] == 'yahoo.com') $countmail->yahoo = $countmail->yahoo+1;
			elseif($sep_domain[1] == 'hotmail.com') $countmail->hotmail = $countmail->hotmail+1;
			elseif($sep_domain[1] == 'nate.com') $countmail->nate = $countmail->nate+1;
			else $countmail->etc = $countmail->etc+1;
		}
		$wwoutput->ggmailing_countmail = 'gmail,'.$countmail->gmail.'|@|naver,'.$countmail->naver.'|@|daum,'.$countmail->daum.'|@|hanmail,'.$countmail->hanmail.'|@|yahoo,'.$countmail->yahoo.'|@|hotmail,'.$countmail->hotmail.'|@|nate,'.$countmail->nate.'|@|etc,'.$countmail->etc;
		$countmail = '';
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ggmailing');
		$i_authkey = $config->ggmailing_authkey;
		$args->temp_is_nickemail = explode('@',$wwoutput->ggmailing_sender_email);
		$obj->ggmailing_is_nick = $args->is_nick?$args->is_nick:$args->temp_is_nickemail[0];
		$obj->ggmailing_is_domain = $args->is_domain?$args->is_domain:$args->temp_is_nickemail[1];
		$gw_obj->ggmailing_document_srl = $wwoutput->ggmailing_document_srl;
		$gw_output = executeQueryArray('ggmailing.getGateway',$gw_obj);
		$wwoutput->ggmailing_is_nick = $gw_output->data[0]->ggmailing_is_nick?$gw_output->data[0]->ggmailing_is_nick:$obj->ggmailing_is_nick;
		$wwoutput->ggmailing_is_domain = $gw_output->data[0]->ggmailing_is_domain?$gw_output->data[0]->ggmailing_is_domain:$obj->ggmailing_is_domain;
		$ggmailing_serv_url = $config->ggmailing_serv_url;
		if($config->ggmailing_ssl == 'N' || !$config->ggmailing_ssl) { $ggmailing_ssl = 'http://'; $ggmailing_ssl_port = ''; } elseif($config->ggmailing_ssl == 'Y') { $ggmailing_ssl = 'https://'; $ggmailing_ssl_port = ':' . $config->ggmailing_ssl_port; }
		$url = $ggmailing_ssl . $ggmailing_serv_url . $ggmailing_ssl_port . '/index.php';
		$i_mid = 'auth_woorimail';
		$i_act = 'procWwapimanagerInsertSenderData';
		$post_data = array(
				"authkey" => $i_authkey,
				"mid" => $i_mid,
				"act" => $i_act,
				"ggmailing_send_srl" => $wwoutput->ggmailing_send_srl,
				"ggmailing_document_srl" => $wwoutput->ggmailing_document_srl,
				"ggmailing_title" => $wwoutput->ggmailing_title,
				"ggmailing_content" => $wwoutput->ggmailing_content,
				"ggmailing_sender_nickname" => $wwoutput->ggmailing_sender_nickname,
				"ggmailing_sender_email" => $wwoutput->ggmailing_sender_email,
				"ggmailing_sender_flag" => $wwoutput->type_replymail,
				"ggmailing_receive_nickname" => substr($wwoutput->ggmailing_receive_nickname,0,-1),
				"ggmailing_receive_email" => substr($wwoutput->ggmailing_receive_email,0,-1),
				"ggmailing_countmail" => $wwoutput->ggmailing_countmail,
				"ggmailing_member_regdate" => substr($wwoutput->ggmailing_member_regdate,0,-1),
				"ggmailing_is_nick" => $wwoutput->ggmailing_is_nick,
				"ggmailing_is_domain" => $wwoutput->ggmailing_is_domain,
				"type_donotsend" => $config->type_donotsend,
				"is_sendok" => $args->is_sendok
			);
		$curl = $this->curl_request_async($url, $post_data, $type='POST', $output='');
		$args->is_sendok = 'W';
		executeQuery('ggmailing.updateGgmailingAdminSend',$args);
	}

	function procGgmailingAdminModuleConfig() {
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ggmailing');
		$args = Context::getRequestVars();
		unset($config);
		foreach($args as $key => $val) if($val) $config->{$key} = trim($val);
		$config->sms_sender_phone = str_replace('-','',$config->sms_sender_phone);
		$config->sms_sender_phone = str_replace('.','',$config->sms_sender_phone);
		$config->sms_sender_phone = str_replace(',','',$config->sms_sender_phone);
		$config->sms_sender_phone = str_replace(' ','',$config->sms_sender_phone);
		$oModuleController = &getController('module');
		$oModuleController->insertModuleConfig('ggmailing', $config);
		$returnUrl = getNotEncodedUrl('','module','admin','act','dispGgmailingAdminConfig','mode','save_ok');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminInsert() {
		$args = Context::getRequestVars();

		if($args->primary_key) $obj->document_srl = $args->primary_key;
		else $obj->document_srl = getNextSequence();
$args->cx = '<br /><br /><div style="border:1px solid #ccc;padding:5px;">귀하의 메일주소는 {member_regdate}, '.getFullUrl('').' 에서 취득하였습니다.
메일 수신을 원하지 않으시면, <a href="'.getFullUrl('').'?act=dispGgmailingDonotsend&email={email}&regdate={member_regdate}&nick_name={nickname}">[수신거부]</a>를 눌러주십시오. 수신거부처리가 이루어집니다.
(If you don’t want to receive this e-mail anymore, click <a href="'.getFullUrl('').'?act=dispGgmailingDonotsend&email={email}&regdate={member_regdate}&nick_name={nickname}">[here]</a>)</div>';
$args->co = '<br /><br /><div style="border:1px solid #ccc;padding:5px;">{nickname}님은 {member_regdate} '.getFullUrl('').' 에서 광고 수신에 동의하셨습니다.
광고 수신을 원하지 않으시면, <a href="'.getFullUrl('').'?act=dispGgmailingDonotsend&email={email}&regdate={member_regdate}&nick_name={nickname}">[수신거부]</a>를 눌러주십시오. 수신거부처리가 이루어집니다.
(If you don’t want to receive this e-mail anymore, click <a href="'.getFullUrl('').'?act=dispGgmailingDonotsend&email={email}&regdate={member_regdate}&nick_name={nickname}">[here]</a>)</div>';
		$obj->title = $args->title;
		if($args->type_donotsend == 'cx') $args->type_donotsend = $args->cx;
		if($args->type_donotsend == 'co') $args->type_donotsend = $args->co;
		if($args->type_donotsend == 'none') $args->type_donotsend = '';
		$obj->content = $args->content . $args->type_donotsend;
		$obj->sender_nickname = $args->sender_nickname?$args->sender_nickname:'설정없음';
		if($args->type_replymail == 'Y') $obj->sender_email = 'SERVICE@woorimail.com';
		elseif($args->type_replymail == 'F') $obj->sender_email = 'NOREPLY@woorimail.com';
		elseif($args->type_replymail == 'G') $obj->sender_email = $args->ggmailing_sender_email;
		else $obj->sender_email = 'NOREPLY@woorimail.com';
		executeQuery('ggmailing.insertGgmailingAdminList',$obj);
		$args->temp_is_nickemail = explode('@',$obj->sender_email);
		$obj->ggmailing_is_nick = $args->is_nick?$args->is_nick:$args->temp_is_nickemail[0];
		$obj->ggmailing_is_domain = $args->is_domain?$args->is_domain:$args->temp_is_nickemail[1];
		$output = executeQuery('ggmailing.insertGgmailingAdminGateway',$obj);
		$args->isvalid = 'Y';
		$args->upload_target_srl = $obj->document_srl;
		executeQuery('file.updateFileValid',$args);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminList');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminUpdate() {
		$args = Context::getRequestVars();
		$obj->document_srl = $args->primary_key;
		$obj->title = $args->title;
		$obj->content = $args->content;
		$obj->sender_nickname = $args->sender_nickname?$args->sender_nickname:'설정없음';
		if($args->type_replymail == 'Y') $obj->sender_email = 'SERVICE@woorimail.com';
		elseif($args->type_replymail == 'F') $obj->sender_email = 'NOREPLY@woorimail.com';
		elseif($args->type_replymail == 'G') $obj->sender_email = $args->ggmailing_sender_email;
		else $obj->sender_email = 'NOREPLY@woorimail.com';
		executeQuery('ggmailing.updateGgmailingAdminList',$obj);
		$args->temp_is_nickemail = explode('@',$obj->sender_email);
		$obj->ggmailing_is_nick = $args->is_nick?$args->is_nick:$args->temp_is_nickemail[0];
		$obj->ggmailing_is_domain = $args->is_domain?$args->is_domain:$args->temp_is_nickemail[1];
		$output = executeQuery('ggmailing.insertGgmailingAdminGateway',$obj);
		$args->isvalid = 'Y';
		$args->upload_target_srl = $obj->document_srl;
		executeQuery('file.updateFileValid',$args);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminList');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSmsInsert() {
		$args = Context::getRequestVars();
		if($args->primary_key) $obj->ggmailing_sms_document_srl = $args->primary_key;
		else $obj->ggmailing_sms_document_srl = getNextSequence();
		$obj->ggmailing_sms_content = nl2br(strip_tags($args->ggmailing_sms_content));
		$obj->ggmailing_sms_sender_number = $args->ggmailing_sms_sender_number;
		$obj->ggmailing_sms_sender_nickname = $args->ggmailing_sms_sender_nickname;
		executeQuery('ggmailing.insertGgmailingAdminSmsList',$obj);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsList');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSmsUpdate() {
		$args = Context::getRequestVars();
		$obj->ggmailing_sms_document_srl = $args->ggmailing_sms_document_srl;
		$obj->ggmailing_sms_content = nl2br(strip_tags($args->ggmailing_sms_content));
		$obj->ggmailing_sms_sender_number = $args->ggmailing_sms_sender_number;
		$obj->ggmailing_sms_sender_nickname = $args->ggmailing_sms_sender_nickname;
		executeQuery('ggmailing.updateGgmailingAdminSmsList',$obj);
		if($_FILES['mms_file1']['tmp_name'] && $_FILES['mms_file1']['type'] == 'image/jpeg') $this->UploadMmsImage($args->ggmailing_sms_document_srl,'mms_file1',$_FILES);
		if($_FILES['mms_file2']['tmp_name'] && $_FILES['mms_file2']['type'] == 'image/jpeg') $this->UploadMmsImage($args->ggmailing_sms_document_srl,'mms_file2',$_FILES);
		if($_FILES['mms_file3']['tmp_name'] && $_FILES['mms_file3']['type'] == 'image/jpeg') $this->UploadMmsImage($args->ggmailing_sms_document_srl,'mms_file3',$_FILES);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsList');
		header("Location:" . $returnUrl);
	}

	function UploadMmsImage($document_srl, $ggname, $gg_FILES) {
		@ mkdir('files/ggmailing/');
		@ chmod('files/ggmailing/',0755);
		@ mkdir('files/ggmailing/mms/');
		@ chmod('files/ggmailing/mms/',0755);
		$target_path = 'files/ggmailing/mms/';
		if(!$gg_FILES[$ggname]['tmp_name'] || $gg_FILES[$ggname]['type'] != 'image/jpeg') {
			$returnUrl = getNotEncodedUrl('','module','admin','act','dispGgmailingAdminSmsList');
			header("Location:" . $returnUrl);
			return;
		}
		$file_tmp_name = $gg_FILES[$ggname]['tmp_name'];
		$file_name = $document_srl.'_'.$ggname.'.jpg';
		$file_path = $target_path . $file_name;
		@ unlink($file_path);
		@ move_uploaded_file($file_tmp_name, $file_path);
		if($gg_FILES[$ggname]['size'] > 20480) FileHandler::createImageFile($file_path, $file_path, '176', '144', 'jpg', 'ratio');
		@ chmod($file_path,0707);
		$single_size = 40 * 1024;
		$multi_size = 60 * 1024;
		$mms_file1 = _XE_PATH_."files/ggmailing/mms/".$document_srl."_mms_file1.jpg";
		$mms_file2 = _XE_PATH_."files/ggmailing/mms/".$document_srl."_mms_file2.jpg";
		$mms_file3 = _XE_PATH_."files/ggmailing/mms/".$document_srl."_mms_file3.jpg";
		if(filesize($file_path) > $single_size) @ unlink($file_path);
		if((filesize($mms_file1)+filesize($mms_file2)+filesize($mms_file3)) > $multi_size) @ unlink($file_path);
	}

	function procGgmailingAdminList() {
		$args = Context::getRequestVars();
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ggmailing');
		if($config->sender_num > 0) {
			$num = $config->sender_num;
		} else {
			$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminConfig','mode','no_num');
			header("Location:" . $returnUrl);
		}
		$obj->document_srl = $args->ggmailing_document_srl;
		$obj->title = $args->title;
		$obj->content = $args->content;
		$obj->sender_nickname = $args->sender_nickname;
		$obj->sender_email = $args->sender_email;
		$output = executeQueryArray('member.getGroup',$args);
		foreach($output->data as $key => $val) {
			$args->ggmailing_group = $val->title;
			$mg = explode('_',$args->ggmailing_group);
		}
		if($mg[0]=='m') {
			$args->list_count = 9999999;
			$output = executeQueryArray('ggmailing.getGgmailingAdminMemberList',$args);
			if(!$output->toBool()) return $output;
			if(!$output->data) {
				$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend','mode','no_member');
				header("Location:" . $returnUrl);
			}
			foreach($output->data as $key => $val) {
				$obj->ggmailing_nickname = str_replace(',','.',$val->ggmailing_nickname);
				$obj->ggmailing_email = str_replace(',','.',$val->ggmailing_email);
				$obj->ggmailing_member_regdate = str_replace(',','.',$val->ggmailing_regdate);
				$ggoutput = executeQueryArray('ggmailing.getDonotsend',$obj);
				if(!$ggoutput->data && $val->ggmailing_nickname && $val->ggmailing_email) {
					$obj->receive_nickname .= str_replace(',','.',$val->ggmailing_nickname) . ',';
					$obj->receive_email .= str_replace(',','.',$val->ggmailing_email) . ',';
					$obj->receive_member_regdate .= str_replace(',','.',$val->ggmailing_regdate) . ',';
					if((($key+1) % $num == 0) && $num) {
						executeQuery('ggmailing.insertGgmailingAdminSend',$obj);
						$obj->receive_nickname = '';
						$obj->receive_email = '';
						$obj->receive_member_regdate = '';
					}
				}
			}
			if($num && ($obj->receive_nickname != '' || $obj->receive_email != '' || $obj->receive_member_regdate != '')) executeQuery('ggmailing.insertGgmailingAdminSend',$obj);
		} elseif($args->group_srl == 'all') {
			if($args->allow_mailing == 'Y') {
				$output = executeQueryArray('ggmailing.getEmailAddrAllowList',$args);
				if(!$output->toBool()) return $output;
			} else {
				$output = executeQueryArray('ggmailing.getEmailAddrList',$args);
				if(!$output->toBool()) return $output;
			}
			if(!$output->data) {
				$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend','mode','no_member');
				header("Location:" . $returnUrl);
			}
			foreach($output->data as $key => $val) {
				$obj->ggmailing_nickname = str_replace(',','.',$val->nick_name);
				$obj->ggmailing_email = str_replace(',','.',$val->email_address);
				$obj->ggmailing_member_regdate = str_replace(',','.',$val->regdate);
				$ggoutput = executeQueryArray('ggmailing.getDonotsend',$obj);
				if(!$ggoutput->data && $val->nick_name && $val->email_address) {
					$obj->receive_nickname .= str_replace(',','.',$val->nick_name) . ',';
					$obj->receive_email .= str_replace(',','.',$val->email_address) . ',';
					$obj->receive_member_regdate .= str_replace(',','.',$val->regdate) . ',';
					if((($key+1) % $num == 0) && $num) {
						executeQuery('ggmailing.insertGgmailingAdminSend',$obj);
						$obj->receive_nickname = '';
						$obj->receive_email = '';
						$obj->receive_member_regdate = '';
					}
				}
			}
			if($num && ($obj->receive_nickname != '' || $obj->receive_email != '')) executeQuery('ggmailing.insertGgmailingAdminSend',$obj);
		} else {
			if($args->allow_mailing == 'Y') {
				$output = executeQueryArray('ggmailing.getGroupEmailAddrAllowList',$args);
				if(!$output->toBool()) return $output;
			} else {
				$output = executeQueryArray('ggmailing.getGroupEmailAddrList', $args);
				if(!$output->toBool()) return $output;
			}
			if(!$output->data) {
				$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend','mode','no_member');
				header("Location:" . $returnUrl);
			}
			foreach($output->data as $key => $val) {
				$obj->ggmailing_nickname = str_replace(',','.',$val->nick_name);
				$obj->ggmailing_email = str_replace(',','.',$val->email_address);
				$obj->ggmailing_member_regdate = str_replace(',','.',$val->regdate);
				$ggoutput = executeQueryArray('ggmailing.getDonotsend',$obj);
				if(!$ggoutput->data && $val->nick_name && $val->email_address) {
					$obj->receive_nickname .= $val->nick_name . ',';
					$obj->receive_email .= $val->email_address . ',';
					$obj->receive_member_regdate .= $val->regdate . ',';
					if((($key+1) % $num == 0) && $num) {
						executeQuery('ggmailing.insertGgmailingAdminSend',$obj);
						$obj->receive_nickname = '';
						$obj->receive_email = '';
						$obj->receive_member_regdate = '';
					}
				}
			}
			if($num && ($obj->receive_nickname != '' || $obj->receive_email != '')) executeQuery('ggmailing.insertGgmailingAdminSend',$obj);
		}
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminSmsList() {
		$args = Context::getRequestVars();
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('ggmailing');
		if($config->sender_num > 0) $num = $config->sender_num;
		else {
			$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminConfig','mode','no_num');
			header("Location:" . $returnUrl);
		}
		$obj->ggmailing_sms_document_srl = $args->ggmailing_sms_document_srl;
		$obj->ggmailing_sms_content = $args->ggmailing_sms_content;
		$obj->ggmailing_sms_sender_nickname = $args->ggmailing_sms_sender_nickname;
		$obj->ggmailing_sms_sender_number = $args->ggmailing_sms_sender_number;
		$output = executeQueryArray('member.getGroup',$args);
		foreach($output->data as $key => $val) {
			$args->ggmailing_group = $val->title;
			$mg = explode('_',$args->ggmailing_group);
		}
		if($mg[0]=='s') {
			$args->list_count = 9999999;
			$output = executeQueryArray('ggmailing.getGgmailingAdminMemberList',$args);
			if(!$output->toBool()) return $output;
			if(!$output->data) {
				$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsSend','mode','no_member');
				header("Location:" . $returnUrl);
			}
			foreach($output->data as $key => $val) {
				if($val->ggmailing_nickname && $val->ggmailing_email && $val->ggmailing_regdate) {
					$obj->ggmailing_sms_receive_nickname .= str_replace(',','.',$val->ggmailing_nickname) . ',';
					$obj->ggmailing_sms_receive_number .= str_replace(',','.',$val->ggmailing_email) . ',';
					$obj->ggmailing_sms_member_regdate .= str_replace(',','.',$val->ggmailing_regdate) . ',';
					if((($key+1) % $num == 0) && $num) {
						executeQuery('ggmailing.insertGgmailingAdminSmsSend',$obj);
						$obj->ggmailing_sms_sender_nickname = '';
						$obj->ggmailing_sms_sender_number = '';
						$obj->ggmailing_sms_member_regdate = '';
					}
				}
			}
			if($num && ($obj->ggmailing_sms_sender_nickname != '' || $obj->ggmailing_sms_sender_number != '' || $obj->ggmailing_sms_member_regdate != '')) executeQuery('ggmailing.insertGgmailingAdminSmsSend',$obj);
		}
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsSend');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminDel() {
		$args = Context::getRequestVars();
		if($args->ggmailing_document_srl) {
			executeQuery('ggmailing.deleteGgmailingAdminList',$args);
			executeQuery('ggmailing.deleteGgmailingAdminGateway',$args);
			$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminList');
			header("Location:" . $returnUrl);
		}
		if($args->ggmailing_send_srl) {
			executeQuery('ggmailing.deleteGgmailingAdminSend',$args);
			$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSend');
			header("Location:" . $returnUrl);
		}
		if($args->ggmailing_sms_document_srl) {
			$mms_file1 = _XE_PATH_."files/ggmailing/mms/".$args->ggmailing_sms_document_srl."_mms_file1.jpg";
			$mms_file2 = _XE_PATH_."files/ggmailing/mms/".$args->ggmailing_sms_document_srl."_mms_file2.jpg";
			$mms_file3 = _XE_PATH_."files/ggmailing/mms/".$args->ggmailing_sms_document_srl."_mms_file3.jpg";
			if(!$args->ggstatus) {
				executeQuery('ggmailing.deleteGgmailingAdminSmsList',$args);
				@ unlink($mms_file1);
				@ unlink($mms_file2);
				@ unlink($mms_file3);
				$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsList');
			} elseif($args->ggstatus) {
				if($args->ggstatus == 'ggfiledel1') @ unlink($mms_file1);
				if($args->ggstatus == 'ggfiledel2') @ unlink($mms_file2);
				if($args->ggstatus == 'ggfiledel3') @ unlink($mms_file3);
				$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsInsert','ggmailing_sms_document_srl',$args->ggmailing_sms_document_srl,'ggstatus','ggimage');
			}
			header("Location:" . $returnUrl);
		}
		if($args->ggmailing_sms_send_srl) {
			executeQuery('ggmailing.deleteGgmailingAdminSmsSend',$args);
			$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminSmsSend');
			header("Location:" . $returnUrl);
		}
	}

	function procGgmailingAdminMemberInsert() {
		$args = Context::getRequestVars();
		@ mkdir('files/ggmailing/');
		@ chmod('files/ggmailing/',0755);
		@ mkdir('files/ggmailing/uploads/');
		@ chmod('files/ggmailing/uploads/',0755);
		$target_path = 'files/ggmailing/uploads/';
		if(!$_FILES['uploadedfile']['tmp_name']) {
			$returnUrl = getNotEncodedUrl('','module','admin','act','dispGgmailingAdminInsertmembers');
			header("Location:" . $returnUrl);
			return;
		}
		FileHandler::removeDir('files/ggmailing/uploads/tmp/');
		@ mkdir($target_path. 'tmp/');
		$file_tmp_name = $_FILES['uploadedfile']['tmp_name'];
		$file_name = basename($_FILES['uploadedfile']['name']);
		$file_name = sha1($file_name) . ".xls";
		$file_path = $target_path . 'tmp/' .$file_name;
		@ move_uploaded_file($file_tmp_name, $file_path);
		@ chmod($file_path,0755);
		include 'modules/ggmailing/classes/PHPExcel/IOFactory.php';
		$inputFileName = $file_path;
		$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
		$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
		for($i=1; $sheetData[$i]; $i++) {
			$args->ggmailing_nickname = str_replace(',','.',$sheetData[$i][A]);
			$args->ggmailing_email = str_replace(',','.',$sheetData[$i][B]);
			$args->ggmailing_group = str_replace(',','.',$sheetData[$i][C]);
			$checkInfos = array('ggmailing_nickname', 'ggmailing_email', 'ggmailing_group');
			$replaceStr = array("\r\n", "\r", "\n", " ", "\t", "\xC2\xAD");
			foreach($checkInfos as $val){
				if(isset($args->{$val})) $args->{$val} = str_replace($replaceStr, '', $args->{$val});
			}
			$output = executeQueryArray('ggmailing.getGgmailingAdminMember', $args);
			if($output->data) {
				foreach($ggoutput->data[0] as $key => $val) {
					if(($val->ggmailing_group != $args->ggmailing_group) || (($val->ggmailing_email != $args->ggmailing_email) && ($val->ggmailing_group == $args->ggmailing_group)))
						executeQuery('ggmailing.insertGgmailingAdminMember',$args);
				}
			} else {
				executeQuery('ggmailing.insertGgmailingAdminMember',$args);
			}
		}
		$returnUrl = getNotEncodedUrl('','module','admin','act','dispGgmailingAdminInsertmembers');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminDBInsert() {
		$args = Context::getRequestVars();
		$obj->ggmailing_group = $args->cms_db_group;
		$cms_type = $args->cms_type;
		$cms_db_table = $args->cms_db_table;
		$cms_db_col_name = $args->cms_db_col_name;
		$cms_db_col_email = $args->cms_db_col_email;
		$cms_db_col_regdate = $args->cms_db_col_regdate;
		$oDB = DB::getInstance();
		$qry = 'select '.$cms_db_col_name.','.$cms_db_col_email.','.$cms_db_col_regdate.' from '.$cms_db_table;
		$result = $oDB->_query($qry);
		$output = $oDB->_fetch($result);
		if(count($output) > 1) {
			foreach($output as $key => $val) {
				$obj->ggmailing_nickname = str_replace(',','.',$val->{$cms_db_col_name});
				$obj->ggmailing_email = str_replace(',','.',$val->{$cms_db_col_email});
				$obj->regdate = date('YmdHis',strtotime(str_replace(',','.',$val->{$cms_db_col_regdate})));
				$ggoutput = executeQueryArray('ggmailing.getGgmailingAdminMemberList', $obj);
				if($obj->ggmailing_nickname && $obj->ggmailing_email && $obj->regdate) {
					if($ggoutput->data) {
						foreach($ggoutput->data as $key => $val) {
							if(($val->ggmailing_group != $obj->ggmailing_group) || (($val->ggmailing_email != $obj->ggmailing_email) && ($val->ggmailing_group == $obj->ggmailing_group)))
								executeQuery('ggmailing.insertGgmailingAdminMember',$obj);
						}
					} else {
						executeQuery('ggmailing.insertGgmailingAdminMember',$obj);
					}
				}
			}
		} else if(count($output) == 1) {
			$obj->ggmailing_nickname = str_replace(',','.',$output->{$cms_db_col_name});
			$obj->ggmailing_email = str_replace(',','.',$output->{$cms_db_col_email});
			$obj->regdate = date('YmdHis',strtotime(str_replace(',','.',$output->{$cms_db_col_regdate})));
			$ggoutput = executeQueryArray('ggmailing.getGgmailingAdminMemberList', $obj);
			if($obj->ggmailing_nickname && $obj->ggmailing_email && $obj->regdate) {
				if($ggoutput->data) {
					foreach($ggoutput->data as $key => $val) {
						if(($val->ggmailing_group != $obj->ggmailing_group) || (($val->ggmailing_email != $obj->ggmailing_email) && ($val->ggmailing_group == $obj->ggmailing_group)))
							executeQuery('ggmailing.insertGgmailingAdminMember',$obj);
					}
				} else {
					executeQuery('ggmailing.insertGgmailingAdminMember',$obj);
				}
			}
		}
		$returnUrl = getNotEncodedUrl('','module','admin','act','dispGgmailingAdminInsertmembers','cms_db_group',$args->cms_db_group);
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminMemberDelete() {
		$args = Context::getRequestVars();
		executeQuery('ggmailing.deleteGgmailingAdminMember',$args);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminInsertmembers');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminBoardmailingDelete() {
		$args = Context::getRequestVars();
		executeQuery('ggmailing.deleteGgmailingBoardMember',$args);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminBoardMailing');
		header("Location:" . $returnUrl);
	}

	function procGgmailingAdminDonotsendDelete() {
		$args = Context::getRequestVars();
		executeQuery('ggmailing.deleteGgmailingDonotsend',$args);
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispGgmailingAdminDonotsend');
		header("Location:" . $returnUrl);
	}
}
