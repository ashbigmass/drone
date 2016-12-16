<?php
class statsAdminController extends stats
{
	public function init() {
	}

	public function procStatsAdminInsertConfig() {
		$args = Context::gets('stats_ignore_admin','stats_ignore_bot','stats_ignore_ip','stats_enable_admin_layer');
		if (!$args->stats_ignore_admin) $args->stats_ignore_admin = 'N';
		if (!$args->stats_ignore_bot) $args->stats_ignore_bot = 'N';
		if (!$args->stats_ignore_ip) $args->stats_ignore_ip = '';
		if (!$args->stats_enable_admin_layer) $args->stats_enable_admin_layer = 'Y';
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('stats', $args);
		if (!$output->toBool()) return $output;
		$this->setMessage('success_registed');
		if (Context::get('success_return_url')) $this->setRedirectUrl(Context::get('success_return_url'));
		else $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'stats', 'menu', 'setting'));
	}
}
