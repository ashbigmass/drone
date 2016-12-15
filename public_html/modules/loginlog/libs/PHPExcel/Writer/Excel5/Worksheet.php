<?php
class PHPExcel_Writer_Excel5_Worksheet extends PHPExcel_Writer_Excel5_BIFFwriter
{
	private $_parser;
	public $_xls_strmax;
	public $_colinfo;
	public $_selection;
	public $_active_pane;
	public $_outline_on;
	public $_outline_style;
	public $_outline_below;
	public $_outline_right;
	public $_str_total;
	public $_str_unique;
	public $_str_table;
	private $_colors;
	private $_firstRowIndex;
	private $_lastRowIndex;
	private $_firstColumnIndex;
	private $_lastColumnIndex;
	public $_phpSheet;
	private $_countCellStyleXfs;
	private $_escher;
	public $_fntHashIndex;

	public function __construct(&$str_total, &$str_unique, &$str_table, &$colors, $parser, $preCalculateFormulas, $phpSheet) {
		parent::__construct();
		$this->_preCalculateFormulas = $preCalculateFormulas;
		$this->_str_total		= &$str_total;
		$this->_str_unique		= &$str_unique;
		$this->_str_table		= &$str_table;
		$this->_colors			= &$colors;
		$this->_parser			= $parser;
		$this->_phpSheet = $phpSheet;
		$this->_xls_strmax		= 255;
		$this->_colinfo			= array();
		$this->_selection		= array(0,0,0,0);
		$this->_active_pane		= 3;
		$this->_print_headers		= 0;
		$this->_outline_style		= 0;
		$this->_outline_below		= 1;
		$this->_outline_right		= 1;
		$this->_outline_on			= 1;
		$this->_fntHashIndex	= array();
		$minR = 1;
		$minC = 'A';
		$maxR  = $this->_phpSheet->getHighestRow();
		$maxC = $this->_phpSheet->getHighestColumn();
		$this->_lastRowIndex = ($maxR > 65535) ? 65535 : $maxR ;
		$this->_firstColumnIndex	= PHPExcel_Cell::columnIndexFromString($minC);
		$this->_lastColumnIndex		= PHPExcel_Cell::columnIndexFromString($maxC);
		if ($this->_lastColumnIndex > 255) $this->_lastColumnIndex = 255;
		$this->_countCellStyleXfs = count($phpSheet->getParent()->getCellStyleXfCollection());
	}

	function close() {
		$_phpSheet = $this->_phpSheet;
		$num_sheets = $_phpSheet->getParent()->getSheetCount();
		$this->_storeBof(0x0010);
		$this->_writePrintHeaders();
		$this->_writePrintGridlines();
		$this->_writeGridset();
		$_phpSheet->calculateColumnWidths();
		if (($defaultWidth = $_phpSheet->getDefaultColumnDimension()->getWidth()) < 0)
			$defaultWidth = PHPExcel_Shared_Font::getDefaultColumnWidthByFont($_phpSheet->getParent()->getDefaultStyle()->getFont());
		$columnDimensions = $_phpSheet->getColumnDimensions();
		$maxCol = $this->_lastColumnIndex -1;
		for ($i = 0; $i <= $maxCol; ++$i) {
			$hidden = 0;
			$level = 0;
			$xfIndex = 15;
			$width = $defaultWidth;
			$columnLetter = PHPExcel_Cell::stringFromColumnIndex($i);
			if (isset($columnDimensions[$columnLetter])) {
				$columnDimension = $columnDimensions[$columnLetter];
				if ($columnDimension->getWidth() >= 0) $width = $columnDimension->getWidth();
				$hidden = $columnDimension->getVisible() ? 0 : 1;
				$level = $columnDimension->getOutlineLevel();
				$xfIndex = $columnDimension->getXfIndex() + 15;
			}
			$this->_colinfo[] = array($i, $i, $width, $xfIndex, $hidden, $level);
		}
		$this->_writeGuts();
		$this->_writeDefaultRowHeight();
		$this->_writeWsbool();
		$this->_writeBreaks();
		$this->_writeHeader();
		$this->_writeFooter();
		$this->_writeHcenter();
		$this->_writeVcenter();
		$this->_writeMarginLeft();
		$this->_writeMarginRight();
		$this->_writeMarginTop();
		$this->_writeMarginBottom();
		$this->_writeSetup();
		$this->_writeProtect();
		$this->_writeScenProtect();
		$this->_writeObjectProtect();
		$this->_writePassword();
		$this->_writeDefcol();
		if (!empty($this->_colinfo)) {
			$colcount = count($this->_colinfo);
			for ($i = 0; $i < $colcount; ++$i) $this->_writeColinfo($this->_colinfo[$i]);
		}
		$autoFilterRange = $_phpSheet->getAutoFilter()->getRange();
		if (!empty($autoFilterRange)) $this->_writeAutoFilterInfo();
		$this->_writeDimensions();
		foreach ($_phpSheet->getRowDimensions() as $rowDimension) {
			$xfIndex = $rowDimension->getXfIndex() + 15;
			$this->_writeRow( $rowDimension->getRowIndex() - 1, $rowDimension->getRowHeight(), $xfIndex, ($rowDimension->getVisible() ? '0' : '1'), $rowDimension->getOutlineLevel() );
		}
		foreach ($_phpSheet->getCellCollection() as $cellID) {
			$cell = $_phpSheet->getCell($cellID);
			$row = $cell->getRow() - 1;
			$column = PHPExcel_Cell::columnIndexFromString($cell->getColumn()) - 1;
			if ($row > 65535 || $column > 255) break;
			$xfIndex = $cell->getXfIndex() + 15;
			$cVal = $cell->getValue();
			if ($cVal instanceof PHPExcel_RichText) {
				$arrcRun = array();
				$str_len = strlen($cVal->getPlainText());
				$str_pos = 0;
				$elements = $cVal->getRichTextElements();
				foreach ($elements as $element) {
					if ($element instanceof PHPExcel_RichText_Run) $str_fontidx = $this->_fntHashIndex[$element->getFont()->getHashCode()];
					else $str_fontidx = 0;
					$arrcRun[] = array('strlen' => $str_pos, 'fontidx' => $str_fontidx);
					$str_pos += strlen($element->getText());
				}
				$this->_writeRichTextString($row, $column, $cVal->getPlainText(), $xfIndex, $arrcRun);
			} else {
				switch ($cell->getDatatype()) {
					case PHPExcel_Cell_DataType::TYPE_STRING:
					case PHPExcel_Cell_DataType::TYPE_NULL:
						if ($cVal === '' || $cVal === null) $this->_writeBlank($row, $column, $xfIndex);
						else $this->_writeString($row, $column, $cVal, $xfIndex);
						break;
					case PHPExcel_Cell_DataType::TYPE_NUMERIC:
						$this->_writeNumber($row, $column, $cVal, $xfIndex);
						break;
					case PHPExcel_Cell_DataType::TYPE_FORMULA:
						$calculatedValue = $this->_preCalculateFormulas ?
							$cell->getCalculatedValue() : null;
						$this->_writeFormula($row, $column, $cVal, $xfIndex, $calculatedValue);
						break;
					case PHPExcel_Cell_DataType::TYPE_BOOL:
						$this->_writeBoolErr($row, $column, $cVal, 0, $xfIndex);
						break;
					case PHPExcel_Cell_DataType::TYPE_ERROR:
						$this->_writeBoolErr($row, $column, self::_mapErrorCode($cVal), 1, $xfIndex);
						break;
				}
			}
		}
		$this->_writeMsoDrawing();
		$this->_writeWindow2();
		$this->_writeZoom();
		if ($_phpSheet->getFreezePane()) $this->_writePanes();
		$this->_writeSelection();
		$this->_writeMergedCells();
		foreach ($_phpSheet->getHyperLinkCollection() as $coordinate => $hyperlink) {
			list($column, $row) = PHPExcel_Cell::coordinateFromString($coordinate);
			$url = $hyperlink->getUrl();
			if ( strpos($url, 'sheet://') !== false ) {$url = str_replace('sheet://', 'internal:', $url);}
			else if ( preg_match('/^(http:|https:|ftp:|mailto:)/', $url) ) {}
			else {$url = 'external:' . $url;}
			$this->_writeUrl($row - 1, PHPExcel_Cell::columnIndexFromString($column) - 1, $url);
		}
		$this->_writeDataValidity();
		$this->_writeSheetLayout();
		$this->_writeSheetProtection();
		$this->_writeRangeProtection();
		$this->_storeEof();
	}

