<?php
class counter_status extends WidgetHandler {

	function proc($args) {
		$oCounterModel = getModel('counter');
		$site_module_info = Context::get('site_module_info');
		$output = $oCounterModel->getStatus(array('00000000', date('Ymd', $_SERVER['REQUEST_TIME']-60*60*24), date('Ymd')), $site_module_info->site_srl);
		if(count($output)) {
			foreach($output as $key => $val)  {
				if(!$key) Context::set('total_counter', $val);
				elseif($key == date("Ymd")) Context::set('today_counter', $val);
				else Context::set('yesterday_counter', $val);
			}
		}
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
		Context::set('colorset', $args->colorset);
		$tpl_file = 'counter_status';
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}
}
