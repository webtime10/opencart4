<?php
namespace Opencart\Admin\Controller\Extension\PlumStore\Theme;

/**
 * Админ-контроллер темы Plum Store (OpenCart 4).
 */
class PlumStore extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/plum_store/theme/plum_store';

	public function install(): void {
		$this->registerPlumEvents();
		$this->load->model('extension/plum_store/theme/plum_store');
		$this->model_extension_plum_store_theme_plum_store->ensureSchema();

		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $this->route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $this->route);
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('plum_store_header');
		$this->model_setting_event->deleteEventByCode('plum_store_footer');
		$this->model_setting_event->deleteEventByCode('plum_store_menu');
		$this->model_setting_event->deleteEventByCode('plum_store_info');
		$this->model_setting_event->deleteEventByCode('plum_store_language');
		$this->model_setting_event->deleteEventByCode('plum_store_currency');
		$this->model_setting_event->deleteEventByCode('wtshop_header');
		$this->model_setting_event->deleteEventByCode('wtshop_footer');
	}

	private function registerPlumEvents(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('plum_store_header');
		$this->model_setting_event->deleteEventByCode('plum_store_footer');
		$this->model_setting_event->deleteEventByCode('plum_store_menu');
		$this->model_setting_event->deleteEventByCode('plum_store_info');
		$this->model_setting_event->deleteEventByCode('plum_store_language');
		$this->model_setting_event->deleteEventByCode('plum_store_currency');
		$this->model_setting_event->deleteEventByCode('wtshop_header');
		$this->model_setting_event->deleteEventByCode('wtshop_footer');

		$this->model_setting_event->addEvent([
			'code'        => 'plum_store_header',
			'description' => 'Plum Store header template',
			'trigger'     => 'catalog/view/common/header/before',
			'action'      => 'extension/plum_store/event/theme.header',
			'status'      => 1,
			'sort_order'  => 1
		]);

		$this->model_setting_event->addEvent([
			'code'        => 'plum_store_footer',
			'description' => 'Plum Store footer template',
			'trigger'     => 'catalog/view/common/footer/before',
			'action'      => 'extension/plum_store/event/theme.footer',
			'status'      => 1,
			'sort_order'  => 1
		]);

		$this->model_setting_event->addEvent([
			'code'        => 'plum_store_menu',
			'description' => 'Plum Store admin menu shortcut',
			'trigger'     => 'admin/view/common/column_left/before',
			'action'      => 'extension/plum_store/theme/plum_store.eventMenu',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->model_setting_event->addEvent([
			'code'        => 'plum_store_language',
			'description' => 'Plum Store language switcher template',
			'trigger'     => 'catalog/view/common/language/before',
			'action'      => 'extension/plum_store/event/theme.language',
			'status'      => 1,
			'sort_order'  => 1
		]);

		$this->model_setting_event->addEvent([
			'code'        => 'plum_store_currency',
			'description' => 'Plum Store currency switcher template',
			'trigger'     => 'catalog/view/common/currency/before',
			'action'      => 'extension/plum_store/event/theme.currency',
			'status'      => 1,
			'sort_order'  => 1
		]);

		$info_events = [
			['admin/view/catalog/information_form/after', 'extension/plum_store/theme/plum_store.eventInformationForm'],
			['admin/model/catalog/information.addInformation/after', 'extension/plum_store/theme/plum_store.eventInformationAdd'],
			['admin/model/catalog/information.editInformation/after', 'extension/plum_store/theme/plum_store.eventInformationEdit'],
			['admin/model/catalog/information.deleteInformation/after', 'extension/plum_store/theme/plum_store.eventInformationDelete'],
		];

		foreach ($info_events as [$trigger, $action]) {
			$this->model_setting_event->addEvent([
				'code'        => 'plum_store_info',
				'description' => 'Plum Store information top/bottom flags',
				'trigger'     => $trigger,
				'action'      => $action,
				'status'      => 1,
				'sort_order'  => 0
			]);
		}
	}

	/**
	 * admin/view/common/column_left/before
	 */
	public function eventMenu(string &$route, array &$data, string &$code = '', string &$output = ''): void {
		if (!$this->user->hasPermission('access', $this->route)) {
			return;
		}

		if (!isset($data['menus']) || !is_array($data['menus'])) {
			return;
		}

		foreach ($data['menus'] as $menu) {
			if (isset($menu['id']) && $menu['id'] === 'menu-plum-store') {
				return;
			}
		}

		$this->load->language($this->route);

		$item = [
			'id'       => 'menu-plum-store',
			'icon'     => 'fa-solid fa-palette',
			'name'     => $this->language->get('text_menu'),
			'href'     => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . '&store_id=0'),
			'children' => []
		];

		array_splice($data['menus'], 1, 0, [$item]);
	}

	/**
	 * Галочки top/bottom на форме Information (OC4 без родного bottom).
	 */
	public function eventInformationForm(string &$route, array &$data, mixed &$output): void {
		if (!is_string($output) || str_contains($output, 'name="plum_top"')) {
			return;
		}

		$this->load->model('extension/plum_store/theme/plum_store');
		$this->model_extension_plum_store_theme_plum_store->ensureSchema();
		$this->load->language($this->route);

		$information_id = (int)($this->request->get['information_id'] ?? 0);
		$flags = $this->model_extension_plum_store_theme_plum_store->getFlags($information_id);

		$top_checked = $flags['top'] ? ' checked' : '';
		$bottom_checked = $flags['bottom'] ? ' checked' : '';

		$html = '<div class="row mb-3">'
			. '<label class="col-sm-2 col-form-label">' . $this->language->get('entry_top') . '</label>'
			. '<div class="col-sm-10"><div class="form-check form-switch form-switch-lg">'
			. '<input type="hidden" name="plum_top" value="0"/>'
			. '<input type="checkbox" name="plum_top" value="1" id="input-plum-top" class="form-check-input"' . $top_checked . '/>'
			. '<div class="form-text">' . $this->language->get('help_top') . '</div>'
			. '</div></div></div>'
			. '<div class="row mb-3">'
			. '<label class="col-sm-2 col-form-label">' . $this->language->get('entry_bottom') . '</label>'
			. '<div class="col-sm-10"><div class="form-check form-switch form-switch-lg">'
			. '<input type="hidden" name="plum_bottom" value="0"/>'
			. '<input type="checkbox" name="plum_bottom" value="1" id="input-plum-bottom" class="form-check-input"' . $bottom_checked . '/>'
			. '<div class="form-text">' . $this->language->get('help_bottom') . '</div>'
			. '</div></div></div>';

		$needle = 'id="input-status"';
		$pos = strpos($output, $needle);

		if ($pos === false) {
			return;
		}

		$start = strrpos(substr($output, 0, $pos), '<div class="row mb-3">');

		if ($start === false) {
			return;
		}

		$output = substr($output, 0, $start) . $html . substr($output, $start);
	}

	public function eventInformationAdd(string &$route, array &$args, mixed &$output): void {
		$information_id = (int)$output;
		$this->saveInformationFlags($information_id);
	}

	public function eventInformationEdit(string &$route, array &$args, mixed &$output): void {
		$information_id = (!empty($args[0]) && !is_array($args[0])) ? (int)$args[0] : 0;
		$this->saveInformationFlags($information_id);
	}

	public function eventInformationDelete(string &$route, array &$args, mixed &$output): void {
		$information_id = (!empty($args[0]) && !is_array($args[0])) ? (int)$args[0] : 0;

		if ($information_id < 1) {
			return;
		}

		$this->load->model('extension/plum_store/theme/plum_store');
		$this->model_extension_plum_store_theme_plum_store->delete($information_id);
	}

	private function saveInformationFlags(int $information_id): void {
		if ($information_id < 1) {
			return;
		}

		$top = !empty($this->request->post['plum_top']) ? 1 : 0;
		$bottom = !empty($this->request->post['plum_bottom']) ? 1 : 0;

		$this->load->model('extension/plum_store/theme/plum_store');
		$this->model_extension_plum_store_theme_plum_store->ensureSchema();
		$this->model_extension_plum_store_theme_plum_store->setFlags($information_id, $top, $bottom);
	}

	public function index(): void {
		$this->load->language($this->route);

		$this->document->setTitle($this->language->get('heading_title'));

		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id)
		];

		$data['save'] = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme');

		$this->load->model('setting/setting');
		$setting_info = $this->model_setting_setting->getSetting('theme_plum_store', $store_id);

		$data['theme_plum_store_status'] = $setting_info['theme_plum_store_status'] ?? '';
		$data['theme_plum_store_color_scheme'] = $setting_info['theme_plum_store_color_scheme'] ?? 'theme-plum';
		$data['theme_plum_store_logo'] = $setting_info['theme_plum_store_logo'] ?? '';
		$data['theme_plum_store_header_layout'] = $setting_info['theme_plum_store_header_layout'] ?? '1';

		if (!in_array((string)$data['theme_plum_store_header_layout'], ['1', '2'], true)) {
			$data['theme_plum_store_header_layout'] = '1';
		}

		$data['header_layouts'] = [
			[
				'value' => '1',
				'label' => $this->language->get('text_header_1'),
				'preview' => HTTP_CATALOG . 'extension/plum_store/catalog/view/image/plum_store/header-1.svg',
			],
			[
				'value' => '2',
				'label' => $this->language->get('text_header_2'),
				'preview' => HTTP_CATALOG . 'extension/plum_store/catalog/view/image/plum_store/header-2.svg',
			],
		];

		$data['color_schemes'] = [
			['value' => 'theme-dark', 'label' => $this->language->get('text_scheme_dark'), 'bg' => '#1a1a1a', 'accent' => '#e4b91c'],
			['value' => 'theme-plum', 'label' => $this->language->get('text_scheme_plum'), 'bg' => '#3a2434', 'accent' => '#b85a82'],
			['value' => 'theme-light', 'label' => $this->language->get('text_scheme_light'), 'bg' => '#e8e3d9', 'accent' => '#b86b4f'],
			['value' => 'theme-ocean', 'label' => $this->language->get('text_scheme_ocean'), 'bg' => '#123645', 'accent' => '#2a9fc7'],
			['value' => 'theme-olive', 'label' => $this->language->get('text_scheme_olive'), 'bg' => '#e9e4d6', 'accent' => '#8a8658'],
			['value' => 'theme-emerald', 'label' => $this->language->get('text_scheme_emerald'), 'bg' => '#e6f2ec', 'accent' => '#2f8f6b'],
		];

		$this->load->model('tool/image');

		if ($data['theme_plum_store_logo'] && is_file(DIR_IMAGE . $data['theme_plum_store_logo'])) {
			if (strtolower(pathinfo($data['theme_plum_store_logo'], PATHINFO_EXTENSION)) === 'svg') {
				$data['logo_thumb'] = HTTP_CATALOG . 'image/' . $data['theme_plum_store_logo'];
			} else {
				$data['logo_thumb'] = $this->model_tool_image->resize($data['theme_plum_store_logo'], 100, 100);
			}
		} else {
			$data['logo_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		$defaults = [
			'theme_plum_store_product_limit'              => 15,
			'theme_plum_store_product_description_length' => 100,
			'theme_plum_store_image_category_width'       => 80,
			'theme_plum_store_image_category_height'      => 80,
			'theme_plum_store_image_thumb_width'          => 228,
			'theme_plum_store_image_thumb_height'         => 228,
			'theme_plum_store_image_popup_width'          => 500,
			'theme_plum_store_image_popup_height'         => 500,
			'theme_plum_store_image_product_width'        => 228,
			'theme_plum_store_image_product_height'       => 228,
			'theme_plum_store_image_additional_width'     => 74,
			'theme_plum_store_image_additional_height'    => 74,
			'theme_plum_store_image_related_width'        => 80,
			'theme_plum_store_image_related_height'       => 80,
			'theme_plum_store_image_compare_width'        => 90,
			'theme_plum_store_image_compare_height'       => 90,
			'theme_plum_store_image_wishlist_width'       => 47,
			'theme_plum_store_image_wishlist_height'      => 47,
			'theme_plum_store_image_cart_width'           => 47,
			'theme_plum_store_image_cart_height'          => 47,
			'theme_plum_store_image_location_width'       => 268,
			'theme_plum_store_image_location_height'      => 50,
		];

		foreach ($defaults as $key => $value) {
			$data[$key] = $setting_info[$key] ?? $value;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/plum_store/theme/plum_store', $data));
	}

	public function save(): void {
		$this->load->language($this->route);

		$json = [];
		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		$required_pairs = [
			'product_limit'       => ['theme_plum_store_product_limit'],
			'product_description' => ['theme_plum_store_product_description_length'],
			'image_category'      => ['theme_plum_store_image_category_width', 'theme_plum_store_image_category_height'],
			'image_thumb'         => ['theme_plum_store_image_thumb_width', 'theme_plum_store_image_thumb_height'],
			'image_popup'         => ['theme_plum_store_image_popup_width', 'theme_plum_store_image_popup_height'],
			'image_product'       => ['theme_plum_store_image_product_width', 'theme_plum_store_image_product_height'],
			'image_additional'    => ['theme_plum_store_image_additional_width', 'theme_plum_store_image_additional_height'],
			'image_related'       => ['theme_plum_store_image_related_width', 'theme_plum_store_image_related_height'],
			'image_compare'       => ['theme_plum_store_image_compare_width', 'theme_plum_store_image_compare_height'],
			'image_wishlist'      => ['theme_plum_store_image_wishlist_width', 'theme_plum_store_image_wishlist_height'],
			'image_cart'          => ['theme_plum_store_image_cart_width', 'theme_plum_store_image_cart_height'],
			'image_location'      => ['theme_plum_store_image_location_width', 'theme_plum_store_image_location_height'],
		];

		if (!$json) {
			if (empty($post['theme_plum_store_product_limit'])) {
				$json['error'] = $this->language->get('error_limit');
			} elseif (empty($post['theme_plum_store_product_description_length'])) {
				$json['error'] = $this->language->get('error_limit');
			} else {
				foreach ($required_pairs as $error_key => $fields) {
					if ($error_key === 'product_limit' || $error_key === 'product_description') {
						continue;
					}

					foreach ($fields as $field) {
						if (empty($post[$field])) {
							$json['error'] = $this->language->get('error_' . $error_key);
							break 2;
						}
					}
				}
			}
		}

		$allowed = ['theme-dark', 'theme-plum', 'theme-light', 'theme-ocean', 'theme-olive', 'theme-emerald'];

		if (!empty($post['theme_plum_store_color_scheme']) && !in_array($post['theme_plum_store_color_scheme'], $allowed, true)) {
			$post['theme_plum_store_color_scheme'] = 'theme-plum';
		}

		$layout = (string)($post['theme_plum_store_header_layout'] ?? '1');
		$post['theme_plum_store_header_layout'] = in_array($layout, ['1', '2'], true) ? $layout : '1';

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('theme_plum_store', $post, $store_id);
			$this->registerPlumEvents();
			$this->load->model('extension/plum_store/theme/plum_store');
			$this->model_extension_plum_store_theme_plum_store->ensureSchema();
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
