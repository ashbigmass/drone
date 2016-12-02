<?php
class SuperCache extends ModuleObject
{
	protected static $_insert_triggers = array(
		array('moduleHandler.init', 'before', 'controller', 'triggerBeforeModuleHandlerInit'),
		array('moduleHandler.init', 'after', 'controller', 'triggerAfterModuleHandlerInit'),
		array('document.getDocumentList', 'before', 'controller', 'triggerBeforeGetDocumentList'),
		array('document.getDocumentList', 'after', 'controller', 'triggerAfterGetDocumentList'),
		array('document.insertDocument', 'after', 'controller', 'triggerAfterInsertDocument'),
		array('document.updateDocument', 'after', 'controller', 'triggerAfterUpdateDocument'),
		array('document.deleteDocument', 'after', 'controller', 'triggerAfterDeleteDocument'),
		array('document.copyDocumentModule', 'after', 'controller', 'triggerAfterCopyDocumentModule'),
		array('document.moveDocumentModule', 'after', 'controller', 'triggerAfterMoveDocumentModule'),
		array('document.moveDocumentToTrash', 'after', 'controller', 'triggerAfterMoveDocumentToTrash'),
		array('document.restoreTrash', 'after', 'controller', 'triggerAfterRestoreDocumentFromTrash'),
		array('comment.insertComment', 'after', 'controller', 'triggerAfterInsertComment'),
		array('comment.updateComment', 'after', 'controller', 'triggerAfterUpdateComment'),
		array('comment.deleteComment', 'after', 'controller', 'triggerAfterDeleteComment'),
		array('moduleHandler.proc', 'after', 'controller', 'triggerAfterModuleHandlerProc'),
		array('display', 'before', 'controller', 'triggerBeforeDisplay'),
		array('display', 'after', 'controller', 'triggerAfterDisplay'),
	);
	protected static $_delete_triggers = array(
		array('moduleObject.proc', 'before', 'controller', 'triggerBeforeModuleObjectProc'),
	);
	protected static $_skipWidgetNames = array(
		'login_info' => true,
		'language_select' => true,
		'point_status' => true,
		'soo_xerstory' => true,
		'widgetContent' => true,
		'widgetBox' => true,
	);
	protected static $_skipWidgetAttrs = array(
		'class' => true,
		'document_srl' => true,
		'style' => true,
		'widget' => true,
		'widget_padding_top' => true,
		'widget_padding_right' => true,
		'widget_padding_bottom' => true,
		'widget_padding_left' => true,
		'widgetstyle' => true,
	);
	protected static $_config_cache = null;
	protected static $_cache_handler_cache = null;

	public function getConfig() {
		if (self::$_config_cache === null) {
			$oModuleModel = getModel('module');
			self::$_config_cache = $oModuleModel->getModuleConfig($this->module) ?: new stdClass;
		}
		return self::$_config_cache;
	}

	public function setConfig($config) {
		$oModuleController = getController('module');
		$result = $oModuleController->insertModuleConfig($this->module, $config);
		if ($result->toBool()) self::$_config_cache = $config;
		return $result;
	}

	protected function _getCacheHandler() {
		$db_info = Context::getDbInfo();
		if ($db_info->use_object_cache) {
			if (!preg_match('/^(?:file|dummy)\b/i', $db_info->use_object_cache)) {
				$handler = CacheHandler::getInstance('object');
				if ($handler->isSupport()) return $handler;
			}
		}
		include_once __DIR__ . '/supercache.filedriver.php';
		return new SuperCacheFileDriver;
	}

	public function getCache($key, $ttl = 86400, $group_key = null) {
		if (self::$_cache_handler_cache === null) self::$_cache_handler_cache = $this->_getCacheHandler();
		$group_key = $group_key ?: $this->module;
		return self::$_cache_handler_cache->get(self::$_cache_handler_cache->getGroupKey($group_key, $key), $ttl);
	}

	public function setCache($key, $value, $ttl = 86400, $group_key = null) {
		if (self::$_cache_handler_cache === null) self::$_cache_handler_cache = $this->_getCacheHandler();
		$group_key = $group_key ?: $this->module;
		return self::$_cache_handler_cache->put(self::$_cache_handler_cache->getGroupKey($group_key, $key), $value, $ttl);
	}

	public function deleteCache($key, $group_key = null) {
		if (self::$_cache_handler_cache === null) self::$_cache_handler_cache = $this->_getCacheHandler();
		$group_key = $group_key ?: $this->module;
		self::$_cache_handler_cache->delete(self::$_cache_handler_cache->getGroupKey($group_key, $key));
	}

	public function clearCache($group_key = null) {
		if (self::$_cache_handler_cache === null) self::$_cache_handler_cache = $this->_getCacheHandler();
		$group_key = $group_key ?: $this->module;
		return self::$_cache_handler_cache->invalidateGroupKey($group_key);
	}

	public function error($message) {
		$args = func_get_args();
		if (count($args) > 1) {
			global $lang;
			array_shift($args);
			$message = vsprintf($lang->$message, $args);
		}
		return new Object(-1, $message);
	}

	public function checkTriggers() {
		$oModuleModel = getModel('module');
		foreach (self::$_insert_triggers as $trigger) {
			if (!$oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1])) return true;
		}
		foreach (self::$_delete_triggers as $trigger) {
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1])) return true;
		}
		return false;
	}

	public function registerTriggers() {
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach (self::$_insert_triggers as $trigger) {
			if (!$oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
				$oModuleController->insertTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]);
		}
		foreach (self::$_delete_triggers as $trigger) {
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
				$oModuleController->deleteTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]);
		}
		return new Object(0, 'success_updated');
	}

	public function moduleInstall() {
		return $this->registerTriggers();
	}

	public function checkUpdate() {
		return $this->checkTriggers();
	}

	public function moduleUpdate() {
		return $this->registerTriggers();
	}

	public function recompileCache() {
		$this->clearCache();
	}
}
