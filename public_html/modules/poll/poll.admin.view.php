<?php
class pollAdminView extends poll
{
	function init() {
	}

	function dispPollAdminList() {
		$search_target = trim(Context::get('search_target'));
		$search_keyword = trim(Context::get('search_keyword'));
		$args = new stdClass();
		if($search_target && $search_keyword) {
			switch($search_target) {
				case 'title' :
					if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
					$args->s_title= $search_keyword;
					break;
				case 'regdate' :
					$args->s_regdate = $search_keyword;
					break;
				case 'ipaddress' :
					$args->s_ipaddress= $search_keyword;
					break;
			}
		}
		$args->page = Context::get('page');
		$args->list_count = 50;
		$args->page_count = 10;
		$args->sort_index = 'P.list_order';
		$oPollAdminModel = getAdminModel('poll');
		$output = $oPollAdminModel->getPollListWithMember($args);
		if(is_array($output->data)) {
			$uploadTargetSrlList = array();
			foreach($output->data as $value) $uploadTargetSrlList[] = $value->upload_target_srl;
			$oDocumentModel = getModel('document');
			$targetDocumentOutput = $oDocumentModel->getDocuments($uploadTargetSrlList);
			if(!is_array($targetDocumentOutput)) $targetDocumentOutput = array();
			$oCommentModel = getModel('comment');
			$columnList = array('comment_srl', 'document_srl');
			$targetCommentOutput = $oCommentModel->getComments($uploadTargetSrlList, $columnList);
			if(!is_array($targetCommentOutput)) $targetCommentOutput = array();
			foreach($output->data as $value) {
				if(array_key_exists($value->upload_target_srl, $targetDocumentOutput)) $value->document_srl = $value->upload_target_srl;
				if(array_key_exists($value->upload_target_srl, $targetCommentOutput)) {
					$value->comment_srl = $value->upload_target_srl;
					$value->document_srl = $targetCommentOutput[$value->comment_srl]->document_srl;
				}
			}
		}
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('poll_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('module_list', $module_list);
		$security = new Security();
		$security->encodeHTML('poll_list..title', 'poll_list..nick_name');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('poll_list');
	}

	function dispPollAdminConfig() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('poll');
		Context::set('config', $config);
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list', $skin_list);
		if(!$skin_list[$config->skin]) $config->skin = "default";
		Context::set('colorset_list', $skin_list[$config->skin]->colorset);
		$security = new Security();
		$security->encodeHTML('config..');
		$security->encodeHTML('skin_list..title');
		$security->encodeHTML('colorset_list..name','colorset_list..title');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('config');
	}

	function dispPollAdminResult() {
		$this->setLayoutFile("popup_layout");
		$args = new stdClass();
		$args->poll_srl = Context::get('poll_srl');
		$args->poll_index_srl = Context::get('poll_index_srl');
		$output = executeQuery('poll.getPoll', $args);
		if(!$output->data) return $this->stop('msg_poll_not_exists');
		$poll = new stdClass();
		$poll->stop_date = $output->data->stop_date;
		$poll->poll_count = $output->data->poll_count;
		$output = executeQuery('poll.getPollTitle', $args);
		if(!$output->data) return $this->stop('msg_poll_not_exists');
		$tmp = &$poll->poll[$args->poll_index_srl];
		$tmp->title = $output->data->title;
		$tmp->checkcount = $output->data->checkcount;
		$tmp->poll_count = $output->data->poll_count;
		$output = executeQuery('poll.getPollItem', $args);
		foreach($output->data as $val) $tmp->item[] = $val;
		$poll->poll_srl = $poll_srl;
		Context::set('poll',$poll);
		$oModuleModel = getModel('module');
		$poll_config = $oModuleModel->getModuleConfig('poll');
		Context::set('poll_config', $poll_config);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('result');
	}
}
