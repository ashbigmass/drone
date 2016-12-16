<?php
class mapsAdminController extends maps
{
	public function init() {
	}

	public function procMapsAdminCofig() {
		$oModuleController = getController('module');
		$config = new stdClass();
		$config->daum_local_api_key = trim(Context::get('daum_local_api_key'));
		$config->map_api_key = trim(Context::get('map_api_key'));
		$config->map_api_type = trim(Context::get('map_api_type'));
		$config->maps_api_type = '';
		if(strlen($config->map_api_key) === 40 || $config->map_api_key === $config->daum_local_api_key ||(trim($config->map_api_type) === 'daum' && strlen($config->map_api_key) == 32)) {
			if((!$config->daum_local_api_key && strlen($config->map_api_key) === 40) || (trim($config->map_api_type) === 'daum' && !$config->daum_local_api_key && strlen($config->map_api_key) == 32)) {
				$config->daum_local_api_key = $config->map_api_key;
			}
			$config->maps_api_type = 'daum';
		} elseif(strlen($config->map_api_key) === 32) {
			$config->maps_api_type = 'naver';
		} elseif(strlen($config->map_api_key) === 64) {
			$config->maps_api_type = 'microsoft';
		} else {
			$config->maps_api_type = 'google';
		}
		$oModuleController->insertModuleConfig('maps', $config);
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	public function procMapsAdminInsert() {
		$action = '';
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->maps_srl = intval(trim(Context::get('maps_srl')));
		$args->member_srl = $logged_info->member_srl;
		$args->title = htmlspecialchars(trim(Context::get('map_title')));
		$args->content = htmlspecialchars(trim(Context::get('map_description')));
		$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$maps_contents = new stdClass();
		$maps_contents->map_center = trim(Context::get('map_center'));
		$maps_contents->map_markers = trim(Context::get('map_markers'));
		$maps_contents->map_zoom = intval(Context::get('map_zoom'));
		$maps_contents = base64_encode(serialize($maps_contents));
		$args->maps_content = $maps_contents;
		if($args->maps_srl > 0) $output = executeQuery('maps.getMapUpdate', $args);
		if($output->data->update) {
			$output = executeQuery('maps.updateMapsContent', $args);
		} else {
			$args->maps_srl = getNextSequence();
			$output = executeQuery('maps.insertMapsContent', $args);
		}
		$this->add("maps_srl", $args->maps_srl);
		return;
	}

	public function procMapsAdminDelete() {
		$args = new stdClass();
		$args->maps_srl = intval(trim(Context::get('maps_srl')));
		if($args->maps_srl > 0) $output = executeQuery('maps.getMapUpdate', $args);
		if($output->data->update) $output = executeQuery('maps.deleteMapContent', $args);
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	public function procMapsAdminTableDrop() {
		DB::dropTable('maps_contents');
	}
}
