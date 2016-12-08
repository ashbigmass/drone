<?php
class XMLDisplayHandler
{
	function toDoc(&$oModule) {
		$variables = $oModule->getVariables();
		$xmlDoc = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<response>\n";
		$xmlDoc .= sprintf("<error>%s</error>\n", $oModule->getError());
		$xmlDoc .= sprintf("<message>%s</message>\n", str_replace(array('<', '>', '&'), array('&lt;', '&gt;', '&amp;'), $oModule->getMessage()));
		$xmlDoc .= $this->_makeXmlDoc($variables);
		$xmlDoc .= "</response>";
		return $xmlDoc;
	}

	function _makeXmlDoc($obj) {
		if(!count($obj)) return;
		$xmlDoc = '';
		foreach($obj as $key => $val) {
			if(is_numeric($key)) $key = 'item';
			if(is_string($val)) $xmlDoc .= sprintf('<%s><![CDATA[%s]]></%s>%s', $key, $val, $key, "\n");
			else if(!is_array($val) && !is_object($val)) $xmlDoc .= sprintf('<%s>%s</%s>%s', $key, $val, $key, "\n");
			else $xmlDoc .= sprintf('<%s>%s%s</%s>%s', $key, "\n", $this->_makeXmlDoc($val), $key, "\n");
		}
		return $xmlDoc;
	}
}
