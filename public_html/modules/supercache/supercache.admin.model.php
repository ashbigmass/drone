<?php
class SuperCacheAdminModel extends SuperCache
{

	public function isListReplacementSupported() {
		if (version_compare(__XE_VERSION__, '1.8.25', '>=')) return 1;
		$document_model_filename = _XE_PATH_ . 'modules/document/document.model.php';
		$document_model_checkstr = '$obj->use_alternate_output';
		if (file_exists($document_model_filename) && strpos(file_get_contents($document_model_filename), $document_model_checkstr) !== false) return 2;
		return 0;
	}

	public function isOffsetQuerySupported() {
		if (defined('RX_VERSION') && version_compare(RX_VERSION, '1.8.25', '>=')) return 1;
		$limit_tag_filename = _XE_PATH_ . 'classes/db/queryparts/limit/Limit.class.php';
		$limit_tag_checkstr = '$offset';
		if (file_exists($limit_tag_filename) && strpos(file_get_contents($limit_tag_filename), $limit_tag_checkstr) !== false) return 2;
		return 0;
	}

	public function isMemcachedSupported() {
		if (class_exists('Memcache')) return 1;
		if (class_exists('Memcached')) {
			if (defined('RX_VERSION')) return 1;
			$memcached_filename = _XE_PATH_ . 'classes/cache/CacheMemcache.class.php';
			$memcached_checkstr = 'new Memcached';
			if (file_exists($memcached_filename) && strpos(file_get_contents($memcached_filename), $memcached_checkstr) !== false) return 2;
		}
		return 0;
	}
}
