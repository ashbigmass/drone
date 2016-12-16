<?php
class sitemapAdminController extends sitemap
{
	function init() {
	}

	function procSitemapAdminInsertConfig() {
		$oModuleModel = getModel('module');
		$sitemap_config = $oModuleModel->getModuleConfig('sitemap');
		$config_vars = Context::getRequestVars();
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('sitemap', $config_vars);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSitemapAdminIndex');
		$this->setRedirectUrl($returnUrl);
	}

	function procSitemapAdminPingSitemap() {
		$pingUrl = array();
		$pingUrl[] = 'http://www.google.com/webmasters/sitemaps/ping?sitemap=';
		$pingUrl[] = 'http://www.bing.com/ping?sitemap=';
		foreach($pingUrl as $url) FileHandler::getRemoteResource($url.getFullUrl('').'sitemap.xml');
		$this->setMessage('success_registed');
	}

	function procSitemapAdminDownloadSitemap() {
		$oSitemapModel = getModel('sitemap');
		$config = $oSitemapModel->getConfig();
		$args = new stdClass();
		$args->status = 'PUBLIC';
		$args->except_module_srl = $config->except_module_srl;
		$args->list_count = $config->sitemap_document_count;
		$result = executeQuery('sitemap.getDocumentSrlByStatus', $args);
		Context::close();
		header('Content-disposition: attachment; filename=sitemap.txt');
		header('Content-type: text/plain');
		echo getFullUrl('').PHP_EOL;
		foreach($result->data as $oDocument) echo getFullUrl('', 'document_srl', $oDocument->document_srl).PHP_EOL;
		exit();
	}
}
