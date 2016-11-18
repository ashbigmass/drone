<?php
class documentItem extends Object {
	var $document_srl = 0;
	var $lang_code = null;
	var $allow_trackback_status = null;
	var $columnList = array();
	var $allowscriptaccessList = array();
	var $allowscriptaccessKey = 0;
	var $uploadedFiles = array();

	function documentItem($document_srl = 0, $load_extra_vars = true, $columnList = array()) {
		$this->document_srl = $document_srl;
		$this->columnList = $columnList;
		$this->_loadFromDB($load_extra_vars);
	}

	function setDocument($document_srl, $load_extra_vars = true) {
		$this->document_srl = $document_srl;
		$this->_loadFromDB($load_extra_vars);
	}

	function _loadFromDB($load_extra_vars = true) {
		if(!$this->document_srl) return;
		$document_item = false;
		$cache_put = false;
		$columnList = array();
		$this->columnList = array();
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'document_item:' . getNumberingPath($this->document_srl) . $this->document_srl;
			$document_item = $oCacheHandler->get($cache_key);
			if($document_item !== false) $columnList = array('readed_count', 'voted_count', 'blamed_count', 'comment_count', 'trackback_count');
		}
		$args = new stdClass();
		$args->document_srl = $this->document_srl;
		$output = executeQuery('document.getDocument', $args, $columnList);
		if($document_item === false) {
			$document_item = $output->data;
			if($document_item && $oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $document_item);
		} else {
			$document_item->readed_count = $output->data->readed_count;
			$document_item->voted_count = $output->data->voted_count;
			$document_item->blamed_count = $output->data->blamed_count;
			$document_item->comment_count = $output->data->comment_count;
			$document_item->trackback_count = $output->data->trackback_count;
		}
		$this->setAttribute($document_item, $load_extra_vars);
	}

	function setAttribute($attribute, $load_extra_vars=true) {
		if(!$attribute->document_srl) {
			$this->document_srl = null;
			return;
		}
		$this->document_srl = $attribute->document_srl;
		$this->lang_code = $attribute->lang_code;
		$this->adds($attribute);
		if($this->get('tags')) {
			$tag_list = explode(',', $this->get('tags'));
			$tag_list = array_map('trim', $tag_list);
			$this->add('tag_list', $tag_list);
		}
		$oDocumentModel = getModel('document');
		if($load_extra_vars) {
			$GLOBALS['XE_DOCUMENT_LIST'][$attribute->document_srl] = $this;
			$oDocumentModel->setToAllDocumentExtraVars();
		}
		$GLOBALS['XE_DOCUMENT_LIST'][$this->document_srl] = $this;
	}

	function isExists() {
		return $this->document_srl ? true : false;
	}

	function isGranted() {
		if($_SESSION['own_document'][$this->document_srl]) return true;
		if(!Context::get('is_logged')) return false;
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;
		$oModuleModel = getModel('module');
		$grant = $oModuleModel->getGrant($oModuleModel->getModuleInfoByModuleSrl($this->get('module_srl')), $logged_info);
		if($grant->manager) return true;
		if($this->get('member_srl') && ($this->get('member_srl') == $logged_info->member_srl || $this->get('member_srl')*-1 == $logged_info->member_srl)) return true;
		return false;
	}

	function setGrant() {
		$_SESSION['own_document'][$this->document_srl] = true;
	}

	function isAccessible() {
		return $_SESSION['accessible'][$this->document_srl]==true?true:false;
	}

	function allowComment() {
		if(!$this->isExists()) return true;
		return $this->get('comment_status') == 'ALLOW' ? true : false;
	}

	function allowTrackback() {
		static $allow_trackback_status = null;
		if(is_null($allow_trackback_status)) {
			if(!getClass('trackback')) {
				$allow_trackback_status = false;
			} else {
				$oModuleModel = getModel('module');
				$trackback_config = $oModuleModel->getModuleConfig('trackback');
				if(!$trackback_config) $trackback_config = new stdClass();
				if(!isset($trackback_config->enable_trackback)) $trackback_config->enable_trackback = 'Y';
				if($trackback_config->enable_trackback != 'Y') $allow_trackback_status = false;
				else {
					$module_srl = $this->get('module_srl');
					// Check settings of each module
					$module_config = $oModuleModel->getModulePartConfig('trackback', $module_srl);
					if($module_config->enable_trackback == 'N') $allow_trackback_status = false;
					else if($this->get('allow_trackback')=='Y' || !$this->isExists()) $allow_trackback_status = true;
				}
			}
		}
		return $allow_trackback_status;
	}

	function isLocked() {
		if(!$this->isExists()) return false;
		return $this->get('comment_status') == 'ALLOW' ? false : true;
	}

	function isEditable() {
		if($this->isGranted() || !$this->get('member_srl')) return true;
		return false;
	}

	function isSecret() {
		$oDocumentModel = getModel('document');
		return $this->get('status') == $oDocumentModel->getConfigStatus('secret') ? true : false;
	}

	function isNotice() {
		return $this->get('is_notice') == 'Y' ? true : false;
	}

	function useNotify() {
		return $this->get('notify_message')=='Y' ? true : false;
	}

	function doCart() {
		if(!$this->document_srl) return false;
		if($this->isCarted()) $this->removeCart();
		else $this->addCart();
	}

	function addCart() {
		$_SESSION['document_management'][$this->document_srl] = true;
	}

	function removeCart() {
		unset($_SESSION['document_management'][$this->document_srl]);
	}

	function isCarted() {
		return $_SESSION['document_management'][$this->document_srl];
	}

	function notify($type, $content) {
		if(!$this->document_srl) return;
		if(!$this->useNotify()) return;
		if(!$this->get('member_srl')) return;
		$logged_info = Context::get('logged_info');
		if($logged_info->member_srl == $this->get('member_srl')) return;
		if($type) $title = "[".$type."] ";
		$title .= cut_str(strip_tags($content), 10, '...');
		$content = sprintf('%s<br /><br />from : <a href="%s" target="_blank">%s</a>',$content, getFullUrl('','document_srl',$this->document_srl), getFullUrl('','document_srl',$this->document_srl));
		$receiver_srl = $this->get('member_srl');
		$sender_member_srl = $logged_info->member_srl;
		$oCommunicationController = getController('communication');
		$oCommunicationController->sendMessage($sender_member_srl, $receiver_srl, $title, $content, false);
	}

	function getLangCode() {
		return $this->get('lang_code');
	}

	function getIpAddress() {
		if($this->isGranted()) return $this->get('ipaddress');
		return '*' . strstr($this->get('ipaddress'), '.');
	}

	function isExistsHomepage() {
		if(trim($this->get('homepage'))) return true;
		return false;
	}

	function getHomepageUrl() {
		$url = trim($this->get('homepage'));
		if(!$url) return;
		if(strncasecmp('http://', $url, 7) !== 0 && strncasecmp('https://', $url, 8) !== 0)  $url = 'http://' . $url;
		return $url;
	}

	function getMemberSrl() {
		return $this->get('member_srl');
	}

	function getUserID() {
		return htmlspecialchars($this->get('user_id'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getUserName() {
		return htmlspecialchars($this->get('user_name'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getNickName() {
		return htmlspecialchars($this->get('nick_name'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getLastUpdater() {
		return htmlspecialchars($this->get('last_updater'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getTitleText($cut_size = 0, $tail='...') {
		if(!$this->document_srl) return;
		if($cut_size) $title = cut_str($this->get('title'), $cut_size, $tail);
		else $title = $this->get('title');
		return $title;
	}

	function getTitle($cut_size = 0, $tail='...') {
		if(!$this->document_srl) return;
		$title = $this->getTitleText($cut_size, $tail);
		$attrs = array();
		$this->add('title_color', trim($this->get('title_color')));
		if($this->get('title_bold')=='Y') $attrs[] = "font-weight:bold;";
		if($this->get('title_color') && $this->get('title_color') != 'N') $attrs[] = "color:#".$this->get('title_color');
		if(count($attrs)) return sprintf("<span style=\"%s\">%s</span>", implode(';',$attrs), htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
		else return htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getContentText($strlen = 0) {
		if(!$this->document_srl) return;
		if($this->isSecret() && !$this->isGranted() && !$this->isAccessible()) return Context::getLang('msg_is_secret');
		$result = $this->_checkAccessibleFromStatus();
		if($result) $_SESSION['accessible'][$this->document_srl] = true;
		$content = $this->get('content');
		$content = preg_replace_callback('/<(object|param|embed)[^>]*/is', array($this, '_checkAllowScriptAccess'), $content);
		$content = preg_replace_callback('/<object[^>]*>/is', array($this, '_addAllowScriptAccess'), $content);
		if($strlen) return cut_str(strip_tags($content),$strlen,'...');
		return htmlspecialchars($content);
	}

	function _addAllowScriptAccess($m) {
		if($this->allowscriptaccessList[$this->allowscriptaccessKey] == 1) $m[0] = $m[0].'<param name="allowscriptaccess" value="never"></param>';
		$this->allowscriptaccessKey++;
		return $m[0];
	}

	function _checkAllowScriptAccess($m) {
		if($m[1] == 'object') $this->allowscriptaccessList[] = 1;
		if($m[1] == 'param') {
			if(stripos($m[0], 'allowscriptaccess')) {
				$m[0] = '<param name="allowscriptaccess" value="never"';
				if(substr($m[0], -1) == '/') $m[0] .= '/';
				$this->allowscriptaccessList[count($this->allowscriptaccessList)-1]--;
			}
		} else if($m[1] == 'embed') {
			if(stripos($m[0], 'allowscriptaccess')) {
				$m[0] = preg_replace('/always|samedomain/i', 'never', $m[0]);
			} else {
				$m[0] = preg_replace('/\<embed/i', '<embed allowscriptaccess="never"', $m[0]);
			}
		}
		return $m[0];
	}

	function getContent($add_popup_menu = true, $add_content_info = true, $resource_realpath = false, $add_xe_content_class = true, $stripEmbedTagException = false) {
		if(!$this->document_srl) return;
		if($this->isSecret() && !$this->isGranted() && !$this->isAccessible()) return Context::getLang('msg_is_secret');
		$result = $this->_checkAccessibleFromStatus();
		if($result) $_SESSION['accessible'][$this->document_srl] = true;
		$content = $this->get('content');
		if(!$stripEmbedTagException) stripEmbedTagForAdmin($content, $this->get('member_srl'));
		$oContext = &Context::getInstance();
		if($oContext->allow_rewrite) {
			$content = preg_replace('/<a([ \t]+)href=("|\')\.\/\?/i',"<a href=\\2". Context::getRequestUri() ."?", $content);
		}
		if($add_popup_menu) {
			$content = sprintf(
				'%s<div class="document_popup_menu"><a href="#popup_menu_area" class="document_%d" onclick="return false">%s</a></div>',
				$content,
				$this->document_srl, Context::getLang('cmd_document_do')
			);
		}
		if($add_content_info) {
			$memberSrl = $this->get('member_srl');
			if($memberSrl < 0) $memberSrl = 0;
			$content = sprintf(
				'<!--BeforeDocument(%d,%d)--><div class="document_%d_%d xe_content">%s</div><!--AfterDocument(%d,%d)-->',
				$this->document_srl, $memberSrl,
				$this->document_srl, $memberSrl,
				$content,
				$this->document_srl, $memberSrl,
				$this->document_srl, $memberSrl
			);
		} else {
			if($add_xe_content_class) $content = sprintf('<div class="xe_content">%s</div>', $content);
		}
		if($resource_realpath) {
			$content = preg_replace_callback('/<img([^>]+)>/i',array($this,'replaceResourceRealPath'), $content);
		}
		return $content;
	}

	function getTransContent($add_popup_menu = true, $add_content_info = true, $resource_realpath = false, $add_xe_content_class = true) {
		$oEditorController = getController('editor');
		$content = $this->getContent($add_popup_menu, $add_content_info, $resource_realpath, $add_xe_content_class);
		$content = $oEditorController->transComponent($content);
		return $content;
	}

	function getSummary($str_size = 50, $tail = '...') {
		$content = $this->getContent(FALSE, FALSE);
		$content = nl2br($content);
		$content = preg_replace('!(<br[\s]*/{0,1}>[\s]*)+!is', ' ', $content);
		$content = str_replace(array('</p>', '</div>', '</li>', '-->'), ' ', $content);
		$content = preg_replace('!<([^>]*?)>!is', '', $content);
		$content = str_replace(array('&lt;', '&gt;', '&quot;', '&nbsp;'), array('<', '>', '"', ' '), $content);
		$content = preg_replace('/ ( +)/is', ' ', $content);
		$content = trim(cut_str($content, $str_size, $tail));
		$content = str_replace(array('<', '>', '"'),array('&lt;', '&gt;', '&quot;'), $content);
		return $content;
	}

	function getRegdate($format = 'Y.m.d H:i:s') {
		return zdate($this->get('regdate'), $format);
	}

	function getRegdateTime() {
		$regdate = $this->get('regdate');
		$year = substr($regdate,0,4);
		$month = substr($regdate,4,2);
		$day = substr($regdate,6,2);
		$hour = substr($regdate,8,2);
		$min = substr($regdate,10,2);
		$sec = substr($regdate,12,2);
		return mktime($hour,$min,$sec,$month,$day,$year);
	}

	function getRegdateGM() {
		return $this->getRegdate('D, d M Y H:i:s').' '.$GLOBALS['_time_zone'];
	}

	function getRegdateDT() {
		return $this->getRegdate('Y-m-d').'T'.$this->getRegdate('H:i:s').substr($GLOBALS['_time_zone'],0,3).':'.substr($GLOBALS['_time_zone'],3,2);
	}

	function getUpdate($format = 'Y.m.d H:i:s') {
		return zdate($this->get('last_update'), $format);
	}

	function getUpdateTime() {
		$year = substr($this->get('last_update'),0,4);
		$month = substr($this->get('last_update'),4,2);
		$day = substr($this->get('last_update'),6,2);
		$hour = substr($this->get('last_update'),8,2);
		$min = substr($this->get('last_update'),10,2);
		$sec = substr($this->get('last_update'),12,2);
		return mktime($hour,$min,$sec,$month,$day,$year);
	}

	function getUpdateGM() {
		return gmdate("D, d M Y H:i:s", $this->getUpdateTime());
	}

	function getUpdateDT() {
		return $this->getUpdate('Y-m-d').'T'.$this->getUpdate('H:i:s').substr($GLOBALS['_time_zone'],0,3).':'.substr($GLOBALS['_time_zone'],3,2);
	}

	function getPermanentUrl() {
		return getFullUrl('','document_srl',$this->get('document_srl'));
	}

	function getTrackbackUrl() {
		if(!$this->document_srl) return;
		$oTrackbackModel = getModel('trackback');
		if($oTrackbackModel) return $oTrackbackModel->getTrackbackUrl($this->document_srl, $this->getDocumentMid());
	}

	function updateReadedCount() {
		$oDocumentController = getController('document');
		if($oDocumentController->updateReadedCount($this)) {
			$readed_count = $this->get('readed_count');
			$this->add('readed_count', $readed_count+1);
		}
	}

	function isExtraVarsExists() {
		if(!$this->get('module_srl')) return false;
		$oDocumentModel = getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($this->get('module_srl'));
		return count($extra_keys)?true:false;
	}

	function getExtraVars() {
		if(!$this->get('module_srl') || !$this->document_srl) return null;
		$oDocumentModel = getModel('document');
		return $oDocumentModel->getExtraVars($this->get('module_srl'), $this->document_srl);
	}

	function getExtraValue($idx) {
		$extra_vars = $this->getExtraVars();
		if(is_array($extra_vars) && array_key_exists($idx,$extra_vars)) {
			return $extra_vars[$idx]->getValue();
		} else {
			return '';
		}
	}

	function getExtraValueHTML($idx) {
		$extra_vars = $this->getExtraVars();
		if(is_array($extra_vars) && array_key_exists($idx,$extra_vars)) {
			return $extra_vars[$idx]->getValueHTML();
		} else {
			return '';
		}
	}

	function getExtraEidValue($eid) {
		$extra_vars = $this->getExtraVars();
		if($extra_vars) {
			foreach($extra_vars as $idx => $key) $extra_eid[$key->eid] = $key;
		}
		if(is_array($extra_eid) && array_key_exists($eid,$extra_eid)) {
			return $extra_eid[$eid]->getValue();
		} else {
			return '';
		}
	}

	function getExtraEidValueHTML($eid) {
		$extra_vars = $this->getExtraVars();
		foreach($extra_vars as $idx => $key) $extra_eid[$key->eid] = $key;
		if(is_array($extra_eid) && array_key_exists($eid,$extra_eid)) {
			return $extra_eid[$eid]->getValueHTML();
		} else {
			return '';
		}
	}

	function getExtraVarsValue($key) {
		$extra_vals = unserialize($this->get('extra_vars'));
		$val = $extra_vals->$key;
		return $val;
	}

	function getCommentCount() {
		return $this->get('comment_count');
	}

	function getComments() {
		if(!$this->getCommentCount()) return;
		if(!$this->isGranted() && $this->isSecret()) return;
		$cpageStr = sprintf('%d_cpage', $this->document_srl);
		$cpage = Context::get($cpageStr);
		if(!$cpage) $cpage = Context::get('cpage');
		$oCommentModel = getModel('comment');
		$output = $oCommentModel->getCommentList($this->document_srl, $cpage, $is_admin);
		if(!$output->toBool() || !count($output->data)) return;
		$accessible = array();
		$comment_list = array();
		foreach($output->data as $key => $val) {
			$oCommentItem = new commentItem();
			$oCommentItem->setAttribute($val);
			if($oCommentItem->isGranted()) $accessible[$val->comment_srl] = true;
			if($val->parent_srl>0 && $val->is_secret == 'Y' && !$oCommentItem->isAccessible() && $accessible[$val->parent_srl]===true) $oCommentItem->setAccessible();
			$comment_list[$val->comment_srl] = $oCommentItem;
		}
		Context::set($cpageStr, $output->page_navigation->cur_page);
		Context::set('cpage', $output->page_navigation->cur_page);
		if($output->total_page>1) $this->comment_page_navigation = $output->page_navigation;
		return $comment_list;
	}

	function getTrackbackCount() {
		return $this->get('trackback_count');
	}

	function getTrackbacks() {
		if(!$this->document_srl) return;
		if(!$this->allowTrackback() || !$this->get('trackback_count')) return;
		$oTrackbackModel = getModel('trackback');
		return $oTrackbackModel->getTrackbackList($this->document_srl, $is_admin);
	}

	function thumbnailExists($width = 80, $height = 0, $type = '') {
		if(!$this->document_srl) return false;
		if(!$this->getThumbnail($width, $height, $type)) return false;
		return true;
	}

	function getThumbnail($width = 80, $height = 0, $thumbnail_type = '') {
		if(!$this->document_srl) return;
		if($this->isSecret() && !$this->isGranted()) return;
		if(!$height) $height = $width;
		$content = $this->get('content');
		if(!$this->get('uploaded_count')) {
			if(!$content) {
				$args = new stdClass();
				$args->document_srl = $this->document_srl;
				$output = executeQuery('document.getDocument', $args, array('content'));
				if($output->toBool() && $output->data) {
					$content = $output->data->content;
					$this->add('content', $content);
				}
			}
			if(!preg_match("!<img!is", $content)) return;
		}
		if(!in_array($thumbnail_type, array('crop','ratio'))) {
			$config = $GLOBALS['__document_config__'];
			if(!$config) {
				$oDocumentModel = getModel('document');
				$config = $oDocumentModel->getDocumentConfig();
				$GLOBALS['__document_config__'] = $config;
			}
			$thumbnail_type = $config->thumbnail_type;
		}
		$thumbnail_path = sprintf('files/thumbnails/%s',getNumberingPath($this->document_srl, 3));
		$thumbnail_file = sprintf('%s%dx%d.%s.jpg', $thumbnail_path, $width, $height, $thumbnail_type);
		$thumbnail_lockfile = sprintf('%s%dx%d.%s.lock', $thumbnail_path, $width, $height, $thumbnail_type);
		$thumbnail_url  = Context::getRequestUri().$thumbnail_file;
		if(file_exists($thumbnail_file) || file_exists($thumbnail_lockfile)) {
			if(filesize($thumbnail_file) < 1) {
				return FALSE;
			} else {
				return $thumbnail_url;
			}
		}
		FileHandler::writeFile($thumbnail_lockfile, '', 'w');
		$source_file = null;
		$is_tmp_file = false;
		if($this->hasUploadedFiles()) {
			$file_list = $this->getUploadedFiles();
			$first_image = null;
			foreach($file_list as $file) {
				if($file->direct_download !== 'Y') continue;
				if($file->cover_image === 'Y' && file_exists($file->uploaded_filename)) {
					$source_file = $file->uploaded_filename;
					break;
				}
				if($first_image) continue;
				if(preg_match("/\.(jpe?g|png|gif|bmp)$/i", $file->source_filename)) {
					if(file_exists($file->uploaded_filename)) {
						$first_image = $file->uploaded_filename;
					}
				}
			}
			if(!$source_file && $first_image) $source_file = $first_image;
		}
		$is_tmp_file = false;
		if(!$source_file) {
			$random = new Password();
			preg_match_all("!<img[^>]*src=(?:\"|\')([^\"\']*?)(?:\"|\')!is", $content, $matches, PREG_SET_ORDER);
			foreach($matches as $target_image) {
				$target_src = trim($target_image[1]);
				if(preg_match('/\/(common|modules|widgets|addons|layouts|m\.layouts)\//i', $target_src)) continue;
				if(!preg_match('/^(http|https):\/\//i',$target_src)) $target_src = Context::getRequestUri().$target_src;
				$target_src = htmlspecialchars_decode($target_src);
				$tmp_file = _XE_PATH_ . 'files/cache/tmp/' . $random->createSecureSalt(32, 'hex');
				FileHandler::getRemoteFile($target_src, $tmp_file);
				if(!file_exists($tmp_file)) continue;
				$imageinfo = getimagesize($tmp_file);
				list($_w, $_h) = $imageinfo;
				if($imageinfo === false || ($_w < ($width * 0.3) && $_h < ($height * 0.3))) {
					FileHandler::removeFile($tmp_file);
					continue;
				}
				$source_file = $tmp_file;
				$is_tmp_file = true;
				break;
			}
		}
		if($source_file) $output = FileHandler::createImageFile($source_file, $thumbnail_file, $width, $height, 'jpg', $thumbnail_type);
		if($is_tmp_file) FileHandler::removeFile($source_file);
		FileHandler::removeFile($thumbnail_lockfile);
		if($output) return $thumbnail_url;
		else FileHandler::writeFile($thumbnail_file, '','w');
		return;
	}

	function getExtraImages($time_interval = 43200) {
		if(!$this->document_srl) return;
		$buffs = array();
		$check_files = false;
		if($this->isSecret()) $buffs[] = "secret";
		$time_check = date("YmdHis", $_SERVER['REQUEST_TIME']-$time_interval);
		if($this->get('regdate')>$time_check) $buffs[] = "new";
		else if($this->get('last_update')>$time_check) $buffs[] = "update";
		if($this->hasUploadedFiles()) $buffs[] = "file";
		return $buffs;
	}

	function getStatus() {
		if(!$this->get('status')) return $this->getDefaultStatus();
		return $this->get('status');
	}

	function printExtraImages($time_check = 43200) {
		if(!$this->document_srl) return;
		$path = sprintf('%s%s',getUrl(), 'modules/document/tpl/icons/');
		$buffs = $this->getExtraImages($time_check);
		if(!count($buffs)) return;
		$buff = array();
		foreach($buffs as $key => $val) $buff[] = sprintf('<img src="%s%s.gif" alt="%s" title="%s" style="margin-right:2px;" />', $path, $val, $val, $val);
		return implode('', $buff);
	}

	function hasUploadedFiles() {
		if(!$this->document_srl) return;
		if($this->isSecret() && !$this->isGranted()) return false;
		return $this->get('uploaded_count')? true : false;
	}

	function getUploadedFiles($sortIndex = 'file_srl') {
		if(!$this->document_srl) return;
		if($this->isSecret() && !$this->isGranted()) return;
		if(!$this->get('uploaded_count')) return;
		if(!$this->uploadedFiles[$sortIndex]) {
			$oFileModel = getModel('file');
			$this->uploadedFiles[$sortIndex] = $oFileModel->getFiles($this->document_srl, array(), $sortIndex, true);
		}
		return $this->uploadedFiles[$sortIndex];
	}

	function getEditor() {
		$module_srl = $this->get('module_srl');
		if(!$module_srl) $module_srl = Context::get('module_srl');
		$oEditorModel = getModel('editor');
		return $oEditorModel->getModuleEditor('document', $module_srl, $this->document_srl, 'document_srl', 'content');
	}

	function isEnableComment() {
		if (!$this->allowComment()) return false;
		if(!$this->isGranted() && $this->isSecret()) return false;
		return true;
	}

	function getCommentEditor() {
		if(!$this->isEnableComment()) return;
		$oEditorModel = getModel('editor');
		return $oEditorModel->getModuleEditor('comment', $this->get('module_srl'), $comment_srl, 'comment_srl', 'content');
	}

	function getProfileImage() {
		if(!$this->isExists() || !$this->get('member_srl')) return;
		$oMemberModel = getModel('member');
		$profile_info = $oMemberModel->getProfileImage($this->get('member_srl'));
		if(!$profile_info) return;
		return $profile_info->src;
	}

	function getSignature() {
		if(!$this->isExists() || !$this->get('member_srl')) return;
		$oMemberModel = getModel('member');
		$signature = $oMemberModel->getSignature($this->get('member_srl'));
		if(!isset($GLOBALS['__member_signature_max_height'])) {
			$oModuleModel = getModel('module');
			$member_config = $oModuleModel->getModuleConfig('member');
			$GLOBALS['__member_signature_max_height'] = $member_config->signature_max_height;
		}
		if($signature) {
			$max_signature_height = $GLOBALS['__member_signature_max_height'];
			if($max_signature_height) $signature = sprintf('<div style="max-height:%dpx;overflow:auto;overflow-x:hidden;height:expression(this.scrollHeight > %d ? \'%dpx\': \'auto\')">%s</div>', $max_signature_height, $max_signature_height, $max_signature_height, $signature);
		}
		return $signature;
	}

	function replaceResourceRealPath($matches) {
		return preg_replace('/src=(["\']?)files/i','src=$1'.Context::getRequestUri().'files', $matches[0]);
	}

	function _checkAccessibleFromStatus() {
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;
		$status = $this->get('status');
		if(empty($status)) return false;
		$oDocumentModel = getModel('document');
		$configStatusList = $oDocumentModel->getStatusList();
		if($status == $configStatusList['public'] || $status == $configStatusList['publish'])
			return true;
		else if($status == $configStatusList['private'] || $status == $configStatusList['secret']) {
			if($this->get('member_srl') == $logged_info->member_srl)
				return true;
		}
		return false;
	}

	function getTranslationLangCodes() {
		$obj = new stdClass;
		$obj->document_srl = $this->document_srl;
		$obj->var_idx = -2;
		$output = executeQueryArray('document.getDocumentTranslationLangCodes', $obj);
		if (!$output->data) $output->data = array();
		$origLangCode = new stdClass;
		$origLangCode->lang_code = $this->getLangCode();
		$output->data[] = $origLangCode;
		return $output->data;
	}

	function getDocumentMid() {
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->mid;
	}

	function getDocumentType() {
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->module;
	}

	function getDocumentAlias() {
		$oDocumentModel = getModel('document');
		return $oDocumentModel->getAlias($this->document_srl);
	}

	function getModuleName() {
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->browser_title;
	}

	function getBrowserTitle() {
		return $this->getModuleName();
	}
}
