<?php
class editorAPI extends editor
{
	function dispEditorSkinColorset(&$oModule) {
		$oModule->add('colorset', Context::get('colorset'));
	}
}
