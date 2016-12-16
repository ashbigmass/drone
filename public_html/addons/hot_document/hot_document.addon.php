<?php
if(!defined('__XE__')) exit();

if($called_position == 'after_module_proc' && Context::get('document_list') && class_exists('documentItem')){
	$module_info = Context::get('module_info');
	$obj = new stdClass();
	if($addon_info->extraction_mode != 'total' ) {
		$obj->module_srl = $module_info->module_srl;
	} else {
		$oModuleModel = &getModel('module');
		foreach($addon_info->mid_list as $key => $val)
			if($oModuleModel->getModuleInfoByMid($val)->consultation != "Y") $module_srl[$key] = $oModuleModel->getModuleInfoByMid($val)->module_srl;
		$module_srl = array_filter($module_srl);
		$module_srl = implode(',',$module_srl);
		if($addon_info->xe_run_method == 'no_run_selected') $obj->notin_module_srl = $module_srl;
		else $obj->module_srl = $module_srl;
	}
	$obj->list_count = $addon_info->list_count;
	$obj->is_notice = 'N';
	if($addon_info->readed_count && $addon_info->readed_count_op == "AND") $obj->readed_count = $addon_info->readed_count;
	if($addon_info->readed_count && $addon_info->readed_count_op == "OR") $obj->readed_count_or = $addon_info->readed_count;
	if($addon_info->voted_count && $addon_info->voted_count_op == "AND") $obj->voted_count = $addon_info->voted_count;
	if($addon_info->voted_count && $addon_info->voted_count_op == "OR") $obj->voted_count_or = $addon_info->voted_count;
	if($addon_info->comment_count && $addon_info->comment_count_op == "AND") $obj->comment_count = $addon_info->comment_count;
	if($addon_info->comment_count && $addon_info->comment_count_op == "OR") $obj->comment_count_or = $addon_info->comment_count;
	if(!$addon_info->cache_time) $cache_time = 0;
	else $cache_time = 60 * $addon_info->cache_time;
	if($addon_info->chk_time){
		$chk_time = time()-(60*60*$addon_info->chk_time);
		$chk_time = date('YmdHis',$chk_time);
		$obj->regdate = $chk_time;
	}
	$obj->sort_index = $addon_info->sort_index;
	if($addon_info->sort_index != 'list_order' && $addon_info->sort_index) $obj->order_type = 'desc';
	$oCacheHandler = CacheHandler::getInstance();
	if($cache_time && $oCacheHandler->isSupport() && $oCacheHandler->isValid("hot_document_$module_info->module_srl",$cache_time)) {
		$cache = $oCacheHandler->get("hot_document_$module_info->module_srl",$cache_time);
		$error = $cache->error;
		$message = $cache->message;
		$httpStatusCode = $cache->httpStatusCode;
		$hot_document = $cache->data;
	} else {
		$output = executeQueryArray('addons.hot_document.getNewestDocuments', $obj);
		if($output->toBool() && $output->data) {
			foreach($output->data as $key => $val) $output->data[$key]->is_notice = 'Y';
			if($oCacheHandler->isSupport() && $cache_time != 0) $oCacheHandler->put("hot_document_$module_info->module_srl",$output,$cache_time);
		}
		$error = $output->error;
		$message = $output->message;
		$httpStatusCode = $output->httpStatusCode;
		$hot_document = $output->data;
	}
	$notice_list = Context::get('notice_list');
	foreach($hot_document as $key => $val){
		$notice_list[$val->document_srl] = new documentItem();
		$notice_list[$val->document_srl]->document_srl = $val->document_srl;
		$notice_list[$val->document_srl]->lang_code = $val->lang_code;
		$notice_list[$val->document_srl]->columnList = Array();
		$notice_list[$val->document_srl]->allowscriptaccessList = Array();
		$notice_list[$val->document_srl]->allowscriptaccessKey = 0;
		$notice_list[$val->document_srl]->uploadedFiles = Array();
		$notice_list[$val->document_srl]->error = $error;
		$notice_list[$val->document_srl]->message = $message;
		$val->hot_document = 'Y';
		$notice_list[$val->document_srl]->variables = (array) $val;
		$notice_list[$val->document_srl]->httpStatusCode = $httpStatusCode;
	}
	Context::set('notice_list',$notice_list);
}
