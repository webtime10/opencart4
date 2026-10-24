<?php
namespace Opencart\Admin\Controller\Extension\WtHomeStage\Module;

class WtHomeStage extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/wt_home_stage/module/wt_home_stage');
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
				'href' => $this->url->link('extension/wt_home_stage/module/wt_home_stage', 'user_token=' . $this->session->data['user_token'])
			];
			$data['save'] = $this->url->link('extension/wt_home_stage/module/wt_home_stage.save', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/wt_home_stage/module/wt_home_stage', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'])
			];
			$data['save'] = $this->url->link('extension/wt_home_stage/module/wt_home_stage.save', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id']);
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['name'] = $module_info['name'] ?? '';
		$data['status'] = $module_info['status'] ?? '';
		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/wt_home_stage/module/wt_home_stage', $data));
	}

	public function save(): void {
		$this->load->language('extension/wt_home_stage/module/wt_home_stage');
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/wt_home_stage/module/wt_home_stage')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$post_info = $this->request->post + ['module_id' => 0, 'name' => '', 'status' => 0];

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!$json) {
			$this->load->model('setting/module');
			if (!$post_info['module_id']) {
				$json['module_id'] = $this->model_setting_module->addModule('wt_home_stage.wt_home_stage', $post_info);
			} else {
				$this->model_setting_module->editModule($post_info['module_id'], $post_info);
			}
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
