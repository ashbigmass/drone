<?php
class EncryptionAdminView extends Encryption
{
	public function dispEncryptionAdminConfig() {
		Context::set('encryption_config', $this->getConfig());
		Context::set('encryption_aes_enabled', function_exists('mcrypt_create_iv'));
		Context::set('encryption_rsa_enabled', function_exists('openssl_pkey_new'));
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('config');
	}
}
