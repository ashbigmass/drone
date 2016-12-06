<?php
class spamfilterAdminView extends spamfilter
{
	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispSpamfilterAdminDeniedIPList() {
		$oSpamFilterModel = getModel('spamfilter');
		$ip_list = $oSpamFilterModel->getDeniedIPList();
		Context::set('ip_list', $ip_list);
		$security = new Security();
		$security->encodeHTML('ip_list..');
		$this->setTemplateFile('denied_ip_list');
	}

	function dispSpamfilterAdminDeniedWordList() {
		$oSpamFilterModel = getModel('spamfilter');
		$word_list = $oSpamFilterModel->getDeniedWordList();
		Context::set('word_list', $word_list);
		$security = new Security();
		$security->encodeHTML('word_list..word');
		$this->setTemplateFile('denied_word_list');
	}

	function dispSpamfilterAdminConfigBlock() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('spamfilter');
		Context::set('config',$config);
		$this->setTemplateFile('config_block');
	}
}
