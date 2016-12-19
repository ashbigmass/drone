<?php
class beluxeWAP extends beluxe
{
	function procWAP(&$pMobile) {
		$oMi = $this->module_info;
		if(!$this->grant->list || (!$this->grant->manager && $oMi->consultation == 'Y')) return $pMobile->setContent(Context::getLang('msg_not_permitted'));
		$cmDocument = &getModel('document');
		$doc_srl = Context::get('document_srl');
		if($doc_srl) {
			$oDocIfo = $cmDocument->getDocument($doc_srl);
			if($oDocIfo->isExists()) {
				if(!$this->grant->view) return $pMobile->setContent(Context::getLang('msg_not_permitted'));
				Context::setBrowserTitle($oDocIfo->getTitleText());
				if($this->act=='dispBoardContentCommentList') {
					$cmComment = &getModel('comment');
					$out = $cmComment->getCommentList($oDocIfo->document_srl, 0, FALSE, $oDocIfo->getCommentCount());
					$content = '';
					if(count($out->data)) {
						foreach($out->data as $key => $val) {
							$oComNew = new commentItem();
							$oComNew->setAttribute($val);
							if(!$oComNew->isAccessible()) continue;
							$content .= "<b>".$oComNew->getNickName()."</b> (".$oComNew->getRegdate("Y-m-d").")<br>\r\n".$oComNew->getContent(FALSE,FALSE)."<br>\r\n";
						}
					}
					$pMobile->setContent( $content );
					$pMobile->setUpperUrl( getUrl('act',''), 'Go upper' );
				} else {
					if(!$oDocIfo->isNotice()) {
						$is_empty = !$this->grant->view && !$oDocIfo->isGranted();
						if(!$is_empty && $oMi->consultation == 'Y') $is_empty = !$oDocIfo->isGranted();
						if(!$is_empty && !$this->grant->manager && $oMi->use_blind == 'Y') {
							$cmThis = &getModel(__XEFM_NAME__);
							$is_empty = $cmThis->isBlind($doc_srl);
						}
					} else {
						$this->grant->view = TRUE;
					}
					if($is_empty) {
						$oDocIfo = $cmDocument->getDocument(0, FALSE, FALSE);
						$content = Context::getLang('msg_not_permitted');
					} else {
						$is_read = true;
						$is_grant = $oDocIfo->isGranted();
						$is_secret = $oDocIfo->isSecret();
						if(!$is_secret && !$is_grant && $oMi->use_point_type != 'A' && $oMi->use_restrict_view!='N') {
							if(!$cmThis) $cmThis = &getModel(__XEFM_NAME__);
							$is_read = $cmThis->isRead($doc_srl, $mbr_srl);
						}
						if(!$is_read) {
							$content = sprintf(Context::getLang('msg_restricted_view'), 0);
						} else {
							$content = '<b>'.$oDocIfo->getNickName().'</b> ('.$oDocIfo->getRegdate("Y-m-d").")<br>\r\n";
							$content .= Context::getLang('replies').' : <a href="'.getUrl('act','dispBoardContentCommentList').'">'.$oDocIfo->getCommentCount().'</a><br>'."\r\n";
							$content .= strip_tags(str_replace('<p>','<br>&nbsp;&nbsp;&nbsp;',$oDocIfo->getContent(FALSE,FALSE,FALSE)),'<br><b><i><u><em><small><strong><big>');
						}
						if($is_read && (!$is_secret || $is_grant)) $oDocIfo->updateReadedCount();
					}
					$pMobile->setContent( $content );
					$pMobile->setUpperUrl( getUrl('document_srl',''), Context::getLang('cmd_list') );
				}
				return;
			}
		}
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = 9;
		$df_navi = explode('|@|',$oMi->default_type_option);
		$args->sort_index = $df_navi[0]?$df_navi[0]:'list_order';
		$args->order_type = $df_navi[1]?$df_navi[1]:'asc';
		$out = $cmDocument->getDocumentList($args, TRUE);
		$doc_list = $out->data;
		$page_navi = $out->page_navigation;
		$_tmp = array();
		if($doc_list && count($doc_list)) {
			foreach($doc_list as $key => $val) {
				$href = getUrl('mid',$_GET['mid'],'document_srl',$val->document_srl);
				$obj = NULL;
				$obj['href'] = $val->getPermanentUrl();
				$title = htmlspecialchars($val->getTitleText());
				if($val->getCommentCount()) $title .= ' ['.$val->getCommentCount().']';
				$obj['link'] = $obj['text'] = '['.$val->getNickName().'] '.$title;
				$_tmp[] = $obj;
			}
			$pMobile->setChilds($_tmp);
		}
		$last_page = $page_navi->last_page;
		$page = (int)Context::get('page');
		if(!$page) $page = 1;
		if($page>1) $pMobile->setPrevUrl(getUrl('mid',$_GET['mid'],'page',$page-1), sprintf('%s (%d/%d)', Context::getLang('cmd_prev'), $page-1, $last_page));
		if($page<$last_page) $pMobile->setNextUrl(getUrl('mid',$_GET['mid'],'page',$page+1), sprintf('%s (%d/%d)', Context::getLang('cmd_next'), $page+1, $last_page));
		$pMobile->mobilePage = $page;
		$pMobile->totalPage = $last_page;
	}
}
