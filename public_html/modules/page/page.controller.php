<?php
class pageController extends page
{
	var $target_path = '';

	function init() { }

	function getTitle($content) {
		preg_match('!<title([^>]*)>(.*?)<\/title>!is', $content, $buff);
		return trim($buff[2]);
	}

	function getHeadScript($content) {
		$content = preg_replace('!<title([^>]*)>(.*?)<\/title>!is','', $content);
		$content = preg_replace('!<(\/){0,1}meta([^>]*)>!is','', $content);
		preg_match_all('!<link([^>]*)>!is', $content, $link_buff);
		for($i=0;$i<count($link_buff[0]);$i++) {
			$tmp_str = trim($link_buff[0][$i]);
			if(!$tmp_str) continue;
			$header_script .=  $tmp_str."\n";
		}
		preg_match_all('!<(style|script)(.*?)<\/(style|script)>!is', $content, $script_buff);
		for($i=0;$i<count($script_buff[0]);$i++) {
			$tmp_str = trim($script_buff[0][$i]);
			if(!$tmp_str) continue;
			$header_script .=  $tmp_str."\n";
		}
		return $header_script;
	}

	function getBodyScript($content) {
		preg_match('!<body([^>]*)>(.*?)<\/body>!is', $content, $body_buff);
		$body_script = $body_buff[2];
		$body_script = preg_replace('!<link([^>]*)>!is', '', $body_script);
		$body_script = preg_replace('!<(style|script)(.*?)<\/(style|script)>!is', '', $body_script);
		return $body_script;
	}

	function replaceSrc($content, $path) {
		$url_info = parse_url($path);
		$host = sprintf("%s://%s%s",$url_info['scheme'],$url_info['host'],$url_info['port']?':'.$url_info['port']:'');
		$this->host = $host.'/';
		$path = $url_info['path'];
		if(substr($path,-1)=='/') $path = substr($path,-1);
		$t = explode('/',$path);
		$_t = array();
		for($i=0,$c=count($t)-1;$i<$c;$i++) {
			$v = trim($t[$i]);
			if(!$v) continue;
			$_t[] = $v;
		}
		$path = $host.'/'.implode('/',$_t);
		if(substr($path,-1)!='/') $path .= '/';
		$this->path = $path;
		$content = preg_replace_callback('/(src=|href=|url\()("|\')?([^"\'\)]+)("|\'\))?/is',array($this,'_replacePath'),$content);
		return $content;
	}

	function _replacePath($matches) {
		$val = trim($matches[3]);
		if(preg_match('/^(http|https|ftp|telnet|mms|mailto)/i',$val)) return $matches[0];
		if(substr($val,0,2)=='./') $path = $this->path.substr($val,2);
		elseif(substr($val,0,1)=='/') $path = $this->host.substr($val,1);
		else $path = $this->path.$val;
		return sprintf("%s%s%s%s", $matches[1], $matches[2], $path, $matches[4]);
	}
}
