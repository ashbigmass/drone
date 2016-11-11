<?php
class install extends ModuleObject {
	function moduleInstall() {
		return new Object();
	}

	function checkUpdate() {
		return false;
	}

	function moduleUpdate() {
		return new Object();
	}

	function recompileCache() {	}
}
