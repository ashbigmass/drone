<?php
	if(!defined('__XE__')) return;
	if(!defined('__ZBXE__')) return;

	if( $called_position == 'before_display_content' ) {
		if( $addon_info->google_use == 'enable' ) {
			Context::addCSSFile('./addons/web_fonts/css/google_icon.min.css');
		}
		if( $addon_info->awesome_use== 'enable' ) {
			Context::addCSSFile('///maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css');
		}
		if( $addon_info->nanumgothic_use== 'enable' ) {
			Context::addCSSFile('///fonts.googleapis.com/earlyaccess/nanumgothic.css');
		}
		if( $addon_info->xeicon== 'enable' ) {
			Context::addCSSFile('././common/xeicon/xeicon.min.css');
		}
		if( $addon_info->button_use == 'enable' ) {
			Context::addCSSFile('./addons/web_fonts/css/button.min.css');
		}
	}
