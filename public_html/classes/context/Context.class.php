<?php
define('FOLLOW_REQUEST_SSL', 0);
define('ENFORCE_SSL', 1);
define('RELEASE_SSL', 2);

class Context {
	public $allow_rewrite = FALSE;
	public $request_method = 'GET';
	public $js_callback_func = '';
	public $response_method = '';
	public $context = NULL;
	public $db_info = NULL;
	public $ftp_info = NULL;
	public $sslActionCacheFile = './files/cache/sslCacheFile.php';
	public $ssl_actions = array();
	public $oFrontEndFileHandler;
	public $html_header = NULL;
	public $body_class = array();
	public $body_header = NULL;
	public $html_footer = NULL;
	public $path = '';
	public $lang_type = '';
	public $lang = NULL;
	public $loaded_lang_files = array();
	public $site_title = '';
	public $get_vars = NULL;
	public $is_uploaded = FALSE;
	public $patterns = array('/<\?/iUsm', '/<\%/iUsm', '/<script\s*?language\s*?=\s*?("|\')?\s*?php\s*("|\')?/iUsm');
	public $isSuccessInit = TRUE;

	function &getInstance() {
		static $theInstance = null;
		if(!$theInstance) $theInstance = new Context();
		return $theInstance;
	}

	function Context() {
		$this->oFrontEndFileHandler = new FrontEndFileHandler();
		$this->get_vars = new stdClass();
		$this->sslActionCacheFile = FileHandler::getRealPath($this->sslActionCacheFile);
		if(is_readable($this->sslActionCacheFile)) {
			require($this->sslActionCacheFile);
			if(isset($sslActions)) $this->ssl_actions = $sslActions;
		}
	}

