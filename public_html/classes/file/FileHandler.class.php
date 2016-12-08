<?php
class FileHandler
{
	function getRealPath($source) {
		if(strlen($source) >= 2 && substr_compare($source, './', 0, 2) === 0) return _XE_PATH_ . substr($source, 2);
		return $source;
	}

	function copyDir($source_dir, $target_dir, $filter = null, $type = null) {
		$source_dir = self::getRealPath($source_dir);
		$target_dir = self::getRealPath($target_dir);
		if(!is_dir($source_dir)) return FALSE;
		self::makeDir($target_dir);
		if(substr($source_dir, -1) != DIRECTORY_SEPARATOR) $source_dir .= DIRECTORY_SEPARATOR;
		if(substr($target_dir, -1) != DIRECTORY_SEPARATOR) $target_dir .= DIRECTORY_SEPARATOR;
		$oDir = dir($source_dir);
		while($file = $oDir->read()) {
			if($file{0} == '.') continue;
			if($filter && preg_match($filter, $file)) continue;
			if(is_dir($source_dir . $file)) {
				self::copyDir($source_dir . $file, $target_dir . $file, $type);
			} else {
				if($type == 'force') {
					@unlink($target_dir . $file);
				} else {
					if(!file_exists($target_dir . $file)) @copy($source_dir . $file, $target_dir . $file);
				}
			}
		}
		$oDir->close();
	}

	function copyFile($source, $target, $force = 'Y') {
		setlocale(LC_CTYPE, 'en_US.UTF8', 'ko_KR.UTF8');
		$source = self::getRealPath($source);
		$target_dir = self::getRealPath(dirname($target));
		$target = basename($target);
		self::makeDir($target_dir);
		if($force == 'Y') @unlink($target_dir . DIRECTORY_SEPARATOR . $target);
		@copy($source, $target_dir . DIRECTORY_SEPARATOR . $target);
	}

	function readFile($filename) {
		if(($filename = self::exists($filename)) === FALSE || filesize($filename) < 1) return;
		return @file_get_contents($filename);
	}

	function writeFile($filename, $buff, $mode = "w") {
		$filename = self::getRealPath($filename);
		$pathinfo = pathinfo($filename);
		self::makeDir($pathinfo['dirname']);
		$flags = 0;
		if(strtolower($mode) == 'a') $flags = FILE_APPEND;
		@file_put_contents($filename, $buff, $flags|LOCK_EX);
		@chmod($filename, 0644);
	}

	function removeFile($filename) {
		return (($filename = self::exists($filename)) !== FALSE) && @unlink($filename);
	}

	function rename($source, $target) {
		return @rename(self::getRealPath($source), self::getRealPath($target));
	}

	function moveFile($source, $target) {
		if(($source = self::exists($source)) !== FALSE) {
			self::removeFile($target);
			return self::rename($source, $target);
		}
		return FALSE;
	}

	function moveDir($source_dir, $target_dir) {
		self::rename($source_dir, $target_dir);
	}

	function readDir($path, $filter = '', $to_lower = FALSE, $concat_prefix = FALSE) {
		$path = self::getRealPath($path);
		$output = array();
		if(substr($path, -1) != '/') $path .= '/';
		if(!is_dir($path)) return $output;
		$files = scandir($path);
		foreach($files as $file) {
			if($file{0} == '.' || ($filter && !preg_match($filter, $file))) continue;
			if($to_lower) $file = strtolower($file);
			if($filter) $file = preg_replace($filter, '$1', $file);
			if($concat_prefix) $file = sprintf('%s%s', str_replace(_XE_PATH_, '', $path), $file);
			$output[] = str_replace(array('/\\', '//'), '/', $file);
		}
		return $output;
	}

	function makeDir($path_string) {
		if(self::exists($path_string) !== FALSE) return TRUE;
		if(!ini_get('safe_mode')) {
			@mkdir($path_string, 0755, TRUE);
			@chmod($path_string, 0755);
		} else {
			static $oFtp = NULL;
			$ftp_info = Context::getFTPInfo();
			if($oFtp == NULL) {
				if(!Context::isFTPRegisted()) return;
				require_once(_XE_PATH_ . 'libs/ftp.class.php');
				$oFtp = new ftp();
				if(!$ftp_info->ftp_host) $ftp_info->ftp_host = "127.0.0.1";
				if(!$ftp_info->ftp_port) $ftp_info->ftp_port = 21;
				if(!$oFtp->ftp_connect($ftp_info->ftp_host, $ftp_info->ftp_port)) return;
				if(!$oFtp->ftp_login($ftp_info->ftp_user, $ftp_info->ftp_password)) {
					$oFtp->ftp_quit();
					return;
				}
			}
			if(!($ftp_path = $ftp_info->ftp_root_path)) $ftp_path = DIRECTORY_SEPARATOR;
			$path_string = str_replace(_XE_PATH_, '', $path_string);
			$path_list = explode(DIRECTORY_SEPARATOR, $path_string);
			$path = _XE_PATH_;
			for($i = 0, $c = count($path_list); $i < $c; $i++) {
				if(!$path_list[$i]) continue;
				$path .= $path_list[$i] . DIRECTORY_SEPARATOR;
				$ftp_path .= $path_list[$i] . DIRECTORY_SEPARATOR;
				if(!is_dir($path)) {
					$oFtp->ftp_mkdir($ftp_path);
					$oFtp->ftp_site("CHMOD 777 " . $ftp_path);
				}
			}
		}
		return is_dir($path_string);
	}

