<?php
class SuperCacheModel extends SuperCache
{
	protected $_subgroup_keys = array();

	public function getFullPageCache($module_srl, $document_srl, $user_agent_type, array $args) {
		$config = $this->getConfig();
		$cache_key = $this->_getFullPageCacheKey($module_srl, $document_srl, $user_agent_type, $args);
		$content = $this->getCache($cache_key, $config->full_cache_duration + 60);
		if (!is_array($content)) return false;
		$current_timestamp = time();
		if ($config->full_cache_stampede_protection !== false && $content['expires'] <= $current_timestamp) {
			$content['expires'] = $current_timestamp + 60;
			$this->setCache($cache_key, $content, 60);
			return false;
		}
		return $content;
	}

	public function setFullPageCache($module_srl, $document_srl, $user_agent_type, array $args, $content, array $extra_data, $http_status_code, $elapsed_time) {
		$config = $this->getConfig();
		$content = array(
			'content' => strval($content),
			'cached' => time(),
			'expires' => time() + $config->full_cache_duration,
			'extra_data' => $extra_data,
			'status' => intval($http_status_code),
			'elapsed' => number_format($elapsed_time * 1000, 1) . ' ms',
		);
		$cache_key = $this->_getFullPageCacheKey($module_srl, $document_srl, $user_agent_type, $args);
		$extra_duration = ($config->full_cache_stampede_protection !== false) ? 60 : 0;
		return $this->setCache($cache_key, $content, $config->full_cache_duration + $extra_duration);
	}

	public function deleteFullPageCache($module_srl = 0, $document_srl = 0) {
		if ($module_srl) $this->_invalidateSubgroupCacheKey('fullpage_module:' . $module_srl);
		if ($document_srl) $this->_invalidateSubgroupCacheKey('fullpage_document:' . $document_srl);
		return true;
	}

	public function getSearchResultCache($args) {
		$config = $this->getConfig();
		$cache_key = $this->_getSearchResultCacheKey($args);
		$content = $this->getCache($cache_key, $config->search_cache_duration);
		if (!is_array($content)) return false;
		$current_timestamp = time();
		if ($content['expires'] <= $current_timestamp) {
			$content['expires'] = $current_timestamp + 60;
			$this->setCache($cache_key, $content, 60);
			return false;
		}
		$query_args = new stdClass;
		$query_args->document_srl = $content['document_srls'];
		$query_args->list_count = $content['list_count'];
		$query_args->sort_index = $content['sort_index'];
		$query_args->order_type = $content['order_type'];
		$output = executeQuery('supercache.getDocumentList', $query_args);
		if (is_object($output->data)) $output->data = array($output->data);
		$this->_fillPaginationData($output, $content['total_count'], $content['list_count'] ?: 20, $args->page_count ?: 10, $args->page ?: 1);
		return $output;
	}

	public function setSearchResultCache($args, $result) {
		$config = $this->getConfig();
		$content = array(
			'document_srls' => array(),
			'total_count' => $result->total_count,
			'list_count' => intval($args->list_count),
			'sort_index' => trim($args->sort_index) ?: 'list_order',
			'order_type' => trim($args->order_type) ?: 'asc',
			'cached' => time(),
			'expires' => time() + $config->search_cache_duration,
		);
		foreach ($result->data as $document) $content['document_srls'][] = $document->document_srl;
		$cache_key = $this->_getSearchResultCacheKey($args);
		return $this->setCache($cache_key, $content, $config->search_cache_duration + 60);
	}

	public function deleteSearchResultCache($module_srl = 0, $is_comment) {
		if ($module_srl) {
			if ($is_comment) $this->_invalidateSubgroupCacheKey('module_search:' . intval($module_srl) . '_comment');
			else $this->_invalidateSubgroupCacheKey('module_search:' . intval($module_srl));
		}
		return true;
	}

	public function getWidgetCache($cache_key, $cache_duration) {
		$config = $this->getConfig();
		$content = $this->getCache($cache_key, $cache_duration);
		if (!is_array($content)) return false;
		$current_timestamp = time();
		if ($config->widget_cache_stampede_protection !== false && $content['expires'] <= $current_timestamp) {
			$content['expires'] = $current_timestamp + 60;
			$this->setCache($cache_key, $content, 60);
			return false;
		}
		return $content['content'];
	}

	public function setWidgetCache($cache_key, $cache_duration, $content, $target_modules) {
		$config = $this->getConfig();
		$content = array(
			'content' => strval($content),
			'expires' => time() + $cache_duration,
		);
		$extra_duration = ($config->widget_cache_stampede_protection !== false) ? 60 : 0;
		$result = $this->setCache($cache_key, $content, $cache_duration + $extra_duration);
		$target_key_base = $this->_getSubgroupCacheKey('widget_target');
		foreach ($target_modules as $target_module_srl) {
			if ($target_module_srl) {
				$target_key = $target_key_base . ':' . $target_module_srl;
				$target_list = $this->getCache($target_key) ?: array();
				$target_list[$cache_key] = true;
				$this->setCache($target_key, $target_list);
			}
		}
		return $result;
	}

