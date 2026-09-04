<?php
/**
 * Wipe OC4 catalog and copy products/categories/brands/options/attributes/SEO from OC3.
 *
 * Usage: php tools/migrate_catalog_oc3_to_oc4.php
 */
declare(strict_types=1);

$oc3 = new mysqli('127.0.0.1', 'opencart3_user', 'opencart3_password', 'opencart3_db', 3308);
$oc4 = new mysqli('127.0.0.1', 'root', 'Dev123!', 'oc4', 3306);

foreach (['oc3' => $oc3, 'oc4' => $oc4] as $label => $db) {
	if ($db->connect_errno) {
		fwrite(STDERR, "$label connect failed: {$db->connect_error}\n");
		exit(1);
	}
	$db->set_charset('utf8mb4');
}

const PREFIX = 'oc_';

/** OC3 language_id → OC4 language_id (skip unknown / ru) */
function mapLang(int $id): ?int {
	return match ($id) {
		1 => 2, // uk-ua
		2 => 1, // en-gb
		default => null,
	};
}

function tableExists(mysqli $db, string $table): bool {
	$res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
	return $res && $res->num_rows > 0;
}

function cols(mysqli $db, string $table): array {
	$res = $db->query('SHOW COLUMNS FROM `' . $table . '`');
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$out[] = $row['Field'];
	}
	return $out;
}

function truncateOc4(mysqli $oc4): void {
	$tables = [
		'product_attribute', 'product_discount', 'product_filter', 'product_image',
		'product_option_value', 'product_option', 'product_related', 'product_reward',
		'product_special', 'product_to_category', 'product_to_download', 'product_to_layout',
		'product_to_store', 'product_subscription', 'product_code', 'product_report',
		'product_viewed', 'product_description', 'product',
		'category_filter', 'category_path', 'category_to_layout', 'category_to_store',
		'category_description', 'category',
		'manufacturer_to_layout', 'manufacturer_to_store', 'manufacturer',
		'option_value_description', 'option_value', 'option_description', 'option',
		'attribute_description', 'attribute', 'attribute_group_description', 'attribute_group',
		'seo_url',
		'wt_filter_option_value_to_product', 'wt_filter_option_value_description',
		'wt_filter_option_value', 'wt_filter_option_description', 'wt_filter_option_to_category',
		'wt_filter_option_to_store', 'wt_filter_option', 'wt_filter_seo_page',
	];

	$oc4->query('SET FOREIGN_KEY_CHECKS=0');
	foreach ($tables as $t) {
		$full = PREFIX . $t;
		$exists = $oc4->query("SHOW TABLES LIKE '" . $oc4->real_escape_string($full) . "'");
		if ($exists && $exists->num_rows) {
			$oc4->query('TRUNCATE TABLE `' . $full . '`');
			echo "TRUNCATE $full\n";
		}
	}
	$oc4->query('SET FOREIGN_KEY_CHECKS=1');
}

