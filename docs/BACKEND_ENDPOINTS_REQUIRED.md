# 🔌 Endpoints Backend Requeridos para Módulo PrestaShop

## 📋 Estado Actual

✅ **Módulo PrestaShop**: 100% completo y funcional
⚠️ **Backend Django**: Endpoints pendientes de implementación

Este documento detalla los endpoints que necesita el módulo PrestaShop para funcionar completamente.

---

## 🎯 Endpoints Prioritarios (Orden de Implementación)

### 1. OAuth - Conexión en 1 Click

#### **GET `/oauth/prestashop/authorize`**

**Descripción**: Página de autorización OAuth donde el usuario confirma conectar su tienda PrestaShop.

**Query Parameters**:
```json
{
  "shop_url": "https://mitienda.com",
  "shop_name": "Mi Tienda PrestaShop",
  "prestashop_version": "8.1.0",
  "module_version": "1.0.0",
  "return_url": "https://mitienda.com/admin/modules?configure=enviamo_connector&callback=1"
}
```

**Flujo**:
1. Usuario hace clic en "Conectar en 1 Click" desde PrestaShop
2. Se abre esta página en Enviamo Dashboard
3. Si el usuario está logueado en Enviamo → mostrar confirmación
4. Si NO está logueado → redirigir a login con `?next=/oauth/prestashop/authorize?...`

**Response (HTML)**:
- Página web con formulario de confirmación
- Botón "Conectar Tienda PrestaShop"
- Info de la tienda a conectar
- Botón cancelar

**Template sugerido**: `templates/oauth/prestashop_authorize.html`

---

#### **POST `/oauth/prestashop/confirm`**

**Descripción**: Procesa la confirmación del usuario y crea la conexión.

**Request Body**:
```json
{
  "shop_url": "https://mitienda.com",
  "shop_name": "Mi Tienda PrestaShop",
  "prestashop_version": "8.1.0",
  "module_version": "1.0.0",
  "return_url": "https://mitienda.com/admin/modules?configure=enviamo_connector&callback=1"
}
```

**Proceso**:
1. Verificar que el usuario está autenticado
2. Crear `MarketplaceClientService` para PrestaShop
3. Generar `webhook_secret` único (UUID o random string)
4. Generar `store_id` único
5. Guardar configuración en BD
6. Generar token temporal (válido 5 minutos)
7. Redirigir a `return_url` con token y store_id

**Response (Redirect)**:
```
Location: {return_url}&status=success&token={temp_token}&store_id={store_id}
```

**Modelo a crear**:
```python
MarketplaceClientService.objects.create(
    user=request.user,
    marketplace_type='prestashop',
    shop_url=shop_url,
    shop_name=shop_name,
    webhook_secret=webhook_secret,
    store_id=store_id,
    is_active=True,
    metadata={
        'prestashop_version': prestashop_version,
        'module_version': module_version
    }
)
```

---

#### **POST `/oauth/prestashop/exchange`**

**Descripción**: Intercambia el token temporal por el webhook_secret permanente.

**Request Body**:
```json
{
  "token": "temp_token_12345"
}
```

**Proceso**:
1. Validar que el token existe y no ha expirado (5 min)
2. Buscar el `MarketplaceClientService` asociado
3. Devolver `webhook_secret` y `store_id`
4. Invalidar el token (single-use)

**Response**:
```json
{
  "success": true,
  "webhook_secret": "whs_abc123...",
  "store_id": "ps_store_456"
}
```

**Seguridad**:
- Token válido solo 5 minutos
- Single-use (se invalida tras el intercambio)
- Rate limiting: 5 intentos/minuto por IP

---

### 2. API Key - Conexión Manual

#### **POST `/api/v1/marketplaces/prestashop/connect`**

**Descripción**: Conexión manual con API Key (fallback si OAuth falla).

**Headers**:
```
Authorization: Bearer env_live_abc123...
Content-Type: application/json
```

**Request Body**:
```json
{
  "shop_url": "https://mitienda.com",
  "shop_name": "Mi Tienda PrestaShop",
  "prestashop_version": "8.1.0",
  "module_version": "1.0.0",
  "webhook_url": "https://mitienda.com/module/enviamo_connector/webhook"
}
```

**Proceso**:
1. Validar API Key (bearer token)
2. Crear `MarketplaceClientService` igual que en OAuth
3. Generar `webhook_secret` y `store_id`
4. Guardar en BD
5. Devolver credenciales

**Response**:
```json
{
  "success": true,
  "store_id": "ps_store_789",
  "webhook_secret": "whs_xyz789...",
  "message": "Tienda conectada correctamente"
}
```

