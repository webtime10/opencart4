<?php
/**
 * Seed OpenCart 4 catalog from setka4 category names.
 * 16 categories × 16 products, photos, attributes, options, galleries.
 * Run: php tools/seed_catalog_oc4.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

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
		throw new RuntimeException($m->error . "\nSQL: " . $sql);
	}
}
function esc(mysqli $m, string $s): string {
	return $m->real_escape_string($s);
}
function collectImages(string $dir): array {
	$out = [];
	if (!is_dir($dir)) {
		return $out;
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) {
		if ($f->isFile() && preg_match('/\.(jpe?g|png|webp|gif)$/i', $f->getFilename())) {
			$out[] = $f->getPathname();
		}
	}
	sort($out);
	return $out;
}

$enId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='en-gb'")->fetch_assoc()['language_id'];
$ukId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='uk-ua'")->fetch_assoc()['language_id'];
$langs = [$enId, $ukId];
echo "langs en={$enId} uk={$ukId}\n";

echo "== 1) Disable old categories ==\n";
q($m, "UPDATE {$p}category SET status=0");
echo "old categories disabled\n";

echo "== 2) Attributes ==\n";
$ag = $m->query("SELECT ag.attribute_group_id FROM {$p}attribute_group ag JOIN {$p}attribute_group_description agd ON ag.attribute_group_id=agd.attribute_group_id WHERE agd.language_id={$enId} AND agd.name='Specs' LIMIT 1")->fetch_assoc();
if ($ag) {
	$agId = (int)$ag['attribute_group_id'];
} else {
	q($m, "INSERT INTO {$p}attribute_group SET sort_order=10");
	$agId = (int)$m->insert_id;
	foreach ([$enId => 'Specs', $ukId => 'Характеристики'] as $lid => $name) {
		q($m, "INSERT INTO {$p}attribute_group_description SET attribute_group_id={$agId}, language_id={$lid}, name='" . esc($m, $name) . "'");
	}
}

$attrDefs = [
	'brand' => [$enId => 'Brand', $ukId => 'Бренд', 'sort' => 1],
	'color' => [$enId => 'Color', $ukId => 'Колір', 'sort' => 2],
	'warranty' => [$enId => 'Warranty', $ukId => 'Гарантія', 'sort' => 3],
	'material' => [$enId => 'Material', $ukId => 'Матеріал', 'sort' => 4],
];
$attrIds = [];
foreach ($attrDefs as $key => $def) {
	$r = $m->query("SELECT a.attribute_id FROM {$p}attribute a JOIN {$p}attribute_description ad ON a.attribute_id=ad.attribute_id WHERE a.attribute_group_id={$agId} AND ad.language_id={$enId} AND ad.name='" . esc($m, $def[$enId]) . "' LIMIT 1");
	if ($row = $r->fetch_assoc()) {
		$attrIds[$key] = (int)$row['attribute_id'];
	} else {
		q($m, "INSERT INTO {$p}attribute SET attribute_group_id={$agId}, sort_order={$def['sort']}");
		$aid = (int)$m->insert_id;
		$attrIds[$key] = $aid;
		foreach ([$enId, $ukId] as $lid) {
			q($m, "INSERT INTO {$p}attribute_description SET attribute_id={$aid}, language_id={$lid}, name='" . esc($m, $def[$lid]) . "'");
		}
	}
}
echo "attrs ok\n";

echo "== 3) Options Color/Size/Warranty + Memory ==\n";
$optColor = 4;
$optSize = 3;
$optWarranty = 2;

// Memory option
$mem = $m->query("SELECT o.option_id FROM {$p}option o JOIN {$p}option_description od ON o.option_id=od.option_id WHERE od.language_id={$enId} AND od.name='Memory' LIMIT 1")->fetch_assoc();
if ($mem) {
	$optMemory = (int)$mem['option_id'];
} else {
	q($m, "INSERT INTO `{$p}option` SET type='select', sort_order=5");
	$optMemory = (int)$m->insert_id;
	foreach ([$enId => 'Memory', $ukId => 'Пам\'ять'] as $lid => $name) {
		q($m, "INSERT INTO {$p}option_description SET option_id={$optMemory}, language_id={$lid}, name='" . esc($m, $name) . "'");
	}
	$memVals = [
		[$enId => '128 GB', $ukId => '128 ГБ'],
		[$enId => '256 GB', $ukId => '256 ГБ'],
		[$enId => '512 GB', $ukId => '512 ГБ'],
		[$enId => '1 TB', $ukId => '1 ТБ'],
	];
	$sort = 1;
	foreach ($memVals as $names) {
		q($m, "INSERT INTO {$p}option_value SET option_id={$optMemory}, image='', sort_order={$sort}");
		$ovid = (int)$m->insert_id;
		foreach ($names as $lid => $name) {
			q($m, "INSERT INTO {$p}option_value_description SET option_value_id={$ovid}, language_id={$lid}, option_id={$optMemory}, name='" . esc($m, $name) . "'");
		}
		$sort++;
	}
}

function optionValueIds(mysqli $m, string $p, int $optionId): array {
	$ids = [];
	$r = $m->query("SELECT option_value_id FROM {$p}option_value WHERE option_id={$optionId} ORDER BY sort_order, option_value_id");
	while ($row = $r->fetch_assoc()) {
		$ids[] = (int)$row['option_value_id'];
	}
	return $ids;
}

// Prefer a short color subset: Black, White, Blue, Silver, Red if present
$allColors = optionValueIds($m, $p, $optColor);
$colorPrefer = [];
$r = $m->query("SELECT ov.option_value_id, ovd.name FROM {$p}option_value ov JOIN {$p}option_value_description ovd ON ov.option_value_id=ovd.option_value_id WHERE ov.option_id={$optColor} AND ovd.language_id={$enId}");
$map = [];
while ($row = $r->fetch_assoc()) {
	$map[strtolower($row['name'])] = (int)$row['option_value_id'];
}
foreach (['black', 'white', 'blue', 'silver', 'red'] as $cn) {
	if (isset($map[$cn])) {
		$colorPrefer[] = $map[$cn];
	}
}
if (count($colorPrefer) < 3) {
	$colorPrefer = array_slice($allColors, 0, 5);
}
$sizeValues = array_slice(optionValueIds($m, $p, $optSize), 0, 4); // S M L XL
$warrantyValues = optionValueIds($m, $p, $optWarranty);
$memoryValues = optionValueIds($m, $p, $optMemory);
echo "options color=" . count($colorPrefer) . " size=" . count($sizeValues) . " warranty=" . count($warrantyValues) . " memory=" . count($memoryValues) . "\n";

echo "== 4) Images ==\n";
$seedDir = $root . '/image/catalog/seed';
$galleryDir = $seedDir . '/_gallery';
if (!is_dir($seedDir)) {
	mkdir($seedDir, 0777, true);
}
if (!is_dir($galleryDir)) {
	mkdir($galleryDir, 0777, true);
}

$fotoImgs = array_merge(
	collectImages($root . '/фото'),
	collectImages($root . '/setka4/img/product')
);
$demoImgs = array_merge(
	collectImages(dirname($root) . '/opencart3.loc/image/catalog/demo'),
	collectImages(dirname($root) . '/opencart3.loc/image/catalog/seed'),
	collectImages($root . '/setka4/img/product')
);
echo 'foto=' . count($fotoImgs) . ' demo=' . count($demoImgs) . "\n";
if (!$fotoImgs && !$demoImgs) {
	throw new RuntimeException('No images found');
}

$absPool = array_values(array_unique(array_merge($fotoImgs, $demoImgs)));
$relGallery = [];
foreach ($absPool as $i => $src) {
	$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'jpg';
	$name = 'g' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT) . '.' . $ext;
	$dest = $galleryDir . '/' . $name;
	if (!is_file($dest)) {
		@copy($src, $dest);
	}
	if (is_file($dest)) {
		$relGallery[] = 'catalog/seed/_gallery/' . $name;
	}
}
echo 'gallery=' . count($relGallery) . "\n";

function copySeedImage(string $src, string $seedDir, string $slug, int $index): string {
	$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'jpg';
	$relDir = 'catalog/seed/' . $slug;
	$absDir = $seedDir . '/' . $slug;
	if (!is_dir($absDir)) {
		mkdir($absDir, 0777, true);
	}
	$name = 'p' . str_pad((string)$index, 2, '0', STR_PAD_LEFT) . '.' . $ext;
	$dest = $absDir . '/' . $name;
	if (!@copy($src, $dest)) {
		throw new RuntimeException('copy failed: ' . $src);
	}
	return $relDir . '/' . $name;
}

echo "== 5) Categories (setka4) ==\n";
$categories = [
	['slug' => 'power', 'en' => 'Power & Charging', 'uk' => 'Енергоживлення', 'pool' => 'demo'],
	['slug' => 'kids', 'en' => 'Kids Products', 'uk' => 'Товари для дітей', 'pool' => 'demo'],
	['slug' => 'laptops', 'en' => 'Laptops & Computers', 'uk' => 'Ноутбуки і комп\'ютери', 'pool' => 'demo'],
	['slug' => 'parts', 'en' => 'PC Components', 'uk' => 'Комп\'ютерні комплектуючі', 'pool' => 'demo'],
	['slug' => 'phones', 'en' => 'Smartphones & Tablets', 'uk' => 'Смартфони та планшети', 'pool' => 'foto'],
	['slug' => 'tv', 'en' => 'TVs & Multimedia', 'uk' => 'Телевізори і мультимедіа', 'pool' => 'demo'],
	['slug' => 'gaming', 'en' => 'Gaming', 'uk' => 'Товари для геймерів', 'pool' => 'demo'],
	['slug' => 'office', 'en' => 'Office & Print', 'uk' => 'Все для друку та офісу', 'pool' => 'demo'],
	['slug' => 'network', 'en' => 'Networking', 'uk' => 'Мережеве обладнання', 'pool' => 'demo'],
	['slug' => 'audio', 'en' => 'Audio', 'uk' => 'Аудіо', 'pool' => 'demo'],
	['slug' => 'gadgets', 'en' => 'Gadgets', 'uk' => 'Гаджети', 'pool' => 'demo'],
	['slug' => 'appliances', 'en' => 'Home Appliances', 'uk' => 'Побутова техніка', 'pool' => 'demo'],
	['slug' => 'smarthome', 'en' => 'Smart Home & Security', 'uk' => 'Розумний дім та охорона', 'pool' => 'demo'],
	['slug' => 'tools', 'en' => 'Tools', 'uk' => 'Інструменти', 'pool' => 'demo'],
	['slug' => 'photo', 'en' => 'Photo & Video', 'uk' => 'Фото та відео', 'pool' => 'demo'],
	['slug' => 'auto', 'en' => 'Auto Accessories', 'uk' => 'Автотовари', 'pool' => 'demo'],
];

// product names: first 16 from OC3 pools
$productNamePools = [
	'power' => ['en' => ['GaN Charger 65W', 'Power Bank 20000mAh', 'USB-C Cable 2m', 'Wireless Pad 15W', 'Car Charger Dual', 'MagSafe Stand', 'PD Hub 100W', 'Solar Power Bank', 'Wall Adapter 30W', 'Cable Organizer Kit', 'Laptop Brick 90W', 'Multi Port Dock', 'Travel Adapter Kit', 'Qi2 Charging Stand', 'USB-C to Lightning', 'Battery Pack Slim'], 'uk' => ['Зарядка GaN 65Вт', 'Павербанк 20000мАш', 'Кабель USB-C 2м', 'Бездротова панель 15Вт', 'Автозарядка Dual', 'Стенд MagSafe', 'Хаб PD 100Вт', 'Сонячний павербанк', 'Блок 30Вт', 'Органайзер кабелів', 'Блок для ноутбука 90Вт', 'Док Multi Port', 'Тревел-адаптер', 'Стенд Qi2', 'USB-C — Lightning', 'Тонкий акумулятор']],
	'kids' => ['en' => ['Kids Tablet 10"', 'STEM Robot Kit', 'Learning Headphones', 'Interactive Globe', 'Coding Mouse', 'Puzzle Pad', 'Kids Smartwatch', 'Music Keyboard Mini', 'AR Flashcards', 'Soft Controller', 'Junior Camera', 'Math Trainer Pro', 'Story Projector', 'Build Blocks Smart', 'Drawing Tablet Kids', 'RC Mini Car'], 'uk' => ['Дитячий планшет 10"', 'STEM робот-набір', 'Навушники для навчання', 'Інтерактивний глобус', 'Кодинг-миша', 'Пазл-пад', 'Дитячий смарт-годинник', 'Міні-синтезатор', 'AR картки', 'М\'який геймпад', 'Дитяча камера', 'Тренажер математики', 'Проектор казок', 'Розумний конструктор', 'Графічний планшет', 'Міні RC машинка']],
	'laptops' => ['en' => ['Ultrabook 14 Air', 'Gaming Laptop 15', 'Business Notebook Pro', '2-in-1 Convertible', 'Workstation 16', 'Student Laptop Lite', 'Creator Laptop OLED', 'Mini PC Cube', 'All-in-One 24', 'Chromebook Go', 'Laptop Sleeve Kit', 'Cooling Pad X', 'USB-C Dock Pro', 'Mechanical KB Travel', 'Laptop Stand Alu', 'Wireless Mouse Silent'], 'uk' => ['Ультрабук 14 Air', 'Ігровий ноутбук 15', 'Бізнес-ноутбук Pro', 'Трансформер 2-в-1', 'Робоча станція 16', 'Студентський Lite', 'Ноутбук Creator OLED', 'Міні-ПК Cube', 'Моноблок 24', 'Chromebook Go', 'Чохол для ноутбука', 'Охолоджувальна підставка', 'Док USB-C Pro', 'Компактна мехклава', 'Алюм. підставка', 'Тиха миша']],
	'parts' => ['en' => ['Ryzen CPU Box', 'GeForce GPU 12GB', 'B650 Motherboard', 'DDR5 32GB Kit', 'NVMe 2TB Gen4', 'PSU 750W Gold', 'Tower Case Airflow', 'AIO Cooler 240', 'Wi-Fi 6E Card', 'Sound Card DAC', 'SATA SSD 1TB', 'ARGB Fan Pack', 'CPU Thermal Paste', 'PCIe Capture Card', 'RAM RGB 16GB', 'HDD 4TB NAS'], 'uk' => ['Процесор Ryzen BOX', 'Відеокарта 12ГБ', 'Материнка B650', 'DDR5 32ГБ комплект', 'NVMe 2ТБ Gen4', 'БЖ 750Вт Gold', 'Корпус Airflow', 'СВО 240', 'Адаптер Wi-Fi 6E', 'Звукова карта DAC', 'SATA SSD 1ТБ', 'Набір вентиляторів ARGB', 'Термопаста', 'Карта захоплення PCIe', 'ОЗП RGB 16ГБ', 'HDD 4ТБ NAS']],
	'phones' => ['en' => ['iPhone 17 Pro 1TB Silver', 'iPhone 17 Pro Natural', 'iPhone 17 256GB Sage', 'iPhone 17 256GB Lavender', 'iPhone 17 Mist Blue', 'iPhone 16 Pro Desert', 'Galaxy S25 Ultra', 'Galaxy A56 5G', 'Pixel 9 Pro', 'Pixel 9a', 'Xiaomi 15 Pro', 'Redmi Note 14', 'Nothing Phone 3', 'OnePlus 13', 'Motorola Edge 50', 'Honor Magic 7'], 'uk' => ['iPhone 17 Pro 1ТБ Silver', 'iPhone 17 Pro Natural', 'iPhone 17 256ГБ Sage', 'iPhone 17 256ГБ Lavender', 'iPhone 17 Mist Blue', 'iPhone 16 Pro Desert', 'Galaxy S25 Ultra', 'Galaxy A56 5G', 'Pixel 9 Pro', 'Pixel 9a', 'Xiaomi 15 Pro', 'Redmi Note 14', 'Nothing Phone 3', 'OnePlus 13', 'Motorola Edge 50', 'Honor Magic 7']],
	'tv' => ['en' => ['OLED TV 55"', 'QLED TV 65"', 'Smart TV 43" HD', 'Soundbar 3.1', 'Streaming Stick 4K', 'TV Wall Mount', 'HDMI 2.1 Cable', 'Media Player Box', 'Projector Full HD', 'Projector Screen 100"', 'TV Antenna Indoor', 'AV Receiver 7.2', 'Blu-ray Player', 'Remote Universal', 'TV Cleaning Kit', 'Apple TV 4K'], 'uk' => ['OLED ТВ 55"', 'QLED ТВ 65"', 'Smart TV 43" HD', 'Саундбар 3.1', 'Стрімер 4K', 'Кронштейн для ТВ', 'Кабель HDMI 2.1', 'Медіаплеєр', 'Проєктор Full HD', 'Екран 100"', 'Кімнатна антена', 'AV-ресивер 7.2', 'Blu-ray плеєр', 'Універсальний пульт', 'Набір для чищення ТВ', 'Apple TV 4K']],
	'gaming' => ['en' => ['Wireless Gamepad Pro', 'Racing Wheel Kit', 'Gaming Headset 7.1', 'RGB Keyboard TKL', 'Gaming Mouse 8K', 'Mousepad XL', 'Capture Card 4K', 'Console Stand Dual', 'Gaming Chair Lite', 'VR Headset Bundle', 'Streaming Mic Arm', 'Ring Light Stream', 'Gamepad Charger Dock', 'FPS Trigger Grips', 'Console Skin Pack', 'Gaming Glasses Blue'], 'uk' => ['Бездротовий геймпад Pro', 'Кермо з педалями', 'Гарнітура 7.1', 'Клавіатура TKL RGB', 'Миша 8K', 'Килимок XL', 'Карта захоплення 4K', 'Підставка під консоль', 'Ігрове крісло Lite', 'VR набір', 'Пантограф для мікрофона', 'Кільцева лампа', 'Док зарядки геймпадів', 'Тригери FPS', 'Скіни для консолі', 'Окуляри від синього світла']],
	'office' => ['en' => ['Laser Printer Mono', 'Ink Tank Printer', 'Document Scanner', 'Label Printer', 'Toner Cartridge BK', 'Paper A4 500', 'Stapler Electric', 'Desk Organizer Set', 'Webcam Conference', 'USB Headset Office', 'Whiteboard Magnetic', 'Laminator A4', 'Shredder Cross-Cut', 'Binding Machine', 'Presentation Remote', 'Ergo Keyboard'], 'uk' => ['Лазерний принтер Ч/Б', 'Струменевий із СНПЧ', 'Сканер документів', 'Принтер етикеток', 'Картридж BK', 'Папір A4 500', 'Електростеплер', 'Органайзер на стіл', 'Веб-камера для дзвінків', 'Офісна гарнітура', 'Магнітна дошка', 'Ламінатор A4', 'Шредер', 'Брошурувальник', 'Пульт для презентацій', 'Ергоклавіатура']],
	'network' => ['en' => ['Wi-Fi 6 Router', 'Mesh Wi-Fi 3-Pack', 'Gigabit Switch 8p', 'PoE Injector', 'Access Point AC', 'Range Extender', 'NAS 2-Bay', 'Cat6 Cable 10m', 'Fiber Media Converter', 'VPN Router Pro', 'LTE Modem USB', 'Network Rack 9U', 'Patch Panel 24', 'RJ45 Crimp Kit', 'Wi-Fi USB Adapter', 'Managed Switch 16p'], 'uk' => ['Роутер Wi-Fi 6', 'Mesh система 3 шт', 'Комутатор 8 портів', 'PoE інжектор', 'Точка доступу AC', 'Підсилювач сигналу', 'NAS 2 диски', 'Кабель Cat6 10м', 'Медіаконвертер', 'VPN-роутер Pro', 'LTE модем USB', 'Стійка 9U', 'Патч-панель 24', 'Кримпер набір', 'USB Wi-Fi адаптер', 'Керований світч 16п']],
	'audio' => ['en' => ['ANC Headphones', 'True Wireless Earbuds', 'Studio Monitor Pair', 'USB Microphone', 'Portable Speaker', 'DAC Amp Portable', 'IEMs Stage', 'Audio Interface 2i2', 'Soundbar Mini', 'Bluetooth Receiver', 'Vinyl Turntable', 'Headphone Amp Desk', 'XLR Cable Pair', 'Speaker Stands', 'Karaoke Mic Wireless', 'Car Speaker Set'], 'uk' => ['Навушники ANC', 'TWS навушники', 'Студійні монітори', 'USB мікрофон', 'Портативна колонка', 'Портативний DAC', 'Сценічні IEM', 'Аудіоінтерфейс 2i2', 'Міні-саундбар', 'BT приймач', 'Вініловий програвач', 'Підсилювач для навушників', 'Пара XLR кабелів', 'Стійки для колонок', 'Караоке мікрофон', 'Автоакустика']],
	'gadgets' => ['en' => ['Smart Ring Fitness', 'Action Camera 4K', 'E-Reader 7"', 'Translator Device', 'Laser Pointer Presenter', 'Pocket Projector', 'Digital Voice Recorder', 'GPS Tracker Mini', 'Smart Scale Wi-Fi', 'UV Sanitizer Box', 'Portable Fan USB', 'Selfie Stick Tripod', 'Card Reader 4-in-1', 'Magnetic Phone Mount', 'Cable MagSafe Pack', 'Smart Plug Duo'], 'uk' => ['Смарт-кільце', 'Екшн-камера 4K', 'Електронна книга 7"', 'Перекладач', 'Презентер лазерний', 'Кишеньковий проєктор', 'Диктофон', 'GPS трекер міні', 'Розумна вага Wi-Fi', 'УФ стерилізатор', 'USB вентилятор', 'Селфі-штатив', 'Картридер 4-в-1', 'Магнітний тримач', 'Набір MagSafe кабелів', 'Розумна розетка Duo']],
	'appliances' => ['en' => ['Robot Vacuum', 'Air Purifier HEPA', 'Humidifier Ultrasonic', 'Espresso Machine', 'Blender Pro 1200', 'Toaster 2-Slice', 'Electric Kettle Steel', 'Microwave 20L', 'Induction Cooktop', 'Hair Dryer Ionic', 'Steam Iron', 'Garment Steamer', 'Dishwasher Compact', 'Fridge Mini Bar', 'Heater Oil Radiator', 'Fan Tower Oscillating'], 'uk' => ['Робот-пилосос', 'Очищувач повітря HEPA', 'Зволожувач', 'Кавомашина', 'Блендер Pro 1200', 'Тостер на 2 скибки', 'Чайник сталевий', 'Мікрохвильовка 20л', 'Індукційна плита', 'Фен іонний', 'Парова праска', 'Відпарювач', 'Посудомийка компакт', 'Міні-холодильник', 'Масляний обігрівач', 'Баштовий вентилятор']],
	'smarthome' => ['en' => ['Smart Hub Zigbee', 'Door Sensor Pack', 'IP Camera Indoor', 'IP Camera Outdoor', 'Smart Bulb Color', 'Smart Switch Wall', 'Thermostat Wi-Fi', 'Smoke Detector Smart', 'Water Leak Sensor', 'Smart Lock Fingerprint', 'Siren Battery', 'Motion Sensor PIR', 'Smart Relay DIN', 'Video Doorbell', 'Curtain Motor Kit', 'IR Blaster Hub'], 'uk' => ['Хаб Zigbee', 'Датчики дверей', 'IP камера indoor', 'IP камера outdoor', 'Розумна лампа RGB', 'Розумний вимикач', 'Термостат Wi-Fi', 'Датчик диму', 'Датчик протікання', 'Замок з відбитком', 'Сирена', 'Датчик руху PIR', 'Реле DIN', 'Відеодомофон', 'Мотор для штор', 'IR хаб']],
	'tools' => ['en' => ['Cordless Drill 18V', 'Impact Driver Kit', 'Angle Grinder 125', 'Multitool Oscillating', 'Laser Level 360', 'Tape Measure 8m', 'Tool Bag Pro', 'Screwdriver Bit Set', 'Socket Set 108pcs', 'Heat Gun Digital', 'Soldering Station', 'Clamp Pack 4', 'Utility Knife Set', 'Work Light LED', 'Rotary Tool Kit', 'Jigsaw Corded'], 'uk' => ['Шурупокрут 18В', 'Імпульсний гвинтокрут', 'Болгарка 125', 'Мультитул', 'Лазерний рівень 360', 'Рулетка 8м', 'Сумка для інструменту', 'Біти набір', 'Головки 108 шт', 'Фен технічний', 'Паяльна станція', 'Струбцини 4 шт', 'Ніж будівельний', 'Ліхтар LED', 'Гравер набір', 'Лобзик']],
	'photo' => ['en' => ['Mirrorless Camera Kit', 'DSLR Body Only', 'Prime Lens 50mm', 'Zoom Lens 24-70', 'Tripod Carbon', 'Gimbal 3-Axis', 'LED Softbox Kit', 'Memory Card 256GB', 'Camera Bag Sling', 'ND Filter Set', 'External Flash TTL', 'Mic Shotgun', 'Monitor Field 5"', 'Battery Grip', 'Cleaning Kit Pro', 'Remote Intervalometer'], 'uk' => ['Бездзеркалка набір', 'Дзеркалка body', 'Об\'єктив 50мм', 'Зум 24-70', 'Штатив карбоновий', 'Стабілізатор 3 осі', 'Софтбокс LED', 'Карта пам\'яті 256ГБ', 'Сумка-слінг', 'ND фільтри', 'Спалах TTL', 'Мікрофон shotgun', 'Монітор 5"', 'Батарейний блок', 'Набір для чищення', 'Пульт інтервалометр']],
	'auto' => ['en' => ['Car Dash Cam Dual', 'OBD2 Scanner BT', 'Phone Mount Mag', 'Tire Inflator Portable', 'Jump Starter 2000A', 'Car Vacuum Mini', 'Seat Organizer', 'USB Hub Car', 'LED Interior Kit', 'Parking Sensor Set', 'Car Air Freshener Ion', 'Wireless CarPlay', 'FM Transmitter', 'Trunk Organizer', 'Steering Cover', 'Floor Mats Set'], 'uk' => ['Відеореєстратор Dual', 'OBD2 сканер BT', 'Тримач Mag', 'Компресор портативний', 'Пусковий пристрій', 'Автопилосос', 'Органайзер на сидіння', 'USB хаб в авто', 'LED салонний набір', 'Парктроніки', 'Іонізатор повітря', 'Wireless CarPlay', 'FM модулятор', 'Органайзер у багажник', 'Оплітка керма', 'Килимки комплект']],
];

$brands = ['Apex', 'Nova', 'Pulse', 'Orbit', 'Vertex', 'Nimbus', 'Quark', 'Lumen'];
$colorsAttr = [
	'en' => ['Black', 'Silver', 'Blue', 'White', 'Graphite'],
	'uk' => ['Чорний', 'Сріблястий', 'Синій', 'Білий', 'Графіт'],
];
$materials = [
	'en' => ['Plastic', 'Aluminum', 'Steel', 'Silicone', 'Glass'],
	'uk' => ['Пластик', 'Алюміній', 'Сталь', 'Силікон', 'Скло'],
];

$sortCat = 1;
$catIds = [];
foreach ($categories as $cat) {
	// reuse if already seeded by slug keyword
	$existing = $m->query("SELECT c.category_id FROM {$p}category c JOIN {$p}seo_url s ON s.`key`='category_id' AND s.value=c.category_id AND s.language_id={$enId} WHERE s.keyword='" . esc($m, $cat['slug']) . "' LIMIT 1")->fetch_assoc();
	if ($existing) {
		$cid = (int)$existing['category_id'];
		q($m, "UPDATE {$p}category SET status=1, sort_order={$sortCat} WHERE category_id={$cid}");
		$catIds[$cat['slug']] = $cid;
		echo "  reuse {$cat['slug']} id={$cid}\n";
		$sortCat++;
		continue;
	}

	q($m, "INSERT INTO {$p}category SET image='', parent_id=0, sort_order={$sortCat}, status=1");
	$cid = (int)$m->insert_id;
	$catIds[$cat['slug']] = $cid;

	foreach ([$enId => $cat['en'], $ukId => $cat['uk']] as $lid => $name) {
		$n = esc($m, $name);
		q($m, "INSERT INTO {$p}category_description SET category_id={$cid}, language_id={$lid}, name='{$n}', description='<p>{$n}</p>', meta_title='{$n}', meta_description='', meta_keyword=''");
	}
	q($m, "INSERT INTO {$p}category_to_store SET category_id={$cid}, store_id=0");
	q($m, "INSERT INTO {$p}category_path SET category_id={$cid}, path_id={$cid}, level=0");

	foreach ([$enId, $ukId] as $lid) {
		$kw = $cat['slug'] . ($lid === $ukId ? '-ua' : '');
		$ex = $m->query("SELECT 1 FROM {$p}seo_url WHERE store_id=0 AND language_id={$lid} AND keyword='" . esc($m, $kw) . "'")->num_rows;
		if (!$ex) {
			q($m, "INSERT INTO {$p}seo_url SET store_id=0, language_id={$lid}, `key`='category_id', value='{$cid}', keyword='" . esc($m, $kw) . "', sort_order=0");
		}
	}
	echo "  {$cat['slug']} id={$cid}\n";
	$sortCat++;
}

echo "== 6) Products (16 each) ==\n";
$created = 0;
$perCat = 16;

foreach ($categories as $cat) {
	$cid = $catIds[$cat['slug']];
	$have = (int)$m->query("SELECT COUNT(*) c FROM {$p}product_to_category WHERE category_id={$cid}")->fetch_assoc()['c'];
	if ($have >= $perCat) {
		echo "  {$cat['slug']}: already {$have}, skip\n";
		continue;
	}

	$pool = ($cat['pool'] === 'foto' && $fotoImgs) ? $fotoImgs : array_values(array_merge($fotoImgs ?: [], $demoImgs));
	if (!$pool) {
		$pool = $demoImgs ?: $fotoImgs;
	}

	$namesEn = $productNamePools[$cat['slug']]['en'];
	$namesUk = $productNamePools[$cat['slug']]['uk'];

	for ($i = 0; $i < $perCat; $i++) {
		$src = $pool[$i % count($pool)];
		$image = copySeedImage($src, $seedDir, $cat['slug'], $i + 1);
		$model = strtoupper($cat['slug']) . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
		$price = round(199 + ($i * 37) + (crc32($cat['slug'] . $i) % 500), 2);
		$qty = 15 + ($i % 40);
		$brand = $brands[$i % count($brands)];
		$colorIdx = $i % count($colorsAttr['en']);
		$matIdx = $i % count($materials['en']);

		q($m, "INSERT INTO {$p}product SET
			master_id=0,
			model='" . esc($m, $model) . "',
			sku='" . esc($m, $model) . "',
			upc='', ean='', jan='', isbn='', mpn='', location='',
			variant='', override='',
			quantity={$qty},
			stock_status_id=7,
			image='" . esc($m, $image) . "',
			manufacturer_id=0,
			shipping=1,
			price='{$price}',
			points=0,
			tax_class_id=0,
			date_available=CURDATE(),
			weight='0.50000000',
			weight_class_id=1,
			length='0.00000000', width='0.00000000', height='0.00000000',
			length_class_id=1,
			subtract=1,
			minimum=1,
			rating=0,
			sort_order=" . ($i + 1) . ",
			status=1,
			date_added=NOW(),
			date_modified=NOW()
		");
		$pid = (int)$m->insert_id;

		foreach ([
			$enId => ['name' => $namesEn[$i], 'desc' => '<p>' . $namesEn[$i] . ' — demo product for «' . $cat['en'] . '». Plum Store OC4.</p>'],
			$ukId => ['name' => $namesUk[$i], 'desc' => '<p>' . $namesUk[$i] . ' — демо-товар для «' . $cat['uk'] . '». Plum Store OC4.</p>'],
		] as $lid => $d) {
			$n = esc($m, $d['name']);
			$ds = esc($m, $d['desc']);
			q($m, "INSERT INTO {$p}product_description SET product_id={$pid}, language_id={$lid}, name='{$n}', description='{$ds}', tag='', meta_title='{$n}', meta_description='', meta_keyword=''");
		}

		q($m, "INSERT INTO {$p}product_to_store SET product_id={$pid}, store_id=0");
		q($m, "INSERT INTO {$p}product_to_category SET product_id={$pid}, category_id={$cid}");

		// gallery 4 images
		$start = ($created + $i) * 3;
		$added = 0;
		for ($g = 0; $g < count($relGallery) && $added < 4; $g++) {
			$rel = $relGallery[($start + $g) % count($relGallery)];
			if ($rel === $image) {
				continue;
			}
			q($m, "INSERT INTO {$p}product_image SET product_id={$pid}, image='" . esc($m, $rel) . "', sort_order=" . ($added + 1));
			$added++;
		}

		$attrValues = [
			'brand' => [$enId => $brand, $ukId => $brand],
			'color' => [$enId => $colorsAttr['en'][$colorIdx], $ukId => $colorsAttr['uk'][$colorIdx]],
			'warranty' => [$enId => '12 months', $ukId => '12 міс.'],
			'material' => [$enId => $materials['en'][$matIdx], $ukId => $materials['uk'][$matIdx]],
		];
		foreach ($attrValues as $key => $vals) {
			$aid = $attrIds[$key];
			foreach ($vals as $lid => $val) {
				q($m, "INSERT INTO {$p}product_attribute SET product_id={$pid}, attribute_id={$aid}, language_id={$lid}, text='" . esc($m, $val) . "'");
			}
		}

		// options
		$attach = [
			['option_id' => $optColor, 'required' => 1, 'values' => $colorPrefer, 'prices' => []],
			['option_id' => $optMemory, 'required' => 1, 'values' => $memoryValues, 'prices' => [0, 40, 90, 180]],
			['option_id' => $optWarranty, 'required' => 0, 'values' => $warrantyValues, 'prices' => [0, 25]],
			['option_id' => $optSize, 'required' => 0, 'values' => $sizeValues, 'prices' => []],
		];
		foreach ($attach as $spec) {
			$oid = (int)$spec['option_id'];
			$req = (int)$spec['required'];
			q($m, "INSERT INTO {$p}product_option SET product_id={$pid}, option_id={$oid}, value='', required={$req}");
			$poId = (int)$m->insert_id;
			foreach ($spec['values'] as $vi => $ovid) {
				$priceOpt = isset($spec['prices'][$vi]) ? (float)$spec['prices'][$vi] : 0.0;
				q($m, "INSERT INTO {$p}product_option_value SET
					product_option_id={$poId},
					product_id={$pid},
					option_id={$oid},
					option_value_id=" . (int)$ovid . ",
					quantity=50,
					subtract=0,
					price='{$priceOpt}',
					price_prefix='+',
					points=0,
					points_prefix='+',
					weight='0.00000000',
					weight_prefix='+'
				");
			}
		}

		// seo
		$baseKw = strtolower($cat['slug'] . '-' . ($i + 1));
		foreach ([$enId => $baseKw, $ukId => $baseKw . '-ua'] as $lid => $kw) {
			$ex = $m->query("SELECT 1 FROM {$p}seo_url WHERE store_id=0 AND language_id={$lid} AND keyword='" . esc($m, $kw) . "'")->num_rows;
			if (!$ex) {
				q($m, "INSERT INTO {$p}seo_url SET store_id=0, language_id={$lid}, `key`='product_id', value='{$pid}', keyword='" . esc($m, $kw) . "', sort_order=0");
			}
		}

		$created++;
	}
	echo "  {$cat['slug']}: {$perCat} products\n";
}

echo "\nDONE products={$created} categories=" . count($catIds) . "\n";

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
