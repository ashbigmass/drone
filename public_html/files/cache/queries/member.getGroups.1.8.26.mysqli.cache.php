<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getGroups");
$query->setAction("select");
$query->setPriority("");
if(isset($args->site_srl)) {
${'site_srl10_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl10_argument'}->createConditionValue();
if(!${'site_srl10_argument'}->isValid()) return ${'site_srl10_argument'}->getErrorMessage();
} else
${'site_srl10_argument'} = NULL;if(${'site_srl10_argument'} !== null) ${'site_srl10_argument'}->setColumnType('number');

${'sort_index11_argument'} = new Argument('sort_index', $args->{'sort_index'});
${'sort_index11_argument'}->ensureDefaultValue('list_order');
if(!${'sort_index11_argument'}->isValid()) return ${'sort_index11_argument'}->getErrorMessage();

${'order_type12_argument'} = new SortArgument('order_type12', $args->order_type);
${'order_type12_argument'}->ensureDefaultValue('asc');
if(!${'order_type12_argument'}->isValid()) return ${'order_type12_argument'}->getErrorMessage();

$query->setColumns(array(
new StarExpression()
));
$query->setTables(array(
new Table('`drcs_member_group`', '`member_group`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`site_srl`',$site_srl10_argument,"equal")))
));
$query->setGroups(array());
$query->setOrder(array(
new OrderByColumn(${'sort_index11_argument'}, $order_type12_argument)
));
$query->setLimit();
return $query; ?>