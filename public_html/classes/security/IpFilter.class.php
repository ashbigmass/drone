<?php
class IpFilter
{
	public function filter($ip_list, $ip = NULL) {
		if(!$ip) $ip = $_SERVER['REMOTE_ADDR'];
		$long_ip = ip2long($ip);
		foreach($ip_list as $filter_ip) {
			$range = explode('-', $filter_ip);
			if(!$range[1]) {
				$star_pos = strpos($filter_ip, '*');
				if($star_pos !== FALSE ) if(strncmp($filter_ip, $ip, $star_pos)===0) return true;
				else if(strcmp($filter_ip, $ip)===0) return true;
			} else if(ip2long($range[0]) <= $long_ip && ip2long($range[1]) >= $long_ip) {
				return true;
			}
		}
		return false;
	}

	public function validate($ip_list = array()) {
		$regex = "/^
			(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)
			(?:
				(?:
					(?:\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}
					(?:-(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){1}
					(?:\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}
				)
				|
				(?:
					(?:\.(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)|\*)){3}
				)
			)
		$/";
		$regex = str_replace(array("\r\n", "\n", "\r","\t"," "), '', $regex);
		foreach($ip_list as $i => $ip) {
			preg_match($regex, $ip, $matches);
			if(!count($matches)) return false;
		}
		return true;
	}
}
