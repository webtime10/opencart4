<?php
/**
 * One-shot: add uk-ua to oc_language the same way extension install() should.
 * Run: php /home/carbon/site/test/_fixes/add_ukrainian_language.php
 */
define('VERSION', '4.1.0.3');
define('APPLICATION', 'Admin');

require_once dirname(__DIR__) . '/admin/config.php';
require_once DIR_SYSTEM . 'startup.php';

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\\System', DIR_SYSTEM);

$registry = new \Opencart\System\Engine\Registry();
$registry->set('autoloader', $autoloader);

$config = new \Opencart\System\Engine\Config();
$config->addPath(DIR_CONFIG);
$config->load('default');
$config->load('admin');
$config->set('application', 'Admin');
$registry->set('config', $config);

$event = new \Opencart\System\Engine\Event($registry);
$registry->set('event', $event);

$factory = new \Opencart\System\Engine\Factory($registry);
$registry->set('factory', $factory);

$loader = new \Opencart\System\Engine\Loader($registry);
$registry->set('load', $loader);

$request = new \Opencart\System\Library\Request();
$registry->set('request', $request);

$db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

$cache = new \Opencart\System\Library\Cache($config->get('cache_engine'), (int)$config->get('cache_expire'));
$registry->set('cache', $cache);

$log = new \Opencart\System\Library\Log('ukrainian_fix.log');
$registry->set('log', $log);

// Base language for copying descriptions
$q = $db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE `code` = 'en-gb' LIMIT 1");
if (!$q->num_rows) {
	fwrite(STDERR, "ERROR: en-gb language not found\n");
	exit(1);
}
$config->set('config_language_id', (int)$q->row['language_id']);
$config->set('config_language', 'en-gb');

$loader->model('localisation/language');
/** @var \Opencart\Admin\Model\Localisation\Language $model */
$model = $registry->get('model_localisation_language');

$existing = $model->getLanguageByCode('uk-ua');
if ($existing) {
	echo "ALREADY_EXISTS language_id={$existing['language_id']}\n";
} else {
	$language_id = $model->addLanguage([
		'name'       => 'Українська',
		'code'       => 'uk-ua',
		'locale'     => 'uk_UA.UTF-8,uk_UA,ukrainian',
		'extension'  => 'language_ukrainian_4100',
		'status'     => 1,
		'sort_order' => 0,
	]);
	echo "ADDED language_id={$language_id}\n";
}

$check = $db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'language' AND `code` = 'ukrainian'");
if (!$check->num_rows) {
	$db->query("INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = 'language_ukrainian_4100', `type` = 'language', `code` = 'ukrainian'");
	echo "REGISTERED extension language/ukrainian\n";
} else {
	echo "EXTENSION_ALREADY_REGISTERED\n";
}

$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'language_ukrainian'");
foreach ([
	'language_ukrainian_status' => '1',
	'language_ukrainian_uk_ua_status' => '1',
] as $key => $value) {
	$db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = 'language_ukrainian', `key` = '" . $db->escape($key) . "', `value` = '" . $db->escape($value) . "', `serialized` = '0'");
}
echo "SETTINGS_OK\n";

$verify = $db->query("SELECT language_id, name, code, locale, extension, status, sort_order FROM `" . DB_PREFIX . "language` WHERE `code` = 'uk-ua'");
print_r($verify->row);
