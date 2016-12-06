<?php
class spamfilter extends ModuleObject
{
	function moduleInstall() {
		$oModuleController = getController('module');
		$oModuleController->insertTrigger('document.insertDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before');
		$oModuleController->insertTrigger('comment.insertComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before');
		$oModuleController->insertTrigger('trackback.insertTrackback', 'spamfilter', 'controller', 'triggerInsertTrackback', 'before');
		$oModuleController->insertTrigger('comment.updateComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before');
		$oModuleController->insertTrigger('document.updateDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before');
		$oModuleController->insertTrigger('communication.sendMessage', 'spamfilter', 'controller', 'triggerSendMessage', 'before');
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before')) return true;
		if(!$oModuleModel->getTrigger('comment.insertComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before')) return true;
		if(!$oModuleModel->getTrigger('trackback.insertTrackback', 'spamfilter', 'controller', 'triggerInsertTrackback', 'before')) return true;
		if(!$oModuleModel->getTrigger('comment.updateComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before')) return true;
		if(!$oModuleModel->getTrigger('document.updateDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before')) return true;
		if(!$oModuleModel->getTrigger('communication.sendMessage', 'spamfilter', 'controller', 'triggerSendMessage', 'before')) return true;
		if(!$oDB->isColumnExists('spamfilter_denied_word', 'hit')) return true;
		if(!$oDB->isColumnExists('spamfilter_denied_word', 'latest_hit')) return true;
		if(!$oDB->isColumnExists('spamfilter_denied_ip', 'description')) return true;
		return false;
	}

	function moduleUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('document.insertDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before'))
			$oModuleController->insertTrigger('document.insertDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before');
		if(!$oModuleModel->getTrigger('comment.insertComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before'))
			$oModuleController->insertTrigger('comment.insertComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before');
		if(!$oModuleModel->getTrigger('trackback.insertTrackback', 'spamfilter', 'controller', 'triggerInsertTrackback', 'before'))
			$oModuleController->insertTrigger('trackback.insertTrackback', 'spamfilter', 'controller', 'triggerInsertTrackback', 'before');
		if(!$oModuleModel->getTrigger('comment.updateComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before'))
			$oModuleController->insertTrigger('comment.updateComment', 'spamfilter', 'controller', 'triggerInsertComment', 'before');
		if(!$oModuleModel->getTrigger('document.updateDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before'))
			$oModuleController->insertTrigger('document.updateDocument', 'spamfilter', 'controller', 'triggerInsertDocument', 'before');
		if(!$oModuleModel->getTrigger('communication.sendMessage', 'spamfilter', 'controller', 'triggerSendMessage', 'before'))
			$oModuleController->insertTrigger('communication.sendMessage', 'spamfilter', 'controller', 'triggerSendMessage', 'before');
		if(!$oDB->isColumnExists('spamfilter_denied_word', 'hit')) {
			$oDB->addColumn('spamfilter_denied_word','hit','number',12,0,true);
			$oDB->addIndex('spamfilter_denied_word','idx_hit', 'hit');
		}
		if(!$oDB->isColumnExists('spamfilter_denied_word', 'latest_hit')) {
			$oDB->addColumn('spamfilter_denied_word','latest_hit','date');
			$oDB->addIndex('spamfilter_denied_word','idx_latest_hit', 'latest_hit');
		}
		if(!$oDB->isColumnExists('spamfilter_denied_ip', 'description')) {
			$oDB->addColumn('spamfilter_denied_ip','description','varchar', 250);
		}
		return new Object(0,'success_updated');
	}

	function recompileCache() {
	}
}
