<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("deleteMemberGroupMember");
$query->setAction("delete");
$query->setPriority("");

${'member_srl24_argument'} = new ConditionArgument('member_srl', $args->member_srl, 'equal');
${'member_srl24_argument'}->checkFilter('number');
${'member_srl24_argument'}->checkNotNull();
${'member_srl24_argument'}->createConditionValue();
if(!${'member_srl24_argument'}->isValid()) return ${'member_srl24_argument'}->getErrorMessage();
if(${'member_srl24_argument'} !== null) ${'member_srl24_argument'}->setColumnType('number');
if(isset($args->group_srl)) {
${'group_srl25_argument'} = new ConditionArgument('group_srl', $args->group_srl, 'equal');
${'group_srl25_argument'}->checkFilter('number');
${'group_srl25_argument'}->createConditionValue();
if(!${'group_srl25_argument'}->isValid()) return ${'group_srl25_argument'}->getErrorMessage();
} else
${'group_srl25_argument'} = NULL;if(${'group_srl25_argument'} !== null) ${'group_srl25_argument'}->setColumnType('number');
if(isset($args->site_srl)) {
${'site_srl26_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl26_argument'}->checkFilter('number');
${'site_srl26_argument'}->createConditionValue();
if(!${'site_srl26_argument'}->isValid()) return ${'site_srl26_argument'}->getErrorMessage();
} else
${'site_srl26_argument'} = NULL;if(${'site_srl26_argument'} !== null) ${'site_srl26_argument'}->setColumnType('number');

$query->setTables(array(
new Table('`drcs_member_group_member`', '`member_group_member`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`member_srl`',$member_srl24_argument,"equal")
,new ConditionWithArgument('`group_srl`',$group_srl25_argument,"equal", 'and')
,new ConditionWithArgument('`site_srl`',$site_srl26_argument,"equal", 'and')))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>