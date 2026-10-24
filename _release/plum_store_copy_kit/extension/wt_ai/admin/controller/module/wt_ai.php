<?php
namespace Opencart\Admin\Controller\Extension\WtAi\Module;

class WtAi extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_ai/module/wt_ai';

	/** @var array<string, string> */
	private array $error = [];

	public function index(): void {
		$this->load->language($this->route);
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

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
		$data['test'] = $this->url->link($this->route . '.test', 'user_token=' . $this->session->data['user_token'], true);

		$legacy_key = (string)$this->config->get('module_wt_ai_api_key');
		$legacy_model = (string)$this->config->get('module_wt_ai_model');
		if ($legacy_model === '') {
			$legacy_model = 'gemini-3.6-flash';
		}

		$defaults = [
			'module_wt_ai_status'              => 0,
			'module_wt_ai_provider'            => 'gemini',
			'module_wt_ai_api_key_gemini'      => $legacy_key,
			'module_wt_ai_api_key_deepseek'    => '',
			'module_wt_ai_api_key_openai'      => '',
			'module_wt_ai_model_gemini'        => $legacy_model,
			'module_wt_ai_model_deepseek'      => 'deepseek-chat',
			'module_wt_ai_model_openai'        => 'gpt-4o-mini',
			'module_wt_ai_prompt_translate'    => $this->defaultTranslatePrompt(),
			'module_wt_ai_prompt_seo'          => $this->defaultSeoPrompt(),
			'module_wt_ai_do_translate'        => 1,
			'module_wt_ai_do_meta_title'       => 1,
			'module_wt_ai_do_meta_description' => 1,
			'module_wt_ai_do_meta_h1'          => 1,
			'module_wt_ai_do_meta_keyword'     => 1,
			'module_wt_ai_overwrite'           => 0,
			'module_wt_ai_product'             => 1,
			'module_wt_ai_category'            => 1,
			'module_wt_ai_article'             => 1,
		];

		foreach ($defaults as $key => $default) {
			$data[$key] = $this->config->get($key);
			if ($data[$key] === null || $data[$key] === '') {
				$data[$key] = $default;
			}
		}

		if (!in_array($data['module_wt_ai_provider'], ['gemini', 'deepseek', 'openai'], true)) {
			$data['module_wt_ai_provider'] = 'gemini';
		}

		$retired = ['gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-2.0-flash-lite', 'gemini-1.5-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-pro', 'gemini-1.5-pro-latest'];
		if (in_array($data['module_wt_ai_model_gemini'], $retired, true)) {
			$data['module_wt_ai_model_gemini'] = 'gemini-3.6-flash';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->route, $data));
	}

	public function save(): void {
		$this->load->language($this->route);
		$json = [];

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$post = $this->request->post;
			foreach ([
				'module_wt_ai_do_translate',
				'module_wt_ai_do_meta_title',
				'module_wt_ai_do_meta_description',
				'module_wt_ai_do_meta_h1',
				'module_wt_ai_do_meta_keyword',
				'module_wt_ai_overwrite',
				'module_wt_ai_product',
				'module_wt_ai_category',
				'module_wt_ai_article',
			] as $flag) {
				if (!isset($post[$flag])) {
					$post[$flag] = 0;
				}
			}

			$provider = isset($post['module_wt_ai_provider']) ? strtolower((string)$post['module_wt_ai_provider']) : 'gemini';
			if (!in_array($provider, ['gemini', 'deepseek', 'openai'], true)) {
				$provider = 'gemini';
			}
			$post['module_wt_ai_provider'] = $provider;

			if (isset($post['module_wt_ai_api_key_gemini'])) {
				$post['module_wt_ai_api_key'] = $post['module_wt_ai_api_key_gemini'];
			}
			if (isset($post['module_wt_ai_model_gemini'])) {
				$post['module_wt_ai_model'] = $post['module_wt_ai_model_gemini'];
			}

			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_wt_ai', $post);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $this->route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $this->route);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_wt_ai', [
			'module_wt_ai_status'              => 1,
			'module_wt_ai_provider'            => 'gemini',
			'module_wt_ai_api_key'             => '',
			'module_wt_ai_api_key_gemini'      => '',
			'module_wt_ai_api_key_deepseek'    => '',
			'module_wt_ai_api_key_openai'      => '',
			'module_wt_ai_model'               => 'gemini-3.6-flash',
			'module_wt_ai_model_gemini'        => 'gemini-3.6-flash',
			'module_wt_ai_model_deepseek'      => 'deepseek-chat',
			'module_wt_ai_model_openai'        => 'gpt-4o-mini',
			'module_wt_ai_prompt_translate'    => $this->defaultTranslatePrompt(),
			'module_wt_ai_prompt_seo'          => $this->defaultSeoPrompt(),
			'module_wt_ai_do_translate'        => 1,
			'module_wt_ai_do_meta_title'       => 1,
			'module_wt_ai_do_meta_description' => 1,
			'module_wt_ai_do_meta_h1'          => 1,
			'module_wt_ai_do_meta_keyword'     => 1,
			'module_wt_ai_overwrite'           => 0,
			'module_wt_ai_product'             => 1,
			'module_wt_ai_category'            => 1,
			'module_wt_ai_article'             => 1,
		]);

		$this->registerEvents();
	}

	public function uninstall(): void {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_ai');
		$this->unregisterEvents();
	}

	private function registerEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_ai');

		$events = [
			['admin/view/catalog/product_form/after', 'extension/wt_ai/module/wt_ai.eventForm'],
			['admin/view/catalog/category_form/after', 'extension/wt_ai/module/wt_ai.eventForm'],
			['admin/view/cms/article_form/after', 'extension/wt_ai/module/wt_ai.eventForm'],
		];

		foreach ($events as [$trigger, $action]) {
			$this->model_setting_event->addEvent([
				'code'        => 'wt_ai',
				'description' => 'WT AI button',
				'trigger'     => $trigger,
				'action'      => $action,
				'status'      => true,
				'sort_order'  => 0,
			]);
		}
	}

	private function unregisterEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_ai');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function eventForm(string &$route, array &$data, mixed &$output): void {
		if (!$this->config->get('module_wt_ai_status') || !is_string($output)) {
			return;
		}

		$entity = '';
		$id = 0;

		if (str_contains($route, 'catalog/product')) {
			if (!$this->config->get('module_wt_ai_product')) {
				return;
			}
			$entity = 'product';
			$id = (int)($this->request->get['product_id'] ?? 0);
		} elseif (str_contains($route, 'catalog/category')) {
			if (!$this->config->get('module_wt_ai_category')) {
				return;
			}
			$entity = 'category';
			$id = (int)($this->request->get['category_id'] ?? 0);
		} elseif (str_contains($route, 'cms/article')) {
			if (!$this->config->get('module_wt_ai_article')) {
				return;
			}
			$entity = 'article';
			$id = (int)($this->request->get['article_id'] ?? 0);
		} else {
			return;
		}

		$this->load->language($this->route);

		$btn = $this->load->view('extension/wt_ai/module/wt_ai_button', [
			'entity'         => $entity,
			'entity_id'      => $id,
			'action'         => $this->url->link($this->route . '.run', 'user_token=' . $this->session->data['user_token']),
			'button_run'     => $this->language->get('button_run'),
			'text_need_save' => $this->language->get('text_need_save'),
			'text_running'   => $this->language->get('text_running'),
			'text_done'      => $this->language->get('text_done'),
		]);

		$needle = '<div class="float-end">';
		if (str_contains($output, $needle)) {
			$output = str_replace($needle, $needle . $btn, $output);
		}
	}

	public function test(): void {
		$this->load->language($this->route);
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->response->setOutput(json_encode([
				'ok'      => false,
				'status'  => 'error',
				'message' => $this->language->get('error_permission'),
			]));
			return;
		}

		$provider = (string)($this->request->post['provider'] ?? '');
		$api_key = (string)($this->request->post['api_key'] ?? '');
		$model = (string)($this->request->post['model'] ?? '');

		$this->load->model('extension/wt_ai/module/wt_ai');
		$result = $this->model_extension_wt_ai_module_wt_ai->testConnection($provider, $api_key, $model);

		if (!empty($result['ok'])) {
			$result['message'] = $this->language->get('text_connected');
		} elseif (($result['message'] ?? '') === 'API key is empty') {
			$result['message'] = $this->language->get('error_api_key');
		}

		$this->response->setOutput(json_encode($result));
	}

	public function run(): void {
		$this->load->language($this->route);
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->response->setOutput(json_encode(['ok' => false, 'message' => $this->language->get('error_permission')]));
			return;
		}

		if (!$this->config->get('module_wt_ai_status')) {
			$this->response->setOutput(json_encode(['ok' => false, 'message' => $this->language->get('error_disabled')]));
			return;
		}

		$this->load->model('extension/wt_ai/module/wt_ai');

		if ($this->model_extension_wt_ai_module_wt_ai->getProviderApiKey() === '') {
			$this->response->setOutput(json_encode(['ok' => false, 'message' => $this->language->get('error_api_key')]));
			return;
		}

		$entity = (string)($this->request->post['entity'] ?? '');
		$id = (int)($this->request->post['entity_id'] ?? 0);

		if ($id <= 0 || !in_array($entity, ['product', 'category', 'article'], true)) {
			$this->response->setOutput(json_encode(['ok' => false, 'message' => $this->language->get('text_need_save')]));
			return;
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(300);
		}

		try {
			$result = $this->model_extension_wt_ai_module_wt_ai->process($entity, $id);
			$this->response->setOutput(json_encode([
				'ok'      => true,
				'message' => $result['message'],
				'log'     => $result['log'],
			]));
		} catch (\Throwable $e) {
			$this->response->setOutput(json_encode(['ok' => false, 'message' => $e->getMessage()]));
		}
	}

	private function defaultTranslatePrompt(): string {
		return "Ты профессиональный переводчик для интернет-магазина.\n"
			. "Переведи поля JSON с языка {source_lang} на язык {target_lang}.\n"
			. "HTML-теги в description не трогай. Не добавляй SEO-текст в описание.\n"
			. "Верни ТОЛЬКО валидный JSON с теми же ключами, что пришли на вход.";
	}

	private function defaultSeoPrompt(): string {
		return "Ты SEO-копирайтер интернет-магазина.\n"
			. "Язык ответа: {target_lang}. По содержимому товара/категории/статьи сгенерируй SEO-поля.\n"
			. "Правила: meta_title ≠ meta_h1; не вставляй H1 и title в текст description.\n"
			. "meta_title до ~60 символов, meta_description до ~160, meta_keyword через запятую.\n"
			. "Верни ТОЛЬКО JSON с запрошенными ключами.";
	}
}
