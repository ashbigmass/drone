<?php
require_once _XE_PATH_ . "libs/phpmailer/phpmailer.php";

class Mail extends PHPMailer
{
	var $sender_name = '';
	var $sender_email = '';
	var $receiptor_name = '';
	var $receiptor_email = '';
	var $title = '';
	var $content = '';
	var $content_type = 'html';
	var $messageId = NULL;
	var $replyTo = NULL;
	var $bcc = NULL;
	var $attachments = array();
	var $cidAttachments = array();
	var $mainMailPart = NULL;
	var $body = '';
	var $header = '';
	var $eol = '';
	var $references = '';
	var $additional_params = NULL;
	var $use_smtp = FALSE;

	function Mail() {
	}

	function useGmailAccount($account_name, $account_passwd) {
		$this->SMTPAuth = TRUE;
		$this->SMTPSecure = "tls";
		$this->Host = 'smtp.gmail.com';
		$this->Port = '587';
		if($this->isVaildMailAddress($account_name)) $this->Username = $account_name;
		else $this->Username = $account_name . '@gmail.com';
		$this->Password = $account_passwd;
		$this->IsSMTP();
	}

	function useSMTP($auth = NULL, $host = NULL, $user = NULL, $pass = NULL, $secure = NULL, $port = 25) {
		$this->SMTPAuth = $auth;
		$this->Host = $host;
		$this->Username = $user;
		$this->Password = $pass;
		$this->Port = $port;
		if($secure == 'ssl' || $secure == 'tls') $this->SMTPSecure = $secure;
		if(($this->SMTPAuth !== NULL && $this->Host !== NULL && $this->Username !== NULL && $this->Password !== NULL) || ($this->SMTPAuth === NULL && $this->Host !== NULL)) {
			$this->IsSMTP();
			$this->AltBody = "To view the message, please use an HTML compatible email viewer!";
			return TRUE;
		} else {
			$this->IsMail();
			return FALSE;
		}
	}

	function setAdditionalParams($additional_params) {
		$this->additional_params = $additional_params;
	}

	function addAttachment($filename, $orgfilename) {
		$this->attachments[$orgfilename] = $filename;
	}

	function addCidAttachment($filename, $cid) {
		$this->cidAttachments[$cid] = $filename;
	}

	function setSender($name, $email) {
		if($this->Mailer == "mail") {
			$this->sender_name = $name;
			$this->sender_email = $email;
		} else {
			$this->SetFrom($email, $name);
		}
	}

	function getSender() {
		if(!stristr(PHP_OS, 'win') && $this->sender_name) return sprintf("%s <%s>", '=?utf-8?b?' . base64_encode($this->sender_name) . '?=', $this->sender_email);
		return $this->sender_email;
	}

	function setReceiptor($name, $email) {
		if($this->Mailer == "mail") {
			$this->receiptor_name = $name;
			$this->receiptor_email = $email;
		} else {
			$this->AddAddress($email, $name);
		}
	}

	function getReceiptor() {
		if(!stristr(PHP_OS, 'win') && $this->receiptor_name && $this->receiptor_name != $this->receiptor_email)
			return sprintf("%s <%s>", '=?utf-8?b?' . base64_encode($this->receiptor_name) . '?=', $this->receiptor_email);
		return $this->receiptor_email;
	}

	function setTitle($title) {
		if($this->Mailer == "mail") $this->title = $title;
		else $this->Subject = $title;
	}

	function getTitle() {
		return '=?utf-8?b?' . base64_encode($this->title) . '?=';
	}

	function setBCC($bcc) {
		if($this->Mailer == "mail") $this->bcc = $bcc;
		else $this->AddBCC($bcc);
	}

	function setMessageID($messageId) {
		$this->messageId = $messageId;
	}

	function setReferences($references) {
		$this->references = $references;
	}

	function setReplyTo($replyTo) {
		if($this->Mailer == "mail") $this->replyTo = $replyTo;
		else $this->AddReplyTo($replyTo);
	}

	function setContent($content) {
		$content = preg_replace_callback('/<img([^>]+)>/i', array($this, 'replaceResourceRealPath'), $content);
		if($this->Mailer == "mail") $this->content = $content;
		else $this->MsgHTML($content);
	}

