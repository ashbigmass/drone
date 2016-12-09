<?php
class loginlog extends ModuleObject
{
	private $triggers = array(
		array('member.doLogin'		, 'loginlog', 'controller', 'triggerAfterLogin',		'after'),
		array('member.deleteMember'	, 'loginlog', 'controller', 'triggerDeleteMember',		'after'),
		array('moduleHandler.init'	, 'loginlog', 'controller', 'triggerBeforeModuleInit',	'after'),
		array('moduleHandler.proc'	, 'loginlog', 'controller', 'triggerBeforeModuleProc',	'after')
	);

	public function moduleInstall() {
		$this->insertTrigger();
		return new Object();
	}

	public function moduleUninstall() {
		$oModuleController = getController('module');
		foreach($this->triggers as $trigger) $oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		return new Object();
	}

	public function checkUpdate() {
		$oModuleModel = getModel('module');
		if(!$this->checkTrigger()) return true;
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('member_loginlog', 'is_succeed')) return true;
		if(!$oDB->isColumnExists('member_loginlog', 'log_srl')) return true;
		if(!$oDB->isColumnExists('member_loginlog', 'platform')) return true;
		if(!$oDB->isColumnExists('member_loginlog', 'browser')) return true;
		if(!$oDB->isColumnExists('member_loginlog', 'user_id')) return true;
		if(!$oDB->isColumnExists('member_loginlog', 'email_address')) return true;
		return false;
	}

	public function checkTrigger() {
		$oModuleModel = getModel('module');
		foreach($this->triggers as $trigger) {
			if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) return false;
		}
		return true;
	}

	public function insertTrigger() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach($this->triggers as $trigger) {
			if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) {
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}
	}

	public function moduleUpdate() {
		@set_time_limit(0);
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$this->insertTrigger();
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('member_loginlog', 'is_succeed')) {
			$oDB->addColumn('member_loginlog', 'is_succeed', 'char', 1, 'Y', true);
			$oDB->addIndex('member_loginlog', 'idx_is_succeed', 'is_succeed', false);
		}
		if(!$oDB->isColumnExists('member_loginlog', 'log_srl')) {
			$oDB->addColumn('member_loginlog', 'log_srl', 'number', 11, '', true);
		}
		if(!$oDB->isColumnExists('member_loginlog', 'platform')) {
			$oDB->addColumn('member_loginlog', 'platform', 'varchar', 50, '', true);
			$oDB->addIndex('member_loginlog', 'idx_platform', 'platform', false);
		}
		if(!$oDB->isColumnExists('member_loginlog', 'browser')) {
			$oDB->addColumn('member_loginlog', 'browser', 'varchar', 50, '', true);
			$oDB->addIndex('member_loginlog', 'idx_browser', 'browser', false);
		}
		if(!$oDB->isColumnExists('member_loginlog', 'user_id')) {
			$oDB->addColumn('member_loginlog', 'user_id', 'varchar', 80, '', true);
			$oDB->addIndex('member_loginlog', 'idx_user_id', 'user_id', false);
		}
		if(!$oDB->isColumnExists('member_loginlog', 'email_address')) {
			$oDB->addColumn('member_loginlog', 'email_address', 'varchar', 250, '', true);
			$oDB->addIndex('member_loginlog', 'idx_email_address', 'email_address', false);
		}
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
	}
}
