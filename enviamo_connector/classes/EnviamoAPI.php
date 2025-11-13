<?php
/**
 * Enviamo API Client
 *
 * Cliente HTTP seguro para comunicación con Enviamo Backend
 * Implementa retry logic, rate limiting y validación de respuestas
 *
 * @author    Enviamo <soporte@enviamo.es>
 * @copyright 2025 Enviamo
 * @license   MIT License
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class EnviamoAPI
{
    const API_URL = 'https://backend.parafarmaciaintegrativa.es';
    const TIMEOUT = 30;
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 1000; // milliseconds

    private $api_key;
    private $last_error;

    /**
     * Constructor
     *
     * @param string|null $api_key API Key opcional (se puede obtener de Configuration)
     */
    public function __construct($api_key = null)
    {
        $this->api_key = $api_key ?: Configuration::get('ENVIAMO_API_KEY');
    }

    /**
     * Conectar tienda manualmente con API Key
     *
     * @param array $data Datos de la tienda
     * @return array|false Resultado de la conexión
     */
    public function connect($data)
    {
        if (empty($this->api_key)) {
            throw new Exception('API Key is required');
        }

        return $this->request(
            'POST',
            '/api/v1/marketplaces/prestashop/connect',
            $data,
            [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => $this->getUserAgent()
            ]
        );
    }

    /**
     * Intercambiar token temporal por webhook_secret
     *
     * @param string $token Token temporal de OAuth
     * @return array|false Datos de la conexión
     */
    public function exchangeToken($token)
    {
        return $this->request(
            'POST',
            '/oauth/prestashop/exchange',
            ['token' => $token],
            [
                'Content-Type' => 'application/json',
                'User-Agent' => $this->getUserAgent()
            ]
        );
    }

    /**
     * Enviar webhook de pedido creado
     *
     * @param array $order_data Datos del pedido
     * @return array|false Respuesta del servidor
     */
    public function sendOrderWebhook($order_data)
    {
        $store_id = Configuration::get('ENVIAMO_STORE_ID');
        $webhook_secret = Configuration::get('ENVIAMO_WEBHOOK_SECRET');

        if (!$store_id || !$webhook_secret) {
            throw new Exception('Store not connected to Enviamo');
        }

        $body = json_encode($order_data);
        $signature = $this->generateSignature($body, $webhook_secret);

        return $this->request(
            'POST',
            '/api/v1/webhooks/prestashop/' . $store_id,
            $order_data,
            [
                'Content-Type' => 'application/json',
                'X-Enviamo-Store-ID' => $store_id,
                'X-Enviamo-Signature' => $signature,
                'User-Agent' => $this->getUserAgent()
            ]
        );
    }

    /**
     * Verificar actualizaciones disponibles
     *
     * @param string $current_version Versión actual del módulo
     * @return array|false Información de actualización
     */
    public function checkUpdates($current_version)
    {
        return $this->request(
            'GET',
            '/api/v1/modules/prestashop/latest',
            ['current_version' => $current_version],
            [
                'User-Agent' => $this->getUserAgent()
            ]
        );
    }

    /**
     * Realizar petición HTTP con retry logic
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Datos a enviar
     * @param array $headers Headers adicionales
     * @return array|false Respuesta decodificada o false en error
     */
    private function request($method, $endpoint, $data = [], $headers = [])
    {
        $url = self::API_URL . $endpoint;
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                $response = $this->executeRequest($method, $url, $data, $headers);

                if ($response !== false) {
                    return $response;
                }

                // Si falla, esperar antes de reintentar
                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY * 1000 * $attempt); // Backoff exponencial
                }
            } catch (Exception $e) {
                EnviamoLogger::log('error', 'API request failed', [
                    'attempt' => $attempt,
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= self::MAX_RETRIES) {
                    $this->last_error = $e->getMessage();
                    return false;
                }

                usleep(self::RETRY_DELAY * 1000 * $attempt);
            }
        }

        return false;
    }

    /**
     * Ejecutar petición HTTP
     *
     * @param string $method HTTP method
     * @param string $url URL completa
     * @param array $data Datos
     * @param array $headers Headers
     * @return array|false Respuesta
     */
    private function executeRequest($method, $url, $data, $headers)
    {
        // Usar cURL para mejor control y seguridad
        $ch = curl_init();

        // Configurar URL y método
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Headers
        $header_array = [];
        foreach ($headers as $key => $value) {
            $header_array[] = $key . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);

        // Datos
        if ($method === 'GET' && !empty($data)) {
            $query = http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
        } elseif (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        // Configuración de seguridad
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Seguridad: no seguir redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);

        // Headers de respuesta
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Ejecutar
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        // Verificar errores de cURL
        if ($response === false) {
            throw new Exception('cURL Error: ' . $curl_error);
        }

        // Separar headers y body
        $response_headers = substr($response, 0, $header_size);
        $response_body = substr($response, $header_size);

        // Verificar código HTTP
        if ($http_code >= 500) {
            throw new Exception('Server Error: ' . $http_code);
        }

        if ($http_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'Unknown error';
            throw new Exception('API Error: ' . $error_message . ' (HTTP ' . $http_code . ')');
        }

        // Decodificar respuesta JSON
        $decoded = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        // Validar estructura de respuesta
        if (!is_array($decoded)) {
            throw new Exception('Invalid response structure');
        }

        return $decoded;
    }

    /**
     * Generar firma HMAC-SHA256 para webhook
     *
     * @param string $payload Payload del webhook
     * @param string $secret Secret para firmar
     * @return string Firma hexadecimal
     */
    private function generateSignature($payload, $secret)
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Obtener User-Agent para identificar el módulo
     *
     * @return string User-Agent string
     */
    private function getUserAgent()
    {
        return sprintf(
            'EnviamoConnector/%s (PrestaShop/%s; PHP/%s)',
            Enviamo_Connector::VERSION,
            _PS_VERSION_,
            PHP_VERSION
        );
    }

    /**
     * Obtener último error
     *
     * @return string|null Mensaje de error
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * Validar webhook entrante desde Enviamo
     *
     * @param string $payload Payload del webhook
     * @param string $signature Firma recibida
     * @return bool True si es válido
     */
    public static function validateWebhookSignature($payload, $signature)
    {
        $secret = Configuration::get('ENVIAMO_WEBHOOK_SECRET');

        if (!$secret) {
            EnviamoLogger::log('error', 'Webhook secret not configured');
            return false;
        }

        $expected_signature = hash_hmac('sha256', $payload, $secret);

        // Comparación timing-safe para prevenir timing attacks
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Test de conexión con Enviamo
     *
     * @return bool True si la conexión es exitosa
     */
    public function testConnection()
    {
        try {
            $result = $this->request(
                'GET',
                '/api/v1/health',
                [],
                ['User-Agent' => $this->getUserAgent()]
            );

            return isset($result['status']) && $result['status'] === 'ok';
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }
}
