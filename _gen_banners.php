<?php
declare(strict_types=1);

$root = __DIR__ . '/extension';

function w(string $path, string $content): void {
	$dir = dirname($path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}
	file_put_contents($path, $content);
	echo "W $path\n";
}

function adminLang(string $name, string $locale): string {
	$en = [
		'heading_title' => $name,
		'text_extension' => 'Extensions',
		'text_success' => "Success: You have modified {$name}!",
		'text_edit' => "Edit {$name}",
		'text_enabled' => 'Enabled',
		'text_disabled' => 'Disabled',
		'text_items' => 'Banners',
		'text_item' => 'Banner',
		'text_no_items' => 'No banners yet. Click Add banner.',
		'entry_name' => 'Module Name',
		'entry_status' => 'Status',
		'entry_title' => 'Title',
		'entry_description' => 'Description',
		'entry_image' => 'Image',
		'entry_percent' => 'Percent / badge',
		'entry_product' => 'Product link',
		'entry_sort_order' => 'Sort Order',
		'help_percent' => 'Example: -40% or 40. Leave empty to hide badge.',
		'help_product' => 'Type at least 2 letters to search products.',
		'button_item_add' => 'Add banner',
		'button_remove' => 'Remove',
		'button_save' => 'Save',
		'button_back' => 'Back',
		'error_permission' => "Warning: You do not have permission to modify {$name}!",
		'error_name' => 'Module Name must be between 3 and 64 characters!',
	];
	$ru = [
		'heading_title' => $name,
		'text_extension' => 'Расширения',
		'text_success' => "Готово: модуль {$name} сохранён!",
		'text_edit' => "Редактирование {$name}",
		'text_enabled' => 'Включено',
		'text_disabled' => 'Выключено',
		'text_items' => 'Баннеры',
		'text_item' => 'Баннер',
		'text_no_items' => 'Баннеров пока нет. Нажмите «Добавить баннер».',
		'entry_name' => 'Название модуля',
		'entry_status' => 'Статус',
		'entry_title' => 'Заголовок',
		'entry_description' => 'Описание',
		'entry_image' => 'Фото',
		'entry_percent' => 'Проценты / бейдж',
		'entry_product' => 'Ссылка на товар',
		'entry_sort_order' => 'Порядок',
		'help_percent' => 'Пример: -40% или 40. Пусто — без бейджа.',
		'help_product' => 'Введите минимум 2 буквы для поиска товара.',
		'button_item_add' => 'Добавить баннер',
		'button_remove' => 'Удалить',
		'button_save' => 'Сохранить',
		'button_back' => 'Назад',
		'error_permission' => "Нет прав на изменение {$name}!",
		'error_name' => 'Название модуля должно быть от 3 до 64 символов!',
	];
	$uk = [
		'heading_title' => $name,
		'text_extension' => 'Розширення',
		'text_success' => "Готово: модуль {$name} збережено!",
		'text_edit' => "Редагування {$name}",
		'text_enabled' => 'Увімкнено',
		'text_disabled' => 'Вимкнено',
		'text_items' => 'Банери',
		'text_item' => 'Банер',
		'text_no_items' => 'Банерів ще немає. Натисніть «Додати банер».',
		'entry_name' => 'Назва модуля',
		'entry_status' => 'Статус',
		'entry_title' => 'Заголовок',
		'entry_description' => 'Опис',
		'entry_image' => 'Фото',
		'entry_percent' => 'Відсотки / бейдж',
		'entry_product' => 'Посилання на товар',
		'entry_sort_order' => 'Порядок',
		'help_percent' => 'Приклад: -40% або 40. Порожньо — без бейджа.',
		'help_product' => 'Введіть мінімум 2 літери для пошуку товару.',
		'button_item_add' => 'Додати банер',
		'button_remove' => 'Видалити',
		'button_save' => 'Зберегти',
		'button_back' => 'Назад',
		'error_permission' => "Немає прав на зміну {$name}!",
		'error_name' => 'Назва модуля має бути від 3 до 64 символів!',
	];
	$map = ['en-gb' => $en, 'ru-ru' => $ru, 'uk-ua' => $uk][$locale] ?? $en;
	$out = "<?php\n";
	foreach ($map as $k => $v) {
		$out .= "\$_['{$k}'] = " . var_export($v, true) . ";\n";
	}
	return $out;
}

