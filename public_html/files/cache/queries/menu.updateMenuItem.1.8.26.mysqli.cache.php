<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("updateMenuItem");
$query->setAction("update");
$query->setPriority("");
if(isset($args->menu_srl)) {
${'menu_srl25_argument'} = new Argument('menu_srl', $args->{'menu_srl'});
if(!${'menu_srl25_argument'}->isValid()) return ${'menu_srl25_argument'}->getErrorMessage();
} else
${'menu_srl25_argument'} = NULL;if(${'menu_srl25_argument'} !== null) ${'menu_srl25_argument'}->setColumnType('number');
if(isset($args->parent_srl)) {
${'parent_srl26_argument'} = new Argument('parent_srl', $args->{'parent_srl'});
if(!${'parent_srl26_argument'}->isValid()) return ${'parent_srl26_argument'}->getErrorMessage();
} else
${'parent_srl26_argument'} = NULL;if(${'parent_srl26_argument'} !== null) ${'parent_srl26_argument'}->setColumnType('number');
if(isset($args->name)) {
${'name27_argument'} = new Argument('name', $args->{'name'});
if(!${'name27_argument'}->isValid()) return ${'name27_argument'}->getErrorMessage();
} else
${'name27_argument'} = NULL;if(${'name27_argument'} !== null) ${'name27_argument'}->setColumnType('text');
if(isset($args->desc)) {
${'desc28_argument'} = new Argument('desc', $args->{'desc'});
if(!${'desc28_argument'}->isValid()) return ${'desc28_argument'}->getErrorMessage();
} else
${'desc28_argument'} = NULL;if(${'desc28_argument'} !== null) ${'desc28_argument'}->setColumnType('varchar');
if(isset($args->url)) {
${'url29_argument'} = new Argument('url', $args->{'url'});
if(!${'url29_argument'}->isValid()) return ${'url29_argument'}->getErrorMessage();
} else
${'url29_argument'} = NULL;if(${'url29_argument'} !== null) ${'url29_argument'}->setColumnType('varchar');
if(isset($args->is_shortcut)) {
${'is_shortcut30_argument'} = new Argument('is_shortcut', $args->{'is_shortcut'});
if(!${'is_shortcut30_argument'}->isValid()) return ${'is_shortcut30_argument'}->getErrorMessage();
} else
${'is_shortcut30_argument'} = NULL;if(${'is_shortcut30_argument'} !== null) ${'is_shortcut30_argument'}->setColumnType('char');
if(isset($args->open_window)) {
${'open_window31_argument'} = new Argument('open_window', $args->{'open_window'});
if(!${'open_window31_argument'}->isValid()) return ${'open_window31_argument'}->getErrorMessage();
} else
${'open_window31_argument'} = NULL;if(${'open_window31_argument'} !== null) ${'open_window31_argument'}->setColumnType('char');
if(isset($args->expand)) {
${'expand32_argument'} = new Argument('expand', $args->{'expand'});
if(!${'expand32_argument'}->isValid()) return ${'expand32_argument'}->getErrorMessage();
} else
${'expand32_argument'} = NULL;if(${'expand32_argument'} !== null) ${'expand32_argument'}->setColumnType('char');
if(isset($args->normal_btn)) {
${'normal_btn33_argument'} = new Argument('normal_btn', $args->{'normal_btn'});
if(!${'normal_btn33_argument'}->isValid()) return ${'normal_btn33_argument'}->getErrorMessage();
} else
${'normal_btn33_argument'} = NULL;if(${'normal_btn33_argument'} !== null) ${'normal_btn33_argument'}->setColumnType('varchar');
if(isset($args->hover_btn)) {
${'hover_btn34_argument'} = new Argument('hover_btn', $args->{'hover_btn'});
if(!${'hover_btn34_argument'}->isValid()) return ${'hover_btn34_argument'}->getErrorMessage();
} else
${'hover_btn34_argument'} = NULL;if(${'hover_btn34_argument'} !== null) ${'hover_btn34_argument'}->setColumnType('varchar');
if(isset($args->active_btn)) {
${'active_btn35_argument'} = new Argument('active_btn', $args->{'active_btn'});
if(!${'active_btn35_argument'}->isValid()) return ${'active_btn35_argument'}->getErrorMessage();
} else
${'active_btn35_argument'} = NULL;if(${'active_btn35_argument'} !== null) ${'active_btn35_argument'}->setColumnType('varchar');
if(isset($args->group_srls)) {
${'group_srls36_argument'} = new Argument('group_srls', $args->{'group_srls'});
if(!${'group_srls36_argument'}->isValid()) return ${'group_srls36_argument'}->getErrorMessage();
} else
${'group_srls36_argument'} = NULL;if(${'group_srls36_argument'} !== null) ${'group_srls36_argument'}->setColumnType('text');

${'menu_item_srl37_argument'} = new ConditionArgument('menu_item_srl', $args->menu_item_srl, 'equal');
${'menu_item_srl37_argument'}->checkFilter('number');
${'menu_item_srl37_argument'}->checkNotNull();
${'menu_item_srl37_argument'}->createConditionValue();
if(!${'menu_item_srl37_argument'}->isValid()) return ${'menu_item_srl37_argument'}->getErrorMessage();
if(${'menu_item_srl37_argument'} !== null) ${'menu_item_srl37_argument'}->setColumnType('number');

$query->setColumns(array(
new UpdateExpression('`menu_srl`', ${'menu_srl25_argument'})
,new UpdateExpression('`parent_srl`', ${'parent_srl26_argument'})
,new UpdateExpression('`name`', ${'name27_argument'})
,new UpdateExpression('`desc`', ${'desc28_argument'})
,new UpdateExpression('`url`', ${'url29_argument'})
,new UpdateExpression('`is_shortcut`', ${'is_shortcut30_argument'})
,new UpdateExpression('`open_window`', ${'open_window31_argument'})
,new UpdateExpression('`expand`', ${'expand32_argument'})
,new UpdateExpression('`normal_btn`', ${'normal_btn33_argument'})
,new UpdateExpression('`hover_btn`', ${'hover_btn34_argument'})
,new UpdateExpression('`active_btn`', ${'active_btn35_argument'})
,new UpdateExpression('`group_srls`', ${'group_srls36_argument'})
));
$query->setTables(array(
new Table('`drcs_menu_item`', '`menu_item`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`menu_item_srl`',$menu_item_srl37_argument,"equal")))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>