<?php
class EncryptionAdminModel extends Encryption
{
	public function getEncryptionAdminNewAESKey() {
		$entropy = $this->getRandomString(48);
		for ($i = 0; $i < 16; $i++) $entropy = hash('sha512', $entropy . $i, true);
		$entropy = substr(base64_encode($entropy), 0, 64);
		$this->add('newkey', $entropy);
	}

	public function getEncryptionAdminNewRSAKey() {
		$args = Context::getRequestVars();
		$bits = intval(max(1024, round($args->key_size / 1024) * 1024));
		$res = openssl_pkey_new(array(
			'digest_alg' => 'sha256',
			'private_key_bits' => $bits,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		));
		openssl_pkey_export($res, $private_key);
		$public_key = openssl_pkey_get_details($res);
		$public_key = $public_key['key'];
		$this->add('privkey', $private_key);
		$this->add('pubkey', $public_key);
	}
}
