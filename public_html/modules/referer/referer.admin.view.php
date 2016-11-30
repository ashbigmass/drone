<?php
/**
 * @class  RefererAdminView
 * @author haneul (haneul0318@gmail.com)
 * @enhanced by KnDol (kndol@kndol.net)
 * @brief  Referer 모듈의 Admin view class
 **/
define('_REFERER_RANKING_', 0);
define('_REFERER_RANKING_DETAIL_', 1);
define('_REMOTE_RANKING_', 2);
define('_USER_RANKING_', 3);
define('_PAGE_RANKING_', 4);
define('_COUNTRY_RANKING_', 5);
define('_UAGENT_RANKING_', 6);
define('_UAGENT_STATISTICS_', 7);
define('_MID_STATISTICS_', 8);

define('_MAX_STAT_DATA_', 14);

class RefererAdminView extends Referer {
	/**
	 * Referer module config.
	 *
	 * @var Object
	 */

	/**
	 * @brief 초기화
	 **/
	function init() {
		// 템플릿 경로 지정 
		$this->setTemplatePath($this->module_path.'tpl');
	}

	/**
	 * @brief 관리자 페이지 초기화면
	 **/
	function dispRefererAdminIndex() {
		$this->dispRefererAdminList();
	}

	function dispRefererAdminDeleteStat () {
		if(Context::get('host')=="") return $this->dispRefererAdminRanking();
		$this->setTemplateFile('delete_stat');
	}

	function dispRefererAdminDeleteRemoteStat () {
		if(Context::get('remote')=="") return $this->dispRefererAdminRemoteRanking();
		$this->setTemplateFile('delete_stat');
	}

	function dispRefererAdminDeleteUserStat () {
		if(Context::get('member_srl')=="") return $this->dispRefererAdminUserRanking();
		$this->setTemplateFile('delete_stat');
	}

	function dispRefererAdminDeletePageStat () {
		if(Context::get('called_from')=="" || Context::get('url')=="") return $this->dispRefererAdminPageRanking();
		$this->setTemplateFile('delete_stat');
	}

	function dispRefererAdminDeleteCountryStat () {
		if(Context::get('country_code')=="") return $this->dispRefererAdminCountryRanking();
		$this->setTemplateFile('delete_stat');
	}

	function dispRefererAdminDeleteUAgentStat () {
		if(Context::get('uagent')=="") return $this->dispRefererAdminUAgentRanking();
		$this->setTemplateFile('delete_stat');
	}

	function dispRefererAdminResetData () {
		$this->setTemplateFile('reset_data');
	}
	
