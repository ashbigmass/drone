<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("getSiteAddons");
$query->setAction("select");
$query->setPriority("");

${'site_srl16_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl16_argument'}->checkNotNull();
${'site_srl16_argument'}->createConditionValue();
if(!${'site_srl16_argument'}->isValid()) return ${'site_srl16_argument'}->getErrorMessage();
if(${'site_srl16_argument'} !== null) ${'site_srl16_argument'}->setColumnType('number');

${'list_order17_argument'} = new Argument('list_order', $args->{'list_order'});
${'list_order17_argument'}->ensureDefaultValue('addon');
if(!${'list_order17_argument'}->isValid()) return ${'list_order17_argument'}->getErrorMessage();

$query->setColumns(array(
new StarExpression()
));
$query->setTables(array(
new Table('`drcs_addons_site`', '`addons_site`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`site_srl`',$site_srl16_argument,"equal")))
));
$query->setGroups(array());
$query->setOrder(array(
new OrderByColumn(${'list_order17_argument'}, "asc")
));
$query->setLimit();
return $query; ?>