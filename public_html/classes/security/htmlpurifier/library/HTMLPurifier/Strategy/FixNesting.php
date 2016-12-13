<?php
class HTMLPurifier_Strategy_FixNesting extends HTMLPurifier_Strategy
{
	public function execute($tokens, $config, $context) {
		$definition = $config->getHTMLDefinition();
		$parent_name = $definition->info_parent;
		array_unshift($tokens, new HTMLPurifier_Token_Start($parent_name));
		$tokens[] = new HTMLPurifier_Token_End($parent_name);
		$is_inline = $definition->info_parent_def->descendants_are_inline;
		$context->register('IsInline', $is_inline);
		$e =& $context->get('ErrorCollector', true);
		$stack = array();
		$exclude_stack = array();
		$start_token = false;
		$context->register('CurrentToken', $start_token);
		for ($i = 0, $size = count($tokens) ; $i < $size; ) {
			$child_tokens = array();
			for ($j = $i, $depth = 0; ; $j++) {
				if ($tokens[$j] instanceof HTMLPurifier_Token_Start) {
					$depth++;
					if ($depth == 1) continue;
				} elseif ($tokens[$j] instanceof HTMLPurifier_Token_End) {
					$depth--;
					if ($depth == 0) break;
				}
				$child_tokens[] = $tokens[$j];
			}
			$start_token = $tokens[$i];
			if ($count = count($stack)) {
				$parent_index = $stack[$count-1];
				$parent_name  = $tokens[$parent_index]->name;
				if ($parent_index == 0) $parent_def   = $definition->info_parent_def;
				else $parent_def   = $definition->info[$parent_name];
			} else {
				$parent_index = $parent_name = $parent_def = null;
			}
			if ($is_inline === false) {
				if (!empty($parent_def) && $parent_def->descendants_are_inline) $is_inline = $count - 1;
			} else {
				if ($count === $is_inline) $is_inline = false;
			}
			$excluded = false;
			if (!empty($exclude_stack)) {
				foreach ($exclude_stack as $lookup) {
					if (isset($lookup[$tokens[$i]->name])) {
						$excluded = true;
						break;
					}
				}
			}
			if ($excluded) {
				$result = false;
				$excludes = array();
			} else {
				if ($i === 0) $def = $definition->info_parent_def;
				else $def = $definition->info[$tokens[$i]->name];
				if (!empty($def->child)) $result = $def->child->validateChildren($child_tokens, $config, $context);
				else $result = false;
				$excludes = $def->excludes;
			}
			if ($result === true || $child_tokens === $result) {
				$stack[] = $i;
				if (!empty($excludes)) $exclude_stack[] = $excludes;
				$i++;
			} elseif($result === false) {
				if ($e) {
					if ($excluded) $e->send(E_ERROR, 'Strategy_FixNesting: Node excluded');
					else $e->send(E_ERROR, 'Strategy_FixNesting: Node removed');
				}
				$length = $j - $i + 1;
				array_splice($tokens, $i, $length);
				$size -= $length;
				if (!$parent_def->child->allow_empty) {
					$i = $parent_index;
					array_pop($stack);
				}
			} else {
				$length = $j - $i - 1;
				if ($e) {
					if (empty($result) && $length) $e->send(E_ERROR, 'Strategy_FixNesting: Node contents removed');
					else $e->send(E_WARNING, 'Strategy_FixNesting: Node reorganized');
				}
				array_splice($tokens, $i + 1, $length, $result);
				$size -= $length;
				$size += count($result);
				$stack[] = $i;
				if (!empty($excludes)) $exclude_stack[] = $excludes;
				$i++;
			}
			$size = count($tokens);
			while ($i < $size and !$tokens[$i] instanceof HTMLPurifier_Token_Start) {
				if ($tokens[$i] instanceof HTMLPurifier_Token_End) {
					array_pop($stack);
					if ($i == 0 || $i == $size - 1) $s_excludes = $definition->info_parent_def->excludes;
					else $s_excludes = $definition->info[$tokens[$i]->name]->excludes;
					if ($s_excludes) array_pop($exclude_stack);
				}
				$i++;
			}
		}
		array_shift($tokens);
		array_pop($tokens);
		$context->destroy('IsInline');
		$context->destroy('CurrentToken');
		return $tokens;
	}
}
