<?php
	if(!defined("__XE__")) exit();

	if(Context::get('module') != admin && $called_position == before_display_content) {
		switch($addon_info->type) {
			case 'a': $script = "<script type=\"text/javascript\">window.onload=function(){var a=document.querySelectorAll('.xe_content img');var i;for(;;i++){if(a[i]){a[i].style.maxWidth='100%';a[i].style.height='auto'}else{break}}};</script>"; break;
			case 'b': $script = "<script type=\"text/javascript\">window.onload=function(){var a=document.querySelectorAll('.xe_content img'),b=document.getElementsByClassName('xe_content'),k,i;if(!b[1])k=0;else k=1;for(i=0;;i++){if(a[i]){a[i].style.maxWidth=b[k].clientWidth+'px';a[i].style.height='auto';}else break;}};</script>"; break;
			case 'c': $script = "<style>.xe_content img {max-width: 100% !important;height: auto !important;}</style>"; break;
			case 'd': $script = "<script type=\"text/javascript\">window.onload=function(){try{\$('.xe_content img').css('max-width', '100%').css('height', 'auto');}catch(e){jQuery('.xe_content img').css('max-width', '100%').css('height', 'auto');}});</script>"; break;
			case 'e': $script = "<script type=\"text/javascript\">window.onload=function(){var a=document.querySelectorAll('.xe_content img'),b=document.getElementsByClassName('xe_content'),k,i,t,g;if(!b[1])k=0;else k=1;for(i=0;;i++){if(a[i]&&a[i].clientWidth>b[k].clientWidth){t=a[i].clientWidth/a[i].clientHeight;a[i].style.width=b[k].clientWidth+'px';g=b[k].clientWidth/t;a[i].style.height=g+'px';}else break;}};</script>"; break;
		}
		Context::addHtmlHeader($script);
	}
?>