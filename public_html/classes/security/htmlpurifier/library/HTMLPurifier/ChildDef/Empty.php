<?php
class HTMLPurifier_ChildDef_Empty extends HTMLPurifier_ChildDef
{
	public $allow_empty = true;
	public $type = 'empty';
	public function __construct() {}

	public function validateChildren($tokens_of_children, $config, $context) {
		return array();
	}
}
