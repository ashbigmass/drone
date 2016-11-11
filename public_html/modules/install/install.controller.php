<?php
class installController extends install { 
	var $db_tmp_config_file = '';
	var $etc_tmp_config_file = '';
	var $flagLicenseAgreement = './files/env/license_agreement';

	function init() {
		if(Context::isInstalled()) return new Object(-1, 'msg_already_installed');
		$this->db_tmp_config_file = _XE_PATH_.'files/config/tmpDB.config.php';
		$this->etc_tmp_config_file = _XE_PATH_.'files/config/tmpEtc.config.php';
	}

	function procCubridDBSetting() {
		return $this->_procDBSetting();
	}

	function procFirebirdDBSetting() {
		return $this->_procDBSetting();
	}

	function procMssqlDBSetting() {
		return $this->_procDBSetting();
	}

	function procMysqlDBSetting() {
		return $this->_procDBSetting();
	}

	function procPostgresqlDBSetting() {
		return $this->_procDBSetting();
	}

	function procSqliteDBSetting() {
		return $this->_procDBSetting();
	}

	function _procDBSetting() {
		$con_string = Context::gets('db_type','db_port','db_hostname','db_userid','db_password','db_database','db_table_prefix');
		$db_info = new stdClass();
		$db_info->master_db = get_object_vars($con_string);
		$db_info->slave_db = array($db_info->master_db);
		$db_info->default_url = Context::getRequestUri();
		$db_info->lang_type = Context::getLangType();
		$db_info->use_mobile_view = 'Y';
		Context::setDBInfo($db_info);
		$oDB = &DB::getInstance();
		$output = $oDB->getError();
		if(!$output->toBool()) return $output;
		if(!$oDB->isConnected()) return $oDB->getError();
		if(!$this->makeDBConfigFile()) return new Object(-1, 'msg_install_failed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'act', 'dispInstallConfigForm');
			header('location:'.$returnUrl);
			return;
		}
	}

	function procConfigSetting() {
		$config_info = Context::gets('use_rewrite','time_zone');
		if($config_info->use_rewrite!='Y') $config_info->use_rewrite = 'N';
		if(!$this->makeEtcConfigFile($config_info)) return new Object(-1, 'msg_install_failed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'act', 'dispInstallManagerForm');
			header('location:'.$returnUrl);
			return;
		}
	}

