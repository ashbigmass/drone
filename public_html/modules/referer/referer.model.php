<?php
/**
 * @class refererModel
 * @author haneul (haneul0318@gmail.com)
 * @enhanced by KnDol (kndol@kndol.net)
 * @brief referer 모듈의 Model class
 **/

class refererModel extends referer {
	function init() {
	}

	function isInsertedHost($host) {
		$args->host = $host;
		$output = executeQuery('referer.getHostStatus', $args);
		return $output->data->count?true:false;
	}

	function isInsertedRemote($remote) {
		$args->remote = $remote;
		$output = executeQuery('referer.getRemoteStatus', $args);
		return $output->data->count?true:false;
	}

	function isInsertedUAgent($uagent) {
		$args->uagent = $uagent;
		$output = executeQuery('referer.getUAgentStatus', $args);
		return $output->data->count?true:false;
	}

	function isInsertedUser($member_srl) {
		$args->member_srl = $member_srl;
		$output = executeQuery('referer.getUserStatus', $args);
		return $output->data->count?true:false;
	}

	function isInsertedPage($ref_mid, $ref_document_srl) {
		$args->ref_mid = $ref_mid;
		$args->ref_document_srl = $ref_document_srl;
		$output = executeQuery('referer.getPageStatus', $args);
		return $output->data->count?true:false;
	}

	function isInsertedCountry($args) {
		$output = executeQuery('referer.getCountryStatus', $args);
		return $output->data->count?true:false;
	}

	function getRecentRefererList()
	{
		$output = executeQuery('referer.getRecentRefererLog');

		// 결과가 없거나 오류 발생시 그냥 return
		if(!$output->toBool()||!count($output->data)) return;

		return $output->data;
	}

	function getLogList($obj) {
		if ($obj->host) {
			$args->host = $obj->host;
			$query_id = 'referer.getRefererLogListHost';
		}
		else if ($obj->remote) {
			$args->remote = $obj->remote;
			$query_id = 'referer.getRefererLogListRemote';
		}
		else if ($obj->uagent) {
			$args->uagent = $obj->uagent;
			$query_id = 'referer.getRefererLogListUAgent';
		}
		else if ($obj->ref_mid || $obj->ref_document_srl) {
			$args->ref_mid = $obj->ref_mid;
			$args->ref_document_srl = $obj->ref_document_srl;
			$query_id = 'referer.getRefererLogListPage';
		}
		else {
			$query_id = $obj->mode == 'members' ? 'referer.getRefererLogListMembers' : 'referer.getRefererLogList';
			$search_target = $obj->search_target;
			if(strpos($search_target, "date") !== false)
				$args->{"search_".$search_target} = preg_replace("/[^0-9]*/s", "", $obj->search_keyword);
			else
				$args->{"search_".$search_target} = trim($obj->search_keyword);
		}

		$args->sort_index = 'regdate';
		$args->page = $obj->page?$obj->page:1;

		$args->list_count = $obj->list_count?$obj->list_count:20;
		$args->page_count = $obj->page_count?$obj->page_count:10;

		$output = executeQueryArray($query_id, $args);

		return $output;
	}

	function getRefererRanking($ranking_mode, $obj) {
		switch ($ranking_mode) {
			case _REFERER_RANKING_:
				return executeQueryArray("referer.getRefererRanking", $obj);
				break;
			case _REFERER_RANKING_DETAIL_:
				return executeQueryArray("referer.getRefererRankingDetail", $obj);
				break;
			case _REMOTE_RANKING_:
				return executeQueryArray("referer.getRemoteRanking", $obj);
				break;
			case _USER_RANKING_:
				return executeQueryArray("referer.getUserRanking", $obj);
				break;
			case _PAGE_RANKING_:
				return executeQueryArray("referer.getPageRanking", $obj);
				break;
			case _COUNTRY_RANKING_:
				return executeQueryArray("referer.getCountryRanking", $obj);
				break;
			case _UAGENT_RANKING_:
				return executeQueryArray("referer.getUAgentRanking", $obj);
				break;
			case _UAGENT_STATISTICS_:
				return executeQueryArray("referer.getUAgentStatistics", $obj);
				break;
			case _MID_STATISTICS_:
				return executeQueryArray("referer.getMidStatistics", $obj);
				break;
		}
	}
	
	/**
	 * @brief Return referer module's configuration
	 */
	function getRefererConfig()
	{
		static $refererConfig;

		if($refererConfig) return $refererConfig;

		// Get member configuration stored in the DB
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('referer');

		if(!$config->GeoIPSite) $config->GeoIPSite = 'auto';
		$config->timeout = (int)$config->timeout;
		if($config->timeout<1) $config->timeout = 5000;
		$config->delete_olddata = (int)$config->delete_olddata;
		if($config->delete_olddata<0) $config->delete_olddata = 0;

		$refererConfig = $config;

		return $config;
	}
	
	/**
	 * @brief Return information of knwon bots
	 */
	function getRefererKnownBots()
	{
		static $KnownBots;

		if ($KnownBots) return $KnownBots;
		
		$bots = array();
		if (($handle = fopen($this->module_path."/Bots.csv", "r")) !== false) {
			while (($data = fgetcsv($handle, 1000, ",")) !== false) {
				$num = count($data);
				if ($num == 2) {
					$bots[$data[0]] = $data[1];
				}
			}
			fclose($handle);
		}
		
		$KnownBots = $bots;

		return $bots;
	}
	
	function isBot($uagent, &$provider = "")
	{
		static $KnownBots;
		$refererConfig = $this->getRefererConfig();
		
		if (!$uagent) { $provider = "Unknown Bot (No User-Agent String)"; return true; }
		if (!$KnownBots) $KnownBots = $this->getRefererKnownBots();
		
		foreach ($KnownBots as $strBot => $strDesc) {
			if ( stripos($uagent, $strBot) !== false ) {
				$provider = $strDesc;
				return true;
			}
		}
		if ($refererConfig->treat_msie6_bot != no && strstr($uagent, 'MSIE 6.0') !== false ) {
			$provider = "MSIE 6.0";
			return true;
		}
		if ($refererConfig->treat_moz5_bot != no && $uagent == 'Mozilla/5.0' ) {
			$provider = "Mozilla/5.0";
			return true;
		}
		if ( strstr($uagent, 'SocialXE ClientBot') === false
			&& (preg_match('/(bot|spider|crawler)/i',$uagent)
			|| strstr($uagent, 'MSIE or Firefox mutant; not on Windows server;') !== false) )
		{
			$provider = "Others";
			return true;
		}
		return false;
	}
}
/* End of file referer.controller.php */
/* Location: ./modules/referer/referer.model.php */
