<?php
namespace Opencart\Admin\Controller\Extension\LanguageUkrainian4100\Language;
class Ukrainian extends \Opencart\System\Engine\Controller{
    private $extensionDescription     = 'Українська локалізація';
    private $extensionFolder          = 'language_ukrainian_4100';
    private $extensionType            = 'language';
    private $extensionCode            = 'ukrainian';
    private $extensionRoute           = 'extension/language_ukrainian_4100/language/ukrainian';
    private $extensionAdminMenu       = '';
    private $extensionAdminFolder     = '';
    private $extensionCatalogFolder   = '';
    private $extensionVersion         = '4.1.0.0';
    private $extensionDbAddon         = false;
    private $extensionStore           = true;
    private $extensionRemoveAll       = false;
    private $error                    = [];
    private $extensionLanguageUA      = true;
    private $extensionLanguageDefault = 'en-gb';
    private $extensionCopy            = true;
    protected string $dir_extension;
    protected string $method_separator;
    public function index(): void{
        $this->load->model('localisation/language');
        // Если Installer только распаковал файлы, а Install в Languages не создал запись — создаём здесь.
        $language_info = $this->ensureUkrainianLanguage();
        if ($language_info){
            $data['entry_language_uk_ua_name'] = $language_info['name'];
            $data[$this->extensionType . '_' . $this->extensionCode . '_uk_ua_status'] = $language_info['status'];

        }
        $data['extensionType'] = $this->extensionType;
        $data['extensionCode'] = $this->extensionCode;
        $data['extensionRoute'] = $this->extensionRoute;
        $data['extensionDbAddon'] = $this->extensionDbAddon;
        $data[$this->extensionType . '_' . $this->extensionCode . '_ua_status'] = $this->extensionLanguageUA;
        $data[$this->extensionType . '_' . $this->extensionCode . '_version'] = $this->extensionVersion;
        $data[$this->extensionType . '_' . $this->extensionCode . '_remove_all'] = $this->config->get($this->extensionType . '_' . $this->extensionCode . '_remove_all');
        $data[$this->extensionType . '_' . $this->extensionCode . '_status'] = $this->config->get($this->extensionType . '_' . $this->extensionCode  . '_status');
        $this->load->language($this->extensionRoute);
        if ($this->extensionStore){
            $data['extension_store'] = true;
            $data['extension_store_required'] = true;
            $this->load->model('setting/store');
            $stores = $this->model_setting_store->getStores();
            $data['stores'] = [];
            
            $data['stores'][] = [
            'store_id' => 0,
            'name'     => $this->config->get('config_name') . ' ' . $this->language->get('text_default')
            ];
            foreach ($stores as $store){
                
                $data['stores'][] = [
                'store_id' => $store['store_id'],
                'name'     => $store['name']
                ];

            }
            $data[$this->extensionType . '_' . $this->extensionCode . '_store_id'] = $this->config->get($this->extensionType . '_' . $this->extensionCode . '_store_id') ?: 0;
            $data[$this->extensionType . '_' . $this->extensionCode . '_store'] = $this->config->get($this->extensionType . '_' . $this->extensionCode . '_store') ?: [0];

        }
        $data['text_version'] = $this->language->get('column_version');
        $data['text_date_added'] = $this->language->get('column_date_added');
        $this->load->model('setting/extension');
        $install_info = $this->model_setting_extension->getInstallByCode($this->extensionFolder);
        if ($install_info){
            $data['extension_name'] = $install_info['name'];
            $data['extension_description'] = version_compare(VERSION, '4.1.0.0', '>=') ? $install_info['description'] : $this->extensionDescription;
            $data['extension_version'] = $install_info['version'] . '-' . $this->extensionVersion;
            $data['extension_author'] = $install_info['author'];
            $data['extension_link'] = $install_info['link'];
            $data['extension_status'] = $install_info['status'];
            $data['extension_date_added'] = $install_info['date_added'];

        }else{
            $data['extension_name'] = $this->extensionFolder;
            $data['extension_description'] = $this->extensionDescription;
            $data['extension_version'] = '' . $this->extensionVersion;
            $data['extension_author'] = '';
            $data['extension_link'] = '';
            $data['extension_status'] = '';
            $data['extension_date_added'] = '';

        }
        $data['extension_information']  = $this->language->get('column_name') . ': ' . $data['extension_name'] . "\n";
        $data['extension_information'] .= $this->language->get('column_version') . ': ' . $data['extension_version'] . "\n";
        $data['extension_information'] .= $this->language->get('entry_author') . ': ' . $data['extension_author'] . "\n";
        $data['extension_information'] .= $this->language->get('button_link') . ': ' . $data['extension_link'] . "\n";
        $data['extension_information'] .= $this->language->get('column_date_added') . ': ' . $data['extension_date_added'] . "";
        $data['extension_information'] = strip_tags($data['extension_information']);
        $this->document->setTitle($this->language->get('heading_title'));
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
        'text' => $this->language->get('text_home'),
        'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
        ];
        
