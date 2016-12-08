<?php
class StarExpression extends SelectExpression
{
	function StarExpression() {
		parent::SelectExpression("*");
	}

	function getArgument() {
		return null;
	}

	function getArguments() {
		return array();
	}
}
