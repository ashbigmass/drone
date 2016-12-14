<?php
class PHPExcel_Style_Border implements PHPExcel_IComparable
{
	const BORDER_NONE				= 'none';
	const BORDER_DASHDOT			= 'dashDot';
	const BORDER_DASHDOTDOT			= 'dashDotDot';
	const BORDER_DASHED				= 'dashed';
	const BORDER_DOTTED				= 'dotted';
	const BORDER_DOUBLE				= 'double';
	const BORDER_HAIR				= 'hair';
	const BORDER_MEDIUM				= 'medium';
	const BORDER_MEDIUMDASHDOT		= 'mediumDashDot';
	const BORDER_MEDIUMDASHDOTDOT	= 'mediumDashDotDot';
	const BORDER_MEDIUMDASHED		= 'mediumDashed';
	const BORDER_SLANTDASHDOT		= 'slantDashDot';
	const BORDER_THICK				= 'thick';
	const BORDER_THIN				= 'thin';
	private $_borderStyle	= PHPExcel_Style_Border::BORDER_NONE;
	private $_color;
	private $_isSupervisor;
	private $_parent;
	private $_parentPropertyName;

	public function __construct($isSupervisor = false, $isConditional = false) {
		$this->_isSupervisor = $isSupervisor;
		$this->_color	= new PHPExcel_Style_Color(PHPExcel_Style_Color::COLOR_BLACK, $isSupervisor);
		if ($isSupervisor) $this->_color->bindParent($this, '_color');
	}

	public function bindParent($parent, $parentPropertyName) {
		$this->_parent = $parent;
		$this->_parentPropertyName = $parentPropertyName;
		return $this;
	}

	public function getIsSupervisor() {
		return $this->_isSupervisor;
	}

	public function getSharedComponent() {
		switch ($this->_parentPropertyName) {
			case '_allBorders':
			case '_horizontal':
			case '_inside':
			case '_outline':
			case '_vertical':
				throw new Exception('Cannot get shared component for a pseudo-border.');
				break;
			case '_bottom':
				return $this->_parent->getSharedComponent()->getBottom();
				break;
			case '_diagonal':
				return $this->_parent->getSharedComponent()->getDiagonal();
				break;
			case '_left':
				return $this->_parent->getSharedComponent()->getLeft();
				break;
			case '_right':
				return $this->_parent->getSharedComponent()->getRight();
				break;
			case '_top':
				return $this->_parent->getSharedComponent()->getTop();
				break;
		}
	}

	public function getActiveSheet() {
		return $this->_parent->getActiveSheet();
	}

	public function getSelectedCells() {
		return $this->getActiveSheet()->getSelectedCells();
	}

	public function getActiveCell() {
		return $this->getActiveSheet()->getActiveCell();
	}

	public function getStyleArray($array) {
		switch ($this->_parentPropertyName) {
			case '_allBorders': $key = 'allborders'; break;
			case '_bottom': $key = 'bottom'; break;
			case '_diagonal': $key = 'diagonal'; break;
			case '_horizontal': $key = 'horizontal'; break;
			case '_inside': $key = 'inside'; break;
			case '_left': $key = 'left'; break;
			case '_outline': $key = 'outline'; break;
			case '_right': $key = 'right'; break;
			case '_top': $key = 'top'; break;
			case '_vertical': $key = 'vertical'; break;
		}
		return $this->_parent->getStyleArray(array($key => $array));
	}

	public function applyFromArray($pStyles = null) {
		if (is_array($pStyles)) {
			if ($this->_isSupervisor) {
				$this->getActiveSheet()->getStyle($this->getSelectedCells())->applyFromArray($this->getStyleArray($pStyles));
			} else {
				if (array_key_exists('style', $pStyles)) $this->setBorderStyle($pStyles['style']);
				if (array_key_exists('color', $pStyles)) $this->getColor()->applyFromArray($pStyles['color']);
			}
		} else {
			throw new Exception("Invalid style array passed.");
		}
		return $this;
	}

	public function getBorderStyle() {
		if ($this->_isSupervisor) return $this->getSharedComponent()->getBorderStyle();
		return $this->_borderStyle;
	}

	public function setBorderStyle($pValue = PHPExcel_Style_Border::BORDER_NONE) {
		if ($pValue == '') $pValue = PHPExcel_Style_Border::BORDER_NONE;
		if ($this->_isSupervisor) {
			$styleArray = $this->getStyleArray(array('style' => $pValue));
			$this->getActiveSheet()->getStyle($this->getSelectedCells())->applyFromArray($styleArray);
		} else {
			$this->_borderStyle = $pValue;
		}
		return $this;
	}

	public function getColor() {
		return $this->_color;
	}

	public function setColor(PHPExcel_Style_Color $pValue = null) {
		$color = $pValue->getIsSupervisor() ? $pValue->getSharedComponent() : $pValue;
		if ($this->_isSupervisor) {
			$styleArray = $this->getColor()->getStyleArray(array('argb' => $color->getARGB()));
			$this->getActiveSheet()->getStyle($this->getSelectedCells())->applyFromArray($styleArray);
		} else {
			$this->_color = $color;
		}
		return $this;
	}

	public function getHashCode() {
		if ($this->_isSupervisor) return $this->getSharedComponent()->getHashCode();
		return md5($this->_borderStyle . $this->_color->getHashCode() . __CLASS__);
	}

	public function __clone() {
		$vars = get_object_vars($this);
		foreach ($vars as $key => $value) {
			if ((is_object($value)) && ($key != '_parent')) $this->$key = clone $value;
			else $this->$key = $value;
		}
	}
}