        $data['breadcrumbs'][] = [
        'text' => $this->language->get('text_extension'),
        'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=' . $this->extensionType),
        ];
        
        $data['breadcrumbs'][] = [
        'text' => $this->language->get('heading_title'),
        'href' => $this->url->link($this->extensionRoute, 'user_token=' . $this->session->data['user_token']),
        ];
        $data['save'] = $this->url->link($this->extensionRoute . $this->method_separator . 'save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=' . $this->extensionType);
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view($this->extensionRoute, $data));

    }
    public function save(): void{
        $this->load->language($this->extensionRoute);
        if (!$this->user->hasPermission('modify', $this->extensionRoute)){
            $json['error']['warning'] = $this->language->get('error_permission');
            return;
        }
        if (!$this->user->hasPermission('modify', $this->extensionRoute)){
            $json['error']['warning'] = $this->language->get('error_permission');
        }
        $json = [];
        if ($this->extensionStore){
            if (!isset($this->request->post[$this->extensionType . '_' . $this->extensionCode . '_store'])){
                $json['error']['store'] = $this->language->get('error_store');
            }
        }
        if (!$json){
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting($this->extensionType . '_' . $this->extensionCode, $this->request->post);
            $this->load->model('localisation/language');
            if ($this->extensionLanguageUA){
                $language_info = $this->ensureUkrainianLanguage();
                if ($language_info) {
                    $language_info['status'] = (empty($this->request->post[$this->extensionType . '_' . $this->extensionCode . '_uk_ua_status']) ? '0' : '1');
                    $language_info['extension'] = $this->extensionFolder;
                    $this->model_localisation_language->editLanguage((int)$language_info['language_id'], $language_info);
                }
            }
            if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['options'])){
                $selected_options = $this->request->post['options'];
                $this->load->model($this->extensionRoute);

                foreach ($selected_options as $option){
                    switch ($option){
                        
                        case 'seo_url':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->addSeoUrl();
                        break;
                        
                        case 'currency':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->addCurrency();
                        break;
                        
                        case 'country_zones':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateCountyZones();
                        break;

                        case 'customer_groups':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateCustomerGroups();
                        break;
                        
                        case 'units_weights':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateUnitsWeights();
                        break;
                        
                        case 'info_pages':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateInfoPages();
                        break;
                        
                        case 'options':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateOptions();
                        break;
                        
                        case 'order_statuses':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateOrderStatuses();
                        break;
                        
                        case 'returns':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateReturns();
                        break;
                        
                        case 'availability_statuses':
                        $this->{'model_' . str_replace('/', '_', $this->extensionRoute)}->translateAvailabilityStatuses();
                        break;

                    }

                }

            }

            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    public function install(): void{
        $this->log->write('Install: ' . $this->extensionCode . ' (' . $this->extensionDescription . ')');

        // Права уже проверены в extension/language.install — silent return здесь
        // приводил к success без записи в таблицу language.

        $this->load->model('setting/setting');
        $this->load->model('localisation/language');

        // OpenCart 4.1: язык ДОЛЖЕН добавляться автоматически при Install в Languages.
        if ($this->extensionLanguageUA) {
            try {
                $language_info = $this->ensureUkrainianLanguage();
                $this->log->write('Ukrainian language ready, language_id=' . (int)($language_info['language_id'] ?? 0));
            } catch (\Throwable $e) {
                $this->log->write('ERROR adding uk-ua language: ' . $e->getMessage());
                throw $e;
            }
        }

        // Статус расширения в списке Languages
        $this->model_setting_setting->editSetting($this->extensionType . '_' . $this->extensionCode, [
            $this->extensionType . '_' . $this->extensionCode . '_status' => 1,
            $this->extensionType . '_' . $this->extensionCode . '_uk_ua_status' => 1,
            $this->extensionType . '_' . $this->extensionCode . '_store' => [0],
            $this->extensionType . '_' . $this->extensionCode . '_remove_all' => 0,
        ]);

        if ($this->extensionCopy){
            $extension_root = rtrim(DIR_EXTENSION, '/\\') . '/';
            $source = $extension_root . $this->extensionFolder . '/extension/opencart/';
            $destination = $extension_root . 'opencart/';
            if ((is_dir($source)) && (is_dir($destination))){
                $this->custom_copy($source, $destination);
            }else{
                $this->log->write('Warning: Translation source or destination folder is not present! source=' . $source . ' destination=' . $destination);
            }

        }
        $extension_startups = false;
        if ($extension_startups){
            
            $startups = array(
            array(
            'code'         => 'admin_' . $this->extensionCode . '',
            'description'  => 'Startup for ' . $this->extensionDescription . '',
            'action'       => $this->extensionRoute . $this->method_separator . 'startupAdmin  >>TBD<< ',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionStartup($startups);

        }
        $admin_startups = false;
        if ($admin_startups && $this->extensionAdminFolder){
            
            $startups = array(
            array(
            'code'         => 'admin_' . $this->extensionCode . '',
            'description'  => 'Startup for ' . $this->extensionDescription . '',
            'action'       => $this->dir_extension . '/' . $this->extensionFolder . '/' . $this->extensionAdminFolder . '/' . $this->extensionCode . $this->method_separator . 'startupAdmin  >>TBD<< ',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionStartup($startups);

        }
        $catalog_startups = false;
        if ($catalog_startups && $this->extensionCatalogFolder){
            $startups = array(
            array(
            'code'         => $this->extensionCode . '',
            'description'  => 'Startup for ' . $this->extensionDescription . '',
            'action'       => $this->dir_extension . '/' . $this->extensionFolder . '/' . $this->extensionCatalogFolder . '/' . $this->extensionCode . $this->method_separator . 'startupCatalog  >>TBD<< ',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionStartup($startups);

        }
        if ($this->extensionAdminMenu){
            
            $events = array(
            array(
            'code'         => 'admin_' . $this->extensionCode . '_menu',
            'description'  => 'Menu entry in Catalog for ' . $this->extensionDescription,
            'trigger'      => 'admin/view/common/column_left/before',
            'action'       => $this->extensionRoute . $this->method_separator . 'eventAdminViewCommonColumnLeftBefore',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionEvent($events);

        }
        $extension_events = false;
        if ($extension_events){
            
            $events = array(
            array(
            'code'         => 'admin_' . $this->extensionCode . '_' . $this->extensionAdminFolder . '',
            'description'  => 'Event for ' . $this->extensionDescription,
            'trigger'      => 'admin/ >>TBD<< ',
            'action'       => $this->extensionRoute . $this->method_separator . 'eventAdmin  >>TBD<< ',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionEvent($events);

        }
        $admin_events = false;
        if ($admin_events && $this->extensionAdminFolder){
            
            $events = array(
            array(
            'code'         => 'admin_' . $this->extensionCode . '',
            'description'  => 'Event for ' . $this->extensionDescription,
            'trigger'      => 'admin/ >>TBD<< ',
            'action'       => $this->dir_extension . '/' . $this->extensionFolder . '/' . $this->extensionAdminFolder . '/' . $this->extensionCode . $this->method_separator . 'eventAdmin >>TBD<< ',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionEvent($events);

        }
        $catalog_events = false;
        if ($catalog_events && $this->extensionCatalogFolder){
            
            $events = array(
            array(
            'code'         => $this->extensionCode . ' >>TBD<< ',
            'description'  => 'Event for ' . $this->extensionDescription . '',
            'trigger'      => ' >>TBD<< ',
            'action'       => $this->dir_extension . '/' . $this->extensionFolder . '/' . $this->extensionCatalogFolder . '/' . 'menu_home' . $this->method_separator . 'eventCatalog >> TBD<<',
            'status'       => true,
            'sort_order'   => 1
            )
            );
            $this->addExtensionEvent($events);

        }
        if ($this->extensionDbAddon){
            $this->load->model($this->extensionRoute);
            $this->{
                'model_' . str_replace('/', '_', $this->extensionRoute)
            }
            ->addExtensionTable();
        }
        if ($this->extensionAdminFolder){
            $this->addPermission($this->user->getGroupId(), 'access', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionAdminFolder . '/' . $this->extensionCode);
            $this->addPermission($this->user->getGroupId(), 'modify', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionAdminFolder . '/' . $this->extensionCode);

        }
        if ($this->extensionAdminFolder){
            $this->addPermission($this->user->getGroupId(), 'access', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionCatalogFolder . '/' . $this->extensionCode);
            $this->addPermission($this->user->getGroupId(), 'modify', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionCatalogFolder . '/' . $this->extensionCode);

        }

    }
    public function uninstall(): void{
        $this->log->write("Uninstall: " . $this->extensionCode . " (" . $this->extensionDescription . ")");    
        if (!$this->user->hasPermission('modify', $this->dir_extension . '/' . $this->extensionType)){
            $this->load->language($this->extensionRoute);
            $json['error']['warning'] = $this->language->get('error_permission');
            return;
        }
        $this->load->model('setting/setting');
        if (!empty($this->config->get($this->extensionType . '_' . $this->extensionCode . '_remove_all')) && (!$this->extensionRemoveAll)){
            $this->extensionRemoveAll = $this->config->get($this->extensionType . '_' . $this->extensionCode . '_remove_all');

        }
        $this->load->model('localisation/language');
        if ($this->extensionLanguageDefault){
            $language_info = $this->model_localisation_language->getLanguageByCode($this->extensionLanguageDefault);

        }else{
            $language_info = $this->model_localisation_language->getLanguageByCode('en-gb');

        }
        if (isset($this->request->cookie['language']) && isset(self::$language_infos[$this->request->cookie['language']]) && isset($language_info)){
            $this->config->set('config_language_id', $language_info['language_id']);
            $this->config->set('config_language_admin', $language_info['code']);
            $this->load->language('default');

        }else{
            $this->load->language('default');

        }
        if ($this->extensionLanguageUA){
            $language_info = $this->model_localisation_language->getLanguageByCode('uk-ua');
            if (($language_info) && ($this->extensionRemoveAll)){
                $this->model_localisation_language->deleteLanguage($language_info['language_id']);
                $this->load->model($this->extensionRoute);
                $this->{
                    'model_' . str_replace('/', '_', $this->extensionRoute)
                }
                ->deleteLanguageEntries($language_info['language_id']);
            }            elseif ($language_info){
                $language_info = $this->model_localisation_language->getLanguageByCode('uk-ua');
                $language_info['status'] = 0;
                $this->model_localisation_language->editLanguage($language_info['language_id'], $language_info);
            }

        }
        if ($this->extensionCode){
            $this->removeExtensionStartup('admin_' . $this->extensionCode . '');
            $this->removeExtensionStartup($this->extensionCode . '');
            $this->removeExtensionStartup($this->extensionCode . '');

        }
        if ($this->extensionCode){
            $this->removeExtensionEvent('admin_' . $this->extensionCode . '_menu');
            $this->removeExtensionEvent('admin_' . $this->extensionCode . '');
            $this->removeExtensionEvent($this->extensionCode  . '');
            $this->removeExtensionEvent($this->extensionCode  . '');

        }
        if ($this->extensionDbAddon){
            if ($this->extensionRemoveAll){
                $this->load->model($this->extensionRoute);
                $this->log->write("DB Table(s) removed");

            }else{
                $this->log->write("DB Table(s) not removed");

            }

        }
        if ($this->extensionRemoveAll){
            $this->model_setting_setting->deleteSetting($this->extensionType . '_' . $this->extensionCode, 0);
            $this->log->write("Configuration removed");
        }else{
            $this->log->write("Configuration not removed");

        }
        $this->removePermission($this->user->getGroupId(), 'access', $this->extensionRoute);
        $this->removePermission($this->user->getGroupId(), 'modify', $this->extensionRoute);
        if ($this->extensionAdminFolder){
            $this->removePermission($this->user->getGroupId(), 'access', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionAdminFolder . '/' . $this->extensionCode);
            $this->removePermission($this->user->getGroupId(), 'modify', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionAdminFolder . '/' . $this->extensionCode);

        }
        if ($this->extensionCatalogFolder){
            $this->removePermission($this->user->getGroupId(), 'access', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionCatalogFolder . '/' . $this->extensionCode);
            $this->removePermission($this->user->getGroupId(), 'modify', $this->dir_extension . '/' . $this->extensionFolder . '/'  . $this->extensionCatalogFolder . '/' . $this->extensionCode);

        }
        if (version_compare(VERSION, '4.0.0.0', '==')){
            $user_group_query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "user_group` 
						WHERE `user_group_id` = '" . (int)$user_group_id . "'
						");    
            if ($user_group_query -> num_rows){
                $data = json_decode($user_group_query->row['permission'], true);
                if (!empty($data[$type])){
                    $data[$type] = array_diff($data[$type], [$route]);
                    
                    $this->db->query("UPDATE `" . DB_PREFIX . "user_group`
                    SET `permission` = '" . $this->db->escape(json_encode($data)) . "'
                    WHERE `user_group_id` = '" . (int)$user_group_id . "'
                    ");

                }
            }

        }

    }
    public function eventAdminViewCommonColumnLeftBefore(string &$route, array &$data, string &$template): void{
        if (!$this->config->get($this->extensionType . '_' . $this->extensionCode . '_status')){
            if ($this->extensionDebug){
                if (!$this->user->hasPermission('access', $this->extensionRoute)){
                }

            }
            return;

        }
        if ($this->extensionAdminMenu){
            $this->load->language($this->extensionRoute);
            if ($this->extensionAdminFolder){
                $extension_admin = $this->extensionAdminFolder;

            }else{
                $extension_admin = $this->extensionType;

            }
            foreach ($data['menus'] as $key => $main_menu){
                if ($main_menu['id'] == 'menu-' . $this->extensionAdminMenu){
                    
                    $data['menus'][$key]['children'][] = [
                    'name'     => $this->language->get('heading_title'),
                    'href'     => $this->url->link($this->dir_extension . '/' . $this->extensionFolder . '/' . $extension_admin . '/' . $this->extensionCode, 'user_token=' . $this->session->data['user_token']),
                    'children' => []
                    ];
                    return;

                }

            }
            
            $menu_entry = [
            'name'     => $this->language->get('heading_title'),
            'href'     => $this->url->link($this->dir_extension . '/' . $this->extensionFolder . '/'  . $extension_admin . '/' . $this->extensionCode, 'user_token=' . $this->session->data['user_token']),
            'children' => []
            ];
            
            $data['menus'][] = [
            'id'       => 'menu-' . $this->extensionAdminMenu,
            'icon'     => 'fas fa-bar-chart-o',
            'name'     => $this->language->get('text_' . $this->extensionAdminMenu),
            'href'     => '',
            'children' => [$menu_entry]
            ];

        }

    }
    public function __construct(\Opencart\System\Engine\Registry $registry){
        parent::__construct($registry);
        $this->dir_extension = basename(DIR_EXTENSION);
        $this->method_separator = version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';

    }
    private function custom_copy($src, $dst) : void{
        $dir = opendir($src);
        if(!is_dir($dst)){
            mkdir($dst, 0755);

        }
        while( $file = readdir($dir) ){
            if (( $file != '.' ) && ( $file != '..' )){
                if ( is_dir($src . '/' . $file) ){
                    $this->custom_copy($src . '/' . $file, $dst . '/' . $file);

                }else{
                    copy($src . '/' . $file, $dst . '/' . $file);

                }

            }

        }
        closedir($dir);

    }
    protected function addPermission(int $user_group_id, string $type, string $route): void{
        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($user_group_id, $type, $route);
    }
    protected function removePermission(int $user_group_id, string $type, string $route): void{
        $this->load->model('user/user_group');
        $this->model_user_user_group->removePermission($user_group_id, $type, $route);
    }
    /**
     * Создать запись uk-ua в таблице language, если её ещё нет (OC 4.1: code + extension, без directory).
     *
     * @return array<string, mixed>
     */
    protected function ensureUkrainianLanguage(): array {
        $this->load->model('localisation/language');

        $language_info = $this->model_localisation_language->getLanguageByCode('uk-ua');

        if ($language_info) {
            if (($language_info['extension'] ?? '') !== $this->extensionFolder) {
                $language_info['extension'] = $this->extensionFolder;
                $this->model_localisation_language->editLanguage((int)$language_info['language_id'], $language_info);
            }

            return $language_info;
        }

        $language_data = [
            'name'       => 'Українська',
            'code'       => 'uk-ua',
            'locale'     => 'uk_UA.UTF-8,uk_UA,ukrainian',
            'extension'  => $this->extensionFolder,
            'status'     => 1,
            'sort_order' => 0
        ];

        $language_id = $this->model_localisation_language->addLanguage($language_data);
        $this->cache->delete('language');

        $language_info = $this->model_localisation_language->getLanguageByCode('uk-ua');

        if (!$language_info) {
            $language_info = $language_data + ['language_id' => $language_id];
        }

        return $language_info;
    }

    protected function addLanguage($language): void{
        $this->load->model('localisation/language');
        $this->model_localisation_language->addLanguage($language);
    }
    protected function editLanguage(int $language_id, array $language): void{
        $this->load->model('localisation/language');
        $this->model_localisation_language->editLanguage($language_id, $language);
    }
    protected function removeLanguage(array $language): void{
        $this->load->model('localisation/language');
        $this->model_localisation_language->deleteLanguage($language['language_id']);
    }
    protected function addExtensionStartup($startups): void{
        if (!empty($startups)){
            $this->load->model('setting/startup');
            if (version_compare(VERSION,'4.0.1.0','>=')){
                foreach ($startups as $startup){
                    
                    $startup_data = array(
                    'code'         => $startup['code'],
                    'description'  => $startup['description'],
                    'action'       => $startup['action'],
                    'status'       => $startup['status'],
                    'sort_order'   => $startup['sort_order']
                    );
                    $this->model_setting_startup->addStartup($startup_data);
                }

            }else{
                foreach ($startups as $startup){
                    $this->model_setting_startup->addStartup($startup['code'], $startup['description'], $startup['action']);
                }

            }

        }

    }
    protected function removeExtensionStartup($startups): void{
        $this->load->model('setting/startup');
        $this->model_setting_startup->deleteStartupByCode($startups);
    }
    protected function addExtensionEvent($events): void{
        if (!empty($events)){
            $this->load->model('setting/event');
            if (version_compare(VERSION,'4.0.1.0','>=')){
                foreach ($events as $event){
                    
                    $event_data = array(
                    'code'         => $event['code'],
                    'description'  => $event['description'],
                    'trigger'      => $event['trigger'],
                    'action'       => $event['action'],
                    'status'       => $event['status'],
                    'sort_order'   => $event['sort_order']
                    );
                    $this->model_setting_event->addEvent($event_data);
                }

            }else{
                foreach ($events as $event){
                    $this->model_setting_event->addEvent($event['code'], $event['description'], $event['trigger'], $event['action']);
                }

            }

        }

    }
    protected function removeExtensionEvent($events): void{
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode($events);
    }
}