<?php
class GeneralXmlParser
{
	var $output = array();

	function parse($input = '') {
		$oParser = xml_parser_create('UTF-8');
		xml_set_object($oParser, $this);
		xml_set_element_handler($oParser, "_tagOpen", "_tagClosed");
		xml_set_character_data_handler($oParser, "_tagBody");
		xml_parse($oParser, $input);
		xml_parser_free($oParser);
		if(count($this->output) < 1) return;
		$this->output = array_shift($this->output);
		return $this->output;
	}

	function _tagOpen($parser, $node_name, $attrs) {
		$obj = new stdClass();
		$obj->node_name = strtolower($node_name);
		$obj->attrs = $attrs;
		$obj->childNodes = array();
		$this->output[] = $obj;
	}

	function _tagBody($parser, $body) {
		$this->output[count($this->output) - 1]->body .= $body;
	}

	function _tagClosed($parser, $node_name) {
		$node_name = strtolower($node_name);
		$cur_obj = array_pop($this->output);
		$parent_obj = &$this->output[count($this->output) - 1];
		$tmp_obj = &$parent_obj->childNodes[$node_name];
		if($tmp_obj) {
			if(is_array($tmp_obj)) $tmp_obj[] = $cur_obj;
			else $tmp_obj = array($tmp_obj, $cur_obj);
		} else {
			$tmp_obj = $cur_obj;
		}
	}
}