function copyTable(mysqli $oc3, mysqli $oc4, string $table, array $options = []): int {
	$src = PREFIX . $table;
	$dst = PREFIX . $table;
	$langCol = $options['lang_col'] ?? null;
	$defaults = $options['defaults'] ?? [];
	$transform = $options['transform'] ?? null;

	if (!tableExists($oc3, $src) || !tableExists($oc4, $dst)) {
		echo "SKIP $table (missing table)\n";
		return 0;
	}

	$srcCols = cols($oc3, $src);
	$dstCols = cols($oc4, $dst);
	$common = array_values(array_intersect($srcCols, $dstCols));

	if (!$common) {
		echo "SKIP $table (no common columns)\n";
		return 0;
	}

	// Ensure defaults for OC4-only required-ish fields when inserting
	$insertCols = $common;
	foreach (array_keys($defaults) as $dcol) {
		if (in_array($dcol, $dstCols, true) && !in_array($dcol, $insertCols, true)) {
			$insertCols[] = $dcol;
		}
	}

	$colList = '`' . implode('`, `', $insertCols) . '`';
	$placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
	$types = str_repeat('s', count($insertCols));

	$sql = "INSERT INTO `$dst` ($colList) VALUES ($placeholders)";
	$stmt = $oc4->prepare($sql);
	if (!$stmt) {
		fwrite(STDERR, "prepare $dst failed: {$oc4->error}\n");
		return 0;
	}

	$select = 'SELECT `' . implode('`, `', $common) . '` FROM `' . $src . '`';
	$res = $oc3->query($select, MYSQLI_USE_RESULT);
	if (!$res) {
		fwrite(STDERR, "select $src failed: {$oc3->error}\n");
		return 0;
	}

	$copied = 0;
	$skipped = 0;

	while ($row = $res->fetch_assoc()) {
		if ($langCol && isset($row[$langCol])) {
			$mapped = mapLang((int)$row[$langCol]);
			if ($mapped === null) {
				$skipped++;
				continue;
			}
			$row[$langCol] = (string)$mapped;
		}

		if ($transform) {
			$row = $transform($row);
			if ($row === null) {
				$skipped++;
				continue;
			}
		}

		$values = [];
		foreach ($insertCols as $col) {
			if (array_key_exists($col, $row)) {
				$values[] = $row[$col];
			} elseif (array_key_exists($col, $defaults)) {
				$values[] = $defaults[$col];
			} else {
				$values[] = null;
			}
		}

		$stmt->bind_param($types, ...$values);
		if (!$stmt->execute()) {
			fwrite(STDERR, "insert $dst failed: {$stmt->error}\n");
			break;
		}
		$copied++;
		if ($copied % 1000 === 0) {
			echo "  $table: $copied...\n";
		}
	}

	$res->free();
	$stmt->close();
	echo "OK $table: $copied rows" . ($skipped ? " (skipped $skipped)" : '') . "\n";
	return $copied;
}