	function procInstall() {
		if(Context::isInstalled()) return new Object(-1, 'msg_already_installed');
		$logged_info = new stdClass();
		$logged_info->is_admin = 'Y';
		Context::set('logged_info', $logged_info);
		if(Context::get('install_config')) {
			$db_info = $this->_makeDbInfoByInstallConfig();
		} else {
			if(FileHandler::exists($this->db_tmp_config_file)) include $this->db_tmp_config_file;
			if(FileHandler::exists($this->etc_tmp_config_file)) include $this->etc_tmp_config_file;
		}
		Context::setDBInfo($db_info);
		$oDB = &DB::getInstance();
		if(!$oDB->isConnected()) return $oDB->getError();
		try {
			$oDB->begin();
			$this->installDownloadedModule();
			$oDB->commit();
		} catch(Exception $e) {
			$oDB->rollback();
			return new Object(-1, $e->getMessage());
		}
		if(!$this->makeConfigFile()) return new Object(-1, 'msg_install_failed');
		$scripts = FileHandler::readDir(_XE_PATH_ . 'modules/install/script', '/(\.php)$/');
		if(count($scripts)>0) {
			sort($scripts);
			foreach($scripts as $script) {
				$script_path = FileHandler::getRealPath('./modules/install/script/');
				$output = include($script_path . $script);
			}
		}
		$oInstallAdminController = getAdminController('install');
		$oInstallAdminController->saveLangSelected(array(Context::getLangType()));
		$this->setMessage('msg_install_completed');
		unset($_SESSION['use_rewrite']);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('');
			header('location:'.$returnUrl);
			return new Object();
		}
	}

	function _makeDbInfoByInstallConfig() {
		$db_info = new stdClass();
		$db_info->master_db = array(
			'db_type' => Context::get('db_type'),
			'db_port' => Context::get('db_port'),
			'db_hostname' => Context::get('db_hostname'),
			'db_userid' => Context::get('db_userid'),
			'db_password' => Context::get('db_password'),
			'db_database' => Context::get('db_database'),
			'db_table_prefix' => Context::get('db_table_prefix')
		);
		$db_info->slave_db = array($db_info->master_db);
		$db_info->default_url = Context::getRequestUri();
		$db_info->lang_type = Context::get('lang_type') ? Context::get('lang_type') : Context::getLangType();
		Context::setLangType($db_info->lang_type);
		$db_info->use_rewrite = Context::get('use_rewrite');
		$db_info->time_zone = Context::get('time_zone');
		return $db_info;
	}

	function procInstallFTP() {
		if(Context::isInstalled()) return new Object(-1, 'msg_already_installed');
		$ftp_info = Context::gets('ftp_host', 'ftp_user','ftp_password','ftp_port','ftp_root_path');
		$ftp_info->ftp_port = (int)$ftp_info->ftp_port;
		if(!$ftp_info->ftp_port) $ftp_info->ftp_port = 21;
		if(!$ftp_info->ftp_host) $ftp_info->ftp_host = '127.0.0.1';
		if(!$ftp_info->ftp_root_path) $ftp_info->ftp_root_path = '/';
		$buff = array('<?php if(!defined("__XE__")) exit();');
		$buff[] = "\$ftp_info = new stdClass();";
		foreach($ftp_info as $key => $val) $buff[] = sprintf("\$ftp_info->%s='%s';", $key, str_replace("'","\\'",$val));
		if(ini_get('safe_mode')) {
			if(!$ftp_info->ftp_user || !$ftp_info->ftp_password) return new Object(-1,'msg_safe_mode_ftp_needed');
			require_once(_XE_PATH_.'libs/ftp.class.php');
			$oFtp = new ftp();
			if(!$oFtp->ftp_connect($ftp_info->ftp_host, $ftp_info->ftp_port)) return new Object(-1, sprintf(Context::getLang('msg_ftp_not_connected'), $ftp_info->ftp_host));
			if(!$oFtp->ftp_login($ftp_info->ftp_user, $ftp_info->ftp_password)) {
				$oFtp->ftp_quit();
				return new Object(-1,'msg_ftp_invalid_auth_info');
			}
			if(!is_dir(_XE_PATH_.'files') && !$oFtp->ftp_mkdir($ftp_info->ftp_root_path.'files')) {
				$oFtp->ftp_quit();
				return new Object(-1,'msg_ftp_mkdir_fail');
			}
			if(!$oFtp->ftp_site("CHMOD 777 ".$ftp_info->ftp_root_path.'files')) {
				$oFtp->ftp_quit();
				return new Object(-1,'msg_ftp_chmod_fail');
			}
			if(!is_dir(_XE_PATH_.'files/config') && !$oFtp->ftp_mkdir($ftp_info->ftp_root_path.'files/config')) {
				$oFtp->ftp_quit();
				return new Object(-1,'msg_ftp_mkdir_fail');
			}
			if(!$oFtp->ftp_site("CHMOD 777 ".$ftp_info->ftp_root_path.'files/config')) {
				$oFtp->ftp_quit();
				return new Object(-1,'msg_ftp_chmod_fail');
			}
			$oFtp->ftp_quit();
		}
		FileHandler::WriteFile(Context::getFTPConfigFile(), join(PHP_EOL, $buff));
	}

	function procInstallCheckFtp() {
		$ftp_info = Context::gets('ftp_user','ftp_password','ftp_port','sftp');
		$ftp_info->ftp_port = (int)$ftp_info->ftp_port;
		if(!$ftp_info->ftp_port) $ftp_info->ftp_port = 21;
		if(!$ftp_info->sftp) $ftp_info->sftp = 'N';
		if(!$ftp_info->ftp_user || !$ftp_info->ftp_password) return new Object(-1,'msg_safe_mode_ftp_needed');
		if($ftp_info->sftp == 'Y') {
			$connection = ssh2_connect('localhost', $ftp_info->ftp_port);
			if(!ssh2_auth_password($connection, $ftp_info->ftp_user, $ftp_info->ftp_password)) return new Object(-1,'msg_ftp_invalid_auth_info');
		} else {
			require_once(_XE_PATH_.'libs/ftp.class.php');
			$oFtp = new ftp();
			if(!$oFtp->ftp_connect('127.0.0.1', $ftp_info->ftp_port)) return new Object(-1, sprintf(Context::getLang('msg_ftp_not_connected'), 'localhost'));
			if(!$oFtp->ftp_login($ftp_info->ftp_user, $ftp_info->ftp_password)) {
				$oFtp->ftp_quit();
				return new Object(-1,'msg_ftp_invalid_auth_info');
			}
			$oFtp->ftp_quit();
		}
		$this->setMessage('msg_ftp_connect_success');
	}

	function checkInstallEnv() {
		$checklist = array();
		$checklist['php_version'] = true;
		if(version_compare(PHP_VERSION, __XE_MIN_PHP_VERSION__, '<')) $checklist['php_version'] = false;
		if(version_compare(PHP_VERSION, __XE_RECOMMEND_PHP_VERSION__, '<')) Context::set('phpversion_warning', true);
		if(is_writable('./')||is_writable('./files')) $checklist['permission'] = true;
		else $checklist['permission'] = false;
		if(function_exists('xml_parser_create')) $checklist['xml'] = true;
		else $checklist['xml'] = false;
		if(ini_get('session.auto_start')!=1) $checklist['session'] = true;
		else $checklist['session'] = false;
		if(function_exists('iconv')) $checklist['iconv'] = true;
		else $checklist['iconv'] = false;
		if(function_exists('imagecreatefromgif')) $checklist['gd'] = true;
		else $checklist['gd'] = false;
		if(DB::getEnableList()) $checklist['db'] = true;
		else $checklist['db'] = false;
		if(!$checklist['php_version'] || !$checklist['permission'] || !$checklist['xml'] || !$checklist['session'] || !$checklist['db']) $install_enable = false;
		else $install_enable = true;
		Context::set('checklist', $checklist);
		Context::set('install_enable', $install_enable);
		Context::set('phpversion', PHP_VERSION);
		return $install_enable;
	}

	function procInstallLicenseAggrement() {
		$vars = Context::getRequestVars();
		$license_agreement = ($vars->license_agreement == 'Y') ? true : false;
		if($license_agreement) {
			$currentTime = $_SERVER['REQUEST_TIME'];
			FileHandler::writeFile($this->flagLicenseAgreement, $currentTime);
		} else {
			FileHandler::removeFile($this->flagLicenseAgreement);
			return new Object(-1, 'msg_must_accept_license_agreement');
		}
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'act', 'dispInstallCheckEnv');
			$this->setRedirectUrl($returnUrl);
		}
	}

	function checkRewriteUsable() {
		$checkString = "isApproached";
		$checkFilePath = 'files/config/tmpRewriteCheck.txt';
		FileHandler::writeFile(_XE_PATH_.$checkFilePath, trim($checkString));
		$scheme = ($_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
		$hostname = $_SERVER['SERVER_NAME'];
		$port = $_SERVER['SERVER_PORT'];
		$str_port = '';
		if($port) $str_port = ':' . $port;
		$tmpPath = $_SERVER['DOCUMENT_ROOT'];
		if(DIRECTORY_SEPARATOR !== '/') $tmpPath = str_replace(DIRECTORY_SEPARATOR, '/', $_SERVER['DOCUMENT_ROOT']);
		$query = "/JUST/CHECK/REWRITE/" . $checkFilePath;
		$currentPath = str_replace($tmpPath, "", _XE_PATH_);
		if($currentPath != "") $query = $currentPath . $query;
		$requestUrl = sprintf('%s://%s%s%s', $scheme, $hostname, $str_port, $query);
		$requestConfig = array();
		$requestConfig['ssl_verify_peer'] = false;
		$buff = FileHandler::getRemoteResource($requestUrl, null, 3, 'GET', null, array(), array(), array(), $requestConfig);
		FileHandler::removeFile(_XE_PATH_.$checkFilePath);
		return (trim($buff) == $checkString);
	}

		$directory_list = array(
			'./files/config',
			'./files/cache/queries',
			'./files/cache/js_filter_compiled',
			'./files/cache/template_compiled',
		);
		foreach($directory_list as $dir) FileHandler::makeDir($dir);
	}

	function installDownloadedModule() {
		$oModuleModel = getModel('module');
		$module_list = FileHandler::readDir('./modules/', NULL, false, true);
		foreach($module_list as $module_path) {
			$tmp_arr = explode('/',$module_path);
			$module = $tmp_arr[count($tmp_arr)-1];
			$xml_info = $oModuleModel->getModuleInfoXml($module);
			if(!$xml_info) continue;
			$modules[$xml_info->category][] = $module;
		}
		$this->installModule('module','./modules/module');
		$oModule = getClass('module');
		if($oModule->checkUpdate()) $oModule->moduleUpdate();
		$install_step = array('system','content','member');
		foreach($install_step as $category) {
			if(count($modules[$category])) {
				foreach($modules[$category] as $module) {
					if($module == 'module') continue;
					$this->installModule($module, sprintf('./modules/%s', $module));
					$oModule = getClass($module);
					if(is_object($oModule) && method_exists($oModule, 'checkUpdate')) {
						if($oModule->checkUpdate()) $oModule->moduleUpdate();
					}
				}
				unset($modules[$category]);
			}
		}
		if(count($modules)) {
			foreach($modules as $category => $module_list) {
				if(count($module_list)) {
					foreach($module_list as $module) {
						if($module == 'module') continue;
						$this->installModule($module, sprintf('./modules/%s', $module));
						$oModule = getClass($module);
						if($oModule && method_exists($oModule, 'checkUpdate') && method_exists($oModule, 'moduleUpdate')) {
							if($oModule->checkUpdate()) $oModule->moduleUpdate();
						}
					}
				}
			}
		}
		return new Object();
	}

	function installModule($module, $module_path) {
		$oDB = &DB::getInstance();
		$schema_dir = sprintf('%s/schemas/', $module_path);
		$schema_files = FileHandler::readDir($schema_dir, NULL, false, true);
		$file_cnt = count($schema_files);
		for($i=0;$i<$file_cnt;$i++) {
			$file = trim($schema_files[$i]);
			if(!$file || substr($file,-4)!='.xml') continue;
			$output = $oDB->createTableByXmlFile($file);
			if($output === false) throw new Exception('msg_create_table_failed');
		}
		unset($oModule);
		$oModule = getClass($module);
		if(method_exists($oModule, 'moduleInstall')) $oModule->moduleInstall();
		return new Object();
	}

	function _getDBConfigFileContents($db_info) {
		if(substr($db_info->master_db['db_table_prefix'], -1) != '_') $db_info->master_db['db_table_prefix'] .= '_';
		foreach($db_info->slave_db as &$slave) {
			if(substr($slave['db_table_prefix'], -1) != '_') $slave['db_table_prefix'] .= '_';
		}
		$buff = array();
		$buff[] = '<?php if(!defined("__XE__")) exit();';
		$buff[] = '$db_info = (object)' . var_export(get_object_vars($db_info), TRUE) . ';';
		return implode(PHP_EOL, $buff);
	}

	function makeDBConfigFile() {
		$db_tmp_config_file = $this->db_tmp_config_file;
		$db_info = Context::getDBInfo();
		if(!$db_info) return;
		$buff = $this->_getDBConfigFileContents($db_info);
		FileHandler::writeFile($db_tmp_config_file, $buff);
		if(@file_exists($db_tmp_config_file)) return true;
		return false;
	}

	function makeEtcConfigFile($config_info) {
		$etc_tmp_config_file = $this->etc_tmp_config_file;
		$buff = '<?php if(!defined("__XE__")) exit();'."\n";
		foreach($config_info as $key => $val) $buff .= sprintf("\$db_info->%s = '%s';\n", $key, str_replace("'","\\'",$val));
		FileHandler::writeFile($etc_tmp_config_file, $buff);
		if(@file_exists($etc_tmp_config_file)) return true;
		return false;
	}

	function makeConfigFile() {
		try {
			$config_file = Context::getConfigFile();
			$db_info = Context::getDBInfo();
			if(!$db_info) return;
			$buff = $this->_getDBConfigFileContents($db_info);
			FileHandler::writeFile($config_file, $buff);
			if(@file_exists($config_file)) {
				FileHandler::removeFile($this->db_tmp_config_file);
				FileHandler::removeFile($this->etc_tmp_config_file);
				return true;
			}
			return false;
		} catch (Exception $e) {
			return false;
		}
	}

	function installByConfig($install_config_file) {
		include $install_config_file;
		if(!is_array($auto_config)) return false;
		$auto_config['module'] = 'install';
		$auto_config['act'] = 'procInstall';
		$fstr = "<%s><![CDATA[%s]]></%s>\r\n";
		$fheader = "POST %s HTTP/1.1\r\nHost: %s\r\nContent-Type: application/xml\r\nContent-Length: %s\r\n\r\n%s\r\n";
		$body = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\r\n<methodCall>\r\n<params>\r\n";
		foreach($auto_config as $k => $v) if(!in_array($k,array('host','port','path'))) $body .= sprintf($fstr,$k,$v,$k);
		$body .= "</params>\r\n</methodCall>";
		$header = sprintf($fheader,$auto_config['path'],$auto_config['host'],strlen($body),$body);
		$fp = @fsockopen($auto_config['host'], $auto_config['port'], $errno, $errstr, 5);
		if($fp) {
			fputs($fp, $header);
			while(!feof($fp)) {
				$line = trim(fgets($fp, 4096));
				if(strncmp('<error>', $line, 7) === 0) {
					fclose($fp);
					return false;
				}
			}
			fclose($fp);
		}
		return true;

	}
}
