<?php
class beluxeAPI extends beluxe
{
	function dispBoardContentList(&$oModule) {
		$api_type = Context::get('api_type');
		$document_list = $this->arrangeContentList(Context::get('document_list'));
		if($api_type =='summary') {
			$content_cut_size = Context::get('content_cut_size');
			$content_cut_size = $content_cut_size?$content_cut_size:50;
			foreach($document_list as $k=>$v) {
				$oDocument = new documentItem();
				$oDocument->setAttribute($v, false);
				$document_list[$k]->content = $oDocument->getSummary($content_cut_size);
				unset($oDocument);
			}
		}
		$oModule->add('document_list',$document_list);
		$oModule->add('page_navigation',Context::get('page_navigation'));
	}

	function dispBoardContentView(&$oModule) {
		$oDocument = Context::get('oDocument');
		$extra_vars = $oDocument->getExtraVars();
		$oDocument->add('extra_vars',$this->arrangeExtraVars($extra_vars));
		$oModule->add('oDocument',$this->arrangeContent($oDocument));
	}

	function dispBoardTagList(&$oModule) {
		$oModule->add('tag_list',Context::get('tag_list'));
	}

	function dispBoardContentCommentList(&$oModule) {
		$oModule->add('comment_list',$this->arrangeComment(Context::get('comment_list')));
	}

	function arrangeContentList($content_list) {
		$output = array();
		if(count($content_list)) foreach($content_list as $key => $val) $output[] = $this->arrangeContent($val);
		return $output;
	}

	function arrangeContent($content) {
		$oBoardView = getView('board');
		$output = new stdClass;
		if($content){
			$output = $content->gets('document_srl','category_srl','member_srl','nick_name','user_id','user_name','title','content','tags','readed_count','voted_count','blamed_count','comment_count','regdate','last_update','extra_vars','status');
			if(!$oBoardView->grant->view) {
				unset($output->content);
				unset($output->tags);
				unset($output->extra_vars);
			}
			$t_width  = Context::get('thumbnail_width');
			$t_height = Context::get('thumbnail_height');
			$t_type   = Context::get('thumbnail_type');
			if ($t_width && $t_height && $t_type && $content->thumbnailExists($t_width, $t_height, $t_type)) $output->thumbnail_src = $content->getThumbnail($t_width, $t_height, $t_type);
		}
		return $output;
	}

	function arrangeComment($comment_list) {
		$output = array();
		if(count($comment_list) > 0 ) {
			foreach($comment_list as $key => $val){
				$item = null;
				$item = $val->gets('comment_srl','parent_srl','depth','is_secret','content','voted_count','blamed_count','user_id','user_name','nick_name','email_address','homepage','regdate','last_update');
				$output[] = $item;
			}
		}
		return $output;
	}

	function arrangeExtraVars($list) {
		$output = array();
		if(count($list)) {
			foreach($list as $key => $val){
				$item = new stdClass;
				$item->name = $val->name;
				$item->type = $val->type;
				$item->desc = $val->desc;
				$item->value = $val->value;
				$output[] = $item;
			}
		}
		return $output;
	}
}
