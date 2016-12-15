<?php
class page extends ModuleObject
{
	function moduleInstall() {
		FileHandler::makeDir('./files/cache/page');
		return new Object();
	}

	function checkUpdate() {
		$output = executeQuery('page.pageTypeOpageCheck');
		if($output->toBool() && $output->data) return true;
		$output = executeQuery('page.pageTypeNullCheck');
		if($output->toBool() && $output->data) return true;
		return false;
	}

	function moduleUpdate() {
		$args = new stdClass;
		$output = executeQueryArray('page.pageTypeOpageCheck');
		if($output->toBool() && count($output->data) > 0) {
			foreach($output->data as $val) {
				$args->module_srl = $val->module_srl;
				$args->name = 'page_type';
				$args->value= 'OUTSIDE';
				$in_out = executeQuery('page.insertPageType', $args);
			}
			$output = executeQuery('page.updateAllOpage');
			if(!$output->toBool()) return $output;
		}
		$output = executeQueryArray('page.pageTypeNullCheck');
		$skin_update_srls = array();
		if($output->toBool() && $output->data) {
			foreach($output->data as $val) {
				$args->module_srl = $val->module_srl;
				$args->name = 'page_type';
				$args->value= 'WIDGET';
				$in_out = executeQuery('page.insertPageType', $args);
				$skin_update_srls[] = $val->module_srl;
			}
		}
		if(count($skin_update_srls)>0) {
			$skin_args = new stdClass;
			$skin_args->module_srls = implode(',',$skin_update_srls);
			$skin_args->is_skin_fix = "Y";
			$ouput = executeQuery('page.updateSkinFix', $skin_args);
		}
		return new Object(0,'success_updated');
	}

	function recompileCache() {
	}
}
