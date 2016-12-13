<?php
class HTMLPurifier_Token_Text extends HTMLPurifier_Token
{
	public $name = '#PCDATA';
	public $data;
	public $is_whitespace;

	public function __construct($data, $line = null, $col = null) {
		$this->data = $data;
		$this->is_whitespace = ctype_space($data);
		$this->line = $line;
		$this->col  = $col;
	}
}
