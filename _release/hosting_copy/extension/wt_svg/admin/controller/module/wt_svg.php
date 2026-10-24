<?php
namespace Opencart\Admin\Controller\Extension\WtSvg\Module;

class WtSvg extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_svg/module/wt_svg';

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

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['module_wt_svg_status'] = $this->config->get('module_wt_svg_status');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/wt_svg/module/wt_svg', $data));
	}

	public function save(): void {
		$this->load->language($this->route);

		$json = [];

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_wt_svg', $this->request->post);

			if (!empty($this->request->post['module_wt_svg_status'])) {
				$this->ensureSvgAllowed();
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		$this->load->model('setting/setting');
		$this->registerEvents();

		$this->model_setting_setting->editSetting('module_wt_svg', [
			'module_wt_svg_status' => 1
		]);

		$this->ensureSvgAllowed();
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->load->model('setting/setting');

		$this->model_setting_event->deleteEventByCode('wt_svg');
		$this->model_setting_setting->deleteSetting('module_wt_svg');
	}

	/**
	 * Before upload: normalize SVG MIME so core filemanager accepts the file.
	 * Browsers often send image/svg+xml;charset=utf-8 / text/xml / application/octet-stream.
	 */
	public function eventUploadBefore(string &$route, array &$args): void {
		if (!$this->isEnabled() || empty($this->request->files['file'])) {
			return;
		}

		$file = &$this->request->files['file'];

		if (!empty($file['name']) && is_array($file['name'])) {
			foreach ($file['name'] as $key => $name) {
				if ($this->isSvgFilename((string)$name)) {
					$file['type'][$key] = 'image/svg+xml';
				}
			}
		} elseif (!empty($file['name']) && $this->isSvgFilename((string)$file['name'])) {
			$file['type'] = 'image/svg+xml';
		}
	}

	/**
	 * admin/model/tool/image.resize/before — for SVG rewrite model route.
	 */
	public function eventResize(string &$route, array &$args): void {
		if (!$this->isEnabled() || empty($args[0]) || !$this->isSvgFilename((string)$args[0])) {
			return;
		}

		$route = 'extension/wt_svg/tool/image.resize';
	}

	public function eventResizeCatalog(string &$route, array &$args): void {
		$this->eventResize($route, $args);
	}

	/**
	 * After core upload: sanitize any SVG that was just written.
	 */
	public function eventUploadAfter(string &$route, array &$args, &$output = null): void {
		if (!$this->isEnabled()) {
			return;
		}

		$base = DIR_IMAGE . 'catalog/';
		$base_real = str_replace('\\', '/', (string)realpath($base));

		if ($base_real === '') {
			return;
		}

		$base_real = rtrim($base_real, '/') . '/';

		if (isset($this->request->get['directory'])) {
			$directory = $base . html_entity_decode($this->request->get['directory'], ENT_QUOTES, 'UTF-8') . '/';
		} else {
			$directory = $base;
		}

		$dir_real = str_replace('\\', '/', (string)realpath($directory));

		if ($dir_real === '' || !str_starts_with(rtrim($dir_real, '/') . '/', $base_real)) {
			return;
		}

		$directory = rtrim($dir_real, '/') . '/';
		$names = [];

		if (!empty($this->request->files['file']['name']) && is_array($this->request->files['file']['name'])) {
			foreach ($this->request->files['file']['name'] as $name) {
				$names[] = $name;
			}
		} elseif (!empty($this->request->files['file']['name'])) {
			$names[] = $this->request->files['file']['name'];
		}

		foreach ($names as $name) {
			$filename = preg_replace('/[\/\\\?%*:|"<>]/', '', basename(html_entity_decode((string)$name, ENT_QUOTES, 'UTF-8')));

			if (!$filename || !$this->isSvgFilename($filename)) {
				continue;
			}

			$path = $directory . $filename;

			if (!is_file($path)) {
				continue;
			}

			$svg = (string)file_get_contents($path);

			if ($svg === '' || !preg_match('/<svg\b/i', $svg)) {
				continue;
			}

			file_put_contents($path, $this->sanitizeSvg($svg));
		}
	}

	public function registerEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_svg');

		$events = [
			[
				'description' => 'WT SVG normalize MIME before upload',
				'trigger'     => 'admin/controller/common/filemanager.upload/before',
				'action'      => 'extension/wt_svg/module/wt_svg.eventUploadBefore',
			],
			[
				'description' => 'WT SVG resize bypass (admin)',
				'trigger'     => 'admin/model/tool/image.resize/before',
				'action'      => 'extension/wt_svg/module/wt_svg.eventResize',
			],
			[
				'description' => 'WT SVG resize bypass (catalog)',
				'trigger'     => 'catalog/model/tool/image.resize/before',
				'action'      => 'extension/wt_svg/module/wt_svg.eventResizeCatalog',
			],
			[
				'description' => 'WT SVG sanitize upload',
				'trigger'     => 'admin/controller/common/filemanager.upload/after',
				'action'      => 'extension/wt_svg/module/wt_svg.eventUploadAfter',
			],
		];

		foreach ($events as $event) {
			$this->model_setting_event->addEvent([
				'code'        => 'wt_svg',
				'description' => $event['description'],
				'trigger'     => $event['trigger'],
				'action'      => $event['action'],
				'status'      => 1,
				'sort_order'  => 0,
			]);
		}
	}

	private function ensureSvgAllowed(): void {
		$this->load->model('setting/setting');

		$ext = (string)$this->config->get('config_file_ext_allowed');
		$lines = preg_split('/\r\n|\r|\n/', $ext) ?: [];
		$lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
		$lower = array_map('strtolower', $lines);
		$ext_changed = false;

		if (!in_array('svg', $lower, true)) {
			$lines[] = 'svg';
			$ext_changed = true;
		}

		// OpenCart explode() uses \r\n — keep that format
		$normalized_ext = implode("\r\n", $lines);

		if ($ext_changed || $normalized_ext !== $ext) {
			$this->model_setting_setting->editValue('config', 'config_file_ext_allowed', $normalized_ext);
			$this->config->set('config_file_ext_allowed', $normalized_ext);
		}

		$mime = (string)$this->config->get('config_file_mime_allowed');
		$mimes = preg_split('/\r\n|\r|\n/', $mime) ?: [];
		$mimes = array_values(array_filter(array_map('trim', $mimes), 'strlen'));
		$need = [
			'image/svg+xml',
			'image/svg',
			'text/xml',
			'application/xml',
			'application/octet-stream',
		];

		foreach ($need as $item) {
			if (!in_array($item, $mimes, true)) {
				$mimes[] = $item;
			}
		}

		$normalized_mime = implode("\r\n", $mimes);

		if ($normalized_mime !== $mime) {
			$this->model_setting_setting->editValue('config', 'config_file_mime_allowed', $normalized_mime);
			$this->config->set('config_file_mime_allowed', $normalized_mime);
		}
	}

	private function isEnabled(): bool {
		return (bool)$this->config->get('module_wt_svg_status');
	}

	private function isSvgFilename(string $filename): bool {
		return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg';
	}

	private function sanitizeSvg(string $svg): string {
		$svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
		$svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg) ?? $svg;
		$svg = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? $svg;
		$svg = preg_replace('/(xlink:href|href)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1=$2$2', $svg) ?? $svg;

		return $svg;
	}
}
