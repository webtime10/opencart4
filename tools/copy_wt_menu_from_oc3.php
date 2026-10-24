<?php
$oc3 = require_config("c:/OSPanel64/home/opencart3.loc/config.php");
$oc4 = require_config("c:/OSPanel64/home/opencart4.loc/config.php");

function require_config($path) {
  // parse defines without including conflicting constants twice
  $src = file_get_contents($path);
  $cfg = [];
  foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX'] as $k) {
    if (preg_match("/define\\('\\Q$k\\E',\\s*'([^']*)'\\)/", $src, $m)) $cfg[$k] = $m[1];
    elseif (preg_match("/define\\('\\Q$k\\E',\\s*([^)]+)\\)/", $src, $m)) $cfg[$k] = trim($m[1], " '\"");
  }
  return $cfg;
}

$oc3 = require_config("c:/OSPanel64/home/opencart3.loc/config.php");
$oc4 = require_config("c:/OSPanel64/home/opencart4.loc/config.php");

$db3 = new mysqli($oc3['DB_HOSTNAME'], $oc3['DB_USERNAME'], $oc3['DB_PASSWORD'], $oc3['DB_DATABASE'], (int)($oc3['DB_PORT'] ?: 3306));
$db4 = new mysqli($oc4['DB_HOSTNAME'], $oc4['DB_USERNAME'], $oc4['DB_PASSWORD'], $oc4['DB_DATABASE'], (int)($oc4['DB_PORT'] ?: 3306));
$db3->set_charset('utf8mb4');
$db4->set_charset('utf8mb4');
$p3 = $oc3['DB_PREFIX'];
$p4 = $oc4['DB_PREFIX'];

// Map OC3 modules by menu_slot
$src = [];
$r = $db3->query("SELECT module_id, name, setting FROM `{$p3}module` WHERE code='wt_menu' ORDER BY module_id");
while ($row = $r->fetch_assoc()) {
  $s = json_decode($row['setting'], true);
  $slot = isset($s['menu_slot']) ? (int)$s['menu_slot'] : 1;
  if ($slot !== 2) $slot = 1;
  $src[$slot] = ['module_id' => (int)$row['module_id'], 'name' => $row['name'], 'setting' => $s];
  echo "OC3 id={$row['module_id']} slot={$slot} tree=" . count($s['tree'] ?? []) . " bytes=" . strlen($row['setting']) . "\n";
}

if (empty($src[1]) || empty($src[2])) {
  fwrite(STDERR, "Need both slots on OC3\n");
  exit(1);
}

// Target OC4 modules wt_menu.wt_menu by slot (or create)
$targets = [];
$r = $db4->query("SELECT module_id, name, setting FROM `{$p4}module` WHERE code='wt_menu.wt_menu' ORDER BY module_id");
while ($row = $r->fetch_assoc()) {
  $s = json_decode($row['setting'], true) ?: [];
  $slot = isset($s['menu_slot']) ? (int)$s['menu_slot'] : 0;
  if ($slot !== 2) $slot = 1;
  // prefer first match per slot
  if (!isset($targets[$slot])) {
    $targets[$slot] = (int)$row['module_id'];
  }
  echo "OC4 id={$row['module_id']} slot={$slot} name={$row['name']}\n";
}

$db4->query("ALTER TABLE `{$p4}module` MODIFY `setting` MEDIUMTEXT NOT NULL");

foreach ([1, 2] as $slot) {
  $from = $src[$slot];
  $setting = $from['setting'];
  $setting['menu_slot'] = $slot;
  $setting['status'] = '1';
  if ($slot === 1) {
    $setting['name'] = 'WT Menu 1 (vertical)';
    $name = 'WT Menu 1 (vertical)';
  } else {
    $setting['name'] = 'WT Menu 2 (horizontal)';
    $name = 'WT Menu 2 (horizontal)';
  }
  $json = json_encode($setting, JSON_UNESCAPED_UNICODE);
  if ($json === false) {
    fwrite(STDERR, "json encode fail slot $slot\n");
    exit(1);
  }

  if (!empty($targets[$slot])) {
    $id = $targets[$slot];
    $stmt = $db4->prepare("UPDATE `{$p4}module` SET name=?, setting=? WHERE module_id=?");
    $stmt->bind_param('ssi', $name, $json, $id);
    $stmt->execute();
    echo "UPDATED OC4 module_id={$id} slot={$slot} tree=" . count($setting['tree'] ?? []) . " bytes=" . strlen($json) . "\n";
  } else {
    $code = 'wt_menu.wt_menu';
    $stmt = $db4->prepare("INSERT INTO `{$p4}module` SET name=?, code=?, setting=?");
    $stmt->bind_param('sss', $name, $code, $json);
    $stmt->execute();
    $id = (int)$db4->insert_id;
    $targets[$slot] = $id;
    echo "INSERTED OC4 module_id={$id} slot={$slot} tree=" . count($setting['tree'] ?? []) . "\n";
  }
}

// Ensure Menu 2 on layout position menu for all layouts (like OC3)
$mod2 = $targets[2];
$code = 'wt_menu.wt_menu.' . $mod2;
$r = $db4->query("SELECT layout_id FROM `{$p4}layout`");
$n = 0;
while ($row = $r->fetch_assoc()) {
  $lid = (int)$row['layout_id'];
  $chk = $db4->query("SELECT layout_module_id FROM `{$p4}layout_module` WHERE layout_id={$lid} AND position='menu' AND code LIKE 'wt_menu.wt_menu.%' LIMIT 1");
  if ($chk && $chk->num_rows) {
    $db4->query("UPDATE `{$p4}layout_module` SET code='" . $db4->real_escape_string($code) . "' WHERE layout_id={$lid} AND position='menu' AND code LIKE 'wt_menu.wt_menu.%'");
  } else {
    $db4->query("INSERT INTO `{$p4}layout_module` SET layout_id={$lid}, code='" . $db4->real_escape_string($code) . "', position='menu', sort_order=0");
    $n++;
  }
}
echo "layout menu assignments ensured (new={$n}), code={$code}\n";
echo "DONE\n";