**Errores**:
```json
{
  "success": false,
  "error": "Invalid API Key"
}
```

---

### 3. Webhooks - Recepción de Pedidos

#### **POST `/api/v1/webhooks/prestashop/{store_id}`**

**Descripción**: Recibe webhooks de eventos desde la tienda PrestaShop.

**Headers**:
```
Content-Type: application/json
X-Enviamo-Store-ID: ps_store_456
X-Enviamo-Signature: abc123...  # HMAC-SHA256 del payload
```

**Request Body (order.created)**:
```json
{
  "event": "order.created",
  "order_id": 12345,
  "order_reference": "XKBKNABJK",
  "created_at": "2025-01-15T10:30:00Z",
  "customer": {
    "id": 789,
    "email": "cliente@email.com",
    "firstname": "Juan",
    "lastname": "Pérez"
  },
  "shipping_address": {
    "firstname": "Juan",
    "lastname": "Pérez",
    "address1": "Calle Mayor 123",
    "postcode": "28001",
    "city": "Madrid",
    "country_iso": "ES",
    "phone": "612345678"
  },
  "products": [
    {
      "id": 456,
      "name": "Producto 1",
      "quantity": 2,
      "price": 29.99,
      "weight": 0.5
    }
  ],
  "total_paid": 59.98,
  "total_weight": 1.0,
  "carrier": {
    "name": "SEUR"
  }
}
```

**Proceso**:
1. **Validar firma HMAC-SHA256**:
   ```python
   expected_signature = hmac.new(
       webhook_secret.encode(),
       request.body,
       hashlib.sha256
   ).hexdigest()

   if not hmac.compare_digest(expected_signature, signature):
       return 401
   ```

2. **Buscar MarketplaceClientService** por `store_id`

3. **Procesar según evento**:
   - `order.created` → Crear `Order` en Enviamo
   - `order.status_changed` → Actualizar estado de `Order`

4. **Crear Order en sistema Enviamo**:
   ```python
   order = Order.objects.create(
       user=marketplace_client.user,
       marketplace_type='prestashop',
       marketplace_order_id=order_data['order_id'],
       reference=order_data['order_reference'],
       customer_name=f"{customer['firstname']} {customer['lastname']}",
       customer_email=customer['email'],
       shipping_address=shipping_address,
       total_amount=order_data['total_paid'],
       # ... más campos
   )
   ```

**Response**:
```json
{
  "success": true,
  "order_id": 456,  # ID interno de Enviamo
  "message": "Pedido procesado correctamente"
}
```

**Errores**:
```json
{
  "success": false,
  "error": "Invalid signature",
  "status": 401
}
```

---

### 4. Webhooks - Envío a PrestaShop

Estos webhooks se envían DESDE Enviamo HACIA PrestaShop cuando hay eventos en el sistema de envíos.

#### **Eventos a Enviar**:

**1. shipment.label_created**
```json
{
  "event": "shipment.label_created",
  "data": {
    "order_reference": "XKBKNABJK",
    "tracking_number": "123456789012",
    "carrier": "SEUR",
    "label_url": "https://backend.parafarmaciaintegrativa.es/media/labels/label_123.pdf"
  }
}
```

**2. shipment.tracking_updated**
```json
{
  "event": "shipment.tracking_updated",
  "data": {
    "order_reference": "XKBKNABJK",
    "tracking_info": {
      "status": "in_transit",
      "location": "Centro de distribución Madrid",
      "timestamp": "2025-01-15T14:30:00Z"
    }
  }
}
```

**3. shipment.delivered**
```json
{
  "event": "shipment.delivered",
  "data": {
    "order_reference": "XKBKNABJK",
    "delivered_at": "2025-01-16T12:00:00Z"
  }
}
```

**4. shipment.error**
```json
{
  "event": "shipment.error",
  "data": {
    "order_reference": "XKBKNABJK",
    "error": "Código postal inválido"
  }
}
```

**Implementación en Django**:
```python
# services/marketplaces/webhooks/sender.py

def send_webhook_to_prestashop(marketplace_client, event, data):
    """
    Envía webhook a tienda PrestaShop.
    """
    webhook_url = f"{marketplace_client.shop_url}/module/enviamo_connector/webhook"

    payload = {
        "event": event,
        "data": data
    }

    # Generar firma HMAC-SHA256
    signature = hmac.new(
        marketplace_client.webhook_secret.encode(),
        json.dumps(payload).encode(),
        hashlib.sha256
    ).hexdigest()

    headers = {
        "Content-Type": "application/json",
        "X-Enviamo-Signature": signature
    }

    # Retry logic (3 intentos, backoff exponencial)
    for attempt in range(3):
        try:
            response = requests.post(
                webhook_url,
                json=payload,
                headers=headers,
                timeout=30
            )

            if response.status_code == 200:
                logger.info(f"Webhook sent successfully: {event}")
                return True

        except Exception as e:
            logger.error(f"Webhook failed (attempt {attempt+1}): {e}")
            time.sleep(2 ** attempt)  # Backoff exponencial

    return False
```

