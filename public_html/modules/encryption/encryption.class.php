<?php
class Encryption extends ModuleObject
{
	protected static $default_config = array(
		'aes_bits' => 128,
		'aes_hmac_bits' => 128,
		'aes_store' => 'DB',
		'aes_key' => null,
		'rsa_bits' => 2048,
		'rsa_hmac_bits' => 128,
		'rsa_store' => 'DB',
		'rsa_privkey' => null,
		'rsa_pubkey' => null,
	);
	protected static $shortcuts = array(
		128 => 'A',
		192 => 'B',
		256 => 'C',
		384 => 'D',
		512 => 'E',
		768 => 'F',
		1024 => 'G',
		2048 => 'H',
		3072 => 'I',
		4096 => 'J',
		5120 => 'K',
		6144 => 'L',
		7168 => 'M',
		8192 => 'N',
	);

	public function getConfig() {
		$config = getModel('module')->getModuleConfig('encryption');
		if (!is_object($config)) $config = new stdClass();
		foreach (self::$default_config as $key => $val) {
			if (!isset($config->{$key})) $config->{$key} = $val;
		}
		if ($config->aes_store !== 'DB' && $config->aes_key === null) {
			if (@file_exists($config->aes_store)) {
				$config->aes_key = trim(file_get_contents($config->aes_store));
				if (strlen($config->aes_key) === 0) $config->aes_key = null;
			}
		}
		if ($config->rsa_store !== 'DB' && $config->rsa_privkey === null && $config->rsa_pubkey === null) {
			if (@file_exists($config->rsa_store)) {
				$rsa_key = trim(file_get_contents($config->rsa_store));
				if (strlen($rsa_key) === 0) $rsa_key = null;
				if (preg_match('/-----BEGIN (ENCRYPTED )?PRIVATE KEY-----(.+)-----END (ENCRYPTED )?PRIVATE KEY-----/sU', $rsa_key, $matches)) {
					$config->rsa_privkey = $matches[0] . "\n";
				}
				if (preg_match('/-----BEGIN PUBLIC KEY-----(.+)-----END PUBLIC KEY-----/sU', $rsa_key, $matches)) {
					$config->rsa_pubkey = $matches[0] . "\n";
				}
			}
		}
		return $config;
	}

	public function getRandomString($length) {
		static $fp = null;
		if ($fp === null) $fp = strncasecmp(PHP_OS, 'WIN', 3) ? @fopen('/dev/urandom', 'rb') : false;
		if ($fp) return fread($fp, $length);
		elseif (version_compare(PHP_VERSION, '5.4', '>=') || strncasecmp(PHP_OS, 'WIN', 3)) return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
		else return mcrypt_create_iv($length);
	}

	public function moduleInstall() {
		return new Object();
	}

	public function checkUpdate() {
		return false;
	}

	public function moduleUpdate() {
		return new Object(0, 'success_updated');
	}

	public function recompileCache() {
	}
}
