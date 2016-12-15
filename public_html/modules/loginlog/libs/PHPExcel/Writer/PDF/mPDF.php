<?php
$pdfRendererClassFile = PHPExcel_Settings::getPdfRendererPath() . '/mpdf.php';
if (file_exists($pdfRendererClassFile)) {
	require_once $pdfRendererClassFile;
} else {
	throw new Exception('Unable to load PDF Rendering library');
}

class PHPExcel_Writer_PDF_mPDF extends PHPExcel_Writer_PDF_Core implements PHPExcel_Writer_IWriter
{
	public function __construct(PHPExcel $phpExcel) {
		parent::__construct($phpExcel);
	}

	public function save($pFilename = null) {
		$this->_phpExcel->garbageCollect();
		$saveArrayReturnType = PHPExcel_Calculation::getArrayReturnType();
		PHPExcel_Calculation::setArrayReturnType(PHPExcel_Calculation::RETURN_ARRAY_AS_VALUE);
		$fileHandle = fopen($pFilename, 'w');
		if ($fileHandle === false) throw new Exception("Could not open file $pFilename for writing.");
		$this->_isPdf = true;
		$this->buildCSS(true);
		$paperSize = 'LETTER';
		if (is_null($this->getSheetIndex())) {
			$orientation = ($this->_phpExcel->getSheet(0)->getPageSetup()->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
			$printPaperSize = $this->_phpExcel->getSheet(0)->getPageSetup()->getPaperSize();
			$printMargins = $this->_phpExcel->getSheet(0)->getPageMargins();
		} else {
			$orientation = ($this->_phpExcel->getSheet($this->getSheetIndex())->getPageSetup()->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
			$printPaperSize = $this->_phpExcel->getSheet($this->getSheetIndex())->getPageSetup()->getPaperSize();
			$printMargins = $this->_phpExcel->getSheet($this->getSheetIndex())->getPageMargins();
		}
		$this->setOrientation($orientation);
		if (!is_null($this->getOrientation())) {
			$orientation = ($this->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_DEFAULT) ?
				PHPExcel_Worksheet_PageSetup::ORIENTATION_PORTRAIT : $this->getOrientation();
		}
		if (!is_null($this->getPaperSize())) $printPaperSize = $this->getPaperSize();
		if (isset(self::$_paperSizes[$printPaperSize])) $paperSize = self::$_paperSizes[$printPaperSize];
		$pdf = new mpdf();
		$pdf->_setPageSize(strtoupper($paperSize), strtolower($orientation));
		$pdf->SetTitle($this->_phpExcel->getProperties()->getTitle());
		$pdf->SetAuthor($this->_phpExcel->getProperties()->getCreator());
		$pdf->SetSubject($this->_phpExcel->getProperties()->getSubject());
		$pdf->SetKeywords($this->_phpExcel->getProperties()->getKeywords());
		$pdf->SetCreator($this->_phpExcel->getProperties()->getCreator());
		$pdf->WriteHTML($this->generateHTMLHeader(false) . $this->generateSheetData() . $this->generateHTMLFooter());
		fwrite($fileHandle, $pdf->Output('','S'));
		fclose($fileHandle);
		PHPExcel_Calculation::setArrayReturnType($saveArrayReturnType);
	}
}
