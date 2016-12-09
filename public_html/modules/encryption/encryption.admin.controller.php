<?php
class EncryptionAdminController extends Encryption
{
	public function procEncryptionAdminInsertConfig() {
		$current_config = $this->getConfig();
		$request_args = Context::getRequestVars();
		$args = new stdClass();
		$args->aes_bits = $request_args->aes_bits;
		$args->aes_hmac_bits = $request_args->aes_hmac_bits;
		$args->aes_store = $request_args->aes_store === 'file' ? $request_args->aes_store_filename : 'DB';
		$args->aes_key = strlen(trim($request_args->aes_key)) ? trim($request_args->aes_key) : null;
		$args->rsa_bits = $request_args->rsa_bits;
		$args->rsa_hmac_bits = $request_args->rsa_hmac_bits;
		$args->rsa_store = $request_args->rsa_store === 'file' ? $request_args->rsa_store_filename : 'DB';
		$args->rsa_privkey = strlen(trim($request_args->rsa_privkey)) ? trim($request_args->rsa_privkey) : null;
		$args->rsa_pubkey = strlen(trim($request_args->rsa_pubkey)) ? trim($request_args->rsa_pubkey) : null;
		if ($args->rsa_privkey === null) $args->rsa_pubkey = null;
		if ($args->rsa_pubkey === null) $args->rsa_privkey = null;
		if ($args->aes_key === null) $args->aes_store = 'DB';
		if ($args->rsa_privkey === null) $args->rsa_store = 'DB';
		if ($args->aes_store !== 'DB' && $args->aes_key !== null) {
			$dir = dirname($args->aes_store);
			if (!file_exists($dir)) {
				$success = @mkdir($dir, 0755, true);
				if (!$success) return $this->stop('msg_encryption_symmetric_key_save_failure');
			}
			$success = @file_put_contents($args->aes_store, $args->aes_key);
			if (!$success) return $this->stop('msg_encryption_symmetric_key_save_failure');
			$args->aes_key = null;
		}
		if ($args->aes_store === 'DB' && $current_config->aes_store !== DB) @unlink($current_config->aes_store);
		if ($args->rsa_store !== 'DB' && $args->rsa_privkey !== null) {
			$dir = dirname($args->rsa_store);
			if (!file_exists($dir)) {
				$success = @mkdir($dir, 0755, true);
				if (!$success) return $this->stop('msg_encryption_asymmetric_key_save_failure');
			}
			$keydata = $args->rsa_privkey . "\n" . $args->rsa_pubkey . "\n";
			$success = @file_put_contents($args->rsa_store, $keydata);
			if (!$success) return $this->stop('msg_encryption_asymmetric_key_save_failure');
			$args->rsa_privkey = null;
			$args->rsa_pubkey = null;
		}
		if ($args->rsa_store === 'DB' && $current_config->rsa_store !== DB) @unlink($current_config->rsa_store);
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('encryption', $args);
		if ($output->toBool()) $this->setMessage('success_registed');
		else return $output;
		if (Context::get('success_return_url')) $this->setRedirectUrl(Context::get('success_return_url'));
		else $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'encryption', 'act', 'dispEncryptionAdminConfig'));
	}
}
