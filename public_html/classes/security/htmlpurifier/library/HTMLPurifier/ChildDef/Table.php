<?php
class HTMLPurifier_ChildDef_Table extends HTMLPurifier_ChildDef
{
	public $allow_empty = false;
	public $type = 'table';
	public $elements = array('tr' => true, 'tbody' => true, 'thead' => true, 'tfoot' => true, 'caption' => true, 'colgroup' => true, 'col' => true);
	public function __construct() {}

	public function validateChildren($tokens_of_children, $config, $context) {
		if (empty($tokens_of_children)) return false;
		$tokens_of_children[] = false;
		$caption = false;
		$thead   = false;
		$tfoot   = false;
		$cols	= array();
		$content = array();
		$nesting = 0;
		$is_collecting = false;
		$collection = array();
		$tag_index = 0;
		$tbody_mode = false;
		foreach ($tokens_of_children as $token) {
			$is_child = ($nesting == 0);
			if ($token === false) {
			} elseif ($token instanceof HTMLPurifier_Token_Start)
				$nesting++;
			} elseif ($token instanceof HTMLPurifier_Token_End) {
				$nesting--;
			}
			if ($is_collecting) {
				if ($is_child) {
					switch ($collection[$tag_index]->name) {
						case 'tbody':
							$tbody_mode = true;
						case 'tr':
							$content[] = $collection;
							break;
						case 'caption':
							if ($caption !== false) break;
							$caption = $collection;
							break;
						case 'thead':
						case 'tfoot':
							$tbody_mode = true;
							$var = $collection[$tag_index]->name;
							if ($$var === false) {
								$$var = $collection;
							} else {
								$collection[$tag_index]->name = 'tbody';
								$collection[count($collection)-1]->name = 'tbody';
								$content[] = $collection;
							}
							break;
						 case 'colgroup':
							$cols[] = $collection;
							break;
					}
					$collection = array();
					$is_collecting = false;
					$tag_index = 0;
				} else {
					$collection[] = $token;
				}
			}
			if ($token === false) break;
			if ($is_child) {
				if ($token->name == 'col') {
					$cols[] = array_merge($collection, array($token));
					$collection = array();
					$tag_index = 0;
					continue;
				}
				switch($token->name) {
					case 'caption':
					case 'colgroup':
					case 'thead':
					case 'tfoot':
					case 'tbody':
					case 'tr':
						$is_collecting = true;
						$collection[] = $token;
						continue;
					default:
						if (!empty($token->is_whitespace)) {
							$collection[] = $token;
							$tag_index++;
						}
						continue;
				}
			}
		}
		if (empty($content)) return false;
		$ret = array();
		if ($caption !== false) $ret = array_merge($ret, $caption);
		if ($cols !== false)	foreach ($cols as $token_array) $ret = array_merge($ret, $token_array);
		if ($thead !== false)   $ret = array_merge($ret, $thead);
		if ($tfoot !== false)   $ret = array_merge($ret, $tfoot);
		if ($tbody_mode) {
			$inside_tbody = false;
			foreach ($content as $token_array) {
				foreach ($token_array as $t) if ($t->name === 'tr' || $t->name === 'tbody') break;
				if ($t->name === 'tr') {
					if ($inside_tbody) {
						$ret = array_merge($ret, $token_array);
					} else {
						$ret[] = new HTMLPurifier_Token_Start('tbody');
						$ret = array_merge($ret, $token_array);
						$inside_tbody = true;
					}
				} elseif ($t->name === 'tbody') {
					if ($inside_tbody) {
						$ret[] = new HTMLPurifier_Token_End('tbody');
						$inside_tbody = false;
						$ret = array_merge($ret, $token_array);
					} else {
						$ret = array_merge($ret, $token_array);
					}
				} else {
					trigger_error("tr/tbody in content invariant failed in Table ChildDef", E_USER_ERROR);
				}
			}
			if ($inside_tbody) $ret[] = new HTMLPurifier_Token_End('tbody');
		} else {
			foreach ($content as $token_array) $ret = array_merge($ret, $token_array);
		}
		if (!empty($collection) && $is_collecting == false) $ret = array_merge($ret, $collection);
		array_pop($tokens_of_children);
		return ($ret === $tokens_of_children) ? true : $ret;
	}
}
