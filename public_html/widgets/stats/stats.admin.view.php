<?php
require_once(_XE_PATH_ . 'modules/stats/library/stats_library.php');

class statsAdminView extends stats
{
	protected $selected_range;
	protected $from_date;
	protected $to_date;
	protected $statsModel;
	protected $site_srl;
	protected $menu;

	function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	function dispStatsAdminIndex() {
		$logged_info = Context::get('logged_info');
		$this->selected_range = Context::get('selected_range');
		if(!$this->selected_range) $this->selected_range = 7;
		Context::set('selected_range', $this->selected_range);
		$this->from_date = date('Ymd', mktime()-(60*60*24*($this->selected_range-1)));
		$this->to_date = date('Ymd');
		Context::set('from_date', $this->from_date);
		Context::set('to_date', $this->to_date);
		$configs = $this->getConfig();
		Context::set('stats_configs', $configs);
		$this->menu = Context::get('menu');
		if(empty($this->menu)) {
			$this->menu = 'overview';
			Context::set('menu', $this->menu);
		}
		$site_module_info = Context::get('site_module_info');
		$this->site_srl = (int) $site_module_info->site_srl;
		switch($this->menu) {
			case 'overview': $this->overview(); break;
			case 'traffic': $this->traffic(); break;
			case 'referer': $this->referer(); break;
			case 'document': $this->document(); break;
			case 'search': $this->search(); break;
			case 'log': $this->log(); break;
			case 'setting': $this->setting(); break;
		}
	}

	private function overview() {
		$statsModel = getModel('stats');
		$addon_on = $statsModel->checkAddon($this->site_srl);
		Context::set('addon_on', $addon_on);
		$dailys = $statsModel->getDailyOverview($this->site_srl, $this->from_date, $this->to_date);
		$index = 0;
		$daily_page_views = array();
		$daily_unique_views = array();
		$daily_robot_views = array();
		$daily_labels = array();
		$full_page_view = 0;
		$full_unique_view = 0;
		$full_robot_view = 0;
		$full_mobile_view = 0;
		foreach($dailys as $day=>$daily) {
			$daily_page_views[] = '[' . $index . ', ' . $daily->page_view . ']';
			$daily_unique_views[] = '[' . $index . ', ' . $daily->unique_view . ']';
			$daily_robot_views[] = '[' . $index . ', ' . $daily->robot_view . ']';
			$full_page_view += $daily->page_view;
			$full_unique_view += $daily->unique_view;
			$full_mobile_view += $daily->unique_mobile_view;
			$full_robot_view += $daily->robot_view;
			$daily_labels[] = '"' . substr($daily->regtext,5,5) . '"';
			$index ++;
		}
		Context::set('daily_page_views', implode(',', $daily_page_views));
		Context::set('daily_unique_views', implode(',', $daily_unique_views));
		Context::set('daily_robot_views', implode(',', $daily_robot_views));
		Context::set('daily_labels', implode(',',$daily_labels));
		Context::set('full_page_view', $full_page_view);
		Context::set('full_unique_view', $full_unique_view);
		Context::set('full_mobile_view', $full_mobile_view);
		Context::set('full_robot_view', $full_robot_view);
		$mobile_graph = array();
		$mobile_data = new StdClass;
		$mobile_data->label = "모바일";
		$mobile_data->count = $full_mobile_view;
		$mobile_data->color = '#000000';
		$mobile_graph[] = $mobile_data;
		$empty_data = new StdClass;
		$empty_data->label = "나머지";
		$empty_data->count = $full_unique_view - $full_mobile_view;
		$empty_data->color = '#bbbbbb';
		$mobile_graph[] = $empty_data;
		Context::set('mobile_graph', $mobile_graph);
		$robot_graph = array();
		$robot_data = new StdClass;
		$robot_data->label = "로봇";
		$robot_data->count = $full_robot_view;
		$robot_data->color = '#000000';
		$robot_graph[] = $robot_data;
		$empty_data = new StdClass;
		$empty_data->label = "나머지";
		$empty_data->count = $full_page_view - $full_robot_view;
		$empty_data->color = '#bbbbbb';
		$robot_graph[] = $empty_data;
		Context::set('robot_graph', $robot_graph);
		$from = $this->to_date;
		$to = $this->to_date;
		$document_count = $statsModel->getDocumentCount($this->site_srl, $from, $to);
		$todays = new StdClass;
		$todays->document = $document_count;
		$today = end($dailys);
		if($today) {
			$todays->unique_view = $today->unique_view;
			$todays->unique_mobile_view = $today->unique_mobile_view;
			$todays->page_view = $today->page_view;
			$todays->robot_view = $today->robot_view;
		} else {
			$todays->unique_view = 0;
			$todays->unique_mobile_view = 0;
			$todays->page_view = 0;
			$todays->robot_view = 0;
		}
		Context::set('todays', $todays);
		$yesterdays = new StdClass;
		$yesterday = prev($dailys);
		if($yesterday) {
			$yesterdays->unique_view = $yesterday->unique_view;
			$yesterdays->unique_mobile_view = $yesterday->unique_mobile_view;
			$yesterdays->page_view = $yesterday->page_view;
			$yesterdays->robot_view = $yesterday->robot_view;
		}
		Context::set('yesterdays', $yesterdays);
		$search_terms = $statsModel->getSearchTermTop($this->site_srl, $this->from_date, $this->to_date, 8);
		if($search_terms && !is_array($search_terms)) $search_terms = array($search_terms);
		Context::set('search_terms', $search_terms);
		$documents = $statsModel->getDocumentTop($this->site_srl, $this->from_date, $this->to_date, 3);
		if($documents && !is_array($documents)) $documents = array($documents);
		Context::set('documents', $documents);
		$referers = $statsModel->getRefererTop($this->site_srl, $this->from_date, $this->to_date, 3);
		if($referers && !is_array($referers)) $referers = array($referers);
		Context::set('referers', $referers);
		$browsers = $statsModel->getBrowserTop($this->site_srl, $this->from_date, $this->to_date, 1);
		$browser = is_array($browsers) ? current($browsers) : $browsers;
		Context::set('browser', $browser);
		if($browser) $full_browser_view = $browser->sum_count;
		else $full_browser_view = 0;
		$browser_graph = array();
		$browser_data = new StdClass;
		$browser_data->label = "브라우저";
		$browser_data->count = $full_browser_view;
		$browser_data->color = '#000000';
		$browser_graph[] = $browser_data;
		$empty_data = new StdClass;
		$empty_data->label = "나머지";
		$empty_data->count = $full_unique_view - $full_browser_view;
		$empty_data->color = '#bbbbbb';
		$browser_graph[] = $empty_data;
		Context::set('browser_graph', $browser_graph);
		$this->setTemplateFile('overview');
	}

