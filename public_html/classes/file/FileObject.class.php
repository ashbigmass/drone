<?php
class FileObject extends Object
{
	var $fp = NULL;
	var $path = NULL;
	var $mode = "r";

	function FileObject($path, $mode) {
		if($path != NULL) $this->Open($path, $mode);
	}

	function append($file_name) {
		$target = new FileObject($file_name, "r");
		while(!$target->feof()) {
			$readstr = $target->read();
			$this->write($readstr);
		}
		$target->close();
	}

	function feof() {
		return feof($this->fp);
	}

	function read($size = 1024) {
		return fread($this->fp, $size);
	}

	function write($str) {
		$len = strlen($str);
		if(!$str || $len <= 0) return FALSE;
		if(!$this->fp) return FALSE;
		$written = fwrite($this->fp, $str);
		return $written;
	}

	function open($path, $mode) {
		if($this->fp != NULL) $this->close();
		$this->fp = fopen($path, $mode);
		if(!is_resource($this->fp)) {
			$this->fp = NULL;
			return FALSE;
		}
		$this->path = $path;
		return TRUE;
	}

	function getPath() {
		if($this->fp != NULL) return $this->path;
		else return NULL;
	}

	function close() {
		if($this->fp != NULL) {
			fclose($this->fp);
			$this->fp = NULL;
		}
	}
}
