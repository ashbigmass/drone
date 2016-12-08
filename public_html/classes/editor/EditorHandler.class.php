<?php
class EditorHandler extends Object
{
	function setInfo($info) {
		Context::set('component_info', $info);
		if(!$info->extra_vars) return;
		foreach($info->extra_vars as $key => $val) $this->{$key} = trim($val->value);
	}
}
