<?php
namespace Opencart\Catalog\Controller\Extension\WtSvg\Module;

class WtSvg extends \Opencart\System\Engine\Controller {
	/**
	 * catalog/model/tool/image.resize/before
	 */
	public function eventResizeCatalog(string &$route, array &$args): void {
		if (!(bool)$this->config->get('module_wt_svg_status')) {
			return;
		}

		$filename = isset($args[0]) ? (string)$args[0] : '';

		if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'svg') {
			return;
		}

		$route = 'extension/wt_svg/tool/image.resize';
	}
}
