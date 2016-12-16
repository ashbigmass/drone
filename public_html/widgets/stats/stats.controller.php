<?php
require_once(_XE_PATH_ . 'modules/stats/library/stats_library.php');

class statsController extends stats
{
	protected $stats_library;
	protected $stats_version;

    public function __construct() {
		$this->stats_library = new stats_library();
		$this->stats_version = '1.14';
	}

	function init() {
	}

	function procStatsExecute() {
	}

	function statsExecute() {
		$logged_info = Context::get('logged_info');
		$config = $this->getConfig();
		$oDB = DB::getInstance();
		$oDB->begin();
		$site_module_info = Context::get('site_module_info');
		$site_srl = (int) $site_module_info->site_srl;
		$mid = Context::get('mid');
		if(!$mid) $mid = '';
		$module_srl = 0;
		if($mid) {
			$oModuleModel = getModel('module');
			if($module = $oModuleModel->getModuleInfoByMid($mid)) $module_srl = $module->module_srl;
		}
		if(empty($module_srl)) $module_srl =$site_module_info->module_srl;
		$document_srl = Context::get('document_srl');
		if(!$document_srl) $document_srl = 0;
		if($config->stats_enable_admin_layer == 'Y') {
			if($logged_info && ($logged_info->is_admin == 'Y' || $logged_info->is_site_admin == 'Y')) $this->__getAdminLayer($site_srl, $module_srl, $document_srl);
		}
		if(!empty($config->stats_ignore_ip)) {
			$cut_ip_1 = explode('.', $_SERVER['REMOTE_ADDR']);
			$ip_address_checks = explode("\n",$config->stats_ignore_ip);
			foreach($ip_address_checks as $check_ip_address) {
				$cut_ip_2 = explode('.', trim($check_ip_address));
				if(count($cut_ip_2) == 4) {
					$check_count = 0;
					foreach($cut_ip_2 as $index => $ip) {
						if($ip == '*') { $check_count ++;}
						if($ip == $cut_ip_1[$index]) $check_count ++;
					}
					if($check_count == 4) return false;
				}
			}
		}
		if($config->stats_ignore_admin == 'Y') {
			if($logged_info && ($logged_info->is_site_admin == 'Y' || $logged_info->is_admin == 'Y')) return false;
		}
		$session_id = $this->session_type == 'session_id' ? session_id() : $_SERVER['REMOTE_ADDR'];
		$user_agent = $this->stats_library->parse_user_agent();
		$referer = $this->stats_library->parse_referer();
		$now = $this->stats_library->get_now();
		$ip_address = $this->stats_library->get_ip_address();
		$is_bot = $this->stats_library->is_bot();
		$is_mobile = $this->stats_library->is_mobile();
		if($config->stats_ignore_bot == 'Y' && $is_bot) return false;
		$statsModel = getModel('stats');
		$statsModel->insertPageView($site_srl, $module_srl, $document_srl, $is_bot, $is_mobile);
		$uniqued = false;
		$oCacheHandler = CacheHandler::getInstance('object');
		$today_key = date('Ymd000000');
		if($oCacheHandler->isSupport()) {
			$object_key = 'stats.'.$this->stats_version.':' . $site_srl . '_' . $module_srl . '_' . $document_srl . '_' . $today_key . '_' . str_replace(array('.', ':'), '-', $session_id);
			$cache_key = $oCacheHandler->getGroupKey('statsUniqued', $object_key);
			$uniqued = $oCacheHandler->get($cache_key);
		}
		if($uniqued === false) {
			$depths = $statsModel->insertUniquePageView($site_srl, $module_srl, $document_srl, $session_id, $is_bot, $is_mobile);
			if(in_array(1, $depths)) $statsModel->insertUserAgent($site_srl, $user_agent, $is_bot, $is_mobile);
			if($oCacheHandler->isSupport()) {
				$uniqued = true;
				$oCacheHandler->put($cache_key, $uniqued);
			}
		}
		$statsModel->insertReferer($site_srl, $session_id, $referer, $is_bot, $is_mobile);
		$statsModel->insertSeatchTermByInside($site_srl, $now);
		$log = $statsModel->getLog($site_srl, $module_srl, $document_srl, $session_id, $today_key);
		if(!$log) $statsModel->insertLog($site_srl, $module_srl, $document_srl, $session_id, $user_agent['full'], $referer['full'], $ip_address, $now, $is_bot, $is_mobile);
		$oDB->commit();
	}

	private function __getAdminLayer($site_srl, $module_srl, $document_srl) {
		$statsModel = getModel('stats');
		$from = date('Ymd000000', mktime()-(60*60*24*3));
		$to = date('Ymd235959');
		$result = $statsModel->getInfo($site_srl, $module_srl, $document_srl, $from, $to);
		if($result) {
			if($result && !is_array($result)) $result = array($result);
			$datas = array();
			foreach($result as $key=>$data) {
				if(substr($data->type,0,2) == 'uv') {
					$datas['uv'] = $data->sum_count;
					$datas['uv.robot'] = $data->sum_bot_count;
					$datas['uv.mobile'] = $data->sum_mobile_count;
					$datas['uv.format'] = number_format($data->sum_count);
					$datas['uv.robot.format'] = number_format($data->sum_bot_count);
					$datas['uv.mobile.format'] = number_format($data->sum_mobile_count);
				} else if(substr($data->type,0,2) == 'pv') {
					$datas['pv'] = $data->sum_count;
					$datas['pv.robot'] = $data->sum_bot_count;
					$datas['pv.mobile'] = $data->sum_mobile_count;
					$datas['pv.format'] = number_format($data->sum_count);
					$datas['pv.robot.format'] = number_format($data->sum_bot_count);
					$datas['pv.mobile.format'] = number_format($data->sum_mobile_count);
				}
			}
			$datas['from'] = zdate($from,'Y-m-d');
			$datas['to'] = zdate($to,'Y-m-d');
			$search_value = $site_srl.','.$module_srl.','.$document_srl;
			$datas['goto_admin_url'] = getUrl('','module','admin','act','dispStatsAdminIndex','menu','log','selected_range','3','search_type','srl','search_value', $search_value);
			$content = @file_get_contents($this->module_path.'/tpl/layer/for_admin.html');
			if($content) {
				foreach($datas as $key => $data) $content = str_replace('{$'.$key.'}',$data,$content);
			}
		} else {
			$content = '';
		}
		Context::addHtmlfooter($content);
	}
}
