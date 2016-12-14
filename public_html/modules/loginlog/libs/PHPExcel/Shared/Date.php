<?php
class PHPExcel_Shared_Date
{
	const CALENDAR_WINDOWS_1900 = 1900;
	const CALENDAR_MAC_1904 = 1904;

	public static $_monthNames = array(	'Jan' => 'January',
										'Feb' => 'February',
										'Mar' => 'March',
										'Apr' => 'April',
										'May' => 'May',
										'Jun' => 'June',
										'Jul' => 'July',
										'Aug' => 'August',
										'Sep' => 'September',
										'Oct' => 'October',
										'Nov' => 'November',
										'Dec' => 'December'
									  );
	private static $ExcelBaseDate	= self::CALENDAR_WINDOWS_1900;
	public static $dateTimeObjectType	= 'DateTime';

	public static function setExcelCalendar($baseDate) {
		if (($baseDate == self::CALENDAR_WINDOWS_1900) || ($baseDate == self::CALENDAR_MAC_1904)) {
			self::$ExcelBaseDate = $baseDate;
			return TRUE;
		}
		return FALSE;
	}


	public static function getExcelCalendar() {
		return self::$ExcelBaseDate;
	}

	public static function ExcelToPHP($dateValue = 0) {
		if (self::$ExcelBaseDate == self::CALENDAR_WINDOWS_1900) {
			$myExcelBaseDate = 25569;
			if ($dateValue < 60) --$myExcelBaseDate;
		} else {
			$myExcelBaseDate = 24107;
		}
		if ($dateValue >= 1) {
			$utcDays = $dateValue - $myExcelBaseDate;
			$returnValue = round($utcDays * 86400);
			if (($returnValue <= PHP_INT_MAX) && ($returnValue >= -PHP_INT_MAX)) $returnValue = (integer) $returnValue;
		} else {
			$hours = round($dateValue * 24);
			$mins = round($dateValue * 1440) - round($hours * 60);
			$secs = round($dateValue * 86400) - round($hours * 3600) - round($mins * 60);
			$returnValue = (integer) gmmktime($hours, $mins, $secs);
		}
		return $returnValue;
	}

	public static function ExcelToPHPObject($dateValue = 0) {
		$dateTime = self::ExcelToPHP($dateValue);
		$days = floor($dateTime / 86400);
		$time = round((($dateTime / 86400) - $days) * 86400);
		$hours = round($time / 3600);
		$minutes = round($time / 60) - ($hours * 60);
		$seconds = round($time) - ($hours * 3600) - ($minutes * 60);
		$dateObj = date_create('1-Jan-1970+'.$days.' days');
		$dateObj->setTime($hours,$minutes,$seconds);
		return $dateObj;
	}

	public static function PHPToExcel($dateValue = 0) {
		$saveTimeZone = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$retValue = FALSE;
		if ((is_object($dateValue)) && ($dateValue instanceof self::$dateTimeObjectType))
			$retValue = self::FormattedPHPToExcel( $dateValue->format('Y'), $dateValue->format('m'), $dateValue->format('d'), $dateValue->format('H'), $dateValue->format('i'), $dateValue->format('s'));
		elseif (is_numeric($dateValue))
			$retValue = self::FormattedPHPToExcel( date('Y',$dateValue), date('m',$dateValue), date('d',$dateValue), date('H',$dateValue), date('i',$dateValue), date('s',$dateValue));
		date_default_timezone_set($saveTimeZone);
		return $retValue;
	}

	public static function FormattedPHPToExcel($year, $month, $day, $hours=0, $minutes=0, $seconds=0) {
		if (self::$ExcelBaseDate == self::CALENDAR_WINDOWS_1900) {
			$excel1900isLeapYear = TRUE;
			if (($year == 1900) && ($month <= 2)) $excel1900isLeapYear = FALSE;
			$myExcelBaseDate = 2415020;
		} else {
			$myExcelBaseDate = 2416481;
			$excel1900isLeapYear = FALSE;
		}
		if ($month > 2) {
			$month -= 3;
		} else {
			$month += 9;
			--$year;
		}
		$century = substr($year,0,2);
		$decade = substr($year,2,2);
		$excelDate = floor((146097 * $century) / 4) + floor((1461 * $decade) / 4) + floor((153 * $month + 2) / 5) + $day + 1721119 - $myExcelBaseDate + $excel1900isLeapYear;
		$excelTime = (($hours * 3600) + ($minutes * 60) + $seconds) / 86400;
		return (float) $excelDate + $excelTime;
	}

	public static function isDateTime(PHPExcel_Cell $pCell) {
		return self::isDateTimeFormat($pCell->getParent()->getStyle($pCell->getCoordinate())->getNumberFormat());
	}

	public static function isDateTimeFormat(PHPExcel_Style_NumberFormat $pFormat) {
		return self::isDateTimeFormatCode($pFormat->getFormatCode());
	}

	private static	$possibleDateFormatCharacters = 'eymdHs';

	public static function isDateTimeFormatCode($pFormatCode = '') {
		switch ($pFormatCode) {
			case PHPExcel_Style_NumberFormat::FORMAT_GENERAL:
				return FALSE;
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_DMYSLASH:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_DMYMINUS:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_DMMINUS:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_MYMINUS:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_DATETIME:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME1:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME2:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME3:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME4:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME5:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME6:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME7:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME8:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX15:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX16:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX17:
			case PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX22:
				return TRUE;
		}
		if ((substr($pFormatCode,0,1) == '_') || (substr($pFormatCode,0,2) == '0 ')) return FALSE;
		if (preg_match('/(^|\])[^\[]*['.self::$possibleDateFormatCharacters.']/i',$pFormatCode)) {
			if (strpos($pFormatCode,'"') !== FALSE) {
				$i = FALSE;
				foreach(explode('"',$pFormatCode) as $subVal) {
					if (($i = !$i) && (preg_match('/(^|\])[^\[]*['.self::$possibleDateFormatCharacters.']/i',$subVal))) return TRUE;
				}
				return FALSE;
			}
			return TRUE;
		}
		return FALSE;
	}

	public static function stringToExcel($dateValue = '') {
		if (strlen($dateValue) < 2) return FALSE;
		if (!preg_match('/^(\d{1,4}[ \.\/\-][A-Z]{3,9}([ \.\/\-]\d{1,4})?|[A-Z]{3,9}[ \.\/\-]\d{1,4}([ \.\/\-]\d{1,4})?|\d{1,4}[ \.\/\-]\d{1,4}([ \.\/\-]\d{1,4})?)( \d{1,2}:\d{1,2}(:\d{1,2})?)?$/iu', $dateValue))
			return FALSE;
		$dateValueNew = PHPExcel_Calculation_DateTime::DATEVALUE($dateValue);
		if ($dateValueNew === PHPExcel_Calculation_Functions::VALUE()) {
			return FALSE;
		} else {
			if (strpos($dateValue, ':') !== FALSE) {
				$timeValue = PHPExcel_Calculation_DateTime::TIMEVALUE($dateValue);
				if ($timeValue === PHPExcel_Calculation_Functions::VALUE()) return FALSE;
				$dateValueNew += $timeValue;
			}
			return $dateValueNew;
		}
	}
}
