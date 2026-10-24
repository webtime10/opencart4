<?php
namespace Opencart\Catalog\Controller\Extension\WtLang\Module;

class WtLang extends \Opencart\System\Engine\Controller {
	/**
	 * OCMOD: startup/seo_url — detect prefix before SEO decode + register rewrite.
	 */
	public function eventBootstrap(): void {
		$this->initialise();

		$wt_lang = $this->registry->get('wt_lang');

		if ($wt_lang && $wt_lang->isActive()) {
			$wt_lang->detectRoute();
			$this->url->addRewrite($this);
		}
	}

	public function initialise(): void {
		if ($this->registry->get('wt_lang')) {
			return;
		}

		require_once DIR_EXTENSION . 'wt_lang/system/library/wt_lang/wt_lang.php';

		$this->registry->set('wt_lang', new \WtLang($this->registry));
	}

	public function rewrite(string $link): string {
		$wt_lang = $this->registry->get('wt_lang');

		if (!$wt_lang) {
			return $link;
		}

		return $wt_lang->rewrite($link);
	}

	/**
	 * Event: catalog/controller/common/header.index/before
	 */
	public function eventHreflang(string &$route = '', array &$args = []): void {
		if (!(bool)$this->config->get('module_wt_lang_status')) {
			return;
		}

		$this->initialise();

		$wt_lang = $this->registry->get('wt_lang');

		if ($wt_lang) {
			$wt_lang->addHreflangLinks();
		}
	}

	/**
	 * Event: catalog/controller/common/language.index/after — direct link switcher.
	 */
	public function eventLanguageSwitcher(string &$route = '', array &$args = [], mixed &$output = null): void {
		if (!(bool)$this->config->get('module_wt_lang_status')) {
			return;
		}

		$this->initialise();

		$wt_lang = $this->registry->get('wt_lang');

		if (!$wt_lang || !$wt_lang->isActive()) {
			return;
		}

		$languages = $wt_lang->getSwitcherLinks();

		if (count($languages) < 2) {
			return;
		}

		$data = [
			'languages'     => $languages,
			'text_language' => $wt_lang->getLanguageSwitcherLabel(),
		];

		if ((bool)$this->config->get('theme_plum_store_status') && $this->config->get('config_theme') === 'plum_store') {
			$data['plum_icons'] = 'extension/plum_store/catalog/view/image/plum_store/icons.svg';
			$output = $this->load->view('extension/plum_store/module/wt_lang_switcher', $data);
		} else {
			$output = $this->load->view('extension/wt_lang/module/wt_lang_switcher', $data);
		}
	}
}