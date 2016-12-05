<?php
if(!defined('__XE__')) exit();

$logged_info = Context::get('logged_info');
if(!$logged_info|| isCrawler()) return;

if($this->module != 'member' && $called_position == 'before_module_init')
	Context::loadLang(_XE_PATH_ . 'modules/communication/lang');
	$oMemberController = getController('member');
	$oMemberController->addMemberMenu('dispCommunicationFriend', 'cmd_view_friend');
	$oMemberController->addMemberMenu('dispCommunicationMessages', 'cmd_view_message_box');
	$flag_file = _XE_PATH_ . 'files/member_extra_info/new_message_flags/' . getNumberingPath($logged_info->member_srl) . $logged_info->member_srl;
	if($addon_info->use_alarm != 'N' && file_exists($flag_file)) {
		$new_message_count = (int) trim(FileHandler::readFile($flag_file));
		FileHandler::removeFile($flag_file);
		Context::loadLang(_XE_PATH_ . 'addons/member_communication/lang');
		Context::loadFile(array('./addons/member_communication/tpl/member_communication.js'), true);
		$text = preg_replace('@\r?\n@', '\\n', addslashes(Context::getLang('alert_new_message_arrived')));
		Context::addHtmlFooter("<script type=\"text/javascript\">jQuery(function(){ xeNotifyMessage('{$text}','{$new_message_count}'); });</script>");
	}
} elseif($this->act == 'getMemberMenu' && $called_position == 'before_module_proc') {
	$member_srl = Context::get('target_srl');
	$oCommunicationModel = getModel('communication');
	if($logged_info->member_srl == $member_srl) {
		$mid = Context::get('cur_mid');
		$oMemberController = getController('member');
		$oMemberController->addMemberPopupMenu(getUrl('', 'mid', $mid, 'act', 'dispCommunicationMessages'), 'cmd_view_message_box', '', 'self');
		$oMemberController->addMemberPopupMenu(getUrl('', 'mid', $mid, 'act', 'dispCommunicationFriend'), 'cmd_view_friend', '', 'self');
	} else {
		$oMemberModel = getModel('member');
		$target_member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		if(!$target_member_info->member_srl) return;
		$oMemberController = getController('member');
		if($logged_info->is_admin == 'Y' || $target_member_info->allow_message == 'Y' || ($target_member_info->allow_message == 'F' && $oCommunicationModel->isFriend($member_srl)))
			$oMemberController->addMemberPopupMenu(getUrl('', 'mid', Context::get('cur_mid'), 'act', 'dispCommunicationSendMessage', 'receiver_srl', $member_srl), 'cmd_send_message', '', 'popup');
		if(!$oCommunicationModel->isAddedFriend($member_srl))
			$oMemberController->addMemberPopupMenu(getUrl('', 'mid', Context::get('cur_mid'), 'act', 'dispCommunicationAddFriend', 'target_srl', $member_srl), 'cmd_add_friend', '', 'popup');
	}
}
