<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getNewestCommentList");
$query->setAction("select");
$query->setPriority("");
if(isset($args->status)) {
${'status40_argument'} = new ConditionArgument('status', $args->status, 'equal');
${'status40_argument'}->createConditionValue();
if(!${'status40_argument'}->isValid()) return ${'status40_argument'}->getErrorMessage();
} else
${'status40_argument'} = NULL;if(${'status40_argument'} !== null) ${'status40_argument'}->setColumnType('number');
if(isset($args->module_srl)) {
${'module_srl41_argument'} = new ConditionArgument('module_srl', $args->module_srl, 'in');
${'module_srl41_argument'}->checkFilter('number');
${'module_srl41_argument'}->createConditionValue();
if(!${'module_srl41_argument'}->isValid()) return ${'module_srl41_argument'}->getErrorMessage();
} else
${'module_srl41_argument'} = NULL;if(${'module_srl41_argument'} !== null) ${'module_srl41_argument'}->setColumnType('number');
if(isset($args->document_srl)) {
${'document_srl42_argument'} = new ConditionArgument('document_srl', $args->document_srl, 'equal');
${'document_srl42_argument'}->checkFilter('number');
${'document_srl42_argument'}->createConditionValue();
if(!${'document_srl42_argument'}->isValid()) return ${'document_srl42_argument'}->getErrorMessage();
} else
${'document_srl42_argument'} = NULL;if(${'document_srl42_argument'} !== null) ${'document_srl42_argument'}->setColumnType('number');

${'list_count44_argument'} = new Argument('list_count', $args->{'list_count'});
${'list_count44_argument'}->ensureDefaultValue('20');
if(!${'list_count44_argument'}->isValid()) return ${'list_count44_argument'}->getErrorMessage();

${'sort_index43_argument'} = new Argument('sort_index', $args->{'sort_index'});
${'sort_index43_argument'}->ensureDefaultValue('list_order');
if(!${'sort_index43_argument'}->isValid()) return ${'sort_index43_argument'}->getErrorMessage();

$query->setColumns(array(
new StarExpression()
));
$query->setTables(array(
new Table('`drcs_comments`', '`comments`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`status`',$status40_argument,"equal", 'and')
,new ConditionWithArgument('`module_srl`',$module_srl41_argument,"in", 'and')
,new ConditionWithArgument('`document_srl`',$document_srl42_argument,"equal", 'and')))
));
$query->setGroups(array());
$query->setOrder(array(
new OrderByColumn(${'sort_index43_argument'}, "asc")
));
$query->setLimit(new Limit(${'list_count44_argument'}));
return $query; ?>