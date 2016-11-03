<?php
class adminlogging extends ModuleObject {

	function moduleInstall() {
		return new Object();
	}

	function checkUpdate() {
		return FALSE;
	}

	function moduleUpdate() {
		return new Object();
	}

	function recompileCache() {
	}

}
