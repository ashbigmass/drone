<?php
define('EXCEL_LOADER_PATH', _XE_PATH_ . '/modules/loginlog/libs/PHPExcel.php');

class Export_Excel extends Export_Core
{
	private $filename;
	private $xlsDoc;
	private $options;

	public function __construct(Array $options = array()) {
		if(!class_exists('PHP_EXCEL')) {
			if(is_readable(EXCEL_LOADER_PATH)) require EXCEL_LOADER_PATH;
			else throw new Exception('Error : ' . EXCEL_LOADER_PATH . ' file not found.');
		}
		$this->xlsDoc = new PHPExcel();
		$this->setOptions($options);
	}

	public function setOptions(Array $option = array()) {
		if(!is_array($option)) return FALSE;
		if(count($option) < 1) return FALSE;
		$xlsDoc = $this->xlsDoc;
		if(isset($option['start_date'])) $this->start_date = $option['start_date'];
		if(isset($option['end_date'])) $this->end_date = $option['end_date'];
		if(isset($option['title'])) $this->title = $option['title'];
		if(isset($option['filename'])) {
			$option['filename'] = trim($option['filename']);
			if(isset($option['filename']{0})) $this->filename = $option['filename'] . '.xls';
		}
		if(isset($option['font'])) {
			if(isset($option['font']['name']) && $option['font']['name']) $xlsDoc->getDefaultStyle()->getFont()->setName($option['font']['name']);
			if(isset($option['font']['size']) && $option['font']['size']) $xlsDoc->getDefaultStyle()->getFont()->setSize($option['font']['size']);
		}
		if(isset($option['properties'])) {
			if(isset($option['properties']['creator'])) $xlsDoc->getProperties()->setCreator($option['properties']['creator']);
			if(isset($option['properties']['modifier'])) $xlsDoc->getProperties()->setLastModifiedBy($option['properties']['modifier']);
		}
		return TRUE;
	}

	public function createSheet($active = FALSE) {
		$this->xlsDoc->createSheet();
		if($active === TRUE) $this->xlsDoc->setActiveSheetIndex($this->xlsDoc->getSheetCount() - 1);
	}