	private function _writeBIFF8CellRangeAddressFixed($range = 'A1') {
		$explodes = explode(':', $range);
		$firstCell = $explodes[0];
		if (count($explodes) == 1) $lastCell = $firstCell;
		else $lastCell = $explodes[1];
		$firstCellCoordinates = PHPExcel_Cell::coordinateFromString($firstCell);
		$lastCellCoordinates  = PHPExcel_Cell::coordinateFromString($lastCell);
		return(pack('vvvv',
			$firstCellCoordinates[1] - 1,
			$lastCellCoordinates[1] - 1,
			PHPExcel_Cell::columnIndexFromString($firstCellCoordinates[0]) - 1,
			PHPExcel_Cell::columnIndexFromString($lastCellCoordinates[0]) - 1
		));
	}

	function getData() {
		$buffer = 4096;
		if (isset($this->_data)) {
			$tmp   = $this->_data;
			unset($this->_data);
			return $tmp;
		}
		return false;
	}

	function printRowColHeaders($print = 1) {
		$this->_print_headers = $print;
	}

	function setOutline($visible = true, $symbols_below = true, $symbols_right = true, $auto_style = false) {
		$this->_outline_on	= $visible;
		$this->_outline_below = $symbols_below;
		$this->_outline_right = $symbols_right;
		$this->_outline_style = $auto_style;
		if ($this->_outline_on) $this->_outline_on = 1;
	 }

	private function _writeNumber($row, $col, $num, $xfIndex) {
		$record	= 0x0203;
		$length	= 0x000E;
		$header		= pack("vv",  $record, $length);
		$data		= pack("vvv", $row, $col, $xfIndex);
		$xl_double	= pack("d",   $num);
		if (self::getByteOrder()) $xl_double = strrev($xl_double);
		$this->_append($header.$data.$xl_double);
		return(0);
	}

	private function _writeString($row, $col, $str, $xfIndex) {
		$this->_writeLabelSst($row, $col, $str, $xfIndex);
	}

	private function _writeRichTextString($row, $col, $str, $xfIndex, $arrcRun){
		$record	= 0x00FD;
		$length	= 0x000A;
		$str = PHPExcel_Shared_String::UTF8toBIFF8UnicodeShort($str, $arrcRun);
		if (!isset($this->_str_table[$str])) $this->_str_table[$str] = $this->_str_unique++;
		$this->_str_total++;
		$header	= pack('vv',   $record, $length);
		$data	= pack('vvvV', $row, $col, $xfIndex, $this->_str_table[$str]);
		$this->_append($header.$data);
	}

	private function _writeLabel($row, $col, $str, $xfIndex) {
		$strlen	= strlen($str);
		$record	= 0x0204;
		$length	= 0x0008 + $strlen;
		$str_error = 0;
		if ($strlen > $this->_xls_strmax) {
			$str	= substr($str, 0, $this->_xls_strmax);
			$length	= 0x0008 + $this->_xls_strmax;
			$strlen	= $this->_xls_strmax;
			$str_error = -3;
		}
		$header	= pack("vv",   $record, $length);
		$data	= pack("vvvv", $row, $col, $xfIndex, $strlen);
		$this->_append($header . $data . $str);
		return($str_error);
	}

	private function _writeLabelSst($row, $col, $str, $xfIndex) {
		$record	= 0x00FD;
		$length	= 0x000A;
		$str = PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($str);
		if (!isset($this->_str_table[$str])) $this->_str_table[$str] = $this->_str_unique++;
		$this->_str_total++;
		$header	= pack('vv',   $record, $length);
		$data	= pack('vvvV', $row, $col, $xfIndex, $this->_str_table[$str]);
		$this->_append($header.$data);
	}

	private function _writeNote($row, $col, $note) {
		$note_length	= strlen($note);
		$record			= 0x001C;
		$max_length		= 2048;
		$length	= 0x0006 + min($note_length, 2048);
		$header	= pack("vv",   $record, $length);
		$data	= pack("vvv", $row, $col, $note_length);
		$this->_append($header . $data . substr($note, 0, 2048));
		for ($i = $max_length; $i < $note_length; $i += $max_length) {
			$chunk  = substr($note, $i, $max_length);
			$length = 0x0006 + strlen($chunk);
			$header = pack("vv",   $record, $length);
			$data   = pack("vvv", -1, 0, strlen($chunk));
			$this->_append($header.$data.$chunk);
		}
		return(0);
	}

	function _writeBlank($row, $col, $xfIndex) {
		$record	= 0x0201;
		$length	= 0x0006;
		$header	= pack("vv",  $record, $length);
		$data	  = pack("vvv", $row, $col, $xfIndex);
		$this->_append($header . $data);
		return 0;
	}

	private function _writeBoolErr($row, $col, $value, $isError, $xfIndex) {
		$record = 0x0205;
		$length = 8;
		$header	= pack("vv",  $record, $length);
		$data	  = pack("vvvCC", $row, $col, $xfIndex, $value, $isError);
		$this->_append($header . $data);
		return 0;
	}

	private function _writeFormula($row, $col, $formula, $xfIndex, $calculatedValue) {
		$record	= 0x0006;
		$stringValue = null;
		if (isset($calculatedValue)) {
			if (is_bool($calculatedValue)) {
				$num = pack('CCCvCv', 0x01, 0x00, (int)$calculatedValue, 0x00, 0x00, 0xFFFF);
			} elseif (is_int($calculatedValue) || is_float($calculatedValue)) {
				$num = pack('d', $calculatedValue);
			} elseif (is_string($calculatedValue)) {
				if (array_key_exists($calculatedValue, PHPExcel_Cell_DataType::getErrorCodes())) {
					$num = pack('CCCvCv', 0x02, 0x00, self::_mapErrorCode($calculatedValue), 0x00, 0x00, 0xFFFF);
				} elseif ($calculatedValue === '') {
					$num = pack('CCCvCv', 0x03, 0x00, 0x00, 0x00, 0x00, 0xFFFF);
				} else {
					$stringValue = $calculatedValue;
					$num = pack('CCCvCv', 0x00, 0x00, 0x00, 0x00, 0x00, 0xFFFF);
				}
			} else {
				$num = pack('d', 0x00);
			}
		} else {
			$num = pack('d', 0x00);
		}
		$grbit		= 0x03;
		$unknown	= 0x0000;
		if ($formula{0} == '=') {
			$formula = substr($formula,1);
		} else {
			$this->_writeString($row, $col, 'Unrecognised character for formula');
			return -1;
		}
		try {
			$error = $this->_parser->parse($formula);
			$formula = $this->_parser->toReversePolish();
			$formlen	= strlen($formula);
			$length	 = 0x16 + $formlen;
			$header	= pack("vv",	  $record, $length);
			$data	  = pack("vvv", $row, $col, $xfIndex) . $num . pack("vVv", $grbit, $unknown, $formlen);
			$this->_append($header . $data . $formula);
			if ($stringValue !== null) $this->_writeStringRecord($stringValue);
			return 0;
		} catch (Exception $e) {
		}
	}

