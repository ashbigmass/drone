<?php
class counterAdminView extends counter
{
	function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	function dispCounterAdminIndex() {
		$selected_date = (int)Context::get('selected_date');
		if(!$selected_date) $selected_date = date("Ymd");
		Context::set('selected_date', $selected_date);
		$oCounterModel = getModel('counter');
		$site_module_info = Context::get('site_module_info');
		$status = $oCounterModel->getStatus(array(0, $selected_date), $site_module_info->site_srl);
		Context::set('total_counter', $status[0]);
		Context::set('selected_day_counter', $status[$selected_date]);
		$type = Context::get('type');
		if(!$type) {
			$type = 'day';
			Context::set('type', $type);
		}
		$detail_status = $oCounterModel->getHourlyStatus($type, $selected_date, $site_module_info->site_srl);
		Context::set('detail_status', $detail_status);
		$this->setTemplateFile('index');
	}
}
