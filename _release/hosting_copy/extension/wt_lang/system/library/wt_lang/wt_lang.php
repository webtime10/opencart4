<?php
/**
 * WT Lang — multilanguage URL prefixes + hreflang (OpenCart 4).
 */
class WtLang {
	/** @var \Opencart\System\Engine\Registry */
	private $registry;

	/** @var \Opencart\System\Engine\Config */
	private $config;

	/** @var \Opencart\System\Library\Request */
	private $request;

	/** @var \Opencart\System\Library\DB */
	private $db;

	/** @var array<string, array<string, mixed>> */
	private array $languages = [];

	/** @var array<string, string> */
	private array $prefix_to_code = [];

	public function __construct(\Opencart\System\Engine\Registry $registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');
		$this->request = $registry->get('request');
		$this->db = $registry->get('db');

		$this->loadLanguages();
	}

	public function isActive(): bool {
		return (bool)$this->config->get('module_wt_lang_status') && !empty($this->languages);
	}

	private function loadLanguages(): void {
		if (!(bool)$this->config->get('module_wt_lang_status')) {
			return;
		}

		$settings = $this->config->get('module_wt_lang_language');

		if (!is_array($settings)) {
			$settings = [];
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE `status` = '1' ORDER BY `sort_order`, `name`");

		foreach ($query->rows as $row) {
			$code = $row['code'];
			$lang = isset($settings[$code]) && is_array($settings[$code]) ? $settings[$code] : [];

			if (empty($lang['status'])) {
				continue;
			}

			$prefix = isset($lang['prefix']) ? trim(strtolower((string)$lang['prefix']), '/') : '';
			$hreflang = !empty($lang['hreflang']) ? (string)$lang['hreflang'] : substr($code, 0, 2);

			$image = $this->config->get('config_url') . 'catalog/language/' . $code . '/' . $code . '.png';

			if (!empty($row['extension'])) {
				$image = $this->config->get('config_url') . 'extension/' . $row['extension'] . '/catalog/language/' . $code . '/' . $code . '.png';
			}

			$this->languages[$code] = [
				'language_id' => (int)$row['language_id'],
				'code'        => $code,
				'name'        => $row['name'],
				'image'       => $image,
				'prefix'      => $prefix,
				'hreflang'    => $hreflang,
				'default'     => !empty($lang['default']),
			];

			if ($prefix !== '') {
				$this->prefix_to_code[$prefix] = $code;
			}
		}
	}

	public function detectRoute(): void {
		if (!$this->isActive() || $this->isAssetRequest()) {
			return;
		}

		$route_path = '';

		if (isset($this->request->get['_route_'])) {
			$route_path = trim((string)$this->request->get['_route_'], '/');
		}

		$parts = ($route_path !== '') ? explode('/', $route_path) : [];
		$first = isset($parts[0]) ? strtolower($parts[0]) : '';

		$target_code = null;

		if ($first !== '' && isset($this->prefix_to_code[$first])) {
			$target_code = $this->prefix_to_code[$first];
			array_shift($parts);
			$new_route = implode('/', $parts);

			if ($new_route === '') {
				unset($this->request->get['_route_'], $_GET['_route_']);
			} else {
				$this->request->get['_route_'] = $new_route;
				$_GET['_route_'] = $new_route;
			}
		} else {
			$target_code = $this->getDefaultCode();
		}

		if ($target_code) {
			$this->applyLanguage($target_code);
		}
	}

	public function rewrite(string $link): string {
		if (!$this->isActive() || $this->shouldSkipLink($link)) {
			return $link;
		}

		$code = (string)($this->request->get['language'] ?? $this->config->get('config_language'));

		if (!$code || !isset($this->languages[$code])) {
			return $link;
		}

		$prefix = $this->languages[$code]['prefix'];

		$link = str_replace('&amp;', '&', $link);
		$parsed = parse_url($link);

		if (!isset($parsed['scheme'], $parsed['host'])) {
			return $link;
		}

		$path = $parsed['path'] ?? '/';

		if (str_contains($path, 'index.php')) {
			$path = str_replace('/index.php', '', $path);
		}

		$query = [];

		if (!empty($parsed['query'])) {
			parse_str($parsed['query'], $query);
		}

		unset($query['language']);

		$path = $this->stripKnownPrefixes($path);

		if ($prefix !== '') {
			$base = rtrim($path, '/');

			if ($base === '' || $base === '/') {
				$path = '/' . $prefix . '/';
			} else {
				$path = '/' . $prefix . $base;
			}
		} elseif ($path === '') {
			$path = '/';
		}

		$port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
		$query_string = $query ? '?' . str_replace('&', '&amp;', http_build_query($query)) : '';
		$fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

		return $parsed['scheme'] . '://' . $parsed['host'] . $port . $path . $query_string . $fragment;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getSwitcherLinks(): array {
		if (!$this->isActive()) {
			return [];
		}

		$route = $this->request->get['route'] ?? 'common/home';
		$params = $this->request->get;
		unset($params['route'], $params['_route_'], $params['language']);

		$current = (string)($this->request->get['language'] ?? $this->config->get('config_language'));
		$links = [];

		foreach ($this->languages as $code => $lang) {
			$links[] = [
				'code'    => $code,
				'name'    => $lang['name'],
				'image'   => $lang['image'],
				'href'    => $this->buildLanguageUrl($code, (string)$route, $params),
				'current' => ($code === $current),
			];
		}

		return $links;
	}

	public function addHreflangLinks(): void {
		if (!$this->isActive() || !(bool)$this->config->get('module_wt_lang_hreflang')) {
			return;
		}

		$route = $this->request->get['route'] ?? 'common/home';

		if ($route === 'error/not_found') {
			return;
		}

		$params = $this->request->get;
		unset($params['route'], $params['_route_'], $params['language']);

		$document = $this->registry->get('document');
		$default_code = $this->getDefaultCode();

		foreach ($this->languages as $code => $lang) {
			$href = $this->buildLanguageUrl($code, (string)$route, $params);

			if (method_exists($document, 'addHreflang')) {
				$document->addHreflang($href, $lang['hreflang']);
			} else {
				$document->addLink($href, 'alternate');
			}
		}

		if ((bool)$this->config->get('module_wt_lang_xdefault') && $default_code) {
			$href = $this->buildLanguageUrl($default_code, (string)$route, $params);

			if (method_exists($document, 'addHreflang')) {
				$document->addHreflang($href, 'x-default');
			}
		}
	}

	public function buildLanguageUrl(string $code, string $route, array $params = []): string {
		if (!isset($this->languages[$code])) {
			return $this->registry->get('url')->link('common/home', 'language=' . $this->config->get('config_language'));
		}

		$saved = [
			'request_language' => $this->request->get['language'] ?? null,
			'config_language'  => $this->config->get('config_language'),
			'config_language_id' => $this->config->get('config_language_id'),
		];

		$this->request->get['language'] = $code;
		$this->config->set('config_language', $code);
		$this->config->set('config_language_id', $this->languages[$code]['language_id']);

		unset($params['route'], $params['_route_'], $params['language']);
		$params['language'] = $code;

		$link = $this->registry->get('url')->link($route, urldecode(http_build_query($params)));

		if ($saved['request_language'] !== null) {
			$this->request->get['language'] = $saved['request_language'];
		} else {
			unset($this->request->get['language']);
		}

		$this->config->set('config_language', $saved['config_language']);
		$this->config->set('config_language_id', $saved['config_language_id']);

		return $link;
	}

	public function getLanguageSwitcherLabel(): string {
		$code = (string)($this->request->get['language'] ?? $this->config->get('config_language'));
		$files = [
			DIR_EXTENSION . 'wt_lang/catalog/language/' . $code . '/module/wt_lang.php',
			DIR_LANGUAGE . $code . '/common/language.php',
		];

		foreach ($files as $file) {
			if (!is_file($file)) {
				continue;
			}

			$_ = [];

			require($file);

			if (!empty($_['text_language'])) {
				return (string)$_['text_language'];
			}
		}

		return 'Language';
	}

	private function getDefaultCode(): ?string {
		foreach ($this->languages as $code => $lang) {
			if ($lang['default']) {
				return $code;
			}
		}

		$codes = array_keys($this->languages);

		return $codes ? $codes[0] : null;
	}

	private function applyLanguage(string $code): void {
		if (!isset($this->languages[$code])) {
			return;
		}

		$lang = $this->languages[$code];

		$this->request->get['language'] = $code;
		$_GET['language'] = $code;

		$this->config->set('config_language_id', $lang['language_id']);
		$this->config->set('config_language', $code);

		$language = new \Opencart\System\Library\Language($code);
		$language->addPath(DIR_LANGUAGE);
		$language->addPath('extension/wt_lang', DIR_EXTENSION . 'wt_lang/catalog/language/');
		$language->load('default', '', $code);

		$this->registry->set('language', $language);

		if (isset($this->request->server['HTTP_HOST'])) {
			setcookie('language', $code, time() + 60 * 60 * 24 * 30, '/', (string)$this->request->server['HTTP_HOST']);
		}
	}

	private function stripKnownPrefixes(string $path): string {
		$path = '/' . trim($path, '/');

		if ($path === '/') {
		 return '/';
		}

		foreach ($this->prefix_to_code as $prefix => $code) {
			if ($path === '/' . $prefix || $path === '/' . $prefix . '/') {
				return '/';
			}

			if (str_starts_with($path, '/' . $prefix . '/')) {
				$stripped = substr($path, strlen($prefix) + 1);

				return $stripped ?: '/';
			}
		}

		return $path;
	}

	private function shouldSkipLink(string $link): bool {
		if (stripos($link, '/admin') !== false) {
			return true;
		}

		if (stripos($link, 'route=extension/payment') !== false || stripos($link, 'route=api/') !== false) {
			return true;
		}

		foreach (['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico', '.woff', '.woff2', '.ttf'] as $ext) {
			if (stripos($link, $ext) !== false) {
				return true;
			}
		}

		return false;
	}

	private function isAssetRequest(): bool {
		$uri = $this->request->server['REQUEST_URI'] ?? '';

		return (bool)preg_match('/\.(css|js|png|jpe?g|gif|webp|ico|svg|woff2?|ttf)(\?|$)/i', $uri);
	}
}
