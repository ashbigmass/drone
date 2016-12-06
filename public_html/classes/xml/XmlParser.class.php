<?php
class Xml_Node_
{
	function __get($name) {
		return NULL;
	}
}

class XmlParser
{
	var $oParser = NULL;
	var $input = NULL;
	var $output = array();
	var $lang = "en";

	function loadXmlFile($filename) {
		if(!file_exists($filename)) return;
		$buff = FileHandler::readFile($filename);
		$oXmlParser = new XmlParser();
		return $oXmlParser->parse($buff);
	}

	function parse($input = '', $arg1 = NULL, $arg2 = NULL) {
		if(__DEBUG__ == 3) $start = getMicroTime();
		$this->lang = Context::getLangType();
		$this->input = $input ? $input : $GLOBALS['HTTP_RAW_POST_DATA'];
		$this->input = str_replace(array('', ''), array('', ''), $this->input);
		preg_match_all("/xml:lang=\"([^\"].+)\"/i", $this->input, $matches);
		if(count($matches[1]) && $supported_lang = array_unique($matches[1])) {
			$tmpLangList = array_flip($supported_lang);
			if(!isset($tmpLangList[$this->lang])) {
				if(isset($tmpLangList['en'])) $this->lang = 'en';
				else $this->lang = array_shift($supported_lang);
			}
		} else {
			$this->lang = '';
		}
		$this->oParser = xml_parser_create('UTF-8');
		xml_set_object($this->oParser, $this);
		xml_set_element_handler($this->oParser, "_tagOpen", "_tagClosed");
		xml_set_character_data_handler($this->oParser, "_tagBody");
		xml_parse($this->oParser, $this->input);
		xml_parser_free($this->oParser);
		if(!count($this->output)) return;
		$output = array_shift($this->output);
		if(__DEBUG__ == 3) $GLOBALS['__xmlparse_elapsed__'] += getMicroTime() - $start;
		return $output;
	}

	function _tagOpen($parser, $node_name, $attrs) {
		$obj = new Xml_Node_();
		$obj->node_name = strtolower($node_name);
		$obj->attrs = $this->_arrToAttrsObj($attrs);
		$this->output[] = $obj;
	}

	function _tagBody($parser, $body) {
		$this->output[count($this->output) - 1]->body .= $body;
	}

	function _tagClosed($parser, $node_name) {
		$node_name = strtolower($node_name);
		$cur_obj = array_pop($this->output);
		$parent_obj = &$this->output[count($this->output) - 1];
		if($this->lang && $cur_obj->attrs->{'xml:lang'} && $cur_obj->attrs->{'xml:lang'} != $this->lang) return;
		if($this->lang && $parent_obj->{$node_name}->attrs->{'xml:lang'} && $parent_obj->{$node_name}->attrs->{'xml:lang'} != $this->lang) return;
		if(isset($parent_obj->{$node_name})) {
			$tmp_obj = $parent_obj->{$node_name};
			if(is_array($tmp_obj)) $parent_obj->{$node_name}[] = $cur_obj;
			else $parent_obj->{$node_name} = array($tmp_obj, $cur_obj);
		} else {
			if(!is_object($parent_obj)) $parent_obj = (object) $parent_obj;
			$parent_obj->{$node_name} = $cur_obj;
		}
	}

	function _arrToAttrsObj($arr) {
		$output = new Xml_Node_();
		foreach($arr as $key => $val) {
			$key = strtolower($key);
			$output->{$key} = $val;
		}
		return $output;
	}
}
