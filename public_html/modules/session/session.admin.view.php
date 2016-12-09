<?php
class sessionAdminView extends session
{
	function init() {
	}

	function dispSessionAdminIndex() {
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('index');
	}
}
