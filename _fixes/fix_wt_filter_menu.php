<?php
/**
 * One-shot: fix admin permissions + ensure OCMOD applied for WT Filter menu.
 */
$mysqli = new mysqli('localhost', 'root', 'Dev123!', 'oc4');
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
	fwrite(STDERR, $mysqli->connect_error . PHP_EOL);
	exit(1);
}

// Fix corrupted permission JSON (leading/trailing dots from bad escape)
$q = $mysqli->query('SELECT permission FROM oc_user_group WHERE user_group_id = 1');
$row = $q->fetch_assoc();
$raw = (string)$row['permission'];
$clean = preg_replace('/^\.+|\.+$/', '', trim($raw));
$p = json_decode($clean, true);

if (!$p) {
	fwrite(STDERR, 'json error: ' . json_last_error_msg() . PHP_EOL);
	exit(1);
}

foreach (['access', 'modify'] as $type) {
	foreach (['extension/wt_filter/catalog/wt_filter', 'extension/wt_filter/module/wt_filter'] as $route) {
		if (!in_array($route, $p[$type] ?? [], true)) {
			$p[$type][] = $route;
			echo "added $type $route\n";
		}
	}
}

$json = json_encode($p);
$stmt = $mysqli->prepare('UPDATE oc_user_group SET permission = ? WHERE user_group_id = 1');
$stmt->bind_param('s', $json);
$stmt->execute();

$check = json_decode($mysqli->query('SELECT permission FROM oc_user_group WHERE user_group_id = 1')->fetch_assoc()['permission'], true);
echo in_array('extension/wt_filter/catalog/wt_filter', $check['access'] ?? [], true)
	? "catalog access OK\n"
	: "catalog access FAIL\n";

// Ensure modification exists and enabled
$xml_path = dirname(__DIR__) . '/extension/wt_filter/ocmod/wt_filter.ocmod.xml';
$xml = file_get_contents($xml_path);
$mod = $mysqli->query("SELECT modification_id, status FROM oc_modification WHERE code = 'wt_filter'")->fetch_assoc();

if (!$mod) {
	$stmt = $mysqli->prepare('INSERT INTO oc_modification SET extension_install_id = 7, name = ?, description = ?, code = ?, author = ?, version = ?, link = ?, xml = ?, status = 1, date_added = NOW()');
	$name = 'WT Filter Modification (OpenCart 4)';
	$desc = '';
	$code = 'wt_filter';
	$author = 'WT';
	$version = '1.0.0';
	$link = '';
	$stmt->bind_param('sssssss', $name, $desc, $code, $author, $version, $link, $xml);
	$stmt->execute();
	echo 'modification inserted id=' . $mysqli->insert_id . PHP_EOL;
} else {
	$stmt = $mysqli->prepare('UPDATE oc_modification SET xml = ?, status = 1 WHERE modification_id = ?');
	$id = (int)$mod['modification_id'];
	$stmt->bind_param('si', $xml, $id);
	$stmt->execute();
	echo 'modification updated id=' . $id . PHP_EOL;
}

echo "Done. Now refresh Modifications in admin.\n";