	function init() {
		if(!isset($GLOBALS['HTTP_RAW_POST_DATA']) && version_compare(PHP_VERSION, '5.6.0', '>=') === TRUE) {
			$GLOBALS['HTTP_RAW_POST_DATA'] = file_get_contents("php://input");
			if(!preg_match('/^[\<\{\[]/', $GLOBALS['HTTP_RAW_POST_DATA']) && strpos($_SERVER['CONTENT_TYPE'], 'json') === FALSE && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'json') === FALSE) unset($GLOBALS['HTTP_RAW_POST_DATA']);
		}
		$this->context = &$GLOBALS['__Context__'];
		$this->context->lang = &$GLOBALS['lang'];
		$this->context->_COOKIE = $_COOKIE;
		$this->_checkGlobalVars();
		$this->setRequestMethod('');
		$this->_setXmlRpcArgument();
		$this->_setJSONRequestArgument();
		$this->_setRequestArgument();
		$this->_setUploadedArgument();
		$this->loadDBInfo();
		if($this->db_info->use_sitelock == 'Y') {
			if(is_array($this->db_info->sitelock_whitelist)) $whitelist = $this->db_info->sitelock_whitelist;
			if(!IpFilter::filter($whitelist)) {
				$title = ($this->db_info->sitelock_title) ? $this->db_info->sitelock_title : 'Maintenance in progress...';
				$message = $this->db_info->sitelock_message;
				define('_XE_SITELOCK_', TRUE);
				define('_XE_SITELOCK_TITLE_', $title);
				define('_XE_SITELOCK_MESSAGE_', $message);
				header("HTTP/1.1 403 Forbidden");
				if(FileHandler::exists(_XE_PATH_ . 'common/tpl/sitelock.user.html')) include _XE_PATH_ . 'common/tpl/sitelock.user.html';
				else include _XE_PATH_ . 'common/tpl/sitelock.html';
				exit;
			}
		}
		if(self::isInstalled()) {
			$oModuleModel = getModel('module');
			$site_module_info = $oModuleModel->getDefaultMid();
			if(!isset($site_module_info)) $site_module_info = new stdClass();
			if($site_module_info->site_srl == 0 && $site_module_info->domain != $this->db_info->default_url) $site_module_info->domain = $this->db_info->default_url;
			$this->set('site_module_info', $site_module_info);
			if($site_module_info->site_srl && isSiteID($site_module_info->domain)) $this->set('vid', $site_module_info->domain, TRUE);
			if(!isset($this->db_info)) $this->db_info = new stdClass();
			$this->db_info->lang_type = $site_module_info->default_language;
			if(!$this->db_info->lang_type) $this->db_info->lang_type = 'en';
			if(!$this->db_info->use_db_session) $this->db_info->use_db_session = 'N';
		}
		$lang_supported = $this->loadLangSelected();
		if($this->lang_type = $this->get('l')) {
			if($_COOKIE['lang_type'] != $this->lang_type) setcookie('lang_type', $this->lang_type, $_SERVER['REQUEST_TIME'] + 3600 * 24 * 1000, '/');
		} elseif($_COOKIE['lang_type']) {
			$this->lang_type = $_COOKIE['lang_type'];
		}
		if(!$this->lang_type) $this->lang_type = $this->db_info->lang_type;
		if(!$this->lang_type) $this->lang_type = 'en';
		if(is_array($lang_supported) && !isset($lang_supported[$this->lang_type])) $this->lang_type = 'en';
		$this->set('lang_supported', $lang_supported);
		$this->setLangType($this->lang_type);
		$this->loadLang(_XE_PATH_ . 'modules/module/lang');
		if(self::isInstalled() && $this->db_info->use_db_session == 'Y') {
			$oSessionModel = getModel('session');
			$oSessionController = getController('session');
			session_set_save_handler(
					array(&$oSessionController, 'open'), array(&$oSessionController, 'close'), array(&$oSessionModel, 'read'), array(&$oSessionController, 'write'), array(&$oSessionController, 'destroy'), array(&$oSessionController, 'gc')
			);
		}
		if($sess = $_POST[session_name()]) session_id($sess);
		session_start();
		if(self::isInstalled()) {
			$oModuleModel = getModel('module');
			$oModuleModel->loadModuleExtends();
			$oMemberModel = getModel('member');
			$oMemberController = getController('member');
			if($oMemberController && $oMemberModel) {
				if($oMemberModel->isLogged()) {
					$oMemberController->setSessionInfo();
				} elseif($_COOKIE['xeak']) {
					$oMemberController->doAutologin();
				}
				$this->set('is_logged', $oMemberModel->isLogged());
				$this->set('logged_info', $oMemberModel->getLoggedInfo());
			}
		}
		$this->lang = &$GLOBALS['lang'];
		$this->loadLang(_XE_PATH_ . 'common/lang/');
		$this->allow_rewrite = ($this->db_info->use_rewrite == 'Y' ? TRUE : FALSE);
		$url = array();
		$current_url = self::getRequestUri();
		if($_SERVER['REQUEST_METHOD'] == 'GET') {
			if($this->get_vars) {
				$url = array();
				foreach($this->get_vars as $key => $val) {
					if(is_array($val) && count($val) > 0) {
						foreach($val as $k => $v) $url[] = $key . '[' . $k . ']=' . urlencode($v);
					} elseif($val) {
						$url[] = $key . '=' . urlencode($val);
					}
				}
				$current_url = self::getRequestUri();
				if($url) $current_url .= '?' . join('&', $url);
			} else {
				$current_url = $this->getUrl();
			}
		} else {
			$current_url = self::getRequestUri();
		}
		$this->set('current_url', $current_url);
		$this->set('request_uri', self::getRequestUri());
		if(strpos($current_url, 'xn--') !== FALSE) $this->set('current_url', self::decodeIdna($current_url));
		if(strpos(self::getRequestUri(), 'xn--') !== FALSE) $this->set('request_uri', self::decodeIdna(self::getRequestUri()));
	}

	function close() {
		session_write_close();
	}

	function loadDBInfo() {
		$self = self::getInstance();
		if(!$self->isInstalled()) return;
		$config_file = $self->getConfigFile();
		if(is_readable($config_file)) include($config_file);
		if(!isset($db_info->master_db)) {
			$db_info->master_db = array();
			$db_info->master_db["db_type"] = $db_info->db_type;
			unset($db_info->db_type);
			$db_info->master_db["db_port"] = $db_info->db_port;
			unset($db_info->db_port);
			$db_info->master_db["db_hostname"] = $db_info->db_hostname;
			unset($db_info->db_hostname);
			$db_info->master_db["db_password"] = $db_info->db_password;
			unset($db_info->db_password);
			$db_info->master_db["db_database"] = $db_info->db_database;
			unset($db_info->db_database);
			$db_info->master_db["db_userid"] = $db_info->db_userid;
			unset($db_info->db_userid);
			$db_info->master_db["db_table_prefix"] = $db_info->db_table_prefix;
			unset($db_info->db_table_prefix);
			if(isset($db_info->master_db["db_table_prefix"]) && substr_compare($db_info->master_db["db_table_prefix"], '_', -1) !== 0) $db_info->master_db["db_table_prefix"] .= '_';
			$db_info->slave_db = array($db_info->master_db);
			$self->setDBInfo($db_info);
			$oInstallController = getController('install');
			$oInstallController->makeConfigFile();
		}

		if(!$db_info->use_prepared_statements) $db_info->use_prepared_statements = 'Y';
		if(!$db_info->time_zone) $db_info->time_zone = date('O');
		$GLOBALS['_time_zone'] = $db_info->time_zone;
		if($db_info->qmail_compatibility != 'Y') $db_info->qmail_compatibility = 'N';
		$GLOBALS['_qmail_compatibility'] = $db_info->qmail_compatibility;
		if(!$db_info->use_db_session) $db_info->use_db_session = 'N';
		if(!$db_info->use_ssl) $db_info->use_ssl = 'none';
		$this->set('_use_ssl', $db_info->use_ssl);
		$self->set('_http_port', ($db_info->http_port) ? $db_info->http_port : NULL);
		$self->set('_https_port', ($db_info->https_port) ? $db_info->https_port : NULL);
		if(!$db_info->sitelock_whitelist) $db_info->sitelock_whitelist = '127.0.0.1';
		if(is_string($db_info->sitelock_whitelist)) $db_info->sitelock_whitelist = explode(',', $db_info->sitelock_whitelist);
		$self->setDBInfo($db_info);
	}

	function getDBType() {
		$self = self::getInstance();
		return $self->db_info->master_db["db_type"];
	}

	function setDBInfo($db_info) {
		$self = self::getInstance();
		$self->db_info = $db_info;
	}

	function getDBInfo() {
		$self = self::getInstance();
		return $self->db_info;
	}

	function getSslStatus() {
		$dbInfo = self::getDBInfo();
		return $dbInfo->use_ssl;
	}

	function getDefaultUrl() {
		$db_info = self::getDBInfo();
		return $db_info->default_url;
	}

	function loadLangSupported() {
		static $lang_supported = null;
		if(!$lang_supported) {
			$langs = file(_XE_PATH_ . 'common/lang/lang.info');
			foreach($langs as $val) {
				list($lang_prefix, $lang_text) = explode(',', $val);
				$lang_text = trim($lang_text);
				$lang_supported[$lang_prefix] = $lang_text;
			}
		}
		return $lang_supported;
	}

	function loadLangSelected() {
		static $lang_selected = null;
		if(!$lang_selected) {
			$orig_lang_file = _XE_PATH_ . 'common/lang/lang.info';
			$selected_lang_file = _XE_PATH_ . 'files/config/lang_selected.info';
			if(!FileHandler::hasContent($selected_lang_file)) {
				$old_selected_lang_file = _XE_PATH_ . 'files/cache/lang_selected.info';
				FileHandler::moveFile($old_selected_lang_file, $selected_lang_file);
			}
			if(!FileHandler::hasContent($selected_lang_file)) {
				$buff = FileHandler::readFile($orig_lang_file);
				FileHandler::writeFile($selected_lang_file, $buff);
				$lang_selected = self::loadLangSupported();
			} else {
				$langs = file($selected_lang_file);
				foreach($langs as $val) {
					list($lang_prefix, $lang_text) = explode(',', $val);
					$lang_text = trim($lang_text);
					$lang_selected[$lang_prefix] = $lang_text;
				}
			}
		}
		return $lang_selected;
	}

	function checkSSO() {
		if($this->db_info->use_sso != 'Y' || isCrawler()) return TRUE;
		$checkActList = array('rss' => 1, 'atom' => 1);
		if(self::getRequestMethod() != 'GET' || !self::isInstalled() || isset($checkActList[self::get('act')])) return TRUE;
		$default_url = trim($this->db_info->default_url);
		if(!$default_url) return TRUE;
		if(substr_compare($default_url, '/', -1) !== 0) $default_url .= '/';
		if($default_url == self::getRequestUri()) {
			if(self::get('url')) {
				$url = base64_decode(self::get('url'));
				$url_info = parse_url($url);
				if(!Password::checkSignature($url, self::get('sig'))) {
					echo self::get('lang')->msg_invalid_request;
					return false;
				}
				$url_info['query'].= ($url_info['query'] ? '&' : '') . 'SSOID=' . urlencode(session_id()) . '&sig=' . urlencode(Password::createSignature(session_id()));
				$redirect_url = sprintf('%s://%s%s%s?%s', $url_info['scheme'], $url_info['host'], $url_info['port'] ? ':' . $url_info['port'] : '', $url_info['path'], $url_info['query']);
				header('location:' . $redirect_url);
				return FALSE;
			}
		} else {
			if($session_name = self::get('SSOID')) {
				if(!Password::checkSignature($session_name, self::get('sig'))) {
					echo self::get('lang')->msg_invalid_request;
					return false;
				}
				setcookie(session_name(), $session_name);
				$url = preg_replace('/[\?\&]SSOID=.+$/', '', self::getRequestUrl());
				header('location:' . $url);
				return FALSE;
			} else if(!self::get('SSOID') && $_COOKIE['sso'] != md5(self::getRequestUri())) {
				setcookie('sso', md5(self::getRequestUri()), 0, '/');
				$origin_url = self::getRequestUrl();
				$origin_sig = Password::createSignature($origin_url);
				$url = sprintf("%s?url=%s&sig=%s", $default_url, urlencode(base64_encode($origin_url)), urlencode($origin_sig));
				header('location:' . $url);
				return FALSE;
			}
		}
		return TRUE;
	}

	function isFTPRegisted() {
		return file_exists(self::getFTPConfigFile());
	}

	function getFTPInfo() {
		$self = self::getInstance();
		if(!$self->isFTPRegisted()) return null;
		include($self->getFTPConfigFile());
		return $ftp_info;
	}

	function addBrowserTitle($site_title) {
		if(!$site_title) return;
		$self = self::getInstance();
		if($self->site_title) {
			$self->site_title .= ' - ' . $site_title;
		} else {
			$self->site_title = $site_title;
		}
	}

	function setBrowserTitle($site_title) {
		if(!$site_title) return;
		$self = self::getInstance();
		$self->site_title = $site_title;
	}

	function getBrowserTitle() {
		$self = self::getInstance();
		$oModuleController = getController('module');
		$oModuleController->replaceDefinedLangCode($self->site_title);
		return htmlspecialchars($self->site_title, ENT_COMPAT | ENT_HTML401, 'UTF-8', FALSE);
	}

	public function getSiteTitle() {
		$oModuleModel = getModel('module');
		$moduleConfig = $oModuleModel->getModuleConfig('module');
		if(isset($moduleConfig->siteTitle)) return $moduleConfig->siteTitle;
		return '';
	}

	function _getBrowserTitle() {
		return $this->getBrowserTitle();
	}

	function loadLang($path) {
		global $lang;
		$self = self::getInstance();
		if(!$self->lang_type) return;
		if(!is_object($lang)) $lang = new stdClass;
		if(!($filename = $self->_loadXmlLang($path))) $filename = $self->_loadPhpLang($path);
		if(!is_array($self->loaded_lang_files)) $self->loaded_lang_files = array();
		if(in_array($filename, $self->loaded_lang_files)) return;
		if($filename && is_readable($filename)) {
			$self->loaded_lang_files[] = $filename;
			include($filename);
		} else {
			$self->_evalxmlLang($path);
		}
	}

	function _evalxmlLang($path) {
		global $lang;
		if(!$path) return;
		$_path = 'eval://' . $path;
		if(in_array($_path, $this->loaded_lang_files)) return;
		if(substr_compare($path, '/', -1) !== 0) $path .= '/';
		$oXmlLangParser = new XmlLangParser($path . 'lang.xml', $this->lang_type);
		$content = $oXmlLangParser->getCompileContent();
		if($content) {
			$this->loaded_lang_files[] = $_path;
			eval($content);
		}
	}

	function _loadXmlLang($path) {
		if(!$path) return;
		$oXmlLangParser = new XmlLangParser($path . ((substr_compare($path, '/', -1) !== 0) ? '/' : '') . 'lang.xml', $this->lang_type);
		return $oXmlLangParser->compile();
	}

	function _loadPhpLang($path) {
		if(!$path) return;
		if(substr_compare($path, '/', -1) !== 0) $path .= '/';
		$path_tpl = $path . '%s.lang.php';
		$file = sprintf($path_tpl, $this->lang_type);
		$langs = array('ko', 'en');
		while(!is_readable($file) && $langs[0]) {
			$file = sprintf($path_tpl, array_shift($langs));
		}
		if(!is_readable($file)) return FALSE;
		return $file;
	}

	function setLangType($lang_type = 'ko') {
		$self = self::getInstance();
		$self->lang_type = $lang_type;
		$self->set('lang_type', $lang_type);
		$_SESSION['lang_type'] = $lang_type;
	}

	function getLangType() {
		$self = self::getInstance();
		return $self->lang_type;
	}

	function getLang($code) {
		if(!$code) return;
		if($GLOBALS['lang']->{$code}) return $GLOBALS['lang']->{$code};
		return $code;
	}

	function setLang($code, $val) {
		if(!isset($GLOBALS['lang'])) $GLOBALS['lang'] = new stdClass();
		$GLOBALS['lang']->{$code} = $val;
	}

	function convertEncoding($source_obj) {
		$charset_list = array(
			'UTF-8', 'EUC-KR', 'CP949', 'ISO8859-1', 'EUC-JP', 'SHIFT_JIS', 'CP932',
			'EUC-CN', 'HZ', 'GBK', 'GB18030', 'EUC-TW', 'BIG5', 'CP950', 'BIG5-HKSCS',
			'ISO2022-CN', 'ISO2022-CN-EXT', 'ISO2022-JP', 'ISO2022-JP-2', 'ISO2022-JP-1',
			'ISO8859-6', 'ISO8859-8', 'JOHAB', 'ISO2022-KR', 'CP1255', 'CP1256', 'CP862',
			'ASCII', 'ISO8859-1', 'ISO8850-2', 'ISO8850-3', 'ISO8850-4', 'ISO8850-5',
			'ISO8850-7', 'ISO8850-9', 'ISO8850-10', 'ISO8850-13', 'ISO8850-14',
			'ISO8850-15', 'ISO8850-16', 'CP1250', 'CP1251', 'CP1252', 'CP1253', 'CP1254',
			'CP1257', 'CP850', 'CP866',
		);
		$obj = clone $source_obj;
		foreach($charset_list as $charset) {
			array_walk($obj,'Context::checkConvertFlag',$charset);
			$flag = self::checkConvertFlag($flag = TRUE);
			if($flag) {
				if($charset == 'UTF-8') return $obj;
				array_walk($obj,'Context::doConvertEncoding',$charset);
				return $obj;
			}
		}
		return $obj;
	}

	function checkConvertFlag(&$val, $key = null, $charset = null) {
		static $flag = TRUE;
		if($charset) {
			if(is_array($val)) array_walk($val,'Context::checkConvertFlag',$charset);
			else if($val && iconv($charset,$charset,$val)!=$val) $flag = FALSE;
			else $flag = FALSE;
		} else {
			$return = $flag;
			$flag = TRUE;
			return $return;
		}
	}

	function doConvertEncoding(&$val, $key = null, $charset) {
		if (is_array($val)) array_walk($val,'Context::doConvertEncoding',$charset);
		else $val = iconv($charset,'UTF-8',$val);
	}

	function convertEncodingStr($str) {
        if(!$str) return null;
		$obj = new stdClass();
		$obj->str = $str;
		$obj = self::convertEncoding($obj);
		return $obj->str;
	}

	function decodeIdna($domain) {
		if(strpos($domain, 'xn--') !== FALSE) {
			require_once(_XE_PATH_ . 'libs/idna_convert/idna_convert.class.php');
			$IDN = new idna_convert(array('idn_version' => 2008));
			$domain = $IDN->decode($domain);
		}
		return $domain;
	}

	function setResponseMethod($method = 'HTML') {
		$self = self::getInstance();
		$methods = array('HTML' => 1, 'XMLRPC' => 1, 'JSON' => 1, 'JS_CALLBACK' => 1);
		$self->response_method = isset($methods[$method]) ? $method : 'HTML';
	}

	function getResponseMethod() {
		$self = self::getInstance();
		if($self->response_method) return $self->response_method;
		$method = $self->getRequestMethod();
		$methods = array('HTML' => 1, 'XMLRPC' => 1, 'JSON' => 1, 'JS_CALLBACK' => 1);
		return isset($methods[$method]) ? $method : 'HTML';
	}

	function setRequestMethod($type = '') {
		$self = self::getInstance();
		$self->js_callback_func = $self->getJSCallbackFunc();
		($type && $self->request_method = $type) or
			((strpos($_SERVER['CONTENT_TYPE'], 'json') || strpos($_SERVER['HTTP_CONTENT_TYPE'], 'json')) && $self->request_method = 'JSON') or
			($GLOBALS['HTTP_RAW_POST_DATA'] && $self->request_method = 'XMLRPC') or
			($self->js_callback_func && $self->request_method = 'JS_CALLBACK') or
			($self->request_method = $_SERVER['REQUEST_METHOD']);
	}

	function _checkGlobalVars() {
		$this->_recursiveCheckVar($_SERVER['HTTP_HOST']);
		$pattern = "/[\,\"\'\{\}\[\]\(\);$]/";
		if(preg_match($pattern, $_SERVER['HTTP_HOST'])) $this->isSuccessInit = FALSE;
	}

	function _setRequestArgument() {
		if(!count($_REQUEST)) return;
		$requestMethod = $this->getRequestMethod();
		foreach($_REQUEST as $key => $val) {
			if($val === '' || self::get($key)) continue;
			$key = htmlentities($key);
			$val = $this->_filterRequestVar($key, $val);
			if($requestMethod == 'GET' && isset($_GET[$key])) $set_to_vars = TRUE;
			elseif($requestMethod == 'POST' && isset($_POST[$key])) $set_to_vars = TRUE;
			elseif($requestMethod == 'JS_CALLBACK' && (isset($_GET[$key]) || isset($_POST[$key]))) $set_to_vars = TRUE;
			else $set_to_vars = FALSE;
			if($set_to_vars) $this->_recursiveCheckVar($val);
			$this->set($key, $val, $set_to_vars);
		}
	}

	function _recursiveCheckVar($val) {
		if(is_string($val)) {
			foreach($this->patterns as $pattern) {
				if(preg_match($pattern, $val)) {
					$this->isSuccessInit = FALSE;
					return;
				}
			}
		} else if(is_array($val)) {
			foreach($val as $val2) $this->_recursiveCheckVar($val2);
		}
	}

	function _setJSONRequestArgument() {
		if($this->getRequestMethod() != 'JSON') return;
		$params = array();
		parse_str($GLOBALS['HTTP_RAW_POST_DATA'], $params);
		foreach($params as $key => $val) $this->set($key, $this->_filterRequestVar($key, $val, 1), TRUE);
	}

	function _setXmlRpcArgument() {
		if($this->getRequestMethod() != 'XMLRPC') return;
		$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
		if(Security::detectingXEE($xml)) {
			header("HTTP/1.0 400 Bad Request");
			exit;
		}
		$oXml = new XmlParser();
		$xml_obj = $oXml->parse($xml);
		$params = $xml_obj->methodcall->params;
		unset($params->node_name, $params->attrs, $params->body);
		if(!count(get_object_vars($params))) return;
		foreach($params as $key => $val) $this->set($key, $this->_filterXmlVars($key, $val), TRUE);
	}

	function _filterXmlVars($key, $val) {
		if(is_array($val)) {
			$stack = array();
			foreach($val as $k => $v) $stack[$k] = $this->_filterXmlVars($k, $v);
			return $stack;
		}
		$body = $val->body;
		unset($val->node_name, $val->attrs, $val->body);
		if(!count(get_object_vars($val))) return $this->_filterRequestVar($key, $body, 0);
		$stack = new stdClass();
		foreach($val as $k => $v) {
			$output = $this->_filterXmlVars($k, $v);
			if(is_object($v) && $v->attrs->type == 'array') $output = array($output);
			if($k == 'value' && (is_array($v) || $v->attrs->type == 'array')) return $output;
			$stack->{$k} = $output;
		}
		if(!count(get_object_vars($stack))) return NULL;
		return $stack;
	}

	function _filterRequestVar($key, $val, $do_stripslashes = 1) {
		if(!($isArray = is_array($val))) $val = array($val);
		$result = array();
		foreach($val as $k => $v) {
			$k = htmlentities($k);
			if($key === 'page' || $key === 'cpage' || substr_compare($key, 'srl', -3) === 0) {
				$result[$k] = !preg_match('/^[0-9,]+$/', $v) ? (int) $v : $v;
			} elseif($key === 'mid' || $key === 'search_keyword') {
				$result[$k] = htmlspecialchars($v, ENT_COMPAT | ENT_HTML401, 'UTF-8', FALSE);
			} elseif($key === 'vid') {
				$result[$k] = urlencode($v);
			} else {
				$result[$k] = $v;
				if($do_stripslashes && version_compare(PHP_VERSION, '5.4.0', '<') && get_magic_quotes_gpc()) $result[$k] = stripslashes($result[$k]);
				if(!is_array($result[$k])) $result[$k] = trim($result[$k]);
			}
		}
		return $isArray ? $result : $result[0];
	}

	function isUploaded() {
		$self = self::getInstance();
		return $self->is_uploaded;
	}

	function _setUploadedArgument() {
		if($_SERVER['REQUEST_METHOD'] != 'POST' || !$_FILES || (stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === FALSE && stripos($_SERVER['HTTP_CONTENT_TYPE'], 'multipart/form-data') === FALSE)) return;
		foreach($_FILES as $key => $val) {
			$tmp_name = $val['tmp_name'];
			if(!is_array($tmp_name)) {
				if(!$tmp_name || !is_uploaded_file($tmp_name)) continue;
				$val['name'] = htmlspecialchars($val['name'], ENT_COMPAT | ENT_HTML401, 'UTF-8', FALSE);
				$this->set($key, $val, TRUE);
				$this->is_uploaded = TRUE;
			} else {
				for($i = 0, $c = count($tmp_name); $i < $c; $i++) {
					if($val['size'][$i] > 0) {
						$file['name'] = $val['name'][$i];
						$file['type'] = $val['type'][$i];
						$file['tmp_name'] = $val['tmp_name'][$i];
						$file['error'] = $val['error'][$i];
						$file['size'] = $val['size'][$i];
						$files[] = $file;
					}
				}
				$this->set($key, $files, TRUE);
			}
		}
	}

	function getRequestMethod() {
		$self = self::getInstance();
		return $self->request_method;
	}

	function getRequestUrl() {
		static $url = null;
		if(is_null($url)) {
			$url = self::getRequestUri();
			if(count($_GET) > 0) {
				foreach($_GET as $key => $val) $vars[] = $key . '=' . ($val ? urlencode(self::convertEncodingStr($val)) : '');
				$url .= '?' . join('&', $vars);
			}
		}
		return $url;
	}

	function getJSCallbackFunc() {
		$self = self::getInstance();
		$js_callback_func = isset($_GET['xe_js_callback']) ? $_GET['xe_js_callback'] : $_POST['xe_js_callback'];
		if(!preg_match('/^[a-z0-9\.]+$/i', $js_callback_func)) {
			unset($js_callback_func);
			unset($_GET['xe_js_callback']);
			unset($_POST['xe_js_callback']);
		}
		return $js_callback_func;
	}

	function getUrl($num_args = 0, $args_list = array(), $domain = null, $encode = TRUE, $autoEncode = FALSE) {
		static $site_module_info = null;
		static $current_info = null;
		$self = self::getInstance();
		if(is_null($site_module_info)) $site_module_info = self::get('site_module_info');
		if($domain && isSiteID($domain)) {
			$vid = $domain;
			$domain = '';
		}
		if(!$domain && !$vid) {
			if($site_module_info->domain && isSiteID($site_module_info->domain)) $vid = $site_module_info->domain;
			else $domain = $site_module_info->domain;
		}
		if($domain) {
			$domain_info = parse_url($domain);
			if(is_null($current_info))
				$current_info = parse_url(($_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . getScriptPath());
			if($domain_info['host'] . $domain_info['path'] == $current_info['host'] . $current_info['path']) {
				unset($domain);
			} else {
				$domain = preg_replace('/^(http|https):\/\//i', '', trim($domain));
				if(substr_compare($domain, '/', -1) !== 0) $domain .= '/';
			}
		}
		$get_vars = array();
		if(!$self->get_vars || $args_list[0] == '') {
			if(is_array($args_list) && $args_list[0] == '') array_shift($args_list);
		} else {
			$get_vars = get_object_vars($self->get_vars);
		}
		for($i = 0, $c = count($args_list); $i < $c; $i += 2) {
			$key = $args_list[$i];
			$val = trim($args_list[$i + 1]);
			if(!isset($val) || !strlen($val)) {
				unset($get_vars[$key]);
				continue;
			}
			$get_vars[$key] = $val;
		}
		unset($get_vars['rnd']);
		if($vid) $get_vars['vid'] = $vid;
		else unset($get_vars['vid']);
		$act = $get_vars['act'];
		$act_alias = array(
			'dispMemberFriend' => 'dispCommunicationFriend',
			'dispMemberMessages' => 'dispCommunicationMessages',
			'dispDocumentAdminManageDocument' => 'dispDocumentManageDocument',
			'dispModuleAdminSelectList' => 'dispModuleSelectList'
		);
		if($act_alias[$act]) $get_vars['act'] = $act_alias[$act];
		$query = '';
		if(count($get_vars) > 0) {
			if($self->allow_rewrite) {
				$var_keys = array_keys($get_vars);
				sort($var_keys);
				$target = join('.', $var_keys);
				$act = $get_vars['act'];
				$vid = $get_vars['vid'];
				$mid = $get_vars['mid'];
				$key = $get_vars['key'];
				$srl = $get_vars['document_srl'];
				$tmpArray = array('rss' => 1, 'atom' => 1, 'api' => 1);
				$is_feed = isset($tmpArray[$act]);
				$target_map = array(
					'vid' => $vid,
					'mid' => $mid,
					'mid.vid' => "$vid/$mid",
					'entry.mid' => "$mid/entry/" . $get_vars['entry'],
					'entry.mid.vid' => "$vid/$mid/entry/" . $get_vars['entry'],
					'document_srl' => $srl,
					'document_srl.mid' => "$mid/$srl",
					'document_srl.vid' => "$vid/$srl",
					'document_srl.mid.vid' => "$vid/$mid/$srl",
					'act' => ($is_feed && $act !== 'api') ? $act : '',
					'act.mid' => $is_feed ? "$mid/$act" : '',
					'act.mid.vid' => $is_feed ? "$vid/$mid/$act" : '',
					'act.document_srl.key' => ($act == 'trackback') ? "$srl/$key/$act" : '',
					'act.document_srl.key.mid' => ($act == 'trackback') ? "$mid/$srl/$key/$act" : '',
					'act.document_srl.key.vid' => ($act == 'trackback') ? "$vid/$srl/$key/$act" : '',
					'act.document_srl.key.mid.vid' => ($act == 'trackback') ? "$vid/$mid/$srl/$key/$act" : ''
				);
				$query = $target_map[$target];
			}
			if(!$query) {
				$queries = array();
				foreach($get_vars as $key => $val) {
					if(is_array($val) && count($val) > 0) {
						foreach($val as $k => $v) $queries[] = $key . '[' . $k . ']=' . urlencode($v);
					} elseif(!is_array($val)) {
						$queries[] = $key . '=' . urlencode($val);
					}
				}
				if(count($queries) > 0) $query = 'index.php?' . join('&', $queries);
			}
		}
		$_use_ssl = $self->get('_use_ssl');
		if($_use_ssl == 'always') {
			$query = $self->getRequestUri(ENFORCE_SSL, $domain) . $query;
		} elseif($_use_ssl == 'optional') {
			$ssl_mode = (($self->get('module') === 'admin') || ($get_vars['module'] === 'admin') || (isset($get_vars['act']) && $self->isExistsSSLAction($get_vars['act']))) ? ENFORCE_SSL : RELEASE_SSL;
			$query = $self->getRequestUri($ssl_mode, $domain) . $query;
		} else {
			if($_SERVER['HTTPS'] == 'on') $query = $self->getRequestUri(ENFORCE_SSL, $domain) . $query;
			else if($domain) $query = $self->getRequestUri(FOLLOW_REQUEST_SSL, $domain) . $query;
			else $query = getScriptPath() . $query;
		}
		if(!$encode) return $query;
		if(!$autoEncode) return htmlspecialchars($query, ENT_COMPAT | ENT_HTML401, 'UTF-8', FALSE);
		$output = array();
		$encode_queries = array();
		$parsedUrl = parse_url($query);
		parse_str($parsedUrl['query'], $output);
		foreach($output as $key => $value) {
			if(preg_match('/&([a-z]{2,}|#\d+);/', urldecode($value))) $value = urlencode(htmlspecialchars_decode(urldecode($value)));
			$encode_queries[] = $key . '=' . $value;
		}
		return htmlspecialchars($parsedUrl['path'] . '?' . join('&', $encode_queries), ENT_COMPAT | ENT_HTML401, 'UTF-8', FALSE);
	}

	function getRequestUri($ssl_mode = FOLLOW_REQUEST_SSL, $domain = null) {
		static $url = array();
		if(!isset($_SERVER['SERVER_PROTOCOL'])) return;
		if(self::get('_use_ssl') == 'always') $ssl_mode = ENFORCE_SSL;
		if($domain) $domain_key = md5($domain);
		else $domain_key = 'default';
		if(isset($url[$ssl_mode][$domain_key])) return $url[$ssl_mode][$domain_key];
		$current_use_ssl = ($_SERVER['HTTPS'] == 'on');
		switch($ssl_mode) {
			case FOLLOW_REQUEST_SSL: $use_ssl = $current_use_ssl; break;
			case ENFORCE_SSL: $use_ssl = TRUE; break;
			case RELEASE_SSL: $use_ssl = FALSE; break;
		}
		if($domain) {
			$target_url = trim($domain);
			if(substr_compare($target_url, '/', -1) !== 0) $target_url.= '/';
		} else {
			$target_url = $_SERVER['HTTP_HOST'] . getScriptPath();
		}
		$url_info = parse_url('http://' . $target_url);
		if($current_use_ssl != $use_ssl) unset($url_info['port']);
		if($use_ssl) {
			$port = self::get('_https_port');
			if($port && $port != 443) $url_info['port'] = $port;
			elseif($url_info['port'] == 443) unset($url_info['port']);
		} else {
			$port = self::get('_http_port');
			if($port && $port != 80) $url_info['port'] = $port;
			elseif($url_info['port'] == 80) unset($url_info['port']);
		}
		$url[$ssl_mode][$domain_key] = sprintf('%s://%s%s%s', $use_ssl ? 'https' : $url_info['scheme'], $url_info['host'], $url_info['port'] && $url_info['port'] != 80 ? ':' . $url_info['port'] : '', $url_info['path']);
		return $url[$ssl_mode][$domain_key];
	}

	function set($key, $val, $set_to_get_vars = 0) {
		$self = self::getInstance();
		$self->context->{$key} = $val;
		if($set_to_get_vars === FALSE) return;
		if($val === NULL || $val === '') {
			unset($self->get_vars->{$key});
			return;
		}
		if($set_to_get_vars || $self->get_vars->{$key}) $self->get_vars->{$key} = $val;
	}

	function get($key) {
		$self = self::getInstance();
		if(!isset($self->context->{$key})) return null;
		return $self->context->{$key};
	}

	function gets() {
		$num_args = func_num_args();
		if($num_args < 1) return;
		$self = self::getInstance();
		$args_list = func_get_args();
		$output = new stdClass();
		foreach($args_list as $v) $output->{$v} = $self->get($v);
		return $output;
	}

	function getAll() {
		$self = self::getInstance();
		return $self->context;
	}

	function getRequestVars() {
		$self = self::getInstance();
		if($self->get_vars) return clone($self->get_vars);
		return new stdClass;
	}

	function addSSLAction($action) {
		$self = self::getInstance();
		if(!is_readable($self->sslActionCacheFile)) {
			$buff = '<?php if(!defined("__XE__"))exit;';
			FileHandler::writeFile($self->sslActionCacheFile, $buff);
		}
		if(!isset($self->ssl_actions[$action])) {
			$self->ssl_actions[$action] = 1;
			$sslActionCacheString = sprintf('$sslActions[\'%s\'] = 1;', $action);
			FileHandler::writeFile($self->sslActionCacheFile, $sslActionCacheString, 'a');
		}
	}

	function addSSLActions($action_array) {
		$self = self::getInstance();
		if(!is_readable($self->sslActionCacheFile)) {
			unset($self->ssl_actions);
			$buff = '<?php if(!defined("__XE__"))exit;';
			FileHandler::writeFile($self->sslActionCacheFile, $buff);
		}
		foreach($action_array as $action) {
			if(!isset($self->ssl_actions[$action])) {
				$self->ssl_actions[$action] = 1;
				$sslActionCacheString = sprintf('$sslActions[\'%s\'] = 1;', $action);
				FileHandler::writeFile($self->sslActionCacheFile, $sslActionCacheString, 'a');
			}
		}
	}

	function subtractSSLAction($action) {
		$self = self::getInstance();
		if($self->isExistsSSLAction($action)) {
			$sslActionCacheString = sprintf('$sslActions[\'%s\'] = 1;', $action);
			$buff = FileHandler::readFile($self->sslActionCacheFile);
			$buff = str_replace($sslActionCacheString, '', $buff);
			FileHandler::writeFile($self->sslActionCacheFile, $buff);
		}
	}

	function getSSLActions() {
		$self = self::getInstance();
		if($self->getSslStatus() == 'optional') return $self->ssl_actions;
	}

	function isExistsSSLAction($action) {
		$self = self::getInstance();
		return isset($self->ssl_actions[$action]);
	}

	function normalizeFilePath($file) {
		if($file{0} != '/' && $file{0} != '.' && strpos($file, '://') === FALSE) $file = './' . $file;
		$file = preg_replace('@/\./|(?<!:)\/\/@', '/', $file);
		while(strpos($file, '/../') !== FALSE) $file = preg_replace('/\/([^\/]+)\/\.\.\//s', '/', $file, 1);
		return $file;
	}

	function getAbsFileUrl($file) {
		$file = self::normalizeFilePath($file);
		$script_path = getScriptPath();
		if(strpos($file, './') === 0) $file = $script_path . substr($file, 2);
		elseif(strpos($file, '../') === 0) $file = self::normalizeFilePath($script_path . $file);
		return $file;
	}

	function loadFile($args) {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->loadFile($args);
	}

	function unloadFile($file, $targetIe = '', $media = 'all') {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->unloadFile($file, $targetIe, $media);
	}

	function unloadAllFiles($type = 'all') {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->unloadAllFiles($type);
	}

	function addJsFile($file, $optimized = FALSE, $targetie = '', $index = 0, $type = 'head', $isRuleset = FALSE, $autoPath = null) {
		if($isRuleset) {
			if(strpos($file, '#') !== FALSE) {
				$file = str_replace('#', '', $file);
				if(!is_readable($file)) $file = $autoPath;
			}
			$validator = new Validator($file);
			$validator->setCacheDir('files/cache');
			$file = $validator->getJsPath();
		}
		$self = self::getInstance();
		$self->oFrontEndFileHandler->loadFile(array($file, $type, $targetie, $index));
	}

	function unloadJsFile($file, $optimized = FALSE, $targetie = '') {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->unloadFile($file, $targetie);
	}

	function unloadAllJsFiles() {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->unloadAllFiles('js');
	}

	function addJsFilter($path, $filename) {
		$oXmlFilter = new XmlJSFilter($path, $filename);
		$oXmlFilter->compile();
	}

	function _getUniqueFileList($files) {
		ksort($files);
		$files = array_values($files);
		$filenames = array();
		for($i = 0, $c = count($files); $i < $c; ++$i) {
			if(in_array($files[$i]['file'], $filenames)) unset($files[$i]);
			$filenames[] = $files[$i]['file'];
		}
		return $files;
	}

	function getJsFile($type = 'head') {
		$self = self::getInstance();
		return $self->oFrontEndFileHandler->getJsFileList($type);
	}

	function addCSSFile($file, $optimized = FALSE, $media = 'all', $targetie = '', $index = 0) {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->loadFile(array($file, $media, $targetie, $index));
	}

	function unloadCSSFile($file, $optimized = FALSE, $media = 'all', $targetie = '') {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->unloadFile($file, $targetie, $media);
	}

	function unloadAllCSSFiles() {
		$self = self::getInstance();
		$self->oFrontEndFileHandler->unloadAllFiles('css');
	}

	function getCSSFile() {
		$self = self::getInstance();
		return $self->oFrontEndFileHandler->getCssFileList();
	}

	function getJavascriptPluginInfo($pluginName) {
		if($plugin_name == 'ui.datepicker') $plugin_name = 'ui';
		$plugin_path = './common/js/plugins/' . $pluginName . '/';
		$info_file = $plugin_path . 'plugin.load';
		if(!is_readable($info_file)) return;
		$list = file($info_file);
		$result = new stdClass();
		$result->jsList = array();
		$result->cssList = array();
		foreach($list as $filename) {
			$filename = trim($filename);
			if(!$filename) continue;
			if(strncasecmp('./', $filename, 2) === 0) $filename = substr($filename, 2);
			if(substr_compare($filename, '.js', -3) === 0) $result->jsList[] = $plugin_path . $filename;
			elseif(substr_compare($filename, '.css', -4) === 0) $result->cssList[] = $plugin_path . $filename;
		}
		if(is_dir($plugin_path . 'lang')) $result->langPath = $plugin_path . 'lang';
		return $result;
	}

	function loadJavascriptPlugin($plugin_name) {
		static $loaded_plugins = array();
		$self = self::getInstance();
		if($plugin_name == 'ui.datepicker') $plugin_name = 'ui';
		if($loaded_plugins[$plugin_name]) return;
		$loaded_plugins[$plugin_name] = TRUE;
		$plugin_path = './common/js/plugins/' . $plugin_name . '/';
		$info_file = $plugin_path . 'plugin.load';
		if(!is_readable($info_file)) return;
		$list = file($info_file);
		foreach($list as $filename) {
			$filename = trim($filename);
			if(!$filename) continue;
			if(strncasecmp('./', $filename, 2) === 0) $filename = substr($filename, 2);
			if(substr_compare($filename, '.js', -3) === 0) $self->loadFile(array($plugin_path . $filename, 'body', '', 0), TRUE);
			if(substr_compare($filename, '.css', -4) === 0) $self->loadFile(array($plugin_path . $filename, 'all', '', 0), TRUE);
		}
		if(is_dir($plugin_path . 'lang')) $self->loadLang($plugin_path . 'lang');
	}

	function addHtmlHeader($header) {
		$self = self::getInstance();
		$self->html_header .= "\n" . $header;
	}

	function clearHtmlHeader() {
		$self = self::getInstance();
		$self->html_header = '';
	}

	function getHtmlHeader() {
		$self = self::getInstance();
		return $self->html_header;
	}

	function addBodyClass($class_name) {
		$self = self::getInstance();
		$self->body_class[] = $class_name;
	}

	function getBodyClass() {
		$self = self::getInstance();
		$self->body_class = array_unique($self->body_class);
		return (count($self->body_class) > 0) ? sprintf(' class="%s"', join(' ', $self->body_class)) : '';
	}

	function addBodyHeader($header) {
		$self = self::getInstance();
		$self->body_header .= "\n" . $header;
	}

	function getBodyHeader() {
		$self = self::getInstance();
		return $self->body_header;
	}

	function addHtmlFooter($footer) {
		$self = self::getInstance();
		$self->html_footer .= ($self->Htmlfooter ? "\n" : '') . $footer;
	}

	function getHtmlFooter() {
		$self = self::getInstance();
		return $self->html_footer;
	}

	function getConfigFile() {
		return _XE_PATH_ . 'files/config/db.config.php';
	}

	function getFTPConfigFile() {
		return _XE_PATH_ . 'files/config/ftp.config.php';
	}

	function isInstalled() {
		return FileHandler::hasContent(self::getConfigFile());
	}

	function transContent($content) {
		return $content;
	}

	function isAllowRewrite() {
		$oContext = self::getInstance();
		return $oContext->allow_rewrite;
	}

	function pathToUrl($path) {
		$xe = _XE_PATH_;
		$path = strtr($path, "\\", "/");
		$base_url = preg_replace('@^https?://[^/]+/?@', '', self::getRequestUri());
		$_xe = explode('/', $xe);
		$_path = explode('/', $path);
		$_base = explode('/', $base_url);
		if(!$_base[count($_base) - 1]) array_pop($_base);
		foreach($_xe as $idx => $dir) {
			if($_path[0] != $dir) break;
			array_shift($_path);
		}
		$idx = count($_xe) - $idx - 1;
		while($idx--) {
			if(count($_base) > 0) array_shift($_base);
			else array_unshift($_base, '..');
		}
		if(count($_base) > 0) array_unshift($_path, join('/', $_base));
		$path = '/' . join('/', $_path);
		if(substr_compare($path, '/', -1) !== 0) $path .= '/';
		return $path;
	}

	function getMetaTag() {
		$self = self::getInstance();
		if(!is_array($self->meta_tags)) $self->meta_tags = array();
		$ret = array();
		foreach($self->meta_tags as $key => $val) {
			list($name, $is_http_equiv) = explode("\t", $key);
			$ret[] = array('name' => $name, 'is_http_equiv' => $is_http_equiv, 'content' => $val);
		}
		return $ret;
	}

	function addMetaTag($name, $content, $is_http_equiv = FALSE) {
		$self = self::getInstance();
		$self->meta_tags[$name . "\t" . ($is_http_equiv ? '1' : '0')] = $content;
	}
}
