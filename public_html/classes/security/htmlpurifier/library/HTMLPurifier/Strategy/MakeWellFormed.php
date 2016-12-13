<?php
class HTMLPurifier_Strategy_MakeWellFormed extends HTMLPurifier_Strategy
{
	protected $tokens;
	protected $t;
	protected $stack;
	protected $injectors;
	protected $config;
	protected $context;

	public function execute($tokens, $config, $context) {
		$definition = $config->getHTMLDefinition();
		$generator = new HTMLPurifier_Generator($config, $context);
		$escape_invalid_tags = $config->get('Core.EscapeInvalidTags');
		$global_parent_allowed_elements = array();
		if (isset($definition->info[$definition->info_parent])) {
			$global_parent_allowed_elements = $definition->info[$definition->info_parent]->child->getAllowedElements($config);
		}
		$e = $context->get('ErrorCollector', true);
		$t = false;
		$i = false;
		$token	  = false;
		$reprocess  = false;
		$stack = array();
		$this->stack   =& $stack;
		$this->t	   =& $t;
		$this->tokens  =& $tokens;
		$this->config  = $config;
		$this->context = $context;
		$context->register('CurrentNesting', $stack);
		$context->register('InputIndex',	 $t);
		$context->register('InputTokens',	$tokens);
		$context->register('CurrentToken',   $token);
		$this->injectors = array();
		$injectors = $config->getBatch('AutoFormat');
		$def_injectors = $definition->info_injector;
		$custom_injectors = $injectors['Custom'];
		unset($injectors['Custom']);
		foreach ($injectors as $injector => $b) {
			if (strpos($injector, '.') !== false) continue;
			$injector = "HTMLPurifier_Injector_$injector";
			if (!$b) continue;
			$this->injectors[] = new $injector;
		}
		foreach ($def_injectors as $injector) $this->injectors[] = $injector;
		foreach ($custom_injectors as $injector) {
			if (!$injector) continue;
			if (is_string($injector)) {
				$injector = "HTMLPurifier_Injector_$injector";
				$injector = new $injector;
			}
			$this->injectors[] = $injector;
		}
		foreach ($this->injectors as $ix => $injector) {
			$error = $injector->prepare($config, $context);
			if (!$error) continue;
			array_splice($this->injectors, $ix, 1);
			trigger_error("Cannot enable {$injector->name} injector because $error is not allowed", E_USER_WARNING);
		}
		for ($t = 0; $t == 0 || isset($tokens[$t - 1]); $reprocess ? $reprocess = false : $t++) {
			if (is_int($i) && $i >= 0) {
				$rewind_to = $this->injectors[$i]->getRewind();
				if (is_int($rewind_to) && $rewind_to < $t) {
					if ($rewind_to < 0) $rewind_to = 0;
					while ($t > $rewind_to) {
						$t--;
						$prev = $tokens[$t];
						unset($prev->skip[$i]);
						$prev->rewind = $i;
						if ($prev instanceof HTMLPurifier_Token_Start) array_pop($this->stack);
						elseif ($prev instanceof HTMLPurifier_Token_End) $this->stack[] = $prev->start;
					}
				}
				$i = false;
			}
			if (!isset($tokens[$t])) {
				if (empty($this->stack)) break;
				$top_nesting = array_pop($this->stack);
				$this->stack[] = $top_nesting;
				if ($e && !isset($top_nesting->armor['MakeWellFormed_TagClosedError'])) {
					$e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag closed by document end', $top_nesting);
				}
				$tokens[] = new HTMLPurifier_Token_End($top_nesting->name);
				$reprocess = true;
				continue;
			}
			$token = $tokens[$t];
			if (empty($token->is_tag)) {
				if ($token instanceof HTMLPurifier_Token_Text) {
					foreach ($this->injectors as $i => $injector) {
						if (isset($token->skip[$i])) continue;
						if ($token->rewind !== null && $token->rewind !== $i) continue;
						$injector->handleText($token);
						$this->processToken($token, $i);
						$reprocess = true;
						break;
					}
				}
				continue;
			}
			if (isset($definition->info[$token->name])) $type = $definition->info[$token->name]->child->type;
			else $type = false;
			$ok = false;
			if ($type === 'empty' && $token instanceof HTMLPurifier_Token_Start) {
				$token = new HTMLPurifier_Token_Empty($token->name, $token->attr, $token->line, $token->col, $token->armor);
				$ok = true;
			} elseif ($type && $type !== 'empty' && $token instanceof HTMLPurifier_Token_Empty) {
				$this->swap(new HTMLPurifier_Token_End($token->name));
				$this->insertBefore(new HTMLPurifier_Token_Start($token->name, $token->attr, $token->line, $token->col, $token->armor));
				$reprocess = true;
				continue;
			} elseif ($token instanceof HTMLPurifier_Token_Empty) {
				$ok = true;
			} elseif ($token instanceof HTMLPurifier_Token_Start) {
				if (!empty($this->stack)) {
					$parent = array_pop($this->stack);
					$this->stack[] = $parent;
					if (isset($definition->info[$parent->name])) {
						$elements = $definition->info[$parent->name]->child->getAllowedElements($config);
						$autoclose = !isset($elements[$token->name]);
					} else {
						$autoclose = false;
					}
					if ($autoclose && $definition->info[$token->name]->wrap) {
						$wrapname = $definition->info[$token->name]->wrap;
						$wrapdef = $definition->info[$wrapname];
						$elements = $wrapdef->child->getAllowedElements($config);
						$parent_elements = $definition->info[$parent->name]->child->getAllowedElements($config);
						if (isset($elements[$token->name]) && isset($parent_elements[$wrapname])) {
							$newtoken = new HTMLPurifier_Token_Start($wrapname);
							$this->insertBefore($newtoken);
							$reprocess = true;
							continue;
						}
					}
					$carryover = false;
					if ($autoclose && $definition->info[$parent->name]->formatting) $carryover = true;
					if ($autoclose) {
						$autoclose_ok = isset($global_parent_allowed_elements[$token->name]);
						if (!$autoclose_ok) {
							foreach ($this->stack as $ancestor) {
								$elements = $definition->info[$ancestor->name]->child->getAllowedElements($config);
								if (isset($elements[$token->name])) {
									$autoclose_ok = true;
									break;
								}
								if ($definition->info[$token->name]->wrap) {
									$wrapname = $definition->info[$token->name]->wrap;
									$wrapdef = $definition->info[$wrapname];
									$wrap_elements = $wrapdef->child->getAllowedElements($config);
									if (isset($wrap_elements[$token->name]) && isset($elements[$wrapname])) {
										$autoclose_ok = true;
										break;
									}
								}
							}
						}
						if ($autoclose_ok) {
							$new_token = new HTMLPurifier_Token_End($parent->name);
							$new_token->start = $parent;
							if ($carryover) {
								$element = clone $parent;
								$element->armor['MakeWellFormed_TagClosedError'] = true;
								$element->carryover = true;
								$this->processToken(array($new_token, $token, $element));
							} else {
								$this->insertBefore($new_token);
							}
							if ($e && !isset($parent->armor['MakeWellFormed_TagClosedError'])) {
								if (!$carryover) $e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag auto closed', $parent);
								else $e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag carryover', $parent);
							}
						} else {
							$this->remove();
						}
						$reprocess = true;
						continue;
					}
				}
				$ok = true;
			}
			if ($ok) {
				foreach ($this->injectors as $i => $injector) {
					if (isset($token->skip[$i])) continue;
					if ($token->rewind !== null && $token->rewind !== $i) continue;
					$injector->handleElement($token);
					$this->processToken($token, $i);
					$reprocess = true;
					break;
				}
				if (!$reprocess) {
					$this->swap($token);
					if ($token instanceof HTMLPurifier_Token_Start) {
						$this->stack[] = $token;
					} elseif ($token instanceof HTMLPurifier_Token_End) {
						throw new HTMLPurifier_Exception('Improper handling of end tag in start code; possible error in MakeWellFormed');
					}
				}
				continue;
			}
			if (!$token instanceof HTMLPurifier_Token_End) {
				throw new HTMLPurifier_Exception('Unaccounted for tag token in input stream, bug in HTML Purifier');
			}
			if (empty($this->stack)) {
				if ($escape_invalid_tags) {
					if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Unnecessary end tag to text');
					$this->swap(new HTMLPurifier_Token_Text(
						$generator->generateFromToken($token)
					));
				} else {
					$this->remove();
					if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Unnecessary end tag removed');
				}
				$reprocess = true;
				continue;
			}
			$current_parent = array_pop($this->stack);
			if ($current_parent->name == $token->name) {
				$token->start = $current_parent;
				foreach ($this->injectors as $i => $injector) {
					if (isset($token->skip[$i])) continue;
					if ($token->rewind !== null && $token->rewind !== $i) continue;
					$injector->handleEnd($token);
					$this->processToken($token, $i);
					$this->stack[] = $current_parent;
					$reprocess = true;
					break;
				}
				continue;
			}
			$this->stack[] = $current_parent;
			$size = count($this->stack);
			$skipped_tags = false;
			for ($j = $size - 2; $j >= 0; $j--) {
				if ($this->stack[$j]->name == $token->name) {
					$skipped_tags = array_slice($this->stack, $j);
					break;
				}
			}
			if ($skipped_tags === false) {
				if ($escape_invalid_tags) {
					$this->swap(new HTMLPurifier_Token_Text($generator->generateFromToken($token)));
					if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Stray end tag to text');
				} else {
					$this->remove();
					if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Stray end tag removed');
				}
				$reprocess = true;
				continue;
			}
			$c = count($skipped_tags);
			if ($e) {
				for ($j = $c - 1; $j > 0; $j--) {
					if (!isset($skipped_tags[$j]->armor['MakeWellFormed_TagClosedError'])) {
						$e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag closed by element end', $skipped_tags[$j]);
					}
				}
			}
			$replace = array($token);
			for ($j = 1; $j < $c; $j++) {
				$new_token = new HTMLPurifier_Token_End($skipped_tags[$j]->name);
				$new_token->start = $skipped_tags[$j];
				array_unshift($replace, $new_token);
				if (isset($definition->info[$new_token->name]) && $definition->info[$new_token->name]->formatting) {
					$element = clone $skipped_tags[$j];
					$element->carryover = true;
					$element->armor['MakeWellFormed_TagClosedError'] = true;
					$replace[] = $element;
				}
			}
			$this->processToken($replace);
			$reprocess = true;
			continue;
		}
		$context->destroy('CurrentNesting');
		$context->destroy('InputTokens');
		$context->destroy('InputIndex');
		$context->destroy('CurrentToken');
		unset($this->injectors, $this->stack, $this->tokens, $this->t);
		return $tokens;
	}
	protected function processToken($token, $injector = -1) {
		if (is_object($token)) $token = array(1, $token);
		if (is_int($token))	$token = array($token);
		if ($token === false)  $token = array(1);
		if (!is_array($token)) throw new HTMLPurifier_Exception('Invalid token type from injector');
		if (!is_int($token[0])) array_unshift($token, 1);
		if ($token[0] === 0) throw new HTMLPurifier_Exception('Deleting zero tokens is not valid');
		$delete = array_shift($token);
		$old = array_splice($this->tokens, $this->t, $delete, $token);
		if ($injector > -1) {
			$oldskip = isset($old[0]) ? $old[0]->skip : array();
			foreach ($token as $object) {
				$object->skip = $oldskip;
				$object->skip[$injector] = true;
			}
		}

	}

	private function insertBefore($token) {
		array_splice($this->tokens, $this->t, 0, array($token));
	}

	private function remove() {
		array_splice($this->tokens, $this->t, 1);
	}

	private function swap($token) {
		$this->tokens[$this->t] = $token;
	}
}
