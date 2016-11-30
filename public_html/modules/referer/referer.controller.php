<?php
/**
 * @class  refererController
 * @author haneul (haneul0318@gmail.com) 
 * @enhanced by KnDol (kndol@kndol.net)
 * @brief  referer 모듈의 controller class
 **/

class refererController extends referer {
    /**
     * @brief initialization
     **/
    function init() {
    }

	function procRefererExecute() {
		$oRefererModel = &getModel('referer');
		$refererConfig = $oRefererModel->getRefererConfig();

	    $direct_access = empty($_SERVER["HTTP_REFERER"]);
    	if ($refererConfig->include_direct_access == "no" && $direct_access) return;

	    // Log only from different hosts
       	$referer = parse_url($_SERVER["HTTP_REFERER"]);
    	if(!$direct_access && $referer['host'] == $_SERVER['HTTP_HOST']) return;

		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == "Y" && $refererConfig->include_admin != "yes") return;

       	$remote = $_SERVER["REMOTE_ADDR"];
		$uagent = trim(removeHackTag($_SERVER["HTTP_USER_AGENT"]), " \t\n\r\0\x0B'");
		$request_uri = removeHackTag($_SERVER["REQUEST_URI"]);

		$isBot = $oRefererModel->isBot($uagent);
		if($refererConfig->include_bot == 'no' && $isBot) return;
		if($refererConfig->exclude_uagent && preg_match("/$refererConfig->exclude_uagent/i", $uagent)) return;
		if($refererConfig->exclude_host   && ((!empty($_SERVER["HTTP_REFERER"]) && preg_match("/$refererConfig->exclude_host/i", $referer['host'])) || preg_match("/$refererConfig->exclude_host/i", $_SERVER["REMOTE_ADDR"]))) return;

		if($isBot)						$member_srl = -1;
		else if ($logged_info == NULL)	$member_srl = 0;
		else							$member_srl = $logged_info->member_srl;
		
		// 접근 페이지 정보 구하기
		$ref_mid = Context::get('mid');
		$ref_document_srl = Context::get('document_srl');
		if (!$ref_mid && $ref_document_srl) {
			$oModuleModel = getModel('module');
			$module = $oModuleModel->getModuleInfoByDocumentSrl($ref_document_srl);
			$ref_mid = $module->mid;
		}
		// mid를 구할 수 없으면 홈페이지에 접속한 것
		if (!$ref_mid) {
			$site_info = Context::get('site_module_info');
			$ref_mid = $site_info->mid;
			$ref_document_srl = '';
		}

