<?php
class HTMLPurifier_Token_Comment extends HTMLPurifier_Token
{
	public $data;
	public $is_whitespace = true;

	public function __construct($data, $line = null, $col = null) {
		$this->data = $data;
		$this->line = $line;
		$this->col  = $col;
	}
}
