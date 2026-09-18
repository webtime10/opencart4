<?php
namespace Opencart\Admin\Controller\Extension\WtInfoBlock\Module;

class WtInfoBlock extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_info_block/module/wt_info_block';

	public function index(): void {
		$this->load->language($this->route);

		$this->document->setTitle($this->language->get('heading_title'));

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

		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['name'] = $module_info['name'] ?? '';
		$data['status'] = $module_info['status'] ?? 1;
		$data['module_description'] = $module_info['module_description'] ?? [];
		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

		$this->load->model('tool/image');

		$items = [];
		$raw_items = $module_info['item'] ?? [];

		foreach ($raw_items as $item) {
			$image = $item['image'] ?? '';
			$thumb = $this->model_tool_image->resize('no_image.png', 100, 100);

			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = $this->model_tool_image->resize($image, 100, 100);

				if (!$thumb || preg_match('/\.svg$/i', $image)) {
					$thumb = HTTP_CATALOG . 'image/' . $image;
				}
			}

			$row = [
				'description'   => $item['description'] ?? [],
				'image'         => $image,
				'thumb'         => $thumb,
				'sort_order'    => isset($item['sort_order']) ? (int)$item['sort_order'] : 0,
				'status'        => isset($item['status']) ? (int)$item['status'] : 1,
				'preview_title' => '',
				'preview_text'  => ''
			];

			if (!empty($row['description']) && is_array($row['description'])) {
				foreach ($row['description'] as $desc) {
					if ($row['preview_title'] === '' && !empty($desc['title'])) {
						$row['preview_title'] = $desc['title'];
					}
					if ($row['preview_text'] === '' && !empty($desc['text'])) {
						$row['preview_text'] = $desc['text'];
					}
					if ($row['preview_title'] !== '' && $row['preview_text'] !== '') {
						break;
					}
				}
			}

			$items[] = $row;
		}

		usort($items, static function ($a, $b) {
			return $a['sort_order'] - $b['sort_order'];
		});

		$data['items'] = $items;

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/wt_info_block/module/wt_info_block', $data));
	}

	public function save(): void {
		$this->load->language($this->route);

		$json = [];

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'module_id' => 0,
			'name'      => '',
			'status'    => 0,
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!$json) {
			$this->load->model('setting/module');

			if (!$post_info['module_id']) {
				$json['module_id'] = $this->model_setting_module->addModule('wt_info_block.wt_info_block', $post_info);
			} else {
				$this->model_setting_module->editModule($post_info['module_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
