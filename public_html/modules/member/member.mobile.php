<?php
require_once(_XE_PATH_.'modules/member/member.view.php');
class memberMobile extends memberView
{
	var $memberInfo;

	function init() {
		$oMemberModel = getModel('member');
		$this->member_config = $oMemberModel->getMemberConfig();
		Context::set('member_config', $this->member_config);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('member_config.signupForm..');
		$mskin = $this->member_config->mskin;
		if(!$mskin) {
			$mskin = 'default';
			$template_path = sprintf('%sm.skins/%s', $this->module_path, $mskin);
		} else {
			$template_path = sprintf('%sm.skins/%s', $this->module_path, $mskin);
		}
		$member_srl = Context::get('member_srl');
		if($member_srl) {
			$oMemberModel = getModel('member');
			$this->memberInfo = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
			if(!$this->memberInfo) Context::set('member_srl','');
			else Context::set('member_info',$this->memberInfo);
		}
		$this->setTemplatePath($template_path);
		$oLayoutModel = getModel('layout');
		$layout_info = $oLayoutModel->getLayout($this->member_config->mlayout_srl);
		if($layout_info) {
			$this->module_info->mlayout_srl = $this->member_config->mlayout_srl;
			$this->setLayoutPath($layout_info->path);
		}
	}

	function dispMemberModifyInfo() {
		parent::dispMemberModifyInfo();
		if($this->member_info) Context::set('oMemberInfo', get_object_vars($this->member_info));
	}
}
