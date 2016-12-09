<?php
class Browser
{
	private $_agent = '';
	private $_browser_name = '';
	private $_version = '';
	private $_platform = '';
	private $_os = '';
	private $_is_aol = false;
	private $_is_mobile = false;
	private $_is_tablet = false;
	private $_is_robot = false;
	private $_is_facebook = false;
	private $_aol_version = '';

	const BROWSER_UNKNOWN = 'unknown';
	const VERSION_UNKNOWN = 'unknown';
	const BROWSER_OPERA = 'Opera';
	const BROWSER_OPERA_MINI = 'Opera Mini';
	const BROWSER_WEBTV = 'WebTV';
	const BROWSER_IE = 'Internet Explorer';
	const BROWSER_POCKET_IE = 'Pocket Internet Explorer';
	const BROWSER_KONQUEROR = 'Konqueror';
	const BROWSER_ICAB = 'iCab';
	const BROWSER_OMNIWEB = 'OmniWeb';
	const BROWSER_FIREBIRD = 'Firebird';
	const BROWSER_FIREFOX = 'Firefox';
	const BROWSER_ICEWEASEL = 'Iceweasel';
	const BROWSER_SHIRETOKO = 'Shiretoko';
	const BROWSER_MOZILLA = 'Mozilla';
	const BROWSER_AMAYA = 'Amaya';
	const BROWSER_LYNX = 'Lynx';
	const BROWSER_SAFARI = 'Safari';
	const BROWSER_IPHONE = 'iPhone';
	const BROWSER_IPOD = 'iPod';
	const BROWSER_IPAD = 'iPad';
	const BROWSER_CHROME = 'Chrome';
	const BROWSER_ANDROID = 'Android';
	const BROWSER_GOOGLEBOT = 'GoogleBot';
	const BROWSER_SLURP = 'Yahoo! Slurp';
	const BROWSER_W3CVALIDATOR = 'W3C Validator';
	const BROWSER_BLACKBERRY = 'BlackBerry';
	const BROWSER_ICECAT = 'IceCat';
	const BROWSER_NOKIA_S60 = 'Nokia S60 OSS Browser';
	const BROWSER_NOKIA = 'Nokia Browser';
	const BROWSER_MSN = 'MSN Browser';
	const BROWSER_MSNBOT = 'MSN Bot';
	const BROWSER_BINGBOT = 'Bing Bot';
	const BROWSER_NETSCAPE_NAVIGATOR = 'Netscape Navigator';
	const BROWSER_GALEON = 'Galeon';
	const BROWSER_NETPOSITIVE = 'NetPositive';
	const BROWSER_PHOENIX = 'Phoenix';
	const PLATFORM_UNKNOWN = 'unknown';
	const PLATFORM_WINDOWS = 'Windows';
	const PLATFORM_WINDOWS8 = 'Windows 8';
	const PLATFORM_WINDOWS7 = 'Windows 7';
	const PLATFORM_WINDOWS_VISTA = 'Windows Vista';
	const PLATFORM_WINDOWS_SERVER_2003 = 'Windows Server 2003/XP x64';
	const PLATFORM_WINDOWS_XP = 'Windows XP';
	const PLATFORM_WINDOWS_2000 = 'Windows 2000';
	const PLATFORM_WINDOWS_ME = 'Windows ME';
	const PLATFORM_WINDOWS_98 = 'Windows 98';
	const PLATFORM_WINDOWS_95 = 'Windows 95';
	const PLATFORM_WINDOWS_CE = 'Windows CE';
	const PLATFORM_APPLE = 'Apple';
	const PLATFORM_LINUX = 'Linux';
	const PLATFORM_UBUNTU = 'Ubuntu';
	const PLATFORM_OS2 = 'OS/2';
	const PLATFORM_BEOS = 'BeOS';
	const PLATFORM_IPHONE = 'iPhone';
	const PLATFORM_IPOD = 'iPod';
	const PLATFORM_IPAD = 'iPad';
	const PLATFORM_BLACKBERRY = 'BlackBerry';
	const PLATFORM_NOKIA = 'Nokia';
	const PLATFORM_FREEBSD = 'FreeBSD';
	const PLATFORM_OPENBSD = 'OpenBSD';
	const PLATFORM_NETBSD = 'NetBSD';
	const PLATFORM_SUNOS = 'SunOS';
	const PLATFORM_OPENSOLARIS = 'OpenSolaris';
	const PLATFORM_ANDROID = 'Android';
	const OPERATING_SYSTEM_UNKNOWN = 'unknown';

