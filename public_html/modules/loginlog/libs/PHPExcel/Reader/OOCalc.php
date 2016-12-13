<?php
if (!defined('PHPEXCEL_ROOT')) {
	define('PHPEXCEL_ROOT', dirname(__FILE__) . '/../../');
	require(PHPEXCEL_ROOT . 'PHPExcel/Autoloader.php');
}

class PHPExcel_Reader_OOCalc implements PHPExcel_Reader_IReader
{
	private $_readDataOnly = false;
	private $_loadSheetsOnly = null;
	private $_styles = array();
	private $_readFilter = null;

	public function __construct() {
		$this->_readFilter 	= new PHPExcel_Reader_DefaultReadFilter();
	}

	public function getReadDataOnly() {
		return $this->_readDataOnly;
	}

	public function setReadDataOnly($pValue = false) {
		$this->_readDataOnly = $pValue;
		return $this;
	}

	public function getLoadSheetsOnly() {
		return $this->_loadSheetsOnly;
	}

	public function setLoadSheetsOnly($value = null) {
		$this->_loadSheetsOnly = is_array($value) ? $value : array($value);
		return $this;
	}

	public function setLoadAllSheets() {
		$this->_loadSheetsOnly = null;
		return $this;
	}

	public function getReadFilter() {
		return $this->_readFilter;
	}

	public function setReadFilter(PHPExcel_Reader_IReadFilter $pValue) {
		$this->_readFilter = $pValue;
		return $this;
	}