	public function invalidateWidgetCache($target_module_srl) {
		$config = $this->getConfig();
		if (!$target_module_srl) return false;
		$target_key = $this->_getSubgroupCacheKey('widget_target') . ':' . $target_module_srl;
		$target_list = $this->getCache($target_key) ?: array();
		$target_count = 0;
		foreach ($target_list as $cache_key => $unused) {
			if ($config->widget_cache_stampede_protection !== false) {
				$content = $this->getCache($cache_key);
				if (is_array($content) && $content['expires'] > time() + 5) {
					$content['expires'] = time();
					$this->setCache($cache_key, $content, 30);
					$target_count++;
				}
			} else {
				$this->deleteCache($cache_key);
			}
		}
		return $target_count ? true : false;
	}

	public function getDocumentCount($module_srl, $category_srl) {
		$config = $this->getConfig();
		$module_srl = intval($module_srl);
		if (!is_array($category_srl)) $category_srl = $category_srl ? explode(',', $category_srl) : array();
		$cache_key = $this->_getDocumentCountCacheKey($module_srl, $category_srl);
		if (mt_rand(0, $config->paging_cache_auto_refresh) !== 0) {
			$content = $this->getCache($cache_key, $config->paging_cache_duration);
			if (is_array($content)) return $content['count'];
		}
		$args = new stdClass;
		$args->module_srl = $module_srl;
		$args->category_srl = $category_srl;
		$args->statusList = array('PUBLIC', 'SECRET');
		$output = executeQuery('supercache.getDocumentCount', $args);
		if ($output->toBool() && isset($output->data->count)) {
			$content = array(
				'count' => intval($output->data->count),
				'cached' => time(),
				'expires' => time() + $config->paging_cache_duration,
			);
			$this->setCache($cache_key, $content, $config->paging_cache_duration);
			return $content['count'];
		} else {
			return false;
		}
	}

	public function getDocumentList($args, $total_count) {
		$page_count = intval($args->page_count) ?: 10;
		$page = intval(max(1, $args->page));
		unset($args->page_count);
		unset($args->page);
		$output = executeQuery('supercache.getDocumentList', $args);
		if (is_object($output->data)) $output->data = array($output->data);
		$this->_fillPaginationData($output, $total_count, $args->list_count, $page_count, $page);
		return $output;
	}

	public function updateDocumentCount($module_srl, $category_srl, $diff) {
		$config = $this->getConfig();
		$categories = $this->_getAllParentCategories($module_srl, $category_srl);
		$categories[] = 'all';
		foreach ($categories as $category) {
			$cache_key = $this->_getDocumentCountCacheKey($module_srl, $category);
			$content = $this->getCache($cache_key, $config->paging_cache_duration);
			if (is_array($content) && $content['expires'] > time()) {
				$content['count'] += $diff;
				$this->setCache($cache_key, $content, $content['expires'] - time());
			}
		}
	}

	public function updateDocumentViewCount($document_srl, $extra_data) {
		$config = $this->getConfig();
		$document_srl = intval($document_srl);
		if (!$document_srl || !$extra_data) return;
		if ($config->full_cache_incr_view_count_probabilistic) {
			$probability = max(1, floor(log($extra_data['view_count'], 1.5)));
			$incr = mt_rand(0, $probability) === 0 ? $probability : 0;
		} else {
			$incr = 1;
		}
		if ($incr) {
			$oDB = DB::getInstance();
			$oDB->query_id = 'supercache.updateReadedCount';
			$output = $oDB->_query(sprintf('UPDATE %sdocuments SET readed_count = readed_count + %d WHERE document_srl = %d', $oDB->prefix, $incr, $document_srl));
			return $output ? true : false;
		}
	}

	public function getWidgetCacheKey($widget_attrs, $logged_info) {
		if (!$logged_info || !$logged_info->member_srl) {
			$group_key = 'nogroup';
		} elseif ($logged_info->is_admin === 'Y') {
			$group_key = 'admin';
		} else {
			$groups = $logged_info->group_list;
			sort($groups);
			$group_key = sha1(implode('|', $groups));
		}
		$subgroup_key = $this->_getSubgroupCacheKey('widget');
		return sprintf('%s:%s:%s:%s', $subgroup_key, $widget_attrs->widget, hash('sha256', serialize($widget_attrs)), $group_key);
	}

