<?php
namespace Opencart\Admin\Controller\Extension\WtMenu\Module;

class WtMenu extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_menu/module/wt_menu';

	public function index(): void {
		$this->load->language($this->route);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('../extension/wt_menu/admin/view/javascript/nestable/nestable.js');

		$this->load->model('setting/module');
		$this->load->model('tool/image');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'])
			];
			$data['save'] = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'])
			];
			$data['save'] = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id']);
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];

		$module_info = [];

		if (isset($this->request->get['module_id'])) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['name'] = $module_info['name'] ?? 'WT Menu';
		$data['status'] = $module_info['status'] ?? 1;
		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

		$menu_slot = isset($module_info['menu_slot']) ? (int)$module_info['menu_slot'] : 1;
		$data['menu_slot'] = ($menu_slot === 2) ? 2 : 1;

		$tree = [];

		if (!empty($module_info['tree']) && is_array($module_info['tree'])) {
			$tree = $this->filterPlaceholderItems($module_info['tree']);
		} elseif (!empty($module_info['item']) && is_array($module_info['item'])) {
			$id = 1;

			foreach ($module_info['item'] as $row) {
				$tree[] = [
					'id'       => $id++,
					'name'     => $row['name'] ?? '',
					'href'     => $row['href'] ?? '',
					'status'   => 1,
					'image'    => '',
					'children' => []
				];
			}
		}

		if (!$tree) {
			$tree = [
				[
					'id'       => 1,
					'name'     => 'Home',
					'href'     => 'index.php?route=common/home',
					'status'   => 1,
					'image'    => '',
					'children' => []
				]
			];
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 40, 40);

		if (!$data['placeholder']) {
			$data['placeholder'] = $this->model_tool_image->resize('placeholder.png', 40, 40);
		}

		$data['tree_html'] = $this->renderTreeHtml($tree);
		$data['item_row'] = $this->maxTreeId($tree) + 1;
		$data['item_count'] = $this->countTree($tree);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/wt_menu/module/wt_menu', $data));
	}

	public function save(): void {
		$this->load->language($this->route);

		$json = [];

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'module_id'  => 0,
			'name'       => '',
			'status'     => 0,
			'menu_slot'  => 1,
			'items'      => [],
			'tree_json'  => '[]',
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!$json) {
			$items_data = is_array($post_info['items']) ? $post_info['items'] : [];
			$tree_json = html_entity_decode((string)$post_info['tree_json'], ENT_QUOTES, 'UTF-8');
			$structure = json_decode($tree_json, true);

			if (!is_array($structure)) {
				$structure = [];
			}

			$save = [
				'name'      => $post_info['name'],
				'status'    => (int)$post_info['status'],
				'menu_slot' => ((int)$post_info['menu_slot'] === 2) ? 2 : 1,
				'tree'      => $this->buildTree($structure, $items_data)
			];

			$this->load->model('setting/module');

			$module_id = (int)$post_info['module_id'];

			if (!$module_id) {
				$json['module_id'] = $this->model_setting_module->addModule('wt_menu.wt_menu', $save);
			} else {
				$this->model_setting_module->editModule($module_id, $save);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		$this->load->model('user/user_group');

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $this->route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $this->route);
	}

	private function filterPlaceholderItems(array $tree): array {
		$result = [];
		$placeholders = ['добавить', 'add', 'название', 'title', 'название пункта', 'item title'];

		foreach ($tree as $item) {
			$name = isset($item['name']) ? trim($item['name']) : '';

			if ($name === '' || in_array(oc_strtolower($name), $placeholders, true)) {
				continue;
			}

			if (!empty($item['children']) && is_array($item['children'])) {
				$item['children'] = $this->filterPlaceholderItems($item['children']);
			} else {
				$item['children'] = [];
			}

			$result[] = $item;
		}

		return $result;
	}

	private function buildTree(array $structure, array $items_data): array {
		$result = [];
		$placeholders = ['добавить', 'add', 'название', 'title'];

		foreach ($structure as $node) {
			if (!isset($node['id'])) {
				continue;
			}

			$id = (int)$node['id'];
			$row = $items_data[$id] ?? [];

			$name = isset($row['name']) ? trim($row['name']) : '';
			$name_l = oc_strtolower($name);

			if ($name === '' || in_array($name_l, $placeholders, true)) {
				continue;
			}

			$item = [
				'id'       => $id,
				'name'     => $name,
				'href'     => isset($row['href']) ? trim(html_entity_decode($row['href'], ENT_QUOTES, 'UTF-8')) : '',
				'status'   => isset($row['status']) ? (int)$row['status'] : 1,
				'image'    => $row['image'] ?? '',
				'children' => []
			];

			if (!empty($node['children']) && is_array($node['children'])) {
				$item['children'] = $this->buildTree($node['children'], $items_data);
			}

			$result[] = $item;
		}

		return $result;
	}

	private function maxTreeId(array $tree): int {
		$max = 0;

		foreach ($tree as $item) {
			$id = isset($item['id']) ? (int)$item['id'] : 0;

			if ($id > $max) {
				$max = $id;
			}

			if (!empty($item['children']) && is_array($item['children'])) {
				$child_max = $this->maxTreeId($item['children']);

				if ($child_max > $max) {
					$max = $child_max;
				}
			}
		}

		return $max;
	}

	private function countTree(array $tree): int {
		$count = 0;

		foreach ($tree as $item) {
			$count++;

			if (!empty($item['children']) && is_array($item['children'])) {
				$count += $this->countTree($item['children']);
			}
		}

		return $count;
	}

	private function renderTreeHtml(array $tree): string {
		$html = '<ol class="dd-list">';

		foreach ($tree as $item) {
			$html .= $this->renderTreeItem($item);
		}

		$html .= '</ol>';

		return $html;
	}

	private function renderTreeItem(array $item): string {
		$id = (int)$item['id'];
		$name = $item['name'] ?? '';
		$href = $item['href'] ?? '';
		$status = isset($item['status']) ? (int)$item['status'] : 1;
		$image = $item['image'] ?? '';

		if ($image && is_file(DIR_IMAGE . $image)) {
			$thumb = $this->model_tool_image->resize($image, 40, 40);
		} else {
			$image = '';
			$thumb = $this->model_tool_image->resize('no_image.png', 40, 40);

			if (!$thumb) {
				$thumb = $this->model_tool_image->resize('placeholder.png', 40, 40);
			}
		}

		$placeholder = $this->model_tool_image->resize('no_image.png', 40, 40);

		if (!$placeholder) {
			$placeholder = $this->model_tool_image->resize('placeholder.png', 40, 40);
		}

		if (!$thumb) {
			$thumb = $placeholder;
		}

		$status_icon = $status ? 'fa-pause' : 'fa-play';
		$status_title = $status ? $this->language->get('text_enabled') : $this->language->get('text_disabled');

		$html  = '<li class="dd-item dd3-item" data-id="' . $id . '" id="wt-item-' . $id . '">';
		$html .= '<div class="dd-handle dd3-handle"></div>';
		$html .= '<div class="dd3-content dd-nodrag">';
		$html .= '<div class="input-group">';
		$html .= '<input type="text" name="items[' . $id . '][name]" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($this->language->get('entry_item_name'), ENT_QUOTES, 'UTF-8') . '" class="form-control wt-item-name" />';
		$html .= '<input type="hidden" name="items[' . $id . '][status]" value="' . $status . '" id="wt-status-' . $id . '" />';
		$html .= '<div class="input-group-text p-0 border-0 bg-transparent">';
		$html .= '<div class="btn-group" role="group">';
		$html .= '<button type="button" onclick="wtPickCategory(' . $id . ');" data-bs-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_category'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-info"><i class="fa-solid fa-folder"></i></button>';
		$html .= '<button type="button" onclick="wtAddChild(' . $id . ');" data-bs-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_add_child'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary"><i class="fa-solid fa-sitemap"></i></button>';
		$html .= '<button type="button" onclick="wtToggleStatus(' . $id . ');" data-bs-toggle="tooltip" title="' . htmlspecialchars($status_title, ENT_QUOTES, 'UTF-8') . '" class="btn btn-light" id="wt-status-btn-' . $id . '"><i class="fa-solid ' . $status_icon . '"></i></button>';
		$html .= '<span class="wt-thumb-wrap">';
		$html .= '<button type="button" id="wt-thumb-link-' . $id . '" class="btn btn-outline-secondary wt-thumb-btn" data-oc-toggle="image" data-oc-target="#wt-image-' . $id . '" data-oc-thumb="#wt-thumb-' . $id . '" title="' . htmlspecialchars($this->language->get('button_image'), ENT_QUOTES, 'UTF-8') . '"><img src="' . htmlspecialchars((string)$thumb, ENT_QUOTES, 'UTF-8') . '" alt="" id="wt-thumb-' . $id . '" data-oc-placeholder="' . htmlspecialchars((string)$placeholder, ENT_QUOTES, 'UTF-8') . '" /></button>';
		$html .= '<input type="hidden" name="items[' . $id . '][image]" value="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" id="wt-image-' . $id . '" />';
		$html .= '</span>';
		$html .= '<button type="button" onclick="wtToggleSettings(' . $id . ');" data-bs-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_settings'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-success"><i class="fa-solid fa-gears"></i></button>';
		$html .= '<button type="button" onclick="wtPickProduct(' . $id . ');" data-bs-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_product'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-info"><i class="fa-solid fa-cart-shopping"></i></button>';
		$html .= '<button type="button" onclick="wtDelete(' . $id . ');" data-bs-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_remove'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-danger"><i class="fa-solid fa-circle-minus"></i></button>';
		$html .= '</div></div></div>';

		$html .= '<div id="wt-extra-' . $id . '" class="wt-extra" style="display:none; margin-top:8px;">';
		$html .= '<input type="text" name="items[' . $id . '][href]" value="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($this->language->get('entry_item_href'), ENT_QUOTES, 'UTF-8') . '" class="form-control" id="wt-href-' . $id . '" />';
		$html .= '<input type="text" id="wt-autocomplete-' . $id . '" value="" placeholder="' . htmlspecialchars($this->language->get('entry_autocomplete'), ENT_QUOTES, 'UTF-8') . '" class="form-control mt-1" style="display:none;" />';
		$html .= '</div>';

		$html .= '</div>';

		if (!empty($item['children']) && is_array($item['children'])) {
			$html .= '<ol class="dd-list">';

			foreach ($item['children'] as $child) {
				$html .= $this->renderTreeItem($child);
			}

			$html .= '</ol>';
		}

		$html .= '</li>';

		return $html;
	}
}
