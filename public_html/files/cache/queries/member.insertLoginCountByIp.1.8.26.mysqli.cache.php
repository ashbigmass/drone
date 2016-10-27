<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("insertLoginCountByIp");
$query->setAction("insert");
$query->setPriority("");

${'ipaddress7_argument'} = new Argument('ipaddress', $args->{'ipaddress'});
${'ipaddress7_argument'}->checkNotNull();
if(!${'ipaddress7_argument'}->isValid()) return ${'ipaddress7_argument'}->getErrorMessage();
if(${'ipaddress7_argument'} !== null) ${'ipaddress7_argument'}->setColumnType('varchar');

${'count8_argument'} = new Argument('count', $args->{'count'});
${'count8_argument'}->checkNotNull();
if(!${'count8_argument'}->isValid()) return ${'count8_argument'}->getErrorMessage();
if(${'count8_argument'} !== null) ${'count8_argument'}->setColumnType('number');

${'regdate9_argument'} = new Argument('regdate', $args->{'regdate'});
${'regdate9_argument'}->ensureDefaultValue(date("YmdHis"));
if(!${'regdate9_argument'}->isValid()) return ${'regdate9_argument'}->getErrorMessage();
if(${'regdate9_argument'} !== null) ${'regdate9_argument'}->setColumnType('date');

${'last_update10_argument'} = new Argument('last_update', $args->{'last_update'});
${'last_update10_argument'}->ensureDefaultValue(date("YmdHis"));
if(!${'last_update10_argument'}->isValid()) return ${'last_update10_argument'}->getErrorMessage();
if(${'last_update10_argument'} !== null) ${'last_update10_argument'}->setColumnType('date');

$query->setColumns(array(
new InsertExpression('`ipaddress`', ${'ipaddress7_argument'})
,new InsertExpression('`count`', ${'count8_argument'})
,new InsertExpression('`regdate`', ${'regdate9_argument'})
,new InsertExpression('`last_update`', ${'last_update10_argument'})
));
$query->setTables(array(
new Table('`drcs_member_login_count`', '`member_login_count`')
));
$query->setConditions(array());
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>