$modules = [
	['code' => 'wt_mini_banner', 'ns' => 'WtMiniBanner', 'class' => 'WtMiniBanner', 'name' => 'WT Mini Banner', 'kind' => 'mini', 'desc' => 'Home side mini banners.'],
	['code' => 'wt_big_banner', 'ns' => 'WtBigBanner', 'class' => 'WtBigBanner', 'name' => 'WT Big Banner', 'kind' => 'big', 'desc' => 'Home big slider banners.'],
];

foreach ($modules as $m) {
	$code = $m['code'];
	$base = "$root/$code";
	$route = "extension/{$code}/module/{$code}";
	$modKey = "{$code}.{$code}";

	w("$base/install.json", json_encode([
		'name' => $m['name'],
		'description' => $m['desc'],
		'version' => '1.0.0',
		'author' => 'WT',
		'link' => 'https://opencart4.loc/',
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

	foreach (['en-gb', 'ru-ru', 'uk-ua'] as $loc) {
		w("$base/admin/language/{$loc}/module/{$code}.php", adminLang($m['name'], $loc));
	}

	// catalog language stub (optional strings)
	w("$base/catalog/language/en-gb/module/{$code}.php", "<?php\n\$_['text_more'] = 'Details';\n");
	w("$base/catalog/language/ru-ru/module/{$code}.php", "<?php\n\$_['text_more'] = 'Подробнее';\n");
	w("$base/catalog/language/uk-ua/module/{$code}.php", "<?php\n\$_['text_more'] = 'Детальніше';\n");

	$adminCtrl = <<<PHP
<?php
namespace Opencart\\Admin\\Controller\\Extension\\{$m['ns']}\\Module;

class {$m['class']} extends \\Opencart\\System\\Engine\\Controller {
	private string \$route = '{$route}';

	public function index(): void {
		\$this->load->language(\$this->route);
		\$this->document->setTitle(\$this->language->get('heading_title'));

		\$data['breadcrumbs'] = [];
		\$data['breadcrumbs'][] = ['text' => \$this->language->get('text_home'), 'href' => \$this->url->link('common/dashboard', 'user_token=' . \$this->session->data['user_token'])];
		\$data['breadcrumbs'][] = ['text' => \$this->language->get('text_extension'), 'href' => \$this->url->link('marketplace/extension', 'user_token=' . \$this->session->data['user_token'] . '&type=module')];

		if (!isset(\$this->request->get['module_id'])) {
			\$data['breadcrumbs'][] = ['text' => \$this->language->get('heading_title'), 'href' => \$this->url->link(\$this->route, 'user_token=' . \$this->session->data['user_token'])];
			\$data['save'] = \$this->url->link(\$this->route . '.save', 'user_token=' . \$this->session->data['user_token']);
		} else {
			\$data['breadcrumbs'][] = ['text' => \$this->language->get('heading_title'), 'href' => \$this->url->link(\$this->route, 'user_token=' . \$this->session->data['user_token'] . '&module_id=' . \$this->request->get['module_id'])];
			\$data['save'] = \$this->url->link(\$this->route . '.save', 'user_token=' . \$this->session->data['user_token'] . '&module_id=' . \$this->request->get['module_id']);
		}

		\$data['back'] = \$this->url->link('marketplace/extension', 'user_token=' . \$this->session->data['user_token'] . '&type=module');
		\$data['user_token'] = \$this->session->data['user_token'];

		\$module_info = [];
		if (isset(\$this->request->get['module_id'])) {
			\$this->load->model('setting/module');
			\$module_info = \$this->model_setting_module->getModule(\$this->request->get['module_id']);
		}

		\$data['name'] = \$module_info['name'] ?? '';
		\$data['status'] = \$module_info['status'] ?? 1;
		\$data['module_id'] = isset(\$this->request->get['module_id']) ? (int)\$this->request->get['module_id'] : 0;

		\$this->load->model('tool/image');
		\$this->load->model('catalog/product');
		\$placeholder = \$this->model_tool_image->resize('no_image.png', 100, 100);
		\$data['placeholder'] = \$placeholder;

		\$items = [];
		foreach ((\$module_info['item'] ?? []) as \$item) {
			\$image = \$item['image'] ?? '';
			\$thumb = \$placeholder;
			if (\$image && is_file(DIR_IMAGE . \$image)) {
				\$thumb = \$this->model_tool_image->resize(\$image, 100, 100) ?: (HTTP_CATALOG . 'image/' . \$image);
			}
			\$product_id = (int)(\$item['product_id'] ?? 0);
			\$product_name = (string)(\$item['product_name'] ?? '');
			if (\$product_id && \$product_name === '') {
				\$product = \$this->model_catalog_product->getProduct(\$product_id);
				\$product_name = \$product['name'] ?? '';
			}
			\$items[] = [
				'title' => (string)(\$item['title'] ?? ''),
				'description' => (string)(\$item['description'] ?? ''),
				'image' => \$image,
				'thumb' => \$thumb,
				'percent' => (string)(\$item['percent'] ?? ''),
				'product_id' => \$product_id,
				'product_name' => \$product_name,
				'sort_order' => (int)(\$item['sort_order'] ?? 0),
				'status' => isset(\$item['status']) ? (int)\$item['status'] : 1,
			];
		}
		usort(\$items, static fn(\$a, \$b) => \$a['sort_order'] <=> \$b['sort_order']);
		\$data['items'] = \$items;

		\$data['header'] = \$this->load->controller('common/header');
		\$data['column_left'] = \$this->load->controller('common/column_left');
		\$data['footer'] = \$this->load->controller('common/footer');
		\$this->response->setOutput(\$this->load->view('extension/{$code}/module/{$code}', \$data));
	}

	public function save(): void {
		\$this->load->language(\$this->route);
		\$json = [];
		if (!\$this->user->hasPermission('modify', \$this->route)) {
			\$json['error']['warning'] = \$this->language->get('error_permission');
		}
		\$post_info = \$this->request->post + ['module_id' => 0, 'name' => '', 'status' => 0, 'item' => []];
		if (!oc_validate_length(\$post_info['name'], 3, 64)) {
			\$json['error']['name'] = \$this->language->get('error_name');
		}
		if (!\$json) {
			\$this->load->model('setting/module');
			if (!(int)\$post_info['module_id']) {
				\$json['module_id'] = \$this->model_setting_module->addModule('{$modKey}', \$post_info);
			} else {
				\$this->model_setting_module->editModule((int)\$post_info['module_id'], \$post_info);
			}
			\$json['success'] = \$this->language->get('text_success');
		}
		\$this->response->addHeader('Content-Type: application/json');
		\$this->response->setOutput(json_encode(\$json));
	}
}
PHP;
	w("$base/admin/controller/module/{$code}.php", $adminCtrl);

	$catCtrl = <<<PHP
<?php
namespace Opencart\\Catalog\\Controller\\Extension\\{$m['ns']}\\Module;

class {$m['class']} extends \\Opencart\\System\\Engine\\Controller {
	public function index(array \$setting = []): string {
		if (isset(\$setting['status']) && !(int)\$setting['status']) {
			return '';
		}

		\$this->load->language('extension/{$code}/module/{$code}');
		\$this->load->model('tool/image');
		\$lang = (string)\$this->config->get('config_language');

		\$items = [];
		\$raw = \$setting['item'] ?? [];
		usort(\$raw, static function (\$a, \$b) {
			return ((int)(\$a['sort_order'] ?? 0)) <=> ((int)(\$b['sort_order'] ?? 0));
		});

		foreach (\$raw as \$item) {
			if (isset(\$item['status']) && !(int)\$item['status']) {
				continue;
			}
			\$image = (string)(\$item['image'] ?? '');
			\$thumb = '';
			if (\$image && is_file(DIR_IMAGE . \$image)) {
				\$thumb = HTTP_SERVER . 'image/' . str_replace(' ', '%20', \$image);
			}
			\$product_id = (int)(\$item['product_id'] ?? 0);
			\$href = '#';
			if (\$product_id) {
				\$href = \$this->url->link('product/product', 'language=' . \$lang . '&product_id=' . \$product_id);
			}
			\$percent = trim((string)(\$item['percent'] ?? ''));
			if (\$percent !== '' && \$percent[0] !== '-' && ctype_digit(str_replace('%', '', \$percent))) {
				\$percent = '-' . rtrim(\$percent, '%') . '%';
			}
			\$items[] = [
				'title' => (string)(\$item['title'] ?? ''),
				'description' => (string)(\$item['description'] ?? ''),
				'image' => \$thumb,
				'percent' => \$percent,
				'href' => \$href,
			];
		}

		if (!\$items) {
			return '';
		}

		\$data['items'] = \$items;
		\$data['text_more'] = \$this->language->get('text_more');

		return \$this->load->view('extension/{$code}/module/{$code}', \$data);
	}
}
PHP;
	w("$base/catalog/controller/module/{$code}.php", $catCtrl);

	if ($m['kind'] === 'mini') {
		$catTwig = <<<'TWIG'
{% for item in items %}
<a class="home-tile{% if item.percent %} home-tile--dark{% endif %}" href="{{ item.href }}"{% if item.image %} style="background-image:url('{{ item.image }}')"{% endif %}>
  {% if item.percent %}<span class="home-tile-badge">{{ item.percent }}</span>{% endif %}
  {% if item.title %}<strong>{{ item.title }}</strong>{% endif %}
  {% if item.description %}<span>{{ item.description }}</span>{% endif %}
</a>
{% endfor %}
TWIG;
	} else {
		$catTwig = <<<'TWIG'
<div class="home-slider" data-home-slider>
  <button type="button" class="home-slider-nav is-prev" data-home-prev aria-label="Prev">‹</button>
  <div class="home-slider-track">
    {% for item in items %}
    <article class="home-slide{% if loop.first %} is-active{% endif %}"{% if item.image %} style="background-image:url('{{ item.image }}')"{% endif %}>
      <div class="home-slide-body">
        {% if item.percent %}<small>{{ item.percent }}</small>{% endif %}
        {% if item.title %}<h2>{{ item.title }}</h2>{% endif %}
        {% if item.description %}<p>{{ item.description }}</p>{% endif %}
        <a class="btn btn-primary btn-sm" href="{{ item.href }}">{{ text_more }}</a>
      </div>
    </article>
    {% endfor %}
  </div>
  <button type="button" class="home-slider-nav is-next" data-home-next aria-label="Next">›</button>
</div>
<script>
(function ($) {
  var $root = $("[data-home-slider]").last();
  if (!$root.length || $root.data("wtBound")) return;
  $root.data("wtBound", 1);
  var $slides = $root.find(".home-slide");
  var i = 0;
  function go(n) {
    i = (n + $slides.length) % $slides.length;
    $slides.removeClass("is-active").eq(i).addClass("is-active");
  }
  $root.on("click", "[data-home-prev]", function () { go(i - 1); });
  $root.on("click", "[data-home-next]", function () { go(i + 1); });
})(jQuery);
</script>
TWIG;
	}
	w("$base/catalog/view/template/module/{$code}.twig", $catTwig);

	$adminTwig = file_get_contents(__DIR__ . '/_banner_admin.twig');
	w("$base/admin/view/template/module/{$code}.twig", $adminTwig);
}

echo "DONE\n";