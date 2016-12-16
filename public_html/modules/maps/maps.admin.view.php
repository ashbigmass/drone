<?php
class mapsAdminView extends maps
{
	public function init() {
	}

	public function dispMapsAdminList() {
		$args = new stdClass();
		$args->page = intval(Context::get('page'));
		$args->order_type = 'desc';
		$oMapsAdminModel = getAdminModel('maps');
		$maps_list = $oMapsAdminModel->getMapsAdminList($args);
		if($maps_list->error) {
			Context::set('total_count', 0);
			Context::set('maps_error', $maps_list->error);
			Context::set('maps_message', $maps_list->message);
		} else {
			if($maps_list->page_navigation->first_page <= $maps_list->page_navigation->cur_page - 1) $maps_list->page_navigation->prev_page = $maps_list->page_navigation->cur_page - 1;
			else $maps_list->page_navigation->prev_page = $maps_list->page_navigation->cur_page;
			Context::set('total_count', $maps_list->total_count);
			Context::set('total_page', $maps_list->total_page);
			Context::set('page', $maps_list->page);
			Context::set('page_navigation', $maps_list->page_navigation);
			Context::set('maps_list', $maps_list->data);
		}
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('maps_list');
	}

	public function dispMapsAdminWrite() {
		$maps_lat = 37.57;
		$maps_lng = 126.98;
		$oMapsModel = getModel('maps');
		$maps_config = $oMapsModel->getMapsConfig();
		$args = new stdClass();
		$args->maps_srl = intval(Context::get('maps_srl'));
		if($args->maps_srl > 0) $output = executeQuery('maps.getMapUpdate', $args);
		if($output->data->update) {
			$output = executeQuery('maps.getMapbySrl', $args);
			$maps_content = unserialize(base64_decode($output->data->maps_content));
			Context::set('map_title',$output->data->title);
			Context::set('map_content',$output->data->content);
			Context::set('map_center',$maps_content->map_center);
			Context::set('map_markers',$maps_content->map_markers);
			Context::set('map_zoom',$maps_content->map_zoom);
		}
		if($maps_config->maps_api_type == 'daum') {
			$map_comp_header_script = '<script src="https://apis.daum.net/maps/maps3.js?apikey='.$maps_config->map_api_key.'"></script>';
			$map_comp_header_script .= '<script>'.sprintf('var defaultlat="%s";'. 'var defaultlng="%s";',$maps_lat,$maps_lng).'</script>';
			Context::set('maps_langcode', 'ko');
		} elseif($maps_config->maps_api_type == 'naver') {
			$map_comp_header_script = '<script src="https://openapi.map.naver.com/openapi/naverMap.naver?ver=2.0&amp;key='.$maps_config->map_api_key.'"></script>';
			$map_comp_header_script .= '<script>'.sprintf('var defaultlat="%s";'.'var defaultlng="%s";',$maps_lat,$maps_lng).'</script>';
			Context::set('maps_langcode', 'ko');
		} elseif($maps_config->maps_api_type == 'microsoft') {
			$langtype = tr_replace($this->xe_langtype, $this->microsoft_langtype, strtolower(Context::getLangType()));
			$map_comp_header_script = '<script type="text/javascript" src="https://ecn.dev.virtualearth.net/mapcontrol/mapcontrol.ashx?v=7.0&amp;mkt=ngt,'.$langtype.'"></script>';
			$map_comp_header_script .= '<script>'.sprintf('var defaultlat="%s";'.'var defaultlng="%s";',$maps_lat,$maps_lng).'</script>';
			Context::set('maps_langcode', 'ko');
		} else {
			$langtype = str_replace($this->xe_langtype, $this->google_langtype, strtolower(Context::getLangType()));
			if(Context::getLangType() == 'zh-CN' || Context::getLangType() == 'zh-TW') {
				$maps_lat = 39.55;
				$maps_lng = 116.23;
			} elseif(Context::getLangType() != 'ko') {
				$maps_lat = 38;
				$maps_lng = -97;
			}
			$map_comp_header_script = '<script src="https://maps-api-ssl.google.com/maps/api/js?sensor=false&amp;language='.$langtype.'"></script>';
			$map_comp_header_script .= '<script>'.sprintf('var defaultlat="%s";'.'var defaultlng="%s";',$maps_lat,$maps_lng).'</script>';
			Context::set('maps_langcode',$langtype);
		}
		Context::set('maps_api_type', $maps_config->maps_api_type);
		Context::set('map_api_key', $maps_config->map_api_key);
		Context::addHtmlHeader($map_comp_header_script);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('maps_write');
	}

	public function dispMapsAdminConfig() {
		$oMapsModel = getModel('maps');
		$maps_config = $oMapsModel->getMapsConfig();
		Context::set('map_api_key', $maps_config->map_api_key);
		Context::set('daum_local_api_key', $maps_config->daum_local_api_key);
		Context::set('map_api_type', $maps_config->map_api_type);
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('maps_config');
	}
}