	private function traffic() {
		$statsModel = getModel('stats');
		$dailys = $statsModel->getDailyOverview($this->site_srl, $this->from_date, $this->to_date, 'desc');
		if($dailys && !is_array($dailys)) $dailys = array($dailys);
		$full_daily_page_view_count = 0;
		$full_daily_unique_view_count = 0;
		$full_daily_robot_view_count = 0;
		foreach($dailys as $daily) {
			$full_daily_page_view_count += $daily->page_view;
			$full_daily_unique_view_count += $daily->unique_view;
			$full_daily_robot_view_count += $daily->page_view;
		}
		Context::set('full_daily_page_view_count', $full_daily_page_view_count);
		Context::set('full_daily_unique_view_count', $full_daily_unique_view_count);
		Context::set('full_daily_robot_view_count', $full_daily_robot_view_count);
		Context::set('dailys', $dailys);
		$platforms = $statsModel->getPlatformTop($this->site_srl, $this->from_date, $this->to_date, 5);
		if($platforms && !is_array($platforms)) $platforms = array($platforms);
		$full_platform_count = 0;
		foreach($platforms as $platform) $full_platform_count += $platform->sum_count;
		Context::set('full_platform_count', $full_platform_count);
		Context::set('platforms', $platforms);
		$browsers = $statsModel->getBrowserTop($this->site_srl, $this->from_date, $this->to_date, 5);
		if($browsers && !is_array($browsers)) $browsers = array($browsers);
		$full_browser_count = 0;
		foreach($browsers as $browser) $full_browser_count += $browser->sum_count;
		Context::set('full_browser_count', $full_browser_count);
		Context::set('browsers', $browsers);
		$this->setTemplateFile('traffic');
	}

