<?php
class fileAdminController extends file
{
	function init() {
	}

	function deleteModuleFiles($module_srl) {
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$columnList = array('file_srl', 'uploaded_filename');
		$output = executeQueryArray('file.getModuleFiles',$args, $columnList);
		if(!$output) return $output;
		$files = $output->data;
		$args->module_srl = $module_srl;
		$output = executeQuery('file.deleteModuleFiles', $args);
		if(!$output->toBool()) return $output;
		FileHandler::removeDir( sprintf("./files/attach/images/%s/", $module_srl) ) ;
		FileHandler::removeDir( sprintf("./files/attach/binaries/%s/", $module_srl) );
		$path = array();
		$cnt = count($files);
		for($i=0;$i<$cnt;$i++) {
			$uploaded_filename = $files[$i]->uploaded_filename;
			FileHandler::removeFile($uploaded_filename);
			$path_info = pathinfo($uploaded_filename);
			if(!in_array($path_info['dirname'], $path)) $path[] = $path_info['dirname'];
		}
		for($i=0;$i<count($path);$i++) FileHandler::removeBlankDir($path[$i]);
		return $output;
	}

	function procFileAdminDeleteChecked() {
		$cart = Context::get('cart');
		if(!$cart) return $this->stop('msg_file_cart_is_null');
		if(!is_array($cart)) $file_srl_list= explode('|@|', $cart);
		else $file_srl_list = $cart;
		$file_count = count($file_srl_list);
		if(!$file_count) return $this->stop('msg_file_cart_is_null');
		$oFileController = getController('file');
		for($i=0;$i<$file_count;$i++) {
			$file_srl = trim($file_srl_list[$i]);
			if(!$file_srl) continue;
			$oFileController->deleteFile($file_srl);
		}
		$this->setMessage( sprintf(Context::getLang('msg_checked_file_is_deleted'), $file_count) );
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispFileAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	function procFileAdminInsertConfig() {
		$config = new stdClass();
		$config->allowed_filesize = Context::get('allowed_filesize');
		$config->allowed_attach_size = Context::get('allowed_attach_size');
		$config->allowed_filetypes = str_replace(' ', '', Context::get('allowed_filetypes'));
		$config->allow_outlink = Context::get('allow_outlink');
		$config->allow_outlink_format = Context::get('allow_outlink_format');
		$config->allow_outlink_site = Context::get('allow_outlink_site');
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('file',$config);
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispFileAdminConfig');
		return $this->setRedirectUrl($returnUrl, $output);
	}

	function procFileAdminInsertModuleConfig() {
		$module_srl = Context::get('target_module_srl');
		if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
		else $module_srl = array($module_srl);
		$download_grant = Context::get('download_grant');
		$file_config = new stdClass;
		$file_config->allow_outlink = Context::get('allow_outlink');
		$file_config->allow_outlink_format = Context::get('allow_outlink_format');
		$file_config->allow_outlink_site = Context::get('allow_outlink_site');
		$file_config->allowed_filesize = Context::get('allowed_filesize');
		$file_config->allowed_attach_size = Context::get('allowed_attach_size');
		$file_config->allowed_filetypes = str_replace(' ', '', Context::get('allowed_filetypes'));
		if(!is_array($download_grant)) $file_config->download_grant = explode('|@|',$download_grant);
		else $file_config->download_grant = $download_grant;
		$userFileAllowSize = FileHandler::returnbytes($file_config->allowed_filesize.'M');
		$userAttachAllowSize = FileHandler::returnbytes($file_config->allowed_attach_size.'M');
		$iniPostMaxSize = FileHandler::returnbytes(ini_get('post_max_size'));
		$iniUploadMaxSize = FileHandler::returnbytes(ini_get('upload_max_filesize'));
		$iniMinSzie = min($iniPostMaxSize, $iniUploadMaxSize);
		if($userFileAllowSize > $iniMinSzie || $userAttachAllowSize > $iniMinSzie) return new Object(-1, 'input size over than config in php.ini');
		$oModuleController = getController('module');
		for($i=0;$i<count($module_srl);$i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			$oModuleController->insertModulePartConfig('file',$srl,$file_config);
		}
		$this->setError(-1);
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispBoardAdminContent');
		$this->setRedirectUrl($returnUrl);
	}

	function procFileAdminAddCart() {
		$file_srl = (int)Context::get('file_srl');
		$oFileModel = getModel('file');
		$output = $oFileModel->getFile($file_srl);
		if($output->file_srl) {
			if($_SESSION['file_management'][$output->file_srl]) unset($_SESSION['file_management'][$output->file_srl]);
			else $_SESSION['file_management'][$output->file_srl] = true;
		}
	}
}
