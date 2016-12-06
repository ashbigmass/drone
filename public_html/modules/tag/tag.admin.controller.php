<?php
class tagAdminController extends tag
{
	function deleteModuleTags($module_srl) {
		$args = new stdClass();
		$args->module_srl = $module_srl;
		return executeQuery('tag.deleteModuleTags', $args);
	}
}
