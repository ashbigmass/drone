<?php
class tar {
	var $filename;
	var $isGzipped;
	var $tar_file;
	var $files;
	var $directories;
	var $numFiles;
	var $numDirectories;

	function tar() {
		return true;
	}

	function __computeUnsignedChecksum($bytestring) {
		for($i=0; $i<512; $i++) $unsigned_chksum += ord($bytestring[$i]);
		for($i=0; $i<8; $i++) $unsigned_chksum -= ord($bytestring[148 + $i]);
		$unsigned_chksum += ord(" ") * 8;
		return $unsigned_chksum;
	}

	function __parseNullPaddedString($string) {
		$position = strpos($string,chr(0));
		if(!$position) $position = strlen($string);
		return substr($string,0,$position);
	}

	function __parseTar() {
		$tar_length = strlen($this->tar_file);
		$main_offset = 0;
		$flag_longlink = false;
		while($main_offset < $tar_length) {
			if(substr($this->tar_file,$main_offset,512) == str_repeat(chr(0),512)) break;
			$file_name	  = $this->__parseNullPaddedString(substr($this->tar_file,$main_offset,100));
			$file_mode	  = substr($this->tar_file,$main_offset + 100,8);
			$file_uid	   = octdec(substr($this->tar_file,$main_offset + 108,8));
			$file_gid	   = octdec(substr($this->tar_file,$main_offset + 116,8));
			$file_size	  = octdec(substr($this->tar_file,$main_offset + 124,12));
			$file_time	  = octdec(substr($this->tar_file,$main_offset + 136,12));
			$file_chksum		= octdec(substr($this->tar_file,$main_offset + 148,6));
			$file_uname	 = $this->__parseNullPaddedString(substr($this->tar_file,$main_offset + 265,32));
			$file_gname	 = $this->__parseNullPaddedString(substr($this->tar_file,$main_offset + 297,32));
			$file_type = substr($this->tar_file,$main_offset + 156,1);
			if($this->__computeUnsignedChecksum(substr($this->tar_file,$main_offset,512)) != $file_chksum) return false;
			$file_contents	  = substr($this->tar_file,$main_offset + 512,$file_size);
			if(strtolower($file_type) == 'l' || $file_name == '././@LongLink') {
				$flag_longlink = true;
				$longlink_name = $this->__parseNullPaddedString($file_contents);
			} elseif($file_type == '0') {
				$this->numFiles++;
				$activeFile = &$this->files[];
				if($flag_longlink) $activeFile["name"]	 = $longlink_name;
				else $activeFile["name"]	 = $file_name;
				$activeFile["type"]	 = $file_type;
				$activeFile["mode"]	 = $file_mode;
				$activeFile["size"]	 = $file_size;
				$activeFile["time"]	 = $file_time;
				$activeFile["user_id"]	  = $file_uid;
				$activeFile["group_id"]	 = $file_gid;
				$activeFile["user_name"]	= $file_uname;
				$activeFile["group_name"]   = $file_gname;
				$activeFile["checksum"]	 = $file_chksum;
				$activeFile["file"]	 = $file_contents;
				$flag_longlink = false;
			} elseif($file_type == '5') {
				$this->numDirectories++;
				$activeDir = &$this->directories[];
				if($flag_longlink) $activeDir["name"]	 = $longlink_name;
				else $activeDir["name"]	 = $file_name;
				$activeDir["type"]	  = $file_type;
				$activeDir["mode"]	  = $file_mode;
				$activeDir["time"]	  = $file_time;
				$activeDir["user_id"]	   = $file_uid;
				$activeDir["group_id"]	  = $file_gid;
				$activeDir["user_name"]	 = $file_uname;
				$activeDir["group_name"]	= $file_gname;
				$activeDir["checksum"]	  = $file_chksum;
				$flag_longlink = false;
			}
			$main_offset += 512 + (ceil($file_size / 512) * 512);
		}
		return true;
	}

	function __readTar($filename='') {
		if(!$filename) $filename = $this->filename;
		$fp = fopen($filename,"rb");
		$this->tar_file = fread($fp,filesize($filename));
		fclose($fp);
		if($this->tar_file[0] == chr(31) && $this->tar_file[1] == chr(139) && $this->tar_file[2] == chr(8)) {
			if(!function_exists("gzinflate")) return false;
			$this->isGzipped = TRUE;
			$this->tar_file = gzinflate(substr($this->tar_file,10,-4));
		}
		$this->__parseTar();
		return true;
	}

