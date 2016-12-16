<?php
class stats_library
{
	public function __construct() {
	}

	public function get_referer() {
		if (!empty($_SERVER['HTTP_REFERER'])) return $_SERVER['HTTP_REFERER'];
		else return false;
	}

	public function parse_referer($referer = null) {
		if( is_null($referer) ) $referer = $this->get_referer();
		$output = array('domain'=>'','path'=>'','full'=>'');
		if($parse_url = parse_url($referer)) {
			$output['domain'] = $parse_url['host'];
			$output['path'] = $parse_url['path'];
			$output['full'] = $referer;
		}
		return $output;
	}

	public function get_now() {
		if (!empty($_SERVER['REQUEST_URI'])) return $_SERVER['REQUEST_URI'];
		else return false;
	}

	public function get_ip_address() {
		if (!empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
		else return false;
	}

	public function get_user_agent() {
		if(!empty($_SERVER['HTTP_USER_AGENT'])) return $_SERVER['HTTP_USER_AGENT'];
		else return false;
	}

	public function parse_user_agent( $agent = null ) {
		if( is_null($agent) ) $agent = $this->get_user_agent();
		$platform = null;
		$browser  = null;
		$version  = null;
		$empty = array( 'platform' => $platform, 'browser' => $browser, 'version' => $version, 'full' => $agent );
		if( !$agent ) return $empty;
		if( preg_match('/\((.*?)\)/im', $agent, $parent_matches) ) {
			preg_match_all('/(?P<platform>Android|CrOS|iPhone|iPad|Linux|Macintosh|Windows(\ Phone\ OS)?|Silk|linux-gnu|BlackBerry|PlayBook|Nintendo\ (WiiU?|3DS)|Xbox)
				(?:\ [^;]*)?
				(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);
			$priority		   = array( 'Android', 'Xbox' );
			$result['platform'] = array_unique($result['platform']);
			if( count($result['platform']) > 1 ) {
				if( $keys = array_intersect($priority, $result['platform']) ) $platform = reset($keys);
				else $platform = $result['platform'][0];
			} elseif( isset($result['platform'][0]) ) {
				$platform = $result['platform'][0];
			}
		}
		if( $platform == 'linux-gnu' ) $platform = 'Linux';
		elseif( $platform == 'CrOS' ) $platform = 'Chrome OS';
		preg_match_all('%(?P<browser>Camino|Kindle(\ Fire\ Build)?|Firefox|Iceweasel|Safari|MSIE|Trident/.*rv|AppleWebKit|Chrome|IEMobile|Opera|OPR|Silk|Lynx|Midori|Version|Wget|curl|NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
			(?:\)?;?)
			(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
			$agent, $result, PREG_PATTERN_ORDER);
		if( !isset($result['browser'][0]) || !isset($result['version'][0]) ) return $empty;
		$browser = $result['browser'][0];
		$version = $result['version'][0];
		$key = 0;
		if( $browser == 'Iceweasel' ) {
			$browser = 'Firefox';
		} else if( $this->find_string_in_browser('Playstation Vita', $key, $result['browser']) ) {
			$platform = 'PlayStation Vita';
			$browser  = 'Browser';
		} else if( $this->find_string_in_browser('Kindle Fire Build', $key, $result['browser']) || $this->find_string_in_browser('Silk', $key, $result['browser']) ) {
			$browser  = $result['browser'][$key] == 'Silk' ? 'Silk' : 'Kindle';
			$platform = 'Kindle Fire';
			if( !($version = $result['version'][$key]) || !is_numeric($version[0]) ) $version = $result['version'][array_search('Version', $result['browser'])];
		} else if( $this->find_string_in_browser('NintendoBrowser', $key, $result['browser']) || $platform == 'Nintendo 3DS' ) {
			$browser = 'NintendoBrowser';
			$version = $result['version'][$key];
		} else if( $this->find_string_in_browser('Kindle', $key, $result['browser']) ) {
			$browser  = $result['browser'][$key];
			$platform = 'Kindle';
			$version  = $result['version'][$key];
		} else if( $this->find_string_in_browser('OPR', $key, $result['browser']) ) {
			$browser = 'Opera Next';
			$version = $result['version'][$key];
		} else if( $this->find_string_in_browser('Opera', $key, $result['browser']) ) {
			$browser = 'Opera';
			$this->find_string_in_browser('Version', $key, $result['browser']);
			$version = $result['version'][$key];
		} else if( $this->find_string_in_browser('Midori', $key, $result['browser']) ) {
			$browser = 'Midori';
			$version = $result['version'][$key];
		} else if( $this->find_string_in_browser('Chrome', $key, $result['browser']) ) {
			$browser = 'Chrome';
			$version = $result['version'][$key];
		} else if( $browser == 'AppleWebKit' ) {
			if( ($platform == 'Android' && !($key = 0)) ) $browser = 'Android Browser';
			else if( $platform == 'BlackBerry' || $platform == 'PlayBook' ) $browser = 'BlackBerry Browser';
			else if( $this->find_string_in_browser('Safari', $key, $result['browser']) ) $browser = 'Safari';
			$this->find_string_in_browser('Version', $key, $result['browser']);
			$version = $result['version'][$key];
		} elseif( $browser == 'MSIE' || strpos($browser, 'Trident') !== false ) {
			if( $this->find_string_in_browser('IEMobile', $key, $result['browser']) ) {
				$browser = 'IEMobile';
			} else {
				$browser = 'MSIE';
				$key	 = 0;
			}
			$version = $result['version'][$key];
		} elseif( $key = preg_grep("/playstation \d/i", array_map('strtolower', $result['browser'])) ) {
			$key = reset($key);
			$platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $key);
			$browser  = 'NetFront';
		}
		return array( 'platform' => $platform, 'browser' => $browser, 'version' => $version , 'full' => $agent);
	}

	private function find_string_in_browser ( $search, &$key, $browser) {
		$xkey = array_search(strtolower($search), array_map('strtolower', $browser));
		if( $xkey !== false ) {
			$key = $xkey;
			return true;
		}
		return false;
	}

	public function is_mobile($agent = null) {
		if( is_null($agent) ) $agent = $this->get_user_agent();
		if(!$agent) return false;
		$agent = strtolower($agent);
		$_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';
		$mobile_browser = '0';
		if(substr($agent,0,8) == 'facebook') return false;
		if(preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', $agent)) $mobile_browser++;
	 	if((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false)) $mobile_browser++;
		if(isset($_SERVER['HTTP_X_WAP_PROFILE'])) $mobile_browser++;
		if(isset($_SERVER['HTTP_PROFILE'])) $mobile_browser++;
	 	$mobile_ua = substr($agent,0,4);
		$mobile_agents = array(
			'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
			'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
			'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
			'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
			'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
			'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
			'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
			'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
			'wapr','webc','winw','xda','xda-'
			);
		if(in_array($mobile_ua, $mobile_agents)) $mobile_browser++;
		if(strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false) $mobile_browser++;
		if(strpos($agent, 'windows') !== false) $mobile_browser=0;
		if(strpos($agent, 'windows phone') !== false) $mobile_browser++;
		if($mobile_browser>0) return true;
		else return false;
	}

	public function is_bot($agent = null) {
		if( is_null($agent) ) $agent = $this->get_user_agent();
		if(!$agent) return false;
		$spiders = array(
			"abot", "dbot", "ebot", "hbot", "kbot", "lbot", "mbot", "nbot", "obot", "pbot",
			"rbot", "sbot", "tbot", "vbot", "ybot", "zbot", "bot.", "bot/", "_bot", ".bot",
			"/bot", "-bot", ":bot", "(bot", "crawl", "slurp", "spider", "seek", "accoona", "acoon",
			"adressendeutschland", "ah-ha.com", "ahoy", "altavista", "ananzi", "anthill", "appie", "arachnophilia", "arale", "araneo",
			"aranha", "architext", "aretha", "arks", "asterias", "atlocal", "atn", "atomz", "augurfind", "backrub",
			"bannana_bot", "baypup", "bdfetch", "big brother", "biglotron", "bjaaland", "blackwidow", "blaiz", "blog", "blo.",
			"bloodhound", "boitho", "booch", "bradley", "butterfly", "calif", "cassandra", "ccubee", "cfetch", "charlotte",
			"churl", "cienciaficcion", "cmc", "collective", "comagent", "combine", "computingsite", "csci", "curl", "cusco", "daumoa",
			"deepindex", "delorie", "depspid", "deweb", "die blinde kuh", "digger", "ditto", "dmoz", "docomo", "download express",
			"dtaagent", "dwcp", "ebiness", "ebingbong", "e-collector", "ejupiter", "emacs-w3 search engine", "esther", "evliya celebi", "ezresult",
			"falcon", "felix ide", "ferret", "fetchrover", "fido", "findlinks", "fireball", "fish search", "fouineur", "funnelweb",
			"gazz", "gcreep", "genieknows", "getterroboplus", "geturl", "glx", "goforit", "golem", "grabber", "grapnel", "gralon",
			"griffon", "gromit", "grub", "gulliver", "hamahakki", "harvest", "havindex", "helix", "heritrix", "hku www octopus",
			"homerweb", "htdig", "html index", "html_analyzer", "htmlgobble", "hubater", "hyper-decontextualizer", "ia_archiver", "ibm_planetwide", "ichiro",
			"iconsurf", "iltrovatore", "image.kapsi.net", "imagelock", "incywincy", "indexer", "infobee", "informant", "ingrid", "inktomisearch.com",
			"inspector web", "intelliagent", "internet shinchakubin", "ip3000", "iron33", "israeli-search", "ivia", "jack", "jakarta", "javabee",
			"jetbot", "jumpstation", "katipo", "kdd-explorer", "kilroy", "knowledge", "kototoi", "kretrieve", "labelgrabber", "lachesis",
			"larbin", "legs", "libwww", "linkalarm", "link validator", "linkscan", "lockon", "lwp", "lycos", "magpie",
			"mantraagent", "mapoftheinternet", "marvin/", "mattie", "mediafox", "mediapartners", "mercator", "merzscope", "microsoft url control", "minirank",
			"miva", "mj12", "mnogosearch", "moget", "monster", "moose", "motor", "multitext", "muncher", "muscatferret",
			"mwd.search", "myweb", "najdi", "nameprotect", "nationaldirectory", "nazilla", "ncsa beta", "nec-meshexplorer", "nederland.zoek", "netcarta webmap engine",
			"netmechanic", "netresearchserver", "netscoop", "newscan-online", "nhse", "nokia6682/", "nomad", "noyona", "nutch", "nzexplorer",
			"objectssearch", "occam", "omni", "open text", "openfind", "openintelligencedata", "orb search", "osis-project", "pack rat", "pageboy",
			"pagebull", "page_verifier", "panscient", "parasite", "partnersite", "patric", "pear.", "pegasus", "peregrinator", "pgp key agent", "phantom",
			"phpdig", "picosearch", "piltdownman", "pimptrain", "pinpoint", "pioneer", "piranha", "plumtreewebaccessor", "pogodak", "poirot",
			"pompos", "poppelsdorf", "poppi", "popular iconoclast", "psycheclone", "publisher", "python", "rambler", "raven search", "roach",
			"road runner", "roadhouse", "robbie", "robofox", "robozilla", "rules", "salty", "sbider", "scooter", "scoutjet",
			"scrubby", "search.", "searchprocess", "semanticdiscovery", "senrigan", "sg-scout", "shai'hulud", "shark", "shopwiki", "sidewinder",
			"sift", "silk", "simmany", "site searcher", "site valet", "sitetech-rover", "skymob.com", "sleek", "smartwit", "sna-",
			"snappy", "snooper", "sohu", "speedfind", "sphere", "sphider", "spinner", "spyder", "steeler/", "suke",
			"suntek", "supersnooper", "surfnomore", "sven", "sygol", "szukacz", "tach black widow", "tarantula", "templeton", "/teoma",
			"t-h-u-n-d-e-r-s-t-o-n-e", "theophrastus", "titan", "titin", "tkwww", "toutatis", "t-rex", "tutorgig", "twiceler", "twisted",
			"ucsd", "udmsearch", "url check", "updated", "vagabondo", "valkyrie", "verticrawl", "victoria", "vision-search", "volcano",
			"voyager/", "voyager-hc", "w3c_validator", "w3m2", "w3mir", "walker", "wallpaper", "wanderer", "wauuu", "wavefire",
			"web core","web hopper", "web wombat", "webbandit", "webcatcher", "webcopy", "webfoot", "weblayers", "weblinker", "weblog monitor",
			"webmirror", "webmonkey", "webquest", "webreaper", "websitepulse", "websnarf", "webstolperer", "webvac", "webwalk", "webwatch",
			"webwombat", "webzinger", "wget", "whizbang", "whowhere", "wild ferret", "worldlight", "wwwc", "wwwster", "xenu",
			"xget", "xift", "xirq", "yandex", "yanga", "yeti", "yodao", "zao/", "zippp", "zyborg",
			"...."
		);
		foreach($spiders as $spider) if ( stripos($agent, $spider) !== false ) return true;
		return false;
	}
}