	public function Browser($userAgent = "") {
		$this->reset();
		if ($userAgent != "") $this->setUserAgent($userAgent);
		else $this->determine();
	}

	public function reset() {
		$this->_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
		$this->_browser_name = self::BROWSER_UNKNOWN;
		$this->_version = self::VERSION_UNKNOWN;
		$this->_platform = self::PLATFORM_UNKNOWN;
		$this->_os = self::OPERATING_SYSTEM_UNKNOWN;
		$this->_is_aol = false;
		$this->_is_mobile = false;
		$this->_is_tablet = false;
		$this->_is_robot = false;
		$this->_is_facebook = false;
		$this->_aol_version = self::VERSION_UNKNOWN;
	}

	function isBrowser($browserName) {
		return (0 == strcasecmp($this->_browser_name, trim($browserName)));
	}

	public function getBrowser() {
		return $this->_browser_name;
	}

	public function setBrowser($browser) {
		$this->_browser_name = $browser;
	}

	public function getPlatform() {
		return $this->_platform;
	}

	public function setPlatform($platform) {
		$this->_platform = $platform;
	}

	public function getVersion() {
		return $this->_version;
	}

	public function setVersion($version) {
		$this->_version = preg_replace('/[^0-9,.,a-z,A-Z-]/', '', $version);
	}

	public function getAolVersion() {
		return $this->_aol_version;
	}

	public function setAolVersion($version) {
		$this->_aol_version = preg_replace('/[^0-9,.,a-z,A-Z]/', '', $version);
	}

	public function isAol() {
		return $this->_is_aol;
	}

	public function isMobile() {
		return $this->_is_mobile;
	}

	public function isTablet() {
		return $this->_is_tablet;
	}

	public function isRobot() {
		return $this->_is_robot;
	}

	public function isFacebook() {
		return $this->_is_facebook;
	}

	public function setAol($isAol) {
		$this->_is_aol = $isAol;
	}

	protected function setMobile($value = true) {
		$this->_is_mobile = $value;
	}

	protected function setTablet($value = true) {
		$this->_is_tablet = $value;
	}

	protected function setRobot($value = true) {
		$this->_is_robot = $value;
	}

	protected function setFacebook($value = true) {
		$this->_is_facebook = $value;
	}

	public function getUserAgent() {
		return $this->_agent;
	}

	public function setUserAgent($agent_string) {
		$this->reset();
		$this->_agent = $agent_string;
		$this->determine();
	}

	public function isChromeFrame() {
		return (strpos($this->_agent, "chromeframe") !== false);
	}

	public function __toString() {
		return "<strong>Browser Name:</strong> {$this->getBrowser()}<br/>\n" .
		"<strong>Browser Version:</strong> {$this->getVersion()}<br/>\n" .
		"<strong>Browser User Agent String:</strong> {$this->getUserAgent()}<br/>\n" .
		"<strong>Platform:</strong> {$this->getPlatform()}<br/>";
	}

	protected function determine() {
		$this->checkPlatform();
		$this->checkBrowsers();
		$this->checkForAol();
	}

	protected function checkBrowsers() {
		return (
			$this->checkBrowserWebTv() ||
			$this->checkBrowserInternetExplorer() ||
			$this->checkBrowserOpera() ||
			$this->checkBrowserGaleon() ||
			$this->checkBrowserNetscapeNavigator9Plus() ||
			$this->checkBrowserFirefox() ||
			$this->checkBrowserChrome() ||
			$this->checkBrowserOmniWeb() ||
			$this->checkBrowserAndroid() ||
			$this->checkBrowseriPad() ||
			$this->checkBrowseriPod() ||
			$this->checkBrowseriPhone() ||
			$this->checkBrowserBlackBerry() ||
			$this->checkBrowserNokia() ||
			$this->checkBrowserGoogleBot() ||
			$this->checkBrowserMSNBot() ||
			$this->checkBrowserBingBot() ||
			$this->checkBrowserSlurp() ||
			$this->checkFacebookExternalHit() ||
			$this->checkBrowserSafari() ||
			$this->checkBrowserNetPositive() ||
			$this->checkBrowserFirebird() ||
			$this->checkBrowserKonqueror() ||
			$this->checkBrowserIcab() ||
			$this->checkBrowserPhoenix() ||
			$this->checkBrowserAmaya() ||
			$this->checkBrowserLynx() ||
			$this->checkBrowserShiretoko() ||
			$this->checkBrowserIceCat() ||
			$this->checkBrowserIceweasel() ||
			$this->checkBrowserW3CValidator() ||
			$this->checkBrowserMozilla()
		);
	}

