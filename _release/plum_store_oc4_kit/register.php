<?php
/**
 * Plum Store / WT — register extensions for OpenCart 4.1.x
 *
 * 1. Copy hosting_copy/extension/* into <site>/extension/
 * 2. Copy this file into <site>/register.php (next to index.php)
 * 3. Open https://your-site/register.php
 * 4. DELETE this file
 *
 * Does:
 *  - oc_extension_install + oc_extension_path (modules appear in admin)
 *  - Install Plum Store theme (oc_extension)
 *  - Register theme Events (incl. left menu "Theme settings" / «Настройки темы»)
 *  - Admin permissions for theme settings
 *  - config_theme = plum_store
 *  - WT Menu: два инстанса (слот 1 = каталог, слот 2 = верхняя полоска) + layout menu
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$config = __DIR__ . '/config.php';
if (!is_file($config)) {
	exit('Put register.php in the OpenCart root (same folder as config.php).');
}

require_once $config;

foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX', 'DIR_EXTENSION'] as $c) {
	if (!defined($c)) {
		exit('Missing constant: ' . $c);
	}
}

$modules = [
	'plum_store',
	'wt_menu',
	'wt_lang',
	'wt_home_stage',
	'wt_big_banner',
	'wt_mini_banner',
	'wt_info_block',
	'wt_svg',
	'wt_ai',
	'wt_filter',
	'wt_exchange',
	'language_ukrainian_4100',
];

$mysqli = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($mysqli->connect_error) {
	exit('DB connect error: ' . htmlspecialchars($mysqli->connect_error));
}
$mysqli->set_charset('utf8mb4');

$prefix = DB_PREFIX;
$extRoot = rtrim(str_replace('\\', '/', DIR_EXTENSION), '/') . '/';

echo '<pre style="font:14px/1.4 Consolas,monospace;padding:16px;">';
echo 'DIR_EXTENSION = ' . $extRoot . "\n";
echo 'OC VERSION    = ' . (defined('VERSION') ? VERSION : '?') . "\n\n";

function collectPaths(string $baseDir, string $code): array {
	$paths = [];
	$root = rtrim($baseDir, '/') . '/' . $code;
	if (!is_dir($root)) {
		return $paths;
	}
	$paths[] = $code;
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($it as $file) {
		$full = str_replace('\\', '/', $file->getPathname());
		$paths[] = $code . '/' . substr($full, strlen($root) + 1);
	}
	$paths = array_values(array_unique($paths));
	sort($paths);
	return $paths;
}

function addPermission(mysqli $mysqli, string $prefix, int $groupId, string $route): void {
	$row = $mysqli->query("SELECT permission FROM `{$prefix}user_group` WHERE user_group_id = " . (int)$groupId)->fetch_assoc();
	if (!$row) {
		return;
	}
	$perm = json_decode((string)$row['permission'], true);
	if (!is_array($perm)) {
		$perm = ['access' => [], 'modify' => []];
	}
	$changed = false;
	foreach (['access', 'modify'] as $k) {
		if (!isset($perm[$k]) || !is_array($perm[$k])) {
			$perm[$k] = [];
		}
		if (!in_array($route, $perm[$k], true)) {
			$perm[$k][] = $route;
			$changed = true;
		}
	}
	if ($changed) {
		$json = $mysqli->real_escape_string(json_encode($perm, JSON_UNESCAPED_UNICODE));
		$mysqli->query("UPDATE `{$prefix}user_group` SET permission = '{$json}' WHERE user_group_id = " . (int)$groupId);
		echo "[PERM] {$route} → user_group {$groupId}\n";
	} else {
		echo "[PERM] {$route} already OK (group {$groupId})\n";
	}
}

/**
 * Ensure one WT Menu module with exact menu_slot (1=catalog vertical, 2=top horizontal).
 * Returns module_id.
 */
