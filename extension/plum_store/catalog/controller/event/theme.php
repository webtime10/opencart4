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
		$route = 'extension/plum_store/common/header';

		$lang = $this->config->get('config_language');

		// Ассеты темы — в twig как {{ plum_stylesheet }}, {{ plum_icons }} и т.д.
		$data['plum_bootstrap'] = 'extension/plum_store/catalog/view/stylesheet/plum_store/bootstrap.min.css';
		$data['plum_stylesheet'] = 'extension/plum_store/catalog/view/stylesheet/plum_store/setka.css';
		$data['plum_theme_js'] = 'extension/plum_store/catalog/view/javascript/plum_store/theme.js';
		$data['plum_bootstrap_js'] = 'extension/plum_store/catalog/view/javascript/plum_store/bootstrap.bundle.min.js';
		$data['plum_icons'] = 'extension/plum_store/catalog/view/image/plum_store/icons.svg';

		// Ссылки под кастомную вёрстку шапки (поиск, сравнение)
		$data['search_action'] = $this->url->link('product/search', 'language=' . $lang);
		$data['compare'] = $this->url->link('product/compare', 'language=' . $lang);

		$scheme = (string)$this->config->get('theme_plum_store_color_scheme');
		$allowed = ['theme-dark', 'theme-plum', 'theme-light', 'theme-ocean', 'theme-olive', 'theme-emerald'];

		if (!in_array($scheme, $allowed, true)) {
			$scheme = 'theme-plum';
		}

		$data['plum_color_scheme'] = $scheme;
		$data['plum_theme_logo'] = '';

		$current_route = (string)($this->request->get['route'] ?? '');
		$data['plum_is_home'] = ($current_route === '' || $current_route === 'common/home');

		// 1) Логотип темы (Extensions → Themes → Plum Store)
		$logo = trim((string)$this->config->get('theme_plum_store_logo'));

		// 2) Если в теме не задан — обычный логотип магазина (System → Settings)
		if ($logo === '') {
			$logo = trim((string)$this->config->get('config_logo'));
		}

		// 3) Уже готовый URL из ядра (header.php)
		if ($logo === '' && !empty($data['logo']) && is_string($data['logo'])) {
			$data['plum_theme_logo'] = $data['logo'];
		} elseif ($logo !== '') {
			$logo = ltrim(str_replace('\\', '/', $logo), '/');
			// Убираем префикс image/, если сохранили полный путь
			if (str_starts_with($logo, 'image/')) {
				$logo = substr($logo, 6);
			}

			$absolute = DIR_IMAGE . $logo;
			// is_file может врать на кириллице в Windows — всё равно отдаём URL, если путь задан
			if (is_file($absolute) || $logo !== '') {
				$base = rtrim((string)$this->config->get('config_url'), '/') . '/';
				$data['plum_theme_logo'] = $base . 'image/' . str_replace(' ', '%20', $logo);
			}
		}
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
	}
}
