<?php
namespace Opencart\Catalog\Model\Extension\PlumStore\Theme;

class PlumStore extends \Opencart\System\Engine\Model {
	public function ensureSchema(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "plum_information` (
			`information_id` int(11) NOT NULL,
			`top` tinyint(1) NOT NULL DEFAULT 0,
			`bottom` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`information_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	/**
	 * @return list<array{title:string,href:string}>
	 */
	public function getTopInformations(): array {
		$this->ensureSchema();

		$lang = (string)$this->config->get('config_language');

		$sql = "SELECT `i`.`information_id`, `id`.`title`
			FROM `" . DB_PREFIX . "plum_information` `pi`
			INNER JOIN `" . DB_PREFIX . "information` `i` ON (`i`.`information_id` = `pi`.`information_id`)
			INNER JOIN `" . DB_PREFIX . "information_description` `id` ON (`i`.`information_id` = `id`.`information_id`)
			INNER JOIN `" . DB_PREFIX . "information_to_store` `i2s` ON (`i`.`information_id` = `i2s`.`information_id`)
			WHERE `pi`.`top` = '1'
				AND `i`.`status` = '1'
				AND `id`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'
				AND `i2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "'
			ORDER BY `i`.`sort_order` ASC, LCASE(`id`.`title`) ASC";

		$query = $this->db->query($sql);
		$result = [];

		foreach ($query->rows as $row) {
			$result[] = [
				'title' => $row['title'],
				'href'  => $this->url->link('information/information', 'language=' . $lang . '&information_id=' . (int)$row['information_id'])
			];
		}

		return $result;
	}

	/**
	 * @return array<int, bool> information_id => true
	 */
	public function getBottomIds(): array {
		$this->ensureSchema();

		$query = $this->db->query("SELECT `information_id` FROM `" . DB_PREFIX . "plum_information` WHERE `bottom` = '1'");
		$ids = [];

		foreach ($query->rows as $row) {
			$ids[(int)$row['information_id']] = true;
		}

		return $ids;
	}
}
