<?php
class loginlogView extends loginlog
{
	public function init() {
		$oLoginlogModel = getModel('loginlog');
		$loginlog_config = $oLoginlogModel->getModuleConfig();
		Context::set('loginlog_config', $loginlog_config);
		$template_path = sprintf("%sskins/%s/",$this->module_path, $loginlog_config->design->skin);
		if(!is_dir($template_path)||!$loginlog_config->design->skin) {
			$loginlog_config->design->skin = 'default';
			$template_path = sprintf("%sskins/%s/",$this->module_path, $loginlog_config->design->skin);
		}
		$this->setTemplatePath($template_path);
	}

	public function dispLoginlogHistories() {
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('msg_not_permitted');
		$args = new stdClass;
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 10;
		$args->sort_index = 'log_srl';
		$args->order_type = 'desc';
		$args->member_srl = $logged_info->member_srl;
		$search_keyword = Context::get('search_keyword');
		$search_target = trim(Context::get('search_target'));
		$output = executeQueryArray('loginlog.getLoginlogList', $args);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('histories', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$this->setTemplateFile('histories');
	}
}