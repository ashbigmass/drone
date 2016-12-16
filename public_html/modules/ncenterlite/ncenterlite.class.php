<?php
class ncenterlite extends ModuleObject
{
	var $message_mid = '';
	var $disable_notify_bar_mid = array();
	var $disable_notify_bar_act = array();
	var $disable_notify = array();
	var $_TYPE_DOCUMENT = 'D';
	var $_TYPE_COMMENT = 'C';
	var $_TYPE_ADMIN_COMMENT = 'A';
	var $_TYPE_MENTION = 'M';
	var $_TYPE_MESSAGE = 'E';
	var $_TYPE_DOCUMENTS = 'P';
	var $_TYPE_VOTED = 'V';
	var $_TYPE_TEST = 'T';
	var $_TYPE_ADMIN_DOCUMENT = 'B';
	var $_TYPE_CUSTOM = 'U';
	var $triggers = array(
		array('comment.insertComment', 'ncenterlite', 'controller', 'triggerAfterInsertComment', 'after'),
		array('comment.deleteComment', 'ncenterlite', 'controller', 'triggerAfterDeleteComment', 'after'),
		array('document.insertDocument', 'ncenterlite', 'controller', 'triggerAfterInsertDocument', 'after'),
		array('document.deleteDocument', 'ncenterlite', 'controller', 'triggerAfterDeleteDocument', 'after'),
		array('display', 'ncenterlite', 'controller', 'triggerBeforeDisplay', 'before'),
		array('moduleHandler.proc', 'ncenterlite', 'controller', 'triggerAfterModuleHandlerProc', 'after'),
		array('member.deleteMember', 'ncenterlite', 'controller', 'triggerAfterDeleteMember', 'after'),
		array('communication.sendMessage', 'ncenterlite', 'controller', 'triggerAfterSendMessage', 'after'),
		array('document.updateVotedCount', 'ncenterlite', 'controller', 'triggerAfterVotedupdate', 'after'),
		array('moduleHandler.init', 'ncenterlite', 'controller', 'triggerAddMemberMenu', 'after'),
		array('document.moveDocumentToTrash', 'ncenterlite', 'controller', 'triggerAfterMoveToTrash', 'after'),
	);
	private $delete_triggers = array(array('moduleObject.proc', 'ncenterlite', 'controller', 'triggerBeforeModuleObjectProc', 'before'));

	function _isDisable() {
		$result = FALSE;
		if(count($this->disable_notify)) {
			$module_info = Context::get('module_info');
			if(in_array($module_info->mid, $this->disable_notify)) $result = TRUE;
		}
		return $result;
	}

	function moduleInstall() {
		return new Object();
	}

	function checkUpdate() {
		$oModuleModel = getModel('module');
		$oDB = &DB::getInstance();
		foreach($this->triggers as $trigger) if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) return true;
		foreach($this->delete_triggers as $trigger) {
			if($oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) return true;
		}
		if(!$oDB->isColumnExists('ncenterlite_notify', 'readed')) return true;
		if(!$oDB->isColumnExists('ncenterlite_notify', 'target_body')) return true;
		if(!$oDB->isColumnExists('ncenterlite_notify', 'notify_type')) return true;
		if(!$oDB->isColumnExists('ncenterlite_notify', 'target_browser')) return true;
		if(!$oDB->isColumnExists('ncenterlite_notify', 'target_p_srl')) return true;
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_srl')) return true;
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_target_srl')) return true;
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_target_p_srl')) return true;
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_target_member_srl')) return true;
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_member_srl_and_readed')) return true;
		if($oDB->isIndexExists('ncenterlite_notify', 'idx_notify')) return true;
		return false;
	}

	function moduleUpdate() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$oDB = &DB::getInstance();
		foreach($this->triggers as $trigger) {
			if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}
		foreach($this->delete_triggers as $trigger) {
			if($oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}
		if(!$oDB->isColumnExists('ncenterlite_notify','readed')) {
			$oDB->addColumn('ncenterlite_notify', 'readed', 'char', 1, 'N', true);
			$oDB->addIndex('ncenterlite_notify', 'idx_readed', array('readed'));
			$oDB->addIndex('ncenterlite_notify', 'idx_member_srl', array('member_srl'));
			$oDB->addIndex('ncenterlite_notify', 'idx_regdate', array('regdate'));
		}
		if(!$oDB->isColumnExists('ncenterlite_notify','target_browser')) $oDB->addColumn('ncenterlite_notify', 'target_browser', 'varchar', 50, true);
		if(!$oDB->isColumnExists('ncenterlite_notify','target_body')) $oDB->addColumn('ncenterlite_notify', 'target_body', 'varchar', 255, true);
		if(!$oDB->isColumnExists('ncenterlite_notify','notify_type')) $oDB->addColumn('ncenterlite_notify', 'notify_type', 'number', 11, 0);
		if(!$oDB->isColumnExists('ncenterlite_notify','target_p_srl')) $oDB->addColumn('ncenterlite_notify', 'target_p_srl', 'number', 10, true);
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_srl')) $oDB->addIndex('ncenterlite_notify', 'idx_srl', array('srl'));
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_target_srl')) $oDB->addIndex('ncenterlite_notify', 'idx_target_srl', array('target_srl'));
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_target_p_srl')) $oDB->addIndex('ncenterlite_notify', 'idx_target_p_srl', array('target_p_srl'));
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_target_member_srl')) $oDB->addIndex('ncenterlite_notify', 'idx_target_member_srl', array('target_member_srl'));
		if(!$oDB->isIndexExists('ncenterlite_notify', 'idx_member_srl_and_readed')) $oDB->addIndex('ncenterlite_notify', 'idx_member_srl_and_readed', array('member_srl', 'readed'));
		if($oDB->isIndexExists('ncenterlite_notify', 'idx_notify')) $oDB->dropIndex('ncenterlite_notify', 'idx_notify');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
		return new Object();
	}

	function moduleUninstall() {
		$oModuleController = getController('module');
		foreach($this->triggers as $trigger) $oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		return new Object();
	}
}
