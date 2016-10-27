<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getMenuItemByUrl");
$query->setAction("select");
$query->setPriority("");

${'url22_argument'} = new ConditionArgument('url', $args->url, 'equal');
${'url22_argument'}->checkNotNull();
${'url22_argument'}->createConditionValue();
if(!${'url22_argument'}->isValid()) return ${'url22_argument'}->getErrorMessage();
if(${'url22_argument'} !== null) ${'url22_argument'}->setColumnType('varchar');
if(isset($args->is_shortcut)) {
${'is_shortcut23_argument'} = new ConditionArgument('is_shortcut', $args->is_shortcut, 'equal');
${'is_shortcut23_argument'}->createConditionValue();
if(!${'is_shortcut23_argument'}->isValid()) return ${'is_shortcut23_argument'}->getErrorMessage();
} else
${'is_shortcut23_argument'} = NULL;if(${'is_shortcut23_argument'} !== null) ${'is_shortcut23_argument'}->setColumnType('char');

${'site_srl24_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl24_argument'}->checkNotNull();
${'site_srl24_argument'}->createConditionValue();
if(!${'site_srl24_argument'}->isValid()) return ${'site_srl24_argument'}->getErrorMessage();

$query->setColumns(array(
new StarExpression()
));
$query->setTables(array(
new Table('`drcs_menu_item`', '`MI`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`MI`.`url`',$url22_argument,"equal")
,new ConditionWithArgument('`MI`.`is_shortcut`',$is_shortcut23_argument,"equal", 'and')
,new ConditionSubquery('`MI`.`menu_srl`',new Subquery('`getSiteSrl`', array(
new SelectExpression('`menu_srl`')
), 
array(
new Table('`drcs_menu`', '`M`')
),
array(
new ConditionGroup(array(
new ConditionWithArgument('`M`.`site_srl`',$site_srl24_argument,"equal")))
),
array(),
array(),
null
),"in", 'and')))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>