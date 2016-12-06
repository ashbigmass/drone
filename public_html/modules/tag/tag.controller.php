<?php
class tagController extends tag
{
	function init() {
	}

	function triggerArrangeTag(&$obj) {
		if(!$obj->tags) return new Object();
		$tag_list = explode(',', $obj->tags);
		$tag_count = count($tag_list);
		$tag_list = array_unique($tag_list);
		if(!count($tag_list)) return new Object();
		foreach($tag_list as $tag) {
			if(!trim($tag)) continue;
			$arranged_tag_list[] = trim($tag);
		}
		if(!count($arranged_tag_list)) $obj->tags = null;
		else $obj->tags = implode(',',$arranged_tag_list);
		return new Object();
	}

	function triggerInsertTag(&$obj) {
		$module_srl = $obj->module_srl;
		$document_srl = $obj->document_srl;
		$tags = $obj->tags;
		if(!$document_srl) return new Object();
		$output = $this->triggerDeleteTag($obj);
		if(!$output->toBool()) return $output;
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$args->document_srl = $document_srl;
		$tag_list = explode(',',$tags);
		$tag_count = count($tag_list);
		for($i=0;$i<$tag_count;$i++) {
			unset($args->tag);
			$args->tag = trim($tag_list[$i]);
			if(!$args->tag) continue;
			$output = executeQuery('tag.insertTag', $args);
			if(!$output->toBool()) return $output;
		}
		return new Object();
	}

	function triggerDeleteTag(&$obj) {
		$document_srl = $obj->document_srl;
		if(!$document_srl) return new Object();
		$args = new stdClass();
		$args->document_srl = $document_srl;
		return executeQuery('tag.deleteTag', $args);
	}

	function triggerDeleteModuleTags(&$obj) {
		$module_srl = $obj->module_srl;
		if(!$module_srl) return new Object();
		$oTagController = getAdminController('tag');
		return $oTagController->deleteModuleTags($module_srl);
	}
}
