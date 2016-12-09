<?php
class rssAdminController extends rss
{
	function init() {
	}

	function procRssAdminInsertConfig() {
		$oModuleModel = getModel('module');
		$total_config = $oModuleModel->getModuleConfig('rss');
		$config_vars = Context::getRequestVars();
		$config_vars->feed_document_count = (int)$config_vars->feed_document_count;
		if(!$config_vars->use_total_feed) $alt_message = 'msg_invalid_request';
		if(!in_array($config_vars->use_total_feed, array('Y','N'))) $config_vars->open_rss = 'Y';
		if($config_vars->image || $config_vars->del_image) {
			$image_obj = $config_vars->image;
			$config_vars->image = $total_config->image;
			if($config_vars->del_image == 'Y' || $image_obj) {
				FileHandler::removeFile($config_vars->image);
				$config_vars->image = '';
				$total_config->image = '';
			}
			if($image_obj['tmp_name'] && is_uploaded_file($image_obj['tmp_name']) && checkUploadedFile($image_obj['tmp_name'])) {
				$image_obj['name'] = Context::convertEncodingStr($image_obj['name']);
				if(!preg_match("/\.(jpg|jpeg|gif|png)$/i", $image_obj['name'])) {
					$alt_message = 'msg_rss_invalid_image_format';
				} else {
					$path = './files/attach/images/rss/';
					if(!FileHandler::makeDir($path)) {
						$alt_message = 'msg_error_occured';
					} else {
						$filename = $path.$image_obj['name'];
						if(!move_uploaded_file($image_obj['tmp_name'], $filename)) $alt_message = 'msg_error_occured';
						else $config_vars->image = $filename;
					}
				}
			}
		}
		if(!$config_vars->image && $config_vars->del_image != 'Y') $config_vars->image = $total_config->image;
		$output = $this->setFeedConfig($config_vars);
		if(!$alt_message) $alt_message = 'success_updated';
		$alt_message = Context::getLang($alt_message);
		$this->setMessage($alt_message, 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispRssAdminIndex');
		$this->setRedirectUrl($returnUrl);
	}

	public function procRssAdminDeleteFeedImage() {
		$delImage = Context::get('del_image');
		$oModuleModel = getModel('module');
		$originConfig = $oModuleModel->getModuleConfig('rss');
		if($delImage == 'Y') {
			FileHandler::removeFile($originConfig->image);
			$originConfig->image = '';
			$output = $this->setFeedConfig($originConfig);
			return new Object(0, 'success_updated');
		}
		return new Object(-1, 'fail_to_delete');
	}

	function procRssAdminInsertModuleConfig() {
		$config_vars = Context::getRequestVars();
		$openRssList = $config_vars->open_rss;
		$openTotalFeedList = $config_vars->open_total_feed;
		$feedDescriptionList = $config_vars->feed_description;
		$feedCopyrightList = $config_vars->feed_copyright;
		$targetModuleSrl = $config_vars->target_module_srl;
		if($targetModuleSrl && !is_array($openRssList)) {
			$openRssList = array($targetModuleSrl => $openRssList);
			$openTotalFeedList = array($targetModuleSrl => $openTotalFeedList);
			$feedDescriptionList = array($targetModuleSrl => $feedDescriptionList);
			$feedCopyrightList = array($targetModuleSrl => $feedCopyrightList);
		}
		if(is_array($openRssList)) {
			foreach($openRssList AS $module_srl=>$open_rss) {
				if(!$module_srl || !$open_rss) return new Object(-1, 'msg_invalid_request');
				if(!in_array($open_rss, array('Y','H','N'))) $open_rss = 'N';
				$this->setRssModuleConfig($module_srl, $open_rss, $openTotalFeedList[$module_srl], $feedDescriptionList[$module_srl], $feedCopyrightList[$module_srl]);
			}
		}
		$this->setMessage('success_updated', 'info');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispBoardAdminContent');
		$this->setRedirectUrl($returnUrl);
	}

	function setFeedConfig($config) {
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('rss',$config);
		return new Object();
	}

	function setRssModuleConfig($module_srl, $open_rss, $open_total_feed = 'N', $feed_description = 'N', $feed_copyright = 'N') {
		$oModuleController = getController('module');
		$config = new stdClass;
		$config->open_rss = $open_rss;
		$config->open_total_feed = $open_total_feed;
		if($feed_description != 'N') { $config->feed_description = $feed_description; }
		if($feed_copyright != 'N') { $config->feed_copyright = $feed_copyright; }
		$oModuleController->insertModulePartConfig('rss',$module_srl,$config);
		return new Object();
	}
}
