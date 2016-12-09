<?php
class communicationModel extends communication
{
	function init() {
	}

	function getConfig() {
		$oModuleModel = getModel('module');
		$communication_config = $oModuleModel->getModuleConfig('communication');
		if(!is_object($communication_config)) $communication_config = new stdClass();
		if(!$communication_config->skin) $communication_config->skin = 'default';
		if(!$communication_config->colorset) $communication_config->colorset = 'white';
		if(!$communication_config->editor_skin) $communication_config->editor_skin = 'ckeditor';
		if(!$communication_config->mskin) $communication_config->mskin = 'default';
		if(!$communication_config->grant_write) $communication_config->grant_write = array('default_grant'=>'member');
		return $communication_config;
	}

	function getGrantArray($default, $group) {
		$grant = array();
		if($default!="") {
			switch($default) {
				case "-2": $grant = array("default_grant"=>"site"); break;
				case "-3": $grant = array("default_grant"=>"manager"); break;
				default : $grant = array("default_grant"=>"member"); break;
			}
		} else if(is_array($group))  {
			$oMemberModel = getModel('member');
			$group_list = $oMemberModel->getGroups($this->site_srl);
			$group_grant = array();
			foreach($group as $group_srl) $group_grant[$group_srl] = $group_list[$group_srl]->title;
			$grant = array('group_grant'=>$group_grant);
		}
		return $grant;
	}

	function checkGrant($arrGrant) {
		if(!$arrGrant) return false;
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return false;
		if($logged_info->is_admin == "Y") return true;
		if($arrGrant['default_grant']) {
			if($arrGrant['default_grant'] == "member" && $logged_info) return true;
			if($arrGrant['default_grant'] == "site" && $this->site_srl == $logged_info->site_srl) return true;
			if($arrGrant['default_grant'] == "manager" && $logged_info->is_admin == "Y") return true;
		}
		if($arrGrant['group_grant']) {
			$group_grant = $arrGrant['group_grant'];
			if(!is_array($group_grant)) return false;
			foreach($logged_info->group_list as $group_srl=>$title) {
				if(isset($group_grant[$group_srl])&&$group_grant[$group_srl]==$title) return true;
			}
		}
		return false;
	}

	function getSelectedMessage($message_srl, $columnList = array()) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->message_srl = $message_srl;
		$output = executeQuery('communication.getMessage', $args, $columnList);
		$message = $output->data;
		if(!$message) return;
		$oMemberModel = getModel('member');
		if($message->sender_srl == $logged_info->member_srl && $message->message_type == 'S') {
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($message->receiver_srl);
		} else {
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($message->sender_srl);
		}
		if($member_info) {
			foreach($member_info as $key => $val) {
				if($key === 'title') continue;
				if($key === 'content') continue;
				if($key === 'sender_srl') continue;
				if($key === 'password') continue;
				if($key === 'regdate') continue;
				$message->{$key} = $val;
			}
		}
		if($message->message_type == 'R' && $message->readed != 'Y') {
			$oCommunicationController = getController('communication');
			$oCommunicationController->setMessageReaded($message_srl);
		}
		return $message;
	}

	function getNewMessage($columnList = array()) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->receiver_srl = $logged_info->member_srl;
		$args->readed = 'N';
		$output = executeQuery('communication.getNewMessage', $args, $columnList);
		if(!count($output->data)) return;
		$message = array_pop($output->data);
		$oCommunicationController = getController('communication');
		$oCommunicationController->setMessageReaded($message->message_srl);
		return $message;
	}

	function getMessages($message_type = "R", $columnList = array()) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		switch($message_type) {
			case 'R' :
				$args->member_srl = $logged_info->member_srl;
				$args->message_type = 'R';
				$query_id = 'communication.getReceivedMessages';
			break;
			case 'T' :
				$args->member_srl = $logged_info->member_srl;
				$args->message_type = 'T';
				$query_id = 'communication.getStoredMessages';
			break;
			default :
				$args->member_srl = $logged_info->member_srl;
				$args->message_type = 'S';
				$query_id = 'communication.getSendedMessages';
			break;
		}
		$args->sort_index = 'message.list_order';
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		return executeQuery($query_id, $args, $columnList);
	}

	function getFriends($friend_group_srl = 0, $columnList = array()) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->friend_group_srl = $friend_group_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->page = Context::get('page');
		$args->sort_index = 'friend.list_order';
		$args->list_count = 10;
		$args->page_count = 10;
		$output = executeQuery('communication.getFriends', $args, $columnList);
		return $output;
	}

	function isAddedFriend($member_srl) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->target_srl = $member_srl;
		$output = executeQuery('communication.isAddedFriend', $args);
		return $output->data->count;
	}

	function getFriendGroupInfo($friend_group_srl) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->friend_group_srl = $friend_group_srl;
		$output = executeQuery('communication.getFriendGroup', $args);
		return $output->data;
	}

	function getFriendGroups() {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$output = executeQueryArray('communication.getFriendGroups', $args);
		$group_list = $output->data;
		if(!$group_list) return;
		return $group_list;
	}

	function isFriend($target_srl) {
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->member_srl = $target_srl;
		$args->target_srl = $logged_info->member_srl;
		$output = executeQuery('communication.isAddedFriend', $args);
		if($output->data->count) return TRUE;
		return FALSE;
	}
}
