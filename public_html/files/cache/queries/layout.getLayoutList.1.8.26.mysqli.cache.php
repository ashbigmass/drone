<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getLayoutList");
$query->setAction("select");
$query->setPriority("");

${'site_srl6_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl6_argument'}->checkFilter('number');
${'site_srl6_argument'}->ensureDefaultValue('0');
${'site_srl6_argument'}->checkNotNull();
${'site_srl6_argument'}->createConditionValue();
if(!${'site_srl6_argument'}->isValid()) return ${'site_srl6_argument'}->getErrorMessage();
if(${'site_srl6_argument'} !== null) ${'site_srl6_argument'}->setColumnType('number');

${'layout_type7_argument'} = new ConditionArgument('layout_type', $args->layout_type, 'equal');
${'layout_type7_argument'}->ensureDefaultValue('P');
${'layout_type7_argument'}->createConditionValue();
if(!${'layout_type7_argument'}->isValid()) return ${'layout_type7_argument'}->getErrorMessage();
if(${'layout_type7_argument'} !== null) ${'layout_type7_argument'}->setColumnType('char');
if(isset($args->layout)) {
${'layout8_argument'} = new ConditionArgument('layout', $args->layout, 'equal');
${'layout8_argument'}->createConditionValue();
if(!${'layout8_argument'}->isValid()) return ${'layout8_argument'}->getErrorMessage();
} else
${'layout8_argument'} = NULL;if(${'layout8_argument'} !== null) ${'layout8_argument'}->setColumnType('varchar');

${'sort_index9_argument'} = new Argument('sort_index', $args->{'sort_index'});
${'sort_index9_argument'}->ensureDefaultValue('layout_srl');
if(!${'sort_index9_argument'}->isValid()) return ${'sort_index9_argument'}->getErrorMessage();

$query->setColumns(array(
new StarExpression()
));
$query->setTables(array(
new Table('`drcs_layouts`', '`layouts`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`site_srl`',$site_srl6_argument,"equal")
,new ConditionWithArgument('`layout_type`',$layout_type7_argument,"equal", 'and')
,new ConditionWithArgument('`layout`',$layout8_argument,"equal", 'and')))
));
$query->setGroups(array());
$query->setOrder(array(
new OrderByColumn(${'sort_index9_argument'}, "desc")
));
$query->setLimit();
return $query; ?>