	function removeDir($path) {
		if(($path = self::isDir($path)) === FALSE) return;
		if(self::isDir($path)) {
			$files = array_diff(scandir($path), array('..', '.'));
			foreach($files as $file) {
				if(($target = self::getRealPath($path . DIRECTORY_SEPARATOR . $file)) === FALSE) continue;
				if(is_dir($target)) self::removeDir($target);
				else unlink($target);
			}
			rmdir($path);
		} else {
			unlink($path);
		}
	}

	function removeBlankDir($path) {
		if(($path = self::isDir($path)) === FALSE) return;
		$files = array_diff(scandir($path), array('..', '.'));
		if(count($files) < 1) {
			rmdir($path);
			return;
		}
		foreach($files as $file) {
			if(($target = self::isDir($path . DIRECTORY_SEPARATOR . $file)) === FALSE) continue;
			self::removeBlankDir($target);
		}
	}

	function removeFilesInDir($path) {
		if(($path = self::getRealPath($path)) === FALSE) return;
		if(is_dir($path)) {
			$files = array_diff(scandir($path), array('..', '.'));
			foreach($files as $file) {
				if(($target = self::getRealPath($path . DIRECTORY_SEPARATOR . $file)) === FALSE) continue;
				if(is_dir($target)) self::removeFilesInDir($target);
				else unlink($target);
			}
		} else {
			if(self::exists($path)) unlink($path);
		}
	}

	function filesize($size) {
		if(!$size) return '0Byte';
		if($size === 1) return '1Byte';
		if($size < 1024) return $size . 'Bytes';
		if($size >= 1024 && $size < 1024 * 1024) return sprintf("%0.1fKB", $size / 1024);
		return sprintf("%0.2fMB", $size / (1024 * 1024));
	}

	function getRemoteResource($url, $body = null, $timeout = 3, $method = 'GET', $content_type = null, $headers = array(), $cookies = array(), $post_data = array(), $request_config = array()) {
		require_once(_XE_PATH_ . 'libs/idna_convert/idna_convert.class.php');
		$IDN = new idna_convert(array('idn_version' => 2008));
		$url = $IDN->encode($url);
		try {
			requirePear();
			require_once('HTTP/Request.php');
			$parsed_url = parse_url(__PROXY_SERVER__);
			if($parsed_url["host"] && $parsed_url["path"]) {
				$oRequest = new HTTP_Request(__PROXY_SERVER__);
				$oRequest->setMethod('POST');
				$oRequest->addPostData('arg', serialize(array('Destination' => $url, 'method' => $method, 'body' => $body, 'content_type' => $content_type, "headers" => $headers, "post_data" => $post_data)));
			} else {
				$oRequest = new HTTP_Request($url);
				if($parsed_url['host']) {
					$request_config['proxy_host'] = $parsed_url['host'];
					$request_config['proxy_port'] = $parsed_url['port'] ? $parsed_url['port'] : '';
					$request_config['proxy_user'] = rawurldecode($parsed_url['user'] ? $parsed_url['user'] : '');
					$request_config['proxy_password'] = rawurldecode($parsed_url['pass'] ? $parsed_url['pass'] : '');
					$request_config['proxy_type'] = $parsed_url['scheme'] ? $parsed_url['scheme'] : 'http';
				}
				if(count($request_config) && method_exists($oRequest, 'setConfig')) {
					foreach($request_config as $key=>$val) {
						if($key === 'observers') {
							foreach($val as $observer) $oRequest->attach($observer);
						} else {
							$oRequest->setConfig($key, $val);
						}
					}
				}
				if(method_exists($oRequest, 'setConfig')) {
					if(extension_loaded('curl')) $oRequest->setConfig('adapter', 'curl');
					elseif(version_compare(PHP_VERSION, '5.6', '<')) $oRequest->setConfig('ssl_verify_host', false);
					if(file_exists(_XE_PATH_ . 'libs/cacert/cacert.pem')) $oRequest->setConfig('ssl_cafile', _XE_PATH_ . 'libs/cacert/cacert.pem');
				}
				if(count($headers) > 0) {
					foreach($headers as $key => $val) $oRequest->addHeader($key, $val);
				}
				$host = parse_url($url, PHP_URL_HOST);
				if($cookies[$host]) {
					foreach($cookies[$host] as $key => $val) $oRequest->addCookie($key, $val);
				}
				if(count($post_data) > 0) {
					foreach($post_data as $key => $val) $oRequest->addPostData($key, $val);
				}
				if(!$content_type) $oRequest->addHeader('Content-Type', 'text/html');
				else $oRequest->addHeader('Content-Type', $content_type);
				$oRequest->setMethod($method);
				if($body) $oRequest->setBody($body);
			}
			if(method_exists($oRequest, 'setConfig')) $oRequest->setConfig('timeout', $timeout);
			elseif(property_exists($oRequest, '_timeout')) $oRequest->_timeout = $timeout;
			$oResponse = $oRequest->sendRequest();
			$code = $oRequest->getResponseCode();
			$header = $oRequest->getResponseHeader();
			$response = $oRequest->getResponseBody();
			if($c = $oRequest->getResponseCookies()) {
				foreach($c as $k => $v) $cookies[$host][$v['name']] = $v['value'];
			}
			if($code > 300 && $code < 399 && $header['location'])
				return self::getRemoteResource($header['location'], $body, $timeout, $method, $content_type, $headers, $cookies, $post_data);
			if($code != 200) return;
			if(isset($request_config['store_body']) && !$request_config['store_body']) return TRUE;
			else return $response;
		} catch(Exception $e) {
			return NULL;
		}
	}

