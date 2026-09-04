<?php
namespace Opencart\Admin\Model\Extension\LanguageUkrainian4100\Language;
class Ukrainian extends \Opencart\System\Engine\Model{
    private $extensionDescription     = 'Українська локалізація';
    private $extensionFolder          = 'language_ukrainian_4100';
    private $extensionType            = 'language';
    private $extensionCode            = 'ukrainian';
    private $extensionRoute           = 'extension/language_ukrainian_4100/language/ukrainian';
    private $language_id;

    public function __construct($registry){
        parent::__construct($registry);
        $query = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language` WHERE `code` = 'uk-ua'");    
        if ($query->row){
            $this->language_id = $query->row['language_id'];

        }else{
            $this->language_id = null;

        }

    }
    public function setLanguageId($language_id){
        $this->language_id = $language_id;

    }
    public function addSeoUrl(): void{
        $query = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language` WHERE `code` = 'uk-ua'");    
        if ($query->row){
            $language_id = $query->row['language_id'];
            $data['store_id'] = $this->config->get('config_store_id');
            
            // Перевіряємо чи є SEO URL для української мови
            $query = $this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'language' AND `value` = 'uk-ua'");    
            if (!$query->row){
                
                // Додаємо SEO URL ящо немає
                $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '" . (int)$data['store_id'] . "', `language_id` = '" . (int)$this->language_id . "', `key` = 'language', `value` = 'uk-ua', `keyword` = 'ua', `sort_order` = '-2'");
                $seo_url_id = $this->db->getLastId();

            }else{
                $seo_url_id = $query->row['seo_url_id'];

            }

        }

    }
    public function addCurrency(){
        
        // Перевіряємо чи є валюта Гривня
        $query = $this->db->query("SELECT `currency_id` FROM `" . DB_PREFIX . "currency` WHERE `code` = 'UAH'");    
        if (!$query->row){
            
            // Додаємо UAH ящо немає
            $this->db->query("INSERT INTO `" . DB_PREFIX . "currency` (`title`, `code`, `symbol_left`, `symbol_right`, `decimal_place`, `value`, `status`, `date_modified`) VALUES ('Гривня', 'UAH', '', ' ₴', '2', 1.00000000, 1, NOW());
            ");
            $currency_id = $this->db->getLastId();

        }else{
            $currency_id = $query->row['currency_id'];

        }

    }
    public function translateCustomerGroups(){
        $query = $this->db->query("SELECT `customer_group_id`, `name` FROM `" . DB_PREFIX . "customer_group_description` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Default' == $row['name']){
                    $this->db->query("UPDATE `" . DB_PREFIX . "customer_group_description` SET `name` = '" . $this->db->escape('Основна') . "' WHERE `customer_group_id` = '" . (int)$row['customer_group_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Wholesale' == $row['name']){
                    $this->db->query("UPDATE `" . DB_PREFIX . "customer_group_description` SET `name` = '" . $this->db->escape('Оптові покупці') . "' WHERE `customer_group_id` = '" . (int)$row['customer_group_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Retail' == $row['name']){
                    $this->db->query("UPDATE `" . DB_PREFIX . "customer_group_description` SET `name` = '" . $this->db->escape('Роздрібні покупці') . "' WHERE `customer_group_id` = '" . (int)$row['customer_group_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }

    }
    public function translateUnitsWeights(){
        
        // Переклад одиниць виміру
        $query = $this->db->query("SELECT `length_class_id`, `title` FROM `" . DB_PREFIX . "length_class_description` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Centimeter' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "length_class_description` SET `title` = '" . $this->db->escape('Сантиметр') . "', `unit` = '" . $this->db->escape('см') . "' WHERE `length_class_id` = '" . (int)$row['length_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Millimeter' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "length_class_description` SET `title` = '" . $this->db->escape('Міліметр') . "', `unit` = '" . $this->db->escape('мм') . "' WHERE `length_class_id` = '" . (int)$row['length_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Inch' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "length_class_description` SET `title` = '" . $this->db->escape('Дюйм') . "', `unit` = '" . $this->db->escape('in') . "' WHERE `length_class_id` = '" . (int)$row['length_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        $this->cache->delete('length_class');
        
        // Переклад одиниць ваги
        $query = $this->db->query("SELECT `weight_class_id`, `title` FROM `" . DB_PREFIX . "weight_class_description` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Kilogram' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "weight_class_description` SET `title` = '" . $this->db->escape('Кілограм') . "' WHERE `weight_class_id` = '" . (int)$row['weight_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Gram' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "weight_class_description` SET `title` = '" . $this->db->escape('Грам') . "' WHERE `weight_class_id` = '" . (int)$row['weight_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Pound' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "weight_class_description` SET `title` = '" . $this->db->escape('Фунт') . "' WHERE `weight_class_id` = '" . (int)$row['weight_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Ounce' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "weight_class_description` SET `title` = '" . $this->db->escape('Унція') . "' WHERE `weight_class_id` = '" . (int)$row['weight_class_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        $this->cache->delete('weight_class');

    }
    public function translateInfoPages(){
        
        // Переклад назв інфосторінок
        $query = $this->db->query("SELECT `information_id`, `title` FROM `" . DB_PREFIX . "information_description` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('About Us' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "information_description` SET `title` = '" . $this->db->escape('Про магазин') . "' WHERE `information_id` = '" . (int)$row['information_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Terms &amp;
                Conditions' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "information_description` SET `title` = '" . $this->db->escape('Умови оформлення замовлення') . "' WHERE `information_id` = '" . (int)$row['information_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Privacy Policy' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "information_description` SET `title` = '" . $this->db->escape('Умови використання сайту') . "' WHERE `information_id` = '" . (int)$row['information_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Delivery Information' == trim($row['title'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "information_description` SET `title` = '" . $this->db->escape('Інформація про доставку') . "' WHERE `information_id` = '" . (int)$row['information_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        $this->cache->delete('information');

    }
    public function translateOptions(){
        
        // Переклад опцій
        $query = $this->db->query("SELECT `option_id`, `name` FROM `" . DB_PREFIX . "option_description` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Radio' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Перемикач') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Checkbox' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Прапорець') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Text' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Текст') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Textarea' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Текстова область') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Date' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Дата') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('File' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Файл') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Select' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Список') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Time' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Час') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Date &amp;
                Time' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Дата та час') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Delivery Date' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Дата доставки') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Size' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "option_description` SET `name` = '" . $this->db->escape('Розмір') . "' WHERE `option_id` = '" . (int)$row['option_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }

    }
    public function translateOrderStatuses(){
        
        // Переклад статусів замовлень
        $query = $this->db->query("SELECT `order_status_id`, `name` FROM `" . DB_PREFIX . "order_status` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Pending' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Очікування') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Processing' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('В обробці') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Shipped' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Доставлено') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Complete' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Завершено') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Canceled' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Скасовано') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Denied' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Помилкове') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Canceled Reversal' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Повністю скасовано') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Failed' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Невдале') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Refunded' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Відшкодоване') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Reversed' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Повністю змінене') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Chargeback' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Повне повернення') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Expired' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Прострочене') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Processed' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Оброблено') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Voided' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "order_status` SET `name` = '" . $this->db->escape('Анульовано') . "' WHERE `order_status_id` = '" . (int)$row['order_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        $this->cache->delete('order_status');

    }
    public function translateReturns(){
        
        // Переклад статусів повернення
        $query = $this->db->query("SELECT `return_status_id`, `name` FROM `" . DB_PREFIX . "return_status` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Pending' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_status` SET `name` = '" . $this->db->escape('В очікуванні') . "' WHERE `return_status_id` = '" . (int)$row['return_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Complete' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_status` SET `name` = '" . $this->db->escape('Виконано') . "' WHERE `return_status_id` = '" . (int)$row['return_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Awaiting Products' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_status` SET `name` = '" . $this->db->escape('Очікування товару') . "' WHERE `return_status_id` = '" . (int)$row['return_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        
        // Переклад операцій повернення
        $query = $this->db->query("SELECT `return_action_id`, `name` FROM `" . DB_PREFIX . "return_action` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Refunded' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_action` SET `name` = '" . $this->db->escape('Відшкодовано') . "' WHERE `return_action_id` = '" . (int)$row['return_action_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Credit Issued' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_action` SET `name` = '" . $this->db->escape('Повернення коштів') . "' WHERE `return_action_id` = '" . (int)$row['return_action_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Replacement Sent' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_action` SET `name` = '" . $this->db->escape('Відправлено заміну') . "' WHERE `return_action_id` = '" . (int)$row['return_action_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        
        // Переклад причин повернення
        $query = $this->db->query("SELECT `return_reason_id`, `name` FROM `" . DB_PREFIX . "return_reason` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('Dead On Arrival' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_reason` SET `name` = '" . $this->db->escape('Отримано/доставлено несправним (зламаним)') . "' WHERE `return_reason_id` = '" . (int)$row['return_reason_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Received Wrong Item' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_reason` SET `name` = '" . $this->db->escape('Отримано не той (помилковий) товар') . "' WHERE `return_reason_id` = '" . (int)$row['return_reason_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Order Error' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_reason` SET `name` = '" . $this->db->escape('Помилкове замовлення') . "' WHERE `return_reason_id` = '" . (int)$row['return_reason_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Faulty, please supply details' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_reason` SET `name` = '" . $this->db->escape('Несправний, будь ласка, вкажіть подробиці') . "' WHERE `return_reason_id` = '" . (int)$row['return_reason_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Other, please supply details' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "return_reason` SET `name` = '" . $this->db->escape('Інше (інша причина), будь ласка, вкажіть/докладіть подробиці') . "' WHERE `return_reason_id` = '" . (int)$row['return_reason_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }

    }

    public function translateAvailabilityStatuses(){
        
        // Переклад статусів наявності
        $query = $this->db->query("SELECT `stock_status_id`, `name` FROM `" . DB_PREFIX . "stock_status` WHERE `language_id` = '1'");    
        if ($query->rows){
            foreach ($query->rows as $row){
                if ('In Stock' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "stock_status` SET `name` = '" . $this->db->escape('В наявності') . "' WHERE `stock_status_id` = '" . (int)$row['stock_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Pre-Order' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "stock_status` SET `name` = '" . $this->db->escape('Попереднє замовлення') . "' WHERE `stock_status_id` = '" . (int)$row['stock_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('Out Of Stock' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "stock_status` SET `name` = '" . $this->db->escape('Немає в наявності') . "' WHERE `stock_status_id` = '" . (int)$row['stock_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }
                if ('2-3 Days' == trim($row['name'])){
                    $this->db->query("UPDATE `" . DB_PREFIX . "stock_status` SET `name` = '" . $this->db->escape('Очікування 2-3 дні') . "' WHERE `stock_status_id` = '" . (int)$row['stock_status_id'] . "' AND `language_id` = '" . (int)$this->language_id . "'");

                }

            }

        }
        $this->cache->delete('stock_status');

    }

    public function translateCountyZones(){
        // Переклад країни
		$this->db->query("UPDATE " . DB_PREFIX . "country SET name = '" . $this->db->escape('Україна') . "' WHERE name = 'Ukraine'");

        // Переклад областей
        $query = $this->db->query("SELECT `zone_id`, `name` FROM `" . DB_PREFIX . "zone`");    

		if ($query->rows) {
			$zoneTranslations = [
				'Cherkaska' => 'Черкаська область',
				'Chernihivska' => 'Чернігівська область',
				'Chernivetska' => 'Чернівецька область',
				'Avtonomna Respublika Krym' => 'Автономна Республіка Крим',
				'Dnipropetrovska' => 'Дніпропетровська область',
				'Donetska' => 'Донецька область',
				'Ivano-Frankivska' => 'Івано-Франківська область',
				'Khersonska' => 'Херсонська область',
				'Khmelnytska' => 'Хмельницька область',
				'Kirovohradska' => 'Кіровоградська область',
				'Kyiv' => 'Київ',
				'Kyivska' => 'Київська область',
				'Luhanska' => 'Луганська область',
				'Lvivska' => 'Львівська область',
				'Mykolaivska' => 'Миколаївська область',
				'Odeska' => 'Одеська область',
				'Poltavska' => 'Полтавська область',
				'Rivnenska' => 'Рівненська область',
				'Sevastopol' => 'Севастополь',
				'Sumska' => 'Сумська область',
				'Ternopilska' => 'Тернопільська область',
				'Vinnytska' => 'Вінницька область',
				'Volynska' => 'Волинська область',
				'Zakarpatska' => 'Закарпатська область',
				'Zaporizka' => 'Запорізька область',
				'Zhytomyrskа' => 'Житомирська область',
				'Kharkivska' => 'Харківська область',
			];

			foreach ($query->rows as $row) {
				$currentName = trim($row['name']);
					if (isset($zoneTranslations[$currentName])) {
						$newName = $zoneTranslations[$currentName];
						$this->db->query("UPDATE `" . DB_PREFIX . "zone` SET `name` = '" . $this->db->escape($newName) . "' WHERE `zone_id` = '" . (int)$row['zone_id'] . "'");
					}
			}
		}

		$this->cache->delete('zone');
    }

    public function deleteLanguageEntries(int $language_id): void{
        
        // customer_group_description
        $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_group_description` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // length_class_description
        $this->db->query("DELETE FROM `" . DB_PREFIX . "length_class_description` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // order_status
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_status` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // return_action
        $this->db->query("DELETE FROM `" . DB_PREFIX . "return_action` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // return_reason
        $this->db->query("DELETE FROM `" . DB_PREFIX . "return_reason` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // return_status
        $this->db->query("DELETE FROM `" . DB_PREFIX . "return_status` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // stock_status
        $this->db->query("DELETE FROM `" . DB_PREFIX . "stock_status` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // subscription_status
        $this->db->query("DELETE FROM `" . DB_PREFIX . "subscription_status` WHERE `language_id` = '" . (int)$language_id . "'");
        
        // weight_class_description
        $this->db->query("DELETE FROM `" . DB_PREFIX . "weight_class_description` WHERE `language_id` = '" . (int)$language_id . "'");

    }

}