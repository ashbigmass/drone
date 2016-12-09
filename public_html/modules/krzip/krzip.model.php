<?php
class krzipModel extends krzip
{
	function getConfig() {
		if(!isset($this->module_config)) {
			$oModuleModel = getModel('module');
			$module_config = $oModuleModel->getModuleConfig('krzip');
			if(!is_object($module_config)) $module_config = new stdClass();
			$default_config = self::$default_config;
			foreach($default_config as $key => $val) {
				if(!isset($module_config->{$key})) $module_config->{$key} = $val;
			}
			$this->module_config = $module_config;
		}
		return $this->module_config;
	}

	function getMigratedPostcode($values) {
		if(is_array($values)) $values = implode(' ', $values);
		$output = array('', trim(preg_replace('/\s+/', ' ', $values)), '', '', '');
		if(preg_match('/\(?([0-9]{3}-[0-9]{3})\)?/', $output[1], $matches)) {
			$output[1] = trim(preg_replace('/\s+/', ' ', str_replace($matches[0], '', $output[1])));
			$output[0] = $matches[1];
		}
		if(preg_match('/\(.+\s.+[읍면동리(마을)(0-9+가)]\s[0-9-]+\)/', $output[1], $matches)) {
			$output[1] = trim(str_replace($matches[0], '', $output[1]));
			$output[2] = $matches[0];
		}
		if(preg_match('/\(.+[읍면동리(마을)(0-9+가)](?:,.*)?\)/u', $output[1], $matches)) {
			$output[1] = trim(str_replace($matches[0], '', $output[1]));
			$output[4] = $matches[0];
		}
		if(preg_match('/^(.+ [가-힝]+[0-9]*[동리로길]\s*[0-9-]+(?:번지?)?),?\s+(.+)$/u', $output[1], $matches)) {
			$output[1] = trim($matches[1]);
			$output[3] = trim($matches[2]);
		}
		return $output;
	}

	function getKrzipCodeList($query) {
		$module_config = $this->getConfig();
		if($module_config->api_handler != 1) return new Object(-1, 'msg_invalid_request');
		if(!isset($query)) $query = Context::get('query');
		$query = trim(strval($query));
		if($query === '') return $this->stop('msg_krzip_no_query');
		$output = $this->getEpostapiSearch($query);
		$this->add('address_list', $output->get('address_list'));
		if(!$output->toBool()) return $output;
	}

	function getEpostapiSearch($query = '') {
		$encoding = strtoupper(mb_detect_encoding($query));
		if($encoding !== 'EUC-KR') $query = iconv($encoding, 'EUC-KR', $query);
		$module_config = $this->getConfig();
		$regkey = $module_config->epostapi_regkey;
		$fields = array('target' => 'postRoad', 'regkey' => $regkey, 'query' => $query);
		$headers = array('accept-language' => 'ko');
		$request_config = array('ssl_verify_peer' => FALSE);
		$buff = FileHandler::getRemoteResource(
			self::$epostapi_host,
			NULL,
			30,
			'POST',
			'application/x-www-form-urlencoded',
			$headers,
			array(),
			$fields,
			$request_config
		);
		$oXmlParser = new XmlParser();
		$result = $oXmlParser->parse($buff);
		if($result->error) {
			$err_msg = trim($result->error->message->body);
			if(!$err_msg) {
				$err_code = intval(str_replace('ERR-', '', $result->error->error_code->body));
				switch($err_code) {
					case 1: $err_msg = 'msg_krzip_is_maintenance'; break;
					case 2: $err_msg = 'msg_krzip_wrong_regkey'; break;
					case 3: $err_msg = 'msg_krzip_no_result'; break;
					default: $err_msg = 'msg_krzip_riddling_wrong'; break;
				}
			}
			return new Object(-1, $err_msg);
		}
		if(!$result->post) return new Object(-1, 'msg_krzip_riddling_wrong');
		$item_list = $result->post->itemlist->item;
		if(!is_array($item_list)) $item_list = array($item_list);
		if(!$item_list) return new Object(-1, 'msg_krzip_no_result');
		$addr_list = array();
		foreach($item_list as $key => $val) {
			$postcode = substr($val->postcd->body, 0, 3) . '-' . substr($val->postcd->body, 3, 3);
			$road_addr = $val->lnmaddress->body;
			$jibun_addr = $val->rnaddress->body;
			$addr_list[] = $this->getMigratedPostcode('(' . $postcode . ') (' . $jibun_addr . ') ' . $road_addr);
		}
		$output = new Object();
		$output->add('address_list', $addr_list);
		return $output;
	}

	function getKrzipCodeSearchHtml($column_name, $values) {
		$template_config = $this->getConfig();
		$template_config->sequence_id = ++self::$sequence_id;
		$template_config->column_name = $column_name;
		$template_config->values = $this->getMigratedPostcode($values);
		Context::set('template_config', $template_config);
		$api_name = strval(self::$api_list[$template_config->api_handler]);
		$oTemplate = TemplateHandler::getInstance();
		$output = $oTemplate->compile($this->module_path . 'tpl', 'template.' . $api_name);
		return $output;
	}
}
