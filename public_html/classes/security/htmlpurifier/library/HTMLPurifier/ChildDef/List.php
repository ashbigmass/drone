<?php
class HTMLPurifier_ChildDef_List extends HTMLPurifier_ChildDef
{
	public $type = 'list';
	public $elements = array('li' => true, 'ul' => true, 'ol' => true);

	public function validateChildren($tokens_of_children, $config, $context) {
		$this->whitespace = false;
		if (empty($tokens_of_children)) return false;
		$result = array();
		$nesting = 0;
		$all_whitespace = true;
		$seen_li = false;
		$need_close_li = false;
		foreach ($tokens_of_children as $token) {
			if (!empty($token->is_whitespace)) {
				$result[] = $token;
				continue;
			}
			$all_whitespace = false;
			if ($nesting == 1 && $need_close_li) {
				$result[] = new HTMLPurifier_Token_End('li');
				$nesting--;
				$need_close_li = false;
			}
			$is_child = ($nesting == 0);
			if ($token instanceof HTMLPurifier_Token_Start) $nesting++;
			elseif ($token instanceof HTMLPurifier_Token_End) $nesting--;
			if ($is_child) {
				if ($token->name === 'li') {
					$seen_li = true;
				} elseif ($token->name === 'ul' || $token->name === 'ol') {
					$need_close_li = true;
					$nesting++;
					if (!$seen_li) {
						$result[] = new HTMLPurifier_Token_Start('li');
					} else {
						while(true) {
							$t = array_pop($result);
							if ($t instanceof HTMLPurifier_Token_End) {
								if ($t->name !== 'li') {
									trigger_error("Only li present invariant violated in List ChildDef", E_USER_ERROR);
									return false;
								}
								break;
							} elseif ($t instanceof HTMLPurifier_Token_Empty) {
								if ($t->name !== 'li') {
									trigger_error("Only li present invariant violated in List ChildDef", E_USER_ERROR);
									return false;
								}
								$result[] = new HTMLPurifier_Token_Start('li', $t->attr, $t->line, $t->col, $t->armor);
								break;
							} else {
								if (!$t->is_whitespace) {
									trigger_error("Only whitespace present invariant violated in List ChildDef", E_USER_ERROR);
									return false;
								}
							}
						}
					}
				} else {
					$result[] = new HTMLPurifier_Token_Start('li');
					$nesting++;
					$seen_li = true;
					$need_close_li = true;
				}
			}
			$result[] = $token;
		}
		if ($need_close_li) $result[] = new HTMLPurifier_Token_End('li');
		if (empty($result)) return false;
		if ($all_whitespace) return false;
		if ($tokens_of_children == $result) return true;
		return $result;
	}
}
