<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class SeoUrl
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class SeoUrl extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<string, string>
	 */
	private array $data = [];

	/**
	 * Index
	 *
	 * @return null
	 */
	public function index() {
		// WT Lang start — prefix detect even when SEO URLs are off
		if ($this->config->get('module_wt_lang_status') && is_dir(DIR_EXTENSION . 'wt_lang/')) {
			$this->load->controller('extension/wt_lang/module/wt_lang.eventBootstrap');
		}
		// WT Lang end

		// Add rewrite to URL class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);

			$this->load->model('design/seo_url');

			// Decode URL
			if (isset($this->request->get['_route_'])) {
				$parts = explode('/', $this->request->get['_route_']);

				// remove any empty arrays from trailing
				if (oc_strlen(end($parts)) == 0) {
					array_pop($parts);
				}

				// WT Filter: /category/color:red/ — сегмент не в seo_url.
				// В OC4 startup/seo_url идёт ДО events, иначе leftover → 404.
				if ($parts) {
					$last = rawurldecode((string)end($parts));

					if ($this->isWtFilterSeoSegment($last)) {
						$url_key = 'filter_wt_filter';

						if (!empty($this->request->get[$url_key])) {
							$this->request->get[$url_key] = $last . ';' . $this->request->get[$url_key];
						} else {
							$this->request->get[$url_key] = $last;
						}

						array_pop($parts);
						$this->request->get['_route_'] = implode('/', $parts);
					}
				}

				foreach ($parts as $key => $value) {
					$seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword($value);

					if ($seo_url_info) {
						$this->request->get[$seo_url_info['key']] = html_entity_decode($seo_url_info['value'], ENT_QUOTES, 'UTF-8');

						unset($parts[$key]);
					}
				}

				if (!isset($this->request->get['route'])) {
					$this->request->get['route'] = $this->config->get('action_default');
				}

				if ($parts) {
					$this->request->get['route'] = $this->config->get('action_error');
				}
			}
		}

		return null;
	}

	/**
	 * Rewrite
	 *
	 * @param string $link
	 *
	 * @return string
	 */
	public function rewrite(string $link): string {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		// Build the url
		$url = '';

		if ($url_info['scheme']) {
			$url .= $url_info['scheme'];
		}

		$url .= '://';

		if ($url_info['host']) {
			$url .= $url_info['host'];
		}

		if (isset($url_info['port'])) {
			$url .= ':' . $url_info['port'];
		}

		parse_str($url_info['query'], $query);

		$language_id = $this->config->get('config_language_id');

		// Start changing the URL query into a path
		$paths = [];

		// Parse the query into its separate parts
		$parts = explode('&', $url_info['query']);

		foreach ($parts as $part) {
			$pair = explode('=', $part);

			if (isset($pair[0])) {
				$key = (string)$pair[0];
			}

			if (isset($pair[1])) {
				$value = (string)$pair[1];
			} else {
				$value = '';
			}

			$index = $key . '=' . $value;

			if (!isset($this->data[$language_id][$index])) {
				$this->data[$language_id][$index] = $this->model_design_seo_url->getSeoUrlByKeyValue((string)$key, (string)$value);
			}

			if ($this->data[$language_id][$index]) {
				$paths[] = $this->data[$language_id][$index];

				unset($query[$key]);
			}
		}

		$sort_order = [];

		foreach ($paths as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $paths);

		// Build the path
		$url .= str_replace('/index.php', '', $url_info['path']);

		foreach ($paths as $result) {
			$url .= '/' . $result['keyword'];
		}

		// Rebuild the URL query
		if ($query) {
			$url .= '?' . str_replace(['%2F'], ['/'], http_build_query($query));
		}

		return $url;
	}

	/**
	 * Хвостовой сегмент ЧПУ фильтра: option:value[;option:value…] / p:100-200 / m:3 / d:1.
	 */
	private function isWtFilterSeoSegment(string $segment): bool {
		$segment = trim($segment);

		if ($segment === '' || !str_contains($segment, ':')) {
			return false;
		}

		return (bool)preg_match('/^(?:[\p{L}\p{N}_-]+):(?:[^\s\/;]+)(?:;(?:[\p{L}\p{N}_-]+):(?:[^\s\/;]+))*$/u', $segment);
	}
}
