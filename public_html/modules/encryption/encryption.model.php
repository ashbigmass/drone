<?php
class EncryptionModel extends Encryption
{
	protected $config = null;
	protected $extension = null;

	public function __construct() {
		if (version_compare(PHP_VERSION, '5.4', '>=') && function_exists('openssl_encrypt')) $this->extension = 'openssl';
		else $this->extension = 'mcrypt';
	}

	public function aesEncrypt($plaintext) {
		if ($this->config === null) $this->config = $this->getConfig();
		if ($this->config->aes_key === null) return false;
		$key_size = intval($this->config->aes_bits / 8);
		$key = substr(hash('sha256', $this->config->aes_key . ':AES-KEY', true), 0, $key_size);
		$iv = $this->getRandomString(16);
		if ($this->extension === 'openssl') {
			$openssl_method = 'aes-' . $this->config->aes_bits . '-cbc';
			$ciphertext = openssl_encrypt($plaintext, $openssl_method, $key, OPENSSL_RAW_DATA, $iv);
		} else {
			$plaintext = $this->applyPKCS7Padding($plaintext, 16);
			$ciphertext = mcrypt_encrypt('rijndael-128', $key, $plaintext, 'cbc', $iv);
		}
		$hmac_size = intval($this->config->aes_hmac_bits / 8);
		$hmac_key = hash('sha256', $this->config->aes_key . ':HMAC-KEY', true);
		$hmac = substr(hash_hmac('sha256', $ciphertext, $hmac_key, true), 0, $hmac_size);
		$meta = $this->createMetadata('A', 'E', $this->config->aes_bits, $this->config->aes_hmac_bits);
		return $meta . base64_encode($iv . $hmac . $ciphertext);
	}

	public function aesDecrypt($ciphertext) {
		if ($this->config === null) $this->config = $this->getConfig();
		if ($this->config->aes_key === null) return false;
		$meta = $this->decodeMetadata(substr($ciphertext, 0, 4));
		if ($meta->encryption_type !== 'A') return false;
		if (!$meta->bits || !$meta->hmac_bits) return false;
		switch ($meta->key_type) {
			case 'E': return $this->aesDecrypt_v2($ciphertext, $meta);
			case 'K': return $this->aesDecrypt_v1($ciphertext, $meta);
			default: return false;
		}
	}

	public function aesDecrypt_v2($ciphertext, $meta) {
		$iv_size = mcrypt_get_iv_size('rijndael-128', 'cbc');
		$hmac_size = intval($meta->hmac_bits / 8);
		if (strlen($ciphertext) % 4 !== 0) return false;
		$ciphertext = @base64_decode(substr($ciphertext, 4));
		if ($ciphertext === false) return false;
		if (strlen($ciphertext) <= $iv_size + $hmac_size) return false;
		$iv = substr($ciphertext, 0, $iv_size);
		$hmac = substr($ciphertext, $iv_size, $hmac_size);
		$ciphertext = substr($ciphertext, $iv_size + $hmac_size);
		$key_size = intval($meta->bits / 8);
		$key = substr(hash('sha256', $this->config->aes_key . ':AES-KEY', true), 0, $key_size);
		$hmac_key = hash('sha256', $this->config->aes_key . ':HMAC-KEY', true);
		$hmac_check = substr(hash_hmac('sha256', $ciphertext, $hmac_key, true), 0, $hmac_size);
		if ($hmac !== $hmac_check) return false;
		if ($this->extension === 'openssl') {
			$openssl_method = 'aes-' . $this->config->aes_bits . '-cbc';
			$plaintext = openssl_decrypt($ciphertext, $openssl_method, $key, OPENSSL_RAW_DATA, $iv);
		} else {
			$plaintext = @mcrypt_decrypt('rijndael-128', $key, $ciphertext, 'cbc', $iv);
			if ($plaintext === false) return false;
			$plaintext = $this->stripPKCS7Padding($plaintext, 16);
			if ($plaintext === false) return false;
		}
		return $plaintext;
	}

