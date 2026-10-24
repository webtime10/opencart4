<?php
namespace Opencart\Catalog\Controller\Extension\PlumStore\Event;

/**
 * Event-контроллер темы Plum Store (OpenCart 4).
 *
 * Это НЕ замена catalog/controller/common/header.php.
 * Ядро по-прежнему само собирает $data (logo, menu, cart, language…).
 *
 * Здесь только классический Event:
 * 1) в oc_event зарегистрированы триггеры:
 *    - catalog/view/common/header/before → theme.header
 *    - catalog/view/common/footer/before → theme.footer
 * 2) перед отрисовкой twig мы:
 *    - меняем $route → какой шаблон грузить (наш из extension/plum_store)
 *    - дописываем в $data пути к CSS / JS / иконкам и нужные ссылки
 *
 * В OpenCart 3 такого файла нет: там тема подхватывается папкой
 * catalog/view/theme/имя_темы/. В OC4 тема — extension, поэтому нужен Event.
 */
class Theme extends \Opencart\System\Engine\Controller {

	/**
	 * Тема активна, только если:
	 * - в настройках темы включён status (theme_plum_store_status = 1)
	 * - в System → Settings выбрана тема config_theme = plum_store
	 */
	private function enabled(): bool {
		return (bool)$this->config->get('theme_plum_store_status')
			&& $this->config->get('config_theme') === 'plum_store';
	}

	/**
	 * Собирает категории как common/menu и рендерит vmenu-шаблон темы.
	 */
	private function renderCatalogMenu(): string {
		$this->load->language('common/menu');
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');

		$lang = $this->config->get('config_language');
		$data['categories'] = [];
		$data['text_all'] = $this->language->get('text_all');
		$data['text_category'] = $this->language->get('text_category');

		foreach ($this->model_catalog_category->getCategories(0) as $category) {
			$children_data = [];

			foreach ($this->model_catalog_category->getCategories((int)$category['category_id']) as $child) {
				$filter_data = [
					'filter_category_id'  => $child['category_id'],
					'filter_sub_category' => true
				];

				$children_data[] = [
					'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
					'href' => $this->url->link('product/category', 'language=' . $lang . '&path=' . $category['category_id'] . '_' . $child['category_id'])
				];
			}

			$data['categories'][] = [
				'category_id' => (int)$category['category_id'],
				'name'        => $category['name'],
				'children'    => $children_data,
				'href'        => $this->url->link('product/category', 'language=' . $lang . '&path=' . $category['category_id'])
			];
		}

		return $this->load->view('extension/plum_store/common/menu', $data);
	}

