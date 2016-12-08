<?php
if(!defined("__XE__")) exit();
$db_info = (object)array (
  'master_db' =>
	  array ('db_type' => 'mysqli', 'db_port' => '3306', 'db_hostname' => '192.168.100.12', 'db_userid' => 'drcsdb', 'db_password' => 'drcsdbwjdqjf', 'db_database' => 'drcsdb', 'db_table_prefix' => 'drcs_',
  ),
  'slave_db' =>
	  array (0 =>
		array ('db_type' => 'mysqli', 'db_port' => '3306', 'db_hostname' => '192.168.100.12', 'db_userid' => 'drcsdb', 'db_password' => 'drcsdbwjdqjf', 'db_database' => 'drcsdb', 'db_table_prefix' => 'drcs_',
    ),
  ),
  'default_url' => 'http://dtcs.saashub.org/', 'use_mobile_view' => 'Y', 'use_rewrite' => 'N', 'time_zone' => '+0900', 'use_prepared_statements' => 'Y', 'qmail_compatibility' => 'N', 'use_db_session' => 'N', 'use_ssl' => 'none', 'sitelock_whitelist' =>
	  array (
		0 => '127.0.0.1',
	  ),
  'use_sso' => 'N', 'use_html5' => 'N', 'admin_ip_list' => NULL,
);