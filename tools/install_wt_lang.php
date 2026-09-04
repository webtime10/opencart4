<?php
/**
 * Register WT Lang on OpenCart 4 (extension + settings + events + OCMOD).
 * Run from site root: php tools/install_wt_lang.php
 */
require_once __DIR__ . '/../config.php';

$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($mysqli->connect_error) {
	fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
	exit(1);
}

$mysqli->set_charset('utf8mb4');

$modPath = DIR_EXTENSION . 'wt_lang/ocmod/wt_lang.ocmod.xml';

if (!is_file($modPath)) {
	fwrite(STDERR, "OCMOD not found: {$modPath}\n");
	exit(1);
}

$modXml = file_get_contents($modPath);

if ($modXml === false) {
	fwrite(STDERR, "Failed to read OCMOD\n");
	exit(1);
}

$code = 'wt_lang';
$stmt = $mysqli->prepare('DELETE FROM `' . DB_PREFIX . 'modification` WHERE `code` = ?');
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->close();

$name = 'WT Lang';
$author = 'WT';
$version = '1.0.0';
$link = '';
$status = 1;

$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'modification` (`extension_install_id`, `name`, `code`, `author`, `version`, `link`, `xml`, `status`, `date_added`) VALUES (0, ?, ?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('ssssssi', $name, $code, $author, $version, $link, $modXml, $status);
$stmt->execute();
$stmt->close();

$res = $mysqli->query("SELECT extension_id FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` = 'wt_lang' LIMIT 1");

if (!$res || !$res->num_rows) {
	$mysqli->query("INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = 'wt_lang', `type` = 'module', `code` = 'wt_lang'");
}

$res = $mysqli->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_wt_lang_status' AND store_id = 0 LIMIT 1");

if (!$res || !$res->num_rows) {
	$langRes = $mysqli->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE status = 1 ORDER BY sort_order, name");
	$configRes = $mysqli->query("SELECT value FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_language' AND store_id = 0 LIMIT 1");
	$default = 'uk-ua';

	if ($configRes && $configRes->num_rows) {
		$configRow = $configRes->fetch_assoc();
		$default = $configRow['value'];
	}

	$language_map = [];

	while ($row = $langRes->fetch_assoc()) {
		$lc = $row['code'];
		$is_default = ($lc === $default);
		$prefix = '';

		if (!$is_default) {
			$prefix = match ($lc) {
				'en-gb' => 'en',
				'uk-ua' => 'uk',
				'ru-ru' => 'ru',
				default => substr($lc, 0, 2),
			};
		}

		$hreflang = match ($lc) {
			'uk-ua' => 'uk',
			'en-gb' => 'en',
			'ru-ru' => 'ru',
			default => $prefix ?: substr($lc, 0, 2),
		};

		$language_map[$lc] = [
			'status'   => 1,
			'prefix'   => $prefix,
			'hreflang' => $hreflang,
			'default'  => $is_default ? 1 : 0,
		];
	}

	$settings = [
		'module_wt_lang_status'   => '1',
		'module_wt_lang_hreflang' => '1',
		'module_wt_lang_xdefault' => '1',
	];

	foreach ($settings as $key => $value) {
		$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'setting` SET store_id = 0, `code` = \'module_wt_lang\', `key` = ?, `value` = ?, serialized = 0');
		$stmt->bind_param('ss', $key, $value);
		$stmt->execute();
		$stmt->close();
	}

	$json = json_encode($language_map);
	$key = 'module_wt_lang_language';
	$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'setting` SET store_id = 0, `code` = \'module_wt_lang\', `key` = ?, `value` = ?, serialized = 1');
	$stmt->bind_param('ss', $key, $json);
	$stmt->execute();
	$stmt->close();
}

// Register catalog events (same as module install())
$mysqli->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'wt_lang'");

$events = [
	['WT Lang hreflang in header', 'catalog/controller/common/header/before', 'extension/wt_lang/module/wt_lang.eventHreflang'],
	['WT Lang language switcher', 'catalog/controller/common/language/after', 'extension/wt_lang/module/wt_lang.eventLanguageSwitcher'],
];

foreach ($events as $event) {
	$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'event` SET `code` = \'wt_lang\', `description` = ?, `trigger` = ?, `action` = ?, `status` = 1, `sort_order` = 0');
	$stmt->bind_param('sss', $event[0], $event[1], $event[2]);
	$stmt->execute();
	$stmt->close();
}

echo "WT Lang OC4 install OK.\n";
echo "1. Admin → Extensions → Modifications → Refresh\n";
echo "2. Admin → Extensions → Extensions → Modules → WT Lang → Install (if needed) → Enable\n";
