<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getMemberSrl");
$query->setAction("select");
$query->setPriority("");
if(isset($args->user_id)) {
${'user_id1_argument'} = new ConditionArgument('user_id', $args->user_id, 'equal');
${'user_id1_argument'}->createConditionValue();
if(!${'user_id1_argument'}->isValid()) return ${'user_id1_argument'}->getErrorMessage();
} else
${'user_id1_argument'} = NULL;if(${'user_id1_argument'} !== null) ${'user_id1_argument'}->setColumnType('varchar');
if(isset($args->email_address)) {
${'email_address2_argument'} = new ConditionArgument('email_address', $args->email_address, 'equal');
${'email_address2_argument'}->createConditionValue();
if(!${'email_address2_argument'}->isValid()) return ${'email_address2_argument'}->getErrorMessage();
} else
${'email_address2_argument'} = NULL;if(${'email_address2_argument'} !== null) ${'email_address2_argument'}->setColumnType('varchar');
if(isset($args->nick_name)) {
${'nick_name3_argument'} = new ConditionArgument('nick_name', $args->nick_name, 'equal');
${'nick_name3_argument'}->createConditionValue();
if(!${'nick_name3_argument'}->isValid()) return ${'nick_name3_argument'}->getErrorMessage();
} else
${'nick_name3_argument'} = NULL;if(${'nick_name3_argument'} !== null) ${'nick_name3_argument'}->setColumnType('varchar');

$query->setColumns(array(
new SelectExpression('`member_srl`')
));
$query->setTables(array(
new Table('`drcs_member`', '`member`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`user_id`',$user_id1_argument,"equal", 'and')
,new ConditionWithArgument('`email_address`',$email_address2_argument,"equal", 'and')
,new ConditionWithArgument('`nick_name`',$nick_name3_argument,"equal", 'and')))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>