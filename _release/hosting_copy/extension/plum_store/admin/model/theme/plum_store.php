<?php
namespace Opencart\Admin\Model\Extension\PlumStore\Theme;

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
	 * @return array{top:int,bottom:int}
	 */
	public function getFlags(int $information_id): array {
		if ($information_id <= 0) {
			return ['top' => 0, 'bottom' => 0];
		}

		$query = $this->db->query("SELECT `top`, `bottom` FROM `" . DB_PREFIX . "plum_information` WHERE `information_id` = '" . $information_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return ['top' => 0, 'bottom' => 0];
		}

		return [
			'top'    => (int)$query->row['top'] ? 1 : 0,
			'bottom' => (int)$query->row['bottom'] ? 1 : 0
		];
	}

	public function setFlags(int $information_id, int $top, int $bottom): void {
		if ($information_id <= 0) {
			return;
		}

		$this->db->query("REPLACE INTO `" . DB_PREFIX . "plum_information` SET `information_id` = '" . $information_id . "', `top` = '" . ($top ? 1 : 0) . "', `bottom` = '" . ($bottom ? 1 : 0) . "'");
	}

	public function delete(int $information_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "plum_information` WHERE `information_id` = '" . (int)$information_id . "'");
	}
}
