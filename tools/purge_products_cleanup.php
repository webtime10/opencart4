<?php
$root = dirname(__DIR__);
require $root . '/config.php';
$m = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
$m->set_charset('utf8mb4');
$p = DB_PREFIX;

$m->query("DELETE FROM {$p}seo_url WHERE `key`='product_id'");
echo "seo_url product_id cleared affected={$m->affected_rows}\n";

$r = $m->query("SELECT module_id, name, code, setting FROM {$p}module");
while ($row = $r->fetch_assoc()) {
	$s = json_decode($row['setting'], true);
	if (!is_array($s)) {
		continue;
	}
	$changed = false;
	if (!empty($s['product']) && is_array($s['product'])) {
		$s['product'] = [];
		$changed = true;
	}
	if ($changed) {
		$json = $m->real_escape_string(json_encode($s, JSON_UNESCAPED_UNICODE));
		$m->query("UPDATE {$p}module SET setting='{$json}' WHERE module_id=" . (int)$row['module_id']);
		echo "module {$row['module_id']} {$row['name']} products cleared\n";
	}
}

echo "products=" . (int)$m->query("SELECT COUNT(*) c FROM {$p}product")->fetch_assoc()['c'] . "\n";
echo "DONE\n";