function ensureWtMenuModule(mysqli $mysqli, string $prefix, int $slot, string $name, array $tree): int {
	$slot = ($slot === 2) ? 2 : 1;
	$code = 'wt_menu.wt_menu';

	$q = $mysqli->query("SELECT module_id, setting FROM `{$prefix}module` WHERE `code` = '" . $mysqli->real_escape_string($code) . "'");
	while ($row = $q->fetch_assoc()) {
		$setting = json_decode((string)$row['setting'], true);
		if (!is_array($setting)) {
			continue;
		}
		$itemSlot = isset($setting['menu_slot']) ? (int)$setting['menu_slot'] : 1;
		if ($itemSlot !== 2) {
			$itemSlot = 1;
		}
		if ($itemSlot === $slot) {
			// Keep existing tree if already filled; only force slot/status/name
			if (empty($setting['tree']) || !is_array($setting['tree'])) {
				$setting['tree'] = $tree;
			}
			$setting['name'] = $name;
			$setting['status'] = 1;
			$setting['menu_slot'] = $slot;
			$json = $mysqli->real_escape_string(json_encode($setting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$mysqli->query("UPDATE `{$prefix}module` SET `name` = '" . $mysqli->real_escape_string($name) . "', `setting` = '{$json}' WHERE module_id = " . (int)$row['module_id']);
			echo "[OK]  WT Menu slot {$slot} module_id=" . (int)$row['module_id'] . " ({$name})\n";
			return (int)$row['module_id'];
		}
	}

	$setting = [
		'name'      => $name,
		'status'    => 1,
		'menu_slot' => $slot,
		'tree'      => $tree,
	];
	$json = $mysqli->real_escape_string(json_encode($setting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$mysqli->query("INSERT INTO `{$prefix}module` SET `name` = '" . $mysqli->real_escape_string($name) . "', `code` = '" . $mysqli->real_escape_string($code) . "', `setting` = '{$json}'");
	$id = (int)$mysqli->insert_id;
	echo "[ADD] WT Menu slot {$slot} module_id={$id} ({$name})\n";
	return $id;
}

function upsertSetting(mysqli $mysqli, string $prefix, string $code, string $key, string $value, int $serialized = 0): void {
	$row = $mysqli->query("SELECT setting_id FROM `{$prefix}setting` WHERE store_id = 0 AND `key` = '" . $mysqli->real_escape_string($key) . "' LIMIT 1")->fetch_assoc();
	$val = $mysqli->real_escape_string($value);
	$codeEsc = $mysqli->real_escape_string($code);
	$keyEsc = $mysqli->real_escape_string($key);
	if ($row) {
		$mysqli->query("UPDATE `{$prefix}setting` SET `value` = '{$val}', serialized = " . (int)$serialized . " WHERE setting_id = " . (int)$row['setting_id']);
	} else {
		$mysqli->query("INSERT INTO `{$prefix}setting` SET store_id = 0, `code` = '{$codeEsc}', `key` = '{$keyEsc}', `value` = '{$val}', serialized = " . (int)$serialized);
	}
}

// -------------------------------------------------------------------------
// 1) Register extension folders in Installer tables
// -------------------------------------------------------------------------
echo "=== 1. Extension paths ===\n";
$ok = 0;
$skip = 0;

foreach ($modules as $code) {
	$dir = $extRoot . $code;
	$installJson = $dir . '/install.json';

	if (!is_dir($dir) || !is_file($installJson)) {
		echo "[SKIP] {$code} — folder or install.json missing\n";
		$skip++;
		continue;
	}

	$info = json_decode((string)file_get_contents($installJson), true);
	if (!is_array($info) || empty($info['name']) || empty($info['version']) || empty($info['author'])) {
		echo "[SKIP] {$code} — bad install.json\n";
		$skip++;
		continue;
	}

	$link = !empty($info['link']) ? $info['link'] : 'https://localhost/';

	$stmt = $mysqli->prepare("SELECT extension_install_id FROM `{$prefix}extension_install` WHERE `code` = ? LIMIT 1");
	$stmt->bind_param('s', $code);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($row) {
		$installId = (int)$row['extension_install_id'];
		$mysqli->query("DELETE FROM `{$prefix}extension_path` WHERE extension_install_id = {$installId}");
		$mysqli->query(
			"UPDATE `{$prefix}extension_install` SET status = 1, name = '" . $mysqli->real_escape_string($info['name']) . "', version = '" . $mysqli->real_escape_string($info['version']) . "', author = '" . $mysqli->real_escape_string($info['author']) . "', link = '" . $mysqli->real_escape_string($link) . "' WHERE extension_install_id = {$installId}"
		);
		echo "[UPD] {$code} install_id={$installId}\n";
	} else {
		$stmt = $mysqli->prepare("INSERT INTO `{$prefix}extension_install` (extension_id, extension_download_id, name, code, version, author, link, status, date_added) VALUES (0, 0, ?, ?, ?, ?, ?, 1, NOW())");
		$name = $info['name'];
		$version = $info['version'];
		$author = $info['author'];
		$stmt->bind_param('sssss', $name, $code, $version, $author, $link);
		$stmt->execute();
		$installId = (int)$mysqli->insert_id;
		$stmt->close();
		echo "[ADD] {$code} install_id={$installId}\n";
	}

	$ins = $mysqli->prepare("INSERT INTO `{$prefix}extension_path` (extension_install_id, path) VALUES (?, ?)");
	$count = 0;
	foreach (collectPaths($extRoot, $code) as $path) {
		$ins->bind_param('is', $installId, $path);
		$ins->execute();
		$count++;
	}
	$ins->close();
	echo "       paths={$count}\n";
	$ok++;
}

echo "Paths done. Registered: {$ok}, skipped: {$skip}\n\n";

// -------------------------------------------------------------------------
// 2) Install Plum Store as theme + admin menu event («Настройки темы»)
// -------------------------------------------------------------------------
echo "=== 2. Plum Store theme + «Настройки темы» menu ===\n";

if (!is_dir($extRoot . 'plum_store')) {
	echo "[SKIP] plum_store folder missing — theme menu not registered\n";
} else {
	$ex = $mysqli->query("SELECT extension_id FROM `{$prefix}extension` WHERE `type` = 'theme' AND `extension` = 'plum_store' AND `code` = 'plum_store'")->fetch_assoc();
	if (!$ex) {
		$mysqli->query("INSERT INTO `{$prefix}extension` SET `extension` = 'plum_store', `type` = 'theme', `code` = 'plum_store'");
		echo "[ADD] oc_extension theme plum_store id=" . $mysqli->insert_id . "\n";
	} else {
		echo "[OK]  oc_extension theme plum_store id=" . $ex['extension_id'] . "\n";
	}

	// Same events as plum_store install() — menu item lives here
	$mysqli->query("DELETE FROM `{$prefix}event` WHERE `code` LIKE 'plum_store_%' OR `code` LIKE 'wtshop_%'");

	$events = [
		['plum_store_header', 'Plum Store header template', 'catalog/view/common/header/before', 'extension/plum_store/event/theme.header', 1, 1],
		['plum_store_footer', 'Plum Store footer template', 'catalog/view/common/footer/before', 'extension/plum_store/event/theme.footer', 1, 1],
		['plum_store_menu', 'Plum Store admin menu — Theme settings', 'admin/view/common/column_left/before', 'extension/plum_store/theme/plum_store.eventMenu', 1, 0],
		['plum_store_language', 'Plum Store language switcher', 'catalog/view/common/language/before', 'extension/plum_store/event/theme.language', 1, 1],
		['plum_store_currency', 'Plum Store currency switcher', 'catalog/view/common/currency/before', 'extension/plum_store/event/theme.currency', 1, 1],
		['plum_store_info', 'Plum Store information flags', 'admin/view/catalog/information_form/after', 'extension/plum_store/theme/plum_store.eventInformationForm', 1, 0],
		['plum_store_info', 'Plum Store information flags', 'admin/model/catalog/information.addInformation/after', 'extension/plum_store/theme/plum_store.eventInformationAdd', 1, 0],
		['plum_store_info', 'Plum Store information flags', 'admin/model/catalog/information.editInformation/after', 'extension/plum_store/theme/plum_store.eventInformationEdit', 1, 0],
		['plum_store_info', 'Plum Store information flags', 'admin/model/catalog/information.deleteInformation/after', 'extension/plum_store/theme/plum_store.eventInformationDelete', 1, 0],
	];

	$eins = $mysqli->prepare("INSERT INTO `{$prefix}event` (`code`, `description`, `trigger`, `action`, `status`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?)");
	foreach ($events as $e) {
		$eins->bind_param('ssssii', $e[0], $e[1], $e[2], $e[3], $e[4], $e[5]);
		$eins->execute();
	}
	$eins->close();
	echo "[OK]  theme events registered (incl. plum_store_menu → left menu «Настройки темы»)\n";

	$themeRoute = 'extension/plum_store/theme/plum_store';
	$groups = $mysqli->query("SELECT user_group_id FROM `{$prefix}user_group`");
	while ($g = $groups->fetch_assoc()) {
		addPermission($mysqli, $prefix, (int)$g['user_group_id'], $themeRoute);
	}

	// Activate theme for default store
	$st = $mysqli->query("SELECT setting_id, `value` FROM `{$prefix}setting` WHERE `key` = 'config_theme' AND store_id = 0")->fetch_assoc();
	if ($st) {
		$mysqli->query("UPDATE `{$prefix}setting` SET `value` = 'plum_store' WHERE setting_id = " . (int)$st['setting_id']);
		echo "[OK]  config_theme: " . $st['value'] . " → plum_store\n";
	} else {
		$mysqli->query("INSERT INTO `{$prefix}setting` SET store_id = 0, `code` = 'config', `key` = 'config_theme', `value` = 'plum_store', serialized = 0");
		echo "[ADD] config_theme = plum_store\n";
	}
}

// -------------------------------------------------------------------------
// 3) WT Menu — слот 1 (каталог) + слот 2 (верхняя полоска)
// -------------------------------------------------------------------------
echo "\n=== 3. WT Menu slots (catalog + top bar) ===\n";

if (!is_dir($extRoot . 'wt_menu')) {
	echo "[SKIP] wt_menu folder missing\n";
} else {
	$ex = $mysqli->query("SELECT extension_id FROM `{$prefix}extension` WHERE `type` = 'module' AND `extension` = 'wt_menu' AND `code` = 'wt_menu'")->fetch_assoc();
	if (!$ex) {
		$mysqli->query("INSERT INTO `{$prefix}extension` SET `extension` = 'wt_menu', `type` = 'module', `code` = 'wt_menu'");
		echo "[ADD] oc_extension module wt_menu id=" . $mysqli->insert_id . "\n";
	} else {
		echo "[OK]  oc_extension module wt_menu id=" . $ex['extension_id'] . "\n";
	}

	upsertSetting($mysqli, $prefix, 'module_wt_menu', 'module_wt_menu_status', '1');
	echo "[OK]  module_wt_menu_status = 1\n";

	$moduleRoute = 'extension/wt_menu/module/wt_menu';
	$groups = $mysqli->query("SELECT user_group_id FROM `{$prefix}user_group`");
	while ($g = $groups->fetch_assoc()) {
		addPermission($mysqli, $prefix, (int)$g['user_group_id'], $moduleRoute);
	}

	// Slot 1 = vertical catalog under «Каталог товарів».
	// Empty tree → theme falls back to OpenCart categories (safe default).
	$catalogId = ensureWtMenuModule($mysqli, $prefix, 1, 'WT Menu Catalog (slot 1)', []);

	// Slot 2 = horizontal meganav under header.
	$topTree = [
		[
			'id'       => 1,
			'name'     => 'Home',
			'href'     => 'index.php?route=common/home',
			'status'   => 1,
			'image'    => '',
			'children' => [],
		],
	];
	$topId = ensureWtMenuModule($mysqli, $prefix, 2, 'WT Menu Top (slot 2)', $topTree);

	// Attach slot 2 to Home + Default layouts, position "menu"
	$layoutCode = 'wt_menu.wt_menu.' . $topId;
	$layoutIds = [];
	$lq = $mysqli->query("SELECT layout_id, name FROM `{$prefix}layout` WHERE name IN ('Home','Default') OR layout_id IN (1,4)");
	while ($lr = $lq->fetch_assoc()) {
		$layoutIds[(int)$lr['layout_id']] = $lr['name'];
	}
	if (!$layoutIds) {
		$lr = $mysqli->query("SELECT layout_id, name FROM `{$prefix}layout` ORDER BY layout_id ASC LIMIT 1")->fetch_assoc();
		if ($lr) {
			$layoutIds[(int)$lr['layout_id']] = $lr['name'];
		}
	}

	foreach ($layoutIds as $layoutId => $layoutName) {
		$exists = $mysqli->query(
			"SELECT layout_module_id FROM `{$prefix}layout_module` WHERE layout_id = " . (int)$layoutId .
			" AND `code` = '" . $mysqli->real_escape_string($layoutCode) . "' AND `position` = 'menu' LIMIT 1"
		)->fetch_assoc();

		if ($exists) {
			echo "[OK]  layout «{$layoutName}» already has slot 2 on position menu\n";
			continue;
		}

		// Remove wrong slot-1 modules from menu position (catalog must not sit in top bar)
		$wrong = $mysqli->query(
			"SELECT layout_module_id, `code` FROM `{$prefix}layout_module` WHERE layout_id = " . (int)$layoutId .
			" AND `position` = 'menu' AND `code` LIKE 'wt_menu.wt_menu.%'"
		);
		while ($w = $wrong->fetch_assoc()) {
			$parts = explode('.', (string)$w['code']);
			$mid = isset($parts[2]) ? (int)$parts[2] : 0;
			if ($mid && $mid === $catalogId) {
				$mysqli->query("DELETE FROM `{$prefix}layout_module` WHERE layout_module_id = " . (int)$w['layout_module_id']);
				echo "[FIX] removed catalog slot 1 from layout «{$layoutName}» position menu\n";
			}
		}

		$mysqli->query(
			"INSERT INTO `{$prefix}layout_module` SET layout_id = " . (int)$layoutId .
			", `code` = '" . $mysqli->real_escape_string($layoutCode) . "', `position` = 'menu', sort_order = 0"
		);
		echo "[ADD] layout «{$layoutName}» ← WT Menu Top (slot 2) @ menu\n";
	}

	echo "\nRule:\n";
	echo "  Slot 1 = button «Каталог товарів» (vertical vmenu). Empty → categories fallback.\n";
	echo "  Slot 2 = brown top bar (horizontal meganav). Never put slot 2 into catalog.\n";
}

// -------------------------------------------------------------------------
// 4) WT Big Banner + WT Mini Banner (home stage slider + side tiles)
// -------------------------------------------------------------------------
echo "\n=== 4. WT Big / Mini Banner ===\n";

$bannerModules = [
	['ext' => 'wt_big_banner', 'code' => 'wt_big_banner.wt_big_banner', 'name' => 'WT Big Banner', 'route' => 'extension/wt_big_banner/module/wt_big_banner'],
	['ext' => 'wt_mini_banner', 'code' => 'wt_mini_banner.wt_mini_banner', 'name' => 'WT Mini Banner', 'route' => 'extension/wt_mini_banner/module/wt_mini_banner'],
];

foreach ($bannerModules as $bm) {
	$ext = $bm['ext'];
	if (!is_dir($extRoot . $ext)) {
		echo "[SKIP] {$ext} folder missing\n";
		continue;
	}

	$ex = $mysqli->query("SELECT extension_id FROM `{$prefix}extension` WHERE `type` = 'module' AND `extension` = '" . $mysqli->real_escape_string($ext) . "' AND `code` = '" . $mysqli->real_escape_string($ext) . "'")->fetch_assoc();
	if (!$ex) {
		$mysqli->query("INSERT INTO `{$prefix}extension` SET `extension` = '" . $mysqli->real_escape_string($ext) . "', `type` = 'module', `code` = '" . $mysqli->real_escape_string($ext) . "'");
		echo "[ADD] oc_extension module {$ext} id=" . $mysqli->insert_id . "\n";
	} else {
		echo "[OK]  oc_extension module {$ext} id=" . $ex['extension_id'] . "\n";
	}

	upsertSetting($mysqli, $prefix, 'module_' . $ext, 'module_' . $ext . '_status', '1');
	echo "[OK]  module_{$ext}_status = 1\n";

	$groups = $mysqli->query("SELECT user_group_id FROM `{$prefix}user_group`");
	while ($g = $groups->fetch_assoc()) {
		addPermission($mysqli, $prefix, (int)$g['user_group_id'], $bm['route']);
	}

	$row = $mysqli->query("SELECT module_id FROM `{$prefix}module` WHERE `code` = '" . $mysqli->real_escape_string($bm['code']) . "' ORDER BY module_id ASC LIMIT 1")->fetch_assoc();
	if ($row) {
		echo "[OK]  {$bm['name']} module_id=" . (int)$row['module_id'] . "\n";
	} else {
		$setting = [
			'name'   => $bm['name'],
			'status' => 1,
			'item'   => [],
		];
		$json = $mysqli->real_escape_string(json_encode($setting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$mysqli->query("INSERT INTO `{$prefix}module` SET `name` = '" . $mysqli->real_escape_string($bm['name']) . "', `code` = '" . $mysqli->real_escape_string($bm['code']) . "', `setting` = '{$json}'");
		echo "[ADD] {$bm['name']} module_id=" . $mysqli->insert_id . "\n";
	}
}

echo "\n=== Done ===\n";
echo "1. Refresh admin — left menu: «Настройки темы» / Theme settings\n";
echo "2. Extensions → Modules — WT Menu + WT Big Banner + WT Mini Banner\n";
echo "3. Fill banners (image/title/description/percent/product); home stage uses them with demo fallback\n";
echo "4. DELETE register.php from the server!\n";
echo '</pre>';
