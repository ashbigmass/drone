<?php
class maps extends ModuleObject
{
	protected $xe_langtype = array('ko', 'en', 'zh-tw', 'zh-cn', 'jp', 'es', 'fr', 'ru', 'vi', 'mn', 'tr');
	protected $google_langtype = array('ko', 'en', 'zh-Hant', 'zh-Hans', 'ja', 'es', 'fr', 'ru', 'vi', 'en', 'tr');
	protected $microsoft_langtype = array('ko-KR', 'en-US', 'zh-TW', 'zh-HK', 'ja-JP', 'es-ES', 'fr-FR', 'ru-RU', 'en-US', 'en-US', 'en-US');

	public function moduleInstall() {
	}

	public function checkUpdate() {
	}

	public function moduleUpdate() {
	}

	public function recompileCache() {
	}
}
