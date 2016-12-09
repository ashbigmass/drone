<?php
class fileAdminModel extends file
{
	function init() {
	}

	function getFileList($obj, $columnList = array()) {
		$args = new stdClass();
		$this->_makeSearchParam($obj, $args);
		if($obj->isvalid == 'Y') $args->isvalid = 'Y';
		elseif($obj->isvalid == 'N') $args->isvalid = 'N';
		if($obj->direct_download == 'Y') $args->direct_download = 'Y';
		elseif($obj->direct_download == 'N') $args->direct_download= 'N';
		$args->sort_index = $obj->sort_index;
		$args->page = $obj->page?$obj->page:1;
		$args->list_count = $obj->list_count?$obj->list_count:20;
		$args->page_count = $obj->page_count?$obj->page_count:10;
		$args->s_module_srl = $obj->module_srl;
		$args->exclude_module_srl = $obj->exclude_module_srl;
		$output = executeQuery('file.getFileList', $args, $columnList);
		if(!$output->toBool()||!count($output->data)) return $output;
		$oFileModel = getModel('file');
		foreach($output->data as $key => $file) {
			if($_SESSION['file_management'][$file->file_srl]) $file->isCarted = true;
			else $file->isCarted = false;
			$file->download_url = $oFileModel->getDownloadUrl($file->file_srl, $file->sid, $file->module_srl);
			$output->data[$key] = $file;
		}
		return $output;
	}

	function getFilesCountByGroupValid($obj = '') {
		$output = executeQueryArray('file.getFilesCountByGroupValid', $args);
		return $output->data;
	}

	function getFilesCountByDate($date = '') {
		if($date) $args->regDate = date('Ymd', strtotime($date));
		$output = executeQuery('file.getFilesCount', $args);
		if(!$output->toBool()) return 0;
		return $output->data->count;
	}

	function _makeSearchParam(&$obj, &$args) {
		$search_target = $obj->search_target?$obj->search_target:trim(Context::get('search_target'));
		$search_keyword = $obj->search_keyword?$obj->search_keyword:trim(Context::get('search_keyword'));
		if($search_target && $search_keyword) {
			switch($search_target) {
				case 'filename' :
					if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
					$args->s_filename = $search_keyword;
				break;
				case 'filesize_more' : $args->s_filesize_more = (int)$search_keyword; break;
				case 'filesize_mega_more' : $args->s_filesize_more = (int)$search_keyword * 1024 * 1024; break;
				case 'filesize_less' : $args->s_filesize_less = (int)$search_keyword; break;
				case 'filesize_mega_less' : $args->s_filesize_less = (int)$search_keyword * 1024 * 1024; break;
				case 'download_count' : $args->s_download_count = (int)$search_keyword; break;
				case 'regdate' : $args->s_regdate = $search_keyword; break;
				case 'ipaddress' : $args->s_ipaddress = $search_keyword; break;
				case 'user_id' : $args->s_user_id = $search_keyword; break;
				case 'user_name' : $args->s_user_name = $search_keyword; break;
				case 'nick_name' : $args->s_nick_name = $search_keyword; break;
				case 'isvalid' : $args->isvalid = $search_keyword; break;
			}
		}
	}
}
