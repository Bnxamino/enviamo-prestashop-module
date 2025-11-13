#!/bin/bash
# Script para configurar GitHub remote y subir código
# Ejecutar después de crear el repositorio en GitHub

set -e  # Salir si hay error

echo "=========================================="
echo "📦 Setup GitHub para Enviamo PrestaShop Module"
echo "=========================================="
echo ""

# Verificar que estamos en el directorio correcto
if [ ! -f "enviamo_connector/enviamo_connector.php" ]; then
    echo "❌ Error: Ejecutar desde el directorio modules/prestashop/"
    exit 1
fi

echo "✅ Directorio verificado"
echo ""

# Configurar remote (CAMBIAR POR TU USUARIO DE GITHUB)
GITHUB_USER="enviamo"  # O tu usuario personal
REPO_NAME="prestashop-module"

echo "🔗 Configurando remote origin..."
git remote add origin https://github.com/$GITHUB_USER/$REPO_NAME.git || echo "Remote ya existe"
git remote -v
echo ""

# Verificar branch actual
CURRENT_BRANCH=$(git branch --show-current)
echo "📍 Branch actual: $CURRENT_BRANCH"
echo ""

# Push inicial
echo "📤 Subiendo código a GitHub..."
git push -u origin $CURRENT_BRANCH

echo ""
echo "=========================================="
echo "✅ ¡Código subido correctamente!"
echo "=========================================="
echo ""
echo "🎯 Próximos pasos:"
echo ""
echo "1. Crear tag para primera release:"
echo "   git tag -a v1.0.0 -m \"Release v1.0.0 - Initial release\""
echo ""
echo "2. Push tag (esto activa GitHub Actions):"
echo "   git push origin v1.0.0"
echo ""
echo "3. GitHub Actions automáticamente:"
echo "   ✅ Crea release"
echo "   ✅ Genera ZIP del módulo"
echo "   ✅ Calcula SHA256 checksum"
echo "   ✅ Notifica al backend de Enviamo"
echo ""
echo "🌐 Repositorio: https://github.com/$GITHUB_USER/$REPO_NAME"
echo ""
