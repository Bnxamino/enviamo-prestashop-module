# 📦 Enviamo Connector para PrestaShop

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/enviamo/prestashop-module/releases)
[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7.6.0+-orange.svg)](https://www.prestashop.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.1+-purple.svg)](https://www.php.net/)

> Módulo oficial de **Enviamo** para PrestaShop. Sincroniza automáticamente tus pedidos y genera etiquetas de envío sin salir de tu tienda.

---

## 🚀 Características

- ✅ **Conexión en 1 Click** - OAuth automático con tu cuenta Enviamo
- 📦 **Sincronización Automática** - Webhooks en tiempo real para nuevos pedidos
- 🏷️ **Etiquetas Instantáneas** - Genera etiquetas de SEUR, GLS, Nacex y más
- 🔄 **Estados Bidireccionales** - Actualización automática de estados de envío
- 🚚 **Multi-Transportista** - Soporta todos los transportistas de Enviamo
- 📊 **Sin Configuración** - Todo se gestiona desde el panel de Enviamo
- 🔐 **Seguro** - Comunicación encriptada y validación de webhooks
- 🌍 **Multi-Idioma** - Español, Inglés, Francés

---

## 📋 Requisitos

- **PrestaShop**: 1.7.6.0 o superior
- **PHP**: 7.1 o superior
- **Cuenta Enviamo**: [Regístrate gratis](https://enviamo.es/registro)
- **SSL**: Certificado SSL activo en tu tienda (recomendado)

---

## 📥 Instalación

### Opción 1: Instalación desde GitHub (Recomendada)

1. **Descarga el módulo**:
   - Ve a [Releases](https://github.com/enviamo/prestashop-module/releases/latest)
   - Descarga el archivo `enviamo-connector-X.X.X.zip`

2. **Instala en PrestaShop**:
   - Ve a `Módulos > Gestor de Módulos` en tu admin
   - Haz clic en "Subir un módulo"
   - Arrastra el archivo ZIP descargado
   - Haz clic en "Instalar"

3. **Conecta con Enviamo**:
   - Haz clic en "Configurar" en el módulo instalado
   - Haz clic en "🚀 Conectar con Enviamo en 1 Click"
   - Autoriza la conexión en la pantalla de Enviamo
   - ¡Listo! Tu tienda ya está conectada

### Opción 2: Instalación Manual con API Key

Si prefieres usar una API Key manualmente:

1. Instala el módulo (pasos 1-2 de arriba)
2. Ve a [Enviamo Dashboard > API Keys](https://enviamo.es/dashboard/api-keys)
3. Genera una nueva API Key
4. En PrestaShop, haz clic en "Conexión Manual con API Key"
5. Pega tu API Key y conecta

---

## ⚙️ Configuración

**¡No necesitas configurar nada en el módulo!** 🎉

Toda la configuración se hace desde tu panel de Enviamo:

1. Ve a [Enviamo Dashboard > Tiendas](https://enviamo.es/dashboard/stores)
2. Selecciona tu tienda PrestaShop
3. Configura:
   - ✅ Sincronización automática de pedidos
   - 🏷️ Generación automática de etiquetas
   - 🚚 Transportista por defecto
   - 📊 Mapeo de estados de pedido
   - 📧 Notificaciones por email

---

## 🔄 Cómo Funciona

### Flujo Automático de Pedidos

```
1. 🛒 Cliente hace un pedido en PrestaShop
   ↓
2. 📡 Webhook envía datos a Enviamo (tiempo real)
   ↓
3. 📦 Enviamo crea el envío automáticamente
   ↓
4. 🏷️ Se genera la etiqueta del transportista
   ↓
5. 📧 Cliente recibe email con tracking
   ↓
6. 🔄 Estado actualizado en PrestaShop
```

### Webhooks Soportados

- `order.created` - Nuevo pedido creado
- `order.updated` - Pedido actualizado
- `order.deleted` - Pedido eliminado
- `order.status_changed` - Cambio de estado

---

## 🔐 Seguridad

- 🔒 **Comunicación HTTPS** - Todas las peticiones encriptadas
- 🔑 **Validación de Webhooks** - Firma SHA256 en cada webhook
- 🛡️ **API Key Segura** - Nunca se expone en el código
- 📝 **Logs de Auditoría** - Registro completo de operaciones

---

## 🆘 Solución de Problemas

### No puedo conectar con Enviamo

- ✅ Verifica que tu tienda tenga SSL activo
- ✅ Comprueba que no haya firewalls bloqueando
- ✅ Revisa los logs en `Módulos > Enviamo Connector > Logs`

### Los pedidos no se sincronizan

- ✅ Verifica la configuración de webhooks en Enviamo
- ✅ Comprueba que los estados mapeados sean correctos
- ✅ Revisa los logs del módulo

### Las etiquetas no se generan

- ✅ Verifica que tengas crédito en tu cuenta Enviamo
- ✅ Comprueba que el transportista esté activo
- ✅ Revisa la dirección de envío del pedido

---

## 📚 Documentación Completa

- [Guía de Instalación](docs/installation.md)
- [Configuración Avanzada](docs/configuration.md)
- [API Reference](docs/api-reference.md)
- [Resolución de Problemas](docs/troubleshooting.md)
- [Developer Guide](docs/developer-guide.md)

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Si encuentras un bug o quieres añadir una funcionalidad:

1. Fork este repositorio
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`
3. Commit tus cambios: `git commit -m 'feat: añadir nueva funcionalidad'`
4. Push a la rama: `git push origin feature/nueva-funcionalidad`
5. Abre un Pull Request

---

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de cambios.

---

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver [LICENSE](LICENSE) para más detalles.

---

## 💬 Soporte

- 📧 **Email**: [soporte@enviamo.es](mailto:soporte@enviamo.es)
- 💬 **Chat en vivo**: [enviamo.es](https://enviamo.es)
- 🐛 **Reportar bug**: [GitHub Issues](https://github.com/enviamo/prestashop-module/issues)
- 📖 **Documentación**: [docs.enviamo.es](https://docs.enviamo.es)

---

## 🌟 ¿Te gusta Enviamo?

¡Dale una estrella ⭐ al repositorio y compártelo con otros!

---

<div align="center">
  <strong>Hecho con ❤️ por <a href="https://enviamo.es">Enviamo</a></strong>
</div>
