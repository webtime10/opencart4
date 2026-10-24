<?php
namespace Opencart\Admin\Controller\Extension\WtLang\Module;

class WtLang extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_lang/module/wt_lang';

	/** @var array<string, string> */
	private array $error = [];

	public function index(): void {
		$this->load->language($this->route);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
			$post = $this->request->post;

			if (isset($post['module_wt_lang_default'])) {
				$default = (string)$post['module_wt_lang_default'];

				if (isset($post['module_wt_lang_language']) && is_array($post['module_wt_lang_language'])) {
					foreach ($post['module_wt_lang_language'] as $code => $row) {
						$post['module_wt_lang_language'][$code]['default'] = ($code === $default) ? 1 : 0;
					}
				}
			}

			$this->model_setting_setting->editSetting('module_wt_lang', $post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'));
		}

		$data = $this->loadLanguageStrings();

		$data['error_warning'] = $this->error['warning'] ?? '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
			],
			[
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'])
			]
		];

		$data['action'] = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token']);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['module_wt_lang_status'] = $this->posted('module_wt_lang_status', 0);
		$data['module_wt_lang_hreflang'] = $this->posted('module_wt_lang_hreflang', 1);
		$data['module_wt_lang_xdefault'] = $this->posted('module_wt_lang_xdefault', 1);

		$saved = $this->config->get('module_wt_lang_language');

		if (!is_array($saved)) {
			$saved = [];
		}

		$data['module_wt_lang_default'] = $this->config->get('config_language');
		$data['languages'] = [];

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if (!$language['status']) {
				continue;
			}

			$code = $language['code'];
			$row = $saved[$code] ?? [];

			if (!empty($row['default'])) {
				$data['module_wt_lang_default'] = $code;
			}

			$data['languages'][] = [
				'language_id' => $language['language_id'],
				'code'        => $code,
				'name'        => $language['name'],
				'status'      => isset($row['status']) ? (int)$row['status'] : 1,
				'prefix'      => $row['prefix'] ?? $this->defaultPrefix($code),
				'hreflang'    => $row['hreflang'] ?? $this->defaultHreflang($code),
			];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/wt_lang/module/wt_lang', $data));
	}

	public function install(): void {
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('user/user_group');

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $this->route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $this->route);

		$languages = [];

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if (!$language['status']) {
				continue;
			}

			$code = $language['code'];
			$is_default = ($code === $this->config->get('config_language'));

			$languages[$code] = [
				'status'   => 1,
				'prefix'   => $is_default ? '' : $this->defaultPrefix($code),
				'hreflang' => $this->defaultHreflang($code),
				'default'  => $is_default ? 1 : 0,
			];
		}

		$this->model_setting_setting->editSetting('module_wt_lang', [
			'module_wt_lang_status'    => 1,
			'module_wt_lang_hreflang'  => 1,
			'module_wt_lang_xdefault'  => 1,
			'module_wt_lang_language'  => $languages,
		]);

		$this->registerEvents();
	}

	public function uninstall(): void {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_lang');

		$this->unregisterEvents();
	}

	private function registerEvents(): void {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('wt_lang');

		$events = [
			[
				'description' => 'WT Lang hreflang in header',
				'trigger'     => 'catalog/controller/common/header/before',
				'action'      => 'extension/wt_lang/module/wt_lang.eventHreflang',
			],
			[
				'description' => 'WT Lang language switcher',
				'trigger'     => 'catalog/controller/common/language/after',
				'action'      => 'extension/wt_lang/module/wt_lang.eventLanguageSwitcher',
			],
		];

		foreach ($events as $event) {
			$this->model_setting_event->addEvent([
				'code'        => 'wt_lang',
				'description' => $event['description'],
				'trigger'     => $event['trigger'],
				'action'      => $event['action'],
				'status'      => 1,
				'sort_order'  => 0,
			]);
		}
	}

	private function unregisterEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_lang');
	}

	private function validate(): bool {
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$prefixes = [];
		$default = $this->request->post['module_wt_lang_default'] ?? '';

		if (isset($this->request->post['module_wt_lang_language']) && is_array($this->request->post['module_wt_lang_language'])) {
			foreach ($this->request->post['module_wt_lang_language'] as $code => $row) {
				if (empty($row['status'])) {
					continue;
				}

				$prefix = isset($row['prefix']) ? trim(strtolower((string)$row['prefix']), '/') : '';

				if ($code === $default) {
					$prefix = '';
				}

				if ($prefix !== '') {
					if (isset($prefixes[$prefix])) {
						$this->error['warning'] = $this->language->get('error_prefix_duplicate');
						break;
					}

					$prefixes[$prefix] = $code;
				}
			}
		}

		return !$this->error;
	}

	private function posted(string $key, mixed $default = ''): mixed {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}

		$value = $this->config->get($key);

		return ($value !== null && $value !== false) ? $value : $default;
	}

	private function defaultPrefix(string $code): string {
		return match ($code) {
			'en-gb', 'english' => 'en',
			'uk-ua', 'ukraine' => 'uk',
			'ru-ru', 'russian' => 'ru',
			default => substr($code, 0, 2),
		};
	}

	private function defaultHreflang(string $code): string {
		return match ($code) {
			'en-gb', 'english' => 'en',
			'uk-ua', 'ukraine' => 'uk',
			'ru-ru', 'russian' => 'ru',
			default => substr($code, 0, 2),
		};
	}

	/**
	 * @return array<string, string>
	 */
	private function loadLanguageStrings(): array {
		$keys = [
			'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no',
			'entry_status', 'entry_hreflang', 'entry_xdefault', 'entry_default_language',
			'column_language', 'column_prefix', 'column_hreflang', 'column_status',
			'help_prefix', 'help_hreflang', 'help_default', 'button_save', 'button_cancel',
		];

		$data = [];

		foreach ($keys as $key) {
			$data[$key] = $this->language->get($key);
		}

		return $data;
	}
}
