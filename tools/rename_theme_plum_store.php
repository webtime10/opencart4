<?php
/**
 * Switch theme from wtshop → plum_store in DB.
 * Run: php tools/rename_theme_plum_store.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__);
require $root . '/config.php';

$m = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . PHP_EOL);
	exit(1);
}
$m->set_charset('utf8mb4');
$p = DB_PREFIX;

function q(mysqli $m, string $sql): void {
	if (!$m->query($sql)) {
		throw new RuntimeException($m->error . "\n" . $sql);
	}
}

function upsert(mysqli $m, string $p, string $code, string $key, string $value): void {
	$row = $m->query("SELECT setting_id FROM {$p}setting WHERE store_id=0 AND `key`='" . $m->real_escape_string($key) . "'")->fetch_assoc();
	if ($row) {
		q($m, "UPDATE {$p}setting SET value='" . $m->real_escape_string($value) . "', code='" . $m->real_escape_string($code) . "' WHERE setting_id=" . (int)$row['setting_id']);
	} else {
		q($m, "INSERT INTO {$p}setting SET store_id=0, code='" . $m->real_escape_string($code) . "', `key`='" . $m->real_escape_string($key) . "', value='" . $m->real_escape_string($value) . "', serialized=0");
	}
}

echo "== config_theme = plum_store ==\n";
upsert($m, $p, 'config', 'config_theme', 'plum_store');
upsert($m, $p, 'theme_plum_store', 'theme_plum_store_status', '1');
upsert($m, $p, 'theme_wtshop', 'theme_wtshop_status', '0');

echo "== extension row ==\n";
$ex = $m->query("SELECT extension_id FROM {$p}extension WHERE type='theme' AND code='plum_store'")->fetch_assoc();
if (!$ex) {
	q($m, "INSERT INTO {$p}extension SET extension='plum_store', type='theme', code='plum_store'");
	echo "inserted plum_store theme extension\n";
} else {
	echo "plum_store theme extension exists\n";
}

echo "== events ==\n";
q($m, "UPDATE {$p}event SET status=0 WHERE code IN ('wtshop_header','wtshop_footer')");
q($m, "DELETE FROM {$p}event WHERE code IN ('plum_store_header','plum_store_footer')");
q($m, "INSERT INTO {$p}event SET code='plum_store_header', description='Plum Store header', `trigger`='catalog/view/common/header/before', action='extension/plum_store/event/theme.header', status=1, sort_order=1");
q($m, "INSERT INTO {$p}event SET code='plum_store_footer', description='Plum Store footer', `trigger`='catalog/view/common/footer/before', action='extension/plum_store/event/theme.footer', status=1, sort_order=1");

// permissions
$ug = $m->query("SELECT permission FROM {$p}user_group WHERE user_group_id=1")->fetch_assoc();
if ($ug) {
	$perm = json_decode($ug['permission'], true);
	if (!is_array($perm)) {
		$perm = [];
	}
	foreach (['access', 'modify'] as $k) {
		if (!isset($perm[$k]) || !is_array($perm[$k])) {
			$perm[$k] = [];
		}
		$route = 'extension/plum_store/theme/plum_store';
		if (!in_array($route, $perm[$k], true)) {
			$perm[$k][] = $route;
		}
	}
	q($m, "UPDATE {$p}user_group SET permission='" . $m->real_escape_string(json_encode($perm, JSON_UNESCAPED_UNICODE)) . "' WHERE user_group_id=1");
	echo "permissions ok\n";
}

if (defined('DIR_CACHE') && is_dir(DIR_CACHE)) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DIR_CACHE, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $f) {
		if ($f->getFilename() === 'index.html') {
			continue;
		}
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
	echo "cache cleared\n";
}

echo "DONE\n";
