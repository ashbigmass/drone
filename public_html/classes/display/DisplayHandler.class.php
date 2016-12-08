<?php
class DisplayHandler extends Handler
{
	var $content_size = 0;
	var $gz_enabled = FALSE;
	var $handler = NULL;

	function printContent(&$oModule) {
		if(
			(defined('__OB_GZHANDLER_ENABLE__') && __OB_GZHANDLER_ENABLE__ == 1) &&
			strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE &&
			function_exists('ob_gzhandler') &&
			extension_loaded('zlib') &&
			$oModule->gzhandler_enable
		)
		{
			$this->gz_enabled = TRUE;
		}
		if(Context::get('xeVirtualRequestMethod') == 'xml') {
			$handler = new VirtualXMLDisplayHandler();
		} else if(Context::getRequestMethod() == 'XMLRPC') {
			$handler = new XMLDisplayHandler();
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE) $this->gz_enabled = FALSE;
		} else if(Context::getRequestMethod() == 'JSON') {
			$handler = new JSONDisplayHandler();
		} else if(Context::getRequestMethod() == 'JS_CALLBACK') {
			$handler = new JSCallbackDisplayHandler();
		} else {
			$handler = new HTMLDisplayHandler();
		}
		$output = $handler->toDoc($oModule);
		ModuleHandler::triggerCall('display', 'before', $output);
		$called_position = 'before_display_content';
		$oAddonController = getController('addon');
		$addon_file = $oAddonController->getCacheFilePath(Mobile::isFromMobilePhone() ? "mobile" : "pc");
		if(file_exists($addon_file)) include($addon_file);
		if(method_exists($handler, "prepareToPrint")) $handler->prepareToPrint($output);
		$httpStatusCode = $oModule->getHttpStatusCode();
		if($httpStatusCode && $httpStatusCode != 200) {
			$this->_printHttpStatusCode($httpStatusCode);
		} else {
			if(Context::getResponseMethod() == 'JSON' || Context::getResponseMethod() == 'JS_CALLBACK') $this->_printJSONHeader();
			else if(Context::getResponseMethod() != 'HTML') $this->_printXMLHeader();
			else $this->_printHTMLHeader();
		}
		$this->content_size = strlen($output);
		$output .= $this->_debugOutput();
		ob_flush();
		if(headers_sent()) $this->gz_enabled = FALSE;
		if($this->gz_enabled) {
			header("Content-Encoding: gzip");
			print ob_gzhandler($output, 5);
		} else {
			print $output;
		}
		ModuleHandler::triggerCall('display', 'after', $output);
		flushSlowlog();
	}

	function _debugOutput() {
		if(!__DEBUG__) return;
		$end = getMicroTime();
		if(__DEBUG_OUTPUT__ == 2 && version_compare(PHP_VERSION, '6.0.0') === -1) {
			static $firephp;
			if(!isset($firephp)) $firephp = FirePHP::getInstance(true);
			if(__DEBUG_PROTECT__ == 1 && __DEBUG_PROTECT_IP__ != $_SERVER['REMOTE_ADDR']) {
				$firephp->fb('Change the value of __DEBUG_PROTECT_IP__ into your IP address in config/config.user.inc.php or config/config.inc.php', 'The IP address is not allowed.');
				return;
			}
			if(__DEBUG__ & 2) {
				$firephp->fb(
					array(
						'Request / Response info >>> ' . $_SERVER['REQUEST_METHOD'] . ' / ' . Context::getResponseMethod(),
						array(
							array('Request URI', 'Request method', 'Response method', 'Response contents size', 'Memory peak usage'),
							array(
								sprintf("%s:%s%s%s%s", $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING'] ? '?' : '', $_SERVER['QUERY_STRING']),
								$_SERVER['REQUEST_METHOD'],
								Context::getResponseMethod(),
								$this->content_size . ' byte',
								FileHandler::filesize(memory_get_peak_usage())
							)
						)
					),
					'TABLE'
				);
				$firephp->fb(
					array(
						'Elapsed time >>> Total : ' . sprintf('%0.5f sec', $end - __StartTime__),
						array(array('DB queries', 'class file load', 'Template compile', 'XmlParse compile', 'PHP', 'Widgets', 'Trans Content'),
							array(
								sprintf('%0.5f sec', $GLOBALS['__db_elapsed_time__']),
								sprintf('%0.5f sec', $GLOBALS['__elapsed_class_load__']),
								sprintf('%0.5f sec (%d called)', $GLOBALS['__template_elapsed__'], $GLOBALS['__TemplateHandlerCalled__']),
								sprintf('%0.5f sec', $GLOBALS['__xmlparse_elapsed__']),
								sprintf('%0.5f sec', $end - __StartTime__ - $GLOBALS['__template_elapsed__'] - $GLOBALS['__xmlparse_elapsed__'] - $GLOBALS['__db_elapsed_time__'] - $GLOBALS['__elapsed_class_load__']),
								sprintf('%0.5f sec', $GLOBALS['__widget_excute_elapsed__']),
								sprintf('%0.5f sec', $GLOBALS['__trans_content_elapsed__'])
							)
						)
					),
					'TABLE'
				);
			}
			if((__DEBUG__ & 4) && $GLOBALS['__db_queries__']) {
				$queries_output = array(array('Result/'.PHP_EOL.'Elapsed time', 'Query ID', 'Query'));
				foreach($GLOBALS['__db_queries__'] as $query) {
					$queries_output[] = array($query['result'] . PHP_EOL . sprintf('%0.5f', $query['elapsed_time']), str_replace(_XE_PATH_, '', $query['called_file']) . PHP_EOL . $query['called_method'] . '()' . PHP_EOL . $query['query_id'], $query['query']);
				}
				$firephp->fb(
					array(
						'DB Queries >>> ' . count($GLOBALS['__db_queries__']) . ' Queries, ' . sprintf('%0.5f sec', $GLOBALS['__db_elapsed_time__']),
						$queries_output
					),
					'TABLE'
				);
			}
		} else {
			$buff = array();
			if(__DEBUG__ & 2) {
				if(__DEBUG_PROTECT__ == 1 && __DEBUG_PROTECT_IP__ != $_SERVER['REMOTE_ADDR']) return;
				$buff[] = "\n- Request/ Response info";
				$buff[] = sprintf("\tRequest URI \t\t\t: %s:%s%s%s%s", $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING'] ? '?' : '', $_SERVER['QUERY_STRING']);
				$buff[] = sprintf("\tRequest method \t\t\t: %s", $_SERVER['REQUEST_METHOD']);
				$buff[] = sprintf("\tResponse method \t\t: %s", Context::getResponseMethod());
				$buff[] = sprintf("\tResponse contents size\t: %d byte", $this->content_size);
				$buff[] = sprintf("\n- Total elapsed time : %0.5f sec", $end - __StartTime__);
				$buff[] = sprintf("\tclass file load elapsed time \t: %0.5f sec", $GLOBALS['__elapsed_class_load__']);
				$buff[] = sprintf("\tTemplate compile elapsed time\t: %0.5f sec (%d called)", $GLOBALS['__template_elapsed__'], $GLOBALS['__TemplateHandlerCalled__']);
				$buff[] = sprintf("\tXmlParse compile elapsed time\t: %0.5f sec", $GLOBALS['__xmlparse_elapsed__']);
				$buff[] = sprintf("\tPHP elapsed time \t\t\t\t: %0.5f sec", $end - __StartTime__ - $GLOBALS['__template_elapsed__'] - $GLOBALS['__xmlparse_elapsed__'] - $GLOBALS['__db_elapsed_time__'] - $GLOBALS['__elapsed_class_load__']);
				$buff[] = sprintf("\tDB class elapsed time \t\t\t: %0.5f sec", $GLOBALS['__dbclass_elapsed_time__'] - $GLOBALS['__db_elapsed_time__']);
				$buff[] = sprintf("\tWidgets elapsed time \t\t\t: %0.5f sec", $GLOBALS['__widget_excute_elapsed__']);
				$buff[] = sprintf("\tLayout compile elapsed time \t: %0.5f sec", $GLOBALS['__layout_compile_elapsed__']);
				$buff[] = sprintf("\tTrans Content \t\t\t\t\t: %0.5f sec", $GLOBALS['__trans_content_elapsed__']);
			}
			if(__DEBUG__ & 4) {
				if(__DEBUG_PROTECT__ == 1 && __DEBUG_PROTECT_IP__ != $_SERVER['REMOTE_ADDR']) return;
				if($GLOBALS['__db_queries__']) {
					$buff[] = sprintf("\n- DB Queries : %d Queries. %0.5f sec", count($GLOBALS['__db_queries__']), $GLOBALS['__db_elapsed_time__']);
					$num = 0;
					foreach($GLOBALS['__db_queries__'] as $query) {
						if($query['result'] == 'Success') {
							$query_result = "Query Success";
						} else {
							$query_result = sprintf("Query $s : %d\n\t\t\t   %s", $query['result'], $query['errno'], $query['errstr']);
						}
						$buff[] = sprintf("\t%02d. %s\n\t\t%0.6f sec. %s.", ++$num, $query['query'], $query['elapsed_time'], $query_result);
						$buff[] = sprintf("\t\tConnection: %s.", $query['connection']);
						$buff[] = sprintf("\t\tQuery ID: %s", $query['query_id']);
						$buff[] = sprintf("\t\tCalled: %s. %s()", str_replace(_XE_PATH_, '', $query['called_file']), $query['called_method']);
					}
				}
			}
			if($buff && __DEBUG_OUTPUT__ == 1 && Context::getResponseMethod() == 'HTML') {
				$buff = implode("\r\n", $buff);
				$buff = sprintf("[%s %s:%d]\r\n%s", date('Y-m-d H:i:s'), $file_name, $line_num, print_r($buff, true));
				if(__DEBUG_PROTECT__ == 1 && __DEBUG_PROTECT_IP__ != $_SERVER['REMOTE_ADDR']) {
					$buff = 'The IP address is not allowed. Change the value of __DEBUG_PROTECT_IP__ into your IP address in config/config.user.inc.php or config/config.inc.php';
				}
				return "<!--\r\n" . $buff . "\r\n-->";
			}
			if($buff && __DEBUG_OUTPUT__ == 0) {
				$debug_file = _XE_PATH_ . 'files/_debug_message.php';
				$buff = implode(PHP_EOL, $buff);
				$buff = sprintf("[%s]\n%s", date('Y-m-d H:i:s'), print_r($buff, true));
				$buff = str_repeat('=', 80) . "\n" . $buff . "\n" . str_repeat('-', 80);
				$buff = "\n<?php\n/*" . $buff . "*/\n?>\n";
				if (!@file_put_contents($debug_file, $buff, FILE_APPEND|LOCK_EX)) return;
			}
		}
	}

	function _printXMLHeader() {
		header("Content-Type: text/xml; charset=UTF-8");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}

	function _printHTMLHeader() {
		header("Content-Type: text/html; charset=UTF-8");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}

	function _printJSONHeader() {
		header("Content-Type: text/html; charset=UTF-8");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}

	function _printHttpStatusCode($code) {
		$statusMessage = Context::get('http_status_message');
		header("HTTP/1.0 $code $statusMessage");
	}
}
