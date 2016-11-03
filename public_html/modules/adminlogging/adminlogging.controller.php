<?php
class adminloggingController extends adminlogging {

	function init() {
		$oMemberModel = getModel('member');
		$logged_info = $oMemberModel->getLoggedInfo();
		if($logged_info->is_admin != 'Y') return $this->stop("msg_is_not_administrator");
	}

	function insertLog($module, $act) {
		if(!$module || !$act) return;

		$args = new stdClass();
		$args->module = $module;
		$args->act = $act;
		$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$args->regdate = date('YmdHis');
		$args->requestVars = print_r(Context::getRequestVars(), TRUE);

		$output = executeQuery('adminlogging.insertLog', $args);
	}

}