	private function _writeStringRecord($stringValue) {
		$record = 0x0207;
		$data = PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($stringValue);
		$length = strlen($data);
		$header = pack('vv', $record, $length);
		$this->_append($header . $data);
	}

	private function _writeUrl($row, $col, $url) {
		return($this->_writeUrlRange($row, $col, $row, $col, $url));
	}

	function _writeUrlRange($row1, $col1, $row2, $col2, $url) {
		if (preg_match('[^internal:]', $url)) return($this->_writeUrlInternal($row1, $col1, $row2, $col2, $url));
		if (preg_match('[^external:]', $url)) return($this->_writeUrlExternal($row1, $col1, $row2, $col2, $url));
		return($this->_writeUrlWeb($row1, $col1, $row2, $col2, $url));
	}

	function _writeUrlWeb($row1, $col1, $row2, $col2, $url) {
		$record	  = 0x01B8;
		$length	  = 0x00000;
		$unknown1	= pack("H*", "D0C9EA79F9BACE118C8200AA004BA90B02000000");
		$unknown2	= pack("H*", "E0C9EA79F9BACE118C8200AA004BA90B");
		$options	 = pack("V", 0x03);
		$url		 = join("\0", preg_split("''", $url, -1, PREG_SPLIT_NO_EMPTY));
		$url		 = $url . "\0\0\0";
		$url_len	 = pack("V", strlen($url));
		$length	  = 0x34 + strlen($url);
		$header	  = pack("vv",   $record, $length);
		$data		= pack("vvvv", $row1, $row2, $col1, $col2);
		$this->_append($header . $data . $unknown1 . $options . $unknown2 . $url_len . $url);
		return 0;
	}

	function _writeUrlInternal($row1, $col1, $row2, $col2, $url) {
		$record	  = 0x01B8;
		$length	  = 0x00000;
		$url = preg_replace('/^internal:/', '', $url);
		$unknown1	= pack("H*", "D0C9EA79F9BACE118C8200AA004BA90B02000000");
		$options	 = pack("V", 0x08);
		$url .= "\0";
		$url_len = PHPExcel_Shared_String::CountCharacters($url);
		$url_len = pack('V', $url_len);
		$url = PHPExcel_Shared_String::ConvertEncoding($url, 'UTF-16LE', 'UTF-8');
		$length	  = 0x24 + strlen($url);
		$header	  = pack("vv",   $record, $length);
		$data		= pack("vvvv", $row1, $row2, $col1, $col2);
		$this->_append($header . $data . $unknown1 . $options . $url_len . $url);
		return 0;
	}

	function _writeUrlExternal($row1, $col1, $row2, $col2, $url) {
		if (preg_match('[^external:\\\\]', $url)) return;
		$record	  = 0x01B8;
		$length	  = 0x00000;
		$url = preg_replace('/^external:/', '', $url);
		$url = preg_replace('/\//', "\\", $url);
		$absolute = 0x00;
		if ( preg_match('/^[A-Z]:/', $url) ) $absolute = 0x02;
		$link_type			   = 0x01 | $absolute;
		$dir_long = $url;
		if (preg_match("/\#/", $url)) $link_type |= 0x08;
		$link_type   = pack("V", $link_type);
		$up_count	= preg_match_all("/\.\.\\\/", $dir_long, $useless);
		$up_count	= pack("v", $up_count);
		$dir_short   = preg_replace("/\.\.\\\/", '', $dir_long) . "\0";
		$dir_long	   = $dir_long . "\0";
		$dir_short_len = pack("V", strlen($dir_short)	  );
		$dir_long_len  = pack("V", strlen($dir_long)	   );
		$stream_len	= pack("V", 0);
		$unknown1 = pack("H*",'D0C9EA79F9BACE118C8200AA004BA90B02000000'	   );
		$unknown2 = pack("H*",'0303000000000000C000000000000046'			   );
		$unknown3 = pack("H*",'FFFFADDE000000000000000000000000000000000000000');
		$unknown4 = pack("v",  0x03											);
		$data		= pack("vvvv", $row1, $row2, $col1, $col2) .
						  $unknown1	 .
						  $link_type	.
						  $unknown2	 .
						  $up_count	 .
						  $dir_short_len.
						  $dir_short	.
						  $unknown3	 .
						  $stream_len;
		$length   = strlen($data);
		$header   = pack("vv", $record, $length);
		$this->_append($header. $data);
		return 0;
	}

	private function _writeRow($row, $height, $xfIndex, $hidden = false, $level = 0) {
		$record	  = 0x0208;
		$length	  = 0x0010;
		$colMic	  = 0x0000;
		$colMac	  = 0x0000;
		$irwMac	  = 0x0000;
		$reserved	= 0x0000;
		$grbit	   = 0x0000;
		$ixfe		= $xfIndex;
		if ( $height < 0 ) $height = null;
		if ($height != null) $miyRw = $height * 20;
		else $miyRw = 0xff;
		$grbit |= $level;
		if ($hidden) $grbit |= 0x0020;
		if ($height !== null) $grbit |= 0x0040;
		if ($xfIndex !== 0xF) $grbit |= 0x0080;
		$grbit |= 0x0100;
		$header   = pack("vv",	   $record, $length);
		$data	 = pack("vvvvvvvv", $row, $colMic, $colMac, $miyRw, $irwMac,$reserved, $grbit, $ixfe);
		$this->_append($header.$data);
	}

	private function _writeDimensions() {
		$record = 0x0200;
		$length = 0x000E;
		$data = pack('VVvvv'
				, $this->_firstRowIndex
				, $this->_lastRowIndex + 1
				, $this->_firstColumnIndex
				, $this->_lastColumnIndex + 1
				, 0x0000
			);
		$header = pack("vv", $record, $length);
		$this->_append($header.$data);
	}

	private function _writeWindow2() {
		$record		 = 0x023E;
		$length		 = 0x0012;

		$grbit		  = 0x00B6;
		$rwTop		  = 0x0000;
		$colLeft		= 0x0000;
		$fDspFmla	   = 0;
		$fDspGrid	   = $this->_phpSheet->getShowGridlines() ? 1 : 0;
		$fDspRwCol	  = $this->_phpSheet->getShowRowColHeaders() ? 1 : 0;
		$fFrozen		= $this->_phpSheet->getFreezePane() ? 1 : 0;
		$fDspZeros	  = 1;
		$fDefaultHdr	= 1;
		$fArabic		= $this->_phpSheet->getRightToLeft() ? 1 : 0;
		$fDspGuts	   = $this->_outline_on;
		$fFrozenNoSplit = 0;
		$fSelected	  = ($this->_phpSheet === $this->_phpSheet->getParent()->getActiveSheet()) ? 1 : 0;
		$fPaged		 = 1;
		$grbit			 = $fDspFmla;
		$grbit			|= $fDspGrid	   << 1;
		$grbit			|= $fDspRwCol	  << 2;
		$grbit			|= $fFrozen		<< 3;
		$grbit			|= $fDspZeros	  << 4;
		$grbit			|= $fDefaultHdr	<< 5;
		$grbit			|= $fArabic		<< 6;
		$grbit			|= $fDspGuts	   << 7;
		$grbit			|= $fFrozenNoSplit << 8;
		$grbit			|= $fSelected	  << 9;
		$grbit			|= $fPaged		 << 10;
		$header  = pack("vv",   $record, $length);
		$data	= pack("vvv", $grbit, $rwTop, $colLeft);
		$rgbHdr	   = 0x0040;
		$zoom_factor_page_break = 0x0000;
		$zoom_factor_normal	 = 0x0000;
		$data .= pack("vvvvV", $rgbHdr, 0x0000, $zoom_factor_page_break, $zoom_factor_normal, 0x00000000);
		$this->_append($header.$data);
	}

