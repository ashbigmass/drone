<?php
class sitemapAdminView extends sitemap
{
	function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile(strtolower(str_replace('dispSitemapAdmin', '', $this->act)));
	}

	function dispSitemapAdminIndex() {
		$oSitemapModel = getModel('sitemap');
		Context::set('sitemap_config', $oSitemapModel->getConfig());
		Context::set('metatag_list', array('all', 'noindex', 'nofollow', 'none', 'noarchive', 'nosnippet'));
		$ht_buff = '';
		$htaccess_flag = false;
		$fp = fopen(_XE_PATH_.'.htaccess', 'r');
		while(!feof($fp)) {
			$ht_buff = fgets($fp);
			if(strpos($ht_buff, 'RewriteRule ^sitemap([0-9]*)\.xml$ ./index.php?module=sitemap&act=sitemap&page=$1 [L]') !== FALSE) $htaccess_flag = true;
		}
		fclose($fp);
		Context::set('htaccess_flag', $htaccess_flag);
		$page = 0;
		$args = new stdClass();
		$args->status = 'PUBLIC';
		$args->except_module_srl = $config->except_module_srl;
		$args->list_count = $config->sitemap_document_count;
		$args->page = $page;
		$result = executeQuery('sitemap.getDocumentSrlByStatus', $args);
		Context::set('result', $result);
	}
}
