<?php
class rssModel extends rss
{
	function getModuleFeedUrl($vid, $mid, $format = 'rss', $absolute_url = false) {
		if($absolute_url) return getFullUrl('','vid',$vid, 'mid',$mid, 'act',$format);
		else return getUrl('','vid',$vid, 'mid',$mid, 'act',$format);
	}

	function getRssModuleConfig($module_srl) {
		$oModuleModel = getModel('module');
		$module_rss_config = $oModuleModel->getModulePartConfig('rss', $module_srl);
		if(!$module_rss_config) {
			$module_rss_config = new stdClass();
			$module_rss_config->open_rss = 'N';
		}
		$module_rss_config->module_srl = $module_srl;
		return $module_rss_config;
	}
}