		// 접속한 국가 정보 구하기
		if ($refererConfig->logging_country == 'yes' && function_exists('curl_init'))
		{
			$geoip = array('nekudo'=>'http://geoip.nekudo.com/api/', 'cdnservice'=>'http://geoip.cdnservice.eu/api/',
				'petabyet'=>'http://api.petabyet.com/geoip/', 'ipapi'=>'http://ip-api.com/json/', 'smartip'=>'http://smart-ip.net/geoip-json/');
			$lang_opt = array('ipapi'=>'', 'smartip'=>'', 'nekudo'=>'/en', 'cdnservice'=>'/en', 'petabyet'=>'');

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_TIMEOUT, $refererConfig->timeout/1000);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			if ($refererConfig->GeoIPSite == 'auto')
			{
				foreach ($geoip AS $key => $val) {
					curl_setopt($curl, CURLOPT_URL, $val . $remote . $lang_opt[$key]);
					$response = curl_exec($curl);
					$location = json_decode($response);
					if ($location) {
						$refererConfig->GeoIPSite = $key;
						break;
					}
				}
			}
			else
			{
				curl_setopt($curl, CURLOPT_URL, $geoip[$refererConfig->GeoIPSite] . $remote . $lang_opt[$refererConfig->GeoIPSite]);
				$response = curl_exec($curl);
				$location = json_decode($response);
			}
			curl_close($curl);
			// 각 서비스마다 조금씩 다른 변수명을 일치시킴
			if ($refererConfig->GeoIPSite == 'ipapi') {
				$location->country_code = $location->countryCode;
			}
			else if ($refererConfig->GeoIPSite == 'smartip') {
				$location->country_code = $location->countryCode;
				$location->country = $location->countryName;
			}
			else if ($refererConfig->GeoIPSite == 'nekudo' || $refererConfig->GeoIPSite == 'cdnservice') {
				$location->country_code = $location->country->code;
				$location->country = $location->country->name;
			}
		}

	    $oDB = &DB::getInstance();
	    $oDB->begin();
	    $ret = $this->insertRefererLog($remote, $referer['host'], $direct_access ? "http://localhost" : removeHackTag($_SERVER["HTTP_REFERER"]), $uagent, $member_srl, $request_uri, $ref_mid, $ref_document_srl, $location);
	    if(!$ret->error)
	    {
		    $this->deleteOlddatedRefererLogs($refererConfig->delete_olddata);
		    $this->updateRefererStatistics($remote, $referer['host'], $uagent, $member_srl, $ref_mid, $ref_document_srl, $location);
		    $oDB->commit();
		}
	}

	function updateRefererStatistics($remote, $host, $uagent, $member_srl, $ref_mid, $ref_document_srl, $location)
	{
	    $oRefererModel = &getModel('referer');
	    
	    $args->remote = $remote;
		if ($location != NULL && $location->country_code != "")
		{
			$args->country_code = $location->country_code;
			$args->country      = $location->country;
		}
		else {
			$args->country = $args->country_code = NULL;
		}
	    if($oRefererModel->isInsertedRemote($remote))
	    {
			$output = executeQuery('referer.updateRemoteStatistics', $args);
	    }
	    else
	    {
			$output = executeQuery('referer.insertRemoteStatistics', $args);
	    }
		if($host != "") {
		    $args->host = $host;
		    if($oRefererModel->isInsertedHost($host))
		    {
				$output = executeQuery('referer.updateRefererStatistics', $args);
		    }
		    else
		    {
				$output = executeQuery('referer.insertRefererStatistics', $args);
		    }
		}
		if($uagent != "") {
		    $args->uagent = $uagent;
		    if($oRefererModel->isInsertedUAgent($uagent))
		    {
				$output = executeQuery('referer.updateUAgentStatistics', $args);
		    }
		    else
		    {
				$output = executeQuery('referer.insertUAgentStatistics', $args);
		    }
		}

	    $args->member_srl = $member_srl;
	    if($oRefererModel->isInsertedUser($member_srl))
	    {
			$output = executeQuery('referer.updateUserStatistics', $args);
	    }
	    else
	    {
			$output = executeQuery('referer.insertUserStatistics', $args);
	    }

	    $args->ref_mid = $ref_mid;
	    $args->ref_document_srl = $ref_document_srl;
	    if($oRefererModel->isInsertedPage($ref_mid, $ref_document_srl))
	    {
			$output = executeQuery('referer.updatePageStatistics', $args);
	    }
	    else
	    {
			$output = executeQuery('referer.insertPageStatistics', $args);
	    }

		if ($location != NULL && $location->country_code != "")
		{
		    if($oRefererModel->isInsertedCountry($args))
		    {
				$output = executeQuery('referer.updateCountryStatistics', $args);
		    }
		    else
		    {
				$output = executeQuery('referer.insertCountryStatistics', $args);
		    }
		}

	    return $output;
	}

	function insertRefererLog($remote, $host, $url, $uagent, $member_srl, $request_uri, $ref_mid, $ref_document_srl, $location)
	{
	    $recent = &getModel('referer')->getRecentRefererList();
	    if($recent->remote == $remote && $recent->url == $url && $recent->uagent == $uagent && $recent->member_srl == $member_srl)
	    {
	    	$args->remote			= $remote;
	    	$args->url				= $url;
	    	$args->uagent			= $uagent;
	    	$args->member_srl		= $member_srl;
	    	$args->regdate			= $recent->regdate;
	    	$args->request_uri		= $recent->request_uri;
		    $args->regdate_last		= date("YmdHis");

		    return executeQuery('referer.updateRefererLog', $args);
	    }
		else
		{
		    $args->host				= $host;
		    $args->remote			= $remote;
		    $args->url				= $url;
		    $args->uagent			= $uagent;
	    	$args->member_srl		= $member_srl;
	    	$args->request_uri		= $request_uri;
			$args->ref_mid 			= $ref_mid;
			$args->ref_document_srl	= $ref_document_srl;
			$args->country_code 	= ($location != NULL && $location->country_code != "") ? $location->country_code : NULL;
		    $args->regdate 			= $args->regdate_last = date("YmdHis");

		    return executeQuery('referer.insertRefererLog', $args);
	    }
	}

	function deleteOlddatedRefererLogs($delete_olddata)
	{
		if($delete_olddata<1) return true;
		$day = "-" . (($delete_olddata == 1) ? $delete_olddata . " day" : $delete_olddata . " days");
	    $args->regdate = date("YmdHis", strtotime($day));
	    return executeQuery('referer.deleteOlddatedLogs', $args);
	}
}
/* End of file referer.controller.php */
/* Location: ./modules/referer/referer.controller.php */
