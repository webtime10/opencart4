<?php
namespace Opencart\Catalog\Controller\Extension\PlumStore\Common;

/**
 * Loads modules assigned to layout position "menu" (Header Menu / meganav).
 */
class MenuBar extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->model('design/layout');

		if (isset($this->request->get['route'])) {
			$route = (string)$this->request->get['route'];
		} else {
			$route = 'common/home';
		}

		$layout_id = 0;

		if ($route == 'product/category' && isset($this->request->get['path'])) {
			$this->load->model('catalog/category');
			$path = explode('_', (string)$this->request->get['path']);
			$layout_id = $this->model_catalog_category->getLayoutId((int)end($path));
		}

		if ($route == 'product/manufacturer.info' && isset($this->request->get['manufacturer_id'])) {
			$this->load->model('catalog/manufacturer');
			$layout_id = $this->model_catalog_manufacturer->getLayoutId((int)$this->request->get['manufacturer_id']);
		}

		if ($route == 'product/product' && isset($this->request->get['product_id'])) {
			$this->load->model('catalog/product');
			$layout_id = $this->model_catalog_product->getLayoutId((int)$this->request->get['product_id']);
		}

		if ($route == 'information/information' && isset($this->request->get['information_id'])) {
			$this->load->model('catalog/information');
			$layout_id = $this->model_catalog_information->getLayoutId((int)$this->request->get['information_id']);
		}

		if (!$layout_id) {
			$layout_id = $this->model_design_layout->getLayout($route);
		}

		if (!$layout_id) {
			$layout_id = $this->config->get('config_layout_id');
		}

		$this->load->model('setting/module');

		$data['modules'] = [];

		$modules = $this->model_design_layout->getModules((int)$layout_id, 'menu');

		foreach ($modules as $module) {
			$part = explode('.', $module['code']);

			if (isset($part[1]) && $this->config->get('module_' . $part[1] . '_status')) {
				$module_data = $this->load->controller('extension/' . $part[0] . '/module/' . $part[1]);

				if ($module_data) {
					$data['modules'][] = $module_data;
				}
			}

			if (isset($part[2])) {
				$setting_info = $this->model_setting_module->getModule((int)$part[2]);

				if ($setting_info && !empty($setting_info['status'])) {
					$output = $this->load->controller('extension/' . $part[0] . '/module/' . $part[1], $setting_info);

					if ($output) {
						$data['modules'][] = $output;
					}
				}
			}
		}

		if (!$data['modules']) {
			// Нет модуля в layout position "menu" — берём WT Menu слот 2
			try {
				$output = $this->load->controller('extension/wt_menu/module/wt_menu', ['__slot' => 2]);
			} catch (\Throwable $e) {
				$output = '';
			}

			if ($output !== '' && strpos($output, 'data-meganav') !== false) {
				$data['modules'][] = $output;
			}
		}

		if (!$data['modules']) {
			return '';
		}

		return $this->load->view('extension/plum_store/common/menu_bar', $data);
	}
}
