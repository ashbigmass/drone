<?php
abstract class HTMLPurifier_Injector
{
	public $name;
	protected $htmlDefinition;
	protected $currentNesting;
	protected $inputTokens;
	protected $inputIndex;
	public $needed = array();
	protected $rewind = false;

	public function rewind($index) {
		$this->rewind = $index;
	}

	public function getRewind() {
		$r = $this->rewind;
		$this->rewind = false;
		return $r;
	}

	public function prepare($config, $context) {
		$this->htmlDefinition = $config->getHTMLDefinition();
		$result = $this->checkNeeded($config);
		if ($result !== false) return $result;
		$this->currentNesting =& $context->get('CurrentNesting');
		$this->inputTokens	=& $context->get('InputTokens');
		$this->inputIndex	 =& $context->get('InputIndex');
		return false;
	}

	public function checkNeeded($config) {
		$def = $config->getHTMLDefinition();
		foreach ($this->needed as $element => $attributes) {
			if (is_int($element)) $element = $attributes;
			if (!isset($def->info[$element])) return $element;
			if (!is_array($attributes)) continue;
			foreach ($attributes as $name) if (!isset($def->info[$element]->attr[$name])) return "$element.$name";
		}
		return false;
	}

	public function allowsElement($name) {
		if (!empty($this->currentNesting)) {
			$parent_token = array_pop($this->currentNesting);
			$this->currentNesting[] = $parent_token;
			$parent = $this->htmlDefinition->info[$parent_token->name];
		} else {
			$parent = $this->htmlDefinition->info_parent_def;
		}
		if (!isset($parent->child->elements[$name]) || isset($parent->excludes[$name])) return false;
		for ($i = count($this->currentNesting) - 2; $i >= 0; $i--) {
			$node = $this->currentNesting[$i];
			$def  = $this->htmlDefinition->info[$node->name];
			if (isset($def->excludes[$name])) return false;
		}
		return true;
	}

	protected function forward(&$i, &$current) {
		if ($i === null) $i = $this->inputIndex + 1;
		else $i++;
		if (!isset($this->inputTokens[$i])) return false;
		$current = $this->inputTokens[$i];
		return true;
	}

	protected function forwardUntilEndToken(&$i, &$current, &$nesting) {
		$result = $this->forward($i, $current);
		if (!$result) return false;
		if ($nesting === null) $nesting = 0;
		if	 ($current instanceof HTMLPurifier_Token_Start) $nesting++;
		elseif ($current instanceof HTMLPurifier_Token_End) {
			if ($nesting <= 0) return false;
			$nesting--;
		}
		return true;
	}

	protected function backward(&$i, &$current) {
		if ($i === null) $i = $this->inputIndex - 1;
		else $i--;
		if ($i < 0) return false;
		$current = $this->inputTokens[$i];
		return true;
	}

	protected function current(&$i, &$current) {
		if ($i === null) $i = $this->inputIndex;
		$current = $this->inputTokens[$i];
	}

	public function handleText(&$token) {}

	public function handleElement(&$token) {}

	public function handleEnd(&$token) {
		$this->notifyEnd($token);
	}

	public function notifyEnd($token) {}
}
