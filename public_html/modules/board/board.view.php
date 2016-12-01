<?php
class boardView extends board
	var $listConfig;
	var $columnList;

	function init() {
		$oSecurity = new Security();
		$oSecurity->encodeHTML('document_srl', 'comment_srl', 'vid', 'mid', 'page', 'category', 'search_target', 'search_keyword', 'sort_index', 'order_type', 'trackback_srl');
		if($this->module_info->list_count) $this->list_count = $this->module_info->list_count;
		if($this->module_info->search_list_count) $this->search_list_count = $this->module_info->search_list_count;
		if($this->module_info->page_count) $this->page_count = $this->module_info->page_count;
		$this->except_notice = $this->module_info->except_notice == 'N' ? FALSE : TRUE;
		$oDocumentModel = getModel('document');
		$statusList = $this->_getStatusNameList($oDocumentModel);
		if(isset($statusList['SECRET'])) $this->module_info->secret = 'Y';
		$count_category = count($oDocumentModel->getCategoryList($this->module_info->module_srl));
		if($count_category) {
			if($this->module_info->hide_category) $this->module_info->use_category = ($this->module_info->hide_category == 'Y') ? 'N' : 'Y';
			else if($this->module_info->use_category) $this->module_info->hide_category = ($this->module_info->use_category == 'Y') ? 'N' : 'Y';
			else {
				$this->module_info->hide_category = 'N';
				$this->module_info->use_category = 'Y';
			}
		} else {
			$this->module_info->hide_category = 'Y';
			$this->module_info->use_category = 'N';
		}
		if($this->module_info->consultation == 'Y' && !$this->grant->manager && !$this->grant->consultation_read) {
			$this->consultation = TRUE;
			if(!Context::get('is_logged')) {
				$this->grant->list = FALSE;
				$this->grant->write_document = FALSE;
				$this->grant->write_comment = FALSE;
				$this->grant->view = FALSE;
			}
		} else {
			$this->consultation = FALSE;
		}
		$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		if(!is_dir($template_path)||!$this->module_info->skin) {
			$this->module_info->skin = 'default';
			$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		}
		$this->setTemplatePath($template_path);
		$oDocumentModel = getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
		Context::set('extra_keys', $extra_keys);
		if (is_array($extra_keys)) foreach($extra_keys as $val) $this->order_target[] = $val->eid;
		Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
		Context::addJsFile($this->module_path.'tpl/js/board.js');
		$args = Context::getRequestVars();
		foreach($args as $name => $value) {
			if(preg_match('/[0-9]+_cpage/', $name)) {
				Context::set($name, '', TRUE);
				Context::set($name, $value);
			}
		}
	}

	function dispBoardContent() {
		if(!$this->grant->access || !$this->grant->list) return $this->dispBoardMessage('msg_not_permitted');
		$this->dispBoardCategoryList();
		foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
		$extra_keys = Context::get('extra_keys');
		if($extra_keys) {
			foreach($extra_keys as $key => $val) {
				if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
			}
		}
		$memberConfig = getModel('module')->getModuleConfig('member');
		foreach($memberConfig->signupForm as $signupFormElement) {
			if(in_array($signupFormElement->title, $search_option)) {
				if($signupFormElement->isPublic == 'N') unset($search_option[$signupFormElement->name]);
			}
		}
		Context::set('search_option', $search_option);
		$oDocumentModel = getModel('document');
		$statusNameList = $this->_getStatusNameList($oDocumentModel);
		if(count($statusNameList) > 0) Context::set('status_list', $statusNameList);
		$this->dispBoardContentView();
		$oBoardModel = getModel('board');
		$this->listConfig = $oBoardModel->getListConfig($this->module_info->module_srl);
		if(!$this->listConfig) $this->listConfig = array();
		$this->_makeListColumnList();
		$this->dispBoardNoticeList();
		$this->dispBoardContentList();
		Context::addJsFilter($this->module_path.'tpl/filter', 'search.xml');
		$oSecurity = new Security();
		$oSecurity->encodeHTML('search_option.');
		$this->setTemplateFile('list');
	}

	function dispBoardCategoryList(){
		if($this->module_info->use_category=='Y') {
			if(!$this->grant->list) {
				Context::set('category_list', array());
				return;
			}
			$oDocumentModel = getModel('document');
			Context::set('category_list', $oDocumentModel->getCategoryList($this->module_srl));
			$oSecurity = new Security();
			$oSecurity->encodeHTML('category_list.', 'category_list.childs.');
		}
	}

	function dispBoardContentView(){
		$document_srl = Context::get('document_srl');
		$page = Context::get('page');
		$oDocumentModel = getModel('document');
		if($document_srl) {
			$oDocument = $oDocumentModel->getDocument($document_srl, false, true);
			if($oDocument->isExists()) {
				if($oDocument->get('module_srl')!=$this->module_info->module_srl ) return $this->stop('msg_invalid_request');
				if($this->grant->manager) $oDocument->setGrant();
				if($this->consultation && !$oDocument->isNotice()) {
					$logged_info = Context::get('logged_info');
					if($oDocument->get('member_srl')!=$logged_info->member_srl) $oDocument = $oDocumentModel->getDocument(0);
				}
				if($oDocument->getStatus() == 'TEMP') {
					if(!$oDocument->isGranted()) $oDocument = $oDocumentModel->getDocument(0);
				}
			} else {
				Context::set('document_srl','',true);
				$this->alertMessage('msg_not_founded');
			}
		} else {
			$oDocument = $oDocumentModel->getDocument(0);
		}
		if($oDocument->isExists()) {
			if(!$this->grant->view && !$oDocument->isGranted()) {
				$oDocument = $oDocumentModel->getDocument(0);
				Context::set('document_srl','',true);
				$this->alertMessage('msg_not_permitted');
			} else {
				Context::addBrowserTitle($oDocument->getTitleText());
				if(!$oDocument->isSecret() || $oDocument->isGranted()) $oDocument->updateReadedCount();
				if($oDocument->isSecret() && !$oDocument->isGranted()) $oDocument->add('content',Context::getLang('thisissecret'));
			}
		}
		$oDocument->add('module_srl', $this->module_srl);
		Context::set('oDocument', $oDocument);
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
	}

	function dispBoardContentFileList(){
		if(!$this->grant->access) return $this->dispBoardMessage('msg_not_permitted');
		$this->dispBoardContentView();
		$oModuleModel = getModel('module');
		$file_module_config = $oModuleModel->getModulePartConfig('file',$this->module_srl);
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
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl, $columnList);
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
		$oDocumentModel = getModel('document');
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		Context::set('file_list',$oDocument->getUploadedFiles());
		$oSecurity = new Security();
		$oSecurity->encodeHTML('file_list..source_filename');
	}

	function dispBoardContentCommentList(){
		$this->dispBoardContentView();
		$oDocumentModel = getModel('document');
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		$comment_list = $oDocument->getComments();
		if(is_array($comment_list)) {
			foreach($comment_list as $key => $val) {
				if(!$val->isAccessible()) $val->add('content',Context::getLang('thisissecret'));
			}
		}
		Context::set('comment_list',$comment_list);
	}

	function dispBoardNoticeList(){
		if(!$this->grant->list) {
			Context::set('notice_list', array());
			return;
		}
		$oDocumentModel = getModel('document');
		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$notice_output = $oDocumentModel->getNoticeList($args, $this->columnList);
		Context::set('notice_list', $notice_output->data);
	}

	function dispBoardContentList(){
		if(!$this->grant->list) {
			Context::set('document_list', array());
			Context::set('total_count', 0);
			Context::set('total_page', 1);
			Context::set('page', 1);
			Context::set('page_navigation', new PageHandler(0,0,1,10));
			return;
		}
		$oDocumentModel = getModel('document');
		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = $this->list_count;
		$args->page_count = $this->page_count;
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');
		$search_option = Context::get('search_option');
		if($search_option==FALSE) $search_option = $this->search_option;
		if(isset($search_option[$args->search_target])==FALSE) $args->search_target = '';
		if($this->module_info->use_category=='Y') $args->category_srl = Context::get('category');
		$args->sort_index = Context::get('sort_index');
		$args->order_type = Context::get('order_type');
		if(!in_array($args->sort_index, $this->order_target)) $args->sort_index = $this->module_info->order_target?$this->module_info->order_target:'list_order';
		if(!in_array($args->order_type, array('asc','desc'))) $args->order_type = $this->module_info->order_type?$this->module_info->order_type:'asc';
		$document_srl = Context::get('document_srl');
		if(!$args->page && $document_srl) {
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if($oDocument->isExists() && !$oDocument->isNotice()) {
				$page = $oDocumentModel->getDocumentPage($oDocument, $args);
				Context::set('page', $page);
				$args->page = $page;
			}
		}
		if($args->category_srl || $args->search_keyword) $args->list_count = $this->search_list_count;
		if($this->consultation) {
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
		}
		Context::set('list_config', $this->listConfig);
		$output = $oDocumentModel->getDocumentList($args, $this->except_notice, TRUE, $this->columnList);
		Context::set('document_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
	}

	function _makeListColumnList() {
		$configColumList = array_keys($this->listConfig);
		$tableColumnList = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'is_notice',
				'title', 'title_bold', 'title_color', 'content', 'readed_count', 'voted_count',
				'blamed_count', 'comment_count', 'trackback_count', 'uploaded_count', 'password', 'user_id',
				'user_name', 'nick_name', 'member_srl', 'email_address', 'homepage', 'tags', 'extra_vars',
				'regdate', 'last_update', 'last_updater', 'ipaddress', 'list_order', 'update_order',
				'allow_trackback', 'notify_message', 'status', 'comment_status');
		$this->columnList = array_intersect($configColumList, $tableColumnList);
		if(in_array('summary', $configColumList)) array_push($this->columnList, 'content');
		$defaultColumn = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'member_srl', 'last_update', 'comment_count', 'trackback_count', 'uploaded_count', 'status', 'regdate', 'title_bold', 'title_color');
		if($this->module_info->skin == 'xe_guestbook' || $this->module_info->default_style == 'blog') $defaultColumn = $tableColumnList;
		if (in_array('last_post', $configColumList)) array_push($this->columnList, 'last_updater');
		if ($this->except_notice) array_push($this->columnList, 'is_notice');
		$this->columnList = array_unique(array_merge($this->columnList, $defaultColumn));
		foreach($this->columnList as $no => $value) $this->columnList[$no] = 'documents.' . $value;
	}

	function dispBoardTagList() {
		if(!$this->grant->list) return $this->dispBoardMessage('msg_not_permitted');
		$oTagModel = getModel('tag');
		$obj = new stdClass;
		$obj->mid = $this->module_info->mid;
		$obj->list_count = 10000;
		$output = $oTagModel->getTagList($obj);
		if(count($output->data)) {
			$numbers = array_keys($output->data);
			shuffle($numbers);
			if(count($output->data)) {
				foreach($numbers as $k => $v) $tag_list[] = $output->data[$v];
			}
		}
		Context::set('tag_list', $tag_list);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('tag_list.');
		$this->setTemplateFile('tag_list');
	}

	function dispBoardWrite() {
		if(!$this->grant->write_document) return $this->dispBoardMessage('msg_not_permitted');
		$oDocumentModel = getModel('document');
		if($this->module_info->use_category=='Y') {
			if(Context::get('is_logged')) {
				$logged_info = Context::get('logged_info');
				$group_srls = array_keys($logged_info->group_list);
			} else {
				$group_srls = array();
			}
			$group_srls_count = count($group_srls);
			$normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
			if(count($normal_category_list)) {
				foreach($normal_category_list as $category_srl => $category) {
					$is_granted = TRUE;
					if($category->group_srls) {
						$category_group_srls = explode(',',$category->group_srls);
						$is_granted = FALSE;
						if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = TRUE;
					}
					if($is_granted) $category_list[$category_srl] = $category;
				}
			}
			Context::set('category_list', $category_list);
		}
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
		$oDocument->setDocument($document_srl);
		if($oDocument->get('module_srl') == $oDocument->get('member_srl')) $savedDoc = TRUE;
		$oDocument->add('module_srl', $this->module_srl);
		if($oDocument->isExists() && $this->module_info->protect_content=="Y" && $oDocument->get('comment_count')>0 && $this->grant->manager==false) {
			return new Object(-1, 'msg_protect_content');
		}
		$oModuleModel = getModel('module');
		if($oDocument->isExists()&&!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');
		if(!$oDocument->isExists()) {
			$point_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
			$logged_info = Context::get('logged_info');
			$oPointModel = getModel('point');
			$pointForInsert = $point_config["insert_document"];
			if($pointForInsert < 0) {
				if( !$logged_info ) {
					return $this->dispBoardMessage('msg_not_permitted');
				} else if (($oPointModel->getPoint($logged_info->member_srl) + $pointForInsert )< 0 ) {
					return $this->dispBoardMessage('msg_not_enough_point');
				}
			}
		}
		if(!$oDocument->get('status')) $oDocument->add('status', $oDocumentModel->getDefaultStatus());
		$statusList = $this->_getStatusNameList($oDocumentModel);
		if(count($statusList) > 0) Context::set('status_list', $statusList);
		Context::set('document_srl',$document_srl);
		Context::set('oDocument', $oDocument);
		$oDocumentController = getController('document');
		$oDocumentController->addXmlJsFilter($this->module_info->module_srl);
		if($oDocument->isExists() && !$savedDoc) Context::set('extra_keys', $oDocument->getExtraVars());
		if(Context::get('logged_info')->is_admin=='Y') Context::addJsFilter($this->module_path.'tpl/filter', 'insert_admin.xml');
		else Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');
		$oSecurity = new Security();
		$oSecurity->encodeHTML('category_list.text', 'category_list.title');
		$this->setTemplateFile('write_form');
	}

	function _getStatusNameList(&$oDocumentModel) {
		$resultList = array();
		if(!empty($this->module_info->use_status)) {
			$statusNameList = $oDocumentModel->getStatusNameList();
			$statusList = explode('|@|', $this->module_info->use_status);
			if(is_array($statusList)) {
				foreach($statusList as $key => $value) $resultList[$value] = $statusNameList[$value];
			}
		}
		return $resultList;
	}

	function dispBoardDelete() {
		if(!$this->grant->write_document) return $this->dispBoardMessage('msg_not_permitted');
		$document_srl = Context::get('document_srl');
		if($document_srl) {
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
		}
		if(!$oDocument || !$oDocument->isExists()) return $this->dispBoardContent();
		if(!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');
		if($this->module_info->protect_content=="Y" && $oDocument->get('comment_count')>0 && $this->grant->manager==false) return $this->dispBoardMessage('msg_protect_content');
		Context::set('oDocument',$oDocument);
		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_document.xml');
		$this->setTemplateFile('delete_form');
	}

	function dispBoardWriteComment() {
		$document_srl = Context::get('document_srl');
		if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists()) return $this->dispBoardMessage('msg_invalid_request');
		if(!$oDocument->allowComment()) return $this->dispBoardMessage('msg_not_allow_comment');
		$oCommentModel = getModel('comment');
		$oSourceComment = $oComment = $oCommentModel->getComment(0);
		$oComment->add('document_srl', $document_srl);
		$oComment->add('module_srl', $this->module_srl);
		Context::set('oDocument',$oDocument);
		Context::set('oSourceComment',$oSourceComment);
		Context::set('oComment',$oComment);
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
		$this->setTemplateFile('comment_form');
	}

	function dispBoardReplyComment() {
		if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');
		$parent_srl = Context::get('comment_srl');
		if(!$parent_srl) return new Object(-1, 'msg_invalid_request');
		$oCommentModel = getModel('comment');
		$oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);
		if(!$oSourceComment->isExists()) return $this->dispBoardMessage('msg_invalid_request');
		if(Context::get('document_srl') && $oSourceComment->get('document_srl') != Context::get('document_srl')) return $this->dispBoardMessage('msg_invalid_request');
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($oSourceComment->get('document_srl'));
		if(!$oDocument->allowComment()) return $this->dispBoardMessage('msg_not_allow_comment');
		$oComment = $oCommentModel->getComment();
		$oComment->add('parent_srl', $parent_srl);
		$oComment->add('document_srl', $oSourceComment->get('document_srl'));
		Context::set('oSourceComment',$oSourceComment);
		Context::set('oComment',$oComment);
		Context::set('module_srl',$this->module_info->module_srl);
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
		$this->setTemplateFile('comment_form');
	}

	function dispBoardModifyComment() {
		if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');
		$document_srl = Context::get('document_srl');
		$comment_srl = Context::get('comment_srl');
		if(!$comment_srl) return new Object(-1, 'msg_invalid_request');
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
		if(!$oComment->isExists()) return $this->dispBoardMessage('msg_invalid_request');
		if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');
		Context::set('oSourceComment', $oCommentModel->getComment());
		Context::set('oComment', $oComment);
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
		$this->setTemplateFile('comment_form');
	}

	function dispBoardDeleteComment() {
		if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');
		$comment_srl = Context::get('comment_srl');
		if($comment_srl) {
			$oCommentModel = getModel('comment');
			$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
		}
		if(!$oComment->isExists() ) return $this->dispBoardContent();
		if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');
		Context::set('oComment',$oComment);
		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');
		$this->setTemplateFile('delete_comment_form');
	}

	function dispBoardDeleteTrackback() {
		$oTrackbackModel = getModel('trackback');
		if(!$oTrackbackModel) return;
		$trackback_srl = Context::get('trackback_srl');
		$columnList = array('trackback_srl');
		$output = $oTrackbackModel->getTrackback($trackback_srl, $columnList);
		$trackback = $output->data;
		if(!$trackback) return $this->dispBoardContent();
		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_trackback.xml');
		$this->setTemplateFile('delete_trackback_form');
	}

	function dispBoardMessage($msg_code) {
		$msg = Context::getLang($msg_code);
		if(!$msg) $msg = $msg_code;
		Context::set('message', $msg);
		$this->setTemplateFile('message');
	}

	function alertMessage($message) {
		$script =  sprintf('<script> jQuery(function(){ alert("%s"); } );</script>', Context::getLang($message));
		Context::addHtmlFooter( $script );
	}
}
