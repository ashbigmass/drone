<?php
class PHPExcel_CachedObjectStorage_SQLite3 extends PHPExcel_CachedObjectStorage_CacheBase implements PHPExcel_CachedObjectStorage_ICache
{
	private $_TableName = null;
	private $_DBHandle = null;

	private function _storeData() {
		if ($this->_currentCellIsDirty) {
			$this->_currentObject->detach();
			$query = $this->_DBHandle->prepare("INSERT OR REPLACE INTO kvp_".$this->_TableName." VALUES(:id,:data)");
			$query->bindValue('id',$this->_currentObjectID,SQLITE3_TEXT);
			$query->bindValue('data',serialize($this->_currentObject),SQLITE3_BLOB);
			$result = $query->execute();
			if ($result === false) throw new Exception($this->_DBHandle->lastErrorMsg());
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
		$query = "SELECT value FROM kvp_".$this->_TableName." WHERE id='".$pCoord."'";
		$cellResult = $this->_DBHandle->querySingle($query);
		if ($cellResult === false) throw new Exception($this->_DBHandle->lastErrorMsg());
		elseif (is_null($cellResult)) return null;
		$this->_currentObjectID = $pCoord;
		$this->_currentObject = unserialize($cellResult);
		$this->_currentObject->attach($this->_parent);
		return $this->_currentObject;
	}

	public function isDataSet($pCoord) {
		if ($pCoord === $this->_currentObjectID) return true;
		$query = "SELECT id FROM kvp_".$this->_TableName." WHERE id='".$pCoord."'";
		$cellResult = $this->_DBHandle->querySingle($query);
		if ($cellResult === false) {
			throw new Exception($this->_DBHandle->lastErrorMsg());
		} elseif (is_null($cellResult)) {
			//	Return null if requested entry doesn't exist in cache
			return false;
		}
		return true;
	}	//	function isDataSet()


    /**
     *	Delete a cell in cache identified by coordinate address
     *
     * @param	string			$pCoord		Coordinate address of the cell to delete
     * @throws	Exception
     */
	public function deleteCacheData($pCoord) {
		if ($pCoord === $this->_currentObjectID) {
			$this->_currentObject->detach();
			$this->_currentObjectID = $this->_currentObject = null;
		}

		//	Check if the requested entry exists in the cache
		$query = "DELETE FROM kvp_".$this->_TableName." WHERE id='".$pCoord."'";
		$result = $this->_DBHandle->exec($query);
		if ($result === false)
			throw new Exception($this->_DBHandle->lastErrorMsg());

		$this->_currentCellIsDirty = false;
	}	//	function deleteCacheData()


	/**
	 * Get a list of all cell addresses currently held in cache
	 *
	 * @return	array of string
	 */
	public function getCellList() {
		$query = "SELECT id FROM kvp_".$this->_TableName;
		$cellIdsResult = $this->_DBHandle->query($query);
		if ($cellIdsResult === false)
			throw new Exception($this->_DBHandle->lastErrorMsg());

		$cellKeys = array();
		while ($row = $cellIdsResult->fetchArray(SQLITE3_ASSOC)) {
			$cellKeys[] = $row['id'];
		}

		return $cellKeys;
	}	//	function getCellList()


	/**
	 * Clone the cell collection
	 *
	 * @param	PHPExcel_Worksheet	$parent		The new worksheet
	 * @return	void
	 */
	public function copyCellCollection(PHPExcel_Worksheet $parent) {
		//	Get a new id for the new table name
		$tableName = str_replace('.','_',$this->_getUniqueID());
		if (!$this->_DBHandle->exec('CREATE TABLE kvp_'.$tableName.' (id VARCHAR(12) PRIMARY KEY, value BLOB)
		                                       AS SELECT * FROM kvp_'.$this->_TableName))
			throw new Exception($this->_DBHandle->lastErrorMsg());

		//	Copy the existing cell cache file
		$this->_TableName = $tableName;
	}	//	function copyCellCollection()


	/**
	 * Clear the cell collection and disconnect from our parent
	 *
	 * @return	void
	 */
	public function unsetWorksheetCells() {
		if(!is_null($this->_currentObject)) {
			$this->_currentObject->detach();
			$this->_currentObject = $this->_currentObjectID = null;
		}
		//	detach ourself from the worksheet, so that it can then delete this object successfully
		$this->_parent = null;

		//	Close down the temporary cache file
		$this->__destruct();
	}	//	function unsetWorksheetCells()


	/**
	 * Initialise this new cell collection
	 *
	 * @param	PHPExcel_Worksheet	$parent		The worksheet for this cell collection
	 */
	public function __construct(PHPExcel_Worksheet $parent) {
		parent::__construct($parent);
		if (is_null($this->_DBHandle)) {
			$this->_TableName = str_replace('.','_',$this->_getUniqueID());
			$_DBName = ':memory:';

			$this->_DBHandle = new SQLite3($_DBName);
			if ($this->_DBHandle === false)
				throw new Exception($this->_DBHandle->lastErrorMsg());
			if (!$this->_DBHandle->exec('CREATE TABLE kvp_'.$this->_TableName.' (id VARCHAR(12) PRIMARY KEY, value BLOB)'))
				throw new Exception($this->_DBHandle->lastErrorMsg());
		}
	}	//	function __construct()


	/**
	 * Destroy this cell collection
	 */
	public function __destruct() {
		if (!is_null($this->_DBHandle)) {
			$this->_DBHandle->close();
		}
		$this->_DBHandle = null;
	}	//	function __destruct()


	/**
	 * Identify whether the caching method is currently available
	 * Some methods are dependent on the availability of certain extensions being enabled in the PHP build
	 *
	 * @return	boolean
	 */
	public static function cacheMethodIsAvailable() {
		if (!class_exists('SQLite3',FALSE)) {
			return false;
		}

		return true;
	}

}
