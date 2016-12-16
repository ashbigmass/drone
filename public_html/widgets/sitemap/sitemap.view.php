<?php
class sitemapView extends sitemap
{
	function sitemap() {
		$oSitemapModel = getModel('sitemap');
		$config = $oSitemapModel->getConfig();
		Context::set('config', $config);
		if($config->use_sitemap == 'N') exit('Not in use');
		$template = 'sitemapindex';
		$page = Context::get('page');
		if($page) $template = 'sitemap';
		$dl = Context::get('dl');
		$logged_info = Context::get('logged_info');
		if($dl == 'true' && $logged_info->is_admin == 'Y') {
			header('Content-disposition: attachment; filename=sitemap'.$page.'.xml');
			header('Content-type: text/xml');
		}
		$args = new stdClass();
		$args->status = 'PUBLIC';
		$args->except_module_srl = $config->except_module_srl;
		$args->list_count = $config->sitemap_document_count;
		$args->page = $page;
		$result = executeQuery('sitemap.getDocumentSrlByStatus', $args);
		Context::set('result', $result);
		$document_srls = array();
		foreach($result->data as $key => $val) $document_srls[] = $val->document_srl;
		$oDocumentModel = getModel('document');
		$oDocuments = $oDocumentModel->getDocuments($document_srls);
		Context::set('oDocuments', $oDocuments);
		Context::setResponseMethod('XMLRPC');
		$path = $this->module_path.'tpl/';
		$oTemplate = new TemplateHandler();
		$content = $oTemplate->compile($path, $template);
		Context::set('content', $content);
		$this->setTemplatePath($path);
		$this->setTemplateFile('display');
	}
}