	function getRemoteFile($url, $target_filename, $body = null, $timeout = 3, $method = 'GET', $content_type = null, $headers = array(), $cookies = array(), $post_data = array(), $request_config = array()) {
		$target_filename = self::getRealPath($target_filename);
		self::writeFile($target_filename, '');
		requirePear();
		require_once('HTTP/Request2/Observer/Download.php');
		$request_config['store_body'] = false;
		$request_config['observers'][] = new HTTP_Request2_Observer_Download($target_filename);
		try {
			$result = self::getRemoteResource($url, $body, $timeout, $method, $content_type, $headers, $cookies, $post_data, $request_config);
		} catch(Exception $e) {
			return FALSE;
		}
		return $result ? TRUE : FALSE;
	}

	function returnBytes($val) {
		$unit = strtoupper(substr($val, -1));
		$val = (float)$val;
		switch ($unit) {
			case 'G': $val *= 1024;
			case 'M': $val *= 1024;
			case 'K': $val *= 1024;
		}
		return round($val);
	}

	function checkMemoryLoadImage(&$imageInfo) {
		$K64 = 65536;
		$TWEAKFACTOR = 2.0;
		$channels = $imageInfo['channels'];
		if(!$channels) $channels = 6;
		$memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $channels / 8 + $K64 ) * $TWEAKFACTOR);
		$availableMemory = self::returnBytes(ini_get('memory_limit')) - memory_get_usage();
		if($availableMemory < $memoryNeeded) return FALSE;
		return TRUE;
	}

	function createImageFile($source_file, $target_file, $resize_width = 0, $resize_height = 0, $target_type = '', $thumbnail_type = 'crop') {
		if (($source_file = self::exists($source_file)) === FALSE) return;
		$target_file = self::getRealPath($target_file);
		if(!$resize_width) $resize_width = 100;
		if(!$resize_height) $resize_height = $resize_width;
		$imageInfo = getimagesize($source_file);
		if(!self::checkMemoryLoadImage($imageInfo)) return FALSE;
		list($width, $height, $type, $attrs) = $imageInfo;
		if($width < 1 || $height < 1) return;
		switch($type) {
			case '1' : $type = 'gif'; break;
			case '2' : $type = 'jpg'; break;
			case '3' : $type = 'png'; break;
			case '6' : $type = 'bmp'; break;
			default : return;
		}
		if(!$target_type) $target_type = $type;
		$target_type = strtolower($target_type);
		$width_per = ($resize_width > 0 && $width >= $resize_width) ? $resize_width / $width : 1;
		$height_per = ($resize_height > 0 && $height >= $resize_height) ? $resize_height / $height : 1;
		$per = NULL;
		if($thumbnail_type == 'ratio') {
			$per = ($width_per > $height_per) ? $height_per : $width_per;
			$resize_width = $width * $per;
			$resize_height = $height * $per;
		} else {
			$per = ($width_per < $height_per) ? $height_per : $width_per;
		}
		$thumb = NULL;
		if(function_exists('imagecreateTRUEcolor')) $thumb = imagecreateTRUEcolor($resize_width, $resize_height);
		else if(function_exists('imagecreate')) $thumb = imagecreate($resize_width, $resize_height);
		if(!$thumb) return FALSE;
		imagefilledrectangle($thumb, 0, 0, $resize_width - 1, $resize_height - 1, imagecolorallocate($thumb, 255, 255, 255));
		$source = NULL;
		switch($type) {
			case 'gif' :
				if(function_exists('imagecreatefromgif')) $source = @imagecreatefromgif($source_file);
			break;
			case 'jpeg' :
			case 'jpg' :
				if(function_exists('imagecreatefromjpeg')) $source = @imagecreatefromjpeg($source_file);
			break;
			case 'png' :
				if(function_exists('imagecreatefrompng')) $source = @imagecreatefrompng($source_file);
			break;
			case 'wbmp' :
			case 'bmp' :
				if(function_exists('imagecreatefromwbmp')) $source = @imagecreatefromwbmp($source_file);
			break;
		}
		if(!$source) {
			imagedestroy($thumb);
			return FALSE;
		}
		$new_width = (int) ($width * $per);
		$new_height = (int) ($height * $per);
		$x = 0;
		$y = 0;
		if($thumbnail_type == 'crop') {
			$x = (int) ($resize_width / 2 - $new_width / 2);
			$y = (int) ($resize_height / 2 - $new_height / 2);
		}
		if(function_exists('imagecopyresampled')) imagecopyresampled($thumb, $source, $x, $y, 0, 0, $new_width, $new_height, $width, $height);
		else imagecopyresized($thumb, $source, $x, $y, 0, 0, $new_width, $new_height, $width, $height);
		self::makeDir(dirname($target_file));
		$output = NULL;
		switch($target_type) {
			case 'gif' :
				if(function_exists('imagegif')) $output = imagegif($thumb, $target_file);
			break;
			case 'jpeg' :
			case 'jpg' :
				if(function_exists('imagejpeg')) $output = imagejpeg($thumb, $target_file, 100);
			break;
			case 'png' :
				if(function_exists('imagepng')) $output = imagepng($thumb, $target_file, 9);
			break;
			case 'wbmp' :
			case 'bmp' :
				if(function_exists('imagewbmp')) $output = imagewbmp($thumb, $target_file, 100);
			break;
		}
		imagedestroy($thumb);
		imagedestroy($source);
		if(!$output) return FALSE;
		@chmod($target_file, 0644);
		return TRUE;
	}

	function readIniFile($filename) {
		if(($filename = self::exists($filename)) === FALSE) return FALSE;
		$arr = parse_ini_file($filename, TRUE);
		if(is_array($arr) && count($arr) > 0) return $arr;
		else return array();
	}

	function writeIniFile($filename, $arr) {
		if(!is_array($arr) || count($arr) == 0) return FALSE;
		self::writeFile($filename, self::_makeIniBuff($arr));
		return TRUE;
	}

	function _makeIniBuff($arr) {
		$return = array();
		foreach($arr as $key => $val) {
			if(is_array($val)) {
				$return[] = sprintf("[%s]", $key);
				foreach($val as $k => $v) $return[] = sprintf("%s=\"%s\"", $k, $v);
			} else if(is_object($val)) {
				continue;
			} else {
				$return[] = sprintf("%s=\"%s\"", $key, $val);
			}
		}
		return join("\n", $return);
	}

	function openFile($filename, $mode) {
		$pathinfo = pathinfo($filename);
		self::makeDir($pathinfo['dirname']);
		require_once("FileObject.class.php");
		return  new FileObject($filename, $mode);
	}

	function hasContent($filename) {
		return (is_readable($filename) && (filesize($filename) > 0));
	}

	function exists($filename) {
		$filename = self::getRealPath($filename);
		return file_exists($filename) ? $filename : FALSE;
	}

	function isDir($path) {
		$path = self::getRealPath($path);
		return is_dir($path) ? $path : FALSE;
	}

	function isWritableDir($path) {
		$path = self::getRealPath($path);
		if(is_dir($path)==FALSE) return FALSE;
		$checkFile = $path . '/_CheckWritableDir';
		$fp = fopen($checkFile, 'w');
		if(!is_resource($fp)) return FALSE;
		fclose($fp);
		self::removeFile($checkFile);
		return TRUE;
	}
}