	public function aesDecrypt_v1($ciphertext, $meta) {
		$cipher = 'rijndael-' . $meta->bits;
		$iv_size = mcrypt_get_iv_size($cipher, 'cbc');
		$hmac_size = intval($meta->hmac_bits / 8);
		if (strlen($ciphertext) % 4 !== 0) return false;
		$ciphertext = @base64_decode(substr($ciphertext, 4));
		if ($ciphertext === false) return false;
		if (strlen($ciphertext) <= $iv_size + $hmac_size) return false;
		$iv = substr($ciphertext, 0, $iv_size);
		$hmac = substr($ciphertext, $iv_size, $hmac_size);
		$ciphertext = substr($ciphertext, $iv_size + $hmac_size);
		$key_size = mcrypt_get_key_size($cipher, 'cbc');
		$key = substr(hash('sha256', $this->config->aes_key, true), 0, $key_size);
		$hmac_check = substr(hash_hmac('sha256', $ciphertext, $key . $iv, true), 0, $hmac_size);
		if ($hmac !== $hmac_check) return false;
		$plaintext = @mcrypt_decrypt($cipher, $key, $ciphertext, 'cbc', $iv);
		if ($plaintext === false) return false;
		$plaintext = @gzuncompress($plaintext);
		if ($plaintext === false) return false;
		return $plaintext;
	}

	public function rsaEncryptWithPublicKey($plaintext) {
		if ($this->config === null) $this->config = $this->getConfig();
		if ($this->config->rsa_pubkey === null) return false;
		$pubkey = @openssl_pkey_get_public($this->config->rsa_pubkey);
		if ($pubkey === false) return false;
		$ciphertext = false;
		$status = @openssl_public_encrypt($plaintext, $ciphertext, $pubkey);
		@openssl_pkey_free($pubkey);
		if (!$status || $ciphertext === false) return false;
		$hmac_key = hash('sha256', trim($this->config->rsa_pubkey));
		$hmac_size = intval($this->config->rsa_hmac_bits / 8);
		$hmac = substr(hash_hmac('sha256', $ciphertext, $hmac_key, true), 0, $hmac_size);
		$meta = $this->createMetadata('R', 'B', $this->config->rsa_bits, $this->config->rsa_hmac_bits);
		return $meta . base64_encode($hmac . $ciphertext);
	}

	public function rsaDecryptWithPublicKey($ciphertext) {
		if ($this->config === null) $this->config = $this->getConfig();
		if ($this->config->rsa_pubkey === null) return false;
		$meta = $this->decodeMetadata(substr($ciphertext, 0, 4));
		if ($meta->encryption_type !== 'R') return false;
		if ($meta->key_type !== 'A' && $meta->key_type !== 'P') return false;
		if (!$meta->bits || !$meta->hmac_bits) return false;
		$hmac_size = intval($meta->hmac_bits / 8);
		$ciphertext = @base64_decode(substr($ciphertext, 4));
		if ($ciphertext === false) return false;
		if (strlen($ciphertext) <= $hmac_size) return false;
		$hmac = substr($ciphertext, 0, $hmac_size);
		$ciphertext = substr($ciphertext, $hmac_size);
		$hmac_key = hash('sha256', trim($this->config->rsa_pubkey));
		$hmac_check = substr(hash_hmac('sha256', $ciphertext, $hmac_key, true), 0, $hmac_size);
		if ($hmac !== $hmac_check) return false;
		$pubkey = @openssl_pkey_get_public($this->config->rsa_pubkey);
		if ($pubkey === false) return false;
		$plaintext = false;
		$status = @openssl_public_decrypt($ciphertext, $plaintext, $pubkey);
		@openssl_pkey_free($pubkey);
		if (!$status || $plaintext === false) return false;
		if ($meta->key_type === 'P') {
			$plaintext = @gzuncompress($plaintext);
			if ($plaintext === false) return false;
		}
		return $plaintext;
	}

