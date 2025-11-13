<?php
/**
 * Enviamo Logger
 *
 * Sistema de logging con cumplimiento GDPR
 * Registra eventos, errores y actividad del módulo
 *
 * @author    Enviamo <soporte@enviamo.es>
 * @copyright 2025 Enviamo
 * @license   MIT License
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class EnviamoLogger
{
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_SUCCESS = 'success';
    const LEVEL_DEBUG = 'debug';

    const MAX_LOGS = 10000; // Máximo de logs en BD
    const MAX_LOG_AGE_DAYS = 90; // Retención de logs (GDPR)

    /**
     * Registrar evento
     *
     * @param string $level Nivel del log (error, warning, info, success, debug)
     * @param string $message Mensaje descriptivo
     * @param array $context Contexto adicional
     * @return bool True si se guardó correctamente
     */
    public static function log($level, $message, $context = [])
    {
        try {
            // Validar nivel
            if (!self::isValidLevel($level)) {
                $level = self::LEVEL_INFO;
            }

            // Sanitizar datos sensibles del contexto (GDPR)
            $context = self::sanitizeContext($context);

            // Insertar en base de datos
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'enviamo_logs`
                    (`type`, `message`, `context`, `created_at`)
                    VALUES (
                        "' . pSQL($level) . '",
                        "' . pSQL($message) . '",
                        "' . pSQL(json_encode($context)) . '",
                        NOW()
                    )';

            $result = Db::getInstance()->execute($sql);

            // Limpiar logs antiguos si es necesario
            self::cleanupOldLogs();

            // Si es error crítico, también guardar en log de PrestaShop
            if ($level === self::LEVEL_ERROR) {
                PrestaShopLogger::addLog(
                    '[Enviamo] ' . $message,
                    3, // ERROR
                    null,
                    'Enviamo_Connector',
                    null,
                    true
                );
            }

            return $result;
        } catch (Exception $e) {
            // Fallback: guardar en archivo si la BD falla
            return self::logToFile($level, $message, $context, $e->getMessage());
        }
    }

    /**
     * Log de error
     */
    public static function error($message, $context = [])
    {
        return self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log de advertencia
     */
    public static function warning($message, $context = [])
    {
        return self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log informativo
     */
    public static function info($message, $context = [])
    {
        return self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log de éxito
     */
    public static function success($message, $context = [])
    {
        return self::log(self::LEVEL_SUCCESS, $message, $context);
    }

    /**
     * Log de debug (solo en modo desarrollo)
     */
    public static function debug($message, $context = [])
    {
        if (_PS_MODE_DEV_) {
            return self::log(self::LEVEL_DEBUG, $message, $context);
        }
        return true;
    }

    /**
     * Obtener logs recientes
     *
     * @param int $limit Límite de logs
     * @param string|null $level Filtrar por nivel
     * @param int|null $days Logs de los últimos N días
     * @return array Array de logs
     */
    public static function getRecentLogs($limit = 50, $level = null, $days = null)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'enviamo_logs` WHERE 1=1';

        if ($level && self::isValidLevel($level)) {
            $sql .= ' AND `type` = "' . pSQL($level) . '"';
        }

        if ($days) {
            $sql .= ' AND `created_at` >= DATE_SUB(NOW(), INTERVAL ' . (int)$days . ' DAY)';
        }

        $sql .= ' ORDER BY `created_at` DESC LIMIT ' . (int)$limit;

        $logs = Db::getInstance()->executeS($sql);

        // Decodificar contexto JSON
        foreach ($logs as &$log) {
            $log['context'] = json_decode($log['context'], true);
        }

        return $logs ?: [];
    }

    /**
     * Contar logs por nivel
     *
     * @param int|null $days Logs de los últimos N días
     * @return array Conteo por nivel
     */
    public static function countLogsByLevel($days = null)
    {
        $sql = 'SELECT `type`, COUNT(*) as `count`
                FROM `' . _DB_PREFIX_ . 'enviamo_logs`
                WHERE 1=1';

        if ($days) {
            $sql .= ' AND `created_at` >= DATE_SUB(NOW(), INTERVAL ' . (int)$days . ' DAY)';
        }

        $sql .= ' GROUP BY `type`';

        $results = Db::getInstance()->executeS($sql);

        $counts = [
            self::LEVEL_ERROR => 0,
            self::LEVEL_WARNING => 0,
            self::LEVEL_INFO => 0,
            self::LEVEL_SUCCESS => 0,
            self::LEVEL_DEBUG => 0
        ];

        foreach ($results as $result) {
            $counts[$result['type']] = (int)$result['count'];
        }

        return $counts;
    }

    /**
     * Limpiar logs antiguos (GDPR compliance)
     *
     * Elimina logs más antiguos de MAX_LOG_AGE_DAYS
     */
    private static function cleanupOldLogs()
    {
        // Limpiar por antigüedad
        $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'enviamo_logs`
                WHERE `created_at` < DATE_SUB(NOW(), INTERVAL ' . self::MAX_LOG_AGE_DAYS . ' DAY)';

        Db::getInstance()->execute($sql);

        // Limpiar por cantidad (mantener solo MAX_LOGS más recientes)
        $count_sql = 'SELECT COUNT(*) as total FROM `' . _DB_PREFIX_ . 'enviamo_logs`';
        $count_result = Db::getInstance()->getRow($count_sql);

        if ($count_result && $count_result['total'] > self::MAX_LOGS) {
            $offset = self::MAX_LOGS;
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'enviamo_logs`
                    WHERE `id_log` NOT IN (
                        SELECT `id_log` FROM (
                            SELECT `id_log` FROM `' . _DB_PREFIX_ . 'enviamo_logs`
                            ORDER BY `created_at` DESC
                            LIMIT ' . $offset . '
                        ) as keep_logs
                    )';

            Db::getInstance()->execute($sql);
        }
    }

    /**
     * Sanitizar contexto para cumplir con GDPR
     *
     * Elimina o enmasca datos personales sensibles
     *
     * @param array $context Contexto original
     * @return array Contexto sanitizado
     */
    private static function sanitizeContext($context)
    {
        if (!is_array($context)) {
            return [];
        }

        $sensitive_keys = [
            'password',
            'api_key',
            'secret',
            'token',
            'credit_card',
            'cvv',
            'ssn'
        ];

        $pii_keys = [
            'email',
            'phone',
            'phone_mobile',
            'dni',
            'nif',
            'passport'
        ];

        foreach ($context as $key => &$value) {
            // Sanitizar claves sensibles (ocultar completamente)
            if (in_array(strtolower($key), $sensitive_keys)) {
                $value = '***REDACTED***';
                continue;
            }

            // Enmascarar PII (mostrar solo primeros/últimos caracteres)
            if (in_array(strtolower($key), $pii_keys) && is_string($value)) {
                $value = self::maskPII($value);
                continue;
            }

            // Sanitizar IPs (anonimizar último octeto)
            if ($key === 'ip_address' && is_string($value)) {
                $value = self::anonymizeIP($value);
                continue;
            }

            // Recursivo para arrays anidados
            if (is_array($value)) {
                $value = self::sanitizeContext($value);
            }
        }

        return $context;
    }

    /**
     * Enmascarar datos personales (PII)
     *
     * @param string $value Valor a enmascarar
     * @return string Valor enmascarado
     */
    private static function maskPII($value)
    {
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // Mostrar primeros 2 y últimos 2 caracteres
        $visible_start = substr($value, 0, 2);
        $visible_end = substr($value, -2);
        $masked_middle = str_repeat('*', $length - 4);

        return $visible_start . $masked_middle . $visible_end;
    }

    /**
     * Anonimizar dirección IP (GDPR)
     *
     * @param string $ip Dirección IP
     * @return string IP anonimizada
     */
    private static function anonymizeIP($ip)
    {
        // IPv4: Reemplazar último octeto con 0
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        // IPv6: Reemplazar últimos 80 bits con ceros
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            for ($i = 4; $i < 8; $i++) {
                $parts[$i] = '0';
            }
            return implode(':', $parts);
        }

        return 'INVALID_IP';
    }

    /**
     * Fallback: Guardar en archivo si BD falla
     *
     * @param string $level Nivel
     * @param string $message Mensaje
     * @param array $context Contexto
     * @param string $db_error Error de BD
     * @return bool
     */
    private static function logToFile($level, $message, $context, $db_error)
    {
        $log_dir = _PS_MODULE_DIR_ . 'enviamo_connector/logs/';

        // Crear directorio si no existe
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . 'enviamo-' . date('Y-m-d') . '.log';

        $log_entry = sprintf(
            "[%s] [%s] %s | Context: %s | DB Error: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            json_encode($context),
            $db_error
        );

        return @file_put_contents($log_file, $log_entry, FILE_APPEND) !== false;
    }

    /**
     * Verificar si el nivel es válido
     *
     * @param string $level Nivel a verificar
     * @return bool
     */
    private static function isValidLevel($level)
    {
        return in_array($level, [
            self::LEVEL_ERROR,
            self::LEVEL_WARNING,
            self::LEVEL_INFO,
            self::LEVEL_SUCCESS,
            self::LEVEL_DEBUG
        ]);
    }

    /**
     * Exportar logs para auditoría
     *
     * @param int $days Días de logs a exportar
     * @return string CSV content
     */
    public static function exportLogsCSV($days = 30)
    {
        $logs = self::getRecentLogs(10000, null, $days);

        $csv = "Fecha,Nivel,Mensaje,Contexto\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                '"%s","%s","%s","%s"' . "\n",
                $log['created_at'],
                $log['type'],
                str_replace('"', '""', $log['message']),
                str_replace('"', '""', json_encode($log['context']))
            );
        }

        return $csv;
    }

    /**
     * Limpiar TODOS los logs (usar con precaución)
     *
     * @return bool
     */
    public static function clearAllLogs()
    {
        $sql = 'TRUNCATE TABLE `' . _DB_PREFIX_ . 'enviamo_logs`';
        return Db::getInstance()->execute($sql);
    }
}