	private function referer() {
		$statsModel = getModel('stats');
		$referers = $statsModel->getRefererTop($this->site_srl, $this->from_date, $this->to_date, 9);
		if($referers && !is_array($referers)) $referers = array($referers);
		$referer_domains = array();
		$referer_full_count = 0;
		if(count($referers)) {
			$brightness = 1;
			$brightness_step = 1/count($referers);
			foreach($referers as $referer) {
				$color = $this->__colourBrightness('#333333', $brightness);
				$referer_data = new StdClass;
				$referer_data->label = $referer->value;
				$referer_data->count = $referer->sum_count;
				$referer_data->color = $color;
				$referer_domains[] = $referer_data;
				$referer_full_count += $referer->sum_count;
				$brightness -= $brightness_step;;
			}
		}
		Context::set('referer_full_count', $referer_full_count);
		Context::set('referer_domains', $referer_domains);
		$detail_referers = $statsModel->getRefererTopDetail($this->site_srl, $this->from_date, $this->to_date, 10);
		if($detail_referers && !is_array($detail_referers)) $detail_referers = array($detail_referers);
		foreach($detail_referers as $key=>$referer) {
			if(substr($referer->referer,0,4) == 'http') {
				$detail_referers[$key]->link = $referer->referer;
				continue;
			}
			$cuts = explode('.', $referer->referer, 2);
			if(count($cuts) == 2) {
				$cuts[0] = str_replace(array('google','naver','daum','nate','yahoo','bing','zum'), array('구글','네이버','다음','네이트','야후','빙','줌'), $cuts[0]);
				$new_referer = $cuts[0] . ' 검색결과 : ' . $cuts[1];
				$detail_referers[$key]->referer = $new_referer;
			}
		}
		Context::set('detail_referers', $detail_referers);
		$this->setTemplateFile('referer');
	}

	private function document() {
		$statsModel = getModel('stats');
		$modules = $statsModel->getModuleTop($this->site_srl, $this->from_date, $this->to_date, 9);
		if($modules && !is_array($modules)) $modules = array($modules);
		$module_datas = array();
		$module_full_count = 0;
		if(count($modules)) {
			$brightness = 1;
			$brightness_step = 1/count($modules);
			foreach($modules as $module) {
				$color = $this->__colourBrightness('#333333', $brightness);
				$module_data = new StdClass;
				$module_data->label = $module->module_title;
				$module_data->mid = $module->module_mid;
				$module_data->count = $module->sum_count;
				$module_data->color = $color;
				$module_datas[] = $module_data;
				$module_full_count += $module->sum_count;
				$brightness -= $brightness_step;;
			}
		}
		Context::set('module_full_count', $module_full_count);
		Context::set('module_datas', $module_datas);
		$documents = $statsModel->getDocumentTop($this->site_srl, $this->from_date, $this->to_date, 10);
		if($documents && !is_array($documents)) $documents = array($documents);
		Context::set('document_datas', $documents);
		$this->setTemplateFile('document');
	}

	private function search() {
		$statsModel = getModel('stats');
		$search_terms = $statsModel->getSearchTermTop($this->site_srl, $this->from_date, $this->to_date, 9);
		if($search_terms && !is_array($search_terms)) $search_terms = array($search_terms);
		$search_term_datas = array();
		$search_term_full_count = 0;
		if(count($search_terms)) {
			$brightness = 1;
			$brightness_step = 1/count($search_terms);
			foreach($search_terms as $search_term) {
				$color = $this->__colourBrightness('#333333', $brightness);
				$search_term_data = new StdClass;
				$search_term_data->label = $search_term->value;
				$search_term_data->count = $search_term->sum_count;
				$search_term_data->color = $color;
				$search_term_datas[] = $search_term_data;
				$search_term_full_count += $search_term->sum_count;
				$brightness -= $brightness_step;;
			}
		}
		Context::set('search_term_full_count', $search_term_full_count);
		Context::set('search_term_datas', $search_term_datas);
		$inside_search_terms = $statsModel->getInsideSearchTermTop($this->site_srl, $this->from_date, $this->to_date, 9);
		if($inside_search_terms && !is_array($inside_search_terms)) $inside_search_terms = array($inside_search_terms);
		$inside_search_term_datas = array();
		$inside_search_term_full_count = 0;
		if(count($inside_search_terms)) {
			$brightness = 1;
			$brightness_step = 1/count($inside_search_terms);
			foreach($inside_search_terms as $inside_search_term) {
				$color = $this->__colourBrightness('#333333', $brightness);
				$inside_search_term_data = new StdClass;
				$inside_search_term_data->label = $inside_search_term->value;
				$inside_search_term_data->count = $inside_search_term->sum_count;
				$inside_search_term_data->color = $color;
				$inside_search_term_datas[] = $inside_search_term_data;
				$inside_search_term_full_count += $inside_search_term->sum_count;
				$brightness -= $brightness_step;;
			}
		}
		Context::set('inside_search_term_full_count', $inside_search_term_full_count);
		Context::set('inside_search_term_datas', $inside_search_term_datas);
		$this->setTemplateFile('search');
	}