---

### 5. Auto-Update System

#### **GET `/api/v1/modules/prestashop/latest`**

**Descripción**: Verifica si hay actualizaciones del módulo disponibles.

**Query Parameters**:
```
?current_version=1.0.0
```

**Response (actualización disponible)**:
```json
{
  "update_available": true,
  "latest_version": "1.1.0",
  "download_url": "https://github.com/enviamo/prestashop-module/releases/download/v1.1.0/enviamo-connector-1.1.0.zip",
  "checksum_sha256": "abc123...",
  "changelog": "## v1.1.0\n- Mejora en rendimiento\n- Corrección de bugs\n",
  "release_date": "2025-01-20T10:00:00Z"
}
```

**Response (sin actualizaciones)**:
```json
{
  "update_available": false,
  "current_version": "1.0.0"
}
```

---

#### **POST `/api/v1/modules/prestashop/new-release`**

**Descripción**: Webhook desde GitHub Actions cuando hay una nueva release.

**Headers**:
```
Authorization: Bearer {ENVIAMO_API_KEY}
Content-Type: application/json
```

**Request Body**:
```json
{
  "version": "1.1.0",
  "download_url": "https://github.com/enviamo/prestashop-module/releases/download/v1.1.0/enviamo-connector-1.1.0.zip",
  "checksum_sha256": "abc123...",
  "changelog": "## Changelog\n...",
  "release_date": "2025-01-20T10:00:00Z"
}
```

**Proceso**:
1. Validar API Key
2. Guardar en modelo `ModuleVersion` o tabla similar
3. Notificar a usuarios con PrestaShop instalado (opcional)

**Response**:
```json
{
  "success": true,
  "message": "Nueva versión registrada correctamente"
}
```

---

## 📊 Modelos Django Sugeridos

### **MarketplaceClientService** (Existente - Extender)

```python
class MarketplaceClientService(models.Model):
    user = models.ForeignKey(User, on_delete=models.CASCADE)
    marketplace_type = models.CharField(max_length=50)  # 'prestashop'
    shop_url = models.URLField()
    shop_name = models.CharField(max_length=255)
    store_id = models.CharField(max_length=100, unique=True)
    webhook_secret = models.CharField(max_length=255)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    metadata = models.JSONField(default=dict)  # prestashop_version, module_version, etc.

    class Meta:
        indexes = [
            models.Index(fields=['store_id']),
            models.Index(fields=['user', 'marketplace_type']),
        ]
```

### **OAuthToken** (Nuevo)

```python
class OAuthToken(models.Model):
    token = models.CharField(max_length=255, unique=True, db_index=True)
    marketplace_client = models.ForeignKey(MarketplaceClientService, on_delete=models.CASCADE)
    expires_at = models.DateTimeField()
    used = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)

    def is_valid(self):
        return not self.used and timezone.now() < self.expires_at
```

### **ModuleVersion** (Nuevo)

```python
class ModuleVersion(models.Model):
    module_name = models.CharField(max_length=100)  # 'prestashop', 'magento', etc.
    version = models.CharField(max_length=50)
    download_url = models.URLField()
    checksum_sha256 = models.CharField(max_length=64)
    changelog = models.TextField()
    release_date = models.DateTimeField()
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        unique_together = ('module_name', 'version')
        ordering = ['-release_date']
```

---

## 🔒 Seguridad Implementada

### Webhook Signature Validation

**Algoritmo**: HMAC-SHA256

**Python (Backend)**:
```python
import hmac
import hashlib

def validate_webhook_signature(payload, signature, secret):
    expected_signature = hmac.new(
        secret.encode('utf-8'),
        payload.encode('utf-8') if isinstance(payload, str) else payload,
        hashlib.sha256
    ).hexdigest()

    return hmac.compare_digest(expected_signature, signature)
```

**PHP (PrestaShop Module)**:
```php
function generateSignature($payload, $secret) {
    return hash_hmac('sha256', $payload, $secret);
}

function validateWebhookSignature($payload, $signature, $secret) {
    $expected_signature = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected_signature, $signature);
}
```

