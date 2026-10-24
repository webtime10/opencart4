<?php
namespace Opencart\Catalog\Model\Extension\WtSvg\Tool;

class Image extends \Opencart\System\Engine\Model {
	public function resize(string $filename, int $width, int $height, string $default = ''): string {
		$filename = html_entity_decode($filename, ENT_QUOTES, 'UTF-8');
		$filename = ltrim(str_replace(['\\', '..'], ['/', ''], $filename), '/');

		if (!is_file(DIR_IMAGE . $filename) || substr(str_replace('\\', '/', (string)realpath(DIR_IMAGE . $filename)), 0, strlen(DIR_IMAGE)) != DIR_IMAGE) {
			return '';
		}

		if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'svg') {
			return '';
		}

		return $this->config->get('config_url') . 'image/' . str_replace(' ', '%20', $filename);
	}
}
