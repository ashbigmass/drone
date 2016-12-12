<?php
class HTMLPurifier_ChildDef_Optional extends HTMLPurifier_ChildDef_Required
{
	public $allow_empty = true;
	public $type = 'optional';
	public function validateChildren($tokens_of_children, $config, $context) {
		$result = parent::validateChildren($tokens_of_children, $config, $context);
		if ($result === false) {
			if (empty($tokens_of_children)) return true;
			elseif ($this->whitespace) return $tokens_of_children;
			else return array();
		}
		return $result;
	}
}
