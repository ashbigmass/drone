<?php
	class moonchat extends WidgetHandler {
		function proc($args) {
			$logged_info = Context::get('logged_info');
			$obj = null;

			$obj->skins = "default";
			$obj->channel_name = $args->channel_name;
			$obj->chat_width = (int)$args->chat_width;
			$obj->chat_height = (int)$args->chat_height;
			$obj->target_name = "moonchat".rand(1,5000);
			$obj->chat_skin = ((int)$args->chat_skin == 1 || (int)$args->chat_skin == 2)?$args->chat_skin:0;
			$oModuleModel = &getModel('module');
			$oPointModel = &getModel('point');
			$config = $oModuleModel->getModuleConfig('point');
			$point = $oPointModel->getPoint($logged_info->member_srl);
			$level = $oPointModel->getLevel($point, $config->level_step);
			$obj->nick = $logged_info->nick_name;
			$obj->mb_id = $logged_info->user_id;
			if($obj->mb_id != "") {
			if($args->icon_type==1) { // 레벨아이콘
					// 포인트/레벨을 구함
					$chat_icon = sprintf('%smodules/point/icons/%s/%d.gif', Context::getRequestUri(), $config->level_icon, $level);
				} elseif($args->icon_type==2) { // 그룹아이콘
					if($logged_info->group_mark->src) $chat_icon = $logged_info->group_mark->src;
				} elseif($args->icon_type==3) { // 아이콘샵
					$oIconshopModel = &getModel('iconshop');
					$icon_image = $oIconshopModel->getMemberIconBySelected($logged_info->member_srl);
					if($icon_image->file1) $chat_icon = Context::getRequestUri().$icon_image->file1;
				} elseif($args->icon_type==4) { // 회원아이콘
				   if($logged_info->image_mark->src) $chat_icon = $logged_info->image_mark->src;
				}
				// 이미지이름 연동
				if($logged_info->image_name->src) {
					$obj->nickcon = $logged_info->image_name->src;
				}
				if($args->connect_win) {
					$obj->connect_win = $args->connect_win;
				} else {
					$obj->connect_win = "on";
				}
				if($chat_icon) $obj->chat_icon = $chat_icon;
				}
				//보안코드
				$obj->mhash = md5($obj->mb_id.$obj->nick.$obj->chat_icon);
			Context::set('moonchat',$obj);

			$act = Context::get('act');
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $obj->skins);
			$tpl_file = ($act == "dispPageAdminContentModify" || $act == "procWidgetGenerateCodeInPage")? "a" : "b";

			$oTemplate = &TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, $tpl_file);
		}
	}
?>
