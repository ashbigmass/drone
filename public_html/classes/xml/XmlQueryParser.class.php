<?php
if(!defined('__XE_LOADED_XML_CLASS__')) define('__XE_LOADED_XML_CLASS__', 1);

class XmlQueryParser extends XmlParser
{

	function XmlQueryParser() {
	}

	function &getInstance() {
		static $theInstance = NULL;
		if(!isset($theInstance)) $theInstance = new XmlQueryParser();
		return $theInstance;
	}

	function &parse_xml_query($query_id, $xml_file, $cache_file) {
		$xml_obj = $this->getXmlFileContent($xml_file);
		$action = strtolower($xml_obj->query->attrs->action);
		if(!$action) return;
		$parser = new QueryParser($xml_obj->query);
		FileHandler::writeFile($cache_file, $parser->toString());
		return $parser;
	}

	function parse($query_id = NULL, $xml_file = NULL, $cache_file = NULL) {
		$this->parse_xml_query($query_id, $xml_file, $cache_file);
	}

	function getXmlFileContent($xml_file) {
		$buff = FileHandler::readFile($xml_file);
		$xml_obj = parent::parse($buff);
		if(!$xml_obj) return;
		unset($buff);
		return $xml_obj;
	}
}
