<?php
/**
 * Enviamo Connector for PrestaShop
 *
 * @author    Enviamo <soporte@enviamo.es>
 * @copyright 2025 Enviamo
 * @license   MIT License
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/EnviamoAPI.php';
require_once dirname(__FILE__) . '/classes/EnviamoWebhook.php';
require_once dirname(__FILE__) . '/classes/EnviamoLogger.php';

class Enviamo_Connector extends Module
{
    const MODULE_NAME = 'enviamo_connector';
    const VERSION = '1.0.0';
    const ENVIAMO_API_URL = 'https://backend.parafarmaciaintegrativa.es';
    const MIN_PS_VERSION = '1.7.6.0';
    const MAX_PS_VERSION = '9.99.99';

    public function __construct()
    {
        $this->name = self::MODULE_NAME;
        $this->tab = 'shipping_logistics';
        $this->version = self::VERSION;
        $this->author = 'Enviamo';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => self::MIN_PS_VERSION,
            'max' => self::MAX_PS_VERSION
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Enviamo Connector');
        $this->description = $this->l('Sincroniza automáticamente tus pedidos con Enviamo y genera etiquetas de envío.');
        $this->confirmUninstall = $this->l('¿Estás seguro de que quieres desinstalar Enviamo Connector? Perderás la conexión con Enviamo.');
    }

    /**
     * Instalación del módulo
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->registerHook('actionObjectOrderAddAfter') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayHeader') &&
            $this->createTables();
    }

    /**
     * Desinstalación del módulo
     */
    public function uninstall()
    {
        return Configuration::deleteByName('ENVIAMO_STORE_ID') &&
            Configuration::deleteByName('ENVIAMO_WEBHOOK_SECRET') &&
            Configuration::deleteByName('ENVIAMO_CONNECTED') &&
            Configuration::deleteByName('ENVIAMO_LAST_SYNC') &&
            $this->deleteTables() &&
            parent::uninstall();
    }

    /**
     * Crear tablas necesarias
     */
    private function createTables()
    {
        $sql = [];

        // Tabla de logs
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'enviamo_logs` (
            `id_log` int(11) NOT NULL AUTO_INCREMENT,
            `type` varchar(50) NOT NULL,
            `message` text NOT NULL,
            `context` text,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id_log`),
            KEY `type` (`type`),
            KEY `created_at` (`created_at`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Tabla de webhooks recibidos
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'enviamo_webhooks` (
            `id_webhook` int(11) NOT NULL AUTO_INCREMENT,
            `event_type` varchar(100) NOT NULL,
            `payload` text NOT NULL,
            `processed` tinyint(1) DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id_webhook`),
            KEY `event_type` (`event_type`),
            KEY `processed` (`processed`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Eliminar tablas
     */
    private function deleteTables()
    {
        $sql = [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'enviamo_logs`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'enviamo_webhooks`'
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Página de configuración
     */
    public function getContent()
    {
        $output = '';

        // Procesar formulario de desconexión
        if (Tools::isSubmit('disconnect_enviamo')) {
            if ($this->disconnectFromEnviamo()) {
                $output .= $this->displayConfirmation($this->l('Desconectado de Enviamo correctamente.'));
            } else {
                $output .= $this->displayError($this->l('Error al desconectar de Enviamo.'));
            }
        }

        // Procesar conexión manual
        if (Tools::isSubmit('submit_manual_connection')) {
            $api_key = Tools::getValue('enviamo_api_key');
            if ($this->connectManually($api_key)) {
                $output .= $this->displayConfirmation($this->l('Conectado con Enviamo correctamente.'));
            } else {
                $output .= $this->displayError($this->l('Error al conectar con Enviamo. Verifica tu API Key.'));
            }
        }

        // Mostrar página de configuración
        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'is_connected' => Configuration::get('ENVIAMO_CONNECTED'),
            'store_id' => Configuration::get('ENVIAMO_STORE_ID'),
            'last_sync' => Configuration::get('ENVIAMO_LAST_SYNC'),
            'enviamo_dashboard_url' => self::ENVIAMO_API_URL . '/dashboard/stores/' . Configuration::get('ENVIAMO_STORE_ID') . '/settings',
            'oauth_url' => $this->getOAuthURL(),
            'recent_logs' => $this->getRecentLogs()
        ]);

        return $output . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Obtener URL de OAuth
     */
    private function getOAuthURL()
    {
        $shop_url = Tools::getShopDomainSsl(true, true);
        $params = [
            'shop_url' => $shop_url,
            'shop_name' => Configuration::get('PS_SHOP_NAME'),
            'prestashop_version' => _PS_VERSION_,
            'module_version' => self::VERSION,
            'return_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&callback=1'
        ];

        return self::ENVIAMO_API_URL . '/oauth/prestashop/authorize?' . http_build_query($params);
    }

    /**
     * Procesar callback de OAuth
     */
    public function processOAuthCallback()
    {
        if (Tools::getValue('status') === 'success') {
            $token = Tools::getValue('token');
            $store_id = Tools::getValue('store_id');

            if ($token && $store_id) {
                // Intercambiar token por webhook_secret
                $api = new EnviamoAPI();
                $result = $api->exchangeToken($token);

                if ($result && isset($result['webhook_secret'])) {
                    Configuration::updateValue('ENVIAMO_STORE_ID', $store_id);
                    Configuration::updateValue('ENVIAMO_WEBHOOK_SECRET', $result['webhook_secret']);
                    Configuration::updateValue('ENVIAMO_CONNECTED', 1);
                    Configuration::updateValue('ENVIAMO_LAST_SYNC', date('Y-m-d H:i:s'));

                    EnviamoLogger::log('success', 'OAuth connection successful', ['store_id' => $store_id]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Conectar manualmente con API Key
     */
    private function connectManually($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        try {
            $api = new EnviamoAPI($api_key);
            $shop_url = Tools::getShopDomainSsl(true, true);

            $result = $api->connect([
                'shop_url' => $shop_url,
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'prestashop_version' => _PS_VERSION_,
                'module_version' => self::VERSION,
                'webhook_url' => $this->context->link->getModuleLink($this->name, 'webhook')
            ]);

            if ($result && $result['success']) {
                Configuration::updateValue('ENVIAMO_STORE_ID', $result['store_id']);
                Configuration::updateValue('ENVIAMO_WEBHOOK_SECRET', $result['webhook_secret']);
                Configuration::updateValue('ENVIAMO_CONNECTED', 1);
                Configuration::updateValue('ENVIAMO_LAST_SYNC', date('Y-m-d H:i:s'));

                EnviamoLogger::log('success', 'Manual connection successful', ['store_id' => $result['store_id']]);
                return true;
            }
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Manual connection failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Desconectar de Enviamo
     */
    private function disconnectFromEnviamo()
    {
        Configuration::deleteByName('ENVIAMO_STORE_ID');
        Configuration::deleteByName('ENVIAMO_WEBHOOK_SECRET');
        Configuration::deleteByName('ENVIAMO_CONNECTED');
        Configuration::deleteByName('ENVIAMO_LAST_SYNC');

        EnviamoLogger::log('info', 'Disconnected from Enviamo');
        return true;
    }

    /**
     * Hook: Pedido creado
     */
    public function hookActionObjectOrderAddAfter($params)
    {
        if (!Configuration::get('ENVIAMO_CONNECTED')) {
            return;
        }

        $order = $params['object'];

        try {
            $webhook = new EnviamoWebhook();
            $webhook->sendOrderCreated($order);

            EnviamoLogger::log('success', 'Order webhook sent', ['order_id' => $order->id]);
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Failed to send order webhook', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Hook: Estado de pedido actualizado
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if (!Configuration::get('ENVIAMO_CONNECTED')) {
            return;
        }

        $order_id = $params['id_order'];
        $new_status = $params['newOrderStatus'];

        try {
            $webhook = new EnviamoWebhook();
            $webhook->sendOrderStatusChanged($order_id, $new_status);

            EnviamoLogger::log('success', 'Order status webhook sent', [
                'order_id' => $order_id,
                'new_status' => $new_status->id
            ]);
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Failed to send status webhook', [
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Hook: Producto actualizado
     */
    public function hookActionProductUpdate($params)
    {
        // TODO: Implementar sincronización de productos si está habilitada
    }

    /**
     * Hook: Display en página de pedido (admin)
     */
    public function hookDisplayAdminOrder($params)
    {
        if (!Configuration::get('ENVIAMO_CONNECTED')) {
            return '';
        }

        $order_id = $params['id_order'];

        $this->context->smarty->assign([
            'order_id' => $order_id,
            'enviamo_dashboard_url' => self::ENVIAMO_API_URL . '/dashboard/orders/' . $order_id
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayAdminOrder.tpl');
    }

    /**
     * Obtener logs recientes
     */
    private function getRecentLogs($limit = 10)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'enviamo_logs`
                ORDER BY `created_at` DESC
                LIMIT ' . (int)$limit;

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Verificar actualizaciones disponibles
     */
    public function checkForUpdates()
    {
        try {
            $api = new EnviamoAPI();
            $result = $api->checkUpdates(self::VERSION);

            if ($result && $result['update_available']) {
                return [
                    'available' => true,
                    'version' => $result['latest_version'],
                    'download_url' => $result['download_url'],
                    'changelog' => $result['changelog']
                ];
            }
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Failed to check updates', ['error' => $e->getMessage()]);
        }

        return ['available' => false];
    }
}
