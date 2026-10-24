<?php
namespace Opencart\Catalog\Controller\Extension\WtHomeStage\Module;

class WtHomeStage extends \Opencart\System\Engine\Controller {
	/**
	 * @param array<string, mixed> $setting
	 */
	public function index(array $setting): string {
		if (isset($setting['status']) && !(int)$setting['status']) {
			return '';
		}

		$this->load->language('extension/wt_home_stage/module/wt_home_stage');

		$lang = $this->config->get('config_language');
		$img = 'extension/plum_store/catalog/view/image/plum_store/product/';

		$data['icons'] = 'extension/plum_store/catalog/view/image/plum_store/icons.svg';
		$data['category'] = $this->url->link('product/special', 'language=' . $lang);
		$data['product'] = $this->url->link('product/search', 'language=' . $lang);

		// Prefer dedicated banner modules; keep static demo as fallback.
		// OC4 load->controller() returns Exception object (does not throw).
		$data['big_banner'] = $this->safeModule('extension/wt_big_banner/module/wt_big_banner', $this->getModuleSetting('wt_big_banner.wt_big_banner'));
		$data['mini_banner'] = $this->safeModule('extension/wt_mini_banner/module/wt_mini_banner', $this->getModuleSetting('wt_mini_banner.wt_mini_banner'));

		$data['slides'] = [
			[
				'eyebrow' => $this->language->get('text_slide1_eyebrow'),
				'title'   => $this->language->get('text_slide1_title'),
				'text'    => $this->language->get('text_slide1_text'),
				'button'  => $this->language->get('text_slide1_button'),
				'href'    => $data['product'],
				'image'   => $img . '1.jpg'
			],
			[
				'eyebrow' => $this->language->get('text_slide2_eyebrow'),
				'title'   => $this->language->get('text_slide2_title'),
				'text'    => $this->language->get('text_slide2_text'),
				'button'  => $this->language->get('text_slide2_button'),
				'href'    => $data['product'],
				'image'   => $img . '2.jpg'
			],
		];

		$data['tiles'] = [
			[
				'class' => 'home-tile--dark',
				'badge' => '−40%',
				'title' => $this->language->get('text_tile1_title'),
				'text'  => $this->language->get('text_tile1_text'),
				'price' => '',
				'href'  => $data['category'],
				'image' => $img . '3.jpg'
			],
			[
				'class' => '',
				'badge' => '',
				'title' => $this->language->get('text_tile2_title'),
				'text'  => $this->language->get('text_tile2_text'),
				'price' => $this->language->get('text_tile2_price'),
				'href'  => $data['product'],
				'image' => $img . '4.jpg'
			],
		];

		$data['benefits'] = [
			['icon' => 'i-wallet', 'title' => $this->language->get('text_benefit1_title'), 'text' => $this->language->get('text_benefit1_text')],
			['icon' => 'i-truck', 'title' => $this->language->get('text_benefit2_title'), 'text' => $this->language->get('text_benefit2_text')],
			['icon' => 'i-shield', 'title' => $this->language->get('text_benefit3_title'), 'text' => $this->language->get('text_benefit3_text')],
		];

		$data['menu'] = $this->load->controller('common/menu');

		return $this->load->view('extension/wt_home_stage/module/wt_home_stage', $data);
	}

	private function safeModule(string $route, array $setting = []): string {
		if (!$setting) {
			return '';
		}

		try {
			$output = $this->load->controller($route, $setting);
		} catch (\Throwable $e) {
			return '';
		}

		if ($output instanceof \Throwable || $output instanceof \Exception) {
			return '';
		}

		return is_string($output) ? $output : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getModuleSetting(string $code): array {
		$query = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = '" . $this->db->escape($code) . "' ORDER BY `module_id` ASC");

		foreach ($query->rows as $row) {
			$setting = json_decode((string)$row['setting'], true);

			if (is_array($setting) && !empty($setting['status'])) {
				return $setting;
			}
		}

		return [];
	}
}