function copySpecialsAsDiscount(mysqli $oc3, mysqli $oc4): int {
	if (!tableExists($oc3, PREFIX . 'product_special')) {
		echo "SKIP product_special→discount (no source)\n";
		return 0;
	}

	$res = $oc3->query(
		'SELECT product_id, customer_group_id, priority, price, date_start, date_end
		 FROM `' . PREFIX . 'product_special`'
	);
	$stmt = $oc4->prepare(
		'INSERT INTO `' . PREFIX . 'product_discount`
		 (`product_id`, `customer_group_id`, `quantity`, `priority`, `price`, `type`, `special`, `date_start`, `date_end`)
		 VALUES (?, ?, 1, ?, ?, \'F\', 1, ?, ?)'
	);

	$copied = 0;
	while ($row = $res->fetch_assoc()) {
		$product_id = (int)$row['product_id'];
		$customer_group_id = (int)$row['customer_group_id'];
		$priority = (int)$row['priority'];
		$price = (float)$row['price'];
		$date_start = (string)$row['date_start'];
		$date_end = (string)$row['date_end'];
		$stmt->bind_param('iiidss', $product_id, $customer_group_id, $priority, $price, $date_start, $date_end);
		if ($stmt->execute()) {
			$copied++;
		}
	}
	$stmt->close();
	echo "OK product_special→discount: $copied rows\n";
	return $copied;
}

function copySeoUrl(mysqli $oc3, mysqli $oc4): int {
	$res = $oc3->query('SELECT store_id, language_id, query, keyword FROM `' . PREFIX . 'seo_url`');
	$stmt = $oc4->prepare(
		'INSERT INTO `' . PREFIX . 'seo_url` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`)
		 VALUES (?, ?, ?, ?, ?, 0)'
	);
	$copied = 0;
	$skipped = 0;

	while ($row = $res->fetch_assoc()) {
		$lang = mapLang((int)$row['language_id']);
		if ($lang === null) {
			$skipped++;
			continue;
		}

		$query = (string)$row['query'];
		if (!preg_match('/^([a-z_]+)=(.+)$/', $query, $m)) {
			$skipped++;
			continue;
		}

		$key = $m[1];
		$value = $m[2];
		if (!in_array($key, ['product_id', 'category_id', 'manufacturer_id', 'information_id'], true)) {
			$skipped++;
			continue;
		}

		$store_id = (int)$row['store_id'];
		$keyword = (string)$row['keyword'];
		$stmt->bind_param('iisss', $store_id, $lang, $key, $value, $keyword);
		if ($stmt->execute()) {
			$copied++;
		}
		if ($copied % 2000 === 0 && $copied) {
			echo "  seo_url: $copied...\n";
		}
	}

	$stmt->close();
	echo "OK seo_url: $copied rows (skipped $skipped)\n";
	return $copied;
}

echo "== Truncate OC4 catalog ==\n";
truncateOc4($oc4);

echo "\n== Copy dictionaries ==\n";
copyTable($oc3, $oc4, 'attribute_group');
copyTable($oc3, $oc4, 'attribute_group_description', ['lang_col' => 'language_id']);
copyTable($oc3, $oc4, 'attribute');
copyTable($oc3, $oc4, 'attribute_description', ['lang_col' => 'language_id']);

copyTable($oc3, $oc4, 'option', ['defaults' => ['validation' => '']]);
copyTable($oc3, $oc4, 'option_description', ['lang_col' => 'language_id']);
copyTable($oc3, $oc4, 'option_value');
copyTable($oc3, $oc4, 'option_value_description', ['lang_col' => 'language_id']);

copyTable($oc3, $oc4, 'manufacturer');
copyTable($oc3, $oc4, 'manufacturer_to_store');

echo "\n== Copy categories ==\n";
copyTable($oc3, $oc4, 'category');
copyTable($oc3, $oc4, 'category_description', ['lang_col' => 'language_id']);
copyTable($oc3, $oc4, 'category_path');
copyTable($oc3, $oc4, 'category_to_store');
copyTable($oc3, $oc4, 'category_to_layout');

echo "\n== Copy products ==\n";
copyTable($oc3, $oc4, 'product', [
	'defaults' => [
		'master_id' => '0',
		'variant'   => '',
		'override'  => '',
		'rating'    => '0',
	],
]);
copyTable($oc3, $oc4, 'product_description', ['lang_col' => 'language_id']);
copyTable($oc3, $oc4, 'product_to_store');
copyTable($oc3, $oc4, 'product_to_category');
copyTable($oc3, $oc4, 'product_to_layout');
copyTable($oc3, $oc4, 'product_attribute', ['lang_col' => 'language_id']);
copyTable($oc3, $oc4, 'product_option');
copyTable($oc3, $oc4, 'product_option_value');
copyTable($oc3, $oc4, 'product_discount');
// OC4 stores specials in product_discount with special=1 (no product_special table)
copySpecialsAsDiscount($oc3, $oc4);
copyTable($oc3, $oc4, 'product_image');
copyTable($oc3, $oc4, 'product_related');
copyTable($oc3, $oc4, 'product_reward');

echo "\n== Copy SEO URLs ==\n";
copySeoUrl($oc3, $oc4);

echo "\n== Copy WT Filter index ==\n";
foreach ([
	'wt_filter_option',
	'wt_filter_option_value',
	'wt_filter_option_to_category',
	'wt_filter_option_to_store',
	'wt_filter_option_value_to_product',
] as $t) {
	copyTable($oc3, $oc4, $t);
}
foreach ([
	'wt_filter_option_description',
	'wt_filter_option_value_description',
	'wt_filter_seo_page',
] as $t) {
	copyTable($oc3, $oc4, $t, ['lang_col' => 'language_id']);
}

echo "\n== Totals OC4 ==\n";
foreach (['product', 'category', 'manufacturer', 'seo_url', 'product_option', 'wt_filter_option_value_to_product'] as $t) {
	if (!tableExists($oc4, PREFIX . $t)) {
		continue;
	}
	$r = $oc4->query('SELECT COUNT(*) c FROM `' . PREFIX . $t . '`');
	$row = $r->fetch_assoc();
	echo "$t: {$row['c']}\n";
}

echo "\nDone. Sync images: rsync -a opencart3.doc/image/catalog/fashion/ test/image/catalog/fashion/\n";
