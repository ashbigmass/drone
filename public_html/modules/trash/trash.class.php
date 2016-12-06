<?php
require_once(_XE_PATH_.'modules/trash/model/TrashVO.php');

class trash extends ModuleObject
{
	function moduleInstall() {
		return new Object();
	}

	function checkUpdate() {
		return false;
	}

	function moduleUpdate() {
		return new Object(0,'success_updated');
	}
}
