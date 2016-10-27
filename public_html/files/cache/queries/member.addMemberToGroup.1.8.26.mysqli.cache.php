<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("addMemberToGroup");
$query->setAction("insert");
$query->setPriority("");

${'group_srl27_argument'} = new Argument('group_srl', $args->{'group_srl'});
${'group_srl27_argument'}->checkNotNull();
if(!${'group_srl27_argument'}->isValid()) return ${'group_srl27_argument'}->getErrorMessage();
if(${'group_srl27_argument'} !== null) ${'group_srl27_argument'}->setColumnType('number');

${'member_srl28_argument'} = new Argument('member_srl', $args->{'member_srl'});
${'member_srl28_argument'}->checkNotNull();
if(!${'member_srl28_argument'}->isValid()) return ${'member_srl28_argument'}->getErrorMessage();
if(${'member_srl28_argument'} !== null) ${'member_srl28_argument'}->setColumnType('number');

${'site_srl29_argument'} = new Argument('site_srl', $args->{'site_srl'});
${'site_srl29_argument'}->ensureDefaultValue('0');
if(!${'site_srl29_argument'}->isValid()) return ${'site_srl29_argument'}->getErrorMessage();
if(${'site_srl29_argument'} !== null) ${'site_srl29_argument'}->setColumnType('number');

${'regdate30_argument'} = new Argument('regdate', $args->{'regdate'});
${'regdate30_argument'}->ensureDefaultValue(date("YmdHis"));
if(!${'regdate30_argument'}->isValid()) return ${'regdate30_argument'}->getErrorMessage();
if(${'regdate30_argument'} !== null) ${'regdate30_argument'}->setColumnType('date');

$query->setColumns(array(
new InsertExpression('`group_srl`', ${'group_srl27_argument'})
,new InsertExpression('`member_srl`', ${'member_srl28_argument'})
,new InsertExpression('`site_srl`', ${'site_srl29_argument'})
,new InsertExpression('`regdate`', ${'regdate30_argument'})
));
$query->setTables(array(
new Table('`drcs_member_group_member`', '`member_group_member`')
));
$query->setConditions(array());
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>