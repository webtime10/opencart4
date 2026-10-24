<?php
require "c:/OSPanel64/home/opencart4.loc/config.php";
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$db->set_charset("utf8mb4");
$p = DB_PREFIX;

$chk = $db->query("SELECT extension_install_id FROM {$p}extension_install WHERE code='wt_menu' LIMIT 1");
if ($chk && $chk->num_rows) {
  echo "already installed id=".$chk->fetch_assoc()['extension_install_id']."\n";
} else {
  $db->query("INSERT INTO {$p}extension_install SET
    extension_id=0, extension_download_id=0,
    name='WT Menu',
    description='Nestable catalog / meganav menu (Menu 1 vertical, Menu 2 horizontal)',
    code='wt_menu', version='1.0.0', author='WT', link='',
    status=1, date_added=NOW()");
  echo "inserted extension_install_id=".$db->insert_id."\n";
}

// ensure extension row
$chk = $db->query("SELECT extension_id FROM {$p}extension WHERE type='module' AND code='wt_menu' LIMIT 1");
if (!$chk || !$chk->num_rows) {
  $db->query("INSERT INTO {$p}extension SET extension='wt_menu', type='module', code='wt_menu'");
  echo "inserted extension\n";
} else {
  echo "extension ok id=".$chk->fetch_assoc()['extension_id']."\n";
}

// permissions for all admin groups
$r = $db->query("SELECT user_group_id, permission FROM {$p}user_group");
$route = 'extension/wt_menu/module/wt_menu';
while ($row = $r->fetch_assoc()) {
  $perm = json_decode($row['permission'], true);
  if (!is_array($perm)) $perm = ['access'=>[], 'modify'=>[]];
  $changed = false;
  foreach (['access','modify'] as $k) {
    if (!isset($perm[$k]) || !is_array($perm[$k])) $perm[$k] = [];
    if (!in_array($route, $perm[$k], true)) {
      $perm[$k][] = $route;
      $changed = true;
    }
  }
  if ($changed) {
    $json = $db->real_escape_string(json_encode($perm, JSON_UNESCAPED_UNICODE));
    $db->query("UPDATE {$p}user_group SET permission='{$json}' WHERE user_group_id=".(int)$row['user_group_id']);
    echo "permissions group {$row['user_group_id']} updated\n";
  }
}

echo "DONE\n";
