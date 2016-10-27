<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getModulePartConfig");
$query->setAction("select");
$query->setPriority("");

${'module19_argument'} = new ConditionArgument('module', $args->module, 'equal');
${'module19_argument'}->checkNotNull();
${'module19_argument'}->createConditionValue();
if(!${'module19_argument'}->isValid()) return ${'module19_argument'}->getErrorMessage();
if(${'module19_argument'} !== null) ${'module19_argument'}->setColumnType('varchar');

${'module_srl20_argument'} = new ConditionArgument('module_srl', $args->module_srl, 'equal');
${'module_srl20_argument'}->checkNotNull();
${'module_srl20_argument'}->createConditionValue();
if(!${'module_srl20_argument'}->isValid()) return ${'module_srl20_argument'}->getErrorMessage();
if(${'module_srl20_argument'} !== null) ${'module_srl20_argument'}->setColumnType('number');

$query->setColumns(array(
new SelectExpression('`config`')
));
$query->setTables(array(
new Table('`drcs_module_part_config`', '`module_part_config`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`module`',$module19_argument,"equal")
,new ConditionWithArgument('`module_srl`',$module_srl20_argument,"equal", 'and')))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>