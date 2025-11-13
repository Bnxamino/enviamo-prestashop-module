<?php
/**
 * Enviamo Webhook Manager
 *
 * Gestiona el envío de webhooks salientes a Enviamo
 * y el procesamiento de webhooks entrantes desde Enviamo
 *
 * @author    Enviamo <soporte@enviamo.es>
 * @copyright 2025 Enviamo
 * @license   MIT License
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class EnviamoWebhook
{
    private $api;

    public function __construct()
    {
        $this->api = new EnviamoAPI();
    }

    /**
     * Enviar webhook de pedido creado
     *
     * @param Order $order Objeto Order de PrestaShop
     * @return bool True si se envió correctamente
     */
    public function sendOrderCreated($order)
    {
        try {
            $order_data = $this->formatOrderData($order);
            $order_data['event'] = 'order.created';

            $result = $this->api->sendOrderWebhook($order_data);

            if ($result && isset($result['success']) && $result['success']) {
                EnviamoLogger::log('success', 'Order created webhook sent', [
                    'order_id' => $order->id,
                    'reference' => $order->reference
                ]);
                return true;
            }

            throw new Exception($this->api->getLastError() ?: 'Unknown error');
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Failed to send order created webhook', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enviar webhook de cambio de estado de pedido
     *
     * @param int $order_id ID del pedido
     * @param OrderState $new_status Nuevo estado
     * @return bool True si se envió correctamente
     */
    public function sendOrderStatusChanged($order_id, $new_status)
    {
        try {
            $order = new Order($order_id);

            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Order not found');
            }

            $order_data = $this->formatOrderData($order);
            $order_data['event'] = 'order.status_changed';
            $order_data['new_status'] = [
                'id' => $new_status->id,
                'name' => $new_status->name,
                'color' => $new_status->color
            ];

            $result = $this->api->sendOrderWebhook($order_data);

            if ($result && isset($result['success']) && $result['success']) {
                EnviamoLogger::log('success', 'Order status changed webhook sent', [
                    'order_id' => $order->id,
                    'new_status' => $new_status->id
                ]);
                return true;
            }

            throw new Exception($this->api->getLastError() ?: 'Unknown error');
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Failed to send status changed webhook', [
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Formatear datos del pedido para enviar a Enviamo
     *
     * @param Order $order Objeto Order
     * @return array Datos formateados
     */
    private function formatOrderData($order)
    {
        $customer = new Customer($order->id_customer);
        $address_delivery = new Address($order->id_address_delivery);
        $address_invoice = new Address($order->id_address_invoice);
        $currency = new Currency($order->id_currency);
        $carrier = new Carrier($order->id_carrier);

        // Obtener productos del pedido
        $products = [];
        $order_products = $order->getProducts();

        foreach ($order_products as $product) {
            $products[] = [
                'id' => $product['product_id'],
                'reference' => $product['product_reference'],
                'name' => $product['product_name'],
                'quantity' => (int)$product['product_quantity'],
                'price' => (float)$product['product_price'],
                'price_with_tax' => (float)$product['product_price_wt'],
                'weight' => (float)$product['product_weight'],
                'ean13' => $product['product_ean13'],
                'upc' => $product['product_upc']
            ];
        }

        return [
            'order_id' => (int)$order->id,
            'order_reference' => $order->reference,
            'created_at' => $order->date_add,
            'updated_at' => $order->date_upd,

            // Customer
            'customer' => [
                'id' => (int)$customer->id,
                'email' => $customer->email,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname
            ],

            // Shipping address
            'shipping_address' => [
                'firstname' => $address_delivery->firstname,
                'lastname' => $address_delivery->lastname,
                'company' => $address_delivery->company,
                'address1' => $address_delivery->address1,
                'address2' => $address_delivery->address2,
                'postcode' => $address_delivery->postcode,
                'city' => $address_delivery->city,
                'country' => Country::getNameById(
                    Context::getContext()->language->id,
                    $address_delivery->id_country
                ),
                'country_iso' => Country::getIsoById($address_delivery->id_country),
                'phone' => $address_delivery->phone,
                'phone_mobile' => $address_delivery->phone_mobile
            ],

            // Billing address
            'billing_address' => [
                'firstname' => $address_invoice->firstname,
                'lastname' => $address_invoice->lastname,
                'company' => $address_invoice->company,
                'address1' => $address_invoice->address1,
                'address2' => $address_invoice->address2,
                'postcode' => $address_invoice->postcode,
                'city' => $address_invoice->city,
                'country' => Country::getNameById(
                    Context::getContext()->language->id,
                    $address_invoice->id_country
                ),
                'country_iso' => Country::getIsoById($address_invoice->id_country),
                'phone' => $address_invoice->phone
            ],

            // Products
            'products' => $products,

            // Totals
            'total_products' => (float)$order->total_products,
            'total_products_wt' => (float)$order->total_products_wt,
            'total_shipping' => (float)$order->total_shipping,
            'total_shipping_tax_incl' => (float)$order->total_shipping_tax_incl,
            'total_paid' => (float)$order->total_paid,
            'total_paid_tax_incl' => (float)$order->total_paid_tax_incl,
            'total_paid_tax_excl' => (float)$order->total_paid_tax_excl,
            'total_weight' => (float)$order->getTotalWeight(),

            // Currency
            'currency' => [
                'iso_code' => $currency->iso_code,
                'sign' => $currency->sign
            ],

            // Carrier
            'carrier' => [
                'id' => (int)$carrier->id,
                'name' => $carrier->name,
                'delay' => $carrier->delay
            ],

            // Status
            'current_state' => (int)$order->getCurrentState(),
            'payment_method' => $order->payment,
            'module' => $order->module,

            // Shop info
            'shop_id' => (int)$order->id_shop,
            'shop_name' => Shop::getShop($order->id_shop)['name']
        ];
    }

    /**
     * Procesar webhook entrante desde Enviamo
     *
     * @param array $payload Datos del webhook
     * @return array Resultado del procesamiento
     */
    public function processIncomingWebhook($payload)
    {
        try {
            // Validar estructura del payload
            if (!isset($payload['event']) || !isset($payload['data'])) {
                throw new Exception('Invalid webhook payload structure');
            }

            $event = $payload['event'];
            $data = $payload['data'];

            // Log del webhook recibido
            EnviamoLogger::log('info', 'Incoming webhook received', [
                'event' => $event,
                'data_keys' => array_keys($data)
            ]);

            // Guardar webhook en BD para auditoría
            $this->saveWebhookToDB($event, $payload);

            // Procesar según tipo de evento
            switch ($event) {
                case 'shipment.label_created':
                    return $this->handleLabelCreated($data);

                case 'shipment.tracking_updated':
                    return $this->handleTrackingUpdated($data);

                case 'shipment.delivered':
                    return $this->handleShipmentDelivered($data);

                case 'shipment.error':
                    return $this->handleShipmentError($data);

                default:
                    EnviamoLogger::log('warning', 'Unknown webhook event', ['event' => $event]);
                    return ['success' => false, 'error' => 'Unknown event type'];
            }
        } catch (Exception $e) {
            EnviamoLogger::log('error', 'Error processing incoming webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Manejar evento: etiqueta creada
     */
    private function handleLabelCreated($data)
    {
        $order_reference = $data['order_reference'];
        $tracking_number = $data['tracking_number'];
        $carrier_name = $data['carrier'];
        $label_url = $data['label_url'];

        // Buscar pedido por referencia
        $order = Order::getByReference($order_reference)->getFirst();

        if (!Validate::isLoadedObject($order)) {
            throw new Exception('Order not found: ' . $order_reference);
        }

        // Añadir número de seguimiento
        $order->setWsShippingNumber($tracking_number);
        $order->update();

        // Cambiar estado a "Enviado" si está configurado
        $shipped_state_id = Configuration::get('PS_OS_SHIPPING');
        if ($shipped_state_id && $order->getCurrentState() != $shipped_state_id) {
            $order->setCurrentState($shipped_state_id);
        }

        // Enviar email al cliente con tracking
        $this->sendTrackingEmail($order, $tracking_number, $label_url);

        EnviamoLogger::log('success', 'Label created processed', [
            'order_id' => $order->id,
            'tracking_number' => $tracking_number
        ]);

        return ['success' => true, 'order_id' => $order->id];
    }

    /**
     * Manejar evento: tracking actualizado
     */
    private function handleTrackingUpdated($data)
    {
        $order_reference = $data['order_reference'];
        $tracking_info = $data['tracking_info'];

        $order = Order::getByReference($order_reference)->getFirst();

        if (!Validate::isLoadedObject($order)) {
            throw new Exception('Order not found: ' . $order_reference);
        }

        EnviamoLogger::log('success', 'Tracking updated processed', [
            'order_id' => $order->id,
            'tracking_status' => $tracking_info['status']
        ]);

        return ['success' => true, 'order_id' => $order->id];
    }

    /**
     * Manejar evento: envío entregado
     */
    private function handleShipmentDelivered($data)
    {
        $order_reference = $data['order_reference'];

        $order = Order::getByReference($order_reference)->getFirst();

        if (!Validate::isLoadedObject($order)) {
            throw new Exception('Order not found: ' . $order_reference);
        }

        // Cambiar a estado "Entregado"
        $delivered_state_id = Configuration::get('PS_OS_DELIVERED');
        if ($delivered_state_id) {
            $order->setCurrentState($delivered_state_id);
        }

        EnviamoLogger::log('success', 'Shipment delivered processed', [
            'order_id' => $order->id
        ]);

        return ['success' => true, 'order_id' => $order->id];
    }

    /**
     * Manejar evento: error en envío
     */
    private function handleShipmentError($data)
    {
        $order_reference = $data['order_reference'];
        $error_message = $data['error'];

        $order = Order::getByReference($order_reference)->getFirst();

        if (!Validate::isLoadedObject($order)) {
            throw new Exception('Order not found: ' . $order_reference);
        }

        // Cambiar a estado "Error"
        $error_state_id = Configuration::get('PS_OS_ERROR');
        if ($error_state_id) {
            $order->setCurrentState($error_state_id);
        }

        EnviamoLogger::log('error', 'Shipment error received', [
            'order_id' => $order->id,
            'error' => $error_message
        ]);

        return ['success' => true, 'order_id' => $order->id];
    }

    /**
     * Guardar webhook en base de datos
     */
    private function saveWebhookToDB($event_type, $payload)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'enviamo_webhooks`
                (`event_type`, `payload`, `processed`, `created_at`)
                VALUES (
                    "' . pSQL($event_type) . '",
                    "' . pSQL(json_encode($payload)) . '",
                    1,
                    NOW()
                )';

        Db::getInstance()->execute($sql);
    }

    /**
     * Enviar email con tracking al cliente
     */
    private function sendTrackingEmail($order, $tracking_number, $label_url)
    {
        // TODO: Implementar envío de email personalizado
        // Por ahora, PrestaShop envía el email automáticamente al cambiar de estado
    }
}
