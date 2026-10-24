<?php
namespace Opencart\Admin\Controller\Extension\WtFilter\Module;

/**
 * Модуль wt_filter (OpenCart 3).
 * Пока только: настройки статуса + AJAX copyFilters (опции/фильтры/атрибуты → свои таблицы).
 */
class WtFilter extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/wt_filter/module/wt_filter';

	private $error = array();

	public function index() {
		$this->load->language($this->route);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->ensureProductEvents();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post = $this->request->post;

			if (isset($post['module_wt_filter_accent_color'])) {
				$post['module_wt_filter_accent_color'] = $this->normalizeAccentColor($post['module_wt_filter_accent_color']);
			}

			$post['module_wt_filter_group_expanded'] = $this->normalizeGroupExpanded(
				isset($post['module_wt_filter_group_expanded']) ? $post['module_wt_filter_group_expanded'] : []
			);

			$this->model_setting_setting->editSetting('module_wt_filter', $post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/wt_filter/module/wt_filter', 'user_token=' . $this->session->data['user_token'])
		);

		$data['action'] = $this->url->link('extension/wt_filter/module/wt_filter', 'user_token=' . $this->session->data['user_token']);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->post['module_wt_filter_status'])) {
			$data['module_wt_filter_status'] = $this->request->post['module_wt_filter_status'];
		} else {
			$data['module_wt_filter_status'] = $this->config->get('module_wt_filter_status');
		}

		if (isset($this->request->post['module_wt_filter_attribute_separator'])) {
			$data['module_wt_filter_attribute_separator'] = $this->request->post['module_wt_filter_attribute_separator'];
		} else {
			$separator = $this->config->get('module_wt_filter_attribute_separator');
			// По умолчанию запятая (если ещё не сохраняли настройку)
			$data['module_wt_filter_attribute_separator'] = ($separator !== null && $separator !== false) ? $separator : ',';
		}

		if (isset($this->request->post['module_wt_filter_price_special'])) {
			$data['module_wt_filter_price_special'] = $this->request->post['module_wt_filter_price_special'];
		} else {
			$price_special = $this->config->get('module_wt_filter_price_special');
			$data['module_wt_filter_price_special'] = ($price_special !== null && $price_special !== false) ? $price_special : 0;
		}

		if (isset($this->request->post['module_wt_filter_discount'])) {
			$data['module_wt_filter_discount'] = $this->request->post['module_wt_filter_discount'];
		} else {
			$discount = $this->config->get('module_wt_filter_discount');
			$data['module_wt_filter_discount'] = ($discount !== null && $discount !== false) ? $discount : 1;
		}

		if (isset($this->request->post['module_wt_filter_seo_text_position'])) {
			$data['module_wt_filter_seo_text_position'] = $this->request->post['module_wt_filter_seo_text_position'];
		} else {
			$seo_pos = $this->config->get('module_wt_filter_seo_text_position');
			$data['module_wt_filter_seo_text_position'] = in_array($seo_pos, ['above', 'below']) ? $seo_pos : 'below';
		}

		if (isset($this->request->post['module_wt_filter_show_counts'])) {
			$data['module_wt_filter_show_counts'] = $this->request->post['module_wt_filter_show_counts'];
		} else {
			$show_counts = $this->config->get('module_wt_filter_show_counts');
			$data['module_wt_filter_show_counts'] = ($show_counts !== null && $show_counts !== false && $show_counts !== '')
				? (int)$show_counts
				: 1;
		}

		if (isset($this->request->post['module_wt_filter_accent_color'])) {
			$data['module_wt_filter_accent_color'] = $this->normalizeAccentColor($this->request->post['module_wt_filter_accent_color']);
		} else {
			$data['module_wt_filter_accent_color'] = $this->normalizeAccentColor($this->config->get('module_wt_filter_accent_color'));
		}

		if (isset($this->request->post['module_wt_filter_group_expanded'])) {
			$data['module_wt_filter_group_expanded'] = $this->normalizeGroupExpanded($this->request->post['module_wt_filter_group_expanded']);
		} else {
			$data['module_wt_filter_group_expanded'] = $this->normalizeGroupExpanded($this->config->get('module_wt_filter_group_expanded'));
		}

		$data['types'] = array('checkbox', 'radio', 'select');

		$this->load->model('setting/store');
		$data['stores'] = $this->model_setting_store->getStores();

		$data['help_copy_btn'] = $this->language->get('help_copy_btn');
		$data['help_update_btn'] = $this->language->get('help_update_btn');
		$data['help_copy'] = $this->language->get('help_copy');
		$data['button_copy'] = $this->language->get('button_copy');
		$data['button_update'] = $this->language->get('button_update');
		$data['text_complete_update'] = $this->language->get('text_complete_update');
		$data['catalog_list'] = $this->url->link('extension/wt_filter/catalog/wt_filter', 'user_token=' . $this->session->data['user_token']);
		$data['text_catalog_list'] = $this->language->get('text_catalog_list');

		// SEO Pages AJAX endpoints (decode &amp; — url->link escapes for HTML, AJAX needs raw &)
		$token = 'user_token=' . $this->session->data['user_token'];
		$data['seo_list'] = html_entity_decode($this->url->link('extension/wt_filter/module/wt_filter.seoList', $token), ENT_QUOTES, 'UTF-8');
		$data['seo_get'] = html_entity_decode($this->url->link('extension/wt_filter/module/wt_filter.seoGet', $token), ENT_QUOTES, 'UTF-8');
		$data['seo_save'] = html_entity_decode($this->url->link('extension/wt_filter/module/wt_filter.seoSave', $token), ENT_QUOTES, 'UTF-8');
		$data['seo_delete'] = html_entity_decode($this->url->link('extension/wt_filter/module/wt_filter.seoDelete', $token), ENT_QUOTES, 'UTF-8');
		$data['seo_preview'] = html_entity_decode($this->url->link('extension/wt_filter/module/wt_filter.seoPreview', $token), ENT_QUOTES, 'UTF-8');
		$data['seo_import'] = html_entity_decode($this->url->link('extension/wt_filter/module/wt_filter.seoImport', $token), ENT_QUOTES, 'UTF-8');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/wt_filter/module/wt_filter', $data));
	}

	/**
	 * AJAX: список SEO-посадочных (пагинация).
	 */
	public function seoList() {
		$this->load->language($this->route);
		$this->load->model('extension/wt_filter/catalog/wt_filter');

		$json = [];

		if (!$this->user->hasPermission('access', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
			$this->jsonOut($json);
			return;
		}

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		if ($page < 1) {
			$page = 1;
		}

		$limit = 12;
		$total = $this->model_extension_wt_filter_catalog_wt_filter->getTotalSeoPages();
		$pages = $this->model_extension_wt_filter_catalog_wt_filter->getSeoPages([
			'start' => ($page - 1) * $limit,
			'limit' => $limit,
		]);

		$rows = [];

		foreach ($pages as $row) {
			$rows[] = [
				'seo_page_id' => (int)$row['seo_page_id'],
				'url'         => $row['url'],
				'h1'          => $row['h1'],
				'status'      => (int)$row['status'],
				'status_text' => ((int)$row['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
			];
		}

		$json['success'] = true;
		$json['total'] = $total;
		$json['page'] = $page;
		$json['pages'] = $total ? (int)ceil($total / $limit) : 1;
		$json['rows'] = $rows;

		$this->jsonOut($json);
	}

	/**
	 * AJAX: одна SEO-запись для редактирования.
	 */
	public function seoGet() {
		$this->load->language($this->route);
		$this->load->model('extension/wt_filter/catalog/wt_filter');

		$json = [];

		if (!$this->user->hasPermission('access', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
			$this->jsonOut($json);
			return;
		}

		$seo_page_id = isset($this->request->get['seo_page_id']) ? (int)$this->request->get['seo_page_id'] : 0;
		$row = $this->model_extension_wt_filter_catalog_wt_filter->getSeoPage($seo_page_id);

		if (!$row) {
			$json['error'] = $this->language->get('error_seo_not_found');
			$this->jsonOut($json);
			return;
		}

		$json['success'] = true;
		$json['page'] = [
			'seo_page_id'      => (int)$row['seo_page_id'],
			'url'              => $row['url'],
			'h1'               => $row['h1'],
			'meta_title'       => $row['meta_title'],
			'meta_description' => $row['meta_description'],
			'meta_keyword'     => $row['meta_keyword'],
			'description'      => $row['description'],
			'status'           => (int)$row['status'],
		];

		$this->jsonOut($json);
	}

	/**
	 * AJAX: превью URL посадочной (категория/бренд, фильтр, конфликт).
	 */
	public function seoPreview() {
		$this->load->language($this->route);
		$this->load->model('extension/wt_filter/catalog/wt_filter');

		$json = [];

		if (!$this->user->hasPermission('access', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
			$this->jsonOut($json);
			return;
		}

		$url = isset($this->request->get['url']) ? (string)$this->request->get['url'] : '';

		if ($url === '' && isset($this->request->post['url'])) {
			$url = (string)$this->request->post['url'];
		}

		$exclude_id = 0;

		if (isset($this->request->get['seo_page_id'])) {
			$exclude_id = (int)$this->request->get['seo_page_id'];
		} elseif (isset($this->request->post['seo_page_id'])) {
			$exclude_id = (int)$this->request->post['seo_page_id'];
		}

		$preview = $this->model_extension_wt_filter_catalog_wt_filter->getSeoPagePreview($url, $exclude_id);

		if (!empty($preview['error'])) {
			$map = [
				'empty'    => 'error_seo_url',
				'path'     => 'error_seo_url',
				'filter'   => 'error_seo_filter',
				'category' => 'error_seo_category',
			];
			$key = isset($map[$preview['error']]) ? $map[$preview['error']] : 'error_seo_url';
			$json['error'] = $this->language->get($key);
			$this->jsonOut($json);
			return;
		}

		$scope_label = ($preview['scope_type'] === 'manufacturer')
			? $this->language->get('text_seo_scope_manufacturer')
			: $this->language->get('text_seo_scope_category');

		$json['success'] = true;
		$json['preview'] = [
			'url'           => $preview['url'],
			'filter_params' => $preview['filter_params'],
			'scope_type'    => $preview['scope_type'],
			'scope_label'   => $scope_label,
			'scope_name'    => $preview['scope_name'],
			'facets'        => $preview['facets'],
			'href'          => $preview['href'],
		];

		if (!empty($preview['conflict'])) {
			$json['preview']['conflict'] = [
				'seo_page_id' => (int)$preview['conflict']['seo_page_id'],
				'h1'          => $preview['conflict']['h1'],
				'url'         => $preview['conflict']['url'],
				'message'     => sprintf(
					$this->language->get('error_seo_duplicate'),
					(int)$preview['conflict']['seo_page_id'],
					$preview['conflict']['h1'] !== '' ? $preview['conflict']['h1'] : $preview['conflict']['url']
				),
			];
		}

		$this->jsonOut($json);
	}

	/**
	 * AJAX: создать / обновить SEO-посадочную.
	 */
	public function seoSave() {
		$this->load->language($this->route);
		$this->load->model('extension/wt_filter/catalog/wt_filter');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
			$this->jsonOut($json);
			return;
		}

		$seo_page_id = isset($this->request->post['seo_page_id']) ? (int)$this->request->post['seo_page_id'] : 0;
		$url = isset($this->request->post['url']) ? (string)$this->request->post['url'] : '';
		$parsed = $this->model_extension_wt_filter_catalog_wt_filter->parseSeoPageUrl($url);

		if (!empty($parsed['error'])) {
			$map = [
				'empty'    => 'error_seo_url',
				'path'     => 'error_seo_url',
				'filter'   => 'error_seo_filter',
				'category' => 'error_seo_category',
			];
			$key = isset($map[$parsed['error']]) ? $map[$parsed['error']] : 'error_seo_url';
			$json['error'] = $this->language->get($key);
			$this->jsonOut($json);
			return;
		}

		$h1 = isset($this->request->post['h1']) ? trim((string)$this->request->post['h1']) : '';

		if ($h1 === '') {
			$json['error'] = $this->language->get('error_seo_h1');
			$this->jsonOut($json);
			return;
		}

		$data = [
			'category_id'      => (int)$parsed['category_id'],
			'manufacturer_id'  => !empty($parsed['manufacturer_id']) ? (int)$parsed['manufacturer_id'] : 0,
			'url'              => $parsed['url'],
			'filter_params'    => $parsed['filter_params'],
			'h1'               => $h1,
			'meta_title'       => isset($this->request->post['meta_title']) ? (string)$this->request->post['meta_title'] : '',
			'meta_description' => isset($this->request->post['meta_description']) ? (string)$this->request->post['meta_description'] : '',
			'meta_keyword'     => isset($this->request->post['meta_keyword']) ? (string)$this->request->post['meta_keyword'] : '',
			'description'      => isset($this->request->post['description']) ? (string)$this->request->post['description'] : '',
			'status'           => !empty($this->request->post['status']) ? 1 : 0,
		];

		$conflict = $this->model_extension_wt_filter_catalog_wt_filter->findSeoPageConflict($data, $seo_page_id);

		if ($conflict) {
			$json['error'] = sprintf(
				$this->language->get('error_seo_duplicate'),
				(int)$conflict['seo_page_id'],
				$conflict['h1'] !== '' ? $conflict['h1'] : $conflict['url']
			);
			$json['conflict_id'] = (int)$conflict['seo_page_id'];
			$this->jsonOut($json);
			return;
		}

		if ($seo_page_id > 0) {
			$this->model_extension_wt_filter_catalog_wt_filter->editSeoPage($seo_page_id, $data);
			$json['seo_page_id'] = $seo_page_id;
		} else {
			$json['seo_page_id'] = $this->model_extension_wt_filter_catalog_wt_filter->addSeoPage($data);
		}

		$json['success'] = true;
		$json['message'] = $this->language->get('text_seo_saved');
		$json['url'] = $data['url'];
		$json['filter_params'] = $data['filter_params'];

		$this->jsonOut($json);
	}

	/**
	 * AJAX: удалить SEO-посадочную.
	 */
	public function seoDelete() {
		$this->load->language($this->route);
		$this->load->model('extension/wt_filter/catalog/wt_filter');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
			$this->jsonOut($json);
			return;
		}

		$seo_page_id = isset($this->request->post['seo_page_id']) ? (int)$this->request->post['seo_page_id'] : 0;

		if ($seo_page_id < 1) {
			$json['error'] = $this->language->get('error_seo_not_found');
			$this->jsonOut($json);
			return;
		}

		$this->model_extension_wt_filter_catalog_wt_filter->deleteSeoPage($seo_page_id);
		$json['success'] = true;
		$json['message'] = $this->language->get('text_seo_deleted');

		$this->jsonOut($json);
	}

	/**
	 * AJAX: массовый импорт SEO-посадочных — одна ссылка на строку.
	 */
	public function seoImport() {
		$this->load->language($this->route);
		$this->load->model('extension/wt_filter/catalog/wt_filter');

		$json = [
			'created'  => 0,
			'skipped'  => 0,
			'errors'   => 0,
			'details'  => [],
		];

		if (!$this->user->hasPermission('modify', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
			$this->jsonOut($json);
			return;
		}

		$raw = isset($this->request->post['urls']) ? (string)$this->request->post['urls'] : '';
		$raw = str_replace(["\r\n", "\r"], "\n", $raw);
		$lines = preg_split('/\n+/', $raw);
		$urls = [];

		foreach ($lines as $line) {
			$line = trim((string)$line);

			if ($line === '') {
				continue;
			}

			$urls[] = $line;
		}

		$urls = array_values(array_unique($urls));

		if (!$urls) {
			$json['error'] = $this->language->get('error_seo_import_empty');
			$this->jsonOut($json);
			return;
		}

		if (count($urls) > 200) {
			$json['error'] = $this->language->get('error_seo_import_limit');
			$this->jsonOut($json);
			return;
		}

		$error_map = [
			'empty'    => 'error_seo_url',
			'path'     => 'error_seo_url',
			'filter'   => 'error_seo_filter',
			'category' => 'error_seo_category',
		];

		foreach ($urls as $url) {
			$preview = $this->model_extension_wt_filter_catalog_wt_filter->getSeoPagePreview($url, 0);

			if (!empty($preview['error'])) {
				$key = isset($error_map[$preview['error']]) ? $error_map[$preview['error']] : 'error_seo_url';
				$json['errors']++;
				$json['details'][] = [
					'url'     => $url,
					'status'  => 'error',
					'message' => $this->language->get($key),
				];
				continue;
			}

			$data = [
				'category_id'      => (int)$preview['category_id'],
				'manufacturer_id'  => (int)$preview['manufacturer_id'],
				'url'              => $preview['url'],
				'filter_params'    => $preview['filter_params'],
				'h1'               => $this->buildSeoImportH1($preview),
				'meta_title'       => $this->buildSeoImportH1($preview),
				'meta_description' => '',
				'meta_keyword'     => '',
				'description'      => '',
				'status'           => 1,
			];

			$conflict = $this->model_extension_wt_filter_catalog_wt_filter->findSeoPageConflict($data, 0);

			if ($conflict) {
				$json['skipped']++;
				$json['details'][] = [
					'url'     => $url,
					'status'  => 'skipped',
					'message' => sprintf(
						$this->language->get('error_seo_duplicate'),
						(int)$conflict['seo_page_id'],
						$conflict['h1'] !== '' ? $conflict['h1'] : $conflict['url']
					),
				];
				continue;
			}

			$seo_page_id = $this->model_extension_wt_filter_catalog_wt_filter->addSeoPage($data);
			$json['created']++;
			$json['details'][] = [
				'url'         => $data['url'],
				'status'      => 'created',
				'seo_page_id' => (int)$seo_page_id,
				'h1'          => $data['h1'],
				'message'     => $data['h1'],
			];
		}

		$json['success'] = true;
		$json['message'] = sprintf(
			$this->language->get('text_seo_import_done'),
			(int)$json['created'],
			(int)$json['skipped'],
			(int)$json['errors']
		);

		$this->jsonOut($json);
	}

	/**
	 * Авто H1 для массового импорта: «Категория — значение1, значение2».
	 */
	private function buildSeoImportH1(array $preview) {
		$parts = [];

		if (!empty($preview['scope_name'])) {
			$parts[] = trim((string)$preview['scope_name']);
		}

		if (!empty($preview['facets']) && is_array($preview['facets'])) {
			foreach ($preview['facets'] as $facet) {
				if (!empty($facet['values'])) {
					$parts[] = trim((string)$facet['values']);
				}
			}
		}

		$h1 = implode(' — ', array_filter($parts));

		return $h1 !== '' ? $h1 : (string)$preview['url'];
	}

	private function jsonOut(array $json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: один шаг копирования (для прогресс-бара).
	 * POST step = truncate|option|filter|attribute|finalize
	 */
	public function copyFilters() {
		$json = array();

		$this->load->language($this->route);

		if (!$this->user->hasPermission('modify', 'extension/wt_filter/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && empty($json['error'])) {
			if (!isset($this->request->post['copy_store'])) {
				$json['error'] = $this->language->get('error_copy_store');
			}

			if (empty($this->request->post['copy_type'])) {
				$json['error'] = $this->language->get('error_copy_type');
			}

			$step = isset($this->request->post['step']) ? (string)$this->request->post['step'] : '';
			$allowed = array('truncate', 'option', 'filter', 'attribute', 'finalize');

			if (!in_array($step, $allowed)) {
				$json['error'] = $this->language->get('error_copy_step');
			}

			if (empty($json['error'])) {
				$this->load->model('extension/wt_filter/catalog/wt_filter');
				$this->model_extension_wt_filter_catalog_wt_filter->copyFiltersStep($step, $this->request->post);

				$messages = array(
					'truncate'  => $this->language->get('text_progress_truncate'),
					'option'    => $this->language->get('text_progress_option'),
					'filter'    => $this->language->get('text_progress_filter'),
					'attribute' => $this->language->get('text_progress_attribute'),
					'finalize'  => $this->language->get('text_progress_finalize'),
				);

				$json['success'] = true;
				$json['step'] = $step;
				$json['message'] = isset($messages[$step]) ? $messages[$step] : $step;

				if ($step === 'finalize') {
					$json['complete'] = true;
					$json['message'] = $this->language->get('text_complete');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Event: admin/view/common/column_left/before — пункт меню Каталог.
	 *
	 * @param string               $route
	 * @param array<string, mixed> $data
	 * @param string               $code
	 * @param mixed                $output
	 */
	public function eventMenu(string &$route, array &$data, string &$code = '', mixed &$output = null): void {
		if (!$this->user->hasPermission('access', 'extension/wt_filter/catalog/wt_filter')) {
			return;
		}

		if (empty($data['menus']) || !is_array($data['menus'])) {
			return;
		}

		foreach ($data['menus'] as &$menu) {
			if (($menu['id'] ?? '') !== 'menu-catalog') {
				continue;
			}

			foreach ($menu['children'] as $child) {
				if (($child['href'] ?? '') && str_contains((string)$child['href'], 'extension/wt_filter/catalog/wt_filter')) {
					return;
				}
			}

			$menu['children'][] = [
				'name'     => 'WT Filter',
				'href'     => $this->url->link('extension/wt_filter/catalog/wt_filter', 'user_token=' . $this->session->data['user_token']),
				'children' => []
			];

			break;
		}
		unset($menu);
	}

	public function install(): void {
		$this->load->model('extension/wt_filter/catalog/wt_filter');
		$this->model_extension_wt_filter_catalog_wt_filter->install();

		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/wt_filter/catalog/wt_filter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/wt_filter/catalog/wt_filter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/wt_filter/module/wt_filter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/wt_filter/module/wt_filter');

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_wt_filter', [
			'module_wt_filter_status'              => 0,
			'module_wt_filter_attribute_separator' => ',',
			'module_wt_filter_price_special'       => 0,
			'module_wt_filter_discount'            => 1,
			'module_wt_filter_seo_text_position'   => 'below',
			'module_wt_filter_show_counts'         => 1,
			'module_wt_filter_accent_color'        => '#229ac8',
			'module_wt_filter_group_expanded'     => $this->defaultGroupExpanded(),
		]);

		$this->registerMenuEvent();
		$this->registerProductEvents();
		$this->registerCatalogEvents();
		$this->registerProductFormEvent();
	}

	public function uninstall(): void {
		$this->unregisterProductEvents();

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_filter_menu');
		$this->model_setting_event->deleteEventByCode('wt_filter_catalog');
		$this->model_setting_event->deleteEventByCode('wt_filter_product_form');

		$this->load->model('extension/wt_filter/catalog/wt_filter');
		$this->model_extension_wt_filter_catalog_wt_filter->uninstall();

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_filter');
	}

	private function registerMenuEvent(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_filter_menu');
		$this->model_setting_event->addEvent([
			'code'        => 'wt_filter_menu',
			'description' => 'WT Filter catalog menu item',
			'trigger'     => 'admin/view/common/column_left/before',
			'action'      => 'extension/wt_filter/module/wt_filter.eventMenu',
			'status'      => 1,
			'sort_order'  => 1
		]);
	}

	/**
	 * Events: точечная переиндексация товара (OC4: model.*.method/after).
	 */
	private function registerProductEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_filter');

		$events = [
			[
				'code'        => 'wt_filter',
				'description' => 'WT Filter index after addProduct',
				'trigger'     => 'admin/model/catalog/product.addProduct/after',
				'action'      => 'extension/wt_filter/module/wt_filter.eventOnProductSave',
			],
			[
				'code'        => 'wt_filter',
				'description' => 'WT Filter index after editProduct',
				'trigger'     => 'admin/model/catalog/product.editProduct/after',
				'action'      => 'extension/wt_filter/module/wt_filter.eventOnProductSave',
			],
			[
				'code'        => 'wt_filter',
				'description' => 'WT Filter index after deleteProduct',
				'trigger'     => 'admin/model/catalog/product.deleteProduct/after',
				'action'      => 'extension/wt_filter/module/wt_filter.eventOnProductDelete',
			],
		];

		foreach ($events as $event) {
			$this->model_setting_event->addEvent([
				'code'        => $event['code'],
				'description' => $event['description'],
				'trigger'     => $event['trigger'],
				'action'      => $event['action'],
				'status'      => 1,
				'sort_order'  => 1
			]);
		}
	}

	private function unregisterProductEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_filter');
	}

	private function registerCatalogEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_filter_catalog');

		$this->model_setting_event->addEvent([
			'code'        => 'wt_filter_catalog',
			'description' => 'WT Filter early initialise + SEO rewrite',
			'trigger'     => 'catalog/controller/startup/seo_url.index/before',
			'action'      => 'extension/wt_filter/module/wt_filter.eventInitialise',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	private function registerProductFormEvent(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_filter_product_form');

		// До header — иначе addScript/addStyle не попадут в страницу
		$this->model_setting_event->addEvent([
			'code'        => 'wt_filter_product_form',
			'description' => 'WT Filter CSS/JS on product form (controller)',
			'trigger'     => 'admin/controller/catalog/product.form/before',
			'action'      => 'extension/wt_filter/module/wt_filter.eventProductFormAssets',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->model_setting_event->addEvent([
			'code'        => 'wt_filter_product_form',
			'description' => 'WT Filter tab config on product form (view)',
			'trigger'     => 'admin/view/catalog/product_form/before',
			'action'      => 'extension/wt_filter/module/wt_filter.eventProductForm',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	/**
	 * Event: admin/controller/catalog/product.form/before — CSS/JS до рендера header.
	 *
	 * В админке <base href=".../admin/">, поэтому путь к extension — через ../
	 */
	public function eventProductFormAssets(string &$route = '', array &$args = []): void {
		$this->document->addStyle('../extension/wt_filter/admin/view/stylesheet/wt_filter/wt_filter.css');
	}

	/**
	 * Event: admin/view/catalog/product_form/before — конфиг вкладки (как в OC3).
	 *
	 * Нельзя load->language() без prefix: heading_title станет «WT Filter» и
	 * затрёт стандартный заголовок товара (в OC4 он берётся из language dump).
	 *
	 * JS подключаем в footer ПОСЛЕ конфига (base=/admin/ → путь ../extension/...).
	 */
	public function eventProductForm(string &$route = '', array &$data = [], mixed &$code = '', mixed &$output = null): void {
		$lang = $this->getProductFormLanguage();

		// Только ключи вкладки — без подмены heading_title / text_form товара
		$data['tab_wt_filter'] = $lang['tab_wt_filter'];
		$data['wt_filter_select_category'] = $lang['wt_filter_select_category'];

		$js = '../extension/wt_filter/admin/view/javascript/wt_filter/wt_filter.js';

		$inline = '<script>window.wt_filter=window.wt_filter||{};wt_filter.php=Object.assign(wt_filter.php||{},{'
			. 'user_token:' . json_encode($this->session->data['user_token'] ?? '') . ','
			. 'tab_wt_filter:' . json_encode($lang['tab_wt_filter']) . ','
			. 'entry_values:' . json_encode($lang['entry_values']) . ','
			. 'wt_filter_select_category:' . json_encode($lang['wt_filter_select_category']) . ','
			. 'text_select:' . json_encode($lang['text_select'])
			. '});</script>'
			. '<script src="' . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . '"></script>';

		$footer = (string)($data['footer'] ?? '');

		if (str_contains($footer, '</body>')) {
			$data['footer'] = str_replace('</body>', $inline . '</body>', $footer);
		} else {
			$data['footer'] = $footer . $inline;
		}
	}

	/**
	 * Локальные строки для вкладки товара без merge в $this->language.
	 *
	 * @return array{tab_wt_filter:string,wt_filter_select_category:string,entry_values:string,text_select:string}
	 */
	private function getProductFormLanguage(): array {
		$defaults = [
			'tab_wt_filter'             => 'Filter options',
			'wt_filter_select_category' => 'First select a category for this product.',
			'entry_values'              => 'Add values for this option.',
			'text_select'               => 'Select',
		];

		$code = (string)$this->config->get('config_language_admin');
		$file = DIR_EXTENSION . 'wt_filter/admin/language/' . $code . '/catalog/wt_filter.php';

		if (!is_file($file)) {
			$file = DIR_EXTENSION . 'wt_filter/admin/language/en-gb/catalog/wt_filter.php';
		}

		if (!is_file($file)) {
			return $defaults;
		}

		$_ = [];
		require $file;

		return [
			'tab_wt_filter'             => $_['tab_wt_filter'] ?? $defaults['tab_wt_filter'],
			'wt_filter_select_category' => $_['wt_filter_select_category'] ?? $defaults['wt_filter_select_category'],
			'entry_values'              => $_['entry_values'] ?? $defaults['entry_values'],
			'text_select'               => $_['text_select'] ?? $defaults['text_select'],
		];
	}

	/**
	 * Если модуль уже стоял до Events — зарегистрировать хуки при открытии настроек.
	 */
	private function ensureProductEvents(): void {
		$this->load->model('setting/event');

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "event`
			WHERE `code` = 'wt_filter'");

		if (empty($query->row['total'])) {
			$this->registerProductEvents();
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "event`
			WHERE `code` = 'wt_filter_menu'");

		if (empty($query->row['total'])) {
			$this->registerMenuEvent();
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "event`
			WHERE `code` = 'wt_filter_catalog'");

		if (empty($query->row['total'])) {
			$this->registerCatalogEvents();
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "event`
			WHERE `code` = 'wt_filter_product_form'");

		if (empty($query->row['total'])) {
			$this->registerProductFormEvent();
		}
	}

	/**
	 * addProduct/after → $output = product_id
	 * editProduct/after → $args[0] = product_id
	 */
	public function eventOnProductSave(&$route, &$args, &$output): void {
		$product_id = 0;

		if ($output !== null && $output !== '' && !is_array($output)) {
			$product_id = (int)$output;
		} elseif (!empty($args[0]) && !is_array($args[0])) {
			$product_id = (int)$args[0];
		}

		if ($product_id < 1) {
			return;
		}

		$this->load->model('extension/wt_filter/catalog/wt_filter');

		// Вкладка товара: ручные значения приоритетнее автоиндекса из option/attribute
		if (!empty($this->request->post['wt_filter_product_option']) && is_array($this->request->post['wt_filter_product_option'])) {
			$this->model_extension_wt_filter_catalog_wt_filter->saveProductFilterValues($product_id, $this->request->post);
		} else {
			$this->model_extension_wt_filter_catalog_wt_filter->indexProduct($product_id);
		}
	}

	/**
	 * deleteProduct/after → $args[0] = product_id
	 */
	public function eventOnProductDelete(&$route, &$args, &$output): void {
		$product_id = (!empty($args[0]) && !is_array($args[0])) ? (int)$args[0] : 0;

		if ($product_id < 1) {
			return;
		}

		$this->load->model('extension/wt_filter/catalog/wt_filter');
		$this->model_extension_wt_filter_catalog_wt_filter->deleteProductFromIndex($product_id);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/wt_filter/module/wt_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Дефолты: цена / производитель / наличие / акции — десктоп открыт, мобилка закрыта.
	 */
	private function defaultGroupExpanded() {
		return [
			'p' => ['desktop' => 1, 'mobile' => 0],
			'm' => ['desktop' => 1, 'mobile' => 0],
			's' => ['desktop' => 1, 'mobile' => 0],
			'd' => ['desktop' => 1, 'mobile' => 0],
		];
	}

	private function normalizeGroupExpanded($raw) {
		$out = $this->defaultGroupExpanded();

		if (!is_array($raw)) {
			return $out;
		}

		foreach ($out as $key => $defaults) {
			$row = isset($raw[$key]) && is_array($raw[$key]) ? $raw[$key] : [];
			$out[$key] = [
				'desktop' => !empty($row['desktop']) ? 1 : 0,
				'mobile'  => !empty($row['mobile']) ? 1 : 0,
			];
		}

		return $out;
	}

	/**
	 * Допустимый цвет акцента витрины: #RGB / #RRGGBB.
	 */
	private function normalizeAccentColor($color) {
		$color = trim((string)$color);

		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
			$hex = substr($color, 1);
		} elseif (preg_match('/^([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
			$hex = $color;
		} else {
			return '#229ac8';
		}

		$hex = strtolower($hex);

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . $hex;
	}
}
