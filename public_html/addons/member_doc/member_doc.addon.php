<?php
if(!defined('__XE__')) exit();

if($called_position=='after_module_proc'){
	if($this->act=='dispBoardWrite'){
		$modulesrl = Context::get('module_srl');
		$document_srl = Context::get('document_srl');
		$valex = 'valex';
		$val = null;
		$val->module_srl = $modulesrl;
		$val->idx = '808080';
		$val->name = '게시글 설정';
		$val->type = 'checkbox';
		$val->default = '전체공개,회원공개';
		$val->desc = '';
		$val->is_required = 'N';
		$val->search = 'Y';
		$val->eid = '';
		$val->value = '';
		if($document_srl)
		{
			$args->document_srl = $document_srl;
			$tmp_output = executeQuery('addons.member_doc.getDocumentExtra', $args);
			if($tmp_output->toBool())
			{
				$extra_vars=unserialize($tmp_output->data->extra_vars);
				$val->value = $extra_vars->$valex;
				$obj = null;
				$obj = new ExtraItem($val->module_srl, $val->idx, $val->name, $val->type, $val->default, $val->desc, $val->is_required, $val->search, $val->value,  $val->eid);
				$extra_keys = Context::get('extra_keys');
				$extra_keys[$val->idx] = $obj;
				Context::set('extra_keys', $extra_keys);
			}
		}else
		{
			$obj = null;
			$obj = new ExtraItem($val->module_srl, $val->idx, $val->name, $val->type, $val->default, $val->desc, $val->is_required, $val->search, $val->value,  $val->eid);
			$extra_keys = Context::get('extra_keys');
			$extra_keys[$val->idx] = $obj;
			Context::set('extra_keys', $extra_keys);
		}
	}elseif($this->act=='procBoardInsertDocument'){
		$valex = 'valex';
		$doc = Context::get('document_srl');
		$idx = '808080';
		$val = Context::get('extra_vars'.$idx);
		Context::set('extra_vars'.$idx, null);
		$args->document_srl = $doc;
		$tmp_output = executeQuery('addons.member_doc.getDocumentExtra', $args);
		if($tmp_output->toBool()){
			$extra_vars=unserialize($tmp_output->data->extra_vars);
			if($val) $extra_vars->$valex = $val; else unset($extra_vars->$valex);
			$args->extra_vars = serialize($extra_vars);
			$tmp_output = executeQuery('addons.member_doc.updateDocumentExtra', $args);}
	}
}

if($called_position == 'before_module_proc'){
	if(($this->act=='dispBoardContent' || $this->act=='getBoardCommentPage') && Context::get('document_srl')){
		$logged_info = Context::get('logged_info');
		$modulesrl = Context::get('module_srl');
		$document_srl = Context::get('document_srl');
		$valex = 'valex';
		if ($document_srl){
			$args->document_srl = $document_srl;
			$tmp_output = executeQuery('addons.member_doc.getDocumentExtra', $args);
			if($tmp_output->toBool()){
				$extra_vars=unserialize($tmp_output->data->extra_vars);
				if($extra_vars->$valex){
					$permit = $extra_vars->$valex;
					if($permit='회원공개'&& !$logged_info)
					{
						exit('<a>'.'권한이 없습니다.'.'</a>');
					}
				}
			}
		}
	}
}