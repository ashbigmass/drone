<?php
class SuperCacheController extends SuperCache
{

	protected $_defaultUrlChecked = false;
	protected $_cacheCurrentSearch = false;
	protected $_cacheCurrentRequest = null;
	protected $_cacheStartTimestamp = null;
	protected $_cacheHttpStatusCode = 200;

	public function triggerBeforeModuleHandlerInit($obj) {
		$config = $this->getConfig();
		$current_domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
		$request_method = Context::getRequestMethod();
		if ($request_method === 'GET' && isset($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $current_domain) {
			$accept_header = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
			if ($config->block_css_request && !strncmp($accept_header, 'text/css', 8)) return $this->terminateWithPlainText('/* block_css_request */');
			if ($config->block_img_request && $accept_header && !strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') && !strncmp($accept_header, 'image/', 6) && !preg_match('/\b(?:ht|x)ml\b/', $accept_header)) return $this->terminateWithPlainText('/* block_img_request */');
		}
		if ($config->redirect_to_default_url && $request_method === 'GET') {
			$default_url = parse_url(Context::getDefaultUrl());
			if ($current_domain !== $default_url['host']) {
				$redirect_url = sprintf('%s://%s%s%s', $default_url['scheme'], $default_url['host'], $default_url['port'] ? (':' . $default_url['port']) : '', $_SERVER['REQUEST_URI']);
				return $this->terminateRedirectTo($redirect_url);
			} else {
				$this->_defaultUrlChecked = true;
			}
		}
		if ($config->full_cache && !$config->full_cache_delay_trigger) $this->checkFullPageCache($obj, $config);
	}

	public function triggerAfterModuleHandlerInit($obj) {
		$config = $this->getConfig();
		if ($config->full_cache && $config->full_cache_delay_trigger) $this->checkFullPageCache($obj, $config);
		if ($config->paging_cache) $this->fillPageVariable($obj, $config);
		spl_autoload_register(function($class) {
			if (preg_match('/^(document|comment)item$/', strtolower($class), $matches)) {
				include_once sprintf('%1$smodules/%2$s/%2$s.item.php', _XE_PATH_, $matches[1]);
			}
		});
	}

	public function triggerBeforeGetDocumentList($obj) {
		$config = $this->getConfig();
		if ((!$config->paging_cache && !$config->search_cache) || (!$obj->mid && !$obj->module_srl)) return;
		if ($config->disable_post_search && $_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' && !$_POST['act'] && $obj->search_keyword) return $this->terminateRequest('disable_post_search');
		if ($obj->use_alternate_output) return;
		if ($obj->search_target || $obj->search_keyword || $obj->exclude_module_srl || $obj->start_date || $obj->end_date || $obj->member_srl) {
			if ($config->search_cache && $obj->search_target && Context::getRequestMethod() === 'GET' && Context::get('module') !== 'admin' && !Context::get('act')) {
				$oModel = getModel('supercache');
				if ($cached_search_result = $oModel->getSearchResultCache($obj)) $obj->use_alternate_output = $cached_search_result;
				else $this->_cacheCurrentSearch = $obj;
			}
			return;
		}
		if ($obj->page > 1 && !$config->paging_cache_use_offset) return;
		$oDocumentModel = getModel('document');
		$oDocumentModel->_setSearchOption($obj, $args, $query_id, $use_division);
		if ($query_id !== 'document.getDocumentList' || $use_division || (is_array($args->module_srl) && count($args->module_srl) > 1)) return;
		if (isset($config->paging_cache_exclude_modules[$args->module_srl])) return;
		$oModel = getModel('supercache');
		$document_count = $oModel->getDocumentCount($args->module_srl, $args->category_srl);
		if ($document_count < $config->paging_cache_threshold) return;
		if ($config->paging_cache_use_offset && $args->page > 1) $args->list_offset = ($args->page - 1) * $args->list_count;
		$obj->use_alternate_output = $oModel->getDocumentList($args, $document_count);
	}

	public function triggerAfterGetDocumentList($obj) {
		if ($this->_cacheCurrentSearch) {
			$oModel = getModel('supercache');
			$oModel->setSearchResultCache($this->_cacheCurrentSearch, $obj);
			$this->_cacheCurrentSearch = false;
		}
	}

	public function triggerAfterInsertDocument($obj) {
		$config = $this->getConfig();
		$oModel = getModel('supercache');
		if ($config->paging_cache) $oModel->updateDocumentCount($obj->module_srl, $obj->category_srl, 1);
		if ($config->full_cache && $config->full_cache_document_action) {
			if (isset($config->full_cache_document_action['refresh_module']) && $obj->module_srl) {
				$oModel->deleteFullPageCache($obj->module_srl, 0);
			}
			if (isset($config->full_cache_document_action['refresh_index'])) {
				$index_module_srl = Context::get('site_module_info')->index_module_srl ?: 0;
				if ($index_module_srl != $obj->module_srl || !isset($config->full_cache_document_action['refresh_module'])) {
					$oModel->deleteFullPageCache($index_module_srl, 0);
				}
			}
		}
		if ($config->search_cache && $config->search_cache_document_action) {
			if (isset($config->search_cache_document_action['refresh_module']) && $obj->module_srl) {
				$oModel->deleteSearchResultCache($obj->module_srl, false);
			}
		}
		if ($config->widget_cache_autoinvalidate_document && $obj->module_srl) {
			$oModel->invalidateWidgetCache($obj->module_srl);
		}
	}

	public function triggerAfterUpdateDocument($obj) {
		$config = $this->getConfig();
		$original = getModel('document')->getDocument($obj->document_srl);
		$original_module_srl = intval($original->get('module_srl'));
		$original_category_srl = intval($original->get('category_srl'));
		$new_module_srl = intval($obj->module_srl) ?: $original_module_srl;
		$new_category_srl = intval($obj->category_srl) ?: $original_category_srl;
		$oModel = getModel('supercache');
		if ($config->paging_cache && ($original_module_srl !== $new_module_srl || $original_category_srl !== $new_category_srl)) {
			$oModel->updateDocumentCount($new_module_srl, $new_category_srl, 1);
			if ($original_module_srl) {
				$oModel->updateDocumentCount($original_module_srl, $original_category_srl, -1);
			}
		}
		if ($config->full_cache && $config->full_cache_document_action) {
			if (isset($config->full_cache_document_action['refresh_document'])) {
				$oModel->deleteFullPageCache(0, $obj->document_srl);
			}
			if (isset($config->full_cache_document_action['refresh_module']) && $obj->module_srl) {
				$oModel->deleteFullPageCache($original_module_srl, 0);
				if ($original_module_srl !== $new_module_srl) $oModel->deleteFullPageCache($new_module_srl, 0);
			}
			if (isset($config->full_cache_document_action['refresh_index'])) {
				$index_module_srl = Context::get('site_module_info')->index_module_srl ?: 0;
				if (($index_module_srl != $original_module_srl && $index_module_srl != $new_module_srl) || !isset($config->full_cache_document_action['refresh_module'])) {
					$oModel->deleteFullPageCache($index_module_srl, 0);
				}
			}
		}
		if ($config->search_cache && $config->search_cache_document_action) {
			if (isset($config->search_cache_document_action['refresh_module'])) {
				if ($original_module_srl) $oModel->deleteSearchResultCache($original_module_srl, false);
				if ($new_module_srl && ($original_module_srl !== $new_module_srl)) $oModel->deleteSearchResultCache($new_module_srl, false);
			}
		}
		if ($config->widget_cache_autoinvalidate_document) {
			if ($original_module_srl) $oModel->invalidateWidgetCache($original_module_srl);
			if ($new_module_srl && ($original_module_srl !== $new_module_srl)) $oModel->invalidateWidgetCache($new_module_srl);
		}
	}

	public function triggerAfterDeleteDocument($obj) {
		$config = $this->getConfig();
		$oModel = getModel('supercache');
		if ($config->paging_cache) $oModel->updateDocumentCount($obj->module_srl, $obj->category_srl, -1);
		if ($config->full_cache && $config->full_cache_document_action) {
			if (isset($config->full_cache_document_action['refresh_document'])) {
				$oModel->deleteFullPageCache(0, $obj->document_srl);
			}
			if (isset($config->full_cache_document_action['refresh_module']) && $obj->module_srl) {
				$oModel->deleteFullPageCache($obj->module_srl, 0);
			}
			if (isset($config->full_cache_document_action['refresh_index'])) {
				$index_module_srl = Context::get('site_module_info')->index_module_srl ?: 0;
				if ($index_module_srl != $obj->module_srl || !isset($config->full_cache_document_action['refresh_module'])) {
					$oModel->deleteFullPageCache($index_module_srl, 0);
				}
			}
		}
		if ($config->search_cache && $config->search_cache_document_action) {
			if (isset($config->search_cache_document_action['refresh_module']) && $obj->module_srl) {
				$oModel->deleteSearchResultCache($obj->module_srl, false);
			}
		}
		if ($config->widget_cache_autoinvalidate_document && $obj->module_srl) {
			$oModel->invalidateWidgetCache($obj->module_srl);
		}
	}

	public function triggerAfterCopyDocumentModule($obj) {
		$this->triggerAfterUpdateDocument($obj);
	}

	public function triggerAfterMoveDocumentModule($obj) {
		$this->triggerAfterUpdateDocument($obj);
	}

	public function triggerAfterMoveDocumentToTrash($obj) {
		$this->triggerAfterDeleteDocument($obj);
	}

	public function triggerAfterRestoreDocumentFromTrash($obj) {
		$this->triggerAfterUpdateDocument($obj);
	}

	public function triggerAfterInsertComment($obj) {
		$config = $this->getConfig();
		if ($config->full_cache && $config->full_cache_comment_action) {
			$oModel = getModel('supercache');
			if (isset($config->full_cache_comment_action['refresh_document']) && $obj->document_srl) {
				$oModel->deleteFullPageCache(0, $obj->document_srl);
			}
			if (isset($config->full_cache_comment_action['refresh_module']) && $obj->module_srl) {
				$oModel->deleteFullPageCache($obj->module_srl, 0);
			}
			if (isset($config->full_cache_comment_action['refresh_index'])) {
				$index_module_srl = Context::get('site_module_info')->index_module_srl ?: 0;
				if ($index_module_srl != $obj->module_srl || !isset($config->full_cache_comment_action['refresh_module'])) {
					$oModel->deleteFullPageCache($index_module_srl, 0);
				}
			}
		}
		if ($config->search_cache && $config->search_cache_comment_action) {
			if (isset($config->search_cache_comment_action['refresh_module']) && $obj->module_srl) {
				$oModel = isset($oModel) ? $oModel : getModel('supercache');
				$oModel->deleteSearchResultCache($obj->module_srl, true);
			}
		}
		if ($config->widget_cache_autoinvalidate_comment && $obj->module_srl) {
			$oModel = isset($oModel) ? $oModel : getModel('supercache');
			$oModel->invalidateWidgetCache($obj->module_srl);
		}
	}

	public function triggerAfterUpdateComment($obj) {
		$config = $this->getConfig();
		if ($config->full_cache && $config->full_cache_comment_action) {
			$original = getModel('comment')->getComment($obj->comment_srl);
			$document_srl = $obj->document_srl ?: $original->document_srl;
			$module_srl = $obj->module_srl ?: $original->module_srl;
			$oModel = getModel('supercache');
			if (isset($config->full_cache_comment_action['refresh_document']) && $document_srl) {
				$oModel->deleteFullPageCache(0, $document_srl);
			}
			if (isset($config->full_cache_comment_action['refresh_module']) && $module_srl) {
				$oModel->deleteFullPageCache($module_srl, 0);
			}
			if (isset($config->full_cache_comment_action['refresh_index'])) {
				$index_module_srl = Context::get('site_module_info')->index_module_srl ?: 0;
				if ($index_module_srl != $module_srl || !isset($config->full_cache_comment_action['refresh_module'])) {
					$oModel->deleteFullPageCache($index_module_srl, 0);
				}
			}
		}
		if ($config->search_cache && $config->search_cache_comment_action) {
			if (isset($config->search_cache_comment_action['refresh_module'])) {
				$module_srl = isset($module_srl) ? $module_srl : ($obj->module_srl ?: getModel('comment')->getComment($obj->comment_srl)->module_srl);
				if ($module_srl) {
					$oModel = isset($oModel) ? $oModel : getModel('supercache');
					$oModel->deleteSearchResultCache($module_srl, true);
				}
			}
		}
		if ($config->widget_cache_autoinvalidate_comment) {
			$module_srl = isset($module_srl) ? $module_srl : ($obj->module_srl ?: getModel('comment')->getComment($obj->comment_srl)->module_srl);
			if ($module_srl) {
				$oModel = isset($oModel) ? $oModel : getModel('supercache');
				$oModel->invalidateWidgetCache($module_srl);
			}
		}
	}

	public function triggerAfterDeleteComment($obj) {
		$config = $this->getConfig();
		$module_srl = $obj->module_srl ?: (method_exists($obj, 'get') ? $obj->get('module_srl'): 0);
		if ($config->full_cache && $config->full_cache_comment_action) {
			$oModel = getModel('supercache');
			if (isset($config->full_cache_comment_action['refresh_document']) && $obj->document_srl) {
				$oModel->deleteFullPageCache(0, $obj->document_srl);
			}
			if (isset($config->full_cache_comment_action['refresh_module']) && $module_srl) {
				$oModel->deleteFullPageCache($module_srl, 0);
			}
			if (isset($config->full_cache_comment_action['refresh_index'])) {
				$index_module_srl = Context::get('site_module_info')->index_module_srl ?: 0;
				if ($index_module_srl != $module_srl || !isset($config->full_cache_comment_action['refresh_module'])) {
					$oModel->deleteFullPageCache($index_module_srl, 0);
				}
			}
		}
		if ($config->search_cache && $config->search_cache_comment_action) {
			if (isset($config->search_cache_comment_action['refresh_module']) && $module_srl) {
				$oModel = isset($oModel) ? $oModel : getModel('supercache');
				$oModel->deleteSearchResultCache($module_srl, true);
			}
		}
		if ($config->widget_cache_autoinvalidate_comment && $module_srl) {
			$oModel = isset($oModel) ? $oModel : getModel('supercache');
			$oModel->invalidateWidgetCache($module_srl);
		}
	}

	public function triggerAfterModuleHandlerProc($obj) {
		$config = $this->getConfig();
		if ($this->_cacheCurrentRequest) {
			if (!is_object($obj) || !method_exists($obj, 'getHttpStatusCode')) {
				$this->_cacheHttpStatusCode = 404;
			} elseif (($status_code = $obj->getHttpStatusCode()) > 200) {
				$this->_cacheHttpStatusCode = intval($status_code);
			}
			if ($this->_cacheHttpStatusCode >= 300 && $this->_cacheHttpStatusCode <= 399) $this->_cacheCurrentRequest = false;
			if ($this->_cacheHttpStatusCode !== 200 && !$config->full_cache_include_404) $this->_cacheCurrentRequest = false;
		}
		if (is_object($obj) && $gzip = $config->use_gzip) {
			if ($gzip !== 'none' && $gzip !== 'default') {
				if (defined('RX_VERSION') && function_exists('config')) config('view.use_gzip', true);
				elseif (!defined('__OB_GZHANDLER_ENABLE__')) define('__OB_GZHANDLER_ENABLE__', 1);
			}
			switch ($gzip) {
				case 'except_robots': $obj->gzhandler_enable = isCrawler() ? false : true; break;
				case 'except_naver': $obj->gzhandler_enable = preg_match('/(yeti|naver)/i', $_SERVER['HTTP_USER_AGENT']) ? false : true; break;
				case 'none': $obj->gzhandler_enable = false; break;
				case 'always':
				default:
				break;
			}
		}
		if ($this->_cacheCurrentRequest && $this->getConfig()->full_cache['pushapp']) {
			$GLOBALS['__triggers__']['display']['before'] = array_filter($GLOBALS['__triggers__']['display']['before'], function($entry) {
				return $entry->module !== 'androidpushapp';
			});
		}
	}

	public function triggerBeforeDisplay(&$content) {
		$config = $this->getConfig();
		if (!$config->widget_cache || !$config->widget_cache_duration) return;
		if (Context::getResponseMethod() !== 'HTML' || preg_match('/^disp(?:Layout|Page)[A-Z]/', Context::get('act'))) return;
		if (isset($config->widget_cache_exclude_modules[Context::get('module_info')->module_srl])) return;
		$oWidgetController = getController('widget');
		if ($oWidgetController->javascript_mode || $oWidgetController->layout_javascript_mode) return;
		if (!function_exists('simplexml_load_string')) return;
		$content = preg_replace_callback('/<img\b(?:[^>]*?)\bwidget="(?:[^>]*?)>/i', array($this, 'procWidgetCache'), $content);
	}

	public function triggerAfterDisplay($content) {
		if ($this->_cacheCurrentRequest) {
			if ($this->_cacheCurrentRequest[1] && ($oDocument = Context::get('oDocument')) && ($oDocument->document_srl == $this->_cacheCurrentRequest[1])) {
				$extra_data = array(
					'member_srl' => abs(intval($oDocument->get('member_srl'))),
					'view_count' => intval($oDocument->get('readed_count')),
				);
			} else {
				$extra_data = array();
			}
			$trigger_output = ModuleHandler::triggerCall('supercache.storeFullPageCache', 'before', $content);
			if (is_object($trigger_output) && method_exists($trigger_output, 'toBool') && !$trigger_output->toBool()) {
				return $trigger_output;
			}
			getModel('supercache')->setFullPageCache(
				$this->_cacheCurrentRequest[0],
				$this->_cacheCurrentRequest[1],
				$this->_cacheCurrentRequest[2],
				$this->_cacheCurrentRequest[3],
				$content,
				$extra_data,
				$this->_cacheHttpStatusCode,
				microtime(true) - $this->_cacheStartTimestamp
			);
		}
	}

	public function checkFullPageCache($obj, $config) {
		if (Context::getRequestMethod() !== 'GET' || PHP_SAPI === 'cli') return;
		$logged_info = Context::get('logged_info');
		if ($logged_info && $logged_info->member_srl) return;
		if ($config->full_cache_exclude_cookies) {
			foreach ($config->full_cache_exclude_cookies as $key => $value) {
				if (isset($_COOKIE[$key]) && strlen($_COOKIE[$key])) return;
			}
		}
		if (isCrawler() && !isset($config->full_cache['robot'])) return;
		$device_type = $this->getDeviceType();
		if ($device_type === 'pc' && !isset($config->full_cache['pc'])) return;
		if ($device_type !== 'pc' && !isset($config->full_cache['mobile'])) return;
		if (strpos($device_type, 'push') !== false && !isset($config->full_cache['pushapp'])) return;
		$site_module_info = Context::get('site_module_info');
		if (!$this->_defaultUrlChecked) {
			$current_domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
			$default_domain = parse_url(Context::getDefaultUrl(), PHP_URL_HOST);
			if ($current_domain !== $default_domain && $current_domain !== parse_url($site_module_info->domain, PHP_URL_HOST)) return;
		}
		$mid = $obj->mid ?: Context::get('mid');
		$act = Context::get('act');
		$document_srl = Context::get('document_srl');
		$is_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off');
		if (isset($config->full_cache_exclude_acts[$act])) return;
		if (!$obj->mid && !$obj->module && !$obj->module_srl) {
			$module_srl = $site_module_info->module_srl;
		} elseif ($obj->module_srl) {
			$module_srl = $obj->module_srl;
		} elseif ($mid) {
			$module_info = getModel('module')->getModuleInfoByMid($mid, intval($site_module_info->site_srl) ?: 0);
			$module_srl = $module_info ? $module_info->module_srl : 0;
		} else {
			$module_srl = 0;
		}
		$module_srl = intval($module_srl);
		if (isset($config->full_cache_exclude_modules[$module_srl])) return;
		if ($act) $page_type = 'other';
		elseif ($document_srl) $page_type = 'document';
		elseif ($module_srl) $page_type = 'module';
		else $page_type = 'url';
		if (!isset($config->full_cache_type[$page_type]) && !($page_type === 'url' && $config->full_cache_include_404)) return;
		$user_agent_type = $device_type . ($is_secure ? '_secure' : '') . '_' . Context::getLangType();
		$request_vars = Context::getRequestVars();
		if (is_object($request_vars)) $request_vars = get_object_vars($request_vars);
		unset($request_vars['mid'], $request_vars['module'], $request_vars['module_srl'], $request_vars['document_srl'], $request_vars['m']);
		if ($config->full_cache_separate_cookies) {
			foreach ($config->full_cache_separate_cookies as $key => $value) {
				if (isset($_COOKIE[$key]) && strlen($_COOKIE[$key])) {
					$request_vars['_COOKIE'][$key] = strval($_COOKIE[$key]);
				}
			}
		}
		if ($page_type === 'url') {
			$request_vars['_REQUEST_URI'] = $_SERVER['REQUEST_URI'];
			$page_type = 'other';
		}
		$oModel = getModel('supercache');
		switch ($page_type) {
			case 'module':
				$this->_cacheCurrentRequest = array($module_srl, 0, $user_agent_type, $request_vars);
				$cache = $oModel->getFullPageCache($module_srl, 0, $user_agent_type, $request_vars);
			break;
			case 'document':
				$this->_cacheCurrentRequest = array($module_srl, $document_srl, $user_agent_type, $request_vars);
				$cache = $oModel->getFullPageCache($module_srl, $document_srl, $user_agent_type, $request_vars);
			break;
			case 'other':
				$this->_cacheCurrentRequest = array(0, 0, $user_agent_type, $request_vars);
				$cache = $oModel->getFullPageCache(0, 0, $user_agent_type, $request_vars);
			break;
		}
		if ($cache) {
			$trigger_output = ModuleHandler::triggerCall('supercache.fetchFullPageCache', 'after', $cache['content']);
			if (is_object($trigger_output) && method_exists($trigger_output, 'toBool') && !$trigger_output->toBool()) $cache = false;
		}
		if ($cache) {
			$expires = max(0, $cache['expires'] - time());
			header('X-SuperCache: HIT, dev=' . $device_type . ', type=' . $page_type . ', expires=' . $expires);
			if ($this->useCacheControlHeaders($config)) {
				$this->printCacheControlHeaders($expires, $config->full_cache_stampede_protection ? 10 : 0);
			}
			if ($page_type === 'document' && $config->full_cache_incr_view_count && isset($cache['extra_data']['view_count'])) {
				if (!isset($_SESSION['readed_document'][$document_srl])) {
					$oModel->updateDocumentViewCount($document_srl, $cache['extra_data']);
					$_SESSION['readed_document'][$document_srl] = true;
				}
			}
			if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $cache['cached']) {
				$this->printHttpStatusCodeHeader(304);
			} else {
				if ($cache['status'] && $cache['status'] !== 200) $this->printHttpStatusCodeHeader($cache['status']);
				header("Content-Type: text/html; charset=UTF-8");
				echo $cache['content'];
				echo "\n" . '<!--' . "\n";
				echo '    Serving ' . strlen($cache['content']) . ' bytes from full page cache' . "\n";
				echo '    Generated at ' . date('Y-m-d H:i:s P', $cache['cached']) . ' in ' . $cache['elapsed'] . "\n";
				echo '    Cache expires in ' . $expires . ' seconds' . "\n";
				echo '-->' . "\n";
			}
			Context::close();
			exit;
		}
		header('X-SuperCache: MISS, dev=' . $device_type . ', type=' . $page_type . ', expires=' . $config->full_cache_duration);
		if ($this->useCacheControlHeaders($config)) {
			$this->printCacheControlHeaders($config->full_cache_duration, $config->full_cache_stampede_protection ? 10 : 0);
		}
		$this->_cacheStartTimestamp = microtime(true);
	}

	public function printHttpStatusCodeHeader($http_status_code) {
		switch ($http_status_code) {
			case 301: return header('HTTP/1.1 301 Moved Permanently');
			case 302: return header('HTTP/1.1 302 Found');
			case 304: return header('HTTP/1.1 304 Not Modified');
			case 400: return header('HTTP/1.1 400 Bad Request');
			case 403: return header('HTTP/1.1 403 Forbidden');
			case 404: return header('HTTP/1.1 404 Not Found');
			case 500: return header('HTTP/1.1 500 Internal Server Error');
			case 503: return header('HTTP/1.1 503 Service Unavailable');
			default: return function_exists('http_response_code') ? http_response_code($http_status_code) : header(sprintf('HTTP/1.1 %d Internal Server Error', $http_status_code));
		}
	}

	public function printCacheControlHeaders($expires, $scatter) {
		$scatter = intval($expires * ($scatter / 100));
		$expires = intval($expires - mt_rand(0, $scatter));
		header('Cache-Control: max-age=' . $expires);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
		header_remove('Pragma');
	}

	public function useCacheControlHeaders($config) {
		if ($config->full_cache_use_headers) {
			return $config->full_cache_use_headers_proxy_too ? true : !(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']);
		} else {
			return false;
		}
	}

	public function fillPageVariable($obj, $config) {
		if ($obj->mid && Context::get('document_srl') && Context::get('act') && !Context::get('module') && !Context::get('page') && ($referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false)) {
			if (strpos($referer, '//' . $_SERVER['HTTP_HOST'] . '/') === false) {
				return;
			} elseif (preg_match('/\/([a-zA-Z0-9_-]+)(?:\?|(?:\/\d+)?$)/', $referer, $matches) && $matches[1] === $obj->mid) {
				Context::set('page', 1);
			} elseif (preg_match('/\bmid=([a-zA-Z0-9_-]+)\b/', $referer, $matches) && $matches[1] === $obj->mid) {
				if (preg_match('/\bpage=(\d+)\b/', $referer, $matches)) Context::set('page', $matches[1]);
				else Context::set('page', 1);
			}
		}
	}

	public function getFullPageCacheStatus() {
		return $this->_cacheCurrentRequest ? true : false;
	}

	public function getDeviceType() {
		$is_mobile_enabled = Mobile::isMobileEnabled();
		if (!$is_mobile_enabled && isset($_SESSION['supercache_device_type']))
			list($device_type, $checksum) = explode('|', $_SESSION['supercache_device_type']);
			if (strlen($checksum) && ($checksum === md5($_SERVER['HTTP_USER_AGENT']))) return $device_type;
		}
		$is_mobile1 = Mobile::isFromMobilePhone();
		$is_mobile2 = Mobile::isMobileCheckByAgent();
		$is_pushapp = (strpos($_SERVER['HTTP_USER_AGENT'], 'XEPUSH') !== false) ? true : false;
		$is_tablet = ($is_mobile1 || $is_mobile2 || $is_pushapp) ? Mobile::isMobilePadCheckByAgent() : false;
		$device_type = ($is_mobile1 ? 'm' : 'p') . ($is_mobile2 ? 'o' : 'c') . ($is_pushapp ? 'push' : '') . ($is_tablet ? 'tab' : '');
		if (!$is_mobile_enabled && (!method_exists('Context', 'getSessionStatus') || Context::getSessionStatus())) {
			$_SESSION['supercache_device_type'] = sprintf('%s|%s', $device_type, md5($_SERVER['HTTP_USER_AGENT']));
		}
		return $device_type;
	}

	public function procWidgetCache($match) {
		$widget_attrs = new stdClass;
		$widget_xml = @simplexml_load_string($match[0]);
		if (!$widget_xml) return $match[0];
		foreach ($widget_xml->attributes() as $key => $value) {
			if (isset(self::$_skipWidgetAttrs[$key])) {
				$widget_attrs->{$key} = strval($value);
			} else {
				$widget_attrs->{$key} = preg_replace_callback('/%u([0-9a-f]+)/i', function($m) {
					return html_entity_decode('&#x' . $m[1] . ';');
				}, rawurldecode(strval($value)));
			}
		}
		if (!$widget_attrs->widget || isset(self::$_skipWidgetNames[$widget_attrs->widget])) return $match[0];
		$config = $this->getConfig();
		if (!isset($config->widget_config[$widget_attrs->widget]) || !$config->widget_config[$widget_attrs->widget]['enabled']) return $match[0];
		$target_modules = array();
		if ($config->widget_cache_autoinvalidate_document || $config->widget_cache_autoinvalidate_comment) {
			if ($widget_attrs->module_srl) $target_modules[] = intval($widget_attrs->module_srl);
			if ($widget_attrs->module_srls) $target_modules = array_unique($target_modules + array_map('intval', explode(',', $widget_attrs->module_srls)));
		}
		$oModel = getModel('supercache');
		$cache_key = $oModel->getWidgetCacheKey($widget_attrs, $config->widget_config[$widget_attrs->widget]['group'] ? Context::get('logged_info') : false);
		$cache_duration = $config->widget_config[$widget_attrs->widget]['duration'] ?: $config->widget_cache_duration;
		if ($widget_attrs->widget_cache && !$config->widget_config[$widget_attrs->widget]['force']) {
			if (preg_match('/^([0-9\.]+)([smhd])$/i', $widget_attrs->widget_cache, $matches) && $matches[1] > 0) {
				$cache_duration = intval(floatval($matches[1]) * intval(strtr(strtolower($matches[2]), array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400))));
			} else {
				$cache_duration = intval(floatval($widget_attrs->widget_cache) * 60) ?: $cache_duration;
			}
		}
		if ($config->widget_cache_stampede_protection !== false) {
			$cache_duration = intval(($cache_duration * 0.8) + ($cache_duration * (crc32($cache_key) % 256) / 1024));
		}
		$widget_content = $oModel->getWidgetCache($cache_key, $cache_duration);
		if ($widget_content === false) {
			$oWidgetController = getController('widget');
			$oWidget = $oWidgetController->getWidgetObject($widget_attrs->widget);
			if ($oWidget && method_exists($oWidget, 'proc')) {
				$widget_content = $oWidget->proc($widget_attrs);
				getController('module')->replaceDefinedLangCode($widget_content);
				$widget_content = trim($widget_content);
				if ($widget_content !== '') {
					$widget_content = str_replace('<!--#Meta:', '<!--Meta:', $widget_content);
					$oModel->setWidgetCache($cache_key, $cache_duration, $widget_content, $target_modules);
				}
			} else {
				return '';
			}
		}
		$inner_styles = sprintf('padding: %dpx %dpx %dpx %dpx !important;', $widget_attrs->widget_padding_top, $widget_attrs->widget_padding_right, $widget_attrs->widget_padding_bottom, $widget_attrs->widget_padding_left);
		$widget_content = sprintf('<div style="*zoom:1;%s">%s</div>', $inner_styles, $widget_content);
		if ($widget_attrs->widgetstyle) {
			$oWidgetController = isset($oWidgetController) ? $oWidgetController : getController('widget');
			$widget_content = $oWidgetController->compileWidgetStyle($widget_attrs->widgetstyle, $widget_attrs->widget, $widget_content, $widget_attrs, false);
		}
		$outer_styles = preg_replace('/url\((.+)(\/?)none\)/is', '', $widget_attrs->style);
		$output = sprintf('<div class="xe-widget-wrapper %s" %sstyle="%s">%s</div>', $widget_attrs->css_class, $widget_attrs->id, $outer_styles, $widget_content);
		return $output;
	}

	public function terminateRequest($reason = '', $data = array()) {
		$output = new Object;
		$output->add('supercache_terminated', $reason);
		foreach ($data as $key => $value) $output->add($key, $value);
		$oDisplayHandler = new DisplayHandler;
		$oDisplayHandler->printContent($output);
		Context::close();
		exit;
	}

	public function terminateWithPlainText($message = '') {
		header('Content-Type: text/plain; charset=UTF-8');
		echo $message;
		Context::close();
		exit;
	}

	public function terminateRedirectTo($url, $status = 301) {
		$this->printHttpStatusCodeHeader($status);
		header('Location: ' . $url);
		header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
		header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
		header_remove('Pragma');
		Context::close();
		exit;
	}
}
