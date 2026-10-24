<?php
namespace Opencart\Catalog\Controller\Extension\WtBenefits\Module;

class WtBenefits extends \Opencart\System\Engine\Controller {
	public function index(array $setting): string {
		static $module = 0;

		if (empty($setting['status'])) {
			return '';
		}

		$this->load->model('tool/image');

		$language_id = (int)$this->config->get('config_language_id');
		$items = [];

		if (!empty($setting['item']) && is_array($setting['item'])) {
			$raw = $setting['item'];

			uasort($raw, static function ($a, $b) {
				return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
			});

			foreach ($raw as $item) {
				if (isset($item['status']) && !(int)$item['status']) {
					continue;
				}

				$title = '';
				$text = '';

				if (!empty($item['description'][$language_id]['title'])) {
					$title = html_entity_decode($item['description'][$language_id]['title'], ENT_QUOTES, 'UTF-8');
				}

				if (!empty($item['description'][$language_id]['text'])) {
					$text = html_entity_decode($item['description'][$language_id]['text'], ENT_QUOTES, 'UTF-8');
				}

				if ($title === '' && $text === '') {
					continue;
				}

				$image = '';
				$path = (string)($item['image'] ?? '');

				if ($path !== '' && is_file(DIR_IMAGE . $path)) {
					if (preg_match('/\.svg$/i', $path)) {
						$base = !empty($this->request->server['HTTPS'])
							? (string)$this->config->get('config_ssl')
							: (string)$this->config->get('config_url');

						if ($base === '') {
							$base = HTTP_SERVER;
						}

						$image = rtrim($base, '/') . '/image/' . str_replace(' ', '%20', $path);
					} else {
						$image = $this->model_tool_image->resize($path, 64, 64);
					}
				}

				$items[] = [
					'title' => $title,
					'text'  => $text,
					'image' => $image
				];
			}
		}

		if (!$items) {
			return '';
		}

		$data['items'] = $items;
		$data['module'] = $module++;
		$data['stylesheet'] = 'extension/wt_benefits/catalog/view/stylesheet/wt_benefits.css?v=5';

		return $this->load->view('extension/wt_benefits/module/wt_benefits', $data);
	}
}
