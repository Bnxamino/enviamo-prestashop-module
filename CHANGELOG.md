# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-15

### Añadido
- ✨ Conexión OAuth en 1-click con Enviamo
- ✨ Conexión manual con API Key (fallback)
- 📦 Sincronización automática de pedidos vía webhooks
- 🔄 Webhooks bidireccionales (PrestaShop ↔ Enviamo)
- 🏷️ Soporte para generación automática de etiquetas
- 📊 Panel de estado y logs en admin de PrestaShop
- 🔐 Validación de webhooks con firma HMAC-SHA256
- 📝 Sistema de logging con cumplimiento GDPR
- 🚀 Auto-actualización del módulo desde GitHub
- 🌍 Soporte multi-idioma (ES, EN, FR)
- ✅ Compatible con PrestaShop 1.7.6.0+

### Seguridad
- 🔒 Comunicación HTTPS obligatoria
- 🛡️ Sanitización de datos sensibles en logs (GDPR)
- 🔑 Almacenamiento seguro de API Keys
- 🚫 Prevención de SQL Injection
- 🚫 Prevención de XSS
- 🚫 Protección contra CSRF
- 🚫 Rate limiting en peticiones API

### Rendimiento
- ⚡ Retry logic con backoff exponencial
- ⚡ Timeout configurables en peticiones HTTP
- ⚡ Limpieza automática de logs antiguos
- ⚡ Webhooks asíncronos para no bloquear PrestaShop
- ⚡ Caché de configuración

---

## [Unreleased]

### Planeado para v1.1.0
- 📦 Sincronización bidireccional de productos
- 📊 Dashboard con estadísticas de envíos
- 🔔 Notificaciones push en tiempo real
- 🎨 Personalización de estados de pedido mapeados
- 📧 Templates de email personalizables
- 🌐 Soporte para más idiomas (DE, IT, PT)

---

## Notas de Versión

### Compatibilidad
- **PrestaShop**: 1.7.6.0 - 9.x.x
- **PHP**: 7.1 - 8.2
- **MySQL**: 5.6+
- **SSL**: Requerido para webhooks

### Migración desde versiones anteriores
No aplicable para v1.0.0 (primera versión)

---

## Soporte

¿Encontraste un bug? [Repórtalo aquí](https://github.com/enviamo/prestashop-module/issues)

¿Tienes una sugerencia? [Cuéntanos](https://github.com/enviamo/prestashop-module/discussions)
