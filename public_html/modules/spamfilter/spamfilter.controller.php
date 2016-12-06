<?php
class spamfilterController extends spamfilter
{
	function init() {
	}

	function setAvoidLog() {
		$_SESSION['avoid_log'] = true;
	}

	function triggerInsertDocument(&$obj) {
		if($_SESSION['avoid_log']) return new Object();
		$is_logged = Context::get('is_logged');
		$logged_info = Context::get('logged_info');
		$grant = Context::get('grant');
		if($is_logged) {
			if($logged_info->is_admin == 'Y') return new Object();
			if($grant->manager) return new Object();
		}
		$oFilterModel = getModel('spamfilter');
		$output = $oFilterModel->isDeniedIP();
		if(!$output->toBool()) return $output;
		$text = '';
		if($is_logged) $text = $obj->title . ' ' . $obj->content . ' ' . $obj->tags;
		else $text = $obj->title . ' ' . $obj->content . ' ' . $obj->nick_name . ' ' . $obj->homepage . ' ' . $obj->tags;
		$output = $oFilterModel->isDeniedWord($text);
		if(!$output->toBool()) return $output;
		if($obj->document_srl == 0) {
			$output = $oFilterModel->checkLimited();
			if(!$output->toBool()) return $output;
		}
		$this->insertLog();
		return new Object();
	}

	function triggerInsertComment(&$obj) {
		if($_SESSION['avoid_log']) return new Object();
		$is_logged = Context::get('is_logged');
		$logged_info = Context::get('logged_info');
		$grant = Context::get('grant');
		if($is_logged) {
			if($logged_info->is_admin == 'Y') return new Object();
			if($grant->manager) return new Object();
		}
		$oFilterModel = getModel('spamfilter');
		$output = $oFilterModel->isDeniedIP();
		if(!$output->toBool()) return $output;
		$text = '';
		if($is_logged) $text = $obj->content;
		else $text = $obj->content . ' ' . $obj->nick_name . ' ' . $obj->homepage;
		$output = $oFilterModel->isDeniedWord($text);
		if(!$output->toBool()) return $output;
		if(!$obj->__isupdate) {
			$output = $oFilterModel->checkLimited();
			if(!$output->toBool()) return $output;
		}
		unset($obj->__isupdate);
		$this->insertLog();
		return new Object();
	}

	function triggerInsertTrackback(&$obj) {
		if($_SESSION['avoid_log']) return new Object();
		$oFilterModel = getModel('spamfilter');
		$output = $oFilterModel->isInsertedTrackback($obj->document_srl);
		if(!$output->toBool()) return $output;
		$output = $oFilterModel->isDeniedIP();
		if(!$output->toBool()) return $output;
		$text = $obj->blog_name . ' ' . $obj->title . ' ' . $obj->excerpt . ' ' . $obj->url;
		$output = $oFilterModel->isDeniedWord($text);
		if(!$output->toBool()) return $output;
		$oTrackbackModel = getModel('trackback');
		$oTrackbackController = getController('trackback');
		list($ipA,$ipB,$ipC,$ipD) = explode('.',$_SERVER['REMOTE_ADDR']);
		$ipaddress = $ipA.'.'.$ipB.'.'.$ipC;
		if($obj->title == $obj->excerpt) {
			$oTrackbackController->deleteTrackbackSender(60*60*6, $ipaddress, $obj->url, $obj->blog_name, $obj->title, $obj->excerpt);
			$this->insertIP($ipaddress.'.*', 'AUTO-DENIED : trackback.insertTrackback');
			return new Object(-1,'msg_alert_trackback_denied');
		}
		return new Object();
	}

	function insertIP($ipaddress_list, $description = null) {
		$regExr = "/^((\d{1,3}(?:.(\d{1,3}|\*)){3})\s*(\/\/(.*)\s*)?)*\s*$/";
		if(!preg_match($regExr,$ipaddress_list)) return new Object(-1, 'msg_invalid');
		$ipaddress_list = str_replace("\r","",$ipaddress_list);
		$ipaddress_list = explode("\n",$ipaddress_list);
		foreach($ipaddress_list as $ipaddressValue) {
			$args = new stdClass();
			preg_match("/(\d{1,3}(?:.(\d{1,3}|\*)){3})\s*(\/\/(.*)\s*)?/",$ipaddressValue,$matches);
			if($ipaddress=trim($matches[1])) {
				$args->ipaddress = $ipaddress;
				if(!$description && $matches[4]) $args->description = $matches[4];
				else $args->description = $description;
			}
			$output = executeQuery('spamfilter.insertDeniedIP', $args);
			if(!$output->toBool()) $fail_list .= $ipaddress.'<br/>';
		}
		$output->add('fail_list',$fail_list);
		return $output;
	}

	function triggerSendMessage(&$obj) {
		if($_SESSION['avoid_log']) return new Object();
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return new Object();
		$oFilterModel = getModel('spamfilter');
		$output = $oFilterModel->isDeniedIP();
		if(!$output->toBool()) return $output;
		$text = $obj->title . ' ' . $obj->content;
		$output = $oFilterModel->isDeniedWord($text);
		if(!$output->toBool()) return $output;
		$output = $oFilterModel->checkLimited(TRUE);
		if(!$output->toBool()) return $output;
		$this->insertLog();
		return new Object();
	}

	function insertLog() {
		$output = executeQuery('spamfilter.insertLog');
		return $output;
	}
}
