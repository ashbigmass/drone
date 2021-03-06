<?php
class PHPExcel_CachedObjectStorage_DiscISAM extends PHPExcel_CachedObjectStorage_CacheBase implements PHPExcel_CachedObjectStorage_ICache
{
	private $_fileName = null;
	private $_fileHandle = null;
	private $_cacheDirectory = NULL;

	private function _storeData() {
		if ($this->_currentCellIsDirty) {
			$this->_currentObject->detach();
			fseek($this->_fileHandle,0,SEEK_END);
			$offset = ftell($this->_fileHandle);
			fwrite($this->_fileHandle, serialize($this->_currentObject));
			$this->_cellCache[$this->_currentObjectID]	= array('ptr' => $offset, 'sz'  => ftell($this->_fileHandle) - $offset);
			$this->_currentCellIsDirty = false;
		}
		$this->_currentObjectID = $this->_currentObject = null;
	}

	public function addCacheData($pCoord, PHPExcel_Cell $cell) {
		if (($pCoord !== $this->_currentObjectID) && ($this->_currentObjectID !== null)) $this->_storeData();
		$this->_currentObjectID = $pCoord;
		$this->_currentObject = $cell;
		$this->_currentCellIsDirty = true;
		return $cell;
	}

	public function getCacheData($pCoord) {
		if ($pCoord === $this->_currentObjectID) return $this->_currentObject;
		$this->_storeData();
		if (!isset($this->_cellCache[$pCoord])) return null;
		$this->_currentObjectID = $pCoord;
		fseek($this->_fileHandle,$this->_cellCache[$pCoord]['ptr']);
		$this->_currentObject = unserialize(fread($this->_fileHandle,$this->_cellCache[$pCoord]['sz']));
		$this->_currentObject->attach($this->_parent);
		return $this->_currentObject;
	}

	public function copyCellCollection(PHPExcel_Worksheet $parent) {
		parent::copyCellCollection($parent);
		$baseUnique = $this->_getUniqueID();
		$newFileName = $this->_cacheDirectory.'/PHPExcel.'.$baseUnique.'.cache';
		copy ($this->_fileName,$newFileName);
		$this->_fileName = $newFileName;
		$this->_fileHandle = fopen($this->_fileName,'a+');
	}

	public function unsetWorksheetCells() {
		if(!is_null($this->_currentObject)) {
			$this->_currentObject->detach();
			$this->_currentObject = $this->_currentObjectID = null;
		}
		$this->_cellCache = array();
		$this->_parent = null;
		$this->__destruct();
	}

	public function __construct(PHPExcel_Worksheet $parent, $arguments) {
		$this->_cacheDirectory	= ((isset($arguments['dir'])) && ($arguments['dir'] !== NULL)) ? $arguments['dir'] : PHPExcel_Shared_File::sys_get_temp_dir();
		parent::__construct($parent);
		if (is_null($this->_fileHandle)) {
			$baseUnique = $this->_getUniqueID();
			$this->_fileName = $this->_cacheDirectory.'/PHPExcel.'.$baseUnique.'.cache';
			$this->_fileHandle = fopen($this->_fileName,'a+');
		}
	}

	public function __destruct() {
		if (!is_null($this->_fileHandle)) {
			fclose($this->_fileHandle);
			unlink($this->_fileName);
		}
		$this->_fileHandle = null;
	}
}
