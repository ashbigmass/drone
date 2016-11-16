<?php

class language_select extends WidgetHandler {
	function proc($args) {
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
		$tpl_file = 'language_select';
		Context::set('colorset', $args->colorset);
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}
}