	/**
	 * Срабатывает на: catalog/view/common/header/before
	 * Action в БД:     extension/plum_store/event/theme.header
	 *
	 * Параметры передаются ПО ССЫЛКЕ — их можно менять до рендера:
	 *
	 * @param string       $route  какой view грузить (было: common/header)
	 * @param array<mixed> $data   данные для Twig (уже заполнены ядром)
	 * @param string       $code   служебное (обычно не трогаем)
	 * @param string       $output для after-событий; на before обычно пусто
	 */
	public function header(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		// Другая тема или plum_store выключен — не вмешиваемся
		if (!$this->enabled()) {
			return;
		}

		// Подмена шаблона:
		// вместо catalog/view/template/common/header.twig
		// → extension/plum_store/catalog/view/template/common/header.twig
		// или header_2.twig при theme_plum_store_header_layout = 2
		$layout = (string)$this->config->get('theme_plum_store_header_layout');
		$route = ($layout === '2')
			? 'extension/plum_store/common/header_2'
			: 'extension/plum_store/common/header';

		// Menu 1 (vertical catalog overlay) via WT Menu slot 1; fallback to category tree
		try {
			$wt_menu = $this->load->controller('extension/wt_menu/module/wt_menu', ['__slot' => 1]);
		} catch (\Throwable $e) {
			$wt_menu = '';
		}

		// Горизонтальный meganav в каталог не подходит — только vmenu
		if ($wt_menu === '' || strpos($wt_menu, 'data-vmenu') === false) {
			$wt_menu = $this->renderCatalogMenu();
		}

		$data['wt_menu'] = $wt_menu;
		$data['menu'] = $data['wt_menu'];

		// Menu 2 (horizontal meganav) from layout position "menu"
		try {
			$data['menu_bar'] = $this->load->controller('extension/plum_store/common/menu_bar');
		} catch (\Throwable $e) {
			$data['menu_bar'] = '';
		}

		$lang = $this->config->get('config_language');

		// Ассеты темы — в twig как {{ plum_stylesheet }}, {{ plum_icons }} и т.д.
		$data['plum_bootstrap'] = 'extension/plum_store/catalog/view/stylesheet/plum_store/bootstrap.min.css';
		$data['plum_stylesheet'] = 'extension/plum_store/catalog/view/stylesheet/plum_store/setka.css?v=oc4home2';
		$data['plum_theme_js'] = 'extension/plum_store/catalog/view/javascript/plum_store/theme.js?v=oc4theme2';
		$data['plum_bootstrap_js'] = 'extension/plum_store/catalog/view/javascript/plum_store/bootstrap.bundle.min.js';
		$data['plum_icons'] = 'extension/plum_store/catalog/view/image/plum_store/icons.svg';

		// Ссылки под кастомную вёрстку шапки (поиск, сравнение)
		$data['search_action'] = $this->url->link('product/search', 'language=' . $lang);
		$data['search_value'] = (string)($this->request->get['search'] ?? '');
		$data['compare'] = $this->url->link('product/compare', 'language=' . $lang);

		$scheme = (string)$this->config->get('theme_plum_store_color_scheme');
		$allowed = ['theme-dark', 'theme-plum', 'theme-light', 'theme-ocean', 'theme-olive', 'theme-emerald'];

		if (!in_array($scheme, $allowed, true)) {
			$scheme = 'theme-plum';
		}

		$data['plum_color_scheme'] = $scheme;
		$data['plum_theme_logo'] = '';
		$data['plum_informations'] = [];

		try {
			$this->load->model('extension/plum_store/theme/plum_store');
			$data['plum_informations'] = $this->model_extension_plum_store_theme_plum_store->getTopInformations();
		} catch (\Throwable $e) {
			$data['plum_informations'] = [];
		}

		if (empty($data['contact'])) {
			$data['contact'] = $this->url->link('information/contact', 'language=' . $lang);
		}

		$current_route = (string)($this->request->get['route'] ?? '');
		$data['plum_is_home'] = ($current_route === '' || $current_route === 'common/home');

		$logo = trim(str_replace('\\', '/', (string)$this->config->get('theme_plum_store_logo')));
		$logo = ltrim($logo, '/');

		if (str_starts_with($logo, 'image/')) {
			$logo = substr($logo, 6);
		}

		$basename = strtolower(basename($logo));

		// Пусто / no_image → в twig встроенный SVG Plum
		if ($logo === '' || in_array($basename, ['no_image.png', 'no_image.jpg', 'placeholder.png'], true)) {
			return;
		}

		// Как в админке — так и на витрине
		$base = rtrim((string)$this->config->get('config_url'), '/') . '/';
		$data['plum_theme_logo'] = $base . 'image/' . str_replace(' ', '%20', $logo);
	}

	/**
	 * Срабатывает на: catalog/view/common/footer/before
	 * Action в БД:     extension/plum_store/event/theme.footer
	 *
	 * Аналогично header: только подмена footer.twig на свой из пакета темы.
	 */
	public function footer(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		if (!$this->enabled()) {
			return;
		}

		// → extension/plum_store/catalog/view/template/common/footer.twig
		$route = 'extension/plum_store/common/footer';

		// Фильтр футера: если хотя бы одна статья с bottom=1 — показываем только их
		if (!empty($data['informations']) && is_array($data['informations'])) {
			try {
				$this->load->model('extension/plum_store/theme/plum_store');
				$bottom_ids = $this->model_extension_plum_store_theme_plum_store->getBottomIds();

				if ($bottom_ids) {
					$data['informations'] = array_values(array_filter(
						$data['informations'],
						static function ($item) use ($bottom_ids) {
							$id = (int)($item['information_id'] ?? 0);
							return $id > 0 && isset($bottom_ids[$id]);
						}
					));
				}
			} catch (\Throwable $e) {
				// leave as-is
			}
		}
	}

	/**
	 * catalog/view/common/language/before
	 */
	public function language(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		if (!$this->enabled()) {
			return;
		}

		$route = 'extension/plum_store/common/language';
		$data['plum_icons'] = 'extension/plum_store/catalog/view/image/plum_store/icons.svg';
	}

	/**
	 * catalog/view/common/currency/before
	 */
	public function currency(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		if (!$this->enabled()) {
			return;
		}

		$route = 'extension/plum_store/common/currency';
		$data['plum_icons'] = 'extension/plum_store/catalog/view/image/plum_store/icons.svg';
	}

	/**
	 * catalog/view/common/column_left/before
	 * Класс menu-vertical-wt — якорь стилей бокового vmenu из макета.
	 */
	public function columnLeft(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		if (!$this->enabled()) {
			return;
		}

		$route = 'extension/plum_store/common/column_left';
	}

	/**
	 * catalog/view/common/home/before
	 * Два блока: сцена (меню+баннеры) и content_bottom (benefits и т.п.).
	 */
	public function home(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		if (!$this->enabled()) {
			return;
		}

		$route = 'extension/plum_store/common/home';
	}
}
