<?php
class fileController extends file
{
	function init() {
	}

	function procFileUpload() {
		Context::setRequestMethod('JSON');
		$file_info = $_FILES['Filedata'];
		if(!is_uploaded_file($file_info['tmp_name'])) exit();
		$oFileModel = getModel('file');
		$editor_sequence = Context::get('editor_sequence');
		$upload_target_srl = intval(Context::get('uploadTargetSrl'));
		if(!$upload_target_srl) $upload_target_srl = intval(Context::get('upload_target_srl'));
		$module_srl = $this->module_srl;
		if(!$_SESSION['upload_info'][$editor_sequence]->enabled) exit();
		if(!$upload_target_srl) $upload_target_srl = $_SESSION['upload_info'][$editor_sequence]->upload_target_srl;
		if(!$upload_target_srl) $_SESSION['upload_info'][$editor_sequence]->upload_target_srl = $upload_target_srl = getNextSequence();
		$output = $this->insertFile($file_info, $module_srl, $upload_target_srl);
		Context::setResponseMethod('JSON');
		if($output->error != '0') $this->stop($output->message);
	}

	function procFileIframeUpload() {
		$editor_sequence = Context::get('editor_sequence');
		$callback = Context::get('callback');
		$module_srl = $this->module_srl;
		$upload_target_srl = intval(Context::get('uploadTargetSrl'));
		if(!$upload_target_srl) $upload_target_srl = intval(Context::get('upload_target_srl'));
		if(!$_SESSION['upload_info'][$editor_sequence]->enabled) exit();
		if(!$upload_target_srl) $upload_target_srl = $_SESSION['upload_info'][$editor_sequence]->upload_target_srl;
		if(!$upload_target_srl) $_SESSION['upload_info'][$editor_sequence]->upload_target_srl = $upload_target_srl = getNextSequence();
		$file_srl = Context::get('file_srl');
		if($file_srl) $this->deleteFile($file_srl);
		$file_info = Context::get('Filedata');
		if(is_uploaded_file($file_info['tmp_name'])) {
			$output = $this->insertFile($file_info, $module_srl, $upload_target_srl);
			Context::set('uploaded_fileinfo',$output);
		}
		Context::set('layout','none');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('iframe');
	}

	function procFileImageResize() {
		$file_srl = Context::get('file_srl');
		$width = Context::get('width');
		$height = Context::get('height');
		if(!$file_srl || !$width) return new Object(-1,'msg_invalid_request');
		$oFileModel = getModel('file');
		$fileInfo = $oFileModel->getFile($file_srl);
		if(!$fileInfo || $fileInfo->direct_download != 'Y') return new Object(-1,'msg_invalid_request');
		$source_src = $fileInfo->uploaded_filename;
		$output_src = $source_src . '.resized' . strrchr($source_src,'.');
		if(!$height) $height = $width-1;
		if(FileHandler::createImageFile($source_src,$output_src,$width,$height,'','ratio')) {
			$output = new stdClass();
			$output->info = getimagesize($output_src);
			$output->src = $output_src;
		} else {
			return new Object(-1,'msg_invalid_request');
		}
		$this->add('resized_info',$output);
	}

