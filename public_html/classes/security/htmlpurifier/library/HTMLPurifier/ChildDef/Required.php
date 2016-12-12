<?php
class HTMLPurifier_ChildDef_Required extends HTMLPurifier_ChildDef
{
	public $elements = array();
	protected $whitespace = false;

	public function __construct($elements) {
		if (is_string($elements)) {
			$elements = str_replace(' ', '', $elements);
			$elements = explode('|', $elements);
		}
		$keys = array_keys($elements);
		if ($keys == array_keys($keys)) {
			$elements = array_flip($elements);
			foreach ($elements as $i => $x) {
				$elements[$i] = true;
				if (empty($i)) unset($elements[$i]);
			}
		}
		$this->elements = $elements;
	}
	public $allow_empty = false;
	public $type = 'required';

	public function validateChildren($tokens_of_children, $config, $context) {
		$this->whitespace = false;
		if (empty($tokens_of_children)) return false;
		$result = array();
		$nesting = 0;
		$is_deleting = false;
		$pcdata_allowed = isset($this->elements['#PCDATA']);
		$all_whitespace = true;
		$escape_invalid_children = $config->get('Core.EscapeInvalidChildren');
		$gen = new HTMLPurifier_Generator($config, $context);
		foreach ($tokens_of_children as $token) {
			if (!empty($token->is_whitespace)) {
				$result[] = $token;
				continue;
			}
			$all_whitespace = false;
			$is_child = ($nesting == 0);
			if ($token instanceof HTMLPurifier_Token_Start) $nesting++;
			elseif ($token instanceof HTMLPurifier_Token_End) $nesting--;
			if ($is_child) {
				$is_deleting = false;
				if (!isset($this->elements[$token->name])) {
					$is_deleting = true;
					if ($pcdata_allowed && $token instanceof HTMLPurifier_Token_Text) {
						$result[] = $token;
					} elseif ($pcdata_allowed && $escape_invalid_children) {
						$result[] = new HTMLPurifier_Token_Text($gen->generateFromToken($token));
					}
					continue;
				}
			}
			if (!$is_deleting || ($pcdata_allowed && $token instanceof HTMLPurifier_Token_Text)) {
				$result[] = $token;
			} elseif ($pcdata_allowed && $escape_invalid_children) {
				$result[] = new HTMLPurifier_Token_Text($gen->generateFromToken($token));
			} else {
			}
		}
		if (empty($result)) return false;
		if ($all_whitespace) {
			$this->whitespace = true;
			return false;
		}
		if ($tokens_of_children == $result) return true;
		return $result;
	}
}
