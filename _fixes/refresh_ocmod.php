<?php
/**
 * CLI refresh of OCMOD (same idea as marketplace/modification.refresh).
 */
require_once dirname(__DIR__) . '/admin/config.php';

$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
$mysqli->set_charset('utf8mb4');

// Clear old generated files
$path = [DIR_EXTENSION . 'ocmod/*'];
$files = [];
while ($path) {
	$next = array_shift($path);
	foreach (glob($next) ?: [] as $file) {
		if (is_dir($file)) {
			$path[] = $file . '/*';
		}
		$files[] = $file;
	}
}
rsort($files);
foreach ($files as $file) {
	if ($file === DIR_EXTENSION . 'ocmod/index.html') {
		continue;
	}
	if (is_file($file)) {
		unlink($file);
	} elseif (is_dir($file)) {
		@rmdir($file);
	}
}

$xmls = [];
foreach (glob(DIR_SYSTEM . '*.ocmod.xml') ?: [] as $file) {
	$xmls[] = file_get_contents($file);
}

$q = $mysqli->query('SELECT xml FROM `' . DB_PREFIX . 'modification` WHERE `status` = 1');
while ($row = $q->fetch_assoc()) {
	$xmls[] = $row['xml'];
}

$original = [];
$modification = [];
$log = [];

foreach ($xmls as $xml) {
	if ($xml === '' || $xml === null) {
		continue;
	}

	$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = false;
	if (!@$dom->loadXml($xml)) {
		$log[] = 'BAD XML skipped';
		continue;
	}

	$log[] = 'MOD: ' . $dom->getElementsByTagName('name')->item(0)->textContent;
	$file_nodes = $dom->getElementsByTagName('modification')->item(0)->getElementsByTagName('file');

	foreach ($file_nodes as $file_node) {
		$operations = $file_node->getElementsByTagName('operation');
		$paths = explode('|', str_replace('\\', '/', $file_node->getAttribute('path')));

		foreach ($paths as $file) {
			$path = '';
			if (substr($file, 0, 7) === 'catalog') {
				$path = DIR_CATALOG . substr($file, 8);
			}
			if (substr($file, 0, 5) === 'admin') {
				$path = DIR_APPLICATION . substr($file, 6);
			}
			if (substr($file, 0, 9) === 'extension') {
				$path = DIR_EXTENSION . substr($file, 10);
			}
			if (substr($file, 0, 6) === 'system') {
				$path = DIR_SYSTEM . substr($file, 7);
			}
			if (!$path) {
				continue;
			}

			foreach (glob($path, GLOB_BRACE) ?: [] as $real) {
				if (substr($real, 0, strlen(DIR_APPLICATION)) === DIR_APPLICATION) {
					$key = 'admin/' . substr($real, strlen(DIR_APPLICATION));
				} elseif (substr($real, 0, strlen(DIR_CATALOG)) === DIR_CATALOG) {
					$key = 'catalog/' . substr($real, strlen(DIR_CATALOG));
				} elseif (substr($real, 0, strlen(DIR_EXTENSION)) === DIR_EXTENSION) {
					$key = 'extension/' . substr($real, strlen(DIR_EXTENSION));
				} elseif (substr($real, 0, strlen(DIR_SYSTEM)) === DIR_SYSTEM) {
					$key = 'system/' . substr($real, strlen(DIR_SYSTEM));
				} else {
					continue;
				}

				if (!isset($modification[$key])) {
					$content = file_get_contents($real);
					$modification[$key] = preg_replace("~\r?\n~", "\n", $content);
					$original[$key] = $modification[$key];
					$log[] = 'FILE: ' . $key;
				}

				foreach ($operations as $operation) {
					$error = $operation->getAttribute('error');
					$search_el = $operation->getElementsByTagName('search')->item(0);
					$add_el = $operation->getElementsByTagName('add')->item(0);
					if (!$search_el || !$add_el) {
						continue;
					}

					$search = $search_el->textContent;
					if (!$search_el->getAttribute('trim') || $search_el->getAttribute('trim') === 'true') {
						$search = trim($search);
					}
					$add = $add_el->textContent;
					$position = $add_el->getAttribute('position') ?: 'replace';
					$offset = (int)$add_el->getAttribute('offset');
					$index = $search_el->getAttribute('index');

					if ($search_el->getAttribute('regex') === 'true') {
						$result = preg_replace($search, $add, $modification[$key]);
						if ($result !== null && $result !== $modification[$key]) {
							$modification[$key] = $result;
							$log[] = 'REGEX OK';
						} else {
							$log[] = 'REGEX NOT FOUND (' . $error . ') ' . $key;
						}
						continue;
					}

					$pos = strpos($modification[$key], $search);
					if ($pos === false) {
						$log[] = 'NOT FOUND (' . $error . ') in ' . $key;
						continue;
					}

					// handle index attribute (0-based match)
					if ($index !== '') {
						$i = 0;
						$p = -1;
						$start = 0;
						while (($p = strpos($modification[$key], $search, $start)) !== false) {
							if ((string)$i === (string)$index) {
								$pos = $p;
								break;
							}
							$start = $p + strlen($search);
							$i++;
							$pos = false;
						}
						if ($pos === false) {
							$log[] = 'INDEX NOT FOUND in ' . $key;
							continue;
						}
					}

					if ($position === 'replace') {
						$modification[$key] = substr_replace($modification[$key], $add, $pos, strlen($search));
					} elseif ($position === 'before') {
						$line_pos = $pos;
						if ($offset) {
							// approximate: move by newlines
						}
						$modification[$key] = substr_replace($modification[$key], $add, $pos, 0);
					} elseif ($position === 'after') {
						$modification[$key] = substr_replace($modification[$key], $add, $pos + strlen($search), 0);
					}
					$log[] = 'OK ' . $position . ' ' . $key;
				}
			}
		}
	}
	$log[] = '----------------------------------------------------------------';
}

foreach ($modification as $key => $value) {
	if ($original[$key] === $value) {
		continue;
	}
	$path = '';
	foreach (explode('/', dirname($key)) as $directory) {
		$path .= '/' . $directory;
		if (!is_dir(DIR_EXTENSION . 'ocmod' . $path)) {
			mkdir(DIR_EXTENSION . 'ocmod' . $path, 0777, true);
		}
	}
	file_put_contents(DIR_EXTENSION . 'ocmod/' . $key, $value);
	echo "wrote ocmod/$key\n";
}

file_put_contents(DIR_LOGS . 'ocmod.log', implode("\n", $log));
echo "log entries: " . count($log) . "\n";
echo (is_file(DIR_EXTENSION . 'ocmod/admin/controller/common/column_left.php') ? "column_left PATCHED\n" : "column_left NOT patched\n");
