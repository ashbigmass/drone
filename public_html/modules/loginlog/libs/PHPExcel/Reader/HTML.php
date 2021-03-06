<?php
if (!defined('PHPEXCEL_ROOT')) {
	define('PHPEXCEL_ROOT', dirname(__FILE__) . '/../../');
	require(PHPEXCEL_ROOT . 'PHPExcel/Autoloader.php');
}

class PHPExcel_Reader_HTML implements PHPExcel_Reader_IReader
{
	private $_inputEncoding	= 'ANSI';
	private $_sheetIndex 	= 0;
	private $_formats = array(
		'h1' => array( 'font' => array( 'bold' => true, 'size' => 24, ), ),
		'h2' => array( 'font' => array( 'bold' => true, 'size' => 18, ), ),
		'h3' => array( 'font' => array( 'bold' => true, 'size' => 13.5, ), ),
		'h4' => array( 'font' => array( 'bold' => true, 'size' => 12, ), ),
		'h5' => array( 'font' => array( 'bold' => true, 'size' => 10, ), ),
		'h6' => array( 'font' => array( 'bold' => true, 'size' => 7.5, ), ),
		'a'  => array( 'font' => array( 'underline' => true, 'color' => array( 'argb' => PHPExcel_Style_Color::COLOR_BLUE, ), ), ),
		'hr' => array( 'borders' => array( 'bottom' => array( 'style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array( PHPExcel_Style_Color::COLOR_BLACK, ), ), ), ),
	);
	private $_readFilter = null;

	public function __construct() {
		$this->_readFilter 	= new PHPExcel_Reader_DefaultReadFilter();
	}

	public function canRead($pFilename) {
		if (!file_exists($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! File does not exist.");
		$fh = fopen($pFilename, 'r');
		$data = fread($fh, 2048);
		fclose($fh);
		return true;
	}

	public function load($pFilename) {
		$objPHPExcel = new PHPExcel();
		return $this->loadIntoExisting($pFilename, $objPHPExcel);
	}

	public function getReadFilter() {
		return $this->_readFilter;
	}

	public function setReadFilter(PHPExcel_Reader_IReadFilter $pValue) {
		$this->_readFilter = $pValue;
		return $this;
	}

	public function setInputEncoding($pValue = 'ANSI') {
		$this->_inputEncoding = $pValue;
		return $this;
	}

	public function getInputEncoding() {
		return $this->_inputEncoding;
	}

	private $_dataArray = array();
	private $_tableLevel = 0;
	private $_nestedColumn = array('A');

	private function _setTableStartColumn($column) {
		if ($this->_tableLevel == 0) $column = 'A';
		++$this->_tableLevel;
		$this->_nestedColumn[$this->_tableLevel] = $column;
		return $this->_nestedColumn[$this->_tableLevel];
	}

	private function _getTableStartColumn() {
		return $this->_nestedColumn[$this->_tableLevel];
	}

	private function _releaseTableStartColumn() {
		--$this->_tableLevel;
		return array_pop($this->_nestedColumn);
	}

	private function _flushCell($sheet,$column,$row,&$cellContent) {
		if (is_string($cellContent)) {
			if (trim($cellContent) > '') {
				echo 'FLUSH CELL: ' , $column , $row , ' => ' , $cellContent , '<br />';
				$cell = $sheet->setCellValue($column.$row,$cellContent,true);
				$this->_dataArray[$row][$column] = $cellContent;
			}
		} else {
			$this->_dataArray[$row][$column] = 'RICH TEXT: ' . $cellContent;
		}
		$cellContent = (string) '';
	}

	private function _processDomElement(DOMNode $element, $sheet, &$row, &$column, &$cellContent) {
		foreach($element->childNodes as $child){
			if ($child instanceOf DOMText) {
				$domText = preg_replace('/\s+/',' ',trim($child->nodeValue));
				if (is_string($cellContent)) {
					$cellContent .= $domText;
				} else {
				}
			} elseif($child instanceOf DOMElement) {
				echo '<b>DOM ELEMENT: </b>' , strtoupper($child->nodeName) , '<br />';
				$attributeArray = array();
				foreach($child->attributes as $attribute) {
					echo '<b>ATTRIBUTE: </b>' , $attribute->name , ' => ' , $attribute->value , '<br />';
					$attributeArray[$attribute->name] = $attribute->value;
				}
				switch($child->nodeName) {
					case 'meta' :
						foreach($attributeArray as $attributeName => $attributeValue) {
							switch($attributeName) {
								case 'content':
									break;
							}
						}
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						break;
					case 'title' :
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						$sheet->setTitle($cellContent);
						$cellContent = '';
						break;
					case 'span'  :
					case 'div'   :
					case 'font'  :
					case 'i'     :
					case 'em'    :
					case 'strong':
					case 'b'     :
						echo 'STYLING, SPAN OR DIV<br />';
						if ($cellContent > '')
							$cellContent .= ' ';
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						if ($cellContent > '')
							$cellContent .= ' ';
						echo 'END OF STYLING, SPAN OR DIV<br />';
						break;
					case 'hr' :
						$this->_flushCell($sheet,$column,$row,$cellContent);
						++$row;
						if (isset($this->_formats[$child->nodeName])) {
							$sheet->getStyle($column.$row)->applyFromArray($this->_formats[$child->nodeName]);
						} else {
							$cellContent = '----------';
							$this->_flushCell($sheet,$column,$row,$cellContent);
						}
						++$row;
					case 'br' :
						if ($this->_tableLevel > 0) {
							$cellContent .= "\n";
						} else {
							$this->_flushCell($sheet,$column,$row,$cellContent);
							++$row;
						}
						echo 'HARD LINE BREAK: ' , '<br />';
						break;
					case 'a'  :
						echo 'START OF HYPERLINK: ' , '<br />';
						foreach($attributeArray as $attributeName => $attributeValue) {
							switch($attributeName) {
								case 'href':
									echo 'Link to ' , $attributeValue , '<br />';
									$sheet->getCell($column.$row)->getHyperlink()->setUrl($attributeValue);
									if (isset($this->_formats[$child->nodeName])) $sheet->getStyle($column.$row)->applyFromArray($this->_formats[$child->nodeName]);
									break;
							}
						}
						$cellContent .= ' ';
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						echo 'END OF HYPERLINK:' , '<br />';
						break;
					case 'h1' :
					case 'h2' :
					case 'h3' :
					case 'h4' :
					case 'h5' :
					case 'h6' :
					case 'ol' :
					case 'ul' :
					case 'p'  :
						if ($this->_tableLevel > 0) {
							$cellContent .= "\n";
							echo 'LIST ENTRY: ' , '<br />';
							$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
							echo 'END OF LIST ENTRY:' , '<br />';
						} else {
							if ($cellContent > '') {
								$this->_flushCell($sheet,$column,$row,$cellContent);
								$row += 2;
							}
							echo 'START OF PARAGRAPH: ' , '<br />';
							$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
							echo 'END OF PARAGRAPH:' , '<br />';
							$this->_flushCell($sheet,$column,$row,$cellContent);
							if (isset($this->_formats[$child->nodeName])) $sheet->getStyle($column.$row)->applyFromArray($this->_formats[$child->nodeName]);
							$row += 2;
							$column = 'A';
						}
						break;
					case 'li'  :
						if ($this->_tableLevel > 0) {
							$cellContent .= "\n";
							echo 'LIST ENTRY: ' , '<br />';
							$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
							echo 'END OF LIST ENTRY:' , '<br />';
						} else {
							if ($cellContent > '') $this->_flushCell($sheet,$column,$row,$cellContent);
							++$row;
							echo 'LIST ENTRY: ' , '<br />';
							$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
							echo 'END OF LIST ENTRY:' , '<br />';
							$this->_flushCell($sheet,$column,$row,$cellContent);
							$column = 'A';
						}
						break;
					case 'table' :
						$this->_flushCell($sheet,$column,$row,$cellContent);
						$column = $this->_setTableStartColumn($column);
						echo 'START OF TABLE LEVEL ' , $this->_tableLevel , '<br />';
						if ($this->_tableLevel > 1) --$row;
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						echo 'END OF TABLE LEVEL ' , $this->_tableLevel , '<br />';
						$column = $this->_releaseTableStartColumn();
						if ($this->_tableLevel > 1) ++$column;
						else ++$row;
						break;
					case 'thead' :
					case 'tbody' :
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						break;
					case 'tr' :
						++$row;
						$column = $this->_getTableStartColumn();
						$cellContent = '';
						echo 'START OF TABLE ' , $this->_tableLevel , ' ROW<br />';
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						echo 'END OF TABLE ' , $this->_tableLevel , ' ROW<br />';
						break;
					case 'th' :
					case 'td' :
						echo 'START OF TABLE ' , $this->_tableLevel , ' CELL<br />';
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						echo 'END OF TABLE ' , $this->_tableLevel , ' CELL<br />';
						$this->_flushCell($sheet,$column,$row,$cellContent);
						++$column;
						break;
					case 'body' :
						$row = 1;
						$column = 'A';
						$content = '';
						$this->_tableLevel = 0;
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
						break;
					default:
						$this->_processDomElement($child,$sheet,$row,$column,$cellContent);
				}
			}
		}
	}

	public function loadIntoExisting($pFilename, PHPExcel $objPHPExcel) {
		if (!file_exists($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! File does not exist.");
		if (!is_file($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! The given file is not a regular file.");
		while ($objPHPExcel->getSheetCount() <= $this->_sheetIndex) $objPHPExcel->createSheet();
		$objPHPExcel->setActiveSheetIndex( $this->_sheetIndex );
		$dom = new domDocument;
		$loaded = $dom->loadHTMLFile($pFilename);
		if ($loaded === false) throw new Exception('Failed to load ',$pFilename,' as a DOM Document');
		$dom->preserveWhiteSpace = false;
		$row = 0;
		$column = 'A';
		$content = '';
		$this->_processDomElement($dom,$objPHPExcel->getActiveSheet(),$row,$column,$content);
		echo '<hr />';
		var_dump($this->_dataArray);
		return $objPHPExcel;
	}

	public function getSheetIndex() {
		return $this->_sheetIndex;
	}

	public function setSheetIndex($pValue = 0) {
		$this->_sheetIndex = $pValue;
		return $this;
	}
}
