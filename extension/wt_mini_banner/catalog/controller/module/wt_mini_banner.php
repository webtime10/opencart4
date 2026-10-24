<?php
namespace Opencart\Catalog\Controller\Extension\WtMiniBanner\Module;

class WtMiniBanner extends \Opencart\System\Engine\Controller {
	public function index(array $setting = []): string {
		if (!$setting || (!isset($setting['status']) && empty($setting['item']))) {
			return '';
		}

		if (isset($setting['status']) && !(int)$setting['status']) {
			return '';
		}

		$this->load->language('extension/wt_mini_banner/module/wt_mini_banner');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$lang = (string)$this->config->get('config_language');
		$items = [];
		$raw = is_array($setting['item'] ?? null) ? $setting['item'] : [];

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

			$product_id = $this->resolveProductId($item);

			if ($product_id < 1) {
				continue;
			}

			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				continue;
			}

			$thumb = '';

			if (!empty($product['image']) && is_file(DIR_IMAGE . $product['image'])) {
				$thumb = $this->model_tool_image->resize($product['image'], 220, 220);
			}

			$title = trim((string)($item['title'] ?? ''));

			if ($title === '') {
				$title = (string)$product['name'];
			}

			$description = trim((string)($item['description'] ?? ''));
			$percent = trim((string)($item['percent'] ?? ''));

			if ($percent === '' && !empty($product['special']) && (float)$product['price'] > 0) {
				$percent = '-' . (int)round((1 - (float)$product['special'] / (float)$product['price']) * 100) . '%';
			} elseif ($percent !== '' && $percent[0] !== '-' && ctype_digit(str_replace('%', '', $percent))) {
				$percent = '-' . rtrim($percent, '%') . '%';
			}

			$items[] = [
				'title'       => $title,
				'description' => $description,
				'image'       => $thumb,
				'percent'     => $percent,
				'href'        => $this->url->link('product/product', 'language=' . $lang . '&product_id=' . $product_id),
			];
		}

		if (!$items) {
			return '';
		}

		$per_view = (int)($setting['per_view'] ?? 1);
		$per_view = max(1, min(6, $per_view));

		$autoplay = (int)($setting['autoplay'] ?? 0);

		if ($autoplay < 0) {
			$autoplay = 0;
		}

		static $module = 0;

		$data['items'] = $items;
		$data['per_view'] = $per_view;
		$data['autoplay'] = $autoplay;
		$data['text_more'] = $this->language->get('text_more');
		$data['module'] = $module++;

		$this->document->addStyle('extension/plum_store/catalog/view/stylesheet/plum_store/swiper.css');
		$this->document->addScript('extension/plum_store/catalog/view/javascript/plum_store/swiper.js', 'footer');

		return $this->load->view('extension/wt_mini_banner/module/wt_mini_banner', $data);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private function resolveProductId(array $item): int {
		$id = (int)($item['product_id'] ?? 0);

		if ($id > 0) {
			return $id;
		}

		foreach (['product_name', 'link', 'href'] as $key) {
			if (empty($item[$key])) {
				continue;
			}

			$str = html_entity_decode((string)$item[$key], ENT_QUOTES, 'UTF-8');

			if (preg_match('/product_id=(\d+)/', $str, $m)) {
				return (int)$m[1];
			}
		}

		return 0;
	}
}