	private function log() {
		$stats_library = new stats_library();
		$statsModel = getModel('stats');
		$search_type = Context::get('search_type');
		$search_value = Context::get('search_value');
		$page = Context::get('page');
		if(!$page) {
			$page = 1;
			Context::set('page', $page);
		}
		$page_count = 15;
		$logs = $statsModel->getLogs($this->site_srl, $search_type, $search_value, $page, $page_count);
		foreach($logs as $key => $log) {
			if(!empty($log->user_agent)) {
				$logs[$key]->user_agent_parse = $stats_library->parse_user_agent($log->user_agent);
				if($stats_library->is_bot($log->user_agent)) $logs[$key]->user_agent_parse['platform'] = 'Bot';
			} else {
				$logs[$key]->user_agent_parse = false;
			}
			if(!empty($log->user_referer)) $logs[$key]->user_referer_parse = $stats_library->parse_referer($log->user_referer);
			else $logs[$key]->user_referer_parse = false;
		}
		Context::set('logs', $logs);
		$search_link_value = false;
		if($search_type == 'srl') {
			$cuts = explode(',', $search_value);
			if(count($cuts) == 3) {
				if($cuts[2] == 0) {
					unset($cuts[2]);
				} else {
					$args = new StdClass;
					$args->document_srl = $cuts[2];
					$output = executeQuery('document.getDocument', $args);
					if($output->data) {
						$search_link_value = new StdClass;
						$search_link_value->type = 'document';
						$search_link_value->data = $output->data;
					}
				}
			}
			if(count($cuts) == 2) {
				$args = new StdClass;
				$args->module_srls = $cuts[1];
				$output = executeQuery('module.getModuleInfoByModuleSrl', $args);
				if($output->data) {
					$search_link_value = new StdClass;
					$search_link_value->type = 'module';
					$search_link_value->data = $output->data;
				}
			}
		}
		Context::set('search_link_value', $search_link_value);
		$total_count = $statsModel->getLogCount($this->site_srl, $search_type, $search_value);
		if($total_count) $total_page = (int) (($total_count - 1) / $page_count) + 1;
		else $total_page = 1;
		$page_navigation = new PageHandler($total_count, $total_page, $page, 10);
		Context::set('page_navigation', $page_navigation);
		$this->setTemplateFile('log');
	}

	private function setting() {
		$config = $this->getConfig();
		Context::set('stats_config', $config);
		$this->setTemplateFile('setting');
	}

	private	function __colourBrightness($hex, $percent) {
		 $hash = '';
		 if (stristr($hex,'#')) {
			$hex = str_replace('#','',$hex);
			$hash = '#';
		 }
		 $rgb = array(hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)));
		 for ($i=0; $i<3; $i++) {
			if ($percent > 0) {
				$rgb[$i] = round($rgb[$i] * $percent) + round(255 * (1-$percent));
			} else {
				$positivePercent = $percent - ($percent*2);
				$rgb[$i] = round($rgb[$i] * $positivePercent) + round(0 * (1-$positivePercent));
			}
			if ($rgb[$i] > 255) $rgb[$i] = 255;
		 }
		 $hex = '';
		 for($i=0; $i < 3; $i++) {
			$hexDigit = dechex($rgb[$i]);
			if(strlen($hexDigit) == 1) $hexDigit = "0" . $hexDigit;
			$hex .= $hexDigit;
		 }
		 return $hash.$hex;
	}
}
