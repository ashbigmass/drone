<?php
abstract class HTMLPurifier_ChildDef
{
	public $type;
	public $allow_empty;
	public $elements = array();

	public function getAllowedElements($config) {
		return $this->elements;
	}

	abstract public function validateChildren($tokens_of_children, $config, $context);
}