	protected function _getFullPageCacheKey($module_srl, $document_srl, $user_agent_type, array $args = array()) {
		$module_srl = intval($module_srl) ?: 0;
		$document_srl = intval($document_srl) ?: 0;
		ksort($args);
		$module_key = $this->_getSubgroupCacheKey('fullpage_module:' . $module_srl);
		$document_key = $document_srl ? $this->_getSubgroupCacheKey('fullpage_document:' . $document_srl) : 'module_index';
		if (!count($args)) {
			$argskey = 'p1';
		} elseif (count($args) === 1 && isset($args['page']) && (is_int($args['page']) || ctype_digit(strval($args['page'])))) {
			$argskey = 'p' . $args['page'];
		} else {
			$argskey = hash('sha256', json_encode($args));
		}
		return sprintf('%s:%s:%s_%s', $module_key, $document_key, $user_agent_type, $argskey);
	}

	protected function _getSearchResultCacheKey($args) {
		$comment_key = ($args->search_target === 'comment') ? '_comment' : '';
		$module_key = $this->_getSubgroupCacheKey('board_search:' . intval($args->module_srl) . $comment_key);
		$category_key = 'category_' . intval($args->category_srl);
		$search_key = hash('sha256', json_encode(array(
			'search_target' => trim($args->search_target),
			'search_keyword' => trim($args->search_keyword),
			'sort_index' => trim($args->sort_index) ?: 'list_order',
			'order_type' => trim($args->order_type) ?: 'asc',
			'list_count' => intval($args->list_count),
			'page_count' => intval($args->page_count),
			'isExtraVars' => (bool)($args->isExtraVars),
		)));
		return sprintf('%s:%s:%s:p%d', $module_key, $category_key, $search_key, max(1, intval($args->page)));
	}

	protected function _getDocumentCountCacheKey($module_srl, $category_srl) {
		$module_key = $this->_getSubgroupCacheKey('document_count:' . intval($module_srl));
		$category_key = 'category_' . ($category_srl ? ((is_array($category_srl) && count($category_srl)) ? end($category_srl) : $category_srl) : 'all');
		return sprintf('%s:%s', $module_key, $category_key);
	}

	protected function _getSubgroupCacheKey($cache_key, $subgroup_portion_only = false) {
		if (isset($this->_subgroup_keys[$cache_key])) {
			$subgroup_key = $this->_subgroup_keys[$cache_key];
		} else {
			$subgroup_key = intval($this->getCache('subgroups:' . $cache_key));
			if (!$subgroup_key) {
				$subgroup_key = 1;
				$this->setCache('subgroups:' . $cache_key, $subgroup_key, 0);
			}
			$this->_subgroup_keys[$cache_key] = $subgroup_key;
		}
		if ($subgroup_portion_only) return $subgroup_key;
		else return $cache_key . ':' . $subgroup_key;
	}

	protected function _invalidateSubgroupCacheKey($cache_key) {
		$old_subgroup_key = $this->_getSubgroupCacheKey($cache_key, true);
		$new_subgroup_key = $old_subgroup_key + 1;
		$this->setCache('subgroups:' . $cache_key, $new_subgroup_key, 0);
		$this->_subgroup_keys[$cache_key] = $new_subgroup_key;
		$config = $this->getConfig();
		if ($config->auto_purge_cache_files) {
			if (self::$_cache_handler_cache instanceof SuperCacheFileDriver) self::$_cache_handler_cache->invalidateSubgroupKey($cache_key, $old_subgroup_key);
		}
		return true;
	}

	protected function _getAllParentCategories($module_srl, $category_srl) {
		if (!$category_srl) return array();
		$categories = getModel('document')->getCategoryList($module_srl);
		if (!isset($categories[$category_srl])) return array();
		$category = $categories[$category_srl];
		$result[] = $category->category_srl;
		while ($category->parent_srl && isset($categories[$category->parent_srl])) {
			$category = $categories[$category->parent_srl];
			$result[] = $category->category_srl;
		}
		return $result;
	}

	protected function _getAllChildCategories($module_srl, $category_srl) {
		if (!$category_srl) return array();
		$categories = getModel('document')->getCategoryList($module_srl);
		if (!isset($categories[$category_srl])) return array();
		$category = $categories[$category_srl];
		$result = $category->childs ?: array();
		$result[] = $category->category_srl;
		return $result;
	}

	protected function _fillPaginationData(&$output, $total_count, $list_count, $page_count, $page) {
		$virtual_number = $total_count - (($page - 1) * $list_count);
		$virtual_range = count($output->data) ? range($virtual_number, $virtual_number - count($output->data) + 1, -1) : array();
		$output->data = count($output->data) ? array_combine($virtual_range, $output->data) : array();
		$output->total_count = $total_count;
		$output->total_page = max(1, ceil($total_count / $list_count));
		$output->page = $page;
		$output->page_navigation = new PageHandler($output->total_count, $output->total_page, $page, $page_count);
	}
}