	function replaceResourceRealPath($matches) {
		return preg_replace('/src=(["\']?)files/i', 'src=$1' . Context::getRequestUri() . 'files', $matches[0]);
	}

	function getPlainContent() {
		return chunk_split(base64_encode(str_replace(array("<", ">", "&"), array("&lt;", "&gt;", "&amp;"), $this->content)));
	}

	function getHTMLContent() {
		return chunk_split(base64_encode($this->content_type != 'html' ? nl2br($this->content) : $this->content));
	}

	function setContentType($mode = 'html') {
		$this->content_type = $mode == 'html' ? 'html' : '';
	}

	function procAttachments() {
		if($this->Mailer == "mail") {
			if(count($this->attachments) > 0) {
				$this->body = $this->header . $this->body;
				$boundary = '----==' . uniqid(rand(), TRUE);
				$this->header = "Content-Type: multipart/mixed;" . $this->eol . "\tboundary=\"" . $boundary . "\"" . $this->eol . $this->eol;
				$this->body = "--" . $boundary . $this->eol . $this->body . $this->eol . $this->eol;
				$res = array();
				$res[] = $this->body;
				foreach($this->attachments as $filename => $attachment) {
					$type = $this->returnMIMEType($filename);
					$file_handler = new FileHandler();
					$file_str = $file_handler->readFile($attachment);
					$chunks = chunk_split(base64_encode($file_str));
					$tempBody = sprintf(
						"--" . $boundary . $this->eol .
						"Content-Type: %s;" . $this->eol .
						"\tname=\"%s\"" . $this->eol .
						"Content-Transfer-Encoding: base64" . $this->eol .
						"Content-Description: %s" . $this->eol .
						"Content-Disposition: attachment;" . $this->eol .
						"\tfilename=\"%s\"" . $this->eol . $this->eol .
						"%s" . $this->eol . $this->eol, $type, $filename, $filename, $filename, $chunks);
					$res[] = $tempBody;
				}
				$this->body = implode("", $res);
				$this->body .= "--" . $boundary . "--";
			}
		} else {
			if(count($this->attachments) > 0) {
				foreach($this->attachments as $filename => $attachment) parent::AddAttachment($attachment);
			}
		}
	}

	function procCidAttachments() {
		if(count($this->cidAttachments) > 0) {
			$this->body = $this->header . $this->body;
			$boundary = '----==' . uniqid(rand(), TRUE);
			$this->header = "Content-Type: multipart/relative;" . $this->eol . "\ttype=\"multipart/alternative\";" . $this->eol . "\tboundary=\"" . $boundary . "\"" . $this->eol . $this->eol;
			$this->body = "--" . $boundary . $this->eol . $this->body . $this->eol . $this->eol;
			$res = array();
			$res[] = $this->body;
			foreach($this->cidAttachments as $cid => $attachment) {
				$filename = basename($attachment);
				$type = $this->returnMIMEType(FileHandler::getRealPath($attachment));
				$file_str = FileHandler::readFile($attachment);
				$chunks = chunk_split(base64_encode($file_str));
				$tempBody = sprintf(
					"--" . $boundary . $this->eol .
					"Content-Type: %s;" . $this->eol .
					"\tname=\"%s\"" . $this->eol .
					"Content-Transfer-Encoding: base64" . $this->eol .
					"Content-ID: <%s>" . $this->eol .
					"Content-Description: %s" . $this->eol .
					"Content-Location: %s" . $this->eol . $this->eol .
					"%s" . $this->eol . $this->eol, $type, $filename, $cid, $filename, $filename, $chunks);
				$res[] = $tempBody;
			}
			$this->body = implode("", $res);
			$this->body .= "--" . $boundary . "--";
		}
	}

