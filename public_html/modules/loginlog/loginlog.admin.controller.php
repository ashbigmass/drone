<?php
class loginlogAdminController extends loginlog
{
	public function init() {
	}

	public function procLoginlogAdminInsertConfig() {
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		$config->delete_logs = Context::get('delete_logs');
		$config->admin_user_log = Context::get('admin_user_log');
		$config->target_group = Context::get('target_group');
		$config->exportConfig = new stdClass;
		$config->exportConfig->exportType = Context::get('exportType');
		$config->exportConfig->listCount = Context::get('listCountForExport');
		$config->exportConfig->pageCount = Context::get('pageCountForExport');
		$config->exportConfig->includeGroup = Context::get('includeGroup');
		$config->exportConfig->excludeGroup = Context::get('excludeGroup');
		$config->exportConfig->includeAdmin = Context::get('includeAdmin');
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('loginlog', $config);
		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl) $returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispLoginlogAdminSetting');
		$this->setMessage('success_saved');
		$this->setRedirectUrl($returnUrl);
	}

	public function procLoginlogAdminSaveListSetting() {
		if(Context::getRequestMethod() == 'GET') return new Object(-1, 'msg_invalid_request');
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		unset($config->ruleset);
		$config->listSetting = Context::get('listSetting');
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('loginlog', $config);
		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispLoginlogAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	public function procLoginlogAdminInsertDesignConfig() {
		if(Context::getRequestMethod() == 'GET') return new Object(-1, 'msg_invalid_request');
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		unset($config->ruleset);
		$config->design = Context::gets('skin', 'mskin');
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('loginlog', $config);
		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispLoginlogAdminDesign');
		$this->setRedirectUrl($returnUrl);
	}

	public function procLoginlogAdminCleanLog() {
		if(Context::get('expire_date')) $args->expire_date = Context::get('expire_date');
		$msg_code = 'success_clean_log';
		$output = executeQuery('loginlog.initLoginlogs', $args);
		if(!$output->toBool()) $msg_code = 'msg_failed_clean_logs';
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispLoginlogAdminArrange');
		$this->setRedirectUrl($returnUrl);
		$this->setMessage($msg_code);
	}

	public function procLoginlogAdminDeleteChecked() {
		$log_srls= Context::get('cart');
		$log_count = count($log_srls);
		for($i=0; $i<$log_count; $i++) {
			$log_srl = $log_srls[$i];
			$this->deleteLog($log_srl);
		}
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispLoginlogAdminList');
		$this->setRedirectUrl($returnUrl);
		$this->setMessage('success_deleted');
	}

	public function procLoginlogAdminExport() {
		if(version_compare(phpversion(), '5.2.0', '<')) {
			$phpWarning = sprintf(Context::getLang('php_version_warning_for_feature'), phpversion());
			return new Object(-1, $phpWarning);
		}
		@set_time_limit(0);
		Context::setRequestMethod('XMLRPC');
		require dirname(__FILE__) . '/libs/Export/Interface/Interface.php';
		require dirname(__FILE__) . '/libs/Export/Core.php';
		$exportFileType = Context::get('type');
		switch($exportFileType) {
			case 'html':
				$classFile = 'HTML.php';
				$filename = 'loginlog_' . date('Y-m-d');
			break;
			case 'excel':
				$classFile = 'Excel.php';
				$filename = 'loginlog_' . date('Y-m-d');
				$this->setTemplatePath($this->module_path.'tpl');
				$this->setTemplateFile('_exportToHTML');
			break;
		}
		require dirname(__FILE__) . '/libs/Export/Excel/' . $classFile;
		$logged_info = Context::get('logged_info');
		$title = '로그인 기록';
		$startDate = Context::get('startDate');
		$endDate = Context::get('endDate');
		if($startDate || $endDate) {
			$title .=' ( ';
			if($startDate) {
				$title .= $startDate . ' ~ ';
				if($endDate) $title .= $endDate;
			} else {
				if($endDate) $title .= ' ~ ' . $endDate;
			}
			$title .=' )';
		}
		$options = array(
			'start_date' => $startDate,
			'end_date' => $endDate,
			'title' => $title,
			'filename' => $filename,
			'properties' =>
				array('creator' => $logged_info->nick_name, 'modifier' => $logged_info->nick_name),
			'font' =>
				array('name' => '나눔고딕', 'size' => 9)
		);
		$object = new Export_Excel($options);
		$object->export();
	}

	public function procLoginlogAdminExportChecked() {
		if(version_compare(phpversion(), '5.2.0', '<')) {
			$phpWarning = sprintf(Context::getLang('php_version_warning_for_feature'), phpversion());
			return new Object(-1, $phpWarning);
		}
		@set_time_limit(0);
		Context::setRequestMethod('XMLRPC');
		require dirname(__FILE__) . '/libs/Export/Interface/Interface.php';
		require dirname(__FILE__) . '/libs/Export/Core.php';
		$exportFileType = Context::get('type');
		switch($exportFileType) {
			case 'excel':
				$classFile = 'Excel.php';
				$filename = 'loginlog_' . date('Y-m-d');
				$this->setTemplatePath($this->module_path.'tpl');
				$this->setTemplateFile('_exportToHTML');
				break;
		}
		require dirname(__FILE__) . '/libs/Export/Excel/' . $classFile;
		$logged_info = Context::get('logged_info');
		$options = array(
			'filename' => $filename,
			'properties' =>
				array('creator' => $logged_info->nick_name, 'modifier' => $logged_info->nick_name),
			'font' =>
				array('name' => '나눔고딕', 'size' => 9)
		);
		$object = new Export_Excel($options);
		$object->export();
	}

	public function deleteLog($log_srl) {
		$args = new stdClass;
		$args->log_srl = $log_srl;
		return executeQuery('loginlog.deleteLog', $args);
	}
}