	function procFileDownload() {
		$oFileModel = getModel('file');
		if(isset($this->grant->access) && $this->grant->access !== true) return new Object(-1, 'msg_not_permitted');
		$file_srl = Context::get('file_srl');
		$sid = Context::get('sid');
		$logged_info = Context::get('logged_info');
		$columnList = array('file_srl', 'sid', 'isvalid', 'source_filename', 'module_srl', 'uploaded_filename', 'file_size', 'member_srl', 'upload_target_srl', 'upload_target_type');
		$file_obj = $oFileModel->getFile($file_srl, $columnList);
		if($file_obj->file_srl!=$file_srl || $file_obj->sid!=$sid) return $this->stop('msg_file_not_found');
		if($logged_info->is_admin != 'Y' && $file_obj->isvalid!='Y') return $this->stop('msg_not_permitted_download');
		$filename = $file_obj->source_filename;
		$file_module_config = $oFileModel->getFileModuleConfig($file_obj->module_srl);
		if($file_module_config->allow_outlink == 'N') {
			if($file_module_config->allow_outlink_format) {
				$allow_outlink_format_array = array();
				$allow_outlink_format_array = explode(',', $file_module_config->allow_outlink_format);
				if(!is_array($allow_outlink_format_array)) $allow_outlink_format_array[0] = $file_module_config->allow_outlink_format;
				foreach($allow_outlink_format_array as $val) {
					$val = trim($val);
					if(preg_match("/\.{$val}$/i", $filename)) {
						$file_module_config->allow_outlink = 'Y';
						break;
					}
				}
			}
			if($file_module_config->allow_outlink != 'Y') {
				$referer = parse_url($_SERVER["HTTP_REFERER"]);
				if($referer['host'] != $_SERVER['HTTP_HOST']) {
					if($file_module_config->allow_outlink_site) {
						$allow_outlink_site_array = array();
						$allow_outlink_site_array = explode("\n", $file_module_config->allow_outlink_site);
						if(!is_array($allow_outlink_site_array)) $allow_outlink_site_array[0] = $file_module_config->allow_outlink_site;
						foreach($allow_outlink_site_array as $val) {
							$site = parse_url(trim($val));
							if($site['host'] == $referer['host']) {
								$file_module_config->allow_outlink = 'Y';
								break;
							}
						}
					}
				}
				else $file_module_config->allow_outlink = 'Y';
			}
			if($file_module_config->allow_outlink != 'Y') return $this->stop('msg_not_allowed_outlink');
		}
		$downloadGrantCount = 0;
		if(is_array($file_module_config->download_grant)) {
			foreach($file_module_config->download_grant AS $value) if($value) $downloadGrantCount++;
		}
		if(is_array($file_module_config->download_grant) && $downloadGrantCount>0) {
			if(!Context::get('is_logged')) return $this->stop('msg_not_permitted_download');
			$logged_info = Context::get('logged_info');
			if($logged_info->is_admin != 'Y') {
				$oModuleModel =& getModel('module');
				$columnList = array('module_srl', 'site_srl');
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($file_obj->module_srl, $columnList);
				if(!$oModuleModel->isSiteAdmin($logged_info, $module_info->site_srl)) {
					$oMemberModel =& getModel('member');
					$member_groups = $oMemberModel->getMemberGroups($logged_info->member_srl, $module_info->site_srl);
					$is_permitted = false;
					for($i=0;$i<count($file_module_config->download_grant);$i++) {
						$group_srl = $file_module_config->download_grant[$i];
						if($member_groups[$group_srl]) {
							$is_permitted = true;
							break;
						}
					}
					if(!$is_permitted) return $this->stop('msg_not_permitted_download');
				}
			}
		}
		$output = ModuleHandler::triggerCall('file.downloadFile', 'before', $file_obj);
		if(!$output->toBool()) return $this->stop(($output->message)?$output->message:'msg_not_permitted_download');
		$args = new stdClass();
		$args->file_srl = $file_srl;
		executeQuery('file.updateFileDownloadCount', $args);
		$output = ModuleHandler::triggerCall('file.downloadFile', 'after', $file_obj);
		$random = new Password();
		$file_key = $_SESSION['__XE_FILE_KEY__'][$file_srl] = $random->createSecureSalt(32, 'hex');
		header('Location: '.getNotEncodedUrl('', 'act', 'procFileOutput','file_srl',$file_srl,'file_key',$file_key));
		Context::close();
		exit();
	}

