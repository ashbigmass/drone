<?php
class HTMLPurifier_ChildDef_Custom extends HTMLPurifier_ChildDef
{
	public $type = 'custom';
	public $allow_empty = false;
	public $dtd_regex;
	private $_pcre_regex;

	public function __construct($dtd_regex) {
		$this->dtd_regex = $dtd_regex;
		$this->_compileRegex();
	}

	protected function _compileRegex() {
		$raw = str_replace(' ', '', $this->dtd_regex);
		if ($raw{0} != '(') $raw = "($raw)";
		$el = '[#a-zA-Z0-9_.-]+';
		$reg = $raw;
		preg_match_all("/$el/", $reg, $matches);
		foreach ($matches[0] as $match) $this->elements[$match] = true;
		$reg = preg_replace("/$el/", '(,\\0)', $reg);
		$reg = preg_replace("/([^,(|]\(+),/", '\\1', $reg);
		$reg = preg_replace("/,\(/", '(', $reg);
		$this->_pcre_regex = $reg;
	}

	public function validateChildren($tokens_of_children, $config, $context) {
		$list_of_children = '';
		$nesting = 0;
		foreach ($tokens_of_children as $token) {
			if (!empty($token->is_whitespace)) continue;
			$is_child = ($nesting == 0);
			if ($token instanceof HTMLPurifier_Token_Start) $nesting++;
			elseif ($token instanceof HTMLPurifier_Token_End) $nesting--;
			if ($is_child) $list_of_children .= $token->name . ',';
		}
		$list_of_children = ',' . rtrim($list_of_children, ',');
		$okay = preg_match('/^,?'.$this->_pcre_regex.'$/', $list_of_children);
		return (bool) $okay;
	}
}
