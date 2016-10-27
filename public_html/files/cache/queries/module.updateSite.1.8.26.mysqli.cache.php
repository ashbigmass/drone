<?php if(!defined('__XE__')) exit();
$query = new Query();
$query->setQueryId("updateSite");
$query->setAction("update");
$query->setPriority("");
if(isset($args->index_module_srl)) {
${'index_module_srl2_argument'} = new Argument('index_module_srl', $args->{'index_module_srl'});
if(!${'index_module_srl2_argument'}->isValid()) return ${'index_module_srl2_argument'}->getErrorMessage();
} else
${'index_module_srl2_argument'} = NULL;if(${'index_module_srl2_argument'} !== null) ${'index_module_srl2_argument'}->setColumnType('number');
if(isset($args->domain)) {
${'domain3_argument'} = new Argument('domain', $args->{'domain'});
if(!${'domain3_argument'}->isValid()) return ${'domain3_argument'}->getErrorMessage();
} else
${'domain3_argument'} = NULL;if(${'domain3_argument'} !== null) ${'domain3_argument'}->setColumnType('varchar');
if(isset($args->default_language)) {
${'default_language4_argument'} = new Argument('default_language', $args->{'default_language'});
if(!${'default_language4_argument'}->isValid()) return ${'default_language4_argument'}->getErrorMessage();
} else
${'default_language4_argument'} = NULL;if(${'default_language4_argument'} !== null) ${'default_language4_argument'}->setColumnType('varchar');

${'site_srl5_argument'} = new ConditionArgument('site_srl', $args->site_srl, 'equal');
${'site_srl5_argument'}->checkFilter('number');
${'site_srl5_argument'}->checkNotNull();
${'site_srl5_argument'}->createConditionValue();
if(!${'site_srl5_argument'}->isValid()) return ${'site_srl5_argument'}->getErrorMessage();
if(${'site_srl5_argument'} !== null) ${'site_srl5_argument'}->setColumnType('number');

$query->setColumns(array(
new UpdateExpression('`index_module_srl`', ${'index_module_srl2_argument'})
,new UpdateExpression('`domain`', ${'domain3_argument'})
,new UpdateExpression('`default_language`', ${'default_language4_argument'})
));
$query->setTables(array(
new Table('`drcs_sites`', '`sites`')
));
$query->setConditions(array(
new ConditionGroup(array(
new ConditionWithArgument('`site_srl`',$site_srl5_argument,"equal")))
));
$query->setGroups(array());
$query->setOrder(array());
$query->setLimit();
return $query; ?>