<?php
class mapsModel extends maps
{
	public function getMapsConfig() {
		$oModuleModel = getModel('module');
		$maps_config = $oModuleModel->getModuleConfig('maps');
		if(!is_object($maps_config)) $maps_config = new stdClass();
		if(!$maps_config->maps_api_type) $maps_config->maps_api_type = 'google';
		return $maps_config;
	}

	public function getApiXmlObject($uri, $headers = null) {
		$xml = '';
		$xml = FileHandler::getRemoteResource($uri, null, 3, 'GET', 'application/xml', $headers);
		$xml = preg_replace("/<\?xml([.^>]*)\?>/i", "", $xml);
		$oXmlParser = new XmlParser();
		$xml_doc = $oXmlParser->parse($xml);
		return $xml_doc;
	}
}
