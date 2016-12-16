<?php
class statsModel extends stats
{
	function init() {
	}

	function getInfo($site_srl, $module_srl, $document_srl = null, $from = false, $to = false) {
		if(!$from) $from = date('Ymd000000', mktime()-(60*60*24*3));
		if(!$to) $to = date('Ymd235959');
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->from = $from;
		$args->to = $to;
		$args->type = array();
		if(!empty($document_srl)) {
			$args->type[] = 'pv.'.$module_srl.'.'.$document_srl;
			$args->type[] = 'uv.'.$module_srl.'.'.$document_srl;
			$args->depth = 3;
		} else {
			$args->type[] = 'pv.'.$module_srl;
			$args->type[] = 'uv.'.$module_srl;
			$args->depth = 2;
		}
		$output = executeQuery('stats.getSum', $args);
		if($output->data) return $output->data;
		return false;
	}

	function checkAddon($site_srl) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->addon = 'stats';
		$args->is_used = 'Y';
		$args->is_used_m = 'Y';
		$output = executeQuery('stats.checkAddons', $args);
		if($output->data) { return true; }
		return false;
	}

	function insertView($key, $site_srl, $module_srl, $document_srl, $is_bot = false, $is_mobile = false, $depths = array(1,2,3)) {
		$insert_time = date('Ymd');
		$pageview_type = $key;
		$args = new StdClass;
		$args->type = $pageview_type;
		$args->site_srl = $site_srl;
		$args->insert_time = $insert_time;
		if(in_array(1, $depths)) {
			$args->depth = 1;
			$output = executeQuery('stats.get', $args);
			if(!$output->data) {
				$args->count = 1;
				if($is_bot) $args->bot_count = 1;
				else $args->bot_count = 0;
				if($is_mobile) $args->mobile_count = 1;
				else $args->mobile_count = 0;
				executeQuery('stats.insert', $args);
			} else {
				if($is_bot) executeQuery('stats.updateByBot', $args);
				else if($is_mobile) executeQuery('stats.updateByMobile', $args);
				else executeQuery('stats.update', $args);
			}
		}
		if(in_array(2, $depths)) {
			$args->depth = 2;
			$pageview_type = $key.'.'.$module_srl;
			$args->type = $pageview_type;
			if($module_srl) $args->value = $module_srl;
			unset($args->count);
			$output = executeQuery('stats.get', $args);
			if(!$output->data) {
				$args->count = 1;
				if($is_bot) $args->bot_count = 1;
				else $args->bot_count = 0;
				if($is_mobile) $args->mobile_count = 1;
				else $args->mobile_count = 0;
				executeQuery('stats.insert', $args);
			} else {
				if($is_bot) executeQuery('stats.updateByBot', $args);
				else if($is_mobile) executeQuery('stats.updateByMobile', $args);
				else executeQuery('stats.update', $args);
			}
		}
		if(in_array(3, $depths) && $document_srl) {
			$args->depth = 3;
			$pageview_type = $key.'.'.$module_srl.'.'.$document_srl;
			$args->type = $pageview_type;
			if($document_srl) $args->value = $document_srl;
			unset($args->count);
			$output = executeQuery('stats.get', $args);
			if(!$output->data) {
				$args->count = 1;
				if($is_bot) $args->bot_count = 1;
				else $args->bot_count = 0;
				if($is_mobile) $args->mobile_count = 1;
				else $args->mobile_count = 0;
				executeQuery('stats.insert', $args);
			} else {
				if($is_bot) executeQuery('stats.updateByBot', $args);
				else if($is_mobile) executeQuery('stats.updateByMobile', $args);
				else executeQuery('stats.update', $args);
			}
		}
	}

	function insertPageView($site_srl, $module_srl, $document_srl, $is_bot, $is_mobile) {
		$this->insertView('pv', $site_srl, $module_srl, $document_srl, $is_bot, $is_mobile, array(1,2,3));
	}

	function insertUniquePageView($site_srl, $module_srl, $document_srl, $session_id, $is_bot, $is_mobile) {
		$depths = array();
		if(!$this->getLog($site_srl,false,false,$session_id)) $depths[] = 1;
		if($module_srl && !$this->getLog($site_srl, $module_srl, false, $session_id)) $depths[] = 2;
		if($document_srl && $module_srl && !$this->getLog($site_srl, $module_srl, $document_srl, $session_id)) $depths[] = 3;
		if(count($depths)) $this->insertView('uv', $site_srl, $module_srl, $document_srl, $is_bot, $is_mobile, $depths);
		return $depths;
	}

	function insertLog($site_srl, $module_srl, $document_srl, $session_id, $user_agent, $referer, $ip_address, $now, $is_bot, $is_mobile) {
		$insert_time = date('YmdHis');
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->module_srl = $module_srl;
		$args->document_srl = $document_srl;
		$args->user_session_id = $session_id;
		$args->user_agent = $user_agent;
		$args->user_referer = $referer;
		$args->user_ip_address = $ip_address;
		$args->user_now = $now;
		$args->is_bot = $is_bot ? 1 : 0;
		$args->is_mobile = $is_mobile ? 1 : 0;
		$args->insert_time = $insert_time;
		executeQuery('stats.insertLog', $args);
	}

	function getLog($site_srl, $module_srl, $document_srl, $session_id, $from = null, $to = null) {
		if(!$from) $from = date('Ymd000000');
		if(!$to) $to = date('Ymd235959');
		$args = new StdClass;
		$args->site_srl = $site_srl;
		if($module_srl !== false) $args->module_srl = $module_srl;
		if($document_srl !== false) $args->document_srl = $document_srl;
		$args->user_session_id = $session_id;
		$args->from = $from;
		$args->to = $to;
		if($result = executeQuery('stats.getLog', $args)) {
			if(!empty($result->data)) return $result->data;
			return false;
		}
		return false;
	}

	function getRefererLog($site_srl, $session_id, $referer, $insert_time) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->user_session_id = $session_id;
		$args->user_referer = $referer;
		$args->from = $insert_time;
		$args->to = $insert_time;
		if($result = executeQuery('stats.getLog', $args)) return empty($result->data) ? false : $result->data;
		return false;
	}

	function insertReferer($site_srl, $session_id, $referer, $is_bot, $is_mobile) {
		$insert_time = date('Ymd');
		$db_info = Context::getDBInfo();
		$default_urls = parse_url($db_info->default_url);
		if($default_urls['host'] == $referer['domain']) return false;
		if($this->getRefererLog($site_srl, $session_id, $referer['full'], $insert_time)) return false;
		if(empty($referer['domain'])) $referer['domain'] = 'direct';
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'rfr';
		$args->depth = 1;
		$args->value = $referer['domain'];
		$args->insert_time = $insert_time;
		$output = executeQuery('stats.get', $args);
		if(!$output->data) {
			$args->count = 1;
			if($is_bot) $args->bot_count = 1;
			else $args->bot_count = 0;
			if($is_mobile) $args->mobile_count = 1;
			else $args->mobile_count = 0;
			executeQuery('stats.insert', $args);
		} else {
			if($is_bot) executeQuery('stats.updateByBot', $args);
			else if($is_mobile) executeQuery('stats.updateByMobile', $args);
			else executeQuery('stats.update', $args);
		}
		$search_term = $this->parseSearchTerm($referer['full']);
		if(!empty($referer['full'])) {
			$args = new StdClass;
			$args->site_srl = $site_srl;
			if($search_term) $args->referer = $search_term['engine'] . '.' . $search_term['term'];
			else $args->referer = $referer['full'];
			$args->insert_time = $insert_time;
			$output = executeQuery('stats.getReferer', $args);
			if(!$output->data) {
				$args->count = 1;
				executeQuery('stats.insertReferer', $args);
			} else {
				executeQuery('stats.updateReferer', $args);
			}
		}
		if($search_term) {
			$args = new StdClass;
			$args->site_srl = $site_srl;
			$args->type = 'stx.'.$search_term['engine'];
			$args->depth = 1;
			$args->value = $search_term['term'];
			$args->insert_time = $insert_time;
			$output = executeQuery('stats.get', $args);
			if(!$output->data) {
				$args->count = 1;
				executeQuery('stats.insert', $args);
			} else {
				executeQuery('stats.update', $args);
			}
		}
	}

	function insertUserAgent($site_srl, $user_agent, $is_bot, $is_mobile) {
		if(empty($user_agent['platform'])) return false;
		$insert_time = date('Ymd');
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->depth = 1;
		$args->insert_time = $insert_time;
		$args->type = 'pltf';
		$args->value = $user_agent['platform'];
		$output = executeQuery('stats.get', $args);
		if(!$output->data) {
			$args->count = 1;
			if($is_bot) $args->bot_count = 1;
			else $args->bot_count = 0;
			if($is_mobile) $args->mobile_count = 1;
			else $args->mobile_count = 0;
			executeQuery('stats.insert', $args);
		} else {
			if($is_bot) executeQuery('stats.updateByBot', $args);
			else if($is_mobile) executeQuery('stats.updateByMobile', $args);
			else executeQuery('stats.update', $args);
		}
		$args->type = 'brws';
		$args->value = $user_agent['browser'];
		$output = executeQuery('stats.get', $args);
		if(!$output->data) {
			$args->count = 1;
			if($is_bot) $args->bot_count = 1;
			else $args->bot_count = 0;
			if($is_mobile) $args->mobile_count = 1;
			else $args->mobile_count = 0;
			executeQuery('stats.insert', $args);
		} else {
			if($is_bot) executeQuery('stats.updateByBot', $args);
			else if($is_mobile) executeQuery('stats.updateByMobile', $args);
			else executeQuery('stats.update', $args);
		}
	}

	function parseSearchTerm($url) {
		$pregs = array('google'=>'^https?:\/\/www\.google\..*[\?&|#]q\=([^&]*).*',
			'naver'=>'^https?\:\/\/search\.naver\.com.*[\?&]query\=([^&]*).*',
			'daum'=>'^https?\:\/\/search\.daum\.net\/search.*[\?&]q\=([^&]*).*',
			'nate'=>'^https?\:\/\/search\.daum\.net\/nate.*[\?&]q\=([^&]*).*',
			'yahoo'=>'^https?\:\/\/search\.yahoo\.com\/.*[\?&]p\=([^&]*).*',
			'bing'=>'^https?\:\/\/www\.bing\.com\/.*[\?&]q\=([^&]*).*',
			'zum'=>'^https?\:\/\/search\.zum\.com\/.*[\?&]query\=([^&]*).*'
		);
		foreach($pregs as $engine => $preg) {
			if (preg_match("/$preg/i", $url, $matches)) {
				$searchengine = $engine;
				$searchterm = $matches[1];
				$searchterm = urldecode($searchterm);
				return array('engine'=>$engine, 'term'=>$searchterm);
			}
		}
		return false;
	}

	function insertSeatchTermByInside($site_srl, $now) {
		if($stx = $this->parseSearchTermByInSide($now)) {
			$insert_time = date('Ymd');
			$args = new StdClass;
			$args->site_srl = $site_srl;
			$args->type = 'in.stx';
			$args->depth = 1;
			$args->value = $stx;
			$args->insert_time = $insert_time;
			$output = executeQuery('stats.get', $args);
			if(!$output->data) {
				$args->count = 1;
				executeQuery('stats.insert', $args);
			} else {
				executeQuery('stats.update', $args);
			}
		}
	}

	function parseSearchTermByInSide($url) {
		$urls = parse_url($url);
		if($default_urls['host'] == $urls['host']) {
			if(preg_match('/(search_keyword|is_keyword=)(\=|)([^&]*).*/i', $urls['query'], $match)) {
				if(!empty($match[3])) return urldecode($match[3]);
			}
		}
		return false;
	}

	function getDocumentCount($site_srl, $from, $to) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->from = $from;
		$args->to = $to;
		$output = executeQuery('stats.getDocumentCount', $args);
		if($output->data) return $output->data->count;
		return false;
	}

	function getDailyOverview($site_srl, $from, $to, $order = 'asc') {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->from = $from;
		$args->to = $to;
		$args->depth = 1;
		$args->type = array('pv','uv');
		$dates = array();
		$year = substr($from, 0, 4);
		$month = substr($from, 4, 2);
		$day = substr($from,6,2);
		$start_date = strtotime($year.'-'.$month.'-'.$day);
		$year = substr($to, 0, 4);
		$month = substr($to, 4, 2);
		$day = substr($to,6,2);
		$end_date = strtotime($year.'-'.$month.'-'.$day);
		for($date = $start_date; $date <= $end_date; $date += 60*60*24) {
			 $data = new StdClass;
			 $data->regdate = date('Ymd', $date);
			 $data->regtime = $date;
			 $data->regtext = date('Y-m-d H:i:s', $date);
			 $data->unique_view = 0;
			 $data->unique_mobile_view = 0;
			 $data->page_view = 0;
			 $data->page_mobile_view = 0;
			 $data->robot_view = 0;
			 $dates[$data->regdate] = $data;
		}
		$output = executeQuery('stats.getDaily', $args);
		if($output->data) {
			foreach($output->data as $data) {
				$key = $data->insert_time;
				if($data->type == 'pv') {
					$dates[$key]->page_view = $data->count - $data->bot_count;
					$dates[$key]->page_mobile_view = $data->mobile_count;
					$dates[$key]->robot_view = $data->bot_count;
				} else if($data->type == 'uv') {
					$dates[$key]->unique_view = $data->count - $data->bot_count;
					$dates[$key]->unique_mobile_view = $data->mobile_count;
				}
			}
			if($order == 'desc') return array_reverse($dates, true);
			return $dates;
		}
		return false;
	}

	function getSearchTermTop($site_srl, $from, $to, $count = 5) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'stx.%';
		$args->from = $from;
		$args->to = $to;
		$args->limit = $count;
		$output = executeQuery('stats.getSearchTerm', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getSearchTermTopEngine($site_srl, $from, $to, $count = 5) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'stx.%';
		$args->from = $from;
		$args->to = $to;
		$args->limit = $count;
		$output = executeQuery('stats.getSearchTermEngine', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getInsideSearchTermTop($site_srl, $from, $to, $count = 5) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'in.stx';
		$args->from = $from;
		$args->to = $to;
		$args->limit = $count;
		$output = executeQuery('stats.getSearchTerm', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getDocumentTop($site_srl, $from, $to, $count) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'uv.%';
		$args->from = $from;
		$args->to = $to;
		$args->depth = 3;
		$args->limit = $count;
		$output = executeQuery('stats.getDocument', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getModuleTop($site_srl, $from, $to, $count) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'uv.%';
		$args->from = $from;
		$args->to = $to;
		$args->depth = 2;
		$args->limit = $count;
		$output = executeQuery('stats.getModule', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getRefererTop($site_srl, $from, $to, $count) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'rfr';
		$args->from = $from;
		$args->to = $to;
		$args->depth = 1;
		$args->limit = $count;
		$output = executeQuery('stats.getTop', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getRefererTopDetail($site_srl, $from, $to, $count) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->from = $from;
		$args->to = $to;
		$args->limit = $count;
		$output = executeQuery('stats.getRefererTop', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getPlatformTop($site_srl, $from, $to, $count) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'pltf';
		$args->from = $from;
		$args->to = $to;
		$args->depth = 1;
		$args->limit = $count;
		$output = executeQuery('stats.getTop', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getBrowserTop($site_srl, $from, $to, $count) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->type = 'brws';
		$args->from = $from;
		$args->to = $to;
		$args->depth = 1;
		$args->limit = $count;
		$output = executeQuery('stats.getTop', $args);
		if($output->data) return $output->data;
		return false;
	}

	function getLogCount($site_srl, $search_type = '', $search_value = '') {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		if($search_type) {
			switch($search_type) {
				case 'ip_address':
					$args->user_ip_address = $search_value;
				break;
				case 'referer':
					$args->user_referer = '%' . $search_value . '%';
				break;
				case 'platform':
				case 'browser':
					$args->user_agent = '%' . $search_value . '%';
				break;
				case 'srl':
					$cuts = explode(',',$search_value);
					if(count($cuts)==3) {
						$args->module_srl = $cuts[1];
						$args->document_srl = $cuts[2];
					} else if(count($cuts)==2) {
						$args->module_srl = $cuts[1];
					}
				break;
			}
		}
		$output = executeQuery('stats.getLogsCount', $args);
		if($output->data) return $output->data->count;
		return false;
	}

	function getLogs($site_srl, $search_type = '', $search_value = '', $page = 1, $count = 5) {
		$args = new StdClass;
		$args->site_srl = $site_srl;
		$args->page = $page;
		$args->limit = $count;
		if($search_type) {
			switch($search_type) {
				case 'ip_address':
					$args->user_ip_address = $search_value;
				break;
				case 'referer':
					$args->user_referer = '%' . $search_value . '%';
				break;
				case 'platform':
				case 'browser':
					$args->user_agent = '%' . $search_value . '%';
				break;
				case 'srl':
					$cuts = explode(',',$search_value);
					if(count($cuts)==3) {
						$args->module_srl = $cuts[1];
						$args->document_srl = $cuts[2];
					} else if(count($cuts)==2) {
						$args->module_srl = $cuts[1];
					}
				break;
			}
		}
		$output = executeQuery('stats.getLogs', $args);
		if($output->data) return $output->data;
		return false;
	}
}
