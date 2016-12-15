<?php
class pageAPI extends page
{
	function dispPageIndex(&$oModule) {
		$page_content = Context::get('page_content');
		$oWidgetController = getController('widget');
		$requestMethod = Context::getRequestMethod();
		Context::setResponseMethod('HTML');
		$oWidgetController->triggerWidgetCompile($page_content);
		Context::setResponseMethod($requestMethod);
		$oModule->add('page_content',$page_content);
	}
}
