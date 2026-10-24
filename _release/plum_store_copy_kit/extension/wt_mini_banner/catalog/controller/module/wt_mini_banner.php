<?php
namespace Opencart\Catalog\Controller\Extension\WtMiniBanner\Module;

class WtMiniBanner extends \Opencart\System\Engine\Controller {
	public function index(array $setting = []): string {
		if (!$setting || (!isset($setting['status']) && empty($setting['item']))) {
			$setting = $this->getFirstModule();
		}

		if (isset($setting['status']) && !(int)$setting['status']) {
			return '';
		}

		$this->load->language('extension/wt_mini_banner/module/wt_mini_banner');
		$this->load->model('tool/image');
		$lang = (string)$this->config->get('config_language');

		$items = [];
		$raw = $setting['item'] ?? [];

		if (!is_array($raw)) {
			$raw = [];
		}

		usort($raw, static function ($a, $b) {
			return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
		});

		foreach ($raw as $item) {
			if (!is_array($item)) {
				continue;
			}

			if (isset($item['status']) && !(int)$item['status']) {
				continue;
			}

			$image = (string)($item['image'] ?? '');
			$thumb = '';

			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = HTTP_SERVER . 'image/' . str_replace(' ', '%20', $image);
			}

			$product_id = (int)($item['product_id'] ?? 0);
			$href = '#';

			if ($product_id) {
				$href = $this->url->link('product/product', 'language=' . $lang . '&product_id=' . $product_id);
			}

			$percent = trim((string)($item['percent'] ?? ''));

			if ($percent !== '' && $percent[0] !== '-' && ctype_digit(str_replace('%', '', $percent))) {
				$percent = '-' . rtrim($percent, '%') . '%';
			}

			$items[] = [
				'title'       => (string)($item['title'] ?? ''),
				'description' => (string)($item['description'] ?? ''),
				'image'       => $thumb,
				'percent'     => $percent,
				'href'        => $href,
			];
		}

		if (!$items) {
			return '';
		}

		$data['items'] = $items;
		$data['text_more'] = $this->language->get('text_more');

		return $this->load->view('extension/wt_mini_banner/module/wt_mini_banner', $data);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getFirstModule(): array {
		$query = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = 'wt_mini_banner.wt_mini_banner' ORDER BY `module_id` ASC");

		foreach ($query->rows as $row) {
			$setting = json_decode((string)$row['setting'], true);

			if (is_array($setting) && !empty($setting['status'])) {
				return $setting;
			}
		}

		return [];
	}
}
