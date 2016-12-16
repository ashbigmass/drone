<?php
class ttfInfo
{
	protected $_dirRestriction = 1;
	protected $_recursive = 0;
	protected $fontsdir;
	protected $filename;

	public function setFontFile($data) {
		if ($this->_dirRestriction && preg_match('[\.\/|\.\.\/]', $data)) $this->exitClass('Error: Directory restriction is enforced!');
		$this->filename = $data;
		return $this;
	}

	public function setFontsDir($data) {
		if ($this->_dirRestriction && preg_match('[\.\/|\.\.\/]', $data)) $this->exitClass('Error: Directory restriction is enforced!');
		$this->fontsdir = $data;
		return $this;
	}

	public function readFontsDir() {
		if (empty($this->fontsdir)) { $this->exitClass('Error: Fonts Directory has not been set with setFontsDir().'); }
		if (empty($this->backupDir)){ $this->backupDir = $this->fontsdir; }
		$this->array = array();
		$d = dir($this->fontsdir);
		while (false !== ($e = $d->read())) {
			if($e != '.' && $e != '..') {
				$e = $this->fontsdir . $e;
				if($this->_recursive && is_dir($e)) {
					$this->setFontsDir($e);
					$this->array = array_merge($this->array, readFontsDir());
				} else if ($this->is_ttf($e) === true) {
					$this->setFontFile($e);
					$this->array[$e] = $this->getFontInfo();
				}
			}
		}
		if (!empty($this->backupDir)){ $this->fontsdir = $this->backupDir; }
		$d->close();
		return $this;
	}

	public function getFontInfo() {
		$fd = fopen ($this->filename, "r");
		$this->text = fread ($fd, filesize($this->filename));
		fclose ($fd);
		unset($fd);
		$number_of_tables = hexdec($this->dec2ord($this->text[4]).$this->dec2ord($this->text[5]));
		for ($i=0;$i<$number_of_tables;$i++) {
			$tag = $this->text[12+$i*16].$this->text[12+$i*16+1].$this->text[12+$i*16+2].$this->text[12+$i*16+3];
			if ($tag == 'name') {
				$this->ntOffset = hexdec(
					$this->dec2ord($this->text[12+$i*16+8]).$this->dec2ord($this->text[12+$i*16+8+1]).
					$this->dec2ord($this->text[12+$i*16+8+2]).$this->dec2ord($this->text[12+$i*16+8+3])
				);
				$offset_storage_dec = hexdec($this->dec2ord($this->text[$this->ntOffset+4]).$this->dec2ord($this->text[$this->ntOffset+5]));
				$number_name_records_dec = hexdec($this->dec2ord($this->text[$this->ntOffset+2]).$this->dec2ord($this->text[$this->ntOffset+3]));
			}
		}
		$storage_dec = $offset_storage_dec + $this->ntOffset;
		$storage_hex = strtoupper(dechex($storage_dec));
		for ($j=0;$j<$number_name_records_dec;$j++) {
			$platform_id_dec	= hexdec($this->dec2ord($this->text[$this->ntOffset+6+$j*12+0]).$this->dec2ord($this->text[$this->ntOffset+6+$j*12+1]));
			$name_id_dec		= hexdec($this->dec2ord($this->text[$this->ntOffset+6+$j*12+6]).$this->dec2ord($this->text[$this->ntOffset+6+$j*12+7]));
			$string_length_dec	= hexdec($this->dec2ord($this->text[$this->ntOffset+6+$j*12+8]).$this->dec2ord($this->text[$this->ntOffset+6+$j*12+9]));
			$string_offset_dec	= hexdec($this->dec2ord($this->text[$this->ntOffset+6+$j*12+10]).$this->dec2ord($this->text[$this->ntOffset+6+$j*12+11]));
			if (!empty($name_id_dec) and empty($font_tags[$name_id_dec])) {
				for($l=0;$l<$string_length_dec;$l++) {
					if (ord($this->text[$storage_dec+$string_offset_dec+$l]) == '0') { continue; }
					else { $font_tags[$name_id_dec] .= ($this->text[$storage_dec+$string_offset_dec+$l]); }
				}
			}
		}
		return $font_tags;
	}

	public function getCopyright() {
		$this->info = $this->getFontInfo();
		return $this->info[0];
	}

	public function getFontFamily() {
		$this->info = $this->getFontInfo();
		return $this->info[1];
	}

	public function getFontSubFamily() {
		$this->info = $this->getFontInfo();
		return $this->info[2];
	}

	public function getFontId() {
		$this->info = $this->getFontInfo();
		return $this->info[3];
	}

	public function getFullFontName() {
		$this->info = $this->getFontInfo();
		return $this->info[4];
	}

	protected function dec2ord($dec) {
		return $this->dec2hex(ord($dec));
	}

	protected function dec2hex($dec) {
		return str_repeat('0', 2-strlen(($hex=strtoupper(dechex($dec))))) . $hex;
	}

	protected function exitClass($message) {
		echo $message;
		exit;
	}

	protected function is_ttf($file) {
		$ext = explode('.', $file);
		$ext = $ext[count($ext)-1];
		return preg_match("/ttf$/i",$ext) ? true : false;
	}
}

function getFontInfo($resource) {
	$ttfInfo = new ttfInfo;
	$ttfInfo->setFontFile($resource);
	return $ttfInfo->getFontInfo();
}