	function send() {
		if($this->Mailer == "mail") {
			$boundary = '----==' . uniqid(rand(), TRUE);
			$this->eol = $GLOBALS['_qmail_compatibility'] == "Y" ? "\n" : "\r\n";
			$this->header = "Content-Type: multipart/alternative;" . $this->eol . "\tboundary=\"" . $boundary . "\"" . $this->eol . $this->eol;
			$this->body = sprintf(
				"--%s" . $this->eol .
				"Content-Type: text/plain; charset=utf-8; format=flowed" . $this->eol .
				"Content-Transfer-Encoding: base64" . $this->eol .
				"Content-Disposition: inline" . $this->eol . $this->eol .
				"%s" .
				"--%s" . $this->eol .
				"Content-Type: text/html; charset=utf-8" . $this->eol .
				"Content-Transfer-Encoding: base64" . $this->eol .
				"Content-Disposition: inline" . $this->eol . $this->eol .
				"%s" .
				"--%s--" .
				"", $boundary, $this->getPlainContent(), $boundary, $this->getHTMLContent(), $boundary
			);
			$this->procCidAttachments();
			$this->procAttachments();
			$headers = sprintf(
				"From: %s" . $this->eol .
				"%s" .
				"%s" .
				"%s" .
				"%s" .
				"MIME-Version: 1.0" . $this->eol . "", $this->getSender(), $this->messageId ? ("Message-ID: <" . $this->messageId . ">" . $this->eol) : "", $this->replyTo ? ("Reply-To: <" . $this->replyTo . ">" . $this->eol) : "", $this->bcc ? ("Bcc: " . $this->bcc . $this->eol) : "", $this->references ? ("References: <" . $this->references . ">" . $this->eol . "In-Reply-To: <" . $this->references . ">" . $this->eol) : ""
			);
			$headers .= $this->header;
			if($this->additional_params) return mail($this->getReceiptor(), $this->getTitle(), $this->body, $headers, $this->additional_params);
			return mail($this->getReceiptor(), $this->getTitle(), $this->body, $headers);
		} else {
			$this->procAttachments();
			return parent::Send();
		}
	}

	function checkMailMX($email_address) {
		if(!Mail::isVaildMailAddress($email_address)) return FALSE;
		list($user, $host) = explode("@", $email_address);
		if(function_exists('checkdnsrr')) {
			if(checkdnsrr($host, "MX") || checkdnsrr($host, "A")) return TRUE;
			else return FALSE;
		}
		return TRUE;
	}

	function isVaildMailAddress($email_address) {
		if(preg_match("/([a-z0-9\_\-\.]+)@([a-z0-9\_\-\.]+)/i", $email_address)) return $email_address;
		else return '';
	}

	function returnMIMEType($filename) {
		preg_match("|\.([a-z0-9]{2,4})$|i", $filename, $fileSuffix);
		switch(strtolower($fileSuffix[1])) {
			case "js" : return "application/x-javascript";
			case "json" : return "application/json";
			case "jpg" :
			case "jpeg" :
			case "jpe" : return "image/jpg";
			case "png" :
			case "gif" :
			case "bmp" :
			case "tiff" : return "image/" . strtolower($fileSuffix[1]);
			case "css" : return "text/css";
			case "xml" : return "application/xml";
			case "doc" :
			case "docx" : return "application/msword";
			case "xls" :
			case "xlt" :
			case "xlm" :
			case "xld" :
			case "xla" :
			case "xlc" :
			case "xlw" :
			case "xll" : return "application/vnd.ms-excel";
			case "ppt" :
			case "pps" : return "application/vnd.ms-powerpoint";
			case "rtf" : return "application/rtf";
			case "pdf" : return "application/pdf";
			case "html" :
			case "htm" :
			case "php" : return "text/html";
			case "txt" : return "text/plain";
			case "mpeg" :
			case "mpg" :
			case "mpe" : return "video/mpeg";
			case "mp3" : return "audio/mpeg3";
			case "wav" : return "audio/wav";
			case "aiff" :
			case "aif" : return "audio/aiff";
			case "avi" : return "video/msvideo";
			case "wmv" : return "video/x-ms-wmv";
			case "mov" : return "video/quicktime";
			case "zip" : return "application/zip";
			case "tar" : return "application/x-tar";
			case "swf" : return "application/x-shockwave-flash";
			default :
				if(function_exists("mime_content_type")) $fileSuffix = mime_content_type($filename);
				return "unknown/" . trim($fileSuffix[0], ".");
		}
	}
}