	private function _writeDefaultRowHeight() {
		$defaultRowHeight = $this->_phpSheet->getDefaultRowDimension()->getRowHeight();
		if ($defaultRowHeight < 0) return;
		$defaultRowHeight = (int) 20 * $defaultRowHeight;
		$record   = 0x0225;
		$length   = 0x0004;
		$header   = pack("vv", $record, $length);
		$data	 = pack("vv",  1, $defaultRowHeight);
		$this->_append($header . $data);
	}

	private function _writeDefcol() {
		$defaultColWidth = 8;
		$record   = 0x0055;
		$length   = 0x0002;
		$header = pack("vv", $record, $length);
		$data = pack("v", $defaultColWidth);
		$this->_append($header . $data);
	}

	private function _writeColinfo($col_array) {
		if (isset($col_array[0])) $colFirst = $col_array[0];
		if (isset($col_array[1])) $colLast = $col_array[1];
		if (isset($col_array[2])) $coldx = $col_array[2];
		else $coldx = 8.43;
		if (isset($col_array[3])) $xfIndex = $col_array[3];
		else $xfIndex = 15;
		if (isset($col_array[4])) $grbit = $col_array[4];
		else $grbit = 0;
		if (isset($col_array[5])) $level = $col_array[5];
		else $level = 0;
		$record   = 0x007D;
		$length   = 0x000C;
		$coldx   *= 256;
		$ixfe	 = $xfIndex;
		$reserved = 0x0000;
		$level = max(0, min($level, 7));
		$grbit |= $level << 8;
		$header   = pack("vv",	 $record, $length);
		$data	 = pack("vvvvvv", $colFirst, $colLast, $coldx, $ixfe, $grbit, $reserved);
		$this->_append($header.$data);
	}

	private function _writeSelection() {
		$selectedCells = $this->_phpSheet->getSelectedCells();
		$selectedCells = PHPExcel_Cell::splitRange($this->_phpSheet->getSelectedCells());
		$selectedCells = $selectedCells[0];
		if (count($selectedCells) == 2) {
			list($first, $last) = $selectedCells;
		} else {
			$first = $selectedCells[0];
			$last  = $selectedCells[0];
		}
		list($colFirst, $rwFirst) = PHPExcel_Cell::coordinateFromString($first);
		$colFirst = PHPExcel_Cell::columnIndexFromString($colFirst) - 1;
		--$rwFirst;
		list($colLast, $rwLast) = PHPExcel_Cell::coordinateFromString($last);
		$colLast = PHPExcel_Cell::columnIndexFromString($colLast) - 1;
		--$rwLast;
		$colFirst = min($colFirst, 255);
		$colLast  = min($colLast,  255);
		$rwFirst = min($rwFirst, 65535);
		$rwLast  = min($rwLast,  65535);
		$record   = 0x001D;
		$length   = 0x000F;
		$pnn	  = $this->_active_pane;
		$rwAct	= $rwFirst;
		$colAct   = $colFirst;
		$irefAct  = 0;
		$cref	 = 1;
		if (!isset($rwLast)) $rwLast   = $rwFirst;
		if (!isset($colLast)) $colLast  = $colFirst;
		if ($rwFirst > $rwLast) list($rwFirst, $rwLast) = array($rwLast, $rwFirst);
		if ($colFirst > $colLast) list($colFirst, $colLast) = array($colLast, $colFirst);
		$header   = pack("vv",		 $record, $length);
		$data	 = pack("CvvvvvvCC",  $pnn, $rwAct, $colAct, $irefAct, $cref, $rwFirst, $rwLast, $colFirst, $colLast);
		$this->_append($header . $data);
	}

	private function _writeMergedCells() {
		$mergeCells = $this->_phpSheet->getMergeCells();
		$countMergeCells = count($mergeCells);
		if ($countMergeCells == 0) return;
		$maxCountMergeCellsPerRecord = 1027;
		$record = 0x00E5;
		$i = 0;
		$j = 0;
		$recordData = '';
		foreach ($mergeCells as $mergeCell) {
			++$i;
			++$j;
			$range = PHPExcel_Cell::splitRange($mergeCell);
			list($first, $last) = $range[0];
			list($firstColumn, $firstRow) = PHPExcel_Cell::coordinateFromString($first);
			list($lastColumn, $lastRow) = PHPExcel_Cell::coordinateFromString($last);
			$recordData .= pack('vvvv', $firstRow - 1, $lastRow - 1, PHPExcel_Cell::columnIndexFromString($firstColumn) - 1, PHPExcel_Cell::columnIndexFromString($lastColumn) - 1);
			if ($j == $maxCountMergeCellsPerRecord or $i == $countMergeCells) {
				$recordData = pack('v', $j) . $recordData;
				$length = strlen($recordData);
				$header = pack('vv', $record, $length);
				$this->_append($header . $recordData);
				$recordData = '';
				$j = 0;
			}
		}
	}

	private function _writeSheetLayout() {
		if (!$this->_phpSheet->isTabColorSet()) return;
		$recordData = pack(
			'vvVVVvv'
			, 0x0862
			, 0x0000
			, 0x00000000
			, 0x00000000
			, 0x00000014
			, $this->_colors[$this->_phpSheet->getTabColor()->getRGB()]
			, 0x0000
		);
		$length = strlen($recordData);
		$record = 0x0862;
		$header = pack('vv', $record, $length);
		$this->_append($header . $recordData);
	}

	private function _writeSheetProtection() 	{
		$record = 0x0867;
		$options  =   (int) !$this->_phpSheet->getProtection()->getObjects()
					| (int) !$this->_phpSheet->getProtection()->getScenarios()           << 1
					| (int) !$this->_phpSheet->getProtection()->getFormatCells()         << 2
					| (int) !$this->_phpSheet->getProtection()->getFormatColumns()       << 3
					| (int) !$this->_phpSheet->getProtection()->getFormatRows()          << 4
					| (int) !$this->_phpSheet->getProtection()->getInsertColumns()       << 5
					| (int) !$this->_phpSheet->getProtection()->getInsertRows()          << 6
					| (int) !$this->_phpSheet->getProtection()->getInsertHyperlinks()    << 7
					| (int) !$this->_phpSheet->getProtection()->getDeleteColumns()       << 8
					| (int) !$this->_phpSheet->getProtection()->getDeleteRows()          << 9
					| (int) !$this->_phpSheet->getProtection()->getSelectLockedCells()   << 10
					| (int) !$this->_phpSheet->getProtection()->getSort()                << 11
					| (int) !$this->_phpSheet->getProtection()->getAutoFilter()          << 12
					| (int) !$this->_phpSheet->getProtection()->getPivotTables()         << 13
					| (int) !$this->_phpSheet->getProtection()->getSelectUnlockedCells() << 14 ;
		$recordData = pack(
			'vVVCVVvv'
			, 0x0867
			, 0x0000
			, 0x0000
			, 0x00
			, 0x01000200
			, 0xFFFFFFFF
			, $options
			, 0x0000
		);
		$length = strlen($recordData);
		$header = pack('vv', $record, $length);
		$this->_append($header . $recordData);
	}

