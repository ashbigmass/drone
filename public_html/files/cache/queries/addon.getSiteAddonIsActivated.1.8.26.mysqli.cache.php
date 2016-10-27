<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getSiteAddonIsActivated");
$query->setAction("select");
$query->setPriority("");

${'site_srl45_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl45_argument'}->checkNotNull();
${'site_srl45_argument'}->createConditionValue();
if(!${'site_srl45_argument'}->isValid()) return ${'site_srl45_argument'}->getErrorMessage();
if(${'site_srl45_argument'} !== null) ${'site_srl45_argument'}->setColumnType('number');

${'addon46_argument'} = new ConditionArgument('addon', $args->addon, 'equal');
${'addon46_argument'}->checkNotNull();
${'addon46_argument'}->createConditionValue();
if(!${'addon46_argument'}->isValid()) return ${'addon46_argument'}->getErrorMessage();
if(${'addon46_argument'} !== null) ${'addon46_argument'}->setColumnType('varchar');

$query->setColumns(array(
new SelectExpression('count(*)', '`count`')
));
$query->setTables(array(
new Table('`drcs_addons_site`', '`addons_site`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`site_srl`',$site_srl45_argument,"equal")
,new ConditionWithArgument('`addon`',$addon46_argument,"equal", 'and')
,new ConditionWithoutArgument('`is_used`',"'Y'","equal", 'and')))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>