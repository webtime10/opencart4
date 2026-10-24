<?php
namespace Opencart\Admin\Controller\Extension\WtBigBanner\Module;

class WtBigBanner extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_big_banner/module/wt_big_banner';

	public function index(): void {
		$this->load->language($this->route);
		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])];
		$data['breadcrumbs'][] = ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')];

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'])];
			$data['save'] = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['breadcrumbs'][] = ['text' => $this->language->get('heading_title'), 'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'])];
			$data['save'] = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id']);
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];

		$module_info = [];
		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['name'] = $module_info['name'] ?? '';
		$data['status'] = $module_info['status'] ?? 1;
		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

		$this->load->model('tool/image');
		$this->load->model('catalog/product');
		$placeholder = $this->model_tool_image->resize('no_image.png', 100, 100);
		$data['placeholder'] = $placeholder;

		$items = [];
		foreach (($module_info['item'] ?? []) as $item) {
			$image = $item['image'] ?? '';
			$thumb = $placeholder;
			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = $this->model_tool_image->resize($image, 100, 100) ?: (HTTP_CATALOG . 'image/' . $image);
			}
			$product_id = (int)($item['product_id'] ?? 0);
			$product_name = (string)($item['product_name'] ?? '');
			if ($product_id && $product_name === '') {
				$product = $this->model_catalog_product->getProduct($product_id);
				$product_name = $product['name'] ?? '';
			}
			$items[] = [
				'title' => (string)($item['title'] ?? ''),
				'description' => (string)($item['description'] ?? ''),
				'image' => $image,
				'thumb' => $thumb,
				'percent' => (string)($item['percent'] ?? ''),
				'product_id' => $product_id,
				'product_name' => $product_name,
				'sort_order' => (int)($item['sort_order'] ?? 0),
				'status' => isset($item['status']) ? (int)$item['status'] : 1,
			];
		}
		usort($items, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
		$data['items'] = $items;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/wt_big_banner/module/wt_big_banner', $data));
	}

	public function save(): void {
		$this->load->language($this->route);
		$json = [];
		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}
		$post_info = $this->request->post + ['module_id' => 0, 'name' => '', 'status' => 0, 'item' => []];
		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}
		if (!$json) {
			$this->load->model('setting/module');
			if (!(int)$post_info['module_id']) {
				$json['module_id'] = $this->model_setting_module->addModule('wt_big_banner.wt_big_banner', $post_info);
			} else {
				$this->model_setting_module->editModule((int)$post_info['module_id'], $post_info);
			}
			$json['success'] = $this->language->get('text_success');
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}