	private function _writeRangeProtection() {
		foreach ($this->_phpSheet->getProtectedCells() as $range => $password) {
			$cellRanges = explode(' ', $range);
			$cref = count($cellRanges);
			$recordData = pack(
				'vvVVvCVvVv',
				0x0868,
				0x00,
				0x0000,
				0x0000,
				0x02,
				0x0,
				0x0000,
				$cref,
				0x0000,
				0x00
			);
			foreach ($cellRanges as $cellRange) $recordData .= $this->_writeBIFF8CellRangeAddressFixed($cellRange);
			$recordData .= pack('VV', 0x0000, hexdec($password));
			$recordData .= PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong('p' . md5($recordData));
			$length = strlen($recordData);
			$record = 0x0868;
			$header = pack("vv", $record, $length);
			$this->_append($header . $recordData);
		}
	}

	private function _writeExterncount($count) {
		$record = 0x0016;
		$length = 0x0002;
		$header = pack("vv", $record, $length);
		$data   = pack("v",  $count);
		$this->_append($header . $data);
	}

	private function _writeExternsheet($sheetname) {
		$record	= 0x0017;
		if ($this->_phpSheet->getTitle() == $sheetname) {
			$sheetname = '';
			$length	= 0x02;
			$cch	   = 1;
			$rgch	  = 0x02;
		} else {
			$length	= 0x02 + strlen($sheetname);
			$cch	   = strlen($sheetname);
			$rgch	  = 0x03;
		}
		$header = pack("vv",  $record, $length);
		$data   = pack("CC", $cch, $rgch);
		$this->_append($header . $data . $sheetname);
	}

	private function _writePanes() {
		$panes = array();
		if ($freezePane = $this->_phpSheet->getFreezePane()) {
			list($column, $row) = PHPExcel_Cell::coordinateFromString($freezePane);
			$panes[0] = $row - 1;
			$panes[1] = PHPExcel_Cell::columnIndexFromString($column) - 1;
		} else {
			return;
		}
		$y	   = isset($panes[0]) ? $panes[0] : null;
		$x	   = isset($panes[1]) ? $panes[1] : null;
		$rwTop   = isset($panes[2]) ? $panes[2] : null;
		$colLeft = isset($panes[3]) ? $panes[3] : null;
		if (count($panes) > 4) $pnnAct = $panes[4];
		else $pnnAct = null;
		$record  = 0x0041;
		$length  = 0x000A;
		if ($this->_phpSheet->getFreezePane()) {
			if (!isset($rwTop)) $rwTop   = $y;
			if (!isset($colLeft)) $colLeft = $x;
		} else {
			if (!isset($rwTop)) $rwTop   = 0;
			if (!isset($colLeft)) $colLeft = 0;
			$y = 20*$y + 255;
			$x = 113.879*$x + 390;
		}
		if (!isset($pnnAct)) {
			if ($x != 0 && $y != 0) $pnnAct = 0;
			if ($x != 0 && $y == 0) $pnnAct = 1;
			if ($x == 0 && $y != 0) $pnnAct = 2;
			if ($x == 0 && $y == 0) $pnnAct = 3;
		}
		$this->_active_pane = $pnnAct;
		$header	 = pack("vv",	$record, $length);
		$data	   = pack("vvvvv", $x, $y, $rwTop, $colLeft, $pnnAct);
		$this->_append($header . $data);
	}

	private function _writeSetup() {
		$record	   = 0x00A1;
		$length	   = 0x0022;
		$iPaperSize   = $this->_phpSheet->getPageSetup()->getPaperSize();
		$iScale = $this->_phpSheet->getPageSetup()->getScale() ? $this->_phpSheet->getPageSetup()->getScale() : 100;
		$iPageStart   = 0x01;
		$iFitWidth	= (int) $this->_phpSheet->getPageSetup()->getFitToWidth();
		$iFitHeight	= (int) $this->_phpSheet->getPageSetup()->getFitToHeight();
		$grbit		= 0x00;
		$iRes		 = 0x0258;
		$iVRes		= 0x0258;
		$numHdr	   = $this->_phpSheet->getPageMargins()->getHeader();
		$numFtr	   = $this->_phpSheet->getPageMargins()->getFooter();
		$iCopies	  = 0x01;
		$fLeftToRight = 0x0;
		$fLandscape = ($this->_phpSheet->getPageSetup()->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE) ? 0x0 : 0x1;
		$fNoPls	   = 0x0;
		$fNoColor	 = 0x0;
		$fDraft	   = 0x0;
		$fNotes	   = 0x0;
		$fNoOrient	= 0x0;
		$fUsePage	 = 0x0;
		$grbit		   = $fLeftToRight;
		$grbit		  |= $fLandscape	<< 1;
		$grbit		  |= $fNoPls		<< 2;
		$grbit		  |= $fNoColor	  << 3;
		$grbit		  |= $fDraft		<< 4;
		$grbit		  |= $fNotes		<< 5;
		$grbit		  |= $fNoOrient	 << 6;
		$grbit		  |= $fUsePage	  << 7;
		$numHdr = pack("d", $numHdr);
		$numFtr = pack("d", $numFtr);
		if (self::getByteOrder()) {
			$numHdr = strrev($numHdr);
			$numFtr = strrev($numFtr);
		}
		$header = pack("vv", $record, $length);
		$data1  = pack("vvvvvvvv", $iPaperSize,
								   $iScale,
								   $iPageStart,
								   $iFitWidth,
								   $iFitHeight,
								   $grbit,
								   $iRes,
								   $iVRes);
		$data2  = $numHdr.$numFtr;
		$data3  = pack("v", $iCopies);
		$this->_append($header . $data1 . $data2 . $data3);
	}

	private function _writeHeader() {
		$record  = 0x0014;
		$recordData = PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($this->_phpSheet->getHeaderFooter()->getOddHeader());
		$length = strlen($recordData);
		$header   = pack("vv", $record, $length);
		$this->_append($header . $recordData);
	}

	private function _writeFooter() {
		$record  = 0x0015;
		$recordData = PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($this->_phpSheet->getHeaderFooter()->getOddFooter());
		$length = strlen($recordData);
		$header	= pack("vv", $record, $length);
		$this->_append($header . $recordData);
	}

	private function _writeHcenter() {
		$record   = 0x0083;
		$length   = 0x0002;
		$fHCenter = $this->_phpSheet->getPageSetup()->getHorizontalCentered() ? 1 : 0;
		$header	= pack("vv", $record, $length);
		$data	  = pack("v",  $fHCenter);
		$this->_append($header.$data);
	}

	private function _writeVcenter() {
		$record   = 0x0084;
		$length   = 0x0002;
		$fVCenter = $this->_phpSheet->getPageSetup()->getVerticalCentered() ? 1 : 0;
		$header	= pack("vv", $record, $length);
		$data	  = pack("v",  $fVCenter);
		$this->_append($header . $data);
	}

