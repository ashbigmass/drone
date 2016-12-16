<?php
class pollModel extends poll
{
	function init() {
	}

	function isPolled($poll_srl) {
		$args = new stdClass;
		$args->poll_srl = $poll_srl;
		if(Context::get('is_logged')) {
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
		} else {
			$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		}
		$output = executeQuery('poll.getPollLog', $args);
		if($output->data->count) return true;
		return false;
	}

	function getPollHtml($poll_srl, $style = '', $skin = 'default') {
		$args = new stdClass;
		$args->poll_srl = $poll_srl;
		$columnList = array('poll_count', 'stop_date');
		$output = executeQuery('poll.getPoll', $args, $columnList);
		if(!$output->data) return '';
		$poll = new stdClass;
		$poll->style = $style;
		$poll->poll_count = (int)$output->data->poll_count;
		$poll->stop_date = $output->data->stop_date;
		$columnList = array('poll_index_srl', 'title', 'checkcount', 'poll_count');
		$output = executeQuery('poll.getPollTitle', $args, $columnList);
		if(!$output->data) return;
		if(!is_array($output->data)) $output->data = array($output->data);
		$poll->poll = array();
		foreach($output->data as $key => $val) {
			$poll->poll[$val->poll_index_srl] = new stdClass;
			$poll->poll[$val->poll_index_srl]->title = $val->title;
			$poll->poll[$val->poll_index_srl]->checkcount = $val->checkcount;
			$poll->poll[$val->poll_index_srl]->poll_count = $val->poll_count;
		}
		$output = executeQuery('poll.getPollItem', $args);
		foreach($output->data as $key => $val) $poll->poll[$val->poll_index_srl]->item[] = $val;
		$poll->poll_srl = $poll_srl;
		if($poll->stop_date >= date("Ymd")) {
			if($this->isPolled($poll_srl)) $tpl_file = "result";
			else $tpl_file = "form";
		} else {
			$tpl_file = "result";
		}
		Context::set('poll',$poll);
		Context::set('skin',$skin);
		$tpl_path = sprintf("%sskins/%s/", $this->module_path, $skin);
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	function getPollResultHtml($poll_srl, $skin = 'default') {
		$args = new stdClass;
		$args->poll_srl = $poll_srl;
		$output = executeQuery('poll.getPoll', $args);
		if(!$output->data) return '';
		$poll = new stdClass;
		$poll->style = $style;
		$poll->poll_count = (int)$output->data->poll_count;
		$poll->stop_date = $output->data->stop_date;
		$columnList = array('poll_index_srl', 'title', 'checkcount', 'poll_count');
		$output = executeQuery('poll.getPollTitle', $args, $columnList);
		if(!$output->data) return;
		if(!is_array($output->data)) $output->data = array($output->data);
		$poll->poll = array();
		foreach($output->data as $key => $val) {
			$poll->poll[$val->poll_index_srl] = new stdClass;
			$poll->poll[$val->poll_index_srl]->title = $val->title;
			$poll->poll[$val->poll_index_srl]->checkcount = $val->checkcount;
			$poll->poll[$val->poll_index_srl]->poll_count = $val->poll_count;
		}
		$output = executeQuery('poll.getPollItem', $args);
		foreach($output->data as $key => $val) $poll->poll[$val->poll_index_srl]->item[] = $val;
		$poll->poll_srl = $poll_srl;
		$tpl_file = "result";
		Context::set('poll',$poll);
		$tpl_path = sprintf("%sskins/%s/", $this->module_path, $skin);
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	function getPollGetColorsetList() {
		$skin = Context::get('skin');
		$oModuleModel = getModel('module');
		$skin_info = $oModuleModel->loadSkinInfo($this->module_path, $skin);
		for($i=0;$i<count($skin_info->colorset);$i++) {
			$colorset = sprintf('%s|@|%s', $skin_info->colorset[$i]->name, $skin_info->colorset[$i]->title);
			$colorset_list[] = $colorset;
		}
		if(count($colorset_list)) $colorsets = implode("\n", $colorset_list);
		$this->add('colorset_list', $colorsets);
	}
}
