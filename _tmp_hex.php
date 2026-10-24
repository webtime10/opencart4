<?php
$c = file_get_contents('C:/OSPanel64/home/opencart4.loc/extension/plum_store/catalog/view/stylesheet/plum_store/_custom.css');
$lines = explode("\n", $c);
for ($i = 0; $i < 12; $i++) {
	$line = $lines[$i] ?? '';
	echo ($i + 1) . ': [' . $line . '] hex=' . bin2hex($line) . PHP_EOL;
}

echo "---- benefits ----\n";
$c2 = file_get_contents('C:/OSPanel64/home/opencart4.loc/extension/wt_benefits/catalog/view/stylesheet/wt_benefits.css');
$lines = explode("\n", $c2);
for ($i = 0; $i < 16; $i++) {
	$line = $lines[$i] ?? '';
	echo ($i + 1) . ': [' . $line . '] hex=' . bin2hex($line) . PHP_EOL;
}
