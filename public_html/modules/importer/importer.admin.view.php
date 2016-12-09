<?php
class importerAdminView extends importer
{
	function init() {
	}

	function dispImporterAdminContent() {
		$this->setTemplatePath($this->module_path.'tpl');
		$source_type = Context::get('source_type');
		switch($source_type) {
			case 'member' :
				$template_filename = "member";
			break;
			case 'ttxml' :
				$oModuleModel = getModel('module');
				$template_filename = "ttxml";
			break;
			case 'module' :
				$oModuleModel = getModel('module');
				$template_filename = "module";
			break;
			case 'message' :
				$template_filename = "message";
			break;
			case 'sync' :
				$template_filename = "sync";
			break;
			default :
				$template_filename = "index";
			break;
		}
		$this->setTemplateFile($template_filename);
	}

	function dispImporterAdminImportForm() {
		$oDocumentModel = getModel('document');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('index');
	}
}
