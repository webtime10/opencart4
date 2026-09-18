<?php
/**
 * Delete ALL products from OpenCart 4 (and related rows).
 * Run: php tools/purge_products.php
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

function tableExists(mysqli $m, string $table): bool {
	$r = $m->query("SHOW TABLES LIKE '" . $m->real_escape_string($table) . "'");
	return $r && $r->num_rows > 0;
}

$before = (int)$m->query("SELECT COUNT(*) c FROM {$p}product")->fetch_assoc()['c'];
echo "products_before={$before}\n";

$tables = [
	'product_attribute',
	'product_description',
	'product_discount',
	'product_filter',
	'product_image',
	'product_option',
	'product_option_value',
	'product_related',
	'product_reward',
	'product_special',
	'product_to_category',
	'product_to_download',
	'product_to_layout',
	'product_to_store',
	'product_recurring',
	'product_subscription',
	'coupon_product',
	'review',
];

$m->begin_transaction();
try {
	foreach ($tables as $t) {
		$full = $p . $t;
		if (!tableExists($m, $full)) {
			echo "skip {$full}\n";
			continue;
		}
		if (!$m->query("DELETE FROM {$full}")) {
			throw new RuntimeException($m->error . " ({$full})");
		}
		echo "cleared {$full} affected={$m->affected_rows}\n";
	}

	if (!$m->query("DELETE FROM {$p}product")) {
		throw new RuntimeException($m->error . ' (product)');
	}
	echo "cleared {$p}product affected={$m->affected_rows}\n";

	// reset AI if empty
	$m->query("ALTER TABLE {$p}product AUTO_INCREMENT=1");

	$m->commit();
} catch (Throwable $e) {
	$m->rollback();
	fwrite(STDERR, $e->getMessage() . PHP_EOL);
	exit(1);
}

$after = (int)$m->query("SELECT COUNT(*) c FROM {$p}product")->fetch_assoc()['c'];
echo "products_after={$after}\n";

// clear cache
if (defined('DIR_CACHE') && is_dir(DIR_CACHE)) {
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(DIR_CACHE, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($it as $f) {
		if ($f->getFilename() === 'index.html') {
			continue;
		}
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
	echo "cache cleared\n";
}

echo "DONE\n";