	function __generateTAR() {
		unset($this->tar_file);
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				unset($header);
				$header .= str_pad($information["name"],100,chr(0));
				$header .= str_pad(decoct($information["mode"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["user_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["group_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct(0),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["time"]),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_repeat(" ",8);
				$header .= "5";
				$header .= str_repeat(chr(0),100);
				$header .= str_pad("ustar",6,chr(32));
				$header .= chr(32) . chr(0);
				$header .= str_pad("",32,chr(0));
				$header .= str_pad("",32,chr(0));
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),155);
				$header .= str_repeat(chr(0),12);
				$checksum = str_pad(decoct($this->__computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
				for($i=0; $i<6; $i++) $header[(148 + $i)] = substr($checksum,$i,1);
				$header[154] = chr(0);
				$header[155] = chr(32);
				$this->tar_file .= $header;
			}
		}
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				unset($header);
				$header .= str_pad($information["name"],100,chr(0));
				$header .= str_pad(decoct($information["mode"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["user_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["group_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["size"]),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["time"]),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_repeat(" ",8);
				$header .= "0";
				$header .= str_repeat(chr(0),100);
				$header .= str_pad("ustar",6,chr(32));
				$header .= chr(32) . chr(0);
				$header .= str_pad($information["user_name"],32,chr(0));	// How do I get a file's user name from PHP?
				$header .= str_pad($information["group_name"],32,chr(0));   // How do I get a file's group name from PHP?
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),155);
				$header .= str_repeat(chr(0),12);
				$checksum = str_pad(decoct($this->__computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
				for($i=0; $i<6; $i++) $header[(148 + $i)] = substr($checksum,$i,1);
				$header[154] = chr(0);
				$header[155] = chr(32);
				$file_contents = str_pad($information["file"],(ceil($information["size"] / 512) * 512),chr(0));
				$this->tar_file .= $header . $file_contents;
			}
		}
		$this->tar_file .= str_repeat(chr(0),512);
		return true;
	}

	function openTAR($filename) {
		unset($this->filename);
		unset($this->isGzipped);
		unset($this->tar_file);
		unset($this->files);
		unset($this->directories);
		unset($this->numFiles);
		unset($this->numDirectories);
		if(!file_exists($filename)) return false;
		$this->filename = $filename;
		$this->__readTar();
		return true;
	}

	function appendTar($filename) {
		if(!file_exists($filename)) return false;
		$this->__readTar($filename);
		return true;
	}

	function getFile($filename) {
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				if($information["name"] == $filename) return $information;
			}
		}
		return false;
	}

	function getDirectory($dirname) {
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				if($information["name"] == $dirname) return $information;
			}
		}
		return false;
	}

	function containsFile($filename) {
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				if($information["name"] == $filename) return true;
			}
		}
		return false;
	}

	function containsDirectory($dirname) {
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				if($information["name"] == $dirname) return true;
			}
		}
		return false;
	}

	function addDirectory($dirname) {
		if(!file_exists($dirname)) return false;
		$file_information = stat($dirname);
		$this->numDirectories++;
		$activeDir	  = &$this->directories[];
		$activeDir["name"]  = $dirname;
		$activeDir["mode"]  = $file_information["mode"];
		$activeDir["time"]  = $file_information["time"];
		$activeDir["user_id"]   = $file_information["uid"];
		$activeDir["group_id"]  = $file_information["gid"];
		$activeDir["checksum"]  = $checksum;
		return true;
	}

	function addFile($filename,$from=null,$to=null) {
		if(!file_exists($filename)) return false;
		if(filesize($filename)==0) return false;
		if($this->containsFile($filename)) return false;
		$file_information = stat($filename);
		$fp = fopen($filename,"rb");
		$file_contents = fread($fp,filesize($filename));
		fclose($fp);
		if($from && $to){
			$file_contents = str_replace($from,$to,$file_contents);
			$file_information["size"] = strlen($file_contents);
		}
		$this->numFiles++;
		$activeFile		 = &$this->files[];
		$activeFile["name"]	 = $filename;
		$activeFile["mode"]	 = $file_information["mode"];
		$activeFile["user_id"]	  = $file_information["uid"];
		$activeFile["group_id"]	 = $file_information["gid"];
		$activeFile["size"]	 = $file_information["size"];
		$activeFile["time"]	 = $file_information["mtime"];
		$activeFile["checksum"]	 = $checksum;
		$activeFile["user_name"]	= "";
		$activeFile["group_name"]   = "";
		$activeFile["file"]	 = $file_contents;
		return true;
	}

	function removeFile($filename) {
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				if($information["name"] == $filename) {
					$this->numFiles--;
					unset($this->files[$key]);
					return true;
				}
			}
		}
		return false;
	}

	function removeDirectory($dirname) {
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				if($information["name"] == $dirname) {
					$this->numDirectories--;
					unset($this->directories[$key]);
					return true;
				}
			}
		}
		return false;
	}

	function saveTar() {
		if(!$this->filename) return false;
		$this->toTar($this->filename,$this->isGzipped);
		return true;
	}

	function toTar($filename,$useGzip) {
		if(!$filename) return false;
		$this->__generateTar();
		if($useGzip) {
			if(!function_exists("gzencode")) return false;
			$file = gzencode($this->tar_file);
		} else {
			$file = $this->tar_file;
		}
		$fp = fopen($filename,"wb");
		fwrite($fp,$file);
		fclose($fp);
		return true;
	}

	function toTarStream() {
		$this->__generateTar();
		return $this->tar_file;
	}
}
?>