	private function _writeMarginLeft() {
		$record  = 0x0026;
		$length  = 0x0008;
		$margin  = $this->_phpSheet->getPageMargins()->getLeft();
		$header	= pack("vv",  $record, $length);
		$data	  = pack("d",   $margin);
		if (self::getByteOrder()) $data = strrev($data);
		$this->_append($header . $data);
	}

	private function _writeMarginRight() {
		$record  = 0x0027;
		$length  = 0x0008;
		$margin  = $this->_phpSheet->getPageMargins()->getRight();
		$header	= pack("vv",  $record, $length);
		$data	  = pack("d",   $margin);
		if (self::getByteOrder()) $data = strrev($data);
		$this->_append($header . $data);
	}

	private function _writeMarginTop() {
		$record  = 0x0028;
		$length  = 0x0008;
		$margin  = $this->_phpSheet->getPageMargins()->getTop();
		$header	= pack("vv",  $record, $length);
		$data	  = pack("d",   $margin);
		if (self::getByteOrder()) $data = strrev($data);
		$this->_append($header . $data);
	}

	private function _writeMarginBottom() {
		$record  = 0x0029;
		$length  = 0x0008;
		$margin  = $this->_phpSheet->getPageMargins()->getBottom();
		$header	= pack("vv",  $record, $length);
		$data	  = pack("d",   $margin);
		if (self::getByteOrder()) $data = strrev($data);
		$this->_append($header . $data);
	}

	private function _writePrintHeaders() {
		$record	  = 0x002a;
		$length	  = 0x0002;
		$fPrintRwCol = $this->_print_headers;
		$header	  = pack("vv", $record, $length);
		$data		= pack("v", $fPrintRwCol);
		$this->_append($header . $data);
	}

	private function _writePrintGridlines() {
		$record	  = 0x002b;
		$length	  = 0x0002;
		$fPrintGrid  = $this->_phpSheet->getPrintGridlines() ? 1 : 0;
		$header	  = pack("vv", $record, $length);
		$data	  = pack("v", $fPrintGrid);
		$this->_append($header . $data);
	}

	private function _writeGridset() {
		$record	  = 0x0082;
		$length	  = 0x0002;
		$fGridSet	= !$this->_phpSheet->getPrintGridlines();
		$header	  = pack("vv",  $record, $length);
		$data		= pack("v",   $fGridSet);
		$this->_append($header . $data);
	}

	private function _writeAutoFilterInfo(){
		$record	  = 0x009D;
		$length	  = 0x0002;
		$rangeBounds = PHPExcel_Cell::rangeBoundaries($this->_phpSheet->getAutoFilter()->getRange());
		$iNumFilters = 1 + $rangeBounds[1][0] - $rangeBounds[0][0];
		$header   = pack("vv", $record, $length);
		$data     = pack("v",  $iNumFilters);
		$this->_append($header . $data);
	}

	private  function _writeGuts() {
		$record	  = 0x0080;
		$length	  = 0x0008;
		$dxRwGut	 = 0x0000;
		$dxColGut	= 0x0000;
		$maxRowOutlineLevel = 0;
		foreach ($this->_phpSheet->getRowDimensions() as $rowDimension) $maxRowOutlineLevel = max($maxRowOutlineLevel, $rowDimension->getOutlineLevel());
		$col_level   = 0;
		$colcount = count($this->_colinfo);
		for ($i = 0; $i < $colcount; ++$i) $col_level = max($this->_colinfo[$i][5], $col_level);
		$col_level = max(0, min($col_level, 7));
		if ($maxRowOutlineLevel) ++$maxRowOutlineLevel;
		if ($col_level) ++$col_level;
		$header	  = pack("vv",   $record, $length);
		$data		= pack("vvvv", $dxRwGut, $dxColGut, $maxRowOutlineLevel, $col_level);
		$this->_append($header.$data);
	}

	private function _writeWsbool() {
		$record	  = 0x0081;
		$length	  = 0x0002;
		$grbit	   = 0x0000;
		$grbit |= 0x0001;
		if ($this->_outline_style) $grbit |= 0x0020;
		if ($this->_phpSheet->getShowSummaryBelow()) $grbit |= 0x0040;
		if ($this->_phpSheet->getShowSummaryRight()) $grbit |= 0x0080;
		if ($this->_phpSheet->getPageSetup()->getFitToPage()) $grbit |= 0x0100;
		if ($this->_outline_on) $grbit |= 0x0400;
		$header	  = pack("vv", $record, $length);
		$data		= pack("v",  $grbit);
		$this->_append($header . $data);
	}

	private function _writeBreaks() {
		$vbreaks = array();
		$hbreaks = array();
		foreach ($this->_phpSheet->getBreaks() as $cell => $breakType) {
			$coordinates = PHPExcel_Cell::coordinateFromString($cell);
			switch ($breakType) {
				case PHPExcel_Worksheet::BREAK_COLUMN:
					$vbreaks[] = PHPExcel_Cell::columnIndexFromString($coordinates[0]) - 1;
					break;
				case PHPExcel_Worksheet::BREAK_ROW:
					$hbreaks[] = $coordinates[1];
					break;
				case PHPExcel_Worksheet::BREAK_NONE:
				default:
					break;
			}
		}
		if (!empty($hbreaks)) {
			sort($hbreaks, SORT_NUMERIC);
			if ($hbreaks[0] == 0) array_shift($hbreaks);
			$record  = 0x001b;
			$cbrk	= count($hbreaks);
			$length  = 2 + 6 * $cbrk;
			$header  = pack("vv", $record, $length);
			$data	= pack("v",  $cbrk);
			foreach ($hbreaks as $hbreak) $data .= pack("vvv", $hbreak, 0x0000, 0x00ff);
			$this->_append($header . $data);
		}
		if (!empty($vbreaks)) {
			$vbreaks = array_slice($vbreaks, 0, 1000);
			sort($vbreaks, SORT_NUMERIC);
			if ($vbreaks[0] == 0) array_shift($vbreaks);
			$record  = 0x001a;
			$cbrk	= count($vbreaks);
			$length  = 2 + 6 * $cbrk;
			$header  = pack("vv",  $record, $length);
			$data	= pack("v",   $cbrk);
			foreach ($vbreaks as $vbreak) $data .= pack("vvv", $vbreak, 0x0000, 0xffff);
			$this->_append($header . $data);
		}
	}

	private function _writeProtect() {
		if (!$this->_phpSheet->getProtection()->getSheet()) return;
		$record	  = 0x0012;
		$length	  = 0x0002;
		$fLock	   = 1;
		$header	  = pack("vv", $record, $length);
		$data		= pack("v",  $fLock);
		$this->_append($header.$data);
	}

	private function _writeScenProtect() {
		if (!$this->_phpSheet->getProtection()->getSheet()) return;
		if (!$this->_phpSheet->getProtection()->getScenarios()) return;
		$record = 0x00DD;
		$length = 0x0002;
		$header = pack('vv', $record, $length);
		$data = pack('v', 1);
		$this->_append($header . $data);
	}

	private function _writeObjectProtect() {
		if (!$this->_phpSheet->getProtection()->getSheet()) return;
		if (!$this->_phpSheet->getProtection()->getObjects()) return;
		$record = 0x0063;
		$length = 0x0002;
		$header = pack('vv', $record, $length);
		$data = pack('v', 1);
		$this->_append($header . $data);
	}

	private function _writePassword() {
		if (!$this->_phpSheet->getProtection()->getSheet() || !$this->_phpSheet->getProtection()->getPassword()) return;
		$record	  = 0x0013;
		$length	  = 0x0002;
		$wPassword   = hexdec($this->_phpSheet->getProtection()->getPassword());
		$header	  = pack("vv", $record, $length);
		$data		= pack("v",  $wPassword);
		$this->_append($header . $data);
	}

