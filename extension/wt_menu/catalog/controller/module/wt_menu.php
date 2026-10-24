<?php
namespace Opencart\Catalog\Controller\Extension\WtMenu\Module;

class WtMenu extends \Opencart\System\Engine\Controller {
	public function index(array $setting = []): string {
		// Header catalog uses __slot; layout modules do not — sidebar wrapper only for layout
		$from_slot = !empty($setting['__slot']);

		if ($from_slot) {
			$setting = $this->getModuleBySlot((int)$setting['__slot']);
		} elseif (!$setting || (!isset($setting['status']) && empty($setting['tree']) && empty($setting['item']))) {
			$setting = $this->getModuleBySlot(1);
		}

		if (empty($setting['status'])) {
			return '';
		}

		$tree = [];

		if (!empty($setting['tree']) && is_array($setting['tree'])) {
			$tree = $setting['tree'];
		} elseif (!empty($setting['item']) && is_array($setting['item'])) {
			foreach ($setting['item'] as $row) {
				if (empty($row['name'])) {
					continue;
				}

				$tree[] = [
					'id'       => isset($row['id']) ? (int)$row['id'] : 0,
					'name'     => $row['name'],
					'href'     => !empty($row['href']) ? $row['href'] : '#',
					'status'   => 1,
					'image'    => '',
					'children' => []
				];
			}
		}

		$this->load->language('common/menu');
		$this->load->model('tool/image');

		$data['items'] = $this->prepareItems($tree);
		$data['text_all'] = $this->language->get('text_all');

		if (!$data['items']) {
			return '';
		}

		$slot = isset($setting['menu_slot']) ? (int)$setting['menu_slot'] : 1;

		if ($slot === 2) {
			$data['items'] = $this->limitHorizontalTree($data['items'], 5, 5);

			return $this->load->view('extension/wt_menu/module/wt_menu_horizontal', $data);
		}

		$data['in_layout'] = !$from_slot;

		return $this->load->view('extension/wt_menu/module/wt_menu', $data);
	}

	private function limitHorizontalTree(array $items, int $maxTop = 5, int $maxCols = 3): array {
		$items = array_slice($items, 0, $maxTop);

		foreach ($items as &$item) {
			if (!empty($item['children']) && is_array($item['children'])) {
				$item['children'] = array_slice($item['children'], 0, $maxCols);
			}
		}
		unset($item);

		return $items;
	}

	private function getModuleBySlot(int $slot): array {
		$slot = ($slot === 2) ? 2 : 1;

		$query = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = 'wt_menu.wt_menu' ORDER BY `module_id` ASC");

		foreach ($query->rows as $row) {
			$setting = json_decode($row['setting'], true);

			if (!is_array($setting) || empty($setting['status'])) {
				continue;
			}

			$item_slot = isset($setting['menu_slot']) ? (int)$setting['menu_slot'] : 1;

			if ($item_slot !== 2) {
				$item_slot = 1;
			}

			if ($item_slot === $slot) {
				return $setting;
			}
		}

		// Нет модуля для запрошенного слота — не подставлять чужой (горизонтальный в каталог и наоборот)
		return [];
	}

	private function prepareItems(array $tree, int $depth = 1): array {
		$result = [];
		$placeholders = ['добавить', 'add', 'название', 'title', 'название пункта', 'item title'];
		$home_names = ['главная', 'home', 'головна'];

		foreach ($tree as $item) {
			if (isset($item['status']) && !(int)$item['status']) {
				continue;
			}

			$name = isset($item['name']) ? trim($item['name']) : '';
			$name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
			$name = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $name));

			if (strpos($name, '>') !== false) {
				$parts = preg_split('/\s*>\s*/u', $name);
				$name = trim((string)end($parts));
			}

			if ($name === '' || in_array(oc_strtolower($name), $placeholders, true)) {
				continue;
			}

			// «Главная» / Home не показываем в вертикальном каталоге
			if (in_array(oc_strtolower($name), $home_names, true)) {
				continue;
			}

			$href_raw = html_entity_decode((string)($item['href'] ?? ''), ENT_QUOTES, 'UTF-8');

			if (preg_match('#(?:^|[?&])route=common/home(?:&|$)#i', str_replace('\\/', '/', $href_raw))) {
				continue;
			}

			$id = !empty($item['id']) ? (int)$item['id'] : (count($result) + 1);
			$image = $item['image'] ?? '';
			$thumb = '';

			if ($image && is_file(DIR_IMAGE . $image)) {
				if ($depth >= 2) {
					$base = !empty($this->request->server['HTTPS'])
						? $this->config->get('config_ssl')
						: $this->config->get('config_url');

					if (!$base) {
						$base = HTTP_SERVER;
					}

					$thumb = rtrim((string)$base, '/') . '/image/' . str_replace(' ', '%20', $image);
				} else {
					$thumb = $this->model_tool_image->resize($image, 48, 48);
				}
			}

			$children = [];

			if (!empty($item['children']) && is_array($item['children'])) {
				$children = $this->prepareItems($item['children'], $depth + 1);
			}

			$result[] = [
				'id'       => $id,
				'name'     => $name,
				'href'     => $this->resolveHref($item['href'] ?? ''),
				'image'    => $image,
				'thumb'    => $thumb,
				'children' => $children
			];
		}

		return $result;
	}

	private function resolveHref(string $href): string {
		$href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
		$href = trim($href);

		if ($href === '' || $href === '#') {
			return '#';
		}

		if (preg_match('#^https?://#i', $href) || strpos($href, '//') === 0) {
			return $href;
		}

		$lang = (string)$this->config->get('config_language');

		if (strpos($href, 'index.php?') === 0) {
			$query = substr($href, strlen('index.php?'));
			parse_str($query, $params);

			$route = $params['route'] ?? 'common/home';
			unset($params['route']);

			if ($lang !== '' && !isset($params['language'])) {
				$params = ['language' => $lang] + $params;
			}

			return $this->url->link($route, http_build_query($params));
		}

		if (strpos($href, 'route=') === 0) {
			parse_str($href, $params);
			$route = $params['route'] ?? 'common/home';
			unset($params['route']);

			if ($lang !== '' && !isset($params['language'])) {
				$params = ['language' => $lang] + $params;
			}

			return $this->url->link($route, http_build_query($params));
		}

		return $href;
	}
}