	public function export() {
		if(!$this->filename) {
			$msg = Context::getLang('msg_loginlg_filename_required');
			throw new Exception($msg);
			return FALSE;
		}
		$oLoginlogModel = getModel('loginlog');
		$config = $oLoginlogModel->getModuleConfig();
		$startDate = str_replace('-', '', $this->start_date);
		$endDate = str_replace('-', '', $this->end_date);
		$startPage = Context::get('startPage') ? Context::get('startPage') : 1;
		$listCount = $config->exportConfig->listCount ? $config->exportConfig->listCount : (int)Context::get('listCount');
		$pageCount = $config->exportConfig->pageCount ? $config->exportConfig->pageCount : Context::get('pageCount');
		$includeAdmin = ($config->exportConfig->includeAdmin ? $config->exportConfig->includeAdmin : Context::get('includeAdmin')) == 'Y';
		$isSucceed = Context::get('isSucceed');
		$ipaddress = Context::get('ipaddress');
		if(!$listCount) $listCount = 100;
		if(!$pageCount) $pageCount = 10;
		$query_id = 'loginlog.getLoginlogListWithinMember';
		$args = new stdClass;
		$selected_log_srls = Context::get('cart');
		if($selected_log_srls) {
			$args->s_log_srl = $selected_log_srls;
		} else {
			switch($config->exportConfig->exportType) {
				case 'include':
					if(is_array($config->exportConfig->includeGroup) && count($config->exportConfig->includeGroup) > 0) {
						$args->include_group_srls = implode(',', $config->exportConfig->includeGroup);
						$query_id = 'loginlog.getLoginlogListWithinMemberGroup';
					}
				break;
				case 'exclude':
					if(is_array($config->exportConfig->excludeGroup) && count($config->exportConfig->excludeGroup) > 0) {
						$args->exclude_group_srls = implode(',', $config->exportConfig->excludeGroup);
						$query_id = 'loginlog.getLoginlogListWithinMemberGroup';
					}
				break;
			}
		}
		$args->daterange_start = $startDate;
		$args->daterange_end = $endDate;
		$args->list_count = $listCount;
		$args->page_count = $pageCount;
		$args->sort_index = 'loginlog.regdate';
		$args->order_type = 'desc';
		if(!$includeAdmin) $args->is_admin = 'N';
		if($exportFileType == 'html') $args->page_count = 1;
		$columnList = array(
			'member.user_id', 'member.user_name', 'member.nick_name',
			'loginlog.regdate', 'loginlog.ipaddress', 'loginlog.is_succeed', 'loginlog.platform', 'loginlog.browser'
		);
		$type = Context::get('type');
		$curPage = $startPage;
		do {
			$args->page = $curPage;
			$output = executeQueryArray($query_id, $args, $columnList);
			if($curPage > 1) $this->createSheet(TRUE);
			$sheetObj = $this->xlsDoc->getActiveSheet();
			$sheetObj->getDefaultRowDimension()->setRowHeight(25);
			$sheetObj->getRowDimension(2)->setRowHeight(40);
			$sheetObj->getRowDimension(3)->setRowHeight(3);
			$sheetObj->getRowDimension(5)->setRowHeight(3);
			$sheetObj->setTitle('Page '.$curPage);
			$sheetObj->setCellValue('B1', $this->title);
			$sheetObj->setCellValue('B4', '번호');
			$sheetObj->setCellValue('C6', '분류');
			$sheetObj->setCellValue('D6', '이름');
			$sheetObj->setCellValue('E6', '아이디');
			$sheetObj->setCellValue('F6', '닉네임');
			$sheetObj->setCellValue('G6', 'OS');
			$sheetObj->setCellValue('H6', '브라우저');
			$sheetObj->setCellValue('I6', 'IP 주소');
			$sheetObj->setCellValue('J6', '로그인 시간');
			$sheetObj->setCellValue('I4', '출력일자 :');
			$sheetObj->setCellValue('J4', zdate(date('YmdHis')));
			$titleStyle = $sheetObj->getStyle('B1');
			$titleStyle->getFont()->setName('나눔고딕')->setSize(20)->setBold(true);
			$titleStyle->getFont()->getColor()->setARGB('FF333399');
			$titleStyle->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
			$titleStyle->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
			$sheetObj->getStyle('B6:J6')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
			$sheetObj->getStyle('B6:J6')->getFill()->getStartColor()->setARGB('FFFFFF99');
			$sheetObj->getStyle('B6:J6')->getFont()->setName('나눔고딕');
			$sheetObj->getStyle('B6:J6')->getFont()->setSize(9);
			$sheetObj->getStyle('B6:J6')->getFont()->setBold(true);
			$sheetObj->getStyle('I4:J4')->getFont()->setName('나눔고딕');
			$sheetObj->getStyle('I4')->getFont()->setBold(true);
			$sheetObj->getStyle('G4:J4')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
			$sheetObj->getStyle('B6:J6')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
			if(count($output->data)) {
				$row = 7;
				foreach($output->data as $key => $val) {
					if($val->is_succeed == 'Y') {
						$sheetObj->getStyle('C'.$row)->getFont()->getColor()->setARGB(PHPExcel_Style_Color::COLOR_DARKGREEN);
					} elseif($val->is_succeed == 'N') {
						$sheetObj->getStyle('C'.$row)->getFont()->getColor()->setARGB(PHPExcel_Style_Color::COLOR_RED);
						$sheetObj->getStyle('C'.$row)->getFont()->setBold(true);
					}
					$sheetObj->getStyle('C'.$row)->getFont()->setBold(true);
					$sheetObj->setCellValue('B'.$row, $key);
					$sheetObj->setCellValue('C'.$row, $val->is_succeed == 'Y' ? '성공' : '실패');
					$sheetObj->setCellValue('D'.$row, $val->user_name);
					$sheetObj->setCellValue('E'.$row, $val->user_id);
					$sheetObj->setCellValue('F'.$row, $val->nick_name);
					$sheetObj->setCellValue('G'.$row, $val->platform);
					$sheetObj->setCellValue('H'.$row, $val->browser);
					$sheetObj->setCellValue('I'.$row, $val->ipaddress);
					$sheetObj->setCellValue('J'.$row, zdate($val->regdate));
					$row++;
				}
				$sheetObj->getStyle('B6:J'.($row-1))->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
			}
			$sheetObj->getColumnDimension('B')->setWidth(8);
			$sheetObj->getColumnDimension('C')->setWidth(8);
			$sheetObj->getColumnDimension('D')->setAutoSize(true);
			$sheetObj->getColumnDimension('E')->setAutoSize(true);
			$sheetObj->getColumnDimension('F')->setAutoSize(true);
			$sheetObj->getColumnDimension('G')->setAutoSize(true);
			$sheetObj->getColumnDimension('H')->setAutoSize(true);
			$sheetObj->getColumnDimension('I')->setAutoSize(true);
			$sheetObj->getColumnDimension('J')->setAutoSize(true);
			if(count($output->data) < 1 || true) {
				$D_size = $sheetObj->getColumnDimension('D')->getWidth();
				$E_size = $sheetObj->getColumnDimension('E')->getWidth();
				$F_size = $sheetObj->getColumnDimension('F')->getWidth();
				$G_size = $sheetObj->getColumnDimension('G')->getWidth();
				$H_size = $sheetObj->getColumnDimension('H')->getWidth();
				$I_size = $sheetObj->getColumnDimension('I')->getWidth();
				$J_size = $sheetObj->getColumnDimension('J')->getWidth();
				$sheetObj->getColumnDimension('D')->setAutoSize(false);
				$sheetObj->getColumnDimension('E')->setAutoSize(false);
				$sheetObj->getColumnDimension('F')->setAutoSize(false);
				$sheetObj->getColumnDimension('G')->setAutoSize(false);
				$sheetObj->getColumnDimension('H')->setAutoSize(false);
				$sheetObj->getColumnDimension('I')->setAutoSize(false);
				$sheetObj->getColumnDimension('J')->setAutoSize(false);
				$sheetObj->getColumnDimension('D')->setWidth($D_size + 20);
				$sheetObj->getColumnDimension('E')->setWidth($E_size + 20);
				$sheetObj->getColumnDimension('F')->setWidth($F_size + 20);
				$sheetObj->getColumnDimension('G')->setWidth($G_size + 20);
				$sheetObj->getColumnDimension('H')->setWidth($H_size + 20);
				$sheetObj->getColumnDimension('I')->setWidth($I_size + 20);
				$sheetObj->getColumnDimension('J')->setWidth($J_size + 20);

			}
			$sheetObj->mergeCells('B1:J2');
			$styleArray = array(
				'borders' => array(
					'allborders' => array(
						'style' => PHPExcel_Style_Border::BORDER_THIN,
						'color' => array('argb' => 'FF555555'),
					),
				),
			);
			if(!$row) $row = 7;
			$sheetObj->setAutoFilter('B6:J'.$row);
			$curPage++;
			$output->page_navigation->getNextPage();
		} while($output->page_navigation->cur_page < $output->page_navigation->total_page);
		$this->xlsDoc->setActiveSheetIndex(0);
		header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
		header('Content-Disposition: attachment;filename="' . $this->filename . '"');
		header('Cache-Control: max-age=0');
		$objWriter = PHPExcel_IOFactory::createWriter($this->xlsDoc, 'Excel5');
		$objWriter->save('php://output');
		Context::close();
	}
}
