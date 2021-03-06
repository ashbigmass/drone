<?php
class IndexTag
{
	var $argument_name;
	var $argument;
	var $default;
	var $sort_order;
	var $sort_order_argument;

	function IndexTag($index) {
		$this->argument_name = $index->attrs->var;
		$this->default = $index->attrs->default;
		$this->argument = new QueryArgument($index);
		$this->sort_order = $index->attrs->order;
		$sortList = array('asc' => 1, 'desc' => 1);
		if(!isset($sortList[$this->sort_order])) {
			$arg = new Xml_Node_();
			$arg->attrs = new Xml_Node_();
			$arg->attrs->var = $this->sort_order;
			$arg->attrs->default = 'asc';
			$this->sort_order_argument = new SortQueryArgument($arg);
			$this->sort_order = '$' . $this->sort_order_argument->getArgumentName() . '_argument';
		} else {
			$this->sort_order = '"' . $this->sort_order . '"';
		}
	}

	function toString() {
		return sprintf('new OrderByColumn(${\'%s_argument\'}, %s)', $this->argument->getArgumentName(), $this->sort_order);
	}

	function getArguments() {
		$arguments = array();
		$arguments[] = $this->argument;
		if($this->sort_order_argument) $arguments[] = $this->sort_order_argument;
		return $arguments;
	}
}
