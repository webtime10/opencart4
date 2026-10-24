<?php
namespace Opencart\Admin\Model\Extension\WtFilter\Catalog;

/**
 * ModelCatalogWtFilter
 *
 * Модель модуля WT Filter для OpenCart 3.
 * Задача: скопировать опции / стандартные фильтры / атрибуты OpenCart
 * в плоские таблицы wt_filter_*, из которых потом строится фильтр на витрине.
 *
 * Диапазоны ID (чтобы источники не пересекались):
 *   option_id / value_id          — как в OC (опции товара)
 *   filter_group/filter + 10000   — стандартные фильтры OC
 *   attribute + 30000             — атрибуты
 */
class WtFilter extends \Opencart\System\Engine\Model {
	/** Сколько keyword обновлять одним SQL (CASE WHEN), чтобы не делать N+1 UPDATE */
	const KEYWORD_BATCH_SIZE = 200;

	/**
	 * Создание таблиц словаря фильтра при установке модуля.
	 *
	 * wt_filter_option              — «характеристика» фильтра (Цвет, Память…)
	 * wt_filter_option_description  — названия характеристик по языкам
	 * wt_filter_option_value        — значение характеристики (Красный, 16 ГБ…)
	 * wt_filter_option_value_description — названия значений по языкам
	 * wt_filter_option_value_to_product  — связь товар ↔ значение (+ slide min/max)
	 * wt_filter_option_to_category  — в каких категориях показывать характеристику
	 * wt_filter_option_to_store     — в каких магазинах показывать
	 */
	public function install() {
		// Каркас характеристики фильтра (type, keyword для ЧПУ, флаги UI)
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option` (
				`option_id` int(11) NOT NULL AUTO_INCREMENT,
				`type` varchar(32) NOT NULL DEFAULT 'checkbox',
				`keyword` varchar(255) NOT NULL DEFAULT '',
				`status` tinyint(1) NOT NULL DEFAULT '0',
				`sort_order` int(11) NOT NULL DEFAULT '0',
				`grouping` int(11) NOT NULL DEFAULT '0',
				`selectbox` tinyint(1) NOT NULL DEFAULT '0',
				`color` tinyint(1) NOT NULL DEFAULT '0',
				`image` tinyint(1) NOT NULL DEFAULT '0',
				`expanded_desktop` tinyint(1) NOT NULL DEFAULT '1',
				`expanded_mobile` tinyint(1) NOT NULL DEFAULT '0',
				PRIMARY KEY (`option_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		// Локализованное имя/описание характеристики
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option_description` (
				`option_id` int(11) NOT NULL,
				`language_id` int(11) NOT NULL,
				`name` varchar(255) NOT NULL DEFAULT '',
				`description` text NOT NULL DEFAULT (''),
				`postfix` varchar(32) NOT NULL DEFAULT '',
				PRIMARY KEY (`option_id`,`language_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		// В каких категориях доступна характеристика
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option_to_category` (
				`option_id` int(11) NOT NULL,
				`category_id` int(11) NOT NULL,
				PRIMARY KEY (`option_id`,`category_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		// В каких магазинах (мультистор) доступна характеристика
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option_to_store` (
				`option_id` int(11) NOT NULL,
				`store_id` int(11) NOT NULL,
				PRIMARY KEY (`option_id`,`store_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		// Значения характеристики (value_id + keyword для ЧПУ + image override + numeric для слайдера)
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option_value` (
				`value_id` bigint(20) NOT NULL AUTO_INCREMENT,
				`option_id` int(11) NOT NULL,
				`keyword` varchar(255) NOT NULL DEFAULT '',
				`color` varchar(7) NOT NULL DEFAULT '',
				`image` varchar(255) NOT NULL DEFAULT '',
				`value_numeric` decimal(15,4) DEFAULT NULL,
				`sort_order` int(11) NOT NULL DEFAULT '0',
				PRIMARY KEY (`value_id`),
				KEY `option_id` (`option_id`),
				KEY `value_numeric` (`option_id`,`value_numeric`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		// Локализованное имя значения
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option_value_description` (
				`value_id` bigint(20) NOT NULL,
				`option_id` int(11) NOT NULL,
				`language_id` int(11) NOT NULL,
				`name` varchar(255) NOT NULL DEFAULT '',
				PRIMARY KEY (`value_id`,`language_id`),
				KEY `option_id` (`option_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		// Связь товара с выбранным значением фильтра
		// category_id — денормализация для быстрых счётчиков (KEY cat_opt_val)
		// slide_value_min/max — число для слайдера (из текста атрибута, напр. "15.6")
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_option_value_to_product` (
				`product_id` int(11) NOT NULL,
				`option_id` int(11) NOT NULL,
				`value_id` bigint(20) NOT NULL,
				`category_id` int(11) NOT NULL DEFAULT '0',
				`slide_value_min` decimal(15,4) NOT NULL DEFAULT '0.0000',
				`slide_value_max` decimal(15,4) NOT NULL DEFAULT '0.0000',
				PRIMARY KEY (`product_id`,`option_id`,`value_id`,`category_id`),
				KEY `option_id` (`option_id`),
				KEY `value_id` (`value_id`),
				KEY `cat_opt_val` (`category_id`,`option_id`,`value_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		$this->ensureSchema();
	}

	/**
	 * Таблица SEO-посадочных страниц фильтра.
	 */
	public function ensureSeoPageTable() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_seo_page` (
				`seo_page_id` INT(11) NOT NULL AUTO_INCREMENT,
				`category_id` INT(11) NOT NULL DEFAULT '0',
				`manufacturer_id` INT(11) NOT NULL DEFAULT '0',
				`url` VARCHAR(255) NOT NULL,
				`filter_params` VARCHAR(255) NOT NULL,
				`h1` VARCHAR(255) NOT NULL,
				`meta_title` VARCHAR(255) NOT NULL,
				`meta_description` TEXT NOT NULL,
				`meta_keyword` VARCHAR(255) NOT NULL,
				`description` MEDIUMTEXT NOT NULL,
				`status` TINYINT(1) NOT NULL DEFAULT '1',
				PRIMARY KEY (`seo_page_id`),
				KEY `category_filter` (`category_id`, `filter_params`),
				KEY `manufacturer_filter` (`manufacturer_id`, `filter_params`),
				KEY `url` (`url`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		$col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "wt_filter_seo_page` LIKE 'manufacturer_id'");

		if (!$col->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "wt_filter_seo_page`
				ADD COLUMN `manufacturer_id` INT(11) NOT NULL DEFAULT '0' AFTER `category_id`,
				ADD KEY `manufacturer_filter` (`manufacturer_id`, `filter_params`)");
		}
	}

	/**
	 * Миграция существующих установок: category_id, value_numeric, типы слайдера.
	 */
	public function ensureSchema() {
		$this->load->helper('extension/wt_filter/wt_filter');
		$this->ensureSeoPageTable();

		$table = DB_PREFIX . 'wt_filter_option_value_to_product';
		$value_table = DB_PREFIX . 'wt_filter_option_value';

		$exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");

		if (!$exists->num_rows) {
			return;
		}

		$col = $this->db->query("SHOW COLUMNS FROM `" . $table . "` LIKE 'category_id'");

		if (!$col->num_rows) {
			$this->db->query("ALTER TABLE `" . $table . "`
				ADD COLUMN `category_id` int(11) NOT NULL DEFAULT '0' AFTER `value_id`");
		}

		// PK должен включать category_id (товар в нескольких категориях → несколько строк)
		$pk = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'PRIMARY'");
		$pk_cols = [];

		foreach ($pk->rows as $row) {
			$pk_cols[(int)$row['Seq_in_index']] = $row['Column_name'];
		}

		ksort($pk_cols);
		$pk_cols = array_values($pk_cols);

		if ($pk_cols !== ['product_id', 'option_id', 'value_id', 'category_id']) {
			$this->db->query("ALTER TABLE `" . $table . "` DROP PRIMARY KEY");
			$this->db->query("ALTER TABLE `" . $table . "`
				ADD PRIMARY KEY (`product_id`,`option_id`,`value_id`,`category_id`)");
		}

		$idx = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'cat_opt_val'");

		if (!$idx->num_rows) {
			$this->db->query("ALTER TABLE `" . $table . "`
				ADD KEY `cat_opt_val` (`category_id`,`option_id`,`value_id`)");
		}

		$vn = $this->db->query("SHOW COLUMNS FROM `" . $value_table . "` LIKE 'value_numeric'");

		if (!$vn->num_rows) {
			$this->db->query("ALTER TABLE `" . $value_table . "`
				ADD COLUMN `value_numeric` decimal(15,4) DEFAULT NULL AFTER `image`,
				ADD KEY `value_numeric` (`option_id`,`value_numeric`)");
		}

		// Legacy типы → новые имена
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'slider_single' WHERE `type` = 'slide'");
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'slider_range' WHERE `type` = 'slide_dual'");
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'checkbox_image' WHERE `type` = 'image_checkbox'");
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'radio_image' WHERE `type` = 'image_radio'");
		// Слайдер + картинки значений (часто «Цвет») — иначе группа скрывается (min=max=0)
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'checkbox_image'
			WHERE `image` = '1' AND `type` IN ('slider_range', 'slider_single', 'slide', 'slide_dual')");

		$opt_table = DB_PREFIX . 'wt_filter_option';
		$ed = $this->db->query("SHOW COLUMNS FROM `" . $opt_table . "` LIKE 'expanded_desktop'");

		if (!$ed->num_rows) {
			$this->db->query("ALTER TABLE `" . $opt_table . "`
				ADD COLUMN `expanded_desktop` tinyint(1) NOT NULL DEFAULT '1' AFTER `image`,
				ADD COLUMN `expanded_mobile` tinyint(1) NOT NULL DEFAULT '0' AFTER `expanded_desktop`");
		}

		$this->expandCategoryLinks();
	}

	/**
	 * Разворачивает строки category_id=0 в связки по всем категориям товара
	 * и восстанавливает option→category / option→store (витрина без них ничего не показывает).
	 */
	private function expandCategoryLinks() {
		$table = DB_PREFIX . 'wt_filter_option_value_to_product';

		$need = $this->db->query("SELECT COUNT(*) AS total FROM `" . $table . "` WHERE category_id = '0'");

		if (!empty($need->row['total'])) {
			$this->db->query("INSERT IGNORE INTO `" . $table . "`
				(`product_id`, `option_id`, `value_id`, `category_id`, `slide_value_min`, `slide_value_max`)
				SELECT w.product_id, w.option_id, w.value_id, p2c.category_id, w.slide_value_min, w.slide_value_max
				FROM `" . $table . "` w
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = w.product_id)
				WHERE w.category_id = '0' AND p2c.category_id > '0'");

			$this->db->query("DELETE FROM `" . $table . "` WHERE category_id = '0'");
		}

		$this->repairOptionCategoryAndStoreLinks();
	}

	/**
	 * Пересобирает wt_filter_option_to_category и добивает привязку к магазину 0,
	 * если у опции вообще нет записи в option_to_store (после копирования без finalize).
	 */
	public function repairOptionCategoryAndStoreLinks() {
		$table = DB_PREFIX . 'wt_filter_option_value_to_product';
		$exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");

		if (!$exists->num_rows) {
			return;
		}

		// Словарь OC-опций есть, а индекс товаров пуст (копирование с quantity>0)
		$oc_opts = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			INNER JOIN `" . DB_PREFIX . "option` o ON (o.option_id = oo.option_id)");
		$oc_links = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . $table . "` oov2p
			INNER JOIN `" . DB_PREFIX . "option` o ON (o.option_id = oov2p.option_id)");

		if (!empty($oc_opts->row['total']) && empty($oc_links->row['total'])) {
			$this->db->query("INSERT INTO `" . $table . "` (product_id, value_id, option_id, category_id, slide_value_min, slide_value_max)
				SELECT pov.product_id, pov.option_value_id, pov.option_id, p2c.category_id, 0, 0
				FROM `" . DB_PREFIX . "product_option_value` pov
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pov.product_id)
				INNER JOIN `" . DB_PREFIX . "wt_filter_option` oo ON (oo.option_id = pov.option_id)
				WHERE p2c.category_id > '0'
				ON DUPLICATE KEY UPDATE
					`slide_value_min` = VALUES(`slide_value_min`),
					`slide_value_max` = VALUES(`slide_value_max`)");
			$this->cache->delete('wt_filter');
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_category` (option_id, category_id)
			SELECT oov2p.option_id, oov2p.category_id
			FROM `" . $table . "` oov2p
			WHERE oov2p.category_id > '0'
			GROUP BY oov2p.option_id, oov2p.category_id
			ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`)");

		// Фоллбек: связи только через product_to_category (если category_id в индексе ещё 0)
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_category` (option_id, category_id)
			SELECT oov2p.option_id, p2c.category_id
			FROM `" . $table . "` oov2p
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = oov2p.product_id)
			WHERE p2c.category_id > '0'
			GROUP BY oov2p.option_id, p2c.category_id
			ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_store` (option_id, store_id)
			SELECT oo.option_id, '0'
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_to_store` o2s ON (o2s.option_id = oo.option_id)
			WHERE o2s.option_id IS NULL");
	}

	/** Удаление всех таблиц модуля при uninstall */
	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option_description`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option_to_category`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option_to_store`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option_value`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option_value_description`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_option_value_to_product`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wt_filter_seo_page`");
	}

	public function getSeoPages($data = []) {
		$this->ensureSeoPageTable();

		$sql = "SELECT * FROM `" . DB_PREFIX . "wt_filter_seo_page` ORDER BY seo_page_id DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 12;

			if ($start < 0) {
				$start = 0;
			}

			if ($limit < 1) {
				$limit = 12;
			}

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalSeoPages() {
		$this->ensureSeoPageTable();

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "wt_filter_seo_page`");

		return (int)$query->row['total'];
	}

	public function getSeoPage($seo_page_id) {
		$this->ensureSeoPageTable();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_seo_page`
			WHERE seo_page_id = '" . (int)$seo_page_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : [];
	}

	public function addSeoPage($data) {
		$this->ensureSeoPageTable();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_seo_page` SET
			category_id = '" . (int)$data['category_id'] . "',
			manufacturer_id = '" . (int)(isset($data['manufacturer_id']) ? $data['manufacturer_id'] : 0) . "',
			url = '" . $this->db->escape($data['url']) . "',
			filter_params = '" . $this->db->escape($data['filter_params']) . "',
			h1 = '" . $this->db->escape($data['h1']) . "',
			meta_title = '" . $this->db->escape($data['meta_title']) . "',
			meta_description = '" . $this->db->escape($data['meta_description']) . "',
			meta_keyword = '" . $this->db->escape($data['meta_keyword']) . "',
			description = '" . $this->db->escape($data['description']) . "',
			status = '" . (int)$data['status'] . "'");

		return (int)$this->db->getLastId();
	}

	public function editSeoPage($seo_page_id, $data) {
		$this->ensureSeoPageTable();

		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_seo_page` SET
			category_id = '" . (int)$data['category_id'] . "',
			manufacturer_id = '" . (int)(isset($data['manufacturer_id']) ? $data['manufacturer_id'] : 0) . "',
			url = '" . $this->db->escape($data['url']) . "',
			filter_params = '" . $this->db->escape($data['filter_params']) . "',
			h1 = '" . $this->db->escape($data['h1']) . "',
			meta_title = '" . $this->db->escape($data['meta_title']) . "',
			meta_description = '" . $this->db->escape($data['meta_description']) . "',
			meta_keyword = '" . $this->db->escape($data['meta_keyword']) . "',
			description = '" . $this->db->escape($data['description']) . "',
			status = '" . (int)$data['status'] . "'
			WHERE seo_page_id = '" . (int)$seo_page_id . "'");
	}

	public function deleteSeoPage($seo_page_id) {
		$this->ensureSeoPageTable();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_seo_page`
			WHERE seo_page_id = '" . (int)$seo_page_id . "'");
	}

	/**
	 * Конфликт посадочной: та же связка категория/бренд+фильтр или тот же URL.
	 *
	 * @return array{type:string,seo_page_id:int,h1:string,url:string}|null
	 */
	public function findSeoPageConflict(array $data, $exclude_id = 0) {
		$this->ensureSeoPageTable();

		$exclude_id = (int)$exclude_id;
		$category_id = (int)$data['category_id'];
		$manufacturer_id = (int)(isset($data['manufacturer_id']) ? $data['manufacturer_id'] : 0);
		$filter_params = (string)$data['filter_params'];
		$url = (string)$data['url'];

		$query = $this->db->query("SELECT seo_page_id, h1, url FROM `" . DB_PREFIX . "wt_filter_seo_page`
			WHERE category_id = '" . $category_id . "'
				AND manufacturer_id = '" . $manufacturer_id . "'
				AND filter_params = '" . $this->db->escape($filter_params) . "'
				AND seo_page_id <> '" . $exclude_id . "'
			LIMIT 1");

		if ($query->num_rows) {
			return [
				'type'        => 'filter',
				'seo_page_id' => (int)$query->row['seo_page_id'],
				'h1'          => (string)$query->row['h1'],
				'url'         => (string)$query->row['url'],
			];
		}

		$query = $this->db->query("SELECT seo_page_id, h1, url FROM `" . DB_PREFIX . "wt_filter_seo_page`
			WHERE url = '" . $this->db->escape($url) . "'
				AND seo_page_id <> '" . $exclude_id . "'
			LIMIT 1");

		if ($query->num_rows) {
			return [
				'type'        => 'url',
				'seo_page_id' => (int)$query->row['seo_page_id'],
				'h1'          => (string)$query->row['h1'],
				'url'         => (string)$query->row['url'],
			];
		}

		return null;
	}

	/**
	 * Человекочитаемое описание filter_params для превью в админке.
	 *
	 * @return array<int, array{option:string,values:string}>
	 */
	public function describeSeoFilterParams($filter_params) {
		$this->load->config('extension/wt_filter/wt_filter');
		$this->load->helper('extension/wt_filter/wt_filter');
		$this->load->language('extension/wt_filter/module/wt_filter');

		$params = $this->decodeSeoFilterSegment((string)$filter_params);
		$language_id = (int)$this->config->get('config_language_id');
		$facets = [];

		foreach ($params as $option_id => $values) {
			if (!is_array($values) || !$values) {
				continue;
			}

			if ($option_id === 'p') {
				$facets[] = [
					'option' => $this->language->get('text_seo_facet_price'),
					'values' => implode(' – ', $values),
				];
				continue;
			}

			if ($option_id === 'd') {
				$facets[] = [
					'option' => $this->language->get('text_seo_facet_discount'),
					'values' => $this->language->get('text_seo_facet_discount_yes'),
				];
				continue;
			}

			if ($option_id === 's') {
				$label = ($values[0] === 'out')
					? $this->language->get('text_seo_facet_stock_out')
					: $this->language->get('text_seo_facet_stock_in');
				$facets[] = [
					'option' => $this->language->get('text_seo_facet_stock'),
					'values' => $label,
				];
				continue;
			}

			if ($option_id === 'm') {
				$names = [];

				foreach ($values as $manufacturer_id) {
					$mq = $this->db->query("SELECT name FROM `" . DB_PREFIX . "manufacturer`
						WHERE manufacturer_id = '" . (int)$manufacturer_id . "' LIMIT 1");
					$names[] = $mq->num_rows ? (string)$mq->row['name'] : (string)$manufacturer_id;
				}

				$facets[] = [
					'option' => $this->language->get('text_seo_facet_manufacturer'),
					'values' => implode(', ', $names),
				];
				continue;
			}

			$option_name = (string)$option_id;

			if (wt_filter_is_id($option_id)) {
				$oq = $this->db->query("SELECT name FROM `" . DB_PREFIX . "wt_filter_option_description`
					WHERE option_id = '" . (int)$option_id . "'
						AND language_id = '" . $language_id . "'
					LIMIT 1");

				if ($oq->num_rows) {
					$option_name = (string)$oq->row['name'];
				}
			} else {
				$oq = $this->db->query("SELECT ood.name FROM `" . DB_PREFIX . "wt_filter_option` oo
					INNER JOIN `" . DB_PREFIX . "wt_filter_option_description` ood ON (oo.option_id = ood.option_id)
					WHERE oo.keyword = '" . $this->db->escape((string)$option_id) . "'
						AND ood.language_id = '" . $language_id . "'
					LIMIT 1");

				if ($oq->num_rows) {
					$option_name = (string)$oq->row['name'];
				}
			}

			$value_names = [];

			foreach ($values as $value_id) {
				if (wt_filter_is_range((string)$value_id)) {
					$value_names[] = (string)$value_id;
					continue;
				}

				$vq = $this->db->query("SELECT name FROM `" . DB_PREFIX . "wt_filter_option_value_description`
					WHERE value_id = '" . $this->db->escape((string)$value_id) . "'
						AND language_id = '" . $language_id . "'
					LIMIT 1");

				if ($vq->num_rows) {
					$value_names[] = (string)$vq->row['name'];
					continue;
				}

				$vq = $this->db->query("SELECT oovd.name FROM `" . DB_PREFIX . "wt_filter_option_value` oov
					INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_description` oovd ON (oov.value_id = oovd.value_id)
					WHERE oov.keyword = '" . $this->db->escape((string)$value_id) . "'
						AND oovd.language_id = '" . $language_id . "'
					LIMIT 1");

				$value_names[] = $vq->num_rows ? (string)$vq->row['name'] : (string)$value_id;
			}

			$facets[] = [
				'option' => $option_name,
				'values' => implode(', ', $value_names),
			];
		}

		return $facets;
	}

	/**
	 * Превью разобранного SEO-URL: scope, фильтр, ссылка, возможный конфликт.
	 *
	 * @return array{error:string}|array
	 */
	public function getSeoPagePreview($url, $exclude_id = 0) {
		$parsed = $this->parseSeoPageUrl($url);

		if (!empty($parsed['error'])) {
			return $parsed;
		}

		$language_id = (int)$this->config->get('config_language_id');
		$scope_type = 'category';
		$scope_name = '';

		if (!empty($parsed['manufacturer_id'])) {
			$scope_type = 'manufacturer';
			$mq = $this->db->query("SELECT name FROM `" . DB_PREFIX . "manufacturer`
				WHERE manufacturer_id = '" . (int)$parsed['manufacturer_id'] . "' LIMIT 1");
			$scope_name = $mq->num_rows ? (string)$mq->row['name'] : ('#' . (int)$parsed['manufacturer_id']);
		} else {
			$cq = $this->db->query("SELECT name FROM `" . DB_PREFIX . "category_description`
				WHERE category_id = '" . (int)$parsed['category_id'] . "'
					AND language_id = '" . $language_id . "'
				LIMIT 1");
			$scope_name = $cq->num_rows ? (string)$cq->row['name'] : ('#' . (int)$parsed['category_id']);
		}

		$catalog = defined('HTTP_CATALOG') ? HTTP_CATALOG : (defined('HTTPS_CATALOG') ? HTTPS_CATALOG : '');
		$href = rtrim((string)$catalog, '/') . $parsed['url'];

		$conflict = $this->findSeoPageConflict([
			'category_id'     => $parsed['category_id'],
			'manufacturer_id' => $parsed['manufacturer_id'],
			'filter_params'   => $parsed['filter_params'],
			'url'             => $parsed['url'],
		], $exclude_id);

		return [
			'category_id'     => (int)$parsed['category_id'],
			'manufacturer_id' => (int)$parsed['manufacturer_id'],
			'filter_params'   => $parsed['filter_params'],
			'url'             => $parsed['url'],
			'scope_type'      => $scope_type,
			'scope_name'      => $scope_name,
			'facets'          => $this->describeSeoFilterParams($parsed['filter_params']),
			'href'            => $href,
			'conflict'        => $conflict,
		];
	}

	/**
	 * Разбор полного/относительного URL фильтра → category_id|manufacturer_id, filter_params, url.
	 *
	 * @return array{category_id:int,manufacturer_id:int,filter_params:string,url:string}|array{error:string}
	 */
	public function parseSeoPageUrl($url) {
		$this->load->config('extension/wt_filter/wt_filter');
		$this->load->helper('extension/wt_filter/wt_filter');

		$url = trim((string)$url);

		if ($url === '') {
			return ['error' => 'empty'];
		}

		$url_key = $this->config->get('wt_filter_url_index') ?: 'filter_wt_filter';
		$path = parse_url($url, PHP_URL_PATH);
		$query_string = parse_url($url, PHP_URL_QUERY);

		// URL без схемы: "path?query" или только "?query"
		if (($path === null || $path === false || $path === '') && strpos($url, '?') !== false) {
			list($path_part, $query_part) = explode('?', $url, 2);
			$path = $path_part !== '' ? $path_part : '/';
			$query_string = $query_part;
		}

		if ($path === null || $path === false || $path === '') {
			$path = (strpos($url, ':') !== false) ? $url : '';
		}

		$qs = [];

		if ($query_string) {
			parse_str((string)$query_string, $qs);
		}

		$path = '/' . ltrim(str_replace('\\', '/', (string)$path), '/');
		$parts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

		// index.php в пути не сегмент ЧПУ
		if ($parts && strtolower((string)$parts[0]) === 'index.php') {
			array_shift($parts);
		}

		$filter_segment = '';

		if (!empty($qs[$url_key])) {
			$filter_segment = (string)$qs[$url_key];
		}

		if ($parts) {
			$last = (string)end($parts);

			if (strpos($last, ':') !== false) {
				if ($filter_segment === '') {
					$filter_segment = $last;
				}
				array_pop($parts);
			}
		}

		if ($filter_segment === '') {
			return ['error' => 'filter'];
		}

		$params = $this->decodeSeoFilterSegment($filter_segment);

		if (!$params) {
			return ['error' => 'filter'];
		}

		unset($params['s']);
		$filter_params = $this->encodeSeoFilterParams($params);

		if ($filter_params === '') {
			return ['error' => 'filter'];
		}

		$category_id = 0;
		$manufacturer_id = 0;
		$path_ids = [];

		// Query: path=10_11 / manufacturer_id=2
		if (!empty($qs['path'])) {
			$path_parts = explode('_', (string)$qs['path']);
			$category_id = (int)end($path_parts);
		}

		if (!empty($qs['manufacturer_id'])) {
			$manufacturer_id = (int)$qs['manufacturer_id'];
		}

		foreach ($parts as $keyword) {
			$query = $this->db->query("SELECT `query` FROM `" . DB_PREFIX . "seo_url`
				WHERE keyword = '" . $this->db->escape($keyword) . "'
					AND (`query` LIKE 'category_id=%' OR `query` LIKE 'manufacturer_id=%')
				ORDER BY store_id ASC, language_id ASC
				LIMIT 1");

			if (!$query->num_rows) {
				continue;
			}

			$pair = explode('=', $query->row['query'], 2);

			if (count($pair) !== 2) {
				continue;
			}

			if ($pair[0] === 'category_id') {
				$path_ids[] = (int)$pair[1];
			} elseif ($pair[0] === 'manufacturer_id') {
				$manufacturer_id = (int)$pair[1];
			}
		}

		if ($path_ids) {
			$category_id = (int)end($path_ids);
		}

		// Категория имеет приоритет, если в пути есть обе сущности
		if ($category_id > 0) {
			$manufacturer_id = 0;
		}

		if ($category_id < 1 && $manufacturer_id < 1) {
			return ['error' => 'category'];
		}

		// ЧПУ-относительный URL, если есть keyword-сегменты; иначе query-форма
		if ($parts) {
			$relative = '/' . implode('/', array_merge($parts, [$filter_params])) . '/';
		} elseif ($manufacturer_id > 0) {
			$relative = '/index.php?route=product/manufacturer.info&manufacturer_id=' . $manufacturer_id
				. '&' . $url_key . '=' . rawurlencode($filter_params);
		} else {
			$path_value = !empty($qs['path']) ? (string)$qs['path'] : (string)$category_id;
			$relative = '/index.php?route=product/category&path=' . rawurlencode($path_value)
				. '&' . $url_key . '=' . rawurlencode($filter_params);
		}

		return [
			'category_id'      => $category_id,
			'manufacturer_id'  => $manufacturer_id,
			'filter_params'    => $filter_params,
			'url'              => $relative,
		];
	}

	private function decodeSeoFilterSegment($segment) {
		$this->load->config('extension/wt_filter/wt_filter');
		$this->load->helper('extension/wt_filter/wt_filter');

		$params = wt_filter_decode_params($segment, $this->config);

		if ($params) {
			return $params;
		}

		// keyword → id (как на витрине)
		$params = [];
		$part_sep = $this->config->get('wt_filter_part_separator') ?: ';';
		$opt_sep = $this->config->get('wt_filter_option_separator') ?: ':';
		$val_sep = $this->config->get('wt_filter_option_value_separator') ?: ',';

		foreach (explode($part_sep, (string)$segment) as $part) {
			if ($part === '' || strpos($part, $opt_sep) === false) {
				continue;
			}

			list($okey, $vraw) = explode($opt_sep, $part, 2);
			$okey = trim($okey);
			$vraw = trim($vraw);

			if ($okey === '' || $vraw === '') {
				continue;
			}

			if (in_array($okey, ['p', 'm', 's', 'd']) || wt_filter_is_id($okey)) {
				$params[$okey] = array_values(array_filter(array_map('trim', explode($val_sep, $vraw)), 'strlen'));
				continue;
			}

			$oq = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "wt_filter_option`
				WHERE keyword = '" . $this->db->escape($okey) . "' LIMIT 1");

			if (!$oq->num_rows) {
				continue;
			}

			$option_id = (int)$oq->row['option_id'];
			$values = [];

			foreach (explode($val_sep, $vraw) as $vkey) {
				$vkey = trim($vkey);

				if ($vkey === '') {
					continue;
				}

				$vq = $this->db->query("SELECT value_id FROM `" . DB_PREFIX . "wt_filter_option_value`
					WHERE option_id = '" . $option_id . "'
						AND keyword = '" . $this->db->escape($vkey) . "' LIMIT 1");

				if ($vq->num_rows) {
					$values[] = (string)$vq->row['value_id'];
				} else {
					$values[] = $vkey;
				}
			}

			if ($values) {
				$params[$option_id] = $values;
			}
		}

		return $params;
	}

	private function encodeSeoFilterParams(array $params) {
		$this->load->config('extension/wt_filter/wt_filter');
		$this->load->helper('extension/wt_filter/wt_filter');

		unset($params['s']);

		$part_sep = $this->config->get('wt_filter_part_separator') ?: ';';
		$opt_sep = $this->config->get('wt_filter_option_separator') ?: ':';
		$val_sep = $this->config->get('wt_filter_option_value_separator') ?: ',';
		$parts = [];

		ksort($params, SORT_STRING);

		foreach ($params as $option_id => $values) {
			if (!is_array($values) || !$values) {
				continue;
			}

			if ($option_id === 'p' || $option_id === 's' || $option_id === 'd') {
				$parts[] = $option_id . $opt_sep . implode($val_sep, $values);
				continue;
			}

			if ($option_id === 'm') {
				$parts[] = 'm' . $opt_sep . implode($val_sep, $values);
				continue;
			}

			if (wt_filter_is_range((string)$values[0])) {
				$parts[] = $option_id . $opt_sep . implode($val_sep, $values);
				continue;
			}

			$oq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "wt_filter_option`
				WHERE option_id = '" . (int)$option_id . "' LIMIT 1");
			$okey = ($oq->num_rows && $oq->row['keyword'] !== '') ? (string)$oq->row['keyword'] : (string)$option_id;

			$vkeys = [];

			foreach ($values as $value_id) {
				$vq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "wt_filter_option_value`
					WHERE value_id = '" . $this->db->escape((string)$value_id) . "' LIMIT 1");

				$vkeys[] = ($vq->num_rows && $vq->row['keyword'] !== '')
					? (string)$vq->row['keyword']
					: (string)$value_id;
			}

			sort($vkeys);
			$parts[] = $okey . $opt_sep . implode($val_sep, $vkeys);
		}

		sort($parts, SORT_STRING);

		return implode($part_sep, $parts);
	}

	/**
	 * Один шаг копирования (AJAX прогресс-бар в админке).
	 *
	 * truncate  — очистить все wt_filter_*
	 * option    — скопировать опции товара (oc_option*)
	 * filter    — скопировать стандартные фильтры OC (oc_filter*)
	 * attribute — скопировать атрибуты (oc_attribute* / product_attribute)
	 * finalize  — категории, магазины, keyword (ЧПУ)
	 *
	 * $data: copy_type, copy_store[], module_wt_filter_attribute_separator, …
	 */
	public function copyFiltersStep($step, $data = []) {
		$this->ensureSchema();

		// Тип UI фильтра по умолчанию: checkbox / radio / select
		$copy_type = !empty($data['copy_type']) ? $data['copy_type'] : 'checkbox';

		switch ($step) {
			case 'truncate':
				// Полная очистка словаря перед пересборкой
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option`");
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option_description`");
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option_to_category`");
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option_to_store`");
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option_value`");
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option_value_to_product`");
				$this->db->query("TRUNCATE `" . DB_PREFIX . "wt_filter_option_value_description`");
				break;

			case 'option':
				// --- Словарь из опций товара (oc_option → wt_filter_option) ---
				// ODKU: при повторном копировании без truncate обновляем поля, а не молча пропускаем
				// type/status/image-флаг не затираем при Update — только у новых строк
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option` (option_id, `type`, `status`, sort_order, image)
					SELECT option_id, '" . $this->db->escape($copy_type) . "', '1', sort_order, IF(`type` = 'image', 1, 0)
					FROM `" . DB_PREFIX . "option`
					ON DUPLICATE KEY UPDATE
						`sort_order` = VALUES(`sort_order`)");

				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` (option_id, language_id, name, description, postfix)
					SELECT option_id, language_id, name, '', '' FROM `" . DB_PREFIX . "option_description`
					ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

				// image из option_value; админский override не затираем (пустое → подтянуть)
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (value_id, option_id, image, sort_order)
					SELECT option_value_id, option_id, IFNULL(image, ''), sort_order FROM `" . DB_PREFIX . "option_value`
					ON DUPLICATE KEY UPDATE
						`option_id` = VALUES(`option_id`),
						`image` = IF(`" . DB_PREFIX . "wt_filter_option_value`.`image` = '', VALUES(`image`), `" . DB_PREFIX . "wt_filter_option_value`.`image`),
						`sort_order` = VALUES(`sort_order`)");

				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (value_id, option_id, language_id, name)
					SELECT option_value_id, option_id, language_id, name FROM `" . DB_PREFIX . "option_value_description`
					ON DUPLICATE KEY UPDATE
						`option_id` = VALUES(`option_id`),
						`name` = VALUES(`name`)");

				// Связи товар↔значение: сначала снести старые link'и OC-опций,
				// потом вставить только позиции с quantity > 0 (актуально при смене остатков)
				$this->db->query("DELETE oov2p FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					INNER JOIN `" . DB_PREFIX . "option` o ON (o.option_id = oov2p.option_id)");

				// quantity не фильтруем: у многих магазинов quantity опции = 0 (остаток на уровне товара)
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product` (product_id, value_id, option_id, category_id, slide_value_min, slide_value_max)
					SELECT pov.product_id, pov.option_value_id, pov.option_id, p2c.category_id, 0, 0
					FROM `" . DB_PREFIX . "product_option_value` pov
					INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pov.product_id)
					WHERE p2c.category_id > '0'
					ON DUPLICATE KEY UPDATE
						`slide_value_min` = VALUES(`slide_value_min`),
						`slide_value_max` = VALUES(`slide_value_max`)");
				break;

			case 'filter':
				// --- Стандартные фильтры OC (filter_group / filter) ---
				// Смещение +10000, чтобы не пересечься с option_id из опций
				$last_option_id = 10000;
				$last_value_id = 10000;

				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option` (option_id, `type`, `status`, sort_order)
					SELECT (filter_group_id + '" . (int)$last_option_id . "'), '" . $this->db->escape($copy_type) . "', '1', sort_order
					FROM `" . DB_PREFIX . "filter_group`
					ON DUPLICATE KEY UPDATE
						`sort_order` = VALUES(`sort_order`)");

				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` (option_id, language_id, name, description, postfix)
					SELECT (filter_group_id + '" . (int)$last_option_id . "'), language_id, name, '', ''
					FROM `" . DB_PREFIX . "filter_group_description`
					ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (value_id, option_id, sort_order)
					SELECT (filter_id + '" . (int)$last_value_id . "'), (filter_group_id + '" . (int)$last_option_id . "'), sort_order
					FROM `" . DB_PREFIX . "filter`
					ON DUPLICATE KEY UPDATE
						`option_id` = VALUES(`option_id`),
						`sort_order` = VALUES(`sort_order`)");

				// OC4: filter_description больше не содержит filter_group_id — берём из filter
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (value_id, option_id, language_id, name)
					SELECT (fd.filter_id + '" . (int)$last_value_id . "'), (f.filter_group_id + '" . (int)$last_option_id . "'), fd.language_id, fd.name
					FROM `" . DB_PREFIX . "filter_description` fd
					INNER JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = fd.filter_id)
					ON DUPLICATE KEY UPDATE
						`option_id` = VALUES(`option_id`),
						`name` = VALUES(`name`)");

				// Пересборка product_filter → wt_filter (только диапазон 10000…29999)
				$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
					WHERE option_id >= '" . (int)$last_option_id . "' AND option_id < '30000'");

				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product` (product_id, value_id, option_id, category_id, slide_value_min, slide_value_max)
					SELECT pf.product_id,
						(pf.filter_id + '" . (int)$last_value_id . "'),
						(f.filter_group_id + '" . (int)$last_option_id . "'),
						p2c.category_id,
						0, 0
					FROM `" . DB_PREFIX . "product_filter` pf
					INNER JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id)
					INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pf.product_id)
					WHERE p2c.category_id > '0'
					ON DUPLICATE KEY UPDATE
						`slide_value_min` = VALUES(`slide_value_min`),
						`slide_value_max` = VALUES(`slide_value_max`)");
				break;

			case 'attribute':
				// Атрибуты — отдельный метод (нормализация текста, SHA1 value_id, слайдер, separator)
				$this->copyAttributes($data, $copy_type);
				break;

			case 'finalize':
				// Добить денормализацию category_id (на случай старых строк)
				$this->expandCategoryLinks();

				// 1–2) Категории + магазины (expandCategoryLinks уже вызывает repair)
				// Доп. магазины из чекбоксов копирования
				if (!empty($data['copy_store']) && is_array($data['copy_store'])) {
					foreach ($data['copy_store'] as $store_id) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_store` (option_id, store_id)
							SELECT option_id, '" . (int)$store_id . "' AS store_id FROM `" . DB_PREFIX . "wt_filter_option`
							ON DUPLICATE KEY UPDATE `store_id` = VALUES(`store_id`)");
					}
				}

				// 3) ЧПУ-keyword из названий (батчами, без UPDATE в цикле на каждую строку)
				$this->generateKeywordsBatched();

				// 4) Числа для слайдера из названий значений ("16GB" → 16)
				$this->syncValueNumerics();

				// 5) Сброс кеша фильтра/товаров
				$this->cache->delete('wt_filter');
				$this->cache->delete('product');
				break;
		}
	}

	/**
	 * Полное копирование одним запросом (без прогресс-бара).
	 * Флаги: copy_truncate, copy_option, copy_filter, copy_attribute.
	 */
	public function copyFilters($data = []) {
		if (!empty($data['copy_truncate'])) {
			$this->copyFiltersStep('truncate', $data);
		}

		if (!empty($data['copy_option'])) {
			$this->copyFiltersStep('option', $data);
		}

		if (!empty($data['copy_filter'])) {
			$this->copyFiltersStep('filter', $data);
		}

		if (!empty($data['copy_attribute'])) {
			$this->copyFiltersStep('attribute', $data);
		}

		if (!empty($data['copy_option']) || !empty($data['copy_filter']) || !empty($data['copy_attribute'])) {
			$this->copyFiltersStep('finalize', $data);
		}
	}

	/**
	 * Собирает записи без keyword и обновляет их пакетами.
	 */
	private function generateKeywordsBatched() {
		$language_id = (int)$this->config->get('config_language_id');

		// Keywords для характеристик
		$query = $this->db->query("SELECT oo.option_id, ood.name
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_description` ood ON (oo.option_id = ood.option_id)
			WHERE ood.language_id = '" . $language_id . "' AND oo.`keyword` = ''");

		$this->batchUpdateKeywords(DB_PREFIX . 'wt_filter_option', 'option_id', $query->rows);

		// Keywords для значений
		$query = $this->db->query("SELECT oov.value_id, oovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` oov
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_value_description` oovd ON (oov.value_id = oovd.value_id)
			WHERE oovd.language_id = '" . $language_id . "' AND oov.`keyword` = ''");

		$this->batchUpdateKeywords(DB_PREFIX . 'wt_filter_option_value', 'value_id', $query->rows);
	}

	/**
	 * Один UPDATE … SET keyword = CASE id WHEN … THEN … END на пачку строк.
	 *
	 * @param string $table
	 * @param string $pk_column option_id|value_id
	 * @param array  $rows
	 */
	private function batchUpdateKeywords($table, $pk_column, array $rows) {
		if (!$rows) {
			return;
		}

		$chunks = array_chunk($rows, self::KEYWORD_BATCH_SIZE);

		foreach ($chunks as $chunk) {
			$cases = [];
			$ids = [];

			foreach ($chunk as $row) {
				$id = $row[$pk_column];
				$keyword = $this->translit($row['name']);

				if ($keyword === '') {
					continue;
				}

				if ($pk_column === 'option_id') {
					$id_sql = "'" . (int)$id . "'";
				} else {
					$id_sql = "'" . $this->db->escape((string)$id) . "'";
				}

				$cases[] = "WHEN " . $id_sql . " THEN '" . $this->db->escape($keyword) . "'";
				$ids[] = $id_sql;
			}

			if (!$cases) {
				continue;
			}

			$this->db->query("UPDATE `" . $table . "` SET `keyword` = CASE `" . $pk_column . "`
				" . implode(' ', $cases) . "
				ELSE `keyword` END
				WHERE `" . $pk_column . "` IN (" . implode(',', $ids) . ")");
		}
	}

	/**
	 * Копирование атрибутов OpenCart → wt_filter_*.
	 *
	 * option_id = attribute_id + 30000
	 * value_id  = SHA1(attribute_id + нормализованный text) → bigint
	 * slide_*   = число, вытащенное из text ("15.6", "100 ГБ")
	 *
	 * Если задан separator — режет составные значения на отдельные (по всем языкам).
	 */
	private function copyAttributes($data, $copy_type) {
		// Убрать лишние пробелы в исходных текстах атрибутов
		$this->db->query("UPDATE `" . DB_PREFIX . "product_attribute` SET text = TRIM(text)");

		$last_option_id = 30000;
		$language_id = (int)$this->config->get('config_language_id');

		// Нормализация текста значения: «красный» → «Красный» (для группировки дублей)
		$norm = "CONCAT(UCASE(LEFT(TRIM(text), 1)), LCASE(SUBSTRING(TRIM(text), 2)))";

		/*
		 * value_id: вместо CRC32 (высокий риск коллизий на больших объёмах)
		 * используем первые 15 hex-символов SHA1 → decimal (положительный bigint).
		 * В ключ входит attribute_id + нормализованный текст, чтобы разные атрибуты
		 * с одинаковым текстом не сливались.
		 */
		$value_id_sql = "CONV(SUBSTRING(SHA1(CONCAT(attribute_id, ':', " . $norm . ")), 1, 15), 16, 10)";

		// Число для слайдера: только если текст начинается с числа (STRICT MySQL8 иначе падает на "Men")
		$slide_sql = "IF(TRIM(text) REGEXP '^-?[0-9]+([.,][0-9]+)?', CAST(REPLACE(TRIM(text), ',', '.') AS DECIMAL(15,4)), 0)";

		// Характеристики из attribute
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option` (option_id, status, type, sort_order)
			SELECT (attribute_id + '" . (int)$last_option_id . "'), '1' AS status, '" . $this->db->escape($copy_type) . "' AS type, sort_order
			FROM `" . DB_PREFIX . "attribute`
			ON DUPLICATE KEY UPDATE
				`sort_order` = VALUES(`sort_order`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` (option_id, language_id, name, description, postfix)
			SELECT (attribute_id + '" . (int)$last_option_id . "'), language_id, name, '', ''
			FROM `" . DB_PREFIX . "attribute_description`
			ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

		// Уникальные значения атрибута (по нормализованному text языка по умолчанию)
		// OC4/MySQL8 ONLY_FULL_GROUP_BY: агрегаты / полный GROUP BY
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (option_id, value_id)
			SELECT (attribute_id + '" . (int)$last_option_id . "'), " . $value_id_sql . "
			FROM `" . DB_PREFIX . "product_attribute`
			WHERE language_id = '" . $language_id . "'
			GROUP BY attribute_id, " . $norm . ", " . $value_id_sql . "
			ON DUPLICATE KEY UPDATE `option_id` = VALUES(`option_id`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (option_id, value_id, language_id, name)
			SELECT (attribute_id + '" . (int)$last_option_id . "'), " . $value_id_sql . ", language_id, MAX(TRIM(text))
			FROM `" . DB_PREFIX . "product_attribute`
			WHERE language_id = '" . $language_id . "'
			GROUP BY attribute_id, language_id, " . $norm . ", " . $value_id_sql . "
			ON DUPLICATE KEY UPDATE
				`option_id` = VALUES(`option_id`),
				`name` = VALUES(`name`)");

		// Описания значений на остальных языках:
		// value_id берём от языка по умолчанию того же товара через INNER JOIN (без N+1 subquery)
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		foreach ($languages as $language) {
			if ((int)$language['language_id'] === $language_id) {
				continue;
			}

			$norm_pa = "CONCAT(UCASE(LEFT(TRIM(pa.text), 1)), LCASE(SUBSTRING(TRIM(pa.text), 2)))";
			$norm_pa2 = "CONCAT(UCASE(LEFT(TRIM(pa2.text), 1)), LCASE(SUBSTRING(TRIM(pa2.text), 2)))";
			$value_id_pa2 = "CONV(SUBSTRING(SHA1(CONCAT(pa2.attribute_id, ':', " . $norm_pa2 . ")), 1, 15), 16, 10)";

			$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (option_id, value_id, language_id, name)
				SELECT
					(pa.attribute_id + '" . (int)$last_option_id . "'),
					" . $value_id_pa2 . " AS value_id,
					'" . (int)$language['language_id'] . "',
					MAX(" . $norm_pa . ")
				FROM `" . DB_PREFIX . "product_attribute` pa
				INNER JOIN `" . DB_PREFIX . "product_attribute` pa2 ON (
					pa2.product_id = pa.product_id
					AND pa2.attribute_id = pa.attribute_id
					AND pa2.language_id = '" . $language_id . "'
				)
				WHERE pa.language_id = '" . (int)$language['language_id'] . "'
				GROUP BY pa.attribute_id, " . $norm_pa . ", " . $value_id_pa2 . "
				ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");
		}

		// Связи товар↔значение атрибута (+ category_id + числа для слайдера)
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` WHERE option_id >= '" . (int)$last_option_id . "'");

		$norm_pa = "CONCAT(UCASE(LEFT(TRIM(pa.text), 1)), LCASE(SUBSTRING(TRIM(pa.text), 2)))";
		$value_id_pa = "CONV(SUBSTRING(SHA1(CONCAT(pa.attribute_id, ':', " . $norm_pa . ")), 1, 15), 16, 10)";
		$slide_pa = "IF(TRIM(pa.text) REGEXP '^-?[0-9]+([.,][0-9]+)?', CAST(REPLACE(TRIM(pa.text), ',', '.') AS DECIMAL(15,4)), 0)";

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product`
			(product_id, option_id, value_id, category_id, slide_value_min, slide_value_max)
			SELECT
				pa.product_id,
				(pa.attribute_id + '" . (int)$last_option_id . "'),
				" . $value_id_pa . " AS value_id,
				p2c.category_id,
				" . $slide_pa . " AS slide_value_min,
				" . $slide_pa . " AS slide_value_max
			FROM `" . DB_PREFIX . "product_attribute` pa
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pa.product_id)
			WHERE pa.language_id = '" . $language_id . "' AND p2c.category_id > '0'
			ON DUPLICATE KEY UPDATE
				`slide_value_min` = VALUES(`slide_value_min`),
				`slide_value_max` = VALUES(`slide_value_max`)");

		// Разделитель составных значений (напр. "Красный, Синий" → два value)
		$separator = '';
		if (!empty($data['module_wt_filter_attribute_separator'])) {
			$separator = (string)$data['module_wt_filter_attribute_separator'];
		} elseif (!empty($data['wt_filter_attribute_separator'])) {
			$separator = (string)$data['wt_filter_attribute_separator'];
		}

		if ($separator === '') {
			return;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_option_value_description`
			WHERE language_id = '" . $language_id . "'
			AND TRIM(name) LIKE '%" . $this->db->escape($separator) . "%'");

		foreach ($query->rows as $result) {
			// Части составного значения по всем языкам (индекс explode должен совпадать)
			$lang_query = $this->db->query("SELECT language_id, name FROM `" . DB_PREFIX . "wt_filter_option_value_description`
				WHERE value_id = '" . $this->db->escape($result['value_id']) . "'");

			$parts_by_lang = [];

			foreach ($lang_query->rows as $row) {
				$parts_by_lang[(int)$row['language_id']] = explode($separator, $row['name']);
			}

			$values = isset($parts_by_lang[$language_id])
				? $parts_by_lang[$language_id]
				: explode($separator, $result['name']);

			foreach ($values as $index => $value) {
				$value = $this->utf8Ucfirst(trim($value));

				if (!$value) {
					continue;
				}

				$slide = $this->parseSlideNumber($value);

				// Уже есть такое значение у этой характеристики (язык по умолчанию)?
				$value_query = $this->db->query("SELECT value_id FROM `" . DB_PREFIX . "wt_filter_option_value_description`
					WHERE language_id = '" . $language_id . "'
					AND option_id = '" . (int)$result['option_id'] . "'
					AND LCASE(TRIM(name)) = '" . $this->db->escape(oc_strtolower($value)) . "'");

				if ($value_query->num_rows) {
					$value_id = $value_query->row['value_id'];
				} else {
					// Новое атомарное значение (AUTO_INCREMENT value_id)
					$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (option_id) VALUES ('" . (int)$result['option_id'] . "')");
					$value_id = $this->db->getLastId();
				}

				// Описания атомарного значения для всех языков (по тому же индексу части)
				foreach ($parts_by_lang as $lang_id => $parts) {
					$part_name = isset($parts[$index]) ? $this->utf8Ucfirst(trim($parts[$index])) : $value;

					if ($part_name === '') {
						$part_name = $value;
					}

					$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (option_id, value_id, language_id, name)
						VALUES ('" . (int)$result['option_id'] . "', '" . $this->db->escape($value_id) . "', '" . (int)$lang_id . "', '" . $this->db->escape($part_name) . "')
						ON DUPLICATE KEY UPDATE
							`option_id` = VALUES(`option_id`),
							`name` = VALUES(`name`)");
				}

				// Перенести товары со старого составного value на новое атомарное (с category_id)
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product` (product_id, option_id, value_id, category_id, slide_value_min, slide_value_max)
					SELECT oov2p.product_id, '" . (int)$result['option_id'] . "', '" . $this->db->escape($value_id) . "',
						oov2p.category_id, '" . (float)$slide . "', '" . (float)$slide . "'
					FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					WHERE oov2p.option_id = '" . (int)$result['option_id'] . "'
					AND oov2p.value_id = '" . $this->db->escape($result['value_id']) . "'
					ON DUPLICATE KEY UPDATE
						`slide_value_min` = VALUES(`slide_value_min`),
						`slide_value_max` = VALUES(`slide_value_max`)");
			}

			// Удалить исходное составное значение целиком (все языки description + связи)
			if ($values) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value` WHERE value_id = '" . $this->db->escape($result['value_id']) . "'");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_description` WHERE value_id = '" . $this->db->escape($result['value_id']) . "'");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` WHERE option_id = '" . (int)$result['option_id'] . "' AND value_id = '" . $this->db->escape($result['value_id']) . "'");
			}
		}
	}

	/**
	 * Первое число из строки для слайдера: "15.6\"", "100 ГБ", "1,5 кг" → float.
	 */
	private function parseSlideNumber($text) {
		$this->load->helper('extension/wt_filter/wt_filter');
		$n = wt_filter_parse_numeric($text);

		return $n === null ? 0.0 : (float)$n;
	}

	/**
	 * Парсит имена значений → value_numeric и подтягивает slide_* у связей товара.
	 */
	public function syncValueNumerics() {
		$this->ensureSchema();
		$this->load->helper('extension/wt_filter/wt_filter');

		$language_id = (int)$this->config->get('config_language_id');
		$query = $this->db->query("SELECT ov.value_id, ovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` ov
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_description` ovd
				ON (ov.value_id = ovd.value_id AND ovd.language_id = '" . $language_id . "')");

		$batch = [];

		foreach ($query->rows as $row) {
			$n = wt_filter_parse_numeric($row['name']);
			$batch[] = [
				'value_id' => $row['value_id'],
				'numeric'  => $n,
			];

			if (count($batch) >= self::KEYWORD_BATCH_SIZE) {
				$this->flushValueNumericBatch($batch);
				$batch = [];
			}
		}

		if ($batch) {
			$this->flushValueNumericBatch($batch);
		}

		// Связи товара: если slide пустой — взять value_numeric
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_value` oov ON (oov.value_id = oov2p.value_id)
			SET oov2p.slide_value_min = oov.value_numeric,
				oov2p.slide_value_max = oov.value_numeric
			WHERE oov.value_numeric IS NOT NULL
				AND oov2p.slide_value_min = 0
				AND oov2p.slide_value_max = 0");
	}

	/**
	 * @param array $batch [['value_id'=>…,'numeric'=>float|null],…]
	 */
	private function flushValueNumericBatch(array $batch) {
		if (!$batch) {
			return;
		}

		$cases = [];
		$ids = [];

		foreach ($batch as $row) {
			$id = $this->db->escape((string)$row['value_id']);
			$ids[] = "'" . $id . "'";

			if ($row['numeric'] === null) {
				$cases[] = "WHEN '" . $id . "' THEN NULL";
			} else {
				$cases[] = "WHEN '" . $id . "' THEN '" . (float)$row['numeric'] . "'";
			}
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option_value` SET
			value_numeric = CASE value_id " . implode(' ', $cases) . " END
			WHERE value_id IN (" . implode(',', $ids) . ")");
	}

	/**
	 * Транслит названия → ЧПУ-keyword (RU + UA → латиница, пробелы → -).
	 * Общие буквы — нейтральная SEO-схема; UA-специфичные (ґ/є/і/ї) и RU (ё/ъ/ы/э) учтены отдельно.
	 */
	public function translit($string) {
		$replace = [
			// Общая кириллица (RU / UA)
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
			'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
			'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
			'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
			'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh',
			'щ' => 'shch', 'ь' => '', 'ю' => 'yu', 'я' => 'ya',

			// Украинские
			'ґ' => 'g', 'є' => 'ye', 'і' => 'i', 'ї' => 'yi',

			// Русские
			'ё' => 'yo', 'ъ' => '', 'ы' => 'y', 'э' => 'e',

			// Апострофы (в т.ч. украинский U+02BC)
			"'" => '', '’' => '', '`' => '', 'ʼ' => '',

			// Разделители
			' ' => '-', '+' => 'plus',
		];

		$string = mb_strtolower((string)$string, 'UTF-8');
		$string = strtr($string, $replace);
		// Только латиница/цифры — для чистого ЧПУ
		$string = preg_replace('![^a-z0-9]+!iu', '-', $string);
		$string = preg_replace('!-{2,}!', '-', $string);

		return trim((string)$string, '-');
	}

	/** Первая буква UTF-8 в верхний регистр (для нормализации значений) */
	private function utf8Ucfirst($str) {
		return oc_strtoupper(oc_substr($str, 0, 1)) . oc_substr($str, 1);
	}

	/**
	 * Список опций (как OCFilter): с values[] и categories[].
	 */
	public function getOptions($data = []) {
		$option_data = [];

		$sql = "SELECT o.*, od.name, od.postfix
			FROM `" . DB_PREFIX . "wt_filter_option` o
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_description` od
				ON (o.option_id = od.option_id)";

		if (!empty($data['filter_category_id'])) {
			$sql .= " LEFT JOIN `" . DB_PREFIX . "wt_filter_option_to_category` o2c ON (o.option_id = o2c.option_id)";
		}

		$sql .= " WHERE od.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_category_id'])) {
			$sql .= " AND o2c.category_id = '" . (int)$data['filter_category_id'] . "'";
		}

		if (!empty($data['filter_type'])) {
			$sql .= " AND o.type = '" . $this->db->escape($data['filter_type']) . "'";
		}

		if (!empty($data['filter_name'])) {
			$sql .= " AND LCASE(od.name) LIKE '%" . $this->db->escape(oc_strtolower($data['filter_name'])) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '' && $data['filter_status'] !== null) {
			$sql .= " AND o.status = '" . (int)$data['filter_status'] . "'";
		}

		$sql .= " GROUP BY o.option_id";

		$sort_data = ['o.sort_order', 'od.name', 'o.type', 'o.status', 'o.option_id'];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY o.sort_order, od.name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;

			if ($start < 0) {
				$start = 0;
			}

			if ($limit < 1) {
				$limit = 20;
			}

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$options_query = $this->db->query($sql);

		if (!$options_query->num_rows) {
			return $option_data;
		}

		$options_id = [];

		foreach ($options_query->rows as $option) {
			$options_id[] = (int)$option['option_id'];
		}

		$values = [];
		$values_query = $this->db->query("SELECT ov.value_id, ov.option_id, ovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` ov
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_value_description` ovd ON (ov.value_id = ovd.value_id)
			WHERE ov.option_id IN (" . implode(',', $options_id) . ")
				AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY ov.sort_order ASC, ovd.name ASC");

		foreach ($values_query->rows as $value) {
			$values[$value['option_id']][] = $value;
		}

		$categories = [];
		$categories_query = $this->db->query("SELECT c.category_id, cd.name, o2c.option_id
			FROM `" . DB_PREFIX . "wt_filter_option_to_category` o2c
			LEFT JOIN `" . DB_PREFIX . "category` c ON (c.category_id = o2c.category_id)
			LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id = c.category_id)
			WHERE o2c.option_id IN (" . implode(',', $options_id) . ")
				AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY c.sort_order, cd.name ASC");

		foreach ($categories_query->rows as $category) {
			$categories[$category['option_id']][] = $category;
		}

		foreach ($options_query->rows as $key => $option) {
			$option_data[$key] = $option;
			$option_data[$key]['values'] = isset($values[$option['option_id']]) ? $values[$option['option_id']] : [];
			$option_data[$key]['categories'] = isset($categories[$option['option_id']]) ? $categories[$option['option_id']] : [];
		}

		return $option_data;
	}

	public function getTotalOptions($data = []) {
		$sql = "SELECT COUNT(DISTINCT o.option_id) AS total
			FROM `" . DB_PREFIX . "wt_filter_option` o
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_description` od
				ON (o.option_id = od.option_id)";

		if (!empty($data['filter_category_id'])) {
			$sql .= " LEFT JOIN `" . DB_PREFIX . "wt_filter_option_to_category` o2c ON (o.option_id = o2c.option_id)";
		}

		$sql .= " WHERE od.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_category_id'])) {
			$sql .= " AND o2c.category_id = '" . (int)$data['filter_category_id'] . "'";
		}

		if (!empty($data['filter_type'])) {
			$sql .= " AND o.type = '" . $this->db->escape($data['filter_type']) . "'";
		}

		if (!empty($data['filter_name'])) {
			$sql .= " AND LCASE(od.name) LIKE '%" . $this->db->escape(oc_strtolower($data['filter_name'])) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '' && $data['filter_status'] !== null) {
			$sql .= " AND o.status = '" . (int)$data['filter_status'] . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function getOption($option_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "wt_filter_option`
			WHERE option_id = '" . (int)$option_id . "'");

		return $query->row;
	}

	public function getOptionDescriptions($option_id) {
		$data = [];
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_option_description`
			WHERE option_id = '" . (int)$option_id . "'");

		foreach ($query->rows as $row) {
			$data[$row['language_id']] = [
				'name'        => $row['name'],
				'description' => $row['description'],
				'postfix'     => $row['postfix'],
			];
		}

		return $data;
	}

	public function getOptionCategoryIds($option_id) {
		$ids = [];
		$query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "wt_filter_option_to_category`
			WHERE option_id = '" . (int)$option_id . "'");

		foreach ($query->rows as $row) {
			$ids[] = (int)$row['category_id'];
		}

		return $ids;
	}

	public function getOptionValues($option_id) {
		$query = $this->db->query("SELECT ov.*, ovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` ov
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_value_description` ovd
				ON (ov.value_id = ovd.value_id AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			WHERE ov.option_id = '" . (int)$option_id . "'
			ORDER BY ov.sort_order, ovd.name");

		return $query->rows;
	}

	public function editOption($option_id, $data) {
		$this->load->helper('extension/wt_filter/wt_filter');

		$type = isset($data['type']) ? (string)$data['type'] : 'checkbox';
		$use_image = !empty($data['image']) || wt_filter_is_image_type($type);

		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET
			type = '" . $this->db->escape($type) . "',
			keyword = '" . $this->db->escape($data['keyword']) . "',
			status = '" . (int)!empty($data['status']) . "',
			sort_order = '" . (int)$data['sort_order'] . "',
			selectbox = '" . (int)!empty($data['selectbox']) . "',
			color = '" . (int)!empty($data['color']) . "',
			image = '" . (int)$use_image . "'
			WHERE option_id = '" . (int)$option_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_description` WHERE option_id = '" . (int)$option_id . "'");

		if (!empty($data['option_description']) && is_array($data['option_description'])) {
			foreach ($data['option_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` SET
					option_id = '" . (int)$option_id . "',
					language_id = '" . (int)$language_id . "',
					name = '" . $this->db->escape($value['name']) . "',
					description = '" . $this->db->escape(isset($value['description']) ? $value['description'] : '') . "',
					postfix = '" . $this->db->escape(isset($value['postfix']) ? $value['postfix'] : '') . "'");
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_to_category` WHERE option_id = '" . (int)$option_id . "'");

		if (!empty($data['category_id']) && is_array($data['category_id'])) {
			foreach ($data['category_id'] as $category_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_category` SET
					option_id = '" . (int)$option_id . "',
					category_id = '" . (int)$category_id . "'");
			}
		}

		// Override image / color значений
		if (!empty($data['value']) && is_array($data['value'])) {
			foreach ($data['value'] as $value_id => $value_data) {
				$image = isset($value_data['image']) ? (string)$value_data['image'] : '';
				$color = isset($value_data['color']) ? (string)$value_data['color'] : '';

				$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option_value` SET
					image = '" . $this->db->escape($image) . "',
					color = '" . $this->db->escape($color) . "'
					WHERE value_id = '" . $this->db->escape((string)$value_id) . "'
						AND option_id = '" . (int)$option_id . "'");
			}
		}

		$this->cache->delete('wt_filter');
		$this->cache->delete('product');
	}

	public function deleteOption($option_id) {
		$option_id = (int)$option_id;

		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_description` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_to_category` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_to_store` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_description` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` WHERE option_id = '" . $option_id . "'");

		$this->cache->delete('wt_filter');
		$this->cache->delete('product');
	}

	/**
	 * Опции фильтра, привязанные к категории (для вкладки товара).
	 */
	public function getOptionsByCategoryId($category_id) {
		$options_data = [];

		$options_query = $this->db->query("SELECT o.*, od.name, od.postfix
			FROM `" . DB_PREFIX . "wt_filter_option` o
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_description` od ON (o.option_id = od.option_id)
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_to_category` o2c ON (o.option_id = o2c.option_id)
			WHERE o2c.category_id = '" . (int)$category_id . "'
				AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY o.sort_order, od.name");

		if (!$options_query->num_rows) {
			return $options_data;
		}

		$options_id = [];

		foreach ($options_query->rows as $option) {
			$options_id[] = (int)$option['option_id'];
		}

		$values_query = $this->db->query("SELECT ov.*, ovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` ov
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_value_description` ovd ON (ov.value_id = ovd.value_id)
			WHERE ov.option_id IN (" . implode(',', $options_id) . ")
				AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY ov.sort_order, ovd.name");

		$values = [];

		foreach ($values_query->rows as $value) {
			$values[$value['option_id']][] = $value;
		}

		foreach ($options_query->rows as $option) {
			$options_data[$option['option_id']] = $option;
			$options_data[$option['option_id']]['values'] = isset($values[$option['option_id']]) ? $values[$option['option_id']] : [];
		}

		return $options_data;
	}

	/**
	 * Выбранные значения фильтра у товара (для callback вкладки).
	 */
	public function getProductValues($product_id) {
		$product_values_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
			WHERE product_id = '" . (int)$product_id . "'
			GROUP BY option_id, value_id");

		foreach ($query->rows as $result) {
			$product_values_data[$result['option_id']][(string)$result['value_id']] = $result;
		}

		return $product_values_data;
	}

	/**
	 * Для copyProduct / getForm (совместимость с OCMOD).
	 */
	public function getProductWtFilterValues($product_id) {
		$product_filter_value_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
			WHERE product_id = '" . (int)$product_id . "'
			GROUP BY option_id, value_id");

		foreach ($query->rows as $result) {
			$product_filter_value_data[$result['option_id']]['values'][(string)$result['value_id']] = array_merge($result, ['selected' => true]);
		}

		return $product_filter_value_data;
	}

	/**
	 * Удалить товар из индекса фильтра (после deleteProduct).
	 */
	public function deleteProductFromIndex($product_id) {
		$product_id = (int)$product_id;

		if ($product_id < 1) {
			return;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
			WHERE product_id = '" . $product_id . "'");

		$this->cache->delete('wt_filter');
		$this->cache->delete('product');
	}

	/**
	 * Переиндексация одного товара в wt_filter из OC (опции / фильтры / атрибуты).
	 * Вызывать после сохранения product_option / product_filter / product_attribute / product_to_category.
	 */
	public function indexProduct($product_id) {
		$product_id = (int)$product_id;

		if ($product_id < 1) {
			return;
		}

		$this->ensureSchema();

		$copy_type = 'checkbox';
		$language_id = (int)$this->config->get('config_language_id');
		$filter_offset = 10000;
		$attribute_offset = 30000;

		// Полностью пересобрать связи товара
		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
			WHERE product_id = '" . $product_id . "'");

		// --- Опции товара ---
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option` (option_id, `type`, `status`, sort_order, image)
			SELECT o.option_id, '" . $this->db->escape($copy_type) . "', '1', o.sort_order, IF(o.`type` = 'image', 1, 0)
			FROM `" . DB_PREFIX . "option` o
			INNER JOIN `" . DB_PREFIX . "product_option` po ON (po.option_id = o.option_id)
			WHERE po.product_id = '" . $product_id . "'
			GROUP BY o.option_id
			ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` (option_id, language_id, name, description, postfix)
			SELECT od.option_id, od.language_id, od.name, '', ''
			FROM `" . DB_PREFIX . "option_description` od
			INNER JOIN `" . DB_PREFIX . "product_option` po ON (po.option_id = od.option_id)
			WHERE po.product_id = '" . $product_id . "'
			GROUP BY od.option_id, od.language_id
			ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (value_id, option_id, image, sort_order)
			SELECT ov.option_value_id, ov.option_id, IFNULL(ov.image, ''), ov.sort_order
			FROM `" . DB_PREFIX . "option_value` ov
			INNER JOIN `" . DB_PREFIX . "product_option_value` pov ON (pov.option_value_id = ov.option_value_id)
			WHERE pov.product_id = '" . $product_id . "'
			GROUP BY ov.option_value_id
			ON DUPLICATE KEY UPDATE
				`option_id` = VALUES(`option_id`),
				`image` = IF(`" . DB_PREFIX . "wt_filter_option_value`.`image` = '', VALUES(`image`), `" . DB_PREFIX . "wt_filter_option_value`.`image`),
				`sort_order` = VALUES(`sort_order`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (value_id, option_id, language_id, name)
			SELECT ovd.option_value_id, ovd.option_id, ovd.language_id, ovd.name
			FROM `" . DB_PREFIX . "option_value_description` ovd
			INNER JOIN `" . DB_PREFIX . "product_option_value` pov ON (pov.option_value_id = ovd.option_value_id)
			WHERE pov.product_id = '" . $product_id . "'
			GROUP BY ovd.option_value_id, ovd.language_id
			ON DUPLICATE KEY UPDATE
				`option_id` = VALUES(`option_id`),
				`name` = VALUES(`name`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product`
			(product_id, value_id, option_id, category_id, slide_value_min, slide_value_max)
			SELECT pov.product_id, pov.option_value_id, pov.option_id, p2c.category_id, 0, 0
			FROM `" . DB_PREFIX . "product_option_value` pov
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pov.product_id)
			WHERE pov.product_id = '" . $product_id . "'
				AND p2c.category_id > '0'
			ON DUPLICATE KEY UPDATE
				`slide_value_min` = VALUES(`slide_value_min`),
				`slide_value_max` = VALUES(`slide_value_max`)");

		// --- Стандартные фильтры OC ---
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option` (option_id, `type`, `status`, sort_order)
			SELECT (fg.filter_group_id + '" . $filter_offset . "'), '" . $this->db->escape($copy_type) . "', '1', fg.sort_order
			FROM `" . DB_PREFIX . "filter_group` fg
			INNER JOIN `" . DB_PREFIX . "filter` f ON (f.filter_group_id = fg.filter_group_id)
			INNER JOIN `" . DB_PREFIX . "product_filter` pf ON (pf.filter_id = f.filter_id)
			WHERE pf.product_id = '" . $product_id . "'
			GROUP BY fg.filter_group_id
			ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` (option_id, language_id, name, description, postfix)
			SELECT (fgd.filter_group_id + '" . $filter_offset . "'), fgd.language_id, fgd.name, '', ''
			FROM `" . DB_PREFIX . "filter_group_description` fgd
			INNER JOIN `" . DB_PREFIX . "filter` f ON (f.filter_group_id = fgd.filter_group_id)
			INNER JOIN `" . DB_PREFIX . "product_filter` pf ON (pf.filter_id = f.filter_id)
			WHERE pf.product_id = '" . $product_id . "'
			GROUP BY fgd.filter_group_id, fgd.language_id
			ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (value_id, option_id, sort_order)
			SELECT (f.filter_id + '" . $filter_offset . "'), (f.filter_group_id + '" . $filter_offset . "'), f.sort_order
			FROM `" . DB_PREFIX . "filter` f
			INNER JOIN `" . DB_PREFIX . "product_filter` pf ON (pf.filter_id = f.filter_id)
			WHERE pf.product_id = '" . $product_id . "'
			GROUP BY f.filter_id
			ON DUPLICATE KEY UPDATE
				`option_id` = VALUES(`option_id`),
				`sort_order` = VALUES(`sort_order`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (value_id, option_id, language_id, name)
			SELECT (fd.filter_id + '" . $filter_offset . "'), (f.filter_group_id + '" . $filter_offset . "'), fd.language_id, fd.name
			FROM `" . DB_PREFIX . "filter_description` fd
			INNER JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = fd.filter_id)
			INNER JOIN `" . DB_PREFIX . "product_filter` pf ON (pf.filter_id = f.filter_id)
			WHERE pf.product_id = '" . $product_id . "'
			GROUP BY fd.filter_id, fd.language_id
			ON DUPLICATE KEY UPDATE
				`option_id` = VALUES(`option_id`),
				`name` = VALUES(`name`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product`
			(product_id, value_id, option_id, category_id, slide_value_min, slide_value_max)
			SELECT pf.product_id,
				(pf.filter_id + '" . $filter_offset . "'),
				(f.filter_group_id + '" . $filter_offset . "'),
				p2c.category_id,
				0, 0
			FROM `" . DB_PREFIX . "product_filter` pf
			INNER JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id)
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pf.product_id)
			WHERE pf.product_id = '" . $product_id . "'
				AND p2c.category_id > '0'
			ON DUPLICATE KEY UPDATE
				`slide_value_min` = VALUES(`slide_value_min`),
				`slide_value_max` = VALUES(`slide_value_max`)");

		// --- Атрибуты ---
		$norm = "CONCAT(UCASE(LEFT(TRIM(pa.text), 1)), LCASE(SUBSTRING(TRIM(pa.text), 2)))";
		$value_id_sql = "CONV(SUBSTRING(SHA1(CONCAT(pa.attribute_id, ':', " . $norm . ")), 1, 15), 16, 10)";
		$slide_sql = "IF(TRIM(pa.text) REGEXP '^-?[0-9]+([.,][0-9]+)?', CAST(REPLACE(TRIM(pa.text), ',', '.') AS DECIMAL(15,4)), 0)";

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option` (option_id, status, type, sort_order)
			SELECT (a.attribute_id + '" . $attribute_offset . "'), '1', '" . $this->db->escape($copy_type) . "', a.sort_order
			FROM `" . DB_PREFIX . "attribute` a
			INNER JOIN `" . DB_PREFIX . "product_attribute` pa ON (pa.attribute_id = a.attribute_id)
			WHERE pa.product_id = '" . $product_id . "'
			GROUP BY a.attribute_id
			ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_description` (option_id, language_id, name, description, postfix)
			SELECT (ad.attribute_id + '" . $attribute_offset . "'), ad.language_id, ad.name, '', ''
			FROM `" . DB_PREFIX . "attribute_description` ad
			INNER JOIN `" . DB_PREFIX . "product_attribute` pa ON (pa.attribute_id = ad.attribute_id)
			WHERE pa.product_id = '" . $product_id . "'
			GROUP BY ad.attribute_id, ad.language_id
			ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value` (option_id, value_id)
			SELECT (pa.attribute_id + '" . $attribute_offset . "'), " . $value_id_sql . "
			FROM `" . DB_PREFIX . "product_attribute` pa
			WHERE pa.product_id = '" . $product_id . "'
				AND pa.language_id = '" . $language_id . "'
				AND TRIM(pa.text) <> ''
			GROUP BY pa.attribute_id, " . $norm . ", " . $value_id_sql . "
			ON DUPLICATE KEY UPDATE `option_id` = VALUES(`option_id`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_description` (option_id, value_id, language_id, name)
			SELECT (pa.attribute_id + '" . $attribute_offset . "'), " . $value_id_sql . ", pa.language_id, MAX(TRIM(pa.text))
			FROM `" . DB_PREFIX . "product_attribute` pa
			WHERE pa.product_id = '" . $product_id . "'
				AND pa.language_id = '" . $language_id . "'
				AND TRIM(pa.text) <> ''
			GROUP BY pa.attribute_id, pa.language_id, " . $norm . ", " . $value_id_sql . "
			ON DUPLICATE KEY UPDATE
				`option_id` = VALUES(`option_id`),
				`name` = VALUES(`name`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product`
			(product_id, option_id, value_id, category_id, slide_value_min, slide_value_max)
			SELECT
				pa.product_id,
				(pa.attribute_id + '" . $attribute_offset . "'),
				" . $value_id_sql . ",
				p2c.category_id,
				" . $slide_sql . ",
				" . $slide_sql . "
			FROM `" . DB_PREFIX . "product_attribute` pa
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pa.product_id)
			WHERE pa.product_id = '" . $product_id . "'
				AND pa.language_id = '" . $language_id . "'
				AND TRIM(pa.text) <> ''
				AND p2c.category_id > '0'
			ON DUPLICATE KEY UPDATE
				`slide_value_min` = VALUES(`slide_value_min`),
				`slide_value_max` = VALUES(`slide_value_max`)");

		// Привязка характеристик к категориям/магазинам товара
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_category` (option_id, category_id)
			SELECT oov2p.option_id, oov2p.category_id
			FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
			WHERE oov2p.product_id = '" . $product_id . "'
				AND oov2p.category_id > '0'
			GROUP BY oov2p.option_id, oov2p.category_id
			ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_store` (option_id, store_id)
			SELECT oov2p.option_id, p2s.store_id
			FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
			INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = oov2p.product_id)
			WHERE oov2p.product_id = '" . $product_id . "'
			GROUP BY oov2p.option_id, p2s.store_id
			ON DUPLICATE KEY UPDATE `store_id` = VALUES(`store_id`)");

		// keyword / числа для новых значений
		$this->generateKeywordsBatched();
		$this->syncValueNumerics();

		$this->cache->delete('wt_filter');
		$this->cache->delete('product');
	}

	/**
	 * Сохранить значения фильтра с вкладки товара (ручной override).
	 * Для автоиндекса из OC используйте indexProduct().
	 */
	public function saveProductFilterValues($product_id, $data) {
		$product_id = (int)$product_id;

		$this->db->query("DELETE FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` WHERE product_id = '" . $product_id . "'");

		if (empty($data['wt_filter_product_option']) || !is_array($data['wt_filter_product_option'])) {
			return;
		}

		$categories = [];

		if (!empty($data['product_category']) && is_array($data['product_category'])) {
			foreach ($data['product_category'] as $category_id) {
				$category_id = (int)$category_id;
				if ($category_id > 0) {
					$categories[$category_id] = $category_id;
				}
			}
		}

		if (!$categories) {
			$categories = [0];
		}

		$rows = [];

		foreach ($data['wt_filter_product_option'] as $option_id => $values) {
			if (empty($values['values']) || !is_array($values['values'])) {
				continue;
			}

			foreach ($values['values'] as $value_id => $value) {
				if (!isset($value['selected'])) {
					continue;
				}

				$slide_min = isset($value['slide_value_min']) ? (float)$value['slide_value_min'] : 0;
				$slide_max = isset($value['slide_value_max']) ? (float)$value['slide_value_max'] : 0;

				foreach ($categories as $category_id) {
					$rows[] = "('" . $product_id . "', '" . (int)$option_id . "', '" . $this->db->escape((string)$value_id) . "', '" . (int)$category_id . "', '" . $slide_min . "', '" . $slide_max . "')";
				}
			}
		}

		if ($rows) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_value_to_product`
				(product_id, option_id, value_id, category_id, slide_value_min, slide_value_max)
				VALUES " . implode(',', $rows) . "
				ON DUPLICATE KEY UPDATE
					`slide_value_min` = VALUES(`slide_value_min`),
					`slide_value_max` = VALUES(`slide_value_max`)");
		}

		$this->cache->delete('wt_filter');
		$this->cache->delete('product');
	}

	public function editOptionField($option_id, $field, $value) {
		$option_id = (int)$option_id;
		$allowed = ['type', 'sort_order', 'status', 'keyword', 'selectbox', 'color', 'image', 'expanded_desktop', 'expanded_mobile'];

		if ($field === 'name') {
			$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option_description` SET
				name = '" . $this->db->escape($value) . "'
				WHERE option_id = '" . $option_id . "'
					AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
		} elseif (in_array($field, $allowed)) {
			$raw_value = $value;

			if (in_array($field, ['status', 'selectbox', 'color', 'image', 'sort_order', 'expanded_desktop', 'expanded_mobile'])) {
				$value = (int)$value;
			} else {
				$value = $this->db->escape($value);
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET
				`" . $field . "` = '" . $value . "'
				WHERE option_id = '" . $option_id . "'");

			// При смене типа на *_image включаем флаг image
			if ($field === 'type') {
				$this->load->helper('extension/wt_filter/wt_filter');

				if (wt_filter_is_image_type($raw_value)) {
					$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `image` = '1' WHERE option_id = '" . $option_id . "'");
				}
			}
		} else {
			return false;
		}

		$this->cache->delete('wt_filter');
		$this->cache->delete('product');

		return true;
	}
}
