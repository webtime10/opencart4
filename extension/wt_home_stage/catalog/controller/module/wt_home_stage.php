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
}