	public function canRead($pFilename) {
		if (!file_exists($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! File does not exist.");
		if (!class_exists('ZipArchive',FALSE)) throw new Exception("ZipArchive library is not enabled");
		$zip = new ZipArchive;
		if ($zip->open($pFilename) === true) {
			$stat = $zip->statName('mimetype');
			if ($stat && ($stat['size'] <= 255)) {
				$mimeType = $zip->getFromName($stat['name']);
			} else {
				$zip->close();
				return FALSE;
			}
			$zip->close();
			return ($mimeType === 'application/vnd.oasis.opendocument.spreadsheet');
		}
		return FALSE;
	}

	public function listWorksheetNames($pFilename) {
		if (!file_exists($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! File does not exist.");
		$worksheetNames = array();
		$zip = new ZipArchive;
		if ($zip->open($pFilename) === true) {
			$xml = simplexml_load_string($zip->getFromName("content.xml"));
			$namespacesContent = $xml->getNamespaces(true);
			$workbook = $xml->children($namespacesContent['office']);
			foreach($workbook->body->spreadsheet as $workbookData) {
				$workbookData = $workbookData->children($namespacesContent['table']);
				foreach($workbookData->table as $worksheetDataSet) {
					$worksheetDataAttributes = $worksheetDataSet->attributes($namespacesContent['table']);
					$worksheetNames[] = $worksheetDataAttributes['name'];
				}
			}
		}
		return $worksheetNames;
	}

	public function load($pFilename) 	{
		$objPHPExcel = new PHPExcel();
		return $this->loadIntoExisting($pFilename, $objPHPExcel);
	}

	private static function identifyFixedStyleValue($styleList,&$styleAttributeValue) {
		$styleAttributeValue = strtolower($styleAttributeValue);
		foreach($styleList as $style) {
			if ($styleAttributeValue == strtolower($style)) {
				$styleAttributeValue = $style;
				return true;
			}
		}
		return false;
	}

	public function listWorksheetInfo($pFilename) {
		if (!file_exists($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! File does not exist.");
		$worksheetInfo = array();
		$zip = new ZipArchive;
		if ($zip->open($pFilename) === true) {
			$xml = simplexml_load_string($zip->getFromName("content.xml"));
			$namespacesContent = $xml->getNamespaces(true);
			$workbook = $xml->children($namespacesContent['office']);
			foreach($workbook->body->spreadsheet as $workbookData) {
				$workbookData = $workbookData->children($namespacesContent['table']);
				foreach($workbookData->table as $worksheetDataSet) {
					$worksheetData = $worksheetDataSet->children($namespacesContent['table']);
					$worksheetDataAttributes = $worksheetDataSet->attributes($namespacesContent['table']);
					$tmpInfo = array();
					$tmpInfo['worksheetName'] = (string) $worksheetDataAttributes['name'];
					$tmpInfo['lastColumnLetter'] = 'A';
					$tmpInfo['lastColumnIndex'] = 0;
					$tmpInfo['totalRows'] = 0;
					$tmpInfo['totalColumns'] = 0;
					$rowIndex = 0;
					foreach ($worksheetData as $key => $rowData) {
						switch ($key) {
							case 'table-row' :
								$rowDataTableAttributes = $rowData->attributes($namespacesContent['table']);
								$rowRepeats = (isset($rowDataTableAttributes['number-rows-repeated'])) ? $rowDataTableAttributes['number-rows-repeated'] : 1;
								$columnIndex = 0;
								foreach ($rowData as $key => $cellData) {
									$cellDataTableAttributes = $cellData->attributes($namespacesContent['table']);
									$colRepeats = (isset($cellDataTableAttributes['number-columns-repeated'])) ?
										$cellDataTableAttributes['number-columns-repeated'] : 1;
									$cellDataOfficeAttributes = $cellData->attributes($namespacesContent['office']);
									if (isset($cellDataOfficeAttributes['value-type'])) {
										$tmpInfo['lastColumnIndex'] = max($tmpInfo['lastColumnIndex'], $columnIndex + $colRepeats - 1);
										$tmpInfo['totalRows'] = max($tmpInfo['totalRows'], $rowIndex + $rowRepeats);
									}
									$columnIndex += $colRepeats;
								}
								$rowIndex += $rowRepeats;
								break;
						}
					}
					$tmpInfo['lastColumnLetter'] = PHPExcel_Cell::stringFromColumnIndex($tmpInfo['lastColumnIndex']);
					$tmpInfo['totalColumns'] = $tmpInfo['lastColumnIndex'] + 1;
					$worksheetInfo[] = $tmpInfo;
				}
			}
		}
		return $worksheetInfo;
	}

	public function loadIntoExisting($pFilename, PHPExcel $objPHPExcel) {
		if (!file_exists($pFilename)) throw new Exception("Could not open " . $pFilename . " for reading! File does not exist.");
		$timezoneObj = new DateTimeZone('Europe/London');
		$GMT = new DateTimeZone('UTC');
		$zip = new ZipArchive;
		if ($zip->open($pFilename) === true) {
			$xml = simplexml_load_string($zip->getFromName("meta.xml"));
			$namespacesMeta = $xml->getNamespaces(true);
			$docProps = $objPHPExcel->getProperties();
			$officeProperty = $xml->children($namespacesMeta['office']);
			foreach($officeProperty as $officePropertyData) {
				$officePropertyDC = array();
				if (isset($namespacesMeta['dc'])) $officePropertyDC = $officePropertyData->children($namespacesMeta['dc']);
				foreach($officePropertyDC as $propertyName => $propertyValue) {
					switch ($propertyName) {
						case 'title' :
								$docProps->setTitle($propertyValue);
								break;
						case 'subject' :
								$docProps->setSubject($propertyValue);
								break;
						case 'creator' :
								$docProps->setCreator($propertyValue);
								$docProps->setLastModifiedBy($propertyValue);
								break;
						case 'date' :
								$creationDate = strtotime($propertyValue);
								$docProps->setCreated($creationDate);
								$docProps->setModified($creationDate);
								break;
						case 'description' :
								$docProps->setDescription($propertyValue);
								break;
					}
				}
				$officePropertyMeta = array();
				if (isset($namespacesMeta['dc'])) $officePropertyMeta = $officePropertyData->children($namespacesMeta['meta']);
				foreach($officePropertyMeta as $propertyName => $propertyValue) {
					$propertyValueAttributes = $propertyValue->attributes($namespacesMeta['meta']);
					switch ($propertyName) {
						case 'initial-creator' :
								$docProps->setCreator($propertyValue);
								break;
						case 'keyword' :
								$docProps->setKeywords($propertyValue);
								break;
						case 'creation-date' :
								$creationDate = strtotime($propertyValue);
								$docProps->setCreated($creationDate);
								break;
						case 'user-defined' :
								$propertyValueType = PHPExcel_DocumentProperties::PROPERTY_TYPE_STRING;
								foreach ($propertyValueAttributes as $key => $value) {
									if ($key == 'name') {
										$propertyValueName = (string) $value;
									} elseif($key == 'value-type') {
										switch ($value) {
											case 'date'	:
												$propertyValue = PHPExcel_DocumentProperties::convertProperty($propertyValue,'date');
												$propertyValueType = PHPExcel_DocumentProperties::PROPERTY_TYPE_DATE;
												break;
											case 'boolean'	:
												$propertyValue = PHPExcel_DocumentProperties::convertProperty($propertyValue,'bool');
												$propertyValueType = PHPExcel_DocumentProperties::PROPERTY_TYPE_BOOLEAN;
												break;
											case 'float'	:
												$propertyValue = PHPExcel_DocumentProperties::convertProperty($propertyValue,'r4');
												$propertyValueType = PHPExcel_DocumentProperties::PROPERTY_TYPE_FLOAT;
												break;
											default :
												$propertyValueType = PHPExcel_DocumentProperties::PROPERTY_TYPE_STRING;
										}
									}
								}
								$docProps->setCustomProperty($propertyValueName,$propertyValue,$propertyValueType);
								break;
					}
				}
			}
			$xml = simplexml_load_string($zip->getFromName("content.xml"));
			$namespacesContent = $xml->getNamespaces(true);
			$workbook = $xml->children($namespacesContent['office']);
			foreach($workbook->body->spreadsheet as $workbookData) {
				$workbookData = $workbookData->children($namespacesContent['table']);
				$worksheetID = 0;
				foreach($workbookData->table as $worksheetDataSet) {
					$worksheetData = $worksheetDataSet->children($namespacesContent['table']);
					$worksheetDataAttributes = $worksheetDataSet->attributes($namespacesContent['table']);
					if ((isset($this->_loadSheetsOnly)) && (isset($worksheetDataAttributes['name'])) &&
						(!in_array($worksheetDataAttributes['name'], $this->_loadSheetsOnly))) {
						continue;
					}
					$objPHPExcel->createSheet();
					$objPHPExcel->setActiveSheetIndex($worksheetID);
					if (isset($worksheetDataAttributes['name'])) {
						$worksheetName = (string) $worksheetDataAttributes['name'];
						$objPHPExcel->getActiveSheet()->setTitle($worksheetName,false);
					}
					$rowID = 1;
					foreach($worksheetData as $key => $rowData) {
						switch ($key) {
							case 'table-header-rows':
								foreach ($rowData as $key=>$cellData) {
									$rowData = $cellData;
									break;
								}
							case 'table-row' :
								$rowDataTableAttributes = $rowData->attributes($namespacesContent['table']);
								$rowRepeats = (isset($rowDataTableAttributes['number-rows-repeated'])) ?
										$rowDataTableAttributes['number-rows-repeated'] : 1;
								$columnID = 'A';
								foreach($rowData as $key => $cellData) {
									if ($this->getReadFilter() !== NULL) {
										if (!$this->getReadFilter()->readCell($columnID, $rowID, $worksheetName)) {
											continue;
										}
									}
									if ($cellData->children) {
										$cellDataText = $cellData->children($namespacesContent['text']);
										$cellDataOffice = $cellData->children($namespacesContent['office']);
										$cellDataOfficeAttributes = $cellData->attributes($namespacesContent['office']);
										$cellDataTableAttributes = $cellData->attributes($namespacesContent['table']);
									} else {
										$cellDataText = '';
										$cellDataOffice = $cellDataOfficeAttributes = $cellDataTableAttributes = array();
									}
									$type = $formatting = $hyperlink = null;
									$hasCalculatedValue = false;
									$cellDataFormula = '';
									if (isset($cellDataTableAttributes['formula'])) {
										$cellDataFormula = $cellDataTableAttributes['formula'];
										$hasCalculatedValue = true;
									}
									if (isset($cellDataOffice->annotation)) {
										$annotationText = $cellDataOffice->annotation->children($namespacesContent['text']);
										$textArray = array();
										foreach($annotationText as $t) {
											foreach($t->span as $text) $textArray[] = (string)$text;
										}
										$text = implode("\n",$textArray);
										$objPHPExcel->getActiveSheet()->getComment( $columnID.$rowID )->setText($this->_parseRichText($text) );
									}
									if (isset($cellDataText->p)) {
										$dataArray = array();
										foreach ($cellDataText->p as $pData) {
											if (isset($pData->span)) {
												$spanSection = "";
												foreach ($pData->span as $spanData) $spanSection .= $spanData;
												array_push($dataArray, $spanSection);
											} else {
												array_push($dataArray, $pData);
											}
										}
										$allCellDataText = implode($dataArray, "\n");
										switch ($cellDataOfficeAttributes['value-type']) {
 											case 'string' :
													$type = PHPExcel_Cell_DataType::TYPE_STRING;
													$dataValue = $allCellDataText;
													if (isset($dataValue->a)) {
														$dataValue = $dataValue->a;
														$cellXLinkAttributes = $dataValue->attributes($namespacesContent['xlink']);
														$hyperlink = $cellXLinkAttributes['href'];
													}
													break;
											case 'boolean' :
													$type = PHPExcel_Cell_DataType::TYPE_BOOL;
													$dataValue = ($allCellDataText == 'TRUE') ? True : False;
													break;
											case 'percentage' :
													$type = PHPExcel_Cell_DataType::TYPE_NUMERIC;
													$dataValue = (float) $cellDataOfficeAttributes['value'];
													if (floor($dataValue) == $dataValue) $dataValue = (integer) $dataValue;
													$formatting = PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00;
													break;
											case 'currency' :
													$type = PHPExcel_Cell_DataType::TYPE_NUMERIC;
													$dataValue = (float) $cellDataOfficeAttributes['value'];
													if (floor($dataValue) == $dataValue) $dataValue = (integer) $dataValue;
													$formatting = PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE;
													break;
											case 'float' :
													$type = PHPExcel_Cell_DataType::TYPE_NUMERIC;
													$dataValue = (float) $cellDataOfficeAttributes['value'];
													if (floor($dataValue) == $dataValue) $dataValue = (integer) $dataValue;
													break;
											case 'date' :
													$type = PHPExcel_Cell_DataType::TYPE_NUMERIC;
												    $dateObj = new DateTime($cellDataOfficeAttributes['date-value'], $GMT);
													$dateObj->setTimeZone($timezoneObj);
													list($year,$month,$day,$hour,$minute,$second) = explode(' ',$dateObj->format('Y m d H i s'));
													$dataValue = PHPExcel_Shared_Date::FormattedPHPToExcel($year,$month,$day,$hour,$minute,$second);
													if ($dataValue != floor($dataValue))
														$formatting = PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX15.' '.PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME4;
													else
														$formatting = PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX15;
													break;
											case 'time' :
													$type = PHPExcel_Cell_DataType::TYPE_NUMERIC;
													$dataValue = PHPExcel_Shared_Date::PHPToExcel(strtotime('01-01-1970 '.implode(':',sscanf($cellDataOfficeAttributes['time-value'],'PT%dH%dM%dS'))));
													$formatting = PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME4;
													break;
										}
									} else {
										$type = PHPExcel_Cell_DataType::TYPE_NULL;
										$dataValue = null;
									}
									if ($hasCalculatedValue) {
										$type = PHPExcel_Cell_DataType::TYPE_FORMULA;
										$cellDataFormula = substr($cellDataFormula,strpos($cellDataFormula,':=')+1);
										$temp = explode('"',$cellDataFormula);
										$tKey = false;
										foreach($temp as &$value) {
											if ($tKey = !$tKey) {
												$value = preg_replace('/\[\.(.*):\.(.*)\]/Ui','$1:$2',$value);
												$value = preg_replace('/\[\.(.*)\]/Ui','$1',$value);
												$value = PHPExcel_Calculation::_translateSeparator(';',',',$value,$inBraces);
											}
										}
										unset($value);
										$cellDataFormula = implode('"',$temp);
									}
									$colRepeats = (isset($cellDataTableAttributes['number-columns-repeated'])) ? $cellDataTableAttributes['number-columns-repeated'] : 1;
									if ($type !== NULL) {
										for ($i = 0; $i < $colRepeats; ++$i) {
											if ($i > 0) ++$columnID;
											if ($type !== PHPExcel_Cell_DataType::TYPE_NULL) {
												for ($rowAdjust = 0; $rowAdjust < $rowRepeats; ++$rowAdjust) {
													$rID = $rowID + $rowAdjust;
													$objPHPExcel->getActiveSheet()->getCell($columnID.$rID)->setValueExplicit((($hasCalculatedValue) ? $cellDataFormula : $dataValue),$type);
													if ($hasCalculatedValue) $objPHPExcel->getActiveSheet()->getCell($columnID.$rID)->setCalculatedValue($dataValue);
													if ($formatting !== NULL)
														$objPHPExcel->getActiveSheet()->getStyle($columnID.$rID)->getNumberFormat()->setFormatCode($formatting);
													else
														$objPHPExcel->getActiveSheet()->getStyle($columnID.$rID)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_GENERAL);
													if ($hyperlink !== NULL) $objPHPExcel->getActiveSheet()->getCell($columnID.$rID)->getHyperlink()->setUrl($hyperlink);
												}
											}
										}
									}
									if ((isset($cellDataTableAttributes['number-columns-spanned'])) || (isset($cellDataTableAttributes['number-rows-spanned']))) {
										if (($type !== PHPExcel_Cell_DataType::TYPE_NULL) || (!$this->_readDataOnly)) {
											$columnTo = $columnID;
											if (isset($cellDataTableAttributes['number-columns-spanned'])) {
												$columnTo = PHPExcel_Cell::stringFromColumnIndex(PHPExcel_Cell::columnIndexFromString($columnID) + $cellDataTableAttributes['number-columns-spanned'] -2);
											}
											$rowTo = $rowID;
											if (isset($cellDataTableAttributes['number-rows-spanned'])) $rowTo = $rowTo + $cellDataTableAttributes['number-rows-spanned'] - 1;
											$cellRange = $columnID.$rowID.':'.$columnTo.$rowTo;
											$objPHPExcel->getActiveSheet()->mergeCells($cellRange);
										}
									}
									++$columnID;
								}
								$rowID += $rowRepeats;
								break;
						}
					}
					++$worksheetID;
				}
			}
		}
		return $objPHPExcel;
	}

	private function _parseRichText($is = '') {
		$value = new PHPExcel_RichText();
		$value->createText($is);
		return $value;
	}
}
