<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getLayout");
$query->setAction("select");
$query->setPriority("");

${'layout_srl18_argument'} = new ConditionArgument('layout_srl', $args->layout_srl, 'equal');
${'layout_srl18_argument'}->checkFilter('number');
${'layout_srl18_argument'}->checkNotNull();
${'layout_srl18_argument'}->createConditionValue();
if(!${'layout_srl18_argument'}->isValid()) return ${'layout_srl18_argument'}->getErrorMessage();
if(${'layout_srl18_argument'} !== null) ${'layout_srl18_argument'}->setColumnType('number');

$query->setColumns(array(
new StarExpression()
));
$query->setTables(array(
new Table('`drcs_layouts`', '`layouts`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`layout_srl`',$layout_srl18_argument,"equal")))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>