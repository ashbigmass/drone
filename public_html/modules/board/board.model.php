<?php
class boardModel extends module

	function init() {
	}

	function getListConfig($module_srl) {
		$oModuleModel = getModel('module');
		$oDocumentModel = getModel('document');
		$list_config = $oModuleModel->getModulePartConfig('board', $module_srl);
		if(!$list_config || count($list_config) <= 0) $list_config = array( 'no', 'title', 'nick_name','regdate','readed_count');
		$inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);
		foreach($list_config as $key) {
			if(preg_match('/^([0-9]+)$/',$key)) {
				if($inserted_extra_vars[$key]) $output['extra_vars'.$key] = $inserted_extra_vars[$key];
				else continue;
			} else {
				$output[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
			}
		}
		return $output;
	}

	function getDefaultListConfig($module_srl) {
		$virtual_vars = array( 'no', 'title', 'regdate', 'last_update', 'last_post', 'nick_name',
				'user_id', 'user_name', 'readed_count', 'voted_count', 'blamed_count', 'thumbnail', 'summary', 'comment_status');
		foreach($virtual_vars as $key) $extra_vars[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
		$oDocumentModel = getModel('document');
		$inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);
		if(count($inserted_extra_vars)) {
			foreach($inserted_extra_vars as $obj) $extra_vars['extra_vars'.$obj->idx] = $obj;
		}
		return $extra_vars;

	}

	function triggerModuleListInSitemap(&$obj) {
		array_push($obj, 'board');
	}
}