	protected function checkBrowserBlackBerry() {
		if (stripos($this->_agent, 'blackberry') !== false) {
			$aresult = explode("/", stristr($this->_agent, "BlackBerry"));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->_browser_name = self::BROWSER_BLACKBERRY;
			$this->setMobile(true);
			return true;
		}
		return false;
	}

	protected function checkForAol() {
		$this->setAol(false);
		$this->setAolVersion(self::VERSION_UNKNOWN);
		if (stripos($this->_agent, 'aol') !== false) {
			$aversion = explode(' ', stristr($this->_agent, 'AOL'));
			$this->setAol(true);
			$this->setAolVersion(preg_replace('/[^0-9\.a-z]/i', '', $aversion[1]));
			return true;
		}
		return false;
	}

	protected function checkBrowserGoogleBot() {
		if (stripos($this->_agent, 'googlebot') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'googlebot'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion(str_replace(';', '', $aversion[0]));
			$this->_browser_name = self::BROWSER_GOOGLEBOT;
			$this->setRobot(true);
			return true;
		}
		return false;
	}

	protected function checkBrowserMSNBot() {
		if (stripos($this->_agent, "msnbot") !== false) {
			$aresult = explode("/", stristr($this->_agent, "msnbot"));
			$aversion = explode(" ", $aresult[1]);
			$this->setVersion(str_replace(";", "", $aversion[0]));
			$this->_browser_name = self::BROWSER_MSNBOT;
			$this->setRobot(true);
			return true;
		}
		return false;
	}

	protected function checkBrowserBingBot() {
		if (stripos($this->_agent, "bingbot") !== false) {
			$aresult = explode("/", stristr($this->_agent, "bingbot"));
			$aversion = explode(" ", $aresult[1]);
			$this->setVersion(str_replace(";", "", $aversion[0]));
			$this->_browser_name = self::BROWSER_BINGBOT;
			$this->setRobot(true);
			return true;
		}
		return false;
	}

