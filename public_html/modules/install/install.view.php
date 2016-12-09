<?php
class installView extends install
{
	var $install_enable = false;

	function init() {
		Context::setBrowserTitle(Context::getLang('introduce_title'));
		$this->setTemplatePath($this->module_path.'tpl');
		if(Context::isInstalled()) return $this->stop('msg_already_installed');
		$oInstallController = getController('install');
		$this->install_enable = $oInstallController->checkInstallEnv();
		if($this->install_enable) $oInstallController->makeDefaultDirectory();
	}

	function dispInstallIntroduce() {
		$install_config_file = FileHandler::getRealPath('./config/install.config.php');
		if(file_exists($install_config_file)) {
			include $install_config_file;
			if(is_array($install_config)) {
				foreach($install_config as $k => $v)  {
					$v = ($k == 'db_table_prefix') ? $v.'_' : $v;
					Context::set($k,$v,true);
				}
				unset($GLOBALS['__DB__']);
				Context::set('install_config', true, true);
				$oInstallController = getController('install');
				$output = $oInstallController->procInstall();
				if (!$output->toBool()) return $output;
				header("location: ./");
				Context::close();
				exit;
			}
		}
		Context::set('l', Context::getLangType());
		$this->setTemplateFile('introduce');
	}

	function dispInstallLicenseAgreement() {
		$this->setTemplateFile('license_agreement');
		$lang_type = Context::getLangType();
		Context::set('lang_type', $lang_type);
	}

	function dispInstallCheckEnv() {
		$oInstallController = getController('install');
		$useRewrite = $oInstallController->checkRewriteUsable() ? 'Y' : 'N';
		$_SESSION['use_rewrite'] = $useRewrite;
		Context::set('use_rewrite', $useRewrite);
		if($useRewrite == 'N' && stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) Context::set('use_nginx', 'Y');
		$this->setTemplateFile('check_env');
	}

	function dispInstallSelectDB() {
		if(!$this->install_enable) return $this->dispInstallCheckEnv();
		if(ini_get('safe_mode') && !Context::isFTPRegisted()) {
			Context::set('progressMenu', '3');
			$this->setTemplateFile('ftp');
		} else {
			$defaultDatabase = 'mysqli';
			$disableList = DB::getDisableList();
			if(is_array($disableList)) {
				foreach($disableList AS $key=>$value) {
					if($value->db_type == $defaultDatabase) {
						$defaultDatabase = 'mysql';
						break;
					}
				}
			}
			Context::set('defaultDatabase', $defaultDatabase);
			Context::set('progressMenu', '4');
			$this->setTemplateFile('select_db');
		}
	}

	function dispInstallDBForm() {
		if(!$this->install_enable) return $this->dispInstallCheckEnv();
		if(!Context::get('db_type')) return $this->dispInstallSelectDB();
		$tpl_filename = sprintf('form.%s', Context::get('db_type'));
		$title = sprintf(Context::getLang('input_dbinfo_by_dbtype'), Context::get('db_type'));
		Context::set('title', $title);
		$error_return_url = getNotEncodedUrl('', 'act', Context::get('act'), 'db_type', Context::get('db_type'));
		if($_SERVER['HTTPS'] == 'on') {
			$parsedUrl = parse_url($error_return_url);
			$error_return_url = '';
			if(isset($parsedUrl['path'])) $error_return_url .= $parsedUrl['path'];
			if(isset($parsedUrl['query'])) $error_return_url .= '?' . $parsedUrl['query'];
			if(isset($parsedUrl['fragment'])) $error_return_url .= '?' . $parsedUrl['fragment'];
		}
		Context::set('error_return_url', $error_return_url);
		$this->setTemplateFile($tpl_filename);
	}

	function dispInstallConfigForm() {
		if(!$this->install_enable) return $this->dispInstallCheckEnv();
		include _XE_PATH_.'files/config/tmpDB.config.php';
		Context::set('use_rewrite', $_SESSION['use_rewrite']);
		Context::set('time_zone', $GLOBALS['time_zone']);
		Context::set('db_type', $db_info->db_type);
		$this->setTemplateFile('config_form');
	}

	function useRewriteModule() {
		if(function_exists('apache_get_modules') && in_array('mod_rewrite',apache_get_modules())) return true;
		require_once(_XE_PATH_.'classes/httprequest/XEHttpRequest.class.php');
		$httpRequest = new XEHttpRequest($_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT']);
		$xeInstallPath = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'index.php', 1));
		$output = $httpRequest->send($xeInstallPath.'modules/install/conf/info.xml');
		return (strpos($output->body, '<?xml') !== 0);
	}

	function dispInstallManagerForm() {
		if(!$this->install_enable) return $this->dispInstallCheckEnv();
		$this->setTemplateFile('admin_form');
	}
}
