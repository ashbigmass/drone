<?php
class sitemapController extends sitemap
{
	function triggerBeforeDisplay(&$output_content) {
		$oSitemapModel = getModel('sitemap');
		$config = $oSitemapModel->getConfig();
		$document_srl = $_REQUEST['document_srl'];
		if($config->use_search_index == 'N') return new Object();
		if($document_srl) {
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if(!$oDocument->isExists() && $config->except_deleted == 'Y') {
				$oSitemapModel->addRobotsMeta('none');
				return new Object();
			}
			if($oDocument->get('status') == 'SECRET' && $config->except_secret == 'Y') {
				$oSitemapModel->addRobotsMeta('none');
				return new Object();
			}
		}
		if(Context::get('module') == 'admin' && $config->except_admin == 'Y') {
			$oSitemapModel->addRobotsMeta('none');
			return new Object();
		}
		$oSitemapModel->addRobotsMeta($config->metatag);
	}
}