	protected function checkBrowserW3CValidator() {
		if (stripos($this->_agent, 'W3C-checklink') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'W3C-checklink'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->_browser_name = self::BROWSER_W3CVALIDATOR;
			return true;
		} else if (stripos($this->_agent, 'W3C_Validator') !== false) {
			$ua = str_replace("W3C_Validator ", "W3C_Validator/", $this->_agent);
			$aresult = explode('/', stristr($ua, 'W3C_Validator'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->_browser_name = self::BROWSER_W3CVALIDATOR;
			return true;
		} else if (stripos($this->_agent, 'W3C-mobileOK') !== false) {
			$this->_browser_name = self::BROWSER_W3CVALIDATOR;
			$this->setMobile(true);
			return true;
		}
		return false;
	}

	protected function checkBrowserSlurp() {
		if (stripos($this->_agent, 'slurp') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'Slurp'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->_browser_name = self::BROWSER_SLURP;
			$this->setRobot(true);
			$this->setMobile(false);
			return true;
		}
		return false;
	}

	protected function checkBrowserInternetExplorer() {
		if (stripos($this->_agent, 'microsoft internet explorer') !== false) {
			$this->setBrowser(self::BROWSER_IE);
			$this->setVersion('1.0');
			$aresult = stristr($this->_agent, '/');
			if (preg_match('/308|425|426|474|0b1/i', $aresult)) $this->setVersion('1.5');
			return true;
		} else if (stripos($this->_agent, 'msie') !== false && stripos($this->_agent, 'opera') === false) {
			if (stripos($this->_agent, 'msnb') !== false) {
				$aresult = explode(' ', stristr(str_replace(';', '; ', $this->_agent), 'MSN'));
				$this->setBrowser(self::BROWSER_MSN);
				$this->setVersion(str_replace(array('(', ')', ';'), '', $aresult[1]));
				return true;
			}
			$aresult = explode(' ', stristr(str_replace(';', '; ', $this->_agent), 'msie'));
			$this->setBrowser(self::BROWSER_IE);
			$this->setVersion(str_replace(array('(', ')', ';'), '', $aresult[1]));
			if(stripos($this->_agent, 'IEMobile') !== false) {
				$this->setBrowser(self::BROWSER_POCKET_IE);
				$this->setMobile(true);
			}
			return true;
		} else if(stripos($this->_agent, 'trident') !== false) {
			$this->setBrowser(self::BROWSER_IE);
			$result = explode('rv:', $this->_agent);
			$this->setVersion(preg_replace('/[^0-9.]+/', '', $result[1]));
			$this->_agent = str_replace(array("Mozilla", "Gecko"), "MSIE", $this->_agent);
		} else if (stripos($this->_agent, 'mspie') !== false || stripos($this->_agent, 'pocket') !== false) {
			$aresult = explode(' ', stristr($this->_agent, 'mspie'));
			$this->setPlatform(self::PLATFORM_WINDOWS_CE);
			$this->setBrowser(self::BROWSER_POCKET_IE);
			$this->setMobile(true);
			if (stripos($this->_agent, 'mspie') !== false) {
				$this->setVersion($aresult[1]);
			} else {
				$aversion = explode('/', $this->_agent);
				$this->setVersion($aversion[1]);
			}
			return true;
		}
		return false;
	}

	protected function checkBrowserOpera() {
		if (stripos($this->_agent, 'opera mini') !== false) {
			$resultant = stristr($this->_agent, 'opera mini');
			if (preg_match('/\//', $resultant)) {
				$aresult = explode('/', $resultant);
				$aversion = explode(' ', $aresult[1]);
				$this->setVersion($aversion[0]);
			} else {
				$aversion = explode(' ', stristr($resultant, 'opera mini'));
				$this->setVersion($aversion[1]);
			}
			$this->_browser_name = self::BROWSER_OPERA_MINI;
			$this->setMobile(true);
			return true;
		} else if (stripos($this->_agent, 'opera') !== false) {
			$resultant = stristr($this->_agent, 'opera');
			if (preg_match('/Version\/(1*.*)$/', $resultant, $matches)) {
				$this->setVersion($matches[1]);
			} else if (preg_match('/\//', $resultant)) {
				$aresult = explode('/', str_replace("(", " ", $resultant));
				$aversion = explode(' ', $aresult[1]);
				$this->setVersion($aversion[0]);
			} else {
				$aversion = explode(' ', stristr($resultant, 'opera'));
				$this->setVersion(isset($aversion[1]) ? $aversion[1] : "");
			}
			if (stripos($this->_agent, 'Opera Mobi') !== false) $this->setMobile(true);
			$this->_browser_name = self::BROWSER_OPERA;
			return true;
		} else if (stripos($this->_agent, 'OPR') !== false) {
			$resultant = stristr($this->_agent, 'OPR');
			if (preg_match('/\//', $resultant)) {
				$aresult = explode('/', str_replace("(", " ", $resultant));
				$aversion = explode(' ', $aresult[1]);
				$this->setVersion($aversion[0]);
			}
			if (stripos($this->_agent, 'Mobile') !== false) $this->setMobile(true);
			$this->_browser_name = self::BROWSER_OPERA;
			return true;
		}
		return false;
	}

	protected function checkBrowserChrome() {
		if (stripos($this->_agent, 'Chrome') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'Chrome'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_CHROME);
			if (stripos($this->_agent, 'Android') !== false) {
				if (stripos($this->_agent, 'Mobile') !== false) $this->setMobile(true);
				else $this->setTablet(true);
			}
			return true;
		}
		return false;
	}

	protected function checkBrowserWebTv() {
		if (stripos($this->_agent, 'webtv') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'webtv'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_WEBTV);
			return true;
		}
		return false;
	}

	protected function checkBrowserNetPositive() {
		if (stripos($this->_agent, 'NetPositive') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'NetPositive'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion(str_replace(array('(', ')', ';'), '', $aversion[0]));
			$this->setBrowser(self::BROWSER_NETPOSITIVE);
			return true;
		}
		return false;
	}

	protected function checkBrowserGaleon() {
		if (stripos($this->_agent, 'galeon') !== false) {
			$aresult = explode(' ', stristr($this->_agent, 'galeon'));
			$aversion = explode('/', $aresult[0]);
			$this->setVersion($aversion[1]);
			$this->setBrowser(self::BROWSER_GALEON);
			return true;
		}
		return false;
	}

