<?php
class HTMLPurifier_ChildDef_Chameleon extends HTMLPurifier_ChildDef
{
	public $inline;
	public $block;
	public $type = 'chameleon';

	public function __construct($inline, $block) {
		$this->inline = new HTMLPurifier_ChildDef_Optional($inline);
		$this->block  = new HTMLPurifier_ChildDef_Optional($block);
		$this->elements = $this->block->elements;
	}

	public function validateChildren($tokens_of_children, $config, $context) {
		if ($context->get('IsInline') === false) {
			return $this->block->validateChildren($tokens_of_children, $config, $context);
		} else {
			return $this->inline->validateChildren($tokens_of_children, $config, $context);
		}
	}
}
