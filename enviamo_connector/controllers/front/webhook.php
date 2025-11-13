<?php
/**
 * Webhook Controller - Frontend
 *
 * Recibe webhooks entrantes desde Enviamo
 * Endpoint: /module/enviamo_connector/webhook
 *
 * @author    Enviamo <soporte@enviamo.es>
 * @copyright 2025 Enviamo
 * @license   MIT License
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/EnviamoAPI.php';
require_once dirname(__FILE__) . '/../../classes/EnviamoWebhook.php';
require_once dirname(__FILE__) . '/../../classes/EnviamoLogger.php';

class Enviamo_ConnectorWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true; // HTTPS obligatorio
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;

    /**
     * Procesar webhook entrante
     */
    public function postProcess()
    {
        // Verificar que la tienda esté conectada
        if (!Configuration::get('ENVIAMO_CONNECTED')) {
            $this->sendResponse(false, 'Store not connected to Enviamo', 401);
            return;
        }

        // Obtener payload del request
        $payload = file_get_contents('php://input');

        if (empty($payload)) {
            EnviamoLogger::error('Empty webhook payload received');
            $this->sendResponse(false, 'Empty payload', 400);
            return;
        }

        // Obtener firma del header
        $signature = isset($_SERVER['HTTP_X_ENVIAMO_SIGNATURE'])
            ? $_SERVER['HTTP_X_ENVIAMO_SIGNATURE']
            : null;

        if (!$signature) {
            EnviamoLogger::error('Webhook signature missing');
            $this->sendResponse(false, 'Signature missing', 401);
            return;
        }

        // Validar firma
        if (!EnviamoAPI::validateWebhookSignature($payload, $signature)) {
            EnviamoLogger::error('Invalid webhook signature', [
                'received_signature' => substr($signature, 0, 10) . '...',
                'payload_length' => strlen($payload)
            ]);
            $this->sendResponse(false, 'Invalid signature', 401);
            return;
        }

        // Decodificar payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            EnviamoLogger::error('Invalid JSON in webhook payload', [
                'json_error' => json_last_error_msg()
            ]);
            $this->sendResponse(false, 'Invalid JSON', 400);
            return;
        }

        // Validar estructura básica
        if (!isset($data['event'])) {
            EnviamoLogger::error('Missing event field in webhook');
            $this->sendResponse(false, 'Missing event field', 400);
            return;
        }

        // Log del webhook recibido
        EnviamoLogger::info('Webhook received', [
            'event' => $data['event'],
            'has_data' => isset($data['data'])
        ]);

        // Procesar webhook
        try {
            $webhook_handler = new EnviamoWebhook();
            $result = $webhook_handler->processIncomingWebhook($data);

            if ($result['success']) {
                $this->sendResponse(true, 'Webhook processed successfully', 200);
            } else {
                $error_message = isset($result['error']) ? $result['error'] : 'Unknown error';
                $this->sendResponse(false, $error_message, 400);
            }
        } catch (Exception $e) {
            EnviamoLogger::error('Exception processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendResponse(false, 'Internal server error', 500);
        }
    }

    /**
     * Enviar respuesta JSON
     *
     * @param bool $success Éxito o error
     * @param string $message Mensaje
     * @param int $http_code Código HTTP
     */
    private function sendResponse($success, $message, $http_code = 200)
    {
        http_response_code($http_code);
        header('Content-Type: application/json');

        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        echo json_encode($response);
        exit;
    }

    /**
     * Bloquear GET requests (solo POST)
     */
    public function initContent()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            EnviamoLogger::warning('Non-POST request to webhook endpoint', [
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->sendResponse(false, 'Method not allowed. Use POST.', 405);
        }
    }
}
