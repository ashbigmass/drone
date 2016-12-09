<?php
class rssView extends rss
{
	function init() {
	}

	function rss($document_list = null, $rss_title = null, $add_description = null) {
		$oDocumentModel = getModel('document');
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$document_list) {
			$site_module_info = Context::get('site_module_info');
			$site_srl = $site_module_info->site_srl;
			$mid = Context::getRequestVars()->mid;
			$start_date = (int)Context::get('start_date');
			$end_date = (int)Context::get('end_date');
			$module_srls = array();
			$rss_config = array();
			$total_config = '';
			$total_config = $oModuleModel->getModuleConfig('rss');
			if($mid) {
				$module_srl = $this->module_info->module_srl;
				$config = $oModuleModel->getModulePartConfig('rss', $module_srl);
				if($config->open_rss && $config->open_rss != 'N') {
					$module_srls[] = $module_srl;
					$open_rss_config[$module_srl] = $config->open_rss;
				}
			} else {
				if($total_config->use_total_feed != 'N') {
					$rss_config = $oModuleModel->getModulePartConfigs('rss', $site_srl);
					if($rss_config) {
						foreach($rss_config as $module_srl => $config) {
							if($config && $config->open_rss != 'N' && $config->open_total_feed != 'T_N') {
								$module_srls[] = $module_srl;
								$open_rss_config[$module_srl] = $config->open_rss;
							}
						}
					}
				}
			}
			if(!count($module_srls) && !$add_description) return $this->dispError();
			$info = new stdClass;
			$args = new stdClass;
			if($module_srls) {
				$args->module_srl = implode(',',$module_srls);
				$args->search_target = 'is_secret';
				$args->search_keyword = 'N';
				$args->page = (int)Context::get('page');
				$args->list_count = 15;
				if($total_config->feed_document_count) $args->list_count = $total_config->feed_document_count;
				if(!$args->page || $args->page < 1) $args->page = 1;
				if($start_date || $start_date != 0) $args->start_date = $start_date;
				if($end_date || $end_date != 0) $args->end_date = $end_date;
				if($start_date == 0) unset($start_date);
				if($end_date == 0) unset($end_date);
				$args->sort_index = 'list_order';
				$args->order_type = 'asc';
				$output = $oDocumentModel->getDocumentList($args);
				$document_list = $output->data;
				if($mid) {
					$info->title = Context::getBrowserTitle();
					$oModuleController->replaceDefinedLangCode($info->title);
					$info->title = str_replace('\'', '&apos;',$info->title);
					if($config->feed_description) {
						$info->description = str_replace('\'', '&apos;', htmlspecialchars($config->feed_description, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
					} else {
						$info->description = str_replace('\'', '&apos;', htmlspecialchars($this->module_info->description, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
					}
					$info->link = getUrl('','mid',$mid);
					$info->feed_copyright = str_replace('\'', '&apos;', htmlspecialchars($feed_config->feed_copyright, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
					if(!$info->feed_copyright) {
						$info->feed_copyright = str_replace('\'', '&apos;', htmlspecialchars($total_config->feed_copyright, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
					}
				}
			}
		}
		if(!$info->title) {
			if($rss_title) $info->title = $rss_title;
			else if($total_config->feed_title) $info->title = $total_config->feed_title;
			else {
				$site_module_info = Context::get('site_module_info');
				$info->title = $site_module_info->browser_title;
			}
			$oModuleController->replaceDefinedLangCode($info->title);
			$info->title = str_replace('\'', '&apos;', htmlspecialchars($info->title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
			$info->description = str_replace('\'', '&apos;', htmlspecialchars($total_config->feed_description, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
			$info->link = Context::getRequestUri();
			$info->feed_copyright = str_replace('\'', '&apos;', htmlspecialchars($total_config->feed_copyright, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
		}
		if($add_description) $info->description .= "\r\n".$add_description;
		if($total_config->image) $info->image = Context::getRequestUri().str_replace('\'', '&apos;', htmlspecialchars($total_config->image, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
		switch(Context::get('format')) {
			case 'atom':
				$info->date = date('Y-m-d\TH:i:sP');
				if($mid) { $info->id = getUrl('','mid',$mid,'act','atom','page',Context::get('page'),'start_date',Context::get('start_date'),'end_date',Context::get('end_date')); }
				else { $info->id = getUrl('','module','rss','act','atom','page',Context::get('page'),'start_date',Context::get('start_date'),'end_date',Context::get('end_date')); }
			break;
			case 'rss1.0':
				$info->date = date('Y-m-d\TH:i:sP');
			break;
			default:
				$info->date = date("D, d M Y H:i:s").' '.$GLOBALS['_time_zone'];
			break;
		}
		if($_SERVER['HTTPS']=='on') $proctcl = 'https://';
		else $proctcl = 'http://';
		$temp_link = explode('/', $info->link);
		if($temp_link[0]=='' && $info->link) $info->link = $proctcl.$_SERVER['HTTP_HOST'].$info->link;
		$temp_id = explode('/', $info->id);
		if($temp_id[0]=='' && $info->id) $info->id = $proctcl.$_SERVER['HTTP_HOST'].$info->id;
		$info->language = str_replace('jp','ja',Context::getLangType());
		Context::set('info', $info);
		Context::set('feed_config', $config);
		Context::set('open_rss_config', $open_rss_config);
		Context::set('document_list', $document_list);
		Context::setResponseMethod("XMLRPC");
		$path = $this->module_path.'tpl/';
		switch (Context::get('format')) {
			case 'xe': $file = 'xe_rss'; break;
			case 'atom': $file = 'atom10'; break;
			case 'rss1.0': $file = 'rss10'; break;
			default:  $file = 'rss20'; break;
		}
		$oTemplate = new TemplateHandler();
		$content = $oTemplate->compile($path, $file);
		Context::set('content', $content);
		$this->setTemplatePath($path);
		$this->setTemplateFile('display');
	}

	function atom() {
		Context::set('format', 'atom');
		$this->rss();
	}

	function dispError() {
		$this->rss(null, null, Context::getLang('msg_rss_is_disabled') );
	}

	function triggerDispRssAdditionSetup(&$obj) {
		$current_module_srl = Context::get('module_srl');
		$current_module_srls = Context::get('module_srls');
		if(!$current_module_srl && !$current_module_srls) {
			$current_module_info = Context::get('current_module_info');
			$current_module_srl = $current_module_info->module_srl;
			if(!$current_module_srl) return new Object();
		}
		$oRssModel = getModel('rss');
		$rss_config = $oRssModel->getRssModuleConfig($current_module_srl);
		Context::set('rss_config', $rss_config);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'rss_module_config');
		$obj .= $tpl;
		return new Object();
	}
}