	protected function checkBrowserKonqueror() {
		if (stripos($this->_agent, 'Konqueror') !== false) {
			$aresult = explode(' ', stristr($this->_agent, 'Konqueror'));
			$aversion = explode('/', $aresult[0]);
			$this->setVersion($aversion[1]);
			$this->setBrowser(self::BROWSER_KONQUEROR);
			return true;
		}
		return false;
	}

	protected function checkBrowserIcab() {
		if (stripos($this->_agent, 'icab') !== false) {
			$aversion = explode(' ', stristr(str_replace('/', ' ', $this->_agent), 'icab'));
			$this->setVersion($aversion[1]);
			$this->setBrowser(self::BROWSER_ICAB);
			return true;
		}
		return false;
	}

	protected function checkBrowserOmniWeb() {
		if (stripos($this->_agent, 'omniweb') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'omniweb'));
			$aversion = explode(' ', isset($aresult[1]) ? $aresult[1] : "");
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_OMNIWEB);
			return true;
		}
		return false;
	}

	protected function checkBrowserPhoenix() {
		if (stripos($this->_agent, 'Phoenix') !== false) {
			$aversion = explode('/', stristr($this->_agent, 'Phoenix'));
			$this->setVersion($aversion[1]);
			$this->setBrowser(self::BROWSER_PHOENIX);
			return true;
		}
		return false;
	}

	protected function checkBrowserFirebird() {
		if (stripos($this->_agent, 'Firebird') !== false) {
			$aversion = explode('/', stristr($this->_agent, 'Firebird'));
			$this->setVersion($aversion[1]);
			$this->setBrowser(self::BROWSER_FIREBIRD);
			return true;
		}
		return false;
	}

	protected function checkBrowserNetscapeNavigator9Plus() {
		if (stripos($this->_agent, 'Firefox') !== false && preg_match('/Navigator\/([^ ]*)/i', $this->_agent, $matches)) {
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_NETSCAPE_NAVIGATOR);
			return true;
		} else if (stripos($this->_agent, 'Firefox') === false && preg_match('/Netscape6?\/([^ ]*)/i', $this->_agent, $matches)) {
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_NETSCAPE_NAVIGATOR);
			return true;
		}
		return false;
	}

	protected function checkBrowserShiretoko() {
		if (stripos($this->_agent, 'Mozilla') !== false && preg_match('/Shiretoko\/([^ ]*)/i', $this->_agent, $matches)) {
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_SHIRETOKO);
			return true;
		}
		return false;
	}

	protected function checkBrowserIceCat() {
		if (stripos($this->_agent, 'Mozilla') !== false && preg_match('/IceCat\/([^ ]*)/i', $this->_agent, $matches)) {
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_ICECAT);
			return true;
		}
		return false;
	}

	protected function checkBrowserNokia() {
		if (preg_match("/Nokia([^\/]+)\/([^ SP]+)/i", $this->_agent, $matches)) {
			$this->setVersion($matches[2]);
			if (stripos($this->_agent, 'Series60') !== false || strpos($this->_agent, 'S60') !== false) {
				$this->setBrowser(self::BROWSER_NOKIA_S60);
			} else {
				$this->setBrowser(self::BROWSER_NOKIA);
			}
			$this->setMobile(true);
			return true;
		}
		return false;
	}

	protected function checkBrowserFirefox() {
		if (stripos($this->_agent, 'safari') === false) {
			if (preg_match("/Firefox[\/ \(]([^ ;\)]+)/i", $this->_agent, $matches)) {
				$this->setVersion($matches[1]);
				$this->setBrowser(self::BROWSER_FIREFOX);
				if (stripos($this->_agent, 'Android') !== false) {
					if (stripos($this->_agent, 'Mobile') !== false) $this->setMobile(true);
					else $this->setTablet(true);
				}
				return true;
			} else if (preg_match("/Firefox$/i", $this->_agent, $matches)) {
				$this->setVersion("");
				$this->setBrowser(self::BROWSER_FIREFOX);
				return true;
			}
		}
		return false;
	}

	protected function checkBrowserIceweasel() {
		if (stripos($this->_agent, 'Iceweasel') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'Iceweasel'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_ICEWEASEL);
			return true;
		}
		return false;
	}

	protected function checkBrowserMozilla() {
		if (stripos($this->_agent, 'mozilla') !== false && preg_match('/rv:[0-9].[0-9][a-b]?/i', $this->_agent) && stripos($this->_agent, 'netscape') === false) {
			$aversion = explode(' ', stristr($this->_agent, 'rv:'));
			preg_match('/rv:[0-9].[0-9][a-b]?/i', $this->_agent, $aversion);
			$this->setVersion(str_replace('rv:', '', $aversion[0]));
			$this->setBrowser(self::BROWSER_MOZILLA);
			return true;
		} else if (stripos($this->_agent, 'mozilla') !== false && preg_match('/rv:[0-9]\.[0-9]/i', $this->_agent) && stripos($this->_agent, 'netscape') === false) {
			$aversion = explode('', stristr($this->_agent, 'rv:'));
			$this->setVersion(str_replace('rv:', '', $aversion[0]));
			$this->setBrowser(self::BROWSER_MOZILLA);
			return true;
		} else if (stripos($this->_agent, 'mozilla') !== false && preg_match('/mozilla\/([^ ]*)/i', $this->_agent, $matches) && stripos($this->_agent, 'netscape') === false) {
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_MOZILLA);
			return true;
		}
		return false;
	}

	protected function checkBrowserLynx() {
		if (stripos($this->_agent, 'lynx') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'Lynx'));
			$aversion = explode(' ', (isset($aresult[1]) ? $aresult[1] : ""));
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_LYNX);
			return true;
		}
		return false;
	}

	protected function checkBrowserAmaya() {
		if (stripos($this->_agent, 'amaya') !== false) {
			$aresult = explode('/', stristr($this->_agent, 'Amaya'));
			$aversion = explode(' ', $aresult[1]);
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_AMAYA);
			return true;
		}
		return false;
	}

	protected function checkBrowserSafari() {
		if (stripos($this->_agent, 'Safari') !== false && stripos($this->_agent, 'iPhone') === false && stripos($this->_agent, 'iPod') === false) {
			$aresult = explode('/', stristr($this->_agent, 'Version'));
			if (isset($aresult[1])) {
				$aversion = explode(' ', $aresult[1]);
				$this->setVersion($aversion[0]);
			} else {
				$this->setVersion(self::VERSION_UNKNOWN);
			}
			$this->setBrowser(self::BROWSER_SAFARI);
			return true;
		}
		return false;
	}

	protected function checkFacebookExternalHit() {
		if(stristr($this->_agent,'FacebookExternalHit')) {
			$this->setRobot(true);
			$this->setFacebook(true);
			return true;
		}
		return false;
	}

	protected function checkForFacebookIos() {
		if(stristr($this->_agent,'FBIOS')) {
			$this->setFacebook(true);
			return true;
		}
		return false;
	}

	protected function getSafariVersionOnIos() {
		$aresult = explode('/',stristr($this->_agent,'Version'));
		if( isset($aresult[1])) {
			$aversion = explode(' ',$aresult[1]);
			$this->setVersion($aversion[0]);
			return true;
		}
		return false;
	}

	protected function getChromeVersionOnIos() {
		$aresult = explode('/',stristr($this->_agent,'CriOS'));
		if( isset($aresult[1])) {
			$aversion = explode(' ',$aresult[1]);
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_CHROME);
			return true;
		}
		return false;
	}

	protected function checkBrowseriPhone() {
		if( stripos($this->_agent,'iPhone') !== false ) {
			$this->setVersion(self::VERSION_UNKNOWN);
			$this->setBrowser(self::BROWSER_IPHONE);
			$this->getSafariVersionOnIos();
			$this->getChromeVersionOnIos();
			$this->checkForFacebookIos();
			$this->setMobile(true);
			return true;
		}
		return false;
	}

	protected function checkBrowseriPad() {
		if( stripos($this->_agent,'iPad') !== false ) {
			$this->setVersion(self::VERSION_UNKNOWN);
			$this->setBrowser(self::BROWSER_IPAD);
			$this->getSafariVersionOnIos();
			$this->getChromeVersionOnIos();
			$this->checkForFacebookIos();
			$this->setTablet(true);
			return true;
		}
		return false;
	}

	protected function checkBrowseriPod() {
		if( stripos($this->_agent,'iPod') !== false ) {
			$this->setVersion(self::VERSION_UNKNOWN);
			$this->setBrowser(self::BROWSER_IPOD);
			$this->getSafariVersionOnIos();
			$this->getChromeVersionOnIos();
			$this->checkForFacebookIos();
			$this->setMobile(true);
			return true;
		}
		return false;
	}

	protected function checkBrowserAndroid() {
		if (stripos($this->_agent, 'Android') !== false) {
			$aresult = explode(' ', stristr($this->_agent, 'Android'));
			if (isset($aresult[1])) {
				$aversion = explode(' ', $aresult[1]);
				$this->setVersion($aversion[0]);
			} else {
				$this->setVersion(self::VERSION_UNKNOWN);
			}
			if (stripos($this->_agent, 'Mobile') !== false) {
				$this->setMobile(true);
			} else {
				$this->setTablet(true);
			}
			$this->setBrowser(self::BROWSER_ANDROID);
			return true;
		}
		return false;
	}

	protected function checkPlatform() {
		if (stripos($this->_agent, 'windows nt 6.2') !== false) $this->_platform = self::PLATFORM_WINDOWS8;
		elseif (stripos($this->_agent, 'windows nt 6.1') !== false) $this->_platform = self::PLATFORM_WINDOWS7;
		elseif (stripos($this->_agent, 'windows nt 6.0') !== false) $this->_platform = self::PLATFORM_WINDOWS_VISTA;
		elseif (stripos($this->_agent, 'windows nt 5.1') !== false) $this->_platform = self::PLATFORM_WINDOWS_XP;
		elseif (stripos($this->_agent, 'windows xp') !== false) $this->_platform = self::PLATFORM_WINDOWS_XP;
		elseif (stripos($this->_agent, 'windows nt 5.0') !== false) $this->_platform = self::PLATFORM_WINDOWS_2000;
		elseif (stripos($this->_agent, 'windows me') !== false) $this->_platform = self::PLATFORM_WINDOWS_ME;
		elseif (stripos($this->_agent, 'win98') !== false) $this->_platform = self::PLATFORM_WINDOWS_98;
		elseif (stripos($this->_agent, 'win95') !== false) $this->_platform = self::PLATFORM_WINDOWS_95;
		elseif (stripos($this->_agent, 'iPad') !== false) $this->_platform = self::PLATFORM_IPAD;
		elseif (stripos($this->_agent, 'iPod') !== false) $this->_platform = self::PLATFORM_IPOD;
		elseif (stripos($this->_agent, 'iPhone') !== false) $this->_platform = self::PLATFORM_IPHONE;
		elseif (stripos($this->_agent, 'mac') !== false) $this->_platform = self::PLATFORM_APPLE;
		elseif (stripos($this->_agent, 'android') !== false) $this->_platform = self::PLATFORM_ANDROID;
		elseif (stripos($this->_agent, 'linux') !== false) $this->_platform = self::PLATFORM_LINUX;
		elseif (stripos($this->_agent, 'ubuntu') !== false) $this->_platform = self::PLATFORM_UBUNTU;
		elseif (stripos($this->_agent, 'Nokia') !== false) $this->_platform = self::PLATFORM_NOKIA;
		elseif (stripos($this->_agent, 'BlackBerry') !== false) $this->_platform = self::PLATFORM_BLACKBERRY;
		elseif (stripos($this->_agent, 'FreeBSD') !== false) $this->_platform = self::PLATFORM_FREEBSD;
		elseif (stripos($this->_agent, 'OpenBSD') !== false) $this->_platform = self::PLATFORM_OPENBSD;
		elseif (stripos($this->_agent, 'NetBSD') !== false) $this->_platform = self::PLATFORM_NETBSD;
		elseif (stripos($this->_agent, 'OpenSolaris') !== false) $this->_platform = self::PLATFORM_OPENSOLARIS;
		elseif (stripos($this->_agent, 'SunOS') !== false) $this->_platform = self::PLATFORM_SUNOS;
		elseif (stripos($this->_agent, 'OS\/2') !== false) $this->_platform = self::PLATFORM_OS2;
		elseif (stripos($this->_agent, 'BeOS') !== false) $this->_platform = self::PLATFORM_BEOS;
		elseif (stripos($this->_agent, 'win') !== false) $this->_platform = self::PLATFORM_WINDOWS;
	}
}