### Rate Limiting

- OAuth endpoints: 10 req/min por IP
- API Key connection: 5 req/min por IP
- Webhook endpoints: 100 req/min por store_id
- Update check: 1 req/hora por store_id

### HTTPS Enforcement

Todos los endpoints deben rechazar conexiones HTTP:

```python
from django.utils.decorators import method_decorator
from django.views.decorators.csrf import csrf_exempt

@method_decorator(csrf_exempt, name='dispatch')
class PrestaShopWebhookView(View):
    def dispatch(self, request, *args, **kwargs):
        if not request.is_secure() and not settings.DEBUG:
            return JsonResponse({
                'error': 'HTTPS required'
            }, status=403)

        return super().dispatch(request, *args, **kwargs)
```

---

## 📝 Logging GDPR-Compliant

Todos los endpoints deben loggear eventos usando el sistema GDPR:

```python
import logging

audit_logger = logging.getLogger('audit')

# Log de conexión OAuth
audit_logger.info('PrestaShop store connected via OAuth', extra={
    'user_id': user.id,
    'shop_url': shop_url,
    'marketplace_type': 'prestashop',
    'action_type': 'MARKETPLACE_CONNECTED'
})

# Log de webhook recibido
audit_logger.info('Webhook received from PrestaShop', extra={
    'store_id': store_id,
    'event': event_type,
    'order_id': order_id
})
```

---

## ✅ Checklist de Implementación

### Endpoints OAuth:
- [ ] `GET /oauth/prestashop/authorize`
- [ ] `POST /oauth/prestashop/confirm`
- [ ] `POST /oauth/prestashop/exchange`

### Endpoints API Key:
- [ ] `POST /api/v1/marketplaces/prestashop/connect`

### Endpoints Webhooks:
- [ ] `POST /api/v1/webhooks/prestashop/{store_id}`
- [ ] Funciones para enviar webhooks A PrestaShop

### Endpoints Auto-Update:
- [ ] `GET /api/v1/modules/prestashop/latest`
- [ ] `POST /api/v1/modules/prestashop/new-release`

### Modelos:
- [ ] Extender `MarketplaceClientService` para PrestaShop
- [ ] Crear `OAuthToken` model
- [ ] Crear `ModuleVersion` model

### Seguridad:
- [ ] Validación de firma HMAC-SHA256
- [ ] Rate limiting en todos los endpoints
- [ ] HTTPS enforcement
- [ ] Logging GDPR-compliant

### Tests:
- [ ] Tests unitarios para cada endpoint
- [ ] Tests de integración end-to-end
- [ ] Tests de seguridad (firma inválida, etc.)

---

## 🎯 Orden de Implementación Recomendado

1. **Fase 1**: Modelos y Base de Datos
   - Extender `MarketplaceClientService`
   - Crear `OAuthToken` y `ModuleVersion` models
   - Migraciones

2. **Fase 2**: OAuth (Conexión 1-Click)
   - `/oauth/prestashop/authorize` (template HTML)
   - `/oauth/prestashop/confirm`
   - `/oauth/prestashop/exchange`

3. **Fase 3**: API Key (Conexión Manual)
   - `/api/v1/marketplaces/prestashop/connect`

4. **Fase 4**: Webhooks Entrantes
   - `/api/v1/webhooks/prestashop/{store_id}`
   - Procesamiento de `order.created`
   - Procesamiento de `order.status_changed`

5. **Fase 5**: Webhooks Salientes
   - Funciones para enviar eventos a PrestaShop
   - Integración con sistema de envíos existente

6. **Fase 6**: Auto-Update
   - `/api/v1/modules/prestashop/latest`
   - `/api/v1/modules/prestashop/new-release`

7. **Fase 7**: Tests y Documentación
   - Tests unitarios e integración
   - Documentación API
   - Postman collection

---

## 📞 Referencias

- **Módulo PrestaShop**: [c:\Users\Benja\Desktop\shippynet\modules\prestashop\](c:\Users\Benja\Desktop\shippynet\modules\prestashop\)
- **Backend Django**: [c:\Users\Benja\Desktop\shippynet\enviamo_backend\](c:\Users\Benja\Desktop\shippynet\enviamo_backend\)
- **Sistema de Logs GDPR**: `core/logging/`
- **Webhooks Existentes**: `services/marketplaces/webhooks/`

---

<div align="center">
  <strong>📋 Documentación completa de endpoints requeridos</strong><br>
  <small>Listo para implementación en Django backend</small>
</div>
