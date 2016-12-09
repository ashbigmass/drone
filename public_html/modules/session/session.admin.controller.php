<?php
class sessionAdminController extends session
{
	function init() {
	}

	function procSessionAdminClear() {
		$oSessionController = getController('session');
		$oSessionController->gc(0);
		$this->add('result',Context::getLang('session_cleared'));
	}
}