	function insertBitmap($row, $col, $bitmap, $x = 0, $y = 0, $scale_x = 1, $scale_y = 1) {
		$bitmap_array = (is_resource($bitmap) ? $this->_processBitmapGd($bitmap) : $this->_processBitmap($bitmap));
		list($width, $height, $size, $data) = $bitmap_array;
		$width  *= $scale_x;
		$height *= $scale_y;
		$this->_positionImage($col, $row, $x, $y, $width, $height);
		$record	  = 0x007f;
		$length	  = 8 + $size;
		$cf		  = 0x09;
		$env		 = 0x01;
		$lcb		 = $size;
		$header	  = pack("vvvvV", $record, $length, $cf, $env, $lcb);
		$this->_append($header.$data);
	}

	function _positionImage($col_start, $row_start, $x1, $y1, $width, $height) {
		$col_end	= $col_start;
		$row_end	= $row_start;
		if ($x1 >= PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_start))) $x1 = 0;
		if ($y1 >= PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_start + 1)) $y1 = 0;
		$width	  = $width  + $x1 -1;
		$height	 = $height + $y1 -1;
		while ($width >= PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_end))) {
			$width -= PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_end));
			++$col_end;
		}
		while ($height >= PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_end + 1)) {
			$height -= PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_end + 1);
			++$row_end;
		}
		if (PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_start)) == 0) return;
		if (PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_end))   == 0) return;
		if (PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_start + 1) == 0) return;
		if (PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_end + 1)   == 0) return;
		$x1 = $x1	 / PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_start))   * 1024;
		$y1 = $y1	 / PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_start + 1)   *  256;
		$x2 = $width  / PHPExcel_Shared_Excel5::sizeCol($this->_phpSheet, PHPExcel_Cell::stringFromColumnIndex($col_end))	 * 1024;
		$y2 = $height / PHPExcel_Shared_Excel5::sizeRow($this->_phpSheet, $row_end + 1)	 *  256;
		$this->_writeObjPicture($col_start, $x1, $row_start, $y1, $col_end, $x2, $row_end, $y2);
	}

	private function _writeObjPicture($colL,$dxL,$rwT,$dyT,$colR,$dxR,$rwB,$dyB) {
		$record	  = 0x005d;
		$length	  = 0x003c;
		$cObj		= 0x0001;
		$OT		  = 0x0008;
		$id		  = 0x0001;
		$grbit	   = 0x0614;
		$cbMacro	 = 0x0000;
		$Reserved1   = 0x0000;
		$Reserved2   = 0x0000;
		$icvBack	 = 0x09;
		$icvFore	 = 0x09;
		$fls		 = 0x00;
		$fAuto	   = 0x00;
		$icv		 = 0x08;
		$lns		 = 0xff;
		$lnw		 = 0x01;
		$fAutoB	  = 0x00;
		$frs		 = 0x0000;
		$cf		  = 0x0009;
		$Reserved3   = 0x0000;
		$cbPictFmla  = 0x0000;
		$Reserved4   = 0x0000;
		$grbit2	  = 0x0001;
		$Reserved5   = 0x0000;
		$header	  = pack("vv", $record, $length);
		$data		= pack("V", $cObj);
		$data	   .= pack("v", $OT);
		$data	   .= pack("v", $id);
		$data	   .= pack("v", $grbit);
		$data	   .= pack("v", $colL);
		$data	   .= pack("v", $dxL);
		$data	   .= pack("v", $rwT);
		$data	   .= pack("v", $dyT);
		$data	   .= pack("v", $colR);
		$data	   .= pack("v", $dxR);
		$data	   .= pack("v", $rwB);
		$data	   .= pack("v", $dyB);
		$data	   .= pack("v", $cbMacro);
		$data	   .= pack("V", $Reserved1);
		$data	   .= pack("v", $Reserved2);
		$data	   .= pack("C", $icvBack);
		$data	   .= pack("C", $icvFore);
		$data	   .= pack("C", $fls);
		$data	   .= pack("C", $fAuto);
		$data	   .= pack("C", $icv);
		$data	   .= pack("C", $lns);
		$data	   .= pack("C", $lnw);
		$data	   .= pack("C", $fAutoB);
		$data	   .= pack("v", $frs);
		$data	   .= pack("V", $cf);
		$data	   .= pack("v", $Reserved3);
		$data	   .= pack("v", $cbPictFmla);
		$data	   .= pack("v", $Reserved4);
		$data	   .= pack("v", $grbit2);
		$data	   .= pack("V", $Reserved5);
		$this->_append($header . $data);
	}

	function _processBitmapGd($image) {
		$width = imagesx($image);
		$height = imagesy($image);
		$data = pack("Vvvvv", 0x000c, $width, $height, 0x01, 0x18);
		for ($j=$height; $j--; ) {
			for ($i=0; $i < $width; ++$i) {
				$color = imagecolorsforindex($image, imagecolorat($image, $i, $j));
				foreach (array("red", "green", "blue") as $key) $color[$key] = $color[$key] + round((255 - $color[$key]) * $color["alpha"] / 127);
				$data .= chr($color["blue"]) . chr($color["green"]) . chr($color["red"]);
			}
			if (3*$width % 4) $data .= str_repeat("\x00", 4 - 3*$width % 4);
		}
		return array($width, $height, strlen($data), $data);
	}

	function _processBitmap($bitmap) {
		$bmp_fd = @fopen($bitmap,"rb");
		if (!$bmp_fd) throw new Exception("Couldn't import $bitmap");
		$data = fread($bmp_fd, filesize($bitmap));
		if (strlen($data) <= 0x36) throw new Exception("$bitmap doesn't contain enough data.\n");
		$identity = unpack("A2ident", $data);
		if ($identity['ident'] != "BM") throw new Exception("$bitmap doesn't appear to be a valid bitmap image.\n");
		$data = substr($data, 2);
		$size_array   = unpack("Vsa", substr($data, 0, 4));
		$size   = $size_array['sa'];
		$data   = substr($data, 4);
		$size  -= 0x36;
		$size  += 0x0C;
		$data = substr($data, 12);
		$width_and_height = unpack("V2", substr($data, 0, 8));
		$width  = $width_and_height[1];
		$height = $width_and_height[2];
		$data   = substr($data, 8);
		if ($width > 0xFFFF) throw new Exception("$bitmap: largest image width supported is 65k.\n");
		if ($height > 0xFFFF) throw new Exception("$bitmap: largest image height supported is 65k.\n");
		$planes_and_bitcount = unpack("v2", substr($data, 0, 4));
		$data = substr($data, 4);
		if ($planes_and_bitcount[2] != 24) throw new Exception("$bitmap isn't a 24bit true color bitmap.\n");
		if ($planes_and_bitcount[1] != 1) throw new Exception("$bitmap: only 1 plane supported in bitmap image.\n");
		$compression = unpack("Vcomp", substr($data, 0, 4));
		$data = substr($data, 4);
		if ($compression['comp'] != 0) throw new Exception("$bitmap: compression not supported in bitmap image.\n");
		$data = substr($data, 20);
		$header  = pack("Vvvvv", 0x000c, $width, $height, 0x01, 0x18);
		$data	= $header . $data;
		return (array($width, $height, $size, $data));
	}

	private function _writeZoom() {
		if ($this->_phpSheet->getSheetView()->getZoomScale() == 100) return;
		$record	  = 0x00A0;
		$length	  = 0x0004;
		$header	  = pack("vv", $record, $length);
		$data		= pack("vv", $this->_phpSheet->getSheetView()->getZoomScale(), 100);
		$this->_append($header . $data);
	}

	public function getEscher() {
		return $this->_escher;
	}

	public function setEscher(PHPExcel_Shared_Escher $pValue = null) {
		$this->_escher = $pValue;
	}

	private function _writeMsoDrawing() {
		if (isset($this->_escher)) {
			$writer = new PHPExcel_Writer_Excel5_Escher($this->_escher);
			$data = $writer->close();
			$spOffsets = $writer->getSpOffsets();
			$spTypes = $writer->getSpTypes();
			$spOffsets[0] = 0;
			$nm = count($spOffsets) - 1;
			for ($i = 1; $i <= $nm; ++$i) {
				$record = 0x00EC;
				$dataChunk = substr($data, $spOffsets[$i -1], $spOffsets[$i] - $spOffsets[$i - 1]);
				$length = strlen($dataChunk);
				$header = pack("vv", $record, $length);
				$this->_append($header . $dataChunk);
				$record = 0x005D;
				$objData = '';
				if($spTypes[$i] == 0x00C9){
					$objData .=
						pack('vvvvvVVV' , 0x0015 , 0x0012 , 0x0014 , $i	 , 0x2101 , 0 , 0 , 0);
					$objData .= pack('vv', 0x00C, 0x0014);
					$objData .= pack('H*', '0000000000000000640001000A00000010000100');
					$objData .= pack('vv', 0x0013, 0x1FEE);
					$objData .= pack('H*', '00000000010001030000020008005700');
				} else {
					$objData .=
						pack('vvvvvVVV', 0x0015, 0x0012, 0x0008, $i, 0x6011, 0, 0, 0);
				}
				$objData .= pack('vv' , 0x0000 , 0x0000);
				$length = strlen($objData);
				$header = pack('vv', $record, $length);
				$this->_append($header . $objData);
			}
		}
	}

	private function _writeDataValidity() {
		$dataValidationCollection = $this->_phpSheet->getDataValidationCollection();
		if (!empty($dataValidationCollection)) {
			$record = 0x01B2;
			$length	  = 0x0012;
			$grbit  = 0x0000;
			$horPos	  = 0x00000000;
			$verPos	  = 0x00000000;
			$objId  = 0xFFFFFFFF;
			$header	  = pack('vv', $record, $length);
			$data		= pack('vVVVV', $grbit, $horPos, $verPos, $objId, count($dataValidationCollection));
			$this->_append($header.$data);
			$record = 0x01BE;
			foreach ($dataValidationCollection as $cellCoordinate => $dataValidation) {
				$data = '';
				$options = 0x00000000;
				$type = $dataValidation->getType();
				switch ($type) {
					case PHPExcel_Cell_DataValidation::TYPE_NONE:		$type = 0x00;	break;
					case PHPExcel_Cell_DataValidation::TYPE_WHOLE:		$type = 0x01;	break;
					case PHPExcel_Cell_DataValidation::TYPE_DECIMAL:	$type = 0x02;	break;
					case PHPExcel_Cell_DataValidation::TYPE_LIST:		$type = 0x03;	break;
					case PHPExcel_Cell_DataValidation::TYPE_DATE:		$type = 0x04;	break;
					case PHPExcel_Cell_DataValidation::TYPE_TIME:		$type = 0x05;	break;
					case PHPExcel_Cell_DataValidation::TYPE_TEXTLENGTH:	$type = 0x06;	break;
					case PHPExcel_Cell_DataValidation::TYPE_CUSTOM:		$type = 0x07;	break;
				}
				$options |= $type << 0;
				$errorStyle = $dataValidation->getType();
				switch ($errorStyle) {
					case PHPExcel_Cell_DataValidation::STYLE_STOP:			$errorStyle = 0x00;		break;
					case PHPExcel_Cell_DataValidation::STYLE_WARNING:		$errorStyle = 0x01;		break;
					case PHPExcel_Cell_DataValidation::STYLE_INFORMATION:	$errorStyle = 0x02;		break;
				}
				$options |= $errorStyle << 4;
				if ($type == 0x03 && preg_match('/^\".*\"$/', $dataValidation->getFormula1())) $options |= 0x01				<< 7;
				$options |= $dataValidation->getAllowBlank() << 8;
				$options |= (!$dataValidation->getShowDropDown()) << 9;
				$options |= $dataValidation->getShowInputMessage() << 18;
				$options |= $dataValidation->getShowErrorMessage() << 19;
				$operator = $dataValidation->getOperator();
				switch ($operator) {
					case PHPExcel_Cell_DataValidation::OPERATOR_BETWEEN: $operator = 0x00			;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_NOTBETWEEN: $operator = 0x01		;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_EQUAL: $operator = 0x02				;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_NOTEQUAL: $operator = 0x03			;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_GREATERTHAN: $operator = 0x04		;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_LESSTHAN: $operator = 0x05			;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_GREATERTHANOREQUAL: $operator = 0x06;	break;
					case PHPExcel_Cell_DataValidation::OPERATOR_LESSTHANOREQUAL: $operator = 0x07	;	break;
				}
				$options |= $operator << 20;
				$data		= pack('V', $options);
				$promptTitle = $dataValidation->getPromptTitle() !== '' ? $dataValidation->getPromptTitle() : chr(0);
				$data .= PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($promptTitle);
				$errorTitle = $dataValidation->getErrorTitle() !== '' ? $dataValidation->getErrorTitle() : chr(0);
				$data .= PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($errorTitle);
				$prompt = $dataValidation->getPrompt() !== '' ? $dataValidation->getPrompt() : chr(0);
				$data .= PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($prompt);
				$error = $dataValidation->getError() !== '' ? $dataValidation->getError() : chr(0);
				$data .= PHPExcel_Shared_String::UTF8toBIFF8UnicodeLong($error);
				try {
					$formula1 = $dataValidation->getFormula1();
					if ($type == 0x03) $formula1 = str_replace(',', chr(0), $formula1);
					$this->_parser->parse($formula1);
					$formula1 = $this->_parser->toReversePolish();
					$sz1 = strlen($formula1);
				} catch(Exception $e) {
					$sz1 = 0;
					$formula1 = '';
				}
				$data .= pack('vv', $sz1, 0x0000);
				$data .= $formula1;
				try {
					$formula2 = $dataValidation->getFormula2();
					if ($formula2 === '') throw new Exception('No formula2');
					$this->_parser->parse($formula2);
					$formula2 = $this->_parser->toReversePolish();
					$sz2 = strlen($formula2);
				} catch(Exception $e) {
					$sz2 = 0;
					$formula2 = '';
				}
				$data .= pack('vv', $sz2, 0x0000);
				$data .= $formula2;
				$data .= pack('v', 0x0001);
				$data .= $this->_writeBIFF8CellRangeAddressFixed($cellCoordinate);
				$length = strlen($data);
				$header = pack("vv", $record, $length);
				$this->_append($header . $data);
			}
		}
	}

	private static function _mapErrorCode($errorCode) {
		switch ($errorCode) {
			case '#NULL!':	return 0x00;
			case '#DIV/0!':	return 0x07;
			case '#VALUE!':	return 0x0F;
			case '#REF!':	return 0x17;
			case '#NAME?':	return 0x1D;
			case '#NUM!':	return 0x24;
			case '#N/A':	return 0x2A;
		}
		return 0;
	}
}