	function dispRefererAdminList() {
		// 목록을 구하기 위한 옵션
		$args->host = Context::get('host'); ///< 선택한 호스트
		$args->remote = Context::get('remote'); ///< 선택한 리모트IP
		$args->uagent = Context::get('uagent'); ///< 선택한 User Agent
		$args->country_code = Context::get('country_code'); ///< 선택한 국가
		$args->ref_mid = Context::get('ref_mid'); ///< 선택한 mid
		$args->ref_document_srl = Context::get('ref_document_srl'); ///< 선택한 document_srl
		$args->page = Context::get('page'); ///< 페이지
		$args->sort_index = 'regdate'; ///< 소팅 값
		$args->mode = Context::get('mode');
		$args->search_target  = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');
		if ($args->search_target=='user_id' && $args->search_keyword) {
			$args->search_target  = 'member_srl';
			$args->search_keyword = $this->getMemberSrlFromUserID($args->search_keyword);
		}

		$oRefererModel = &getModel('referer');
		$output = $oRefererModel->getLogList($args);
		if ($output->page > $output->total_page) {
			$args->page = $output->total_page;
			$output = $oRefererModel->getLogList($args);
		}
		$refererConfig = $oRefererModel->getRefererConfig();

		Context::set('refererConfig', $refererConfig);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('referer_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		// 템플릿 지정
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('referer_list');
	}
	
	function dispRefererAdminRankingPage($ranking_mode){
		if ($ranking_mode != _UAGENT_STATISTICS_) {
			$args->host = Context::get('host');
			if ($args->host != "") $ranking_mode = _REFERER_RANKING_DETAIL_;
			$args->page = Context::get('page'); ///< 페이지
			$args->sort_index = 'cnt'; ///< 소팅 값
			$args->list_count = 20; // 한 페이지에 표시할 항목 수
			$search_keyword = trim(Context::get('search_keyword'));
			if ($search_keyword) {
				$args->search_keyword  = $search_keyword;
				if ($ranking_mode == _USER_RANKING_ && $args->search_keyword) {
					$args->search_target  = 'member_srl';
					$args->search_keyword = $this->getMemberSrlFromUserID($args->search_keyword);
				}
			}
		}
		$oRefererModel = &getModel('referer');
		$output = $oRefererModel->getRefererRanking($ranking_mode, $args);

		if ($ranking_mode != _UAGENT_STATISTICS_) {
			if ($output->page > $output->total_page) {
				$args->page = $output->total_page;
				$output = $oRefererModel->getRefererRanking($ranking_mode, $args);
			}
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
			Context::set('referer_status', $output->data);
			Context::set('rank', $args->list_count*($output->page-1)+1);
		}
		$refererConfig = $oRefererModel->getRefererConfig();
		Context::set('refererConfig', $refererConfig);

		// 템플릿 지정
		$this->setTemplatePath($this->module_path.'tpl');
		switch ($ranking_mode) {
			case _REFERER_RANKING_:
				$this->setTemplateFile('referer_ranking');
				break;
			case _REFERER_RANKING_DETAIL_:
				$this->setTemplateFile('referer_ranking_detail');
				break;
			case _REMOTE_RANKING_:
				$this->setTemplateFile('referer_remote_ranking');
				break;
			case _USER_RANKING_:
   				$this->setTemplateFile('referer_user_ranking');
				break;
			case _PAGE_RANKING_:
				$this->preparePageStatData($output->data);
				$this->setTemplateFile('referer_page_ranking');
				break;
			case _COUNTRY_RANKING_:
   				$this->setTemplateFile('referer_country_ranking');
				break;
			case _UAGENT_RANKING_:
   				$this->setTemplateFile('referer_uagent_ranking');
				break;
			case _UAGENT_STATISTICS_:
				$this->prepareUAStatData($output->data);

				if ($refererConfig->logging_country == 'yes') {
					$args->page = 1;
					$args->sort_index = 'cnt';
					$args->list_count = 300;
					$output = $oRefererModel->getRefererRanking(_COUNTRY_RANKING_, $args);
					$this->prepareCountryStatData($output->data);
				}
				$output = $oRefererModel->getRefererRanking(_MID_STATISTICS_, $args);
				$this->prepareMidStatData($output->data);
   				$this->setTemplateFile('referer_uagent_stat');
				break;
		}   
	}

	function dispRefererAdminRanking(){
		$this->dispRefererAdminRankingPage(_REFERER_RANKING_);
	}
	
	function dispRefererAdminRemoteRanking(){
		$this->dispRefererAdminRankingPage(_REMOTE_RANKING_);
	}

	function dispRefererAdminUserRanking(){
		$this->dispRefererAdminRankingPage(_USER_RANKING_);
	}

	function dispRefererAdminPageRanking(){
		$this->dispRefererAdminRankingPage(_PAGE_RANKING_);
	}

	function dispRefererAdminCountryRanking(){
		$this->dispRefererAdminRankingPage(_COUNTRY_RANKING_);
	}

	function dispRefererAdminUAgentRanking(){
		$this->dispRefererAdminRankingPage(_UAGENT_RANKING_);
	}

	function dispRefererAdminUAgentStat(){
		$this->dispRefererAdminRankingPage(_UAGENT_STATISTICS_);
	}

	function preparePageStatData(&$data) {
		$oContext = &Context::getInstance();
		foreach($data as $no => $val) {
			if($oContext->allow_rewrite) {
				if($val->ref_mid == '/') $val->url = "/";
				else if(!$val->ref_document_srl) $val->url = "/$val->ref_mid";
				else if(!$val->ref_mid) $val->url = "/$val->ref_document_srl";
				else $val->url = "/$val->ref_mid/$val->ref_document_srl";
			}
			else {
				if($val->ref_mid == '/') $val->url = "/";
				else if(!$val->ref_document_srl) $val->url = "/index.php?ref_mid=$val->ref_mid";
				else if(!$val->ref_mid) $val->url = "/index.php?ref_document_srl=$val->ref_document_srl";
				else $val->url = "/index.php?ref_mid=$val->ref_mid&ref_document_srl=$val->ref_document_srl";
			}
		}
	}

	function prepareCountryStatData(&$data) {
		$Countries = array();
		$i = 0;
		$others = 0;
		foreach($data as $no => $val) {
			if ($i++<_MAX_STAT_DATA_) {
				$Countries[$val->country] = $val->cnt;
			}
			else {
				$others += $val->cnt;
			}
		}
		if ($others) $Countries['Other Countries'] = $others;
		Context::set('Countries', $Countries);
	}

	function prepareMidStatData(&$data) {
		$Mids = array();
		$i = 0;
		$others = 0;
		foreach($data as $no => $val) {
			if ($i++<_MAX_STAT_DATA_) {
				$Mids[$val->ref_mid] += $val->cnt;
			}
			else {
				$Mids['Others'] += $val->cnt;
			}
		}
		Context::set('Mids', $Mids);
	}

	function prepareUAStatData(&$data) {
		$Types = array('desktop' => 0, 'mobile' => 0, 'online' => 0, 'unknown' => 0, 'bot' => 0, 'notbot' => 0);
		$Bots = array();
		$Browsers = array();
		$OSes = array();
		$IEs = array();
		$Windows = array();
		$Macs = array();
		$iOSes = array();
		$Androids = array();

		$oRefererModel = &getModel('referer');

		foreach($data as $no => $val) {
			$uagent = $val->uagent;
			$count = $val->cnt;
			$botprovider = $os = $browser = $mac = $ios = $android = $win = $ie = "";
			$mobile = false;
			
			if ( $oRefererModel->isBot($uagent, $botprovider) ) {
				if (array_key_exists($botprovider, $Bots)) $Bots[$botprovider] += $count;
				else $Bots[$botprovider] = $count;
				$Types['bot'] += $count;
			}
			else {
				if ( stripos($uagent, "iPhone") !== false || stripos($uagent, "iPod") !== false || stripos($uagent, "iPad") !== false ) 
				{
					$mobile = true;
					$os = "iOS";

					if ( preg_match('/OS ([0-9_]+) like/i',$uagent, $matches) ) {
						$ios = "iOS " . str_replace("_", ".", $matches[1]);

						if (array_key_exists($ios, $iOSes)) $iOSes[$ios] += $count;
						else $iOSes[$ios] = $count;
					}
				}
				else if ( stripos($uagent, "Android") !== false ) {
					$mobile = true;
					$os  = 'Android';
					if ( stripos($uagent, "Android 1.0") !== false ) $android = "1.0 Apple pie";
					else if ( stripos($uagent, "Android 1.1") !== false ) $android = "1.1 Bananabread";
					else if ( stripos($uagent, "Android 1.5") !== false ) $android = "1.5 Cupcake";
					else if ( stripos($uagent, "Android 1.6") !== false ) $android = "1.6 Donut";
					else if ( stripos($uagent, "Android 2.0") !== false ) $android = "2.0 Eclair";
					else if ( stripos($uagent, "Android 2.1") !== false ) $android = "2.1 Eclair";
					else if ( stripos($uagent, "Android 2.2") !== false ) $android = "2.2 Froyo";
					else if ( stripos($uagent, "Android 2.3") !== false ) $android = "2.3 Gingerbread";
					else if ( stripos($uagent, "Android 3.0") !== false ) $android = "3.0 Honeycomb";
					else if ( stripos($uagent, "Android 3.1") !== false ) $android = "3.1 Honeycomb";
					else if ( stripos($uagent, "Android 3.2") !== false ) $android = "3.2 Honeycomb";
					else if ( stripos($uagent, "Android 4.0") !== false ) $android = "4.0 ICS";
					else if ( stripos($uagent, "Android 4.1") !== false ) $android = "4.1 Jelly Bean";
					else if ( stripos($uagent, "Android 4.2") !== false ) $android = "4.2 Jelly Bean";
					else if ( stripos($uagent, "Android 4.3") !== false ) $android = "4.3 Jelly Bean";
					else if ( stripos($uagent, "Android 4.4") !== false ) $android = "4.4 KitKat";
					else if ( stripos($uagent, "Android 5.0") !== false ) $android = "5.0 Lollipop";
					else if ( stripos($uagent, "Android 5.1") !== false ) $android = "5.1 Lollipop";
					else if ( stripos($uagent, "Android 6.0") !== false ) $android = "6.0 Marshmallow";
					else if ( stripos($uagent, "Android 6.1") !== false ) $android = "6.1 Marshmallow";
					else if ( stripos($uagent, "Android 7.0") !== false ) $android = "7.0 N";
					else $android = "Others";

					if (array_key_exists($android, $Androids)) $Androids[$android] += $count;
					else $Androids[$android] = $count;
				}
				else if ( stripos($uagent, "Windows CE") !== false )
				{
					$mobile = true;
					$os = "Windows CE";
					if ( stripos($uagent, "IEMobile") !== false ) $browser = "Internet Explorer Mobile";
				}
				else if ( stripos($uagent, "Zune") !== false )
				{
					$mobile = true;
					$os = "MS Zune OS";
					$browser = "Internet Explorer Mobile";
				}
				else if ( stripos($uagent, "Windows Phone") !== false )
				{
					$mobile = true;
					$os = "MS Windows Phone";
					if ( stripos($uagent, "Windows Phone 6") !== false ) $os .= " 6";
					else if ( stripos($uagent, "Windows Phone OS 7") !== false ) $os .= " 7";
					else if ( stripos($uagent, "Windows Phone 8.0") !== false ) $os .= " 8.0";
					else if ( stripos($uagent, "Windows Phone 8.1") !== false ) $os .= " 8.1";
					$browser = "Internet Explorer Mobile";
				}
				else if ( stripos($uagent, "BlackBerry") !== false )
				{
					$mobile = true;
					$os = "BlackBerry";
					$browser = "BlackBerry Browser";
				}
				else if ( stripos($uagent, "Linux") !== false ) {
					$os = "Linux";
					if ( stripos($uagent, "x86_64") !== false ) $os .= " (64bits)";
				}
				else if ( stripos($uagent, "FreeBSD") !== false ) {
					$os = "FreeBSD";
					if ( stripos($uagent, "amd64") !== false) $os .= " (64bits)";
				}
				else if ( stripos($uagent, "OpenBSD") !== false ) {
					$os = "OpenBSD";
					if ( stripos($uagent, "amd64") !== false ) $os .= " (64bits)";
				}
				else if ( stripos($uagent, "macintosh") !== false || stripos($uagent, "mac os x") !== false ) {
					$os = "Mac OS X";
					if    ( stripos($uagent, "10.11") !== false || stripos($uagent, "10_11") !== false ) $mac = "10.11 El Capitan";
					else if ( stripos($uagent, "10.10") !== false || stripos($uagent, "10_10") !== false ) $mac = "10.10 Yosemite";
					else if ( stripos($uagent, "10.9") !== false || stripos($uagent, "10_9") !== false ) $mac = "10.9 Mavericks";
					else if ( stripos($uagent, "10.8") !== false || stripos($uagent, "10_8") !== false ) $mac = "10.8 Mountain Lion";
					else if ( stripos($uagent, "10.7") !== false || stripos($uagent, "10_7") !== false ) $mac = "10.7 Lion";
					else if ( stripos($uagent, "10.6") !== false || stripos($uagent, "10_6") !== false ) $mac = "10.6 Snow Leopard";
					else if ( stripos($uagent, "10.5") !== false || stripos($uagent, "10_5") !== false ) $mac = "10.5 Leopard";
					else if ( stripos($uagent, "10.4") !== false || stripos($uagent, "10_4") !== false ) $mac = "10.4 Tiger";
					else if ( stripos($uagent, "10.3") !== false || stripos($uagent, "10_3") !== false ) $mac = "10.3 Panther";
					else if ( stripos($uagent, "10.2") !== false || stripos($uagent, "10_2") !== false ) $mac = "10.2 Jaguar";
					else if ( stripos($uagent, "10.1") !== false || stripos($uagent, "10_1") !== false ) $mac = "10.1 Puma";
					else if ( stripos($uagent, "10.0") !== false || stripos($uagent, "10_0") !== false ) $mac = "10.0 Cheetar";
					else $mac = "Others";

					if (array_key_exists($mac, $Macs)) $Macs[$mac] += $count;
					else $Macs[$mac] = $count;
				}
				else if ( stripos($uagent, "Windows") !== false || stripos($uagent, "Win") !== false ) {
					$os = "MS Windows";
					if ( stripos($uagent, "NT 10.0") !== false ) $win = "Win 10";
					else if ( stripos($uagent, "NT 6.3") !== false ) $win = "Win 8.1";
					else if ( stripos($uagent, "NT 6.2") !== false ) $win = "Win 8";
					else if ( stripos($uagent, "NT 6.1") !== false ) $win = "Win 7";
					else if ( stripos($uagent, "NT 6.0") !== false ) $win = "Win Vista";
					else if ( stripos($uagent, "NT 5.2") !== false ) $win = "Win 2003 Server";
					else if ( stripos($uagent, "NT 5.1") !== false ) $win = "Win XP";
					else if ( stripos($uagent, "NT 5.0") !== false ) $win = "Win 2000";
					else if ( stripos($uagent, "NT")  !== false) $win = "Win NT";
					else if ( stripos($uagent, "Windows 98") !== false ) $win = "Win 98";
					else if ( stripos($uagent, "Windows 95") !== false ) $win = "Win 95";
					else $win = "Others";
					if ( stripos($uagent, "WOW64") !== false || stripos($uagent, "Win64") !== false || stripos($uagent, "x64") !== false ) {
						$os .= " (64bits)";
						$win .= " (64bits)";
					}
					
					if (array_key_exists($win, $Windows)) $Windows[$win] += $count;
					else $Windows[$win] = $count;

					if ( stripos($uagent, "Edge/15") !== false ) $ie = "Edge 15";
					else if ( stripos($uagent, "Edge/14") !== false ) $ie = "Edge 14";
					else if ( stripos($uagent, "Edge/13") !== false ) $ie = "Edge 13";
					else if ( stripos($uagent, "Edge/12") !== false ) $ie = "Edge 12";
					else if ( stripos($uagent, "Trident/7.0") !== false ) $ie = "Internet Explorer 11";
					else if ( stripos($uagent, "Trident/6.0") !== false ) $ie = "Internet Explorer 10";
					else if ( stripos($uagent, "Trident/5.0") !== false ) $ie = "Internet Explorer 9";
					else if ( stripos($uagent, "Trident/4.0") !== false ) $ie = "Internet Explorer 8";
					else if ( stripos($uagent, "MSIE 7") !== false ) $ie = "Internet Explorer 7";
					else if ( stripos($uagent, "MSIE 6") !== false ) $ie = "Internet Explorer 6";
					else if ( stripos($uagent, "MSIE 5") !== false ) $ie = "Internet Explorer 5";
					if ( stripos($uagent, "DigExt") !== false ) $ie .= " (Offline Browsing)";

					if ($ie != "") {
						$browser = stripos($ie, "Edge") !== false ? "Microsoft Edge" : "Internet Explorer";
						if (array_key_exists($ie, $IEs)) $IEs[$ie] += $count;
						else $IEs[$ie] = $count;
					}
				}
				
				if ($browser == "") {
					if ( stripos($uagent, "NAVER") !== false && stripos($uagent, "inapp") !== false ) {
						$browser = "Naver App";
						$mobile = true;
					}
					else if ( stripos($uagent, "DaumApps") !== false ) {
						$browser = "Daum App";
						$mobile = true;
					}
					else if ( stripos($uagent, "Opera") !== false ) $browser = "Opera Browser";
					else if ( stripos($uagent, "Opr") !== false ) $browser = "Opera Browser";
					else if ( stripos($uagent, "Chrome") !== false ) $browser = "Google Chrome";
					else if ( stripos($uagent, "Firefox") !== false || stripos($uagent, "Minefield") !== false ) $browser = "Mozilla Firefox";
					else if ( stripos($uagent, "Netscape") !== false ) $browser = "Netscape Navigator";
					else if ( stripos($uagent, "Mozilla") !== false && stripos($uagent, "Nav") !== false ) $browser = "Netscape Navigator";
					else if ( stripos($uagent, "Safari") !== false ) $browser = "Apple Safari";
					else if ( (stripos($uagent, "iPhone") !== false || stripos($uagent, "iPod") !== false || stripos($uagent, "iPad") !== false) && stripos($uagent, "AppleWebKit") !== false ) $browser = "Apple Safari";
					else if ( stripos($uagent, "Sleuth") !== false ) $browser = "Xenu\'s Link Sleuth";
					else if ( stripos($uagent, "feedfetcher") !== false ) { $browser = "Google FeedFetcher"; $os = "Online"; }
					else if ( stripos($uagent, "UniversalFeedParser") !== false ) { $browser = "UniversalFeedParser"; $os = "Online"; }
					else if ( stripos($uagent, "FeedDemon") !== false ) { $browser = "FeedDemon RSS reader"; $os = "Online"; }
					else if ( stripos($uagent, "NewsGatorOnline") !== false ) { $browser = "NewsGator Online RSS Reader"; $os = "Online"; }
					else if ( stripos($uagent, "SocialXE ClientBot") !== false ) { $browser = "SocialXE ClientBot"; $os = "Online"; }
					else $browser = "Others";
				}
				if ($os == "") $os = "Others";
		
				if (array_key_exists($os, $OSes)) $OSes[$os] += $count;
				else $OSes[$os] = $count;
		
				if (array_key_exists($browser, $Browsers)) $Browsers[$browser] += $count;
				else $Browsers[$browser] = $count;

				if ( $mobile )	$Types['mobile'] += $count;
				else if ($os == "Online") $Types['online'] += $count;
				else if ($os == "Others") $Types['unknown'] += $count;
				else			$Types['desktop'] += $count;

				$Types['notbot'] += $count;
			}
		}
		arsort($Bots);
		arsort($Browsers);
		arsort($OSes);
		arsort($IEs);
		arsort($Windows);
		arsort($Macs);
		arsort($iOSes);
		arsort($Androids);
		Context::set('Types', $Types);
		Context::set('Bots', $Bots);
		Context::set('Browsers', $Browsers);
		Context::set('OSes', $OSes);
		Context::set('IEs', $IEs);
		Context::set('Windows', $Windows);
		Context::set('Macs', $Macs);
		Context::set('iOSes', $iOSes);
		Context::set('Androids', $Androids);
	}

	function dispRefererAdminConfirmReset(){
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('reset_data');
	}
	
	/**
	 * Set the config.
	 *
	 * @return void
	 */
	public function dispRefererAdminConfig()
	{
		$oRefererModel = &getModel('referer');
		$refererConfig = $oRefererModel->getRefererConfig();
		Context::set('refererConfig', $refererConfig);

		$this->setTemplateFile('referer_config');
	}
}
/* End of file referer.admin.view.php */
/* Location: ./modules/referer/referer.admin.view.php */