	public function procFileOutput() {
		$oFileModel = getModel('file');
		$file_srl = Context::get('file_srl');
		$file_key = Context::get('file_key');
		if(strstr($_SERVER['HTTP_USER_AGENT'], "Android")) $is_android = true;
		if($is_android && $_SESSION['__XE_FILE_KEY_AND__'][$file_srl]) $session_key = '__XE_FILE_KEY_AND__';
		else $session_key = '__XE_FILE_KEY__';
		$columnList = array('source_filename', 'uploaded_filename', 'file_size');
		$file_obj = $oFileModel->getFile($file_srl, $columnList);
		$uploaded_filename = $file_obj->uploaded_filename;
		if(!file_exists($uploaded_filename)) return $this->stop('msg_file_not_found');
		if(!$file_key || $_SESSION[$session_key][$file_srl] != $file_key) {
			unset($_SESSION[$session_key][$file_srl]);
			return $this->stop('msg_invalid_request');
		}
		$file_size = $file_obj->file_size;
		$filename = $file_obj->source_filename;
		if(preg_match('#(?:Chrome|Edge)/(\d+)\.#', $_SERVER['HTTP_USER_AGENT'], $matches) && $matches[1] >= 11) {
			if($is_android && preg_match('#\bwv\b|(?:Version|Browser)/\d+#', $_SERVER['HTTP_USER_AGENT'])) $filename_param = 'filename="' . $filename . '"';
			else $filename_param = "filename*=UTF-8''" . rawurlencode($filename) . '; filename="' . rawurlencode($filename) . '"';
		} elseif(preg_match('#(?:Firefox|Safari|Trident)/(\d+)\.#', $_SERVER['HTTP_USER_AGENT'], $matches) && $matches[1] >= 6) {
			$filename_param = "filename*=UTF-8''" . rawurlencode($filename) . '; filename="' . rawurlencode($filename) . '"';
		} elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE) {
			$filename = rawurlencode($filename);
			$filename_param = 'filename="' . preg_replace('/\./', '%2e', $filename, substr_count($filename, '.') - 1) . '"';
		} else {
			$filename_param = 'filename="' . $filename . '"';
		}
		if($is_android) if($_SESSION['__XE_FILE_KEY__'][$file_srl]) $_SESSION['__XE_FILE_KEY_AND__'][$file_srl] = $file_key;
		unset($_SESSION[$session_key][$file_srl]);
		Context::close();
		$fp = fopen($uploaded_filename, 'rb');
		if(!$fp) return $this->stop('msg_file_not_found');
		header("Cache-Control: ");
		header("Pragma: ");
		header("Content-Type: application/octet-stream");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Content-Length: " .(string)($file_size));
		header('Content-Disposition: attachment; ' . $filename_param);
		header("Content-Transfer-Encoding: binary\n");
		if(filesize($uploaded_filename) > 1024 * 1024) {
			while(!feof($fp)) echo fread($fp, 1024);
			fclose($fp);
		} else {
			fpassthru($fp);
		}
		exit();
	}

	function procFileDelete() {
		$editor_sequence = Context::get('editor_sequence');
		$file_srl = Context::get('file_srl');
		$file_srls = Context::get('file_srls');
		if($file_srls) $file_srl = $file_srls;
		if(!$_SESSION['upload_info'][$editor_sequence]->enabled) exit();
		$upload_target_srl = $_SESSION['upload_info'][$editor_sequence]->upload_target_srl;
		$logged_info = Context::get('logged_info');
		$oFileModel = getModel('file');
		$srls = explode(',',$file_srl);
		if(!count($srls)) return;
		for($i=0;$i<count($srls);$i++) {
			$srl = (int)$srls[$i];
			if(!$srl) continue;
			$args = new stdClass;
			$args->file_srl = $srl;
			$output = executeQuery('file.getFile', $args);
			if(!$output->toBool()) continue;
			$file_info = $output->data;
			if(!$file_info) continue;
			$file_grant = $oFileModel->getFileGrant($file_info, $logged_info);
			if(!$file_grant->is_deletable) continue;
			if($upload_target_srl && $file_srl) $output = $this->deleteFile($file_srl);
		}
	}

	function procFileGetList() {
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');
		$fileSrls = Context::get('file_srls');
		if($fileSrls) $fileSrlList = explode(',', $fileSrls);
		global $lang;
		if(count($fileSrlList) > 0) {
			$oFileModel = getModel('file');
			$fileList = $oFileModel->getFile($fileSrlList);
			if(!is_array($fileList)) $fileList = array($fileList);
			if(is_array($fileList)) {
				foreach($fileList AS $key=>$value) {
					$value->human_file_size = FileHandler::filesize($value->file_size);
					if($value->isvalid=='Y') $value->validName = $lang->is_valid;
					else $value->validName = $lang->is_stand_by;
				}
			}
		} else {
			$fileList = array();
			$this->setMessage($lang->no_files);
		}
		$this->add('file_list', $fileList);
	}

	function triggerCheckAttached(&$obj) {
		$document_srl = $obj->document_srl;
		if(!$document_srl) return new Object();
		$oFileModel = getModel('file');
		$obj->uploaded_count = $oFileModel->getFilesCount($document_srl);
		return new Object();
	}

	function triggerAttachFiles(&$obj) {
		$document_srl = $obj->document_srl;
		if(!$document_srl) return new Object();
		$output = $this->setFilesValid($document_srl);
		if(!$output->toBool()) return $output;
		return new Object();
	}

	function triggerDeleteAttached(&$obj) {
		$document_srl = $obj->document_srl;
		if(!$document_srl) return new Object();
		$output = $this->deleteFiles($document_srl);
		return $output;
	}

	function triggerCommentCheckAttached(&$obj) {
		$comment_srl = $obj->comment_srl;
		if(!$comment_srl) return new Object();
		$oFileModel = getModel('file');
		$obj->uploaded_count = $oFileModel->getFilesCount($comment_srl);
		return new Object();
	}

	function triggerCommentAttachFiles(&$obj) {
		$comment_srl = $obj->comment_srl;
		$uploaded_count = $obj->uploaded_count;
		if(!$comment_srl || !$uploaded_count) return new Object();
		$output = $this->setFilesValid($comment_srl);
		if(!$output->toBool()) return $output;
		return new Object();
	}

	function triggerCommentDeleteAttached(&$obj) {
		$comment_srl = $obj->comment_srl;
		if(!$comment_srl) return new Object();
		if($obj->isMoveToTrash) return new Object();
		$output = $this->deleteFiles($comment_srl);
		return $output;
	}

	function triggerDeleteModuleFiles(&$obj) {
		$module_srl = $obj->module_srl;
		if(!$module_srl) return new Object();
		$oFileController = getAdminController('file');
		return $oFileController->deleteModuleFiles($module_srl);
	}

	function setUploadInfo($editor_sequence, $upload_target_srl=0) {
		if(!isset($_SESSION['upload_info'][$editor_sequence])) $_SESSION['upload_info'][$editor_sequence] = new stdClass();
		$_SESSION['upload_info'][$editor_sequence]->enabled = true;
		$_SESSION['upload_info'][$editor_sequence]->upload_target_srl = $upload_target_srl;
	}

	function setFilesValid($upload_target_srl) {
		$args = new stdClass();
		$args->upload_target_srl = $upload_target_srl;
		return executeQuery('file.updateFileValid', $args);
	}

	function insertFile($file_info, $module_srl, $upload_target_srl, $download_count = 0, $manual_insert = false) {
		$trigger_obj = new stdClass;
		$trigger_obj->module_srl = $module_srl;
		$trigger_obj->upload_target_srl = $upload_target_srl;
		$output = ModuleHandler::triggerCall('file.insertFile', 'before', $trigger_obj);
		if(!$output->toBool()) return $output;
		if(preg_match('/^=\?UTF-8\?B\?(.+)\?=$/i', $file_info['name'], $match)) $file_info['name'] = base64_decode(strtr($match[1], ':', '/'));
		if(!$manual_insert) {
			$logged_info = Context::get('logged_info');
			if($logged_info->is_admin != 'Y') {
				$oFileModel = getModel('file');
				$config = $oFileModel->getFileConfig($module_srl);
				if(isset($config->allowed_filetypes) && $config->allowed_filetypes !== '*.*') {
					$filetypes = explode(';', $config->allowed_filetypes);
					$ext = array();
					foreach($filetypes as $item) {
						$item = explode('.', $item);
						$ext[] = strtolower($item[1]);
					}
					$uploaded_ext = explode('.', $file_info['name']);
					$uploaded_ext = strtolower(array_pop($uploaded_ext));
					if(!in_array($uploaded_ext, $ext)) return $this->stop('msg_not_allowed_filetype');
				}
				$allowed_filesize = $config->allowed_filesize * 1024 * 1024;
				$allowed_attach_size = $config->allowed_attach_size * 1024 * 1024;
				if($allowed_filesize < filesize($file_info['tmp_name'])) return new Object(-1, 'msg_exceeds_limit_size');
				$size_args = new stdClass;
				$size_args->upload_target_srl = $upload_target_srl;
				$output = executeQuery('file.getAttachedFileSize', $size_args);
				$attached_size = (int)$output->data->attached_size + filesize($file_info['tmp_name']);
				if($attached_size > $allowed_attach_size) return new Object(-1, 'msg_exceeds_limit_size');
			}
		}
		$file_info['name'] = preg_replace('/\.(php|phtm|phar|html?|cgi|pl|exe|jsp|asp|inc)/i', '$0-x',$file_info['name']);
		$file_info['name'] = removeHackTag($file_info['name']);
		$file_info['name'] = str_replace(array('<','>'),array('%3C','%3E'),$file_info['name']);
		$random = new Password();
		if(preg_match("/\.(jpe?g|gif|png|wm[va]|mpe?g|avi|swf|flv|mp[1-4]|as[fx]|wav|midi?|moo?v|qt|r[am]{1,2}|m4v)$/i", $file_info['name'])) {
			$path = sprintf("./files/attach/images/%s/%s", $module_srl,getNumberingPath($upload_target_srl,3));
			$ext = substr(strrchr($file_info['name'],'.'),1);
			$_filename = $random->createSecureSalt(32, 'hex').'.'.$ext;
			$filename  = $path.$_filename;
			$idx = 1;
			while(file_exists($filename)) {
				$filename = $path.preg_replace('/\.([a-z0-9]+)$/i','_'.$idx.'.$1',$_filename);
				$idx++;
			}
			$direct_download = 'Y';
		} else {
			$path = sprintf("./files/attach/binaries/%s/%s", $module_srl, getNumberingPath($upload_target_srl,3));
			$filename = $path.$random->createSecureSalt(32, 'hex');
			$direct_download = 'N';
		}
		if(!FileHandler::makeDir($path)) return new Object(-1,'msg_not_permitted_create');
		if(!checkUploadedFile($file_info['tmp_name']))  return new Object(-1,'msg_file_upload_error');
		$random = new Password();
		if($manual_insert) {
			@copy($file_info['tmp_name'], $filename);
			if(!file_exists($filename)) {
				$filename = $path.$random->createSecureSalt(32, 'hex').'.'.$ext;
				@copy($file_info['tmp_name'], $filename);
			}
		} else {
			if(!@move_uploaded_file($file_info['tmp_name'], $filename)) {
				$filename = $path.$random->createSecureSalt(32, 'hex').'.'.$ext;
				if(!@move_uploaded_file($file_info['tmp_name'], $filename))  return new Object(-1,'msg_file_upload_error');
			}
		}
		$oMemberModel = getModel('member');
		$member_srl = $oMemberModel->getLoggedMemberSrl();
		$args = new stdClass;
		$args->file_srl = getNextSequence();
		$args->upload_target_srl = $upload_target_srl;
		$args->module_srl = $module_srl;
		$args->direct_download = $direct_download;
		$args->source_filename = $file_info['name'];
		$args->uploaded_filename = $filename;
		$args->download_count = $download_count;
		$args->file_size = @filesize($filename);
		$args->comment = NULL;
		$args->member_srl = $member_srl;
		$args->sid = $random->createSecureSalt(32, 'hex');
		$output = executeQuery('file.insertFile', $args);
		if(!$output->toBool()) return $output;
		$trigger_output = ModuleHandler::triggerCall('file.insertFile', 'after', $args);
		if(!$trigger_output->toBool()) return $trigger_output;
		$_SESSION['__XE_UPLOADING_FILES_INFO__'][$args->file_srl] = true;
		$output->add('file_srl', $args->file_srl);
		$output->add('file_size', $args->file_size);
		$output->add('sid', $args->sid);
		$output->add('direct_download', $args->direct_download);
		$output->add('source_filename', $args->source_filename);
		$output->add('upload_target_srl', $upload_target_srl);
		$output->add('uploaded_filename', $args->uploaded_filename);
		return $output;
	}

	function deleteFile($file_srl) {
		if(!$file_srl) return;
		$srls = (is_array($file_srl)) ? $file_srl : explode(',', $file_srl);
		if(!count($srls)) return;
		$oDocumentController = getController('document');
		$documentSrlList = array();
		foreach($srls as $srl) {
			$srl = (int)$srl;
			if(!$srl)  continue;
			$args = new stdClass();
			$args->file_srl = $srl;
			$output = executeQuery('file.getFile', $args);
			if(!$output->toBool() || !$output->data)  continue;
			$file_info = $output->data;
			if($file_info->upload_target_srl) $documentSrlList[] = $file_info->upload_target_srl;
			$source_filename = $output->data->source_filename;
			$uploaded_filename = $output->data->uploaded_filename;
			$trigger_obj = $output->data;
			$output = ModuleHandler::triggerCall('file.deleteFile', 'before', $trigger_obj);
			if(!$output->toBool()) return $output;
			$output = executeQuery('file.deleteFile', $args);
			if(!$output->toBool()) return $output;
			$trigger_output = ModuleHandler::triggerCall('file.deleteFile', 'after', $trigger_obj);
			if(!$trigger_output->toBool()) return $trigger_output;
			FileHandler::removeFile($uploaded_filename);
		}
		$oDocumentController->updateUploaedCount($documentSrlList);
		return $output;
	}

	function deleteFiles($upload_target_srl) {
		$oFileModel = getModel('file');
		$columnList = array('file_srl', 'uploaded_filename', 'module_srl');
		$file_list = $oFileModel->getFiles($upload_target_srl, $columnList);
		if(!is_array($file_list)||!count($file_list)) return new Object();
		$path = array();
		$file_count = count($file_list);
		for($i=0;$i<$file_count;$i++) {
			$this->deleteFile($file_list[$i]->file_srl);
			$uploaded_filename = $file_list[$i]->uploaded_filename;
			$path_info = pathinfo($uploaded_filename);
			if(!in_array($path_info['dirname'], $path)) $path[] = $path_info['dirname'];
		}
		$args = new stdClass();
		$args->upload_target_srl = $upload_target_srl;
		$output = executeQuery('file.deleteFiles', $args);
		if(!$output->toBool()) return $output;
		for($i=0, $c=count($path); $i<$c; $i++) FileHandler::removeBlankDir($path[$i]);
		return $output;
	}

	function moveFile($source_srl, $target_module_srl, $target_srl) {
		if($source_srl == $target_srl) return;
		$oFileModel = getModel('file');
		$file_list = $oFileModel->getFiles($source_srl);
		if(!$file_list) return;
		$file_count = count($file_list);
		for($i=0;$i<$file_count;$i++) {
			unset($file_info);
			$file_info = $file_list[$i];
			$old_file = $file_info->uploaded_filename;
			if(preg_match("/\.(jpg|jpeg|gif|png|wmv|wma|mpg|mpeg|avi|swf|flv|mp1|mp2|mp3|mp4|asf|wav|asx|mid|midi|asf|mov|moov|qt|rm|ram|ra|rmm|m4v)$/i", $file_info->source_filename)) {
				$path = sprintf("./files/attach/images/%s/%s/", $target_module_srl,$target_srl);
				$new_file = $path.$file_info->source_filename;
			} else {
				$path = sprintf("./files/attach/binaries/%s/%s/", $target_module_srl, $target_srl);
				$random = new Password();
				$new_file = $path.$random->createSecureSalt(32, 'hex');
			}
			if($old_file == $new_file) continue;
			FileHandler::makeDir($path);
			FileHandler::rename($old_file, $new_file);
			$args = new stdClass;
			$args->file_srl = $file_info->file_srl;
			$args->uploaded_filename = $new_file;
			$args->module_srl = $file_info->module_srl;
			$args->upload_target_srl = $target_srl;
			executeQuery('file.updateFile', $args);
		}
	}

	public function procFileSetCoverImage() {
		$vars = Context::getRequestVars();
		$logged_info = Context::get('logged_info');
		if(!$vars->editor_sequence) return new Object(-1, 'msg_invalid_request');
		$upload_target_srl = $_SESSION['upload_info'][$vars->editor_sequence]->upload_target_srl;
		$oFileModel = getModel('file');
		$file_info = $oFileModel->getFile($vars->file_srl);
		if(!$file_info) return new Object(-1, 'msg_not_founded');
		if(!$this->manager && !$file_info->member_srl === $logged_info->member_srl) return new Object(-1, 'msg_not_permitted');
		$args =  new stdClass();
		$args->file_srl = $vars->file_srl;
		$args->upload_target_srl = $upload_target_srl;
		$oDB = &DB::getInstance();
		$oDB->begin();
		$args->cover_image = 'N';
		$output = executeQuery('file.updateClearCoverImage', $args);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$args->cover_image = 'Y';
		$output = executeQuery('file.updateCoverImage', $args);
		if(!$output->toBool()) {
			$oDB->rollback();
			return $output;
		}
		$oDB->commit();
		$thumbnail_path = sprintf('files/thumbnails/%s', getNumberingPath($upload_target_srl, 3));
		Filehandler::removeFilesInDir($thumbnail_path);
	}

	function printUploadedFileList($editor_sequence, $upload_target_srl) {
		return;
	}

	function triggerCopyModule(&$obj) {
		$oModuleModel = getModel('module');
		$fileConfig = $oModuleModel->getModulePartConfig('file', $obj->originModuleSrl);
		$oModuleController = getController('module');
		if(is_array($obj->moduleSrlList)) {
			foreach($obj->moduleSrlList AS $key=>$moduleSrl) $oModuleController->insertModulePartConfig('file', $moduleSrl, $fileConfig);
		}
	}
}