	public function rsaEncryptWithPrivateKey($plaintext, $passphrase = null) {
		if ($this->config === null) $this->config = $this->getConfig();
		if ($this->config->rsa_privkey === null || $this->config->rsa_pubkey === null) return false;
		$privkey = @openssl_pkey_get_private($this->config->rsa_privkey, strval($passphrase));
		if ($privkey === false) return false;
		$ciphertext = false;
		$status = @openssl_private_encrypt($plaintext, $ciphertext, $privkey);
		@openssl_pkey_free($privkey);
		if (!$status || $ciphertext === false) return false;
		$hmac_key = hash('sha256', trim($this->config->rsa_pubkey));
		$hmac_size = intval($this->config->rsa_hmac_bits / 8);
		$hmac = substr(hash_hmac('sha256', $ciphertext, $hmac_key, true), 0, $hmac_size);
		$meta = $this->createMetadata('R', 'A', $this->config->rsa_bits, $this->config->rsa_hmac_bits);
		return $meta . base64_encode($hmac . $ciphertext);
	}

	public function rsaDecryptWithPrivateKey($ciphertext, $passphrase = null) {
		if ($this->config === null) $this->config = $this->getConfig();
		if ($this->config->rsa_privkey === null || $this->config->rsa_pubkey === null) return false;
		$meta = $this->decodeMetadata(substr($ciphertext, 0, 4));
		if ($meta->encryption_type !== 'R') return false;
		if ($meta->key_type !== 'B' && $meta->key_type !== 'U') return false;
		if (!$meta->bits || !$meta->hmac_bits) return false;
		$hmac_size = intval($meta->hmac_bits / 8);
		$ciphertext = @base64_decode(substr($ciphertext, 4));
		if ($ciphertext === false) return false;
		if (strlen($ciphertext) <= $hmac_size) return false;
		$hmac = substr($ciphertext, 0, $hmac_size);
		$ciphertext = substr($ciphertext, $hmac_size);
		$hmac_key = hash('sha256', trim($this->config->rsa_pubkey));
		$hmac_check = substr(hash_hmac('sha256', $ciphertext, $hmac_key, true), 0, $hmac_size);
		if ($hmac !== $hmac_check) return false;
		$privkey = @openssl_pkey_get_private($this->config->rsa_privkey, strval($passphrase));
		if ($privkey === false) return false;
		$plaintext = false;
		$status = @openssl_private_decrypt($ciphertext, $plaintext, $privkey);
		@openssl_pkey_free($privkey);
		if (!$status || $plaintext === false) return false;
		if ($meta->key_type === 'U') {
			$plaintext = @gzuncompress($plaintext);
			if ($plaintext === false) return false;
		}
		return $plaintext;
	}

	protected function applyPKCS7Padding($str, $block_size) {
		$padding_size = $block_size - (strlen($str) % $block_size);
		if ($padding_size === 0) $padding_size = $block_size;
		return $str . str_repeat(chr($padding_size), $padding_size);
	}

	protected function stripPKCS7Padding($str, $block_size) {
		if (strlen($str) % $block_size !== 0) return false;
		$padding_size = ord(substr($str, -1));
		if ($padding_size < 1 || $padding_size > $block_size) return false;
		if (substr($str, (-1 * $padding_size)) !== str_repeat(chr($padding_size), $padding_size)) return false;
		return substr($str, 0, strlen($str) - $padding_size);
	}

	protected function createMetadata($cipher, $key_type, $bits, $hmac_bits) {
		return $cipher . $key_type . self::$shortcuts[$bits] . self::$shortcuts[$hmac_bits];
	}

	protected function decodeMetadata($metadata) {
		return (object)array(
			'encryption_type' => $metadata[0],
			'key_type' => $metadata[1],
			'bits' => array_search($metadata[2], self::$shortcuts),
			'hmac_bits' => array_search($metadata[3], self::$shortcuts),
		);
	}
}
