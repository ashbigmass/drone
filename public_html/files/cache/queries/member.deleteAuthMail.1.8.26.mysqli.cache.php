<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("deleteAuthMail");
$query->setAction("delete");
$query->setPriority("");

${'member_srl4_argument'} = new ConditionArgument('member_srl', $args->member_srl, 'equal');
${'member_srl4_argument'}->checkNotNull();
${'member_srl4_argument'}->createConditionValue();
if(!${'member_srl4_argument'}->isValid()) return ${'member_srl4_argument'}->getErrorMessage();
if(${'member_srl4_argument'} !== null) ${'member_srl4_argument'}->setColumnType('number');

$query->setTables(array(
new Table('`drcs_member_auth_mail`', '`member_auth_mail`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`member_srl`',$member_srl4_argument,"equal")))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>