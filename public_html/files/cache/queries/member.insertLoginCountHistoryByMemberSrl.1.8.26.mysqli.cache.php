<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("insertLoginCountHistoryByMemberSrl");
$query->setAction("insert");
$query->setPriority("");

${'member_srl4_argument'} = new Argument('member_srl', $args->{'member_srl'});
${'member_srl4_argument'}->checkNotNull();
if(!${'member_srl4_argument'}->isValid()) return ${'member_srl4_argument'}->getErrorMessage();
if(${'member_srl4_argument'} !== null) ${'member_srl4_argument'}->setColumnType('number');

${'content5_argument'} = new Argument('content', $args->{'content'});
${'content5_argument'}->checkNotNull();
if(!${'content5_argument'}->isValid()) return ${'content5_argument'}->getErrorMessage();
if(${'content5_argument'} !== null) ${'content5_argument'}->setColumnType('bigtext');

${'last_update6_argument'} = new Argument('last_update', $args->{'last_update'});
${'last_update6_argument'}->ensureDefaultValue(date("YmdHis"));
if(!${'last_update6_argument'}->isValid()) return ${'last_update6_argument'}->getErrorMessage();
if(${'last_update6_argument'} !== null) ${'last_update6_argument'}->setColumnType('date');

$query->setColumns(array(
new InsertExpression('`member_srl`', ${'member_srl4_argument'})
,new InsertExpression('`content`', ${'content5_argument'})
,new InsertExpression('`last_update`', ${'last_update6_argument'})
));
$query->setTables(array(
new Table('`drcs_member_count_history`', '`member_count_history`')
));
$query->setConditions(array());
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>