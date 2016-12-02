<?php
class SuperCacheAdminView extends SuperCache
{

	protected static $_menus = array(
		'dispSupercacheAdminConfigBasic' => 'cmd_supercache_config_basic',
		'dispSupercacheAdminConfigFullCache' => 'cmd_supercache_config_full_cache',
		'dispSupercacheAdminConfigBoardCache' => 'cmd_supercache_config_board_cache',
		'dispSupercacheAdminConfigWidgetCache' => 'cmd_supercache_config_widget_cache',
		'dispSupercacheAdminConfigOther' => 'cmd_supercache_config_other',
	);

	public function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
		$lang = Context::get('lang');
		foreach (self::$_menus as $key => $value) self::$_menus[$key] = $lang->$value;
		Context::set('sc_menus', self::$_menus);
	}

	public function dispSuperCacheAdminConfigBasic() {
		Context::set('sc_config', $config = $this->getConfig());
		$object_cache_option = htmlspecialchars(Context::getDbInfo()->use_object_cache);
		if (!$object_cache_option || $object_cache_option === 'dummy') $object_cache_option = 'default';
		Context::set('sc_object_cache', $object_cache_option);
		Context::set('is_rhymix', defined('RX_BASEDIR'));
		Context::set('is_memcached_supported', getAdminModel('supercache')->isMemcachedSupported());
		$this->setTemplateFile('basic');
	}

	public function dispSuperCacheAdminConfigFullCache() {
		Context::set('sc_config', $config = $this->getConfig());
		$site_srl = intval(Context::get('site_module_info')->site_srl) ?: 0;
		$module_list = getModel('module')->getMidList((object)array('site_srl' => $site_srl));
		Context::set('sc_modules', $module_list);
		$this->setTemplateFile('full_cache');
	}

	public function dispSuperCacheAdminConfigBoardCache() {
		Context::set('sc_config', $config = $this->getConfig());
		$oAdminModel = getAdminModel('supercache');
		Context::set('sc_list_replace', $oAdminModel->isListReplacementSupported());
		Context::set('sc_offset_query', $oAdminModel->isOffsetQuerySupported());
		$site_srl = intval(Context::get('site_module_info')->site_srl) ?: 0;
		$module_list = getModel('module')->getMidList((object)array('site_srl' => $site_srl));
		$module_list = array_filter($module_list, function($val) {
			return in_array($val->module, array('board', 'bodex', 'beluxe'));
		});
		Context::set('sc_modules', $module_list);
		$this->setTemplateFile('board_cache');
	}

	public function dispSuperCacheAdminConfigWidgetCache() {
		Context::set('sc_config', $config = $this->getConfig());
		$oWidgetModel = getModel('widget');
		$widget_list = $oWidgetModel->getDownloadedWidgetList();
		Context::set('widget_list', $widget_list);
		Context::set('widget_blacklist', self::$_skipWidgetNames);
		Context::set('widget_default_on', array(
			'best_content' => true,
			'content' => true,
			'cameronListOne' => true,
			'doorweb_content' => true,
			'opageWidget' => true,
			'treasurej_popular' => true,
		));
		$site_srl = intval(Context::get('site_module_info')->site_srl) ?: 0;
		$module_list = getModel('module')->getMidList((object)array('site_srl' => $site_srl));
		Context::set('sc_modules', $module_list);
		$this->setTemplateFile('widget_cache');
	}

	public function dispSuperCacheAdminConfigOther() {
		Context::set('sc_config', $config = $this->getConfig());
		if (defined('RX_VERSION')) Context::set('gzip_setting_changeable', true);
		else Context::set('gzip_setting_changeable', !defined('__OB_GZHANDLER_ENABLE__') || constant('__OB_GZHANDLER_ENABLE__'));
		$this->setTemplateFile('other');
	}
}
