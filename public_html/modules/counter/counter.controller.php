<?php
class counterController extends counter
{
	function init() {
	}

	function procCounterExecute() {
	}

	function counterExecute() {
		$oDB = DB::getInstance();
		$oDB->begin();
		$site_module_info = Context::get('site_module_info');
		$site_srl = (int) $site_module_info->site_srl;
		$oCounterModel = getModel('counter');
		if($oCounterModel->isInsertedTodayStatus($site_srl)) {
			if($oCounterModel->isLogged($site_srl)) {
				$this->insertPageView($site_srl);
			} else {
				$this->insertLog($site_srl);
				$this->insertUniqueVisitor($site_srl);
			}
		} else {
			$this->insertTodayStatus(0, $site_srl);
		}
		$oDB->commit();
	}

	function insertLog($site_srl = 0) {
		$args = new stdClass();
		$args->regdate = date("YmdHis");
		$args->user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 250);
		$args->site_srl = $site_srl;
		return executeQuery('counter.insertCounterLog', $args);
	}

	function insertUniqueVisitor($site_srl = 0) {
		$args = new stdClass();
		$args->regdate = '0,' . date('Ymd');
		if($site_srl) {
			$args->site_srl = $site_srl;
			$output = executeQuery('counter.updateSiteCounterUnique', $args);
		} else {
			$output = executeQuery('counter.updateCounterUnique', $args);
		}
	}

	function insertPageView($site_srl = 0) {
		$args = new stdClass;
		$args->regdate = '0,' . date('Ymd');
		if($site_srl) {
			$args->site_srl = $site_srl;
			executeQuery('counter.updateSiteCounterPageview', $args);
		} else {
			executeQuery('counter.updateCounterPageview', $args);
		}
	}

	function insertTotalStatus($site_srl = 0) {
		$args = new stdClass();
		$args->regdate = 0;
		if($site_srl) {
			$args->site_srl = $site_srl;
			executeQuery('counter.insertSiteTodayStatus', $args);
		} else {
			executeQuery('counter.insertTodayStatus', $args);
		}
	}

	function insertTodayStatus($regdate = 0, $site_srl = 0) {
		$args = new stdClass();
		if($regdate) $args->regdate = $regdate;
		else $args->regdate = date("Ymd");
		if($site_srl) {
			$args->site_srl = $site_srl;
			$query_id = 'counter.insertSiteTodayStatus';
			$u_args->site_srl = $site_srl;
			executeQuery($query_id, $u_args);
		} else {
			$query_id = 'counter.insertTodayStatus';
			executeQuery($query_id);
		}
		$output = executeQuery($query_id, $args);
		$this->insertLog($site_srl);
		$this->insertUniqueVisitor($site_srl);
	}

	function deleteSiteCounterLogs($site_srl) {
		$args = new stdClass();
		$args->site_srl = $site_srl;
		executeQuery('counter.deleteSiteCounter', $args);
		executeQuery('counter.deleteSiteCounterLog', $args);
	}
}
