<?php
class documentAdminModel extends document
	function init() {}

	function getDocumentTrashList($obj) {
		if(!in_array($obj->sort_index, array('list_order','delete_date','title'))) $obj->sort_index = 'list_order';
		if(!in_array($obj->order_type, array('desc','asc'))) $obj->order_type = 'asc';
		if($obj->mid) {
			$oModuleModel = getModel('module');
			$obj->module_srl = $oModuleModel->getModuleSrlByMid($obj->mid);
			unset($obj->mid);
		}
		if(is_array($obj->module_srl)) $args->module_srl = implode(',', $obj->module_srl);
		else $args->module_srl = $obj->module_srl;
		$args->sort_index = $obj->sort_index;
		$args->order_type = $obj->order_type;
		$args->page = $obj->page?$obj->page:1;
		$args->list_count = $obj->list_count?$obj->list_count:20;
		$args->page_count = $obj->page_count?$obj->page_count:10;
		$args->member_srl = $obj->member_srl;
		$query_id = 'document.getTrashList';
		$output = executeQueryArray($query_id, $args);
		if(!$output->toBool() || !count($output->data)) return $output;
		$idx = 0;
		$data = $output->data;
		unset($output->data);
		$keys = array_keys($data);
		$virtual_number = $keys[0];
		foreach($data as $key => $attribute) {
			$oDocument = null;
			$oDocument = new documentItem();
			$oDocument->setAttribute($attribute, false);
			if($is_admin) $oDocument->setGrant();
			$output->data[$virtual_number] = $oDocument;
			$virtual_number--;
		}
		return $output;
	}

	function getDocumentTrash($trash_srl) {
		$args->trash_srl = $trash_srl;
		$output = executeQuery('document.getTrash', $args);
		$node = $output->data;
		if(!$node) return;
		return $node;
	}

	function getDocumentCountByDate($date = '', $moduleSrlList = array(), $statusList = array()) {
		$args = new stdClass();
		if($date) $args->regDate = date('Ymd', strtotime($date));
		if(count($moduleSrlList)>0) $args->moduleSrlList = $moduleSrlList;
		if(count($statusList)>0) $args->statusList = $statusList;
		$output = executeQuery('document.getDocumentCountByDate', $args);
		if(!$output->toBool()) return 0;
		return $output->data->count;
	}
}
