<?php
class krzipController extends krzip
{
	function updateConfig($args) {
		if(!$args || !is_object($args)) $args = new stdClass();
		$oModuleController = getController('module');
		$output = $oModuleController->updateModuleConfig('krzip', $args);
		if($output->toBool()) unset($this->module_config);
		return $output;
	}
}
