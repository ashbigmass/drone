<!--@if($layout_info->layout_type == 'one_page')-->
jQuery(document).ready(function($) {
	$('.header-wrapper').waypoint('sticky');
});
    jQuery(function($){
        $(".arctic_scroll").arctic_scroll({
            speed: 800
        });
    jQuery(function($){
$('.arctic_scroll').tooltip();
        });
    });
<!--@end-->
<!--@if($layout_info->layout_type == 'one_page' && $layout_info->bg_slide == 'yes')-->
jQuery(function($){
$.supersized({
slide_interval          :   3000,// Length between transitions
transition              :   1,
transition_speed:1000,// Speed of transition
random: 1,
slides :  [
<block cond="$bg_image1">
{image : '{$bg_image1}'}
</block>
<block cond="$bg_image1 && $bg_image2">
,
{image : '{$bg_image2}'}
</block>
<block cond="$bg_image1 && $bg_image2 && $bg_image3">
,
{image : '{$bg_image3}'}
</block>
<block cond="$bg_image1 && $bg_image2 && $bg_image3 && $bg_image4">
,
{image : '{$bg_image4}'}
</block>
<block cond="$bg_image1 && $bg_image2 && $bg_image3 && $bg_image4 && $bg_image5">
,
{image : '{$bg_image5}'}
</block>
],

// Theme Options   
progress_bar:1,// Timer for each slide

});
    });
<!--@end-->
<!--@if($layout_info->layout_type == 'main_page' && $layout_info->main_slide == 'yes')-->
jQuery(function(){
	jQuery('#camera_wrap').camera({
	height: '40%',
	minHeight: '',
	thumbnails: false,
	pagination: false,
	time : 5000,
	transPeriod : 1500,
	navigation: false,//true or false, to display or not the navigation buttons
	navigationHover	: false,	//if true the navigation button (prev, next and play/stop buttons) will be visible on hover state only, if false they will be visible always
	mobileNavHover: false,
	playPause: false//same as above, but only for mobile devices
		});
	});
<!--@end-->