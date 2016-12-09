<?php
define('__XE__',   TRUE);
require dirname(__FILE__) . '/config/config.inc.php';

$oContext = Context::getInstance();
$oContext->init();

if($oContext->checkSSO()) {
	$oModuleHandler = new ModuleHandler();
	try {
		if($oModuleHandler->init()) $oModuleHandler->displayContent($oModuleHandler->procModule());
	} catch(Exception $e) {
		htmlHeader();
		echo Context::getLang($e->getMessage());
		htmlFooter();
	}
}

$oContext->close();
